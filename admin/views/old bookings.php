<?php
require_once __DIR__ . '/../../core.php';

$pdo = getPDO();
$bookings = $pdo->query("SELECT * FROM bookings ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<h2>📖 All Bookings</h2>
<table border="1" cellpadding="6" cellspacing="0">
  <tr>
    <th>ID</th>
    <th>Reservation #</th>
    <th>Guest</th>
    <th>Email</th>
    <th>Check-in</th>
    <th>Check-out</th>
    <th>Total</th>
    <th>Status</th>
    <th>Actions</th>
  </tr>
  <?php foreach ($bookings as $b): ?>
    <tr>
      <td><?= (int)$b['id'] ?></td>
      <td><?= htmlspecialchars($b['reservation_number'] ?? '') ?></td>
      <td><?= htmlspecialchars(($b['guest_first_name'] ?? '') . ' ' . ($b['guest_last_name'] ?? '')) ?></td>
      <td><?= htmlspecialchars($b['guest_email'] ?? '') ?></td>
      <td><?= htmlspecialchars($b['checkin_date'] ?? '') ?></td>
      <td><?= htmlspecialchars($b['checkout_date'] ?? '') ?></td>
      <td>$<?= number_format((float)$b['total_price'], 2) ?></td>
      <td><?= htmlspecialchars($b['status'] ?? '') ?></td>
      <td>
        <a href="?view=booking-edit&id=<?= $b['id'] ?>">✏️ Edit</a> |
        <a href="?view=delete-booking&id=<?= $b['id'] ?>" onclick="return confirm('Are you sure you want to delete this booking?');">🗑️ Delete</a> |
        <a href="?view=email-log&id=<?= $b['id'] ?>">📧 Emails</a>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
