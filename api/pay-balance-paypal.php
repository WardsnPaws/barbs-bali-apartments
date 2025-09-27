<?php
// api/pay-balance-paypal.php - PayPal balance payment processing

require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/email-utils.php';

header('Content-Type: application/json');

try {
    // Get and validate input data
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data received');
    }

    // Validate required fields
    if (!isset($data['amount'], $data['reservation'], $data['paypal_details'])) {
        throw new Exception('Missing required payment data');
    }

    $amount = floatval($data['amount']);
    $reservation = trim($data['reservation']);
    $paypalDetails = $data['paypal_details'];

    // Basic validation
    if ($amount < 1) {
        throw new Exception('Invalid payment amount');
    }

    if (empty($reservation)) {
        throw new Exception('Invalid reservation number');
    }

    if (!isset($paypalDetails['id'])) {
        throw new Exception('Invalid PayPal transaction details');
    }

    // Get booking details from database
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE reservation_number = ? LIMIT 1");
    $stmt->execute([$reservation]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception('Reservation not found');
    }

    // Calculate current balance to verify payment amount
    $paidStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE booking_id = ?");
    $paidStmt->execute([$booking['id']]);
    $alreadyPaid = (float) $paidStmt->fetchColumn();
    $balanceLeft = max((float)$booking['total_price'] - $alreadyPaid, 0);

    // Verify payment amount doesn't exceed balance owed
    if ($amount > $balanceLeft + 0.01) { // Allow small rounding differences
        throw new Exception('Payment amount exceeds balance owed');
    }

    // Record the payment in database
    $insertPayment = $pdo->prepare("
        INSERT INTO payments (booking_id, amount, method, note, paid_at) 
        VALUES (?, ?, 'paypal', ?, NOW())
    ");
    
    $paymentNote = "Balance payment via PayPal - Transaction ID: " . $paypalDetails['id'];
    $insertPayment->execute([$booking['id'], $amount, $paymentNote]);

    // Calculate new balance after this payment
    $newBalance = max($balanceLeft - $amount, 0);

    // Send payment confirmation email
    try {
        $emailSubject = "Payment Received - Reservation " . $reservation;
        
        $apartmentName = $booking['apartment_id'] == 1 ? 'Apartment 6205' : 
                        ($booking['apartment_id'] == 2 ? 'Apartment 6207' : 'Both Apartments');
        
        $emailBody = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #2e8b57;'>Payment Confirmation</h2>
            
            <p>Dear {$booking['guest_first_name']},</p>
            
            <p>We have successfully received your balance payment via PayPal.</p>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3>Payment Details</h3>
                <p><strong>Reservation Number:</strong> {$reservation}</p>
                <p><strong>Payment Amount:</strong> $" . number_format($amount, 2) . " AUD</p>
                <p><strong>Payment Method:</strong> PayPal</p>
                <p><strong>Transaction ID:</strong> {$paypalDetails['id']}</p>
                <p><strong>Payment Date:</strong> " . date('F j, Y g:i A') . "</p>
            </div>
            
            <div style='background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3>Booking Summary</h3>
                <p><strong>Apartment:</strong> {$apartmentName}</p>
                <p><strong>Check-in:</strong> " . date('F j, Y', strtotime($booking['checkin_date'])) . "</p>
                <p><strong>Check-out:</strong> " . date('F j, Y', strtotime($booking['checkout_date'])) . "</p>
                <p><strong>Total Booking Amount:</strong> $" . number_format($booking['total_price'], 2) . " AUD</p>
                <p><strong>Remaining Balance:</strong> $" . number_format($newBalance, 2) . " AUD</p>
            </div>";
        
        if ($newBalance <= 0.01) {
            $emailBody .= "
            <div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0; color: #155724;'>
                <h3>🎉 Booking Fully Paid!</h3>
                <p>Congratulations! Your booking is now fully paid. We look forward to welcoming you to Barbs Bali Apartments!</p>
            </div>";
        } else {
            $emailBody .= "
            <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; color: #856404;'>
                <h3>Outstanding Balance</h3>
                <p>You still have a remaining balance of $" . number_format($newBalance, 2) . " AUD.</p>
                <p>You can pay this anytime through your booking management portal.</p>
            </div>";
        }
        
        $emailBody .= "
            <p>If you have any questions about your booking or payment, please don't hesitate to contact us.</p>
            
            <p>Thank you for choosing Barbs Bali Apartments!</p>
            
            <p>Best regards,<br>
            <strong>The Barbs Bali Team</strong><br>
            Your home away from home in Bali</p>
        </div>";

        // Send email using your existing email system
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Barbs Bali Apartments <bookings@barbsbaliapartments.com>" . "\r\n";
        
        $emailSent = mail($booking['guest_email'], $emailSubject, $emailBody, $headers);
        
        if (!$emailSent) {
            error_log("Failed to send balance payment confirmation email to: " . $booking['guest_email']);
        }
        
    } catch (Exception $emailError) {
        error_log("Balance payment email error: " . $emailError->getMessage());
        // Don't fail the payment for email issues
    }

    // Log successful payment
    error_log("PayPal balance payment successful: $amount AUD for reservation $reservation");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'payment_amount' => $amount,
        'new_balance' => $newBalance,
        'transaction_id' => $paypalDetails['id'],
        'fully_paid' => $newBalance <= 0.01
    ]);

} catch (Exception $e) {
    error_log("PayPal balance payment error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>