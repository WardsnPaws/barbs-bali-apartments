<?php
echo 'PHP running<br>';
$path = __DIR__ . '/views/settings.php';
echo 'Expected path: ' . htmlspecialchars($path) . '<br>';
echo 'file_exists: ' . (file_exists($path) ? 'yes' : 'no') . '<br>';
echo 'realpath: ' . (realpath($path) ?: 'realpath returned false') . '<br>';
?>