
<?php

$pageTitle = "Sliders";

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
| Fetch Sliders
|--------------------------------------------------------------------------
*/
$sliders = [];

$stmt = $mysqli->prepare(
    "SELECT
        id,
        subtitle,
        title,
        old_price,
        price,
        image,
        button_text,
        button_link,
        sort_order,
        status
     FROM sliders
     ORDER BY sort_order ASC, id DESC"
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $sliders[] = $row;
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

    <title>Sliders - ClothWear</title>


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

            .slider-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 12px;
            }

            .slider-header h5 {
                font-size: 17px;
            }

            .slider-header .btn {
                width: 100%;
                text-align: center;
            }

        }

        .slider-image {
            width: 120px;
            height: 65px;
            object-fit: cover;
            border-radius: 8px;
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
                    Sliders
                </h4>

            </div>


            <!-- SEARCH -->
            <div class="d-flex align-items-center gap-3 border rounded ps-4">

                <input
                    type="text"
                    class="form-control"
                    placeholder="Search sliders..."
                    style="width: 220px;"
                    id="sliderSearch"
                >

            </div>

        </div>


        <!-- SLIDER CARD -->
        <div class="card">


            <!-- CARD HEADER -->
            <div class="card-header pb-0">

                <div class="slider-header d-flex justify-content-between align-items-center">

                    <h5 class="mb-0">
                        Slider Management
                    </h5>


                    <a
                        href="create.php"
                        class="btn bg-gradient-dark btn-sm"
                    >

                        <i class="fa-solid fa-plus me-1"></i>

                        Add Slider

                    </a>

                </div>

            </div>


            <!-- SLIDER TABLE -->
            <div class="card-body px-0 pb-2">

                <div class="table-responsive p-0">

                    <table class="table align-items-center mb-0">


                        <!-- TABLE HEADER -->
                        <thead>

                            <tr>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Slider
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Price
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Button
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Order
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
                        <tbody id="sliderTableBody">


                        <?php if (empty($sliders)): ?>


                            <tr>

                                <td
                                    colspan="6"
                                    class="text-center py-4"
                                >

                                    <p class="text-sm text-muted mb-0">
                                        No sliders found.
                                    </p>

                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach ($sliders as $slider): ?>

                                <tr>


                                    <!-- SLIDER -->
                                    <td>

                                        <div class="d-flex px-3 py-1 align-items-center">


                                            <?php if (!empty($slider['image'])): ?>

                                                <img
                                                    src="../../public/uploads/sliders/<?php echo htmlspecialchars($slider['image']); ?>"
                                                    class="slider-image me-3"
                                                    alt="<?php echo htmlspecialchars($slider['title']); ?>"
                                                >

                                            <?php endif; ?>


                                            <div class="d-flex flex-column justify-content-center">

                                                <?php if (!empty($slider['subtitle'])): ?>

                                                    <p class="text-xs text-secondary mb-1">

                                                        <?php
                                                        echo htmlspecialchars(
                                                            $slider['subtitle']
                                                        );
                                                        ?>

                                                    </p>

                                                <?php endif; ?>


                                                <h6 class="mb-0 text-sm">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $slider['title']
                                                    );
                                                    ?>

                                                </h6>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- PRICE -->
                                    <td>

                                        <?php if (
                                            $slider['old_price'] !== null &&
                                            $slider['old_price'] !== ''
                                        ): ?>

                                            <p class="text-xs text-secondary text-decoration-line-through mb-1">

                                                $<?php
                                                echo number_format(
                                                    (float) $slider['old_price'],
                                                    2
                                                );
                                                ?>

                                            </p>

                                        <?php endif; ?>


                                        <?php if (
                                            $slider['price'] !== null &&
                                            $slider['price'] !== ''
                                        ): ?>

                                            <p class="text-xs font-weight-bold mb-0">

                                                $<?php
                                                echo number_format(
                                                    (float) $slider['price'],
                                                    2
                                                );
                                                ?>

                                            </p>

                                        <?php else: ?>

                                            <p class="text-xs text-muted mb-0">
                                                —
                                            </p>

                                        <?php endif; ?>

                                    </td>


                                    <!-- BUTTON -->
                                    <td>

                                        <p class="text-xs font-weight-bold mb-0">

                                            <?php
                                            echo htmlspecialchars(
                                                $slider['button_text']
                                            );
                                            ?>

                                        </p>

                                    </td>


                                    <!-- SORT ORDER -->
                                    <td>

                                        <p class="text-xs font-weight-bold mb-0">

                                            <?php
                                            echo (int) $slider['sort_order'];
                                            ?>

                                        </p>

                                    </td>


                                    <!-- STATUS -->
                                    <td>

                                        <?php if ((int) $slider['status'] === 1): ?>

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
                                            href="edit.php?id=<?php echo (int) $slider['id']; ?>"
                                            class="btn btn-link text-secondary p-0 me-3"
                                            title="Edit Slider"
                                        >

                                            <i class="fa-solid fa-pen"></i>

                                        </a>


                                        <!-- DELETE -->
                                        <button
                                            type="button"
                                            class="btn btn-link text-danger p-0"
                                            title="Delete Slider"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteSliderModal"
                                            data-slider-id="<?php echo (int) $slider['id']; ?>"
                                            data-slider-name="<?php echo htmlspecialchars($slider['title']); ?>"
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
     DELETE SLIDER MODAL
=================================================== -->

<div
    class="modal fade"
    id="deleteSliderModal"
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

                <strong id="deleteSliderName"></strong>.

                This slider will be permanently deleted.

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
                    id="confirmDeleteSliderButton"
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


<!-- SLIDER SEARCH -->
<script>

    const searchInput =
        document.getElementById('sliderSearch');

    searchInput.addEventListener('keyup', function () {

        const searchValue =
            this.value.toLowerCase();

        const rows =
            document.querySelectorAll(
                '#sliderTableBody tr'
            );


        rows.forEach(function (row) {

            const sliderName =
                row.innerText.toLowerCase();

            if (sliderName.includes(searchValue)) {

                row.style.display = '';

            } else {

                row.style.display = 'none';

            }

        });

    });

</script>


<!-- DELETE SLIDER MODAL -->
<script>

    const deleteSliderModal =
        document.getElementById('deleteSliderModal');


    deleteSliderModal.addEventListener(
        'show.bs.modal',
        function (event) {

            const button =
                event.relatedTarget;


            const sliderId =
                button.getAttribute(
                    'data-slider-id'
                );


            const sliderName =
                button.getAttribute(
                    'data-slider-name'
                );


            const sliderNameElement =
                document.getElementById(
                    'deleteSliderName'
                );


            const confirmDeleteButton =
                document.getElementById(
                    'confirmDeleteSliderButton'
                );


            sliderNameElement.textContent =
                sliderName;


            confirmDeleteButton.href =
                'delete.php?id=' + sliderId;

        }
    );

</script>


</body>

</html>

