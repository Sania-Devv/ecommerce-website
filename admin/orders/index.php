<?php

$pageTitle = "Orders";

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
| Fetch Orders
|--------------------------------------------------------------------------
*/

$orders = [];

$stmt = $mysqli->prepare(
    "SELECT
        o.id,
        o.order_number,
        o.total_amount,
        o.payment_method,
        o.payment_status,
        o.order_status,
        o.created_at,
        u.name AS customer_name
     FROM orders o
     INNER JOIN users u
        ON o.user_id = u.id
     ORDER BY o.id DESC"
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link
        rel="apple-touch-icon"
        sizes="76x76"
        href="../assets/img/apple-icon.png"
    >

    <link
        rel="icon"
        type="image/png"
        href="../assets/img/favicon.png"
    >

    <title>Orders - ClothWear Admin</title>

    <link
        rel="stylesheet"
        type="text/css"
        href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900"
    />

    <link
        href="../assets/css/nucleo-icons.css"
        rel="stylesheet"
    />

    <link
        href="../assets/css/nucleo-svg.css"
        rel="stylesheet"
    />

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0"
    />

    <link
        id="pagestyle"
        href="../assets/css/material-dashboard.css?v=3.2.0"
        rel="stylesheet"
    />

</head>


<body class="g-sidenav-show bg-gray-100">

    <!-- Admin Header -->
    <?php require_once '../../includes/admin-header.php'; ?>

    <!-- Admin Sidebar -->
    <?php require_once '../../includes/admin-sidenavbar.php'; ?>


    <!-- Main Content -->
    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">

        <div class="container-fluid py-4">

            <!-- Page Heading -->
            <div class="row">

                <div class="col-12">

                    <div class="d-flex justify-content-between align-items-center mb-4">

                        <div>

                            <h4 class="font-weight-bolder mb-0">
                                Orders Management
                            </h4>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Orders Table -->
            <div class="row">

                <div class="col-12">

                    <div class="card mb-4">

                        <!-- Card Header -->
                        <div class="card-header pb-0">

                            <div class="d-flex justify-content-between align-items-center">

                                <h6>
                                    Orders
                                </h6>

                                <span class="text-sm text-secondary">
                                    Manage customer orders
                                </span>

                            </div>

                        </div>


                        <!-- Table -->
                        <div class="card-body px-0 pt-0 pb-2">

                            <div class="table-responsive p-0">

                                <table class="table align-items-center mb-0">

                                    <thead>

                                        <tr>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Order
                                            </th>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                                                Customer
                                            </th>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Total
                                            </th>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Payment
                                            </th>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Order Status
                                            </th>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Date
                                            </th>

                                            <th class="text-secondary opacity-7">
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php if (empty($orders)): ?>

                                            <tr>

                                                <td
                                                    colspan="7"
                                                    class="text-center py-5"
                                                >

                                                    <p class="text-sm text-secondary mb-0">
                                                        No orders found.
                                                    </p>

                                                </td>

                                            </tr>

                                        <?php else: ?>

                                            <?php foreach ($orders as $order): ?>

                                                <?php

                                                /*
                                                |--------------------------------------------------------------------------
                                                | Payment Badge
                                                |--------------------------------------------------------------------------
                                                */

                                                $paymentBadge = 'bg-gradient-warning';

                                                if ($order['payment_status'] === 'completed') {
                                                    $paymentBadge = 'bg-gradient-success';
                                                } elseif ($order['payment_status'] === 'failed') {
                                                    $paymentBadge = 'bg-gradient-danger';
                                                }


                                                /*
                                                |--------------------------------------------------------------------------
                                                | Order Status Badge
                                                |--------------------------------------------------------------------------
                                                */

                                                $orderBadge = 'bg-gradient-info';

                                                if ($order['order_status'] === 'delivered') {
                                                    $orderBadge = 'bg-gradient-success';

                                                } elseif ($order['order_status'] === 'cancelled') {
                                                    $orderBadge = 'bg-gradient-danger';

                                                } elseif ($order['order_status'] === 'shipped') {
                                                    $orderBadge = 'bg-gradient-primary';
                                                }

                                                ?>

                                                <tr>

                                                    <!-- Order -->
                                                    <td>

                                                        <div class="d-flex px-3 py-1">

                                                            <div class="d-flex flex-column justify-content-center">

                                                                <h6 class="mb-0 text-sm">

                                                                    #
                                                                    <?php echo htmlspecialchars(
                                                                        $order['order_number']
                                                                    ); ?>

                                                                </h6>

                                                            </div>

                                                        </div>

                                                    </td>


                                                    <!-- Customer -->
                                                    <td>

                                                        <p class="text-sm font-weight-bold mb-0">

                                                            <?php echo htmlspecialchars(
                                                                $order['customer_name']
                                                            ); ?>

                                                        </p>

                                                    </td>


                                                    <!-- Total -->
                                                    <td>

                                                        <p class="text-sm font-weight-bold mb-0">

                                                            Rs.
                                                            <?php echo number_format(
                                                                (float) $order['total_amount'],
                                                                2
                                                            ); ?>

                                                        </p>

                                                    </td>


                                                    <!-- Payment -->
                                                    <td>

                                                        <span
                                                            class="badge badge-sm <?php echo $paymentBadge; ?>"
                                                        >

                                                            <?php echo htmlspecialchars(
                                                                ucfirst(
                                                                    $order['payment_status']
                                                                )
                                                            ); ?>

                                                        </span>

                                                        <small class="d-block text-xs text-secondary mt-1">

                                                            <?php echo strtoupper(
                                                                $order['payment_method']
                                                            ); ?>

                                                        </small>

                                                    </td>


                                                    <!-- Order Status -->
                                                    <td>

                                                        <span
                                                            class="badge badge-sm <?php echo $orderBadge; ?>"
                                                        >

                                                            <?php echo htmlspecialchars(
                                                                ucfirst(
                                                                    $order['order_status']
                                                                )
                                                            ); ?>

                                                        </span>

                                                    </td>


                                                    <!-- Date -->
                                                    <td>

                                                        <span class="text-secondary text-xs font-weight-bold">

                                                            <?php echo date(
                                                                'd M Y',
                                                                strtotime($order['created_at'])
                                                            ); ?>

                                                        </span>

                                                    </td>


                                                    <!-- View -->
                                                    <td class="align-middle">

                                                        <a
                                                            href="detail.php?id=<?php echo (int) $order['id']; ?>"
                                                            class="btn btn-link text-secondary mb-0"
                                                            title="View Order"
                                                        >

                                                            <i class="fa fa-eye text-xs"></i>

                                                        </a>

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        <?php endif; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Admin Footer -->
            <?php require_once '../../includes/admin-footer.php'; ?>

        </div>

    </main>


    <!-- Popper -->
    <script src="../assets/js/core/popper.min.js"></script>

    <!-- Bootstrap -->
    <script src="../assets/js/core/bootstrap.min.js"></script>

    <!-- Perfect Scrollbar -->
    <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>

    <!-- Smooth Scrollbar -->
    <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>

    <!-- Material Dashboard -->
    <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>

</body>

</html>