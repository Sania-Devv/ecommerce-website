<?php

$pageTitle = "Edit Category";

require_once __DIR__ . '/../../core/Sessions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth($mysqli);

/* =========================
   ADMIN PROTECTION
========================= */

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: ../login.php');
    exit;
}


/* =========================
   GET CATEGORY ID
========================= */

$categoryId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($categoryId <= 0) {

    header('Location: index.php');
    exit;
}


/* =========================
   FETCH CATEGORY
========================= */

$stmt = $mysqli->prepare(
    "SELECT id, name, slug, image, status
     FROM categories
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $categoryId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();

    header('Location: index.php');
    exit;
}

$category = $result->fetch_assoc();

$stmt->close();


/* =========================
   DEFAULT VALUES
========================= */

$categoryName = $category['name'];
$categoryStatus = ((int) $category['status'] === 1)
    ? 'active'
    : 'inactive';

$oldImage = $category['image'];

$error = '';


/* =========================
   UPDATE CATEGORY
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_category'])) {

    $name = trim($_POST['name'] ?? '');
    $status = $_POST['status'] ?? 'active';


    /* =========================
       VALIDATION
    ========================== */

    if ($name === '') {

        $error = "Category name is required.";

    } elseif (!in_array($status, ['active', 'inactive'], true)) {

        $error = "Invalid category status.";

    } else {

        /* =========================
           CREATE SLUG
        ========================== */

        $slug = strtolower(
            trim(
                preg_replace(
                    '/[^A-Za-z0-9-]+/',
                    '-',
                    $name
                ),
                '-'
            )
        );


        /* =========================
           CHECK DUPLICATE
        ========================== */

        $stmt = $mysqli->prepare(
            "SELECT id
             FROM categories
             WHERE (name = ? OR slug = ?)
             AND id != ?
             LIMIT 1"
        );

        $stmt->bind_param(
            "ssi",
            $name,
            $slug,
            $categoryId
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $error = "Another category with this name already exists.";

            $stmt->close();

        } else {

            $stmt->close();


            /* =========================
               IMAGE VARIABLES
            ========================== */

            $newImageName = $oldImage;


            /* =========================
               NEW IMAGE UPLOAD
            ========================== */

            if (
                isset($_FILES['image'])
                && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {

                    $error = "Image upload failed.";

                } else {

                    $allowedTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp'
                    ];

                    $maxSize = 2 * 1024 * 1024; // 2MB

                    $tmpName = $_FILES['image']['tmp_name'];
                    $fileSize = $_FILES['image']['size'];


                    /* =========================
                       CHECK MIME TYPE
                    ========================== */

                    $finfo = finfo_open(FILEINFO_MIME_TYPE);

                    $mimeType = finfo_file(
                        $finfo,
                        $tmpName
                    );

                    finfo_close($finfo);


                    if (!isset($allowedTypes[$mimeType])) {

                        $error =
                            "Only JPG, PNG and WEBP images are allowed.";

                    } elseif ($fileSize > $maxSize) {

                        $error =
                            "Image size must be less than 2MB.";

                    } else {

                        $extension =
                            $allowedTypes[$mimeType];


                        /* =========================
                           UNIQUE FILE NAME
                        ========================== */

                        $newImageName =
                            $slug . '_' . uniqid() . '.' . $extension;


                        $uploadDir =
                            __DIR__ .
                            '/../../public/uploads/categories/';


                        if (!is_dir($uploadDir)) {

                            mkdir(
                                $uploadDir,
                                0755,
                                true
                            );
                        }


                        $uploadPath =
                            $uploadDir . $newImageName;


                        if (!move_uploaded_file(
                            $tmpName,
                            $uploadPath
                        )) {

                            $error =
                                "Could not save image.";
                        }
                    }
                }
            }


            /* =========================
               UPDATE DATABASE
            ========================== */

            if ($error === '') {

                $statusValue =
                    ($status === 'active') ? 1 : 0;


                $stmt = $mysqli->prepare(
                    "UPDATE categories
                     SET name = ?,
                         slug = ?,
                         image = ?,
                         status = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "sssii",
                    $name,
                    $slug,
                    $newImageName,
                    $statusValue,
                    $categoryId
                );


                if ($stmt->execute()) {

                    $stmt->close();


                    /* =========================
                       DELETE OLD IMAGE
                       ONLY AFTER SUCCESS
                    ========================== */

                    if (
                        $newImageName !== $oldImage
                        && $oldImage !== null
                        && $oldImage !== ''
                    ) {

                        $oldImagePath =
                            __DIR__ .
                            '/../../public/uploads/categories/' .
                            $oldImage;


                        if (file_exists($oldImagePath)) {

                            unlink($oldImagePath);
                        }
                    }


                    header(
                        'Location: index.php?success=updated'
                    );

                    exit;

                } else {

                    $error =
                        "Category update failed.";


                    /* =========================
                       DELETE NEW IMAGE
                       IF DB UPDATE FAILED
                    ========================== */

                    if (
                        $newImageName !== $oldImage
                        && $newImageName !== null
                    ) {

                        $newImagePath =
                            __DIR__ .
                            '/../../public/uploads/categories/' .
                            $newImageName;


                        if (file_exists($newImagePath)) {

                            unlink($newImagePath);
                        }
                    }


                    $stmt->close();
                }
            }
        }
    }


    /* =========================
       KEEP FORM VALUES
    ========================== */

    if ($error !== '') {

        $categoryName = $name;
        $categoryStatus = $status;
    }
}

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="utf-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1, shrink-to-fit=no">

<link rel="apple-touch-icon"
      sizes="76x76"
      href="../assets/img/apple-icon.png">

<link rel="icon"
      type="image/png"
      href="../assets/img/favicon.png">

<title>Edit Category - ClothWear</title>


<!-- Fonts -->
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900">


<!-- Nucleo Icons -->
<link href="../assets/css/nucleo-icons.css"
      rel="stylesheet">

<link href="../assets/css/nucleo-svg.css"
      rel="stylesheet">


<!-- Font Awesome -->
<script src="https://kit.fontawesome.com/42d5adcbca.js"
        crossorigin="anonymous"></script>


<!-- Material Icons -->
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">


<!-- Material Dashboard CSS -->
<link id="pagestyle"
      href="../assets/css/material-dashboard.css?v=3.2.0"
      rel="stylesheet">


<!-- =========================
     CUSTOM CATEGORY FORM CSS
========================== -->

<style>

    /* =========================
       FORM CARD
    ========================== */

    .category-form-card {
        border-radius: 16px;
        overflow: hidden;
    }


    /* =========================
       CARD HEADER
    ========================== */

    .category-form-header {
        padding: 28px 30px 18px;
        border-bottom: 1px solid #e9ecef;
    }

    .category-form-header h5 {
        font-weight: 700;
        color: #212529;
    }

    .category-form-header p {
        margin-bottom: 0;
        color: #67748e;
    }


    /* =========================
       FORM BODY
    ========================== */

    .category-form-body {
        padding: 30px;
    }


    /* =========================
       FORM FIELD
    ========================== */

    .category-field {
        margin-bottom: 28px;
    }


    /* =========================
       LABEL
    ========================== */

    .category-field label {
        display: flex;
        align-items: center;
        gap: 8px;

        margin-bottom: 10px;

        font-size: 14px;
        font-weight: 700;

        color: #344767;
    }


    /* Label Icon */

    .category-field label i {
        font-size: 14px;
        color: #2e37a4;
    }


    /* =========================
       INPUT + SELECT
    ========================== */

    .category-input {
        width: 100%;
        min-height: 48px;

        padding: 12px 15px;

        border: 1px solid #d8dce3;
        border-radius: 8px;

        background-color: #ffffff;

        font-size: 14px;
        color: #344767;

        transition: all 0.2s ease;

        box-shadow: none;
    }


    /* Placeholder */

    .category-input::placeholder {
        color: #adb5bd;
    }


    /* =========================
       INPUT FOCUS
    ========================== */

    .category-input:focus {

        border-color: #2e37a4;

        box-shadow:
            0 0 0 3px rgba(46, 55, 164, 0.10);

        outline: none;
    }


    /* =========================
       SELECT
    ========================== */

    select.category-input {
        cursor: pointer;
    }


    /* =========================
       HELPER TEXT
    ========================== */

    .field-help {
        display: block;

        margin-top: 7px;

        font-size: 12px;

        color: #8392ab;
    }


    /* =========================
       BUTTON AREA
    ========================== */

    .category-form-actions {

        display: flex;

        justify-content: flex-end;

        gap: 10px;

        padding-top: 20px;

        margin-top: 5px;

        border-top: 1px solid #e9ecef;
    }


    /* =========================
       UPDATE BUTTON
    ========================== */

    .category-form-actions .update-btn {

        min-width: 160px;

        padding: 11px 18px;

        font-weight: 600;
    }


    /* =========================
       CANCEL BUTTON
    ========================== */

    .category-form-actions .cancel-btn {

        min-width: 100px;

        padding: 11px 18px;

        font-weight: 600;
    }


    /* =========================
       CATEGORY ID INFO
    ========================== */

    .category-id-info {

        display: inline-flex;

        align-items: center;

        gap: 6px;

        margin-top: 5px;

        padding: 5px 10px;

        border-radius: 6px;

        background-color: #f8f9fa;

        color: #8392ab;

        font-size: 11px;

        font-weight: 600;
    }


    /* =========================
       MOBILE
    ========================== */

    @media (max-width: 576px) {

        .category-form-body {
            padding: 20px;
        }


        .category-form-header {
            padding: 22px 20px 16px;
        }


        .category-form-actions {
            flex-direction: column-reverse;
        }


        .category-form-actions .btn {
            width: 100%;
        }

    }

</style>

</head>

<body class="g-sidenav-show bg-gray-100">

<!-- =========================
     ADMIN HEADER
========================== -->

<?php require_once '../../includes/admin-header.php'; ?>


<!-- =========================
     ADMIN SIDEBAR
========================== -->

<?php require_once '../../includes/admin-sidenavbar.php'; ?>


<!-- =========================
     MAIN CONTENT
========================== -->

<main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">


    <!-- PAGE CONTENT -->

    <div class="container-fluid py-4">


        <!-- =========================
             PAGE HEADER
        ========================== -->

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

            <div>

                <h4 class="fw-bold text-dark mb-1">
                    Edit Category
                </h4>

                <p class="text-sm text-secondary mb-0">
                    Update the category information
                </p>

            </div>


            <!-- BACK BUTTON -->

            <a
                href="index.php"
                class="btn btn-outline-secondary btn-sm"
            >

                <i class="fa-solid fa-arrow-left me-1"></i>

                Back to Categories

            </a>

        </div>


        <!-- =========================
             CATEGORY FORM
        ========================== -->

        <div class="row">

            <div class="col-lg-8 col-md-10 mx-auto">


                <!-- FORM CARD -->

                <div class="card category-form-card">


                    <!-- =========================
                         CARD HEADER
                    ========================== -->

                    <div class="category-form-header">

                        <h5 class="mb-1">
                            Category Information
                        </h5>

                        <p class="text-sm">
                            Update the details of this product category.
                        </p>

                        <span class="category-id-info">

                            <i class="fa-solid fa-hashtag"></i>

                            Category ID:
                            <?php echo htmlspecialchars($categoryId); ?>

                        </span>

                    </div>


                    <!-- =========================
                         FORM BODY
                    ========================== -->

                    <div class="category-form-body">


                      <form
    method="POST"
    action=""
    enctype="multipart/form-data"
>


                            <!-- Hidden ID -->

                            <input
                                type="hidden"
                                name="category_id"
                                value="<?php echo htmlspecialchars($categoryId); ?>"
                            >


                            <!-- =========================
                                 CATEGORY NAME
                            ========================== -->

                            <div class="category-field">

                                <label for="categoryName">

                                    <i class="fa-solid fa-tag"></i>

                                    Category Name

                                </label>


                                <input
                                    type="text"
                                    id="categoryName"
                                    name="name"
                                    class="category-input"
                                    value="<?php echo htmlspecialchars($categoryName); ?>"
                                    placeholder="Enter category name"
                                    required
                                >


                                <span class="field-help">

                                    Update the name of this product category.

                                </span>

                            </div>
<!-- =========================
     CATEGORY IMAGE
========================== -->

<div class="category-field">

    <label for="categoryImage">

        <i class="fa-solid fa-image"></i>

        Category Image

    </label>


    <?php if (!empty($oldImage)): ?>

        <div style="margin-bottom: 12px;">

            <img
                src="../../public/uploads/categories/<?php echo htmlspecialchars($oldImage); ?>"
                alt="Category Image"
                style="
                    width: 100px;
                    height: 100px;
                    object-fit: cover;
                    border-radius: 10px;
                    border: 1px solid #e9ecef;
                "
            >

        </div>

    <?php endif; ?>


    <input
        type="file"
        id="categoryImage"
        name="image"
        class="category-input"
        accept="image/jpeg,image/png,image/webp"
    >


    <span class="field-help">

        Optional. Upload a new image to replace the current one.
        JPG, PNG or WEBP only. Maximum size: 2MB.

    </span>

</div>

                            <!-- =========================
                                 STATUS
                            ========================== -->

                            <div class="category-field">

                                <label for="categoryStatus">

                                    <i class="fa-solid fa-circle-check"></i>

                                    Category Status

                                </label>


                                <select
                                    id="categoryStatus"
                                    name="status"
                                    class="category-input"
                                    required
                                >

                                    <option
                                        value="active"
                                        <?php echo ($categoryStatus === "active") ? "selected" : ""; ?>
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="inactive"
                                        <?php echo ($categoryStatus === "inactive") ? "selected" : ""; ?>
                                    >
                                        Inactive
                                    </option>

                                </select>


                                <span class="field-help">

                                    Change whether this category is currently available.

                                </span>

                            </div>


                            <!-- =========================
                                 BUTTONS
                            ========================== -->

                            <div class="category-form-actions">


                                <!-- CANCEL -->

                                <a
                                    href="index.php"
                                    class="btn btn-light cancel-btn"
                                >

                                    <i class="fa-solid fa-xmark me-1"></i>

                                    Cancel

                                </a>


                                <!-- UPDATE -->

                                <button
                                    type="submit"
                                    name="update_category"
                                    class="btn bg-gradient-dark update-btn"
                                >

                                    <i class="fa-solid fa-pen-to-square me-1"></i>

                                    Update Category

                                </button>


                            </div>


                        </form>

                    </div>

                </div>

            </div>

        </div>


        <!-- =========================
             ADMIN FOOTER
        ========================== -->

        <?php require_once '../../includes/admin-footer.php'; ?>


    </div>

</main>
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
</body>

</html>