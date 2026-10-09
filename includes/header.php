
<?php

require_once __DIR__ . '/../core/Sessions.php';
require_once __DIR__ . '/../config/database.php';

if (
    isset($_SESSION['user_role']) &&
    $_SESSION['user_role'] === 'admin'
) {
    header('Location: ../admin/index.php');
    exit;
}


/* =========================================================
   HEADER CATEGORIES
   ========================================================= */

$headerCategories = [];

$categoryQuery = "
    SELECT id, name
    FROM categories
    WHERE status = 1
    ORDER BY name ASC
";

$categoryResult = $mysqli->query($categoryQuery);

if ($categoryResult) {

    while ($category = $categoryResult->fetch_assoc()) {

        $headerCategories[] = $category;

    }
}


/* =========================================================
   USER SESSION
   ========================================================= */

$isLoggedIn = isset($_SESSION['user_id']);

$userName = $_SESSION['user_name'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';


/* =========================================================
   HEADER CART
   ========================================================= */

$headerCartItems = [];
$headerCartCount = 0;
$headerCartTotal = 0;


if ($isLoggedIn) {

    $userId = (int) $_SESSION['user_id'];

    $cartStmt = $mysqli->prepare("
        SELECT
            ci.quantity,
            p.id AS product_id,
            p.name,
            p.price,
            p.image
        FROM cart c
        INNER JOIN cart_items ci
            ON c.id = ci.cart_id
        INNER JOIN products p
            ON ci.product_id = p.id
        WHERE c.user_id = ?
        ORDER BY ci.id DESC
    ");

    if ($cartStmt) {

        $cartStmt->bind_param("i", $userId);
        $cartStmt->execute();

        $cartResult = $cartStmt->get_result();

        while ($cartItem = $cartResult->fetch_assoc()) {

            /*
             * Product images are now stored as JSON:
             *
             * ["5/abc.webp","5/xyz.webp"]
             *
             * Get the first image for header cart.
             *
             * Also supports old single-image format.
             */

            $cartItem['first_image'] = '';

            if (!empty($cartItem['image'])) {

                $decodedImages = json_decode(
                    $cartItem['image'],
                    true
                );

                if (is_array($decodedImages)) {

                    $cartItem['first_image'] =
                        $decodedImages[0] ?? '';

                } else {

                    // Backward compatibility
                    $cartItem['first_image'] =
                        $cartItem['image'];

                }
            }


            $headerCartItems[] = $cartItem;


            $headerCartCount +=
                (int) $cartItem['quantity'];


            $headerCartTotal +=
                (float) $cartItem['price'] *
                (int) $cartItem['quantity'];
        }

        $cartStmt->close();
    }
}

?>

<style>

/* =========================================================
   HEADER SEARCH - CUSTOM
   ========================================================= */

.header-middle > .container {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.header-middle .header-left {
    display: flex;
    align-items: center;
}

.header-middle .header-right {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    margin-left: auto;
}


/* =========================================================
   SHOPMART LOGO
   Molla Blue
   ========================================================= */

.header-middle .logo.brand-text-logo {
    display: inline-flex;
    align-items: center;
    text-decoration: none !important;
}

.brand-text-logo .brand-name {
    font-size: 26px;
    font-weight: 800;
    letter-spacing: -0.5px;
    color: #222;
    line-height: 1;
}

.brand-text-logo .brand-name span {
    color: #3399ff;
}

.header-middle .logo.brand-text-logo:hover .brand-name span {
    color: #3399ff;
}


/* =========================================================
   CUSTOM SEARCH
   ========================================================= */

.custom-header-search {
    position: relative;
    width: 42px;
    height: 48px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-shrink: 0;
}

.custom-search-toggle {
    width: 42px;
    height: 42px;

    padding: 0;
    margin: 0;

    border: 0;
    outline: none;

    background: transparent;

    display: flex;
    align-items: center;
    justify-content: center;

    cursor: pointer;

    color: #222;

    position: relative;
    z-index: 1002;
}

.custom-search-toggle:hover {
    color: #3399ff;
}

.custom-search-toggle:focus {
    outline: none;
}

.custom-search-toggle i {
    font-size: 21px;
    line-height: 1;
}


/* =========================================================
   SEARCH FORM
   ========================================================= */

.custom-search-form {
    position: absolute;

    top: 50%;
    right: 0;

    width: 0;
    height: 46px;

    transform: translateY(-50%);

    opacity: 0;
    visibility: hidden;

    overflow: hidden;

    display: flex;
    align-items: center;

    background: #fff;

    border: 1px solid #dcdcdc;
    border-radius: 24px;

    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.10);

    z-index: 1001;

    transition:
        width 0.25s ease,
        opacity 0.2s ease,
        visibility 0.2s ease;
}

.custom-header-search.is-open .custom-search-form {
    width: 280px;

    opacity: 1;
    visibility: visible;
}

.custom-header-search.is-open .custom-search-toggle {
    opacity: 0;
    visibility: hidden;
}

.custom-search-form input {
    width: 100%;
    height: 44px;

    margin: 0;
    padding: 0 48px 0 16px;

    border: 0 !important;
    outline: none !important;

    background: transparent !important;

    box-shadow: none !important;

    font-family: inherit;
    font-size: 14px;
    color: #333;
}

.custom-search-form input::placeholder {
    color: #999;
}

.custom-search-submit {
    position: absolute;

    top: 0;
    right: 0;

    width: 45px;
    height: 44px;

    padding: 0;
    margin: 0;

    border: 0;
    outline: none;

    background: transparent;

    display: flex;
    align-items: center;
    justify-content: center;

    cursor: pointer;

    color: #222;
}

.custom-search-submit:hover {
    color: #3399ff;
}

.custom-search-submit:focus {
    outline: none;
}

.custom-search-submit i {
    font-size: 18px;
}


/* =========================================================
   CART
   ========================================================= */

.header-middle .cart-dropdown {
    margin: 0 !important;
    padding: 0 !important;
    flex-shrink: 0;
}

.header-middle .cart-dropdown > a {
    margin: 0 !important;
}


/* =========================================================
   REMOVE OLD MOLLA DESKTOP SEARCH
   ========================================================= */

.header-middle .header-center {
    display: none !important;
}


/* =========================================================
   USER ACCOUNT
   ========================================================= */

.header-top-account .dropdown-toggle::after {
    display: none !important;
}

.header-top-account .header-account {
    position: relative;
}

.header-top-account .header-account > a {
    padding: 4px 0;
}

.header-top-account .header-account > a i.icon-angle-down {
    font-size: 12px;
    margin-left: 2px;
    transition: transform 0.2s ease;
}

.header-top-account .header-account.show > a i.icon-angle-down {
    transform: rotate(180deg);
}


/* =========================================================
   ACCOUNT DROPDOWN
   ========================================================= */

.header-top-account .dropdown-menu {
    position: absolute !important;
    top: calc(100% + 12px) !important;
    right: 0 !important;
    left: auto !important;
    transform: none !important;
    margin: 0 !important;

    min-width: 170px;

    padding: 8px 0;

    border: none;
    border-radius: 8px;

    box-shadow: 0 8px 24px rgba(0,0,0,0.12);

    z-index: 1060 !important;
}

.header-top-account .dropdown-menu a {
    display: flex !important;
    align-items: center;
    gap: 8px;

    padding: 9px 18px !important;

    font-size: 13px;

    color: #444 !important;

    transition: all 0.2s ease;
}

.header-top-account .dropdown-menu a:hover {
    background-color: #f7f7f7;

    color: #3399ff !important;

    padding-left: 22px !important;
}


/* =========================================================
   TOP ACCOUNT
   ========================================================= */

.header-top-account {
    display: flex;
    align-items: center;
}

.header-top-account a {
    color: #333;
}

.header-top-account a:hover {
    color: #3399ff;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 991px) {

    .header-middle > .container {
        min-height: 70px;
    }

    .header-middle .header-right {
        gap: 5px;
    }

    .custom-header-search {
        width: 40px;
        height: 44px;
    }

    .custom-search-toggle {
        width: 40px;
        height: 40px;
    }

    .custom-search-toggle i {
        font-size: 20px;
    }

    .custom-search-form {
        height: 42px;
    }

    .custom-header-search.is-open .custom-search-form {
        width: 220px;
    }

    .custom-search-form input {
        height: 40px;
        padding-left: 14px;
        padding-right: 42px;
        font-size: 13px;
    }

    .custom-search-submit {
        width: 40px;
        height: 40px;
    }

    .custom-search-submit i {
        font-size: 17px;
    }

    .header-account {
        display: none;
    }
}


/* =========================================================
   SMALL MOBILE
   ========================================================= */

@media (max-width: 575px) {

    .header-middle > .container {
        padding-left: 15px;
        padding-right: 15px;
    }

    .header-middle .logo img {
        width: 90px;
        height: auto;
    }

    .header-middle .header-right {
        gap: 3px;
    }

    .brand-text-logo .brand-name {
        font-size: 20px;
    }

    .custom-header-search.is-open .custom-search-form {
        width: 185px;
    }

    .custom-search-form input {
        font-size: 12px;
    }
}

</style>


<header class="header header-intro-clearance header-4">


    <!-- =====================================================
         HEADER TOP
         ===================================================== -->

    <div class="header-top">

        <div class="container">

            <div class="header-left">

                <a href="tel:#">

                    <i class="icon-phone"></i>

                    Call: +0123 456 789

                </a>

            </div>


            <div class="header-right">

                <div class="header-top-account">

                    <?php if ($isLoggedIn): ?>

                        <div class="dropdown header-account">

                            <a
                                href="#"
                                class="dropdown-toggle"
                                data-toggle="dropdown"
                                role="button"
                                aria-haspopup="true"
                                aria-expanded="false"
                            >

                                <i class="icon-user"></i>

                                <?php echo htmlspecialchars($userName); ?>

                                <i class="icon-angle-down"></i>

                            </a>


                            <div class="dropdown-menu dropdown-menu-right">

                                <a href="my-orders.php">

                                    <i class="icon-cube"></i>

                                    My Orders

                                </a>


                                <a href="logout.php">

                                    <i class="icon-power-off"></i>

                                    Logout

                                </a>

                            </div>

                        </div>

                    <?php else: ?> 
                        <li> 
                            <a href="login.php"> Sign In / Sign Up </a> 
                    </li> 
                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         HEADER MIDDLE
         ===================================================== -->

    <div class="header-middle">

        <div class="container">


            <!-- ================= LEFT ================= -->

            <div class="header-left">

                <!-- Mobile Menu -->

                <button
                    class="mobile-menu-toggler"
                    type="button"
                >

                    <span class="sr-only">
                        Toggle mobile menu
                    </span>

                    <i class="icon-bars"></i>

                </button>


                <!-- ShopMart Logo -->

                <a
                    href="index.php"
                    class="logo brand-text-logo"
                >

                    <span class="brand-name">
                        Shop<span>Mart</span>
                    </span>

                </a>

            </div>


            <!-- ================= RIGHT ================= -->

            <div class="header-right">


                <!-- ================= SEARCH ================= -->

                <div
                    class="custom-header-search"
                    id="customHeaderSearch"
                >

                    <button
                        type="button"
                        class="custom-search-toggle"
                        id="customSearchToggle"
                        aria-label="Search"
                        aria-expanded="false"
                    >

                        <i class="icon-search"></i>

                    </button>


                    <form
                        action="products.php"
                        method="get"
                        class="custom-search-form"
                        id="customSearchForm"
                    >

                        <input
                            type="search"
                            name="q"
                            id="customSearchInput"
                            placeholder="Search product..."
                            autocomplete="off"
                            required
                        >

                        <button
                            type="submit"
                            class="custom-search-submit"
                            aria-label="Submit search"
                        >

                            <i class="icon-search"></i>

                        </button>

                    </form>

                </div>


                <!-- ================= CART ================= -->

                <div class="dropdown cart-dropdown">

                    <a
                        href="cart.php"
                        class="dropdown-toggle"
                        role="button"
                        data-toggle="dropdown"
                        aria-haspopup="true"
                        aria-expanded="false"
                        data-display="static"
                    >

                        <div class="icon">

                            <i class="icon-shopping-cart"></i>

                            <span class="cart-count">

                                <?php echo $headerCartCount; ?>

                            </span>

                        </div>

                        <p>
                            Cart
                        </p>

                    </a>


                    <div class="dropdown-menu dropdown-menu-right">

                        <div class="dropdown-cart-products">

                            <?php if (!empty($headerCartItems)): ?>

                                <?php foreach ($headerCartItems as $cartItem): ?>

                                    <div class="product">


                                        <div class="product-cart-details">

                                            <h4 class="product-title">

                                                <a
                                                    href="product-detail.php?id=<?php echo (int) $cartItem['product_id']; ?>"
                                                >

                                                    <?php echo htmlspecialchars($cartItem['name']); ?>

                                                </a>

                                            </h4>


                                            <span class="cart-product-info">

                                                <span class="cart-product-qty">

                                                    <?php echo (int) $cartItem['quantity']; ?>

                                                </span>

                                                x

                                                $<?php echo number_format(
                                                    (float) $cartItem['price'],
                                                    2
                                                ); ?>

                                            </span>

                                        </div>


                                        <!-- ================= CART IMAGE ================= -->

                                        <figure class="product-image-container">

                                            <a
                                                href="product-detail.php?id=<?php echo (int) $cartItem['product_id']; ?>"
                                                class="product-image"
                                            >

                                                <?php if (!empty($cartItem['first_image'])): ?>

                                                    <img
                                                        src="uploads/products/<?php echo htmlspecialchars($cartItem['first_image']); ?>"
                                                        alt="<?php echo htmlspecialchars($cartItem['name']); ?>"
                                                    >

                                                <?php else: ?>

                                                    <img
                                                        src="assets/images/products/table/product-1.jpg"
                                                        alt="<?php echo htmlspecialchars($cartItem['name']); ?>"
                                                    >

                                                <?php endif; ?>

                                            </a>

                                        </figure>

                                    </div>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <p class="text-center p-2">
                                    Your cart is empty.
                                </p>

                            <?php endif; ?>

                        </div>


                        <!-- Cart Total -->

                        <div class="dropdown-cart-total">

                            <span>
                                Total
                            </span>

                            <span class="cart-total-price">

                                $<?php echo number_format(
                                    $headerCartTotal,
                                    2
                                ); ?>

                            </span>

                        </div>


                        <!-- Cart Buttons -->

                        <div class="dropdown-cart-action">

                            <a
                                href="cart.php"
                                class="btn btn-primary"
                            >

                                View Cart

                            </a>


                            <a
                                href="checkout.php"
                                class="btn btn-outline-primary-2"
                            >

                                <span>
                                    Checkout
                                </span>

                                <i class="icon-long-arrow-right"></i>

                            </a>

                        </div>

                    </div>

                </div>


                <!-- ================= END CART ================= -->

            </div>

        </div>

    </div>


    <!-- =====================================================
         HEADER BOTTOM
         ===================================================== -->

    <div class="header-bottom sticky-header">

        <div class="container">


            <!-- ================= CATEGORIES ================= -->

            <div class="header-left">

                <div class="dropdown category-dropdown">

                    <a
                        href="products.php"
                        class="dropdown-toggle"
                        role="button"
                        data-toggle="dropdown"
                        aria-haspopup="true"
                        aria-expanded="false"
                        data-display="static"
                        title="Browse Categories"
                    >

                        <i class="icon-bars"></i>

                        Browse Categories

                        <i class="icon-angle-down"></i>

                    </a>


                    <div class="dropdown-menu">

                        <nav class="side-nav">

                            <ul class="menu-vertical sf-arrows">

                                <li class="item-lead">

                                    <a href="products.php">
                                        All Products
                                    </a>

                                </li>


                                <?php if (!empty($headerCategories)): ?>

                                    <?php foreach ($headerCategories as $cat): ?>

                                        <li>

                                            <a
                                                href="products.php?category_id=<?php echo (int) $cat['id']; ?>"
                                            >

                                                <?php echo htmlspecialchars($cat['name']); ?>

                                            </a>

                                        </li>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </ul>

                        </nav>

                    </div>

                </div>

            </div>


            <!-- ================= MAIN NAV ================= -->

            <div class="header-center">

                <nav class="main-nav">

                    <ul class="menu sf-arrows">


                        <!-- HOME -->

                        <li>

                            <a href="index.php">
                                Home
                            </a>

                        </li>


                        <!-- SHOP -->

                        <li>

                            <a
                                href="products.php"
                                class="sf-with-ul"
                            >

                                Shop

                            </a>


                            <div class="megamenu megamenu-md">

                                <div class="row no-gutters">


                                    <!-- Shop Menu -->

                                    <div class="col-md-8 shop-menu-content">

                                        <div class="menu-col">

                                            <div class="row">


                                                <!-- Column 1 -->

                                                <div class="col-md-6">

                                                    <div class="menu-title">
                                                        Shopping
                                                    </div>

                                                    <ul>

                                                        <li>

                                                            <a href="cart.php">
                                                                Cart
                                                            </a>

                                                        </li>

                                                        <li>

                                                            <a href="checkout.php">
                                                                Checkout
                                                            </a>

                                                        </li>

                                                    </ul>

                                                </div>


                                                <!-- Column 2 -->

                                                <div class="col-md-6">

                                                    <div class="menu-title">
                                                        Order
                                                    </div>

                                                    <ul>

                                                        <li>

                                                            <a href="order-confirmation.php">
                                                                Order Confirmation
                                                            </a>

                                                        </li>

                                                    </ul>


                                                    <div class="menu-title">
                                                        Account
                                                    </div>

                                                    <ul>

                                                        <?php if ($isLoggedIn): ?>

                                                            <li>

                                                                <a href="logout.php">
                                                                    Sign Out
                                                                </a>

                                                            </li>

                                                        <?php endif; ?>

                                                    </ul>

                                                </div>

                                            </div>

                                        </div>

                                    </div>


                                    <!-- Banner -->

                                    <div class="col-md-4 shop-menu-banner">

                                        <div class="banner banner-overlay">

                                            <a
                                                href="products.php"
                                                class="banner banner-menu"
                                            >

                                                <img
                                                    src="assets/images/menu/banner-1.jpg"
                                                    alt="Banner"
                                                >


                                                <div class="banner-content banner-content-top">

                                                    <div class="banner-title text-white">

                                                        Last
                                                        <br>

                                                        Chance

                                                        <br>

                                                        <span>

                                                            <strong>
                                                                Sale
                                                            </strong>

                                                        </span>

                                                    </div>

                                                </div>

                                            </a>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </li>


                        <!-- NEW ARRIVAL -->

                        <li>

                            <a href="index.php#new-arrivals">
                                New Arrival
                            </a>

                        </li>


                        <!-- TRENDING PRODUCTS -->

                        <li>

                            <a href="index.php#trending-products">
                                Trending Products
                            </a>

                        </li>


                        <!-- MY ORDERS -->

                        <?php if ($isLoggedIn): ?>

                            <li>

                                <a href="my-orders.php">
                                    My Orders
                                </a>

                            </li>

                        <?php endif; ?>


                    </ul>

                </nav>

            </div>


            <div class="header-right">

            </div>

        </div>

    </div>

</header>


<!-- =========================================================
     MOBILE MENU OVERLAY
     ========================================================= -->

<div class="mobile-menu-overlay"></div>


<!-- =========================================================
     MOBILE MENU
     ========================================================= -->

<div class="mobile-menu-container mobile-menu-light">

    <div class="mobile-menu-wrapper">


        <!-- Close -->

        <span class="mobile-menu-close">

            <i class="icon-close"></i>

        </span>


        <!-- Mobile Search -->

        <form
            action="products.php"
            method="get"
            class="mobile-search"
        >

            <label
                for="mobile-search"
                class="sr-only"
            >

                Search

            </label>


            <input
                type="search"
                class="form-control"
                name="q"
                id="mobile-search"
                placeholder="Search product..."
                required
            >


            <button
                class="btn btn-primary"
                type="submit"
            >

                <i class="icon-search"></i>

            </button>

        </form>


        <!-- Mobile Tabs -->

        <ul
            class="nav nav-pills-mobile nav-border-anim"
            role="tablist"
        >

            <li class="nav-item">

                <a
                    class="nav-link active"
                    id="mobile-menu-link"
                    data-toggle="tab"
                    href="#mobile-menu-tab"
                    role="tab"
                    aria-controls="mobile-menu-tab"
                    aria-selected="true"
                >

                    Menu

                </a>

            </li>


            <li class="nav-item">

                <a
                    class="nav-link"
                    id="mobile-cats-link"
                    data-toggle="tab"
                    href="#mobile-cats-tab"
                    role="tab"
                    aria-controls="mobile-cats-tab"
                    aria-selected="false"
                >

                    Categories

                </a>

            </li>

        </ul>


        <!-- Mobile Tab Content -->

        <div class="tab-content">


            <!-- ================= MOBILE MENU ================= -->

            <div
                class="tab-pane fade show active"
                id="mobile-menu-tab"
                role="tabpanel"
                aria-labelledby="mobile-menu-link"
            >

                <nav class="mobile-nav">

                    <ul class="mobile-menu">


                        <li>

                            <a href="index.php">
                                Home
                            </a>

                        </li>


                        <li>

                            <a href="products.php">
                                Shop
                            </a>

                        </li>


                        <li>

                            <a href="index.php#new-arrival">
                                New Arrival
                            </a>

                        </li>


                        <li>

                            <a href="index.php#trending-products">
                                Trending Products
                            </a>

                        </li>


                        <?php if ($isLoggedIn): ?>

                            <li>

                                <a href="my-orders.php">
                                    My Orders
                                </a>

                            </li>


                            <li>

                                <a href="logout.php">
                                    Logout
                                </a>

                            </li>

                        <?php else: ?>

                            <li>

                                <a href="login.php">
                                    Sign In
                                </a>

                            </li>



                        <?php endif; ?>


                        <li>

                            <a href="cart.php">
                                Cart
                            </a>

                        </li>


                        <li>

                            <a href="checkout.php">
                                Checkout
                            </a>

                        </li>


                    </ul>

                </nav>

            </div>


            <!-- ================= MOBILE CATEGORIES ================= -->

            <div
                class="tab-pane fade"
                id="mobile-cats-tab"
                role="tabpanel"
                aria-labelledby="mobile-cats-link"
            >

                <nav class="mobile-cats-nav">

                    <ul class="mobile-cats-menu">


                        <li>

                            <a
                                class="mobile-cats-lead"
                                href="products.php"
                            >

                                All Products

                            </a>

                        </li>


                        <li>

                            <a href="products.php">

                                Categories

                            </a>

                        </li>


                    </ul>

                </nav>

            </div>

        </div>


        <!-- ================= SOCIAL ICONS ================= -->

        <div class="social-icons">

            <a
                href="#"
                class="social-icon"
                target="_blank"
                title="Facebook"
            >

                <i class="icon-facebook-f"></i>

            </a>


            <a
                href="#"
                class="social-icon"
                target="_blank"
                title="Twitter"
            >

                <i class="icon-twitter"></i>

            </a>


            <a
                href="#"
                class="social-icon"
                target="_blank"
                title="Instagram"
            >

                <i class="icon-instagram"></i>

            </a>


            <a
                href="#"
                class="social-icon"
                target="_blank"
                title="Youtube"
            >

                <i class="icon-youtube"></i>

            </a>

        </div>

    </div>

</div>


<!-- =========================================================
     CUSTOM SEARCH JAVASCRIPT
     ========================================================= -->

<script>

document.addEventListener("DOMContentLoaded", function () {

    const searchContainer =
        document.getElementById("customHeaderSearch");


    const searchToggle =
        document.getElementById("customSearchToggle");


    const searchInput =
        document.getElementById("customSearchInput");


    if (
        !searchContainer ||
        !searchToggle ||
        !searchInput
    ) {
        return;
    }


    /* OPEN SEARCH */

    searchToggle.addEventListener("click", function (event) {

        event.preventDefault();

        event.stopPropagation();


        const isOpen =
            searchContainer.classList.toggle("is-open");


        searchToggle.setAttribute(
            "aria-expanded",
            isOpen ? "true" : "false"
        );


        if (isOpen) {

            setTimeout(function () {

                searchInput.focus();

            }, 200);

        }

    });


    /* PREVENT FORM CLICK FROM CLOSING SEARCH */

    searchInput.addEventListener("click", function (event) {

        event.stopPropagation();

    });


    /* CLICK OUTSIDE = CLOSE */

    document.addEventListener("click", function (event) {

        if (!searchContainer.contains(event.target)) {

            searchContainer.classList.remove("is-open");

            searchToggle.setAttribute(
                "aria-expanded",
                "false"
            );

        }

    });


    /* ESCAPE = CLOSE */

    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape") {

            searchContainer.classList.remove("is-open");

            searchToggle.setAttribute(
                "aria-expanded",
                "false"
            );

            searchToggle.focus();

        }

    });

});

</script>

