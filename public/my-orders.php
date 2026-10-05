<?php

require_once __DIR__ . '/../core/Sessions.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmt = $mysqli->prepare("
    SELECT
        id,
        order_number,
        total_amount,
        payment_method,
        payment_status,
        order_status,
        created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY id DESC
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();

$pageTitle = "My Orders";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>My Orders</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
<style>
    /* Page Header */
    .page-header {
        padding: 60px 0;
    }

    .page-content {
        background: #f7f7f7;
        padding: 40px 0 70px;
    }

    /* Card */
    .card {
        border: none !important;
        border-radius: 10px !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06) !important;
    }

    .card-body {
        padding: 28px !important;
    }

    .card .title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 1px solid #eee;
        color: #222;
    }

    /* Empty state */
    .text-center.py-5 h3 {
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
    }

    .text-center.py-5 .btn-primary {
        margin-top: 10px;
        border-radius: 25px;
        padding: 10px 28px;
        font-weight: 500;
    }

    /* Orders table */
    .table-orders thead th {
        background-color: #f5f5f5;
        color: #888;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
        border: none !important;
        padding: 14px 16px;
    }

    .table-orders tbody td {
        vertical-align: middle;
        padding: 16px;
        border-top: 1px solid #f0f0f0 !important;
        color: #444;
    }

    .table-orders tbody tr:hover {
        background-color: #fafafa;
    }

    .table-orders td strong {
        color: #222;
        font-weight: 500;
    }

    .table-responsive {
        border-radius: 8px;
        overflow: hidden;
    }

    /* Badges */
    .badge {
        padding: 6px 16px !important;
        border-radius: 20px !important;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }

    .badge-success   { background-color: #e5f6ea !important; color: #1d9c4b !important; }
    .badge-warning   { background-color: #fff5e0 !important; color: #b8860b !important; }
    .badge-info      { background-color: #e3f3fb !important; color: #1a7bad !important; }
    .badge-danger    { background-color: #fce8e8 !important; color: #d93838 !important; }
    .badge-secondary { background-color: #eee !important; color: #777 !important; }

    /* View Details button */
    .table-orders .btn-outline-primary.btn-sm {
        border-radius: 20px;
        padding: 6px 18px;
        font-weight: 500;
        border-color: #cc9966;
        color: #cc9966;
        transition: all 0.25s ease;
        white-space: nowrap;
    }

    .table-orders .btn-outline-primary.btn-sm:hover {
        background-color: #cc9966;
        border-color: #cc9966;
        color: #fff;
    }

    @media (max-width: 767px) {
        .card-body {
            padding: 20px !important;
        }

        .page-header {
            padding: 40px 0;
        }

        .table-orders {
            min-width: 650px;
        }
    }
</style>
</head>

<body>

<?php require_once __DIR__ . '/../includes/header.php'; ?>


<main class="main">

    <!-- Page Header -->
    <div
       class="page-header text-center" style="background-image: url('assets/images/page-header-bg.jpg')"
    >
        <div class="container">

            <h1 class="page-title">
                My Orders
            </h1>

        </div>
    </div>


    <!-- Orders Section -->
    <div class="page-content">

        <div class="container">

            <div class="row">

                <div class="col-12">

                    <div class="card shadow-sm">

                        <div class="card-body">

                            <h2 class="title mb-4">
                                Order History
                            </h2>


                            <?php if (empty($orders)): ?>

                                <div class="text-center py-5">

                                    <h3>
                                        No Orders Yet
                                    </h3>

                                    <p class="text-muted">
                                        You haven't placed any orders yet.
                                    </p>

                                    <a
                                        href="products.php"
                                        class="btn btn-primary"
                                    >
                                        Start Shopping
                                    </a>

                                </div>

                            <?php else: ?>

                                <div class="table-responsive">

                                    <table class="table table-orders">

                                        <thead>

                                            <tr>

                                                <th>
                                                    Order
                                                </th>

                                                <th>
                                                    Date
                                                </th>

                                                <th>
                                                    Total
                                                </th>

                                                <th>
                                                    Payment
                                                </th>

                                                <th>
                                                    Status
                                                </th>

                                                <th>
                                                    Action
                                                </th>

                                            </tr>

                                        </thead>


                                        <tbody>

                                            <?php foreach ($orders as $order): ?>

                                                <tr>

                                                    <!-- Order Number -->
                                                    <td>

                                                        <strong>
                                                            <?php
                                                            echo htmlspecialchars(
                                                                $order['order_number']
                                                            );
                                                            ?>
                                                        </strong>

                                                    </td>


                                                    <!-- Date -->
                                                    <td>

                                                        <?php
                                                        echo date(
                                                            'd M Y',
                                                            strtotime($order['created_at'])
                                                        );
                                                        ?>

                                                    </td>


                                                    <!-- Total -->
                                                    <td>

                                                        <strong>
                                                            Rs.
                                                            <?php
                                                            echo number_format(
                                                                (float) $order['total_amount'],
                                                                2
                                                            );
                                                            ?>
                                                        </strong>

                                                    </td>


                                                    <!-- Payment -->
                                                    <td>

                                                        <?php
                                                        echo strtoupper(
                                                            htmlspecialchars(
                                                                $order['payment_method']
                                                            )
                                                        );
                                                        ?>

                                                    </td>


                                                    <!-- Status -->
                                                    <td>

                                                        <?php

                                                        $status = $order['order_status'];

                                                        if ($status === 'processing') {
                                                            $badgeClass = 'badge-warning';
                                                            $statusText = 'Processing';

                                                        } elseif ($status === 'shipped') {
                                                            $badgeClass = 'badge-info';
                                                            $statusText = 'Shipped';

                                                        } elseif ($status === 'delivered') {
                                                            $badgeClass = 'badge-success';
                                                            $statusText = 'Delivered';

                                                        } elseif ($status === 'cancelled') {
                                                            $badgeClass = 'badge-danger';
                                                            $statusText = 'Cancelled';

                                                        } else {
                                                            $badgeClass = 'badge-secondary';
                                                            $statusText = ucfirst($status);
                                                        }

                                                        ?>

                                                        <span
                                                            class="badge <?php echo $badgeClass; ?>"
                                                        >
                                                            <?php echo $statusText; ?>
                                                        </span>

                                                    </td>


                                                    <!-- Action -->
                                                    <td>

                                                        <a
                                                            href="order-detail.php?id=<?php echo (int) $order['id']; ?>"
                                                            class="btn btn-outline-primary btn-sm"
                                                        >
                                                            View Details
                                                        </a>

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        </tbody>

                                    </table>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</main>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>


<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>

</body>

</html>