<?php

require_once __DIR__ . '/../core/Sessions.php';
require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| Add To Cart
|--------------------------------------------------------------------------
*/

if (isset($_GET['add_to_cart'])) {

    $productId = (int) $_GET['add_to_cart'];

    // User login check
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $userId = (int) $_SESSION['user_id'];


    /*
    |--------------------------------------------------------------------------
    | Check Product + Active Deal + Stock
    |--------------------------------------------------------------------------
    */

    $productStmt = $mysqli->prepare("
        SELECT
            id,
            stock
        FROM products
        WHERE id = ?
        AND status = 1
        AND stock > 0
        AND discount_percentage > 0
        AND deal_start IS NOT NULL
        AND deal_end IS NOT NULL
        AND NOW() BETWEEN deal_start AND deal_end
        LIMIT 1
    ");

    $productStmt->bind_param("i", $productId);
    $productStmt->execute();

    $productResult = $productStmt->get_result();
    $product = $productResult->fetch_assoc();

    $productStmt->close();


    // Product/deal doesn't exist
    if (!$product) {
        header('Location: deals.php');
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Find User Cart
    |--------------------------------------------------------------------------
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


    /*
    |--------------------------------------------------------------------------
    | Create Cart
    |--------------------------------------------------------------------------
    */

    if (!$cart) {

        $createCartStmt = $mysqli->prepare("
            INSERT INTO cart (user_id)
            VALUES (?)
        ");

        $createCartStmt->bind_param("i", $userId);
        $createCartStmt->execute();

        $cartId = $createCartStmt->insert_id;

        $createCartStmt->close();

    } else {

        $cartId = (int) $cart['id'];
    }


    /*
    |--------------------------------------------------------------------------
    | Check Existing Cart Item
    |--------------------------------------------------------------------------
    */

    $itemStmt = $mysqli->prepare("
        SELECT id, quantity
        FROM cart_items
        WHERE cart_id = ?
        AND product_id = ?
        LIMIT 1
    ");

    $itemStmt->bind_param("ii", $cartId, $productId);
    $itemStmt->execute();

    $itemResult = $itemStmt->get_result();
    $cartItem = $itemResult->fetch_assoc();

    $itemStmt->close();


    if ($cartItem) {

        $newQuantity = (int) $cartItem['quantity'] + 1;

        // Don't exceed stock
        if ($newQuantity > (int) $product['stock']) {
            $newQuantity = (int) $product['stock'];
        }

        $updateItemStmt = $mysqli->prepare("
            UPDATE cart_items
            SET
                quantity = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $updateItemStmt->bind_param(
            "ii",
            $newQuantity,
            $cartItem['id']
        );

        $updateItemStmt->execute();
        $updateItemStmt->close();

    } else {

        $quantity = 1;

        $insertItemStmt = $mysqli->prepare("
            INSERT INTO cart_items
            (
                cart_id,
                product_id,
                quantity
            )
            VALUES (?, ?, ?)
        ");

        $insertItemStmt->bind_param(
            "iii",
            $cartId,
            $productId,
            $quantity
        );

        $insertItemStmt->execute();
        $insertItemStmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Update Cart Timestamp
    |--------------------------------------------------------------------------
    */

    $updateCartStmt = $mysqli->prepare("
        UPDATE cart
        SET updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    $updateCartStmt->bind_param("i", $cartId);
    $updateCartStmt->execute();
    $updateCartStmt->close();


    header('Location: cart.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Active Deals
|--------------------------------------------------------------------------
*/

$dealResult = $mysqli->query("
    SELECT
        p.id,
        p.name,
        p.slug,
        p.description,
        p.price,
        p.discount_percentage,
        p.deal_start,
        p.deal_end,
        p.stock,
        p.image,
        c.id AS category_id,
        c.name AS category_name
    FROM products p
    INNER JOIN categories c
        ON c.id = p.category_id
    WHERE p.status = 1
    AND c.status = 1
    AND p.stock > 0
    AND p.discount_percentage > 0
    AND p.deal_start IS NOT NULL
    AND p.deal_end IS NOT NULL
    AND NOW() BETWEEN p.deal_start AND p.deal_end
    ORDER BY p.id DESC
");


/*
|--------------------------------------------------------------------------
| Product Count
|--------------------------------------------------------------------------
*/

$dealCount = $dealResult
    ? $dealResult->num_rows
    : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="viewport"
          content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>Deals & Offers</title>

    <meta name="keywords" content="E-Commerce, Deals, Offers">

    <meta name="description"
          content="Browse our latest deals and special offers">

    <meta name="author" content="E-Commerce">


    <!-- Favicon -->

    <link rel="apple-touch-icon"
          sizes="180x180"
          href="assets/images/icons/apple-touch-icon.png">

    <link rel="icon"
          type="image/png"
          sizes="32x32"
          href="assets/images/icons/favicon-32x32.png">

    <link rel="icon"
          type="image/png"
          sizes="16x16"
          href="assets/images/icons/favicon-16x16.png">

    <link rel="shortcut icon"
          href="assets/images/icons/favicon.ico">


    <!-- Plugins CSS -->

    <link rel="stylesheet"
          href="assets/css/bootstrap.min.css">

    <link rel="stylesheet"
          href="assets/css/plugins/owl-carousel/owl.carousel.css">

    <link rel="stylesheet"
          href="assets/css/plugins/magnific-popup/magnific-popup.css">

    <link rel="stylesheet"
          href="assets/css/plugins/nouislider/nouislider.css">


    <!-- Main CSS -->

    <link rel="stylesheet"
          href="assets/css/style.css">

</head>


<body>

<div class="page-wrapper">


    <?php include '../includes/header.php'; ?>


    <main class="main">


        <!-- Breadcrumb -->

        <nav aria-label="breadcrumb"
             class="breadcrumb-nav border-0 mb-0">

            <div class="container">

                <ol class="breadcrumb">

                    <li class="breadcrumb-item">

                        <a href="index.php">
                            Home
                        </a>

                    </li>

                    <li class="breadcrumb-item active"
                        aria-current="page">

                        Deals & Offers

                    </li>

                </ol>

            </div>

        </nav>


        <div class="page-content">

            <div class="container">


                <!-- Page Heading -->

                <div class="heading heading-center mb-4">

                    <h2 class="title">
                        Deals & Offers
                    </h2>

                    <p>
                        Grab our latest special offers before they expire.
                    </p>

                </div>


                <!-- Toolbox -->

                <div class="toolbox mb-4">

                    <div class="toolbox-left">

                        <div class="toolbox-info">

                            Showing

                            <span>
                                <?= $dealCount ?>
                            </span>

                            Active Deals

                        </div>

                    </div>

                </div>


                <!-- Deals -->

                <?php if ($dealResult && $dealResult->num_rows > 0): ?>

                    <div class="products">

                        <div class="row">


                            <?php while ($deal = $dealResult->fetch_assoc()): ?>

                                <?php

                                $price = (float) $deal['price'];

                                $discount = (float) $deal['discount_percentage'];

                                $dealPrice = $price - ($price * $discount / 100);

                                ?>


                                <div class="col-6 col-md-4 col-lg-3">

                                    <div class="product product-7 text-center">


                                        <!-- Product Media -->

                                        <figure class="product-media">


                                            <!-- Discount -->

                                            <span class="product-label label-sale">

                                                <?= number_format($discount, 0) ?>% OFF

                                            </span>


                                            <a href="product-detail.php?id=<?= (int) $deal['id'] ?>">


                                                <?php if (!empty($deal['image'])): ?>

                                                    <img
                                                        src="uploads/products/<?= htmlspecialchars($deal['image']) ?>"
                                                        alt="<?= htmlspecialchars($deal['name']) ?>"
                                                        class="product-image"
                                                    >

                                                <?php else: ?>

                                                    <img
                                                        src="assets/images/products/product-1.jpg"
                                                        alt="<?= htmlspecialchars($deal['name']) ?>"
                                                        class="product-image"
                                                    >

                                                <?php endif; ?>


                                            </a>


                                            <!-- Product Action -->

                                            <div class="product-action">

                                                <a
                                                    href="deals.php?add_to_cart=<?= (int) $deal['id'] ?>"
                                                    class="btn-product btn-cart"
                                                >

                                                    <span>
                                                        Add to Cart
                                                    </span>

                                                </a>

                                            </div>


                                        </figure>


                                        <!-- Product Body -->

                                        <div class="product-body">


                                            <!-- Category -->

                                            <div class="product-cat">

                                                <a href="products.php?category_id=<?= (int) $deal['category_id'] ?>">

                                                    <?= htmlspecialchars($deal['category_name']) ?>

                                                </a>

                                            </div>


                                            <!-- Product Name -->

                                            <h3 class="product-title">

                                                <a href="product-detail.php?id=<?= (int) $deal['id'] ?>">

                                                    <?= htmlspecialchars($deal['name']) ?>

                                                </a>

                                            </h3>


                                            <!-- Price -->

                                            <div class="product-price">

                                                <span class="old-price">

                                                    Rs.
                                                    <?= number_format($price, 2) ?>

                                                </span>

                                                <span class="new-price">

                                                    Rs.
                                                    <?= number_format($dealPrice, 2) ?>

                                                </span>

                                            </div>


                                            <!-- Deal Ends -->

                                            <div class="ratings-container">

                                                <span class="ratings-text">

                                                    Deal ends:
                                                    <?= date(
                                                        'd M Y, h:i A',
                                                        strtotime($deal['deal_end'])
                                                    ) ?>

                                                </span>

                                            </div>


                                        </div>

                                    </div>

                                </div>


                            <?php endwhile; ?>


                        </div>

                    </div>


                <?php else: ?>


                    <!-- No Deals -->

                    <div class="text-center py-5">

                        <h3>
                            No Active Deals
                        </h3>

                        <p>
                            There are currently no active deals available.
                        </p>

                        <a href="products.php"
                           class="btn btn-primary">

                            View All Products

                        </a>

                    </div>


                <?php endif; ?>


            </div>

        </div>

    </main>


    <?php include '../includes/footer.php'; ?>


</div>


<!-- Scroll Top -->

<button id="scroll-top"
        title="Back to Top">

    <i class="icon-arrow-up"></i>

</button>


<!-- JS Files -->

<script src="assets/js/jquery.min.js"></script>

<script src="assets/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/jquery.hoverIntent.min.js"></script>

<script src="assets/js/jquery.waypoints.min.js"></script>

<script src="assets/js/superfish.min.js"></script>

<script src="assets/js/owl.carousel.min.js"></script>

<script src="assets/js/bootstrap-input-spinner.js"></script>

<script src="assets/js/jquery.magnific-popup.min.js"></script>

<script src="assets/js/jquery.magnific-popup.min.js"></script>

<script src="assets/js/jquery.elevateZoom.min.js"></script>

<script src="assets/js/main.js"></script>

</body>

</html>