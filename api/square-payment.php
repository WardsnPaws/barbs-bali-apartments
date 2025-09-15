<?php
// square-payment.php - Corrected for actual database schema
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/price-calc.php';
require __DIR__ . '/../vendor/autoload.php';

use Square\SquareClient;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;

header('Content-Type: application/json');

session_start();

// Debug initial state
error_log("Square payment - Initial session: " . json_encode($_SESSION));

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['token']) || !isset($_SESSION['booking'])) {
    error_log("Square payment - Missing session or payment token");
    echo json_encode(['success' => false, 'message' => 'Missing session or payment token']);
    exit;
}

$booking = $_SESSION['booking'];

// Debug booking data
error_log("Square payment - Booking data: " . json_encode($booking));

// Ensure we have all required booking data
$required = ['guest_first_name', 'guest_last_name', 'guest_email', 'apartment_id', 'checkin_date', 'checkout_date'];
foreach ($required as $field) {
    if (empty($booking[$field])) {
        error_log("Square payment - Missing booking field: $field");
        echo json_encode(['success' => false, 'message' => "Missing booking data: $field"]);
        exit;
    }
}

// Calculate pricing using the same method as other parts of the system
$apartmentId = (int)$booking['apartment_id'];
error_log("Square payment - Apartment ID: $apartmentId");

if ($apartmentId === 3) {
    // Combined booking - calculate each apartment separately then sum
    $booking6205 = $booking;
    $booking6205['apartment_id'] = 1;
    unset($booking6205['both_apartments'], $booking6205['apartment_ids']);

    $booking6207 = $booking;
    $booking6207['apartment_id'] = 2;
    unset($booking6207['both_apartments'], $booking6207['apartment_ids']);

    error_log("Square payment - Calculating 6205: " . json_encode($booking6205));
    error_log("Square payment - Calculating 6207: " . json_encode($booking6207));

    $price6205 = calculateBookingTotal($booking6205);
    $price6207 = calculateBookingTotal($booking6207);

    $total = (float)$price6205['total'] + (float)$price6207['total'];
    $nights = $price6205['nights']; // Should be same for both
    
    error_log("Square payment - 6205 total: {$price6205['total']}, 6207 total: {$price6207['total']}, combined: $total");
} else {
    // Single apartment
    error_log("Square payment - Calculating single apartment: " . json_encode($booking));
    $priceInfo = calculateBookingTotal($booking);
    $total = (float)$priceInfo['total'];
    $nights = $priceInfo['nights'];
    
    error_log("Square payment - Single apartment total: $total");
}

// Calculate deposit vs full payment
$checkinDate = new DateTime($booking['checkin_date']);
$today = new DateTime();
$daysUntil = $today->diff($checkinDate)->days;
$depositOnly = $daysUntil > DEPOSIT_THRESHOLD_DAYS;
$amountToCharge = $depositOnly ? round($total * DEPOSIT_RATE, 2) : $total;

error_log("Square payment - Days until checkin: $daysUntil, deposit only: " . ($depositOnly ? 'yes' : 'no'));
error_log("Square payment - Total: $total, amount to charge: $amountToCharge");

// Validate amount
if ($amountToCharge <= 0) {
    error_log("Square payment - Invalid amount to charge: $amountToCharge");
    echo json_encode(['success' => false, 'message' => 'Invalid payment amount calculated']);
    exit;
}

// Process payment with Square
try {
    $client = new SquareClient([
        'accessToken' => SQUARE_ACCESS_TOKEN,
        'environment' => 'sandbox' // Change to 'production' for live
    ]);

    // Convert amount to cents for Square API
    $amountCents = (int) round($amountToCharge * 100);
    error_log("Square payment - Amount in cents: $amountCents");

    $money = new Money();
    $money->setAmount($amountCents);
    $money->setCurrency(strtoupper(CURRENCY));

    $request = new CreatePaymentRequest(
        $data['token'],
        uniqid('BB_'), // Unique idempotency key
        $money
    );

    $request->setAutocomplete(true);
    $request->setLocationId(SQUARE_LOCATION_ID);
    $request->setNote("Barbs Bali - {$booking['guest_first_name']} {$booking['guest_last_name']} - " . 
                     ($depositOnly ? 'Deposit' : 'Full Payment'));

    $paymentsApi = $client->getPaymentsApi();
    $response = $paymentsApi->createPayment($request);

    if (!$response->isSuccess()) {
        $errors = $response->getErrors();
        $errorMsg = $errors ? $errors[0]->getDetail() : 'Unknown payment error';
        error_log("Square payment - Payment failed: $errorMsg");
        echo json_encode(['success' => false, 'message' => 'Payment failed: ' . $errorMsg]);
        exit;
    }

    error_log("Square payment - Payment successful");

    // Store booking in database
    $pdo = getPDO();
    $pdo->beginTransaction();

    try {
        // Generate unique reservation number
        $resNum = strtoupper(uniqid('BB'));

        // Insert booking record - matching your actual database schema
        $insertBooking = $pdo->prepare("INSERT INTO bookings (
            reservation_number, apartment_id, guest_first_name, guest_last_name, guest_email,
            guest_phone, guest_address, checkin_date, checkout_date, total_price, sofa_bed, 
            status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())");

        $insertBooking->execute([
            $resNum,
            $apartmentId,
            $booking['guest_first_name'],
            $booking['guest_last_name'],
            $booking['guest_email'],
            $booking['guest_phone'] ?? '',
            $booking['guest_address'] ?? '',
            $booking['checkin_date'],
            $booking['checkout_date'],
            $total,
            $booking['sofa_bed'] ? 1 : 0
        ]);

        $bookingId = $pdo->lastInsertId();
        error_log("Square payment - Created booking ID: $bookingId");

        // Insert extras
        if (!empty($booking['extras']) && is_array($booking['extras'])) {
            $insertExtra = $pdo->prepare("INSERT INTO booking_extras (booking_id, extra_id) VALUES (?, ?)");
            foreach ($booking['extras'] as $extraId) {
                $insertExtra->execute([$bookingId, (int)$extraId]);
                error_log("Square payment - Added extra: $extraId");
            }
        }

        // Insert payment record - CORRECTED to match your database schema
        $insertPayment = $pdo->prepare("INSERT INTO payments (
            booking_id, amount, transaction_id, method, note, paid_at, created_at
        ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        
        $paymentResult = $response->getResult()->getPayment();
        $transactionId = $paymentResult ? $paymentResult->getId() : 'unknown';
        $paymentNote = $depositOnly ? 'Deposit payment via Square' : 'Full payment via Square';
        
        $insertPayment->execute([
            $bookingId, 
            $amountToCharge, 
            $transactionId, 
            'square',  // This matches your 'method' column
            $paymentNote
        ]);
        
        // Schedule emails (if email_schedule table exists)
        try {
            $emailStmt = $pdo->prepare("INSERT INTO email_schedule
                (booking_id, email_type, send_date, sent_status)
                VALUES (?, ?, ?, 'scheduled')");

            $today = date('Y-m-d');
            
            // Confirmation email (immediate)
            $emailStmt->execute([$bookingId, 'confirmation', $today]);
            
            // Balance reminder if deposit only
            if ($depositOnly) {
                $balanceReminderDate = (clone $checkinDate)->modify('-90 days')->format('Y-m-d');
                if ($balanceReminderDate >= $today) {
                    $emailStmt->execute([$bookingId, 'balance_reminder', $balanceReminderDate]);
                }
            }

            // Check-in reminder (14 days before)
            $checkinReminderDate = (clone $checkinDate)->modify('-14 days')->format('Y-m-d');
            if ($checkinReminderDate >= $today) {
                $emailStmt->execute([$bookingId, 'checkin_reminder', $checkinReminderDate]);
            }

            // Housekeeping notice (7 days before)
            $housekeepingDate = (clone $checkinDate)->modify('-7 days')->format('Y-m-d');
            if ($housekeepingDate >= $today) {
                $emailStmt->execute([$bookingId, 'housekeeping_notice', $housekeepingDate]);
            }
        } catch (Exception $emailScheduleError) {
            // Email scheduling failed, but don't fail the booking
            error_log("Square payment - Email scheduling failed: " . $emailScheduleError->getMessage());
        }

        $pdo->commit();
        error_log("Square payment - Database transaction committed");

        // Send confirmation email if email-utils.php exists and function is available
        if (function_exists('sendConfirmationEmail')) {
            try {
                sendConfirmationEmail(
                    $booking['guest_email'],
                    $resNum,
                    $booking,
                    $total,
                    $amountToCharge,
                    null // No email ID if email_schedule failed
                );
                
                error_log("Square payment - Confirmation email sent");
            } catch (Exception $emailError) {
                error_log("Square payment - Email sending failed: " . $emailError->getMessage());
                // Don't fail the payment for email issues
            }
        }

        // Clear session
        unset($_SESSION['booking']);
        unset($_SESSION['amount_due']);
        unset($_SESSION['price_breakdown']);

        echo json_encode([
            'success' => true,
            'reservation_number' => $resNum,
            'total_paid' => $amountToCharge
        ]);

    } catch (Exception $dbError) {
        $pdo->rollback();
        error_log("Square payment - Database error: " . $dbError->getMessage());
        throw $dbError;
    }

} catch (Exception $e) {
    error_log("Square payment - General error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Payment processing error: ' . $e->getMessage()
    ]);
}