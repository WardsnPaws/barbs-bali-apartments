<?php
// admin/views/email-template-editor.php - Visual Email Template Editor

require_once __DIR__ . '/../../includes/core.php';
require_once __DIR__ . '/../auth.php';

$pdo = getPDO();

$templateId = $_GET['id'] ?? null;
$action = $_GET['action'] ?? 'edit';

// Load template if editing
$template = null;
if ($templateId && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        header('Location: email-templates.php?error=' . urlencode('Template not found'));
        exit;
    }
}

// Get all available variables
$variables = $pdo->query("
    SELECT * FROM email_template_variables 
    ORDER BY data_source, display_name
")->fetchAll(PDO::FETCH_ASSOC);

// Group variables by data source
$groupedVariables = [];
foreach ($variables as $var) {
    $groupedVariables[$var['data_source']][] = $var;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $templateName = trim($_POST['template_name']);
        $templateType = $_POST['template_type'];
        $subjectLine = trim($_POST['subject_line']);
        $htmlContent = $_POST['html_content'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if ($action === 'create') {
            $stmt = $pdo->prepare("
                INSERT INTO email_templates (template_name, template_type, subject_line, html_content, is_active, created_at, updated_at, last_modified_by) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 'Admin')
            ");
            $stmt->execute([$templateName, $templateType, $subjectLine, $htmlContent, $isActive]);
            $newId = $pdo->lastInsertId();
            
            header('Location: email-templates.php?success=' . urlencode('Template created successfully'));
            exit;
        } else {
            $stmt = $pdo->prepare("
                UPDATE email_templates 
                SET template_name = ?, template_type = ?, subject_line = ?, html_content = ?, is_active = ?, updated_at = NOW(), last_modified_by = 'Admin'
                WHERE id = ?
            ");
            $stmt->execute([$templateName, $templateType, $subjectLine, $htmlContent, $isActive, $templateId]);
            
            header('Location: email-templates.php?success=' . urlencode('Template updated successfully'));
            exit;
        }
    } catch (Exception $e) {
        $error = "Error saving template: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $action === 'create' ? 'Create' : 'Edit' ?> Email Template - Barbs Bali Admin</title>
    
    <!-- Include TinyMCE for WYSIWYG editing -->
    <script src="https://cdn.tiny.cloud/1/YOUR_TINYMCE_API_KEY/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        .editor-layout {
            display: grid;
            grid-template-columns: 300px 1fr 400px;
            gap: 20px;
            min-height: 80vh;
        }
        .sidebar {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        .main-editor {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .preview-panel {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            color: #2c3e50;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #1e7e34; }
        
        .variable-group {
            margin-bottom: 20px;
        }
        .variable-group h4 {
            color: #495057;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e9ecef;
        }
        .variable-item {
            padding: 8px 12px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 13px;
        }
        .variable-item:hover {
            background: #e9ecef;
            border-color: #007bff;
        }
        .variable-name {
            font-weight: 600;
            color: #007bff;
        }
        .variable-description {
            color: #6c757d;
            font-size: 11px;
            margin-top: 2px;
        }
        .preview-iframe {
            width: 100%;
            height: 600px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .save-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        @media (max-width: 1200px) {
            .editor-layout {
                grid-template-columns: 1fr;
            }
            .sidebar, .preview-panel {
                position: static;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>
            <?= $action === 'create' ? '➕ Create New' : '✏️ Edit' ?> Email Template
            <?php if ($template): ?>
                - <?= htmlspecialchars($template['template_name']) ?>
            <?php endif; ?>
        </h1>
        <div>
            <a href="email-templates.php" class="btn btn-secondary">← Back to Templates</a>
            <?php if ($template): ?>
                <a href="email-preview.php?id=<?= $template['id'] ?>" class="btn btn-primary" target="_blank">👁️ Preview</a>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" id="templateForm">
        <div class="editor-layout">
            <!-- Variables Sidebar -->
            <div class="sidebar">
                <h3 style="margin-top: 0;">📋 Available Variables</h3>
                <p style="color: #6c757d; font-size: 13px;">Click any variable to insert it into your template</p>
                
                <?php foreach ($groupedVariables as $source => $vars): ?>
                    <div class="variable-group">
                        <h4><?= ucfirst($source) ?> Data</h4>
                        <?php foreach ($vars as $var): ?>
                            <div class="variable-item" onclick="insertVariable('{{<?= $var['variable_name'] ?>}}')">
                                <div class="variable-name">{{<?= $var['variable_name'] ?>}}</div>
                                <div class="variable-description"><?= htmlspecialchars($var['description']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Main Editor -->
            <div class="main-editor">
                <div class="form-group">
                    <label for="template_name">Template Name</label>
                    <input type="text" id="template_name" name="template_name" class="form-control" 
                           value="<?= htmlspecialchars($template['template_name'] ?? '') ?>" 
                           placeholder="e.g., Booking Confirmation V2" required>
                </div>

                <div class="form-group">
                    <label for="template_type">Template Type</label>
                    <select id="template_type" name="template_type" class="form-control" required>
                        <option value="">Select Template Type</option>
                        <option value="booking_confirmation" <?= ($template['template_type'] ?? '') === 'booking_confirmation' ? 'selected' : '' ?>>Booking Confirmation</option>
                        <option value="balance_reminder" <?= ($template['template_type'] ?? '') === 'balance_reminder' ? 'selected' : '' ?>>Balance Reminder</option>
                        <option value="checkin_reminder" <?= ($template['template_type'] ?? '') === 'checkin_reminder' ? 'selected' : '' ?>>Check-in Reminder</option>
                        <option value="housekeeping_notice" <?= ($template['template_type'] ?? '') === 'housekeeping_notice' ? 'selected' : '' ?>>Housekeeping Notice</option>
                        <option value="custom" <?= ($template['template_type'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom Template</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="subject_line">Email Subject Line</label>
                    <input type="text" id="subject_line" name="subject_line" class="form-control" 
                           value="<?= htmlspecialchars($template['subject_line'] ?? '') ?>" 
                           placeholder="e.g., Your Booking is Confirmed - {{reservationnumber}}" required>
                </div>

                <div class="form-group">
                    <label for="html_content">Email Content</label>
                    <textarea id="html_content" name="html_content" style="height: 500px;">
                        <?= htmlspecialchars($template['html_content'] ?? '') ?>
                    </textarea>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" <?= ($template['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="is_active">Template is Active</label>
                    </div>
                </div>

                <div class="save-actions">
                    <button type="submit" class="btn btn-success">
                        💾 <?= $action === 'create' ? 'Create' : 'Save' ?> Template
                    </button>
                    <button type="button" onclick="previewTemplate()" class="btn btn-primary">
                        👁️ Preview
                    </button>
                    <a href="email-templates.php" class="btn btn-secondary">Cancel</a>
                </div>
            </div>

            <!-- Preview Panel -->
            <div class="preview-panel">
                <h3 style="margin-top: 0;">📱 Live Preview</h3>
                <div style="margin-bottom: 15px;">
                    <button type="button" onclick="refreshPreview()" class="btn btn-primary" style="width: 100%;">
                        🔄 Refresh Preview
                    </button>
                </div>
                <iframe id="previewFrame" class="preview-iframe" src="about:blank"></iframe>
            </div>
        </div>
    </form>
</div>

<script>
// Initialize TinyMCE editor
tinymce.init({
    selector: '#html_content',
    height: 500,
    plugins: 'code preview fullscreen link image table lists',
    toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code preview fullscreen',
    content_style: 'body { font-family: Arial, sans-serif; font . number_format($balance, 2) . ' AUD</strong><br>
                    <p style="margin: 10px 0;">Please pay your remaining balance at your convenience:</p>
                    <a href="' . $paymentLink . '" style="display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 10px;">
                        💳 Pay Balance Now
                    </a>
                </div>';
    }
    
    private function getUrgencyNotice($checkinDate, $balance) {
        if ($balance <= 0) {
            return '';
        }
        
        $daysUntilCheckin = (new DateTime())->diff($checkinDate)->days;
        
        if ($daysUntilCheckin <= 7) {
            return '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545; margin: 15px 0; color: #721c24;">
                        <strong>⚠️ Urgent Payment Required</strong><br>
                        Your check-in is in ' . $daysUntilCheckin . ' days. Please pay your balance immediately to avoid any issues.
                    </div>';
        } elseif ($daysUntilCheckin <= 14) {
            return '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107; margin: 15px 0; color: #856404;">
                        <strong>⏰ Payment Reminder</strong><br>
                        Your check-in is in ' . $daysUntilCheckin . ' days. Please pay your balance within the next week.
                    </div>';
        }
        
        return '';
    }
    
    private function getBookingManagementLink($reservationNumber) {
        return 'https://barbsbaliapartments.com/public/my-booking.php?res=' . urlencode($reservationNumber);
    }
    
    private function getPaymentLink($reservationNumber, $amount = null) {
        $link = 'https://barbsbaliapartments.com/public/pay-balance.php?res=' . urlencode($reservationNumber);
        if ($amount) {
            $link .= '&amount=' . $amount;
        }
        return $link;
    }
    
    /**
     * Replace variables in template content
     */
    private function replaceVariables($content, $data) {
        $processed = $content;
        
        foreach ($data as $key => $value) {
            $processed = str_replace('{{' . $key . '}}', $value, $processed);
        }
        
        // Clean up any unreplaced variables
        $processed = preg_replace('/{{[^}]+}}/', '[Variable not found]', $processed);
        
        return $processed;
    }
    
    /**
     * Send processed email
     */
    public function sendEmail($templateId, $bookingId, $additionalData = []) {
        $processed = $this->processTemplate($templateId, $bookingId, $additionalData);
        
        // Get recipient email from booking data
        $recipientEmail = $this->bookingData['guestemail'];
        
        // Send email using your existing email system
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Barbs Bali Apartments <bookings@barbsbaliapartments.com>\r\n";
        
        $emailSent = mail($recipientEmail, $processed['subject'], $processed['content'], $headers);
        
        if ($emailSent) {
            // Update template usage count
            $this->pdo->prepare("UPDATE email_templates SET usage_count = usage_count + 1 WHERE id = ?")
                     ->execute([$templateId]);
        }
        
        return $emailSent;
    }
}

// Helper function for backward compatibility
function processEmailTemplate($templateContent, $bookingData) {
    $processed = $templateContent;
    
    foreach ($bookingData as $key => $value) {
        $processed = str_replace('{{' . $key . '}}', $value, $processed);
    }
    
    return $processed;
}
?>