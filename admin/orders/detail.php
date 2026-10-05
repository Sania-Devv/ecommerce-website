<?php

$pageTitle = "Order Details";

require_once __DIR__ . '/../../core/Sessions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../config/smtp.php';
require_once __DIR__ . '/../../config/stripe.php';

$auth = new Auth($mysqli);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: ../login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Message Variables
|--------------------------------------------------------------------------
*/

$errorMessage = '';
$successMessage = '';


/*
|--------------------------------------------------------------------------
| Update Order Status
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_status'])
) {

    $orderId = isset($_POST['order_id'])
        ? (int) $_POST['order_id']
        : 0;

    $newStatus = $_POST['order_status'] ?? '';

    $allowedStatuses = [
        'processing',
        'shipped',
        'delivered',
        'cancelled'
    ];

    if ($orderId <= 0) {

        header(
            'Location: detail.php?error='
            . urlencode('Invalid Order ID.')
        );

        exit;
    }

    if (!in_array($newStatus, $allowedStatuses, true)) {

        header(
            'Location: detail.php?id='
            . $orderId
            . '&error='
            . urlencode('Invalid Order Status.')
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Start Transaction
    |--------------------------------------------------------------------------
    */

    $mysqli->begin_transaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | Get Current Order + Customer Information
        |--------------------------------------------------------------------------
        */

        $currentStmt = $mysqli->prepare("
            SELECT
                o.order_status,
                o.payment_method,
                o.payment_status,
                o.transaction_id,
                o.order_number,
                u.name AS customer_name,
                u.email AS customer_email
            FROM orders o
            INNER JOIN users u
                ON o.user_id = u.id
            WHERE o.id = ?
            FOR UPDATE
        ");

        if (!$currentStmt) {

            throw new Exception(
                "Unable to prepare order query."
            );
        }

        $currentStmt->bind_param(
            "i",
            $orderId
        );

        if (!$currentStmt->execute()) {

            throw new Exception(
                "Unable to load order information."
            );
        }

        $currentResult = $currentStmt->get_result();

        if ($currentResult->num_rows !== 1) {

            throw new Exception(
                "Order not found."
            );
        }

        $currentOrder = $currentResult->fetch_assoc();

        $currentStatus = $currentOrder['order_status'];

        $paymentMethod = strtolower(
            $currentOrder['payment_method']
        );

        $currentPaymentStatus =
            $currentOrder['payment_status'];

        $transactionId =
            $currentOrder['transaction_id'];

        $orderNumber =
            $currentOrder['order_number'];

        $customerName =
            $currentOrder['customer_name'];

        $customerEmail =
            $currentOrder['customer_email'];

        $currentStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Forward-Only Order Status Transitions
        |--------------------------------------------------------------------------
        */

        $allowedTransitions = [

            'processing' => [
                'processing',
                'shipped',
                'cancelled'
            ],

            'shipped' => [
                'shipped',
                'delivered'
            ],

            'delivered' => [
                'delivered'
            ],

            'cancelled' => [
                'cancelled'
            ],

        ];


        $validNextStatuses =
            $allowedTransitions[$currentStatus] ?? [];


        if (
            !in_array(
                $newStatus,
                $validNextStatuses,
                true
            )
        ) {

            throw new Exception(
                'Order cannot move from "'
                . ucfirst($currentStatus)
                . '" to "'
                . ucfirst($newStatus)
                . '".'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Payment / Order Status Validation
        |--------------------------------------------------------------------------
        |
        | COD:
        | processing -> pending
        | shipped    -> pending
        | delivered  -> completed
        |
        | Stripe:
        | processing -> completed
        | shipped    -> completed
        | delivered  -> completed
        |
        */

        if (
            $newStatus === 'delivered'
            && $paymentMethod === 'cod'
            && $currentPaymentStatus !== 'completed'
        ) {

            throw new Exception(
                'COD order cannot be delivered until payment is completed.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Cancelled Order
        |--------------------------------------------------------------------------
        |
        | Cancelled orders cannot be delivered.
        |
        */

        if (
            $newStatus === 'cancelled'
            && $currentStatus !== 'cancelled'
        ) {

            /*
            |--------------------------------------------------------------
            | COD cancellation
            |--------------------------------------------------------------
            */

            if ($paymentMethod === 'cod') {

                $newPaymentStatus = 'failed';

            } else {

                /*
                |----------------------------------------------------------
                | Stripe cancellation
                |----------------------------------------------------------
                |
                | If a Stripe payment was completed, refund the full
                | payment before cancelling the order.
                |
                */

                if (
                    $paymentMethod === 'stripe'
                    && $currentPaymentStatus === 'completed'
                ) {

                    if (empty($transactionId)) {
                        throw new Exception(
                            'Stripe transaction ID not found. Refund could not be processed.'
                        );
                    }

                    $stripeSession = \Stripe\Checkout\Session::retrieve([
                        'id' => $transactionId,
                        'expand' => ['payment_intent']
                    ]);

                    if ($stripeSession->payment_status !== 'paid') {
                        throw new Exception(
                            'Stripe payment is not in a refundable state.'
                        );
                    }

                    $paymentIntent = $stripeSession->payment_intent;

                    if (!$paymentIntent) {
                        throw new Exception(
                            'Stripe payment intent not found. Refund could not be processed.'
                        );
                    }

                    if (is_string($paymentIntent)) {
                        $paymentIntent =\Stripe\PaymentIntent::retrieve(
                            $paymentIntent
                        );
                    }

                    if (
                        isset($paymentIntent->status)
                        && $paymentIntent->status !== 'succeeded'
                    ) {
                        throw new Exception(
                            'Stripe payment is not in a refundable state.'
                        );
                    }

                    $refund = \Stripe\Refund::create(
                        [
                            'payment_intent' => $paymentIntent->id
                        ],
                        [
                            'idempotency_key' => 'order_refund_' . $orderId
                        ]
                    );

                    if ($refund->status !== 'succeeded') {
                        throw new Exception(
                            'Stripe refund could not be completed.'
                        );
                    }

                    // Existing database statuses are pending/completed/failed.
                    $newPaymentStatus = 'failed';

                } else {

                    // Preserve existing behavior for non-Stripe payments.
                    $newPaymentStatus =
                        $currentPaymentStatus;
                }
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | Synchronize Payment Status With Order Stage
            |--------------------------------------------------------------------------
            */

            if ($paymentMethod === 'cod') {

                if (
                    $newStatus === 'processing'
                    || $newStatus === 'shipped'
                ) {

                    $newPaymentStatus = 'pending';

                } elseif ($newStatus === 'delivered') {

                    $newPaymentStatus = 'completed';

                } else {

                    $newPaymentStatus =
                        $currentPaymentStatus;
                }

            } else {

                /*
                | Stripe payment is completed for normal order stages.
                */

                if (
                    $newStatus === 'processing'
                    || $newStatus === 'shipped'
                    || $newStatus === 'delivered'
                ) {

                    $newPaymentStatus = 'completed';

                } else {

                    $newPaymentStatus =
                        $currentPaymentStatus;
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Update Order Status + Payment Status Together
        |--------------------------------------------------------------------------
        */

        $updateStmt = $mysqli->prepare("
            UPDATE orders
            SET
                order_status = ?,
                payment_status = ?
            WHERE id = ?
        ");

        if (!$updateStmt) {

            throw new Exception(
                "Unable to prepare status update."
            );
        }

        $updateStmt->bind_param(
            "ssi",
            $newStatus,
            $newPaymentStatus,
            $orderId
        );

        if (!$updateStmt->execute()) {

            throw new Exception(
                "Order status could not be updated."
            );
        }

        $updateStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Restore Stock
        |--------------------------------------------------------------------------
        |
        | Only:
        | processing -> cancelled
        |
        */

        if (
            $currentStatus === 'processing'
            && $newStatus === 'cancelled'
        ) {

            $itemStmt = $mysqli->prepare("
                SELECT
                    product_id,
                    quantity
                FROM order_items
                WHERE order_id = ?
            ");

            if (!$itemStmt) {

                throw new Exception(
                    "Unable to prepare order items query."
                );
            }

            $itemStmt->bind_param(
                "i",
                $orderId
            );

            if (!$itemStmt->execute()) {

                throw new Exception(
                    "Unable to load order items."
                );
            }

            $itemResult =
                $itemStmt->get_result();

            $orderItems = [];

            while (
                $item =
                $itemResult->fetch_assoc()
            ) {

                $orderItems[] = $item;
            }

            $itemStmt->close();


            if (empty($orderItems)) {

                throw new Exception(
                    "No order items found."
                );
            }


            $stockStmt = $mysqli->prepare("
                UPDATE products
                SET stock = stock + ?
                WHERE id = ?
            ");

            if (!$stockStmt) {

                throw new Exception(
                    "Unable to prepare stock query."
                );
            }


            foreach ($orderItems as $item) {

                $productId =
                    (int) $item['product_id'];

                $quantity =
                    (int) $item['quantity'];

                $stockStmt->bind_param(
                    "ii",
                    $quantity,
                    $productId
                );

                if (!$stockStmt->execute()) {

                    throw new Exception(
                        "Stock could not be restored."
                    );
                }

                if (
                    $stockStmt->affected_rows !== 1
                ) {

                    throw new Exception(
                        "Product not found while restoring stock."
                    );
                }
            }

            $stockStmt->close();
        }


        /*
        |--------------------------------------------------------------------------
        | Commit Transaction
        |--------------------------------------------------------------------------
        */

        $mysqli->commit();


        /*
        |--------------------------------------------------------------------------
        | Send Status Email Only When Order Status Changes
        |--------------------------------------------------------------------------
        */

        if ($currentStatus !== $newStatus) {

            sendOrderStatusEmail(
                $customerEmail,
                $customerName,
                $orderNumber,
                $newStatus
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Success Toast
        |--------------------------------------------------------------------------
        */

        header(
            "Location: detail.php?id="
            . $orderId
            . "&success="
            . urlencode(
                'Order status updated successfully.'
            )
        );

        exit;


    } catch (Exception $e) {

        /*
        |--------------------------------------------------------------------------
        | Rollback
        |--------------------------------------------------------------------------
        */

        $mysqli->rollback();


        /*
        |--------------------------------------------------------------------------
        | Error Toast
        |--------------------------------------------------------------------------
        */

        header(
            "Location: detail.php?id="
            . $orderId
            . "&error="
            . urlencode($e->getMessage())
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Update Payment Status
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_payment_status'])
) {

    $orderId = isset($_POST['order_id'])
        ? (int) $_POST['order_id']
        : 0;

    $newPaymentStatus =
        $_POST['payment_status'] ?? '';


    $allowedPaymentStatuses = [
        'pending',
        'completed',
        'failed'
    ];


    if ($orderId <= 0) {

        header(
            'Location: detail.php?error='
            . urlencode('Invalid Order ID.')
        );

        exit;
    }


    if (
        !in_array(
            $newPaymentStatus,
            $allowedPaymentStatuses,
            true
        )
    ) {

        header(
            "Location: detail.php?id="
            . $orderId
            . "&error="
            . urlencode('Invalid Payment Status.')
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Start Transaction
    |--------------------------------------------------------------------------
    */

    $mysqli->begin_transaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | Get Current Payment + Order Status
        |--------------------------------------------------------------------------
        */

        $paymentCheckStmt =
            $mysqli->prepare("
                SELECT
                    order_status,
                    payment_method,
                    payment_status,
                    order_number,
                    u.name AS customer_name,
                    u.email AS customer_email
                FROM orders o
                INNER JOIN users u
                    ON o.user_id = u.id
                WHERE o.id = ?
                FOR UPDATE
            ");

        if (!$paymentCheckStmt) {

            throw new Exception(
                "Unable to prepare payment query."
            );
        }

        $paymentCheckStmt->bind_param(
            "i",
            $orderId
        );

        if (!$paymentCheckStmt->execute()) {

            throw new Exception(
                "Unable to load payment information."
            );
        }

        $paymentResult =
            $paymentCheckStmt->get_result();

        if ($paymentResult->num_rows !== 1) {

            throw new Exception(
                "Order not found."
            );
        }

        $paymentOrder =
            $paymentResult->fetch_assoc();

        $currentOrderStatus =
            $paymentOrder['order_status'];

        $paymentMethod =
            strtolower(
                $paymentOrder['payment_method']
            );

        $currentPaymentStatus =
            $paymentOrder['payment_status'];

        $orderNumber =
            $paymentOrder['order_number'];

        $customerName =
            $paymentOrder['customer_name'];

        $customerEmail =
            $paymentOrder['customer_email'];

        $paymentCheckStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Payment Status Is Also Forward-Only
        |--------------------------------------------------------------------------
        |
        | pending -> completed
        | pending -> failed
        |
        | completed / failed are terminal.
        |
        */

        $allowedPaymentTransitions = [

            'pending' => [
                'pending',
                'completed',
                'failed'
            ],

            'completed' => [
                'completed'
            ],

            'failed' => [
                'failed'
            ],

        ];


        $validPaymentStatuses =
            $allowedPaymentTransitions[
                $currentPaymentStatus
            ] ?? [];


        if (
            !in_array(
                $newPaymentStatus,
                $validPaymentStatuses,
                true
            )
        ) {

            throw new Exception(
                'Payment status cannot move from "'
                . ucfirst($currentPaymentStatus)
                . '" to "'
                . ucfirst($newPaymentStatus)
                . '".'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | No Reverse Order Movement
        |--------------------------------------------------------------------------
        |
        | Payment changes can only move the order forward.
        |
        */

        $newOrderStatus =
            $currentOrderStatus;


        /*
        |--------------------------------------------------------------------------
        | Payment Completed
        |--------------------------------------------------------------------------
        |
        | Pending -> Completed
        |
        | Order automatically becomes Delivered.
        |
        */

        if (
            $currentPaymentStatus === 'pending'
            && $newPaymentStatus === 'completed'
        ) {

            if (
                $currentOrderStatus === 'processing'
                || $currentOrderStatus === 'shipped'
            ) {

                $newOrderStatus = 'delivered';

            } elseif (
                $currentOrderStatus === 'delivered'
            ) {

                $newOrderStatus = 'delivered';

            } elseif (
                $currentOrderStatus === 'cancelled'
            ) {

                throw new Exception(
                    'A cancelled order cannot be marked as payment completed.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Payment Failed
        |--------------------------------------------------------------------------
        |
        | Pending -> Failed
        |
        | Order becomes Cancelled.
        |
        */

        if (
            $currentPaymentStatus === 'pending'
            && $newPaymentStatus === 'failed'
        ) {

            if (
                $currentOrderStatus === 'processing'
            ) {

                $newOrderStatus = 'cancelled';

            } elseif (
                $currentOrderStatus === 'shipped'
            ) {

                throw new Exception(
                    'A shipped order cannot be cancelled because payment failed.'
                );

            } elseif (
                $currentOrderStatus === 'delivered'
            ) {

                throw new Exception(
                    'A delivered order cannot be marked as payment failed.'
                );

            } elseif (
                $currentOrderStatus === 'cancelled'
            ) {

                $newOrderStatus = 'cancelled';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Update Payment + Order Status
        |--------------------------------------------------------------------------
        */

        $updatePaymentStmt =
            $mysqli->prepare("
                UPDATE orders
                SET
                    payment_status = ?,
                    order_status = ?
                WHERE id = ?
            ");

        if (!$updatePaymentStmt) {

            throw new Exception(
                "Unable to prepare payment update."
            );
        }

        $updatePaymentStmt->bind_param(
            "ssi",
            $newPaymentStatus,
            $newOrderStatus,
            $orderId
        );

        if (!$updatePaymentStmt->execute()) {

            throw new Exception(
                "Payment status could not be updated."
            );
        }

        $updatePaymentStmt->close();


        /*
        |--------------------------------------------------------------------------
        | Restore Stock
        |--------------------------------------------------------------------------
        |
        | Payment Pending -> Failed
        | Processing -> Cancelled
        |
        */

        if (
            $currentOrderStatus === 'processing'
            && $newOrderStatus === 'cancelled'
        ) {

            $itemStmt = $mysqli->prepare("
                SELECT
                    product_id,
                    quantity
                FROM order_items
                WHERE order_id = ?
            ");

            if (!$itemStmt) {

                throw new Exception(
                    "Unable to prepare order items query."
                );
            }

            $itemStmt->bind_param(
                "i",
                $orderId
            );

            if (!$itemStmt->execute()) {

                throw new Exception(
                    "Unable to load order items."
                );
            }

            $itemResult =
                $itemStmt->get_result();

            $orderItems = [];

            while (
                $item =
                $itemResult->fetch_assoc()
            ) {

                $orderItems[] = $item;
            }

            $itemStmt->close();


            if (empty($orderItems)) {

                throw new Exception(
                    "No order items found."
                );
            }


            $stockStmt = $mysqli->prepare("
                UPDATE products
                SET stock = stock + ?
                WHERE id = ?
            ");

            if (!$stockStmt) {

                throw new Exception(
                    "Unable to prepare stock query."
                );
            }


            foreach ($orderItems as $item) {

                $productId =
                    (int) $item['product_id'];

                $quantity =
                    (int) $item['quantity'];

                $stockStmt->bind_param(
                    "ii",
                    $quantity,
                    $productId
                );

                if (!$stockStmt->execute()) {

                    throw new Exception(
                        "Stock could not be restored."
                    );
                }

                if (
                    $stockStmt->affected_rows !== 1
                ) {

                    throw new Exception(
                        "Product not found while restoring stock."
                    );
                }
            }

            $stockStmt->close();
        }


        /*
        |--------------------------------------------------------------------------
        | Commit Payment Transaction
        |--------------------------------------------------------------------------
        */

        $mysqli->commit();


        /*
        |--------------------------------------------------------------------------
        | Send Order Email If Order Status Changed
        |--------------------------------------------------------------------------
        */

        if (
            $currentOrderStatus !==
            $newOrderStatus
        ) {

            sendOrderStatusEmail(
                $customerEmail,
                $customerName,
                $orderNumber,
                $newOrderStatus
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Success Toast
        |--------------------------------------------------------------------------
        */

        header(
            "Location: detail.php?id="
            . $orderId
            . "&success="
            . urlencode(
                'Payment status updated successfully.'
            )
        );

        exit;


    } catch (Exception $e) {

        /*
        |--------------------------------------------------------------------------
        | Rollback
        |--------------------------------------------------------------------------
        */

        $mysqli->rollback();


        /*
        |--------------------------------------------------------------------------
        | Error Toast
        |--------------------------------------------------------------------------
        */

        header(
            "Location: detail.php?id="
            . $orderId
            . "&error="
            . urlencode($e->getMessage())
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Get Order ID
|--------------------------------------------------------------------------
*/

$orderId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


$errorMessage = isset($_GET['error'])
    ? urldecode($_GET['error'])
    : '';


$successMessage = isset($_GET['success'])
    ? urldecode($_GET['success'])
    : '';


if ($orderId <= 0) {

    header('Location: index.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Order + Customer Information
|--------------------------------------------------------------------------
*/

$stmt = $mysqli->prepare(
    "SELECT
        o.id,
        o.order_number,
        o.total_amount,
        o.payment_method,
        o.payment_status,
        o.order_status,
        o.shipping_address,
        o.created_at,

        u.name AS customer_name,
        u.email AS customer_email

     FROM orders o

     INNER JOIN users u
        ON o.user_id = u.id

     WHERE o.id = ?

     LIMIT 1"
);

$stmt->bind_param(
    "i",
    $orderId
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    $stmt->close();

    header('Location: index.php');

    exit;
}

$order = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Order Variables
|--------------------------------------------------------------------------
*/

$orderNumber =
    $order['order_number'];

$customerName =
    $order['customer_name'];

$customerEmail =
    $order['customer_email'];

$orderDate = date(
    'd M Y',
    strtotime($order['created_at'])
);

$paymentStatus =
    $order['payment_status'];

$orderStatus =
    $order['order_status'];

$paymentMethod =
    $order['payment_method'];

$shippingAddress =
    $order['shipping_address'];

$grandTotal =
    (float) $order['total_amount'];


/*
|--------------------------------------------------------------------------
| Get Order Items
|--------------------------------------------------------------------------
*/

$orderItems = [];

$stmt = $mysqli->prepare(
    "SELECT
        oi.product_id,
        oi.quantity,
        oi.unit_price,
        oi.subtotal,

        p.name AS product_name,

        c.name AS category_name

     FROM order_items oi

     INNER JOIN products p
        ON oi.product_id = p.id

     INNER JOIN categories c
        ON p.category_id = c.id

     WHERE oi.order_id = ?

     ORDER BY oi.id ASC"
);

$stmt->bind_param(
    "i",
    $orderId
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $orderItems[] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Calculate Subtotal
|--------------------------------------------------------------------------
*/

$subtotal = 0;

foreach ($orderItems as $item) {

    $subtotal +=
        (float) $item['subtotal'];
}


/*
|--------------------------------------------------------------------------
| Shipping / Discount
|--------------------------------------------------------------------------
*/

$shipping = 0;

$discount = 0;


/*
|--------------------------------------------------------------------------
| Status Badge Classes
|--------------------------------------------------------------------------
*/

$orderStatusBadge = 'status-info';

if ($orderStatus === 'delivered') {

    $orderStatusBadge = 'status-success';

} elseif ($orderStatus === 'cancelled') {

    $orderStatusBadge = 'status-warning';

} elseif ($orderStatus === 'shipped') {

    $orderStatusBadge = 'status-success';

} elseif ($orderStatus === 'processing') {

    $orderStatusBadge = 'status-info';
}


$paymentStatusBadge = 'status-warning';

if ($paymentStatus === 'completed') {

    $paymentStatusBadge = 'status-success';

} elseif ($paymentStatus === 'failed') {

    $paymentStatusBadge = 'status-warning';
}


/*
|--------------------------------------------------------------------------
| Which Order Statuses Can Be Selected
|--------------------------------------------------------------------------
*/

$statusTransitions = [

    'processing' => [
        'processing',
        'shipped',
        'cancelled'
    ],

    'shipped' => [
        'shipped',
        'delivered'
    ],

    'delivered' => [
        'delivered'
    ],

    'cancelled' => [
        'cancelled'
    ],

];

$selectableStatuses =
    $statusTransitions[$orderStatus]
    ?? [$orderStatus];


/*
|--------------------------------------------------------------------------
| Payment Status Transitions
|--------------------------------------------------------------------------
*/

$paymentTransitions = [

    'pending' => [
        'pending',
        'completed',
        'failed'
    ],

    'completed' => [
        'completed'
    ],

    'failed' => [
        'failed'
    ],

];

$selectablePaymentStatuses =
    $paymentTransitions[$paymentStatus]
    ?? [$paymentStatus];

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, shrink-to-fit=no"
    >

    <link
        rel="apple-touch-icon"
        sizes="76x76"
        href="../assets/img/apple-icon.png"
    >

    <link
        rel="icon"
        type="image/png"
        href="../assets/img/favicon.png"
    >

    <title>
        Order Details - ClothWear Admin
    </title>


    <!-- Fonts -->

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900"
    >


    <!-- Nucleo Icons -->

    <link
        href="../assets/css/nucleo-icons.css"
        rel="stylesheet"
    >

    <link
        href="../assets/css/nucleo-svg.css"
        rel="stylesheet"
    >


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- Material Icons -->

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0"
    >


    <!-- Material Dashboard CSS -->

    <link
        id="pagestyle"
        href="../assets/css/material-dashboard.css?v=3.2.0"
        rel="stylesheet"
    >


    <!-- Custom Order Detail CSS -->

    <style>

        .order-card {
            border-radius: 16px;
            overflow: hidden;
        }


        .order-card-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e9ecef;
        }


        .order-card-header h5 {
            font-weight: 700;
            color: #212529;
        }


        .order-id-info {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            margin-top: 5px;

            padding: 5px 10px;

            border-radius: 6px;

            background-color: #f8f9fa;

            color: #8392ab;

            font-size: 11px;

            font-weight: 600;
        }


        .order-info-box {

            padding: 20px;

            border: 1px solid #e9ecef;

            border-radius: 12px;

            height: 100%;
        }


        .order-info-box h6 {

            font-size: 14px;

            font-weight: 700;

            color: #344767;

            margin-bottom: 15px;
        }


        .info-label {

            display: block;

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            color: #8392ab;

            margin-bottom: 4px;
        }


        .info-value {

            font-size: 14px;

            font-weight: 600;

            color: #344767;

            margin-bottom: 14px;
        }


        .shipping-address {

            white-space: pre-line;
        }


        .order-status-badge {

            display: inline-block;

            padding: 6px 12px;

            border-radius: 6px;

            font-size: 11px;

            font-weight: 700;
        }


        .status-success {

            background-color: #d1e7dd;

            color: #146c43;
        }


        .status-warning {

            background-color: #fff3cd;

            color: #997404;
        }


        .status-info {

            background-color: #cff4fc;

            color: #087990;
        }


        .order-items-table th {

            font-size: 11px;

            text-transform: uppercase;

            color: #8392ab;

            font-weight: 700;

            border-bottom: 1px solid #e9ecef;
        }


        .order-items-table td {

            padding: 16px 12px;

            vertical-align: middle;

            border-bottom: 1px solid #f0f2f5;
        }


        .product-name {

            font-size: 14px;

            font-weight: 700;

            color: #344767;

            margin-bottom: 3px;
        }


        .product-category {

            font-size: 11px;

            color: #8392ab;
        }


        .order-summary {

            margin-left: auto;

            max-width: 350px;

            padding: 20px;
        }


        .summary-row {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 12px;

            font-size: 14px;

            color: #67748e;
        }


        .summary-total {

            display: flex;

            justify-content: space-between;

            align-items: center;

            padding-top: 15px;

            margin-top: 10px;

            border-top: 1px solid #e9ecef;

            font-size: 16px;

            font-weight: 700;

            color: #212529;
        }


        .order-actions {

            display: flex;

            justify-content: flex-end;

            gap: 10px;

            padding-top: 20px;

            margin-top: 10px;

            border-top: 1px solid #e9ecef;
        }


        /* ================================================
           Status update field
           ================================================ */

        .status-update-field {
            position: relative;
        }


        .status-update-field select.form-select {
            padding: 10px 36px 10px 14px;
            border-radius: 8px;
            border: 1px solid #d2d6da;
            font-size: 13px;
            font-weight: 600;
            color: #344767;
            background-position: right 12px center;
        }


        .status-update-field select.form-select:focus {
            border-color: #3399ff;
            box-shadow: 0 0 0 2px rgba(51, 153, 255, 0.15);
        }


        .status-update-field select.form-select:disabled {
            background-color: #f8f9fa;
            cursor: not-allowed;
            opacity: 0.7;
        }


        .btn-update-status[disabled],
        .btn-update-payment[disabled] {
            opacity: 0.7;
            cursor: not-allowed;
        }


        .btn-spinner {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }


        /* ================================================
           PAGE POLISH
           ================================================ */

        .order-card {
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
            border: none;
        }


        .order-card-header {
            background: linear-gradient(
                135deg,
                #f8fafc 0%,
                #ffffff 100%
            );

            padding: 28px 32px;
        }


        .order-card-header h5 {
            font-size: 20px;
            letter-spacing: -0.2px;
        }


        .order-status-badge {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 8px 16px;

            border-radius: 30px;

            font-size: 12px;

            letter-spacing: 0.3px;
        }


        .order-status-badge i {
            font-size: 12px;
        }


        /* Icon avatars */

        .info-box-icon {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            width: 34px;

            height: 34px;

            border-radius: 10px;

            margin-right: 10px;

            font-size: 14px;
        }


        .info-box-icon.icon-customer {

            background: #e7f1ff;

            color: #3399ff;
        }


        .info-box-icon.icon-shipping {

            background: #fff4e5;

            color: #f0923b;
        }


        .info-box-icon.icon-payment {

            background: #e6f8ee;

            color: #2bb673;
        }


        .order-info-box {

            transition:
                box-shadow 0.2s ease,
                transform 0.2s ease;
        }


        .order-info-box:hover {

            box-shadow:
                0 6px 18px rgba(0, 0, 0, 0.06);

            transform: translateY(-2px);
        }


        .order-info-box h6 {

            display: flex;

            align-items: center;
        }


        /* ================================================
           ORDER STATUS STEPPER
           ================================================ */

        .order-stepper {

            display: flex;

            align-items: flex-start;

            padding: 28px 32px 8px;

            border-bottom: 1px solid #f0f2f5;

            overflow-x: auto;
        }


        .order-step {

            flex: 1;

            min-width: 110px;

            text-align: center;

            position: relative;
        }


        .order-step:not(:last-child)::after {

            content: "";

            position: absolute;

            top: 17px;

            left: 50%;

            width: 100%;

            height: 2px;

            background: #e9ecef;

            z-index: 0;
        }


        .order-step.is-complete:not(:last-child)::after {

            background: #2bb673;
        }


        .order-step-circle {

            width: 34px;

            height: 34px;

            border-radius: 50%;

            background: #e9ecef;

            color: #8392ab;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            font-size: 13px;

            font-weight: 700;

            position: relative;

            z-index: 1;

            margin-bottom: 8px;
        }


        .order-step.is-complete .order-step-circle {

            background: #2bb673;

            color: #fff;
        }


        .order-step.is-current .order-step-circle {

            background: #3399ff;

            color: #fff;

            box-shadow:
                0 0 0 4px rgba(51, 153, 255, 0.18);
        }


        .order-step.is-cancelled .order-step-circle {

            background: #e35d6a;

            color: #fff;
        }


        .order-step-label {

            display: block;

            font-size: 12px;

            font-weight: 600;

            color: #8392ab;
        }


        .order-step.is-complete .order-step-label,
        .order-step.is-current .order-step-label {

            color: #344767;
        }


        /* Order items table */

        .order-items-table tbody tr:hover {

            background-color: #f8f9fa;
        }


        .order-items-table thead th:first-child {

            padding-left: 12px;
        }


        /* Order summary */

        .order-summary {

            background: #f8f9fa;

            border-radius: 14px;

            margin-top: 10px;
        }


        .summary-total {

            color: #344767;
        }


        .summary-total strong {

            color: #2bb673;

            font-size: 18px;
        }


        .order-section-title {

            display: flex;

            align-items: center;

            gap: 8px;

            font-size: 15px;
        }


        .order-section-title i {

            color: #3399ff;
        }


        /* ================================================
           Toast
           ================================================ */

        .cw-toast {

            position: fixed;

            top: 20px;

            right: 20px;

            z-index: 99999;

            min-width: 300px;

            max-width: 420px;

            padding: 14px 18px;

            border-radius: 10px;

            color: #fff;

            display: flex;

            align-items: flex-start;

            gap: 10px;

            box-shadow:
                0 8px 25px rgba(0, 0, 0, 0.18);

            font-size: 14px;

            font-weight: 500;

            animation:
                cwToastIn 0.3s ease;
        }


        .cw-toast-success {

            background: #198754;
        }


        .cw-toast-error {

            background: #dc3545;
        }


        .cw-toast-icon {

            font-size: 17px;

            margin-top: 1px;
        }


        .cw-toast-message {

            flex: 1;

            line-height: 1.5;
        }


        .cw-toast-close {

            background: transparent;

            border: 0;

            color: #fff;

            font-size: 20px;

            line-height: 1;

            padding: 0;

            cursor: pointer;

            opacity: 0.9;
        }


        @keyframes cwToastIn {

            from {

                opacity: 0;

                transform:
                    translateY(-10px)
                    translateX(10px);
            }

            to {

                opacity: 1;

                transform:
                    translateY(0)
                    translateX(0);
            }
        }


        @media (max-width: 768px) {

            .order-card-header {
                padding: 20px;
            }


            .order-info-box {
                margin-bottom: 15px;
            }


            .order-summary {
                max-width: 100%;
            }


            .order-actions {
                flex-direction: column;
            }


            .order-actions .btn {
                width: 100%;
            }


            .cw-toast {

                top: 15px;

                right: 15px;

                left: 15px;

                min-width: 0;

                max-width: none;
            }

        }

    </style>

</head>


<body class="g-sidenav-show bg-gray-100">


    <!-- Admin Header -->

    <?php require_once '../../includes/admin-header.php'; ?>


    <!-- Admin Sidebar -->

    <?php require_once '../../includes/admin-sidenavbar.php'; ?>


    <!-- Main Content -->

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">

        <div class="container-fluid py-4">


            <!-- Page Heading -->

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">

                <div>

                    <h4 class="fw-bold text-dark mb-1">
                        Order Details
                    </h4>

                    <p class="text-sm text-secondary mb-0">
                        View complete information about this order
                    </p>

                </div>


                <a
                    href="index.php"
                    class="btn btn-outline-secondary btn-sm"
                >

                    <i class="fa-solid fa-arrow-left me-1"></i>

                    Back to Orders

                </a>

            </div>


            <!-- Order Card -->

            <div class="card order-card mb-4">


                <!-- Card Header -->

                <div class="order-card-header">

                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">

                        <div>

                            <h5 class="mb-1">

                                Order
                                <?php echo htmlspecialchars($orderNumber); ?>

                            </h5>


                            <p class="text-sm text-secondary mb-0">

                                Placed on
                                <?php echo htmlspecialchars($orderDate); ?>

                            </p>


                            <span class="order-id-info">

                                <i class="fa-solid fa-hashtag"></i>

                                Order ID:
                                <?php echo (int) $orderId; ?>

                            </span>

                        </div>


                        <!-- Order Status -->

                        <div>

                            <span class="order-status-badge <?php echo $orderStatusBadge; ?>">

                                <?php

                                $statusIcons = [

                                    'processing' =>
                                        'fa-clock',

                                    'shipped' =>
                                        'fa-truck',

                                    'delivered' =>
                                        'fa-circle-check',

                                    'cancelled' =>
                                        'fa-circle-xmark',

                                ];

                                $statusIcon =
                                    $statusIcons[$orderStatus]
                                    ?? 'fa-circle';

                                ?>

                                <i class="fa-solid <?php echo $statusIcon; ?>"></i>

                                <?php

                                echo htmlspecialchars(
                                    ucfirst($orderStatus)
                                );

                                ?>

                            </span>

                        </div>

                    </div>

                </div>


                <!-- Order Status Stepper -->

                <?php

                $stepOrder = [
                    'processing',
                    'shipped',
                    'delivered'
                ];

                $currentStepIndex =
                    array_search(
                        $orderStatus,
                        $stepOrder,
                        true
                    );

                ?>

                <div class="order-stepper">

                    <?php if ($orderStatus === 'cancelled'): ?>

                        <div class="order-step is-cancelled">

                            <span class="order-step-circle">

                                <i class="fa-solid fa-xmark"></i>

                            </span>

                            <span class="order-step-label">
                                Cancelled
                            </span>

                        </div>

                    <?php else: ?>

                        <?php foreach (
                            $stepOrder
                            as $index => $stepName
                        ): ?>

                            <?php

                            $stepClass = '';

                            if (
                                $currentStepIndex !== false
                            ) {

                                if (
                                    $index <
                                    $currentStepIndex
                                ) {

                                    $stepClass =
                                        'is-complete';

                                } elseif (
                                    $index ===
                                    $currentStepIndex
                                ) {

                                    $stepClass =
                                        'is-current';
                                }
                            }

                            ?>

                            <div class="order-step <?php echo $stepClass; ?>">

                                <span class="order-step-circle">

                                    <?php if (
                                        $stepClass ===
                                        'is-complete'
                                    ): ?>

                                        <i class="fa-solid fa-check"></i>

                                    <?php else: ?>

                                        <?php echo $index + 1; ?>

                                    <?php endif; ?>

                                </span>

                                <span class="order-step-label">

                                    <?php echo ucfirst($stepName); ?>

                                </span>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>


                <!-- Card Body -->

                <div class="card-body p-4">


                    <!-- Customer + Shipping + Payment -->

                    <div class="row mb-4">


                        <!-- Customer Information -->

                        <div class="col-lg-4 col-md-6 mb-3">

                            <div class="order-info-box">

                                <h6>

                                    <span class="info-box-icon icon-customer">

                                        <i class="fa-solid fa-user"></i>

                                    </span>

                                    Customer Information

                                </h6>


                                <span class="info-label">
                                    Name
                                </span>

                                <div class="info-value">

                                    <?php echo htmlspecialchars(
                                        $customerName
                                    ); ?>

                                </div>


                                <span class="info-label">
                                    Email
                                </span>

                                <div class="info-value mb-0">

                                    <?php echo htmlspecialchars(
                                        $customerEmail
                                    ); ?>

                                </div>

                            </div>

                        </div>


                        <!-- Shipping Information -->

                        <div class="col-lg-4 col-md-6 mb-3">

                            <div class="order-info-box">

                                <h6>

                                    <span class="info-box-icon icon-shipping">

                                        <i class="fa-solid fa-location-dot"></i>

                                    </span>

                                    Shipping Information

                                </h6>


                                <span class="info-label">
                                    Shipping Address
                                </span>


                                <div class="info-value shipping-address">

                                    <?php echo htmlspecialchars(
                                        $shippingAddress
                                    ); ?>

                                </div>


                                <span class="info-label">
                                    Delivery Status
                                </span>


                                <div class="info-value mb-0">

                                    <form
                                        action="detail.php?id=<?php echo (int) $order['id']; ?>"
                                        method="POST"
                                        id="orderStatusForm"
                                        class="status-update-field"
                                    >

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?php echo (int) $order['id']; ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="update_status"
                                            value="1"
                                        >


                                        <select
                                            name="order_status"
                                            id="orderStatusSelect"
                                            class="form-select mb-2"
                                            <?php echo count($selectableStatuses) <= 1 ? 'disabled' : ''; ?>
                                        >

                                            <option
                                                value="processing"
                                                <?php echo $orderStatus === 'processing' ? 'selected' : ''; ?>
                                                <?php echo !in_array('processing', $selectableStatuses, true) ? 'disabled' : ''; ?>
                                            >
                                                Processing
                                            </option>


                                            <option
                                                value="shipped"
                                                <?php echo $orderStatus === 'shipped' ? 'selected' : ''; ?>
                                                <?php echo !in_array('shipped', $selectableStatuses, true) ? 'disabled' : ''; ?>
                                            >
                                                Shipped
                                            </option>


                                            <option
                                                value="delivered"
                                                <?php echo $orderStatus === 'delivered' ? 'selected' : ''; ?>
                                                <?php echo !in_array('delivered', $selectableStatuses, true) ? 'disabled' : ''; ?>
                                            >
                                                Delivered
                                            </option>


                                            <option
                                                value="cancelled"
                                                <?php echo $orderStatus === 'cancelled' ? 'selected' : ''; ?>
                                                <?php echo !in_array('cancelled', $selectableStatuses, true) ? 'disabled' : ''; ?>
                                            >
                                                Cancelled
                                            </option>

                                        </select>


                                        <button
                                            type="submit"
                                            id="updateStatusBtn"
                                            class="btn btn-primary btn-sm btn-update-status"
                                            <?php echo count($selectableStatuses) <= 1 ? 'disabled' : ''; ?>
                                        >

                                            <span class="btn-label">
                                                Update Status
                                            </span>

                                        </button>

                                    </form>

                                </div>

                            </div>

                        </div>


                        <!-- Payment Information -->

                        <div class="col-lg-4 col-md-6 mb-3">

                            <div class="order-info-box">

                                <h6>

                                    <span class="info-box-icon icon-payment">

                                        <i class="fa-solid fa-credit-card"></i>

                                    </span>

                                    Payment Information

                                </h6>


                                <span class="info-label">
                                    Payment Method
                                </span>


                                <div class="info-value">

                                    <?= strtoupper(
                                        htmlspecialchars(
                                            $order['payment_method']
                                        )
                                    ) ?>

                                </div>


                                <span class="info-label">
                                    Payment Status
                                </span>


                                <div class="info-value mb-0">

                                    <form
                                        method="POST"
                                        id="paymentStatusForm"
                                        class="status-update-field"
                                    >

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?= (int) $order['id'] ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="update_payment_status"
                                            value="1"
                                        >


                                        <select
                                            name="payment_status"
                                            id="paymentStatusSelect"
                                            class="form-select mb-2"
                                            <?php echo count($selectablePaymentStatuses) <= 1 ? 'disabled' : ''; ?>
                                        >

                                            <option
                                                value="pending"
                                                <?= $paymentStatus === 'pending' ? 'selected' : '' ?>
                                                <?= !in_array('pending', $selectablePaymentStatuses, true) ? 'disabled' : '' ?>
                                            >
                                                Pending
                                            </option>


                                            <option
                                                value="completed"
                                                <?= $paymentStatus === 'completed' ? 'selected' : '' ?>
                                                <?= !in_array('completed', $selectablePaymentStatuses, true) ? 'disabled' : '' ?>
                                            >
                                                Completed
                                            </option>


                                            <option
                                                value="failed"
                                                <?= $paymentStatus === 'failed' ? 'selected' : '' ?>
                                                <?= !in_array('failed', $selectablePaymentStatuses, true) ? 'disabled' : '' ?>
                                            >
                                                Failed
                                            </option>

                                        </select>


                                        <button
                                            type="submit"
                                            id="updatePaymentBtn"
                                            class="btn btn-primary btn-sm btn-update-payment"
                                            <?php echo count($selectablePaymentStatuses) <= 1 ? 'disabled' : ''; ?>
                                        >

                                            <span class="btn-label">
                                                Update Payment Status
                                            </span>

                                        </button>

                                    </form>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Order Items -->

                    <div class="mb-4">

                        <h6 class="fw-bold mb-3 order-section-title">

                            <i class="fa-solid fa-box"></i>

                            Order Items

                        </h6>


                        <div class="table-responsive">

                            <table class="table align-items-center mb-0 order-items-table">

                                <thead>

                                    <tr>

                                        <th>
                                            Product
                                        </th>

                                        <th class="text-center">
                                            Quantity
                                        </th>

                                        <th class="text-end">
                                            Price
                                        </th>

                                        <th class="text-end">
                                            Total
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php if (
                                        empty($orderItems)
                                    ): ?>

                                        <tr>

                                            <td
                                                colspan="4"
                                                class="text-center py-4"
                                            >

                                                <span class="text-sm text-secondary">

                                                    No items found for this order.

                                                </span>

                                            </td>

                                        </tr>


                                    <?php else: ?>


                                        <?php foreach (
                                            $orderItems
                                            as $item
                                        ): ?>

                                            <tr>

                                                <td>

                                                    <div class="product-name">

                                                        <?php echo htmlspecialchars(
                                                            $item['product_name']
                                                        ); ?>

                                                    </div>


                                                    <div class="product-category">

                                                        <?php echo htmlspecialchars(
                                                            $item['category_name']
                                                        ); ?>

                                                    </div>

                                                </td>


                                                <td class="text-center">

                                                    <span class="text-sm font-weight-bold">

                                                        <?php echo (int) $item['quantity']; ?>

                                                    </span>

                                                </td>


                                                <td class="text-end">

                                                    <span class="text-sm font-weight-bold">

                                                        Rs.

                                                        <?php echo number_format(
                                                            (float) $item['unit_price'],
                                                            2
                                                        ); ?>

                                                    </span>

                                                </td>


                                                <td class="text-end">

                                                    <span class="text-sm font-weight-bold">

                                                        Rs.

                                                        <?php echo number_format(
                                                            (float) $item['subtotal'],
                                                            2
                                                        ); ?>

                                                    </span>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>


                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>


                    <!-- Order Summary -->

                    <div class="order-summary">

                        <div class="summary-row">

                            <span>
                                Subtotal
                            </span>

                            <strong>

                                Rs.

                                <?php echo number_format(
                                    $subtotal,
                                    2
                                ); ?>

                            </strong>

                        </div>


                        <div class="summary-row">

                            <span>
                                Shipping
                            </span>

                            <strong>

                                Rs.

                                <?php echo number_format(
                                    $shipping,
                                    2
                                ); ?>

                            </strong>

                        </div>


                        <div class="summary-row">

                            <span>
                                Discount
                            </span>

                            <strong>

                                -Rs.

                                <?php echo number_format(
                                    $discount,
                                    2
                                ); ?>

                            </strong>

                        </div>


                        <div class="summary-total">

                            <span>
                                Grand Total
                            </span>

                            <strong>

                                Rs.

                                <?php echo number_format(
                                    $grandTotal,
                                    2
                                ); ?>

                            </strong>

                        </div>

                    </div>


                    <!-- Actions -->

                    <div class="order-actions">

                        <a
                            href="index.php"
                            class="btn btn-light"
                        >

                            <i class="fa-solid fa-arrow-left me-1"></i>

                            Back to Orders

                        </a>


                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            onclick="window.print()"
                        >

                            <i class="fa-solid fa-print me-1"></i>

                            Print Order

                        </button>

                    </div>


                </div>

            </div>


            <!-- Admin Footer -->

            <?php require_once '../../includes/admin-footer.php'; ?>


        </div>

    </main>


    <!-- Popper -->

    <script src="../assets/js/core/popper.min.js"></script>


    <!-- Bootstrap -->

    <script src="../assets/js/core/bootstrap.min.js"></script>


    <!-- Perfect Scrollbar -->

    <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>


    <!-- Smooth Scrollbar -->

    <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>


    <!-- Material Dashboard -->

    <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>


    <!-- Status / Payment Update -->

    <script>

    document.addEventListener(
        "DOMContentLoaded",
        function () {


            /*
            |--------------------------------------------------------------------------
            | Toast
            |--------------------------------------------------------------------------
            */

            <?php if ($successMessage): ?>

                showToast(
                    <?= json_encode(
                        $successMessage
                    ) ?>,
                    "success"
                );

            <?php endif; ?>


            <?php if ($errorMessage): ?>

                showToast(
                    <?= json_encode(
                        $errorMessage
                    ) ?>,
                    "error"
                );

            <?php endif; ?>


            /*
            |--------------------------------------------------------------------------
            | Loading State
            |--------------------------------------------------------------------------
            */

            function attachLoadingState(
                formId,
                btnId,
                loadingText
            ) {

                var form =
                    document.getElementById(
                        formId
                    );

                var btn =
                    document.getElementById(
                        btnId
                    );


                if (!form || !btn) {

                    return;
                }


                form.addEventListener(
                    "submit",
                    function () {

                        btn.setAttribute(
                            "disabled",
                            "disabled"
                        );


                        btn.innerHTML =
                            '<span class="btn-spinner">' +

                            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>' +

                            loadingText +

                            '</span>';
                    }
                );
            }


            attachLoadingState(
                "orderStatusForm",
                "updateStatusBtn",
                "Updating..."
            );


            attachLoadingState(
                "paymentStatusForm",
                "updatePaymentBtn",
                "Updating..."
            );


            /*
            |--------------------------------------------------------------------------
            | Toast Function
            |--------------------------------------------------------------------------
            */

            function showToast(
                message,
                type
            ) {

                var oldToast =
                    document.querySelector(
                        ".cw-toast"
                    );


                if (oldToast) {

                    oldToast.remove();
                }


                var toast =
                    document.createElement(
                        "div"
                    );


                toast.className =
                    "cw-toast " +
                    (
                        type === "success"
                            ? "cw-toast-success"
                            : "cw-toast-error"
                    );


                var icon =
                    type === "success"
                        ? "fa-circle-check"
                        : "fa-circle-exclamation";


                toast.innerHTML =

                    '<i class="fa-solid ' +
                    icon +
                    ' cw-toast-icon"></i>' +

                    '<div class="cw-toast-message">' +
                    escapeHtml(message) +
                    '</div>' +

                    '<button type="button" class="cw-toast-close">&times;</button>';


                document.body.appendChild(
                    toast
                );


                var closeButton =
                    toast.querySelector(
                        ".cw-toast-close"
                    );


                closeButton.addEventListener(
                    "click",
                    function () {

                        toast.remove();

                    }
                );


                setTimeout(
                    function () {

                        if (
                            toast &&
                            toast.parentNode
                        ) {

                            toast.remove();
                        }

                    },
                    5000
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Escape Toast Text
            |--------------------------------------------------------------------------
            */

            function escapeHtml(
                text
            ) {

                var div =
                    document.createElement(
                        "div"
                    );

                div.textContent = text;

                return div.innerHTML;
            }

        }
    );

    </script>

</body>

</html>