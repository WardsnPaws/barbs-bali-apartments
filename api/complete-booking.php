<?php
// api/complete-booking.php - Complete booking processing and email scheduling

require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/email-utils.php';

function completeBooking($reservationNumber, $amountPaid, $transactionId, $paymentMethod = 'paypal') {
    try {
        $pdo = getPDO();
        $pdo->beginTransaction();
        
        // 1. Find the booking by reservation number
        $bookingStmt = $pdo->prepare("
            SELECT * FROM bookings 
            WHERE reservation_number = ? 
            AND status IN ('pending', 'confirmed')
        ");
        $bookingStmt->execute([$reservationNumber]);
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            throw new Exception("Booking not found: $reservationNumber");
        }
        
        $bookingId = $booking['id'];
        error_log("Processing payment for booking ID: $bookingId");
        
        // 2. Update booking status to confirmed
        $updateStmt = $pdo->prepare("
            UPDATE bookings 
            SET status = 'confirmed', updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$bookingId]);
        
        // 3. Record the payment
        $paymentStmt = $pdo->prepare("
            INSERT INTO payments (booking_id, amount, transaction_id, method, note, paid_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $paymentNote = ucfirst($paymentMethod) . " payment processed";
        $paymentStmt->execute([
            $bookingId,
            $amountPaid,
            $transactionId,
            $paymentMethod,
            $paymentNote
        ]);
        
        error_log("Payment recorded: $amountPaid via $paymentMethod");
        
        // 4. Schedule emails if not already scheduled
        $emailCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM email_schedule WHERE booking_id = ?");
        $emailCheckStmt->execute([$bookingId]);
        $existingEmails = $emailCheckStmt->fetchColumn();
        
        if ($existingEmails == 0) {
            error_log("Scheduling emails for booking: $bookingId");
            
            $emailsScheduled = 0;
            $today = date('Y-m-d');
            
            // Schedule confirmation email (immediate)
            try {
                $stmt = $pdo->prepare("INSERT INTO email_schedule (booking_id, email_type, send_date, sent_status) VALUES (?, 'confirmation', ?, 'scheduled')");
                $result = $stmt->execute([$bookingId, $today]);
                if ($result) {
                    $emailsScheduled++;
                    error_log("Confirmation email scheduled");
                }
            } catch (Exception $e) {
                error_log("Error scheduling confirmation email: " . $e->getMessage());
            }
            
            // Calculate if this is a deposit or full payment
            $daysUntilCheckin = (strtotime($booking['checkin_date']) - strtotime('today')) / (24 * 3600);
            $isDepositOnly = ($daysUntilCheckin > 30) && ($amountPaid < $booking['total_price']);
            
            // Schedule balance reminder if needed
            if ($isDepositOnly) {
                try {
                    $reminderDate = date('Y-m-d', strtotime($booking['checkin_date'] . ' -7 days'));
                    $stmt = $pdo->prepare("INSERT INTO email_schedule (booking_id, email_type, send_date, sent_status) VALUES (?, 'balance_reminder', ?, 'scheduled')");
                    $result = $stmt->execute([$bookingId, $reminderDate]);
                    if ($result) {
                        $emailsScheduled++;
                        error_log("Balance reminder scheduled for: $reminderDate");
                    }
                } catch (Exception $e) {
                    error_log("Error scheduling balance reminder: " . $e->getMessage());
                }
            }
            
            // Schedule check-in reminder (1 day before)
            try {
                $checkinReminderDate = date('Y-m-d', strtotime($booking['checkin_date'] . ' -1 day'));
                $stmt = $pdo->prepare("INSERT INTO email_schedule (booking_id, email_type, send_date, sent_status) VALUES (?, 'checkin_reminder', ?, 'scheduled')");
                $result = $stmt->execute([$bookingId, $checkinReminderDate]);
                if ($result) {
                    $emailsScheduled++;
                    error_log("Check-in reminder scheduled for: $checkinReminderDate");
                }
            } catch (Exception $e) {
                error_log("Error scheduling check-in reminder: " . $e->getMessage());
            }
            
            // Schedule housekeeping notice (day of check-in)
            try {
                $stmt = $pdo->prepare("INSERT INTO email_schedule (booking_id, email_type, send_date, sent_status) VALUES (?, 'housekeeping_notice', ?, 'scheduled')");
                $result = $stmt->execute([$bookingId, $booking['checkin_date']]);
                if ($result) {
                    $emailsScheduled++;
                    error_log("Housekeeping notice scheduled for: {$booking['checkin_date']}");
                }
            } catch (Exception $e) {
                error_log("Error scheduling housekeeping notice: " . $e->getMessage());
            }
            
            error_log("Total emails scheduled: $emailsScheduled");
        } else {
            error_log("Emails already scheduled for this booking");
        }
        
        // 5. Send immediate confirmation email
        try {
            // Get the confirmation email ID for tracking
            $emailIdStmt = $pdo->prepare("SELECT id FROM email_schedule WHERE booking_id = ? AND email_type = 'confirmation' ORDER BY created_at DESC LIMIT 1");
            $emailIdStmt->execute([$bookingId]);
            $confirmationEmailId = $emailIdStmt->fetchColumn();
            
            // Prepare booking data for email
            $emailBooking = [
                'guest_first_name' => $booking['guest_first_name'],
                'guest_last_name' => $booking['guest_last_name'],
                'guest_email' => $booking['guest_email'],
                'apartment_id' => $booking['apartment_id'],
                'checkin_date' => $booking['checkin_date'],
                'checkout_date' => $booking['checkout_date'],
                'total_price' => $booking['total_price']
            ];
            
            $emailSent = sendConfirmationEmail(
                $booking['guest_email'],
                $reservationNumber,
                $emailBooking,
                $booking['total_price'],
                $amountPaid,
                $confirmationEmailId
            );
            
            if ($emailSent && $confirmationEmailId) {
                // Mark confirmation email as sent
                $pdo->prepare("UPDATE email_schedule SET sent_status = 'sent', sent_timestamp = NOW() WHERE id = ?")
                    ->execute([$confirmationEmailId]);
                error_log("Confirmation email sent and marked as sent");
            }
            
        } catch (Exception $e) {
            error_log("Error sending confirmation email: " . $e->getMessage());
            // Don't fail the whole transaction for email issues
        }
        
        // 6. Commit transaction
        $pdo->commit();
        error_log("Booking completion successful for: $reservationNumber");
        
        return [
            'success' => true,
            'booking_id' => $bookingId,
            'reservation_number' => $reservationNumber,
            'amount_paid' => $amountPaid,
            'guest_name' => $booking['guest_first_name'] . ' ' . $booking['guest_last_name'],
            'checkin_date' => $booking['checkin_date'],
            'checkout_date' => $booking['checkout_date'],
            'total_amount' => $booking['total_price']
        ];
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollback();
        }
        error_log("Booking completion failed: " . $e->getMessage());
        throw $e;
    }
}

// If called directly (for testing)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $reservationNumber = $_POST['reservation_number'] ?? '';
        $amountPaid = $_POST['amount_paid'] ?? 0;
        $transactionId = $_POST['transaction_id'] ?? '';
        $paymentMethod = $_POST['payment_method'] ?? 'unknown';
        
        if (empty($reservationNumber)) {
            throw new Exception("Reservation number is required");
        }
        
        $result = completeBooking($reservationNumber, $amountPaid, $transactionId, $paymentMethod);
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>