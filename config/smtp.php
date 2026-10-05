<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../vendor/autoload.php';

function sendOrderEmail($toEmail, $customerName, $orderNumber, $orderTotal ){
    $mail = new PHPMailer(true);
    try{
        // SMTp seetting 
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'clothwear829@gmail.com';
        $mail->Password   = 'aere mwgl nnuq kyxc';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // sender
        $mail->setFrom(
            'clothwear829@gmail.com',
            'ClothWear'
        );
        // receiver
        $mail->addAddress($toEmail,$customerName);
        // Email
        $mail->isHTML(true);
        $mail->Subject = 'ClothWear - Order Confirmation';
        $mail->Body = "
            <h2>Thank you for your order!</h2>

            <p>Hello " . htmlspecialchars($customerName) . ",</p>

            <p>Your ClothWear order has been successfully placed.</p>

            <p><strong>Order Number:</strong> "
            . htmlspecialchars($orderNumber) .
            "</p>

            <p><strong>Total Amount:</strong> Rs. "
            . number_format($orderTotal, 2) .
            "</p>

            <p>We will process your order shortly.</p>

            <p>Thank you for shopping with ClothWear.</p>
        ";

        $mail->send();

        return true;

    }
     catch (Exception $e) {

        return false;
    }

}

/*
|--------------------------------------------------------------------------
| Order Status Update Email
|--------------------------------------------------------------------------
*/

function sendOrderStatusEmail(
    $toEmail,
    $customerName,
    $orderNumber,
    $orderStatus
) {
    $mail = new PHPMailer(true);

    try {

        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'clothwear829@gmail.com';
        $mail->Password   = 'aere mwgl nnuq kyxc';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender
        $mail->setFrom(
            'clothwear829@gmail.com',
            'ClothWear'
        );

        // Receiver
        $mail->addAddress(
            $toEmail,
            $customerName
        );

        // Email
        $mail->isHTML(true);

        $mail->Subject = 'ClothWear - Order Status Update';

        $statusText = ucfirst($orderStatus);

        $mail->Body = "
            <h2>Order Status Updated</h2>

            <p>
                Hello " . htmlspecialchars($customerName) . ",
            </p>

            <p>
                Your ClothWear order status has been updated.
            </p>

            <p>
                <strong>Order Number:</strong>
                " . htmlspecialchars($orderNumber) . "
            </p>

            <p>
                <strong>New Order Status:</strong>
                " . htmlspecialchars($statusText) . "
            </p>

            <p>
                Thank you for shopping with ClothWear.
            </p>
        ";

        $mail->send();

        return true;

    }catch (Exception $e) {
    die("Status Email Error: " . $e->getMessage());
}
}
function sendAdminOrderCancellationEmail(
    $orderNumber,
    $customerName,
    $customerEmail
) {
    $mail = new PHPMailer(true);

    try {

        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'clothwear829@gmail.com';
        $mail->Password   = 'aere mwgl nnuq kyxc';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender
        $mail->setFrom(
            'clothwear829@gmail.com',
            'ClothWear'
        );

        // Admin receiver
        $mail->addAddress(
            'clothwear829@gmail.com',
            'ClothWear Admin'
        );

        // Email
        $mail->isHTML(true);

        $mail->Subject = 'ClothWear - Order Cancelled by Customer';

        $mail->Body = "
            <h2>Order Cancelled by Customer</h2>

            <p>
                A customer has cancelled an order from the ClothWear website.
            </p>

            <p>
                <strong>Order Number:</strong>
                " . htmlspecialchars($orderNumber) . "
            </p>

            <p>
                <strong>Customer Name:</strong>
                " . htmlspecialchars($customerName) . "
            </p>

            <p>
                <strong>Customer Email:</strong>
                " . htmlspecialchars($customerEmail) . "
            </p>

            <p>
                <strong>Status:</strong>
                Cancelled
            </p>

            <p>
                Please check the admin dashboard for order details.
            </p>
        ";

        $mail->send();

        return true;

    } catch (Exception $e) {

        return false;
    }
}
function sendAdminNewOrderEmail(
    $orderNumber,
    $customerName,
    $customerEmail,
    $orderTotal
) {
    $mail = new PHPMailer(true);

    try {

        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'clothwear829@gmail.com';
        $mail->Password   = 'aere mwgl nnuq kyxc';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender
        $mail->setFrom(
            'clothwear829@gmail.com',
            'ClothWear'
        );

        // Admin email
        $mail->addAddress(
            'clothwear829@gmail.com',
            'ClothWear Admin'
        );

        // Email
        $mail->isHTML(true);

        $mail->Subject = 'ClothWear - New Order Received';

        $mail->Body = "
            <h2>New Order Received</h2>

            <p>A new order has been placed on ClothWear.</p>

            <p>
                <strong>Order Number:</strong>
                " . htmlspecialchars($orderNumber) . "
            </p>

            <p>
                <strong>Customer Name:</strong>
                " . htmlspecialchars($customerName) . "
            </p>

            <p>
                <strong>Customer Email:</strong>
                " . htmlspecialchars($customerEmail) . "
            </p>

            <p>
                <strong>Order Total:</strong>
                Rs. " . number_format($orderTotal, 2) . "
            </p>

            <p>Please check the admin dashboard for complete order details.</p>
        ";

        $mail->send();

        return true;

    } catch (Exception $e) {

        return false;
    }
}