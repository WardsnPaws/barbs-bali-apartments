<?php
echo "<h2>Path Diagnosis</h2>\n";

// Test from different contexts
$contexts = [
    'From debug/' => __DIR__ . '/../vendor/autoload.php',
    'From includes/' => dirname(__DIR__) . '/includes/../vendor/autoload.php', 
    'From api/' => dirname(__DIR__) . '/api/../vendor/autoload.php',
    'Absolute path' => '/home/barbs141/public_html/barbs-bali-apartments/vendor/autoload.php'
];

foreach ($contexts as $context => $path) {
    $realPath = realpath($path);
    $exists = file_exists($path);
    
    echo "<strong>$context:</strong><br>\n";
    echo "Path: $path<br>\n";
    echo "Real path: " . ($realPath ?: 'NOT RESOLVED') . "<br>\n";
    echo "Exists: " . ($exists ? '✅ YES' : '❌ NO') . "<br><br>\n";
}

// Test the specific path that should work
echo "<h3>Expected Working Path Test</h3>\n";
$workingPath = '/home/barbs141/public_html/barbs-bali-apartments/vendor/autoload.php';
if (file_exists($workingPath)) {
    echo "✅ Confirmed: $workingPath exists<br>\n";
    
    require_once $workingPath;
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        echo "✅ PHPMailer available<br>\n";
    }
    if (class_exists('Square\\SquareClient')) {
        echo "✅ Square SDK available<br>\n";
    }
} else {
    echo "❌ ERROR: $workingPath does not exist!<br>\n";
}
?>