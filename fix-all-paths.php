<?php
/**
 * Comprehensive Path Fixes for Barbs Bali Apartments
 * Run this script to fix all file path issues after reorganization
 */

$projectRoot = 'C:/wamp64/www/barbs-bali-apartments/';

// Define all the path corrections needed
$fixes = [
    // CONFIG FILES
    'config/config.php' => [
        '__DIR__ . \'/../config_secrets.php\'' => '__DIR__ . \'/config_secrets.php\'',
    ],
    
    // INCLUDES FILES  
    'includes/core.php' => [
        'require_once \'config.php\';' => 'require_once __DIR__ . \'/../config/config.php\';',
    ],
    
    'includes/email-utils.php' => [
        'require_once \'config.php\';' => 'require_once __DIR__ . \'/../config/config.php\';',
        '__DIR__ . "/../templates/email/$templateFile.html"' => '__DIR__ . "/../templates/email/$templateFile.html"', // Already correct
    ],
    
    'includes/price-calc.php' => [
        'require_once __DIR__ . \'/../config.php\';' => 'require_once __DIR__ . \'/../config/config.php\';',
    ],
    
    // API FILES
    'api/booking-process.php' => [
        'require_once __DIR__ . \'/../includes/core.php\';' => 'require_once __DIR__ . \'/../includes/core.php\';', // Already correct
        'require_once __DIR__ . \'/../includes/price-calc.php\';' => 'require_once __DIR__ . \'/../includes/price-calc.php\';', // Already correct
        'header("Location: ../public/secure-payment.php");' => 'header("Location: secure-payment.php");', // Stay within API folder
    ],
    
    'api/check-availability.php' => [
        'require_once \'../includes/core.php\';' => 'require_once __DIR__ . \'/../includes/core.php\';',
    ],
    
    'api/get-session-booking.php' => [
        'require_once \'core/price-calc.php\';' => 'require_once __DIR__ . \'/../includes/price-calc.php\';',
    ],
    
    'api/mark-paid.php' => [
        'require_once \'../includes/core.php\';' => 'require_once __DIR__ . \'/../includes/core.php\';',
        'require_once \'core/price-calc.php\';' => 'require_once __DIR__ . \'/../includes/price-calc.php\';',
        'require_once \'../includes/email-utils.php\';' => 'require_once __DIR__ . \'/../includes/email-utils.php\';',
    ],
    
    'api/pay-balance-process.php' => [
        'require_once \'../includes/core.php\';' => 'require_once __DIR__ . \'/../includes/core.php\';',
        'require \'vendor/autoload.php\';' => 'require __DIR__ . \'/../vendor/autoload.php\';',
    ],
    
    'api/resend-confirmation.php' => [
        'require_once \'../includes/core.php\';' => 'require_once __DIR__ . \'/../includes/core.php\';',
        'require_once \'../includes/email-utils.php\';' => 'require_once __DIR__ . \'/../includes/email-utils.php\';',
        'header("Location: my-booking.php?res="' => 'header("Location: ../public/my-booking.php?res="',
    ],
    
    'api/secure-payment.php' => [
        'require_once \'../config/config.php\';' => 'require_once __DIR__ . \'/../config/config.php\';',
        'fetch("get-session-booking.php")' => 'fetch("../api/get-session-booking.php")',
        'fetch("square-payment.php"' => 'fetch("../api/square-payment.php"',
        'fetch("mark-paid.php"' => 'fetch("../api/mark-paid.php"',
        'window.location.href = "thank-you.html"' => 'window.location.href = "../public/thank-you.html"',
    ],
    
    'api/square-payment.php' => [
        'require_once \'../includes/core.php\';' => 'require_once __DIR__ . \'/../includes/core.php\';',
        'require_once \'core/price-calc.php\';' => 'require_once __DIR__ . \'/../includes/price-calc.php\';',
        'require \'vendor/autoload.php\';' => 'require __DIR__ . \'/../vendor/autoload.php\';',
    ],
    
    'api/update-extras.php' => [
        'require_once \'../includes/core.php\';' => 'require_once __DIR__ . \'/../includes/core.php\';',
        'require_once \'core/price-calc.php\';' => 'require_once __DIR__ . \'/../includes/price-calc.php\';',
        'header("Location: extras-payment.php?res="' => 'header("Location: ../public/extras-payment.php?res="',
        'header("Location: my-booking.php?res="' => 'header("Location: ../public/my-booking.php?res="',
    ],
    
    // PUBLIC FILES
    'public/booking-form.html' => [
        'fetch(\'get-calendar-data.php\')' => 'fetch(\'../api/get-calendar-data.php\')',
        'fetch(\'check-availability.php\', {' => 'fetch(\'../api/check-availability.php\', {',
        'action="booking-process.php"' => 'action="../api/booking-process.php"',
    ],
    
    'public/my-booking.php' => [
        'require_once \'../includes/core.php\';' => 'require_once __DIR__ . \'/../includes/core.php\';',
        'require_once \'core/price-calc.php\';' => 'require_once __DIR__ . \'/../includes/price-calc.php\';',
        'action="update-extras.php"' => 'action="../api/update-extras.php"',
        'action="resend-confirmation.php"' => 'action="../api/resend-confirmation.php"',
        'fetch("pay-balance-process.php"' => 'fetch("../api/pay-balance-process.php"',
        'href="index.php"' => 'href="index.html"',
    ],
    
    'public/pay-balance.php' => [
        'require_once \'../includes/core.php\';' => 'require_once __DIR__ . \'/../includes/core.php\';',
        'require_once \'email-utils.php\';' => 'require_once __DIR__ . \'/../includes/email-utils.php\';',
        'require \'vendor/autoload.php\';' => 'require __DIR__ . \'/../vendor/autoload.php\';',
    ],
    
    // ADMIN FILES
    'admin/login.php' => [
        'require_once \'../core.php\';' => 'require_once __DIR__ . \'/../includes/core.php\';',
    ],
    
    'admin/index.php' => [
        'href="/index.html"' => 'href="../public/index.html"',
    ],
    
    'admin/logout.php' => [
        'header("Location: /index.html");' => 'header("Location: ../public/index.html");',
    ],
    
    'admin/update-booking.php' => [
        'require_once __DIR__ . \'/../core.php\';' => 'require_once __DIR__ . \'/../includes/core.php\';',
        'require_once __DIR__ . \'/../core/price-calc.php\';' => 'require_once __DIR__ . \'/../includes/price-calc.php\';',
    ],
    
    'admin/views/booking-edit.php' => [
        'require_once __DIR__ . \'/../../core.php\';' => 'require_once __DIR__ . \'/../../includes/core.php\';',
    ],
    
    'admin/views/bookings.php' => [
        'require_once __DIR__ . \'/../../core.php\';' => 'require_once __DIR__ . \'/../../includes/core.php\';',
    ],
    
    'admin/views/calendar.php' => [
        'require_once __DIR__ . \'/../../core.php\';' => 'require_once __DIR__ . \'/../../includes/core.php\';',
    ],
    
    'admin/views/dashboard.php' => [
        'require_once __DIR__ . \'/../../core.php\';' => 'require_once __DIR__ . \'/../../includes/core.php\';',
    ],
    
    'admin/views/delete-booking.php' => [
        'require_once \'../core.php\';' => 'require_once __DIR__ . \'/../../includes/core.php\';',
        'require_once \'auth.php\';' => 'require_once __DIR__ . \'/../auth.php\';',
    ],
    
    'admin/views/email-log.php' => [
        'require_once __DIR__ . \'/../../core.php\';' => 'require_once __DIR__ . \'/../../includes/core.php\';',
    ],
    
    'admin/views/payments.php' => [
        'require_once __DIR__ . \'/../../core.php\';' => 'require_once __DIR__ . \'/../../includes/core.php\';',
    ],
    
    'admin/views/settings.php' => [
        '$configPath = realpath(__DIR__ . \'/../../config.php\')' => '$configPath = realpath(__DIR__ . \'/../../config/config.php\')',
        '$corePath   = realpath(__DIR__ . \'/../../core.php\')' => '$corePath   = realpath(__DIR__ . \'/../../includes/core.php\')',
        '(__DIR__ . \'/../../config.php\')' => '(__DIR__ . \'/../../config/config.php\')',
        '(__DIR__ . \'/../../core.php\')' => '(__DIR__ . \'/../../includes/core.php\')',
    ],
    
    // ADMIN TOOLS
    'admin/tools/create-fake-booking.php' => [
        'require_once __DIR__ . \'/../../core.php\';' => 'require_once __DIR__ . \'/../../includes/core.php\';',
    ],
    
    'admin/tools/fake-payment.php' => [
        'require_once __DIR__ . \'/../../core.php\';' => 'require_once __DIR__ . \'/../../includes/core.php\';',
    ],
    
    'admin/tools/fetch-booking-summary.php' => [
        'require_once __DIR__ . \'/../../core.php\';' => 'require_once __DIR__ . \'/../../includes/core.php\';',
    ],
    
    'admin/tools/index.php' => [
        'require_once __DIR__ . \'/../../core.php\';' => 'require_once __DIR__ . \'/../../includes/core.php\';',
    ],
    
    'admin/tools/reset-test-data.php' => [
        'require_once __DIR__ . \'/../../core.php\';' => 'require_once __DIR__ . \'/../../includes/core.php\';',
    ],
    
    'admin/tools/send-all-scheduled.php' => [
        'require_once __DIR__ . \'/../../send-scheduled-emails.php\';' => 'require_once __DIR__ . \'/../../scripts/send-scheduled-emails.php\';',
    ],
    
    'admin/tools/send-scheduled-emails.php' => [
        'require_once __DIR__ . \'/../../send-scheduled-emails.php\';' => 'require_once __DIR__ . \'/../../scripts/send-scheduled-emails.php\';',
    ],
    
    // SCRIPTS
    'scripts/send-scheduled-emails.php' => [
        'require_once \'../includes/core.php\';' => 'require_once __DIR__ . \'/../includes/core.php\';',
        'require_once \'email-utils.php\';' => 'require_once __DIR__ . \'/../includes/email-utils.php\';',
        '__DIR__ . "/email-templates/{$emailType}.html"' => '__DIR__ . "/../templates/email/{$emailType}.html"',
    ],
];

// Function to apply fixes to a file
function applyFixes($filePath, $fixes) {
    if (!file_exists($filePath)) {
        echo "! File not found: $filePath\n";
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    foreach ($fixes as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    
    if ($content !== $originalContent) {
        if (file_put_contents($filePath, $content)) {
            echo "✓ Fixed paths in: $filePath\n";
            return true;
        } else {
            echo "✗ Failed to write: $filePath\n";
            return false;
        }
    } else {
        echo "- No changes needed: $filePath\n";
        return true;
    }
}

// Main execution
echo "=== Comprehensive Path Fixes for Barbs Bali Apartments ===\n\n";

$totalFiles = 0;
$fixedFiles = 0;

foreach ($fixes as $relativeFilePath => $fileFixes) {
    $fullPath = $projectRoot . $relativeFilePath;
    $totalFiles++;
    
    echo "Processing: $relativeFilePath\n";
    
    if (applyFixes($fullPath, $fileFixes)) {
        $fixedFiles++;
    }
    
    echo "\n";
}

// Special fixes for image paths in index.html
echo "Fixing image paths in index.html...\n";
$indexPath = $projectRoot . 'public/index.html';
if (file_exists($indexPath)) {
    $indexContent = file_get_contents($indexPath);
    
    // Fix image source paths - assuming images are in assets/images/
    $imageReplacements = [
        'src="images/' => 'src="../assets/images/',
        'url(\'images/' => 'url(\'../assets/images/',
        'url("images/' => 'url("../assets/images/',
    ];
    
    $originalIndexContent = $indexContent;
    foreach ($imageReplacements as $search => $replace) {
        $indexContent = str_replace($search, $replace, $indexContent);
    }
    
    if ($indexContent !== $originalIndexContent) {
        if (file_put_contents($indexPath, $indexContent)) {
            echo "✓ Fixed image paths in index.html\n";
        } else {
            echo "✗ Failed to fix image paths in index.html\n";
        }
    } else {
        echo "- No image path changes needed in index.html\n";
    }
} else {
    echo "! index.html not found\n";
}

echo "\n=== SUMMARY ===\n";
echo "Total files processed: $totalFiles\n";
echo "Files successfully updated: $fixedFiles\n";
echo "\n=== NEXT STEPS ===\n";
echo "1. Test your booking form at: http://localhost/barbs-bali-apartments/public/\n";
echo "2. Test admin panel at: http://localhost/barbs-bali-apartments/admin/\n";
echo "3. Check for any remaining errors in browser console\n";
echo "4. Commit changes to Git: git add . && git commit -m 'Fix all file paths after reorganization'\n";

// Additional recommendations
echo "\n=== RECOMMENDATIONS ===\n";
echo "1. Update your WAMP virtual host to point to the 'public' folder\n";
echo "2. Move config_secrets.php outside the web root for security\n";
echo "3. Test the booking flow end-to-end\n";
echo "4. Verify email functionality\n";
?>