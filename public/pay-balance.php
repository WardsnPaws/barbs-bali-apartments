<?php
// public/pay-balance.php - Enhanced balance payment interface with PayPal support

require_once __DIR__ . '/../includes/core.php';
require_once __DIR__ . '/../config/config.php';

$error = '';
$booking = null;
$balanceLeft = 0;

// Get reservation and amount from URL
$reservationNumber = $_GET['res'] ?? '';
$requestedAmount = floatval($_GET['amount'] ?? 0);

if (empty($reservationNumber)) {
    $error = "No reservation number provided.";
} else {
    try {
        $pdo = getPDO();
        
        // Get booking details
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE reservation_number = ? LIMIT 1");
        $stmt->execute([$reservationNumber]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            $error = "Reservation not found.";
        } else {
            // Calculate current balance
            $paidStmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE booking_id = ?");
            $paidStmt->execute([$booking['id']]);
            $totalPaid = (float) $paidStmt->fetchColumn();
            $balanceLeft = max($booking['total_price'] - $totalPaid, 0);
            
            // If no amount specified, use full balance
            if ($requestedAmount <= 0) {
                $requestedAmount = $balanceLeft;
            }
            
            // Validate requested amount doesn't exceed balance
            if ($requestedAmount > $balanceLeft + 0.01) {
                $requestedAmount = $balanceLeft;
            }
        }
    } catch (Exception $e) {
        $error = "Error retrieving booking information.";
        error_log("Balance payment lookup error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Balance - Barbs Bali Apartments</title>
    
    <!-- Square Payment SDK -->
    <script src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
    
    <!-- PayPal Payment SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?= PAYPAL_CLIENT_ID ?>&currency=AUD"></script>
    
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 700px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
            line-height: 1.6;
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
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .booking-summary {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #007bff;
        }
        .booking-summary h3 {
            margin-top: 0;
            color: #0056b3;
        }
        .payment-amount {
            background: #fff3cd;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #ffc107;
            text-align: center;
        }
        .payment-amount h3 {
            margin-top: 0;
            color: #856404;
        }
        .amount-display {
            font-size: 32px;
            font-weight: bold;
            color: #28a745;
            margin: 15px 0;
        }
        .payment-method-selector {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .payment-method-selector h3 {
            margin-top: 0;
            color: #495057;
            font-size: 18px;
        }
        .payment-method-selector label {
            display: block;
            margin: 12px 0;
            padding: 15px;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: normal;
        }
        .payment-method-selector label:hover {
            border-color: #007bff;
            background: #f8f9ff;
            transform: translateY(-1px);
        }
        .payment-method-selector input[type="radio"] {
            margin-right: 12px;
            transform: scale(1.2);
        }
        .payment-method-selector input[type="radio"]:checked + .method-text {
            font-weight: bold;
            color: #007bff;
        }
        .method-text {
            font-size: 16px;
        }
        .payment-section {
            margin: 20px 0;
            padding: 25px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: white;
        }
        .payment-section h4 {
            margin-top: 0;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
        }
        .payment-section p {
            color: #6c757d;
            margin-bottom: 20px;
        }
        #card-container {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fafafa;
            min-height: 120px;
        }
        #paypal-button-container {
            margin: 20px 0;
            min-height: 50px;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            width: 100%;
        }
        .btn:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #1e7e34;
        }
        #status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            font-weight: bold;
            display: none;
        }
        #status.processing {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            display: block;
        }
        #status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        #status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            text-align: center;
            border: 1px solid #f5c6cb;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        .back-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .back-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .security-info {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            border-left: 4px solid #28a745;
            font-size: 14px;
            color: #155724;
        }
        
        @media (max-width: 600px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
            .payment-method-selector label {
                padding: 12px;
            }
            .amount-display {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>💳 Pay Balance</h1>
        <p>Barbs Bali Apartments - Secure Payment</p>
    </div>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
            <br><br>
            <a href="my-booking.php">← Back to Booking Lookup</a>
        </div>
    <?php elseif ($balanceLeft <= 0): ?>
        <div class="error">
            🎉 This booking is already fully paid!
            <br><br>
            <a href="my-booking.php?res=<?= urlencode($reservationNumber) ?>">← Back to Booking Details</a>
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
                <span class="info-label">Apartment:</span>
                <span><?= $booking['apartment_id'] == 1 ? 'Apartment 6205' : ($booking['apartment_id'] == 2 ? 'Apartment 6207' : 'Both Apartments') ?></span>
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
                <span class="info-label">Total Booking:</span>
                <span>$<?= number_format($booking['total_price'], 2) ?> AUD</span>
            </div>
            <div class="info-row">
                <span class="info-label">Outstanding Balance:</span>
                <span style="color: #dc3545; font-weight: bold;">$<?= number_format($balanceLeft, 2) ?> AUD</span>
            </div>
        </div>

        <div class="payment-amount">
            <h3>💰 Payment Amount</h3>
            <div class="amount-display">
                $<span id="payment-amount"><?= number_format($requestedAmount, 2) ?></span> AUD
            </div>
            <input type="hidden" id="payment-amount-value" value="<?= $requestedAmount ?>">
            <input type="hidden" id="reservation-number" value="<?= htmlspecialchars($reservationNumber) ?>">
        </div>

        <div class="payment-method-selector">
            <h3>Choose Payment Method</h3>
            
            <label>
                <input type="radio" name="payment_method" value="square" checked>
                <span class="method-text">💳 Credit/Debit Card (Visa, Mastercard, Amex)</span>
            </label>
            
            <label>
                <input type="radio" name="payment_method" value="paypal">
                <span class="method-text">🔵 PayPal (PayPal Balance, Bank Account, or Card via PayPal)</span>
            </label>
        </div>

        <!-- Square Payment Section -->
        <div id="square-section" class="payment-section">
            <h4>💳 Credit/Debit Card Payment</h4>
            <p>Enter your card details below. All payments are processed securely through Square.</p>
            <div id="card-container"></div>
            <button id="pay-btn" class="btn">Pay $<?= number_format($requestedAmount, 2) ?> AUD with Card</button>
        </div>

        <!-- PayPal Payment Section -->
        <div id="paypal-section" class="payment-section" style="display: none;">
            <h4>🔵 PayPal Payment</h4>
            <p>Click the PayPal button below to pay securely through PayPal. You can use your PayPal balance, bank account, or any card linked to your PayPal account.</p>
            <div id="paypal-button-container"></div>
        </div>

        <div id="status"></div>

        <div class="security-info">
            🔒 <strong>Secure Payment:</strong> All payments are processed through encrypted, PCI-compliant payment processors. Your financial information is never stored on our servers.
        </div>

        <div class="back-link">
            <a href="my-booking.php?res=<?= urlencode($reservationNumber) ?>">← Back to Booking Details</a>
        </div>

    <?php endif; ?>
</div>

<script>
// Global variables
let payments;
let card;
const reservationNumber = document.getElementById('reservation-number')?.value;
const paymentAmount = parseFloat(document.getElementById('payment-amount-value')?.value || 0);

// Initialize everything when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (reservationNumber && paymentAmount > 0) {
        initializeSquarePayments();
        initializePayPal();
        setupPaymentMethodToggle();
    }
});

// Initialize Square Payment System
async function initializeSquarePayments() {
    try {
        const appId = "<?= SQUARE_APPLICATION_ID ?>";
        const locationId = "<?= SQUARE_LOCATION_ID ?>";
        
        if (!appId || !locationId) {
            throw new Error('Square configuration missing');
        }
        
        payments = Square.payments(appId, locationId);
        card = await payments.card();
        await card.attach("#card-container");

        document.getElementById("pay-btn").addEventListener("click", handleSquarePayment);
        
        console.log('✅ Square payment system initialized');
        
    } catch (error) {
        console.error('Square initialization error:', error);
        document.getElementById('card-container').innerHTML = 
            '<p style="color: #dc3545; text-align: center; padding: 20px;">Error initializing card payment. Please try PayPal or contact support.</p>';
    }
}

// Handle Square Payment
async function handleSquarePayment() {
    const statusEl = document.getElementById("status");
    const payBtn = document.getElementById("pay-btn");
    
    statusEl.className = 'processing';
    statusEl.textContent = "Processing card payment...";
    payBtn.disabled = true;
    
    try {
        const result = await card.tokenize();

        if (result.status !== "OK") {
            throw new Error(result.errors[0].message);
        }

        // Send payment to server
        const response = await fetch("../api/pay-balance-process.php", {
            method: "POST",
            headers: { 
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({ 
                token: result.token,
                amount: paymentAmount,
                reservation: reservationNumber
            })
        });

        if (!response.ok) {
            throw new Error(`Server error: ${response.status} ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success) {
            statusEl.textContent = "✅ Payment successful! Redirecting...";
            statusEl.className = 'success';
            
            setTimeout(() => {
                window.location.href = `my-booking.php?res=${encodeURIComponent(reservationNumber)}&payment=success`;
            }, 2000);
        } else {
            throw new Error(data.message || 'Payment failed');
        }

    } catch (error) {
        console.error('Square payment error:', error);
        statusEl.textContent = "❌ Payment failed: " + error.message;
        statusEl.className = 'error';
        payBtn.disabled = false;
    }
}

// Initialize PayPal Payment System
function initializePayPal() {
    try {
        paypal.Buttons({
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: paymentAmount.toFixed(2),
                            currency_code: 'AUD'
                        },
                        description: `Balance payment for reservation ${reservationNumber}`
                    }]
                });
            },
            
            onApprove: async function(data, actions) {
                try {
                    const details = await actions.order.capture();
                    
                    const statusEl = document.getElementById('status');
                    statusEl.textContent = "Processing PayPal payment...";
                    statusEl.className = 'processing';
                    
                    console.log('PayPal payment details:', details);
                    
                    // Send PayPal payment to server
                    const response = await fetch('../api/pay-balance-paypal.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            payment_method: 'paypal',
                            amount: paymentAmount,
                            reservation: reservationNumber,
                            paypal_details: details
                        })
                    });
                    
                    if (!response.ok) {
                        throw new Error(`Server error: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    console.log('PayPal payment result:', result);
                    
                    if (result.success) {
                        statusEl.textContent = "✅ PayPal payment successful! Redirecting...";
                        statusEl.className = 'success';
                        
                        setTimeout(() => {
                            window.location.href = `my-booking.php?res=${encodeURIComponent(reservationNumber)}&payment=success`;
                        }, 2000);
                    } else {
                        throw new Error(result.message || 'PayPal payment failed');
                    }
                    
                } catch (error) {
                    console.error('PayPal payment error:', error);
                    document.getElementById('status').textContent = "❌ PayPal payment failed: " + error.message;
                    document.getElementById('status').className = 'error';
                }
            },
            
            onError: function(err) {
                console.error('PayPal error:', err);
                document.getElementById('status').textContent = "❌ PayPal payment error occurred";
                document.getElementById('status').className = 'error';
            }
        }).render('#paypal-button-container');
        
        console.log('✅ PayPal payment system initialized');
        
    } catch (error) {
        console.error('PayPal initialization error:', error);
        document.getElementById('paypal-button-container').innerHTML = 
            '<p style="color: #dc3545; text-align: center; padding: 20px;">Error initializing PayPal. Please try card payment or contact support.</p>';
    }
}

// Setup payment method toggle functionality
function setupPaymentMethodToggle() {
    const radios = document.querySelectorAll('input[name="payment_method"]');
    
    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            const squareSection = document.getElementById('square-section');
            const paypalSection = document.getElementById('paypal-section');
            const statusEl = document.getElementById('status');
            
            if (this.value === 'square') {
                squareSection.style.display = 'block';
                paypalSection.style.display = 'none';
                console.log('Switched to Square payment');
            } else if (this.value === 'paypal') {
                squareSection.style.display = 'none';
                paypalSection.style.display = 'block';
                console.log('Switched to PayPal payment');
            }
            
            // Clear any previous status messages when switching
            statusEl.textContent = '';
            statusEl.className = '';
        });
    });
}

// Debug information
console.log('Payment page loaded:', {
    reservationNumber: reservationNumber,
    paymentAmount: paymentAmount,
    paypalAvailable: typeof paypal !== 'undefined',
    squareAvailable: typeof Square !== 'undefined'
});
</script>

</body>
</html>