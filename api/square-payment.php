<?php
// api/square-payment.php - Complete corrected version
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('display_errors', 0); // Don't display errors in production
// Ensure Square SDK is available before processing
try {
    if (!class_exists('Square\\SquareClient')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        if (!class_exists('Square\\SquareClient')) {
            throw new Exception("Square SDK failed to load");
        }
    }
} catch (Exception $e) {
    error_log("Square payment failed: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Payment system temporarily unavailable']);
    exit;
}
// Include autoloader and use statements MUST come first
$vendorPath = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($vendorPath)) {
    error_log("Square payment failed: vendor not found at $vendorPath");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Payment system temporarily unavailable']);
    exit;
}
require_once __DIR__ . '/../vendor/autoload.php';
use Square\SquareClient;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;

// Set JSON header first
header('Content-Type: application/json');

// Start session before any output
session_start();

try {
    // Check if required files exist before including
    $requiredFiles = [
        __DIR__ . '/../includes/core.php',
        __DIR__ . '/../includes/price-calc.php', 
        __DIR__ . '/../includes/email-utils.php'
    ];

    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            throw new Exception("Required file not found: " . basename($file));
        }
    }

    // Include required files
    require_once __DIR__ . '/../includes/core.php';
    require_once __DIR__ . '/../includes/price-calc.php';
    require_once __DIR__ . '/../includes/email-utils.php';

    // Check if Square classes are available
    if (!class_exists('Square\SquareClient')) {
        throw new Exception("Square SDK not properly loaded");
    }

    // Check if required constants are defined
    $requiredConstants = ['SQUARE_ACCESS_TOKEN', 'SQUARE_LOCATION_ID', 'CURRENCY', 'DEPOSIT_THRESHOLD_DAYS', 'DEPOSIT_RATE'];
    foreach ($requiredConstants as $constant) {
        if (!defined($constant)) {
            throw new Exception("Required constant not defined: $constant");
        }
    }

    error_log("Square payment - Initial session: " . json_encode($_SESSION));

    // Get and validate input
    $input = file_get_contents("php://input");
    if (empty($input)) {
        throw new Exception("No input data received");
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON data: " . json_last_error_msg());
    }

    if (!isset($data['token'])) {
        throw new Exception("Missing payment token");
    }

    if (!isset($_SESSION['booking']) || empty($_SESSION['booking'])) {
        throw new Exception("No booking session found");
    }

    $booking = $_SESSION['booking'];
    error_log("Square payment - Booking data: " . json_encode($booking));

    // Validate required booking fields
    $required = ['guest_first_name', 'guest_last_name', 'guest_email', 'apartment_id', 'checkin_date', 'checkout_date'];
    foreach ($required as $field) {
        if (empty($booking[$field])) {
            throw new Exception("Missing booking field: $field");
        }
    }

    // Validate and sanitize apartment ID
    $apartmentId = filter_var($booking['apartment_id'], FILTER_VALIDATE_INT);
    if ($apartmentId === false || $apartmentId < 1) {
        throw new Exception("Invalid apartment ID");
    }

    error_log("Square payment - Apartment ID: $apartmentId");

    // Calculate pricing with error handling
    try {
        if ($apartmentId === 3) {
            // For a combined booking, total = 6205 total + 6207 total
            $booking6205 = $booking;
            $booking6205['apartment_id'] = 1;
            unset($booking6205['both_apartments'], $booking6205['apartment_ids']); // avoid confusion in calculator

            $booking6207 = $booking;
            $booking6207['apartment_id'] = 2;
            unset($booking6207['both_apartments'], $booking6207['apartment_ids']);

            $price6205 = calculateBookingTotal($booking6205);
            $price6207 = calculateBookingTotal($booking6207);
            
            if (!$price6205 || !$price6207 || !isset($price6205['total']) || !isset($price6207['total'])) {
                throw new Exception("Failed to calculate combined pricing");
            }
            
            $total = (float)$price6205['total'] + (float)$price6207['total'];
        } else {
            $priceInfo = calculateBookingTotal($booking);
            if (!$priceInfo || !isset($priceInfo['total'])) {
                throw new Exception("Failed to calculate pricing");
            }
            $total = (float)$priceInfo['total'];
        }

        if ($total <= 0) {
            throw new Exception("Invalid total amount: $total");
        }

        error_log("Square payment - Total calculated: $total");
    } catch (Exception $e) {
        throw new Exception("Pricing calculation error: " . $e->getMessage());
    }

    // Calculate deposit vs full payment
    $checkinDate = new DateTime($booking['checkin_date']);
    $today = new DateTime();
    $daysUntil = $today->diff($checkinDate)->days;
    $depositOnly = $daysUntil > DEPOSIT_THRESHOLD_DAYS;
    $amountToCharge = $depositOnly ? round($total * DEPOSIT_RATE, 2) : $total;

    if ($amountToCharge <= 0) {
        throw new Exception("Invalid charge amount: $amountToCharge");
    }

    error_log("Square payment - Days until checkin: $daysUntil, deposit only: " . ($depositOnly ? 'yes' : 'no'));
    error_log("Square payment - Amount to charge: $amountToCharge");

    // Process payment with Square
    try {
        $client = new SquareClient([
            'accessToken' => SQUARE_ACCESS_TOKEN,
            'environment' => 'sandbox'
        ]);

        $amountCents = (int) round($amountToCharge * 100);
        if ($amountCents <= 0) {
            throw new Exception("Invalid amount in cents: $amountCents");
        }

        error_log("Square payment - Amount in cents: $amountCents");

        $money = new Money();
        $money->setAmount($amountCents);
        $money->setCurrency(strtoupper(CURRENCY));

        $request = new CreatePaymentRequest(
            $data['token'],
            uniqid('BB_'),
            $money
        );

        $request->setAutocomplete(true);
        $request->setLocationId(SQUARE_LOCATION_ID);
        $request->setNote("Barbs Bali - {$booking['guest_first_name']} {$booking['guest_last_name']}");

        $paymentsApi = $client->getPaymentsApi();
        $response = $paymentsApi->createPayment($request);

        if (!$response->isSuccess()) {
            $errors = $response->getErrors();
            $errorMsg = $errors ? $errors[0]->getDetail() : 'Unknown payment error';
            throw new Exception("Payment failed: $errorMsg");
        }

        error_log("Square payment - Payment successful");

    } catch (Exception $e) {
        throw new Exception("Square API error: " . $e->getMessage());
    }

    // Database operations
    try {
        $pdo = getPDO();
        if (!$pdo) {
            throw new Exception("Database connection failed");
        }

        $pdo->beginTransaction();

        // Generate unique reservation number
        $resNum = strtoupper(uniqid('BB'));
        error_log("Square payment - Generated reservation number: $resNum");

        // Insert booking record
        $insertBooking = $pdo->prepare("INSERT INTO bookings (
            reservation_number, apartment_id, guest_first_name, guest_last_name, guest_email,
            guest_phone, guest_address, checkin_date, checkout_date, total_price, sofa_bed, 
            status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())");

        $bookingInsertResult = $insertBooking->execute([
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
            isset($booking['sofa_bed']) && $booking['sofa_bed'] ? 1 : 0
        ]);

        if (!$bookingInsertResult) {
            throw new Exception("Failed to insert booking: " . implode(', ', $insertBooking->errorInfo()));
        }

        $bookingId = $pdo->lastInsertId();
        error_log("Square payment - Created booking ID: $bookingId");

        // Insert extras if any
        if (!empty($booking['extras']) && is_array($booking['extras'])) {
            $insertExtra = $pdo->prepare("INSERT INTO booking_extras (booking_id, extra_id) VALUES (?, ?)");
            foreach ($booking['extras'] as $extraId) {
                $extraId = filter_var($extraId, FILTER_VALIDATE_INT);
                if ($extraId !== false) {
                    $extraResult = $insertExtra->execute([$bookingId, $extraId]);
                    error_log("Square payment - Added extra $extraId: " . ($extraResult ? 'success' : 'failed'));
                }
            }
        }

        // Insert payment record - CORRECTED for actual table structure
        $insertPayment = $pdo->prepare("INSERT INTO payments (
            booking_id, amount, method, note, paid_at
        ) VALUES (?, ?, ?, ?, NOW())");
        
        $paymentResult = $response->getResult()->getPayment();
        $transactionId = $paymentResult ? $paymentResult->getId() : 'unknown';
        $paymentNote = $depositOnly ? "Deposit payment via Square (ID: $transactionId)" : "Full payment via Square (ID: $transactionId)";
        
        $paymentInsertResult = $insertPayment->execute([
            $bookingId, 
            $amountToCharge, 
            'square',
            $paymentNote
        ]);

        if (!$paymentInsertResult) {
            throw new Exception("Failed to insert payment: " . implode(', ', $insertPayment->errorInfo()));
        }

        error_log("Square payment - Payment record inserted");

        // Email scheduling (simplified)
        try {
            // Check if email_schedule table exists
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'email_schedule'");
            $tableExists = $tableCheck->rowCount() > 0;
            
            if (!$tableExists) {
                $createTable = "
                CREATE TABLE email_schedule (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    booking_id INT NOT NULL,
                    email_type ENUM('confirmation', 'balance_reminder', 'checkin_reminder', 'housekeeping_notice') NOT NULL,
                    send_date DATE NOT NULL,
                    sent_status ENUM('scheduled', 'sent', 'failed') DEFAULT 'scheduled',
                    sent_timestamp TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
                )";
                $pdo->exec($createTable);
                error_log("Square payment - email_schedule table created");
            }

            // Schedule confirmation email (immediate)
            $emailStmt = $pdo->prepare("INSERT INTO email_schedule (booking_id, email_type, send_date, sent_status) VALUES (?, ?, ?, 'scheduled')");
            $today = date('Y-m-d');
            $emailStmt->execute([$bookingId, 'confirmation', $today]);
            
            error_log("Square payment - Confirmation email scheduled");

            // Schedule other emails if needed
            if ($depositOnly) {
                $balanceReminderDate = (clone $checkinDate)->modify('-7 days')->format('Y-m-d');
                if ($balanceReminderDate >= $today) {
                    $emailStmt->execute([$bookingId, 'balance_reminder', $balanceReminderDate]);
                    error_log("Square payment - Balance reminder scheduled for: $balanceReminderDate");
                }
            }

            // Schedule check-in reminder (1 day before)
            $checkinReminderDate = (clone $checkinDate)->modify('-1 day')->format('Y-m-d');
            if ($checkinReminderDate >= $today) {
                $emailStmt->execute([$bookingId, 'checkin_reminder', $checkinReminderDate]);
                error_log("Square payment - Check-in reminder scheduled for: $checkinReminderDate");
            }

            // Schedule housekeeping notice (day of check-in)
            $emailStmt->execute([$bookingId, 'housekeeping_notice', $booking['checkin_date']]);
            error_log("Square payment - Housekeeping notice scheduled for: {$booking['checkin_date']}");

        } catch (Exception $emailError) {
            error_log("Square payment - Email scheduling error: " . $emailError->getMessage());
            // Don't fail the whole transaction for email issues
        }

        $pdo->commit();
        error_log("Square payment - Database transaction committed");

        // Send confirmation email
        try {
            require_once __DIR__ . '/../includes/email-utils.php';
            
            // Get the confirmation email schedule ID
            $emailIdStmt = $pdo->prepare("SELECT id FROM email_schedule WHERE booking_id = ? AND email_type = 'confirmation' ORDER BY created_at DESC LIMIT 1");
            $emailIdStmt->execute([$bookingId]);
            $confirmationEmailId = $emailIdStmt->fetchColumn();
            
            $emailBooking = [
                'guest_first_name' => $booking['guest_first_name'],
                'guest_last_name' => $booking['guest_last_name'],
                'guest_email' => $booking['guest_email'],
                'apartment_id' => $apartmentId,
                'checkin_date' => $booking['checkin_date'],
                'checkout_date' => $booking['checkout_date'],
                'total_price' => $total
            ];

            if (function_exists('sendConfirmationEmail')) {
                $emailSent = sendConfirmationEmail(
                    $booking['guest_email'],
                    $resNum,
                    $emailBooking,
                    $total,
                    $amountToCharge,
                    $confirmationEmailId  // Pass the email schedule ID
                );
                
                if ($emailSent && $confirmationEmailId) {
                    error_log("Square payment - Confirmation email sent and marked as sent");
                } else {
                    error_log("Square payment - Confirmation email failed to send");
                }
            } else {
                error_log("Square payment - sendConfirmationEmail function not available");
            }
        } catch (Exception $emailError) {
            error_log("Square payment - Email sending error: " . $emailError->getMessage());
        }

        // Clear session
        unset($_SESSION['booking']);
        unset($_SESSION['amount_due']);
        unset($_SESSION['price_breakdown']);

        // Return success
        echo json_encode([
            'success' => true,
            'reservation_number' => $resNum,
            'total_paid' => $amountToCharge,
            'booking_id' => $bookingId,
            'message' => 'Payment processed successfully'
        ]);

    } catch (Exception $dbError) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollback();
        }
        throw new Exception("Database error: " . $dbError->getMessage());
    }

} catch (Exception $e) {
    error_log("Square payment - Error: " . $e->getMessage());
    error_log("Square payment - Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>