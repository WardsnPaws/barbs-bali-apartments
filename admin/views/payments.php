<?php
require_once __DIR__ . '/../../includes/core.php';
$pdo = getPDO();

$payments = $pdo->query("
    SELECT p.amount, p.paid_at, p.note, b.guest_first_name, b.guest_last_name, b.reservation_number
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    ORDER BY p.paid_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>💳 Payment Log</h2>

<table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse: collapse;">
  <thead>
    <tr>
      <th>Guest</th>
      <th>Reservation #</th>
      <th>Amount (AUD)</th>
      <th>Note</th>
      <th>Date Paid</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($payments as $pay): ?>
      <tr>
        <td><?= htmlspecialchars($pay['guest_first_name'] . ' ' . $pay['guest_last_name']) ?></td>
        <td><?= htmlspecialchars($pay['reservation_number']) ?></td>
        <td>$<?= number_format($pay['amount'], 2) ?></td>
        <td><?= htmlspecialchars($pay['note']) ?></td>
        <td><?= date('Y-m-d H:i', strtotime($pay['paid_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
