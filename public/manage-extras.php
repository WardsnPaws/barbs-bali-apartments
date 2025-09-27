<?php
// public/manage-extras.php - Complete extras management interface

require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/price-calc.php';

$reservationNumber = $_GET['res'] ?? '';
$error = '';
$booking = null;
$allExtras = [];
$currentExtrasIds = [];

if (empty($reservationNumber)) {
    $error = "No reservation number provided.";
} else {
    try {
        $pdo = getPDO();
        
        // Get booking
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE reservation_number = ? LIMIT 1");
        $stmt->execute([$reservationNumber]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            $error = "Reservation not found.";
        } else {
            // Get all available extras
            $extrasStmt = $pdo->prepare("SELECT * FROM extras ORDER BY name");
            $extrasStmt->execute();
            $allExtras = $extrasStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get current extras for this booking
            $currentStmt = $pdo->prepare("SELECT extra_id FROM booking_extras WHERE booking_id = ?");
            $currentStmt->execute([$booking['id']]);
            $currentExtrasIds = array_column($currentStmt->fetchAll(PDO::FETCH_ASSOC), 'extra_id');
        }
    } catch (Exception $e) {
        $error = "Error retrieving booking information.";
        error_log("Manage extras error: " . $e->getMessage());
    }
}

// Calculate days until check-in
$daysUntilCheckin = 999;
$requiresImmediatePayment = false;
if ($booking) {
    $checkinDate = new DateTime($booking['checkin_date']);
    $today = new DateTime();
    $daysUntilCheckin = $today->diff($checkinDate)->days;
    $requiresImmediatePayment = $daysUntilCheckin <= 90;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Extras - Barbs Bali Apartments</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 20px auto; 
            padding: 20px; 
            background: #f5f5f5; 
        }
        .container { 
            background: white; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .header { 
            text-align: center; 
            color: #6f42c1; 
            margin-bottom: 30px; 
        }
        .booking-summary {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #007bff;
        }
        .extras-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            border-left: 4px solid #6f42c1;
        }
        .extra-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin: 10px 0;
            background: white;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }
        .extra-item:hover {
            border-color: #6f42c1;
            transform: translateY(-1px);
        }
        .extra-item input[type="checkbox"] {
            margin-right: 15px;
            transform: scale(1.3);
        }
        .extra-details {
            flex: 1;
        }
        .extra-name {
            font-weight: bold;
            color: #495057;
            font-size: 16px;
        }
        .extra-price {
            color: #28a745;
            font-weight: bold;
            font-size: 18px;
        }
        .extra-type {
            color: #6c757d;
            font-size: 14px;
        }
        .btn {
            background: #6f42c1;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background: #5a32a3;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: center;
        }
        .warning {
            color: #856404;
            background: #fff3cd;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        .price-preview {
            background: #d4edda;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        .back-link {
            text-align: center;
            margin-top: 30px;
        }
        .back-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        @media (max-width: 600px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
            .extra-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
    <script>
        function updatePricePreview() {
            // This will be enhanced with AJAX to show real-time pricing
            const checkboxes = document.querySelectorAll('input[name="extras[]"]:checked');
            const count = checkboxes.length;
            
            const preview = document.getElementById('price-preview');
            if (preview) {
                if (count > 0) {
                    preview.style.display = 'block';
                    document.getElementById('selected-count').textContent = count;
                } else {
                    preview.style.display = 'none';
                }
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[name="extras[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updatePricePreview);
            });
            updatePricePreview();
        });
    </script>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🎯 Manage Booking Extras</h1>
        <p>Barbs Bali Apartments - Customize Your Stay</p>
    </div>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
            <br><br>
            <a href="my-booking.php">← Back to Booking Lookup</a>
        </div>
    <?php else: ?>
        
        <div class="booking-summary">
            <h3>📋 Booking Summary</h3>
            <div class="info-row">
                <span class="info-label">Reservation:</span>
                <span><?= htmlspecialchars($booking['reservation_number']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Guest:</span>
                <span><?= htmlspecialchars($booking['guest_first_name'] . ' ' . $booking['guest_last_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Check-in:</span>
                <span><?= date('F j, Y', strtotime($booking['checkin_date'])) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Check-out:</span>
                <span><?= date('F j, Y', strtotime($booking['checkout_date'])) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Current Total:</span>
                <span>$<?= number_format($booking['total_price'], 2) ?> AUD</span>
            </div>
        </div>

        <?php if ($requiresImmediatePayment): ?>
            <div class="warning">
                <strong>⚠️ Payment Required:</strong> Your check-in is within 90 days (<?= $daysUntilCheckin ?> days). 
                Any changes to extras will require immediate payment of any additional balance.
            </div>
        <?php endif; ?>

        <div class="extras-form">
            <h3>Select Your Extras</h3>
            <p>Choose the extras you'd like to add to your stay. You can select or deselect items as needed.</p>
            
            <form method="POST" action="../api/update-extras.php">
                <input type="hidden" name="reservation_number" value="<?= htmlspecialchars($reservationNumber) ?>">
                
                <?php foreach ($allExtras as $extra): ?>
                    <div class="extra-item">
                        <input type="checkbox" 
                               name="extras[]" 
                               value="<?= $extra['id'] ?>"
                               id="extra_<?= $extra['id'] ?>"
                               <?= in_array($extra['id'], $currentExtrasIds) ? 'checked' : '' ?>>
                        
                        <label for="extra_<?= $extra['id'] ?>" class="extra-details">
                            <div class="extra-name"><?= htmlspecialchars($extra['name']) ?></div>
                            <div class="extra-price">$<?= number_format($extra['price'], 2) ?> AUD</div>
                            <div class="extra-type"><?= $extra['per_night'] ? 'Per night' : 'One-time charge' ?></div>
                        </label>
                    </div>
                <?php endforeach; ?>

                <div id="price-preview" class="price-preview" style="display: none;">
                    <h4>📊 Selection Summary</h4>
                    <p>You have selected <span id="selected-count">0</span> extras. Your booking total will be recalculated after saving.</p>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn">💾 Save Changes</button>
                    <a href="my-booking.php?res=<?= urlencode($reservationNumber) ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <div class="back-link">
            <a href="my-booking.php?res=<?= urlencode($reservationNumber) ?>">← Back to Booking Details</a>
        </div>

    <?php endif; ?>
</div>

</body>
</html>