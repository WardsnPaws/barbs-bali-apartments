<?php
// my-booking.php

require_once '../includes/core.php';

$resNum = $_GET['res'] ?? '';
$pdo = getPDO();

// Get booking
$stmt = $pdo->prepare("SELECT * FROM bookings WHERE reservation_number = :res LIMIT 1");
$stmt->execute([':res' => $resNum]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    die("Booking not found.");
}

// Check for success messages
$showUpdatedMessage = isset($_GET['updated']) && $_GET['updated'] == '1';
$showPaymentSuccess = isset($_GET['payment_success']) && $_GET['payment_success'] == '1';

// Normalize apartment id and prepare combined breakdown if needed
if (isset($booking['apartment_id']) && $booking['apartment_id'] === 'both') {
    $booking['apartment_id'] = 3;
}
$isCombined = ((int)$booking['apartment_id'] === 3);

// Base fields
$bookingId = $booking['id'];
$guestName = $booking['guest_first_name'] . ' ' . $booking['guest_last_name'];

// Get the actual total from database (which should be updated after extras changes)
$total = (float)$booking['total_price'];

$price_breakdown = null;
$apt = ($booking['apartment_id'] == 1) ? '6205' : (($booking['apartment_id'] == 2) ? '6207' : 'Both');

if ($isCombined) {
    // compute per-apartment prices using core/price-calc.php
    require_once 'core/price-calc.php';

    // Get current extras for this booking
    $extrasStmt = $pdo->prepare("SELECT extra_id FROM booking_extras WHERE booking_id = ?");
    $extrasStmt->execute([$bookingId]);
    $currentExtras = array_column($extrasStmt->fetchAll(PDO::FETCH_ASSOC), 'extra_id');

    $b1 = [
        'apartment_id' => 1,
        'checkin_date' => $booking['checkin_date'],
        'checkout_date' => $booking['checkout_date'],
        'sofa_bed' => !empty($booking['sofa_bed']),
        'extras' => $currentExtras
    ];
    $b2 = [
        'apartment_id' => 2,
        'checkin_date' => $booking['checkin_date'],
        'checkout_date' => $booking['checkout_date'],
        'sofa_bed' => !empty($booking['sofa_bed']),
        'extras' => $currentExtras
    ];

    $price1 = calculateBookingTotal($b1);
    $price2 = calculateBookingTotal($b2);

    $combinedTotal = (float)$price1['total'] + (float)$price2['total'];

    // Use the database total (should match calculated total after extras update)
    $total = $total; // Keep database total as authoritative
    $apt = '6205 & 6207';
    $price_breakdown = [
        '6205' => $price1,
        '6207' => $price2
    ];
}

// Get current extras
$currentExtras = $pdo->prepare("SELECT extra_id FROM booking_extras WHERE booking_id = ?");
$currentExtras->execute([$bookingId]);
$activeExtras = array_column($currentExtras->fetchAll(PDO::FETCH_ASSOC), 'extra_id');

// Get payments
$paidStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE booking_id = ?");
$paidStmt->execute([$bookingId]);
$alreadyPaid = (float) $paidStmt->fetchColumn();
$balanceLeft = max($total - $alreadyPaid, 0);

// Load available extras
$extras = $pdo->query("SELECT * FROM extras ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <title>My Booking — Barbs Bali Apartments</title>
  <script src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
  <style>
    body { font-family: Arial, sans-serif; padding: 30px; max-width: 800px; margin: auto; }
    .box { background: #f9f9f9; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
    .success-message { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745; }
    .warning-message { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107; }
    label { display: block; margin-top: 10px; }
    button { margin-top: 15px; padding: 10px 20px; }
    #card-container { margin-top: 15px; }
    .breakdown { background: #fff; padding: 12px; border-radius: 6px; margin-top: 10px; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .logout-btn { background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; }
    .logout-btn:hover { background: #c82333; }
  </style>
</head>
<body>

  <div class="header">
    <h2>Welcome back, <?= htmlspecialchars($guestName) ?></h2>
    <a href="index.php" class="logout-btn">🚪 Logout</a>
  </div>

  <?php if ($showUpdatedMessage): ?>
    <div class="success-message">
      ✅ Extras have been successfully updated! Your booking total has been recalculated.
    </div>
  <?php endif; ?>

  <?php if ($showPaymentSuccess): ?>
    <div class="success-message">
      ✅ Payment received successfully! Thank you for your payment.
    </div>
  <?php endif; ?>

  <div class="box">
    <h3>Booking Summary</h3>
    <p><strong>Apartment:</strong> <?= htmlspecialchars($apt) ?></p>
    <p><strong>Check-in:</strong> <?= htmlspecialchars($booking['checkin_date']) ?></p>
    <p><strong>Check-out:</strong> <?= htmlspecialchars($booking['checkout_date']) ?></p>
    <?php if (!empty($booking['sofa_bed'])): ?>
    <p><strong>Sofa Bed:</strong> ✅ Included</p>
    <?php endif; ?>
    <p><strong>Total Price:</strong> $<?= number_format($total, 2) ?> AUD</p>
    <p><strong>Paid:</strong> $<?= number_format($alreadyPaid, 2) ?> AUD</p>
    <p><strong>Balance:</strong> $<?= number_format($balanceLeft, 2) ?> AUD</p>

    <?php if ($price_breakdown): ?>
      <div class="breakdown">
        <h4>Price Breakdown</h4>
        <p><strong>Apartment 6205:</strong> $<?= number_format($price_breakdown['6205']['total'], 2) ?> (<?= intval($price_breakdown['6205']['nights']) ?> nights)</p>
        <p><strong>Apartment 6207:</strong> $<?= number_format($price_breakdown['6207']['total'], 2) ?> (<?= intval($price_breakdown['6207']['nights']) ?> nights)</p>
        <?php if (!empty($booking['sofa_bed'])): ?>
        <p><strong>Sofa Bed:</strong> Included across both apartments</p>
        <?php endif; ?>
        <hr />
        <p><strong>Combined Total:</strong> $<?= number_format($total, 2) ?> AUD</p>
      </div>
    <?php endif; ?>
  </div>

  <div class="box">
    <h3>Update Extras</h3>
    
    <?php
    // Check if within 90 days and show warning
    $checkinDate = new DateTime($booking['checkin_date']);
    $today = new DateTime();
    $daysUntilCheckin = $today->diff($checkinDate)->days;
    
    if ($daysUntilCheckin <= 90 && $balanceLeft == 0): ?>
      <div class="warning-message">
        <strong>Note:</strong> Your check-in is within 90 days. Any additional extras will require immediate payment.
      </div>
    <?php endif; ?>
    
    <form method="POST" action="update-extras.php">
    <input type="hidden" name="reservation_number" value="<?= htmlspecialchars($resNum) ?>" />
    <?php foreach ($extras as $extra): ?>
    <label>
    <input type="checkbox" name="extras[]" value="<?= $extra['id'] ?>"
    <?= in_array($extra['id'], $activeExtras) ? 'checked' : '' ?> />
    <?= htmlspecialchars($extra['name']) ?> ($<?= $extra['price'] ?> <?= $extra['per_night'] ? 'per night' : '' ?>)
    </label>
    <?php endforeach; ?>
    <button type="submit">Update Extras</button>
    </form>
  </div>

  <?php if ($balanceLeft > 0): ?>
  <div class="box">
    <h3>Make a Payment</h3>
    <label>Amount (AUD):
    <input type="number" id="amount" min="1" step="0.01" max="<?= $balanceLeft ?>" />
    </label>
    <div id="card-container"></div>
    <button id="pay-btn">Submit Payment</button>
    <div id="pay-status"></div>
  </div>
  <?php endif; ?>

  <div class="box">
    <h3>Email Confirmation</h3>
    <form method="POST" action="resend-confirmation.php">
    <input type="hidden" name="reservation_number" value="<?= htmlspecialchars($resNum) ?>" />
    <button type="submit">Resend Confirmation Email</button>
    </form>
  </div>

  <script>
    async function main() {
    const payments = Square.payments("<?= SQUARE_APPLICATION_ID ?>", "<?= SQUARE_LOCATION_ID ?>");
    const card = await payments.card();
    await card.attach("#card-container");

    document.getElementById("pay-btn").addEventListener("click", async () => {
    const amount = parseFloat(document.getElementById("amount").value);
    const status = document.getElementById("pay-status");
    status.textContent = "";

    if (!amount || amount < 1) {
    status.textContent = "Please enter a valid amount.";
    return;
    }

    const result = await card.tokenize();
    if (result.status !== "OK") {
    status.textContent = "Card error: " + result.errors[0].message;
    return;
    }

    fetch("pay-balance-process.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
    token: result.token,
    amount: amount,
    reservation: "<?= $resNum ?>"
    })
    })
    .then(res => res.json())
    .then(data => {
    status.textContent = data.success
    ? "✅ Payment successful!"
    : "❌ " + data.message;
    
    if (data.success) {
    setTimeout(() => location.reload(), 1500);
    }
    })
    .catch(err => {
    status.textContent = "❌ Error processing payment.";
    console.error(err);
    });
    });
    }

    main();
  </script>

</body>
</html>