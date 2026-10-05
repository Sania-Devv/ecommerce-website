<?php

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
| Get Product Images
|--------------------------------------------------------------------------
*/

$stmt = $mysqli->prepare(
    "SELECT image
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

$productImages = $product['image'];

$stmt->close();


/*
|--------------------------------------------------------------------------
| Delete Product
|--------------------------------------------------------------------------
*/

$stmt = $mysqli->prepare(
    "DELETE FROM products
     WHERE id = ?"
);

$stmt->bind_param("i", $productId);

if ($stmt->execute()) {

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Delete All Product Images
    |--------------------------------------------------------------------------
    */

    $productFolder = __DIR__ .
        '/../../public/uploads/products/' .
        $productId;


    // Decode JSON images
    $images = json_decode($productImages, true);


    /*
    |--------------------------------------------------------------------------
    | Delete Images
    |--------------------------------------------------------------------------
    */

    if (is_array($images)) {

        foreach ($images as $image) {

            if (empty($image)) {
                continue;
            }

            $imagePath = __DIR__ .
                '/../../public/uploads/products/' .
                $image;

            if (file_exists($imagePath) && is_file($imagePath)) {
                unlink($imagePath);
            }
        }

    } elseif (!empty($productImages)) {

        /*
        |----------------------------------------------------------------------
        | Old single-image format support
        |----------------------------------------------------------------------
        */

        $imagePath = __DIR__ .
            '/../../public/uploads/products/' .
            $productImages;

        if (file_exists($imagePath) && is_file($imagePath)) {
            unlink($imagePath);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Product Folder
    |--------------------------------------------------------------------------
    */

    if (is_dir($productFolder)) {

        $files = scandir($productFolder);

        foreach ($files as $file) {

            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $productFolder . '/' . $file;

            if (is_file($filePath)) {
                unlink($filePath);
            }
        }

        rmdir($productFolder);
    }


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header('Location: index.php?success=deleted');
    exit;

} else {

    $stmt->close();

    header('Location: index.php?error=delete_failed');
    exit;
}