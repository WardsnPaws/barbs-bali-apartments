<?php
require_once __DIR__ . '/../core.php';
require_once __DIR__ . '/../core/price-calc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $checkin = $_POST['checkin_date'] ?? null;
    $checkout = $_POST['checkout_date'] ?? null;
    $status = $_POST['status'] ?? 'pending';

    if (!$id || !$checkin || !$checkout) {
        die("Missing booking ID or dates.");
    }

    $pdo = getPDO();

    // Update the booking table
    $stmt = $pdo->prepare("UPDATE bookings SET checkin_date = ?, checkout_date = ?, status = ? WHERE id = ?");
    $stmt->execute([$checkin, $checkout, $status, $id]);

    // Fetch booking data for price recalculation
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    $booking['apartment_id'] = $booking['apartment_id'] == 1 || $booking['apartment_id'] == 2 ? (int)$booking['apartment_id'] : 'both';
    $booking['sofa_bed'] = false;

    // Fetch any extras
    $extrasStmt = $pdo->prepare("SELECT extra_id FROM booking_extras WHERE booking_id = ?");
    $extrasStmt->execute([$id]);
    $booking['extras'] = $extrasStmt->fetchAll(PDO::FETCH_COLUMN);

    $priceInfo = calculateBookingTotal($booking);
    $newTotal = $priceInfo['total'];

    $pdo->prepare("UPDATE bookings SET total_price = ? WHERE id = ?")
        ->execute([$newTotal, $id]);

    header("Location: index.php?view=bookings");
    exit;
} else {
    echo "Invalid request method.";
    exit;
}
