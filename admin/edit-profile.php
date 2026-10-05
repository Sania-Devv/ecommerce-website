<?php

require_once __DIR__ . '/../core/Sessions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';

$auth = new Auth($mysqli);

/*
|--------------------------------------------------------------------------
| Admin Access
|--------------------------------------------------------------------------
*/

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: /public/login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Logged In Admin
|--------------------------------------------------------------------------
*/

$userId = $_SESSION['user_id'];

$stmt = $mysqli->prepare("
    SELECT id, name, email, password, role, is_active
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$admin = $result->fetch_assoc();

$stmt->close();

if (!$admin) {
    header('Location: /admin/profile.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Form Values
|--------------------------------------------------------------------------
*/

$name = $admin['name'];
$email = $admin['email'];

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Update Profile
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($name === '' || $email === '') {

        $error = "Name and email are required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Check Email
        |--------------------------------------------------------------------------
        |
        | Make sure another user is not already using this email.
        |
        */

        $stmt = $mysqli->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            AND id != ?
            LIMIT 1
        ");

        $stmt->bind_param("si", $email, $userId);
        $stmt->execute();

        $emailResult = $stmt->get_result();

        if ($emailResult->num_rows > 0) {

            $error = "This email address is already in use.";

        }

        $stmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Password Validation
    |--------------------------------------------------------------------------
    */

    if ($error === '' && $newPassword !== '') {

        if ($currentPassword === '') {

            $error = "Please enter your current password.";

        } elseif (!password_verify($currentPassword, $admin['password'])) {

            $error = "Current password is incorrect.";

        } elseif (strlen($newPassword) < 6) {

            $error = "New password must be at least 6 characters.";

        } elseif ($newPassword !== $confirmPassword) {

            $error = "New password and confirm password do not match.";

        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Database
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        if ($newPassword !== '') {

            $hashedPassword = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $stmt = $mysqli->prepare("
                UPDATE users
                SET name = ?,
                    email = ?,
                    password = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->bind_param(
                "sssi",
                $name,
                $email,
                $hashedPassword,
                $userId
            );

        } else {

            $stmt = $mysqli->prepare("
                UPDATE users
                SET name = ?,
                    email = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->bind_param(
                "ssi",
                $name,
                $email,
                $userId
            );
        }

        if ($stmt->execute()) {

            /*
            |--------------------------------------------------------------------------
            | Update Session
            |--------------------------------------------------------------------------
            */

            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;

            $success = "Profile updated successfully.";

            /*
            |--------------------------------------------------------------------------
            | Refresh Admin Data
            |--------------------------------------------------------------------------
            */

            $admin['name'] = $name;
            $admin['email'] = $email;

            /*
            |--------------------------------------------------------------------------
            | Clear Password Fields
            |--------------------------------------------------------------------------
            */

            $currentPassword = '';
            $newPassword = '';
            $confirmPassword = '';

        } else {

            $error = "Something went wrong while updating your profile.";
        }

        $stmt->close();
    }
}

$pageTitle = "Edit Profile";

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
    Edit Profile - ClothWear Admin
</title>

<!-- Fonts -->
<link
    rel="stylesheet"
    href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900">

<!-- Nucleo Icons -->
<link
    href="assets/css/nucleo-icons.css"
    rel="stylesheet">

<link
    href="assets/css/nucleo-svg.css"
    rel="stylesheet">

<!-- Font Awesome -->
<script
    src="https://kit.fontawesome.com/42d5adcbca.js"
    crossorigin="anonymous">
</script>

<!-- Material Symbols -->
<link
    rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">

<!-- Material Dashboard -->
<link
    id="pagestyle"
    href="assets/css/material-dashboard.css?v=3.2.0"
    rel="stylesheet">

<style>

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
    | Page Header
    |--------------------------------------------------------------------------
    */

    .edit-profile-header {
        min-height: 190px;
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

    .edit-profile-header::before {
        content: "";
        position: absolute;
        width: 350px;
        height: 350px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.05);
        top: -200px;
        right: -50px;
    }

    .edit-profile-header::after {
        content: "";
        position: absolute;
        width: 250px;
        height: 250px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.04);
        bottom: -170px;
        left: 30%;
    }

    /*
    |--------------------------------------------------------------------------
    | Profile Icon
    |--------------------------------------------------------------------------
    */

    .edit-profile-avatar {
        width: 82px;
        height: 82px;
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
        font-size: 30px;
        font-weight: 700;
        border: 4px solid #ffffff;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
    }

    /*
    |--------------------------------------------------------------------------
    | Form Icons
    |--------------------------------------------------------------------------
    */

    .form-icon-box {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 11px;
        background: #f1f3f5;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .form-icon-box span {
        font-size: 21px;
        color: #344767;
    }

    /*
    |--------------------------------------------------------------------------
    | Password Section
    |--------------------------------------------------------------------------
    */

    .password-section {
        border: 1px solid #e9ecef;
        border-radius: 14px;
        padding: 20px;
        background: #fafafa;
    }

    .password-title-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .password-title-icon span {
        font-size: 20px;
        color: #344767;
    }

    /*
    |--------------------------------------------------------------------------
    | Info Card
    |--------------------------------------------------------------------------
    */

    .security-info {
        background: #f8f9fa;
        border-radius: 14px;
        padding: 18px;
    }

    /*
    |--------------------------------------------------------------------------
    | Mobile
    |--------------------------------------------------------------------------
    */

    @media (max-width: 767px) {

        .edit-profile-header {
            min-height: 160px;
        }

        .edit-profile-avatar {
            width: 70px;
            height: 70px;
            font-size: 26px;
        }

    }

</style>


</head>

<body class="g-sidenav-show bg-gray-100">


<?php require_once '../includes/admin-header.php'; ?>

<?php require_once '../includes/admin-sidenavbar.php'; ?>

<main class="main-content position-relative border-radius-lg">

    <div class="container-fluid py-4">

        <!-- Page Heading -->
        <div class="row">

            <div class="col-12">

                <div class="mb-4">

                    <h4 class="font-weight-bolder mb-1">
                        Edit Profile
                    </h4>

                    <p class="text-sm text-secondary mb-0">
                        Update your ClothWear administrator account
                    </p>

                </div>

            </div>

        </div>

        <!-- Profile Header -->
        <div class="edit-profile-header mb-4">

            <div class="position-absolute top-0 start-0 p-4">

                <span class="badge bg-white text-dark px-3 py-2">

                    <span
                        class="material-symbols-rounded align-middle me-1"
                        style="font-size:16px;">
                        manage_accounts
                    </span>

                    Account Settings

                </span>

            </div>

        </div>

        <!-- Profile Card -->
        <div class="row">

            <div class="col-lg-8 mb-4">

                <div class="card h-100">

                    <div class="card-header pb-0">

                        <div class="d-flex align-items-center">

                            <div class="edit-profile-avatar me-3">

                                <?= htmlspecialchars(
                                    strtoupper(
                                        substr(
                                            trim($name),
                                            0,
                                            1
                                        )
                                    )
                                ) ?>

                            </div>

                            <div>

                                <h5 class="mb-1">
                                    <?= htmlspecialchars($name) ?>
                                </h5>

                                <p class="text-sm text-secondary mb-0">
                                    Administrator Account
                                </p>

                            </div>

                        </div>

                    </div>

                    <div class="card-body p-4">

                        <?php if ($success): ?>

                            <div
                                class="alert alert-success d-flex align-items-center">

                                <span
                                    class="material-symbols-rounded me-2">
                                    check_circle
                                </span>

                                <?= htmlspecialchars($success) ?>

                            </div>

                        <?php endif; ?>

                        <?php if ($error): ?>

                            <div
                                class="alert alert-danger d-flex align-items-center">

                                <span
                                    class="material-symbols-rounded me-2">
                                    error
                                </span>

                                <?= htmlspecialchars($error) ?>

                            </div>

                        <?php endif; ?>

                        <form method="POST">

                            <!-- Personal Information -->

                            <h6 class="font-weight-bolder mb-3">
                                Personal Information
                            </h6>

                            <!-- Name -->

                            <div class="d-flex align-items-start mb-4">

                                <div class="form-icon-box me-3">

                                    <span class="material-symbols-rounded">
                                        person
                                    </span>

                                </div>

                                <div class="flex-grow-1">

                                    <label class="form-label text-sm">
                                        Full Name
                                    </label>

                                    <input
                                        type="text"
                                        name="name"
                                        class="form-control"
                                        value="<?= htmlspecialchars($name) ?>"
                                        required>

                                </div>

                            </div>

                            <!-- Email -->

                            <div class="d-flex align-items-start mb-4">

                                <div class="form-icon-box me-3">

                                    <span class="material-symbols-rounded">
                                        mail
                                    </span>

                                </div>

                                <div class="flex-grow-1">

                                    <label class="form-label text-sm">
                                        Email Address
                                    </label>

                                    <input
                                        type="email"
                                        name="email"
                                        class="form-control"
                                        value="<?= htmlspecialchars($email) ?>"
                                        required>

                                    <small class="text-secondary">
                                        This email will be used for login.
                                    </small>

                                </div>

                            </div>

                            <hr class="horizontal dark my-4">

                            <!-- Password -->

                            <div class="password-section">

                                <div class="d-flex align-items-center mb-3">

                                    <div class="password-title-icon me-3">

                                        <span
                                            class="material-symbols-rounded">
                                            lock
                                        </span>

                                    </div>

                                    <div>

                                        <h6 class="mb-1">
                                            Change Password
                                        </h6>

                                        <p class="text-xs text-secondary mb-0">
                                            Leave these fields empty if you
                                            don't want to change your password.
                                        </p>

                                    </div>

                                </div>

                                <!-- Current Password -->

                                <div class="mb-3">

                                    <label class="form-label text-sm">
                                        Current Password
                                    </label>

                                    <input
                                        type="password"
                                        name="current_password"
                                        class="form-control"
                                        placeholder="Enter current password">

                                </div>

                                <!-- New Password -->

                                <div class="mb-3">

                                    <label class="form-label text-sm">
                                        New Password
                                    </label>

                                    <input
                                        type="password"
                                        name="new_password"
                                        class="form-control"
                                        placeholder="Enter new password">

                                </div>

                                <!-- Confirm Password -->

                                <div>

                                    <label class="form-label text-sm">
                                        Confirm New Password
                                    </label>

                                    <input
                                        type="password"
                                        name="confirm_password"
                                        class="form-control"
                                        placeholder="Confirm new password">

                                </div>

                            </div>

                            <!-- Buttons -->

                            <div class="d-flex justify-content-end gap-2 mt-4">

                                <a
                                    href="profile.php"
                                    class="btn btn-outline-secondary mb-0">

                                    Cancel

                                </a>

                                <button
                                    type="submit"
                                    class="btn bg-gradient-dark mb-0">

                                    <span
                                        class="material-symbols-rounded align-middle me-1"
                                        style="font-size:18px;">
                                        save
                                    </span>

                                    Save Changes

                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

            <!-- Right Side -->

            <div class="col-lg-4 mb-4">

                <div class="card mb-4">

                    <div class="card-header pb-0">

                        <h6 class="mb-1">
                            Account Summary
                        </h6>

                        <p class="text-sm text-secondary mb-0">
                            Current account information
                        </p>

                    </div>

                    <div class="card-body p-4">

                        <div class="mb-4">

                            <p class="text-xs text-secondary mb-1">
                                Account Role
                            </p>

                            <div class="d-flex align-items-center">

                                <span
                                    class="material-symbols-rounded me-2"
                                    style="font-size:20px;">
                                    admin_panel_settings
                                </span>

                                <span class="text-sm font-weight-bold">
                                    Administrator
                                </span>

                            </div>

                        </div>

                        <div class="mb-4">

                            <p class="text-xs text-secondary mb-1">
                                Account Status
                            </p>

                            <div class="d-flex align-items-center">

                                <span
                                    class="material-symbols-rounded text-success me-2"
                                    style="font-size:20px;">
                                    check_circle
                                </span>

                                <span class="text-sm font-weight-bold text-success">
                                    Active
                                </span>

                            </div>

                        </div>

                        <div>

                            <p class="text-xs text-secondary mb-1">
                                Login Email
                            </p>

                            <div class="d-flex align-items-start">

                                <span
                                    class="material-symbols-rounded me-2"
                                    style="font-size:20px;">
                                    alternate_email
                                </span>

                                <span class="text-sm text-break">
                                    <?= htmlspecialchars($email) ?>
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="card">

                    <div class="card-body p-4">

                        <div class="security-info">

                            <div class="d-flex align-items-start">

                                <span
                                    class="material-symbols-rounded me-3">
                                    security
                                </span>

                                <div>

                                    <h6 class="mb-1">
                                        Security Tip
                                    </h6>

                                    <p class="text-sm text-secondary mb-0">
                                        Use a strong password and never
                                        share your administrator login
                                        credentials with anyone.
                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <?php require_once '../includes/admin-footer.php'; ?>

    </div>

</main>

<!-- Core JS -->

<script src="assets/js/core/popper.min.js"></script>

<script src="assets/js/core/bootstrap.min.js"></script>

<script src="assets/js/plugins/perfect-scrollbar.min.js"></script>

<script src="assets/js/plugins/smooth-scrollbar.min.js"></script>

<script src="assets/js/material-dashboard.min.js?v=3.2.0"></script>


</body>

</html>
