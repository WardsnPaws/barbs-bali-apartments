<?php
// pay-balance-process.php

require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/email-utils.php';
require __DIR__ . '/../vendor/autoload.php'; // Square SDK

use Square\SquareClient;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['token'], $data['amount'], $data['reservation'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit;
}

$token = $data['token'];
$amount = floatval($data['amount']);
$reservation = $data['reservation'];

if ($amount < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount.']);
    exit;
}

// Look up booking
$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE reservation_number = :res LIMIT 1");
$stmt->execute([':res' => $reservation]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Reservation not found.']);
    exit;
}

$bookingId = $booking['id'];
$guestEmail = $booking['guest_email'];
$guestName = $booking['guest_first_name'] . ' ' . $booking['guest_last_name'];
$total = $booking['total_price'];

// Sum all past payments
$paidStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE booking_id = ?");
$paidStmt->execute([$bookingId]);
$alreadyPaid = (float) $paidStmt->fetchColumn();
$newTotalPaid = $alreadyPaid + $amount;
$balanceLeft = max($total - $newTotalPaid, 0);

// Process Square payment
try {
    $client = new SquareClient([
        'accessToken' => SQUARE_ACCESS_TOKEN,
        'environment' => 'sandbox'
    ]);

    $money = new Money();
    $money->setAmount((int) round($amount * 100));
    $money->setCurrency(strtoupper(CURRENCY));

    $paymentRequest = new CreatePaymentRequest(
        $token,
        uniqid(),
        $money
    );

    $paymentRequest->setAutocomplete(true);
    $paymentRequest->setLocationId(SQUARE_LOCATION_ID);
    $paymentRequest->setNote("Balance payment for {$reservation}");

    $paymentsApi = $client->getPaymentsApi();
    $response = $paymentsApi->createPayment($paymentRequest);

    if ($response->isSuccess()) {
        // Log payment
        $log = $pdo->prepare("INSERT INTO payments (booking_id, amount, note) VALUES (?, ?, ?)");
        $log->execute([$bookingId, $amount, 'Balance payment via Square']);

        // Send confirmation email
        $placeholders = [
            'guestfirstname'      => $booking['guest_first_name'],
            'guestlastname'       => $booking['guest_last_name'],
            'reservationnumber'   => $reservation,
            'arrivaldatelong'     => date('l, j F Y', strtotime($booking['checkin_date'])),
            'departuredatelong'   => date('l, j F Y', strtotime($booking['checkout_date'])),
            'apartmentnumber'     => $booking['apartment_id'] == 1 ? '6205' : ($booking['apartment_id'] == 2 ? '6207' : 'Both'),
            'grandtotal'          => number_format($total, 2),
            'amountpaid'          => number_format($newTotalPaid, 2),
            'balance'             => number_format($balanceLeft, 2),
            'paymentlink_anyamount' => 'https://barbsbaliapartments.com/pay?res=' . $reservation
        ];

        $template = loadTemplate('booking-confirmation');
        $body = replacePlaceholders($template, $placeholders);
        sendEmailSMTP($guestEmail, 'Payment Received – Barbs Bali Apartments', $body);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment failed.']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
