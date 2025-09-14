<?php
require_once 'core.php';
require_once 'email-utils.php';

$pdo = getPDO();

$stmt = $pdo->prepare("SELECT * FROM email_schedule WHERE send_date <= CURDATE() AND sent_status = 'scheduled'");
$stmt->execute();
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($emails as $email) {
    $bookingId = $email['booking_id'];
    $emailType = $email['email_type'];
    $emailId   = $email['id'];

    // Fetch booking details
    $bookingStmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) continue;

    $recipient = $booking['guest_email'];
    $subject = "Barbs Bali – " . ucfirst(str_replace('-', ' ', $emailType));

    // Load and modify email template
    $templatePath = __DIR__ . "/email-templates/{$emailType}.html";
    if (!file_exists($templatePath)) continue;
    $template = file_get_contents($templatePath);

    // Inject tracker pixel
    $tracker = "<img src=\"http://localhost/booking/email-tracker.php?email_id={$emailId}\" width=\"1\" height=\"1\" style=\"display:none;\" />";
    $template .= $tracker;

    // Replace placeholders
    $template = str_replace("{{guestfirstname}}", $booking['guest_first_name'], $template);
    $template = str_replace("{{guestlastname}}", $booking['guest_last_name'], $template);
    $template = str_replace("{{reservationnumber}}", $booking['reservation_number'], $template);
    $template = str_replace("{{arrivaldatelong}}", date('l, F j, Y', strtotime($booking['checkin_date'])), $template);
    $template = str_replace("{{departuredatelong}}", date('l, F j, Y', strtotime($booking['checkout_date'])), $template);
    $template = str_replace("{{apartmentnumber}}", $booking['apartment_id'] == 1 ? '6205' : ($booking['apartment_id'] == 2 ? '6207' : 'Both'), $template);
    $template = str_replace("{{grandtotal}}", "$" . number_format((float)$booking['total_price'], 2), $template);

    // You may calculate these from payments table if needed:
    $template = str_replace("{{amountpaid}}", "$0.00", $template);
    $template = str_replace("{{balance}}", "$0.00", $template);

    // Send the email
    if (sendEmailSMTP($recipient, $subject, $template)) {
        $pdo->prepare("UPDATE email_schedule SET sent_status = 'sent', sent_timestamp = NOW() WHERE id = ?")
            ->execute([$emailId]);
    }
}
