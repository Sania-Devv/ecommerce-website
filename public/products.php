<?php

require_once __DIR__ . '/../core/Sessions.php';
require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Category Filter
|--------------------------------------------------------------------------
*/

$categoryId = isset($_GET['category_id'])
    ? (int) $_GET['category_id']
    : 0;


/*
|--------------------------------------------------------------------------
| Selected Category
|--------------------------------------------------------------------------
*/

$selectedCategory = null;

if ($categoryId > 0) {

    $categoryStmt = $mysqli->prepare("
        SELECT id, name
        FROM categories
        WHERE id = ?
        AND status = 1
        LIMIT 1
    ");

    $categoryStmt->bind_param("i", $categoryId);
    $categoryStmt->execute();

    $categoryResult = $categoryStmt->get_result();
    $selectedCategory = $categoryResult->fetch_assoc();

    $categoryStmt->close();
}


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


    // Check product
    $productStmt = $mysqli->prepare("
        SELECT id, stock
        FROM products
        WHERE id = ?
        AND status = 1
        LIMIT 1
    ");

    $productStmt->bind_param("i", $productId);
    $productStmt->execute();

    $productResult = $productStmt->get_result();
    $product = $productResult->fetch_assoc();

    $productStmt->close();


    // Product doesn't exist
    if (!$product) {

        header('Location: products.php');
        exit;
    }


    // Out of stock
    if ((int) $product['stock'] <= 0) {

        header('Location: products.php');
        exit;
    }


    // Find user's cart
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


    // Create cart if doesn't exist
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


    // Check if product already exists in cart
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

        // Increase quantity
        $newQuantity = (int) $cartItem['quantity'] + 1;

        // Don't exceed stock
        if ($newQuantity > (int) $product['stock']) {
            $newQuantity = (int) $product['stock'];
        }

        $updateItemStmt = $mysqli->prepare("
            UPDATE cart_items
            SET quantity = ?,
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

        // Add new product
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


    // Update cart timestamp
    $updateCartStmt = $mysqli->prepare("
        UPDATE cart
        SET updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    $updateCartStmt->bind_param("i", $cartId);
    $updateCartStmt->execute();
    $updateCartStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Redirect back to the page where Add to Cart was clicked
    |--------------------------------------------------------------------------
    */

    $redirect = $_GET['redirect'] ?? 'cart.php';

    header('Location: ' . $redirect);
    exit;
}


/*
|--------------------------------------------------------------------------
| Products
|--------------------------------------------------------------------------
*/

if ($categoryId > 0 && $selectedCategory) {

    $productStmt = $mysqli->prepare("
        SELECT
            p.id,
            p.name,
            p.slug,
            p.description,
            p.price,
            p.stock,
            p.image,
            c.id AS category_id,
            c.name AS category_name
        FROM products p
        INNER JOIN categories c
            ON c.id = p.category_id
        WHERE p.status = 1
        AND c.status = 1
        AND p.category_id = ?
        ORDER BY p.id DESC
    ");

    $productStmt->bind_param("i", $categoryId);
    $productStmt->execute();

    $productResult = $productStmt->get_result();

} else {

    $productResult = $mysqli->query("
        SELECT
            p.id,
            p.name,
            p.slug,
            p.description,
            p.price,
            p.stock,
            p.image,
            c.id AS category_id,
            c.name AS category_name
        FROM products p
        INNER JOIN categories c
            ON c.id = p.category_id
        WHERE p.status = 1
        AND c.status = 1
        ORDER BY p.id DESC
    ");
}


/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

$categoriesResult = $mysqli->query("
    SELECT
        c.id,
        c.name,
        COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p
        ON p.category_id = c.id
        AND p.status = 1
    WHERE c.status = 1
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
");


/*
|--------------------------------------------------------------------------
| Product Count
|--------------------------------------------------------------------------
*/

$productCount = $productResult
    ? $productResult->num_rows
    : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, shrink-to-fit=no"
    >

    <title>

        <?php if ($selectedCategory): ?>

            <?= htmlspecialchars($selectedCategory['name']) ?> Products

        <?php else: ?>

            Products

        <?php endif; ?>

    </title>

    <meta name="keywords" content="E-Commerce">
    <meta name="description" content="Browse our products">
    <meta name="author" content="E-Commerce">


    <!-- Favicon -->

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


    <!-- Plugins CSS -->

    <link
        rel="stylesheet"
        href="assets/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/plugins/owl-carousel/owl.carousel.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/plugins/magnific-popup/magnific-popup.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/plugins/nouislider/nouislider.css"
    >

    <!-- Main CSS -->

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

</head>


<body>

<div class="page-wrapper">


    <?php include '../includes/header.php'; ?>


    <main class="main">


        <!-- Breadcrumb -->

        <nav
            aria-label="breadcrumb"
            class="breadcrumb-nav border-0 mb-0"
        >

            <div class="container">

                <ol class="breadcrumb">

                    <li class="breadcrumb-item">

                        <a href="index.php">
                            Home
                        </a>

                    </li>

                    <li
                        class="breadcrumb-item active"
                        aria-current="page"
                    >

                        <?php if ($selectedCategory): ?>

                            <?= htmlspecialchars($selectedCategory['name']) ?>

                        <?php else: ?>

                            Products

                        <?php endif; ?>

                    </li>

                </ol>

            </div>

        </nav>


        <div class="page-content">

            <div class="container">


                <!-- Page Heading -->

                <div class="heading heading-center mb-4">

                    <h2 class="title">

                        <?php if ($selectedCategory): ?>

                            <?= htmlspecialchars($selectedCategory['name']) ?>

                        <?php else: ?>

                            All Products

                        <?php endif; ?>

                    </h2>

                </div>


                <!-- Category Buttons -->

                <div class="mb-5">

                    <div class="text-center">

                        <!-- All Products -->

                        <a
                            href="products.php"
                            class="btn btn-outline-primary m-1
                            <?= $categoryId === 0 ? 'active' : '' ?>"
                        >

                            All Products

                        </a>


                        <?php if ($categoriesResult && $categoriesResult->num_rows > 0): ?>

                            <?php while ($category = $categoriesResult->fetch_assoc()): ?>

                                <a
                                    href="products.php?category_id=<?= (int) $category['id'] ?>"
                                    class="btn btn-outline-primary m-1
                                    <?= $categoryId === (int) $category['id'] ? 'active' : '' ?>"
                                >

                                    <?= htmlspecialchars($category['name']) ?>

                                    <small>
                                        (<?= (int) $category['product_count'] ?>)
                                    </small>

                                </a>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- Toolbox -->

                <div class="toolbox mb-4">

                    <div class="toolbox-left">

                        <div class="toolbox-info">

                            Showing

                            <span>
                                <?= $productCount ?>
                            </span>

                            Products

                        </div>

                    </div>

                </div>


                <!-- Products -->

                <?php if ($productResult && $productResult->num_rows > 0): ?>

                    <div class="products">

                        <div class="row">


                            <?php while ($product = $productResult->fetch_assoc()): ?>

                                <?php
                                    $productImages = [];

                                    if (!empty($product['image'])) {

                                        $decodedImages = json_decode(
                                            $product['image'],
                                            true
                                        );

                                        if (is_array($decodedImages)) {

                                            $productImages = $decodedImages;

                                        } else {

                                            // Old single-image format
                                            $productImages = [
                                                $product['image']
                                            ];
                                        }
                                    }

                                    $firstImage = $productImages[0] ?? '';
                                ?>


                                <div class="col-6 col-md-4 col-lg-3">

                                    <div class="product product-7 text-center">


                                        <!-- Product Media -->

                                        <figure class="product-media">


                                            <?php if ((int) $product['stock'] <= 0): ?>

                                                <span class="product-label label-out">

                                                    Out of Stock

                                                </span>

                                            <?php endif; ?>


                                            <a
                                                href="product-detail.php?id=<?= (int) $product['id'] ?>"
                                            >


                                                <?php if (!empty($firstImage)): ?>

                                                    <img
                                                        src="uploads/products/<?= htmlspecialchars($firstImage) ?>"
                                                        alt="<?= htmlspecialchars($product['name']) ?>"
                                                        class="product-image"
                                                    >

                                                <?php else: ?>

                                                    <img
                                                        src="assets/images/products/product-1.jpg"
                                                        alt="<?= htmlspecialchars($product['name']) ?>"
                                                        class="product-image"
                                                    >

                                                <?php endif; ?>


                                            </a>


                                            <!-- Product Action -->

                                            <div class="product-action">


                                                <?php if ((int) $product['stock'] > 0): ?>

                                                    <a
                                                        href="products.php?add_to_cart=<?= (int) $product['id'] ?>&redirect=products.php<?php if ($categoryId > 0): ?>&category_id=<?= (int) $categoryId ?><?php endif; ?>"
                                                        class="btn-product btn-cart"
                                                        title="Add to cart"
                                                    >

                                                        <span>
                                                            Add to Cart
                                                        </span>

                                                    </a>

                                                <?php else: ?>

                                                    <span class="btn-product">

                                                        <span>
                                                            Out of Stock
                                                        </span>

                                                    </span>

                                                <?php endif; ?>


                                            </div>


                                        </figure>


                                        <!-- Product Body -->

                                        <div class="product-body">


                                            <!-- Category -->

                                            <div class="product-cat">

                                                <a
                                                    href="products.php?category_id=<?= (int) $product['category_id'] ?>"
                                                >

                                                    <?= htmlspecialchars($product['category_name']) ?>

                                                </a>

                                            </div>


                                            <!-- Product Name -->

                                            <h3 class="product-title">

                                                <a
                                                    href="product-detail.php?id=<?= (int) $product['id'] ?>"
                                                >

                                                    <?= htmlspecialchars($product['name']) ?>

                                                </a>

                                            </h3>


                                            <!-- Product Price -->

                                            <div class="product-price">

                                                Rs.
                                                <?= number_format((float) $product['price'], 2) ?>

                                            </div>


                                            <!-- Stock -->

                                            <div class="ratings-container">

                                                <?php if ((int) $product['stock'] > 0): ?>

                                                    <span class="ratings-text">

                                                        <?= (int) $product['stock'] ?>
                                                        items available

                                                    </span>

                                                <?php else: ?>

                                                    <span class="ratings-text">

                                                        Out of Stock

                                                    </span>

                                                <?php endif; ?>

                                            </div>


                                        </div>

                                    </div>

                                </div>


                            <?php endwhile; ?>


                        </div>

                    </div>


                <?php else: ?>


                    <!-- No Products -->

                    <div class="text-center py-5">

                        <h3>
                            No Products Found
                        </h3>

                        <p>
                            There are no products available in this category.
                        </p>

                        <a
                            href="products.php"
                            class="btn btn-primary"
                        >

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

<button
    id="scroll-top"
    title="Back to Top"
>

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

<script src="assets/js/jquery.elevateZoom.min.js"></script>

<script src="assets/js/main.js"></script>

</body>

</html>