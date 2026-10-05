<?php

require_once __DIR__ . '/../core/Sessions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';

$auth = new Auth($mysqli);

$error = '';
$success = '';

$emailError = '';
$passwordError = '';

$nameError = '';
$registerEmailError = '';
$registerPasswordError = '';
$policyError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // =========================
    // LOGIN
    // =========================
    if (isset($_POST['login'])) {

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Email validation
        if ($email === '') {

            $emailError = "Email is required.";

        } elseif (
            !preg_match(
                '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/',
                $email
            )
        ) {

            $emailError = "Please enter a valid email address.";
        }

        // Password validation
        if ($password === '') {

            $passwordError = "Password is required.";

        } elseif (strlen($password) < 6) {

            $passwordError = "Password must be at least 6 characters.";
        }

        // Login only if validation passes
        if ($emailError === '' && $passwordError === '') {

            $result = $auth->login($email, $password);

            if ($result === true) {

                // Admin user
                if ($auth->isAdmin()) {

                    header('Location: ../admin/index.php');
                    exit;
                }

                // Customer user
                if ($auth->isCustomer()) {

                    header('Location: index.php');
                    exit;
                }

                $error = "Invalid user role.";
            } else {

                $error = $result;
            }
        }
    }


    // =========================
    // REGISTER
    // =========================
    if (isset($_POST['register'])) {

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['register_email'] ?? '');
        $password = $_POST['register_password'] ?? '';
        $policy = isset($_POST['policy']);


        // Name validation
        if ($name === '') {

            $nameError = "Name is required.";

        } elseif (strlen($name) < 2) {

            $nameError = "Name must be at least 2 characters.";

        } elseif (!preg_match('/^[A-Za-z\s]+$/', $name)) {

            $nameError = "Name can contain letters and spaces only.";
        }


        // Register email validation
        if ($email === '') {

            $registerEmailError = "Email is required.";

        } elseif (
            !preg_match(
                '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/',
                $email
            )
        ) {

            $registerEmailError = "Please enter a valid email address.";
        }


        // Register password validation
        if ($password === '') {

            $registerPasswordError = "Password is required.";

        } elseif (strlen($password) < 6) {

            $registerPasswordError = "Password must be at least 6 characters.";
        }


        // Privacy policy validation
        if (!$policy) {

            $policyError = "You must agree to the privacy policy.";
        }


        // Register only if validation passes
        if (
            $nameError === '' &&
            $registerEmailError === '' &&
            $registerPasswordError === '' &&
            $policyError === ''
        ) {

            $result = $auth->register(
                $name,
                $email,
                $password
            );

            if ($result === true) {

                $success = "Registration successful. Please login.";

            } else {

                $error = $result;
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, shrink-to-fit=no"
    >

    <title>Molla - Bootstrap eCommerce Template</title>

    <meta
        name="keywords"
        content="HTML5 Template"
    >

    <meta
        name="description"
        content="Molla - Bootstrap eCommerce Template"
    >

    <meta
        name="author"
        content="p-themes"
    >

    <!-- Favicon -->
    <link
        rel="apple-touch-icon"
        sizes="180x180"
        href="assets/images/icons/apple-touch-icon.png"
    >

    <link
        rel="icon"
        type="image/png"
        sizes="32x32"
        href="assets/images/icons/favicon-32x32.png"
    >

    <link
        rel="icon"
        type="image/png"
        sizes="16x16"
        href="assets/images/icons/favicon-16x16.png"
    >

    <link
        rel="manifest"
        href="assets/images/icons/site.html"
    >

    <link
        rel="mask-icon"
        href="assets/images/icons/safari-pinned-tab.svg"
        color="#666666"
    >

    <link
        rel="shortcut icon"
        href="assets/images/icons/favicon.ico"
    >

    <meta
        name="apple-mobile-web-app-title"
        content="Molla"
    >

    <meta
        name="application-name"
        content="Molla"
    >

    <meta
        name="msapplication-TileColor"
        content="#cc9966"
    >

    <meta
        name="msapplication-config"
        content="assets/images/icons/browserconfig.xml"
    >

    <meta
        name="theme-color"
        content="#ffffff"
    >

    <!-- Plugins CSS File -->
    <link
        rel="stylesheet"
        href="assets/css/bootstrap.min.css"
    >

    <!-- Main CSS File -->
    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <style>

        .field-error {
            display: block;
            margin-top: 5px;
            font-size: 13px;
        }

        .has-error .form-control {
            border-color: #dc3545 !important;
        }

        .toast-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            min-width: 280px;
            max-width: 380px;
            padding: 15px 20px;
            border-radius: 4px;
            color: #fff;
            font-size: 14px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .toast-error {
            background: #dc3545;
        }

        .toast-success {
            background: #28a745;
        }

        @media (max-width: 575px) {

            .toast-message {
                left: 15px;
                right: 15px;
                min-width: auto;
                max-width: none;
            }

        }

    </style>

</head>

<body>

<div class="page-wrapper">

    <?php include '../includes/header.php'; ?>

    <main class="main">

        <!-- Breadcrumb -->
        <nav
            aria-label="breadcrumb"
            class="breadcrumb-nav border-0 mb-0"
        >

            <div class="container">

                <ol class="breadcrumb">

                    <li class="breadcrumb-item">
                        <a href="index.php">Home</a>
                    </li>

                    <li
                        class="breadcrumb-item active"
                        aria-current="page"
                    >
                        Login
                    </li>

                </ol>

            </div>

        </nav>


        <!-- Login Page -->
        <div
            class="login-page bg-image pt-8 pb-8 pt-md-12 pb-md-12 pt-lg-17 pb-lg-17"
            style="background-image: url('assets/images/backgrounds/login-bg.jpg')"
        >

            <div class="container">

                <div class="form-box">

                    <div class="form-tab">

                        <!-- Tabs -->
                        <ul
                            class="nav nav-pills nav-fill"
                            role="tablist"
                        >

                            <li class="nav-item">

                                <a
                                    class="nav-link active"
                                    id="signin-tab-2"
                                    data-toggle="tab"
                                    href="#signin-2"
                                    role="tab"
                                    aria-controls="signin-2"
                                    aria-selected="true"
                                >
                                    Sign In
                                </a>

                            </li>

                            <li class="nav-item">

                                <a
                                    class="nav-link"
                                    id="register-tab-2"
                                    data-toggle="tab"
                                    href="#register-2"
                                    role="tab"
                                    aria-controls="register-2"
                                    aria-selected="false"
                                >
                                    Register
                                </a>

                            </li>

                        </ul>


                        <div class="tab-content">

                            <!-- =========================
                                 SIGN IN
                            ========================== -->

                            <div
                                class="tab-pane fade show active"
                                id="signin-2"
                                role="tabpanel"
                                aria-labelledby="signin-tab-2"
                            >

                                <form
                                    action="login.php"
                                    method="POST"
                                    novalidate
                                >

                                    <div class="form-group">

                                        <label for="singin-email-2">
                                            Username or email address *
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control"
                                            id="singin-email-2"
                                            name="email"
                                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                        >

                                        <?php if ($emailError): ?>

                                            <small class="text-danger field-error">
                                                <?= htmlspecialchars($emailError) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>


                                    <div class="form-group">

                                        <label for="singin-password-2">
                                            Password *
                                        </label>

                                        <input
                                            type="password"
                                            class="form-control"
                                            id="singin-password-2"
                                            name="password"
                                        >

                                        <?php if ($passwordError): ?>

                                            <small class="text-danger field-error">
                                                <?= htmlspecialchars($passwordError) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>


                                    <div class="form-footer">

                                        <button
                                            type="submit"
                                            name="login"
                                            class="btn btn-outline-primary-2"
                                        >

                                            <span>LOG IN</span>

                                            <i class="icon-long-arrow-right"></i>

                                        </button>


                                        <div class="custom-control custom-checkbox">

                                            <input
                                                type="checkbox"
                                                class="custom-control-input"
                                                id="signin-remember-2"
                                            >

                                            <label
                                                class="custom-control-label"
                                                for="signin-remember-2"
                                            >
                                                Remember Me
                                            </label>

                                        </div>


                                        <a
                                            href="#"
                                            class="forgot-link"
                                        >
                                            Forgot Your Password?
                                        </a>

                                    </div>

                                </form>


                                <div class="form-choice">

                                    <p class="text-center">
                                        or sign in with
                                    </p>

                                    <div class="row">

                                        <div class="col-sm-6">

                                            <a
                                                href="#"
                                                class="btn btn-login btn-g"
                                            >

                                                <i class="icon-google"></i>

                                                Login With Google

                                            </a>

                                        </div>


                                        <div class="col-sm-6">

                                            <a
                                                href="#"
                                                class="btn btn-login btn-f"
                                            >

                                                <i class="icon-facebook-f"></i>

                                                Login With Facebook

                                            </a>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <!-- =========================
                                 REGISTER
                            ========================== -->

                            <div
                                class="tab-pane fade"
                                id="register-2"
                                role="tabpanel"
                                aria-labelledby="register-tab-2"
                            >

                                <form
                                    action="login.php"
                                    method="POST"
                                    novalidate
                                >

                                    <div class="form-group">

                                        <label for="register-name-2">
                                            Your name *
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control"
                                            id="register-name-2"
                                            name="name"
                                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                        >

                                        <?php if ($nameError): ?>

                                            <small class="text-danger field-error">
                                                <?= htmlspecialchars($nameError) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>


                                    <div class="form-group">

                                        <label for="register-email-2">
                                            Your email address *
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control"
                                            id="register-email-2"
                                            name="register_email"
                                            value="<?= htmlspecialchars($_POST['register_email'] ?? '') ?>"
                                        >

                                        <?php if ($registerEmailError): ?>

                                            <small class="text-danger field-error">
                                                <?= htmlspecialchars($registerEmailError) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>


                                    <div class="form-group">

                                        <label for="register-password-2">
                                            Password *
                                        </label>

                                        <input
                                            type="password"
                                            class="form-control"
                                            id="register-password-2"
                                            name="register_password"
                                        >

                                        <?php if ($registerPasswordError): ?>

                                            <small class="text-danger field-error">
                                                <?= htmlspecialchars($registerPasswordError) ?>
                                            </small>

                                        <?php endif; ?>

                                    </div>


                                    <div class="form-footer">

                                        <button
                                            type="submit"
                                            name="register"
                                            class="btn btn-outline-primary-2"
                                        >

                                            <span>SIGN UP</span>

                                            <i class="icon-long-arrow-right"></i>

                                        </button>


                                        <div class="custom-control custom-checkbox">

                                            <input
                                                type="checkbox"
                                                class="custom-control-input"
                                                id="register-policy-2"
                                                name="policy"
                                                <?= isset($_POST['policy']) ? 'checked' : '' ?>
                                            >

                                            <label
                                                class="custom-control-label"
                                                for="register-policy-2"
                                            >

                                                I agree to the

                                                <a href="#">
                                                    privacy policy
                                                </a>

                                                *

                                            </label>

                                        </div>

                                    </div>

                                    <?php if ($policyError): ?>

                                        <small class="text-danger field-error">
                                            <?= htmlspecialchars($policyError) ?>
                                        </small>

                                    <?php endif; ?>

                                </form>


                                <div class="form-choice">

                                    <p class="text-center">
                                        or sign in with
                                    </p>

                                    <div class="row">

                                        <div class="col-sm-6">

                                            <a
                                                href="#"
                                                class="btn btn-login btn-g"
                                            >

                                                <i class="icon-google"></i>

                                                Login With Google

                                            </a>

                                        </div>


                                        <div class="col-sm-6">

                                            <a
                                                href="#"
                                                class="btn btn-login btn-f"
                                            >

                                                <i class="icon-facebook-f"></i>

                                                Login With Facebook

                                            </a>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>


    <?php include '../includes/footer.php'; ?>

</div>


<!-- Toast -->
<?php if ($error || $success): ?>

    <div
        id="toastMessage"
        class="toast-message <?= $error ? 'toast-error' : 'toast-success' ?>"
    >

        <?= htmlspecialchars($error ?: $success) ?>

    </div>

<?php endif; ?>


<!-- Scroll Top -->
<button
    id="scroll-top"
    title="Back to Top"
>
    <i class="icon-arrow-up"></i>
</button>


<!-- Plugins JS File -->
<script src="assets/js/jquery.min.js"></script>

<script src="assets/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/jquery.hoverIntent.min.js"></script>

<script src="assets/js/jquery.waypoints.min.js"></script>

<script src="assets/js/superfish.min.js"></script>

<script src="assets/js/owl.carousel.min.js"></script>

<!-- Main JS File -->
<script src="assets/js/main.js"></script>


<script>

    // Auto hide toast after 4 seconds
    setTimeout(function () {

        const toast = document.getElementById('toastMessage');

        if (toast) {

            toast.style.transition = 'opacity 0.4s ease';
            toast.style.opacity = '0';

            setTimeout(function () {

                toast.remove();

            }, 400);
        }

    }, 4000);

</script>

</body>

</html>