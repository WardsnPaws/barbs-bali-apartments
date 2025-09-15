<?php
/**
 * Fix index.html path references for the new file structure
 */

$indexPath = 'C:/wamp64/www/barbs-bali-apartments/public/index.html';

if (!file_exists($indexPath)) {
    die("Error: index.html not found at $indexPath\n");
}

echo "Fixing index.html path references...\n\n";

$content = file_get_contents($indexPath);
$originalContent = $content;

// JavaScript path fixes for booking form and API calls
$pathFixes = [
    // Booking form references - now it's in the same public folder
    "iframe.src = 'booking/booking-form-enhanced.html'" => "iframe.src = 'booking-form.html'",
    "let url = 'booking/booking-form-enhanced.html'" => "let url = 'booking-form.html'",
    
    // Customer portal form action
    'action="booking/my-booking.php"' => 'action="my-booking.php"',
    
    // Admin login link
    'href="booking/admin/login.php"' => 'href="../admin/login.php"',
    
    // Customer portal footer button
    'href="booking/index.php"' => 'href="my-booking.php"',
    
    // Any other booking/ references that should point to current or parent directories
    'href="booking/' => 'href="../api/',  // For any API calls
    'src="booking/' => 'src="../api/',    // For any scripts
];

// Apply fixes
foreach ($pathFixes as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

// Additional fix for any remaining booking/ references in JavaScript
$jsPatternFixes = [
    // Fix any fetch calls to booking endpoints
    "fetch('booking/" => "fetch('../api/",
    'fetch("booking/' => 'fetch("../api/',
    
    // Fix any window.location references
    "window.location.href = 'booking/" => "window.location.href = '../api/",
    'window.location.href = "booking/' => 'window.location.href = "../api/',
];

foreach ($jsPatternFixes as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

// Check if changes were made
if ($content !== $originalContent) {
    // Create backup
    $backupPath = $indexPath . '.backup.' . date('Y-m-d_H-i-s');
    copy($indexPath, $backupPath);
    echo "✓ Created backup: $backupPath\n";
    
    // Save updated content
    if (file_put_contents($indexPath, $content)) {
        echo "✓ Successfully updated index.html with correct path references\n";
        
        // Show what was changed
        echo "\nChanges made:\n";
        foreach ($pathFixes as $search => $replace) {
            if (strpos($originalContent, $search) !== false) {
                echo "  • $search → $replace\n";
            }
        }
        
        foreach ($jsPatternFixes as $search => $replace) {
            if (strpos($originalContent, $search) !== false) {
                echo "  • $search → $replace\n";
            }
        }
        
    } else {
        echo "✗ Failed to write updated index.html\n";
        exit(1);
    }
} else {
    echo "- No path changes needed in index.html\n";
}

echo "\n=== VERIFICATION ===\n";
echo "Please check these paths are now working:\n";
echo "1. Main site: http://localhost/barbs-bali-apartments/public/\n";
echo "2. Book Now button should open: booking-form.html\n";
echo "3. Customer Portal should link to: my-booking.php\n";
echo "4. Admin login should link to: ../admin/login.php\n";

echo "\n=== ADDITIONAL CHECKS NEEDED ===\n";
echo "You should also verify:\n";
echo "• booking-form.html exists in public/ folder\n";
echo "• my-booking.php exists in public/ folder\n";
echo "• All image references work (../assets/images/)\n";

// Quick file existence check
$filesToCheck = [
    'C:/wamp64/www/barbs-bali-apartments/public/booking-form.html',
    'C:/wamp64/www/barbs-bali-apartments/public/my-booking.php',
];

echo "\n=== FILE EXISTENCE CHECK ===\n";
foreach ($filesToCheck as $file) {
    $basename = basename($file);
    if (file_exists($file)) {
        echo "✓ $basename exists\n";
    } else {
        echo "✗ $basename MISSING - you may need to copy/rename it\n";
    }
}

echo "\nPath fixing complete!\n";
?>