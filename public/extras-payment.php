<?php
// public/extras-payment.php

require_once __DIR__ . '/../includes/core.php';
session_start();

$resNum = $_GET['res'] ?? '';

// Check if we have payment session data
if (!isset($_SESSION['extras_payment'])) {
    die("Payment session expired. Please update your extras again.");
}

$paymentInfo = $_SESSION['extras_payment'];

// Verify reservation number matches
if ($paymentInfo['reservation_number'] !== $resNum) {
    die("Invalid reservation number.");
}

$balanceOwed = $paymentInfo['balance_owed'];
$bookingTotal = $paymentInfo['booking_total'];
$guestName = $paymentInfo['guest_name'];
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Payment Required - Barbs Bali Apartments</title>
  <script src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
  <style>
    body { 
      font-family: Arial, sans-serif; 
      padding: 30px; 
      background: #f9f9f9; 
      max-width: 600px; 
      margin: auto; 
    }
    .container { 
      background: #fff; 
      padding: 30px; 
      border-radius: 10px; 
      box-shadow: 0 0 10px rgba(0,0,0,0.1); 
    }
    h2 { 
      color: #2e8b57; 
      text-align: center; 
      margin-bottom: 20px; 
    }
    .payment-info {
      background: #e7f3ff;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 25px;
      border-left: 4px solid #007bff;
    }
    .payment-info h3 {
      margin-top: 0;
      color: #007bff;
    }
    .amount-due {
      font-size: 1.5em;
      font-weight: bold;
      color: #dc3545;
      text-align: center;
      margin: 20px 0;
    }
    #card-container { 
      margin: 20px 0; 
      padding: 15px;
      border: 2px dashed #ddd;
      border-radius: 8px;
    }
    button { 
      width: 100%;
      padding: 15px; 
      background: #2e8b57; 
      color: white; 
      border: none; 
      border-radius: 5px; 
      font-size: 1.1em; 
      cursor: pointer; 
      margin-top: 15px;
    }
    button:hover { 
      background: #256b47; 
    }
    button:disabled {
      background: #ccc;
      cursor: not-allowed;
    }
    #status { 
      font-weight: bold; 
      margin-top: 15px; 
      text-align: center;
      padding: 10px;
      border-radius: 5px;
    }
    .back-link {
      display: inline-block;
      margin-bottom: 20px;
      color: #007bff;
      text-decoration: none;
      font-weight: bold;
    }
    .back-link:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

<div class="container">
  <a href="my-booking.php?res=<?= htmlspecialchars($resNum) ?>" class="back-link">← Back to My Booking</a>
  
  <h2>Payment Required</h2>
  
  <div class="payment-info">
    <h3>Extras Payment Due</h3>
    <p><strong>Guest:</strong> <?= htmlspecialchars($guestName) ?></p>
    <p><strong>Reservation:</strong> <?= htmlspecialchars($resNum) ?></p>
    <p><strong>Updated Booking Total:</strong> $<?= number_format($bookingTotal, 2) ?> AUD</p>
    <p>Since your check-in is within 90 days, payment for the additional extras is required immediately.</p>
  </div>

  <div class="amount-due">
    Amount Due: $<?= number_format($balanceOwed, 2) ?> AUD
  </div>

  <div id="card-container">
    <p style="text-align: center; color: #666; margin: 0;">Enter your card details below</p>
  </div>
  
  <button id="pay-btn">Pay $<?= number_format($balanceOwed, 2) ?> AUD</button>
  
  <div id="status"></div>
</div>

<script>
async function main() {
  const appId = "<?= SQUARE_APPLICATION_ID ?>";
  const locationId = "<?= SQUARE_LOCATION_ID ?>";
  
  try {
    const payments = Square.payments(appId, locationId);
    const card = await payments.card();
    await card.attach("#card-container");

    document.getElementById("pay-btn").addEventListener("click", async () => {
      const status = document.getElementById("status");
      const payBtn = document.getElementById("pay-btn");
      
      // Disable button and show processing
      payBtn.disabled = true;
      payBtn.textContent = "Processing...";
      status.textContent = "Processing payment...";
      status.style.backgroundColor = "#fff3cd";
      status.style.color = "#856404";

      try {
        const result = await card.tokenize();
        
        if (result.status !== "OK") {
          throw new Error(result.errors[0].message);
        }

        const response = await fetch("../api/process-extras-payment.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            token: result.token,
            amount: <?= $balanceOwed ?>,
            reservation: "<?= $resNum ?>"
          })
        });
        
        const data = await response.json();
        
        if (data.success) {
          status.textContent = "✅ Payment successful! Redirecting...";
          status.style.backgroundColor = "#d4edda";
          status.style.color = "#155724";
          
          // Redirect back to booking page after success
          setTimeout(() => {
            window.location.href = "my-booking.php?res=<?= $resNum ?>&payment_success=1";
          }, 2000);
        } else {
          throw new Error(data.message || "Payment failed");
        }
        
      } catch (error) {
        status.textContent = "❌ " + error.message;
        status.style.backgroundColor = "#f8d7da";
        status.style.color = "#721c24";
        
        // Re-enable button
        payBtn.disabled = false;
        payBtn.textContent = "Pay $<?= number_format($balanceOwed, 2) ?> AUD";
      }
    });
    
  } catch (error) {
    document.getElementById("status").textContent = "❌ Failed to load payment form: " + error.message;
    document.getElementById("status").style.backgroundColor = "#f8d7da";
    document.getElementById("status").style.color = "#721c24";
  }
}

main();
</script>

</body>
</html>