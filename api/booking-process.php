<?php
// api/booking-process.php

session_start(); // must be at the top before any output

require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/price-calc.php';

// Ensure required fields are present
$required = ['guest_first_name', 'guest_last_name', 'guest_email', 'apartment_id', 'checkin_date', 'checkout_date'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        die("Missing required field: $field");
    }
}

// Sanitize inputs
$guestFirstName = htmlspecialchars(trim($_POST['guest_first_name']));
$guestLastName  = htmlspecialchars(trim($_POST['guest_last_name']));
$guestEmail     = filter_var(trim($_POST['guest_email']), FILTER_SANITIZE_EMAIL);
$guestPhone     = htmlspecialchars(trim($_POST['guest_phone'] ?? ''));
$guestAddress   = htmlspecialchars(trim($_POST['guest_address'] ?? ''));
$apartmentId    = $_POST['apartment_id']; // allow 'both' or numeric
$checkinDate    = $_POST['checkin_date'];
$checkoutDate   = $_POST['checkout_date'];

// Fix 1: Properly handle sofa bed - check for various possible values
$sofaBed = false;
if (isset($_POST['sofa_bed'])) {
    $sofaBedValue = $_POST['sofa_bed'];
    $sofaBed = ($sofaBedValue == '1' || $sofaBedValue === 'on' || $sofaBedValue === true || $sofaBedValue === 1);
}

// Fix 2: Properly handle extras array - ensure integers and filter out empty values
$extras = [];
if (isset($_POST['extras']) && is_array($_POST['extras'])) {
    foreach ($_POST['extras'] as $extraId) {
        $extraIdInt = (int)$extraId;
        if ($extraIdInt > 0) { // Only include valid positive integers
            $extras[] = $extraIdInt;
        }
    }
}

// Debug logging (remove or comment out in production)
error_log("Booking Process - Raw POST data: " . json_encode($_POST));
error_log("Booking Process - Processed sofa_bed: " . ($sofaBed ? 'true' : 'false'));
error_log("Booking Process - Processed extras: " . json_encode($extras));

// Validate dates using timestamps
$checkinTs  = strtotime($checkinDate);
$checkoutTs = strtotime($checkoutDate);
if (!$checkinTs || !$checkoutTs || $checkinTs >= $checkoutTs) {
    die("Invalid check-in or check-out date.");
}

// Availability re-check before payment
if ($apartmentId === 'both') {
    // For combined booking, BOTH must be free
    $conflict6205 = checkBookingConflict(1, $checkinDate, $checkoutDate);
    $conflict6207 = checkBookingConflict(2, $checkinDate, $checkoutDate);

    if ($conflict6205 || $conflict6207) {
        die("Sorry, one or both apartments are no longer available.");
    }
} else {
    $aptNumeric = (int)$apartmentId;
    if (checkBookingConflict($aptNumeric, $checkinDate, $checkoutDate)) {
        die("Sorry, the apartment is no longer available for those dates.");
    }
}

// Build the base booking data with proper data types
$baseBooking = [
    'guest_first_name' => $guestFirstName,
    'guest_last_name'  => $guestLastName,
    'guest_email'      => $guestEmail,
    'guest_phone'      => $guestPhone,
    'guest_address'    => $guestAddress,
    // store numeric 3 for combined booking so downstream code can treat it consistently
    'apartment_id'     => ($apartmentId === 'both') ? 3 : (int)$apartmentId,    // 1 | 2 | 3
    'checkin_date'     => $checkinDate,
    'checkout_date'    => $checkoutDate,
    'sofa_bed'         => $sofaBed,      // Ensure boolean
    'extras'           => $extras        // Ensure array of integers
];

// Store booking in session (include helpful flags/ids for downstream)
if ($apartmentId === 'both') {
    $baseBooking['both_apartments'] = true;
    $baseBooking['apartment_ids'] = [1, 2]; // 6205, 6207
}

$_SESSION['booking'] = $baseBooking;

// Calculate total and store amount due
if ($apartmentId === 'both') {
    // For a combined booking, total = 6205 total + 6207 total
    $booking6205 = $baseBooking;
    $booking6205['apartment_id'] = 1;
    unset($booking6205['both_apartments'], $booking6205['apartment_ids']); // avoid confusion in calculator

    $booking6207 = $baseBooking;
    $booking6207['apartment_id'] = 2;
    unset($booking6207['both_apartments'], $booking6207['apartment_ids']);

    $price6205 = calculateBookingTotal($booking6205);
    $price6207 = calculateBookingTotal($booking6207);

    $combinedTotal = (float)$price6205['total'] + (float)$price6207['total'];

    // Save combined pricing info
    $_SESSION['amount_due'] = $combinedTotal;
    $_SESSION['price_breakdown'] = [
        '6205' => $price6205,
        '6207' => $price6207,
        'combined_total' => $combinedTotal
    ];
    
    // Debug logging
    error_log("Combined booking - 6205 total: " . $price6205['total']);
    error_log("Combined booking - 6207 total: " . $price6207['total']);
    error_log("Combined booking - combined total: $combinedTotal");
} else {
    $priceInfo = calculateBookingTotal($_SESSION['booking']);
    $_SESSION['amount_due'] = (float)$priceInfo['total'];
    $_SESSION['price_breakdown'] = $priceInfo;
    
    // Debug logging
    error_log("Single apartment booking - total: " . $priceInfo['total']);
}

// Final debug log of session data
error_log("Session booking data: " . json_encode($_SESSION['booking']));
error_log("Session amount due: " . $_SESSION['amount_due']);

// Redirect to secure payment page
header("Location: secure-payment.php");
exit;