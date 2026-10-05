<?php

require_once __DIR__ . '/../core/Sessions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';

$auth = new Auth($mysqli);

$error = '';
$emailError = '';
$passwordError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
if ($email === '') {

    $emailError = "Email is required.";

} elseif (!preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $email)) {

    $emailError = "Please enter a valid email address.";

}

if ($password === '') {

    $passwordError = "Password is required.";

} elseif (strlen($password) < 8) {

    $passwordError = "Password must be at least 8 characters.";

}
if ($emailError === '' && $passwordError === '') {

    $result = $auth->login($email, $password);
        if ($result === true) {

            // Only admin can access admin dashboard
            if ($auth->isAdmin()) {

                header('Location: /admin/index.php');
                exit;

            } else {

                // Customer is not allowed to login through admin login
                $auth->logout();

                $error = "Access denied. Only admin users can access the admin dashboard.";
            }

        } else {

            $error = $result;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8" />

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, shrink-to-fit=no"
    >

    <link
        rel="apple-touch-icon"
        sizes="76x76"
        href="assets/img/apple-icon.png"
    >

    <link
        rel="icon"
        type="image/png"
        href="assets/img/favicon.png"
    >

    <title>
        Admin Sign In
    </title>

    <!-- Fonts -->
    <link
        rel="stylesheet"
        type="text/css"
        href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900"
    />

    <!-- Nucleo Icons -->
    <link
        href="assets/css/nucleo-icons.css"
        rel="stylesheet"
    />

    <link
        href="assets/css/nucleo-svg.css"
        rel="stylesheet"
    />

    <!-- Font Awesome -->
    <script
        src="https://kit.fontawesome.com/42d5adcbca.js"
        crossorigin="anonymous"
    ></script>

    <!-- Material Icons -->
    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0"
    />

    <!-- Material Dashboard CSS -->
    <link
        id="pagestyle"
        href="assets/css/material-dashboard.css?v=3.2.0"
        rel="stylesheet"
    />

    <!-- Field error styling fix -->
    <style>

        /*
            Error message ab input-group ke BAHAR hai, isliye
            field ki width/floating-label dobara normal kaam
            karegi. Ye sirf spacing + red-border look ke liye.
        */

        .field-error {
            display: block;
            margin-top: -8px;
            margin-bottom: 14px;
            font-size: 12px;
        }

        .input-group-outline.has-error .form-label {
            color: #dc3545 !important;
        }

        /* Jo text user type kare wo hamesha black/normal rahe,
           sirf label aur border red hon error ke waqt */
        .input-group-outline.has-error .form-control {
            color: #212529 !important;
        }

        .input-group-outline.has-error::before,
        .input-group-outline.has-error::after {
            border-color: #dc3545 !important;
        }

        /*
            Browser autofill (jab password manager field fill
            karta hai) apna khud ka blue/lavender background
            force kar deta hai. Ye trick us background ko
            field ke normal white color se override kar deti
            hai aur text ka color bhi sahi (black) rakhti hai.
        */

        .input-group-outline input:-webkit-autofill,
        .input-group-outline input:-webkit-autofill:hover,
        .input-group-outline input:-webkit-autofill:focus,
        .input-group-outline input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
            box-shadow: 0 0 0 1000px #ffffff inset !important;
            -webkit-text-fill-color: #212529 !important;
            caret-color: #212529 !important;
            transition: background-color 5000s ease-in-out 0s;
        }

    </style>

</head>

<body class="bg-gray-200">

    <div class="container position-sticky z-index-sticky top-0">

        <div class="row">

            <div class="col-12">

                <!-- Navbar -->
                <nav class="navbar navbar-expand-lg blur border-radius-xl top-0 z-index-3 shadow position-absolute my-3 py-2 start-0 end-0 mx-4">

                    <div class="container-fluid ps-2 pe-0">

                        <button
                            class="navbar-toggler shadow-none ms-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#navigation"
                            aria-controls="navigation"
                            aria-expanded="false"
                            aria-label="Toggle navigation"
                        >

                            <span class="navbar-toggler-icon mt-2">

                                <span class="navbar-toggler-bar bar1"></span>
                                <span class="navbar-toggler-bar bar2"></span>
                                <span class="navbar-toggler-bar bar3"></span>

                            </span>

                        </button>


                        <div
                            class="collapse navbar-collapse"
                            id="navigation"
                        >

                            <ul class="navbar-nav mx-auto">

                                <li class="nav-item">

                                    <a
                                        class="nav-link d-flex align-items-center me-2"
                                        href="/public/index.php"
                                    >

                                        <i class="fa fa-home opacity-6 text-dark me-1"></i>

                                        Storefront

                                    </a>

                                </li>

                                <li class="nav-item">

                                    <a
                                        class="nav-link me-2"
                                        href="/admin/login.php"
                                    >

                                        <i class="fas fa-key opacity-6 text-dark me-1"></i>

                                        Admin Sign In

                                    </a>

                                </li>

                            </ul>

                        </div>

                    </div>

                </nav>
                <!-- End Navbar -->

            </div>

        </div>

    </div>


    <main class="main-content mt-0">

        <div
            class="page-header align-items-start min-vh-100"
            style="background-image: url('https://images.unsplash.com/photo-1497294815431-9365093b7331?ixlib=rb-1.2.1&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1950&q=80');"
        >

            <span class="mask bg-gradient-dark opacity-6"></span>


            <div class="container my-auto">

                <div class="row">

                    <div class="col-lg-4 col-md-8 col-12 mx-auto">

                        <div class="card z-index-0 fadeIn3 fadeInBottom">


                            <!-- Card Header -->

                            <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">

                                <div class="bg-gradient-dark shadow-dark border-radius-lg py-3 pe-1">

                                    <h4 class="text-white font-weight-bolder text-center mt-2 mb-0">
                                        Admin Sign in
                                    </h4>

                                    <div class="row mt-3">

                                        <div class="col-2 text-center ms-auto">

                                            <a
                                                class="btn btn-link px-3"
                                                href="javascript:;"
                                            >

                                                <i class="fa fa-facebook text-white text-lg"></i>

                                            </a>

                                        </div>


                                        <div class="col-2 text-center px-1">

                                            <a
                                                class="btn btn-link px-3"
                                                href="javascript:;"
                                            >

                                                <i class="fa fa-github text-white text-lg"></i>

                                            </a>

                                        </div>


                                        <div class="col-2 text-center me-auto">

                                            <a
                                                class="btn btn-link px-3"
                                                href="javascript:;"
                                            >

                                                <i class="fa fa-google text-white text-lg"></i>

                                            </a>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <!-- Card Body -->

                            <div class="card-body">

                                <?php if ($error): ?>

                                    <div class="alert alert-danger">

                                        <?= htmlspecialchars($error) ?>

                                    </div>

                                <?php endif; ?>


                                <form
                                    role="form"
                                    class="text-start"
                                    method="POST"
                                    action=""
                                >

                                    <!-- Email -->

                                    <div class="input-group input-group-outline my-3<?php echo $emailError ? ' has-error' : ''; ?>">

                                       <input
                                        type="text"
                                        name="email"
                                        class="form-control"
                                        placeholder="Email"
                                        value="<?= htmlspecialchars($email ?? '') ?>"
                                    >

                                    </div>

                                    <?php if ($emailError): ?>
                                        <small class="text-danger field-error">
                                            <?= htmlspecialchars($emailError) ?>
                                        </small>
                                    <?php endif; ?>


                                    <!-- Password -->

                                    <div class="input-group input-group-outline mb-3<?php echo $passwordError ? ' has-error' : ''; ?>">


                                         <input
                                            type="password"
                                            name="password"
                                            class="form-control"
                                            placeholder="Password"
                                        >

                                    </div>

                                    <?php if ($passwordError): ?>
                                        <small class="text-danger field-error">
                                            <?= htmlspecialchars($passwordError) ?>
                                        </small>
                                    <?php endif; ?>


                                    <!-- Remember Me -->

                                    <div class="form-check form-switch d-flex align-items-center mb-3">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="rememberMe"
                                        >

                                        <label
                                            class="form-check-label mb-0 ms-3"
                                            for="rememberMe"
                                        >
                                            Remember me
                                        </label>

                                    </div>


                                    <!-- Login Button -->

                                    <div class="text-center">

                                        <button
                                            type="submit"
                                            class="btn bg-gradient-dark w-100 my-4 mb-2"
                                        >

                                            Sign in

                                        </button>

                                    </div>


                                    <!-- Customer Login -->

                                    <p class="mt-4 text-sm text-center">

                                        Customer?

                                        <a
                                            href="/public/login.php"
                                            class="text-primary text-gradient font-weight-bold"
                                        >
                                            Customer Sign In
                                        </a>

                                    </p>

                                </form>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Footer -->

            <footer class="footer position-absolute bottom-2 py-2 w-100">

                <div class="container">

                    <div class="row align-items-center justify-content-lg-between">

                        <div class="col-12 col-md-6 my-auto">

                            <div class="copyright text-center text-sm text-white text-lg-start">

                                ©

                                <script>
                                    document.write(new Date().getFullYear())
                                </script>

                                , ClothWear Admin Dashboard

                            </div>

                        </div>


                        <div class="col-12 col-md-6">

                            <ul class="nav nav-footer justify-content-center justify-content-lg-end">

                                <li class="nav-item">

                                    <a
                                        href="/public/index.php"
                                        class="nav-link text-white"
                                    >
                                        Storefront
                                    </a>

                                </li>

                                <li class="nav-item">

                                    <a
                                        href="/admin/login.php"
                                        class="nav-link pe-0 text-white"
                                    >
                                        Admin Login
                                    </a>

                                </li>

                            </ul>

                        </div>

                    </div>

                </div>

            </footer>

        </div>

    </main>


    <!-- Core JS Files -->

    <script src="assets/js/core/popper.min.js"></script>

    <script src="assets/js/core/bootstrap.min.js"></script>

    <script src="assets/js/plugins/perfect-scrollbar.min.js"></script>

    <script src="assets/js/plugins/smooth-scrollbar.min.js"></script>


    <script>

        var win = navigator.platform.indexOf('Win') > -1;

        if (win && document.querySelector('#sidenav-scrollbar')) {

            var options = {
                damping: '0.5'
            };

            Scrollbar.init(
                document.querySelector('#sidenav-scrollbar'),
                options
            );
        }

    </script>


    <script
        async
        defer
        src="https://buttons.github.io/buttons.js"
    ></script>


    <script src="assets/js/material-dashboard.min.js?v=3.2.0"></script>

    <!-- Reliable floating-label fix for input-group-outline fields -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {

        document.querySelectorAll(".input-group-outline input").forEach(function (input) {

            var group = input.closest(".input-group-outline");

            if (!group) {
                return;
            }

            function syncFilledState() {
                if (input.value.trim() !== "") {
                    group.classList.add("is-filled");
                } else {
                    group.classList.remove("is-filled");
                }
            }

            // Page load ke waqt bhi check karo (agar value pehle se set hai)
            syncFilledState();

            input.addEventListener("input", syncFilledState);

            input.addEventListener("focus", function () {
                group.classList.add("is-focused");
            });

            input.addEventListener("blur", function () {
                group.classList.remove("is-focused");
                syncFilledState();
            });

        });

    });
    </script>

</body>

</html>