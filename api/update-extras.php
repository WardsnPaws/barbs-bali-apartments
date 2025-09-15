<?php
// update-extras.php

require_once '../includes/core.php';
require_once 'core/price-calc.php';

$resNum = $_POST['reservation_number'] ?? '';
$selectedExtras = $_POST['extras'] ?? [];

if (!$resNum) {
    die("Missing reservation number.");
}

$pdo = getPDO();

// Get booking
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE reservation_number = :res LIMIT 1");
$stmt->execute([':res' => $resNum]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    die("Booking not found.");
}

$bookingId = $booking['id'];

// Get current extras before update
$currentExtrasStmt = $pdo->prepare("SELECT extra_id FROM booking_extras WHERE booking_id = ?");
$currentExtrasStmt->execute([$bookingId]);
$currentExtras = array_column($currentExtrasStmt->fetchAll(PDO::FETCH_ASSOC), 'extra_id');

// Remove old extras
$pdo->prepare("DELETE FROM booking_extras WHERE booking_id = ?")->execute([$bookingId]);

// Add new extras
if (!empty($selectedExtras)) {
    $insert = $pdo->prepare("INSERT INTO booking_extras (booking_id, extra_id) VALUES (?, ?)");
    foreach ($selectedExtras as $extraId) {
        $insert->execute([$bookingId, (int) $extraId]);
    }
}

// Recalculate total price with new extras
$bookingData = [
    'apartment_id' => $booking['apartment_id'],
    'checkin_date' => $booking['checkin_date'],
    'checkout_date' => $booking['checkout_date'],
    'sofa_bed' => !empty($booking['sofa_bed']),
    'extras' => $selectedExtras
];

// Calculate new total
if ($booking['apartment_id'] == 3) { // Combined booking
    // For combined bookings, calculate each apartment separately
    $booking1 = $bookingData;
    $booking1['apartment_id'] = 1;
    $booking2 = $bookingData;
    $booking2['apartment_id'] = 2;
    
    $price1 = calculateBookingTotal($booking1);
    $price2 = calculateBookingTotal($booking2);
    $newTotal = $price1['total'] + $price2['total'];
} else {
    $priceInfo = calculateBookingTotal($bookingData);
    $newTotal = $priceInfo['total'];
}

// Update the booking total in database
$updateTotal = $pdo->prepare("UPDATE bookings SET total_price = ? WHERE id = ?");
$updateTotal->execute([$newTotal, $bookingId]);

// Check if we need to collect payment immediately (within 90 days)
$checkinDate = new DateTime($booking['checkin_date']);
$today = new DateTime();
$daysUntilCheckin = $today->diff($checkinDate)->days;

// Get how much has already been paid
$paidStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE booking_id = ?");
$paidStmt->execute([$bookingId]);
$alreadyPaid = (float) $paidStmt->fetchColumn();

$balanceOwed = $newTotal - $alreadyPaid;

// If check-in is within 90 days and there's a balance, they need to pay now
if ($daysUntilCheckin <= 90 && $balanceOwed > 0) {
    // Store the updated booking info in session for payment
    session_start();
    $_SESSION['extras_payment'] = [
        'reservation_number' => $resNum,
        'balance_owed' => $balanceOwed,
        'booking_total' => $newTotal,
        'guest_name' => $booking['guest_first_name'] . ' ' . $booking['guest_last_name']
    ];
    
    // Redirect to payment page
    header("Location: extras-payment.php?res=" . urlencode($resNum));
    exit;
} else {
    // Just redirect back to the dashboard with success message
    header("Location: my-booking.php?res=" . urlencode($resNum) . "&updated=1");
    exit;
}