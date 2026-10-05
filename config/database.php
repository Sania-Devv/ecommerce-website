<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "ecommerce";

$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");