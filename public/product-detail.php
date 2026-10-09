<?php
require_once __DIR__ . '/../core/Sessions.php';
require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| Get Product ID
|--------------------------------------------------------------------------
*/

$productId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($productId <= 0) {
    header('Location: products.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Add To Cart
|--------------------------------------------------------------------------
*/

if (isset($_GET['add_to_cart'])) {

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $userId = (int) $_SESSION['user_id'];

    $quantity = isset($_GET['quantity'])
        ? (int) $_GET['quantity']
        : 1;

    if ($quantity < 1) {
        $quantity = 1;
    }

    // Check product
    $cartProductStmt = $mysqli->prepare("
        SELECT id, stock
        FROM products
        WHERE id = ?
        AND status = 1
        LIMIT 1
    ");

    $cartProductStmt->bind_param("i", $productId);
    $cartProductStmt->execute();

    $cartProductResult = $cartProductStmt->get_result();
    $cartProduct = $cartProductResult->fetch_assoc();

    $cartProductStmt->close();

    if (!$cartProduct || (int) $cartProduct['stock'] <= 0) {
        header('Location: products.php');
        exit;
    }

    // Get user's cart
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

    // Create cart if it doesn't exist
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

        $newQuantity = (int) $cartItem['quantity'] + $quantity;

        if ($newQuantity > (int) $cartProduct['stock']) {
            $newQuantity = (int) $cartProduct['stock'];
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

        if ($quantity > (int) $cartProduct['stock']) {
            $quantity = (int) $cartProduct['stock'];
        }

        $insertItemStmt = $mysqli->prepare("
            INSERT INTO cart_items
            (cart_id, product_id, quantity)
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

    // Update cart time
    $updateCartStmt = $mysqli->prepare("
        UPDATE cart
        SET updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    $updateCartStmt->bind_param("i", $cartId);
    $updateCartStmt->execute();
    $updateCartStmt->close();

    header('Location: product-detail.php?id=' . $productId);
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Product
|--------------------------------------------------------------------------
*/

$stmt = $mysqli->prepare("
    SELECT
        p.id,
        p.category_id,
        p.name,
        p.slug,
        p.description,
        p.price,
        p.stock,
        p.image,
        c.name AS category_name
    FROM products p
    INNER JOIN categories c
        ON p.category_id = c.id
    WHERE p.id = ?
      AND p.status = 1
      AND c.status = 1
    LIMIT 1
");

$stmt->bind_param("i", $productId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();

    header('Location: products.php');
    exit;
}

$product = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Product Images
|--------------------------------------------------------------------------
*/

$productImages = [];

if (!empty($product['image'])) {

    $decodedImages = json_decode(
        $product['image'],
        true
    );

    if (is_array($decodedImages)) {

        $productImages = $decodedImages;

    } else {

        // Backward compatibility for old single-image format
        $productImages = [
            $product['image']
        ];
    }
}

$firstImage = $productImages[0] ?? '';


/*
|--------------------------------------------------------------------------
| Get Related Products
|--------------------------------------------------------------------------
*/

$relatedProducts = [];

$relatedStmt = $mysqli->prepare("
    SELECT
        p.id,
        p.name,
        p.price,
        p.image,
        c.name AS category_name
    FROM products p
    INNER JOIN categories c
        ON p.category_id = c.id
    WHERE p.status = 1
      AND c.status = 1
      AND p.id != ?
      AND p.category_id = ?
    ORDER BY p.id DESC
    LIMIT 4
");

$relatedStmt->bind_param(
    "ii",
    $productId,
    $product['category_id']
);

$relatedStmt->execute();

$relatedResult = $relatedStmt->get_result();

while ($row = $relatedResult->fetch_assoc()) {
    $relatedProducts[] = $row;
}

$relatedStmt->close();


// If same category products are less than 4,
// get other active products as fallback.

if (count($relatedProducts) < 4) {

    $neededProducts = 4 - count($relatedProducts);

    $existingIds = [$productId];

    foreach ($relatedProducts as $relatedProduct) {
        $existingIds[] = (int) $relatedProduct['id'];
    }

    $placeholders = implode(
        ',',
        array_fill(0, count($existingIds), '?')
    );

    $types = str_repeat('i', count($existingIds));

    $fallbackStmt = $mysqli->prepare("
        SELECT
            p.id,
            p.name,
            p.price,
            p.image,
            c.name AS category_name
        FROM products p
        INNER JOIN categories c
            ON p.category_id = c.id
        WHERE p.status = 1
          AND c.status = 1
          AND p.id NOT IN ($placeholders)
        ORDER BY p.id DESC
        LIMIT ?
    ");

    $types .= 'i';

    $params = $existingIds;
    $params[] = $neededProducts;

    $fallbackStmt->bind_param(
        $types,
        ...$params
    );

    $fallbackStmt->execute();

    $fallbackResult = $fallbackStmt->get_result();

    while ($row = $fallbackResult->fetch_assoc()) {
        $relatedProducts[] = $row;
    }

    $fallbackStmt->close();
}

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
        <?php echo htmlspecialchars($product['name']); ?> - Molla
    </title>

    <meta name="keywords" content="HTML5 Template">

    <meta
        name="description"
        content="<?php echo htmlspecialchars($product['name']); ?>"
    >

    <meta name="author" content="p-themes">

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
        rel="manifest"
        href="assets/images/icons/site.html"
    >

    <link
        rel="mask-icon"
        href="assets/images/icons/safari-pinned-tab.svg"
        color="#666666"
    >

    <link
        rel="shortcut icon"
        href="assets/images/icons/favicon.ico"
    >

    <meta
        name="apple-mobile-web-app-title"
        content="Molla"
    >

    <meta
        name="application-name"
        content="Molla"
    >

    <meta
        name="msapplication-TileColor"
        content="#cc9966"
    >

    <meta
        name="msapplication-config"
        content="assets/images/icons/browserconfig.xml"
    >

    <meta
        name="theme-color"
        content="#ffffff"
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

    <!-- Main CSS -->
    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/plugins/nouislider/nouislider.css"
    >

<style>

/* Main product image */

.product-details-top .product-gallery-image {
    width: 100% !important;
    max-width: 100% !important;
    height: 500px !important;
    margin: 0 !important;
    padding: 0 !important;
    display: block !important;
    overflow: hidden !important;
}

.product-details-top .product-gallery-image img {
    width: 100% !important;
    max-width: 100% !important;
    height: 100% !important;
    max-height: 100% !important;
    object-fit: cover !important;
    object-position: center !important;
    display: block !important;
    cursor: pointer;
}


/* Product thumbnails */

.product-image-thumbnails {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.product-image-thumbnail {
    width: 80px;
    height: 90px;
    padding: 0;
    border: 1px solid #ddd;
    background: #fff;
    cursor: pointer;
    overflow: hidden;
}

.product-image-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.product-image-thumbnail.active {
    border: 2px solid #333;
}


@media (max-width: 767px) {

    .product-details-top .product-gallery-image {
        height: 400px !important;
    }

    .product-image-thumbnail {
        width: 65px;
        height: 75px;
    }
}

</style>

</head>

<body>

<div class="page-wrapper">

    <?php include '../includes/header.php'; ?>


    <main class="main">

        <div class="page-content">


            <!-- =========================================================
                 PRODUCT TOP
            ========================================================== -->


<div class="product-details-top">

    <div class="bg-light pb-5 mb-4">

        <!-- Breadcrumb -->
        <nav
            aria-label="breadcrumb"
            class="breadcrumb-nav border-0 mb-0"
        >
            <div class="container d-flex align-items-center">

                <ol class="breadcrumb">

                    <li class="breadcrumb-item">
                        <a href="index.php">
                            Home
                        </a>
                    </li>

                    <li class="breadcrumb-item">
                        <a href="products.php">
                            Products
                        </a>
                    </li>

                    <li
                        class="breadcrumb-item active"
                        aria-current="page"
                    >
                        <?php
                        echo htmlspecialchars(
                            $product['name']
                        );
                        ?>
                    </li>

                </ol>

            </div>
        </nav>


        <!-- =====================================================
             PRODUCT IMAGE + DETAILS
        ====================================================== -->

        <div class="container">

            <div class="row align-items-start">

                <!-- =================================================
                     LEFT SIDE - PRODUCT IMAGE
                ================================================== -->

                <div class="col-lg-6 col-md-6">

                    <figure class="product-gallery-image">

                        <?php if (!empty($firstImage)): ?>

                            <img
                                id="mainProductImage"
                                src="uploads/products/<?php echo htmlspecialchars($firstImage); ?>"
                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                            >

                        <?php else: ?>

                            <img
                                id="mainProductImage"
                                src="assets/images/products/product-1.jpg"
                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                            >

                        <?php endif; ?>

                    </figure>


                    <!-- =================================================
                         PRODUCT IMAGE THUMBNAILS
                    ================================================== -->

                    <?php if (count($productImages) > 1): ?>

                        <div class="product-image-thumbnails">

                            <?php foreach ($productImages as $index => $image): ?>

                                <button
                                    type="button"
                                    class="product-image-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                    onclick="changeProductImage(this)"
                                    data-image="uploads/products/<?php echo htmlspecialchars($image); ?>"
                                >

                                    <img
                                        src="uploads/products/<?php echo htmlspecialchars($image); ?>"
                                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                                    >

                                </button>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- =================================================
                     RIGHT SIDE - PRODUCT DETAILS
                ================================================== -->

                <div class="col-lg-6 col-md-6">

                    <div
                        class="product-details product-details-separator"
                    >

                        <!-- Product Name -->

                        <h1 class="product-title">

                            <?php
                            echo htmlspecialchars(
                                $product['name']
                            );
                            ?>

                        </h1>


                        <!-- Rating -->


                      


                        <!-- Price -->

                        <div class="product-price">

                            Rs.
                            <?php
                            echo number_format(
                                (float) $product['price'],
                                2
                            );
                            ?>

                        </div>


                        <!-- Description -->

                        <div class="product-content">

                            <?php if (!empty($product['description'])): ?>

                                <p>

                                    <?php
                                    echo nl2br(
                                        htmlspecialchars(
                                            $product['description']
                                        )
                                    );
                                    ?>

                                </p>

                            <?php else: ?>

                                <p>
                                    No description available.
                                </p>

                            <?php endif; ?>

                        </div>


                        <!-- Stock -->

                        <div class="mb-3">

                            <?php if ((int) $product['stock'] > 0): ?>

                                <span class="text-success">

                                    <i class="icon-check"></i>

                                    In Stock
                                    (
                                    <?php
                                    echo (int) $product['stock'];
                                    ?>
                                    available)

                                </span>

                            <?php else: ?>

                                <span class="text-danger">
                                    Out of Stock
                                </span>

                            <?php endif; ?>

                        </div>


                        <!-- Quantity + Add To Cart -->

                        <?php if ((int) $product['stock'] > 0): ?>

                            <div class="product-details-action">

                                <div class="details-action-col">

                                    <div
                                        class="product-details-quantity"
                                    >

                                        <input
                                            type="number"
                                            id="qty"
                                            class="form-control"
                                            value="1"
                                            min="1"
                                            max="<?php echo (int) $product['stock']; ?>"
                                            step="1"
                                            data-decimals="0"
                                            required
                                        >

                                    </div>

                                    <a
                                        href="product-detail.php?id=<?php echo (int) $product['id']; ?>&add_to_cart=1"
                                        class="btn-product btn-cart"
                                        onclick="this.href='product-detail.php?id=<?php echo (int) $product['id']; ?>&add_to_cart=1&quantity=' + document.getElementById('qty').value;"
                                    >
                                        <span>
                                            Add to Cart
                                        </span>
                                    </a>

                                </div>

                            </div>

                        <?php else: ?>

                            <button
                                type="button"
                                class="btn btn-outline-danger"
                                disabled
                            >
                                Out of Stock
                            </button>

                        <?php endif; ?>


                        <!-- Product Footer -->

                        <div
                            class="product-details-footer details-footer-col"
                        >

                            <!-- Category -->

                            <div class="product-cat">

                                <span>
                                    Category:
                                </span>

                                <a
                                    href="products.php?category_id=<?php echo (int) $product['category_id']; ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $product['category_name']
                                    );
                                    ?>

                                </a>

                            </div>


                            <!-- Social -->

                            <div
                                class="social-icons social-icons-sm"
                            >

                                <span class="social-label">
                                    Share:
                                </span>

                                <a
                                    href="#"
                                    class="social-icon"
                                    title="Facebook"
                                >
                                    <i class="icon-facebook-f"></i>
                                </a>

                                <a
                                    href="#"
                                    class="social-icon"
                                    title="Twitter"
                                >
                                    <i class="icon-twitter"></i>
                                </a>

                                <a
                                    href="#"
                                    class="social-icon"
                                    title="Instagram"
                                >
                                    <i class="icon-instagram"></i>
                                </a>

                                <a
                                    href="#"
                                    class="social-icon"
                                    title="Pinterest"
                                >
                                    <i class="icon-pinterest"></i>
                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


            <!-- =========================================================
                 PRODUCT TABS
            ========================================================== -->

            <div class="container">

                <div class="product-details-tab">


                    <!-- Tab Navigation -->

                    <ul
                        class="nav nav-pills justify-content-center"
                        role="tablist"
                    >

                        <li class="nav-item">

                            <a
                                class="nav-link active"
                                id="product-desc-link"
                                data-toggle="tab"
                                href="#product-desc-tab"
                                role="tab"
                                aria-controls="product-desc-tab"
                                aria-selected="true"
                            >
                                Description
                            </a>

                        </li>


                        <li class="nav-item">

                            <a
                                class="nav-link"
                                id="product-info-link"
                                data-toggle="tab"
                                href="#product-info-tab"
                                role="tab"
                                aria-controls="product-info-tab"
                                aria-selected="false"
                            >
                                Additional information
                            </a>

                        </li>


                        <li class="nav-item">

                            <a
                                class="nav-link"
                                id="product-shipping-link"
                                data-toggle="tab"
                                href="#product-shipping-tab"
                                role="tab"
                                aria-controls="product-shipping-tab"
                                aria-selected="false"
                            >
                                Shipping & Returns
                            </a>

                        </li>

                    </ul>


                    <div class="tab-content">


                        <!-- =================================================
                             DESCRIPTION
                        ================================================== -->

                        <div
                            class="tab-pane fade show active"
                            id="product-desc-tab"
                            role="tabpanel"
                            aria-labelledby="product-desc-link"
                        >

                            <div class="product-desc-content">

                                <h3>
                                    Product Description
                                </h3>

                                <?php if (!empty($product['description'])): ?>

                                    <p>

                                        <?php
                                        echo nl2br(
                                            htmlspecialchars(
                                                $product['description']
                                            )
                                        );
                                        ?>

                                    </p>

                                <?php else: ?>

                                    <p>
                                        No description available for this
                                        product.
                                    </p>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- =================================================
                             ADDITIONAL INFORMATION
                        ================================================== -->

                        <div
                            class="tab-pane fade"
                            id="product-info-tab"
                            role="tabpanel"
                            aria-labelledby="product-info-link"
                        >

                            <div class="product-desc-content">

                                <h3>
                                    Product Information
                                </h3>

                                <ul>

                                    <li>

                                        <strong>
                                            Product:
                                        </strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $product['name']
                                        );
                                        ?>

                                    </li>


                                    <li>

                                        <strong>
                                            Category:
                                        </strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $product['category_name']
                                        );
                                        ?>

                                    </li>


                                    <li>

                                        <strong>
                                            Price:
                                        </strong>

                                        Rs.
                                        <?php
                                        echo number_format(
                                            (float) $product['price'],
                                            2
                                        );
                                        ?>

                                    </li>


                                    <li>

                                        <strong>
                                            Stock:
                                        </strong>

                                        <?php
                                        echo (int) $product['stock'];
                                        ?>

                                    </li>

                                </ul>

                            </div>

                        </div>


                        <!-- =================================================
                             SHIPPING
                        ================================================== -->

                        <div
                            class="tab-pane fade"
                            id="product-shipping-tab"
                            role="tabpanel"
                            aria-labelledby="product-shipping-link"
                        >

                            <div class="product-desc-content">

                                <h3>
                                    Shipping & Returns
                                </h3>

                                <p>
                                    We currently offer standard shipping
                                    on all orders.
                                </p>

                                <p>
                                    For returns, please contact customer
                                    support according to the store return
                                    policy.
                                </p>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


                <!-- =========================================================
                    YOU MAY ALSO LIKE
                ========================================================= -->

<div class="container">

    <h2 class="title text-center mb-4">
        You May Also Like
    </h2>

    <div
        class="owl-carousel owl-simple carousel-equal-height carousel-with-shadow"
        data-toggle="owl"
        data-owl-options='{
            "nav": false,
            "dots": true,
            "margin": 20,
            "loop": false,
            "responsive": {
                "0": {
                    "items": 1
                },
                "480": {
                    "items": 2
                },
                "768": {
                    "items": 3
                },
                "992": {
                    "items": 4
                },
                "1200": {
                    "items": 4,
                    "nav": true,
                    "dots": false
                }
            }
        }'
    >

        <?php if (!empty($relatedProducts)): ?>

            <?php foreach ($relatedProducts as $relatedProduct): ?>

                <?php
                    $relatedImages = [];

                    if (!empty($relatedProduct['image'])) {

                        $decodedRelatedImages = json_decode(
                            $relatedProduct['image'],
                            true
                        );

                        if (is_array($decodedRelatedImages)) {

                            $relatedImages = $decodedRelatedImages;

                        } else {

                            // Old single-image format
                            $relatedImages = [
                                $relatedProduct['image']
                            ];
                        }
                    }

                    $relatedFirstImage = $relatedImages[0] ?? '';
                ?>

                <div class="product product-7 text-center">

                    <figure class="product-media">

                        <a
                            href="product-detail.php?id=<?php echo (int) $relatedProduct['id']; ?>"
                        >

                            <?php if (!empty($relatedFirstImage)): ?>

                                <img
                                    src="uploads/products/<?php echo htmlspecialchars($relatedFirstImage); ?>"
                                    alt="<?php echo htmlspecialchars($relatedProduct['name']); ?>"
                                    class="product-image"
                                >

                            <?php else: ?>

                                <img
                                    src="assets/images/products/product-1.jpg"
                                    alt="<?php echo htmlspecialchars($relatedProduct['name']); ?>"
                                    class="product-image"
                                >

                            <?php endif; ?>

                        </a>

                    </figure>


                    <div class="product-body">

                        <div class="product-cat">

                            <a
                                href="products.php"
                            >
                                <?php
                                echo htmlspecialchars(
                                    $relatedProduct['category_name']
                                );
                                ?>
                            </a>

                        </div>


                        <h3 class="product-title">

                            <a
                                href="product-detail.php?id=<?php echo (int) $relatedProduct['id']; ?>"
                            >
                                <?php
                                echo htmlspecialchars(
                                    $relatedProduct['name']
                                );
                                ?>
                            </a>

                        </h3>


                        <div class="product-price">

                         Rs.
                            <?php
                            echo number_format(
                                (float) $relatedProduct['price'],
                                2
                            );
                            ?>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <p class="text-center">
                No related products available.
            </p>

        <?php endif; ?>

    </div>

</div>


        </div>

    </main>


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

<script src="assets/js/owl.carousel.min.js"></script>

<script src="assets/js/bootstrap-input-spinner.js"></script>

<script src="assets/js/jquery.elevateZoom.min.js"></script>

<script src="assets/js/jquery.magnific-popup.min.js"></script>

<script src="assets/js/main.js"></script>


<!-- Product Image Gallery -->

<script>
function changeProductImage(thumbnail) {

    const mainImage = document.getElementById('mainProductImage');

    const imagePath = thumbnail.getAttribute('data-image');

    if (mainImage && imagePath) {

        mainImage.src = imagePath;

    }

    document
        .querySelectorAll('.product-image-thumbnail')
        .forEach(function(item) {

            item.classList.remove('active');

        });

    thumbnail.classList.add('active');
}
</script>

</body>

</html>