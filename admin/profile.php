<?php

session_start();

$pageTitle = "Profile";

require_once '../config/database.php';

/*
|--------------------------------------------------------------------------
| Get Logged In Admin
|--------------------------------------------------------------------------
*/

$adminId = $_SESSION['user_id'] ?? null;

$admin = null;

if ($adminId) {

    $stmt = $mysqli->prepare("
        SELECT
            id,
            name,
            email,
            role,
            is_active,
            created_at,
            updated_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param("i", $adminId);

        $stmt->execute();

        $result = $stmt->get_result();

        $admin = $result->fetch_assoc();

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| Fallback
|--------------------------------------------------------------------------
|
| Agar session mein user_id available nahi hai to admin account
| fetch kar lenge.
|
*/

if (!$admin) {

    $result = $mysqli->query("
        SELECT
            id,
            name,
            email,
            role,
            is_active,
            created_at,
            updated_at
        FROM users
        WHERE role = 'admin'
        ORDER BY id ASC
        LIMIT 1
    ");

    if ($result) {
        $admin = $result->fetch_assoc();
    }
}


/*
|--------------------------------------------------------------------------
| Default Values
|--------------------------------------------------------------------------
*/

$adminName = $admin['name'] ?? 'Admin';

$adminEmail = $admin['email'] ?? 'admin@clothwear.com';

$adminRole = $admin['role'] ?? 'admin';

$adminStatus = (int) ($admin['is_active'] ?? 0);

$adminCreated = !empty($admin['created_at'])
    ? date('d M Y', strtotime($admin['created_at']))
    : 'N/A';


/*
|--------------------------------------------------------------------------
| Admin Initial
|--------------------------------------------------------------------------
*/

$adminInitial = strtoupper(substr(trim($adminName), 0, 1));


/*
|--------------------------------------------------------------------------
| Store Statistics
|--------------------------------------------------------------------------
*/

$totalProducts = 0;
$totalCategories = 0;
$totalOrders = 0;
$totalCustomers = 0;


/* Products */

$result = $mysqli->query("
    SELECT COUNT(*) AS total
    FROM products
");

if ($result) {

    $row = $result->fetch_assoc();

    $totalProducts = (int) ($row['total'] ?? 0);
}


/* Categories */

$result = $mysqli->query("
    SELECT COUNT(*) AS total
    FROM categories
");

if ($result) {

    $row = $result->fetch_assoc();

    $totalCategories = (int) ($row['total'] ?? 0);
}


/* Orders */

$result = $mysqli->query("
    SELECT COUNT(*) AS total
    FROM orders
");

if ($result) {

    $row = $result->fetch_assoc();

    $totalOrders = (int) ($row['total'] ?? 0);
}


/* Customers */

$result = $mysqli->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'
");

if ($result) {

    $row = $result->fetch_assoc();

    $totalCustomers = (int) ($row['total'] ?? 0);
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
        href="assets/img/apple-icon.png">

    <link rel="icon"
        type="image/png"
        href="assets/img/favicon.png">

    <title>
        Profile - ClothWear Admin
    </title>


    <!-- Fonts -->

    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900">


    <!-- Nucleo Icons -->

    <link href="assets/css/nucleo-icons.css"
        rel="stylesheet">

    <link href="assets/css/nucleo-svg.css"
        rel="stylesheet">


    <!-- Font Awesome -->

    <script src="https://kit.fontawesome.com/42d5adcbca.js"
        crossorigin="anonymous">
    </script>


    <!-- Material Icons -->

    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">


    <!-- Material Dashboard -->

    <link id="pagestyle"
        href="assets/css/material-dashboard.css?v=3.2.0"
        rel="stylesheet">


    <style>

        /*
        |--------------------------------------------------------------------------
        | Page
        |--------------------------------------------------------------------------
        */

        html,
        body {

            min-height: 100%;

            height: auto;

            overflow-x: hidden;

            overflow-y: auto;

        }


        .main-content {

            min-height: 100vh !important;

            height: auto !important;

            max-height: none !important;

        }


        /*
        |--------------------------------------------------------------------------
        | Profile Cover
        |--------------------------------------------------------------------------
        */

        .profile-cover {

            min-height: 260px;

            border-radius: 16px;

            position: relative;

            overflow: hidden;

            background:
                linear-gradient(
                    135deg,
                    #212529 0%,
                    #343a40 50%,
                    #495057 100%
                );

        }


        .profile-cover::before {

            content: "";

            position: absolute;

            width: 420px;

            height: 420px;

            border-radius: 50%;

            background: rgba(255, 255, 255, 0.05);

            top: -220px;

            right: -80px;

        }


        .profile-cover::after {

            content: "";

            position: absolute;

            width: 300px;

            height: 300px;

            border-radius: 50%;

            background: rgba(255, 255, 255, 0.04);

            bottom: -190px;

            left: 30%;

        }


        /*
        |--------------------------------------------------------------------------
        | Profile Card
        |--------------------------------------------------------------------------
        */

        .profile-main-card {

            margin-top: -70px;

            position: relative;

            z-index: 5;

            border-radius: 16px;

        }


        /*
        |--------------------------------------------------------------------------
        | Admin Avatar
        |--------------------------------------------------------------------------
        */

        .admin-avatar {

            width: 105px;

            height: 105px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background: linear-gradient(
                135deg,
                #344767,
                #1f2d3d
            );

            color: #ffffff;

            font-size: 38px;

            font-weight: 700;

            border: 5px solid #ffffff;

            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);

        }


        /*
        |--------------------------------------------------------------------------
        | Profile Info Icons
        |--------------------------------------------------------------------------
        */

        .profile-info-icon {

            width: 42px;

            height: 42px;

            min-width: 42px;

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #f1f3f5;

        }


        .profile-info-icon span {

            font-size: 21px;

            color: #344767;

        }


        /*
        |--------------------------------------------------------------------------
        | Statistics
        |--------------------------------------------------------------------------
        */

        .profile-stat {

            padding: 18px;

            border-radius: 14px;

            background: #f8f9fa;

            height: 100%;

        }


        .profile-stat-icon {

            width: 42px;

            height: 42px;

            border-radius: 11px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #ffffff;

            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);

        }


        .profile-stat-icon span {

            font-size: 21px;

            color: #344767;

        }


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        .status-dot {

            width: 8px;

            height: 8px;

            border-radius: 50%;

            display: inline-block;

            margin-right: 6px;

        }


        .status-active {

            background: #2dce89;

        }


        .status-inactive {

            background: #f5365c;

        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 767px) {

            .profile-cover {

                min-height: 200px;

            }


            .profile-main-card {

                margin-top: -50px;

            }


            .admin-avatar {

                width: 90px;

                height: 90px;

                font-size: 32px;

            }

        }

    </style>

</head>


<body class="g-sidenav-show bg-gray-100">


    <!-- Admin Header -->

    <?php require_once '../includes/admin-header.php'; ?>


    <!-- Admin Sidebar -->

    <?php require_once '../includes/admin-sidenavbar.php'; ?>


    <!-- Main Content -->

    <main class="main-content position-relative border-radius-lg">

        <div class="container-fluid py-4">


            <!-- =====================================================
                 PAGE HEADER
            ====================================================== -->

            <div class="row">

                <div class="col-12">

                    <div class="mb-4">

                        <h4 class="font-weight-bolder mb-1">
                            My Profile
                        </h4>

                        <p class="text-sm text-secondary mb-0">
                            Manage your ClothWear admin account and view store overview
                        </p>

                    </div>

                </div>

            </div>


            <!-- =====================================================
                 PROFILE COVER
            ====================================================== -->

            <div class="profile-cover mb-0">

                <div class="position-absolute top-0 start-0 p-4">

                    <span class="badge bg-white text-dark px-3 py-2">

                        <span class="material-symbols-rounded align-middle me-1"
                            style="font-size:16px;">
                            admin_panel_settings
                        </span>

                        Admin Account

                    </span>

                </div>

            </div>


            <!-- =====================================================
                 PROFILE MAIN CARD
            ====================================================== -->

            <div class="card profile-main-card mx-2 mx-md-3">

                <div class="card-body p-3 p-md-4">


                    <!-- Profile Header -->

                    <div class="row align-items-center">


                        <!-- Avatar -->

                        <div class="col-auto">

                            <div class="admin-avatar">

                                <?= htmlspecialchars($adminInitial) ?>

                            </div>

                        </div>


                        <!-- Name -->

                        <div class="col">

                            <h4 class="mb-1 font-weight-bolder">

                                <?= htmlspecialchars($adminName) ?>

                            </h4>

                            <p class="mb-2 text-sm text-secondary">

                                <?= htmlspecialchars($adminEmail) ?>

                            </p>

                            <div class="d-flex align-items-center gap-2 flex-wrap">

                                <span class="badge bg-gradient-dark">

                                    <?= ucfirst(htmlspecialchars($adminRole)) ?>

                                </span>


                                <?php if ($adminStatus === 1): ?>

                                    <span class="text-sm text-success">

                                        <span class="status-dot status-active"></span>

                                        Active

                                    </span>

                                <?php else: ?>

                                    <span class="text-sm text-danger">

                                        <span class="status-dot status-inactive"></span>

                                        Inactive

                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- Edit Button -->

                        <div class="col-12 col-md-auto mt-3 mt-md-0">

                            <a href="edit-profile.php"
                                class="btn bg-gradient-dark mb-0">

                                <span class="material-symbols-rounded align-middle me-1"
                                    style="font-size:18px;">
                                    edit
                                </span>

                                Edit Profile

                            </a>

                        </div>


                    </div>


                </div>

            </div>


            <!-- =====================================================
                 CONTENT
            ====================================================== -->

            <div class="row mt-4">


                <!-- =================================================
                     ACCOUNT INFORMATION
                ================================================== -->

                <div class="col-lg-5 mb-4">

                    <div class="card h-100">

                        <div class="card-header pb-0">

                            <h6 class="mb-1">
                                Account Information
                            </h6>

                            <p class="text-sm text-secondary mb-0">
                                Your administrator account details
                            </p>

                        </div>


                        <div class="card-body p-4">


                            <!-- Name -->

                            <div class="d-flex align-items-center mb-4">

                                <div class="profile-info-icon me-3">

                                    <span class="material-symbols-rounded">
                                        person
                                    </span>

                                </div>

                                <div>

                                    <p class="text-xs text-secondary mb-1">
                                        Full Name
                                    </p>

                                    <h6 class="mb-0">
                                        <?= htmlspecialchars($adminName) ?>
                                    </h6>

                                </div>

                            </div>


                            <!-- Email -->

                            <div class="d-flex align-items-center mb-4">

                                <div class="profile-info-icon me-3">

                                    <span class="material-symbols-rounded">
                                        mail
                                    </span>

                                </div>

                                <div>

                                    <p class="text-xs text-secondary mb-1">
                                        Email Address
                                    </p>

                                    <h6 class="mb-0 text-break">
                                        <?= htmlspecialchars($adminEmail) ?>
                                    </h6>

                                </div>

                            </div>


                            <!-- Role -->

                            <div class="d-flex align-items-center mb-4">

                                <div class="profile-info-icon me-3">

                                    <span class="material-symbols-rounded">
                                        admin_panel_settings
                                    </span>

                                </div>

                                <div>

                                    <p class="text-xs text-secondary mb-1">
                                        Account Role
                                    </p>

                                    <h6 class="mb-0">
                                        <?= ucfirst(htmlspecialchars($adminRole)) ?>
                                    </h6>

                                </div>

                            </div>


                            <!-- Status -->

                            <div class="d-flex align-items-center mb-4">

                                <div class="profile-info-icon me-3">

                                    <span class="material-symbols-rounded">
                                        verified_user
                                    </span>

                                </div>

                                <div>

                                    <p class="text-xs text-secondary mb-1">
                                        Account Status
                                    </p>

                                    <h6 class="mb-0">

                                        <?php if ($adminStatus === 1): ?>

                                            <span class="text-success">
                                                Active
                                            </span>

                                        <?php else: ?>

                                            <span class="text-danger">
                                                Inactive
                                            </span>

                                        <?php endif; ?>

                                    </h6>

                                </div>

                            </div>


                            <!-- Joined -->

                            <div class="d-flex align-items-center">

                                <div class="profile-info-icon me-3">

                                    <span class="material-symbols-rounded">
                                        calendar_month
                                    </span>

                                </div>

                                <div>

                                    <p class="text-xs text-secondary mb-1">
                                        Admin Since
                                    </p>

                                    <h6 class="mb-0">
                                        <?= htmlspecialchars($adminCreated) ?>
                                    </h6>

                                </div>

                            </div>


                        </div>

                    </div>

                </div>



                <!-- =================================================
                     STORE OVERVIEW
                ================================================== -->

                <div class="col-lg-7 mb-4">

                    <div class="card h-100">

                        <div class="card-header pb-0">

                            <h6 class="mb-1">
                                Store Overview
                            </h6>

                            <p class="text-sm text-secondary mb-0">
                                Quick overview of your ClothWear store
                            </p>

                        </div>


                        <div class="card-body p-4">

                            <div class="row">


                                <!-- Products -->

                                <div class="col-md-6 mb-3">

                                    <div class="profile-stat">

                                        <div class="d-flex justify-content-between align-items-center">

                                            <div>

                                                <p class="text-xs text-secondary mb-1">
                                                    Products
                                                </p>

                                                <h5 class="font-weight-bolder mb-0">
                                                    <?= number_format($totalProducts) ?>
                                                </h5>

                                            </div>


                                            <div class="profile-stat-icon">

                                                <span class="material-symbols-rounded">
                                                    inventory_2
                                                </span>

                                            </div>

                                        </div>

                                    </div>

                                </div>



                                <!-- Categories -->

                                <div class="col-md-6 mb-3">

                                    <div class="profile-stat">

                                        <div class="d-flex justify-content-between align-items-center">

                                            <div>

                                                <p class="text-xs text-secondary mb-1">
                                                    Categories
                                                </p>

                                                <h5 class="font-weight-bolder mb-0">
                                                    <?= number_format($totalCategories) ?>
                                                </h5>

                                            </div>


                                            <div class="profile-stat-icon">

                                                <span class="material-symbols-rounded">
                                                    category
                                                </span>

                                            </div>

                                        </div>

                                    </div>

                                </div>



                                <!-- Orders -->

                                <div class="col-md-6 mb-3">

                                    <div class="profile-stat">

                                        <div class="d-flex justify-content-between align-items-center">

                                            <div>

                                                <p class="text-xs text-secondary mb-1">
                                                    Total Orders
                                                </p>

                                                <h5 class="font-weight-bolder mb-0">
                                                    <?= number_format($totalOrders) ?>
                                                </h5>

                                            </div>


                                            <div class="profile-stat-icon">

                                                <span class="material-symbols-rounded">
                                                    shopping_cart
                                                </span>

                                            </div>

                                        </div>

                                    </div>

                                </div>



                                <!-- Customers -->

                                <div class="col-md-6 mb-3">

                                    <div class="profile-stat">

                                        <div class="d-flex justify-content-between align-items-center">

                                            <div>

                                                <p class="text-xs text-secondary mb-1">
                                                    Customers
                                                </p>

                                                <h5 class="font-weight-bolder mb-0">
                                                    <?= number_format($totalCustomers) ?>
                                                </h5>

                                            </div>


                                            <div class="profile-stat-icon">

                                                <span class="material-symbols-rounded">
                                                    group
                                                </span>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                            </div>


                            <!-- Security Note -->

                            <div class="mt-2 p-3 rounded-3"
                                style="background:#f8f9fa;">

                                <div class="d-flex align-items-start">

                                    <span class="material-symbols-rounded me-3 text-dark">
                                        security
                                    </span>

                                    <div>

                                        <h6 class="mb-1">
                                            Administrator Access
                                        </h6>

                                        <p class="text-sm text-secondary mb-0">
                                            Your account has administrator privileges
                                            for managing products, categories, orders
                                            and customers.
                                        </p>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>



            <!-- =====================================================
                 ACCOUNT DETAILS
            ====================================================== -->

            <div class="row">

                <div class="col-12 mb-4">

                    <div class="card">

                        <div class="card-header pb-0">

                            <h6 class="mb-1">
                                Account Details
                            </h6>

                            <p class="text-sm text-secondary mb-0">
                                Important information about your administrator account
                            </p>

                        </div>


                        <div class="card-body p-4">

                            <div class="row">


                                <!-- User ID -->

                                <div class="col-md-4 mb-3 mb-md-0">

                                    <p class="text-xs text-secondary mb-1">
                                        User ID
                                    </p>

                                    <p class="text-sm font-weight-bold mb-0">
                                        #<?= htmlspecialchars($admin['id'] ?? 'N/A') ?>
                                    </p>

                                </div>


                                <!-- Account Created -->

                                <div class="col-md-4 mb-3 mb-md-0">

                                    <p class="text-xs text-secondary mb-1">
                                        Account Created
                                    </p>

                                    <p class="text-sm font-weight-bold mb-0">
                                        <?= htmlspecialchars($adminCreated) ?>
                                    </p>

                                </div>


                                <!-- Account Type -->

                                <div class="col-md-4">

                                    <p class="text-xs text-secondary mb-1">
                                        Account Type
                                    </p>

                                    <p class="text-sm font-weight-bold mb-0">
                                        Administrator
                                    </p>

                                </div>


                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Admin Footer -->

            <?php require_once '../includes/admin-footer.php'; ?>


        </div>

    </main>



    <!-- Scripts -->

    <script src="assets/js/core/popper.min.js"></script>

    <script src="assets/js/core/bootstrap.min.js"></script>

    <script src="assets/js/plugins/perfect-scrollbar.min.js"></script>

    <script src="assets/js/plugins/smooth-scrollbar.min.js"></script>

    <script src="assets/js/material-dashboard.min.js?v=3.2.0"></script>


</body>

</html>