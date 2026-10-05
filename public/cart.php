<?php

require_once __DIR__ . '/../core/Sessions.php';
require_once __DIR__ . '/../config/database.php';

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


/*
|--------------------------------------------------------------------------
| 1. Update Cart
|--------------------------------------------------------------------------
*/

if (isset($_POST['update_cart'])) {

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $userId = (int) $_SESSION['user_id'];

    if (
        isset($_POST['item_id']) &&
        isset($_POST['quantity']) &&
        is_array($_POST['item_id']) &&
        is_array($_POST['quantity'])
    ) {

        foreach ($_POST['item_id'] as $index => $cartItemId) {

            $cartItemId = (int) $cartItemId;

            $quantity = isset($_POST['quantity'][$index])
                ? (int) $_POST['quantity'][$index]
                : 1;

            if ($quantity < 1) {
                $quantity = 1;
            }

            // Check item belongs to current user's cart
            $itemStmt = $mysqli->prepare("
                SELECT ci.id, p.stock
                FROM cart_items ci
                INNER JOIN cart c
                    ON ci.cart_id = c.id
                INNER JOIN products p
                    ON ci.product_id = p.id
                WHERE ci.id = ?
                AND c.user_id = ?
                LIMIT 1
            ");

            $itemStmt->bind_param(
                "ii",
                $cartItemId,
                $userId
            );

            $itemStmt->execute();

            $itemResult = $itemStmt->get_result();
            $item = $itemResult->fetch_assoc();

            $itemStmt->close();

            if ($item) {

                // Quantity stock se zyada nahi honi chahiye
                if ($quantity > (int) $item['stock']) {
                    $quantity = (int) $item['stock'];
                }

                $updateStmt = $mysqli->prepare("
                    UPDATE cart_items
                    SET quantity = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");

                $updateStmt->bind_param(
                    "ii",
                    $quantity,
                    $cartItemId
                );

                $updateStmt->execute();
                $updateStmt->close();
            }
        }
    }

    header('Location: cart.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| 2. Remove Cart Item
|--------------------------------------------------------------------------
*/

if (isset($_GET['remove'])) {

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $userId = (int) $_SESSION['user_id'];
    $cartItemId = (int) $_GET['remove'];

    $removeStmt = $mysqli->prepare("
        DELETE ci
        FROM cart_items ci
        INNER JOIN cart c
            ON ci.cart_id = c.id
        WHERE ci.id = ?
        AND c.user_id = ?
    ");

    $removeStmt->bind_param(
        "ii",
        $cartItemId,
        $userId
    );

    $removeStmt->execute();
    $removeStmt->close();

    header('Location: cart.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| 3. Check User Login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| 4. Get User Cart
|--------------------------------------------------------------------------
*/

$cartStmt = $mysqli->prepare("
    SELECT id
    FROM cart
    WHERE user_id = ?
    LIMIT 1
");

$cartStmt->bind_param(
    "i",
    $userId
);

$cartStmt->execute();

$cartResult = $cartStmt->get_result();
$cart = $cartResult->fetch_assoc();

$cartStmt->close();


/*
|--------------------------------------------------------------------------
| 5. Get Cart Items
|--------------------------------------------------------------------------
*/

$cartItems = [];
$subtotal = 0;

if ($cart) {

    $cartId = (int) $cart['id'];

    $itemsStmt = $mysqli->prepare("
        SELECT
            ci.id AS cart_item_id,
            ci.quantity,
            p.id AS product_id,
            p.name,
            p.price,
            p.image,
            p.stock
        FROM cart_items ci
        INNER JOIN products p
            ON p.id = ci.product_id
        WHERE ci.cart_id = ?
        AND p.status = 1
        ORDER BY ci.id DESC
    ");

    $itemsStmt->bind_param(
        "i",
        $cartId
    );

    $itemsStmt->execute();

    $itemsResult = $itemsStmt->get_result();

    while ($item = $itemsResult->fetch_assoc()) {

        /*
        |--------------------------------------------------------------------------
        | Get First Product Image
        |--------------------------------------------------------------------------
        |
        | DB format:
        | ["5/image1.jpg","5/image2.jpg",...]
        |
        */

        $item['first_image'] = getProductFirstImage(
            $item['image']
        );

        $item['total'] =
            (float) $item['price'] *
            (int) $item['quantity'];

        $subtotal += $item['total'];

        $cartItems[] = $item;
    }

    $itemsStmt->close();
}


/*
|--------------------------------------------------------------------------
| 6. Shipping & Total
|--------------------------------------------------------------------------
*/

$shipping = 0;
$shippingMethod = 'free';

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

$total = $subtotal + $shipping;

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

    <title>Shopping Cart</title>

    <meta
        name="keywords"
        content="HTML5 Template"
    >

    <meta
        name="description"
        content="Shopping Cart"
    >

    <meta
        name="author"
        content="p-themes"
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
        name="theme-color"
        content="#ffffff"
    >

    <link
        rel="stylesheet"
        href="assets/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

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
                    Shopping Cart
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
                        Shopping Cart
                    </li>

                </ol>

            </div>

        </nav>


        <!-- Cart Content -->

        <div class="page-content">

            <div class="cart">

                <div class="container">

                    <div class="row">


                        <!-- LEFT SIDE -->

                        <div class="col-lg-9">


                            <?php if (!empty($cartItems)): ?>

                                <form
                                    action="cart.php"
                                    method="POST"
                                >

                                    <table
                                        class="table table-cart table-mobile"
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
                                                Quantity
                                            </th>

                                            <th>
                                                Total
                                            </th>

                                            <th></th>

                                        </tr>

                                        </thead>


                                        <tbody>


                                        <?php foreach ($cartItems as $item): ?>

                                            <tr>


                                                <!-- Product -->

                                                <td class="product-col">

                                                    <div class="product">


                                                        <figure class="product-media">

                                                            <a
                                                                href="product-detail.php?id=<?= (int) $item['product_id'] ?>"
                                                            >

                                                                <?php if (!empty($item['first_image'])): ?>

                                                                    <img
                                                                        src="uploads/products/<?= htmlspecialchars($item['first_image']) ?>"
                                                                        alt="<?= htmlspecialchars($item['name']) ?>"
                                                                    >

                                                                <?php else: ?>

                                                                    <img
                                                                        src="assets/images/products/table/product-1.jpg"
                                                                        alt="<?= htmlspecialchars($item['name']) ?>"
                                                                    >

                                                                <?php endif; ?>

                                                            </a>

                                                        </figure>


                                                        <h3 class="product-title">

                                                            <a
                                                                href="product-detail.php?id=<?= (int) $item['product_id'] ?>"
                                                            >

                                                                <?= htmlspecialchars($item['name']) ?>

                                                            </a>

                                                        </h3>


                                                    </div>

                                                </td>


                                                <!-- Price -->

                                                <td class="price-col">

                                                    Rs.
                                                    <?= number_format(
                                                        (float) $item['price'],
                                                        2
                                                    ) ?>

                                                </td>


                                                <!-- Quantity -->

                                                <td class="quantity-col">

                                                    <div class="cart-product-quantity">

                                                        <input
                                                            type="number"
                                                            class="form-control"
                                                            name="quantity[]"
                                                            data-item-id="<?= (int) $item['cart_item_id'] ?>"
                                                            value="<?= (int) $item['quantity'] ?>"
                                                            min="1"
                                                            max="<?= (int) $item['stock'] ?>"
                                                            step="1"
                                                            data-decimals="0"
                                                            required
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="item_id[]"
                                                            value="<?= (int) $item['cart_item_id'] ?>"
                                                        >

                                                    </div>

                                                </td>


                                                <!-- Total -->

                                                <td class="total-col">

                                                    Rs.
                                                    <?= number_format(
                                                        (float) $item['total'],
                                                        2
                                                    ) ?>

                                                </td>


                                                <!-- Remove -->

                                                <td class="remove-col">

                                                    <a
                                                        href="cart.php?remove=<?= (int) $item['cart_item_id'] ?>"
                                                        class="btn-remove"
                                                        title="Remove item"
                                                    >

                                                        <i class="icon-close"></i>

                                                    </a>

                                                </td>


                                            </tr>

                                        <?php endforeach; ?>


                                        </tbody>

                                    </table>


                                    <!-- Cart Bottom -->

                                    <div class="cart-bottom">


                                        


                                        <button
                                            type="submit"
                                            name="update_cart"
                                            value="1"
                                            class="btn btn-outline-dark-2"
                                        >

                                            <span>
                                                UPDATE CART
                                            </span>

                                            <i class="icon-refresh"></i>

                                        </button>


                                    </div>

                                </form>

                            <?php else: ?>


                                <!-- Empty Cart -->

                                <div class="text-center py-5">

                                    <h2>
                                        Your Cart is Empty
                                    </h2>

                                    <p>
                                        You have no products in your shopping cart.
                                    </p>

                                    <a
                                        href="products.php"
                                        class="btn btn-primary"
                                    >
                                        Continue Shopping
                                    </a>

                                </div>


                            <?php endif; ?>


                        </div>


                        <!-- RIGHT SIDE -->

                        <aside class="col-lg-3">


                            <div class="summary summary-cart">


                                <h3 class="summary-title">
                                    Cart Total
                                </h3>


                                <table class="table table-summary">

                                    <tbody>


                                    <!-- Subtotal -->

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


                                    <!-- Shipping -->

                                    <tr class="summary-shipping">

                                        <td>
                                            Shipping:
                                        </td>

                                        <td>&nbsp;</td>

                                    </tr>


                                    <?php foreach ($shippingOptions as $key => $option): ?>

                                        <tr class="summary-shipping-row">

                                            <td>

                                                <div class="custom-control custom-radio">

                                                    <input
                                                        type="radio"
                                                        id="<?= $key ?>-shipping"
                                                        name="shipping"
                                                        class="custom-control-input shipping-option"
                                                        value="<?= $key ?>"
                                                        data-price="<?= $option['amount'] ?>"
                                                        <?= $key === 'free' ? 'checked' : '' ?>
                                                    >

                                                    <label
                                                        class="custom-control-label"
                                                        for="<?= $key ?>-shipping"
                                                    >

                                                        <?= htmlspecialchars($option['name']) ?>

                                                    </label>

                                                </div>

                                            </td>

                                            <td>

                                                Rs.
                                                <?= number_format(
                                                    $option['amount'],
                                                    2
                                                ) ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>


                                    <!-- Total -->

                                    <tr class="summary-total">

                                        <td>
                                            Total:
                                        </td>

                                        <td>

                                            Rs.
                                            <?= number_format(
                                                $total,
                                                2
                                            ) ?>

                                        </td>

                                    </tr>


                                    </tbody>

                                </table>


                                <?php if (!empty($cartItems)): ?>

                                    <a
                                        href="checkout.php"
                                        class="btn btn-outline-primary-2 btn-order btn-block"
                                    >

                                        PROCEED TO CHECKOUT

                                    </a>

                                <?php endif; ?>


                            </div>


                            <a
                                href="products.php"
                                class="btn btn-outline-dark-2 btn-block mb-3"
                            >

                                <span>
                                    CONTINUE SHOPPING
                                </span>

                                <i class="icon-refresh"></i>

                            </a>


                        </aside>


                    </div>

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


<script>

document.addEventListener('DOMContentLoaded', function () {

    const shippingOptions =
        document.querySelectorAll('.shipping-option');

    const totalElement =
        document.querySelector('.summary-total td:last-child');

    const subtotal =
        <?= json_encode((float) $subtotal) ?>;

    shippingOptions.forEach(function (option) {

        option.addEventListener('change', function () {

            const shipping =
                parseFloat(this.dataset.price) || 0;

            const total =
                subtotal + shipping;

            totalElement.textContent =
                'Rs. ' + total.toFixed(2);

        });

    });

});

</script>


<script src="assets/js/jquery.min.js"></script>

<script src="assets/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/jquery.hoverIntent.min.js"></script>

<script src="assets/js/jquery.waypoints.min.js"></script>

<script src="assets/js/superfish.min.js"></script>

<script src="assets/js/owl.carousel.min.js"></script>

<script src="assets/js/bootstrap-input-spinner.js"></script>

<script src="assets/js/main.js"></script>


</body>

</html>