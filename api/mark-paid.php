<?php
// api/mark-paid.php - Updated for PayPal payments with email

session_start();
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/complete-booking.php';

header('Content-Type: application/json');

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['payment_method']) || $input['payment_method'] !== 'paypal') {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
    exit;
}

// Check session data
if (!isset($_SESSION['booking']) || !isset($_SESSION['amount_due'])) {
    echo json_encode(['success' => false, 'message' => 'Booking session expired']);
    exit;
}

$booking = $_SESSION['booking'];
$amountDue = $_SESSION['amount_due'];

try {
    // PayPal payment details (you might want to capture more details from the frontend)
    $paymentDetails = [
        'method' => 'paypal',
        'reference' => $input['paypal_order_id'] ?? uniqid('paypal-'),
        'amount' => $amountDue,
        'currency' => 'AUD',
        'status' => 'completed'
    ];
    
    // Complete the booking with email
    $bookingResult = completeBookingWithEmail($paymentDetails);
    
    if ($bookingResult['success']) {
        error_log("PayPal booking completed successfully: " . $bookingResult['reservation_number']);
        
        echo json_encode([
            'success' => true,
            'message' => 'PayPal payment processed and booking confirmed',
            'reservation_number' => $bookingResult['reservation_number'],
            'email_sent' => $bookingResult['email_sent']
        ]);
    } else {
        error_log("PayPal payment successful but booking save failed: " . $bookingResult['message']);
        echo json_encode([
            'success' => false,
            'message' => 'Payment processed but booking save failed. Please contact support.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("PayPal mark paid exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Booking completion error: ' . $e->getMessage()
    ]);
}
?>