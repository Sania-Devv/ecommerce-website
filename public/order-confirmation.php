
<?php

require_once __DIR__ . '/../core/Sessions.php';
require_once __DIR__ . '/../config/database.php';


// Customer must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];


// Get latest order of logged-in customer
$orderStmt = $mysqli->prepare("
    SELECT
        o.id,
        o.order_number,
        o.total_amount,
        o.payment_method,
        o.payment_status,
        o.order_status,
        o.created_at
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.id DESC
    LIMIT 1
");

$orderStmt->bind_param("i", $userId);
$orderStmt->execute();

$orderResult = $orderStmt->get_result();
$order = $orderResult->fetch_assoc();

$orderStmt->close();


if (!$order) {
    header('Location: products.php');
    exit;
}


// Get ordered products
$itemStmt = $mysqli->prepare("
    SELECT
        oi.product_id,
        oi.quantity,
        oi.unit_price,
        oi.subtotal,
        p.name AS product_name,
        p.image AS product_image
    FROM order_items oi
    INNER JOIN products p
        ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");

$itemStmt->bind_param("i", $order['id']);
$itemStmt->execute();

$itemResult = $itemStmt->get_result();

$orderItems = [];

while ($row = $itemResult->fetch_assoc()) {
    $orderItems[] = $row;
}

$itemStmt->close();


// Calculate subtotal
$subtotal = 0;

foreach ($orderItems as $item) {
    $subtotal += (float) $item['subtotal'];
}

$total = (float) $order['total_amount'];


// Payment method
$paymentMethod = strtoupper(
    htmlspecialchars($order['payment_method'])
);


// Format date
$orderDate = date(
    'd M Y',
    strtotime($order['created_at'])
);

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

    <title>
        Order Confirmation - Molla
    </title>


    <!-- Plugins CSS -->

    <link
        rel="stylesheet"
        href="assets/css/bootstrap.min.css"
    >


    <!-- Main CSS -->

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >


    <!-- Demo 4 CSS -->

    <link
        rel="stylesheet"
        href="assets/css/demos/demo-4.css"
    >


    <!-- Icons -->

    <link
        rel="stylesheet"
        href="assets/css/plugins/animate/animate.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/plugins/owl-carousel/owl.carousel.css"
    >

</head>


<body>

<div class="page-wrapper">


    <!-- HEADER -->

    <?php include '../includes/header.php'; ?>


    <main class="main">


        <!-- Page Header -->

        <div
            class="page-header text-center"
            style="background-image: url('assets/images/page-header-bg.jpg')"
        >

            <div class="container">

                <h1 class="page-title">
                    Order Confirmation
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
                        <a href="products.php">
                            Shop
                        </a>
                    </li>

                    <li
                        class="breadcrumb-item active"
                        aria-current="page"
                    >
                        Order Confirmation
                    </li>

                </ol>

            </div>

        </nav>


        <div class="page-content">

            <div class="cart">

                <div class="container">

                    <div class="row">


                        <!-- LEFT SIDE -->

                        <div class="col-lg-8">


                            <!-- Success Message -->

                            <div
                                class="text-center p-4 mb-4"
                                style="
                                    background-color: #f9f9f9;
                                    border-radius: 4px;
                                    border: 1px solid #e0e0e0;
                                "
                            >

                                <div class="mb-2">

                                    <i
                                        class="icon-check"
                                        style="
                                            font-size: 40px;
                                            color: #c96;
                                        "
                                    ></i>

                                </div>


                                <h3
                                    class="title mb-1"
                                    style="font-size: 22px;"
                                >
                                    Thank You For Your Order!
                                </h3>


                                <p class="mb-0 text-muted">
                                    Your order has been placed successfully
                                    and is currently being processed.
                                </p>

                            </div>


                            <!-- Order Information -->

                            <div class="card card-dashboard mb-4">

                                <div class="card-body">

                                    <div class="row">


                                        <div class="col-md-4">

                                            <p class="mb-0">

                                                <strong>
                                                    Order Number
                                                </strong>

                                                <br>

                                                <?php
                                                echo htmlspecialchars(
                                                    $order['order_number']
                                                );
                                                ?>

                                            </p>

                                        </div>


                                        <div class="col-md-4">

                                            <p class="mb-0">

                                                <strong>
                                                    Order Date
                                                </strong>

                                                <br>

                                                <?php
                                                echo $orderDate;
                                                ?>

                                            </p>

                                        </div>


                                        <div class="col-md-4">

                                            <p class="mb-0">

                                                <strong>
                                                    Payment
                                                </strong>

                                                <br>

                                                <?php
                                                echo $paymentMethod;
                                                ?>

                                            </p>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <!-- Ordered Products -->

                            <div class="card card-dashboard mb-4">

                                <div class="card-body">

                                    <h3
                                        class="card-title"
                                        style="
                                            font-size: 18px;
                                            border-bottom: 1px solid #ebebeb;
                                            padding-bottom: 12px;
                                        "
                                    >
                                        Ordered Products
                                    </h3>


                                    <div class="table-responsive">

                                        <table
                                            class="table table-cart table-mobile mb-0"
                                        >

                                            <thead>

                                                <tr>

                                                    <th>
                                                        Product
                                                    </th>

                                                    <th>
                                                        Price
                                                    </th>

                                                    <th>
                                                        Qty
                                                    </th>

                                                    <th>
                                                        Total
                                                    </th>

                                                </tr>

                                            </thead>


                                            <tbody>

                                                <?php foreach ($orderItems as $item): ?>

                                                    <tr>


                                                        <!-- Product -->

                                                        <td class="product-col">

                                                            <div class="product">

                                                                <figure
                                                                    class="product-media"
                                                                >

                                                                    <a
                                                                        href="product-detail.php?id=<?php echo (int) $item['product_id']; ?>"
                                                                    >

                                                                        <?php
$productImage = '';

if (!empty($item['product_image'])) {
    $decodedImages = json_decode($item['product_image'], true);

    if (is_array($decodedImages)) {
        $productImage = $decodedImages[0] ?? '';
    } else {
        // Old single-image format support
        $productImage = $item['product_image'];
    }
}
?>

<?php if (!empty($productImage)): ?>

    <img
        src="uploads/products/<?php echo htmlspecialchars($productImage); ?>"
        alt="<?php echo htmlspecialchars($item['product_name']); ?>"
    >

<?php else: ?>

    <img
        src="assets/images/products/table/product-1.jpg"
        alt="Product image"
    >

<?php endif; ?>

                                                                    </a>

                                                                </figure>


                                                                <h3 class="product-title">

                                                                    <a
                                                                        href="product-detail.php?id=<?php echo (int) $item['product_id']; ?>"
                                                                    >

                                                                        <?php
                                                                        echo htmlspecialchars(
                                                                            $item['product_name']
                                                                        );
                                                                        ?>

                                                                    </a>

                                                                </h3>

                                                            </div>

                                                        </td>


                                                        <!-- Price -->

                                                        <td class="price-col">

                                                            Rs.

                                                            <?php
                                                            echo number_format(
                                                                (float) $item['unit_price'],
                                                                2
                                                            );
                                                            ?>

                                                        </td>


                                                        <!-- Quantity -->

                                                        <td class="quantity-col">

                                                            <?php
                                                            echo (int) $item['quantity'];
                                                            ?>

                                                        </td>


                                                        <!-- Total -->

                                                        <td class="total-col">

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


                            <!-- Action Buttons -->

                            <div class="text-center text-lg-left mb-4">

                                <a
                                    href="order-detail.php?id=<?php echo (int) $order['id']; ?>"
                                    class="btn btn-outline-primary-2 mr-2 mb-2"
                                >
                                    VIEW ORDER DETAILS
                                </a>


                                <a
                                    href="my-orders.php"
                                    class="btn btn-outline-primary-2 mb-2"
                                >
                                    MY ORDERS
                                </a>

                            </div>


                        </div>


                        <!-- RIGHT SIDE -->

                        <aside class="col-lg-4">


                            <!-- Order Summary -->

                            <div class="summary summary-cart">

                                <h3 class="summary-title">
                                    Order Summary
                                </h3>


                                <table class="table table-summary">

                                    <tbody>


                                        <!-- Order -->

                                        <tr class="summary-subtotal">

                                            <td>
                                                Order:
                                            </td>

                                            <td class="text-right">

                                                #
                                                <?php
                                                echo htmlspecialchars(
                                                    $order['order_number']
                                                );
                                                ?>

                                            </td>

                                        </tr>


                                        <!-- Date -->

                                        <tr class="summary-subtotal">

                                            <td>
                                                Date:
                                            </td>

                                            <td class="text-right">

                                                <?php
                                                echo $orderDate;
                                                ?>

                                            </td>

                                        </tr>


                                        <!-- Subtotal -->

                                        <tr class="summary-subtotal">

                                            <td>
                                                Subtotal:
                                            </td>

                                            <td class="text-right">

                                                Rs.

                                                <?php
                                                echo number_format(
                                                    $subtotal,
                                                    2
                                                );
                                                ?>

                                            </td>

                                        </tr>


                                        <!-- Shipping -->

                                        <tr class="summary-shipping">

                                            <td>
                                                Shipping:
                                            </td>

                                            <td class="text-right">
                                                Free Shipping
                                            </td>

                                        </tr>


                                        <!-- Payment -->

                                        <tr class="summary-shipping">

                                            <td>
                                                Payment:
                                            </td>

                                            <td class="text-right">

                                                <?php
                                                echo $paymentMethod;
                                                ?>

                                            </td>

                                        </tr>


                                        <!-- Total -->

                                        <tr class="summary-total">

                                            <td>
                                                Total:
                                            </td>

                                            <td class="text-right">

                                                Rs.

                                                <?php
                                                echo number_format(
                                                    $total,
                                                    2
                                                );
                                                ?>

                                            </td>

                                        </tr>


                                    </tbody>

                                </table>


                                <!-- Continue Shopping -->

                                <a
                                    href="products.php"
                                    class="btn btn-outline-primary-2 btn-block"
                                >
                                    CONTINUE SHOPPING
                                </a>

                            </div>


                            <!-- Back Home -->

                            <a
                                href="index.php"
                                class="btn btn-outline-dark-2 btn-block mb-3"
                            >

                                <span>
                                    BACK TO HOME
                                </span>

                                <i class="icon-refresh"></i>

                            </a>


                        </aside>


                    </div>

                </div>

            </div>

        </div>

    </main>


    <!-- FOOTER -->

    <?php include '../includes/footer.php'; ?>


</div>


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

<script src="assets/js/main.js"></script>


</body>

</html>
