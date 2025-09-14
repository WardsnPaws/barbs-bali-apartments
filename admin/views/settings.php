<?php
// admin/views/settings.php
// Simple admin settings editor (make backups before saving)

require_once __DIR__ . '/../auth.php'; // resolves to admin/auth.php

// Ensure admin is logged in (now handled by auth.php)

// Paths to files we will edit
$configPath = realpath(__DIR__ . '/../../config.php') ?: (__DIR__ . '/../../config.php');
$corePath   = realpath(__DIR__ . '/../../core.php')   ?: (__DIR__ . '/../../core.php');

// Helper: readable file contents or empty string if missing
function load_file_safe($path) {
    return is_readable($path) ? file_get_contents($path) : '';
}

// Helper: backup existing file before overwrite
function backup_file($path) {
    if (!file_exists($path)) return true;
    $bak = $path . '.' . date('Ymd_His') . '.bak';
    return copy($path, $bak);
}

$errors = [];
$success = '';

// Load current contents
$configContent = load_file_safe($configPath);
$coreContent   = load_file_safe($corePath);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF protection suggestion: you can add a token check here
    if (isset($_POST['update_config'])) {
    $new = $_POST['config_content'] ?? '';
    if (trim($new) === '') {
    $errors[] = 'Configuration content is empty. Not saved.';
    } else {
    if (!is_writable(dirname($configPath)) && !is_writable($configPath)) {
    $errors[] = 'Config file or directory not writable.';
    } else {
    if (!backup_file($configPath)) {
    $errors[] = 'Could not create backup of config.php. Aborting.';
    } else {
    $written = @file_put_contents($configPath, $new);
    if ($written === false) {
    $errors[] = 'Failed to write config.php';
    } else {
    $success = 'Configuration updated successfully. Backup created.';
    $configContent = $new;
    }
    }
    }
    }
    }

    if (isset($_POST['update_core'])) {
    $new = $_POST['core_content'] ?? '';
    if (trim($new) === '') {
    $errors[] = 'Core content is empty. Not saved.';
    } else {
    if (!is_writable(dirname($corePath)) && !is_writable($corePath)) {
    $errors[] = 'Core file or directory not writable.';
    } else {
    if (!backup_file($corePath)) {
    $errors[] = 'Could not create backup of core.php. Aborting.';
    } else {
    $written = @file_put_contents($corePath, $new);
    if ($written === false) {
    $errors[] = 'Failed to write core.php';
    } else {
    $success = 'Core functions updated successfully. Backup created.';
    $coreContent = $new;
    }
    }
    }
    }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>textarea.form-control { font-family: monospace; }</style>
</head>
<body class="p-4">
  <div class="container">
    <h1>System Settings</h1>

    <?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul></div>
    <?php endif; ?>

    <div class="card mb-4">
    <div class="card-header">config.php</div>
    <div class="card-body">
    <form method="post" onsubmit="return confirm('Are you sure? This will overwrite config.php and create a backup.');">
    <div class="mb-3">
    <textarea name="config_content" class="form-control" rows="18"><?php echo htmlspecialchars($configContent); ?></textarea>
    </div>
    <button class="btn btn-primary" name="update_config" type="submit">Save config.php</button>
    </form>
    </div>
    </div>

    <div class="card mb-4">
    <div class="card-header">core.php</div>
    <div class="card-body">
    <form method="post" onsubmit="return confirm('Are you sure? This will overwrite core.php and create a backup.');">
    <div class="mb-3">
    <textarea name="core_content" class="form-control" rows="18"><?php echo htmlspecialchars($coreContent); ?></textarea>
    </div>
    <button class="btn btn-primary" name="update_core" type="submit">Save core.php</button>
    </form>
    </div>
    </div>

    <p class="text-muted small">
    Note: backups are created in the same directory with a timestamp suffix (e.g. config.php.20250905_123456.bak).
    Editing these files can break the application — test on your local WAMP and keep backups.
    </p>
  </div>
</body>
</html>