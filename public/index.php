<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Sessions.php';

$categoryQuery = "
    SELECT id, name, image
    FROM categories
    WHERE status = 1
    ORDER BY id DESC
";

$categoryResult = $mysqli->query($categoryQuery);

$categories = [];

if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

$productQuery = "
    SELECT
        p.id,
        p.category_id,
        p.name,
        p.price,
        p.image,
        p.stock,
        c.name AS category_name
    FROM products p
    INNER JOIN categories c
        ON p.category_id = c.id
    WHERE p.status = 1
      AND c.status = 1
    ORDER BY p.id DESC
    LIMIT 8
";

$productResult = $mysqli->query($productQuery);

$homeProducts = [];

if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $homeProducts[] = $row;
    }
}
  
/* NEW ARRIVALS - CATEGORY PRODUCTS */

$categoryProducts = [];

foreach ($categories as $category) {

    $categoryId = (int) $category['id'];

    $stmt = $mysqli->prepare("
        SELECT
            p.id,
            p.category_id,
            p.name,
            p.price,
            p.image,
            p.stock,
            c.name AS category_name
        FROM products p
        INNER JOIN categories c
            ON p.category_id = c.id
        WHERE p.status = 1
          AND c.status = 1
          AND p.category_id = ?
        ORDER BY p.id DESC
        LIMIT 8
    ");

    $stmt->bind_param("i", $categoryId);
    $stmt->execute();

    $result = $stmt->get_result();

    $categoryProducts[$categoryId] = [];

    while ($row = $result->fetch_assoc()) {
        $categoryProducts[$categoryId][] = $row;
    }

    $stmt->close();
}

// cta
$ctaProduct = null;

$ctaQuery = "
    SELECT
        p.id,
        p.name,
        p.price,
        p.image
    FROM products p
    INNER JOIN categories c
        ON p.category_id = c.id
    WHERE p.status = 1
      AND c.status = 1
    ORDER BY p.id DESC
    LIMIT 1
";

$ctaResult = $mysqli->query($ctaQuery);

if ($ctaResult && $ctaResult->num_rows > 0) {
    $ctaProduct = $ctaResult->fetch_assoc();
}
/* TRENDING PRODUCTS */

$trendingProducts = [];

$trendingQuery = "
    SELECT
        p.id,
        p.name,
        p.price,
        p.image,
        p.stock,
        c.name AS category_name
    FROM products p
    INNER JOIN categories c
        ON p.category_id = c.id
    WHERE p.status = 1
      AND c.status = 1
    ORDER BY p.id DESC
    LIMIT 8
";

$trendingResult = $mysqli->query($trendingQuery);

if ($trendingResult) {
    while ($row = $trendingResult->fetch_assoc()) {
        $trendingProducts[] = $row;
    }
}


/* BEST SELLING PRODUCTS */

$bestSellingProducts = [];

$bestSellingQuery = "
    SELECT
        p.id,
        p.name,
        p.price,
        p.image,
        p.stock,
        c.name AS category_name,
        COALESCE(SUM(oi.quantity), 0) AS sold_quantity
    FROM products p
    INNER JOIN categories c
        ON p.category_id = c.id
    LEFT JOIN order_items oi
        ON p.id = oi.product_id
    LEFT JOIN orders o
        ON oi.order_id = o.id
       AND o.order_status != 'cancelled'
    WHERE p.status = 1
      AND c.status = 1
    GROUP BY
        p.id,
        p.name,
        p.price,
        p.image,
        p.stock,
        c.name
    ORDER BY sold_quantity DESC, p.id DESC
    LIMIT 8
";

$bestSellingResult = $mysqli->query($bestSellingQuery);

if ($bestSellingResult) {
    while ($row = $bestSellingResult->fetch_assoc()) {
        $bestSellingProducts[] = $row;
    }
}
/* DEALS & OUTLET PRODUCTS */


$dealProducts = [];

$dealQuery = "
    SELECT
        p.id,
        p.name,
        p.price,
        p.discount_percentage,
        p.deal_end,
        p.image
    FROM products p
    INNER JOIN categories c
        ON p.category_id = c.id
    WHERE p.status = 1
      AND c.status = 1
      AND p.stock > 0
      AND p.discount_percentage > 0
      AND p.deal_start IS NOT NULL
      AND p.deal_end IS NOT NULL
      AND NOW() BETWEEN p.deal_start AND p.deal_end
    ORDER BY p.id DESC
    LIMIT 2
";

$dealResult = $mysqli->query($dealQuery);

if ($dealResult) {

    while ($row = $dealResult->fetch_assoc()) {
        $dealProducts[] = $row;
    }

}

// Fetch active sliders
$sliderQuery = $mysqli->query("
    SELECT subtitle, title, old_price, price, image, button_text, button_link
    FROM sliders
    WHERE status = 1
    ORDER BY sort_order ASC, id DESC
");

$sliders = [];

if ($sliderQuery) {
    while ($slider = $sliderQuery->fetch_assoc()) {
        $sliders[] = $slider;
    }
}

/* PRODUCT IMAGE HELPER */

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


/* NORMALIZE PRODUCT IMAGES */

foreach ($homeProducts as &$product) {
    $product['image'] = getProductFirstImage($product['image']);
}
unset($product);

foreach ($categoryProducts as &$products) {

    foreach ($products as &$product) {
        $product['image'] = getProductFirstImage($product['image']);
    }

    unset($product);
}
unset($products);

foreach ($trendingProducts as &$product) {
    $product['image'] = getProductFirstImage($product['image']);
}
unset($product);

foreach ($bestSellingProducts as &$product) {
    $product['image'] = getProductFirstImage($product['image']);
}
unset($product);

foreach ($dealProducts as &$product) {
    $product['image'] = getProductFirstImage($product['image']);
}
unset($product);

if (!empty($ctaProduct)) {
    $ctaProduct['image'] = getProductFirstImage($ctaProduct['image']);
}
?>


<!DOCTYPE html>
<html lang="en">


<!-- molla/index-4.html  22 Nov 2019 09:53:08 GMT -->
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Molla - Bootstrap eCommerce Template</title>
    <meta name="keywords" content="HTML5 Template">
    <meta name="description" content="Molla - Bootstrap eCommerce Template">
    <meta name="author" content="p-themes">
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/icons/favicon-16x16.png">
    <link rel="manifest" href="assets/images/icons/site.html">
    <link rel="mask-icon" href="assets/images/icons/safari-pinned-tab.svg" color="#666666">
    <link rel="shortcut icon" href="assets/images/icons/favicon.ico">
    <meta name="apple-mobile-web-app-title" content="Molla">
    <meta name="application-name" content="Molla">
    <meta name="msapplication-TileColor" content="#cc9966">
    <meta name="msapplication-config" content="assets/images/icons/browserconfig.xml">
    <meta name="theme-color" content="#ffffff">
    <link rel="stylesheet" href="assets/vendor/line-awesome/line-awesome/line-awesome/css/line-awesome.min.css">
    <!-- Plugins CSS File -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/plugins/owl-carousel/owl.carousel.css">
    <link rel="stylesheet" href="assets/css/plugins/magnific-popup/magnific-popup.css">
    <link rel="stylesheet" href="assets/css/plugins/jquery.countdown.css">
    <!-- Main CSS File -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/skins/skin-demo-4.css">
    <link rel="stylesheet" href="assets/css/demos/demo-4.css">
   <style>
    /* trending product */
/* Row ko flex kar ke banner aur products barabar height ke banate hain */
#trending-products > .container > .row {
    display: flex !important;
    flex-wrap: wrap !important;
    align-items: stretch !important;
}
#trending-products .owl-carousel .owl-item {
    padding: 0 10px;
}

#trending-products .owl-carousel .product {
    width: 100%;
}

.col-xl-5col,
.col-xl-4-5col {
    display: flex !important;
}

.col-xl-5col .banner {
    position: relative !important;
    width: 100% !important;
    overflow: hidden !important;
    background-color: #f5f5f5;
}

.col-xl-5col .banner a {
    display: block !important;
    width: 100% !important;
    height: 100% !important;
    position: relative;
}

.col-xl-5col .banner img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    object-position: center top !important;
    display: block !important;
}

/* Bottom solid bar - Shop Now */
.col-xl-5col .banner-bottom-bar {
    position: absolute;
    left: 0;
    bottom: 0;
    width: 100%;
    background-color: #3399cc;
    color: #fff;
    padding: 16px 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 15px;
    font-weight: 600;
    z-index: 2;
    transition: background-color 0.25s ease;
}

.col-xl-5col .banner:hover .banner-bottom-bar {
    background-color: #2b85b3;
}
style>
@media (min-width: 1200px) {
    .col-xl-5col {
        flex: 0 0 20% !important;
        max-width: 20% !important;
    }
 
    .col-xl-4-5col {
        flex: 0 0 80% !important;
        max-width: 80% !important;
    }
}

 
#trending-products .tab-content.tab-content-carousel > .tab-pane {
    display: block !important;
    position: absolute !important;
    top: 0;
    left: 0;
    width: 100%;
    visibility: hidden !important;
    height: 0 !important;
    overflow: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
}
 
#trending-products .tab-content.tab-content-carousel > .tab-pane.active {
    position: relative !important;
    visibility: visible !important;
    height: auto !important;
    overflow: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}
 
</style>
        
</head>

<body>
    <div class="page-wrapper">
   <?php include '../includes/header.php'; ?>

        <main class="main">
           <div class="intro-slider-container mb-5">

    <div class="intro-slider owl-carousel owl-theme owl-nav-inside owl-light"
        data-toggle="owl"
        data-owl-options='{
            "dots": true,
            "nav": false,
            "responsive": {
                "1200": {
                    "nav": true,
                    "dots": false
                }
            }
        }'>

        <?php if (!empty($sliders)): ?>

            <?php foreach ($sliders as $slider): ?>

                <div class="intro-slide">

                    <div class="container intro-content">

                        <div class="row align-items-center">

    <!-- IMAGE - 50% -->
    <div class="col-12 col-md-6 order-md-1">

        <div class="intro-slide-image text-center">
            <img
                src="uploads/sliders/<?= htmlspecialchars($slider['image']) ?>"
                alt="<?= htmlspecialchars($slider['title']) ?>"
                class="img-fluid"
                style="max-height: 400px; width: 100%; object-fit: contain;"
            >
        </div>

    </div>

    <!-- CONTENT - 50% -->
    <div class="col-12 col-md-6 order-md-2 my-auto">

        <?php if (!empty($slider['subtitle'])): ?>

            <h3 class="intro-subtitle text-primary">
                <?= htmlspecialchars($slider['subtitle']) ?>
            </h3>

        <?php endif; ?>

        <h1 class="intro-title">
            <?= htmlspecialchars($slider['title']) ?>
        </h1>

        <?php if ($slider['old_price'] !== null || $slider['price'] !== null): ?>

            <div class="intro-price">

                <?php if ($slider['old_price'] !== null): ?>

                    <sup class="intro-old-price">
                        Rs. <?= number_format((float)$slider['old_price'], 0) ?>
                    </sup>

                <?php endif; ?>

                <?php if ($slider['price'] !== null): ?>

                    <span class="text-primary">
                        Rs. <?= number_format((float)$slider['price'], 0) ?>
                    </span>

                <?php endif; ?>

            </div>

        <?php endif; ?>

        <a href="<?= htmlspecialchars($slider['button_link']) ?>"
           class="btn btn-primary btn-round">

            <span>
                <?= htmlspecialchars($slider['button_text']) ?>
            </span>

            <i class="icon-long-arrow-right"></i>

        </a>

    </div>

</div>

</div>
 </div>

<?php endforeach; ?>
<?php endif; ?>

</div>

    <span class="slider-loader"></span>

</div><!-- End .intro-slider-container -->


<!-- Popular categories -->
           <div class="container">
    <h2 class="title text-center mb-4">Explore Popular Categories</h2>

    <div class="cat-blocks-container">
        <div class="row">

            <?php if (!empty($categories)): ?>

                <?php foreach ($categories as $category): ?>

                    <div class="col-6 col-sm-4 col-lg-2">
                        <a href="products.php?category_id=<?php echo (int) $category['id']; ?>" class="cat-block">

                            <figure>
                                <span>
                                    <?php if (!empty($category['image'])): ?>

                                        <img
                                            src="uploads/categories/<?php echo htmlspecialchars($category['image']); ?>"
                                            alt="<?php echo htmlspecialchars($category['name']); ?>"
                                        >

                                    <?php else: ?>

                                        <img
                                            src="assets/images/demos/demo-4/cats/1.png"
                                            alt="<?php echo htmlspecialchars($category['name']); ?>"
                                        >

                                    <?php endif; ?>
                                </span>
                            </figure>

                            <h3 class="cat-block-title">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </h3>

                        </a>
                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="col-12 text-center">
                    <p>No categories available.</p>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div><!-- End .container -->

            <div class="mb-4"></div><!-- End .mb-4 -->

            <div class="mb-3"></div><!-- End .mb-5 -->

            <div class="container new-arrivals" id="new-arrivals">
                <div class="heading heading-flex mb-3">
                    <div class="heading-left">
                        <h2 class="title">New Arrivals</h2><!-- End .title -->
                    </div><!-- End .heading-left -->

                   <div class="heading-right">
                        <ul class="nav nav-pills nav-border-anim justify-content-center" role="tablist">

                            <!-- All -->
                            <li class="nav-item">
                                <a class="nav-link active"
                                    id="new-all-link"
                                    data-toggle="tab"
                                    href="#new-all-tab"
                                    role="tab"
                                    aria-controls="new-all-tab"
                                    aria-selected="true">
                                    All
                                </a>
                            </li>

                                    <?php foreach ($categories as $category): ?>

                                        <li class="nav-item">

                                            <a class="nav-link"
                                                id="new-category-<?php echo (int) $category['id']; ?>-link"
                                                data-toggle="tab"
                                                href="#new-category-<?php echo (int) $category['id']; ?>-tab"
                                                role="tab"
                                                aria-controls="new-category-<?php echo (int) $category['id']; ?>-tab"
                                                aria-selected="false">

                                                <?php echo htmlspecialchars($category['name']); ?>

                                            </a>

                                        </li>

                                    <?php endforeach; ?>

                                </ul>
                   </div><!-- End .heading-right -->
                </div><!-- End .heading -->

                <div class="tab-content tab-content-carousel just-action-icons-sm">
                    <div class="tab-pane p-0 fade show active" id="new-all-tab" role="tabpanel" aria-labelledby="new-all-link">

                                <div class="owl-carousel owl-full carousel-equal-height carousel-with-shadow"
                                    data-toggle="owl"
                                    data-owl-options='{
                                        "nav": true,
                                        "dots": true,
                                        "margin": 20,
                                        "loop": false,
                                        "responsive": {
                                            "0": {
                                                "items": 2
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
                                                "items": 5
                                            }
                                        }
                                    }'>

                                    <?php if (!empty($homeProducts)): ?>

                                        <?php foreach ($homeProducts as $product): ?>

                                            <div class="product product-2">

                                                <figure  class="product-media" style="height: 250px; overflow: hidden;">

                                                    <a href="product-detail.php?id=<?php echo (int) $product['id']; ?>">

                                                        <img 
                                src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                class="product-image"
                            >

                        </a>

                      

                        <div class="product-action">

    <?php if ((int) $product['stock'] > 0): ?>

        <a
            href="products.php?add_to_cart=<?php echo (int) $product['id']; ?>&redirect=index.php%23new-arrivals"
            class="btn-product btn-cart"
            title="Add to cart"
        >
            <span>Add to cart</span>
        </a>

    <?php else: ?>

        <span class="btn-product">
            <span>Out of stock</span>
        </span>

    <?php endif; ?>
<a
    href="product-detail.php?id=<?php echo (int) $product['id']; ?>"
    class="btn-product btn-details"
    title="View Details">
    <i class="icon-eye"></i>
    <span>View Details</span>
</a>

</div><!-- End .product-action -->

                    </figure><!-- End .product-media -->


                    <div class="product-body">

                        <div class="product-cat">

                            <a href="products.php?category_id=<?php echo (int) $product['category_id']; ?>">

                                <?php echo htmlspecialchars($product['category_name']); ?>

                            </a>

                        </div><!-- End .product-cat -->


                        <h3 class="product-title">

                            <a href="product-detail.php?id=<?php echo (int) $product['id']; ?>">

                                <?php echo htmlspecialchars($product['name']); ?>

                            </a>

                        </h3><!-- End .product-title -->


                        <div class="product-price">

                            $<?php echo number_format((float) $product['price'], 2); ?>

                        </div><!-- End .product-price -->


                        <?php if ((int) $product['stock'] > 0): ?>

                            <div class="ratings-container">

                                <span class="ratings-text">
                                    In Stock
                                </span>

                            </div><!-- End .ratings-container -->

                        <?php else: ?>

                            <div class="ratings-container">

                                <span class="ratings-text">
                                    Out of Stock
                                </span>

                            </div><!-- End .ratings-container -->

                        <?php endif; ?>


                    </div><!-- End .product-body -->

                </div><!-- End .product -->

            <?php endforeach; ?>

        <?php else: ?>

            <div class="text-center w-100 py-5">

                <p>No products available.</p>

            </div>

        <?php endif; ?>

    </div><!-- End .owl-carousel -->

</div><!-- .End .tab-pane --><!-- .End .tab-pane -->

<?php foreach ($categories as $category): ?>

    <?php
        $categoryId = (int) $category['id'];
        $products = $categoryProducts[$categoryId] ?? [];
    ?>

    <div
        class="tab-pane p-0 fade"
        id="new-category-<?php echo $categoryId; ?>-tab"
        role="tabpanel"
        aria-labelledby="new-category-<?php echo $categoryId; ?>-link">

        <div
            class="owl-carousel owl-full carousel-equal-height carousel-with-shadow"
            data-toggle="owl"
            data-owl-options='{
                "nav": true,
                "dots": true,
                "margin": 20,
                "loop": false,
                "responsive": {
                    "0": {
                        "items": 2
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
                        "items": 5
                    }
                }
            }'>

            <?php if (!empty($products)): ?>

                <?php foreach ($products as $product): ?>

                    <div class="product product-2">

                        <figure class="product-media">

                            <a href="product-detail.php?id=<?php echo (int) $product['id']; ?>">

                                <img
                                    src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                                    class="product-image"
                                >

                            </a>

                            

                            <div class="product-action">

    <?php if ((int) $product['stock'] > 0): ?>

        <a
            href="products.php?add_to_cart=<?php echo (int) $product['id']; ?>&redirect=index.php%23new-arrivals"
            class="btn-product btn-cart"
            title="Add to cart"
        >
            <span>Add to cart</span>
        </a>

    <?php else: ?>

        <span class="btn-product">
            <span>Out of stock</span>
        </span>

    <?php endif; ?>

  <a
    href="product-detail.php?id=<?php echo (int) $product['id']; ?>"
    class="btn-product btn-details"
    title="View Details">
    <i class="icon-eye"></i>
    <span>View Details</span>
</a>

</div>

                        </figure>

                        <div class="product-body">

                            <div class="product-cat">

                                <?php echo htmlspecialchars($product['category_name']); ?>

                            </div>

                            <h3 class="product-title">

                                <a href="product-detail.php?id=<?php echo (int) $product['id']; ?>">

                                    <?php echo htmlspecialchars($product['name']); ?>

                                </a>

                            </h3>

                            <div class="product-price">

                                $<?php echo number_format((float) $product['price'], 2); ?>

                            </div>

                            <?php if ((int) $product['stock'] > 0): ?>

                                <div class="ratings-container">

                                    <span class="ratings-text">
                                        In Stock
                                    </span>

                                </div>

                            <?php else: ?>

                                <div class="ratings-container">

                                    <span class="ratings-text">
                                        Out of Stock
                                    </span>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="text-center w-100 py-5">

                    <p>
                        No products available in
                        <?php echo htmlspecialchars($category['name']); ?>.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>

        <?php endforeach; ?>
                </div><!-- End .tab-content -->
            </div><!-- End .container -->

            <div class="mb-6"></div><!-- End .mb-6 -->
<style>
.cta.cta-border {
    position: relative !important;
    overflow: hidden !important;
    display: flex !important;
    align-items: stretch !important;
    margin-left: 0 !important;
    /* padding ko override nahi kar rahe - theme ki default height aayegi */
}

.cta.cta-border img.cta-img {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    bottom: 0 !important;
    height: 100% !important;
    width: 220px !important;
    max-width: 220px !important;
    object-fit: cover !important;
    z-index: 2 !important;
}

.cta.cta-border .row {
    width: 100% !important;
    margin: 0 !important;
}

.cta.cta-border .col-md-12 {
    padding: 0 !important;
}

.cta.cta-border .cta-content {
    position: relative !important;
    z-index: 3 !important;
    width: 100% !important;
    padding-left: 260px !important;
    padding-right: 30px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 25px !important;
    flex-wrap: nowrap !important;
}

.cta.cta-border .cta-text {
    text-align: right !important;
    margin: 0 !important;
    white-space: nowrap;
}
</style>
            <?php if ($ctaProduct): ?>

    <div class="container">

        <div
            class="cta cta-border mb-5"
            style="background-image: url(assets/images/demos/demo-4/bg-1.jpg);"
        >

            <!-- Dynamic Product Image -->
            <img
                src="uploads/products/<?php echo htmlspecialchars($ctaProduct['image']); ?>"
                alt="<?php echo htmlspecialchars($ctaProduct['name']); ?>"
                class="cta-img"
            >

            <div class="row justify-content-center">

                <div class="col-md-12">

                    <div class="cta-content">

                        <div class="cta-text text-right text-white">

                            <p>
                                Shop Today's Deals
                                <br>

                                <strong>
                                    <?php echo htmlspecialchars($ctaProduct['name']); ?>
                                </strong>
                            </p>

                        </div>

                        <a
    href="product-detail.php?id=<?php echo (int) $ctaProduct['id']; ?>"
    class="btn btn-primary btn-round"
>
    <span>
        Shop Now -
        $<?php echo number_format(
            (float) $ctaProduct['price'],
            2
        ); ?>
    </span>

    <i class="icon-long-arrow-right"></i>
</a>
                    </div>

                </div>

            </div>

        </div>

    </div>

<?php endif; ?><!-- End .container -->
<!-- Deals -->
            <div class="container">

    <div class="heading text-center mb-3">
        <h2 class="title">Deals & Outlet</h2>
        <p class="title-desc">Today's deal and more</p>
    </div>

    <div class="row">

        <?php if (!empty($dealProducts)): ?>

            <?php foreach ($dealProducts as $index => $deal): ?>

                <div class="col-lg-6 deal-col">

                    <div
                        class="deal"
                        style="background-image: url('assets/images/demos/demo-4/deal/bg-<?php echo $index + 1; ?>.jpg');"
                    >

                        <div class="deal-top">

                            <?php if ($index === 0): ?>

                                <h2>Deal of the Day.</h2>
                                <h4>Limited quantities.</h4>

                            <?php else: ?>

                                <h2>Your Exclusive Offers.</h2>
                                <h4>Sign in to see amazing deals.</h4>

                            <?php endif; ?>

                        </div>

                        <div class="deal-content">

                            <h3 class="product-title">

                                <a
                                    href="product-detail.php?id=<?php echo (int) $deal['id']; ?>"
                                >
                                    <?php echo htmlspecialchars($deal['name']); ?>
                                </a>

                            </h3>

                            <div class="product-price">

                              <?php
$originalPrice = (float) $deal['price'];
$discount = (float) $deal['discount_percentage'];

$dealPrice = $originalPrice - (
    $originalPrice * $discount / 100
);
?>

<div class="product-price">

    <span class="old-price">
        Rs. <?php echo number_format($originalPrice, 2); ?>
    </span>

    <span class="new-price">
        Rs. <?php echo number_format($dealPrice, 2); ?>
    </span>

</div>

                            </div>

                            <a
                                href="product-detail.php?id=<?php echo (int) $deal['id']; ?>"
                                class="btn btn-link"
                            >
                                <span>Shop Now</span>
                                <i class="icon-long-arrow-right"></i>
                            </a>

                        </div>

                        <div class="deal-bottom">

                           <div
    class="deal-countdown <?php echo $index === 0
        ? 'daily-deal-countdown'
        : 'offer-countdown'; ?>"
    data-until="<?php echo htmlspecialchars(
        date('Y-m-d H:i:s', strtotime($deal['deal_end']))
    ); ?>"
></div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="col-12 text-center">
                <p>No deals available.</p>
            </div>

        <?php endif; ?>

    </div>

    <div class="more-container text-center mt-1 mb-5">

        <a
            href="products.php"
            class="btn btn-outline-dark-2 btn-round btn-more"
        >
            <span>Shop more Outlet deals</span>
            <i class="icon-long-arrow-right"></i>
        </a>

    </div>

</div><!-- End .container -->

            <div class="container">
                <hr class="mb-0">
                <div class="owl-carousel mt-5 mb-5 owl-simple" data-toggle="owl" 
                    data-owl-options='{
                        "nav": false, 
                        "dots": false,
                        "margin": 30,
                        "loop": false,
                        "responsive": {
                            "0": {
                                "items":2
                            },
                            "420": {
                                "items":3
                            },
                            "600": {
                                "items":4
                            },
                            "900": {
                                "items":5
                            },
                            "1024": {
                                "items":6
                            }
                        }
                    }'>
                    <a href="#" class="brand">
                        <img src="assets/images/brands/1.png" alt="Brand Name">
                    </a>

                    <a href="#" class="brand">
                        <img src="assets/images/brands/2.png" alt="Brand Name">
                    </a>

                    <a href="#" class="brand">
                        <img src="assets/images/brands/3.png" alt="Brand Name">
                    </a>

                    <a href="#" class="brand">
                        <img src="assets/images/brands/4.png" alt="Brand Name">
                    </a>

                    <a href="#" class="brand">
                        <img src="assets/images/brands/5.png" alt="Brand Name">
                    </a>

                    <a href="#" class="brand">
                        <img src="assets/images/brands/6.png" alt="Brand Name">
                    </a>
                </div><!-- End .owl-carousel -->
            </div><!-- End .container -->
<!-- trending products  -->
            <div class="bg-light pt-5 pb-6" id="trending-products">
    <div class="container trending-products">

        <div class="heading heading-flex mb-3">
            <div class="heading-left">
                <h2 class="title">Trending Products</h2>
            </div>

            <div class="heading-right">
                <ul class="nav nav-pills nav-border-anim justify-content-center" role="tablist">

                    <li class="nav-item">
                        <a
                            class="nav-link active"
                            id="trending-link"
                            data-toggle="tab"
                            href="#trending-tab"
                            role="tab"
                            aria-controls="trending-tab"
                            aria-selected="true"
                        >
                            Trending Products
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link"
                            id="best-selling-link"
                            data-toggle="tab"
                            href="#best-selling-tab"
                            role="tab"
                            aria-controls="best-selling-tab"
                            aria-selected="false"
                        >
                            Best Selling
                        </a>
                    </li>

                </ul>
            </div>
        </div>

        <div class="row">

            <!-- LEFT BANNER -->
     <div class="col-xl-5col d-none d-xl-block">
    <div class="banner banner-overlay">
        <a href="products.php">

            <img
                src="assets/images/banners/3cols/shopnowimg.webp"
                alt="Shop Products"
            >

            <div class="banner-bottom-bar">
                <span>Shop Now</span>
                <i class="icon-long-arrow-right"></i>
            </div>

        </a>
    </div>
</div>

            <!-- PRODUCTS -->
            <div class="col-xl-4-5col">

                <div class="tab-content tab-content-carousel just-action-icons-sm">

                    <!-- TRENDING PRODUCTS -->
                    <div
                        class="tab-pane p-0 fade show active"
                        id="trending-tab"
                        role="tabpanel"
                        aria-labelledby="trending-link"
                    >

                        <div
                            class="owl-carousel owl-full carousel-equal-height carousel-with-shadow"
                            data-toggle="owl"
                            data-owl-options='{
                                "nav": true,
                                "dots": false,
                                "margin": 20,
                                "loop": false,
                                "responsive": {
                                    "0": {
                                        "items": 2
                                    },
                                    "480": {
                                        "items": 2
                                    },
                                    "768": {
                                        "items": 3
                                    },
                                    "992": {
                                        "items": 4
                                    }
                                }
                            }'
                        >

                            <?php if (!empty($trendingProducts)): ?>

                                <?php foreach ($trendingProducts as $product): ?>

                                    <div class="product product-2">

                                        <figure class="product-media">

                                            <?php if ((int) $product['stock'] > 0): ?>
                                                <span class="product-label label-circle label-new">
                                                    New
                                                </span>
                                            <?php else: ?>
                                                <span class="product-label label-circle label-sale">
                                                    Out
                                                </span>
                                            <?php endif; ?>

                                            <a href="product-detail.php?id=<?php echo (int) $product['id']; ?>">

                                                <?php if (!empty($product['image'])): ?>

                                                    <img
                                                        src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                                                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                        class="product-image"
                                                    >

                                                <?php else: ?>

                                                    <img
                                                        src="assets/images/demos/demo-4/products/product-1.jpg"
                                                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                        class="product-image"
                                                    >

                                                <?php endif; ?>

                                            </a>

                                            <div class="product-action">

                                                <?php if ((int) $product['stock'] > 0): ?>

                                                    <a
                                                       href="products.php?add_to_cart=<?php echo (int) $product['id']; ?>&redirect=index.php%23trending-products"
                                                        class="btn-product btn-cart"
                                                        title="Add to cart"
                                                    >
                                                        <span>Add to cart</span>
                                                    </a>

                                                <?php else: ?>

                                                    <span class="btn-product">
                                                        <span>Out of stock</span>
                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                        </figure>

                                        <div class="product-body">

                                            <div class="product-cat">
                                                <a href="products.php">
                                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                                </a>
                                            </div>

                                            <h3 class="product-title">
                                                <a href="product-detail.php?id=<?php echo (int) $product['id']; ?>">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                </a>
                                            </h3>

                                            <div class="product-price">
                                                $<?php echo number_format((float) $product['price'], 2); ?>
                                            </div>

                                            <?php if ((int) $product['stock'] > 0): ?>

                                                <div class="product-stock">
                                                    <small>
                                                        <?php echo (int) $product['stock']; ?> in stock
                                                    </small>
                                                </div>

                                            <?php else: ?>

                                                <div class="product-stock">
                                                    <small>
                                                        Out of stock
                                                    </small>
                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <div class="text-center py-5">
                                    <p>No trending products available.</p>
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>


                    <!-- BEST SELLING PRODUCTS -->
                    <div
                        class="tab-pane p-0 fade"
                        id="best-selling-tab"
                        role="tabpanel"
                        aria-labelledby="best-selling-link"
                    >

                        <div
                            class="owl-carousel owl-full carousel-equal-height carousel-with-shadow "
                            data-toggle="owl"
                            data-owl-options='{
                                "nav": true,
                                "dots": false,
                                "margin": 20,
                                "loop": false,
                                "responsive": {
                                    "0": {
                                        "items": 2
                                    },
                                    "480": {
                                        "items": 2
                                    },
                                    "768": {
                                        "items": 3
                                    },
                                    "992": {
                                        "items": 4
                                    }
                                }
                            }'
                        >

                            <?php if (!empty($bestSellingProducts)): ?>

                                <?php foreach ($bestSellingProducts as $product): ?>

                                    <div class="product product-2">

                                        <figure class="product-media">

                                            <?php if ((int) $product['sold_quantity'] > 0): ?>

                                                <span class="product-label label-circle label-top">
                                                    Best
                                                </span>

                                            <?php endif; ?>

                                            <a href="product-detail.php?id=<?php echo (int) $product['id']; ?>">

                                                <?php if (!empty($product['image'])): ?>

                                                    <img
                                                        src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                                                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                        class="product-image"
                                                    >

                                                <?php else: ?>

                                                    <img
                                                        src="assets/images/demos/demo-4/products/product-1.jpg"
                                                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                        class="product-image"
                                                    >

                                                <?php endif; ?>

                                            </a>

                                            <div class="product-action">

                                                <?php if ((int) $product['stock'] > 0): ?>

                                                    <a
                                                        href="products.php?add_to_cart=<?php echo (int) $product['id']; ?>&redirect=index.php%23trending-products"
                                                        class="btn-product btn-cart"
                                                        title="Add to cart"
                                                    >
                                                        <span>Add to cart</span>
                                                    </a>

                                                <?php else: ?>

                                                    <span class="btn-product">
                                                        <span>Out of stock</span>
                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                        </figure>

                                        <div class="product-body">

                                            <div class="product-cat">
                                                <a href="products.php">
                                                    <?php echo htmlspecialchars($product['category_name']); ?>
                                                </a>
                                            </div>

                                            <h3 class="product-title">
                                                <a href="product-detail.php?id=<?php echo (int) $product['id']; ?>">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                </a>
                                            </h3>

                                            <div class="product-price">
                                                $<?php echo number_format((float) $product['price'], 2); ?>
                                            </div>

                                            <div class="product-stock">
                                                <small>
                                                    <?php echo (int) $product['sold_quantity']; ?> sold
                                                </small>
                                            </div>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <div class="text-center py-5">
                                    <p>No sales data available yet.</p>
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>
</div><!-- End .bg-light pt-5 pb-6 -->

            <div class="mb-5"></div><!-- End .mb-5 -->

          

            <div class="mb-4"></div><!-- End .mb-4 -->

            <div class="container">
                <hr class="mb-0">
            </div><!-- End .container -->

            <div class="icon-boxes-container bg-transparent">
                <div class="container">
                    <div class="row">
                        <div class="col-sm-6 col-lg-3">
                            <div class="icon-box icon-box-side">
                                <span class="icon-box-icon text-dark">
                                    <i class="icon-rocket"></i>
                                </span>
                                <div class="icon-box-content">
                                    <h3 class="icon-box-title">Free Shipping</h3><!-- End .icon-box-title -->
                                    <p>Orders $50 or more</p>
                                </div><!-- End .icon-box-content -->
                            </div><!-- End .icon-box -->
                        </div><!-- End .col-sm-6 col-lg-3 -->

                        <div class="col-sm-6 col-lg-3">
                            <div class="icon-box icon-box-side">
                                <span class="icon-box-icon text-dark">
                                    <i class="icon-rotate-left"></i>
                                </span>

                                <div class="icon-box-content">
                                    <h3 class="icon-box-title">Free Returns</h3><!-- End .icon-box-title -->
                                    <p>Within 30 days</p>
                                </div><!-- End .icon-box-content -->
                            </div><!-- End .icon-box -->
                        </div><!-- End .col-sm-6 col-lg-3 -->

                        <div class="col-sm-6 col-lg-3">
                            <div class="icon-box icon-box-side">
                                <span class="icon-box-icon text-dark">
                                    <i class="icon-info-circle"></i>
                                </span>

                                <div class="icon-box-content">
                                    <h3 class="icon-box-title">Get 20% Off 1 Item</h3><!-- End .icon-box-title -->
                                    <p>when you sign up</p>
                                </div><!-- End .icon-box-content -->
                            </div><!-- End .icon-box -->
                        </div><!-- End .col-sm-6 col-lg-3 -->

                        <div class="col-sm-6 col-lg-3">
                            <div class="icon-box icon-box-side">
                                <span class="icon-box-icon text-dark">
                                    <i class="icon-life-ring"></i>
                                </span>

                                <div class="icon-box-content">
                                    <h3 class="icon-box-title">We Support</h3><!-- End .icon-box-title -->
                                    <p>24/7 amazing services</p>
                                </div><!-- End .icon-box-content -->
                            </div><!-- End .icon-box -->
                        </div><!-- End .col-sm-6 col-lg-3 -->
                    </div><!-- End .row -->
                </div><!-- End .container -->
            </div><!-- End .icon-boxes-container -->
        </main><!-- End .main -->
 <?php include '../includes/footer.php'; ?>
    </div><!-- End .page-wrapper -->
    <button id="scroll-top" title="Back to Top"><i class="icon-arrow-up"></i></button>


    <!-- Plugins JS File -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.hoverIntent.min.js"></script>
    <script src="assets/js/jquery.waypoints.min.js"></script>
    <script src="assets/js/superfish.min.js"></script>
    <script src="assets/js/owl.carousel.min.js"></script>
    <script src="assets/js/bootstrap-input-spinner.js"></script>
    <script src="assets/js/jquery.plugin.min.js"></script>
    <script src="assets/js/jquery.magnific-popup.min.js"></script>
    <script src="assets/js/jquery.countdown.min.js"></script>
    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>
    <script src="assets/js/demos/demo-4.js"></script>
</body>


<!-- molla/index-4.html  22 Nov 2019 09:54:18 GMT -->
</html>