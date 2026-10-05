<?php

$pageTitle = "Edit Product";

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
| Get Product ID
|--------------------------------------------------------------------------
*/

$productId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($productId <= 0) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Product From Database
|--------------------------------------------------------------------------
*/

$stmt = $mysqli->prepare(
    "SELECT
        id,
        category_id,
        name,
        description,
        price,
        discount_percentage,
        deal_start,
        deal_end,
        stock,
        image,
        status
     FROM products
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $productId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    $stmt->close();

    header('Location: index.php');
    exit;
}

$product = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Existing Product Data
|--------------------------------------------------------------------------
*/

$productName        = $product['name'];
$productCategory    = (string) $product['category_id'];
$productPrice       = $product['price'];
$productDiscount    = $product['discount_percentage'];
$productDealStart   = $product['deal_start'];
$productDealEnd     = $product['deal_end'];
$productStock       = $product['stock'];

$productStatus = ((int) $product['status'] === 1)
    ? 'active'
    : 'inactive';

$productDescription = $product['description'];
$productImage       = $product['image'];


/*
|--------------------------------------------------------------------------
| Convert Existing Images Into Array
|--------------------------------------------------------------------------
|
| New format:
| ["22/image1.webp","22/image2.webp"]
|
| Old format:
| image1.webp
|
*/

$currentImages = [];

$decodedImages = json_decode($productImage, true);

if (is_array($decodedImages)) {

    foreach ($decodedImages as $imagePath) {

        if (
            is_string($imagePath) &&
            trim($imagePath) !== ''
        ) {

            $currentImages[] = $imagePath;
        }
    }

} elseif (!empty($productImage)) {

    /*
    | Old single image format
    */

    $currentImages[] = $productImage;
}


/*
|--------------------------------------------------------------------------
| Update Product
|--------------------------------------------------------------------------
*/

if (isset($_POST['update_product'])) {

    $updateProductId = (int) ($_POST['product_id'] ?? 0);

    $productName = trim(
        $_POST['product_name'] ?? ''
    );

    $categoryId = (int) (
        $_POST['category'] ?? 0
    );

    $price = trim(
        $_POST['price'] ?? ''
    );

    $stock = (int) (
        $_POST['stock'] ?? 0
    );

    $status = $_POST['status'] ?? '';

    $description = trim(
        $_POST['description'] ?? ''
    );

    $discountPercentage = trim(
        $_POST['discount_percentage'] ?? ''
    );

    $dealStart = trim(
        $_POST['deal_start'] ?? ''
    );

    $dealEnd = trim(
        $_POST['deal_end'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($updateProductId !== $productId) {

        $error = "Invalid product.";

    } elseif ($productName === '') {

        $error = "Product name is required.";

    } elseif ($categoryId <= 0) {

        $error = "Please select a category.";

    } elseif (
        $price === '' ||
        !is_numeric($price) ||
        (float) $price < 0
    ) {

        $error = "Please enter a valid price.";

    } elseif ($stock < 0) {

        $error = "Stock cannot be negative.";

    } elseif (
        !in_array(
            $status,
            ['active', 'inactive'],
            true
        )
    ) {

        $error = "Invalid product status.";
    }


    /*
    |--------------------------------------------------------------------------
    | Deal Validation
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        if ($discountPercentage === '') {

            $discountPercentage = 0;
        }


        if (
            !is_numeric($discountPercentage) ||
            (float) $discountPercentage < 0 ||
            (float) $discountPercentage > 100
        ) {

            $error =
                "Discount must be between 0 and 100 percent.";

        } elseif (
            (float) $discountPercentage > 0
        ) {

            if (
                $dealStart === '' ||
                $dealEnd === ''
            ) {

                $error =
                    "Deal start and end dates are required when a discount is set.";

            } else {

                $startDate = DateTime::createFromFormat(
                    'Y-m-d\TH:i',
                    $dealStart
                );

                $endDate = DateTime::createFromFormat(
                    'Y-m-d\TH:i',
                    $dealEnd
                );


                if (!$startDate) {

                    $error =
                        "Invalid deal start date.";

                } elseif (!$endDate) {

                    $error =
                        "Invalid deal end date.";

                } elseif ($endDate <= $startDate) {

                    $error =
                        "Deal end date must be after the start date.";

                } else {

                    $dealStart =
                        $startDate->format(
                            'Y-m-d H:i:s'
                        );

                    $dealEnd =
                        $endDate->format(
                            'Y-m-d H:i:s'
                        );
                }
            }

        } else {

            $dealStart = null;
            $dealEnd = null;
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

        $stmt->bind_param(
            "i",
            $categoryId
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {

            $error =
                "Selected category does not exist or is inactive.";
        }

        $stmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Image Handling
    |--------------------------------------------------------------------------
    */

    $finalImages = $currentImages;

    /*
    | Images selected for deletion
    */

    $removeImages = $_POST['remove_images'] ?? [];

    if (!is_array($removeImages)) {
        $removeImages = [];
    }


    /*
    |--------------------------------------------------------------------------
    | Remove Selected Images From Final List
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        if (!empty($removeImages)) {

            $finalImages = array_values(
                array_filter(
                    $finalImages,
                    function ($imagePath) use ($removeImages) {

                        return !in_array(
                            $imagePath,
                            $removeImages,
                            true
                        );
                    }
                )
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | New Image Upload
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $images = $_FILES['images'] ?? null;

        $validIndexes = [];

        if (
            $images &&
            isset($images['name']) &&
            is_array($images['name'])
        ) {

            foreach (
                $images['error']
                as $index => $uploadError
            ) {

                if (
                    $uploadError !==
                    UPLOAD_ERR_NO_FILE
                ) {

                    $validIndexes[] = $index;
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Maximum 5 Images
        |--------------------------------------------------------------------------
        */

        if (
            count($finalImages) +
            count($validIndexes) > 5
        ) {

            $error =
                "A product can have a maximum of 5 images.";
        }


        /*
        |--------------------------------------------------------------------------
        | Validate New Images
        |--------------------------------------------------------------------------
        */

        if (
            $error === '' &&
            count($validIndexes) > 0
        ) {

            $maxFileSize =
                2 * 1024 * 1024;

            $allowedTypes = [

                'image/jpeg' => 'jpg',

                'image/png' => 'png',

                'image/webp' => 'webp'
            ];

            $validatedImages = [];


            foreach (
                $validIndexes
                as $index
            ) {

                $image = [

                    'tmp_name' =>
                        $images['tmp_name'][$index],

                    'error' =>
                        $images['error'][$index],

                    'size' =>
                        $images['size'][$index]
                ];


                /*
                | Upload error
                */

                if (
                    $image['error'] !==
                    UPLOAD_ERR_OK
                ) {

                    $error =
                        "Failed to upload one of the images.";

                    break;
                }


                /*
                | File size
                */

                if (
                    $image['size'] >
                    $maxFileSize
                ) {

                    $error =
                        "Each image must not exceed 2MB.";

                    break;
                }


                /*
                | MIME validation
                */

                $finfo =
                    finfo_open(
                        FILEINFO_MIME_TYPE
                    );

                $mimeType =
                    finfo_file(
                        $finfo,
                        $image['tmp_name']
                    );

                finfo_close($finfo);


                if (
                    !isset(
                        $allowedTypes[$mimeType]
                    )
                ) {

                    $error =
                        "Only JPG, PNG and WEBP images are allowed.";

                    break;
                }


                $validatedImages[] = [

                    'tmp_name' =>
                        $image['tmp_name'],

                    'extension' =>
                        $allowedTypes[$mimeType]
                ];
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Save New Images
        |--------------------------------------------------------------------------
        */

        if (
            $error === '' &&
            !empty($validatedImages)
        ) {

            $productDirectory =
                __DIR__ .
                '/../../public/uploads/products/' .
                $productId .
                '/';


            if (!is_dir($productDirectory)) {

                if (
                    !mkdir(
                        $productDirectory,
                        0755,
                        true
                    )
                ) {

                    $error =
                        "Failed to create product image folder.";
                }
            }


            /*
            | Save files
            */

            if ($error === '') {

                $newSavedFiles = [];


                foreach (
                    $validatedImages
                    as $imageData
                ) {

                    $fileName =
                        bin2hex(
                            random_bytes(8)
                        ) .
                        '.' .
                        $imageData['extension'];


                    $destination =
                        $productDirectory .
                        $fileName;


                    if (
                        move_uploaded_file(
                            $imageData['tmp_name'],
                            $destination
                        )
                    ) {

                        $relativePath =
                            $productId .
                            '/' .
                            $fileName;

                        $finalImages[] =
                            $relativePath;

                        $newSavedFiles[] =
                            $destination;

                    } else {

                        $error =
                            "Failed to save one of the product images.";

                        break;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Cleanup Newly Uploaded Files
                |--------------------------------------------------------------------------
                */

                if ($error !== '') {

                    foreach (
                        $newSavedFiles
                        as $savedFile
                    ) {

                        if (
                            file_exists($savedFile)
                        ) {

                            unlink($savedFile);
                        }
                    }

                    $finalImages =
                        array_values(
                            array_filter(
                                $currentImages,
                                function ($imagePath)
                                use ($removeImages) {

                                    return !in_array(
                                        $imagePath,
                                        $removeImages,
                                        true
                                    );
                                }
                            )
                        );
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | At Least One Image
    |--------------------------------------------------------------------------
    */

    if (
        $error === '' &&
        count($finalImages) < 1
    ) {

        $error =
            "Product must have at least one image.";
    }


    /*
    |--------------------------------------------------------------------------
    | Update Product In Database
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $priceValue =
            (float) $price;

        $discountValue =
            (float) $discountPercentage;

        $statusValue =
            ($status === 'active')
                ? 1
                : 0;

        $dealStartValue =
            ($dealStart !== '')
                ? $dealStart
                : null;

        $dealEndValue =
            ($dealEnd !== '')
                ? $dealEnd
                : null;


        /*
        | Convert image paths to JSON
        */

        $imagesJson =
            json_encode(
                array_values($finalImages),
                JSON_UNESCAPED_SLASHES
            );


        $stmt = $mysqli->prepare(
            "UPDATE products
             SET
                category_id = ?,
                name = ?,
                description = ?,
                price = ?,
                discount_percentage = ?,
                deal_start = ?,
                deal_end = ?,
                stock = ?,
                image = ?,
                status = ?
             WHERE id = ?"
        );


        $stmt->bind_param(
            "issddssisii",
            $categoryId,
            $productName,
            $description,
            $priceValue,
            $discountValue,
            $dealStartValue,
            $dealEndValue,
            $stock,
            $imagesJson,
            $statusValue,
            $updateProductId
        );


        if ($stmt->execute()) {

            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | Delete Selected Old Images
            |--------------------------------------------------------------------------
            */

            if (!empty($removeImages)) {

                foreach (
                    $removeImages
                    as $removedImage
                ) {

                    if (
                        !in_array(
                            $removedImage,
                            $finalImages,
                            true
                        )
                    ) {

                        /*
                        | New folder format
                        */

                        $oldImagePath =
                            __DIR__ .
                            '/../../public/uploads/products/' .
                            $removedImage;


                        /*
                        | Old format compatibility
                        */

                        if (
                            !file_exists(
                                $oldImagePath
                            )
                        ) {

                            $oldImagePath =
                                __DIR__ .
                                '/../../public/uploads/products/' .
                                basename(
                                    $removedImage
                                );
                        }


                        if (
                            file_exists(
                                $oldImagePath
                            )
                        ) {

                            unlink(
                                $oldImagePath
                            );
                        }
                    }
                }
            }


            header(
                'Location: index.php?success=updated'
            );

            exit;

        } else {

            $stmt->close();

            $error =
                "Failed to update product.";
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

while (
    $row = $result->fetch_assoc()
) {

    $categories[] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Display Images
|--------------------------------------------------------------------------
*/

$displayImages = $currentImages;

if (
    isset($_POST['update_product']) &&
    $error !== ''
) {

    $displayImages =
        isset($finalImages)
            ? $finalImages
            : $currentImages;
}

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="utf-8">

<meta name="viewport"
    content="width=device-width, initial-scale=1, shrink-to-fit=no">

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

<title>Edit Product - ClothWear</title>


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

.product-field label i {
    font-size: 14px;
    color: #242426;
}


/* =========================
   INPUT
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

.product-input::placeholder {
    color: #adb5bd;
}

.product-input:focus {

    border-color: #2e37a4;

    box-shadow:
        0 0 0 3px rgba(46, 55, 164, 0.10);

    outline: none;
}

select.product-input {
    cursor: pointer;
}

textarea.product-input {
    min-height: 130px;
    resize: vertical;
}

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
   PRODUCT ID
========================== */

.product-id-info {

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
   IMAGE GRID
========================== */

.product-images-grid {

    display: grid;

    grid-template-columns:
        repeat(auto-fill, minmax(130px, 1fr));

    gap: 16px;

    margin-top: 12px;
}


/* =========================
   IMAGE CARD
========================== */

.product-image-card {

    position: relative;

    border: 1px solid #e1e5ea;

    border-radius: 12px;

    padding: 8px;

    background: #ffffff;
}


/* =========================
   IMAGE
========================== */

.product-image-card img {

    width: 100%;

    height: 120px;

    object-fit: cover;

    border-radius: 8px;

    display: block;
}


/* =========================
   REMOVE CHECKBOX
========================== */

.remove-image {

    display: flex;

    align-items: center;

    gap: 6px;

    margin-top: 8px;

    font-size: 12px;

    color: #dc3545;

    cursor: pointer;
}

.remove-image input {
    cursor: pointer;
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
   BUTTONS
========================== */

.product-form-actions .update-btn {

    min-width: 160px;

    padding: 11px 18px;

    font-weight: 600;
}

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

    .product-images-grid {
        grid-template-columns:
            repeat(2, 1fr);
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
            Edit Product
        </h4>

        <p class="text-sm text-secondary mb-0">
            Update the product information
        </p>

    </div>


    <a
        href="index.php"
        class="btn btn-outline-secondary btn-sm"
    >

        <i class="fa-solid fa-arrow-left me-1"></i>

        Back to Products

    </a>

</div>


<!-- =========================
     FORM CARD
========================== -->

<div class="row">

<div class="col-lg-10 col-md-11 mx-auto">


<div class="card product-form-card">


<!-- CARD HEADER -->

<div class="product-form-header">

    <h5 class="mb-1">
        Product Information
    </h5>

    <p class="text-sm">
        Update the details of this product.
    </p>


    <span class="product-id-info">

        <i class="fa-solid fa-hashtag"></i>

        Product ID:
        <?php echo htmlspecialchars($productId); ?>

    </span>

</div>


<!-- FORM BODY -->

<div class="product-form-body">


<form
    method="POST"
    action=""
    enctype="multipart/form-data"
>


<?php if ($error !== ''): ?>

    <div class="alert alert-danger mb-4">

        <?php
        echo htmlspecialchars($error);
        ?>

    </div>

<?php endif; ?>


<input
    type="hidden"
    name="product_id"
    value="<?php echo htmlspecialchars($productId); ?>"
>


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
        value="<?php
            echo htmlspecialchars(
                $_POST['product_name']
                ?? $productName
            );
        ?>"
        placeholder="Enter product name"
        required
    >


    <span class="field-help">
        Update the name of this product.
    </span>

</div>


<!-- =========================
     CATEGORY + PRICE
========================== -->

<div class="row">


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

<option value="">
    Select Category
</option>


<?php foreach ($categories as $category): ?>

<option
    value="<?php
        echo (int) $category['id'];
    ?>"
    <?php

    $selectedCategory =
        $_POST['category']
        ?? $productCategory;

    echo (
        (string) $selectedCategory ===
        (string) $category['id']
    )
        ? 'selected'
        : '';

    ?>
>

    <?php
    echo htmlspecialchars(
        $category['name']
    );
    ?>

</option>

<?php endforeach; ?>

</select>


<span class="field-help">
    Select the category for this product.
</span>

</div>

</div>


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
    value="<?php
        echo htmlspecialchars(
            $_POST['price']
            ?? $productPrice
        );
    ?>"
    placeholder="Enter price"
    min="0"
    step="0.01"
    required
>


<span class="field-help">
    Update the product price.
</span>

</div>

</div>

</div>


<!-- =========================
     DEAL INFORMATION
========================== -->

<div class="row">


<div class="col-md-6">

<div class="product-field">

<label for="discount_percentage">

    <i class="fa-solid fa-percent"></i>

    Discount Percentage

</label>


<input
    type="number"
    id="discount_percentage"
    name="discount_percentage"
    class="product-input"
    value="<?php

        echo htmlspecialchars(
            $_POST['discount_percentage']
            ?? $productDiscount
        );

    ?>"
    placeholder="e.g. 20"
    min="0"
    max="100"
    step="0.01"
>


<span class="field-help">
    Enter 0 if this product has no deal.
</span>

</div>

</div>


<div class="col-md-6">

<div class="product-field">

<label for="deal_start">

    <i class="fa-solid fa-calendar-plus"></i>

    Deal Start

</label>


<input
    type="datetime-local"
    id="deal_start"
    name="deal_start"
    class="product-input"
    value="<?php

        echo htmlspecialchars(
            $_POST['deal_start']
            ??
            (
                !empty($productDealStart)
                ? date(
                    'Y-m-d\TH:i',
                    strtotime(
                        $productDealStart
                    )
                )
                : ''
            )
        );

    ?>"
>


<span class="field-help">
    Set when the deal should start.
</span>

</div>

</div>


<div class="col-md-6">

<div class="product-field">

<label for="deal_end">

    <i class="fa-solid fa-calendar-xmark"></i>

    Deal End

</label>


<input
    type="datetime-local"
    id="deal_end"
    name="deal_end"
    class="product-input"
    value="<?php

        echo htmlspecialchars(
            $_POST['deal_end']
            ??
            (
                !empty($productDealEnd)
                ? date(
                    'Y-m-d\TH:i',
                    strtotime(
                        $productDealEnd
                    )
                )
                : ''
            )
        );

    ?>"
>


<span class="field-help">
    Set when the deal should end.
</span>

</div>

</div>

</div>


<!-- =========================
     STOCK + STATUS
========================== -->

<div class="row">


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
    value="<?php
        echo htmlspecialchars(
            $_POST['stock']
            ?? $productStock
        );
    ?>"
    placeholder="Enter stock quantity"
    min="0"
    required
>


<span class="field-help">
    Update the available quantity.
</span>

</div>

</div>


<div class="col-md-6">

<div class="product-field">

<label for="status">

    <i class="fa-solid fa-circle-check"></i>

    Product Status

</label>


<?php

$selectedStatus =
    $_POST['status']
    ?? $productStatus;

?>


<select
    id="status"
    name="status"
    class="product-input"
    required
>

<option
    value="active"
    <?php
        echo $selectedStatus === 'active'
            ? 'selected'
            : '';
    ?>
>
    Active
</option>


<option
    value="inactive"
    <?php
        echo $selectedStatus === 'inactive'
            ? 'selected'
            : '';
    ?>
>
    Inactive
</option>

</select>


<span class="field-help">
    Change whether this product is currently active.
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
><?php

echo htmlspecialchars(
    $_POST['description']
    ?? $productDescription
);

?></textarea>


<span class="field-help">
    Update the product description.
</span>

</div>


<!-- =========================
     PRODUCT IMAGES
========================== -->

<div class="product-field">


<label for="images">

    <i class="fa-solid fa-images"></i>

    Product Images

</label>


<?php if (!empty($displayImages)): ?>

    <p class="text-xs text-secondary mb-2">
        Current Images
    </p>


    <div class="product-images-grid">

        <?php foreach (
            $displayImages
            as $imagePath
        ): ?>

            <?php

            /*
            | New folder format:
            | 22/image.webp
            |
            | Old format:
            | image.webp
            */

            $imageUrl =
                '../../public/uploads/products/' .
                $imagePath;

            ?>


            <div class="product-image-card">

                <img
                    src="<?php
                        echo htmlspecialchars(
                            $imageUrl
                        );
                    ?>"
                    alt="<?php
                        echo htmlspecialchars(
                            $productName
                        );
                    ?>"
                >


                <label class="remove-image">

                    <input
                        type="checkbox"
                        name="remove_images[]"
                        value="<?php
                            echo htmlspecialchars(
                                $imagePath
                            );
                        ?>"
                    >

                    Remove

                </label>

            </div>

        <?php endforeach; ?>

    </div>

<?php else: ?>

    <p class="text-sm text-secondary">
        No product images available.
    </p>

<?php endif; ?>


<br>


<input
    type="file"
    id="images"
    name="images[]"
    class="product-input"
    accept="image/jpeg,image/png,image/webp"
    multiple
>


<span class="field-help">

    Upload additional images.
    JPG, PNG or WEBP only.
    Maximum 2MB per image.
    Maximum 5 images per product.

</span>


</div>


<!-- =========================
     BUTTONS
========================== -->

<div class="product-form-actions">


<a
    href="index.php"
    class="btn btn-light cancel-btn"
>

    <i class="fa-solid fa-xmark me-1"></i>

    Cancel

</a>


<button
    type="submit"
    name="update_product"
    class="btn bg-gradient-dark update-btn"
>

    <i class="fa-solid fa-pen-to-square me-1"></i>

    Update Product

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

<script
    src="../assets/js/material-dashboard.min.js?v=3.2.0"
></script>


</body>

</html>