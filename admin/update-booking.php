<?php
require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/price-calc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $firstName = $_POST['guest_first_name'] ?? '';
    $lastName = $_POST['guest_last_name'] ?? '';
    $email = $_POST['guest_email'] ?? '';
    $apartmentId = $_POST['apartment_id'] ?? null;
    $checkin = $_POST['checkin_date'] ?? null;
    $checkout = $_POST['checkout_date'] ?? null;
    $sofaBed = isset($_POST['sofa_bed']) ? 1 : 0;
    $status = $_POST['status'] ?? 'pending';

    if (!$id || !$checkin || !$checkout || !$apartmentId) {
        die("Missing required booking information.");
    }

    $pdo = getPDO();

    // Update the booking table with all fields including sofa_bed
    $stmt = $pdo->prepare("UPDATE bookings SET guest_first_name = ?, guest_last_name = ?, guest_email = ?, apartment_id = ?, checkin_date = ?, checkout_date = ?, sofa_bed = ?, status = ? WHERE id = ?");
    $stmt->execute([$firstName, $lastName, $email, $apartmentId, $checkin, $checkout, $sofaBed, $status, $id]);

    // Fetch updated booking data for price recalculation
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    // Prepare booking data for price calculation
    $bookingForCalc = [
        'apartment_id' => $booking['apartment_id'],
        'checkin_date' => $booking['checkin_date'],
        'checkout_date' => $booking['checkout_date'],
        'sofa_bed' => !empty($booking['sofa_bed']) // Use actual sofa bed value from database
    ];

    // Fetch any extras
    $extrasStmt = $pdo->prepare("SELECT extra_id FROM booking_extras WHERE booking_id = ?");
    $extrasStmt->execute([$id]);
    $bookingForCalc['extras'] = $extrasStmt->fetchAll(PDO::FETCH_COLUMN);

    // Recalculate total price
    $priceInfo = calculateBookingTotal($bookingForCalc);
    $newTotal = $priceInfo['total'];

    // Update the total price
    $pdo->prepare("UPDATE bookings SET total_price = ? WHERE id = ?")
        ->execute([$newTotal, $id]);

    header("Location: index.php?view=bookings");
    exit;
} else {
    echo "Invalid request method.";
    exit;
}