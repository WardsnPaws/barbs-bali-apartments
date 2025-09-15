<?php
// debug/test-email-scheduling.php - Test email scheduling functionality

require_once __DIR__ . '/../includes/core.php';

echo "<h2>Email Scheduling Test</h2>\n";
echo "<pre>\n";

try {
    $pdo = getPDO();
    
    // Get the most recent booking
    $stmt = $pdo->prepare("SELECT * FROM bookings ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo "❌ No bookings found. Please make a test booking first.\n";
        exit;
    }
    
    echo "Using booking: ID {$booking['id']}, Reservation: {$booking['reservation_number']}\n";
    echo "Guest: {$booking['guest_first_name']} {$booking['guest_last_name']}\n";
    echo "Check-in: {$booking['checkin_date']}\n\n";
    
    $bookingId = $booking['id'];
    $checkinDate = new DateTime($booking['checkin_date']);
    $today = date('Y-m-d');
    
    // Calculate days until checkin
    $daysUntil = (new DateTime())->diff($checkinDate)->days;
    $depositOnly = $daysUntil > (defined('DEPOSIT_THRESHOLD_DAYS') ? DEPOSIT_THRESHOLD_DAYS : 90);
    
    echo "Days until check-in: {$daysUntil}\n";
    echo "Is deposit only: " . ($depositOnly ? 'YES' : 'NO') . "\n\n";
    
    // Clear any existing scheduled emails for this booking
    $pdo->prepare("DELETE FROM email_schedule WHERE booking_id = ?")->execute([$bookingId]);
    echo "Cleared existing scheduled emails for this booking\n\n";
    
    // Now test scheduling emails exactly like square-payment.php does
    echo "=== TESTING EMAIL SCHEDULING ===\n";
    
    $emailStmt = $pdo->prepare("INSERT INTO email_schedule
        (booking_id, email_type, send_date, sent_status)
        VALUES (?, ?, ?, 'scheduled')");

    try {
        // Schedule confirmation email for immediate sending
        $result1 = $emailStmt->execute([$bookingId, 'confirmation', $today]);
        echo "1. Confirmation email: " . ($result1 ? "✅ SCHEDULED" : "❌ FAILED") . "\n";
        if (!$result1) {
            $errorInfo = $emailStmt->errorInfo();
            echo "   Error: " . print_r($errorInfo, true) . "\n";
        }

        // Schedule balance reminder if this was a deposit
        if ($depositOnly) {
            $balanceReminderDate = (clone $checkinDate)->modify('-90 days')->format('Y-m-d');
            $result2 = $emailStmt->execute([$bookingId, 'balance_reminder', $balanceReminderDate]);
            echo "2. Balance reminder for {$balanceReminderDate}: " . ($result2 ? "✅ SCHEDULED" : "❌ FAILED") . "\n";
            if (!$result2) {
                $errorInfo = $emailStmt->errorInfo();
                echo "   Error: " . print_r($errorInfo, true) . "\n";
            }
        } else {
            echo "2. Balance reminder: ⏭️ SKIPPED (full payment)\n";
        }

        // Schedule check-in reminder (14 days before)
        $checkinReminderDate = (clone $checkinDate)->modify('-14 days')->format('Y-m-d');
        $result3 = $emailStmt->execute([$bookingId, 'checkin_reminder', $checkinReminderDate]);
        echo "3. Check-in reminder for {$checkinReminderDate}: " . ($result3 ? "✅ SCHEDULED" : "❌ FAILED") . "\n";
        if (!$result3) {
            $errorInfo = $emailStmt->errorInfo();
            echo "   Error: " . print_r($errorInfo, true) . "\n";
        }

        // Schedule housekeeping notice (7 days before)
        $housekeepingDate = (clone $checkinDate)->modify('-7 days')->format('Y-m-d');
        $result4 = $emailStmt->execute([$bookingId, 'housekeeping_notice', $housekeepingDate]);
        echo "4. Housekeeping notice for {$housekeepingDate}: " . ($result4 ? "✅ SCHEDULED" : "❌ FAILED") . "\n";
        if (!$result4) {
            $errorInfo = $emailStmt->errorInfo();
            echo "   Error: " . print_r($errorInfo, true) . "\n";
        }

    } catch (Exception $e) {
        echo "❌ EXCEPTION during scheduling: " . $e->getMessage() . "\n";
        echo "Stack trace: " . $e->getTraceAsString() . "\n";
    }
    
    echo "\n=== VERIFYING SCHEDULED EMAILS ===\n";
    $verifyStmt = $pdo->prepare("SELECT * FROM email_schedule WHERE booking_id = ? ORDER BY send_date");
    $verifyStmt->execute([$bookingId]);
    $scheduledEmails = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($scheduledEmails)) {
        echo "❌ NO EMAILS FOUND IN DATABASE!\n";
        
        // Let's check if the table exists and has the right structure
        echo "\nChecking table structure...\n";
        $structure = $pdo->query("DESCRIBE email_schedule")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($structure as $column) {
            echo "  {$column['Field']} | {$column['Type']} | {$column['Null']} | {$column['Key']}\n";
        }
        
    } else {
        echo "✅ Found " . count($scheduledEmails) . " scheduled emails:\n\n";
        foreach ($scheduledEmails as $email) {
            echo "  Type: {$email['email_type']}\n";
            echo "  Send Date: {$email['send_date']}\n"; 
            echo "  Status: {$email['sent_status']}\n";
            echo "  Created: {$email['created_at']}\n";
            echo "  ---\n";
        }
    }
    
    echo "\n=== TESTING MANUAL INSERT ===\n";
    try {
        $manualStmt = $pdo->prepare("INSERT INTO email_schedule (booking_id, email_type, send_date, sent_status, created_at) VALUES (?, 'test', ?, 'scheduled', NOW())");
        $manualResult = $manualStmt->execute([$bookingId, $today]);
        echo "Manual insert: " . ($manualResult ? "✅ SUCCESS" : "❌ FAILED") . "\n";
        
        if ($manualResult) {
            $manualId = $pdo->lastInsertId();
            echo "Inserted ID: {$manualId}\n";
            
            // Clean up
            $pdo->prepare("DELETE FROM email_schedule WHERE id = ?")->execute([$manualId]);
            echo "Test record cleaned up\n";
        }
    } catch (Exception $e) {
        echo "❌ Manual insert failed: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>