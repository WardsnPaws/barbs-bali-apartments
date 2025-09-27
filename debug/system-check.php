<?php
// debug/system-check.php - Verify system configuration and file structure

echo "<h1>Barbs Bali Apartments - System Check</h1>\n";
echo "<p>Verifying all components for deployment...</p>\n";

// Check if we can include core files
$errors = [];
$warnings = [];

// 1. Check core includes
echo "<h2>📁 File Structure Check</h2>\n";

$criticalFiles = [
    '../includes/core.php' => 'Database connection and core functions',
    '../includes/email-utils.php' => 'Email sending and templates',
    '../includes/price-calc.php' => 'Booking price calculations',
    '../config/config.php' => 'Main configuration',
    '../config/config_secrets.php' => 'SMTP and API credentials'
];

foreach ($criticalFiles as $file => $description) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        echo "✅ <strong>$file</strong> - $description<br>\n";
    } else {
        echo "❌ <strong>$file</strong> - $description <em>(MISSING)</em><br>\n";
        $errors[] = "Missing critical file: $file";
    }
}

// 2. Check email templates
echo "<h2>📧 Email Template Check</h2>\n";
$templateDir = __DIR__ . '/../templates/email/';
$emailTemplates = [
    'booking-confirmation.html',
    'balance-reminder.html', 
    'checkin-reminder.html',
    'housekeeping-notice.html'
];

if (is_dir($templateDir)) {
    echo "✅ Email template directory exists<br>\n";
    foreach ($emailTemplates as $template) {
        $templatePath = $templateDir . $template;
        if (file_exists($templatePath)) {
            echo "✅ <strong>$template</strong><br>\n";
        } else {
            echo "⚠️ <strong>$template</strong> <em>(Missing - using fallback)</em><br>\n";
            $warnings[] = "Missing email template: $template";
        }
    }
} else {
    echo "❌ Email template directory missing<br>\n";
    $errors[] = "Email template directory not found";
}

// 3. Test database connection
echo "<h2>🗄️ Database Connection Check</h2>\n";
try {
    require_once __DIR__ . '/../includes/core.php';
    $pdo = getPDO();
    echo "✅ Database connection successful<br>\n";
    
    // Check required tables
    $requiredTables = [
        'bookings' => 'Main booking records',
        'email_schedule' => 'Email scheduling system', 
        'email_tracking' => 'Email open tracking',
        'extras' => 'Booking add-ons',
        'booking_extras' => 'Booking-extra relationships',
        'payments' => 'Payment records'
    ];
    
    echo "<h3>Required Tables:</h3>\n";
    foreach ($requiredTables as $table => $description) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "✅ <strong>$table</strong> - $description<br>\n";
        } else {
            echo "❌ <strong>$table</strong> - $description <em>(MISSING)</em><br>\n";
            $errors[] = "Missing database table: $table";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>\n";
    $errors[] = "Database connection error: " . $e->getMessage();
}

// 4. Check email configuration
echo "<h2>📨 Email Configuration Check</h2>\n";
if (defined('SMTP_HOST')) {
    echo "✅ SMTP_HOST: " . SMTP_HOST . "<br>\n";
} else {
    echo "❌ SMTP_HOST not defined<br>\n";
    $errors[] = "SMTP configuration missing";
}

if (defined('SMTP_FROM_EMAIL')) {
    echo "✅ SMTP_FROM_EMAIL: " . SMTP_FROM_EMAIL . "<br>\n";
} else {
    echo "❌ SMTP_FROM_EMAIL not defined<br>\n";
    $errors[] = "SMTP FROM email not configured";
}

// 5. Check PHPMailer
echo "<h2>📮 PHPMailer Check</h2>\n";
$vendorPaths = [
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__) . '/vendor/autoload.php'
];

$phpmailerFound = false;
foreach ($vendorPaths as $vendorPath) {
    if (file_exists($vendorPath)) {
        try {
            require_once $vendorPath;
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                echo "✅ PHPMailer found at: $vendorPath<br>\n";
                $phpmailerFound = true;
                break;
            }
        } catch (Exception $e) {
            // Continue to next path
        }
    }
}

if (!$phpmailerFound) {
    echo "❌ PHPMailer not found<br>\n";
    $errors[] = "PHPMailer library missing - run 'composer install'";
}

// 6. Check API endpoints
echo "<h2>🔗 API Endpoint Check</h2>\n";
$apiFiles = [
    'booking-process.php',
    'square-payment.php', 
    'check-availability.php',
    'get-calendar-data.php'
];

foreach ($apiFiles as $apiFile) {
    $apiPath = __DIR__ . '/../api/' . $apiFile;
    if (file_exists($apiPath)) {
        echo "✅ <strong>api/$apiFile</strong><br>\n";
    } else {
        echo "❌ <strong>api/$apiFile</strong> <em>(MISSING)</em><br>\n";
        $errors[] = "Missing API file: $apiFile";
    }
}

// 7. Summary
echo "<h2>📊 Summary</h2>\n";

if (empty($errors)) {
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>\n";
    echo "<h3 style='color: #155724; margin-top: 0;'>🎉 System Ready for Testing!</h3>\n";
    echo "<p style='color: #155724;'>All critical components are in place.</p>\n";
    echo "</div>\n";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>\n";
    echo "<h3 style='color: #721c24; margin-top: 0;'>❌ Critical Issues Found</h3>\n";
    foreach ($errors as $error) {
        echo "<p style='color: #721c24; margin: 5px 0;'>• $error</p>\n";
    }
    echo "</div>\n";
}

if (!empty($warnings)) {
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>\n";
    echo "<h3 style='color: #856404; margin-top: 0;'>⚠️ Warnings</h3>\n";
    foreach ($warnings as $warning) {
        echo "<p style='color: #856404; margin: 5px 0;'>• $warning</p>\n";
    }
    echo "</div>\n";
}

echo "<h2>🧪 Next Steps</h2>\n";
echo "<ol>\n";
echo "<li><a href='/booking-debug/'>Run Email Scheduling Debug</a></li>\n";
echo "<li><a href='/booking/'>Test Main Booking Page</a></li>\n";
echo "<li><a href='/admin/'>Test Admin Interface</a></li>\n";
echo "<li>Make a test booking to verify email system</li>\n";
echo "</ol>\n";

?>