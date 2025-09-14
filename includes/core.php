<?php

// ────
// core.php – Shared Core Functions
// ────

require_once 'config.php';

function getPDO() {
    try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
    } catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
    }
}

function getAvailabilityCalendar($days = 30) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    $today = new DateTime();
    $endDate = (clone $today)->modify("+$days days");
    $calendar = [];
    $current = clone $today;

    while ($current <= $endDate) {
    $dateStr = $current->format('Y-m-d');
    $calendar[$dateStr] = [
    'A' => true,
    'B' => true,
    'guestA' => '',
    'guestB' => ''
    ];
    $current->modify('+1 day');
    }

    $sql = "SELECT apartment_id, checkin_date, checkout_date, guest_first_name, guest_last_name FROM bookings WHERE status = 'confirmed'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
    $apartmentId = $row['apartment_id'];
    $apartment = ($apartmentId == 1) ? 'A' : (($apartmentId == 2) ? 'B' : 'AB');
    $checkin = new DateTime($row['checkin_date']);
    $checkout = new DateTime($row['checkout_date']);
    $guest = $row['guest_first_name'] . ' ' . $row['guest_last_name'];

    $currentDate = clone $checkin;
    while ($currentDate < $checkout) {
    $dateKey = $currentDate->format('Y-m-d');
    if (isset($calendar[$dateKey])) {
    if ($apartment == 'A' || $apartment == 'AB') {
    $calendar[$dateKey]['A'] = false;
    $calendar[$dateKey]['guestA'] = $guest;
    }
    if ($apartment == 'B' || $apartment == 'AB') {
    $calendar[$dateKey]['B'] = false;
    $calendar[$dateKey]['guestB'] = $guest;
    }
    }
    $currentDate->modify('+1 day');
    }
    }
    }

    return $calendar;
}

function checkBookingConflict($apartmentId, $checkinDate, $checkoutDate) {
    try {
    $pdo = getPDO();

    // Normalize to integer for comparisons
    $apt = (int)$apartmentId;

    if ($apt === 3) {
    // If checking availability for the combined unit (3), any existing booking
    // for apartment 1, 2 or 3 that overlaps should be considered a conflict.
    $sql = "SELECT COUNT(*) FROM bookings
    WHERE apartment_id IN (1,2,3)
    AND status = 'confirmed'
    AND (
    checkin_date < :checkout_date
    AND checkout_date > :checkin_date
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
    ':checkout_date' => $checkoutDate,
    ':checkin_date' => $checkinDate
    ]);

    } else {
    // For single apartment checks (1 or 2), also consider any combined booking (3) as a conflict
    $sql = "SELECT COUNT(*) FROM bookings
    WHERE (apartment_id = :apartment_id OR apartment_id = 3)
    AND status = 'confirmed'
    AND (
    checkin_date < :checkout_date
    AND checkout_date > :checkin_date
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
    ':apartment_id' => $apt,
    ':checkout_date' => $checkoutDate,
    ':checkin_date' => $checkinDate
    ]);
    }

    $count = $stmt->fetchColumn();
    return $count > 0;
    } catch (PDOException $e) {
    error_log('Conflict check error: ' . $e->getMessage());
    return true;
    }
}