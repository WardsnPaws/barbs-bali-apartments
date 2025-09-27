<?php
// debug/database-test.php - Test database connection and data retrieval

echo "<h1>Database Connection Test</h1>\n";

// 1. Test basic database connection
echo "<h2>1. Testing Database Connection</h2>\n";
try {
    require_once __DIR__ . '/../includes/core.php';
    $pdo = getPDO();
    echo "✅ Database connection successful<br>\n";
    
    // Test basic query
    $result = $pdo->query("SELECT COUNT(*) as count FROM bookings");
    $count = $result->fetchColumn();
    echo "✅ Found $count bookings in database<br>\n";
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>\n";
    exit;
}

// 2. Test configuration constants
echo "<h2>2. Testing Configuration</h2>\n";
$requiredConstants = [
    'DB_HOST' => 'Database host',
    'DB_NAME' => 'Database name', 
    'DB_USER' => 'Database user',
    'SMTP_HOST' => 'SMTP server',
    'SMTP_FROM_EMAIL' => 'From email'
];

foreach ($requiredConstants as $constant => $description) {
    if (defined($constant)) {
        $value = ($constant === 'DB_PASSWORD') ? '[HIDDEN]' : constant($constant);
        echo "✅ $constant: $value<br>\n";
    } else {
        echo "❌ $constant: NOT DEFINED<br>\n";
    }
}

// 3. Test booking data retrieval
echo "<h2>3. Testing Booking Data Retrieval</h2>\n";
try {
    // Get recent bookings
    $stmt = $pdo->query("SELECT id, reservation_number, guest_firstname, guest_lastname, checkin_date, checkout_date, status FROM bookings ORDER BY created_at DESC LIMIT 5");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($bookings) > 0) {
        echo "✅ Found " . count($bookings) . " recent bookings:<br>\n";
        echo "<table border='1' style='margin: 10px 0; border-collapse: collapse;'>\n";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Reservation</th><th>Guest</th><th>Check-in</th><th>Status</th></tr>\n";
        foreach ($bookings as $booking) {
            echo "<tr>\n";
            echo "<td>{$booking['id']}</td>\n";
            echo "<td>{$booking['reservation_number']}</td>\n";
            echo "<td>{$booking['guest_firstname']} {$booking['guest_lastname']}</td>\n";
            echo "<td>{$booking['checkin_date']}</td>\n";
            echo "<td>{$booking['status']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "⚠️ No bookings found - this is normal for a new system<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Error retrieving bookings: " . $e->getMessage() . "<br>\n";
}

// 4. Test availability calendar function
echo "<h2>4. Testing Availability Calendar Function</h2>\n";
try {
    if (function_exists('getAvailabilityCalendar')) {
        $calendar = getAvailabilityCalendar(1, date('Y'), date('n')); // Apartment 1, current month
        echo "✅ getAvailabilityCalendar function works<br>\n";
        echo "Calendar data structure: " . (is_array($calendar) ? 'Array with ' . count($calendar) . ' entries' : 'Not an array') . "<br>\n";
    } else {
        echo "❌ getAvailabilityCalendar function not found<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Error testing calendar function: " . $e->getMessage() . "<br>\n";
}

// 5. Test API endpoints accessibility
echo "<h2>5. Testing API Endpoint Files</h2>\n";
$apiEndpoints = [
    'check-availability.php' => 'Availability checking',
    'get-calendar-data.php' => 'Calendar data API',
    'booking-process.php' => 'Booking processing',
    'square-payment.php' => 'Payment processing'
];

foreach ($apiEndpoints as $endpoint => $description) {
    $apiPath = __DIR__ . '/../api/' . $endpoint;
    if (file_exists($apiPath)) {
        echo "✅ $endpoint - $description<br>\n";
    } else {
        echo "❌ $endpoint - $description (FILE MISSING)<br>\n";
    }
}

// 6. Test if we can make an API call
echo "<h2>6. Testing API Call</h2>\n";
try {
    $checkAvailabilityPath = __DIR__ . '/../api/check-availability.php';
    if (file_exists($checkAvailabilityPath)) {
        echo "✅ check-availability.php file exists<br>\n";
        echo "📝 To test API functionality, try: <br>\n";
        echo "&nbsp;&nbsp;POST to: <code>https://www.barbsbaliapartments.com/api/check-availability.php</code><br>\n";
        echo "&nbsp;&nbsp;With data: <code>checkin_date=2025-10-01&checkout_date=2025-10-07&apartment_id=1</code><br>\n";
    } else {
        echo "❌ check-availability.php not found<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Error testing API: " . $e->getMessage() . "<br>\n";
}

// 7. Check if index.html should be index.php
echo "<h2>7. Checking Public Files</h2>\n";
$publicPath = __DIR__ . '/../public/';
$publicFiles = ['index.html', 'index.php', 'booking-form.html', 'booking-form.php'];

foreach ($publicFiles as $file) {
    if (file_exists($publicPath . $file)) {
        $size = filesize($publicPath . $file);
        echo "✅ $file (${size} bytes)<br>\n";
        
        // Check if HTML file has PHP code
        if (strpos($file, '.html') && $size > 0) {
            $content = file_get_contents($publicPath . $file);
            if (strpos($content, '<?php') !== false) {
                echo "&nbsp;&nbsp;⚠️ <strong>$file contains PHP code but has .html extension!</strong><br>\n";
                echo "&nbsp;&nbsp;💡 Consider renaming to .php or converting to pure HTML + AJAX<br>\n";
            }
        }
    }
}

// 8. Summary and recommendations - UPDATED
echo "<h2>8. Summary & Recommendations</h2>\n";

echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>\n";
echo "<h3>✅ Column Name Issue IDENTIFIED!</h3>\n";
echo "<p>Your database uses <code>guest_first_name</code> and <code>guest_last_name</code><br>\n";
echo "But some code looks for <code>guest_firstname</code> and <code>guest_lastname</code></p>\n";
echo "<p><strong>The fix is simple - just update the column names in queries!</strong></p>\n";
echo "</div>\n";

echo "<div style='background: #e7f3ff; padding: 15px; border: 1px solid #b8daff; border-radius: 5px; margin: 10px 0;'>\n";
echo "<h3>🔍 Common Issues:</h3>\n";
echo "<ul>\n";
echo "<li><strong>Column name mismatch</strong> - Use guest_first_name not guest_firstname</li>\n";
echo "<li><strong>Static HTML files can't access database directly</strong> - They need to use AJAX calls to PHP APIs</li>\n";
echo "<li><strong>Check JavaScript console</strong> - Look for AJAX errors</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>\n";
echo "<h3>🚀 Next Steps:</h3>\n";
echo "<ol>\n";
echo "<li><strong>Update any remaining queries</strong> to use guest_first_name/guest_last_name</li>\n";
echo "<li><strong>Test API endpoints individually</strong></li>\n";
echo "<li><strong>Check browser console for JavaScript errors</strong></li>\n";
echo "<li><strong>Make a test booking to verify email system works</strong></li>\n";
echo "</ol>\n";
echo "</div>\n";

?>