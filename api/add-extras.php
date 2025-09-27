<?php
// api/add-extras.php - Add extras to existing booking

require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/price-calc.php';

header('Content-Type: application/json');

try {
    $reservationNumber = $_POST['reservation_number'] ?? '';
    $selectedExtras = $_POST['extras'] ?? [];

    if (empty($reservationNumber)) {
        throw new Exception('Missing reservation number');
    }

    if (empty($selectedExtras)) {
        throw new Exception('No extras selected');
    }

    $pdo = getPDO();

    // Get booking
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE reservation_number = ? LIMIT 1");
    $stmt->execute([$reservationNumber]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception('Booking not found');
    }

    $bookingId = $booking['id'];

    // Add new extras (don't remove existing ones)
    $insertStmt = $pdo->prepare("INSERT IGNORE INTO booking_extras (booking_id, extra_id) VALUES (?, ?)");
    
    foreach ($selectedExtras as $extraId) {
        $extraId = (int) $extraId;
        if ($extraId > 0) {
            $insertStmt->execute([$bookingId, $extraId]);
        }
    }

    // Get all current extras for price recalculation
    $currentExtrasStmt = $pdo->prepare("SELECT extra_id FROM booking_extras WHERE booking_id = ?");
    $currentExtrasStmt->execute([$bookingId]);
    $allCurrentExtras = array_column($currentExtrasStmt->fetchAll(PDO::FETCH_ASSOC), 'extra_id');

    // Recalculate total price
    $bookingData = [
        'apartment_id' => $booking['apartment_id'],
        'checkin_date' => $booking['checkin_date'],
        'checkout_date' => $booking['checkout_date'],
        'sofa_bed' => !empty($booking['sofa_bed']),
        'extras' => $allCurrentExtras
    ];

    // Calculate new total
    if ($booking['apartment_id'] == 3) { // Combined booking
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

    // Update booking total
    $updateStmt = $pdo->prepare("UPDATE bookings SET total_price = ? WHERE id = ?");
    $updateStmt->execute([$newTotal, $bookingId]);

    // Check if payment is required immediately
    $checkinDate = new DateTime($booking['checkin_date']);
    $today = new DateTime();
    $daysUntilCheckin = $today->diff($checkinDate)->days;

    // Get current payment status
    $paidStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE booking_id = ?");
    $paidStmt->execute([$bookingId]);
    $alreadyPaid = (float) $paidStmt->fetchColumn();

    $balanceOwed = $newTotal - $alreadyPaid;
    $requiresImmediatePayment = ($daysUntilCheckin <= 90 && $balanceOwed > 0);

    if ($requiresImmediatePayment) {
        // Redirect to payment page
        header("Location: ../public/pay-balance.php?res=" . urlencode($reservationNumber) . "&amount=" . $balanceOwed . "&extras_added=1");
        exit;
    } else {
        // Redirect back to booking page with success
        header("Location: ../public/my-booking.php?res=" . urlencode($reservationNumber) . "&updated=1");
        exit;
    }

} catch (Exception $e) {
    error_log("Add extras error: " . $e->getMessage());
    header("Location: ../public/my-booking.php?res=" . urlencode($_POST['reservation_number'] ?? '') . "&error=" . urlencode($e->getMessage()));
    exit;
}
?>