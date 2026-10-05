<?php

$pageTitle = "Reports";

require_once '../config/database.php';


/*
|--------------------------------------------------------------------------
| Helper Function
|--------------------------------------------------------------------------
*/

function getCount($mysqli, $query)
{
    $result = $mysqli->query($query);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int) ($row['total'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| SALES REPORT FILTER
|--------------------------------------------------------------------------
*/

$reportType = $_GET['report'] ?? 'monthly';

if (!in_array($reportType, ['weekly', 'monthly', 'yearly'], true)) {
    $reportType = 'monthly';
}


/*
|--------------------------------------------------------------------------
| SUMMARY DATA
|--------------------------------------------------------------------------
*/

/* Total Customers */

$totalUsers = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'customer'"
);


/* Total Categories */

$totalCategories = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM categories"
);


/* Total Products */

$totalProducts = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM products"
);


/* Total Orders */

$totalOrders = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM orders"
);


/*
|--------------------------------------------------------------------------
| ORDER STATUS
|--------------------------------------------------------------------------
*/

/* Processing Orders */

$processingOrders = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE LOWER(order_status) = 'processing'"
);


/* Shipped Orders */

$shippedOrders = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE LOWER(order_status) = 'shipped'"
);


/* Delivered Orders */

$deliveredOrders = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE LOWER(order_status) = 'delivered'"
);


/* Cancelled Orders */

$cancelledOrders = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE LOWER(order_status) = 'cancelled'"
);


/*
|--------------------------------------------------------------------------
| EXISTING CARD NAMES
|--------------------------------------------------------------------------
*/

$completedOrders = $deliveredOrders;

$pendingOrders = $processingOrders + $shippedOrders;


/*
|--------------------------------------------------------------------------
| TOTAL SALES
|--------------------------------------------------------------------------
|
| Cancelled orders sales mein include nahi honge.
|
*/

$totalSales = 0;

$salesQuery = "
    SELECT COALESCE(SUM(total_amount), 0) AS total_sales
    FROM orders
    WHERE LOWER(order_status) != 'cancelled'
";

$salesResult = $mysqli->query($salesQuery);

if ($salesResult) {

    $salesRow = $salesResult->fetch_assoc();

    $totalSales = (float) ($salesRow['total_sales'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| SALES REPORT DATA
|--------------------------------------------------------------------------
*/

$salesReport = [];


/*
|--------------------------------------------------------------------------
| WEEKLY SALES
|--------------------------------------------------------------------------
*/

if ($reportType === 'weekly') {

    $salesQuery = "
        SELECT

            DATE_SUB(
                DATE(created_at),
                INTERVAL WEEKDAY(created_at) DAY
            ) AS week_start,

            CONCAT(

                DATE_FORMAT(
                    DATE_SUB(
                        DATE(created_at),
                        INTERVAL WEEKDAY(created_at) DAY
                    ),
                    '%d %b %Y'
                ),

                ' - ',

                DATE_FORMAT(
                    DATE_ADD(
                        DATE_SUB(
                            DATE(created_at),
                            INTERVAL WEEKDAY(created_at) DAY
                        ),
                        INTERVAL 6 DAY
                    ),
                    '%d %b %Y'
                )

            ) AS period_name,

            COUNT(*) AS order_count,

            COALESCE(SUM(total_amount), 0) AS sales

        FROM orders

        WHERE LOWER(order_status) != 'cancelled'

          AND created_at >= DATE_SUB(
                CURDATE(),
                INTERVAL 11 WEEK
          )

        GROUP BY week_start

        ORDER BY week_start DESC
    ";

    $salesResult = $mysqli->query($salesQuery);

    if ($salesResult) {

        while ($row = $salesResult->fetch_assoc()) {

            $salesReport[] = $row;
        }
    }
}


/*
|--------------------------------------------------------------------------
| MONTHLY SALES
|--------------------------------------------------------------------------
*/

if ($reportType === 'monthly') {

    $salesQuery = "
        SELECT

            DATE_FORMAT(created_at, '%Y-%m') AS sale_period,

            DATE_FORMAT(created_at, '%M %Y') AS period_name,

            COUNT(*) AS order_count,

            COALESCE(SUM(total_amount), 0) AS sales

        FROM orders

        WHERE LOWER(order_status) != 'cancelled'

          AND created_at >= DATE_SUB(
                CURDATE(),
                INTERVAL 11 MONTH
          )

        GROUP BY
            DATE_FORMAT(created_at, '%Y-%m'),
            DATE_FORMAT(created_at, '%M %Y')

        ORDER BY sale_period DESC
    ";

    $salesResult = $mysqli->query($salesQuery);

    if ($salesResult) {

        while ($row = $salesResult->fetch_assoc()) {

            $salesReport[] = $row;
        }
    }
}


/*
|--------------------------------------------------------------------------
| YEARLY SALES
|--------------------------------------------------------------------------
*/

if ($reportType === 'yearly') {

    $salesQuery = "
        SELECT

            YEAR(created_at) AS sale_year,

            YEAR(created_at) AS period_name,

            COUNT(*) AS order_count,

            COALESCE(SUM(total_amount), 0) AS sales

        FROM orders

        WHERE LOWER(order_status) != 'cancelled'

          AND created_at >= DATE_SUB(
                CURDATE(),
                INTERVAL 4 YEAR
          )

        GROUP BY YEAR(created_at)

        ORDER BY sale_year DESC
    ";

    $salesResult = $mysqli->query($salesQuery);

    if ($salesResult) {

        while ($row = $salesResult->fetch_assoc()) {

            $salesReport[] = $row;
        }
    }
}


/*
|--------------------------------------------------------------------------
| GRAPH DATA
|--------------------------------------------------------------------------
*/

$graphReport = array_reverse($salesReport);

$graphLabels = [];

$graphSales = [];

foreach ($graphReport as $report) {

    $graphLabels[] = $report['period_name'];

    $graphSales[] = (float) $report['sales'];
}


/*
|--------------------------------------------------------------------------
| CURRENT MONTH
|--------------------------------------------------------------------------
*/

$currentMonth = date('F Y');

$currentMonthOrders = 0;

$currentMonthSales = 0;

$currentMonthQuery = "
    SELECT

        COUNT(*) AS order_count,

        COALESCE(SUM(total_amount), 0) AS sales

    FROM orders

    WHERE LOWER(order_status) != 'cancelled'

      AND MONTH(created_at) = MONTH(CURDATE())

      AND YEAR(created_at) = YEAR(CURDATE())
";

$currentMonthResult = $mysqli->query($currentMonthQuery);

if ($currentMonthResult) {

    $currentMonthRow = $currentMonthResult->fetch_assoc();

    $currentMonthOrders =
        (int) ($currentMonthRow['order_count'] ?? 0);

    $currentMonthSales =
        (float) ($currentMonthRow['sales'] ?? 0);
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
        Reports - ClothWear Admin
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

    <script
        src="https://kit.fontawesome.com/42d5adcbca.js"
        crossorigin="anonymous">
    </script>


    <!-- Material Icons -->

    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">


    <!-- Material Dashboard -->

    <link id="pagestyle"
        href="assets/css/material-dashboard.css?v=3.2.0"
        rel="stylesheet">


    <!-- Chart.js -->

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<style>

/* =========================================================
   GENERAL PAGE
========================================================= */

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

.container-fluid {
    min-height: auto;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.reports-page-title {
    font-size: 1.35rem;
    font-weight: 700;
    color: #344767;
    margin-bottom: 5px;
}

.reports-page-subtitle {
    font-size: 0.875rem;
    color: #67748e;
    margin-bottom: 0;
}


/* =========================================================
   ALL REPORT CARDS
========================================================= */

.report-card,
.status-card,
.current-month-card,
.sales-overview-card,
.sales-details-card {
    border: 0 !important;
    border-radius: 16px !important;
    background: #ffffff;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
    transition: all 0.25s ease;
}

.report-card:hover,
.status-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
}


/* =========================================================
   SUMMARY CARDS
========================================================= */

.report-card .card-body,
.status-card .card-body {
    padding: 21px !important;
}

.report-card p,
.status-card p {
    color: #67748e;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.35px;
}

.report-card h5,
.status-card h5 {
    color: #344767;
    font-size: 1.25rem;
    font-weight: 700;
}


/* =========================================================
   COLORFUL ICON BOXES
========================================================= */

.report-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    transition: all 0.25s ease;
}


/* Sales */

.sales-icon {
    background: #e8f7ef !important;
    color: #2e8b62 !important;
}


/* Orders */

.orders-icon {
    background: #eaf2ff !important;
    color: #3f6fd9 !important;
}


/* Customers */

.customers-icon {
    background: #fff1e4 !important;
    color: #e58a2b !important;
}


/* Products */

.products-icon {
    background: #f1eafd !important;
    color: #8a5fc7 !important;
}


/* Current Month */

.month-orders-icon {
    background: #eaf2ff !important;
    color: #3f6fd9 !important;
}

.month-sales-icon {
    background: #e8f7ef !important;
    color: #2e8b62 !important;
}


/* Icon */

.report-icon .material-symbols-rounded {
    font-size: 27px;
    line-height: 1;
}


/* Hover */

.report-card:hover .report-icon {
    transform: scale(1.06);
}


/* =========================================================
   STATUS CARDS
========================================================= */

.status-card {
    overflow: hidden;
}

.status-card .card-body {
    padding: 19px !important;
}

.status-card .material-symbols-rounded {
    font-size: 28px;
}


/* =========================================================
   CURRENT MONTH
========================================================= */

.current-month-card {
    overflow: hidden;
}

.current-month-card .card-header {
    padding: 22px 24px 5px;
    background: #ffffff;
    border-bottom: 0;
}

.current-month-card .card-header h6 {
    color: #344767;
    font-size: 0.95rem;
    font-weight: 700;
}

.current-month-card .card-header p {
    color: #67748e;
    font-size: 0.75rem;
}

.current-month-card .card-body {
    padding: 20px 24px 24px;
}


/* =========================================================
   SALES OVERVIEW
========================================================= */

.sales-overview-card {
    overflow: hidden;
}

.sales-overview-header {
    padding: 22px 24px 14px !important;
    background: #ffffff;
    border-bottom: 1px solid #f0f1f3;
}

.sales-overview-title {
    color: #344767;
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 4px;
}

.sales-overview-subtitle {
    color: #67748e;
    font-size: 0.75rem;
    margin-bottom: 0;
}


/* =========================================================
   REPORT FILTER
========================================================= */

.report-filter-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

.report-filter-label {
    color: #67748e;
    font-size: 0.72rem;
    font-weight: 600;
}

.report-filter {
    min-width: 140px;
    height: 38px;

    padding: 7px 38px 7px 12px !important;

    border: 1px solid #dfe3e8 !important;
    border-radius: 9px !important;

    background-color: #ffffff !important;
    background-image: var(--bs-form-select-bg-img) !important;
    background-repeat: no-repeat !important;
    background-position: right 12px center !important;
    background-size: 16px 12px !important;

    color: #344767 !important;

    font-size: 0.78rem !important;
    font-weight: 500 !important;

    box-shadow: none !important;

    cursor: pointer;

    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}

.report-filter:hover {
    border-color: #c8cdd4 !important;
}

.report-filter:focus {
    border-color: #344767 !important;
    box-shadow: 0 0 0 2px rgba(52, 71, 103, 0.08) !important;
}

/* =========================================================
   CHART AREA
========================================================= */

.sales-overview-card .card-body {
    padding: 15px 20px 22px;
}

.sales-chart-wrapper {
    position: relative;
    width: 100%;
    height: 380px;
    padding: 5px 5px 0;
}

#salesChart {
    width: 100% !important;
    height: 100% !important;
}


/* =========================================================
   SALES DETAILS
========================================================= */

.sales-details-card {
    overflow: hidden;
}

.sales-details-card .card-header {
    padding: 21px 24px 14px;
    background: #ffffff;
    border-bottom: 1px solid #f0f1f3;
}

.sales-details-card .card-header h6 {
    color: #344767;
    font-size: 0.95rem;
    font-weight: 700;
}

.sales-details-card .card-header p {
    color: #67748e;
    font-size: 0.75rem;
}


/* =========================================================
   TABLE
========================================================= */

.sales-table {
    margin-top: 0 !important;
}

.sales-table thead th {
    background: #f8f9fa;

    border-bottom: 1px solid #e9ecef !important;

    padding: 14px 18px !important;

    color: #67748e !important;

    font-size: 0.66rem !important;

    font-weight: 700 !important;

    letter-spacing: 0.45px;
}

.sales-table tbody td {
    padding: 15px 18px !important;

    border-bottom: 1px solid #f0f2f5 !important;

    vertical-align: middle;
}

.sales-table tbody tr:last-child td {
    border-bottom: 0 !important;
}

.sales-table tbody tr {
    transition: background 0.2s ease;
}

.sales-table tbody tr:hover {
    background: #fafbfc;
}

.sales-period {
    color: #344767;
    font-size: 0.8rem;
    font-weight: 600;
}

.sales-number {
    color: #344767;
    font-size: 0.8rem;
    font-weight: 600;
}


/* =========================================================
   STATUS BADGES
========================================================= */

.sales-table .badge {
    padding: 6px 10px;

    border-radius: 7px;

    font-size: 0.65rem;

    font-weight: 600;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.sales-empty-state {
    padding: 55px 20px;
    text-align: center;
}

.sales-empty-state .material-symbols-rounded {
    font-size: 45px;
    color: #a0a7b1;
}

.sales-empty-state p {
    color: #67748e;
    font-size: 0.78rem;
    margin-top: 10px;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 767px) {

    .reports-page-title {
        font-size: 1.15rem;
    }

    .reports-page-subtitle {
        font-size: 0.78rem;
    }

    .report-card .card-body,
    .status-card .card-body {
        padding: 18px !important;
    }

    .report-card h5,
    .status-card h5 {
        font-size: 1.1rem;
    }

    .report-icon {
        width: 44px;
        height: 44px;
    }

    .report-icon .material-symbols-rounded {
        font-size: 23px;
    }

    .current-month-card .card-header {
        padding: 19px 18px 5px;
    }

    .current-month-card .card-body {
        padding: 17px 18px 20px;
    }

    .sales-overview-card .card-header {
        padding: 19px 18px 14px !important;
    }

    .sales-overview-card .card-body {
        padding: 10px 10px 18px;
    }

    .report-filter-wrapper {
        width: 100%;
        justify-content: space-between;
        margin-top: 12px;
    }

    .report-filter {
        min-width: 135px;
    }

    .sales-chart-wrapper {
        height: 290px;
        padding: 5px 0;
    }

    .sales-details-card .card-header {
        padding: 19px 18px 14px;
    }

    .sales-table thead th,
    .sales-table tbody td {
        padding: 12px 10px !important;
    }
}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 480px) {

    .report-icon {
        width: 40px;
        height: 40px;
    }

    .report-icon .material-symbols-rounded {
        font-size: 21px;
    }

    .sales-chart-wrapper {
        height: 260px;
    }

    .sales-table {
        min-width: 600px;
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


            <!-- PAGE HEADER -->

            <div class="row">

                <div class="col-12">

                    <div class="mb-4">

                        <h4 class="reports-page-title">
                            Analytics & Reports
                        </h4>

                        <p class="reports-page-subtitle">
                            Overview of your ClothWear store performance
                        </p>

                    </div>

                </div>

            </div>


            <!-- MAIN SUMMARY CARDS -->

            <div class="row">


                <!-- TOTAL SALES -->

                <div class="col-xl-3 col-sm-6 mb-4">

                    <div class="card report-card">

                        <div class="card-body">

                            <div class="row">

                                <div class="col-8">

                                    <p class="text-sm mb-1 text-uppercase font-weight-bold">
                                        Total Sales
                                    </p>

                                    <h5 class="font-weight-bolder mb-0">
                                        Rs. <?= number_format($totalSales, 2) ?>
                                    </h5>

                                </div>


                                <div class="col-4 text-end">

                                    <div class="report-icon sales-icon">

                                        <span class="material-symbols-rounded">
                                            payments
                                        </span>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- TOTAL ORDERS -->

                <div class="col-xl-3 col-sm-6 mb-4">

                    <div class="card report-card">

                        <div class="card-body">

                            <div class="row">

                                <div class="col-8">

                                    <p class="text-sm mb-1 text-uppercase font-weight-bold">
                                        Total Orders
                                    </p>

                                    <h5 class="font-weight-bolder mb-0">
                                        <?= number_format($totalOrders) ?>
                                    </h5>

                                </div>


                                <div class="col-4 text-end">

                                    <div class="report-icon orders-icon">

                                        <span class="material-symbols-rounded">
                                            shopping_cart
                                        </span>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- TOTAL CUSTOMERS -->

                <div class="col-xl-3 col-sm-6 mb-4">

                    <div class="card report-card">

                        <div class="card-body">

                            <div class="row">

                                <div class="col-8">

                                    <p class="text-sm mb-1 text-uppercase font-weight-bold">
                                        Total Customers
                                    </p>

                                    <h5 class="font-weight-bolder mb-0">
                                        <?= number_format($totalUsers) ?>
                                    </h5>

                                </div>


                                <div class="col-4 text-end">

                                    <div class="report-icon customers-icon">

                                        <span class="material-symbols-rounded">
                                            group
                                        </span>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- TOTAL PRODUCTS -->

                <div class="col-xl-3 col-sm-6 mb-4">

                    <div class="card report-card">

                        <div class="card-body">

                            <div class="row">

                                <div class="col-8">

                                    <p class="text-sm mb-1 text-uppercase font-weight-bold">
                                        Total Products
                                    </p>

                                    <h5 class="font-weight-bolder mb-0">
                                        <?= number_format($totalProducts) ?>
                                    </h5>

                                </div>


                                <div class="col-4 text-end">

                                    <div class="report-icon products-icon">

                                        <span class="material-symbols-rounded">
                                            inventory_2
                                        </span>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- ORDER STATUS CARDS -->

            <div class="row">


                <!-- COMPLETED -->

                <div class="col-xl-3 col-md-6 mb-4">

                    <div class="card status-card">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <p class="text-sm mb-1 text-uppercase font-weight-bold">
                                        Completed Orders
                                    </p>

                                    <h5 class="font-weight-bolder mb-0">
                                        <?= number_format($completedOrders) ?>
                                    </h5>

                                </div>

                                <span class="material-symbols-rounded text-success">
                                    check_circle
                                </span>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- PENDING -->

                <div class="col-xl-3 col-md-6 mb-4">

                    <div class="card status-card">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <p class="text-sm mb-1 text-uppercase font-weight-bold">
                                        Pending Orders
                                    </p>

                                    <h5 class="font-weight-bolder mb-0">
                                        <?= number_format($pendingOrders) ?>
                                    </h5>

                                </div>

                                <span class="material-symbols-rounded text-warning">
                                    pending
                                </span>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- CANCELLED -->

                <div class="col-xl-3 col-md-6 mb-4">

                    <div class="card status-card">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <p class="text-sm mb-1 text-uppercase font-weight-bold">
                                        Cancelled Orders
                                    </p>

                                    <h5 class="font-weight-bolder mb-0">
                                        <?= number_format($cancelledOrders) ?>
                                    </h5>

                                </div>

                                <span class="material-symbols-rounded text-danger">
                                    cancel
                                </span>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- CATEGORIES -->

                <div class="col-xl-3 col-md-6 mb-4">

                    <div class="card status-card">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <p class="text-sm mb-1 text-uppercase font-weight-bold">
                                        Categories
                                    </p>

                                    <h5 class="font-weight-bolder mb-0">
                                        <?= number_format($totalCategories) ?>
                                    </h5>

                                </div>

                                <span class="material-symbols-rounded text-info">
                                    category
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- CURRENT MONTH -->

            <div class="row">

                <div class="col-12">

                    <div class="card current-month-card mb-4">


                        <div class="card-header">

                            <h6 class="mb-1">
                                Current Month
                            </h6>

                            <p class="text-sm text-secondary mb-0">
                                <?= htmlspecialchars($currentMonth) ?>
                                performance
                            </p>

                        </div>


                        <div class="card-body">

                            <div class="row">


                                <!-- ORDERS THIS MONTH -->

                                <div class="col-md-6 mb-3 mb-md-0">

                                    <div class="d-flex align-items-center">

                                        <div class="report-icon month-orders-icon me-3">

                                            <span class="material-symbols-rounded">
                                                shopping_cart
                                            </span>

                                        </div>

                                        <div>

                                            <p class="text-sm mb-0">
                                                Orders this month
                                            </p>

                                            <h5 class="font-weight-bolder mb-0">
                                                <?= number_format($currentMonthOrders) ?>
                                            </h5>

                                        </div>

                                    </div>

                                </div>


                                <!-- SALES THIS MONTH -->

                                <div class="col-md-6">

                                    <div class="d-flex align-items-center">

                                        <div class="report-icon month-sales-icon me-3">

                                            <span class="material-symbols-rounded">
                                                payments
                                            </span>

                                        </div>

                                        <div>

                                            <p class="text-sm mb-0">
                                                Sales this month
                                            </p>

                                            <h5 class="font-weight-bolder mb-0">

                                                Rs.

                                                <?= number_format($currentMonthSales, 2) ?>

                                            </h5>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- SALES ANALYTICS -->

            <div class="row">

                <div class="col-12">

                    <div class="card sales-overview-card mb-4">


                        <!-- CARD HEADER -->

                        <div class="card-header sales-overview-header">

                            <div class="d-flex justify-content-between align-items-center flex-wrap">


                                <div>

                                    <h6 class="sales-overview-title">
                                        Sales Overview
                                    </h6>

                                    <p class="sales-overview-subtitle">
                                        Monitor your store revenue and order performance
                                    </p>

                                </div>


                                <!-- FILTER -->

                                <form method="GET"
                                    class="report-filter-wrapper mt-2 mt-md-0">

                                    <span class="report-filter-label">
                                        View by
                                    </span>

                                    <select
                                        name="report"
                                        class="form-select form-select-sm report-filter"
                                        onchange="this.form.submit()">

                                        <option value="weekly"
                                            <?= $reportType === 'weekly' ? 'selected' : '' ?>>
                                            Weekly
                                        </option>

                                        <option value="monthly"
                                            <?= $reportType === 'monthly' ? 'selected' : '' ?>>
                                            Monthly
                                        </option>

                                        <option value="yearly"
                                            <?= $reportType === 'yearly' ? 'selected' : '' ?>>
                                            Yearly
                                        </option>

                                    </select>

                                </form>

                            </div>

                        </div>


                        <!-- CHART -->

                        <div class="card-body">

                            <?php if (!empty($salesReport)): ?>

                                <div class="sales-chart-wrapper">

                                    <canvas id="salesChart"></canvas>

                                </div>

                            <?php else: ?>

                                <div class="sales-empty-state">

                                    <span class="material-symbols-rounded">
                                        analytics
                                    </span>

                                    <p>
                                        No sales data available for this period.
                                    </p>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>


            <!-- SALES DETAILS TABLE -->

            <div class="row">

                <div class="col-12">

                    <div class="card sales-details-card mb-4">


                        <!-- TABLE HEADER -->

                        <div class="card-header">

                            <h6 class="mb-1">
                                Sales Details
                            </h6>

                            <p class="text-sm text-secondary mb-0">
                                <?= ucfirst($reportType) ?>
                                sales performance
                            </p>

                        </div>


                        <div class="card-body px-0 pt-0 pb-2">

                            <div class="table-responsive p-0">


                                <table class="table align-items-center mb-0 sales-table">


                                    <thead>

                                        <tr>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-3">
                                                Period
                                            </th>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Orders
                                            </th>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Sales
                                            </th>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Status
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>


                                        <?php if (!empty($salesReport)): ?>


                                            <?php foreach ($salesReport as $report): ?>


                                                <tr>


                                                    <!-- PERIOD -->

                                                    <td>

                                                        <div class="d-flex px-3 py-1">

                                                            <div class="d-flex flex-column justify-content-center">

                                                                <h6 class="sales-period mb-0">

                                                                    <?= htmlspecialchars(
                                                                        $report['period_name']
                                                                    ) ?>

                                                                </h6>

                                                            </div>

                                                        </div>

                                                    </td>


                                                    <!-- ORDERS -->

                                                    <td>

                                                        <p class="sales-number mb-0">

                                                            <?= number_format(
                                                                (int) $report['order_count']
                                                            ) ?>

                                                        </p>

                                                    </td>


                                                    <!-- SALES -->

                                                    <td>

                                                        <p class="sales-number mb-0">

                                                            Rs.

                                                            <?= number_format(
                                                                (float) $report['sales'],
                                                                2
                                                            ) ?>

                                                        </p>

                                                    </td>


                                                    <!-- STATUS -->

                                                    <td>

                                                        <?php if (
                                                            (float) $report['sales'] > 0
                                                        ): ?>

                                                            <span class="badge bg-gradient-success">

                                                                Sales Recorded

                                                            </span>

                                                        <?php else: ?>

                                                            <span class="badge bg-gradient-secondary">

                                                                No Sales

                                                            </span>

                                                        <?php endif; ?>

                                                    </td>


                                                </tr>


                                            <?php endforeach; ?>


                                        <?php else: ?>


                                            <tr>

                                                <td colspan="4">

                                                    <div class="sales-empty-state">

                                                        <span class="material-symbols-rounded">
                                                            analytics
                                                        </span>

                                                        <p>
                                                            No sales data available yet.
                                                        </p>

                                                    </div>

                                                </td>

                                            </tr>


                                        <?php endif; ?>


                                    </tbody>


                                </table>


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


    <?php if (!empty($salesReport)): ?>

        <script>

            const salesLabels =
                <?= json_encode($graphLabels) ?>;

            const salesData =
                <?= json_encode($graphSales) ?>;


            const salesChartElement =
                document.getElementById('salesChart');


            if (salesChartElement) {

                new Chart(
                    salesChartElement,
                    {

                        type: 'line',

                        data: {

                            labels: salesLabels,

                            datasets: [

                                {

                                    label: 'Sales',

                                    data: salesData,

                                    tension: 0.35,

                                    fill: true,

                                    borderWidth: 3,

                                    pointRadius: 4,

                                    pointHoverRadius: 7,

                                    pointBorderWidth: 2

                                }

                            ]

                        },


                        options: {

                            responsive: true,

                            maintainAspectRatio: false,


                            interaction: {

                                intersect: false,

                                mode: 'index'

                            },


                            plugins: {

                                legend: {

                                    display: false

                                },


                                tooltip: {

                                    backgroundColor: '#344767',

                                    titleColor: '#ffffff',

                                    bodyColor: '#ffffff',

                                    padding: 12,

                                    cornerRadius: 8,

                                    displayColors: false,


                                    callbacks: {

                                        label: function(context) {

                                            return 'Sales: Rs. ' +

                                                Number(
                                                    context.raw
                                                ).toLocaleString(
                                                    'en-PK',
                                                    {
                                                        minimumFractionDigits: 2,
                                                        maximumFractionDigits: 2
                                                    }
                                                );

                                        }

                                    }

                                }

                            },


                            scales: {

                                y: {

                                    beginAtZero: true,

                                    border: {

                                        display: false

                                    },


                                    grid: {

                                        color: 'rgba(0, 0, 0, 0.06)',

                                        drawTicks: false

                                    },


                                    ticks: {

                                        padding: 10,

                                        color: '#7b809a',

                                        font: {

                                            size: 11

                                        },


                                        callback: function(value) {

                                            return 'Rs. ' +

                                                Number(
                                                    value
                                                ).toLocaleString(
                                                    'en-PK'
                                                );

                                        }

                                    }

                                },


                                x: {

                                    border: {

                                        display: false

                                    },


                                    grid: {

                                        display: false

                                    },


                                    ticks: {

                                        padding: 10,

                                        color: '#7b809a',

                                        font: {

                                            size: 11

                                        },

                                        maxRotation: 0,

                                        minRotation: 0

                                    }

                                }

                            }

                        }

                    }
                );

            }

        </script>

    <?php endif; ?>


</body>

</html>