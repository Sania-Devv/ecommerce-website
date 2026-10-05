<?php

require_once __DIR__ . '/../core/Sessions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/stripe.php';
require_once __DIR__ . '/../config/smtp.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$sessionId = $_GET['session_id'] ?? '';

if ($sessionId === '') {
    die('Invalid Stripe session.');
}

try {

    /*
     * Get Stripe Checkout Session
     */
    $session = \Stripe\Checkout\Session::retrieve($sessionId);

    /*
     * Make sure payment was successful
     */
    if ($session->payment_status !== 'paid') {
        die('Payment was not completed.');
    }

    /*
     * Get user's cart
     */
    $cartStmt = $mysqli->prepare("
        SELECT id
        FROM cart
        WHERE user_id = ?
        LIMIT 1
    ");

    $cartStmt->bind_param("i", $userId);
    $cartStmt->execute();

    $cartResult = $cartStmt->get_result();
    $cart = $cartResult->fetch_assoc();

    $cartStmt->close();

    if (!$cart) {
        die('Cart not found.');
    }

    $cartId = (int) $cart['id'];

    /*
     * Get cart items
     */
    $itemsStmt = $mysqli->prepare("
        SELECT
            ci.product_id,
            ci.quantity,
            p.name,
            p.price,
            p.discount_percentage,
            p.deal_start,
            p.deal_end,
            p.stock
        FROM cart_items ci
        INNER JOIN products p
            ON ci.product_id = p.id
        WHERE ci.cart_id = ?
          AND p.status = 1
        FOR UPDATE
    ");

    $itemsStmt->bind_param("i", $cartId);
    $itemsStmt->execute();

    $itemsResult = $itemsStmt->get_result();

    $cartItems = [];
    $subtotal = 0;

    while ($item = $itemsResult->fetch_assoc()) {

        $originalPrice = (float) $item['price'];
        $discount = (float) $item['discount_percentage'];
        $quantity = (int) $item['quantity'];

        /*
         * Check active deal
         */
        $isDealActive =
            $discount > 0 &&
            !empty($item['deal_start']) &&
            !empty($item['deal_end']) &&
            strtotime($item['deal_start']) <= time() &&
            strtotime($item['deal_end']) >= time();

        if ($isDealActive) {

            $unitPrice =
                $originalPrice -
                ($originalPrice * $discount / 100);

        } else {

            $unitPrice = $originalPrice;
        }

        /*
         * Validate quantity
         */
        if ($quantity <= 0) {
            throw new Exception(
                "Invalid quantity for product: " .
                $item['name']
            );
        }

        /*
         * Validate stock
         */
        if ((int) $item['stock'] < $quantity) {
            throw new Exception(
                "Insufficient stock for product: " .
                $item['name']
            );
        }

        $item['unit_price'] = $unitPrice;
        $item['total'] = $unitPrice * $quantity;

        $subtotal += $item['total'];

        $cartItems[] = $item;
    }

    $itemsStmt->close();

    if (empty($cartItems)) {
        die('Your cart is empty.');
    }

    /*
     * Shipping
     */
    $shipping =
        (float) ($session->metadata->shipping_amount ?? 0);

    $shippingMethod =
        $session->metadata->shipping_method ?? 'free';

    $shippingOptions = [
        'free' => 'Free Shipping',
        'standard' => 'Standard Shipping',
        'express' => 'Express Shipping'
    ];

    if (!isset($shippingOptions[$shippingMethod])) {
        die('Invalid shipping method.');
    }

    $shippingName = $shippingOptions[$shippingMethod];

    $total = $subtotal + $shipping;

    /*
     * Verify Stripe amount against server-side amount
     */
    $stripeAmount = (int) $session->amount_total;

    $expectedAmount = (int) round($total * 100);

    if ($stripeAmount !== $expectedAmount) {
        die('Payment amount verification failed.');
    }

    /*
     * Prevent duplicate order
     */
    $existingStmt = $mysqli->prepare("
        SELECT id, order_number
        FROM orders
        WHERE transaction_id = ?
        LIMIT 1
    ");

    $existingStmt->bind_param("s", $sessionId);
    $existingStmt->execute();

    $existingResult = $existingStmt->get_result();
    $existingOrder = $existingResult->fetch_assoc();

    $existingStmt->close();

    if ($existingOrder) {

        header(
            'Location: order-confirmation.php?order=' .
            urlencode($existingOrder['order_number'])
        );

        exit;
    }

    /*
     * Get customer details
     */
    $detailsStmt = $mysqli->prepare("
        SELECT
            first_name,
            last_name,
            country,
            address,
            apartment,
            city,
            state,
            postcode,
            phone,
            email
        FROM customer_details
        WHERE user_id = ?
        LIMIT 1
    ");

    $detailsStmt->bind_param("i", $userId);
    $detailsStmt->execute();

    $detailsResult = $detailsStmt->get_result();
    $details = $detailsResult->fetch_assoc();

    $detailsStmt->close();

    if (!$details) {
        die('Customer details not found.');
    }

    /*
     * Create shipping address
     */
    $shippingAddress =
        $details['first_name'] . ' ' .
        $details['last_name'] . "\n" .
        $details['address'];

    if (!empty($details['apartment'])) {

        $shippingAddress .=
            "\n" . $details['apartment'];
    }

    $shippingAddress .=
        "\n" . $details['city'] .
        ', ' . $details['state'] .
        ', ' . $details['postcode'] .
        "\nPhone: " . $details['phone'] .
        "\nEmail: " . $details['email'];

    /*
     * Start database transaction
     */
    $mysqli->begin_transaction();

    /*
     * Generate order number
     */
    $orderNumber =
        'ORD-' .
        date('YmdHis') .
        '-' .
        $userId;

    /*
     * Create Stripe order
     */
    $orderStmt = $mysqli->prepare("
        INSERT INTO orders (
            user_id,
            order_number,
            total_amount,
            shipping_amount,
            shipping_method,
            payment_method,
            payment_status,
            order_status,
            transaction_id,
            shipping_address
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            'stripe',
            'completed',
            'processing',
            ?,
            ?
        )
    ");

    $orderStmt->bind_param(
        "isddsss",
        $userId,
        $orderNumber,
        $total,
        $shipping,
        $shippingName,
        $sessionId,
        $shippingAddress
    );

    $orderStmt->execute();

    $orderId = $mysqli->insert_id;

    $orderStmt->close();

    /*
     * Insert order items
     */
    $itemStmt = $mysqli->prepare("
        INSERT INTO order_items (
            order_id,
            product_id,
            quantity,
            unit_price,
            subtotal
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($cartItems as $item) {

        $productId = (int) $item['product_id'];
        $quantity = (int) $item['quantity'];
        $unitPrice = (float) $item['unit_price'];
        $subtotalItem = (float) $item['total'];

        $itemStmt->bind_param(
            "iiidd",
            $orderId,
            $productId,
            $quantity,
            $unitPrice,
            $subtotalItem
        );

        $itemStmt->execute();
    }

    $itemStmt->close();

    /*
     * Reduce stock
     */
    $stockStmt = $mysqli->prepare("
        UPDATE products
        SET stock = stock - ?
        WHERE id = ?
          AND stock >= ?
    ");

    foreach ($cartItems as $item) {

        $productId = (int) $item['product_id'];
        $quantity = (int) $item['quantity'];

        $stockStmt->bind_param(
            "iii",
            $quantity,
            $productId,
            $quantity
        );

        $stockStmt->execute();

        if ($stockStmt->affected_rows !== 1) {

            throw new Exception(
                "Stock could not be updated for product: " .
                $item['name']
            );
        }
    }

    $stockStmt->close();

    /*
     * Clear cart
     */
    $clearStmt = $mysqli->prepare("
        DELETE FROM cart_items
        WHERE cart_id = ?
    ");

    $clearStmt->bind_param("i", $cartId);
    $clearStmt->execute();

    $clearStmt->close();

    /*
     * Commit everything
     */
    $mysqli->commit();

    /*
     * Send Stripe order confirmation email
     */
    $customerName =
        $details['first_name'] . ' ' .
        $details['last_name'];

    $email = $details['email'];

    sendOrderEmail(
        $email,
        $customerName,
        $orderNumber,
        $total
    );
sendAdminNewOrderEmail(
    $orderNumber,
    $customerName,
    $email,
    $total
);
    /*
     * Go to confirmation page
     */
    header(
        'Location: order-confirmation.php?order=' .
        urlencode($orderNumber)
    );

    exit;

} catch (Exception $e) {

    $mysqli->rollback();

    die(
        'Stripe order could not be completed: ' .
        htmlspecialchars($e->getMessage())
    );
}