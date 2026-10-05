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
   GET SLIDER
========================= */

$stmt = $mysqli->prepare(
    "SELECT id, image
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
   DELETE SLIDER
========================= */

$stmt = $mysqli->prepare(
    "DELETE FROM sliders
     WHERE id = ?"
);

$stmt->bind_param("i", $sliderId);

if ($stmt->execute()) {

    $stmt->close();


    /* =========================
       DELETE SLIDER IMAGE
    ========================== */

    if (!empty($slider['image'])) {

        $imagePath =
            __DIR__ .
            '/../../public/uploads/sliders/' .
            $slider['image'];

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