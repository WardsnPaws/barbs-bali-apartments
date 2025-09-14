<?php
require_once 'config.php';
session_start();
// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Secure Payment – Barbs Bali Apartments</title>
  <script src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
  <script src="https://www.paypal.com/sdk/js?client-id=<?php echo PAYPAL_CLIENT_ID; ?>&currency=AUD"></script>
  <style>
    body { font-family: Arial; padding: 30px; background: #f9f9f9; max-width: 700px; margin: auto; }
    .summary { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 8px rgba(0,0,0,0.1); margin-bottom: 30px; }
    h2 { color: #2e8b57; }
    #card-container, #paypal-button-container { margin-top: 20px; margin-bottom: 20px; }
    #status { font-weight: bold; margin-top: 15px; }
    button { padding: 12px 24px; background: #2e8b57; color: white; border: none; border-radius: 5px; font-size: 1.1em; cursor: pointer; }
    button:hover { background: #256b47; }
    label { margin-right: 20px; }
  </style>
</head>
<body>

<h2>Secure Payment</h2>

<div class="summary" id="bookingSummary">Loading booking details...</div>

<div>
  <label><input type="radio" name="payment_method" value="square" checked> Credit Card</label>
  <label><input type="radio" name="payment_method" value="paypal"> PayPal</label>
</div>

<div id="square-section">
  <div id="card-container"></div>
  <button id="pay-btn">Pay with Card</button>
</div>

<div id="paypal-section" style="display:none;">
  <div id="paypal-button-container"></div>
</div>

<div id="status"></div>

<script>
let booking = {};

async function main() {
  const response = await fetch("get-session-booking.php");
  booking = await response.json();

  if (!booking || !booking.guest_first_name) {
    document.getElementById('bookingSummary').textContent = '⚠️ Booking session expired.';
    return;
  }

  const amountDue = parseFloat(booking.amount_due);
  const totalPrice = parseFloat(booking.total_price);
  const isDeposit = booking.is_deposit === true || booking.is_deposit === '1';
  const label = isDeposit ? `Deposit Due Now (20% of $${totalPrice.toFixed(2)})` : 'Full Payment Due Now';

  document.getElementById('bookingSummary').innerHTML = `
    <p><strong>Guest:</strong> ${booking.guest_first_name} ${booking.guest_last_name}</p>
    <p><strong>Email:</strong> ${booking.guest_email}</p>
    <p><strong>Apartment:</strong> ${booking.apartment_id == 1 ? '6205' : (booking.apartment_id == 2 ? '6207' : 'Both')}</p>
    <p><strong>Check-in:</strong> ${booking.checkin_date}</p>
    <p><strong>Check-out:</strong> ${booking.checkout_date}</p>
    <p><strong>Total Price:</strong> $${totalPrice.toFixed(2)} AUD</p>
    <p><strong>${label}:</strong> <strong>$${amountDue.toFixed(2)} AUD</strong></p>
  `;

  const appId = "<?= SQUARE_APPLICATION_ID ?>";
  const locationId = "<?= SQUARE_LOCATION_ID ?>";
  const payments = Square.payments(appId, locationId);
  const card = await payments.card();
  await card.attach("#card-container");

  document.getElementById("pay-btn").addEventListener("click", async () => {
    const status = document.getElementById("status");
    status.textContent = "Processing payment...";
    const result = await card.tokenize();

    if (result.status !== "OK") {
      status.textContent = "❌ Card error: " + result.errors[0].message;
      return;
    }

    fetch("square-payment.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ token: result.token })
    })
    .then(res => res.json())
    .then(response => {
      if (response.success) {
        window.location.href = "thank-you.html";
      } else {
        status.textContent = "❌ Payment failed: " + response.message;
      }
    })
    .catch(error => {
      status.textContent = "❌ Error processing payment.";
      console.error(error);
    });
  });

  paypal.Buttons({
    createOrder: (data, actions) => {
      return actions.order.create({
        purchase_units: [{
          amount: { value: amountDue.toFixed(2) },
          description: `Barbs Bali Booking - ${booking.guest_first_name} ${booking.guest_last_name}`
        }]
      });
    },
    onApprove: (data, actions) => {
      return actions.order.capture().then(function(details) {
        document.getElementById("status").textContent = "✅ Payment received via PayPal.";
        fetch("mark-paid.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ payment_method: 'paypal', booking: booking })
        })
        .then(() => {
          window.location.href = "thank-you.html";
        });
      });
    },
    onError: err => {
      document.getElementById("status").textContent = "❌ PayPal error.";
      console.error(err);
    }
  }).render("#paypal-button-container");
}

main();

const radios = document.querySelectorAll('input[name="payment_method"]');
radios.forEach(radio => {
  radio.addEventListener('change', () => {
    document.getElementById('square-section').style.display =
      radio.value === 'square' ? 'block' : 'none';
    document.getElementById('paypal-section').style.display =
      radio.value === 'paypal' ? 'block' : 'none';
  });
});
</script>

</body>
</html>