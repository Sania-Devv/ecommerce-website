<?php

require_once __DIR__ . '/../core/Sessions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/smtp.php';

/*
|--------------------------------------------------------------------------
| Product Image Helper
|--------------------------------------------------------------------------
*/

function getProductFirstImage($image)
{
    if (empty($image)) {
        return '';
    }

    $decodedImages = json_decode($image, true);

    if (is_array($decodedImages)) {
        return $decodedImages[0] ?? '';
    }

    return $image;
}


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];


// CANCEL ORDER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {

    $cancelOrderId = isset($_POST['order_id'])
        ? (int) $_POST['order_id']
        : 0;

    if ($cancelOrderId <= 0) {
        die("Invalid order ID.");
    }

    // start transaction
    $mysqli->begin_transaction();

    try {

        // cancel order only if its belongs to users and is currently processing
        $cancelStmt = $mysqli->prepare(
            "UPDATE orders 
            SET order_status = 'cancelled'
            WHERE id = ?
            AND user_id = ?
            AND order_status = 'processing'"
        );

        if (!$cancelStmt) {
            throw new Exception(
                "Prepare failed: " . $mysqli->error
            );
        }

        $cancelStmt->bind_param(
            "ii",
            $cancelOrderId,
            $userId
        );

        $cancelStmt->execute();

        // If no row was updated, order cannot be cancelled
        if ($cancelStmt->affected_rows !== 1) {
            throw new Exception(
                "Order cannot be cancelled."
            );
        }

        $cancelStmt->close();


        // Get customer and order information for admin email
        $infoStmt = $mysqli->prepare("
            SELECT
                order_number,
                u.name AS customer_name,
                u.email AS customer_email
            FROM orders o
            INNER JOIN users u
                ON o.user_id = u.id
            WHERE o.id = ?
            AND o.user_id = ?
            LIMIT 1
        ");

        if (!$infoStmt) {
            throw new Exception(
                "Prepare failed: " . $mysqli->error
            );
        }

        $infoStmt->bind_param(
            "ii",
            $cancelOrderId,
            $userId
        );

        $infoStmt->execute();

        $infoResult = $infoStmt->get_result();
        $cancelOrderInfo = $infoResult->fetch_assoc();

        $infoStmt->close();

        if (!$cancelOrderInfo) {
            throw new Exception(
                "Order information could not be found."
            );
        }


        // Get products and quantities from this order
        $itemStmt = $mysqli->prepare("
            SELECT product_id, quantity
            FROM order_items
            WHERE order_id = ?
        ");

        if (!$itemStmt) {
            throw new Exception(
                "Prepare failed: " . $mysqli->error
            );
        }

        $itemStmt->bind_param(
            "i",
            $cancelOrderId
        );

        $itemStmt->execute();

        $itemResult = $itemStmt->get_result();
        $orderItemsToRestore = [];

        while ($item = $itemResult->fetch_assoc()) {
            $orderItemsToRestore[] = $item;
        }

        $itemStmt->close();


        // restore product stock
        $stockStmt = $mysqli->prepare(
            "UPDATE products
            SET stock = stock + ?
            WHERE id = ?"
        );

        if (!$stockStmt) {
            throw new Exception(
                "Prepare failed: " . $mysqli->error
            );
        }

        foreach ($orderItemsToRestore as $item) {

            $productId = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];

            $stockStmt->bind_param(
                "ii",
                $quantity,
                $productId
            );

            $stockStmt->execute();

            if ($stockStmt->affected_rows !== 1) {
                throw new Exception(
                    "Stock could not be restored."
                );
            }
        }

        $stockStmt->close();


        // complete transaction
        $mysqli->commit();


        // Send cancellation email to admin
        sendAdminOrderCancellationEmail(
            $cancelOrderInfo['order_number'],
            $cancelOrderInfo['customer_name'],
            $cancelOrderInfo['customer_email']
        );

        header(
            "Location: order-detail.php?id=" . $cancelOrderId
        );

        exit;

    } catch (Exception $e) {

        $mysqli->rollback();

        die(
            "Order cancellations failed: " . $e->getMessage()
        );
    }
}


$orderId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($orderId <= 0) {
    header('Location: my-orders.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Order
|--------------------------------------------------------------------------
*/

$orderStmt = $mysqli->prepare("
    SELECT
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
      AND o.user_id = ?
    LIMIT 1
");

$orderStmt->bind_param("ii", $orderId, $userId);
$orderStmt->execute();

$orderResult = $orderStmt->get_result();
$order = $orderResult->fetch_assoc();

$orderStmt->close();


if (!$order) {
    header('Location: my-orders.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Order Items
|--------------------------------------------------------------------------
*/

$itemStmt = $mysqli->prepare("
    SELECT
        oi.product_id,
        oi.quantity,
        oi.unit_price,
        oi.subtotal,
        p.name AS product_name,
        p.image AS product_image,
        c.name AS category_name
    FROM order_items oi
    INNER JOIN products p
        ON oi.product_id = p.id
    LEFT JOIN categories c
        ON p.category_id = c.id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");

$itemStmt->bind_param("i", $orderId);
$itemStmt->execute();

$itemResult = $itemStmt->get_result();

$orderItems = [];

while ($row = $itemResult->fetch_assoc()) {

    /*
    |--------------------------------------------------------------------------
    | Get First Product Image
    |--------------------------------------------------------------------------
    |
    | DB format:
    | ["5/image1.jpg","5/image2.jpg",...]
    |
    */

    $row['first_image'] = getProductFirstImage(
        $row['product_image']
    );

    $orderItems[] = $row;
}

$itemStmt->close();


/*
|--------------------------------------------------------------------------
| Calculate Subtotal
|--------------------------------------------------------------------------
*/

$subtotal = 0;

foreach ($orderItems as $item) {
    $subtotal += (float) $item['subtotal'];
}

$shipping = 0;
$discount = 0;

$total = (float) $order['total_amount'];


/*
|--------------------------------------------------------------------------
| Status Classes
|--------------------------------------------------------------------------
*/

$orderStatus = $order['order_status'];

if ($orderStatus === 'processing') {
    $statusClass = 'badge-warning';
} elseif ($orderStatus === 'shipped') {
    $statusClass = 'badge-info';
} elseif ($orderStatus === 'delivered') {
    $statusClass = 'badge-success';
} elseif ($orderStatus === 'cancelled') {
    $statusClass = 'badge-danger';
} else {
    $statusClass = 'badge-secondary';
}


$paymentStatus = $order['payment_status'];

if ($paymentStatus === 'completed') {
    $paymentClass = 'badge-success';
} elseif ($paymentStatus === 'failed') {
    $paymentClass = 'badge-danger';
} else {
    $paymentClass = 'badge-warning';
}


$pageTitle = "Order Details";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Order Details
    </title>

    <link
        rel="stylesheet"
        href="assets/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

<style>

    /* Page Header */
    .page-header {
        padding: 60px 0;
    }

    .order-detail-page,
    .page-content {
        background: #f7f7f7;
        padding: 40px 0 70px;
    }

    /* Cards */
    .card {
        border: none !important;
        border-radius: 10px !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06) !important;
        margin-bottom: 24px;
    }

    .card-body {
        padding: 28px !important;
    }

    .card .title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 1px solid #eee;
        color: #222;
    }

    /* Back button */
    .btn-outline-primary.btn-sm {
        border-radius: 25px;
        padding: 8px 20px;
        font-weight: 500;
        border-color: #cc9966;
        color: #cc9966;
        transition: all 0.25s ease;
    }

    .btn-outline-primary.btn-sm:hover {
        background-color: #cc9966;
        border-color: #cc9966;
        color: #fff;
    }

    /* Order number header */
    .card .title.mb-2 {
        border: none;
        padding: 0;
        font-size: 20px;
        margin-bottom: 6px;
    }

    .text-muted {
        color: #888 !important;
        font-size: 14px;
    }

    /* Badges */
    .badge {
        padding: 6px 16px !important;
        border-radius: 20px !important;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }

    .badge-success {
        background-color: #e5f6ea !important;
        color: #1d9c4b !important;
    }

    .badge-warning {
        background-color: #fff5e0 !important;
        color: #b8860b !important;
    }

    .badge-info {
        background-color: #e3f3fb !important;
        color: #1a7bad !important;
    }

    .badge-danger {
        background-color: #fce8e8 !important;
        color: #d93838 !important;
    }

    .badge-secondary {
        background-color: #eee !important;
        color: #777 !important;
    }

    /* Info boxes text */
    .card p {
        margin-bottom: 10px;
        line-height: 1.8;
        color: #555;
    }

    .card p strong {
        color: #222;
        min-width: 90px;
        display: inline-block;
    }

    /* Table */
    .table-responsive {
        border-radius: 8px;
        overflow: hidden;
    }

    .table thead th {
        background-color: #f5f5f5;
        color: #888;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
        border: none !important;
        padding: 14px 16px;
    }

    .table tbody td {
        vertical-align: middle;
        padding: 16px;
        border-top: 1px solid #f0f0f0 !important;
        color: #444;
    }

    .table tbody tr:hover {
        background-color: #fafafa;
    }

    .table td img {
        border-radius: 8px;
        border: 1px solid #eee;
    }

    .table td strong {
        color: #222;
        font-weight: 500;
    }

    /* Order Summary card highlighted */
    .col-md-5 .card {
        background: #fffaf3;
        border: 1px solid #f0e0c8 !important;
    }

    .col-md-5 .card .d-flex span {
        color: #777;
    }

    .col-md-5 .card .d-flex strong {
        color: #333;
    }

    .col-md-5 hr {
        border-top: 1px dashed #ddd;
        margin: 16px 0;
    }

    .col-md-5 .d-flex.justify-content-between:last-child strong {
        font-size: 18px;
        color: #cc9966;
    }

    @media (max-width: 767px) {

        .card-body {
            padding: 20px !important;
        }

        .page-header {
            padding: 40px 0;
        }

        .table {
            min-width: 650px;
        }
    }


    /* Modal */
    #cancelOrderModal .modal-content {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    }

    #cancelOrderModal .modal-header {
        background-color: #cc9966;
        border-bottom: none;
        padding: 18px 24px;
    }

    #cancelOrderModal .modal-title {
        color: #fff;
        font-weight: 600;
        font-size: 17px;
    }

    #cancelOrderModal .close {
        color: #fff;
        opacity: 0.9;
        text-shadow: none;
        font-size: 24px;
    }

    #cancelOrderModal .close:hover {
        opacity: 1;
        color: #fff;
    }

    #cancelOrderModal .modal-body {
        padding: 35px 24px 25px;
    }

    #cancelOrderModal .modal-body h4 {
        font-size: 19px;
        font-weight: 600;
        margin: 15px 0 10px;
        color: #222;
    }

    #cancelOrderModal .modal-body p {
        font-size: 14px;
        line-height: 1.7;
        color: #777;
    }

    #cancelOrderModal .modal-footer {
        border-top: 1px solid #f0f0f0;
        padding: 18px 24px;
        gap: 12px;
    }

    #cancelOrderModal .modal-footer .btn {
        border-radius: 25px;
        padding: 9px 22px;
        font-weight: 500;
        font-size: 14px;
        margin: 0;
    }

    #cancelOrderModal .btn-outline-secondary {
        border-color: #ddd;
        color: #555;
    }

    #cancelOrderModal .btn-outline-secondary:hover {
        background-color: #f5f5f5;
        border-color: #ddd;
        color: #333;
    }

    #cancelOrderModal .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
    }

    /* Responsive */
    @media (max-width: 575px) {

        #cancelOrderModal .modal-dialog {
            margin: 15px;
            max-width: calc(100% - 30px);
        }

        #cancelOrderModal .modal-footer {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        #cancelOrderModal .modal-footer form,
        #cancelOrderModal .modal-footer .btn {
            width: 100%;
        }

        #cancelOrderModal .modal-body {
            padding: 28px 20px 20px;
        }
    }


    /* Cancel button loader */
    #confirmCancelBtn {
        min-width: 150px;
        transition: all 0.2s ease;
    }

    #confirmCancelBtn:disabled {
        opacity: 0.75;
        cursor: not-allowed;
    }

    #cancelBtnLoader {
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }

</style>

</head>


<body>

<?php require_once __DIR__ . '/../includes/header.php'; ?>


<!-- Cancel Order Modal -->

<div
    class="modal fade"
    id="cancelOrderModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="cancelOrderModalLabel"
    aria-hidden="true"
>

    <div
        class="modal-dialog modal-dialog-centered"
        role="document"
    >

        <div class="modal-content">

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="cancelOrderModalLabel"
                >
                    Cancel Order
                </h5>

                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    aria-label="Close"
                >
                    <span aria-hidden="true">
                        &times;
                    </span>
                </button>

            </div>


            <div class="modal-body text-center">

                <div class="mb-3">

                    <i
                        class="icon-close"
                        style="font-size: 40px; color: #dc3545;"
                    ></i>

                </div>

                <h4>
                    Are you sure?
                </h4>

                <p class="text-muted mb-0">

                    Do you really want to cancel this order?

                    <br>

                    This action cannot be undone.

                </p>

            </div>


            <div class="modal-footer justify-content-center">

                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    data-dismiss="modal"
                >
                    No, Keep Order
                </button>


                <form
                    action="order-detail.php?id=<?php echo (int) $order['id']; ?>"
                    method="POST"
                >

                    <input
                        type="hidden"
                        name="order_id"
                        value="<?php echo (int) $order['id']; ?>"
                    >

                    <button
                        type="submit"
                        name="cancel_order"
                        value="1"
                        class="btn btn-danger"
                        id="confirmCancelBtn"
                    >

                        <span id="cancelBtnText">
                            Yes, Cancel Order
                        </span>

                        <span
                            id="cancelBtnLoader"
                            style="display: none;"
                        >

                            <span
                                class="spinner-border spinner-border-sm"
                                role="status"
                                aria-hidden="true"
                            ></span>

                            Cancelling...

                        </span>

                    </button>

                </form>

            </div>

        </div>

    </div>

</div>


<main class="main">


    <!-- Page Header -->

    <div
        class="page-header text-center"
        style="background-image: url('assets/images/page-header-bg.jpg')"
    >

        <div class="container">

            <h1 class="page-title">
                Order Details
            </h1>

        </div>

    </div>


    <!-- Order Details -->

    <div class="page-content">

        <div class="container">


            <!-- Order Header -->

            <div class="row">

                <div class="col-12">

                    <div class="mb-4">

                        <a
                            href="my-orders.php"
                            class="btn btn-outline-primary btn-sm"
                        >
                            ← Back to My Orders
                        </a>

                    </div>


                    <div class="card shadow-sm">

                        <div class="card-body">

                            <div class="row align-items-center">

                                <div class="col-md-6">

                                    <h2 class="title mb-2">

                                        Order #

                                        <?php
                                        echo htmlspecialchars(
                                            $order['order_number']
                                        );
                                        ?>

                                    </h2>

                                    <p class="mb-0 text-muted">

                                        Placed on

                                        <?php
                                        echo date(
                                            'd M Y, h:i A',
                                            strtotime($order['created_at'])
                                        );
                                        ?>

                                    </p>

                                </div>


                                <div class="col-md-6 text-md-right mt-3 mt-md-0">

                                    <span
                                        class="badge <?php echo $statusClass; ?>"
                                    >

                                        <?php
                                        echo ucfirst(
                                            $orderStatus
                                        );
                                        ?>

                                    </span>


                                    <?php if ($orderStatus === 'processing'): ?>

                                        <div class="mt-3">

                                            <button
                                                type="button"
                                                class="btn btn-outline-danger btn-sm"
                                                data-toggle="modal"
                                                data-target="#cancelOrderModal"
                                            >
                                                Cancel Order
                                            </button>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Customer + Payment -->

            <div class="row mt-4">


                <!-- Customer Information -->

                <div class="col-md-6 mb-4">

                    <div class="card shadow-sm h-100">

                        <div class="card-body">

                            <h3 class="title mb-4">
                                Customer Information
                            </h3>


                            <p>

                                <strong>
                                    Name:
                                </strong>

                                <?php
                                echo htmlspecialchars(
                                    $order['customer_name']
                                );
                                ?>

                            </p>


                            <p class="mb-0">

                                <strong>
                                    Email:
                                </strong>

                                <?php
                                echo htmlspecialchars(
                                    $order['customer_email']
                                );
                                ?>

                            </p>

                        </div>

                    </div>

                </div>


                <!-- Payment Information -->

                <div class="col-md-6 mb-4">

                    <div class="card shadow-sm h-100">

                        <div class="card-body">

                            <h3 class="title mb-4">
                                Payment Information
                            </h3>


                            <p>

                                <strong>
                                    Method:
                                </strong>

                                <?php
                                echo strtoupper(
                                    htmlspecialchars(
                                        $order['payment_method']
                                    )
                                );
                                ?>

                            </p>


                            <p class="mb-0">

                                <strong>
                                    Payment Status:
                                </strong>

                                <span
                                    class="badge <?php echo $paymentClass; ?>"
                                >

                                    <?php
                                    echo ucfirst(
                                        $paymentStatus
                                    );
                                    ?>

                                </span>

                            </p>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Shipping Address -->

            <div class="row">

                <div class="col-12 mb-4">

                    <div class="card shadow-sm">

                        <div class="card-body">

                            <h3 class="title mb-4">
                                Shipping Address
                            </h3>


                            <p class="mb-0">

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $order['shipping_address']
                                    )
                                );
                                ?>

                            </p>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Ordered Products -->

            <div class="row">

                <div class="col-12">

                    <div class="card shadow-sm">

                        <div class="card-body">

                            <h3 class="title mb-4">
                                Ordered Products
                            </h3>


                            <div class="table-responsive">

                                <table class="table">

                                    <thead>

                                        <tr>

                                            <th>
                                                Product
                                            </th>

                                            <th>
                                                Category
                                            </th>

                                            <th>
                                                Quantity
                                            </th>

                                            <th>
                                                Unit Price
                                            </th>

                                            <th>
                                                Subtotal
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php foreach ($orderItems as $item): ?>

                                            <tr>

                                                <!-- Product -->

                                                <td>

                                                    <div class="d-flex align-items-center">

                                                        <?php if (!empty($item['first_image'])): ?>

                                                            <img
                                                                src="uploads/products/<?php echo htmlspecialchars($item['first_image']); ?>"
                                                                alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                                width="60"
                                                                height="60"
                                                                style="
                                                                    object-fit: cover;
                                                                    margin-right: 15px;
                                                                    border-radius: 8px;
                                                                    width: 60px;
                                                                    height: 60px;
                                                                "
                                                            >

                                                        <?php else: ?>

                                                            <img
                                                                src="assets/images/products/table/product-1.jpg"
                                                                alt="No Image"
                                                                width="60"
                                                                height="60"
                                                                style="
                                                                    object-fit: cover;
                                                                    margin-right: 15px;
                                                                    border-radius: 8px;
                                                                    width: 60px;
                                                                    height: 60px;
                                                                "
                                                            >

                                                        <?php endif; ?>


                                                        <strong>

                                                            <?php
                                                            echo htmlspecialchars(
                                                                $item['product_name']
                                                            );
                                                            ?>

                                                        </strong>

                                                    </div>

                                                </td>


                                                <!-- Category -->

                                                <td>

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $item['category_name'] ?? '-'
                                                    );
                                                    ?>

                                                </td>


                                                <!-- Quantity -->

                                                <td>

                                                    <?php
                                                    echo (int) $item['quantity'];
                                                    ?>

                                                </td>


                                                <!-- Unit Price -->

                                                <td>

                                                    Rs.

                                                    <?php
                                                    echo number_format(
                                                        (float) $item['unit_price'],
                                                        2
                                                    );
                                                    ?>

                                                </td>


                                                <!-- Subtotal -->

                                                <td>

                                                    <strong>

                                                        Rs.

                                                        <?php
                                                        echo number_format(
                                                            (float) $item['subtotal'],
                                                            2
                                                        );
                                                        ?>

                                                    </strong>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Order Summary -->

            <div class="row justify-content-end mt-4">

                <div class="col-md-5">

                    <div class="card shadow-sm">

                        <div class="card-body">

                            <h3 class="title mb-4">
                                Order Summary
                            </h3>


                            <div class="d-flex justify-content-between mb-2">

                                <span>
                                    Subtotal
                                </span>

                                <strong>
                                    Rs.

                                    <?php
                                    echo number_format(
                                        $subtotal,
                                        2
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="d-flex justify-content-between mb-2">

                                <span>
                                    Shipping
                                </span>

                                <strong>
                                    Free
                                </strong>

                            </div>


                            <div class="d-flex justify-content-between mb-2">

                                <span>
                                    Discount
                                </span>

                                <strong>
                                    Rs.

                                    <?php
                                    echo number_format(
                                        $discount,
                                        2
                                    );
                                    ?>

                                </strong>

                            </div>


                            <hr>


                            <div class="d-flex justify-content-between">

                                <strong>
                                    Total
                                </strong>

                                <strong>

                                    Rs.

                                    <?php
                                    echo number_format(
                                        $total,
                                        2
                                    );
                                    ?>

                                </strong>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


        </div>

    </div>

</main>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const cancelForm =
        document.querySelector('#cancelOrderModal form');

    const cancelButton =
        document.getElementById('confirmCancelBtn');

    const cancelBtnText =
        document.getElementById('cancelBtnText');

    const cancelBtnLoader =
        document.getElementById('cancelBtnLoader');


    if (cancelForm && cancelButton) {

        let isSubmitting = false;


        cancelForm.addEventListener('submit', function (e) {

            // Agar pehle hi ek baar submit ho chuka hai
            // double-click ya Enter dobara dabana rok dein.
            if (isSubmitting) {

                e.preventDefault();
                return;

            }


            isSubmitting = true;

            cancelButton.style.pointerEvents = 'none';

            cancelButton.setAttribute(
                'aria-disabled',
                'true'
            );


            // Hide normal text
            if (cancelBtnText) {

                cancelBtnText.style.display = 'none';

            }


            // Show loader
            if (cancelBtnLoader) {

                cancelBtnLoader.style.display = 'inline-flex';

            }

        });

    }

});

</script>


<script src="assets/js/jquery.min.js"></script>

<script src="assets/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/main.js"></script>


</body>

</html>

