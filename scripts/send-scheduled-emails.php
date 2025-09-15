<?php
// scripts/send-scheduled-emails.php - Enhanced email scheduling system
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/email-utils.php';

$pdo = getPDO();

echo "Starting scheduled email processing...\n";

// Get emails that are due to be sent
$stmt = $pdo->prepare("
    SELECT es.*, b.* 
    FROM email_schedule es
    JOIN bookings b ON es.booking_id = b.id
    WHERE es.send_date <= CURDATE() 
    AND es.sent_status = 'scheduled'
    ORDER BY es.send_date ASC, es.id ASC
");
$stmt->execute();
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($emails)) {
    echo "No scheduled emails to send.\n";
    exit;
}

echo "Found " . count($emails) . " scheduled emails to send.\n";

$sentCount = 0;
$failedCount = 0;

foreach ($emails as $email) {
    $bookingId = $email['booking_id'];
    $emailType = $email['email_type'];
    $emailId = $email['id'];
    $resNum = $email['reservation_number'];
    $guestEmail = $email['guest_email'];
    
    echo "Processing {$emailType} email for reservation {$resNum} (Email ID: {$emailId})...\n";

    try {
        // Prepare booking data for email functions
        $bookingData = [
            'guest_first_name' => $email['guest_first_name'],
            'guest_last_name' => $email['guest_last_name'],
            'guest_email' => $email['guest_email'],
            'apartment_id' => $email['apartment_id'],
            'checkin_date' => $email['checkin_date'],
            'checkout_date' => $email['checkout_date'],
            'total_price' => $email['total_price']
        ];

        $emailSent = false;

        switch ($emailType) {
            case 'confirmation':
                // Get payment info for confirmation
                $paymentStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE booking_id = ?");
                $paymentStmt->execute([$bookingId]);
                $totalPaid = (float) $paymentStmt->fetchColumn();
                
                $emailSent = sendConfirmationEmail(
                    $guestEmail,
                    $resNum,
                    $bookingData,
                    (float)$email['total_price'],
                    $totalPaid,
                    $emailId
                );
                break;

            case 'balance_reminder':
                // Get current payment status
                $paymentStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE booking_id = ?");
                $paymentStmt->execute([$bookingId]);
                $totalPaid = (float) $paymentStmt->fetchColumn();
                
                // Only send if there's still a balance
                $balance = (float)$email['total_price'] - $totalPaid;
                if ($balance > 0.01) {
                    $emailSent = sendBalanceReminderEmail(
                        $guestEmail,
                        $resNum,
                        $bookingData,
                        (float)$email['total_price'],
                        $totalPaid,
                        $emailId
                    );
                } else {
                    echo "  No balance due, skipping reminder.\n";
                    $emailSent = true; // Mark as "sent" since no reminder needed
                }
                break;

            case 'checkin_reminder':
                $emailSent = sendCheckinReminderEmail(
                    $guestEmail,
                    $resNum,
                    $bookingData,
                    $emailId
                );
                break;

            case 'housekeeping_notice':
                // Send to housekeeping email instead of guest
                $housekeepingEmail = defined('HOUSEKEEPING_EMAIL') ? HOUSEKEEPING_EMAIL : 'housekeeping@barbsbaliapartments.com';
                
                $emailSent = sendHousekeepingNoticeEmail(
                    $housekeepingEmail,
                    $resNum,
                    $bookingData,
                    $emailId
                );
                break;

            default:
                echo "  Unknown email type: {$emailType}\n";
                continue 2;
        }

        if ($emailSent) {
            // Mark email as sent
            $updateStmt = $pdo->prepare("UPDATE email_schedule SET sent_status = 'sent', sent_timestamp = NOW() WHERE id = ?");
            $updateStmt->execute([$emailId]);
            
            $sentCount++;
            echo "  ✅ {$emailType} email sent successfully to {$guestEmail}\n";
        } else {
            // Mark as failed
            $updateStmt = $pdo->prepare("UPDATE email_schedule SET sent_status = 'failed', sent_timestamp = NOW() WHERE id = ?");
            $updateStmt->execute([$emailId]);
            
            $failedCount++;
            echo "  ❌ Failed to send {$emailType} email to {$guestEmail}\n";
        }

    } catch (Exception $e) {
        // Mark as failed and log error
        $updateStmt = $pdo->prepare("UPDATE email_schedule SET sent_status = 'failed', sent_timestamp = NOW() WHERE id = ?");
        $updateStmt->execute([$emailId]);
        
        $failedCount++;
        echo "  ❌ Exception sending {$emailType} email: " . $e->getMessage() . "\n";
        error_log("Email sending exception for email ID {$emailId}: " . $e->getMessage());
    }

    // Small delay to be nice to the SMTP server
    usleep(500000); // 0.5 second delay
}

echo "\nEmail processing complete:\n";
echo "✅ Sent: {$sentCount}\n";
echo "❌ Failed: {$failedCount}\n";
echo "📧 Total processed: " . ($sentCount + $failedCount) . "\n";

// Optional: Clean up old sent emails (older than 30 days)
$cleanupStmt = $pdo->prepare("DELETE FROM email_schedule WHERE sent_status = 'sent' AND sent_timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$cleanupStmt->execute();
$cleanedUp = $cleanupStmt->rowCount();

if ($cleanedUp > 0) {
    echo "🧹 Cleaned up {$cleanedUp} old email records\n";
}