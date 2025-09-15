<?php
// resend-confirmation.php

require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/email-utils.php';

$resNum = $_POST['reservation_number'] ?? '';

if (!$resNum) {
    die("Missing reservation number.");
}

$pdo = getPDO();

// Get booking
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE reservation_number = :res LIMIT 1");
$stmt->execute([':res' => $resNum]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    die("Booking not found.");
}

$bookingId = $booking['id'];
$total = $booking['total_price'];
$email = $booking['guest_email'];

// Sum payments
$paidStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE booking_id = ?");
$paidStmt->execute([$bookingId]);
$alreadyPaid = (float) $paidStmt->fetchColumn();
$balanceLeft = max($total - $alreadyPaid, 0);

// Send email
$placeholders = [
    'guestfirstname'      => $booking['guest_first_name'],
    'guestlastname'       => $booking['guest_last_name'],
    'reservationnumber'   => $resNum,
    'arrivaldatelong'     => date('l, j F Y', strtotime($booking['checkin_date'])),
    'departuredatelong'   => date('l, j F Y', strtotime($booking['checkout_date'])),
    'apartmentnumber'     => $booking['apartment_id'] == 1 ? '6205' : ($booking['apartment_id'] == 2 ? '6207' : 'Both'),
    'grandtotal'          => number_format($total, 2),
    'amountpaid'          => number_format($alreadyPaid, 2),
    'balance'             => number_format($balanceLeft, 2),
    'paymentlink_anyamount' => 'https://barbsbaliapartments.com/pay?res=' . urlencode($resNum)
];

$template = loadTemplate('booking-confirmation');
$body = replacePlaceholders($template, $placeholders);
sendEmailSMTP($email, 'Booking Confirmation – Barbs Bali Apartments', $body);

// Redirect back
header("Location: ../public/my-booking.php?res=" . urlencode($resNum));
exit;
