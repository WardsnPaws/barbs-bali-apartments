<?php

// ────────────────────────────────
// config.php – Central Configuration File
// ────────────────────────────────
// Reads secrets from environment variables if available.
// Fallback values are for local/WAMP testing only.
// On production (e.g. Crazy Domains), set env vars in control panel or .htaccess.

// Load secrets from file outside webroot if present
$secrets = [];
$secretsFile = __DIR__ . '/config_secrets.php';
if (file_exists($secretsFile)) {
    $s = include $secretsFile;
    if (is_array($s)) $secrets = $s;
}

// Helper: check getenv first, then $secrets array, then fallback default
function env($key, $default = null) {
    $v = getenv($key);
    if ($v !== false) return $v;
    global $secrets;
    return $secrets[$key] ?? $default;
}

// Database Credentials

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'barbs141_database');
define('DB_PASS', 'T@kesMySQL2bH@ppy');
define('DB_NAME', 'barbs141_bali_booking');

// Alternative constant name (some systems use this)
define('DB_PASSWORD', 'T@kesMySQL2bH@ppy');

// Square API Configuration (sandbox keys - replace with live for production)
define('SQUARE_APPLICATION_ID', env('SQUARE_APPLICATION_ID', 'sandbox-sq0idb-_qiYzv__WUfvY8Zmxn-Srw'));
define('SQUARE_ACCESS_TOKEN',   env('SQUARE_ACCESS_TOKEN',   'EAAAlznsxg7GGSO8lJeTG50Agr_fcMQbQGM3vzV1eo20y4k-cVQcCV1I0DrU4vNA'));
define('SQUARE_LOCATION_ID',    env('SQUARE_LOCATION_ID',    'LXH9S1ZFQ32WE'));
define('SQUARE_MCC',            env('SQUARE_MCC',            '7299'));

// PayPal API Configuration (sandbox - replace with live for production)
define('PAYPAL_CLIENT_ID', env('PAYPAL_CLIENT_ID', 'ATGD6YEDIugvmDtsUAB77IMG-aalxulLt1awBPQ7Ry5fsYg-fLRtu5l8z8htyJD5ECrR8UPlArHdP0Ix'));

// Email SMTP Configuration (use env vars for credentials in production)
define('SMTP_HOST',      env('SMTP_HOST', 'mail.barbsbaliapartments.com'));
define('SMTP_PORT',      env('SMTP_PORT', 587));
define('SMTP_USERNAME',  env('SMTP_USERNAME', 'bookings@barbsbaliapartments.com'));
define('SMTP_PASSWORD',  env('SMTP_PASSWORD', 'T@kesbundy2bH@ppy'));
define('SMTP_FROM_EMAIL',env('SMTP_FROM_EMAIL', 'bookings@barbsbaliapartments.com'));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'Barbs Bali Apartments'));

// Housekeeping notification email
define('HOUSEKEEPING_EMAIL', env('HOUSEKEEPING_EMAIL', 'housekeeping@barbsbaliapartments.com'));

// Currency
define('CURRENCY', 'AUD');

// Apartment Nightly Rates
define('APARTMENT_RATES', json_encode([
    1 => 70,
    2 => 70
]));

// Sofa-bed price per night
define('SOFA_BED_PRICE', 5);

// Discount when both apartments are booked
define('INTERCONNECTING_DISCOUNT_PER_APT', 5);

// Extras Pricing
define('EXTRAS_PRICING', json_encode([
    3 => ['name' => 'Airport Pickup', 'price' => 35, 'per_night' => false],
    4 => ['name' => 'Airport Drop-off', 'price' => 25, 'per_night' => false],
    5 => ['name' => 'Late Checkout', 'price' => 40, 'per_night' => false],
    6 => ['name' => 'Linen Change Daily', 'price' => 5, 'per_night' => true]
]));

// Deposit Logic
define('DEPOSIT_THRESHOLD_DAYS', 90);
define('DEPOSIT_RATE', 0.20); // 20% if > 90 days

// Admin login credentials (change for production; consider hashed passwords)
define('ADMIN_USER', env('ADMIN_USER', 'admin'));
define('ADMIN_PASS', env('ADMIN_PASS', 'letmein')); // Change this in production