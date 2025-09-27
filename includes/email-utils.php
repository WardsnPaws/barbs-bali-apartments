<?php
// includes/email-utils.php - Complete version with email schedule tracking

require_once __DIR__ . '/../config/config.php';

function markEmailAsSent($emailScheduleId) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("UPDATE email_schedule SET sent_status = 'sent', sent_timestamp = NOW() WHERE id = ?");
        $result = $stmt->execute([$emailScheduleId]);
     // Defensive vendor loading - don't assume it always works
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';
    
    if (file_exists($vendorPath)) {
        require_once $vendorPath;
    }
    
    // Double-check it actually loaded
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log("WARNING: PHPMailer unavailable - using basic mail() fallback");
        define('PHPMAILER_UNAVAILABLE', true);
    }
}   
        if ($result) {
            error_log("Email schedule ID $emailScheduleId marked as sent");
            return true;
        } else {
            error_log("Failed to mark email schedule ID $emailScheduleId as sent");
            return false;
        }
    } catch (Exception $e) {
        error_log("Error marking email as sent: " . $e->getMessage());
        return false;
    }
}

function sendConfirmationEmail($guestEmail, $reservationNumber, $booking, $totalPrice, $amountPaid, $emailScheduleId = null) {
    try {
        // Determine if this is a deposit or full payment
        $isDeposit = $amountPaid < $totalPrice;
        $balanceRemaining = $totalPrice - $amountPaid;
        
        // Get apartment name
        $apartmentName = '';
        switch($booking['apartment_id']) {
            case 1:
                $apartmentName = 'Apartment 6205 (Ocean View)';
                break;
            case 2:
                $apartmentName = 'Apartment 6207 (Garden View)';
                break;
            case 3:
                $apartmentName = 'Both Apartments (6205 & 6207)';
                break;
            default:
                $apartmentName = 'Selected Apartment';
        }

        // Format dates
        $checkinFormatted = date('l, F j, Y', strtotime($booking['checkin_date']));
        $checkoutFormatted = date('l, F j, Y', strtotime($booking['checkout_date']));
        
        // Calculate nights
        $nights = (strtotime($booking['checkout_date']) - strtotime($booking['checkin_date'])) / (24 * 3600);

        // Email subject
        $subject = "Booking Confirmation - Barbs Bali Apartments - " . $reservationNumber;

        // Email body
        $emailBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2e8b57; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .booking-details { background: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
                .detail-row:last-child { border-bottom: none; }
                .highlight { background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 10px 0; }
                .payment-info { background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { background: #333; color: white; padding: 15px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Booking Confirmed!</h1>
                    <p>Thank you for choosing Barbs Bali Apartments</p>
                </div>
                
                <div class='content'>
                    <p>Dear " . htmlspecialchars($booking['guest_first_name']) . " " . htmlspecialchars($booking['guest_last_name']) . ",</p>
                    
                    <p>Your booking has been confirmed! Below are your reservation details:</p>
                    
                    <div class='booking-details'>
                        <h3>Reservation Details</h3>
                        <div class='detail-row'>
                            <strong>Reservation Number:</strong>
                            <span>" . htmlspecialchars($reservationNumber) . "</span>
                        </div>
                        <div class='detail-row'>
                            <strong>Guest Name:</strong>
                            <span>" . htmlspecialchars($booking['guest_first_name']) . " " . htmlspecialchars($booking['guest_last_name']) . "</span>
                        </div>
                        <div class='detail-row'>
                            <strong>Email:</strong>
                            <span>" . htmlspecialchars($guestEmail) . "</span>
                        </div>
                        <div class='detail-row'>
                            <strong>Apartment:</strong>
                            <span>" . htmlspecialchars($apartmentName) . "</span>
                        </div>
                        <div class='detail-row'>
                            <strong>Check-in:</strong>
                            <span>" . $checkinFormatted . "</span>
                        </div>
                        <div class='detail-row'>
                            <strong>Check-out:</strong>
                            <span>" . $checkoutFormatted . "</span>
                        </div>
                        <div class='detail-row'>
                            <strong>Duration:</strong>
                            <span>" . $nights . " night" . ($nights != 1 ? 's' : '') . "</span>
                        </div>
                    </div>
                    
                    <div class='payment-info'>
                        <h3>Payment Information</h3>
                        <div class='detail-row'>
                            <strong>Total Booking Price:</strong>
                            <span>$" . number_format($totalPrice, 2) . " AUD</span>
                        </div>
                        <div class='detail-row'>
                            <strong>Amount Paid Today:</strong>
                            <span>$" . number_format($amountPaid, 2) . " AUD</span>
                        </div>";
                        
        if ($isDeposit) {
            $emailBody .= "
                        <div class='detail-row'>
                            <strong>Balance Remaining:</strong>
                            <span>$" . number_format($balanceRemaining, 2) . " AUD</span>
                        </div>
                        <div class='highlight'>
                            <strong>Payment Schedule:</strong><br>
                            You have paid the deposit today. The remaining balance of $" . number_format($balanceRemaining, 2) . " AUD is due 7 days before your check-in date. We will send you a payment reminder.
                        </div>";
        } else {
            $emailBody .= "
                        <div class='highlight'>
                            <strong>Payment Complete:</strong><br>
                            Your booking is fully paid. No further payment is required.
                        </div>";
        }
        
        $emailBody .= "
                    </div>
                    
                    <div class='highlight'>
                        <h3>Important Information</h3>
                        <ul>
                            <li><strong>Check-in time:</strong> 3:00 PM</li>
                            <li><strong>Check-out time:</strong> 11:00 AM</li>
                            <li><strong>Address:</strong> Jl. Pantai Berawa, Canggu, Bali</li>
                            <li><strong>Contact:</strong> +62 XXX-XXX-XXXX</li>
                        </ul>
                        <p>We will send you detailed check-in instructions 1 day before your arrival.</p>
                    </div>
                    
                    <p>If you have any questions about your booking, please contact us with your reservation number: <strong>" . htmlspecialchars($reservationNumber) . "</strong></p>
                    
                    <p>We look forward to welcoming you to Barbs Bali Apartments!</p>
                    
                    <p>Warm regards,<br>
                    The Barbs Bali Team</p>
                </div>
                
                <div class='footer'>
                    <p>&copy; 2025 Barbs Bali Apartments | Canggu, Bali, Indonesia</p>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";

        // Send email using PHPMailer or mail function
        if (function_exists('sendEmail')) {
            $emailSent = sendEmail($guestEmail, $subject, $emailBody);
        } else {
            // Fallback to PHP mail function
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Barbs Bali Apartments <bookings@barbsbali.com>" . "\r\n";
            
            $emailSent = mail($guestEmail, $subject, $emailBody, $headers);
        }

        if ($emailSent) {
            error_log("Confirmation email sent successfully to: $guestEmail");
            
            // Mark email as sent in schedule if ID provided
            if ($emailScheduleId) {
                markEmailAsSent($emailScheduleId);
            }
            
            return true;
        } else {
            error_log("Failed to send confirmation email to: $guestEmail");
            return false;
        }

    } catch (Exception $e) {
        error_log("Error sending confirmation email: " . $e->getMessage());
        return false;
    }
}

function sendBalanceReminderEmail($guestEmail, $reservationNumber, $booking, $balanceAmount, $emailScheduleId = null) {
    try {
        // Get apartment name
        $apartmentName = '';
        switch($booking['apartment_id']) {
            case 1:
                $apartmentName = 'Apartment 6205 (Ocean View)';
                break;
            case 2:
                $apartmentName = 'Apartment 6207 (Garden View)';
                break;
            case 3:
                $apartmentName = 'Both Apartments (6205 & 6207)';
                break;
            default:
                $apartmentName = 'Selected Apartment';
        }

        $checkinFormatted = date('l, F j, Y', strtotime($booking['checkin_date']));
        
        $subject = "Payment Reminder - Barbs Bali Apartments - " . $reservationNumber;
        
        $emailBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ff6b35; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .payment-due { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #ffc107; }
                .booking-details { background: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .footer { background: #333; color: white; padding: 15px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Payment Reminder</h1>
                    <p>Balance Due for Your Upcoming Stay</p>
                </div>
                
                <div class='content'>
                    <p>Dear " . htmlspecialchars($booking['guest_first_name']) . " " . htmlspecialchars($booking['guest_last_name']) . ",</p>
                    
                    <p>This is a friendly reminder that your check-in date is approaching and the remaining balance for your booking is now due.</p>
                    
                    <div class='payment-due'>
                        <h3>Payment Due</h3>
                        <p><strong>Amount Due: $" . number_format($balanceAmount, 2) . " AUD</strong></p>
                        <p><strong>Due Date: 7 days before check-in</strong></p>
                    </div>
                    
                    <div class='booking-details'>
                        <h3>Booking Details</h3>
                        <p><strong>Reservation:</strong> " . htmlspecialchars($reservationNumber) . "</p>
                        <p><strong>Apartment:</strong> " . htmlspecialchars($apartmentName) . "</p>
                        <p><strong>Check-in:</strong> " . $checkinFormatted . "</p>
                    </div>
                    
                    <p>To complete your payment, please visit our payment portal: <a href='https://barbsbali.com/pay-balance'>Pay Balance</a></p>
                    
                    <p>If you have any questions, please contact us with your reservation number.</p>
                    
                    <p>We look forward to your stay!</p>
                    
                    <p>Best regards,<br>
                    The Barbs Bali Team</p>
                </div>
                
                <div class='footer'>
                    <p>&copy; 2025 Barbs Bali Apartments | Canggu, Bali, Indonesia</p>
                </div>
            </div>
        </body>
        </html>";

        if (function_exists('sendEmail')) {
            $emailSent = sendEmail($guestEmail, $subject, $emailBody);
        } else {
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Barbs Bali Apartments <bookings@barbsbali.com>" . "\r\n";
            
            $emailSent = mail($guestEmail, $subject, $emailBody, $headers);
        }

        if ($emailSent && $emailScheduleId) {
            markEmailAsSent($emailScheduleId);
        }

        return $emailSent;

    } catch (Exception $e) {
        error_log("Error sending balance reminder email: " . $e->getMessage());
        return false;
    }
}

function sendCheckinReminderEmail($guestEmail, $reservationNumber, $booking, $emailScheduleId = null) {
    try {
        $apartmentName = '';
        switch($booking['apartment_id']) {
            case 1:
                $apartmentName = 'Apartment 6205 (Ocean View)';
                break;
            case 2:
                $apartmentName = 'Apartment 6207 (Garden View)';
                break;
            case 3:
                $apartmentName = 'Both Apartments (6205 & 6207)';
                break;
            default:
                $apartmentName = 'Selected Apartment';
        }

        $checkinFormatted = date('l, F j, Y', strtotime($booking['checkin_date']));
        
        $subject = "Check-in Tomorrow - Barbs Bali Apartments - " . $reservationNumber;
        
        $emailBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .checkin-info { background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .important { background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
                .footer { background: #333; color: white; padding: 15px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Ready for Check-in!</h1>
                    <p>Your stay begins tomorrow</p>
                </div>
                
                <div class='content'>
                    <p>Dear " . htmlspecialchars($booking['guest_first_name']) . " " . htmlspecialchars($booking['guest_last_name']) . ",</p>
                    
                    <p>We're excited to welcome you tomorrow to Barbs Bali Apartments!</p>
                    
                    <div class='checkin-info'>
                        <h3>Check-in Information</h3>
                        <p><strong>Date:</strong> " . $checkinFormatted . "</p>
                        <p><strong>Time:</strong> 3:00 PM onwards</p>
                        <p><strong>Apartment:</strong> " . htmlspecialchars($apartmentName) . "</p>
                        <p><strong>Address:</strong> Jl. Pantai Berawa, Canggu, Bali</p>
                    </div>
                    
                    <div class='important'>
                        <h3>Important Reminders</h3>
                        <ul>
                            <li>Please bring a valid ID for check-in</li>
                            <li>Check-in is available from 3:00 PM</li>
                            <li>Our contact number: +62 XXX-XXX-XXXX</li>
                            <li>WiFi details will be provided upon arrival</li>
                        </ul>
                    </div>
                    
                    <p>If you have any questions or need assistance, please don't hesitate to contact us.</p>
                    
                    <p>See you tomorrow!</p>
                    
                    <p>Best regards,<br>
                    The Barbs Bali Team</p>
                </div>
                
                <div class='footer'>
                    <p>&copy; 2025 Barbs Bali Apartments | Canggu, Bali, Indonesia</p>
                </div>
            </div>
        </body>
        </html>";

        if (function_exists('sendEmail')) {
            $emailSent = sendEmail($guestEmail, $subject, $emailBody);
        } else {
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Barbs Bali Apartments <bookings@barbsbali.com>" . "\r\n";
            
            $emailSent = mail($guestEmail, $subject, $emailBody, $headers);
        }

        if ($emailSent && $emailScheduleId) {
            markEmailAsSent($emailScheduleId);
        }

        return $emailSent;

    } catch (Exception $e) {
        error_log("Error sending check-in reminder email: " . $e->getMessage());
        return false;
    }
}

// Generic email sending function (if using PHPMailer)
function sendEmail($to, $subject, $body) {
    try {
        // This is a placeholder - implement your actual email sending logic here
        // Could use PHPMailer, SendGrid, or other email service
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Barbs Bali Apartments <bookings@barbsbali.com>" . "\r\n";
        
        return mail($to, $subject, $body, $headers);
        
    } catch (Exception $e) {
        error_log("Error in sendEmail function: " . $e->getMessage());
        return false;
    }
}
?>