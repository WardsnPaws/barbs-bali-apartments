<?php
require_once __DIR__ . '/../config/config.php';
session_start();

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if we have booking session data
if (!isset($_SESSION['booking'])) {
    header('Location: ../public/index.html');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Secure Payment – Barbs Bali Apartments</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Updated Square Web Payments SDK -->
  <script type="text/javascript" src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
  <script src="https://www.paypal.com/sdk/js?client-id=<?php echo PAYPAL_CLIENT_ID; ?>&currency=AUD"></script>
  <style>
    body { 
      font-family: Arial, sans-serif; 
      padding: 30px; 
      background: #f9f9f9; 
      max-width: 700px; 
      margin: auto; 
      line-height: 1.6;
    }
    .container {
      background: #fff; 
      padding: 30px; 
      border-radius: 8px; 
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .summary { 
      background: #f8f9fa; 
      padding: 20px; 
      border-radius: 8px; 
      margin-bottom: 30px; 
      border-left: 4px solid #007bff;
    }
    h2 { 
      color: #2e8b57; 
      margin-top: 0;
    }
    .payment-method-selector {
      margin: 20px 0;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
    }
    .payment-method-selector label {
      display: inline-block;
      margin-right: 20px;
      font-weight: bold;
      cursor: pointer;
      padding: 8px 12px;
      border-radius: 4px;
      transition: background-color 0.3s;
    }
    .payment-method-selector label:hover {
      background-color: #e9ecef;
    }
    .payment-method-selector input[type="radio"] {
      margin-right: 8px;
    }
    #card-container, #paypal-button-container { 
      margin: 20px 0; 
      padding: 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      background: #fff;
    }
    .postal-code-note {
      font-size: 13px;
      color: #6c757d;
      margin: 10px 0;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 4px;
      border-left: 3px solid #007bff;
    }
    #status { 
      font-weight: bold; 
      margin: 15px 0; 
      padding: 10px;
      border-radius: 4px;
      text-align: center;
    }
    #status.processing {
      background: #fff3cd;
      color: #856404;
      border: 1px solid #ffeaa7;
    }
    #status.success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    #status.error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    button { 
      padding: 12px 24px; 
      background: #2e8b57; 
      color: white; 
      border: none; 
      border-radius: 5px; 
      font-size: 1.1em; 
      cursor: pointer; 
      transition: background-color 0.3s;
      width: 100%;
      margin-top: 10px;
    }
    button:hover { 
      background: #256b47; 
    }
    button:disabled {
      background: #ccc;
      cursor: not-allowed;
    }
    .security-notice {
      background: #e7f3ff;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      border-left: 4px solid #007bff;
    }
    .booking-detail {
      display: flex;
      justify-content: space-between;
      margin: 8px 0;
      padding: 5px 0;
      border-bottom: 1px solid #eee;
    }
    .booking-detail:last-child {
      border-bottom: none;
      font-weight: bold;
      font-size: 1.1em;
      color: #2e8b57;
    }
    @media (max-width: 600px) {
      body {
        padding: 15px;
      }
      .container {
        padding: 20px;
      }
      .payment-method-selector label {
        display: block;
        margin: 10px 0;
      }
    }
  </style>
</head>
<body>

<div class="container">
  <h2>Secure Payment</h2>

  <div class="security-notice">
    🔒 Your payment is secured with 256-bit SSL encryption. We never store your card details.
  </div>

  <div class="summary" id="bookingSummary">
    Loading booking details...
  </div>

  <div class="payment-method-selector">
    <label>
      <input type="radio" name="payment_method" value="square" checked> 
      Credit Card (Visa, Mastercard, Amex)
    </label>
    <label>
      <input type="radio" name="payment_method" value="paypal"> 
      PayPal
    </label>
  </div>

  <div id="square-section">
    <div id="card-container"></div>
    <div class="postal-code-note">
      <strong>Post Code/ZIP Code:</strong> Australian customers enter your 4-digit post code (e.g. 3000). International customers use your standard postal code format. This field is required for payment security verification.
    </div>
    <button id="pay-btn">Complete Payment</button>
  </div>

  <div id="paypal-section" style="display:none;">
    <div id="paypal-button-container"></div>
  </div>

  <div id="status"></div>
</div>

<script>
let booking = {};
let card = null;
let payments = null;

async function main() {
  try {
    // Load booking data
    const response = await fetch("get-session-booking.php");
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const contentType = response.headers.get("content-type");
    if (!contentType || !contentType.includes("application/json")) {
      throw new Error("Invalid response format");
    }
    
    booking = await response.json();

    if (!booking || !booking.guest_first_name) {
      document.getElementById('bookingSummary').innerHTML = 
        '<p style="color: #dc3545;">⚠️ Booking session expired. Please start over.</p>';
      return;
    }

    displayBookingSummary();
    await initializeSquarePayments();
    initializePayPal();
    setupPaymentMethodToggle();

  } catch (error) {
    console.error('Initialization error:', error);
    document.getElementById('bookingSummary').innerHTML = 
      '<p style="color: #dc3545;">⚠️ Error loading booking details. Please refresh the page.</p>';
  }
}

function displayBookingSummary() {
  const amountDue = parseFloat(booking.amount_due);
  const totalPrice = parseFloat(booking.total_price || booking.amount_due);
  const isDeposit = booking.is_deposit === true || booking.is_deposit === '1';
  
  let apartmentName;
  switch(booking.apartment_id) {
    case 1:
    case '1':
      apartmentName = 'Apartment 6205 (Ocean View)';
      break;
    case 2:
    case '2':
      apartmentName = 'Apartment 6207 (Garden View)';
      break;
    case 3:
    case '3':
      apartmentName = 'Both Apartments (6205 & 6207)';
      break;
    default:
      apartmentName = 'Selected Apartment';
  }

  const checkinDate = new Date(booking.checkin_date).toLocaleDateString('en-US', {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
  
  const checkoutDate = new Date(booking.checkout_date).toLocaleDateString('en-US', {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });

  const nights = Math.ceil((new Date(booking.checkout_date) - new Date(booking.checkin_date)) / (1000 * 60 * 60 * 24));

  document.getElementById('bookingSummary').innerHTML = `
    <h3>Booking Summary</h3>
    <div class="booking-detail">
      <span>Guest:</span>
      <span>${booking.guest_first_name} ${booking.guest_last_name}</span>
    </div>
    <div class="booking-detail">
      <span>Email:</span>
      <span>${booking.guest_email}</span>
    </div>
    <div class="booking-detail">
      <span>Apartment:</span>
      <span>${apartmentName}</span>
    </div>
    <div class="booking-detail">
      <span>Check-in:</span>
      <span>${checkinDate}</span>
    </div>
    <div class="booking-detail">
      <span>Check-out:</span>
      <span>${checkoutDate}</span>
    </div>
    <div class="booking-detail">
      <span>Duration:</span>
      <span>${nights} night${nights !== 1 ? 's' : ''}</span>
    </div>
    ${totalPrice !== amountDue ? `
    <div class="booking-detail">
      <span>Total Price:</span>
      <span>$${totalPrice.toFixed(2)} AUD</span>
    </div>
    ` : ''}
    <div class="booking-detail">
      <span>${isDeposit ? 'Amount Due Now:' : 'Total Amount:'}</span>
      <span>$${amountDue.toFixed(2)} AUD</span>
    </div>
  `;
}

async function initializeSquarePayments() {
  try {
    const appId = "<?= SQUARE_APPLICATION_ID ?>";
    const locationId = "<?= SQUARE_LOCATION_ID ?>";
    
    if (!appId || !locationId) {
      throw new Error('Square configuration missing');
    }
    
    // Check if Square is properly loaded
    if (typeof Square === 'undefined') {
      throw new Error('Square SDK not loaded');
    }
    
    // Initialize payments with current SDK format
    payments = Square.payments(appId, locationId);
    
    // Create card payment method with current syntax
    card = await payments.card();
    await card.attach('#card-container');

    // Setup payment button
    document.getElementById("pay-btn").addEventListener("click", handleSquarePayment);
    
    console.log('Square payments initialized successfully');
    
  } catch (error) {
    console.error('Square initialization error:', error);
    document.getElementById('card-container').innerHTML = 
      '<p style="color: #dc3545;">Error initializing payment system. Please try PayPal or contact support.</p>';
  }
}

async function handleSquarePayment() {
  const statusEl = document.getElementById("status");
  const payBtn = document.getElementById("pay-btn");
  
  // Reset status
  statusEl.className = '';
  statusEl.textContent = "Processing payment...";
  statusEl.className = 'processing';
  payBtn.disabled = true;
  
  try {
    // Tokenize the card with current SDK syntax
    const tokenResult = await card.tokenize();

    if (tokenResult.status === 'OK') {
      // Send payment to server
      const response = await fetch("square-payment.php", {
        method: "POST",
        headers: { 
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        body: JSON.stringify({ 
          token: tokenResult.token,
          csrf_token: "<?= $_SESSION['csrf_token'] ?>"
        })
      });

      // Check response type
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        const errorText = await response.text();
        console.error("Non-JSON response:", errorText);
        throw new Error("Server returned invalid response format");
      }

      // Check HTTP status
      if (!response.ok) {
        throw new Error(`Server error: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();
      console.log("Payment response:", data);

      if (data.success) {
        statusEl.textContent = "✅ Payment successful! Redirecting...";
        statusEl.className = 'success';
        
        // Redirect after showing success message
        setTimeout(() => {
          window.location.href = "../public/thank-you.html?reservation=" + encodeURIComponent(data.reservation_number || '');
        }, 2000);
      } else {
        throw new Error(data.message || "Payment failed");
      }
    } else {
      // Handle tokenization errors
      throw new Error(tokenResult.errors[0].message || "Card validation failed");
    }

  } catch (error) {
    console.error("Payment error:", error);
    statusEl.textContent = "❌ " + error.message;
    statusEl.className = 'error';
    payBtn.disabled = false;
  }
}

function initializePayPal() {
  if (typeof paypal === 'undefined') {
    console.error('PayPal SDK not loaded');
    return;
  }

  const amountDue = parseFloat(booking.amount_due);
  
  paypal.Buttons({
    createOrder: (data, actions) => {
      return actions.order.create({
        purchase_units: [{
          amount: { 
            value: amountDue.toFixed(2),
            currency_code: 'AUD'
          },
          description: `Barbs Bali Booking - ${booking.guest_first_name} ${booking.guest_last_name}`
        }]
      });
    },
    onApprove: async (data, actions) => {
      try {
        const details = await actions.order.capture();
        const statusEl = document.getElementById("status");
        
        statusEl.textContent = "✅ Payment received via PayPal. Processing...";
        statusEl.className = 'success';
        
        // Process PayPal payment completion
        const response = await fetch("mark-paid.php", {
          method: "POST",
          headers: { 
            "Content-Type": "application/json",
            "Accept": "application/json"
          },
          body: JSON.stringify({ 
            payment_method: 'paypal', 
            booking: booking,
            paypal_details: details,
            csrf_token: "<?= $_SESSION['csrf_token'] ?>"
          })
        });

        if (response.ok) {
          setTimeout(() => {
            window.location.href = "../public/thank-you.html";
          }, 1500);
        } else {
          throw new Error('Failed to process PayPal payment');
        }
        
      } catch (error) {
        console.error('PayPal processing error:', error);
        document.getElementById("status").textContent = "❌ Error processing PayPal payment";
        document.getElementById("status").className = 'error';
      }
    },
    onError: (err) => {
      console.error('PayPal error:', err);
      document.getElementById("status").textContent = "❌ PayPal payment failed";
      document.getElementById("status").className = 'error';
    }
  }).render("#paypal-button-container");
}

function setupPaymentMethodToggle() {
  const radios = document.querySelectorAll('input[name="payment_method"]');
  radios.forEach(radio => {
    radio.addEventListener('change', () => {
      const squareSection = document.getElementById('square-section');
      const paypalSection = document.getElementById('paypal-section');
      
      if (radio.value === 'square') {
        squareSection.style.display = 'block';
        paypalSection.style.display = 'none';
      } else {
        squareSection.style.display = 'none';
        paypalSection.style.display = 'block';
      }
      
      // Clear any previous status messages when switching
      document.getElementById('status').textContent = '';
      document.getElementById('status').className = '';
    });
  });
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', main);
</script>

</body>
</html>