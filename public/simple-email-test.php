<?php
// test-email-booking.php - Quick test of the email system

session_start();
require_once '../includes/core.php';
require_once '../includes/email-utils.php';

// Create fake booking data
$testBooking = [
    'guest_first_name' => 'John',
    'guest_last_name' => 'Test',
    'apartment_id' => 1,
    'checkin_date' => '2025-10-01',
    'checkout_date' => '2025-10-07'
];

// Test the existing function from your email-utils.php
$result = sendConfirmationEmail(
    'your-email@example.com', // CHANGE THIS
    'TEST-20250915-001',      // Test reservation number
    $testBooking,             // Booking data
    150.00,                   // Total amount
    150.00,                   // Amount paid
    'test-email-001'          // Email ID
);

if ($result) {
    echo "✅ Test email sent successfully!";
} else {
    echo "❌ Test email failed. Check error logs.";
}
?>