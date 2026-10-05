<style>
    /* ================================
       HEADER / NAVBAR ALIGNMENT
       ================================ */

    @media (min-width: 1200px) {

        .navbar-main {
            margin-left: 260px !important;
            margin-right: 12px !important;
            width: calc(100% - 292px) !important;
            padding-left: 10px !important;
        }

        .navbar-main > .container-fluid {
            padding-left: 0 !important;
        }

        .navbar-main .breadcrumb {
            padding-left: 0 !important;
        }
    }


    /* ================================
       TABLET / MOBILE
       ================================ */

    @media (max-width: 1199.98px) {

        .navbar-main {
            margin-left: 12px !important;
            margin-right: 12px !important;
            width: auto !important;
        }

    }

/* =========================================
   SMALL SCREEN ONLY
   ========================================= */

@media (max-width: 575.98px) {

    /* Make the right-side navbar stack vertically */
    .navbar-main #navbar {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        width: 100% !important;
    }

    /* Type here field */
    .navbar-main #navbar > .ms-md-auto {
        width: 100% !important;
        padding-right: 0 !important;
        margin-bottom: 10px !important;
    }

    .navbar-main #navbar > .ms-md-auto .input-group {
        width: 100% !important;
    }

    /* Online Builder + icons row */
    .navbar-main #navbar > .navbar-nav {
        width: 100% !important;
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: flex-end !important;
    }

    /* Online Builder */
    .navbar-main #navbar > .navbar-nav > .nav-item:first-child {
        margin-right: auto !important;
    }

    .navbar-main #navbar > .navbar-nav > .nav-item:first-child .btn {
        margin-right: 0 !important;
    }
}

</style>


<nav class="navbar navbar-main navbar-expand-lg px-0 shadow-none border-radius-xl"
     id="navbarBlur"
     data-scroll="false">

    <div class="container-fluid py-1 px-3">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">

            <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1">

                <li class="breadcrumb-item text-sm">

                    <a class="opacity-5 text-dark" href="javascript:;">
                        Pages
                    </a>

                </li>

                <li class="breadcrumb-item text-sm text-dark active"
                    aria-current="page">

                    <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>

                </li>

            </ol>

        </nav>


        <!-- Right Side Navbar -->
        <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4"
             id="navbar">

            <div class="ms-md-auto pe-md-3 d-flex align-items-center">

                <div class="input-group input-group-outline">

                    <label class="form-label">
                        Type here...
                    </label>

                    <input type="text" class="form-control">

                </div>

            </div>


            <ul class="navbar-nav d-flex align-items-center justify-content-end">

                <!-- Online Builder -->
                <li class="nav-item d-flex align-items-center">

                    <a class="btn btn-outline-primary btn-sm mb-0 me-3"
                       target="_blank"
                       href="https://www.creative-tim.com/builder?ref=navbar-material-dashboard">

                        Online Builder

                    </a>

                </li>


                <!-- Mobile Sidebar Toggle -->
                <li class="nav-item d-xl-none ps-3 d-flex align-items-center">

                    <a href="javascript:;"
                       class="nav-link text-body p-0"
                       id="iconNavbarSidenav">

                        <div class="sidenav-toggler-inner">

                            <i class="sidenav-toggler-line"></i>
                            <i class="sidenav-toggler-line"></i>
                            <i class="sidenav-toggler-line"></i>

                        </div>

                    </a>

                </li>


                <!-- Settings -->
                <li class="nav-item px-3 d-flex align-items-center">

                    <a href="javascript:;"
                       class="nav-link text-body p-0">

                        <i class="material-symbols-rounded fixed-plugin-button-nav">
                            settings
                        </i>

                    </a>

                </li>


                <!-- Notifications -->
                <li class="nav-item dropdown pe-3 d-flex align-items-center">

                    <a href="javascript:;"
                       class="nav-link text-body p-0"
                       id="dropdownMenuButton"
                       data-bs-toggle="dropdown"
                       aria-expanded="false">

                        <i class="material-symbols-rounded">
                            notifications
                        </i>

                    </a>


                    <ul class="dropdown-menu dropdown-menu-end px-2 py-3 me-sm-n4"
                        aria-labelledby="dropdownMenuButton">

                        <li class="mb-2">

                            <a class="dropdown-item border-radius-md"
                               href="javascript:;">

                                <div class="d-flex py-1">

                                    <div class="my-auto">

                                        <img src="assets/img/team-2.jpg"
                                             class="avatar avatar-sm me-3">

                                    </div>

                                    <div class="d-flex flex-column justify-content-center">

                                        <h6 class="text-sm font-weight-normal mb-1">

                                            <span class="font-weight-bold">
                                                New message
                                            </span>

                                            from Laur

                                        </h6>

                                        <p class="text-xs text-secondary mb-0">

                                            <i class="fa fa-clock me-1"></i>
                                            13 minutes ago

                                        </p>

                                    </div>

                                </div>

                            </a>

                        </li>


                        <li class="mb-2">

                            <a class="dropdown-item border-radius-md"
                               href="javascript:;">

                                <div class="d-flex py-1">

                                    <div class="my-auto">

                                        <img src="assets/img/small-logos/logo-spotify.svg"
                                             class="avatar avatar-sm bg-gradient-dark me-3">

                                    </div>

                                    <div class="d-flex flex-column justify-content-center">

                                        <h6 class="text-sm font-weight-normal mb-1">

                                            <span class="font-weight-bold">
                                                New album
                                            </span>

                                            by Travis Scott

                                        </h6>

                                        <p class="text-xs text-secondary mb-0">

                                            <i class="fa fa-clock me-1"></i>
                                            1 day

                                        </p>

                                    </div>

                                </div>

                            </a>

                        </li>


                        <li>

                            <a class="dropdown-item border-radius-md"
                               href="javascript:;">

                                <div class="d-flex py-1">

                                    <div class="avatar avatar-sm bg-gradient-secondary me-3 my-auto">

                                        <svg width="12px"
                                             height="12px"
                                             viewBox="0 0 43 36"
                                             xmlns="http://www.w3.org/2000/svg">

                                            <path
                                                d="M43,10.7482083 L43,3.58333333 C43,1.60354167 41.3964587,0 39.4166667,0 L3.58333333,0 C1.60354167,0 0,1.60354167 0,3.58333333 L0,10.7482083 L43,10.7482083 Z"
                                                opacity="0.593633743">
                                            </path>

                                            <path
                                                d="M0,16.125 L0,32.25 C0,34.2297917 1.60354167,35.8333333 3.58333333,35.8333333 L39.4166667,35.8333333 C41.3964587,35.8333333 43,34.2297917 43,32.25 L43,16.125 L0,16.125 Z">
                                            </path>

                                        </svg>

                                    </div>


                                    <div class="d-flex flex-column justify-content-center">

                                        <h6 class="text-sm font-weight-normal mb-1">
                                            Payment successfully completed
                                        </h6>

                                        <p class="text-xs text-secondary mb-0">

                                            <i class="fa fa-clock me-1"></i>
                                            2 days

                                        </p>

                                    </div>

                                </div>

                            </a>

                        </li>

                    </ul>

                </li>


                <!-- Profile -->
                <li class="nav-item d-flex align-items-center">

                    <a href="/public/logout.php"
   class="nav-link text-body font-weight-bold px-0">

    <i class="material-symbols-rounded">
        logout
    </i>

</a>

                </li>

            </ul>

        </div>

    </div>

</nav>

<!-- End Navbar -->
