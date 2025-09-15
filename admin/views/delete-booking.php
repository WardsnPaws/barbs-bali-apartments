<?php
require_once __DIR__ . '/../../includes/core.php';
require_once __DIR__ . '/../auth.php';

$pdo = getPDO();
$id = $_GET['id'] ?? '';

if (!$id || !is_numeric($id)) {
    die("Invalid booking ID.");
}

// Delete extras
$pdo->prepare("DELETE FROM booking_extras WHERE booking_id = ?")->execute([$id]);

// Delete emails
$pdo->prepare("DELETE FROM email_schedule WHERE booking_id = ?")->execute([$id]);

// Delete payments
$pdo->prepare("DELETE FROM payments WHERE booking_id = ?")->execute([$id]);

// Delete the booking itself
$pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$id]);

header("Location: index.php?view=bookings");
exit;
