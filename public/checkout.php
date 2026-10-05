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


// ==============================
// VALIDATION VARIABLES
// ==============================

$error = '';

$firstNameError = '';
$lastNameError = '';
$countryError = '';
$addressError = '';
$cityError = '';
$stateError = '';
$postcodeError = '';
$phoneError = '';
$emailError = '';
$shippingMethodError = '';
$paymentMethodError = '';


// ==============================
// FETCH SAVED CUSTOMER DETAILS
// ==============================

$customerDetails = null;

$stmt = $mysqli->prepare("
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

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$customerDetails = $result->fetch_assoc();

$stmt->close();


// ==============================
// GET USER'S CART
// ==============================

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
    header('Location: cart.php');
    exit;
}

$cartId = (int) $cart['id'];


// ==============================
// GET CART PRODUCTS
// ==============================

$itemsStmt = $mysqli->prepare("
    SELECT
        ci.id AS cart_item_id,
        ci.quantity,
        p.id AS product_id,
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
    ORDER BY ci.id DESC
");

$itemsStmt->bind_param("i", $cartId);
$itemsStmt->execute();

$itemsResult = $itemsStmt->get_result();

$cartItems = [];
$subtotal = 0;


// ==============================
// CALCULATE PRODUCT PRICES
// ==============================

while ($item = $itemsResult->fetch_assoc()) {

    $originalPrice = (float) $item['price'];
    $discount = (float) $item['discount_percentage'];
    $quantity = (int) $item['quantity'];

    // Deal is active only when:
    // 1. Discount is greater than 0
    // 2. Deal start exists
    // 3. Deal end exists
    // 4. Current time is inside deal period

    $isDealActive =
        $discount > 0 &&
        !empty($item['deal_start']) &&
        !empty($item['deal_end']) &&
        strtotime($item['deal_start']) <= time() &&
        strtotime($item['deal_end']) >= time();


    // ==============================
    // CALCULATE UNIT PRICE
    // ==============================

    if ($isDealActive) {

        $unitPrice =
            $originalPrice -
            ($originalPrice * $discount / 100);

    } else {

        $unitPrice = $originalPrice;
    }


    // Prevent negative price

    if ($unitPrice < 0) {
        $unitPrice = 0;
    }


    // ==============================
    // CALCULATE ITEM TOTAL
    // ==============================

    $itemTotal = $unitPrice * $quantity;

    $item['unit_price'] = $unitPrice;
    $item['total'] = $itemTotal;
    $item['is_deal_active'] = $isDealActive;

    $subtotal += $itemTotal;

    $cartItems[] = $item;
}

$itemsStmt->close();


// ==============================
// EMPTY CART CHECK
// ==============================

if (empty($cartItems)) {
    header('Location: cart.php');
    exit;
}


// ==============================
// SHIPPING OPTIONS
// ==============================

$shippingOptions = [

    'free' => [
        'name' => 'Free Shipping',
        'amount' => 0
    ],

    'standard' => [
        'name' => 'Standard Shipping',
        'amount' => 250
    ],

    'express' => [
        'name' => 'Express Shipping',
        'amount' => 500
    ]

];


// ==============================
// DEFAULT SHIPPING
// ==============================

$shipping = 0;
$shippingName = 'Free Shipping';

$total = $subtotal + $shipping;


// ==============================
// DEFAULT FORM VALUES
// ==============================

$firstName = $customerDetails['first_name'] ?? '';
$lastName = $customerDetails['last_name'] ?? '';
$country = $customerDetails['country'] ?? 'Pakistan';
$address = $customerDetails['address'] ?? '';
$apartment = $customerDetails['apartment'] ?? '';
$city = $customerDetails['city'] ?? '';
$state = $customerDetails['state'] ?? '';
$postcode = $customerDetails['postcode'] ?? '';
$phone = $customerDetails['phone'] ?? '';
$email = $customerDetails['email'] ?? '';
$orderNotes = '';

$shippingMethod = 'free';
$paymentMethod = 'cod';


// ==============================
// PLACE ORDER
// ==============================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    // ==============================
    // PAYMENT METHOD
    // ==============================

    $paymentMethod = $_POST['payment_method'] ?? '';

    if (!in_array($paymentMethod, ['cod', 'stripe'], true)) {

        $paymentMethodError =
            'Please select a payment method.';
    }


    // ==============================
    // SHIPPING METHOD
    // ==============================

    $shippingMethod = $_POST['shipping_method'] ?? '';

    if (!isset($shippingOptions[$shippingMethod])) {

        $shippingMethodError =
            'Please select a shipping method.';
    } else {

        $shippingName =
            $shippingOptions[$shippingMethod]['name'];

        $shipping =
            (float) $shippingOptions[$shippingMethod]['amount'];
    }


    // ==============================
    // FINAL TOTAL
    // ==============================

    $total = $subtotal + $shipping;


    // ==============================
    // BILLING DETAILS
    // ==============================

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $apartment = trim($_POST['apartment'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postcode = trim($_POST['postcode'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $orderNotes = trim($_POST['order_notes'] ?? '');


    // ==============================
    // VALIDATION
    // ==============================

    // First Name

    if ($firstName === '') {

        $firstNameError =
            'First name is required.';

    } elseif (strlen($firstName) < 2) {

        $firstNameError =
            'First name must be at least 2 characters.';

    } elseif (!preg_match('/^[A-Za-z\s]+$/', $firstName)) {

        $firstNameError =
            'First name can contain letters and spaces only.';
    }


    // Last Name

    if ($lastName === '') {

        $lastNameError =
            'Last name is required.';

    } elseif (strlen($lastName) < 2) {

        $lastNameError =
            'Last name must be at least 2 characters.';

    } elseif (!preg_match('/^[A-Za-z\s]+$/', $lastName)) {

        $lastNameError =
            'Last name can contain letters and spaces only.';
    }


    // Country

    if ($country === '') {

        $countryError =
            'Country is required.';

    } elseif (strlen($country) < 2) {

        $countryError =
            'Please enter a valid country.';

    } elseif (!preg_match('/^[A-Za-z\s]+$/', $country)) {

        $countryError =
            'Country can contain letters and spaces only.';
    }


    // Address

    if ($address === '') {

        $addressError =
            'Street address is required.';

    } elseif (strlen($address) < 5) {

        $addressError =
            'Please enter a valid street address.';
    }


    // City

    if ($city === '') {

        $cityError =
            'Town / City is required.';

    } elseif (strlen($city) < 2) {

        $cityError =
            'Please enter a valid town or city.';

    } elseif (!preg_match('/^[A-Za-z\s]+$/', $city)) {

        $cityError =
            'Town / City can contain letters and spaces only.';
    }


    // State

    if ($state === '') {

        $stateError =
            'State / County is required.';

    } elseif (strlen($state) < 2) {

        $stateError =
            'Please enter a valid state or county.';

    } elseif (!preg_match('/^[A-Za-z\s]+$/', $state)) {

        $stateError =
            'State / County can contain letters and spaces only.';
    }


    // Postcode

    if ($postcode === '') {

        $postcodeError =
            'Postcode / ZIP is required.';

    } elseif (!preg_match('/^[0-9]{4,10}$/', $postcode)) {

        $postcodeError =
            'Postcode must contain 4 to 10 digits.';
    }


   // Phone
if ($phone === '') {
    $phoneError = 'Phone number is required.';
} elseif (!preg_match('/^(?:\+92|0092|03)[0-9]{9,10}$/', preg_replace('/[\s\-()]/', '', $phone))) {
    $phoneError = 'Please enter a valid phone number.';
}


  // Email
if ($email === '') {
    $emailError = 'Email is required.';
} elseif (!preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $email)) {
    $emailError = 'Please enter a valid email address.';
}


    // ==============================
    // CHECK VALIDATION RESULT
    // ==============================

    $hasValidationErrors =
        $firstNameError !== '' ||
        $lastNameError !== '' ||
        $countryError !== '' ||
        $addressError !== '' ||
        $cityError !== '' ||
        $stateError !== '' ||
        $postcodeError !== '' ||
        $phoneError !== '' ||
        $emailError !== '' ||
        $shippingMethodError !== '' ||
        $paymentMethodError !== '';


    if ($hasValidationErrors) {

        $error =
            'Please correct the highlighted fields.';

    } else {


        // ==============================
        // CHECK PRODUCT STOCK
        // ==============================

        foreach ($cartItems as $item) {

            $productId =
                (int) $item['product_id'];

            $quantity =
                (int) $item['quantity'];

            $stock =
                (int) $item['stock'];


            if ($stock <= 0) {

                $error =
                    'Product is out of stock: ' .
                    $item['name'];

                break;
            }


            if ($quantity > $stock) {

                $error =
                    'Insufficient stock for product: ' .
                    $item['name'];

                break;
            }
        }


        // ==============================
        // CONTINUE ONLY IF STOCK IS OK
        // ==============================

        if ($error === '') {


            // ==============================
            // CREATE SHIPPING ADDRESS
            // ==============================

            $shippingAddress =
                "Name: " . $firstName . " " . $lastName . "\n" .
                "Country: " . $country . "\n" .
                "Address: " . $address . "\n" .
                "Apartment: " . $apartment . "\n" .
                "City: " . $city . "\n" .
                "State: " . $state . "\n" .
                "Postcode: " . $postcode . "\n" .
                "Phone: " . $phone . "\n" .
                "Email: " . $email;


            if ($orderNotes !== '') {

                $shippingAddress .=
                    "\nOrder Notes: " . $orderNotes;
            }


            // ==============================
            // STRIPE PAYMENT
            // ==============================

            if ($paymentMethod === 'stripe') {

                try {

                    $lineItems = [];


                    // ==============================
                    // PRODUCT SUBTOTAL
                    // ==============================

                    if ($subtotal > 0) {

                        $lineItems[] = [

                            'price_data' => [

                                'currency' => 'pkr',

                                'product_data' => [

                                    'name' =>
                                        'ClothWear Order',
                                ],

                                'unit_amount' =>
                                    (int) round(
                                        $subtotal * 100
                                    ),
                            ],

                            'quantity' => 1,
                        ];
                    }


                    // ==============================
                    // SHIPPING
                    // ==============================

                    if ($shipping > 0) {

                        $lineItems[] = [

                            'price_data' => [

                                'currency' => 'pkr',

                                'product_data' => [

                                    'name' =>
                                        $shippingName,
                                ],

                                'unit_amount' =>
                                    (int) round(
                                        $shipping * 100
                                    ),
                            ],

                            'quantity' => 1,
                        ];
                    }


                    // ==============================
                    // CREATE STRIPE SESSION
                    // ==============================

                    $session =
                        \Stripe\Checkout\Session::create([

                            'mode' => 'payment',

                            'payment_method_types' => [
                                'card'
                            ],

                            'line_items' =>
                                $lineItems,

                            'metadata' => [

                                'user_id' =>
                                    (string) $userId,

                                'payment_method' =>
                                    'stripe',

                                'shipping_method' =>
                                    $shippingMethod,

                                'shipping_amount' =>
                                    number_format(
                                        $shipping,
                                        2,
                                        '.',
                                        ''
                                    ),

                                'subtotal' =>
                                    number_format(
                                        $subtotal,
                                        2,
                                        '.',
                                        ''
                                    ),

                                'total' =>
                                    number_format(
                                        $total,
                                        2,
                                        '.',
                                        ''
                                    ),
                            ],

                            'success_url' =>
                                'http://ecommerce.local/public/stripe-success.php?session_id={CHECKOUT_SESSION_ID}',

                            'cancel_url' =>
                                'http://ecommerce.local/public/checkout.php?payment=cancelled',
                        ]);


                    header(
                        'Location: ' .
                        $session->url
                    );

                    exit;


                } catch (Exception $e) {

                    $error =
                        'Stripe payment could not be started: ' .
                        $e->getMessage();
                }
            }


            // ==============================
            // COD ORDER
            // ==============================

            if (
                $paymentMethod === 'cod' &&
                $error === ''
            ) {


                // ==============================
                // GENERATE ORDER NUMBER
                // ==============================

                $orderNumber =
                    'ORD-' .
                    date('YmdHis') .
                    '-' .
                    $userId;


                // ==============================
                // START TRANSACTION
                // ==============================

                $mysqli->begin_transaction();


                try {


                    // ==============================
                    // SAVE / UPDATE CUSTOMER DETAILS
                    // ==============================

                    $customerStmt =
                        $mysqli->prepare("

                            INSERT INTO customer_details (

                                user_id,
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

                            )

                            VALUES (
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?
                            )

                            ON DUPLICATE KEY UPDATE

                                first_name =
                                    VALUES(first_name),

                                last_name =
                                    VALUES(last_name),

                                country =
                                    VALUES(country),

                                address =
                                    VALUES(address),

                                apartment =
                                    VALUES(apartment),

                                city =
                                    VALUES(city),

                                state =
                                    VALUES(state),

                                postcode =
                                    VALUES(postcode),

                                phone =
                                    VALUES(phone),

                                email =
                                    VALUES(email)
                        ");


                    $customerStmt->bind_param(
                        "issssssssss",
                        $userId,
                        $firstName,
                        $lastName,
                        $country,
                        $address,
                        $apartment,
                        $city,
                        $state,
                        $postcode,
                        $phone,
                        $email
                    );


                    $customerStmt->execute();

                    $customerStmt->close();


                    // ==============================
                    // INSERT INTO ORDERS
                    // ==============================

                    $orderStmt =
                        $mysqli->prepare("

                            INSERT INTO orders (

                                user_id,
                                order_number,
                                total_amount,
                                shipping_amount,
                                shipping_method,
                                payment_method,
                                payment_status,
                                order_status,
                                shipping_address

                            )

                            VALUES (

                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                'pending',
                                'processing',
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
                        $paymentMethod,
                        $shippingAddress
                    );


                    $orderStmt->execute();

                    $orderId =
                        $mysqli->insert_id;

                    $orderStmt->close();


                    // ==============================
                    // INSERT ORDER ITEMS
                    // ==============================

                    $itemStmt =
                        $mysqli->prepare("

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

                        $productId =
                            (int) $item['product_id'];

                        $quantity =
                            (int) $item['quantity'];

                        $unitPrice =
                            (float) $item['unit_price'];

                        $itemSubtotal =
                            (float) $item['total'];


                        $itemStmt->bind_param(
                            "iiidd",
                            $orderId,
                            $productId,
                            $quantity,
                            $unitPrice,
                            $itemSubtotal
                        );


                        $itemStmt->execute();
                    }


                    $itemStmt->close();


                    // ==============================
                    // DECREASE PRODUCT STOCK
                    // ==============================

                    $stockStmt =
                        $mysqli->prepare("

                            UPDATE products

                            SET stock =
                                stock - ?

                            WHERE id = ?

                              AND stock >= ?
                        ");


                    foreach ($cartItems as $item) {

                        $productId =
                            (int) $item['product_id'];

                        $quantity =
                            (int) $item['quantity'];


                        $stockStmt->bind_param(
                            "iii",
                            $quantity,
                            $productId,
                            $quantity
                        );


                        $stockStmt->execute();


                        if (
                            $stockStmt->affected_rows !== 1
                        ) {

                            throw new Exception(
                                "Stock could not be updated for product: " .
                                $item['name']
                            );
                        }
                    }


                    $stockStmt->close();


                    // ==============================
                    // CLEAR CART
                    // ==============================

                    $clearCartStmt =
                        $mysqli->prepare("

                            DELETE FROM cart_items

                            WHERE cart_id = ?
                        ");


                    $clearCartStmt->bind_param(
                        "i",
                        $cartId
                    );


                    $clearCartStmt->execute();

                    $clearCartStmt->close();


                    // ==============================
                    // COMPLETE TRANSACTION
                    // ==============================

                    $mysqli->commit();


                    // ==============================
                    // SEND CUSTOMER EMAIL
                    // ==============================

                    sendOrderEmail(
                        $email,
                        $firstName . ' ' . $lastName,
                        $orderNumber,
                        $total
                    );


                    // ==============================
                    // SEND ADMIN EMAIL
                    // ==============================

                    sendAdminNewOrderEmail(
                        $orderNumber,
                        $firstName . ' ' . $lastName,
                        $email,
                        $total
                    );


                    // ==============================
                    // ORDER CONFIRMATION
                    // ==============================

                    header(
                        'Location: order-confirmation.php?order=' .
                        urlencode($orderNumber)
                    );

                    exit;


                } catch (Exception $e) {

                    $mysqli->rollback();

                    $error =
                        "Order placement failed: " .
                        $e->getMessage();
                }
            }
        }
    }
}

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge"
    >

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, shrink-to-fit=no"
    >

    <title>Checkout - ClothWear</title>

    <meta
        name="keywords"
        content="ClothWear, Checkout"
    >

    <meta
        name="description"
        content="ClothWear checkout"
    >

    <meta
        name="author"
        content="ClothWear"
    >

    <link
        rel="apple-touch-icon"
        sizes="180x180"
        href="assets/images/icons/apple-touch-icon.png"
    >

    <link
        rel="icon"
        type="image/png"
        sizes="32x32"
        href="assets/images/icons/favicon-32x32.png"
    >

    <link
        rel="icon"
        type="image/png"
        sizes="16x16"
        href="assets/images/icons/favicon-16x16.png"
    >

    <link
        rel="shortcut icon"
        href="assets/images/icons/favicon.ico"
    >

    <link
        rel="stylesheet"
        href="assets/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >


    <style>

        /* ==============================
           CHECKOUT VALIDATION
        ============================== */

        .field-error {
            display: block;
            margin-top: 5px;
            margin-bottom: 10px;
            font-size: 13px;
        }


        .form-control.field-invalid {
            border-color: #dc3545 !important;
        }


        /* ==============================
   CHECKOUT TOAST
============================== */

.checkout-toast {
    position: fixed;
    top: 25px;
    right: 25px;
    z-index: 99999;
    min-width: 320px;
    max-width: 420px;
    padding: 14px 20px;
    background: #dc3545;
    color: #fff;
    border-radius: 5px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.18);
    font-size: 14px;
    line-height: 1.5;

    opacity: 0;
    visibility: hidden;
    transform: translateY(-20px);

    transition:
        opacity 0.3s ease,
        transform 0.3s ease,
        visibility 0.3s ease;
}

.checkout-toast.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

@media (max-width: 575px) {

    .checkout-toast {
        top: 15px;
        left: 15px;
        right: 15px;
        min-width: auto;
        max-width: none;
    }
}

    </style>

</head>


<body>

<div class="page-wrapper">


    <?php include '../includes/header.php'; ?>


    <main class="main">


        <!-- Page Header -->

        <div
            class="page-header text-center"
            style="background-image: url('assets/images/page-header-bg.jpg')"
        >

            <div class="container">

                <h1 class="page-title">

                    Checkout

                    <span>Shop</span>

                </h1>

            </div>

        </div>


        <!-- Breadcrumb -->

        <nav
            aria-label="breadcrumb"
            class="breadcrumb-nav"
        >

            <div class="container">

                <ol class="breadcrumb">

                    <li class="breadcrumb-item">

                        <a href="index.php">
                            Home
                        </a>

                    </li>

                    <li class="breadcrumb-item">

                        <a href="#">
                            Shop
                        </a>

                    </li>

                    <li
                        class="breadcrumb-item active"
                        aria-current="page"
                    >

                        Checkout

                    </li>

                </ol>

            </div>

        </nav>


        <!-- Page Content -->

        <div class="page-content">

            <div class="checkout">

                <div class="container">


                    <?php if ($error): ?>

                        <div
                            id="checkout-toast"
                            class="checkout-toast"
                        >
                            <?= htmlspecialchars($error) ?>
                        </div>

                    <?php endif; ?>


                    <form
                        action="checkout.php"
                        method="post"
                        id="checkout-form"
                        novalidate
                    >

                        <div class="row">


                            <!-- BILLING DETAILS -->

                            <div class="col-lg-9">

                                <h2 class="checkout-title">
                                    Billing Details
                                </h2>


                                <div class="row">


                                    <!-- FIRST NAME -->

                                    <div class="col-sm-6">

                                        <label>
                                            First Name *
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control<?= $firstNameError ? ' field-invalid' : ''; ?>"
                                            name="first_name"
                                            value="<?= htmlspecialchars($firstName) ?>"
                                        >

                                        <?php if ($firstNameError): ?>

                                            <small class="text-danger field-error">
                                                <?= htmlspecialchars($firstNameError) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>


                                    <!-- LAST NAME -->

                                    <div class="col-sm-6">

                                        <label>
                                            Last Name *
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control<?= $lastNameError ? ' field-invalid' : ''; ?>"
                                            name="last_name"
                                            value="<?= htmlspecialchars($lastName) ?>"
                                        >

                                        <?php if ($lastNameError): ?>

                                            <small class="text-danger field-error">
                                                <?= htmlspecialchars($lastNameError) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <!-- COUNTRY -->

                                <label>
                                    Country *
                                </label>

                                <input
                                    type="text"
                                    class="form-control<?= $countryError ? ' field-invalid' : ''; ?>"
                                    name="country"
                                    value="<?= htmlspecialchars($country) ?>"
                                >

                                <?php if ($countryError): ?>

                                    <small class="text-danger field-error">
                                        <?= htmlspecialchars($countryError) ?>
                                    </small>

                                <?php endif; ?>


                                <!-- ADDRESS -->

                                <label>
                                    Street address *
                                </label>

                                <input
                                    type="text"
                                    class="form-control<?= $addressError ? ' field-invalid' : ''; ?>"
                                    name="address"
                                    value="<?= htmlspecialchars($address) ?>"
                                >

                                <?php if ($addressError): ?>

                                    <small class="text-danger field-error">
                                        <?= htmlspecialchars($addressError) ?>
                                    </small>

                                <?php endif; ?>


                                <!-- APARTMENT -->

                                <input
                                    type="text"
                                    class="form-control"
                                    name="apartment"
                                    value="<?= htmlspecialchars($apartment) ?>"
                                    placeholder="Apartments, suite, unit etc ..."
                                >


                                <div class="row">


                                    <!-- CITY -->

                                    <div class="col-sm-6">

                                        <label>
                                            Town / City *
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control<?= $cityError ? ' field-invalid' : ''; ?>"
                                            name="city"
                                            value="<?= htmlspecialchars($city) ?>"
                                        >

                                        <?php if ($cityError): ?>

                                            <small class="text-danger field-error">
                                                <?= htmlspecialchars($cityError) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>


                                    <!-- STATE -->

                                    <div class="col-sm-6">

                                        <label>
                                            State / County *
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control<?= $stateError ? ' field-invalid' : ''; ?>"
                                            name="state"
                                            value="<?= htmlspecialchars($state) ?>"
                                        >

                                        <?php if ($stateError): ?>

                                            <small class="text-danger field-error">
                                                <?= htmlspecialchars($stateError) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <div class="row">


                                    <!-- POSTCODE -->

                                    <div class="col-sm-6">

                                        <label>
                                            Postcode / ZIP *
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control<?= $postcodeError ? ' field-invalid' : ''; ?>"
                                            name="postcode"
                                            value="<?= htmlspecialchars($postcode) ?>"
                                        >

                                        <?php if ($postcodeError): ?>

                                            <small class="text-danger field-error">
                                                <?= htmlspecialchars($postcodeError) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>


                                    <!-- PHONE -->

                                    <div class="col-sm-6">

                                        <label>
                                            Phone *
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control<?= $phoneError ? ' field-invalid' : ''; ?>"
                                            name="phone"
                                            value="<?= htmlspecialchars($phone) ?>"
                                        >

                                        <?php if ($phoneError): ?>

                                            <small class="text-danger field-error">
                                                <?= htmlspecialchars($phoneError) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <!-- EMAIL -->

                                <label>
                                    Email address *
                                </label>

                                <input
                                    type="text"
                                    class="form-control<?= $emailError ? ' field-invalid' : ''; ?>"
                                    name="email"
                                    value="<?= htmlspecialchars($email) ?>"
                                >

                                <?php if ($emailError): ?>

                                    <small class="text-danger field-error">
                                        <?= htmlspecialchars($emailError) ?>
                                    </small>

                                <?php endif; ?>


                                <!-- ORDER NOTES -->

                                <label>
                                    Order notes (optional)
                                </label>

                                <textarea
                                    class="form-control"
                                    name="order_notes"
                                    cols="30"
                                    rows="4"
                                    placeholder="Notes about your order, e.g. special notes for delivery"
                                ><?= htmlspecialchars($orderNotes) ?></textarea>

                            </div>


                            <!-- ORDER SUMMARY -->

                            <aside class="col-lg-3">

                                <div class="summary">


                                    <h3 class="summary-title">
                                        Your Order
                                    </h3>


                                    <table class="table table-summary">

                                        <thead>

                                            <tr>

                                                <th>
                                                    Product
                                                </th>

                                                <th>
                                                    Total
                                                </th>

                                            </tr>

                                        </thead>


                                        <tbody>


                                            <?php foreach ($cartItems as $item): ?>

                                                <tr>

                                                    <td>

                                                        <a
                                                            href="product-detail.php?id=<?= (int) $item['product_id'] ?>"
                                                        >

                                                            <?= htmlspecialchars($item['name']) ?>

                                                        </a>

                                                        <span class="text-muted">

                                                            ×

                                                            <?= (int) $item['quantity'] ?>

                                                        </span>

                                                    </td>


                                                    <td>

                                                        Rs.

                                                        <?= number_format(
                                                            (float) $item['total'],
                                                            2
                                                        ) ?>

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>


                                            <!-- SUBTOTAL -->

                                            <tr class="summary-subtotal">

                                                <td>
                                                    Subtotal:
                                                </td>

                                                <td>

                                                    Rs.

                                                    <?= number_format(
                                                        $subtotal,
                                                        2
                                                    ) ?>

                                                </td>

                                            </tr>


                                            <!-- SHIPPING -->

                                            <tr>

                                                <td>
                                                    Shipping:
                                                </td>


                                                <td>


                                                    <label
                                                        style="display:block; margin-bottom:8px;"
                                                    >

                                                        <input
                                                            type="radio"
                                                            name="shipping_method"
                                                            value="free"
                                                            data-price="0"
                                                            <?= $shippingMethod === 'free' ? 'checked' : '' ?>
                                                        >

                                                        Free Shipping — Rs. 0

                                                    </label>


                                                    <label
                                                        style="display:block; margin-bottom:8px;"
                                                    >

                                                        <input
                                                            type="radio"
                                                            name="shipping_method"
                                                            value="standard"
                                                            data-price="250"
                                                            <?= $shippingMethod === 'standard' ? 'checked' : '' ?>
                                                        >

                                                        Standard Shipping — Rs. 250

                                                    </label>


                                                    <label
                                                        style="display:block;"
                                                    >

                                                        <input
                                                            type="radio"
                                                            name="shipping_method"
                                                            value="express"
                                                            data-price="500"
                                                            <?= $shippingMethod === 'express' ? 'checked' : '' ?>
                                                        >

                                                        Express Shipping — Rs. 500

                                                    </label>


                                                    <?php if ($shippingMethodError): ?>

                                                        <small class="text-danger field-error">
                                                            <?= htmlspecialchars($shippingMethodError) ?>
                                                        </small>

                                                    <?php endif; ?>


                                                </td>

                                            </tr>


                                            <!-- TOTAL -->

                                            <tr class="summary-total">

                                                <td>
                                                    Total:
                                                </td>

                                                <td>

                                                    Rs.

                                                    <span id="checkout-total">

                                                        <?= number_format(
                                                            $total,
                                                            2
                                                        ) ?>

                                                    </span>

                                                </td>

                                            </tr>

                                        </tbody>

                                    </table>


                                    <!-- PAYMENT METHOD -->

                                    <div
                                        class="accordion-summary"
                                        id="accordion-payment"
                                    >


                                        <!-- COD -->

                                        <div class="card">

                                            <div
                                                class="card-header"
                                                id="heading-cod"
                                            >

                                                <h2 class="card-title">

                                                    <a
                                                        role="button"
                                                        data-toggle="collapse"
                                                        href="#collapse-cod"
                                                        aria-expanded="true"
                                                        aria-controls="collapse-cod"
                                                    >

                                                        Cash on Delivery

                                                    </a>

                                                </h2>

                                            </div>


                                            <div
                                                id="collapse-cod"
                                                class="collapse show"
                                                aria-labelledby="heading-cod"
                                                data-parent="#accordion-payment"
                                            >

                                                <div class="card-body">

                                                    <label>

                                                        <input
                                                            type="radio"
                                                            name="payment_method"
                                                            value="cod"
                                                            <?= $paymentMethod === 'cod' ? 'checked' : '' ?>
                                                        >

                                                        Cash on Delivery

                                                    </label>


                                                    <p>
                                                        Pay when your order is delivered to you.
                                                    </p>

                                                </div>

                                            </div>

                                        </div>


                                        <!-- STRIPE -->

                                        <div class="card">

                                            <div
                                                class="card-header"
                                                id="heading-stripe"
                                            >

                                                <h2 class="card-title">

                                                    <a
                                                        class="collapsed"
                                                        role="button"
                                                        data-toggle="collapse"
                                                        href="#collapse-stripe"
                                                        aria-expanded="false"
                                                        aria-controls="collapse-stripe"
                                                    >

                                                        Stripe Payment

                                                    </a>

                                                </h2>

                                            </div>


                                            <div
                                                id="collapse-stripe"
                                                class="collapse"
                                                aria-labelledby="heading-stripe"
                                                data-parent="#accordion-payment"
                                            >

                                                <div class="card-body">

                                                    <label>

                                                        <input
                                                            type="radio"
                                                            name="payment_method"
                                                            value="stripe"
                                                            <?= $paymentMethod === 'stripe' ? 'checked' : '' ?>
                                                        >

                                                        Pay securely with Stripe

                                                    </label>


                                                    <p>
                                                        You will be redirected to Stripe to complete your payment securely.
                                                    </p>

                                                </div>

                                            </div>

                                        </div>


                                        <?php if ($paymentMethodError): ?>

                                            <small class="text-danger field-error">
                                                <?= htmlspecialchars($paymentMethodError) ?>
                                            </small>

                                        <?php endif; ?>


                                    </div>


                                    <!-- PLACE ORDER -->

                                    <button
                                        type="submit"
                                        class="btn btn-outline-primary-2 btn-order btn-block"
                                        id="place-order-btn"
                                    >

                                        <span
                                            class="btn-text"
                                            id="place-order-text"
                                        >
                                            Place Order
                                        </span>


                                        <span
                                            id="place-order-loader"
                                            style="display:none;"
                                        >

                                            <span
                                                class="spinner-border spinner-border-sm"
                                                role="status"
                                                aria-hidden="true"
                                            ></span>

                                            Processing...

                                        </span>


                                        <span class="btn-hover-text">
                                            Place Order
                                        </span>

                                    </button>


                                </div>

                            </aside>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </main>


    <?php include '../includes/footer.php'; ?>

</div>


<!-- Scroll Top -->

<button
    id="scroll-top"
    title="Back to Top"
>

    <i class="icon-arrow-up"></i>

</button>


<!-- Plugins JS -->

<script src="assets/js/jquery.min.js"></script>

<script src="assets/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/jquery.hoverIntent.min.js"></script>

<script src="assets/js/jquery.waypoints.min.js"></script>

<script src="assets/js/superfish.min.js"></script>

<script src="assets/js/owl.carousel.min.js"></script>


<!-- CHECKOUT JAVASCRIPT -->

<script>

document.addEventListener('DOMContentLoaded', function () {


    // ==============================
    // SHIPPING TOTAL
    // ==============================

    const shippingOptions =
        document.querySelectorAll(
            'input[name="shipping_method"]'
        );


    const subtotal =
        <?= json_encode((float) $subtotal) ?>;


    const totalElement =
        document.getElementById(
            'checkout-total'
        );


    shippingOptions.forEach(function (option) {

        option.addEventListener(
            'change',
            function () {

                const shipping =
                    parseFloat(
                        this.getAttribute(
                            'data-price'
                        )
                    ) || 0;


                const total =
                    subtotal + shipping;


                totalElement.textContent =
                    total.toFixed(2);

            }
        );

    });

// ==============================
// TOAST
// ==============================

const toast = document.getElementById('checkout-toast');

if (toast) {

    setTimeout(function () {
        toast.classList.add('show');
    }, 100);

    setTimeout(function () {
        toast.classList.remove('show');
    }, 4100);
}


    // ==============================
    // PLACE ORDER LOADING
    // ==============================

    const checkoutForm =
        document.getElementById(
            'checkout-form'
        );


    const placeOrderButton =
        document.getElementById(
            'place-order-btn'
        );


    const placeOrderText =
        document.getElementById(
            'place-order-text'
        );


    const placeOrderLoader =
        document.getElementById(
            'place-order-loader'
        );


    if (
        checkoutForm &&
        placeOrderButton
    ) {

        checkoutForm.addEventListener(
            'submit',
            function () {

                if (placeOrderButton.disabled) {
                    return;
                }


                placeOrderButton.disabled =
                    true;


                if (placeOrderText) {

                    placeOrderText.style.display =
                        'none';
                }


                if (placeOrderLoader) {

                    placeOrderLoader.style.display =
                        'inline-block';
                }


                placeOrderButton.style.pointerEvents =
                    'none';

            }
        );

    }

});

</script>


</body>

</html>