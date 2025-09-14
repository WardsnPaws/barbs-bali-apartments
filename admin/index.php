<?php
// admin/index.php

require_once __DIR__ . '/auth.php';

$view = $_GET['view'] ?? 'dashboard';
$validViews = ['dashboard','bookings','calendar','payments','settings','logout','booking-edit','delete-booking','email-log'];

if (!in_array($view, $validViews)) {
    $view = 'dashboard';
}

// Candidate view files (prefer views/<view>.php)
$viewFileCandidates = [
    __DIR__ . "/views/{$view}.php",
    __DIR__ . "/{$view}.php"
];

$viewFile = null;
foreach ($viewFileCandidates as $candidate) {
    if (file_exists($candidate)) { $viewFile = $candidate; break; }
}
$missingViewMessage = '';
if (!$viewFile) {
    $missingViewMessage = '<h2>View not found</h2><p>Requested view: ' . htmlspecialchars($view) . '</p><p>Checked: ' . htmlspecialchars(implode(', ', $viewFileCandidates)) . '</p>';
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Admin Console – Barbs Bali Apartments</title>
  <style>
    body { font-family: Arial; margin: 0; }
    header { background: #2e8b57; padding: 20px; color: white; }
    nav a { color: white; text-decoration: none; margin-right: 20px; font-weight: bold; }
    .content { padding: 30px; }
  </style>
</head>
<body>

  <header>
    <h1>Admin Console – Barbs Bali Apartments</h1>
    <nav>
      <a href="/index.html">🏠 Home</a>
      <a href="?view=dashboard">Dashboard</a>
      <a href="?view=bookings">Bookings</a>
      <a href="?view=calendar">Calendar</a>
      <a href="?view=payments">Payments</a>
      <a href="?view=settings">Settings</a>
      <a href="tools/index.php">Test Tools</a>
      <a href="logout.php">Logout</a>
    </nav>
  </header>

  <div class="content">
    <?php
    if ($viewFile) {
        include $viewFile;
    } else {
        // friendly debug message
        echo $missingViewMessage;
    }
    ?>
  </div>

</body>
</html>