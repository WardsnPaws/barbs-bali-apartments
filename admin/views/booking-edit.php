<?php
require_once __DIR__ . '/../../core.php';

$pdo = getPDO();

$id = $_GET['id'] ?? '';
if (!$id || !is_numeric($id)) {
    die("Invalid booking ID.");
}

$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->execute([$id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    die("Booking not found.");
}
?>

<h2>✏️ Edit Booking #<?= $booking['id'] ?></h2>

<form method="POST" action="update-booking.php">
  <input type="hidden" name="id" value="<?= $booking['id'] ?>" />

  <label>First Name:
    <input type="text" name="guest_first_name" value="<?= htmlspecialchars($booking['guest_first_name']) ?>" required />
  </label><br><br>

  <label>Last Name:
    <input type="text" name="guest_last_name" value="<?= htmlspecialchars($booking['guest_last_name']) ?>" required />
  </label><br><br>

  <label>Email:
    <input type="email" name="guest_email" value="<?= htmlspecialchars($booking['guest_email']) ?>" required />
  </label><br><br>

  <label>Apartment:
    <select name="apartment_id">
      <option value="1" <?= $booking['apartment_id'] == 1 ? 'selected' : '' ?>>6205</option>
      <option value="2" <?= $booking['apartment_id'] == 2 ? 'selected' : '' ?>>6207</option>
      <option value="3" <?= ($booking['apartment_id'] == 3 || $booking['apartment_id'] == 'both') ? 'selected' : '' ?>>Both</option>
    </select>
  </label><br><br>

  <label>Check-in Date:
    <input type="date" name="checkin_date" value="<?= $booking['checkin_date'] ?>" required />
  </label><br><br>

  <label>Check-out Date:
    <input type="date" name="checkout_date" value="<?= $booking['checkout_date'] ?>" required />
  </label><br><br>

  <label>
    <input type="checkbox" name="sofa_bed" value="1" <?= !empty($booking['sofa_bed']) ? 'checked' : '' ?> />
    Sofa Bed Required
  </label><br><br>

  <label>Status:
    <select name="status">
      <option value="pending" <?= $booking['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="confirmed" <?= $booking['status'] == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
      <option value="cancelled" <?= $booking['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
    </select>
  </label><br><br>

  <button type="submit">💾 Save Changes</button>
</form>