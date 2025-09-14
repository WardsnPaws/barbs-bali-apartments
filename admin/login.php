<?php
session_start();
require_once '../core.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Admin Login – Barbs Bali Apartments</title>
  <style>
    body { font-family: Arial; background: #f0f0f0; padding: 60px; text-align: center; }
    form { display: inline-block; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    input { display: block; width: 250px; padding: 10px; margin-bottom: 15px; }
    button { padding: 10px 20px; }
    .error { color: red; margin-bottom: 15px; }
  </style>
</head>
<body>

  <form method="POST">
    <h2>Admin Login</h2>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <input type="text" name="username" placeholder="Username" required />
    <input type="password" name="password" placeholder="Password" required />
    <button type="submit">Login</button>
  </form>

</body>
</html>
