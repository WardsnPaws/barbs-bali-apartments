<?php
// debug/test-api.php - Test API endpoints independently

echo "<h1>API Endpoints Test</h1>\n";

// Test data
$testData = [
    'checkin_date' => '2025-10-01',
    'checkout_date' => '2025-10-07', 
    'apartment_id' => '1'
];

echo "<p>Testing with data: " . json_encode($testData) . "</p>\n";

// 1. Test check-availability.php
echo "<h2>1. Testing check-availability.php</h2>\n";
try {
    // Simulate POST request
    $_POST = $testData;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Capture output
    ob_start();
    $apiPath = __DIR__ . '/../api/check-availability.php';
    
    if (file_exists($apiPath)) {
        include $apiPath;
        $output = ob_get_contents();
        ob_end_clean();
        
        echo "<div style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6;'>\n";
        echo "<strong>Response:</strong><br>\n";
        echo "<pre>" . htmlspecialchars($output) . "</pre>\n";
        echo "</div>\n";
        
        // Try to decode JSON
        $decoded = json_decode($output, true);
        if ($decoded !== null) {
            echo "✅ Valid JSON response<br>\n";
            if (isset($decoded['available'])) {
                echo "✅ Contains 'available' field: " . ($decoded['available'] ? 'TRUE' : 'FALSE') . "<br>\n";
            }
        } else {
            echo "⚠️ Response is not valid JSON<br>\n";
        }
    } else {
        echo "❌ check-availability.php file not found<br>\n";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ Error testing check-availability: " . $e->getMessage() . "<br>\n";
}

// Reset $_POST
$_POST = [];

// 2. Test get-calendar-data.php
echo "<h2>2. Testing get-calendar-data.php</h2>\n";
try {
    $_GET = [
        'apartment_id' => '1',
        'year' => date('Y'),
        'month' => date('n')
    ];
    
    ob_start();
    $calendarApiPath = __DIR__ . '/../api/get-calendar-data.php';
    
    if (file_exists($calendarApiPath)) {
        include $calendarApiPath;
        $calendarOutput = ob_get_contents();
        ob_end_clean();
        
        echo "<div style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6;'>\n";
        echo "<strong>Response:</strong><br>\n";
        echo "<pre>" . htmlspecialchars(substr($calendarOutput, 0, 500)) . (strlen($calendarOutput) > 500 ? '...' : '') . "</pre>\n";
        echo "</div>\n";
        
        $calendarDecoded = json_decode($calendarOutput, true);
        if ($calendarDecoded !== null) {
            echo "✅ Valid JSON response<br>\n";
            if (is_array($calendarDecoded)) {
                echo "✅ Calendar data is array with " . count($calendarDecoded) . " entries<br>\n";
            }
        } else {
            echo "⚠️ Response is not valid JSON<br>\n";
        }
    } else {
        echo "❌ get-calendar-data.php file not found<br>\n";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ Error testing calendar API: " . $e->getMessage() . "<br>\n";
}

// Reset $_GET
$_GET = [];

// 3. Test database functions directly
echo "<h2>3. Testing Core Database Functions</h2>\n";
try {
    require_once __DIR__ . '/../includes/core.php';
    
    // Test checkBookingConflict
    if (function_exists('checkBookingConflict')) {
        $conflict = checkBookingConflict(1, '2025-10-01', '2025-10-07');
        echo "✅ checkBookingConflict function works - Result: " . ($conflict ? 'CONFLICT FOUND' : 'NO CONFLICT') . "<br>\n";
    } else {
        echo "❌ checkBookingConflict function not found<br>\n";
    }
    
    // Test getAvailabilityCalendar  
    if (function_exists('getAvailabilityCalendar')) {
        $calendar = getAvailabilityCalendar(1, 2025, 10);
        echo "✅ getAvailabilityCalendar function works - Returned " . (is_array($calendar) ? count($calendar) . " days" : "non-array") . "<br>\n";
    } else {
        echo "❌ getAvailabilityCalendar function not found<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error testing core functions: " . $e->getMessage() . "<br>\n";
}

// 4. Check JavaScript/AJAX setup
echo "<h2>4. Frontend Integration Check</h2>\n";
$indexPath = __DIR__ . '/../public/index.html';
if (file_exists($indexPath)) {
    $indexContent = file_get_contents($indexPath);
    
    // Check for AJAX calls
    if (strpos($indexContent, 'ajax') !== false || strpos($indexContent, 'fetch') !== false || strpos($indexContent, 'XMLHttpRequest') !== false) {
        echo "✅ Found AJAX/fetch calls in index.html<br>\n";
    } else {
        echo "⚠️ No AJAX/fetch calls found in index.html<br>\n";
    }
    
    // Check for API URLs
    if (strpos($indexContent, '/api/') !== false) {
        echo "✅ Found API endpoint references<br>\n";
    } else {
        echo "⚠️ No API endpoint references found<br>\n";
    }
    
    // Check if it's actually PHP
    if (strpos($indexContent, '<?php') !== false) {
        echo "⚠️ <strong>index.html contains PHP code - should be renamed to index.php!</strong><br>\n";
    }
} else {
    echo "❌ index.html not found<br>\n";
}

echo "<h2>5. Recommendations</h2>\n";
echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>\n";
echo "<h3>🔧 Most Likely Issues:</h3>\n";
echo "<ul>\n";
echo "<li><strong>Static HTML can't access PHP/Database</strong> - Use AJAX to call API endpoints</li>\n";
echo "<li><strong>Wrong file extension</strong> - If index.html has PHP, rename to index.php</li>\n";
echo "<li><strong>API endpoints not working</strong> - Check error logs</li>\n";
echo "<li><strong>JavaScript errors</strong> - Check browser console</li>\n";
echo "</ul>\n";
echo "</div>\n";

?>

<script>
// Simple JavaScript test to check if APIs are reachable
console.log('Testing API endpoints from JavaScript...');

// Test availability API
fetch('/api/check-availability.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'checkin_date=2025-10-01&checkout_date=2025-10-07&apartment_id=1'
})
.then(response => response.text())
.then(data => {
    console.log('✅ Availability API response:', data);
})
.catch(error => {
    console.error('❌ Availability API error:', error);
});

// Test calendar API
fetch('/api/get-calendar-data.php?apartment_id=1&year=2025&month=10')
.then(response => response.text()) 
.then(data => {
    console.log('✅ Calendar API response:', data.substring(0, 200) + '...');
})
.catch(error => {
    console.error('❌ Calendar API error:', error);
});
</script>