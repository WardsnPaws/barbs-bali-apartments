<?php
// admin/auth.php

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Build a correct absolute path to the admin login page based on the current request URL
    $adminDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // e.g. /booking/admin
    $loginUrl = $adminDir . '/login.php';
    header('Location: ' . $loginUrl);
    exit;
}