<?php
// api/mark-paid.php - Fixed version
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/price-calc.php';
// Note: complete-booking.php is not needed - we'll handle everything inline

try {
    // Get input data
    $input = file_get_contents("php://input");
    if (empty($input)) {
        throw new Exception("No input data received");
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON data: " . json_last_error_msg());
    }

    // Validate CSRF token
    if (!isset($data['csrf_token']) || !isset($_SESSION['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception("Invalid CSRF token");
    }

    if (!isset($data['payment_method'])) {
        throw new Exception("Missing payment method");
    }

    if (!isset($_SESSION['booking']) || empty($_SESSION['booking'])) {
        throw new Exception("No booking session found");
    }

    $booking = $_SESSION['booking'];
    $paymentMethod = $data['payment_method'];

    error_log("Mark paid - Processing $paymentMethod payment");
    error_log("Mark paid - Booking data: " . json_encode($booking));

    // Calculate total amount and what should be charged
    $apartmentId = (int)$booking['apartment_id'];
    
    if ($apartmentId === 3) {
        // Combined booking
        $booking6205 = $booking;
        $booking6205['apartment_id'] = 1;
        unset($booking6205['both_apartments'], $booking6205['apartment_ids']);

        $booking6207 = $booking;
        $booking6207['apartment_id'] = 2;
        unset($booking6207['both_apartments'], $booking6207['apartment_ids']);

        $price6205 = calculateBookingTotal($booking6205);
        $price6207 = calculateBookingTotal($booking6207);
        $total = (float)$price6205['total'] + (float)$price6207['total'];
    } else {
        $priceInfo = calculateBookingTotal($booking);
        $total = (float)$priceInfo['total'];
    }

    // Calculate deposit vs full payment
    $checkinDate = new DateTime($booking['checkin_date']);
    $today = new DateTime();
    $daysUntil = $today->diff($checkinDate)->days;
    $depositOnly = $daysUntil > DEPOSIT_THRESHOLD_DAYS;
    $amountPaid = $depositOnly ? round($total * DEPOSIT_RATE, 2) : $total;

    error_log("Mark paid - Total: $total, Amount paid: $amountPaid, Deposit only: " . ($depositOnly ? 'yes' : 'no'));

    // Handle different payment methods
    $transactionId = '';
    
    if ($paymentMethod === 'paypal') {
        if (!isset($data['paypal_details']) || !isset($data['paypal_details']['id'])) {
            throw new Exception("Missing PayPal transaction details");
        }
        $transactionId = $data['paypal_details']['id'];
        error_log("Mark paid - PayPal transaction ID: $transactionId");
    } else {
        throw new Exception("Unsupported payment method: $paymentMethod");
    }

    // Create booking in database
    $pdo = getPDO();
    $pdo->beginTransaction();

    try {
        // Generate unique reservation number
        $resNum = strtoupper(uniqid('BB'));
        error_log("Mark paid - Generated reservation number: $resNum");

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
            throw new Exception("Failed to insert booking");
        }

        $bookingId = $pdo->lastInsertId();
        error_log("Mark paid - Created booking ID: $bookingId");

        // Insert extras if any
        if (!empty($booking['extras']) && is_array($booking['extras'])) {
            $insertExtra = $pdo->prepare("INSERT INTO booking_extras (booking_id, extra_id) VALUES (?, ?)");
            foreach ($booking['extras'] as $extraId) {
                $extraId = filter_var($extraId, FILTER_VALIDATE_INT);
                if ($extraId !== false && $extraId > 0) {
                    $insertExtra->execute([$bookingId, $extraId]);
                    error_log("Mark paid - Added extra $extraId");
                }
            }
        }

        // Insert payment record - using correct table structure
        $insertPayment = $pdo->prepare("INSERT INTO payments (
            booking_id, amount, method, note, paid_at
        ) VALUES (?, ?, ?, ?, NOW())");
        
        $paymentNote = $depositOnly ? "Deposit payment via $paymentMethod (ID: $transactionId)" : "Full payment via $paymentMethod (ID: $transactionId)";
        
        $paymentInsertResult = $insertPayment->execute([
            $bookingId, 
            $amountPaid, 
            $paymentMethod,
            $paymentNote
        ]);

        if (!$paymentInsertResult) {
            throw new Exception("Failed to insert payment");
        }

        error_log("Mark paid - Payment record inserted");

        // Email scheduling
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
                error_log("Mark paid - email_schedule table created");
            }

            // Schedule confirmation email (immediate)
            $emailStmt = $pdo->prepare("INSERT INTO email_schedule (booking_id, email_type, send_date, sent_status) VALUES (?, ?, ?, 'scheduled')");
            $today = date('Y-m-d');
            $emailStmt->execute([$bookingId, 'confirmation', $today]);
            
            error_log("Mark paid - Confirmation email scheduled");

            // Schedule other emails if needed
            if ($depositOnly) {
                $balanceReminderDate = (clone $checkinDate)->modify('-7 days')->format('Y-m-d');
                if ($balanceReminderDate >= $today) {
                    $emailStmt->execute([$bookingId, 'balance_reminder', $balanceReminderDate]);
                    error_log("Mark paid - Balance reminder scheduled for: $balanceReminderDate");
                }
            }

            // Schedule check-in reminder (1 day before)
            $checkinReminderDate = (clone $checkinDate)->modify('-1 day')->format('Y-m-d');
            if ($checkinReminderDate >= $today) {
                $emailStmt->execute([$bookingId, 'checkin_reminder', $checkinReminderDate]);
                error_log("Mark paid - Check-in reminder scheduled for: $checkinReminderDate");
            }

            // Schedule housekeeping notice (day of check-in)
            $emailStmt->execute([$bookingId, 'housekeeping_notice', $booking['checkin_date']]);
            error_log("Mark paid - Housekeeping notice scheduled for: {$booking['checkin_date']}");

        } catch (Exception $emailError) {
            error_log("Mark paid - Email scheduling error: " . $emailError->getMessage());
            // Don't fail the whole transaction for email issues
        }

        $pdo->commit();
        error_log("Mark paid - Database transaction committed");

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
                    $amountPaid,
                    $confirmationEmailId  // Pass the email schedule ID
                );
                
                if ($emailSent && $confirmationEmailId) {
                    error_log("Mark paid - Confirmation email sent and marked as sent");
                } else {
                    error_log("Mark paid - Confirmation email failed to send");
                }
            } else {
                error_log("Mark paid - sendConfirmationEmail function not available");
            }
        } catch (Exception $emailError) {
            error_log("Mark paid - Email sending error: " . $emailError->getMessage());
        }

        // Clear session
        unset($_SESSION['booking']);
        unset($_SESSION['amount_due']);
        unset($_SESSION['price_breakdown']);

        // Return success
        echo json_encode([
            'success' => true,
            'reservation_number' => $resNum,
            'total_paid' => $amountPaid,
            'booking_id' => $bookingId,
            'message' => 'Payment processed successfully'
        ]);

    } catch (Exception $dbError) {
        $pdo->rollback();
        throw $dbError;
    }

} catch (Exception $e) {
    error_log("Mark paid - Error: " . $e->getMessage());
    error_log("Mark paid - Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>