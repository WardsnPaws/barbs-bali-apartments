<?php
require_once 'core.php';
require_once 'core/price-calc.php';
require_once 'email-utils.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['booking'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input.']);
    exit;
}

$booking = $input['booking'];
$method  = $input['payment_method'] ?? 'paypal';

try {
    $pdo = getPDO();

    // Recalculate total to be safe
    $priceInfo = calculateBookingTotal($booking);
    $amount = $priceInfo['total'];
    $paid = $booking['is_deposit'] ? round($amount * DEPOSIT_RATE, 2) : $amount;

    // Ensure reservation number is set
    $resNum = $booking['reservation_number'] ?? strtoupper(uniqid('BB'));
    $booking['reservation_number'] = $resNum;

    // Insert booking into bookings table
    $stmt = $pdo->prepare("INSERT INTO bookings (
        reservation_number, apartment_id, guest_first_name, guest_last_name, guest_email,
        checkin_date, checkout_date, total_price, status
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')");

    $stmt->execute([
        $resNum,
        $booking['apartment_id'],
        $booking['guest_first_name'],
        $booking['guest_last_name'],
        $booking['guest_email'],
        $booking['checkin_date'],
        $booking['checkout_date'],
        $amount
    ]);

    $bookingId = $pdo->lastInsertId();

    // Insert any selected extras
    if (!empty($booking['extras'])) {
        $extrasStmt = $pdo->prepare("INSERT INTO booking_extras (booking_id, extra_id, quantity) VALUES (?, ?, 1)");
        foreach ($booking['extras'] as $extraId) {
            $extrasStmt->execute([$bookingId, $extraId]);
        }
    }

    // Insert payment record
    $pdo->prepare("INSERT INTO payments (booking_id, amount, note) VALUES (?, ?, ?)")
        ->execute([$bookingId, $paid, ucfirst($method) . ' payment received']);

    // Schedule all emails like Square flow does
    $emailStmt = $pdo->prepare("INSERT INTO email_schedule
        (booking_id, email_type, send_date, sent_status)
        VALUES (?, ?, ?, ?)");

    $today = date('Y-m-d');
    $checkinDate = new DateTime($booking['checkin_date']);
    $daysUntil = (new DateTime())->diff($checkinDate)->days;
    $depositOnly = $daysUntil > DEPOSIT_THRESHOLD_DAYS;

    // Schedule confirmation email for immediate sending
    $emailStmt->execute([$bookingId, 'confirmation', $today, 'scheduled']);

    // Schedule balance reminder if this was a deposit
    if ($depositOnly) {
        $balanceReminderDate = (clone $checkinDate)->modify('-90 days')->format('Y-m-d');
        $emailStmt->execute([$bookingId, 'balance_reminder', $balanceReminderDate, 'scheduled']);
    }

    // Schedule check-in reminder (14 days before)
    $checkinReminderDate = (clone $checkinDate)->modify('-14 days')->format('Y-m-d');
    $emailStmt->execute([$bookingId, 'checkin_reminder', $checkinReminderDate, 'scheduled']);

    // Schedule housekeeping notice (7 days before)
    $housekeepingDate = (clone $checkinDate)->modify('-7 days')->format('Y-m-d');
    $emailStmt->execute([$bookingId, 'housekeeping_notice', $housekeepingDate, 'scheduled']);

    // Send confirmation email immediately and mark as sent
    $confirmationEmailId = $pdo->lastInsertId(); // This will be the confirmation email ID
    
    // Get the actual confirmation email ID
    $emailIdStmt = $pdo->prepare("SELECT id FROM email_schedule WHERE booking_id = ? AND email_type = 'confirmation'");
    $emailIdStmt->execute([$bookingId]);
    $confirmationEmailId = $emailIdStmt->fetchColumn();

    sendConfirmationEmail(
        $booking['guest_email'],
        $resNum,
        $booking,
        $amount,
        $paid,
        $confirmationEmailId
    );

    // Mark confirmation email as sent
    $pdo->prepare("UPDATE email_schedule SET sent_status = 'sent', sent_timestamp = NOW() WHERE id = ?")
        ->execute([$confirmationEmailId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}