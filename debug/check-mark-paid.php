<?php
// debug/check-mark-paid.php - Debug the mark-paid.php error

echo "<h1>Mark-Paid.php Error Diagnosis</h1>\n";

// 1. Check if file exists
$markPaidPath = __DIR__ . '/../api/mark-paid.php';
echo "<h2>1. File Check</h2>\n";

if (file_exists($markPaidPath)) {
    echo "✅ mark-paid.php exists<br>\n";
    
    $fileSize = filesize($markPaidPath);
    echo "File size: $fileSize bytes<br>\n";
    
    // Check if file is readable
    if (is_readable($markPaidPath)) {
        echo "✅ File is readable<br>\n";
    } else {
        echo "❌ File is not readable<br>\n";
    }
    
} else {
    echo "❌ mark-paid.php does not exist!<br>\n";
    echo "Expected location: $markPaidPath<br>\n";
    
    // Check if it exists elsewhere
    $possiblePaths = [
        __DIR__ . '/../api/mark-paid.php',
        __DIR__ . '/../../api/mark-paid.php',
        dirname(__DIR__) . '/api/mark-paid.php'
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            echo "Found at: $path<br>\n";
        }
    }
}

// 2. Test database connection in the context of mark-paid
echo "<h2>2. Database Connection Test</h2>\n";
try {
    require_once __DIR__ . '/../includes/core.php';
    $pdo = getPDO();
    echo "✅ Database connection works<br>\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>\n";
}

// 3. Test what mark-paid.php expects
echo "<h2>3. Testing mark-paid.php Expectations</h2>\n";

if (file_exists($markPaidPath)) {
    echo "<h3>Examining mark-paid.php code...</h3>\n";
    
    $content = file_get_contents($markPaidPath);
    
    // Check for required functions
    if (strpos($content, 'getPDO') !== false) {
        echo "✅ Uses getPDO function<br>\n";
    }
    
    if (strpos($content, '$_POST') !== false) {
        echo "✅ Expects POST data<br>\n";
        
        // Try to identify what POST variables it expects
        preg_match_all('/\$_POST\[\'([^\']+)\'\]/', $content, $matches);
        if (!empty($matches[1])) {
            echo "Expected POST variables: <br>\n";
            foreach (array_unique($matches[1]) as $var) {
                echo "&nbsp;&nbsp;- $var<br>\n";
            }
        }
    }
    
    if (strpos($content, 'sendConfirmationEmail') !== false) {
        echo "✅ Tries to send confirmation email<br>\n";
    }
    
    // Check column names
    if (strpos($content, 'guest_first_name') !== false) {
        echo "✅ Uses correct column names<br>\n";
    } elseif (strpos($content, 'guest_firstname') !== false) {
        echo "❌ Uses old column names<br>\n";
    }
}

// 4. Simulate a mark-paid request
echo "<h2>4. Simulating mark-paid Request</h2>\n";

if (file_exists($markPaidPath)) {
    // Set up test POST data (typical PayPal response)
    $_POST = [
        'reservation_number' => 'TEST123',
        'amount_paid' => '100.00',
        'transaction_id' => 'PAYPAL_TEST_123',
        'payment_method' => 'paypal'
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    echo "Test POST data set:<br>\n";
    foreach ($_POST as $key => $value) {
        echo "&nbsp;&nbsp;$key: $value<br>\n";
    }
    
    echo "<br>Attempting to execute mark-paid.php...<br>\n";
    
    // Capture any output and errors
    ob_start();
    $originalErrorReporting = error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    try {
        include $markPaidPath;
        $output = ob_get_contents();
        ob_end_clean();
        
        echo "<div style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6;'>\n";
        echo "<strong>mark-paid.php Output:</strong><br>\n";
        echo "<pre>" . htmlspecialchars($output) . "</pre>\n";
        echo "</div>\n";
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "❌ Exception in mark-paid.php: " . $e->getMessage() . "<br>\n";
        echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre><br>\n";
    } catch (Error $e) {
        ob_end_clean();
        echo "❌ Fatal error in mark-paid.php: " . $e->getMessage() . "<br>\n";
        echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre><br>\n";
    }
    
    error_reporting($originalErrorReporting);
    
    // Reset POST data
    $_POST = [];
}

// 5. Check error logs
echo "<h2>5. Error Log Check</h2>\n";
$errorLogPaths = [
    ini_get('error_log'),
    '/tmp/php-errors.log',
    __DIR__ . '/../logs/error.log',
    __DIR__ . '/../../error_log'
];

foreach ($errorLogPaths as $logPath) {
    if ($logPath && file_exists($logPath)) {
        echo "Found error log: $logPath<br>\n";
        
        $logContent = file_get_contents($logPath);
        $recentErrors = array_slice(explode("\n", $logContent), -10); // Last 10 lines
        
        echo "<div style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; max-height: 200px; overflow-y: scroll;'>\n";
        echo "<strong>Recent errors:</strong><br>\n";
        foreach ($recentErrors as $error) {
            if (trim($error)) {
                echo htmlspecialchars($error) . "<br>\n";
            }
        }
        echo "</div>\n";
        break;
    }
}

echo "<h2>6. Recommendations</h2>\n";
echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>\n";
echo "<h3>🔧 Next Steps:</h3>\n";
echo "<ol>\n";
echo "<li><strong>Check cPanel error logs</strong> for the exact 500 error message</li>\n";
echo "<li><strong>Fix any issues found in mark-paid.php</strong></li>\n";
echo "<li><strong>Create missing thank-you.html file</strong></li>\n";
echo "<li><strong>Test PayPal integration</strong> once files are fixed</li>\n";
echo "</ol>\n";
echo "</div>\n";

?>