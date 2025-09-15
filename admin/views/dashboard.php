<?php
require_once __DIR__ . '/../../includes/core.php';

$pdo = getPDO();

// Total bookings
$totalBookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();

// Upcoming bookings (next 30 days)
$upcoming = $pdo->query("SELECT COUNT(*) FROM bookings WHERE checkin_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

// Total revenue (from payments table)
$totalRevenue = $pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn();
$totalRevenue = $totalRevenue ? number_format($totalRevenue, 2) : '0.00';

// Latest 5 payments
$recentPayments = $pdo->query("
    SELECT p.amount, p.paid_at, b.guest_first_name, b.guest_last_name
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    ORDER BY p.paid_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>📊 Dashboard Overview</h2>

<p><strong>Total Bookings:</strong> <?= $totalBookings ?></p>
<p><strong>Upcoming (Next 30 Days):</strong> <?= $upcoming ?></p>
<p><strong>Total Revenue:</strong> $<?= $totalRevenue ?> AUD</p>

<h3>💳 Recent Payments</h3>
<ul>
  <?php foreach ($recentPayments as $pay): ?>
    <li>
      <?= htmlspecialchars($pay['guest_first_name']) ?> <?= htmlspecialchars($pay['guest_last_name']) ?> – 
      $<?= number_format($pay['amount'], 2) ?> on <?= date('d M Y', strtotime($pay['paid_at'])) ?>
    </li>
  <?php endforeach; ?>
</ul>
