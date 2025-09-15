<?php
// debug/email-schedule-debug.php - Diagnose email scheduling issues

require_once __DIR__ . '/../includes/core.php';

echo "<h2>Email Scheduling Debug Script</h2>\n";
echo "<pre>\n";

try {
    $pdo = getPDO();
    echo "✅ Database connection successful\n\n";

    // Check if email_schedule table exists
    echo "=== CHECKING EMAIL_SCHEDULE TABLE ===\n";
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'email_schedule'");
    if ($tableCheck->rowCount() > 0) {
        echo "✅ email_schedule table exists\n";
        
        // Check table structure
        $structure = $pdo->query("DESCRIBE email_schedule")->fetchAll(PDO::FETCH_ASSOC);
        echo "Table structure:\n";
        foreach ($structure as $column) {
            echo "  - {$column['Field']} ({$column['Type']}) {$column['Null']} {$column['Key']}\n";
        }
    } else {
        echo "❌ email_schedule table does NOT exist\n";
        echo "Creating email_schedule table...\n";
        
        $createTable = "
        CREATE TABLE email_schedule (
            id INT PRIMARY KEY AUTO_INCREMENT,
            booking_id INT NOT NULL,
            email_type ENUM('confirmation', 'balance_reminder', 'checkin_reminder', 'housekeeping_notice') NOT NULL,
            send_date DATE NOT NULL,
            sent_status ENUM('scheduled', 'sent', 'failed') DEFAULT 'scheduled',
            sent_timestamp TIMESTAMP NULL,
            opened_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            INDEX idx_send_date_status (send_date, sent_status),
            INDEX idx_booking_id (booking_id)
        )";
        
        $pdo->exec($createTable);
        echo "✅ email_schedule table created\n";
    }
    
    echo "\n=== CHECKING RECENT BOOKINGS ===\n";
    $recentBookings = $pdo->query("
        SELECT id, reservation_number, guest_first_name, guest_last_name, 
               checkin_date, checkout_date, created_at, status
        FROM bookings 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($recentBookings)) {
        echo "❌ No bookings found in database\n";
    } else {
        echo "Recent bookings:\n";
        foreach ($recentBookings as $booking) {
            echo "  ID: {$booking['id']} | {$booking['reservation_number']} | {$booking['guest_first_name']} {$booking['guest_last_name']} | {$booking['status']} | {$booking['created_at']}\n";
        }
    }
    
    echo "\n=== CHECKING SCHEDULED EMAILS ===\n";
    $scheduledEmails = $pdo->query("
        SELECT es.*, b.reservation_number, b.guest_first_name, b.guest_last_name
        FROM email_schedule es
        LEFT JOIN bookings b ON es.booking_id = b.id
        ORDER BY es.created_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($scheduledEmails)) {
        echo "❌ No scheduled emails found\n";
        
        // Check if there are any bookings that should have scheduled emails
        if (!empty($recentBookings)) {
            echo "\n=== MANUAL EMAIL SCHEDULING TEST ===\n";
            $testBooking = $recentBookings[0];
            $bookingId = $testBooking['id'];
            $checkinDate = new DateTime($testBooking['checkin_date']);
            $today = date('Y-m-d');
            
            echo "Testing email scheduling for booking ID: {$bookingId}\n";
            
            try {
                $emailStmt = $pdo->prepare("INSERT INTO email_schedule
                    (booking_id, email_type, send_date, sent_status)
                    VALUES (?, ?, ?, 'scheduled')");

                // Schedule confirmation email
                $result1 = $emailStmt->execute([$bookingId, 'confirmation', $today]);
                echo "Confirmation email scheduled: " . ($result1 ? "✅" : "❌") . "\n";

                // Schedule check-in reminder (14 days before)
                $checkinReminderDate = (clone $checkinDate)->modify('-14 days')->format('Y-m-d');
                $result2 = $emailStmt->execute([$bookingId, 'checkin_reminder', $checkinReminderDate]);
                echo "Check-in reminder scheduled for {$checkinReminderDate}: " . ($result2 ? "✅" : "❌") . "\n";

                // Schedule housekeeping notice (7 days before)
                $housekeepingDate = (clone $checkinDate)->modify('-7 days')->format('Y-m-d');
                $result3 = $emailStmt->execute([$bookingId, 'housekeeping_notice', $housekeepingDate]);
                echo "Housekeeping notice scheduled for {$housekeepingDate}: " . ($result3 ? "✅" : "❌") . "\n";
                
            } catch (Exception $e) {
                echo "❌ Error scheduling emails: " . $e->getMessage() . "\n";
            }
        }
        
    } else {
        echo "Found scheduled emails:\n";
        foreach ($scheduledEmails as $email) {
            $bookingInfo = $email['reservation_number'] ? "{$email['reservation_number']} ({$email['guest_first_name']} {$email['guest_last_name']})" : "Booking ID: {$email['booking_id']}";
            echo "  ID: {$email['id']} | Type: {$email['email_type']} | Send: {$email['send_date']} | Status: {$email['sent_status']} | Booking: {$bookingInfo}\n";
        }
    }
    
    echo "\n=== CHECKING PAYMENTS TABLE ===\n";
    $paymentsCheck = $pdo->query("SHOW TABLES LIKE 'payments'")->rowCount();
    if ($paymentsCheck > 0) {
        echo "✅ payments table exists\n";
        $recentPayments = $pdo->query("SELECT * FROM payments ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($recentPayments)) {
            echo "Recent payments:\n";
            foreach ($recentPayments as $payment) {
                echo "  Booking ID: {$payment['booking_id']} | Amount: \${$payment['amount']} | Method: {$payment['method']} | Date: {$payment['created_at']}\n";
            }
        }
    } else {
        echo "❌ payments table does NOT exist\n";
    }
    
    echo "\n=== TESTING DATABASE PERMISSIONS ===\n";
    try {
        $testInsert = $pdo->prepare("INSERT INTO email_schedule (booking_id, email_type, send_date, sent_status) VALUES (999999, 'confirmation', '2025-01-01', 'test')");
        $testInsert->execute();
        echo "✅ INSERT permission works\n";
        
        // Clean up test record
        $pdo->prepare("DELETE FROM email_schedule WHERE booking_id = 999999 AND sent_status = 'test'")->execute();
        echo "✅ DELETE permission works\n";
        
    } catch (Exception $e) {
        echo "❌ Database permission error: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== CONFIGURATION CHECK ===\n";
    echo "DEPOSIT_THRESHOLD_DAYS: " . (defined('DEPOSIT_THRESHOLD_DAYS') ? DEPOSIT_THRESHOLD_DAYS : 'NOT DEFINED') . "\n";
    echo "DEPOSIT_RATE: " . (defined('DEPOSIT_RATE') ? DEPOSIT_RATE : 'NOT DEFINED') . "\n";
    echo "Current date: " . date('Y-m-d') . "\n";
    echo "Current timestamp: " . date('Y-m-d H:i:s') . "\n";

} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== RECOMMENDATIONS ===\n";
echo "1. If email_schedule table was missing, try making a new booking\n";
echo "2. Check the square-payment.php file for database errors\n";
echo "3. Look at PHP error logs during booking process\n";
echo "4. Verify foreign key constraints are working\n";

echo "</pre>";
?>