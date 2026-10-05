<?php

$pageTitle = "Edit Slider";

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
   GET SLIDER ID
========================= */

$sliderId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($sliderId <= 0) {
    header('Location: index.php');
    exit;
}


/* =========================
   FETCH SLIDER
========================= */

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
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $sliderId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();

    header('Location: index.php');
    exit;
}

$slider = $result->fetch_assoc();

$stmt->close();


/* =========================
   DEFAULT VALUES
========================= */

$subtitle = $slider['subtitle'];
$title = $slider['title'];
$oldPrice = $slider['old_price'];
$price = $slider['price'];
$oldImage = $slider['image'];
$buttonText = $slider['button_text'];
$buttonLink = $slider['button_link'];
$sortOrder = $slider['sort_order'];

$sliderStatus = ((int) $slider['status'] === 1)
    ? 'active'
    : 'inactive';

$error = '';


/* =========================
   UPDATE SLIDER
========================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_slider'])
) {

    $subtitle = trim($_POST['subtitle'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $oldPrice = trim($_POST['old_price'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $buttonText = trim($_POST['button_text'] ?? '');
    $buttonLink = trim($_POST['button_link'] ?? '');
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $status = $_POST['status'] ?? 'active';


    /* =========================
       VALIDATION
    ========================== */

    if ($title === '') {

        $error = "Slider title is required.";

    } elseif ($oldPrice !== '' && !is_numeric($oldPrice)) {

        $error = "Old price must be a valid number.";

    } elseif ($price !== '' && !is_numeric($price)) {

        $error = "Current price must be a valid number.";

    } elseif ($buttonText === '') {

        $error = "Button text is required.";

    } elseif ($buttonLink === '') {

        $error = "Button link is required.";

    } elseif (!in_array($status, ['active', 'inactive'], true)) {

        $error = "Invalid slider status.";

    } elseif ($sortOrder < 0) {

        $error = "Sort order cannot be negative.";

    } else {

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

                $maxSize = 5 * 1024 * 1024; // 5MB

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
                        "Image size must be less than 5MB.";

                } else {

                    $extension = $allowedTypes[$mimeType];


                    /* =========================
                       SAFE FILE NAME
                    ========================== */

                    $safeTitle = strtolower(
                        trim(
                            preg_replace(
                                '/[^A-Za-z0-9-]+/',
                                '-',
                                $title
                            ),
                            '-'
                        )
                    );

                    $newImageName =
                        $safeTitle . '_' .
                        uniqid() .
                        '.' .
                        $extension;


                    $uploadDir =
                        __DIR__ .
                        '/../../public/uploads/sliders/';


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
                "UPDATE sliders
                 SET subtitle = ?,
                     title = ?,
                     old_price = NULLIF(?, ''),
                     price = NULLIF(?, ''),
                     image = ?,
                     button_text = ?,
                     button_link = ?,
                     sort_order = ?,
                     status = ?
                 WHERE id = ?"
            );


            $stmt->bind_param(
                "sssssssiii",
                $subtitle,
                $title,
                $oldPrice,
                $price,
                $newImageName,
                $buttonText,
                $buttonLink,
                $sortOrder,
                $statusValue,
                $sliderId
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
                        '/../../public/uploads/sliders/' .
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
                    "Slider update failed.";


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
                        '/../../public/uploads/sliders/' .
                        $newImageName;


                    if (file_exists($newImagePath)) {

                        unlink($newImagePath);
                    }
                }


                $stmt->close();
            }
        }
    }


    /* =========================
       KEEP FORM VALUES
    ========================== */

    if ($error !== '') {

        $sliderStatus = $status;
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

<title>Edit Slider - ClothWear</title>


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


<style>

/* =========================
   FORM CARD
========================= */

.slider-form-card {
    border-radius: 16px;
    overflow: hidden;
}


/* =========================
   CARD HEADER
========================= */

.slider-form-header {
    padding: 28px 30px 18px;
    border-bottom: 1px solid #e9ecef;
}

.slider-form-header h5 {
    font-weight: 700;
    color: #212529;
}

.slider-form-header p {
    margin-bottom: 0;
    color: #67748e;
}


/* =========================
   FORM BODY
========================= */

.slider-form-body {
    padding: 30px;
}


/* =========================
   FORM FIELD
========================= */

.slider-field {
    margin-bottom: 28px;
}


/* =========================
   LABEL
========================= */

.slider-field label {
    display: flex;
    align-items: center;
    gap: 8px;

    margin-bottom: 10px;

    font-size: 14px;
    font-weight: 700;

    color: #344767;
}

.slider-field label i {
    font-size: 14px;
    color: #2e37a4;
}


/* =========================
   INPUT + SELECT
========================= */

.slider-input {
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

.slider-input::placeholder {
    color: #adb5bd;
}


/* =========================
   INPUT FOCUS
========================= */

.slider-input:focus {

    border-color: #2e37a4;

    box-shadow:
        0 0 0 3px rgba(46, 55, 164, 0.10);

    outline: none;
}


/* =========================
   SELECT
========================= */

select.slider-input {
    cursor: pointer;
}


/* =========================
   HELPER TEXT
========================= */

.field-help {
    display: block;

    margin-top: 7px;

    font-size: 12px;

    color: #8392ab;
}


/* =========================
   BUTTON AREA
========================= */

.slider-form-actions {

    display: flex;

    justify-content: flex-end;

    gap: 10px;

    padding-top: 20px;

    margin-top: 5px;

    border-top: 1px solid #e9ecef;
}


/* =========================
   UPDATE BUTTON
========================= */

.slider-form-actions .update-btn {

    min-width: 160px;

    padding: 11px 18px;

    font-weight: 600;
}


/* =========================
   CANCEL BUTTON
========================= */

.slider-form-actions .cancel-btn {

    min-width: 100px;

    padding: 11px 18px;

    font-weight: 600;
}


/* =========================
   SLIDER ID INFO
========================= */

.slider-id-info {

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
   IMAGE PREVIEW
========================= */

.slider-image-preview {
    margin-bottom: 12px;
}

.slider-image-preview img {
    width: 220px;
    height: 110px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #e9ecef;
}


/* =========================
   MOBILE
========================= */

@media (max-width: 576px) {

    .slider-form-body {
        padding: 20px;
    }

    .slider-form-header {
        padding: 22px 20px 16px;
    }

    .slider-form-actions {
        flex-direction: column-reverse;
    }

    .slider-form-actions .btn {
        width: 100%;
    }

    .slider-image-preview img {
        width: 100%;
        height: auto;
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


<div class="container-fluid py-4">


<!-- =========================
     PAGE HEADER
========================== -->

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

    <div>

        <h4 class="fw-bold text-dark mb-1">
            Edit Slider
        </h4>

        <p class="text-sm text-secondary mb-0">
            Update the slider information
        </p>

    </div>


    <!-- BACK BUTTON -->

    <a
        href="index.php"
        class="btn btn-outline-secondary btn-sm"
    >

        <i class="fa-solid fa-arrow-left me-1"></i>

        Back to Sliders

    </a>

</div>


<!-- =========================
     SLIDER FORM
========================== -->

<div class="row">

<div class="col-lg-8 col-md-10 mx-auto">


<div class="card slider-form-card">


<!-- =========================
     CARD HEADER
========================== -->

<div class="slider-form-header">

    <h5 class="mb-1">
        Slider Information
    </h5>

    <p class="text-sm">
        Update the details of this homepage slider.
    </p>

    <span class="slider-id-info">

        <i class="fa-solid fa-hashtag"></i>

        Slider ID:
        <?php echo htmlspecialchars($sliderId); ?>

    </span>

</div>


<!-- =========================
     FORM BODY
========================== -->

<div class="slider-form-body">


<?php if ($error !== ''): ?>

    <div class="alert alert-danger text-sm">
        <?php echo htmlspecialchars($error); ?>
    </div>

<?php endif; ?>


<form
    method="POST"
    action=""
    enctype="multipart/form-data"
>


<!-- =========================
     SUBTITLE
========================== -->

<div class="slider-field">

    <label for="sliderSubtitle">

        <i class="fa-solid fa-align-left"></i>

        Subtitle

    </label>


    <input
        type="text"
        id="sliderSubtitle"
        name="subtitle"
        class="slider-input"
        value="<?php echo htmlspecialchars($subtitle); ?>"
        placeholder="e.g. New Collection"
    >


    <span class="field-help">
        Small text displayed above the main slider title.
    </span>

</div>


<!-- =========================
     TITLE
========================== -->

<div class="slider-field">

    <label for="sliderTitle">

        <i class="fa-solid fa-heading"></i>

        Slider Title

    </label>


    <input
        type="text"
        id="sliderTitle"
        name="title"
        class="slider-input"
        value="<?php echo htmlspecialchars($title); ?>"
        placeholder="e.g. Summer Fashion Collection"
        required
    >


    <span class="field-help">
        Main heading displayed on the homepage slider.
    </span>

</div>


<!-- =========================
     OLD PRICE
========================== -->

<div class="slider-field">

    <label for="oldPrice">

        <i class="fa-solid fa-tag"></i>

        Old Price

    </label>


    <input
        type="number"
        step="0.01"
        min="0"
        id="oldPrice"
        name="old_price"
        class="slider-input"
        value="<?php echo htmlspecialchars($oldPrice ?? ''); ?>"
        placeholder="e.g. 4999"
    >


    <span class="field-help">
        Optional. Original price shown before discount.
    </span>

</div>


<!-- =========================
     CURRENT PRICE
========================== -->

<div class="slider-field">

    <label for="currentPrice">

        <i class="fa-solid fa-money-bill"></i>

        Current Price

    </label>


    <input
        type="number"
        step="0.01"
        min="0"
        id="currentPrice"
        name="price"
        class="slider-input"
        value="<?php echo htmlspecialchars($price ?? ''); ?>"
        placeholder="e.g. 3499"
    >


    <span class="field-help">
        Current discounted price displayed on the slider.
    </span>

</div>


<!-- =========================
     IMAGE
========================== -->

<div class="slider-field">

    <label for="sliderImage">

        <i class="fa-solid fa-image"></i>

        Slider Image

    </label>


    <?php if (!empty($oldImage)): ?>

        <div class="slider-image-preview">

            <img
                src="../../public/uploads/sliders/<?php echo htmlspecialchars($oldImage); ?>"
                alt="Slider Image"
            >

        </div>

    <?php endif; ?>


    <input
        type="file"
        id="sliderImage"
        name="image"
        class="slider-input"
        accept="image/jpeg,image/png,image/webp"
    >


    <span class="field-help">

        Optional. Upload a new image to replace the current one.
        JPG, PNG or WEBP only. Maximum size: 5MB.

    </span>

</div>


<!-- =========================
     BUTTON TEXT
========================== -->

<div class="slider-field">

    <label for="buttonText">

        <i class="fa-solid fa-arrow-pointer"></i>

        Button Text

    </label>


    <input
        type="text"
        id="buttonText"
        name="button_text"
        class="slider-input"
        value="<?php echo htmlspecialchars($buttonText); ?>"
        placeholder="e.g. Shop Now"
        required
    >


    <span class="field-help">
        Text displayed inside the slider button.
    </span>

</div>


<!-- =========================
     BUTTON LINK
========================== -->

<div class="slider-field">

    <label for="buttonLink">

        <i class="fa-solid fa-link"></i>

        Button Link

    </label>


    <input
        type="text"
        id="buttonLink"
        name="button_link"
        class="slider-input"
        value="<?php echo htmlspecialchars($buttonLink); ?>"
        placeholder="e.g. products.php"
        required
    >


    <span class="field-help">
        Page that opens when the slider button is clicked.
    </span>

</div>


<!-- =========================
     SORT ORDER
========================== -->

<div class="slider-field">

    <label for="sortOrder">

        <i class="fa-solid fa-arrow-down-1-9"></i>

        Sort Order

    </label>


    <input
        type="number"
        id="sortOrder"
        name="sort_order"
        class="slider-input"
        value="<?php echo htmlspecialchars($sortOrder); ?>"
        min="0"
        required
    >


    <span class="field-help">
        Lower numbers appear first. Example: 0, 1, 2.
    </span>

</div>


<!-- =========================
     STATUS
========================== -->

<div class="slider-field">

    <label for="sliderStatus">

        <i class="fa-solid fa-circle-check"></i>

        Slider Status

    </label>


    <select
        id="sliderStatus"
        name="status"
        class="slider-input"
        required
    >

        <option
            value="active"
            <?php echo ($sliderStatus === "active") ? "selected" : ""; ?>
        >
            Active
        </option>

        <option
            value="inactive"
            <?php echo ($sliderStatus === "inactive") ? "selected" : ""; ?>
        >
            Inactive
        </option>

    </select>


    <span class="field-help">
        Change whether this slider is currently visible on the homepage.
    </span>

</div>


<!-- =========================
     BUTTONS
========================== -->

<div class="slider-form-actions">


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
        name="update_slider"
        class="btn bg-gradient-dark update-btn"
    >

        <i class="fa-solid fa-pen-to-square me-1"></i>

        Update Slider

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