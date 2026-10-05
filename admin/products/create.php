<?php

$pageTitle = "Create Product";

require_once __DIR__ . '/../../core/Sessions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth($mysqli);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$error = '';

/*
|--------------------------------------------------------------------------
| Create Product
|--------------------------------------------------------------------------
*/

if (isset($_POST['create_product'])) {

    $productName = trim($_POST['product_name'] ?? '');
    $categoryId  = (int) ($_POST['category'] ?? 0);
    $price       = trim($_POST['price'] ?? '');
    $stock       = (int) ($_POST['stock'] ?? 0);
    $status      = $_POST['status'] ?? 'active';
    $description = trim($_POST['description'] ?? '');
    $discountPercentage = trim($_POST['discount_percentage'] ?? '');
    $dealStart = trim($_POST['deal_start'] ?? '');
    $dealEnd   = trim($_POST['deal_end'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($productName === '') {
        $error = "Product name is required.";

    } elseif ($categoryId <= 0) {
        $error = "Please select a category.";

    } elseif ($price === '' || !is_numeric($price) || (float) $price < 0) {
        $error = "Please enter a valid price.";

    } elseif ($stock < 0) {
        $error = "Stock cannot be negative.";

    } elseif (!in_array($status, ['active', 'inactive'], true)) {
        $error = "Invalid product status.";

    }

    // deals validations 
    if($error === '') {
        // empty discount means no deal
        if ($discountPercentage === '')
            {
                $discountPercentage = 0;
            }
            if (
    !is_numeric($discountPercentage) ||
    (float) $discountPercentage < 0 ||
    (float) $discountPercentage > 100
){
                $error = "Discount must be between 0 and 100 percent.";
            }
            // If discount is greater than 0,
        // start and end dates are required
        elseif ((float) $discountPercentage > 0) {

            if ($dealStart === '' || $dealEnd === '') {
                $error = "Deal start and end dates are required when a discount is set.";
            }

            elseif (strtotime($dealStart) === false) {
                $error = "Invalid deal start date.";
            }

            elseif (strtotime($dealEnd) === false) {
                $error = "Invalid deal end date.";
            }
            elseif (strtotime($dealEnd) <= strtotime($dealStart)) {
                $error = "Deal end date must be after the start date.";
            }
     }
     // No discount means no deal dates
        else {
            $dealStart = null;
            $dealEnd = null;
        }
    }

    // conversion date time 
    if ($error === '' && (float) $discountPercentage > 0) {

    $startDate = DateTime::createFromFormat('Y-m-d\TH:i', $dealStart);
    $endDate   = DateTime::createFromFormat('Y-m-d\TH:i', $dealEnd);

    if (!$startDate || !$endDate) {
        $error = "Invalid deal date format.";
    } else {
        $dealStart = $startDate->format('Y-m-d H:i:s');
        $dealEnd   = $endDate->format('Y-m-d H:i:s');
    }
}
    
    /*
    |--------------------------------------------------------------------------
    | Check Category
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $stmt = $mysqli->prepare(
            "SELECT id
             FROM categories
             WHERE id = ?
             AND status = 1
             LIMIT 1"
        );

        $stmt->bind_param("i", $categoryId);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            $error = "Selected category does not exist or is inactive.";
        }

        $stmt->close();
    }

    /*
|--------------------------------------------------------------------------
| Image Upload
|--------------------------------------------------------------------------
*/


if ($error === '') {

    $images = $_FILES['images'] ?? null;

    if (
        !$images ||
        !isset($images['name']) ||
        !is_array($images['name'])
    ) {
        $error = "Product images are required.";
    } else {

        /*
        |--------------------------------------------------------------------------
        | Remove empty upload entries
        |--------------------------------------------------------------------------
        */

        $validIndexes = [];

        foreach ($images['error'] as $index => $uploadError) {

            if ($uploadError !== UPLOAD_ERR_NO_FILE) {
                $validIndexes[] = $index;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Maximum 5 Images
        |--------------------------------------------------------------------------
        */

        if (count($validIndexes) < 1) {

            $error = "Please upload at least one product image.";

        } elseif (count($validIndexes) > 5) {

            $error = "You can upload a maximum of 5 images.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Validate Each Image
            |--------------------------------------------------------------------------
            */

            $maxFileSize = 2 * 1024 * 1024;

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            $validatedImages = [];

            foreach ($validIndexes as $index) {

                $image = [
                    'name'     => $images['name'][$index],
                    'type'     => $images['type'][$index],
                    'tmp_name' => $images['tmp_name'][$index],
                    'error'    => $images['error'][$index],
                    'size'     => $images['size'][$index]
                ];

                /*
                | Upload error
                */

                if ($image['error'] !== UPLOAD_ERR_OK) {

                    $error = "Failed to upload one of the images.";
                    break;
                }

                /*
                | File size
                */

                if ($image['size'] > $maxFileSize) {

                    $error = "Each image must not exceed 2MB.";
                    break;
                }

                /*
                | MIME type
                */

                $finfo = finfo_open(FILEINFO_MIME_TYPE);

                $mimeType = finfo_file(
                    $finfo,
                    $image['tmp_name']
                );

                finfo_close($finfo);

                if (!isset($allowedTypes[$mimeType])) {

                    $error = "Only JPG, PNG and WEBP images are allowed.";
                    break;
                }

                /*
                | Store validated image
                */

                $validatedImages[] = [
                    'tmp_name' => $image['tmp_name'],
                    'extension' => $allowedTypes[$mimeType]
                ];
            }

          
        }
    }
}

 
    /*
    |--------------------------------------------------------------------------
    | Generate Slug
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $slug = strtolower(
            trim(
                preg_replace(
                    '/[^A-Za-z0-9-]+/',
                    '-',
                    $productName
                ),
                '-'
            )
        );

        if ($slug === '') {
            $slug = 'product';
        }

        /*
        | Make sure slug is unique
        */
        $originalSlug = $slug;
        $counter = 1;

        while (true) {

            $stmt = $mysqli->prepare(
                "SELECT id
                 FROM products
                 WHERE slug = ?
                 LIMIT 1"
            );

            $stmt->bind_param("s", $slug);
            $stmt->execute();

            $result = $stmt->get_result();

            $exists = $result->num_rows > 0;

            $stmt->close();

            if (!$exists) {
                break;
            }

            $counter++;
            $slug = $originalSlug . '-' . $counter;
        }
    }
/*
|--------------------------------------------------------------------------
| Insert Product
|--------------------------------------------------------------------------
*/

if ($error === '') {

    $priceValue = (float) $price;
    $discountValue = (float) $discountPercentage;
    $statusValue = ($status === 'active') ? 1 : 0;

    $dealStartValue = ($dealStart !== '') ? $dealStart : null;
    $dealEndValue   = ($dealEnd !== '') ? $dealEnd : null;


    /*
    |--------------------------------------------------------------------------
    | Insert Product First
    |--------------------------------------------------------------------------
    */

    $stmt = $mysqli->prepare(
        "INSERT INTO products
        (
            category_id,
            name,
            slug,
            description,
            price,
            discount_percentage,
            deal_start,
            deal_end,
            stock,
            image,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    /*
    | Initially image column is empty JSON array.
    */

    $emptyImages = json_encode([]);

    $stmt->bind_param(
        "isssddssisi",
        $categoryId,
        $productName,
        $slug,
        $description,
        $priceValue,
        $discountValue,
        $dealStartValue,
        $dealEndValue,
        $stock,
        $emptyImages,
        $statusValue
    );


    if ($stmt->execute()) {

        /*
        |--------------------------------------------------------------------------
        | Get Newly Created Product ID
        |--------------------------------------------------------------------------
        */

        $newProductId = $mysqli->insert_id;

        $stmt->close();


        /*
        |--------------------------------------------------------------------------
        | Create Product-Specific Folder
        |--------------------------------------------------------------------------
        */

        $productDirectory =
            __DIR__ .
            '/../../public/uploads/products/' .
            $newProductId .
            '/';

        if (!is_dir($productDirectory)) {

            if (!mkdir($productDirectory, 0755, true)) {

                /*
                | Remove product if folder creation fails
                */

                $deleteStmt = $mysqli->prepare(
                    "DELETE FROM products WHERE id = ?"
                );

                $deleteStmt->bind_param(
                    "i",
                    $newProductId
                );

                $deleteStmt->execute();
                $deleteStmt->close();

                $error = "Failed to create product image folder.";

            }
        }


        /*
        |--------------------------------------------------------------------------
        | Save Images
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $imagePaths = [];
            $savedFiles = [];

            foreach ($validatedImages as $imageData) {

                $extension = $imageData['extension'];

                /*
                | Generate unique filename
                */

                $fileName =
                    bin2hex(random_bytes(8)) .
                    '.' .
                    $extension;

                $destination =
                    $productDirectory .
                    $fileName;


                /*
                | Move uploaded file
                */

                if (
                    move_uploaded_file(
                        $imageData['tmp_name'],
                        $destination
                    )
                ) {

                    /*
                    | Save relative path for JSON
                    */

                    $relativePath =
                        $newProductId .
                        '/' .
                        $fileName;

                    $imagePaths[] = $relativePath;
                    $savedFiles[] = $destination;

                } else {

                    $error = "Failed to save one of the product images.";
                    break;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | If Image Upload Failed
            |--------------------------------------------------------------------------
            */

            if ($error !== '') {

                /*
                | Delete already uploaded images
                */

                foreach ($savedFiles as $savedFile) {

                    if (file_exists($savedFile)) {
                        unlink($savedFile);
                    }
                }

                /*
                | Remove product folder
                */

                if (is_dir($productDirectory)) {
                    rmdir($productDirectory);
                }

                /*
                | Remove product record
                */

                $deleteStmt = $mysqli->prepare(
                    "DELETE FROM products WHERE id = ?"
                );

                $deleteStmt->bind_param(
                    "i",
                    $newProductId
                );

                $deleteStmt->execute();
                $deleteStmt->close();

            } else {

                /*
                |--------------------------------------------------------------------------
                | Convert Image Paths To JSON
                |--------------------------------------------------------------------------
                */

                $imagesJson = json_encode(
                    $imagePaths,
                    JSON_UNESCAPED_SLASHES
                );


                /*
                |--------------------------------------------------------------------------
                | Update Product With Image JSON
                |--------------------------------------------------------------------------
                */

                $updateStmt = $mysqli->prepare(
                    "UPDATE products
                     SET image = ?
                     WHERE id = ?"
                );

                $updateStmt->bind_param(
                    "si",
                    $imagesJson,
                    $newProductId
                );

                if ($updateStmt->execute()) {

                    $updateStmt->close();

                    header('Location: index.php?success=created');
                    exit;

                } else {

                    $updateStmt->close();

                    /*
                    | Delete uploaded images
                    */

                    foreach ($savedFiles as $savedFile) {

                        if (file_exists($savedFile)) {
                            unlink($savedFile);
                        }
                    }

                    if (is_dir($productDirectory)) {
                        rmdir($productDirectory);
                    }

                    /*
                    | Delete product record
                    */

                    $deleteStmt = $mysqli->prepare(
                        "DELETE FROM products WHERE id = ?"
                    );

                    $deleteStmt->bind_param(
                        "i",
                        $newProductId
                    );

                    $deleteStmt->execute();
                    $deleteStmt->close();

                    $error = "Failed to save product images.";
                }
            }
        }

    } else {

        $stmt->close();

        $error = "Failed to create product.";
    }
}
}
/*
|--------------------------------------------------------------------------
| Get Active Categories
|--------------------------------------------------------------------------
*/

$categories = [];

$stmt = $mysqli->prepare(
    "SELECT id, name
     FROM categories
     WHERE status = 1
     ORDER BY name ASC"
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

<meta name="viewport"
    content="width=device-width, initial-scale=1, shrink-to-fit=no">

<link rel="apple-touch-icon"
    sizes="76x76"
    href="../assets/img/apple-icon.png">

<link rel="icon"
    type="image/png"
    href="../assets/img/favicon.png">

<title>Create Product - ClothWear</title>


<!-- Fonts -->
<link
    rel="stylesheet"
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


<!-- =========================
     CUSTOM PRODUCT FORM CSS
========================== -->

<style>

    /* =========================
       FORM CARD
    ========================== */

    .product-form-card {
        border-radius: 16px;
        overflow: hidden;
    }


    /* =========================
       CARD HEADER
    ========================== */

    .product-form-header {
        padding: 28px 30px 18px;
        border-bottom: 1px solid #e9ecef;
    }

    .product-form-header h5 {
        font-weight: 700;
        color: #212529;
    }

    .product-form-header p {
        margin-bottom: 0;
        color: #67748e;
    }


    /* =========================
       FORM BODY
    ========================== */

    .product-form-body {
        padding: 30px;
    }


    /* =========================
       FORM FIELD
    ========================== */

    .product-field {
        margin-bottom: 28px;
    }


    /* =========================
       LABEL
    ========================== */

    .product-field label {
        display: flex;
        align-items: center;
        gap: 8px;

        margin-bottom: 10px;

        font-size: 14px;
        font-weight: 700;

        color: #344767;
    }


    /* Label Icon */

    .product-field label i {
        font-size: 14px;
        /* color: #2e37a4; */
         color: #242426;
    }


    /* =========================
       INPUT / SELECT / TEXTAREA
    ========================== */

    .product-input {
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

    .product-input::placeholder {
        color: #adb5bd;
    }


    /* =========================
       INPUT FOCUS
    ========================== */

    .product-input:focus {

        border-color: #2e37a4;

        box-shadow:
            0 0 0 3px rgba(46, 55, 164, 0.10);

        outline: none;
    }


    /* =========================
       SELECT
    ========================== */

    select.product-input {
        cursor: pointer;
    }


    /* =========================
       TEXTAREA
    ========================== */

    textarea.product-input {
        min-height: 130px;
        resize: vertical;
    }


    /* =========================
       FILE INPUT
    ========================== */

    input[type="file"].product-input {
        padding: 10px 12px;
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

    .product-form-actions {

        display: flex;

        justify-content: flex-end;

        gap: 10px;

        padding-top: 20px;

        margin-top: 5px;

        border-top: 1px solid #e9ecef;
    }


    /* =========================
       CREATE BUTTON
    ========================== */

    .product-form-actions .create-btn {

        min-width: 160px;

        padding: 11px 18px;

        font-weight: 600;
    }


    /* =========================
       CANCEL BUTTON
    ========================== */

    .product-form-actions .cancel-btn {

        min-width: 100px;

        padding: 11px 18px;

        font-weight: 600;
    }


    /* =========================
       MOBILE
    ========================== */

    @media (max-width: 576px) {

        .product-form-body {
            padding: 20px;
        }


        .product-form-header {
            padding: 22px 20px 16px;
        }


        .product-form-actions {
            flex-direction: column-reverse;
        }


        .product-form-actions .btn {
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


    <div class="container-fluid py-4">


        <!-- =========================
             PAGE HEADER
        ========================== -->

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">

            <div>

                <h4 class="fw-bold text-dark mb-1">
                    Create Product
                </h4>

                <p class="text-sm text-secondary mb-0">
                    Add a new product to your inventory
                </p>

            </div>


            <!-- BACK BUTTON -->

            <a
                href="index.php"
                class="btn btn-outline-secondary btn-sm"
            >

                <i class="fa-solid fa-arrow-left me-1"></i>

                Back to Products

            </a>

        </div>


        <!-- =========================
             PRODUCT FORM CARD
        ========================== -->

        <div class="row">

            <div class="col-lg-10 col-md-11 mx-auto">


                <div class="card product-form-card">


                    <!-- =========================
                         CARD HEADER
                    ========================== -->

                    <div class="product-form-header">

                        <h5 class="mb-1">
                            Product Information
                        </h5>

                        <p class="text-sm">
                            Enter the details of the new product.
                        </p>

                    </div>


                    <!-- =========================
                         FORM BODY
                    ========================== -->

                    <div class="product-form-body">


                     <form
                        action=""
                        method="POST"
                        enctype="multipart/form-data"
                    >

                        <?php if ($error !== ''): ?>

                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>

                        <?php endif; ?>
                            

                            <!-- =========================
                                 PRODUCT NAME
                            ========================== -->

                            <div class="product-field">

                                <label for="product_name">

                                    <i class="fa-solid fa-box"></i>

                                    Product Name

                                </label>


                                <input
                                    type="text"
                                    id="product_name"
                                    name="product_name"
                                    class="product-input"
                                    placeholder="Enter product name"
                                    required
                                >


                                <span class="field-help">
                                    Enter the name of the product.
                                </span>

                            </div>


                            <!-- =========================
                                 CATEGORY + PRICE
                            ========================== -->

                            <div class="row">


                                <!-- CATEGORY -->

                                <div class="col-md-6">

                                    <div class="product-field">

                                        <label for="category">

                                            <i class="fa-solid fa-layer-group"></i>

                                            Category

                                        </label>


                                        <select
                                            id="category"
                                            name="category"
                                            class="product-input"
                                            required
                                        >

                                            <?php foreach ($categories as $category): ?>

    <option value="<?php echo (int) $category['id']; ?>">
        <?php echo htmlspecialchars($category['name']); ?>
    </option>

<?php endforeach; ?>

                                        </select>


                                        <span class="field-help">
                                            Select the category for this product.
                                        </span>

                                    </div>

                                </div>


                                <!-- PRICE -->

                                <div class="col-md-6">

                                    <div class="product-field">

                                        <label for="price">

                                            <i class="fa-solid fa-money-bill"></i>

                                            Price

                                        </label>


                                        <input
                                            type="number"
                                            id="price"
                                            name="price"
                                            class="product-input"
                                            placeholder="Enter price"
                                            min="0"
                                            step="0.01"
                                            required
                                        >


                                        <span class="field-help">
                                            Enter the product price.
                                        </span>

                                    </div>

                                </div>

                            </div>


                            <!-- =========================
                                 STOCK + STATUS
                            ========================== -->

                            <div class="row">


                                <!-- STOCK -->

             <div class="col-md-6">

                                                    <div class="product-field">

                                                        <label for="stock">

                                                            <i class="fa-solid fa-boxes-stacked"></i>

                                                            Stock Quantity

                                                        </label>


                                                        <input
                                                            type="number"
                                                            id="stock"
                                                            name="stock"
                                                            class="product-input"
                                                            placeholder="Enter stock quantity"
                                                            min="0"
                                                            required
                                                        >


                                                        <span class="field-help">
                                                            Enter the available quantity.
                                                        </span>

                                                    </div>

                                                </div>
                <!-- Discount Percentage -->
                <div class="col-md-6">
                    <div class="product-field">
                        <label for="discount_percentage">Discount Percentage</label>
                        <input
                            type="number"
                            name="discount_percentage"
                            id="discount_percentage"
                            class="form-control product-input"
                            min="0"
                            max="100"
                            step="0.01"
                            value="<?php echo htmlspecialchars($_POST['discount_percentage'] ?? ''); ?>"
                            placeholder="e.g. 20"
                        >
                        <small class="text-muted">
                            Enter 0 if this product has no deal.
                        </small>
                    </div>
                </div>

                <!-- Deal Start -->
                <div class="col-md-6">
                    <div class="product-field">
                        <label for="deal_start">Deal Start</label>
                        <input
                            type="datetime-local"
                            name="deal_start"
                            id="deal_start"
                            class="form-control product-input"
                            value="<?php echo htmlspecialchars($_POST['deal_start'] ?? ''); ?>"
                        >
                    </div>
                </div>

                <!-- Deal End -->
                <div class="col-md-6">
                    <div class="product-field">
                        <label for="deal_end">Deal End</label>
                        <input
                            type="datetime-local"
                            name="deal_end"
                            id="deal_end"
                            class="form-control product-input"
                            value="<?php echo htmlspecialchars($_POST['deal_end'] ?? ''); ?>"
                        >
                    </div>
                </div>

                                <!-- STATUS -->

                                <div class="col-md-6">

                                    <div class="product-field">

                                        <label for="status">

                                            <i class="fa-solid fa-circle-check"></i>

                                            Product Status

                                        </label>


                                        <select
                                            id="status"
                                            name="status"
                                            class="product-input"
                                            required
                                        >

                                            <option value="active">
                                                Active
                                            </option>

                                            <option value="inactive">
                                                Inactive
                                            </option>

                                        </select>


                                        <span class="field-help">
                                            Choose whether this product is active.
                                        </span>

                                    </div>

                                </div>

                            </div>


                            <!-- =========================
                                 DESCRIPTION
                            ========================== -->

                            <div class="product-field">

                                <label for="description">

                                    <i class="fa-solid fa-align-left"></i>

                                    Product Description

                                </label>


                                <textarea
                                    id="description"
                                    name="description"
                                    class="product-input"
                                    rows="5"
                                    placeholder="Enter product description"
                                ></textarea>


                                <span class="field-help">
                                    Add a short description of the product.
                                </span>

                            </div>


                            <!-- =========================
                                 PRODUCT IMAGE
                            ========================== -->

                            <div class="product-field">

                                <label for="image">

                                    <i class="fa-solid fa-image"></i>

                                    Product Image

                                </label>

                                <input 
                                    type="file" 
                                    id="image" 
                                    name="images[]" 
                                    class="product-input" 
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                    required
                                >

                                <span class="field-help">
                                    Upload 1 to 5 images. JPG, PNG or WEBP only. Maximum 2MB per image.
                                </span>

                            </div>


                            <!-- =========================
                                 BUTTONS
                            ========================== -->

                            <div class="product-form-actions">


                                <!-- CANCEL -->

                                <a
                                    href="index.php"
                                    class="btn btn-light cancel-btn"
                                >

                                    <i class="fa-solid fa-xmark me-1"></i>

                                    Cancel

                                </a>


                                <!-- CREATE -->

                                <button
                                    type="submit"
                                    name="create_product"
                                    class="btn bg-gradient-dark create-btn"
                                >

                                    <i class="fa-solid fa-plus me-1"></i>

                                    Create Product

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
