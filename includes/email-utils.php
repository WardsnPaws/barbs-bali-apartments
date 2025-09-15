<?php
// includes/email-utils.php - Fixed version with proper functionality

require_once __DIR__ . '/../config/config.php';

// Include PHPMailer - adjust path as needed
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Fallback path
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Load email template from templates/email directory
 */
function loadTemplate($templateFile) {
    $templatePath = __DIR__ . "/../templates/email/{$templateFile}.html";
    
    if (!file_exists($templatePath)) {
        error_log("Email template not found: $templatePath");
        return '';
    }
    
    return file_get_contents($templatePath);
}

/**
 * Replace placeholders in email template
 */
function replacePlaceholders($template, $placeholders) {
    foreach ($placeholders as $key => $value) {
        $template = str_replace("{{{$key}}}", $value, $template);
    }
    return $template;
}

/**
 * Send email via SMTP using PHPMailer
 */
function sendEmailSMTP($to, $subject, $htmlBody) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $result = $mail->send();
        
        if ($result) {
            error_log("Email sent successfully to: $to");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        error_log("Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send booking confirmation email
 */
function sendConfirmationEmail($toEmail, $resNum, $booking, $total, $paid, $emailId = null) {
    try {
        $template = loadTemplate('booking-confirmation');
        
        if (empty($template)) {
            error_log("Failed to load booking confirmation template");
            return false;
        }

        // Get database connection for extras lookup
        $pdo = getPDO();

        // Determine apartment display name
        $apartmentDisplay = 'Unknown';
        if ($booking['apartment_id'] == 1) {
            $apartmentDisplay = '6205';
        } elseif ($booking['apartment_id'] == 2) {
            $apartmentDisplay = '6207';
        } elseif ($booking['apartment_id'] == 3) {
            $apartmentDisplay = 'Both (6205 & 6207)';
        }

        // Fetch extras for this booking
        $extrasStmt = $pdo->prepare("
            SELECT e.name, e.price, e.per_night 
            FROM booking_extras be
            JOIN extras e ON be.extra_id = e.id
            WHERE be.booking_id = (
                SELECT id FROM bookings WHERE reservation_number = ? LIMIT 1
            )
        ");
        $extrasStmt->execute([$resNum]);
        $extras = $extrasStmt->fetchAll(PDO::FETCH_ASSOC);

        // Build extras list
        $extrasList = '';
        if (!empty($extras)) {
            foreach ($extras as $extra) {
                $priceDisplay = '$' . number_format($extra['price'], 2);
                if ($extra['per_night']) {
                    $priceDisplay .= ' per night';
                }
                $extrasList .= "<li>" . htmlspecialchars($extra['name']) . " (" . $priceDisplay . ")</li>";
            }
        } else {
            $extrasList = "<li>No extras selected</li>";
        }

        // Calculate balance
        $balance = max($total - $paid, 0);

        // Prepare all placeholders
        $placeholders = [
            'guestfirstname'      => htmlspecialchars($booking['guest_first_name']),
            'guestlastname'       => htmlspecialchars($booking['guest_last_name']),
            'reservationnumber'   => htmlspecialchars($resNum),
            'arrivaldatelong'     => date('l, j F Y', strtotime($booking['checkin_date'])),
            'departuredatelong'   => date('l, j F Y', strtotime($booking['checkout_date'])),
            'apartmentnumber'     => $apartmentDisplay,
            'grandtotal'          => number_format($total, 2),
            'amountpaid'          => number_format($paid, 2),
            'balance'             => number_format($balance, 2),
            'includedextras'      => $extrasList,
            'paymentlink_anyamount' => 'https://barbsbaliapartments.com/booking/public/my-booking.php?res=' . urlencode($resNum),
            'bookingmanagementlink' => 'https://barbsbaliapartments.com/booking/public/my-booking.php?res=' . urlencode($resNum)
        ];

        // Add email tracking if email ID provided
        if ($emailId) {
            $trackingPixel = "<img src=\"https://barbsbaliapartments.com/booking/api/email-tracker.php?email_id={$emailId}\" width=\"1\" height=\"1\" style=\"display:none;\" />";
            $template = str_replace('{{email_id}}', $emailId, $template);
            $template = $trackingPixel . $template;
        } else {
            // Remove tracking pixel placeholder if no email ID
            $template = str_replace('<img src="http://localhost/booking/email-tracker.php?email_id={{email_id}}" width="1" height="1" style="display:none;" />', '', $template);
        }

        // Replace placeholders
        $htmlBody = replacePlaceholders($template, $placeholders);

        // Send the email
        $result = sendEmailSMTP($toEmail, "Booking Confirmation – Barbs Bali Apartments", $htmlBody);
        
        if ($result) {
            error_log("Confirmation email sent successfully to: $toEmail for reservation: $resNum");
        } else {
            error_log("Failed to send confirmation email to: $toEmail for reservation: $resNum");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error in sendConfirmationEmail: " . $e->getMessage());
        return false;
    }
}

/**
 * Send balance reminder email
 */
function sendBalanceReminderEmail($toEmail, $resNum, $booking, $total, $paid, $emailId = null) {
    try {
        $template = loadTemplate('balance-reminder');
        
        if (empty($template)) {
            error_log("Failed to load balance reminder template");
            return false;
        }

        $balance = max($total - $paid, 0);
        
        // Only send if there's actually a balance
        if ($balance <= 0) {
            error_log("No balance due for reservation $resNum, skipping reminder email");
            return true;
        }

        $apartmentDisplay = 'Unknown';
        if ($booking['apartment_id'] == 1) {
            $apartmentDisplay = '6205';
        } elseif ($booking['apartment_id'] == 2) {
            $apartmentDisplay = '6207';
        } elseif ($booking['apartment_id'] == 3) {
            $apartmentDisplay = 'Both (6205 & 6207)';
        }

        $placeholders = [
            'guestfirstname'      => htmlspecialchars($booking['guest_first_name']),
            'guestlastname'       => htmlspecialchars($booking['guest_last_name']),
            'reservationnumber'   => htmlspecialchars($resNum),
            'arrivaldatelong'     => date('l, j F Y', strtotime($booking['checkin_date'])),
            'departuredatelong'   => date('l, j F Y', strtotime($booking['checkout_date'])),
            'apartmentnumber'     => $apartmentDisplay,
            'grandtotal'          => number_format($total, 2),
            'amountpaid'          => number_format($paid, 2),
            'balance'             => number_format($balance, 2),
            'paymentlink_anyamount' => 'https://barbsbaliapartments.com/booking/public/my-booking.php?res=' . urlencode($resNum),
            'bookingmanagementlink' => 'https://barbsbaliapartments.com/booking/public/my-booking.php?res=' . urlencode($resNum)
        ];

        // Add email tracking if email ID provided
        if ($emailId) {
            $trackingPixel = "<img src=\"https://barbsbaliapartments.com/booking/api/email-tracker.php?email_id={$emailId}\" width=\"1\" height=\"1\" style=\"display:none;\" />";
            $template = str_replace('{{email_id}}', $emailId, $template);
            $template = $trackingPixel . $template;
        } else {
            $template = str_replace('<img src="http://localhost/booking/email-tracker.php?email_id={{email_id}}" width="1" height="1" style="display:none;" />', '', $template);
        }

        $htmlBody = replacePlaceholders($template, $placeholders);
        
        $result = sendEmailSMTP($toEmail, "Balance Reminder – Barbs Bali Apartments", $htmlBody);
        
        if ($result) {
            error_log("Balance reminder email sent successfully to: $toEmail for reservation: $resNum");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error in sendBalanceReminderEmail: " . $e->getMessage());
        return false;
    }
}

/**
 * Send check-in reminder email
 */
function sendCheckinReminderEmail($toEmail, $resNum, $booking, $emailId = null) {
    try {
        $template = loadTemplate('checkin-reminder');
        
        if (empty($template)) {
            error_log("Failed to load checkin reminder template");
            return false;
        }

        $apartmentDisplay = 'Unknown';
        if ($booking['apartment_id'] == 1) {
            $apartmentDisplay = '6205';
        } elseif ($booking['apartment_id'] == 2) {
            $apartmentDisplay = '6207';
        } elseif ($booking['apartment_id'] == 3) {
            $apartmentDisplay = 'Both (6205 & 6207)';
        }

        $placeholders = [
            'guestfirstname'      => htmlspecialchars($booking['guest_first_name']),
            'guestlastname'       => htmlspecialchars($booking['guest_last_name']),
            'reservationnumber'   => htmlspecialchars($resNum),
            'arrivaldatelong'     => date('l, j F Y', strtotime($booking['checkin_date'])),
            'departuredatelong'   => date('l, j F Y', strtotime($booking['checkout_date'])),
            'apartmentnumber'     => $apartmentDisplay,
            'bookingmanagementlink' => 'https://barbsbaliapartments.com/booking/public/my-booking.php?res=' . urlencode($resNum)
        ];

        if ($emailId) {
            $trackingPixel = "<img src=\"https://barbsbaliapartments.com/booking/api/email-tracker.php?email_id={$emailId}\" width=\"1\" height=\"1\" style=\"display:none;\" />";
            $template = str_replace('{{email_id}}', $emailId, $template);
            $template = $trackingPixel . $template;
        } else {
            $template = str_replace('<img src="http://localhost/booking/email-tracker.php?email_id={{email_id}}" width="1" height="1" style="display:none;" />', '', $template);
        }

        $htmlBody = replacePlaceholders($template, $placeholders);
        
        $result = sendEmailSMTP($toEmail, "Your Stay is Coming Up – Barbs Bali Apartments", $htmlBody);
        
        if ($result) {
            error_log("Checkin reminder email sent successfully to: $toEmail for reservation: $resNum");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error in sendCheckinReminderEmail: " . $e->getMessage());
        return false;
    }
}

/**
 * Send housekeeping notice email
 */
function sendHousekeepingNoticeEmail($toEmail, $resNum, $booking, $emailId = null) {
    try {
        $template = loadTemplate('housekeeping-notice');
        
        if (empty($template)) {
            error_log("Failed to load housekeeping notice template");
            return false;
        }

        $apartmentDisplay = 'Unknown';
        if ($booking['apartment_id'] == 1) {
            $apartmentDisplay = '6205';
        } elseif ($booking['apartment_id'] == 2) {
            $apartmentDisplay = '6207';
        } elseif ($booking['apartment_id'] == 3) {
            $apartmentDisplay = 'Both (6205 & 6207)';
        }

        $placeholders = [
            'guestfirstname'      => htmlspecialchars($booking['guest_first_name']),
            'guestlastname'       => htmlspecialchars($booking['guest_last_name']),
            'guestemail'          => htmlspecialchars($booking['guest_email']),
            'reservationnumber'   => htmlspecialchars($resNum),
            'arrivaldatelong'     => date('l, j F Y', strtotime($booking['checkin_date'])),
            'departuredatelong'   => date('l, j F Y', strtotime($booking['checkout_date'])),
            'apartmentnumber'     => $apartmentDisplay,
            'grandtotal'          => number_format($booking['total_price'], 2)
        ];

        if ($emailId) {
            $trackingPixel = "<img src=\"https://barbsbaliapartments.com/booking/api/email-tracker.php?email_id={$emailId}\" width=\"1\" height=\"1\" style=\"display:none;\" />";
            $template = str_replace('{{email_id}}', $emailId, $template);
            $template = $trackingPixel . $template;
        } else {
            $template = str_replace('<img src="http://localhost/booking/email-tracker.php?email_id={{email_id}}" width="1" height="1" style="display:none;" />', '', $template);
        }

        $htmlBody = replacePlaceholders($template, $placeholders);
        
        $result = sendEmailSMTP($toEmail, "Upcoming Guest Arrival – Housekeeping Notice", $htmlBody);
        
        if ($result) {
            error_log("Housekeeping notice email sent successfully to: $toEmail for reservation: $resNum");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error in sendHousekeepingNoticeEmail: " . $e->getMessage());
        return false;
    }
}