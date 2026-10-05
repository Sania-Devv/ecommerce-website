<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'];

\Stripe\Stripe::setApiKey($stripeSecretKey);

?>