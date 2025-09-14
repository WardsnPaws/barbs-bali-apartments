// admin/tools/index.php
<?php
require_once __DIR__ . '/../../core.php';

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Test Tools Dashboard</title>
  <style>
    body { font-family: Arial; padding: 30px; }
    h2 { color: #2e8b57; }
    a.tool-link { display: block; margin: 10px 0; font-size: 1.1em; }
    input { margin-left: 10px; }
  </style>
</head>
<body>

  <h2>🧪 Test Tools Dashboard</h2>

  <a class="tool-link" href="send-all-scheduled.php" target="_blank">▶️ Send All Scheduled Emails Now</a>
  <a class="tool-link" href="create-fake-booking.php" target="_blank">➕ Create Fake Booking</a>
  <a class="tool-link" href="reset-test-data.php" onclick="return confirm('Are you sure you want to delete all test data?');" target="_blank">🧹 Reset All Test Data</a>

  <form method="GET" action="fake-payment.php" target="_blank">
    <label>Add Fake Payment:
      Booking ID <input type="number" name="booking" value="1" />
      Amount <input type="number" name="amount" value="100.00" step="0.01" />
      <button type="submit">Submit</button>
    </label>
  </form>

  <form method="GET" action="fetch-booking-summary.php" target="_blank">
    <label>Fetch Booking Summary:
      Res# <input type="text" name="res" value="BB" />
      <button type="submit">View</button>
    </label>
  </form>

</body>
</html>
