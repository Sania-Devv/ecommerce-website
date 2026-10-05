<?php

$pageTitle = "Deals";

require_once __DIR__ . '/../../core/Sessions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth($mysqli);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: ../login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Remove Deal
|--------------------------------------------------------------------------
*/

if (isset($_GET['remove']) && (int) $_GET['remove'] > 0) {

    $removeProductId = (int) $_GET['remove'];

    $stmt = $mysqli->prepare(
        "UPDATE products
         SET
            discount_percentage = 0,
            deal_start = NULL,
            deal_end = NULL
         WHERE id = ?"
    );

    $stmt->bind_param("i", $removeProductId);

    if ($stmt->execute()) {

        $stmt->close();

        header('Location: index.php?success=removed');
        exit;
    }

    $stmt->close();

    header('Location: index.php?error=remove_failed');
    exit;
}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage = '';

if (isset($_GET['success']) && $_GET['success'] === 'removed') {

    $successMessage = "Deal removed successfully.";
}

if (isset($_GET['error']) && $_GET['error'] === 'remove_failed') {

    $errorMessage = "Failed to remove deal.";
}


/*
|--------------------------------------------------------------------------
| Get Active Deals
|--------------------------------------------------------------------------
*/

$deals = [];

$query = "
    SELECT
        p.id,
        p.name,
        p.price,
        p.discount_percentage,
        p.deal_start,
        p.deal_end,
        p.stock,
        p.image,
        p.status,
        c.name AS category_name
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
";

$result = $mysqli->query($query);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $deals[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Product First Image
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
| Deal Price
|--------------------------------------------------------------------------
*/

function getDealPrice($price, $discount)
{
    return $price - ($price * $discount / 100);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, shrink-to-fit=no"
    >

    <link
        rel="apple-touch-icon"
        sizes="76x76"
        href="../assets/img/apple-icon.png"
    >

    <link
        rel="icon"
        type="image/png"
        href="../assets/img/favicon.png"
    >

    <title>Deals - ClothWear</title>


    <!-- Fonts -->

    <link
        rel="stylesheet"
        type="text/css"
        href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900"
    >


    <!-- Nucleo Icons -->

    <link
        href="../assets/css/nucleo-icons.css"
        rel="stylesheet"
    >

    <link
        href="../assets/css/nucleo-svg.css"
        rel="stylesheet"
    >


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- Material Icons -->

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0"
    >


    <!-- Material Dashboard CSS -->

    <link
        id="pagestyle"
        href="../assets/css/material-dashboard.css?v=3.2.0"
        rel="stylesheet"
    >

</head>


<body class="g-sidenav-show bg-gray-100">


<!-- ADMIN HEADER -->

<?php require_once '../../includes/admin-header.php'; ?>


<!-- ADMIN SIDEBAR -->

<?php require_once '../../includes/admin-sidenavbar.php'; ?>


<!-- MAIN CONTENT -->

<main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">

    <div class="container-fluid py-4">


        <!-- PAGE HEADER -->

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">

            <div>

                <h4 class="fw-bold text-dark mb-0">
                    Deals
                </h4>

            </div>


            <!-- SEARCH -->

            <div class="d-flex align-items-center gap-3 border rounded ps-4">

                <input
                    type="text"
                    class="form-control"
                    placeholder="Search deals..."
                    style="width: 220px;"
                >

            </div>

        </div>


        <!-- DEAL CARD -->

        <div class="card">


            <!-- CARD HEADER -->

            <div class="card-header pb-0">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                    <h5 class="mb-0">
                        Active Deals
                    </h5>


                    <a
                        href="../products/create.php"
                        class="btn bg-gradient-dark btn-sm"
                    >

                        <i class="fa-solid fa-plus me-1"></i>

                        Add Product Deal

                    </a>

                </div>

            </div>


            <!-- MESSAGES -->

            <div class="px-4 pt-3">

                <?php if ($successMessage !== ''): ?>

                    <div class="alert alert-success">

                        <i class="fa-solid fa-circle-check me-2"></i>

                        <?php echo htmlspecialchars($successMessage); ?>

                    </div>

                <?php endif; ?>


                <?php if ($errorMessage !== ''): ?>

                    <div class="alert alert-danger">

                        <i class="fa-solid fa-circle-exclamation me-2"></i>

                        <?php echo htmlspecialchars($errorMessage); ?>

                    </div>

                <?php endif; ?>

            </div>


            <!-- DEAL TABLE -->

            <div class="card-body px-0 pb-2">

                <div class="table-responsive p-0">

                    <table class="table align-items-center mb-0">

                        <thead>

                            <tr>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Product
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Category
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Price
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Discount
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Deal Price
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Stock
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Deal Ends
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-end pe-4">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php if (empty($deals)): ?>

                            <tr>

                                <td
                                    colspan="8"
                                    class="text-center py-5"
                                >

                                    <div class="mb-3">

                                        <i
                                            class="fa-solid fa-tags text-secondary"
                                            style="font-size: 35px;"
                                        ></i>

                                    </div>

                                    <h6 class="text-secondary mb-1">
                                        No active deals found.
                                    </h6>

                                    <p class="text-xs text-secondary mb-0">
                                        Add a discount and valid deal dates to a product.
                                    </p>

                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach ($deals as $deal): ?>

                                <?php

                                $originalPrice =
                                    (float) $deal['price'];

                                $discount =
                                    (float) $deal['discount_percentage'];

                                $dealPrice =
                                    getDealPrice(
                                        $originalPrice,
                                        $discount
                                    );

                                $firstImage =
                                    getProductFirstImage($deal['image']);

                                ?>


                                <tr>


                                    <!-- PRODUCT -->

                                    <td>

                                        <div class="d-flex px-3 py-1">

                                            <div>

                                                <?php if (!empty($firstImage)): ?>

                                                    <img
                                                        src="../../public/uploads/products/<?php echo htmlspecialchars($firstImage); ?>"
                                                        class="avatar avatar-sm me-3"
                                                        alt="<?php echo htmlspecialchars($deal['name']); ?>"
                                                        style="object-fit: cover;"
                                                    >

                                                <?php else: ?>

                                                    <img
                                                        src="../assets/images/products/table/product-1.jpg"
                                                        class="avatar avatar-sm me-3"
                                                        alt="<?php echo htmlspecialchars($deal['name']); ?>"
                                                        style="object-fit: cover;"
                                                    >

                                                <?php endif; ?>

                                            </div>


                                            <div class="d-flex flex-column justify-content-center">

                                                <h6 class="mb-0 text-sm">

                                                    <?php echo htmlspecialchars($deal['name']); ?>

                                                </h6>


                                                <p class="text-xs text-secondary mb-0">

                                                    Product ID:
                                                    <?php echo (int) $deal['id']; ?>

                                                </p>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- CATEGORY -->

                                    <td>

                                        <span class="badge bg-gradient-secondary">

                                            <?php echo htmlspecialchars($deal['category_name']); ?>

                                        </span>

                                    </td>


                                    <!-- PRICE -->

                                    <td>

                                        <p class="text-xs font-weight-bold mb-0">

                                            Rs.
                                            <?php echo number_format(
                                                $originalPrice,
                                                2
                                            ); ?>

                                        </p>

                                    </td>


                                    <!-- DISCOUNT -->

                                    <td>

                                        <span class="badge bg-gradient-success">

                                            <?php echo number_format(
                                                $discount,
                                                0
                                            ); ?>% OFF

                                        </span>

                                    </td>


                                    <!-- DEAL PRICE -->

                                    <td>

                                        <p class="text-xs font-weight-bold text-success mb-0">

                                            Rs.
                                            <?php echo number_format(
                                                $dealPrice,
                                                2
                                            ); ?>

                                        </p>

                                    </td>


                                    <!-- STOCK -->

                                    <td>

                                        <p class="text-xs font-weight-bold mb-0">

                                            <?php echo (int) $deal['stock']; ?>

                                        </p>

                                    </td>


                                    <!-- DEAL ENDS -->

                                    <td>

                                        <p class="text-xs font-weight-bold mb-0">

                                            <?php
                                            echo date(
                                                'd M Y',
                                                strtotime($deal['deal_end'])
                                            );
                                            ?>

                                        </p>

                                        <p class="text-xs text-secondary mb-0">

                                            <?php
                                            echo date(
                                                'h:i A',
                                                strtotime($deal['deal_end'])
                                            );
                                            ?>

                                        </p>

                                    </td>


                                    <!-- ACTION -->

                                    <td class="text-end pe-4">


                                        <!-- EDIT -->

                                        <a
                                            href="../products/edit.php?id=<?php echo (int) $deal['id']; ?>"
                                            class="btn btn-link text-secondary p-0 me-3"
                                            title="Edit Deal"
                                        >

                                            <i class="fa-solid fa-pen"></i>

                                        </a>


                                        <!-- REMOVE -->

                                        <button
                                            type="button"
                                            class="btn btn-link text-danger p-0"
                                            title="Remove Deal"
                                            data-bs-toggle="modal"
                                            data-bs-target="#removeDealModal"
                                            data-product-id="<?php echo (int) $deal['id']; ?>"
                                            data-product-name="<?php echo htmlspecialchars($deal['name']); ?>"
                                        >

                                            <i class="fa-solid fa-trash"></i>

                                        </button>

                                    </td>

                                </tr>


                            <?php endforeach; ?>


                        <?php endif; ?>


                        </tbody>

                    </table>

                </div>

            </div>

        </div>


        <!-- ADMIN FOOTER -->

        <?php require_once '../../includes/admin-footer.php'; ?>


    </div>

</main>


<!-- REMOVE DEAL MODAL -->

<div
    class="modal fade"
    id="removeDealModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered modal-sm">

        <div class="modal-content text-center p-4">


            <!-- WARNING ICON -->

            <div class="text-danger fs-1 mb-2">

                <i class="fa-solid fa-circle-exclamation"></i>

            </div>


            <!-- TITLE -->

            <h6 class="fw-bold">

                Remove Deal?

            </h6>


            <!-- MESSAGE -->

            <p class="small text-muted mb-3">

                You are about to remove the deal from

                <strong id="removeDealProductName"></strong>.

                The product itself will not be deleted.

            </p>


            <!-- BUTTONS -->

            <div class="d-flex justify-content-center gap-2">


                <!-- CANCEL -->

                <button
                    type="button"
                    class="btn btn-light btn-sm"
                    data-bs-dismiss="modal"
                >

                    Cancel

                </button>


                <!-- CONFIRM -->

                <a
                    href="#"
                    id="confirmRemoveDealButton"
                    class="btn btn-danger btn-sm"
                >

                    Yes, Remove

                </a>

            </div>

        </div>

    </div>

</div>


<!-- SCRIPTS -->

<script src="../assets/js/core/popper.min.js"></script>

<script src="../assets/js/core/bootstrap.min.js"></script>

<script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>

<script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>

<script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>


<!-- REMOVE DEAL MODAL SCRIPT -->

<script>

    const removeDealModal =
        document.getElementById('removeDealModal');

    removeDealModal.addEventListener(
        'show.bs.modal',
        function (event) {

            const button = event.relatedTarget;

            const productId =
                button.getAttribute('data-product-id');

            const productName =
                button.getAttribute('data-product-name');

            document.getElementById(
                'removeDealProductName'
            ).textContent = productName;

            document.getElementById(
                'confirmRemoveDealButton'
            ).href =
                'index.php?remove=' + productId;

        }
    );

</script>


</body>

</html>