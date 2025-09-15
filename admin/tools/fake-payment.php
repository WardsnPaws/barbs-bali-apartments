<?php
require_once __DIR__ . '/../../includes/core.php';


$pdo = getPDO();
$bookingId = $_GET['booking'] ?? 1;
$amount = $_GET['amount'] ?? 100.00;

$pdo->prepare("INSERT INTO payments (booking_id, amount, note) VALUES (?, ?, ?)")
    ->execute([$bookingId, $amount, 'Manual test payment']);

echo "✅ Fake payment of \$$amount added to booking #$bookingId.";
