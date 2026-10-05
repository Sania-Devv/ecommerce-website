
<?php

require_once __DIR__ . '/../config/database.php';

$name = "Admin";
$email = "admin@ecommerce.com";
$password = "Admin@123";

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$role = "admin";
$isActive = 1;

$stmt = $mysqli->prepare(
    "INSERT INTO users (name, email, password, role, is_active)
     VALUES (?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "ssssi",
    $name,
    $email,
    $hashedPassword,
    $role,
    $isActive
);

if ($stmt->execute()) {
    echo "Admin created successfully!";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();

