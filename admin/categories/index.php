<?php

$pageTitle = "Categories";

require_once __DIR__ . '/../../core/Sessions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth($mysqli);

/*
|--------------------------------------------------------------------------
| Admin Protection
|--------------------------------------------------------------------------
*/
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: ../login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Categories
|--------------------------------------------------------------------------
*/
$categories = [];

$stmt = $mysqli->prepare(
    "SELECT 
        c.id,
        c.name,
        c.image,
        c.status,
        COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p
        ON p.category_id = c.id
     GROUP BY c.id, c.name, c.image, c.status
     ORDER BY c.id DESC"
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
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

    <title>Categories - ClothWear</title>


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


    <!-- Material Dashboard -->
    <link
        id="pagestyle"
        href="../assets/css/material-dashboard.css?v=3.2.0"
        rel="stylesheet"
    >


    <style>

        .search-wrapper {
            width: 180px;
        }

        .search-input {
            width: 100%;
        }

        @media (max-width: 400px) {

            .search-wrapper {
                width: 130px;
                padding-left: 6px !important;
                padding-right: 6px !important;
            }

            .search-input {
                font-size: 13px;
                padding: 6px 4px;
            }

        }

        @media (max-width: 340px) {

            .search-wrapper {
                width: 115px;
            }

            .search-input {
                font-size: 12px;
                padding: 5px 3px;
            }

            .d-flex.justify-content-between {
                gap: 8px !important;
            }

        }

        @media (max-width: 576px) {

            .category-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 12px;
            }

            .category-header h5 {
                font-size: 17px;
            }

            .category-header .btn {
                width: 100%;
                text-align: center;
            }

        }

    </style>

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
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

            <div>

                <h4 class="fw-bold text-dark mb-0">
                    Categories
                </h4>

            </div>


            <!-- SEARCH -->
            <div class="d-flex align-items-center gap-3 border rounded ps-4">

                <input
                    type="text"
                    class="form-control"
                    placeholder="Search categories..."
                    style="width: 220px;"
                    id="categorySearch"
                >

            </div>

        </div>


        <!-- CATEGORY CARD -->
        <div class="card">


            <!-- CARD HEADER -->
            <div class="card-header pb-0">

                <div class="category-header d-flex justify-content-between align-items-center">

                    <h5 class="mb-0">
                        Category Management
                    </h5>


                    <a
                        href="create.php"
                        class="btn bg-gradient-dark btn-sm"
                    >

                        <i class="fa-solid fa-plus me-1"></i>

                        Add Category

                    </a>

                </div>

            </div>


            <!-- CATEGORY TABLE -->
            <div class="card-body px-0 pb-2">

                <div class="table-responsive p-0">

                    <table class="table align-items-center mb-0">


                        <!-- TABLE HEADER -->
                        <thead>

                            <tr>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Category Name
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Items Count
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Status
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-end pe-4">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <!-- TABLE BODY -->
                        <tbody id="categoryTableBody">


                        <?php if (empty($categories)): ?>


                            <tr>

                                <td
                                    colspan="4"
                                    class="text-center py-4"
                                >

                                    <p class="text-sm text-muted mb-0">
                                        No categories found.
                                    </p>

                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach ($categories as $category): ?>

                                <tr>


                                    <!-- CATEGORY NAME -->
                                    <td>

                                        <div class="d-flex px-3 py-1 align-items-center">


                                            <?php if (!empty($category['image'])): ?>

                                                <img
                                                    src="../../public/uploads/categories/<?php echo htmlspecialchars($category['image']); ?>"
                                                    class="avatar avatar-sm me-3"
                                                    alt="<?php echo htmlspecialchars($category['name']); ?>"
                                                >

                                            <?php endif; ?>


                                            <div class="d-flex flex-column justify-content-center">

                                                <h6 class="mb-0 text-sm">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $category['name']
                                                    );
                                                    ?>

                                                </h6>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- PRODUCT COUNT -->
                                    <td>

                                        <p class="text-xs font-weight-bold mb-0">

                                            <?php
                                            echo (int) $category['product_count'];
                                            ?>

                                            Products

                                        </p>

                                    </td>


                                    <!-- STATUS -->
                                    <td>

                                        <?php if ((int) $category['status'] === 1): ?>

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
                                            href="edit.php?id=<?php echo (int) $category['id']; ?>"
                                            class="btn btn-link text-secondary p-0 me-3"
                                            title="Edit Category"
                                        >

                                            <i class="fa-solid fa-pen"></i>

                                        </a>


                                        <!-- DELETE -->
                                        <button
                                            type="button"
                                            class="btn btn-link text-danger p-0"
                                            title="Delete Category"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteCategoryModal"
                                            data-category-id="<?php echo (int) $category['id']; ?>"
                                            data-category-name="<?php echo htmlspecialchars($category['name']); ?>"
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


<!-- ==================================================
     DELETE CATEGORY MODAL
=================================================== -->

<div
    class="modal fade"
    id="deleteCategoryModal"
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

                <strong id="deleteCategoryName"></strong>.

                This category will be permanently deleted.

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
                    id="confirmDeleteCategoryButton"
                    class="btn btn-danger btn-sm"
                >

                    Yes, Delete

                </a>

            </div>

        </div>

    </div>

</div>


<!-- JAVASCRIPT -->


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


<!-- CATEGORY SEARCH -->
<script>

    const searchInput =
        document.getElementById('categorySearch');

    searchInput.addEventListener('keyup', function () {

        const searchValue =
            this.value.toLowerCase();

        const rows =
            document.querySelectorAll(
                '#categoryTableBody tr'
            );


        rows.forEach(function (row) {

            const categoryName =
                row.innerText.toLowerCase();

            if (categoryName.includes(searchValue)) {

                row.style.display = '';

            } else {

                row.style.display = 'none';

            }

        });

    });

</script>


<!-- DELETE CATEGORY MODAL -->
<script>

    const deleteCategoryModal =
        document.getElementById('deleteCategoryModal');


    deleteCategoryModal.addEventListener(
        'show.bs.modal',
        function (event) {

            const button =
                event.relatedTarget;


            const categoryId =
                button.getAttribute(
                    'data-category-id'
                );


            const categoryName =
                button.getAttribute(
                    'data-category-name'
                );


            const categoryNameElement =
                document.getElementById(
                    'deleteCategoryName'
                );


            const confirmDeleteButton =
                document.getElementById(
                    'confirmDeleteCategoryButton'
                );


            categoryNameElement.textContent =
                categoryName;


            confirmDeleteButton.href =
                'delete.php?id=' + categoryId;

        }
    );

</script>


</body>

</html>