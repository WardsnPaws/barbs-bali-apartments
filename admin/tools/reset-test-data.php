// admin/tools/reset-test-data.php
<?php
require_once __DIR__ . '/../../includes/core.php';


$pdo = getPDO();

$pdo->exec("DELETE FROM payments");
$pdo->exec("DELETE FROM booking_extras");
$pdo->exec("DELETE FROM email_schedule");
$pdo->exec("DELETE FROM bookings");

echo "🧹 Test data wiped (bookings, extras, payments, email_schedule).";
