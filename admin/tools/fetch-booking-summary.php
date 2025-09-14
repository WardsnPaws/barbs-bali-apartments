// admin/tools/fetch-booking-summary.php
<?php
require_once __DIR__ . '/../../core.php';


$res = $_GET['res'] ?? '';
$pdo = getPDO();

$stmt = $pdo->prepare("SELECT * FROM bookings WHERE reservation_number = ?");
$stmt->execute([$res]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($data) {
    echo "<pre>" . print_r($data, true) . "</pre>";
} else {
    echo "❌ Booking not found.";
}