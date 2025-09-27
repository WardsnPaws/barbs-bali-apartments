<?php
// debug/check-config-syntax.php - Find and fix config.php syntax error

echo "<h1>Config.php Syntax Error Check</h1>\n";

$configPath = __DIR__ . '/../config/config.php';

if (!file_exists($configPath)) {
    echo "❌ config.php not found at: $configPath<br>\n";
    exit;
}

echo "✅ config.php found<br><br>\n";

// Read the config file
$configContent = file_get_contents($configPath);
$lines = explode("\n", $configContent);

echo "<h2>Content Around Line 28:</h2>\n";
echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; font-family: monospace;'>\n";

// Show lines 25-35 to see the context around line 28
for ($i = 24; $i < 35; $i++) {
    if (isset($lines[$i])) {
        $lineNum = $i + 1;
        $line = htmlspecialchars($lines[$i]);
        
        if ($lineNum == 28) {
            echo "<strong style='color: red;'>Line $lineNum: $line</strong><br>\n";
        } else {
            echo "Line $lineNum: $line<br>\n";
        }
    }
}

echo "</div><br>\n";

// Try to parse the PHP file to get exact error
echo "<h2>PHP Syntax Check:</h2>\n";
$syntaxCheck = shell_exec("php -l " . escapeshellarg($configPath) . " 2>&1");
echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6;'>\n";
echo "<pre>$syntaxCheck</pre>\n";
echo "</div><br>\n";

// Common syntax issues to check for
echo "<h2>Common Syntax Issues to Check:</h2>\n";

$commonIssues = [
    "'=>'" => "Incorrect arrow syntax (should be single arrow: =>)",
    '=> >' => "Space in arrow (should be: =>)",
    '=>=' => "Double equals in arrow (should be: =>)",
    '= >' => "Space before arrow (should be: =>)",
    'env(' => "env() function (not standard PHP)",
    'define(' => "define() function syntax"
];

foreach ($commonIssues as $pattern => $description) {
    if (strpos($configContent, $pattern) !== false) {
        echo "⚠️ Found potential issue: $description<br>\n";
        
        // Show the line(s) where this pattern appears
        foreach ($lines as $lineNum => $line) {
            if (strpos($line, $pattern) !== false) {
                $displayLineNum = $lineNum + 1;
                echo "&nbsp;&nbsp;Line $displayLineNum: " . htmlspecialchars($line) . "<br>\n";
            }
        }
        echo "<br>\n";
    }
}

echo "<h2>Expected Syntax Examples:</h2>\n";
echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb;'>\n";
echo "<strong>✅ Correct define() syntax:</strong><br>\n";
echo "<code>define('DB_HOST', 'localhost');</code><br>\n";
echo "<code>define('DB_NAME', 'database_name');</code><br><br>\n";

echo "<strong>❌ Incorrect syntax that causes errors:</strong><br>\n";
echo "<code>define('DB_HOST' => 'localhost');</code> // Wrong!<br>\n";
echo "<code>define('DB_HOST', env('DB_HOST' => 'localhost'));</code> // Wrong!<br>\n";
echo "</div><br>\n";

echo "<h2>Recommended Fix:</h2>\n";
echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7;'>\n";
echo "<ol>\n";
echo "<li><strong>Edit config.php line 28</strong> and fix the syntax error</li>\n";
echo "<li><strong>Ensure all define() statements follow the correct format:</strong><br>\n";
echo "&nbsp;&nbsp;<code>define('CONSTANT_NAME', 'value');</code></li>\n";
echo "<li><strong>Remove any env() functions</strong> and use direct values</li>\n";
echo "<li><strong>Test the fix</strong> by running this diagnostic again</li>\n";
echo "</ol>\n";
echo "</div>\n";

?>