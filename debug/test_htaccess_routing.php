<?php
// debug/test-routing.php - Test .htaccess routing

echo "<h1>.htaccess Routing Test</h1>\n";

// 1. Check if thank-you.html exists in the right location
echo "<h2>1. File Location Check</h2>\n";

$thankYouPaths = [
    __DIR__ . '/../public/thank-you.html' => '/barbs-bali-apartments/public/thank-you.html',
    __DIR__ . '/../../public/thank-you.html' => '/public_html/public/thank-you.html',
    __DIR__ . '/../../../public/thank-you.html' => '/public_html/public/thank-you.html (alt)'
];

foreach ($thankYouPaths as $path => $description) {
    if (file_exists($path)) {
        $size = filesize($path);
        echo "✅ Found at: <strong>$description</strong> ($size bytes)<br>\n";
    } else {
        echo "❌ Not found: $description<br>\n";
    }
}

// 2. Test the routing with actual HTTP requests
echo "<h2>2. URL Routing Test</h2>\n";

$testUrls = [
    '/booking/' => 'Main booking page',
    '/booking/thank-you.html' => 'Thank you page (routed)',
    '/barbs-bali-apartments/public/thank-you.html' => 'Direct file access',
    '/public/thank-you.html' => 'Wrong direct access'
];

foreach ($testUrls as $url => $description) {
    echo "<h3>Testing: $description</h3>\n";
    echo "URL: <code>$url</code><br>\n";
    
    // Use cURL to test the URL
    $fullUrl = 'https://www.barbsbaliapartments.com' . $url;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        echo "✅ Status: $httpCode - <strong>Working</strong><br>\n";
    } elseif ($httpCode == 404) {
        echo "❌ Status: $httpCode - <strong>Not Found</strong><br>\n";
    } elseif ($httpCode == 403) {
        echo "⚠️ Status: $httpCode - <strong>Forbidden</strong><br>\n";
    } else {
        echo "⚠️ Status: $httpCode - <strong>Other Issue</strong><br>\n";
    }
    
    echo "<a href='$fullUrl' target='_blank'>Test manually</a><br><br>\n";
}

// 3. Check .htaccess rules
echo "<h2>3. .htaccess Rules Analysis</h2>\n";

$htaccessPath = __DIR__ . '/../../.htaccess';
if (file_exists($htaccessPath)) {
    echo "✅ .htaccess found<br><br>\n";
    
    $htaccessContent = file_get_contents($htaccessPath);
    $lines = explode("\n", $htaccessContent);
    
    echo "<h3>Relevant Routing Rules:</h3>\n";
    echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; font-family: monospace;'>\n";
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (strpos($line, 'booking') !== false || strpos($line, 'RewriteRule') !== false) {
            if (strpos($line, 'booking') !== false) {
                $displayLine = $lineNum + 1;
                echo "<strong>Line $displayLine:</strong> " . htmlspecialchars($line) . "<br>\n";
            }
        }
    }
    
    echo "</div><br>\n";
    
    // Check for specific patterns
    echo "<h3>Rule Analysis:</h3>\n";
    if (strpos($htaccessContent, 'booking/(.+\.html)') !== false) {
        echo "✅ Found HTML file routing rule<br>\n";
    } else {
        echo "❌ HTML file routing rule missing<br>\n";
    }
    
    if (strpos($htaccessContent, '/barbs-bali-apartments/public/') !== false) {
        echo "✅ Found correct target directory<br>\n";
    } else {
        echo "❌ Target directory rule missing<br>\n";
    }
    
} else {
    echo "❌ .htaccess not found at: $htaccessPath<br>\n";
}

// 4. Recommendations
echo "<h2>4. Routing Fix Recommendations</h2>\n";

echo "<div style='background: #e7f3ff; padding: 15px; border: 1px solid #b8daff; border-radius: 5px;'>\n";
echo "<h3>🔧 If thank-you.html is not accessible via /booking/thank-you.html:</h3>\n";
echo "<ol>\n";
echo "<li><strong>Ensure file exists</strong> at: <code>/barbs-bali-apartments/public/thank-you.html</code></li>\n";
echo "<li><strong>Check .htaccess rule</strong> for HTML files in booking path</li>\n";
echo "<li><strong>Test file permissions</strong> - file should be 644</li>\n";
echo "<li><strong>Clear any server cache</strong> if using caching</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>\n";
echo "<h3>🎯 Expected Flow:</h3>\n";
echo "<ul>\n";
echo "<li>Payment completes → Redirects to <code>/booking/thank-you.html</code></li>\n";
echo "<li>.htaccess routes to → <code>/barbs-bali-apartments/public/thank-you.html</code></li>\n";
echo "<li>File serves → Thank you page displays</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>\n";
echo "<h3>✅ Quick Fixes if Needed:</h3>\n";
echo "<ol>\n";
echo "<li><strong>Upload thank-you.html</strong> to correct location</li>\n";
echo "<li><strong>Update redirect URL</strong> in payment code to use <code>/booking/thank-you.html</code></li>\n";
echo "<li><strong>Test URL manually</strong> before testing payment flow</li>\n";
echo "</ol>\n";
echo "</div>\n";

?>