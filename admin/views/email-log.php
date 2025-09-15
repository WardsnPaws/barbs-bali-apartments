<?php
require_once __DIR__ . '/../../includes/core.php';
$pdo = getPDO();

$bookingId = $_GET['id'] ?? null;
if (!$bookingId || !is_numeric($bookingId)) {
    echo "<p>Invalid booking ID.</p>";
    return;
}

$stmt = $pdo->prepare("SELECT * FROM email_schedule WHERE booking_id = ? ORDER BY send_date DESC");
$stmt->execute([$bookingId]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<h2>📧 Email Log</h2>
<p><a href="?view=bookings">← Back to Bookings</a></p>

<?php if (empty($logs)): ?>
    <p>No emails found for this booking.</p>
<?php else: ?>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr>
            <th>Email Type</th>
            <th>Send Date</th>
            <th>Status</th>
            <th>Sent At</th>
        </tr>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><?= htmlspecialchars($log['email_type']) ?></td>
            <td><?= htmlspecialchars($log['send_date']) ?></td>
            <td><?= htmlspecialchars($log['sent_status']) ?></td>
            <td><?= $log['sent_timestamp'] ? htmlspecialchars($log['sent_timestamp']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
