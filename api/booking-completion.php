<?php
// api/complete-booking-fixed.php - Fixed to match your existing structure

session_start();
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/email-utils.php';

function completeBookingWithEmail($paymentDetails = []) {
    // Check if we have booking session data
    if (!isset($_SESSION['booking']) || !isset($_SESSION['amount_due'])) {
        error_log("Complete booking failed - no session data");
        return ['success' => false, 'message' => 'Booking session expired'];
    }
    
    $booking = $_SESSION['booking'];
    $amountDue = $_SESSION['amount_due'];
    
    try {
        $pdo = getPDO(); // Using your existing function from core.php
        $pdo->beginTransaction();
        
        // Generate reservation number
        $reservationNumber = generateReservationNumber();
        
        // Handle different booking types
        if (isset($booking['both_apartments']) && $booking['both_apartments']) {
            // Combined booking - create two separate bookings with same reservation number
            $bookingIds = [];
            
            // Create booking for apartment 6205 (ID 1)
            $booking6205 = $booking;
            $booking6205['apartment_id'] = 1;
            unset($booking6205['both_apartments'], $booking6205['apartment_ids']);
            
            $bookingId1 = insertBookingRecord($pdo, $booking6205, $reservationNumber, $amountDue / 2, $paymentDetails);
            $bookingIds[] = $bookingId1;
            
            // Create booking for apartment 6207 (ID 2)  
            $booking6207 = $booking;
            $booking6207['apartment_id'] = 2;
            unset($booking6207['both_apartments'], $booking6207['apartment_ids']);
            
            $bookingId2 = insertBookingRecord($pdo, $booking6207, $reservationNumber, $amountDue / 2, $paymentDetails);
            $bookingIds[] = $bookingId2;
            
            // Insert extras for both bookings
            if (!empty($booking['extras'])) {
                insertBookingExtras($pdo, $bookingId1, $booking['extras']);
                insertBookingExtras($pdo, $bookingId2, $booking['extras']); 
            }
            
            $mainBookingId = $bookingId1; // Use first booking for email
            
        } else {
            // Single apartment booking
            $bookingId = insertBookingRecord($pdo, $booking, $reservationNumber, $amountDue, $paymentDetails);
            
            // Insert extras
            if (!empty($booking['extras'])) {
                insertBookingExtras($pdo, $bookingId, $booking['extras']);
            }
            
            $mainBookingId = $bookingId;
        }
        
        $pdo->commit();
        
        // Send confirmation email using your existing function
        // Note: Your function signature is: sendConfirmationEmail($toEmail, $resNum, $booking, $total, $paid, $emailId)
        $emailResult = sendConfirmationEmail(
            $booking['guest_email'], 
            $reservationNumber, 
            $booking, 
            $amountDue, 
            $amountDue, // amount paid (for now assuming full payment)
            'booking-' . $mainBookingId
        );
        
        if (!$emailResult) {
            error_log("Booking saved but email failed for reservation: " . $reservationNumber);
        } else {
            error_log("Booking completed and email sent for reservation: " . $reservationNumber);
        }
        
        // Clear session data
        unset($_SESSION['booking'], $_SESSION['amount_due'], $_SESSION['price_breakdown']);
        
        return [
            'success' => true,
            'reservation_number' => $reservationNumber,
            'booking_id' => $mainBookingId,
            'email_sent' => $emailResult
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Complete booking error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function generateReservationNumber() {
    // Format: BARB-YYYYMMDD-XXX
    $date = date('Ymd');
    $random = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    return "BARB-{$date}-{$random}";
}

function insertBookingRecord($pdo, $booking, $reservationNumber, $totalAmount, $paymentDetails) {
    $sql = "INSERT INTO bookings (
        reservation_number, guest_first_name, guest_last_name, guest_email, guest_phone, guest_address,
        apartment_id, checkin_date, checkout_date, sofa_bed, total_amount, amount_paid, 
        payment_method, payment_reference, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $reservationNumber,
        $booking['guest_first_name'],
        $booking['guest_last_name'], 
        $booking['guest_email'],
        $booking['guest_phone'] ?? '',
        $booking['guest_address'] ?? '',
        $booking['apartment_id'],
        $booking['checkin_date'],
        $booking['checkout_date'],
        $booking['sofa_bed'] ? 1 : 0,
        $totalAmount,
        $totalAmount, // For now, assume full payment
        $paymentDetails['method'] ?? 'unknown',
        $paymentDetails['reference'] ?? '',
    ]);
    
    return $pdo->lastInsertId();
}

function insertBookingExtras($pdo, $bookingId, $extras) {
    if (empty($extras)) return;
    
    $sql = "INSERT INTO booking_extras (booking_id, extra_id) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    
    foreach ($extras as $extraId) {
        $stmt->execute([$bookingId, $extraId]);
    }
}

// If called directly (for testing)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $result = completeBookingWithEmail(['method' => 'test', 'reference' => 'TEST-REF']);
    header('Content-Type: application/json');
    echo json_encode($result);
}
?>