<?php

$pageTitle = "Users";

require_once __DIR__ . '/../../core/Sessions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth($mysqli);

/*
|--------------------------------------------------------------------------
| Admin Guard
|--------------------------------------------------------------------------
*/

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: ../login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Delete User
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {

    $userId = isset($_POST['user_id'])
        ? (int) $_POST['user_id']
        : 0;

    if ($userId <= 0) {
        header('Location: index.php');
        exit;
    }

    /*
    | Do not allow admin account deletion
    */

    $checkStmt = $mysqli->prepare("
        SELECT role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();

    $checkResult = $checkStmt->get_result();
    $userToDelete = $checkResult->fetch_assoc();

    $checkStmt->close();

    if (!$userToDelete) {
        header('Location: index.php');
        exit;
    }

    if ($userToDelete['role'] === 'admin') {
        header('Location: index.php');
        exit;
    }


    /*
    | Delete customer
    */

    $deleteStmt = $mysqli->prepare("
        DELETE FROM users
        WHERE id = ?
          AND role = 'customer'
    ");

    $deleteStmt->bind_param("i", $userId);
    $deleteStmt->execute();

    $deleteStmt->close();

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Fetch Users
|--------------------------------------------------------------------------
*/

$users = [];

$userQuery = "
    SELECT
        id,
        name,
        email,
        role,
        is_active,
        created_at
    FROM users
    ORDER BY id DESC
";

$userResult = $mysqli->query($userQuery);

if ($userResult) {

    while ($row = $userResult->fetch_assoc()) {
        $users[] = $row;
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
        href="../assets/img/apple-icon.png"
    >

    <link
        rel="icon"
        type="image/png"
        href="../assets/img/favicon.png"
    >

    <title>
        Users - ClothWear Admin
    </title>


    <!-- Fonts -->

    <link
        rel="stylesheet"
        type="text/css"
        href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900"
    />


    <!-- Nucleo Icons -->

    <link
        href="../assets/css/nucleo-icons.css"
        rel="stylesheet"
    />

    <link
        href="../assets/css/nucleo-svg.css"
        rel="stylesheet"
    />


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- Material Icons -->

    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0"
    />


    <!-- Material Dashboard -->

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
                                Users Management
                            </h4>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Users Table -->

            <div class="row">

                <div class="col-12">

                    <div class="card mb-4">


                        <!-- Card Header -->

                        <div class="card-header pb-0">

                            <div class="d-flex justify-content-between align-items-center">

                                <h6>
                                    Users
                                </h6>

                                <span class="text-sm text-secondary">
                                    Manage registered users
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
                                                User
                                            </th>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                                                Email
                                            </th>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Role
                                            </th>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Status
                                            </th>

                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Joined
                                            </th>

                                            <th class="text-secondary opacity-7">
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>


                                        <?php if (!empty($users)): ?>


                                            <?php foreach ($users as $user): ?>


                                                <tr>


                                                    <!-- User -->

                                                    <td>

                                                        <div class="d-flex px-3 py-1">

                                                            <div class="d-flex flex-column justify-content-center">

                                                                <h6 class="mb-0 text-sm">

                                                                    <?php
                                                                    echo htmlspecialchars(
                                                                        $user['name']
                                                                    );
                                                                    ?>

                                                                </h6>

                                                            </div>

                                                        </div>

                                                    </td>


                                                    <!-- Email -->

                                                    <td>

                                                        <p class="text-sm font-weight-bold mb-0">

                                                            <?php
                                                            echo htmlspecialchars(
                                                                $user['email']
                                                            );
                                                            ?>

                                                        </p>

                                                    </td>


                                                    <!-- Role -->

                                                    <td>

                                                        <?php if ($user['role'] === 'admin'): ?>

                                                            <span class="badge badge-sm bg-gradient-dark">
                                                                Admin
                                                            </span>

                                                        <?php else: ?>

                                                            <span class="badge badge-sm bg-gradient-info">
                                                                Customer
                                                            </span>

                                                        <?php endif; ?>

                                                    </td>


                                                    <!-- Status -->

                                                    <td>

                                                        <?php if ((int) $user['is_active'] === 1): ?>

                                                            <span class="badge badge-sm bg-gradient-success">
                                                                Active
                                                            </span>

                                                        <?php else: ?>

                                                            <span class="badge badge-sm bg-gradient-secondary">
                                                                Inactive
                                                            </span>

                                                        <?php endif; ?>

                                                    </td>


                                                    <!-- Joined -->

                                                    <td>

                                                        <span class="text-secondary text-xs font-weight-bold">

                                                            <?php
                                                            echo date(
                                                                'd M Y',
                                                                strtotime($user['created_at'])
                                                            );
                                                            ?>

                                                        </span>

                                                    </td>


                                                    <!-- Action -->

                                                    <td class="align-middle">


                                                        <?php if ($user['role'] !== 'admin'): ?>

                                                            <button
                                                                type="button"
                                                                class="btn btn-link text-secondary mb-0"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#deleteUserModal"
                                                                data-user-id="<?php echo (int) $user['id']; ?>"
                                                                data-user-name="<?php echo htmlspecialchars($user['name']); ?>"
                                                            >

                                                                <i class="fa fa-trash text-xs"></i>

                                                            </button>

                                                        <?php else: ?>

                                                            <span class="text-secondary text-xs">
                                                                Protected
                                                            </span>

                                                        <?php endif; ?>


                                                    </td>


                                                </tr>


                                            <?php endforeach; ?>


                                        <?php else: ?>


                                            <tr>

                                                <td
                                                    colspan="6"
                                                    class="text-center py-4"
                                                >

                                                    <span class="text-secondary">
                                                        No users found.
                                                    </span>

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


        </div>


        <!-- Admin Footer -->

        <?php require_once '../../includes/admin-footer.php'; ?>


    </main>


    <!-- Delete User Modal -->

    <div
        class="modal fade"
        id="deleteUserModal"
        tabindex="-1"
        aria-labelledby="deleteUserModalLabel"
        aria-hidden="true"
    >

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">


                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="deleteUserModalLabel"
                    >
                        Delete User
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    >
                    </button>

                </div>


                <div class="modal-body">

                    Are you sure you want to delete
                    <strong id="deleteUserName"></strong>?

                </div>


                <div class="modal-footer">


                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>


                    <form
                        method="POST"
                        action="index.php"
                    >

                        <input
                            type="hidden"
                            name="user_id"
                            id="deleteUserId"
                            value=""
                        >

                        <button
                            type="submit"
                            name="delete_user"
                            value="1"
                            class="btn btn-danger"
                        >
                            Delete
                        </button>

                    </form>


                </div>

            </div>

        </div>

    </div>


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


    <!-- Delete User Modal Script -->

    <script>

        const deleteUserModal = document.getElementById('deleteUserModal');

        deleteUserModal.addEventListener('show.bs.modal', function (event) {

            const button = event.relatedTarget;

            const userId = button.getAttribute('data-user-id');

            const userName = button.getAttribute('data-user-name');

            document.getElementById('deleteUserId').value = userId;

            document.getElementById('deleteUserName').textContent = userName;

        });

    </script>


</body>

</html>