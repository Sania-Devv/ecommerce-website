<?php

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
   GET CATEGORY
========================= */

$stmt = $mysqli->prepare(
    "SELECT id, image
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
   CHECK PRODUCTS
========================= */

$stmt = $mysqli->prepare(
    "SELECT COUNT(*) AS product_count
     FROM products
     WHERE category_id = ?"
);

$stmt->bind_param("i", $categoryId);
$stmt->execute();

$result = $stmt->get_result();

$data = $result->fetch_assoc();

$productCount = (int) $data['product_count'];

$stmt->close();


/* =========================
   DON'T DELETE IF PRODUCTS EXIST
========================= */

if ($productCount > 0) {

    header(
        'Location: index.php?error=category_has_products'
    );

    exit;
}


/* =========================
   DELETE CATEGORY
========================= */

$stmt = $mysqli->prepare(
    "DELETE FROM categories
     WHERE id = ?"
);

$stmt->bind_param("i", $categoryId);

if ($stmt->execute()) {

    $stmt->close();


    /* =========================
       DELETE CATEGORY IMAGE
    ========================== */

    if (!empty($category['image'])) {

        $imagePath =
            __DIR__ .
            '/../../public/uploads/categories/' .
            $category['image'];

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }


    header('Location: index.php?success=deleted');

    exit;
}


$stmt->close();

header('Location: index.php?error=delete_failed');

exit;