<?php
// www/booking/check-availability.php
require_once __DIR__ . '/../includes/core.php';
header('Content-Type: application/json');

$response = [
    'success' => false,
    'apartments' => [],
    'message' => 'Unknown error'
];

try {
    $checkin = $_POST['checkin'] ?? $_POST['checkin_date'] ?? null;
    $checkout = $_POST['checkout'] ?? $_POST['checkout_date'] ?? null;

    if (!$checkin || !$checkout) {
        throw new Exception("Missing check-in or check-out date.");
    }

    $checkinDate = DateTime::createFromFormat('Y-m-d', $checkin);
    $checkoutDate = DateTime::createFromFormat('Y-m-d', $checkout);
    $today = new DateTime("today");

    if (!$checkinDate || !$checkoutDate || $checkoutDate <= $checkinDate) {
        throw new Exception("Invalid date selection.");
    }
    if ($checkinDate < $today) {
        throw new Exception("Check-in must be today or later.");
    }

    // Check Apartment 6205 (DB id = 1)
    $conflict6205 = checkBookingConflict(1, $checkin, $checkout);

    // Check Apartment 6207 (DB id = 2)
    $conflict6207 = checkBookingConflict(2, $checkin, $checkout);

    $response['success'] = true;
    $response['apartments'] = [
        '6205' => !$conflict6205,
        '6207' => !$conflict6207
    ];

    if (!$conflict6205 && !$conflict6207) {
        $response['message'] = "Both apartments (6205 & 6207) are available 🎉";
    } elseif (!$conflict6205 && $conflict6207) {
        $response['message'] = "Apartment 6205 is available ✅, Apartment 6207 is booked ❌";
    } elseif ($conflict6205 && !$conflict6207) {
        $response['message'] = "Apartment 6207 is available ✅, Apartment 6205 is booked ❌";
    } else {
        $response['message'] = "No availability for those dates 😞";
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;