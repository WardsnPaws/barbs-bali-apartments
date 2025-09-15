<?php
// api/process-extras-payment.php

require_once __DIR__ . '/../includes/core.php';
require __DIR__ . '/../vendor/autoload.php';

use Square\SquareClient;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;

header('Content-Type: application/json');

session_start();

$data = json_decode(file_get_contents("php://input"), true);

// Basic validation
if (!isset($data['token'], $data['amount'], $data['reservation'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit;
}

if (!isset($_SESSION['extras_payment'])) {
    echo json_encode(['success' => false, 'message' => 'Payment session expired.']);
    exit;
}

$token = $data['token'];
$amount = floatval($data['amount']);
$reservation = $data['reservation'];
$paymentInfo = $_SESSION['extras_payment'];

// Verify reservation matches
if ($paymentInfo['reservation_number'] !== $reservation) {
    echo json_encode(['success' => false, 'message' => 'Invalid reservation.']);
    exit;
}

// Verify amount matches what we expect
if (abs($amount - $paymentInfo['balance_owed']) > 0.01) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment amount.']);
    exit;
}

if ($amount < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment amount.']);
    exit;
}

// Get booking from database
$pdo = getPDO();
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE reservation_number = :res LIMIT 1");
$stmt->execute([':res' => $reservation]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

// Process payment with Square
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
    $paymentRequest->setNote("Extras payment for reservation {$reservation}");

    $paymentsApi = $client->getPaymentsApi();
    $response = $paymentsApi->createPayment($paymentRequest);

    if ($response->isSuccess()) {
        // Record the payment in the database
        $insertPayment = $pdo->prepare("INSERT INTO payments (booking_id, amount, method, note, paid_at) VALUES (?, ?, 'square', ?, NOW())");
        $insertPayment->execute([
            $booking['id'],
            $amount,
            "Extras payment - Balance due"
        ]);

        // Clear the payment session
        unset($_SESSION['extras_payment']);

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
    error_log("Extras payment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Payment processing error. Please try again.']);
}