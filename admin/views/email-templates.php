<?php
// admin/views/email-templates.php - Email Template Management Dashboard

require_once __DIR__ . '/../../includes/core.php';
require_once __DIR__ . '/../auth.php';

$pdo = getPDO();

// Handle template actions
$action = $_GET['action'] ?? 'list';
$templateId = $_GET['id'] ?? null;
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Process actions
if ($action === 'activate' && $templateId) {
    $stmt = $pdo->prepare("UPDATE email_templates SET is_active = 1 WHERE id = ?");
    if ($stmt->execute([$templateId])) {
        header('Location: email-templates.php?success=' . urlencode('Template activated successfully'));
        exit;
    }
} elseif ($action === 'deactivate' && $templateId) {
    $stmt = $pdo->prepare("UPDATE email_templates SET is_active = 0 WHERE id = ?");
    if ($stmt->execute([$templateId])) {
        header('Location: email-templates.php?success=' . urlencode('Template deactivated successfully'));
        exit;
    }
} elseif ($action === 'duplicate' && $templateId) {
    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($original) {
        $newName = $original['template_name'] . ' (Copy)';
        $insertStmt = $pdo->prepare("
            INSERT INTO email_templates (template_name, template_type, subject_line, html_content, is_active, created_at, updated_at, last_modified_by) 
            VALUES (?, ?, ?, ?, 0, NOW(), NOW(), 'Admin')
        ");
        $insertStmt->execute([$newName, $original['template_type'], $original['subject_line'], $original['html_content']]);
        header('Location: email-templates.php?success=' . urlencode('Template duplicated successfully'));
        exit;
    }
}

// Get all templates with usage statistics
$templates = $pdo->query("
    SELECT et.*, 
           COALESCE(et.usage_count, 0) as usage_count_stored,
           COUNT(es.id) as usage_count_actual,
           MAX(es.created_at) as last_used
    FROM email_templates et
    LEFT JOIN email_schedule es ON et.template_name = es.email_type 
        OR (et.template_type = es.email_type)
    GROUP BY et.id
    ORDER BY et.template_type, et.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get template variables for the editor
$variables = $pdo->query("
    SELECT * FROM email_template_variables 
    WHERE is_active = 1
    ORDER BY data_source, sort_order, display_name
")->fetchAll(PDO::FETCH_ASSOC);

// Group variables by data source
$groupedVariables = [];
foreach ($variables as $var) {
    $groupedVariables[$var['data_source']][] = $var;
}

// Calculate statistics
$totalTemplates = count($templates);
$activeTemplates = count(array_filter($templates, fn($t) => $t['is_active']));
$totalEmailsSent = array_sum(array_column($templates, 'usage_count_actual'));
$totalVariables = count($variables);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Template Manager - Barbs Bali Admin</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
            color: #212529;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 28px;
        }
        .header-subtitle {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .quick-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }
        .btn-success { 
            background: #28a745; 
            color: white; 
        }
        .btn-success:hover { 
            background: #1e7e34; 
            transform: translateY(-1px);
        }
        .btn-info { 
            background: #17a2b8; 
            color: white; 
        }
        .btn-info:hover { 
            background: #117a8b; 
        }
        .btn-warning { 
            background: #ffc107; 
            color: #212529; 
        }
        .btn-warning:hover { 
            background: #e0a800; 
        }
        .btn-primary { 
            background: #007bff; 
            color: white; 
        }
        .btn-primary:hover { 
            background: #0056b3; 
        }
        .btn-secondary { 
            background: #6c757d; 
            color: white; 
        }
        .btn-secondary:hover { 
            background: #545b62; 
        }
        .btn-danger { 
            background: #dc3545; 
            color: white; 
        }
        .btn-danger:hover { 
            background: #c82333; 
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .stats-bar {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
        }
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
        }
        .template-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        .template-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .template-header {
            padding: 25px;
            border-bottom: 1px solid #f1f3f4;
        }
        .template-type {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }
        .type-booking_confirmation { 
            background: #e7f3ff; 
            color: #0066cc; 
        }
        .type-balance_reminder { 
            background: #fff3cd; 
            color: #856404; 
        }
        .type-checkin_reminder { 
            background: #d4edda; 
            color: #155724; 
        }
        .type-housekeeping_notice { 
            background: #f8d7da; 
            color: #721c24; 
        }
        .type-custom { 
            background: #e2e3e5; 
            color: #6c757d; 
        }
        
        .template-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 8px 0;
            line-height: 1.3;
        }
        .template-stats {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.4;
        }
        .template-stats .stat-highlight {
            color: #495057;
            font-weight: 600;
        }
        .template-actions {
            padding: 20px 25px;
            background: #f8f9fa;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .template-actions .btn {
            padding: 8px 16px;
            font-size: 13px;
            flex: 1;
            min-width: 80px;
        }
        .create-new-card {
            border: 2px dashed #dee2e6;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            transition: all 0.3s ease;
        }
        .create-new-card:hover {
            border-color: #007bff;
            background: linear-gradient(135deg, #e7f3ff 0%, #cce7ff 100%);
            transform: translateY(-3px);
        }
        .create-new-content {
            text-align: center;
            padding: 40px;
        }
        .create-new-icon {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
            transition: color 0.3s ease;
        }
        .create-new-card:hover .create-new-icon {
            color: #007bff;
        }
        .template-status {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .status-active { background: #28a745; }
        .status-inactive { background: #6c757d; }
        
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .templates-grid { grid-template-columns: 1fr; }
            .stats-bar { 
                grid-template-columns: repeat(2, 1fr); 
                gap: 20px;
                padding: 20px;
            }
            .quick-actions { 
                justify-content: center; 
            }
            .template-actions {
                flex-direction: column;
            }
            .template-actions .btn {
                flex: none;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>
            📧 Email Template Manager
        </h1>
        <p class="header-subtitle">Create, edit, and manage all your automated email communications with a professional visual editor.</p>
        
        <div class="quick-actions">
            <a href="email-template-editor.php?action=create" class="btn btn-success">
                ➕ Create New Template
            </a>
            <a href="email-variables.php" class="btn btn-info">
                🔧 Manage Variables
            </a>
            <a href="../index.php" class="btn btn-secondary">
                ← Back to Admin
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="success-message">
            ✅ <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message">
            ❌ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-number"><?= $totalTemplates ?></div>
            <div class="stat-label">Total Templates</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= $activeTemplates ?></div>
            <div class="stat-label">Active Templates</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= $totalEmailsSent ?></div>
            <div class="stat-label">Emails Sent</div>
        </div>
        <div class="stat-item">
            <div class="stat-number"><?= $totalVariables ?></div>
            <div class="stat-label">Available Variables</div>
        </div>
    </div>

    <div class="templates-grid">
        <?php foreach ($templates as $template): ?>
            <div class="template-card" style="position: relative;">
                <div class="template-status <?= $template['is_active'] ? 'status-active' : 'status-inactive' ?>"></div>
                
                <div class="template-header">
                    <span class="template-type type-<?= $template['template_type'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $template['template_type'])) ?>
                    </span>
                    <h3 class="template-title"><?= htmlspecialchars($template['template_name']) ?></h3>
                    <div class="template-stats">
                        📊 Used <span class="stat-highlight"><?= $template['usage_count_actual'] ?></span> times
                        <?php if ($template['last_used']): ?>
                            • Last: <span class="stat-highlight"><?= date('M j, Y', strtotime($template['last_used'])) ?></span>
                        <?php endif; ?>
                        <br>
                        📝 Modified: <span class="stat-highlight"><?= date('M j, Y', strtotime($template['updated_at'])) ?></span>
                        <?php if ($template['last_modified_by']): ?>
                            by <?= htmlspecialchars($template['last_modified_by']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="template-actions">
                    <a href="email-template-editor.php?id=<?= $template['id'] ?>" class="btn btn-primary">
                        ✏️ Edit
                    </a>
                    <a href="?action=duplicate&id=<?= $template['id'] ?>" class="btn btn-warning">
                        📋 Copy
                    </a>
                    <?php if ($template['is_active']): ?>
                        <a href="?action=deactivate&id=<?= $template['id'] ?>" 
                           class="btn btn-secondary"
                           onclick="return confirm('Deactivate this template? It will no longer be used for automatic emails.')">
                            🚫 Deactivate
                        </a>
                    <?php else: ?>
                        <a href="?action=activate&id=<?= $template['id'] ?>" class="btn btn-success">
                            ✅ Activate
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Create New Template Card -->
        <div class="template-card create-new-card">
            <div class="create-new-content">
                <div class="create-new-icon">➕</div>
                <h3 class="template-title" style="margin-bottom: 15px;">Create New Template</h3>
                <p style="color: #6c757d; margin-bottom: 20px;">Start building a custom email template with our visual editor</p>
                <a href="email-template-editor.php?action=create" class="btn btn-success">
                    🎨 Start Creating
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh stats every 30 seconds
setTimeout(function() {
    location.reload();
}, 30000);

// Add loading states to buttons
document.querySelectorAll('.btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (this.href && !this.href.includes('javascript:')) {
            this.style.opacity = '0.6';
            this.innerHTML = '⏳ Loading...';
        }
    });
});
</script>

</body>
</html>