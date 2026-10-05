<?php

$pageTitle = "Products";

require_once __DIR__ . '/../../core/Sessions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth($mysqli);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$products = [];

$stmt = $mysqli->prepare(
    "SELECT
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
     ORDER BY p.id DESC"
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    /*
    |--------------------------------------------------------------------------
    | Get First Product Image
    |--------------------------------------------------------------------------
    |
    | New format:
    | ["22/image1.webp","22/image2.webp"]
    |
    | Old format:
    | image1.webp
    |
    */

    $productImages = [];

    $decodedImages = json_decode(
        $row['image'],
        true
    );

    if (is_array($decodedImages)) {

        foreach ($decodedImages as $imagePath) {

            if (
                is_string($imagePath) &&
                trim($imagePath) !== ''
            ) {

                $productImages[] = $imagePath;
            }
        }

    } elseif (!empty($row['image'])) {

        /*
        | Backward compatibility for old products
        */

        $productImages[] = $row['image'];
    }


    /*
    |--------------------------------------------------------------------------
    | First Image
    |--------------------------------------------------------------------------
    */

    $row['first_image'] =
        $productImages[0] ?? '';

    $row['image_count'] =
        count($productImages);


    $products[] = $row;
}

$stmt->close();

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

    <title>Products - ClothWear</title>


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

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">

            <div>

                <h4 class="fw-bold text-dark mb-0">
                    Products
                </h4>

            </div>


            <!-- SEARCH -->

            <div class="d-flex align-items-center gap-3 border rounded ps-4">

                <input
                    type="text"
                    id="productSearch"
                    class="form-control"
                    placeholder="Search products..."
                    style="width: 220px;"
                >

            </div>

        </div>


        <!-- PRODUCT CARD -->

        <div class="card">


            <!-- CARD HEADER -->

            <div class="card-header pb-0">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                    <h5 class="mb-0">
                        Product Inventory
                    </h5>


                    <a
                        href="/admin/products/create.php"
                        class="btn bg-gradient-dark btn-sm"
                    >

                        <i class="fa-solid fa-plus me-1"></i>

                        Add Product

                    </a>

                </div>

            </div>


            <!-- PRODUCT TABLE -->

            <div class="card-body px-0 pb-2">

                <div class="table-responsive p-0">

                    <table
                        class="table align-items-center mb-0"
                        id="productsTable"
                    >

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
                                    Deal
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Stock
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Status
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-end pe-4">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody id="productsTableBody">


                        <?php if (empty($products)): ?>

                            <tr>

                                <td
                                    colspan="7"
                                    class="text-center py-4"
                                >

                                    No products found.

                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach ($products as $product): ?>

                                <tr>


                                    <!-- PRODUCT -->

                                    <td>

                                        <div class="d-flex px-3 py-1">


                                            <!-- PRODUCT IMAGE -->

                                            <div>

                                                <?php if (!empty($product['first_image'])): ?>

                                                    <img
                                                        src="../../public/uploads/products/<?php
                                                            echo htmlspecialchars(
                                                                $product['first_image']
                                                            );
                                                        ?>"
                                                        class="avatar avatar-sm me-3"
                                                        alt="<?php
                                                            echo htmlspecialchars(
                                                                $product['name']
                                                            );
                                                        ?>"
                                                        style="object-fit: cover;"
                                                    >

                                                <?php else: ?>

                                                    <div
                                                        class="avatar avatar-sm me-3 bg-gray-200 d-flex align-items-center justify-content-center"
                                                    >

                                                        <i class="fa-solid fa-image text-secondary"></i>

                                                    </div>

                                                <?php endif; ?>

                                            </div>


                                            <!-- PRODUCT INFO -->

                                            <div class="d-flex flex-column justify-content-center">

                                                <h6 class="mb-0 text-sm">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $product['name']
                                                    );
                                                    ?>

                                                </h6>


                                                <p class="text-xs text-secondary mb-0">

                                                    Product ID:
                                                    <?php
                                                    echo (int) $product['id'];
                                                    ?>


                                                    <?php if ($product['image_count'] > 1): ?>

                                                        <span class="ms-2">

                                                            <i class="fa-solid fa-images"></i>

                                                            <?php
                                                            echo (int) $product['image_count'];
                                                            ?>

                                                        </span>

                                                    <?php endif; ?>

                                                </p>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- CATEGORY -->

                                    <td>

                                        <span class="badge bg-gradient-secondary">

                                            <?php

                                            echo htmlspecialchars(
                                                $product['category_name']
                                            );

                                            ?>

                                        </span>

                                    </td>


                                    <!-- PRICE -->

                                    <td>

                                        <p class="text-xs font-weight-bold mb-0">

                                            Rs.

                                            <?php

                                            echo number_format(
                                                (float) $product['price'],
                                                2
                                            );

                                            ?>

                                        </p>

                                    </td>


                                    <!-- DEAL -->

                                    <td>

                                        <?php

                                        $discount =
                                            (float) $product['discount_percentage'];


                                        $isDealActive =
                                            $discount > 0 &&
                                            !empty($product['deal_start']) &&
                                            !empty($product['deal_end']) &&
                                            strtotime(
                                                $product['deal_start']
                                            ) <= time() &&
                                            strtotime(
                                                $product['deal_end']
                                            ) >= time();

                                        ?>


                                        <?php if ($isDealActive): ?>

                                            <span class="badge bg-gradient-success">

                                                <?php

                                                echo number_format(
                                                    $discount,
                                                    0
                                                );

                                                ?>% OFF

                                            </span>


                                        <?php elseif ($discount > 0): ?>

                                            <span class="badge bg-gradient-warning">

                                                Scheduled

                                            </span>


                                        <?php else: ?>

                                            <span class="badge bg-gradient-secondary">

                                                No Deal

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- STOCK -->

                                    <td>

                                        <p class="text-xs font-weight-bold mb-0">

                                            <?php

                                            echo (int) $product['stock'];

                                            ?>

                                        </p>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <?php if (
                                            (int) $product['status'] === 1
                                        ): ?>

                                            <span class="badge bg-gradient-success">

                                                Active

                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-gradient-secondary">

                                                Inactive

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- ACTION -->

                                    <td class="text-end pe-4">


                                        <!-- EDIT -->

                                        <a
                                            href="edit.php?id=<?php
                                                echo (int) $product['id'];
                                            ?>"
                                            class="btn btn-link text-secondary p-0 me-3"
                                            title="Edit"
                                        >

                                            <i class="fa-solid fa-pen"></i>

                                        </a>


                                        <!-- DELETE -->

                                        <button
                                            type="button"
                                            class="btn btn-link text-danger p-0"
                                            title="Delete"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteProductModal"
                                            data-product-id="<?php
                                                echo (int) $product['id'];
                                            ?>"
                                            data-product-name="<?php
                                                echo htmlspecialchars(
                                                    $product['name']
                                                );
                                            ?>"
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


<!-- DELETE PRODUCT MODAL -->

<div
    class="modal fade"
    id="deleteProductModal"
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

                Are you sure?

            </h6>


            <!-- MESSAGE -->

            <p class="small text-muted mb-3">

                You are about to delete

                <strong id="deleteProductName"></strong>.

                This product will be permanently deleted.

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


                <!-- CONFIRM DELETE -->

                <a
                    href="#"
                    id="confirmDeleteButton"
                    class="btn btn-danger btn-sm"
                >

                    Yes, Delete

                </a>

            </div>

        </div>

    </div>

</div>


<!-- Popper -->

<script src="../assets/js/core/popper.min.js"></script>


<!-- Bootstrap -->

<script src="../assets/js/core/bootstrap.min.js"></script>


<!-- Perfect Scrollbar -->

<script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>


<!-- Smooth Scrollbar -->

<script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>


<!-- Material Dashboard -->

<script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>


<!-- PRODUCT SEARCH -->

<script>

    const productSearch =
        document.getElementById('productSearch');

    productSearch.addEventListener('keyup', function () {

        const searchValue =
            this.value.toLowerCase().trim();

        const rows =
            document.querySelectorAll('#productsTableBody tr');

        rows.forEach(function (row) {

            const productText =
                row.innerText.toLowerCase();

            if (productText.includes(searchValue)) {

                row.style.display = '';

            } else {

                row.style.display = 'none';

            }

        });

    });

</script>


<!-- DELETE MODAL SCRIPT -->

<script>

    const deleteModal =
        document.getElementById('deleteProductModal');


    deleteModal.addEventListener(
        'show.bs.modal',
        function (event) {

            const button =
                event.relatedTarget;


            const productId =
                button.getAttribute(
                    'data-product-id'
                );


            const productName =
                button.getAttribute(
                    'data-product-name'
                );


            const productNameElement =
                document.getElementById(
                    'deleteProductName'
                );


            const confirmDeleteButton =
                document.getElementById(
                    'confirmDeleteButton'
                );


            productNameElement.textContent =
                productName;


            confirmDeleteButton.href =
                'delete.php?id=' +
                productId;

        }
    );

</script>


</body>

</html>