<?php
// core/price-calc.php
require_once __DIR__ . '/../config/config.php';

function calculateBookingTotal($bookingData) {
    $baseRates = json_decode(APARTMENT_RATES, true);
    $extrasList = json_decode(EXTRAS_PRICING, true);
    $sofaPrice = SOFA_BED_PRICE;
    $discountPerNight = INTERCONNECTING_DISCOUNT_PER_APT;

    // Debug logging
    error_log("Price calc - Input booking data: " . json_encode($bookingData));

    $checkin = new DateTime($bookingData['checkin_date']);
    $checkout = new DateTime($bookingData['checkout_date']);
    $nights = $checkin->diff($checkout)->days;

    // Handle apartment selection - support both string 'both' and numeric 3
    $apartmentId = $bookingData['apartment_id'];
    if ($apartmentId === 'both' || (int)$apartmentId === 3) {
        $apartments = [1, 2];
    } else {
        $apartments = [(int) $apartmentId];
    }
    $apartmentCount = count($apartments);

    $total = 0;
    $breakdown = [
        'base_rate' => 0,
        'sofa_bed' => 0,
        'extras' => 0,
        'discount' => 0,
        'nights' => $nights,
        'apartments' => $apartments
    ];

    // Base price calculation
    foreach ($apartments as $aptId) {
        if (!isset($baseRates[$aptId])) {
            error_log("Price calc - Invalid apartment ID: $aptId");
            continue;
        }
        $baseRate = $baseRates[$aptId] * $nights;
        $total += $baseRate;
        $breakdown['base_rate'] += $baseRate;
        error_log("Price calc - Apartment $aptId base rate: " . $baseRates[$aptId] . " * $nights nights = $baseRate");
    }

    // Sofa bed calculation - check for multiple possible values
    $sofaBedSelected = false;
    if (isset($bookingData['sofa_bed'])) {
        $sofaBedValue = $bookingData['sofa_bed'];
        // Handle boolean true, string '1', integer 1, or string 'on' (from checkbox)
        $sofaBedSelected = (
            $sofaBedValue === true || 
            $sofaBedValue === '1' || 
            $sofaBedValue === 1 || 
            $sofaBedValue === 'on' ||
            $sofaBedValue === 'yes'
        );
    }
    
    if ($sofaBedSelected) {
        $sofaBedCost = $sofaPrice * $nights;
        $total += $sofaBedCost;
        $breakdown['sofa_bed'] = $sofaBedCost;
        error_log("Price calc - Sofa bed: $sofaPrice * $nights nights = $sofaBedCost");
    } else {
        error_log("Price calc - No sofa bed selected (value was: " . json_encode($bookingData['sofa_bed'] ?? 'not set') . ")");
    }

    // Extras calculation
    $extrasTotal = 0;
    if (!empty($bookingData['extras']) && is_array($bookingData['extras'])) {
        error_log("Price calc - Processing extras: " . json_encode($bookingData['extras']));
        
        foreach ($bookingData['extras'] as $extraId) {
            $extraId = (int)$extraId; // Ensure integer
            if (!isset($extrasList[$extraId])) {
                error_log("Price calc - Invalid extra ID: $extraId");
                continue;
            }
            
            $extra = $extrasList[$extraId];
            $extraCost = 0;

            if ($extra['per_night']) {
                // Per night extras (like daily linen change)
                $extraCost = $extra['price'] * $nights * $apartmentCount;
                error_log("Price calc - Per night extra '{$extra['name']}': {$extra['price']} * $nights nights * $apartmentCount apartments = $extraCost");
            } else {
                // One-time extras
                if (in_array($extra['name'], ['Late Checkout']) && $apartmentCount > 1) {
                    // Late checkout applies per apartment when booking both
                    $extraCost = $extra['price'] * $apartmentCount;
                    error_log("Price calc - Per apartment extra '{$extra['name']}': {$extra['price']} * $apartmentCount apartments = $extraCost");
                } else {
                    // Airport transfers and other one-time services
                    $extraCost = $extra['price'];
                    error_log("Price calc - One-time extra '{$extra['name']}': {$extra['price']} = $extraCost");
                }
            }
            
            $total += $extraCost;
            $extrasTotal += $extraCost;
        }
    } else {
        error_log("Price calc - No extras selected");
    }
    
    $breakdown['extras'] = $extrasTotal;

    // Discount for booking both apartments
    if ($apartmentCount === 2) {
        $discount = $discountPerNight * $apartmentCount * $nights;
        $total -= $discount;
        $breakdown['discount'] = $discount;
        error_log("Price calc - Interconnecting discount: $discountPerNight * $apartmentCount apartments * $nights nights = $discount");
    }

    $finalTotal = round($total, 2);
    error_log("Price calc - Final total: $finalTotal");

    return [
        'total' => $finalTotal,
        'nights' => $nights,
        'breakdown' => $breakdown
    ];
}