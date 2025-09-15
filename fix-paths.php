<?php
/**
 * Path Fix Script for Barbs Bali Apartments
 * Fixes file paths after reorganization
 */

$projectRoot = 'C:/wamp64/www/barbs-bali-apartments/';

// Define path replacements for different file locations
$pathFixes = [
    // Files in /api/ folder
    'api' => [
        "__DIR__ . '/core.php'" => "__DIR__ . '/../includes/core.php'",
        "__DIR__ . '/core/price-calc.php'" => "__DIR__ . '/../includes/price-calc.php'",
        "__DIR__ . '/email-utils.php'" => "__DIR__ . '/../includes/email-utils.php'",
        "__DIR__ . '/config.php'" => "__DIR__ . '/../config/config.php'",
        "include 'core.php'" => "include '../includes/core.php'",
        "require 'core.php'" => "require '../includes/core.php'",
        "require_once 'core.php'" => "require_once '../includes/core.php'",
        "include_once 'core.php'" => "include_once '../includes/core.php'",
        "include 'config.php'" => "include '../config/config.php'",
        "require 'config.php'" => "require '../config/config.php'",
        "require_once 'config.php'" => "require_once '../config/config.php'",
        "include_once 'config.php'" => "include_once '../config/config.php'",
        "include 'email-utils.php'" => "include '../includes/email-utils.php'",
        "require 'email-utils.php'" => "require '../includes/email-utils.php'",
        "require_once 'email-utils.php'" => "require_once '../includes/email-utils.php'",
        "include_once 'email-utils.php'" => "include_once '../includes/email-utils.php'",
        // Redirect fixes for files in /api/
        "header(\"Location: secure-payment.php\")" => "header(\"Location: ../public/secure-payment.php\")",
        "header(\"Location: thank-you.html\")" => "header(\"Location: ../public/thank-you.html\")",
        "header('Location: secure-payment.php')" => "header('Location: ../public/secure-payment.php')",
        "header('Location: thank-you.html')" => "header('Location: ../public/thank-you.html')",
    ],
    
    // Files in /public/ folder
    'public' => [
        "__DIR__ . '/core.php'" => "__DIR__ . '/../includes/core.php'",
        "__DIR__ . '/config.php'" => "__DIR__ . '/../config/config.php'",
        "__DIR__ . '/email-utils.php'" => "__DIR__ . '/../includes/email-utils.php'",
        "include 'core.php'" => "include '../includes/core.php'",
        "require 'core.php'" => "require '../includes/core.php'",
        "require_once 'core.php'" => "require_once '../includes/core.php'",
        "include_once 'core.php'" => "include_once '../includes/core.php'",
        "include 'config.php'" => "include '../config/config.php'",
        "require 'config.php'" => "require '../config/config.php'",
        "require_once 'config.php'" => "require_once '../config/config.php'",
        "include_once 'config.php'" => "include_once '../config/config.php'",
        // API endpoint references
        "action=\"booking-process.php\"" => "action=\"../api/booking-process.php\"",
        "action='booking-process.php'" => "action='../api/booking-process.php'",
        "href=\"check-availability.php\"" => "href=\"../api/check-availability.php\"",
        "href='check-availability.php'" => "href='../api/check-availability.php'",
    ],
    
    // Files in /admin/ folder  
    'admin' => [
        "__DIR__ . '/core.php'" => "__DIR__ . '/../includes/core.php'",
        "__DIR__ . '/config.php'" => "__DIR__ . '/../config/config.php'",
        "__DIR__ . '/email-utils.php'" => "__DIR__ . '/../includes/email-utils.php'",
        "__DIR__ . '/config_secrets.php'" => "__DIR__ . '/../config/config_secrets.php'",
        "include 'core.php'" => "include '../includes/core.php'",
        "require 'core.php'" => "require '../includes/core.php'",
        "require_once 'core.php'" => "require_once '../includes/core.php'",
        "include_once 'core.php'" => "include_once '../includes/core.php'",
        "include 'config.php'" => "include '../config/config.php'",
        "require 'config.php'" => "require '../config/config.php'",
        "require_once 'config.php'" => "require_once '../config/config.php'",
        "include_once 'config.php'" => "include_once '../config/config.php'",
        "include 'auth.php'" => "include 'auth.php'", // stays same, auth.php is in admin folder
        "require 'auth.php'" => "require 'auth.php'",
        "require_once 'auth.php'" => "require_once 'auth.php'",
    ],
    
    // Files in /scripts/ folder
    'scripts' => [
        "__DIR__ . '/core.php'" => "__DIR__ . '/../includes/core.php'",
        "__DIR__ . '/config.php'" => "__DIR__ . '/../config/config.php'",
        "__DIR__ . '/email-utils.php'" => "__DIR__ . '/../includes/email-utils.php'",
        "__DIR__ . '/config_secrets.php'" => "__DIR__ . '/../config/config_secrets.php'",
        "include 'core.php'" => "include '../includes/core.php'",
        "require 'core.php'" => "require '../includes/core.php'",
        "require_once 'core.php'" => "require_once '../includes/core.php'",
        "include_once 'core.php'" => "include_once '../includes/core.php'",
        "include 'config.php'" => "include '../config/config.php'",
        "require 'config.php'" => "require '../config/config.php'",
        "require_once 'config.php'" => "require_once '../config/config.php'",
        "include_once 'config.php'" => "include_once '../config/config.php'",
    ]
];

function fixPathsInFile($filePath, $replacements) {
    if (!file_exists($filePath)) {
        echo "! File not found: $filePath\n";
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    foreach ($replacements as $old => $new) {
        $content = str_replace($old, $new, $content);
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

function processDirectory($dir, $replacements) {
    if (!is_dir($dir)) {
        echo "! Directory not found: $dir\n";
        return;
    }
    
    $files = glob($dir . '/*.php');
    foreach ($files as $file) {
        fixPathsInFile($file, $replacements);
    }
    
    // Process subdirectories
    $subdirs = glob($dir . '/*', GLOB_ONLYDIR);
    foreach ($subdirs as $subdir) {
        $files = glob($subdir . '/*.php');
        foreach ($files as $file) {
            fixPathsInFile($file, $replacements);
        }
    }
}

// Main execution
echo "=== Barbs Bali Apartments Path Fixer ===\n\n";

foreach ($pathFixes as $folder => $replacements) {
    echo "Processing {$folder}/ directory...\n";
    processDirectory($projectRoot . $folder, $replacements);
    echo "\n";
}

// Special case: fix email template paths in email-utils.php
echo "Fixing email template paths...\n";
$emailUtilsPath = $projectRoot . 'includes/email-utils.php';
if (file_exists($emailUtilsPath)) {
    $emailUtilsContent = file_get_contents($emailUtilsPath);
    $emailUtilsContent = str_replace(
        "__DIR__ . '/email-templates/",
        "__DIR__ . '/../templates/email/",
        $emailUtilsContent
    );
    $emailUtilsContent = str_replace(
        "email-templates/",
        "../templates/email/",
        $emailUtilsContent
    );
    
    if (file_put_contents($emailUtilsPath, $emailUtilsContent)) {
        echo "✓ Fixed email template paths in email-utils.php\n";
    } else {
        echo "✗ Failed to fix email template paths\n";
    }
}

echo "\n=== PATH FIXING COMPLETE ===\n";
echo "Please test your application to ensure all paths work correctly.\n";
echo "You may need to update your web server document root or .htaccess files.\n";
?>