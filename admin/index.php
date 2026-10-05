<?php

$pageTitle = "Dashboard";

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
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function getCount($mysqli, $query)
{
    $result = $mysqli->query($query);

    if ($result) {
        $row = $result->fetch_assoc();
        return (int) ($row['total'] ?? 0);
    }

    return 0;
}

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

/* Total Products */
$totalProducts = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total FROM products"
);

/* Total Categories */
$totalCategories = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total FROM categories"
);

/* Total Orders */
$totalOrders = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total FROM orders"
);

/* Total Customers */
$totalCustomers = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'customer'"
);

/*
|--------------------------------------------------------------------------
| Order Status Counts
|--------------------------------------------------------------------------
*/

/* Processing Orders */
$processingOrders = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE order_status = 'processing'"
);

/* Shipped Orders */
$shippedOrders = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE order_status = 'shipped'"
);

/* Delivered Orders */
$deliveredOrders = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE order_status = 'delivered'"
);

/* Cancelled Orders */
$cancelledOrders = getCount(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM orders
     WHERE order_status = 'cancelled'"
);

/*
|--------------------------------------------------------------------------
| Today's Customers
|--------------------------------------------------------------------------
*/

$todayCustomers = getCount(
    $mysqli,
    "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'
    AND DATE(created_at) = CURDATE()
    "
);

/*
|--------------------------------------------------------------------------
| This Month Orders
|--------------------------------------------------------------------------
*/

$thisMonthOrders = getCount(
    $mysqli,
    "
    SELECT COUNT(*) AS total
    FROM orders
    WHERE MONTH(created_at) = MONTH(CURDATE())
    AND YEAR(created_at) = YEAR(CURDATE())
    "
);

/*
|--------------------------------------------------------------------------
| Total Sales
|--------------------------------------------------------------------------
|
| Cancelled orders are excluded.
|
*/

$totalSales = 0;

$result = $mysqli->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total
    FROM orders
    WHERE order_status != 'cancelled'
");

if ($result) {
    $row = $result->fetch_assoc();
    $totalSales = (float) ($row['total'] ?? 0);
}

/*
|--------------------------------------------------------------------------
| This Month Sales
|--------------------------------------------------------------------------
*/

$thisMonthSales = 0;

$result = $mysqli->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total
    FROM orders
    WHERE order_status != 'cancelled'
    AND MONTH(created_at) = MONTH(CURDATE())
    AND YEAR(created_at) = YEAR(CURDATE())
");

if ($result) {
    $row = $result->fetch_assoc();
    $thisMonthSales = (float) ($row['total'] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Monthly Orders - Last 6 Months
|--------------------------------------------------------------------------
*/

$monthlyLabels = [];
$monthlyOrders = [];

for ($i = 5; $i >= 0; $i--) {

    $date = new DateTime();
    $date->modify("-$i months");

    $monthNumber = (int) $date->format('m');
    $year = (int) $date->format('Y');

    $monthlyLabels[] = $date->format('M');

    $stmt = $mysqli->prepare("
        SELECT COUNT(*) AS total
        FROM orders
        WHERE MONTH(created_at) = ?
        AND YEAR(created_at) = ?
    ");

    $stmt->bind_param(
        "ii",
        $monthNumber,
        $year
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $monthlyOrders[] = (int) ($row['total'] ?? 0);

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Recent Orders
|--------------------------------------------------------------------------
*/

$recentOrders = [];

$result = $mysqli->query("
    SELECT
        id,
        order_number,
        order_status,
        total_amount,
        created_at
    FROM orders
    ORDER BY created_at DESC
    LIMIT 6
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $recentOrders[] = $row;
    }
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
    Dashboard - ClothWear Admin
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
<!-- Molla Line Awesome Icons -->
<link
    rel="stylesheet"
    href="../public/assets/vendor/line-awesome/line-awesome/line-awesome/css/line-awesome.min.css">
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
| Welcome Header
|--------------------------------------------------------------------------
*/

.dashboard-welcome {
    padding: 8px 4px 18px;
}

.dashboard-welcome h4 {
    letter-spacing: -0.3px;
}

/*
|--------------------------------------------------------------------------
| Stat Cards
|--------------------------------------------------------------------------
*/

.dashboard-stat-card {
    height: 100%;
    border-radius: 14px;
    overflow: hidden;
}

.dashboard-stat-card .card-header {
    padding: 18px 18px 12px;
}

.dashboard-stat-icon {
    width: 48px;
    height: 48px;
    min-width: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #343a40;
    color: #ffffff;
}

.dashboard-stat-icon span {
    font-size: 24px;
}

.dashboard-stat-value {
    font-size: 1.35rem;
    font-weight: 700;
    margin-bottom: 0;
}

.dashboard-stat-footer {
    padding: 10px 18px 15px;
}

/*
|--------------------------------------------------------------------------
| Status Cards
|--------------------------------------------------------------------------
*/

.order-status-card {
    border-radius: 14px;
    padding: 18px;
    height: 100%;
    background: #f8f9fa;
}

.order-status-icon {
    width: 42px;
    height: 42px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
}

.order-status-icon span {
    font-size: 21px;
}

/*
|--------------------------------------------------------------------------
| Chart
|--------------------------------------------------------------------------
*/

.chart-container {
    position: relative;
    height: 280px;
}

/*
|--------------------------------------------------------------------------
| Quick Actions
|--------------------------------------------------------------------------
*/

.quick-action {
    display: flex;
    align-items: center;
    padding: 14px;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    text-decoration: none;
    transition: 0.2s ease;
}

.quick-action:hover {
    background: #f8f9fa;
    transform: translateY(-1px);
}

.quick-action-icon {
    width: 42px;
    height: 42px;
    min-width: 42px;
    border-radius: 10px;
    background: #f1f3f5;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
}

.quick-action-icon span {
    font-size: 21px;
    color: #344767;
}

/*
|--------------------------------------------------------------------------
| Recent Orders
|--------------------------------------------------------------------------
*/

.order-id {
    font-weight: 700;
    color: #344767;
}

.order-status-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 6px 10px;
    border-radius: 20px;
    text-transform: capitalize;
    white-space: nowrap;
}

.order-status-processing {
    background: #fff3cd;
    color: #856404;
}

.order-status-shipped {
    background: #cfe2ff;
    color: #084298;
}

.order-status-delivered {
    background: #d1e7dd;
    color: #0f5132;
}

.order-status-cancelled {
    background: #f8d7da;
    color: #842029;
}

</style>

</head>

<body class="g-sidenav-show bg-gray-100">

<?php require_once '../includes/admin-header.php'; ?>

<?php require_once '../includes/admin-sidenavbar.php'; ?>

<main class="main-content position-relative border-radius-lg">

<div class="container-fluid py-4">

<!-- ========================================================= -->
<!-- Welcome -->
<!-- ========================================================= -->

<div class="dashboard-welcome">

    <h4 class="mb-1 font-weight-bolder">
        Dashboard
    </h4>

    <p class="text-sm text-secondary mb-0">
        Welcome back. Here's what's happening with your ClothWear store.
    </p>

</div>

<!-- ========================================================= -->
<!-- Main Statistics -->
<!-- ========================================================= -->

<div class="row">

    <!-- Total Sales -->

    <div class="col-xl-3 col-sm-6 mb-4">

        <div class="card dashboard-stat-card">

            <div class="card-header">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <p class="text-sm text-secondary mb-1">
                            Total Sales
                        </p>

                        <h4 class="dashboard-stat-value">
                            Rs. <?= number_format($totalSales, 0) ?>
                        </h4>

                    </div>

                    <div class="dashboard-stat-icon">

                        <span class="material-symbols-rounded">
                            payments
                        </span>

                    </div>

                </div>

            </div>

            <hr class="dark horizontal my-0">

            <div class="dashboard-stat-footer">

                <p class="text-sm mb-0 text-secondary">
                    All non-cancelled orders
                </p>

            </div>

        </div>

    </div>

    <!-- Total Orders -->

    <div class="col-xl-3 col-sm-6 mb-4">

        <div class="card dashboard-stat-card">

            <div class="card-header">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <p class="text-sm text-secondary mb-1">
                            Total Orders
                        </p>

                        <h4 class="dashboard-stat-value">
                            <?= number_format($totalOrders) ?>
                        </h4>

                    </div>

                    <div class="dashboard-stat-icon">

                        <span class="material-symbols-rounded">
                            shopping_cart
                        </span>

                    </div>

                </div>

            </div>

            <hr class="dark horizontal my-0">

            <div class="dashboard-stat-footer">

                <p class="text-sm mb-0 text-secondary">
                    <?= number_format($thisMonthOrders) ?> this month
                </p>

            </div>

        </div>

    </div>

    <!-- Customers -->

    <div class="col-xl-3 col-sm-6 mb-4">

        <div class="card dashboard-stat-card">

            <div class="card-header">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <p class="text-sm text-secondary mb-1">
                            Customers
                        </p>

                        <h4 class="dashboard-stat-value">
                            <?= number_format($totalCustomers) ?>
                        </h4>

                    </div>

                    <div class="dashboard-stat-icon">

                        <span class="material-symbols-rounded">
                            group
                        </span>

                    </div>

                </div>

            </div>

            <hr class="dark horizontal my-0">

            <div class="dashboard-stat-footer">

                <p class="text-sm mb-0 text-secondary">
                    <?= number_format($todayCustomers) ?> joined today
                </p>

            </div>

        </div>

    </div>

    <!-- Products -->

    <div class="col-xl-3 col-sm-6 mb-4">

        <div class="card dashboard-stat-card">

            <div class="card-header">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <p class="text-sm text-secondary mb-1">
                            Products
                        </p>

                        <h4 class="dashboard-stat-value">
                            <?= number_format($totalProducts) ?>
                        </h4>

                    </div>

                    <div class="dashboard-stat-icon">

                        <span class="material-symbols-rounded">
                            inventory_2
                        </span>

                    </div>

                </div>

            </div>

            <hr class="dark horizontal my-0">

            <div class="dashboard-stat-footer">

                <p class="text-sm mb-0 text-secondary">
                    <?= number_format($totalCategories) ?> categories
                </p>

            </div>

        </div>

    </div>

</div>

<!-- ========================================================= -->
<!-- Order Status -->
<!-- ========================================================= -->

<div class="row">

    <!-- Processing -->

    <div class="col-lg-3 col-sm-6 mb-4">

        <div class="order-status-card">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <p class="text-xs text-secondary mb-1">
                        Processing Orders
                    </p>

                    <h5 class="font-weight-bolder mb-0">
                        <?= number_format($processingOrders) ?>
                    </h5>

                </div>

                <div class="order-status-icon">

                    <span class="material-symbols-rounded text-warning">
                        pending
                    </span>

                </div>

            </div>

        </div>

    </div>

    <!-- Shipped -->

    <div class="col-lg-3 col-sm-6 mb-4">

        <div class="order-status-card">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <p class="text-xs text-secondary mb-1">
                        Shipped Orders
                    </p>

                    <h5 class="font-weight-bolder mb-0">
                        <?= number_format($shippedOrders) ?>
                    </h5>

                </div>

                <div class="order-status-icon">

                    <span class="material-symbols-rounded text-primary">
                        local_shipping
                    </span>

                </div>

            </div>

        </div>

    </div>

    <!-- Delivered -->

    <div class="col-lg-3 col-sm-6 mb-4">

        <div class="order-status-card">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <p class="text-xs text-secondary mb-1">
                        Delivered Orders
                    </p>

                    <h5 class="font-weight-bolder mb-0">
                        <?= number_format($deliveredOrders) ?>
                    </h5>

                </div>

                <div class="order-status-icon">

                    <span class="material-symbols-rounded text-success">
                        check_circle
                    </span>

                </div>

            </div>

        </div>

    </div>

    <!-- Cancelled -->

    <div class="col-lg-3 col-sm-6 mb-4">

        <div class="order-status-card">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <p class="text-xs text-secondary mb-1">
                        Cancelled Orders
                    </p>

                    <h5 class="font-weight-bolder mb-0">
                        <?= number_format($cancelledOrders) ?>
                    </h5>

                </div>

                <div class="order-status-icon">

                    <span class="material-symbols-rounded text-danger">
                        cancel
                    </span>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- ========================================================= -->
<!-- Chart + Quick Actions -->
<!-- ========================================================= -->

<div class="row">

    <!-- Monthly Orders Chart -->

    <div class="col-lg-8 mb-4">

        <div class="card h-100">

            <div class="card-header pb-0">

                <h6 class="mb-1">
                    Orders Overview
                </h6>

                <p class="text-sm text-secondary mb-0">
                    Order activity for the last 6 months
                </p>

            </div>

            <div class="card-body">

                <div class="chart-container">

                    <canvas id="ordersChart"></canvas>

                </div>

            </div>

        </div>

    </div>

    <!-- Quick Actions -->

    <div class="col-lg-4 mb-4">

        <div class="card h-100">

            <div class="card-header pb-0">

                <h6 class="mb-1">
                    Quick Actions
                </h6>

                <p class="text-sm text-secondary mb-0">
                    Frequently used admin actions
                </p>

            </div>

            <div class="card-body">

                <a
                    href="products/index.php"
                    class="quick-action mb-3">

                    <div class="quick-action-icon">

                        <span class="material-symbols-rounded">
                            inventory_2
                        </span>

                    </div>

                    <div>

                        <h6 class="mb-1">
                            Manage Products
                        </h6>

                        <p class="text-xs text-secondary mb-0">
                            Add or manage store products
                        </p>

                    </div>

                </a>

                <a
                    href="categories/index.php"
                    class="quick-action mb-3">

                    <div class="quick-action-icon">

                        <span class="material-symbols-rounded">
                            category
                        </span>

                    </div>

                    <div>

                        <h6 class="mb-1">
                            Manage Categories
                        </h6>

                        <p class="text-xs text-secondary mb-0">
                            Organize your product categories
                        </p>

                    </div>

                </a>

                <a
                    href="orders/index.php"
                    class="quick-action mb-3">

                    <div class="quick-action-icon">

                        <span class="material-symbols-rounded">
                            shopping_cart
                        </span>

                    </div>

                    <div>

                        <h6 class="mb-1">
                            View Orders
                        </h6>

                        <p class="text-xs text-secondary mb-0">
                            Manage customer orders
                        </p>

                    </div>

                </a>

                <a
                    href="reports.php"
                    class="quick-action">

                    <div class="quick-action-icon">

                        <span class="material-symbols-rounded">
                            analytics
                        </span>

                    </div>

                    <div>

                        <h6 class="mb-1">
                            View Reports
                        </h6>

                        <p class="text-xs text-secondary mb-0">
                            Check store performance
                        </p>

                    </div>

                </a>

            </div>

        </div>

    </div>

</div>

<!-- ========================================================= -->
<!-- Recent Orders -->
<!-- ========================================================= -->

<div class="row">

    <div class="col-lg-8 mb-4">

        <div class="card">

            <div class="card-header pb-0">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h6 class="mb-1">
                            Recent Orders
                        </h6>

                        <p class="text-sm text-secondary mb-0">
                            Latest customer orders
                        </p>

                    </div>

                    <a
                        href="orders/index.php"
                        class="text-sm font-weight-bold text-dark">

                        View All

                    </a>

                </div>

            </div>

            <div class="card-body px-0 pb-2">

                <div class="table-responsive">

                    <table class="table align-items-center mb-0">

                        <thead>

                            <tr>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-4">
                                    Order
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Status
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Amount
                                </th>

                                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                    Date
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (!empty($recentOrders)): ?>

                            <?php foreach ($recentOrders as $order): ?>

                                <?php

                                $orderStatus = strtolower(
                                    trim($order['order_status'] ?? '')
                                );

                                $statusClass = '';

                                if ($orderStatus === 'processing') {

                                    $statusClass = 'order-status-processing';

                                } elseif ($orderStatus === 'shipped') {

                                    $statusClass = 'order-status-shipped';

                                } elseif ($orderStatus === 'delivered') {

                                    $statusClass = 'order-status-delivered';

                                } elseif ($orderStatus === 'cancelled') {

                                    $statusClass = 'order-status-cancelled';

                                }

                                ?>

                                <tr>

                                    <td class="ps-4">

                                        <span class="order-id text-sm">

                                            #<?= htmlspecialchars(
                                                $order['order_number']
                                            ) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <span class="order-status-badge <?= $statusClass ?>">

                                            <?= htmlspecialchars(
                                                ucfirst($orderStatus ?: 'Unknown')
                                            ) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <span class="text-sm font-weight-bold">

                                            Rs.
                                            <?= number_format(
                                                (float) $order['total_amount'],
                                                0
                                            ) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <span class="text-sm text-secondary">

                                            <?= !empty($order['created_at'])

                                                ? date(
                                                    'd M Y, h:i A',
                                                    strtotime(
                                                        $order['created_at']
                                                    )
                                                )

                                                : 'N/A'
                                            ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="4"
                                    class="text-center py-4">

                                    <span
                                        class="material-symbols-rounded text-secondary mb-2"
                                        style="font-size:32px;">

                                        shopping_cart

                                    </span>

                                    <p class="text-sm text-secondary mb-0">

                                        No orders found yet.

                                    </p>

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

    <!-- Store Snapshot -->

    <div class="col-lg-4 mb-4">

        <div class="card h-100">

            <div class="card-header pb-0">

                <h6 class="mb-1">
                    Store Snapshot
                </h6>

                <p class="text-sm text-secondary mb-0">
                    Current store data
                </p>

            </div>

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">

                    <div class="d-flex align-items-center">

                        <span
                            class="material-symbols-rounded me-3 text-dark">

                            inventory_2

                        </span>

                        <span class="text-sm">
                            Products
                        </span>

                    </div>

                    <span class="font-weight-bold">

                        <?= number_format($totalProducts) ?>

                    </span>

                </div>

                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">

                    <div class="d-flex align-items-center">

                        <span
                            class="material-symbols-rounded me-3 text-dark">

                            category

                        </span>

                        <span class="text-sm">
                            Categories
                        </span>

                    </div>

                    <span class="font-weight-bold">

                        <?= number_format($totalCategories) ?>

                    </span>

                </div>

                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">

                    <div class="d-flex align-items-center">

                        <span
                            class="material-symbols-rounded me-3 text-dark">

                            group

                        </span>

                        <span class="text-sm">
                            Customers
                        </span>

                    </div>

                    <span class="font-weight-bold">

                        <?= number_format($totalCustomers) ?>

                    </span>

                </div>

                <div class="d-flex justify-content-between align-items-center py-3">

                    <div class="d-flex align-items-center">

                        <span
                            class="material-symbols-rounded me-3 text-dark">

                            payments

                        </span>

                        <span class="text-sm">
                            Monthly Sales
                        </span>

                    </div>

                    <span class="font-weight-bold">

                        Rs.
                        <?= number_format(
                            $thisMonthSales,
                            0
                        ) ?>

                    </span>

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

<script src="assets/js/plugins/chartjs.min.js"></script>

<script>

const ordersLabels =
    <?= json_encode($monthlyLabels) ?>;

const ordersData =
    <?= json_encode($monthlyOrders) ?>;

const ordersCanvas =
    document.getElementById('ordersChart');

if (ordersCanvas) {

    new Chart(
        ordersCanvas,
        {
            type: 'line',

            data: {

                labels: ordersLabels,

                datasets: [

                    {

                        label: 'Orders',

                        data: ordersData,

                        tension: 0.4,

                        borderWidth: 2,

                        pointRadius: 4,

                        pointBorderWidth: 2,

                        fill: false,

                        borderColor: '#344767',

                        pointBackgroundColor: '#344767'

                    }

                ]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {
                        display: false
                    }

                },

                interaction: {

                    intersect: false,

                    mode: 'index'

                },

                scales: {

                    y: {

                        beginAtZero: true,

                        grid: {

                            drawBorder: false,

                            color: '#e9ecef'

                        },

                        ticks: {

                            precision: 0,

                            color: '#737373'

                        }

                    },

                    x: {

                        grid: {

                            display: false

                        },

                        ticks: {

                            color: '#737373'

                        }

                    }

                }

            }

        }
    );

}

</script>

<script>

var win =
    navigator.platform.indexOf('Win') > -1;

if (
    win &&
    document.querySelector('#sidenav-scrollbar')
) {

    var options = {
        damping: '0.5'
    };

    Scrollbar.init(
        document.querySelector('#sidenav-scrollbar'),
        options
    );

}

</script>

<script src="assets/js/material-dashboard.min.js?v=3.2.0"></script>

</body>

</html>

