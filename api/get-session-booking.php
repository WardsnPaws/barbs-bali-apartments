<?php
// get-session-booking.php
require_once __DIR__ . '/../includes/price-calc.php';
session_start();
header('Content-Type: application/json');

// Debug logging
error_log("Get session booking - Session data: " . json_encode($_SESSION));

if (!isset($_SESSION['booking'])) {
    error_log("Get session booking - No booking in session");
    echo json_encode(null);
    exit;
}

$booking = $_SESSION['booking'];

// Debug the raw booking data
error_log("Get session booking - Raw booking: " . json_encode($booking));

// Normalize stored apartment id (should be numeric 1|2|3)
$storedApt = isset($booking['apartment_id']) ? (int)$booking['apartment_id'] : 0;
error_log("Get session booking - Stored apartment ID: $storedApt");

// Calculate pricing based on apartment configuration
if ($storedApt === 3) {
    // Combined booking (both apartments)
    // Build copies for calculations - remove helper flags to avoid confusion
    $b1 = $booking;
    $b1['apartment_id'] = 1;
    unset($b1['both_apartments'], $b1['apartment_ids']);

    $b2 = $booking;
    $b2['apartment_id'] = 2;
    unset($b2['both_apartments'], $b2['apartment_ids']);

    error_log("Get session booking - Calculating for apartment 1: " . json_encode($b1));
    error_log("Get session booking - Calculating for apartment 2: " . json_encode($b2));

    $price1 = calculateBookingTotal($b1);
    $price2 = calculateBookingTotal($b2);

    // Sum totals and construct breakdown
    $total = (float)$price1['total'] + (float)$price2['total'];
    $nights = isset($price1['nights']) ? $price1['nights'] : (isset($price2['nights']) ? $price2['nights'] : null);

    $booking['price_breakdown'] = [
        '6205' => $price1,
        '6207' => $price2,
        'combined_total' => $total
    ];
    
    error_log("Get session booking - Combined total: $total (6205: {$price1['total']}, 6207: {$price2['total']})");
} else {
    // Single apartment
    error_log("Get session booking - Calculating for single apartment: " . json_encode($booking));
    $priceInfo = calculateBookingTotal($booking);
    $total = (float)$priceInfo['total'];
    $nights = isset($priceInfo['nights']) ? $priceInfo['nights'] : null;
    $booking['price_breakdown'] = $priceInfo;
    
    error_log("Get session booking - Single apartment total: $total");
}

// Calculate deposit logic
$checkinDate = new DateTime($booking['checkin_date']);
$today = new DateTime();
$daysUntil = $today->diff($checkinDate)->days;
$isDeposit = $daysUntil > DEPOSIT_THRESHOLD_DAYS;
$deposit = round($total * DEPOSIT_RATE, 2);

$booking['total_price'] = $total;
$booking['deposit_due'] = $deposit;
$booking['is_deposit'] = $isDeposit;
$booking['amount_due'] = $isDeposit ? $deposit : $total;
$booking['nights'] = $nights;
$booking['days_until_checkin'] = $daysUntil;

// Final debug log
error_log("Get session booking - Final output: total=$total, deposit=$deposit, is_deposit=" . ($isDeposit ? 'true' : 'false') . ", amount_due={$booking['amount_due']}");

echo json_encode($booking);