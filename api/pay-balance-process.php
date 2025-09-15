<?php
// pay-balance-process.php

require_once '../includes/core.php';
require 'vendor/autoload.php'; // Square SDK

use Square\SquareClient;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

// Basic validation
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

// Get booking
$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE reservation_number = :res LIMIT 1");
$stmt->execute([':res' => $reservation]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Reservation not found.']);
    exit;
}

// Verify the amount doesn't exceed balance owed
$paidStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE booking_id = ?");
$paidStmt->execute([$booking['id']]);
$alreadyPaid = (float) $paidStmt->fetchColumn();
$balanceLeft = max((float)$booking['total_price'] - $alreadyPaid, 0);

if ($amount > $balanceLeft + 0.01) { // Allow small rounding differences
    echo json_encode(['success' => false, 'message' => 'Amount exceeds balance owed.']);
    exit;
}

// Set up Square payment
try {
    $client = new SquareClient([
        'accessToken' => SQUARE_ACCESS_TOKEN,
        'environment' => 'sandbox'
    ]);

    $money = new Money();
    $money->setAmount((int) round($amount * 100)); // Convert to cents
    $money->setCurrency(strtoupper(CURRENCY));

    $paymentRequest = new CreatePaymentRequest(
        $token,
        uniqid(), // idempotency key
        $money
    );

    $paymentRequest->setAutocomplete(true);
    $paymentRequest->setLocationId(SQUARE_LOCATION_ID);
    $paymentRequest->setNote("Balance payment for reservation {$reservation}");

    $paymentsApi = $client->getPaymentsApi();
    $response = $paymentsApi->createPayment($paymentRequest);

    if ($response->isSuccess()) {
        // Record the payment in the database
        $insertPayment = $pdo->prepare("INSERT INTO payments (booking_id, amount, method, note, paid_at) VALUES (?, ?, 'square', ?, NOW())");
        $insertPayment->execute([
            $booking['id'],
            $amount,
            "Balance payment via Square"
        ]);

        echo json_encode(['success' => true, 'message' => 'Payment processed successfully.']);
    } else {
        $errors = $response->getErrors();
        $errorMessage = 'Payment declined.';
        if (!empty($errors)) {
            $errorMessage = $errors[0]->getDetail() ?? 'Payment declined.';
        }
        echo json_encode(['success' => false, 'message' => $errorMessage]);
    }

} catch (Exception $e) {
    error_log("Balance payment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Payment processing error. Please try again.']);
}