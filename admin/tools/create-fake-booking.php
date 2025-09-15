// admin/tools/create-fake-booking.php
<?php
require_once __DIR__ . '/../../includes/core.php';


$pdo = getPDO();
$resNum = strtoupper(uniqid('BB'));

$stmt = $pdo->prepare("INSERT INTO bookings (
  reservation_number, apartment_id, guest_first_name, guest_last_name, guest_email,
  checkin_date, checkout_date, total_price, status
) VALUES (
  ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')");

$stmt->execute([
  $resNum, 1, 'Test', 'Guest', 'test@example.com',
  date('Y-m-d', strtotime('+5 days')),
  date('Y-m-d', strtotime('+10 days')),
  350.00
]);