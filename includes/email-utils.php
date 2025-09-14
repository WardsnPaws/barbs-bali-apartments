<?php
// email-utils.php

require_once 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// You must install PHPMailer via Composer first
require 'vendor/autoload.php';

function loadTemplate($templateFile) {
    $file = __DIR__ . "/email-templates/$templateFile.html";
    return file_exists($file) ? file_get_contents($file) : '';
}

function replacePlaceholders($template, $placeholders) {
    foreach ($placeholders as $key => $value) {
        $template = str_replace("{{{$key}}}", $value, $template);
    }
    return $template;
}

function sendEmailSMTP($to, $subject, $htmlBody) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

function sendConfirmationEmail($toEmail, $resNum, $booking, $total, $paid, $emailId) {
    $template = loadTemplate('booking-confirmation');

    $pdo = getPDO();

    // Fetch extras linked to booking
    $extrasStmt = $pdo->prepare("
        SELECT e.name FROM booking_extras be
        JOIN extras e ON be.extra_id = e.id
        WHERE be.booking_id IN (
            SELECT id FROM bookings WHERE reservation_number = ?
        )
    ");
    $extrasStmt->execute([$resNum]);
    $extras = $extrasStmt->fetchAll(PDO::FETCH_COLUMN);

    $extrasList = '';
    if ($extras) {
        foreach ($extras as $ex) {
            $extrasList .= "<li>" . htmlspecialchars($ex) . "</li>";
        }
    } else {
        $extrasList = "<li>No extras selected</li>";
    }

    $placeholders = [
        'guestfirstname'      => $booking['guest_first_name'],
        'guestlastname'       => $booking['guest_last_name'],
        'reservationnumber'   => $resNum,
        'arrivaldatelong'     => date('l, j F Y', strtotime($booking['checkin_date'])),
        'departuredatelong'   => date('l, j F Y', strtotime($booking['checkout_date'])),
        'apartmentnumber'     => $booking['apartment_id'] == 1 ? '6205' : ($booking['apartment_id'] == 2 ? '6207' : 'Both'),
        'grandtotal'          => number_format($total, 2),
        'amountpaid'          => number_format($paid, 2),
        'balance'             => number_format($total - $paid, 2),
        'paymentlink_anyamount' => 'https://barbsbaliapartments.com/pay',
        'includedextras'      => $extrasList
    ];

    $htmlBody = replacePlaceholders($template, $placeholders);
    sendEmailSMTP($toEmail, "Booking Confirmation – Barbs Bali Apartments", $htmlBody);
}
