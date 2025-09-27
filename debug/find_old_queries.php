<?php
// debug/find-old-queries.php - Find files with old column names

echo "<h1>Finding Old Database Queries</h1>\n";

$searchTerms = [
    'guest_firstname',
    'guest_lastname'
];

$searchDirectories = [
    __DIR__ . '/../includes/',
    __DIR__ . '/../api/',
    __DIR__ . '/../admin/',
    __DIR__ . '/../scripts/',
    __DIR__ . '/../debug/'
];

echo "<h2>1. Searching for Old Column Names</h2>\n";

foreach ($searchTerms as $term) {
    echo "<h3>🔍 Searching for: <code>$term</code></h3>\n";
    
    foreach ($searchDirectories as $dir) {
        if (!is_dir($dir)) continue;
        
        $files = glob($dir . '*.php');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (strpos($content, $term) !== false) {
                $relativePath = str_replace(__DIR__ . '/../', '', $file);
                echo "❌ Found in: <strong>$relativePath</strong><br>\n";
                
                // Show the specific lines
                $lines = explode("\n", $content);
                foreach ($lines as $lineNum => $line) {
                    if (strpos($line, $term) !== false) {
                        $lineNumber = $lineNum + 1;
                        $trimmedLine = trim($line);
                        echo "&nbsp;&nbsp;&nbsp;Line $lineNumber: <code>" . htmlspecialchars($trimmedLine) . "</code><br>\n";
                    }
                }
                echo "<br>\n";
            }
        }
    }
}

// 2. Check specifically problematic files
echo "<h2>2. Key Files to Check</h2>\n";

$keyFiles = [
    'includes/core.php' => 'Core database functions',
    'includes/email-utils.php' => 'Email functions', 
    'debug/database-test.php' => 'Database test script',
    'api/square-payment.php' => 'Payment processing',
    'scripts/send-scheduled-emails.php' => 'Email scheduling'
];

foreach ($keyFiles as $file => $description) {
    $fullPath = __DIR__ . '/../' . $file;
    if (file_exists($fullPath)) {
        echo "✅ <strong>$file</strong> - $description<br>\n";
        
        $content = file_get_contents($fullPath);
        $hasOldColumns = false;
        
        foreach ($searchTerms as $term) {
            if (strpos($content, $term) !== false) {
                $hasOldColumns = true;
                echo "&nbsp;&nbsp;❌ Contains: $term<br>\n";
            }
        }
        
        if (!$hasOldColumns) {
            echo "&nbsp;&nbsp;✅ Column names look correct<br>\n";
        }
        
        echo "<br>\n";
    } else {
        echo "❌ <strong>$file</strong> - Missing<br>\n";
    }
}

// 3. Check the immediate database connection
echo "<h2>3. Testing Which Script Has the Error</h2>\n";

// Try to reproduce the exact error
try {
    require_once __DIR__ . '/../includes/core.php';
    $pdo = getPDO();
    
    // This should work (correct column names)
    echo "<h3>✅ Testing with correct column names:</h3>\n";
    $stmt = $pdo->query("SELECT id, reservation_number, guest_first_name, guest_last_name FROM bookings LIMIT 1");
    $result = $stmt->fetch();
    echo "Works! Found booking: {$result['guest_first_name']} {$result['guest_last_name']}<br><br>\n";
    
    // This should fail (old column names)
    echo "<h3>❌ Testing with old column names (this will fail):</h3>\n";
    try {
        $stmt = $pdo->query("SELECT id, reservation_number, guest_firstname, guest_lastname FROM bookings LIMIT 1");
        echo "Unexpected: This worked!<br>\n";
    } catch (Exception $e) {
        echo "Expected error: " . $e->getMessage() . "<br><br>\n";
    }
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>\n";
}

// 4. Check email scheduling
echo "<h2>4. Testing Email Scheduling Issue</h2>\n";

try {
    require_once __DIR__ . '/../includes/core.php';
    $pdo = getPDO();
    
    // Check if any emails are actually scheduled
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM email_schedule");
    $emailCount = $stmt->fetchColumn();
    
    echo "📧 Total emails in schedule: $emailCount<br>\n";
    
    if ($emailCount == 0) {
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<h3>⚠️ No Emails Scheduled</h3>\n";
        echo "<p>This means:</p>\n";
        echo "<ul>\n";
        echo "<li>Either no bookings have been made through the new system</li>\n";
        echo "<li>Or the email scheduling code in square-payment.php isn't working</li>\n";
        echo "<li>Or existing bookings were made before email scheduling was added</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
    }
    
    // Get recent booking to test email scheduling
    $stmt = $pdo->query("SELECT * FROM bookings ORDER BY created_at DESC LIMIT 1");
    $lastBooking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastBooking) {
        echo "<h3>Last Booking Details:</h3>\n";
        echo "ID: {$lastBooking['id']}<br>\n";
        echo "Guest: {$lastBooking['guest_first_name']} {$lastBooking['guest_last_name']}<br>\n";
        echo "Created: {$lastBooking['created_at']}<br>\n";
        echo "Status: {$lastBooking['status']}<br>\n";
        
        // Check if this booking has any scheduled emails
        $emailStmt = $pdo->prepare("SELECT * FROM email_schedule WHERE booking_id = ?");
        $emailStmt->execute([$lastBooking['id']]);
        $emails = $emailStmt->fetchAll();
        
        if (count($emails) > 0) {
            echo "✅ This booking has " . count($emails) . " scheduled emails<br>\n";
        } else {
            echo "❌ This booking has NO scheduled emails<br>\n";
            echo "💡 This suggests the email scheduling in square-payment.php isn't working<br>\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error checking email schedule: " . $e->getMessage() . "<br>\n";
}

echo "<h2>5. Recommendations</h2>\n";
echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>\n";
echo "<h3>🔧 Action Items:</h3>\n";
echo "<ol>\n";
echo "<li><strong>Find and fix any remaining files</strong> with guest_firstname/guest_lastname</li>\n";
echo "<li><strong>Test the square-payment.php file</strong> to see if email scheduling works</li>\n";
echo "<li><strong>Make a test booking</strong> through the system to verify emails get scheduled</li>\n";
echo "<li><strong>Check if existing bookings need emails retroactively scheduled</strong></li>\n";
echo "</ol>\n";
echo "</div>\n";

?>