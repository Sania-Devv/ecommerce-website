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

// CTA - Shop Today's Deals
$ctaProduct = null;

$ctaQuery = "
    SELECT
        p.id,
        p.name,
        p.price,
        p.discount_percentage,
        p.deal_start,
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
    LIMIT 8
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
/* FIX: Shop Now button cut on mobile */
@media (max-width: 767px) {
    .intro-slider .intro-content .btn,
    .intro-slider .intro-content a.btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: auto !important;
        max-width: 100% !important;
        min-width: 0 !important;
        height: auto !important;
        min-height: 44px !important;
        padding: 12px 18px !important;
        margin: 8px 0 12px !important;
        white-space: normal !important;
        overflow: visible !important;
        box-sizing: border-box !important;
        line-height: 1.4 !important;
        position: relative !important;
        z-index: 5 !important;
    }

    .intro-slider .intro-content,
    .intro-slider .intro-content .row,
    .intro-slider .intro-content .row > div {
        overflow: visible !important;
        height: auto !important;
        min-height: 0 !important;
    }

    .intro-slider .owl-stage-outer {
        overflow: hidden !important;
    }

    .intro-slider .intro-slide {
        height: auto !important;
        min-height: 0 !important;
        padding-bottom: 15px !important;
    }
}
/* ==========================
   MAIN SLIDER - RESPONSIVE
========================== */

.intro-slider-container {
    width: 100%;
    overflow: hidden;
}

.intro-slider .intro-slide {
    min-width: 0;
    box-sizing: border-box;
}

.intro-slider .intro-content {
    width: 100%;
    box-sizing: border-box;
    padding-top: 30px;
    padding-bottom: 30px;
}

.intro-slider .intro-content .row {
    row-gap: 20px;
}

/* Image */
.intro-slider .intro-slide-image {
    width: 100%;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.intro-slider .intro-slide-image img {
    display: block;
    width: 100%;
    max-width: 100%;
    max-height: 400px;
    height: auto;
    object-fit: contain;
    object-position: center;
}

/* Text area */
.intro-slider .intro-content .col-12 {
    min-width: 0;
}

.intro-slider .intro-subtitle {
    max-width: 100%;
    font-size: 18px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.intro-slider .intro-title {
    max-width: 100%;
    font-size: clamp(28px, 3vw, 46px);
    line-height: 1.15;
    overflow-wrap: anywhere;
    word-break: normal;
    margin-bottom: 15px;
}

.intro-slider .intro-price {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 6px 12px;
    margin-bottom: 20px;
    overflow-wrap: anywhere;
}

.intro-slider .intro-old-price {
    font-size: 16px;
}

.intro-slider .intro-price .text-primary {
    font-size: 25px;
    font-weight: 600;
}

.intro-slider .intro-content .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    max-width: 100%;
    white-space: normal;
    text-align: center;
    overflow-wrap: anywhere;
}

/* ==========================
   TABLET
========================== */

@media (min-width: 768px) and (max-width: 991px) {

    .intro-slider .intro-content {
        padding-top: 20px;
        padding-bottom: 20px;
    }

    .intro-slider .intro-content .row {
        align-items: center !important;
    }

    .intro-slider .intro-slide-image img {
        max-height: 280px !important;
    }

    .intro-slider .intro-subtitle {
        font-size: 15px;
        line-height: 1.4;
    }

    .intro-slider .intro-title {
        font-size: 30px;
        line-height: 1.2;
        margin-bottom: 12px;
    }

    .intro-slider .intro-price {
        margin-bottom: 15px;
    }

    .intro-slider .intro-price .text-primary {
        font-size: 21px;
    }

    .intro-slider .intro-content .btn {
        padding: 10px 16px;
        font-size: 13px;
    }
}

/* ==========================
   MOBILE - RESPONSIVE FIX
========================== */

@media (max-width: 767px) {

    .intro-slider .intro-content {
        width: 100% !important;
        max-width: 100% !important;
        height: auto !important;
        min-height: 0 !important;
        padding: 20px 15px 30px !important;
        box-sizing: border-box !important;
        overflow: visible !important;
    }

    .intro-slider .intro-content .row {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        row-gap: 10px;
        width: 100% !important;
        margin: 0 !important;
    }

    .intro-slider .intro-content .row > [class*="col-"] {
        width: 100% !important;
        max-width: 100% !important;
        flex: 0 0 100% !important;
        min-width: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-top: 0 !important;
        box-sizing: border-box !important;
    }

    /* Product image */
    .intro-slider .intro-slide-image {
        width: 100% !important;
        min-width: 0 !important;
        margin-bottom: 10px;
    }

    .intro-slider .intro-slide-image img {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        height: auto !important;
        max-height: 210px !important;
        object-fit: contain !important;
    }

    /* Subtitle */
    .intro-slider .intro-content .intro-subtitle {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        height: auto !important;
        max-height: none !important;
        margin-bottom: 8px !important;
        font-size: 14px !important;
        line-height: 1.5 !important;
        white-space: normal !important;
        overflow: visible !important;
        overflow-wrap: anywhere !important;
        box-sizing: border-box !important;
    }

    /* Main title */
    .intro-slider .intro-content .intro-title {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        height: auto !important;
        max-height: none !important;
        margin-bottom: 12px !important;
        font-size: clamp(24px, 7vw, 32px) !important;
        line-height: 1.25 !important;
        white-space: normal !important;
        overflow: visible !important;
        overflow-wrap: anywhere !important;
        word-break: normal !important;
        box-sizing: border-box !important;
    }

    /* Prices */
    .intro-slider .intro-content .intro-price {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: baseline !important;
        gap: 5px 8px !important;
        width: 100% !important;
        height: auto !important;
        margin-bottom: 15px !important;
        overflow: visible !important;
    }

    .intro-slider .intro-content .intro-price .text-primary {
        font-size: 21px !important;
    }

    /* Button */
    .intro-slider .intro-content .btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px;
        width: auto !important;
        max-width: 100% !important;
        height: auto !important;
        min-height: 42px;
        padding: 10px 16px !important;
        font-size: 13px !important;
        line-height: 1.4 !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
        box-sizing: border-box !important;
    }
}

/* ==========================
   SMALL MOBILE
========================== */

@media (max-width: 400px) {

    .intro-slider .intro-content {
        padding: 16px 12px 30px !important;
    }

    .intro-slider .intro-slide-image img {
        max-height: 165px !important;
    }

    .intro-slider .intro-content .intro-title {
        font-size: 24px !important;
        line-height: 1.3 !important;
    }

    .intro-slider .intro-content .intro-subtitle {
        font-size: 13px !important;
        line-height: 1.5 !important;
    }

    .intro-slider .intro-content .intro-price .text-primary {
        font-size: 19px !important;
    }
}


/* ===== FINAL MOBILE FIX: height clipping + hidden button ===== */
@media (max-width: 767px) {

    /* Sab containers ki fixed/inline height khatam */
    .intro-slider-container,
    .intro-slider,
    .intro-slider .owl-stage-outer,
    .intro-slider .owl-stage,
    .intro-slider .owl-item,
    .intro-slider .intro-slide {
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
    }

    /* Clipping band: sirf horizontal slide ke liye hidden, vertical nahi kaatna */
    .intro-slider-container {
        overflow: visible !important;
    }
    .intro-slider .owl-stage-outer {
        overflow: hidden !important;
        padding-bottom: 0 !important;
    }

    /* Slide ko column layout mein rakhein, neeche padding ke saath */
    .intro-slider .intro-slide {
        display: block !important;
        padding: 0 0 30px !important;
    }

    .intro-slider .intro-content {
        height: auto !important;
        min-height: 0 !important;
        overflow: visible !important;
        padding: 20px 15px 35px !important;
    }

    .intro-slider .intro-content .row {
        flex-direction: column !important;
        align-items: stretch !important;
        height: auto !important;
        overflow: visible !important;
    }

    .intro-slider .intro-content .row > [class*="col-"] {
        height: auto !important;
        overflow: visible !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
    }

    /* Text visible */
    .intro-slider .intro-subtitle,
    .intro-slider .intro-title,
    .intro-slider .intro-price {
        position: static !important;
        height: auto !important;
        max-height: none !important;
        overflow: visible !important;
        opacity: 1 !important;
        visibility: visible !important;
        transform: none !important;
    }

    /* Button hamesha visible */
    .intro-slider .intro-content .btn,
    .intro-slider .intro-content a.btn {
        display: inline-flex !important;
        position: relative !important;
        opacity: 1 !important;
        visibility: visible !important;
        transform: none !important;
        float: none !important;
        margin: 10px 0 5px !important;
        z-index: 10 !important;
    }
}
/* =========================================
   TRENDING PRODUCTS - RESPONSIVE FIX
========================================= */

#trending-products {
    width: 100%;
    overflow: hidden;
}

#trending-products > .container {
    width: 100%;
}

/* Heading and tabs */
#trending-products .heading-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px 20px;
}

#trending-products .heading-left,
#trending-products .heading-right {
    min-width: 0;
}

#trending-products .heading-left {
    flex: 1 1 220px;
}

#trending-products .heading-right {
    flex: 0 1 auto;
}

#trending-products .heading .title {
    margin-bottom: 0;
    overflow-wrap: anywhere;
}

#trending-products .heading-right .nav {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

#trending-products .heading-right .nav-link {
    white-space: normal;
    text-align: center;
}

/* Main row */
#trending-products > .container > .row {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
}

/* Mobile and tablet: products use full width */
#trending-products .col-xl-5col,
#trending-products .col-xl-4-5col {
    min-width: 0;
}

#trending-products .col-xl-4-5col {
    flex: 0 0 100%;
    max-width: 100%;
    width: 100%;
}

/* Desktop banner */
#trending-products .col-xl-5col .banner {
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 300px;
    overflow: hidden;
    background-color: #f5f5f5;
}

#trending-products .col-xl-5col .banner a {
    display: block;
    position: relative;
    width: 100%;
    height: 100%;
}

#trending-products .col-xl-5col .banner img {
    display: block;
    width: 100%;
    height: 100%;
    min-height: 300px;
    object-fit: cover;
    object-position: center top;
}

#trending-products .banner-bottom-bar {
    position: absolute;
    bottom: 0;
    left: 0;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 14px 18px;
    background-color: #3399cc;
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    transition: background-color 0.25s ease;
}

#trending-products .banner:hover .banner-bottom-bar {
    background-color: #2b85b3;
}

/* Product column and carousel */
#trending-products .tab-content-carousel {
    width: 100%;
    min-width: 0;
}

#trending-products .tab-content-carousel > .tab-pane {
    width: 100%;
}

/* Let Bootstrap handle tab visibility */
#trending-products .tab-content-carousel > .tab-pane:not(.active) {
    display: none;
}

#trending-products .tab-content-carousel > .tab-pane.active {
    display: block;
}

#trending-products .owl-carousel {
    width: 100%;
    min-width: 0;
}

#trending-products .owl-carousel .owl-stage-outer {
    width: 100%;
}

#trending-products .owl-carousel .owl-item {
    box-sizing: border-box;
}

#trending-products .owl-carousel .product {
    width: 100%;
    max-width: 100%;
    min-width: 0;
}

/* Prevent long product names from stretching cards */
#trending-products .product-body {
    min-width: 0;
}

#trending-products .product-title {
    overflow-wrap: anywhere;
    word-break: normal;
}

#trending-products .product-cat {
    overflow-wrap: anywhere;
}

/* Tablet */
@media (max-width: 1199px) {
    #trending-products .col-xl-5col {
        display: none !important;
    }

    #trending-products .col-xl-4-5col {
        flex: 0 0 100%;
        max-width: 100%;
        width: 100%;
    }
}

/* ==========================
   MOBILE WIDTH 401px–767px
========================== */

@media (min-width: 401px) and (max-width: 767px) {

    .intro-slider .intro-content {
        width: 100% !important;
        max-width: 100% !important;
        padding: 22px 20px 32px !important;
        box-sizing: border-box !important;
    }

    .intro-slider .intro-content .row {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        margin: 0 !important;
        row-gap: 12px !important;
    }

    .intro-slider .intro-content .row > [class*="col-"] {
        width: 100% !important;
        max-width: 100% !important;
        flex: 0 0 100% !important;
        padding: 0 !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .intro-slider .intro-slide-image {
        width: 100% !important;
        margin: 0 auto 8px !important;
    }

    .intro-slider .intro-slide-image img {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        height: auto !important;
        max-height: 190px !important;
        object-fit: contain !important;
    }

    .intro-slider .intro-content .intro-subtitle {
        font-size: 14px !important;
        line-height: 1.5 !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
    }

    .intro-slider .intro-content .intro-title {
        font-size: 29px !important;
        line-height: 1.25 !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
        word-break: normal !important;
        height: auto !important;
        max-height: none !important;
        margin-bottom: 12px !important;
    }

    .intro-slider .intro-content .intro-price {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 6px 10px !important;
    }

    .intro-slider .intro-content .intro-price .text-primary {
        font-size: 21px !important;
    }

    .intro-slider .intro-content .btn {
        max-width: 100% !important;
        white-space: normal !important;
        padding: 10px 15px !important;
    }
}


/* Small tablets and mobile */
@media (max-width: 767px) {
    #trending-products {
        padding-top: 30px !important;
        padding-bottom: 35px !important;
    }

    #trending-products > .container {
        padding-left: 15px;
        padding-right: 15px;
    }

    #trending-products .heading-flex {
        align-items: flex-start;
        flex-direction: column;
        margin-bottom: 20px;
    }

    #trending-products .heading-left,
    #trending-products .heading-right {
        width: 100%;
        flex: 0 0 100%;
    }

    #trending-products .heading .title {
        font-size: 22px;
        line-height: 1.3;
    }

    #trending-products .heading-right .nav {
        justify-content: flex-start;
        width: 100%;
    }

    #trending-products .heading-right .nav-link {
        padding: 8px 12px;
        font-size: 13px;
    }

    #trending-products .owl-carousel .product {
        margin-bottom: 5px;
    }
}

/* Very small mobile screens */
@media (max-width: 400px) {
    #trending-products .heading .title {
        font-size: 20px;
    }

    #trending-products .heading-right .nav-link {
        padding: 7px 9px;
        font-size: 12px;
    }
}

/* Desktop: banner 20%, products 80% */
@media (min-width: 1200px) {
    #trending-products .col-xl-5col {
        display: flex;
        flex: 0 0 20%;
        max-width: 20%;
    }

    #trending-products .col-xl-4-5col {
        flex: 0 0 80%;
        max-width: 80%;
        width: 80%;
    }
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

                            Rs.<?php echo number_format((float) $product['price'], 2); ?>

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

                                Rs.<?php echo number_format((float) $product['price'], 2); ?>

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

<!-- Shop Today's Deals -->

<style>
/* ==========================
   SHOP TODAY'S DEALS BANNER
========================== */

.cta.cta-border {
    position: relative !important;
    display: flex !important;
    align-items: center !important;
    width: 100%;
    min-height: 190px;
    margin-left: 0 !important;
    overflow: hidden !important;
    box-sizing: border-box;
    border-radius: 8px;
    background-size: cover;
    background-position: center;
}

/* Product image */
.cta.cta-border img.cta-img {
    position: absolute !important;
    top: 0 !important;
    bottom: 0 !important;
    left: 0 !important;

    width: 220px !important;
    max-width: 220px !important;
    height: 100% !important;

    object-fit: contain !important;
    object-position: center;
    padding: 12px;
    box-sizing: border-box;
    z-index: 2 !important;
}

/* Bootstrap row */
.cta.cta-border > .row {
    width: 100% !important;
    margin: 0 !important;
}

.cta.cta-border .col-md-12 {
    width: 100%;
    padding: 0 !important;
}

/* Text and button area */
.cta.cta-border .cta-content {
    position: relative !important;
    z-index: 3 !important;

    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 20px;

    width: 100% !important;
    min-width: 0;
    padding: 25px 30px 25px 250px !important;
    box-sizing: border-box;

    flex-wrap: wrap !important;
}

/* Product name and heading */
.cta.cta-border .cta-text {
    flex: 1 1 200px;
    min-width: 0;
    margin: 0 !important;
    text-align: right !important;
    color: #fff;
    overflow-wrap: anywhere;
}

.cta.cta-border .cta-text p {
    margin: 0;
    line-height: 1.6;
    font-size: 16px;
}

.cta.cta-border .cta-text strong {
    display: inline-block;
    margin-top: 5px;
    font-size: 22px;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

/* Shop Now button */
.cta.cta-border .cta-content > .btn {
    flex: 0 0 auto;
    white-space: normal;
    max-width: 100%;
    text-align: center;
}

/* ==========================
   TABLET - RESPONSIVE FIX
========================== */

@media (min-width: 768px) and (max-width: 991px) {

    .cta.cta-border {
        min-height: 145px !important;
        height: auto !important;
    }

    .cta.cta-border img.cta-img {
        width: 145px !important;
        max-width: 145px !important;
        height: 100% !important;
        padding: 8px !important;
        object-fit: contain !important;
    }

    .cta.cta-border .cta-content {
        gap: 10px !important;
        padding: 16px 14px 16px 160px !important;
        flex-wrap: nowrap !important;
    }

    .cta.cta-border .cta-text {
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }

    .cta.cta-border .cta-text p {
        font-size: 13px !important;
        line-height: 1.4 !important;
    }

    .cta.cta-border .cta-text strong {
        font-size: 17px !important;
        line-height: 1.3 !important;
    }

    .cta.cta-border .cta-content > .btn {
        flex: 0 0 auto !important;
        padding: 9px 10px !important;
        font-size: 11px !important;
        white-space: normal !important;
    }
}


/* ==========================
   MOBILE
========================== */

@media (max-width: 767px) {
    .cta.cta-border {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        min-height: 0;
        padding: 0 !important;
        background-position: center;
    }

    .cta.cta-border img.cta-img {
        position: relative !important;
        top: auto !important;
        right: auto !important;
        bottom: auto !important;
        left: auto !important;

        display: block;
        width: 100% !important;
        max-width: 100% !important;
        height: 180px !important;

        margin: 0;
        padding: 15px;
        object-fit: contain !important;
        background: rgba(255, 255, 255, 0.94);
        box-sizing: border-box;
    }

    .cta.cta-border > .row {
        width: 100% !important;
    }

    .cta.cta-border .cta-content {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 15px;

        width: 100% !important;
        padding: 22px 16px !important;
        box-sizing: border-box;
    }

    .cta.cta-border .cta-text {
        flex: 0 1 auto;
        width: 100%;
        text-align: center !important;
    }

    .cta.cta-border .cta-text p {
        font-size: 15px;
    }

    .cta.cta-border .cta-text strong {
        font-size: 20px;
    }

    .cta.cta-border .cta-content > .btn {
        align-self: center;
        max-width: 100%;
        white-space: normal;
    }
}

/* Small mobile */
@media (max-width: 400px) {
    .cta.cta-border img.cta-img {
        height: 155px !important;
    }

    .cta.cta-border .cta-content {
        padding: 18px 12px !important;
    }

    .cta.cta-border .cta-text strong {
        font-size: 18px;
    }
}
</style>

<?php if (!empty($ctaProduct)): ?>

    <?php
        $ctaOriginalPrice = (float) $ctaProduct['price'];
        $ctaDiscount = (float) $ctaProduct['discount_percentage'];

        $ctaDealPrice = $ctaOriginalPrice
            - ($ctaOriginalPrice * $ctaDiscount / 100);
    ?>

    <div class="container">

        <div
            class="cta cta-border mb-5"
            style="background-image: url('assets/images/demos/demo-4/bg-1.jpg');"
        >

            <!-- Active Deal Product Image -->
            <img
                src="uploads/products/<?php echo htmlspecialchars($ctaProduct['image']); ?>"
                alt="<?php echo htmlspecialchars($ctaProduct['name']); ?>"
                class="cta-img"
            >

            <div class="row justify-content-center">
                <div class="col-md-12">

                    <div class="cta-content">

                        <div class="cta-text">

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
                                Shop Now - Rs.
                                <?php echo number_format($ctaDealPrice, 2); ?>
                            </span>

                            <i class="icon-long-arrow-right"></i>
                        </a>

                    </div>

                </div>
            </div>

        </div>

    </div>

<?php endif; ?>

<!-- End Shop Today's Deals -->



<!-- Deals & Outlet -->

<style>
/* ==============================
   DEALS & OUTLET - BASE
============================== */

.deal-carousel {
    width: 100%;
    padding: 0 5px 15px;
    box-sizing: border-box;
}

.deal-col {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    margin-bottom: 0;
    box-sizing: border-box;
}

/* Desktop: separate text and image columns */
.deal-carousel .deal-col .deal {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
    grid-template-rows: auto 1fr auto;
    column-gap: 16px;
    align-items: start;

    width: 100%;
    height: 100%;
    min-height: 400px;
    padding: 25px;
    overflow: hidden;
    box-sizing: border-box;
    border-radius: 8px;
    background: #f5f5f5;
}

/* Product image stays inside its own column */
.deal-carousel .deal-col .deal-product-image {
    position: static;
    grid-column: 2;
    grid-row: 1 / 4;

    display: block;
    width: 100%;
    max-width: 250px;
    height: 250px;
    margin: auto;
    padding: 0;

    transform: none;
    object-fit: contain;
    align-self: center;
    justify-self: center;
    box-sizing: border-box;
    z-index: 1;
}

/* Text content stays in the left column */
.deal-carousel .deal-col .deal-top,
.deal-carousel .deal-col .deal-content,
.deal-carousel .deal-col .deal-bottom {
    position: relative;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    padding: 0;
    box-sizing: border-box;
    z-index: 2;
}

.deal-carousel .deal-col .deal-top {
    grid-column: 1;
    grid-row: 1;
}

.deal-carousel .deal-col .deal-content {
    grid-column: 1;
    grid-row: 2;
    align-self: start;
    margin-top: 15px;
}

.deal-carousel .deal-col .deal-bottom {
    grid-column: 1;
    grid-row: 3;
    align-self: end;
    margin-top: 18px;
}

/* Headings and product name */
.deal-carousel .deal-col .deal-top h2 {
    font-size: 26px;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.deal-carousel .deal-col .deal-top h4 {
    font-size: 16px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.deal-carousel .deal-col .product-title {
    line-height: 1.4;
    overflow-wrap: anywhere;
    word-break: normal;
}

/* Prices wrap instead of overlapping */
.deal-carousel .deal-col .product-price {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 5px 10px;
}

.deal-carousel .deal-col .product-price span {
    overflow-wrap: anywhere;
}

/* ==============================
   OWL CAROUSEL
============================== */

.deal-carousel .owl-stage {
    display: flex;
    align-items: stretch;
}

.deal-carousel .owl-item {
    display: flex;
    min-width: 0;
    padding: 0 10px;
    box-sizing: border-box;
}

.deal-carousel .owl-item > .deal-col {
    display: flex;
    flex: 1 1 auto;
    min-width: 0;
    width: 100%;
    max-width: 100%;
}

.deal-carousel .owl-item > .deal-col > .deal {
    flex: 1 1 auto;
    min-width: 0;
}

.deal-carousel .owl-nav {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin: 10px 10px 0;
}

.deal-carousel .owl-nav button {
    width: 38px;
    height: 38px;
    border: 1px solid #ddd !important;
    border-radius: 50%;
    background: #fff !important;
    color: #333 !important;
    font-size: 22px !important;
}

.deal-carousel .owl-nav button:hover {
    background: #3399cc !important;
    color: #fff !important;
}

.deal-carousel .owl-dots {
    margin-top: 8px;
    text-align: center;
}

/* ==============================
   TABLET
============================== */

@media (min-width: 768px) and (max-width: 991px) {

    .deal-carousel .deal-col .deal {
        grid-template-columns: minmax(0, 1fr) minmax(0, 0.8fr);
        column-gap: 10px;
        min-height: 410px;
        padding: 18px;
    }

    .deal-carousel .deal-col .deal-product-image {
        max-width: 180px;
        height: 190px;
    }

    .deal-carousel .deal-col .deal-top h2 {
        font-size: 21px;
        line-height: 1.3;
    }

    .deal-carousel .deal-col .deal-top h4 {
        font-size: 14px;
        line-height: 1.5;
    }

    .deal-carousel .deal-col .product-title {
        font-size: 17px;
    }

    .deal-carousel .deal-col .product-price {
        gap: 5px;
    }
}

/* ==============================
   MOBILE
============================== */

@media (max-width: 767px) {

    .deal-carousel .deal-col .deal {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        min-height: 0;
        height: auto;
        padding: 20px 16px;
        gap: 0;
    }

    .deal-carousel .deal-col .deal-top {
        order: 1;
        width: 100%;
        margin: 0;
    }

    .deal-carousel .deal-col .deal-product-image {
        position: relative;
        order: 2;

        width: 100%;
        max-width: 100%;
        height: 210px;
        margin: 15px 0;
        padding: 10px;

        transform: none;
        object-fit: contain;
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
    }

    .deal-carousel .deal-col .deal-content {
        order: 3;
        width: 100%;
        margin-top: 0;
    }

    .deal-carousel .deal-col .deal-bottom {
        order: 4;
        width: 100%;
        margin-top: 15px;
    }

    .deal-carousel .deal-col .deal-top,
    .deal-carousel .deal-col .deal-content,
    .deal-carousel .deal-col .deal-bottom {
        max-width: 100%;
        padding: 0;
    }

    .deal-carousel .deal-col .deal-top h2 {
        font-size: 22px;
        line-height: 1.3;
    }

    .deal-carousel .deal-col .deal-top h4 {
        font-size: 15px;
        line-height: 1.5;
    }

    .deal-carousel .deal-col .product-title {
        font-size: 18px;
        line-height: 1.4;
    }

    .deal-carousel .owl-item {
        padding: 0 5px;
    }

    .deal-carousel .owl-nav {
        justify-content: center;
    }
}

/* ==============================
   SMALL MOBILE
============================== */

@media (max-width: 400px) {

    .deal-carousel .deal-col .deal {
        padding: 16px 12px;
    }

    .deal-carousel .deal-col .deal-product-image {
        height: 180px;
    }

    .deal-carousel .deal-col .deal-top h2 {
        font-size: 20px;
    }

    .deal-carousel .deal-col .product-title {
        font-size: 16px;
    }
}
</style>



<div class="container"> 
<div class="heading text-center mb-3"> 
<h2 class="title">Deals &amp; Outlet</h2> 
<p class="title-desc">Today's deal and more</p> </div> 
<?php if (!empty($dealProducts)): ?> 
<div class="deal-carousel owl-carousel owl-theme"> 
<?php foreach ($dealProducts as $index => $deal): ?> 
<?php $dealImage = ''; if (!empty($deal['image'])) 
{ $dealImage = 'uploads/products/' . $deal['image']; }
 $originalPrice = (float) $deal['price'];
 $discount = (float) $deal['discount_percentage']; 
$dealPrice = $originalPrice - ($originalPrice * $discount / 100); ?> 
<div class="deal-col"> 
<div class="deal"> 
<img src="<?php echo htmlspecialchars($dealImage); ?>" 
alt="<?php echo htmlspecialchars($deal['name']); ?>"
 class="deal-product-image" > 
<div class="deal-top"> 
<?php if ($index === 0): ?>
 <h2>Deal of the Day.</h2> 
<h4>Limited quantities.</h4>  
<?php else: ?> <h2>Your Exclusive Offers.</h2> 
<h4>Sign in to see amazing deals.</h4> 
<?php endif; ?> 
</div> 
<div class="deal-content"> 
<h3 class="product-title">
 <a href="product-detail.php?id=<?php echo (int) $deal['id']; ?>"> 
<?php echo htmlspecialchars($deal['name']); ?> </a> 
</h3> <div class="product-price"> 
<span class="old-price">
 Rs. <?php echo number_format($originalPrice, 2); ?> 
</span> 
<span class="new-price"> Rs. <?php echo number_format($dealPrice, 2); ?> </span>
 </div> 
<a href="product-detail.php?id=<?php echo (int) $deal['id']; ?>" 
class="btn btn-link" > 
<span>Shop Now</span>
 <i class="icon-long-arrow-right"></i> </a> 
</div> <div class="deal-bottom"> 
<div class="deal-countdown 
<?php echo $index === 0 ? 'daily-deal-countdown' : 'offer-countdown'; ?>" 
data-until="<?php echo htmlspecialchars( date('Y-m-d H:i:s', strtotime($deal['deal_end'])) ); ?>" >
</div>
 </div>
 </div>
 </div> 
<?php endforeach; ?> 
</div> <?php else: ?> 
<div class="text-center"> 
<p>No deals available.</p>
 </div> <?php endif; ?> 
</div> <div class="more-container text-center mt-1 mb-5"> 
<a href="products.php" class="btn btn-outline-dark-2 btn-round btn-more"> 
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
                                                Rs.<?php echo number_format((float) $product['price'], 2); ?>
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
                                                Rs.<?php echo number_format((float) $product['price'], 2); ?>
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

<script>
$(document).ready(function () {
    var $deals = $('.deal-carousel');

    if (
        $deals.length &&
        $.fn.owlCarousel &&
        !$deals.hasClass('owl-loaded')
    ) {
        $deals.owlCarousel({
            loop: false,
            margin: 20,
            nav: true,
            dots: true,
            autoplay: false,
            smartSpeed: 500,
            navText: [
                '<i class="icon-angle-left"></i>',
                '<i class="icon-angle-right"></i>'
            ],
            responsive: {
                0: {
                    items: 1
                },
                768: {
                    items: 2
                }
            }
        });
    }
});
</script>


</body>


<!-- molla/index-4.html  22 Nov 2019 09:54:18 GMT -->
</html>