<?php
// admin/api/send-test-email.php - Send test emails for templates

require_once __DIR__ . '/../../includes/core.php';
require_once __DIR__ . '/../../includes/email-template-engine.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['template_id'], $data['test_email'])) {
        throw new Exception('Missing required parameters');
    }
    
    $templateId = $data['template_id'];
    $testEmail = $data['test_email'];
    $bookingId = $data['booking_id'] ?? null;
    
    // Validate email address
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    $pdo = getPDO();
    
    // Get template
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        throw new Exception('Template not found');
    }
    
    $engine = new EmailTemplateEngine();
    
    if ($bookingId) {
        // Use real booking data
        $processed = $engine->processTemplate($templateId, $bookingId);
    } else {
        // Create sample booking data
        $sampleBooking = [
            'id' => 999999,
            'reservation_number' => 'TEST' . strtoupper(uniqid()),
            'guest_first_name' => 'John',
            'guest_last_name' => 'Doe',
            'guest_email' => $testEmail,
            'apartment_id' => 1,
            'checkin_date' => date('Y-m-d', strtotime('+30 days')),
            'checkout_date' => date('Y-m-d', strtotime('+33 days')),
            'total_price' => 450.00,
            'sofa_bed' => 1,
            'status' => 'confirmed',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Insert temporary sample booking
        $stmt = $pdo->prepare("
            INSERT INTO bookings (reservation_number, guest_first_name, guest_last_name, guest_email, apartment_id, checkin_date, checkout_date, total_price, sofa_bed, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sampleBooking['reservation_number'],
            $sampleBooking['guest_first_name'],
            $sampleBooking['guest_last_name'],
            $sampleBooking['guest_email'],
            $sampleBooking['apartment_id'],
            $sampleBooking['checkin_date'],
            $sampleBooking['checkout_date'],
            $sampleBooking['total_price'],
            $sampleBooking['sofa_bed'],
            $sampleBooking['status'],
            $sampleBooking['created_at']
        ]);
        
        $tempBookingId = $pdo->lastInsertId();
        
        // Add sample payment
        $pdo->prepare("INSERT INTO payments (booking_id, amount, method, note, paid_at) VALUES (?, ?, ?, ?, ?)")
           ->execute([$tempBookingId, 90.00, 'test', 'Sample deposit payment', date('Y-m-d H:i:s')]);
        
        try {
            // Process template with sample data
            $processed = $engine->processTemplate($templateId, $tempBookingId, [
                'guestemail' => $testEmail // Override with test email
            ]);
            
            // Send test email
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Barbs Bali Apartments <bookings@barbsbaliapartments.com>\r\n";
            $headers .= "X-Test-Email: true\r\n";
            
            $subject = "[TEST] " . $processed['subject'];
            $content = '<div style="background: #fff3cd; padding: 15px; margin-bottom: 20px; border-left: 4px solid #ffc107; color: #856404;">
                           <strong>🧪 This is a test email</strong><br>
                           This email was sent for template testing purposes. All data is sample data.
                       </div>' . $processed['content'];
            
            $emailSent = mail($testEmail, $subject, $content, $headers);
            
        } finally {
            // Clean up sample booking
            $pdo->prepare("DELETE FROM payments WHERE booking_id = ?")->execute([$tempBookingId]);
            $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$tempBookingId]);
        }
    }
    
    if ($emailSent) {
        echo json_encode([
            'success' => true, 
            'message' => 'Test email sent successfully to ' . $testEmail
        ]);
    } else {
        throw new Exception('Failed to send test email');
    }
    
} catch (Exception $e) {
    error_log("Test email error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>