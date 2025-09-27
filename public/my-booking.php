<?php
// public/my-booking.php - Enhanced customer booking management with email verification

require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../includes/price-calc.php';
require_once __DIR__ . '/../config/config.php';

// Handle booking lookup
$booking = null;
$payments = [];
$error = '';
$success = '';

if (isset($_GET['res']) || isset($_POST['reservation_number'])) {
    $reservationNumber = $_GET['res'] ?? $_POST['reservation_number'];
    $guestEmail = $_POST['guest_email'] ?? '';
    
    if (!empty($reservationNumber) && !empty($guestEmail)) {
        try {
            $pdo = getPDO();
            
            // Get booking details WITH email verification
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE reservation_number = ? AND guest_email = ? LIMIT 1");
            $stmt->execute([$reservationNumber, $guestEmail]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($booking) {
                // Get payment history
                $paymentStmt = $pdo->prepare("
                    SELECT amount, method, note, paid_at 
                    FROM payments 
                    WHERE booking_id = ? 
                    ORDER BY paid_at DESC
                ");
                $paymentStmt->execute([$booking['id']]);
                $payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Calculate balance
                $totalPaid = array_sum(array_column($payments, 'amount'));
                $balanceLeft = max($booking['total_price'] - $totalPaid, 0);
                
                // Get current extras
                $extrasStmt = $pdo->prepare("
                    SELECT e.id, e.name, e.price, e.per_night, be.id as booking_extra_id
                    FROM extras e
                    LEFT JOIN booking_extras be ON e.id = be.extra_id AND be.booking_id = ?
                    ORDER BY e.name
                ");
                $extrasStmt->execute([$booking['id']]);
                $allExtras = $extrasStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Separate current extras from available extras
                $currentExtras = [];
                $availableExtras = [];
                
                foreach ($allExtras as $extra) {
                    if ($extra['booking_extra_id']) {
                        $currentExtras[] = $extra;
                    } else {
                        $availableExtras[] = $extra;
                    }
                }
                
            } else {
                $error = "Reservation not found or email address doesn't match. Please check your reservation number and email address.";
            }
        } catch (Exception $e) {
            $error = "Error retrieving booking information.";
            error_log("Booking lookup error: " . $e->getMessage());
        }
    } elseif (!empty($reservationNumber) && empty($guestEmail)) {
        $error = "Please enter both reservation number and email address to access your booking.";
    }
}

// Handle success messages
if (isset($_GET['updated'])) {
    $success = "Your booking has been successfully updated!";
}
if (isset($_GET['payment'])) {
    $success = "Payment received successfully! Thank you.";
}

// Calculate days until check-in for payment requirements
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
    <title>My Booking - Barbs Bali Apartments</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 900px; 
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
            color: #2e8b57; 
            margin-bottom: 30px; 
        }
        .lookup-form { 
            background: #f8f9fa; 
            padding: 25px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            border-left: 4px solid #007bff;
        }
        .security-notice {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #0066cc;
            font-size: 14px;
        }
        .booking-details { 
            background: #e7f3ff; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            border-left: 4px solid #007bff;
        }
        .payment-section { 
            background: #fff3cd; 
            padding: 20px; 
            border-radius: 8px; 
            border-left: 4px solid #ffc107; 
            margin-bottom: 20px;
        }
        .extras-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #6f42c1;
            margin-bottom: 20px;
        }
        .balance-payment { 
            background: #f8d7da; 
            padding: 20px; 
            border-radius: 8px; 
            border-left: 4px solid #dc3545; 
            margin-top: 20px; 
        }
        .btn { 
            background: #007bff; 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block; 
            margin: 5px; 
            font-size: 14px;
        }
        .btn:hover { 
            background: #0056b3; 
        }
        .btn-success { 
            background: #28a745; 
        }
        .btn-success:hover { 
            background: #1e7e34; 
        }
        .btn-purple {
            background: #6f42c1;
        }
        .btn-purple:hover {
            background: #5a32a3;
        }
        .error { 
            color: #dc3545; 
            background: #f8d7da; 
            padding: 15px; 
            border-radius: 4px; 
            margin: 15px 0; 
            border-left: 4px solid #dc3545;
        }
        .success { 
            color: #155724; 
            background: #d4edda; 
            padding: 15px; 
            border-radius: 4px; 
            margin: 15px 0; 
            border-left: 4px solid #28a745;
        }
        .warning {
            color: #856404;
            background: #fff3cd;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
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
        .payment-history { 
            margin-top: 20px; 
        }
        .payment-item { 
            background: #f8f9fa; 
            padding: 10px; 
            margin: 5px 0; 
            border-radius: 4px; 
            border-left: 3px solid #28a745; 
        }
        .extras-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .extras-card {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .extras-card h4 {
            margin-top: 0;
            color: #495057;
        }
        .extra-item {
            padding: 10px;
            margin: 8px 0;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #6f42c1;
        }
        .extra-item.current {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .add-extras-form {
            background: white;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .checkbox-group {
            margin: 10px 0;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            margin: 5px 0;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .checkbox-group label:hover {
            background: #e9ecef;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }
        .price-tag {
            font-weight: bold;
            color: #28a745;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #495057;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        @media (max-width: 600px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
            .info-row {
                flex-direction: column;
                gap: 5px;
            }
            .extras-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🏨 My Booking</h1>
        <p>Barbs Bali Apartments - Booking Management</p>
    </div>

    <?php if ($error): ?>
        <div class="error">
            <strong>⚠️ Access Denied:</strong> <?= htmlspecialchars($error) ?>
            <div style="text-align: center; margin-top: 15px;">
                <a href="index.html" class="btn">🏠 Back to Home</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">
            <strong>✅ Success:</strong> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!$booking): ?>
        <div class="security-notice">
            <strong>🔒 Secure Access:</strong> For your privacy and security, we require both your reservation number and email address to access your booking information.
        </div>

        <div class="lookup-form">
            <h3>Look Up Your Booking</h3>
            <p>Please enter your reservation details to securely access your booking information.</p>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="reservation_number">Reservation Number *</label>
                        <input type="text" 
                               id="reservation_number" 
                               name="reservation_number" 
                               class="form-control"
                               placeholder="e.g., BB12345" 
                               value="<?= htmlspecialchars($_POST['reservation_number'] ?? $_GET['res'] ?? '') ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="guest_email">Email Address *</label>
                        <input type="email" 
                               id="guest_email" 
                               name="guest_email" 
                               class="form-control"
                               placeholder="your.email@example.com" 
                               value="<?= htmlspecialchars($_POST['guest_email'] ?? '') ?>"
                               required>
                    </div>
                </div>
                
                <button type="submit" class="btn">🔍 Access My Booking</button>
                
                <div style="margin-top: 15px; font-size: 12px; color: #6c757d;">
                    <strong>Privacy Notice:</strong> Your booking information is protected. We'll only show details if both your reservation number and email address match our records.
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="booking-details">
            <h3>📋 Booking Details</h3>
            
            <div class="info-row">
                <span class="info-label">Reservation Number:</span>
                <span><?= htmlspecialchars($booking['reservation_number']) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Guest Name:</span>
                <span><?= htmlspecialchars($booking['guest_first_name'] . ' ' . $booking['guest_last_name']) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span><?= htmlspecialchars($booking['guest_email']) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Apartment:</span>
                <span><?= $booking['apartment_id'] == 1 ? 'Apartment 6205' : ($booking['apartment_id'] == 2 ? 'Apartment 6207' : 'Both Apartments') ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Check-in:</span>
                <span><?= date('l, F j, Y', strtotime($booking['checkin_date'])) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Check-out:</span>
                <span><?= date('l, F j, Y', strtotime($booking['checkout_date'])) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Sofa Bed:</span>
                <span><?= $booking['sofa_bed'] ? '✅ Included' : '❌ Not included' ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Total Amount:</span>
                <span>$<?= number_format($booking['total_price'], 2) ?> AUD</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span style="color: <?= $booking['status'] == 'confirmed' ? '#28a745' : '#ffc107' ?>;">
                    <?= ucfirst($booking['status']) ?>
                </span>
            </div>
        </div>

        <!-- Extras Management Section -->
        <div class="extras-section">
            <h3>🎯 Booking Extras</h3>
            
            <?php if ($requiresImmediatePayment && $balanceLeft == 0): ?>
                <div class="warning">
                    <strong>⚠️ Payment Required:</strong> Your check-in is within 90 days. Any additional extras will require immediate payment.
                </div>
            <?php endif; ?>
            
            <div class="extras-grid">
                <!-- Current Extras -->
                <div class="extras-card">
                    <h4>✅ Current Extras</h4>
                    <?php if (empty($currentExtras)): ?>
                        <p style="color: #6c757d;">No extras currently selected.</p>
                    <?php else: ?>
                        <?php foreach ($currentExtras as $extra): ?>
                            <div class="extra-item current">
                                <strong><?= htmlspecialchars($extra['name']) ?></strong><br>
                                <span class="price-tag">$<?= number_format($extra['price'], 2) ?> AUD</span>
                                <?= $extra['per_night'] ? ' per night' : ' (one-time)' ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Available Extras -->
                <div class="extras-card">
                    <h4>➕ Add More Extras</h4>
                    <?php if (empty($availableExtras)): ?>
                        <p style="color: #6c757d;">All available extras are already added to your booking.</p>
                    <?php else: ?>
                        <form method="POST" action="../api/add-extras.php" class="add-extras-form">
                            <input type="hidden" name="reservation_number" value="<?= htmlspecialchars($booking['reservation_number']) ?>">
                            <input type="hidden" name="guest_email" value="<?= htmlspecialchars($booking['guest_email']) ?>">
                            
                            <div class="checkbox-group">
                                <?php foreach ($availableExtras as $extra): ?>
                                    <label>
                                        <input type="checkbox" name="extras[]" value="<?= $extra['id'] ?>">
                                        <div>
                                            <strong><?= htmlspecialchars($extra['name']) ?></strong><br>
                                            <span class="price-tag">$<?= number_format($extra['price'], 2) ?> AUD</span>
                                            <?= $extra['per_night'] ? ' per night' : ' (one-time)' ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="submit" class="btn btn-purple">Add Selected Extras</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($currentExtras)): ?>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="manage-extras.php?res=<?= urlencode($booking['reservation_number']) ?>&email=<?= urlencode($booking['guest_email']) ?>" class="btn btn-purple">
                        🔧 Manage All Extras
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php 
        $totalPaid = array_sum(array_column($payments, 'amount'));
        $balanceLeft = max($booking['total_price'] - $totalPaid, 0);
        ?>

        <div class="payment-section">
            <h3>💳 Payment Information</h3>
            
            <div class="info-row">
                <span class="info-label">Total Booking Amount:</span>
                <span>$<?= number_format($booking['total_price'], 2) ?> AUD</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Amount Paid:</span>
                <span>$<?= number_format($totalPaid, 2) ?> AUD</span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Outstanding Balance:</span>
                <span style="color: <?= $balanceLeft > 0 ? '#dc3545' : '#28a745' ?>; font-weight: bold;">
                    $<?= number_format($balanceLeft, 2) ?> AUD
                </span>
            </div>

            <?php if ($balanceLeft > 0): ?>
                <div class="balance-payment">
                    <h4>💰 Balance Payment Required</h4>
                    <p>You have an outstanding balance of <strong>$<?= number_format($balanceLeft, 2) ?> AUD</strong></p>
                    <p>Please pay your balance using one of the secure payment options below:</p>
                    
                    <a href="pay-balance.php?res=<?= urlencode($booking['reservation_number']) ?>&amount=<?= $balanceLeft ?>" 
                       class="btn btn-success">
                        💳 Pay Balance Now
                    </a>
                </div>
            <?php else: ?>
                <div style="color: #28a745; font-weight: bold; text-align: center; padding: 10px;">
                    ✅ Your booking is fully paid! Thank you!
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($payments)): ?>
            <div class="payment-history">
                <h3>📊 Payment History</h3>
                
                <?php foreach ($payments as $payment): ?>
                    <div class="payment-item">
                        <strong>$<?= number_format($payment['amount'], 2) ?> AUD</strong>
                        via <?= htmlspecialchars(ucfirst($payment['method'])) ?>
                        <br>
                        <small><?= date('F j, Y g:i A', strtotime($payment['paid_at'])) ?></small>
                        <?php if ($payment['note']): ?>
                            <br><small><?= htmlspecialchars($payment['note']) ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="my-booking.php" class="btn">🔍 Look Up Another Booking</a>
            <a href="index.html" class="btn">🏠 Back to Home</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>