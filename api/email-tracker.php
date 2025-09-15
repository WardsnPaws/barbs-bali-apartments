<?php
// api/email-tracker.php - Email tracking pixel endpoint
require_once __DIR__ . '/../includes/core.php';

$emailId = $_GET['email_id'] ?? null;

if ($emailId && is_numeric($emailId)) {
    try {
        $pdo = getPDO();
        
        // Check if email_tracking table exists, if not create it
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'email_tracking'");
        if ($tableCheck->rowCount() == 0) {
            // Create email_tracking table
            $pdo->exec("
                CREATE TABLE email_tracking (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    email_schedule_id INT,
                    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (email_schedule_id)
                )
            ");
        }
        
        // Record the email open
        $stmt = $pdo->prepare("INSERT INTO email_tracking (email_schedule_id, ip_address, user_agent) VALUES (?, ?, ?)");
        $stmt->execute([
            $emailId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // Update the email_schedule table to mark as opened
        $updateStmt = $pdo->prepare("UPDATE email_schedule SET opened_at = NOW() WHERE id = ? AND opened_at IS NULL");
        $updateStmt->execute([$emailId]);
        
    } catch (Exception $e) {
        error_log("Email tracking error: " . $e->getMessage());
    }
}

// Return a 1x1 transparent PNG pixel
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 1x1 transparent PNG in base64
$pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
echo $pixel;
exit;