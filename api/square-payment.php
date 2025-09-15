<?php
// api/square-payment-debug.php - Debug version to identify email scheduling issues
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/price-calc.php';
require_once __DIR__ . '/../includes/email-utils.php';
require __DIR__ . '/../vendor/autoload.php';

use Square\SquareClient;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;

header('Content-Type: application/json');

session_start();

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Debug initial state
error_log("Square payment DEBUG - Initial session: " . json_encode($_SESSION));

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['token']) || !isset($_SESSION['booking'])) {
    error_log("Square payment DEBUG - Missing session or payment token");
    echo json_encode(['success' => false, 'message' => 'Missing session or payment token']);
    exit;
}

$booking = $_SESSION['booking'];

// Debug booking data
error_log("Square payment DEBUG - Booking data: " . json_encode($booking));

// Ensure we have all required booking data
$required = ['guest_first_name', 'guest_last_name', 'guest_email', 'apartment_id', 'checkin_date', 'checkout_date'];
foreach ($required as $field) {
    if (empty($booking[$field])) {
        error_log("Square payment DEBUG - Missing booking field: $field");
        echo json_encode(['success' => false, 'message' => "Missing booking data: $field"]);
        exit;
    }
}

// Calculate pricing
$apartmentId = (int)$booking['apartment_id'];
error_log("Square payment DEBUG - Apartment ID: $apartmentId");

if ($apartmentId === 3) {
    $booking6205 = $booking;
    $booking6205['apartment_id'] = 1;
    unset($booking6205['both_apartments'], $booking6205['apartment_ids']);

    $booking6207 = $booking;
    $booking6207['apartment_id'] = 2;
    unset($booking6207['both_apartments'], $booking6207['apartment_ids']);

    $price6205 = calculateBookingTotal($booking6205);
    $price6207 = calculateBookingTotal($booking6207);
    $total = (float)$price6205['total'] + (float)$price6207['total'];
    
    error_log("Square payment DEBUG - Combined total: $total");
} else {
    $priceInfo = calculateBookingTotal($booking);
    $total = (float)$priceInfo['total'];
    
    error_log("Square payment DEBUG - Single apartment total: $total");
}

// Calculate deposit vs full payment
$checkinDate = new DateTime($booking['checkin_date']);
$today = new DateTime();
$daysUntil = $today->diff($checkinDate)->days;
$depositOnly = $daysUntil > DEPOSIT_THRESHOLD_DAYS;
$amountToCharge = $depositOnly ? round($total * DEPOSIT_RATE, 2) : $total;

error_log("Square payment DEBUG - Days until checkin: $daysUntil, deposit only: " . ($depositOnly ? 'yes' : 'no'));
error_log("Square payment DEBUG - Amount to charge: $amountToCharge");

// Process payment with Square
try {
    $client = new SquareClient([
        'accessToken' => SQUARE_ACCESS_TOKEN,
        'environment' => 'sandbox'
    ]);

    $amountCents = (int) round($amountToCharge * 100);
    error_log("Square payment DEBUG - Amount in cents: $amountCents");

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
    $request->setNote("Barbs Bali DEBUG - {$booking['guest_first_name']} {$booking['guest_last_name']}");

    $paymentsApi = $client->getPaymentsApi();
    $response = $paymentsApi->createPayment($request);

    if (!$response->isSuccess()) {
        $errors = $response->getErrors();
        $errorMsg = $errors ? $errors[0]->getDetail() : 'Unknown payment error';
        error_log("Square payment DEBUG - Payment failed: $errorMsg");
        echo json_encode(['success' => false, 'message' => 'Payment failed: ' . $errorMsg]);
        exit;
    }

    error_log("Square payment DEBUG - Payment successful");

    // Store booking in database
    $pdo = getPDO();
    $pdo->beginTransaction();

    try {
        // Generate unique reservation number
        $resNum = strtoupper(uniqid('BB'));
        error_log("Square payment DEBUG - Generated reservation number: $resNum");

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
            $booking['sofa_bed'] ? 1 : 0
        ]);

        if (!$bookingInsertResult) {
            throw new Exception("Failed to insert booking: " . print_r($insertBooking->errorInfo(), true));
        }

        $bookingId = $pdo->lastInsertId();
        error_log("Square payment DEBUG - Created booking ID: $bookingId");

        // Insert extras
        if (!empty($booking['extras']) && is_array($booking['extras'])) {
            $insertExtra = $pdo->prepare("INSERT INTO booking_extras (booking_id, extra_id) VALUES (?, ?)");
            foreach ($booking['extras'] as $extraId) {
                $extraResult = $insertExtra->execute([$bookingId, (int)$extraId]);
                error_log("Square payment DEBUG - Added extra $extraId: " . ($extraResult ? 'success' : 'failed'));
            }
        }

        // Insert payment record
        $insertPayment = $pdo->prepare("INSERT INTO payments (
            booking_id, amount, transaction_id, method, note, paid_at, created_at
        ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        
        $paymentResult = $response->getResult()->getPayment();
        $transactionId = $paymentResult ? $paymentResult->getId() : 'unknown';
        $paymentNote = $depositOnly ? 'Deposit payment via Square (DEBUG)' : 'Full payment via Square (DEBUG)';
        
        $paymentInsertResult = $insertPayment->execute([
            $bookingId, 
            $amountToCharge, 
            $transactionId, 
            'square',
            $paymentNote
        ]);

        if (!$paymentInsertResult) {
            throw new Exception("Failed to insert payment: " . print_r($insertPayment->errorInfo(), true));
        }

        error_log("Square payment DEBUG - Payment record inserted");
        
        // ===== EMAIL SCHEDULING DEBUG SECTION =====
        error_log("Square payment DEBUG - Starting email scheduling...");
        
        // Check if email_schedule table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'email_schedule'");
        if ($tableCheck->rowCount() == 0) {
            error_log("Square payment DEBUG - email_schedule table does not exist, creating it...");
            
            $createTable = "
            CREATE TABLE email_schedule (
                id INT PRIMARY KEY AUTO_INCREMENT,
                booking_id INT NOT NULL,
                email_type ENUM('confirmation', 'balance_reminder', 'checkin_reminder', 'housekeeping_notice') NOT NULL,
                send_date DATE NOT NULL,
                sent_status ENUM('scheduled', 'sent', 'failed') DEFAULT 'scheduled',
                sent_timestamp TIMESTAMP NULL,
                opened_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
                INDEX idx_send_date_status (send_date, sent_status),
                INDEX idx_booking_id (booking_id)
            )";
            
            $pdo->exec($createTable);
            error_log("Square payment DEBUG - email_schedule table created");
        } else {
            error_log("Square payment DEBUG - email_schedule table exists");
        }

        // Prepare email scheduling statement
        $emailStmt = $pdo->prepare("INSERT INTO email_schedule
            (booking_id, email_type, send_date, sent_status)
            VALUES (?, ?, ?, 'scheduled')");

        $today = date('Y-m-d');
        $emailsScheduled = 0;
        
        // Schedule confirmation email (immediate)
        try {
            $confirmationResult = $emailStmt->execute([$bookingId, 'confirmation', $today]);
            if ($confirmationResult) {
                $emailsScheduled++;
                error_log("Square payment DEBUG - Confirmation email scheduled successfully");
            } else {
                error_log("Square payment DEBUG - Failed to schedule confirmation email: " . print_r($emailStmt->errorInfo(), true));
            }
        } catch (Exception $e) {
            error_log("Square payment DEBUG - Exception scheduling confirmation email: " . $e->getMessage());
        }
        
        // Schedule balance reminder if deposit only
        if ($depositOnly) {
            try {
                $balanceReminderDate = (clone $checkinDate)->modify('-90 days')->format('Y-m-d');
                if ($balanceReminderDate >= $today) {
                    $balanceResult = $emailStmt->execute([$bookingId, 'balance_reminder', $balanceReminderDate]);
                    if ($balanceResult) {
                        $emailsScheduled++;
                        error_log("Square payment DEBUG - Balance reminder scheduled for $balanceReminderDate");
                    } else {
                        error_log("Square payment DEBUG - Failed to schedule balance reminder: " . print_r($emailStmt->errorInfo(), true));
                    }
                } else {
                    error_log("Square payment DEBUG - Balance reminder date $balanceReminderDate is in the past, skipping");
                }
            } catch (Exception $e) {
                error_log("Square payment DEBUG - Exception scheduling balance reminder: " . $e->getMessage());
            }
        } else {
            error_log("Square payment DEBUG - Full payment, no balance reminder needed");
        }

        // Schedule check-in reminder (14 days before)
        try {
            $checkinReminderDate = (clone $checkinDate)->modify('-14 days')->format('Y-m-d');
            if ($checkinReminderDate >= $today) {
                $checkinResult = $emailStmt->execute([$bookingId, 'checkin_reminder', $checkinReminderDate]);
                if ($checkinResult) {
                    $emailsScheduled++;
                    error_log("Square payment DEBUG - Check-in reminder scheduled for $checkinReminderDate");
                } else {
                    error_log("Square payment DEBUG - Failed to schedule check-in reminder: " . print_r($emailStmt->errorInfo(), true));
                }
            } else {
                error_log("Square payment DEBUG - Check-in reminder date $checkinReminderDate is in the past, skipping");
            }
        } catch (Exception $e) {
            error_log("Square payment DEBUG - Exception scheduling check-in reminder: " . $e->getMessage());
        }

        // Schedule housekeeping notice (7 days before)
        try {
            $housekeepingDate = (clone $checkinDate)->modify('-7 days')->format('Y-m-d');
            if ($housekeepingDate >= $today) {
                $housekeepingResult = $emailStmt->execute([$bookingId, 'housekeeping_notice', $housekeepingDate]);
                if ($housekeepingResult) {
                    $emailsScheduled++;
                    error_log("Square payment DEBUG - Housekeeping notice scheduled for $housekeepingDate");
                } else {
                    error_log("Square payment DEBUG - Failed to schedule housekeeping notice: " . print_r($emailStmt->errorInfo(), true));
                }
            } else {
                error_log("Square payment DEBUG - Housekeeping notice date $housekeepingDate is in the past, skipping");
            }
        } catch (Exception $e) {
            error_log("Square payment DEBUG - Exception scheduling housekeeping notice: " . $e->getMessage());
        }

        error_log("Square payment DEBUG - Total emails scheduled: $emailsScheduled");

        // Verify emails were actually inserted
        $verifyStmt = $pdo->prepare("SELECT COUNT(*) FROM email_schedule WHERE booking_id = ?");
        $verifyStmt->execute([$bookingId]);
        $actualCount = $verifyStmt->fetchColumn();
        error_log("Square payment DEBUG - Emails found in database: $actualCount");

        if ($actualCount == 0) {
            error_log("Square payment DEBUG - NO EMAILS IN DATABASE! This is the problem!");
            
            // Let's try a very simple insert to test
            try {
                $simpleStmt = $pdo->prepare("INSERT INTO email_schedule (booking_id, email_type, send_date) VALUES (?, 'confirmation', ?)");
                $simpleResult = $simpleStmt->execute([$bookingId, $today]);
                error_log("Square payment DEBUG - Simple insert test: " . ($simpleResult ? 'SUCCESS' : 'FAILED'));
                if (!$simpleResult) {
                    error_log("Square payment DEBUG - Simple insert error: " . print_r($simpleStmt->errorInfo(), true));
                }
            } catch (Exception $e) {
                error_log("Square payment DEBUG - Simple insert exception: " . $e->getMessage());
            }
        }

        $pdo->commit();
        error_log("Square payment DEBUG - Database transaction committed");

        // Send confirmation email immediately
        $confirmationEmailId = null;
        
        // Get the confirmation email ID for tracking
        try {
            $emailIdStmt = $pdo->prepare("SELECT id FROM email_schedule WHERE booking_id = ? AND email_type = 'confirmation' LIMIT 1");
            $emailIdStmt->execute([$bookingId]);
            $confirmationEmailId = $emailIdStmt->fetchColumn();
            error_log("Square payment DEBUG - Confirmation email ID: " . ($confirmationEmailId ?: 'NOT FOUND'));
        } catch (Exception $e) {
            error_log("Square payment DEBUG - Error getting confirmation email ID: " . $e->getMessage());
        }

        // Prepare booking array for email template
        $emailBooking = [
            'guest_first_name' => $booking['guest_first_name'],
            'guest_last_name' => $booking['guest_last_name'],
            'guest_email' => $booking['guest_email'],
            'apartment_id' => $apartmentId,
            'checkin_date' => $booking['checkin_date'],
            'checkout_date' => $booking['checkout_date'],
            'total_price' => $total
        ];

        // Send confirmation email
        try {
            $emailSent = sendConfirmationEmail(
                $booking['guest_email'],
                $resNum,
                $emailBooking,
                $total,
                $amountToCharge,
                $confirmationEmailId
            );
            
            error_log("Square payment DEBUG - Confirmation email sent: " . ($emailSent ? 'SUCCESS' : 'FAILED'));

            if ($emailSent && $confirmationEmailId) {
                // Mark confirmation email as sent
                $pdo->prepare("UPDATE email_schedule SET sent_status = 'sent', sent_timestamp = NOW() WHERE id = ?")
                    ->execute([$confirmationEmailId]);
                error_log("Square payment DEBUG - Confirmation email marked as sent");
            }
        } catch (Exception $e) {
            error_log("Square payment DEBUG - Exception sending confirmation email: " . $e->getMessage());
        }

        // Clear session
        unset($_SESSION['booking']);
        unset($_SESSION['amount_due']);
        unset($_SESSION['price_breakdown']);

        // Return success with debug info
        echo json_encode([
            'success' => true,
            'reservation_number' => $resNum,
            'total_paid' => $amountToCharge,
            'booking_id' => $bookingId,
            'emails_scheduled' => $emailsScheduled,
            'emails_in_db' => $actualCount,
            'debug_mode' => true
        ]);

    } catch (Exception $dbError) {
        $pdo->rollback();
        error_log("Square payment DEBUG - Database error: " . $dbError->getMessage());
        error_log("Square payment DEBUG - Stack trace: " . $dbError->getTraceAsString());
        
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $dbError->getMessage(),
            'debug_mode' => true
        ]);
        exit;
    }

} catch (Exception $e) {
    error_log("Square payment DEBUG - General error: " . $e->getMessage());
    error_log("Square payment DEBUG - Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Payment processing error: ' . $e->getMessage(),
        'debug_mode' => true
    ]);
}