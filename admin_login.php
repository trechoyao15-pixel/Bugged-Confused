<?php
session_start();

// Hardcoded admin credentials
define('ADMIN_EMAIL', 'admin@gmail.com');
define('ADMIN_PASSWORD', 'admin');

// Already logged in → go straight to dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please fill in both fields.';
    } elseif ($email !== ADMIN_EMAIL || $password !== ADMIN_PASSWORD) {
        $error = 'Invalid email or password.';
    } else {
        // Success → set session
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_email']     = ADMIN_EMAIL;
        $_SESSION['admin_username']  = 'Admin';
        $_SESSION['admin_id']        = 1;
        $_SESSION['admin_csrf']      = bin2hex(random_bytes(24));

        header('Location: admin_dashboard.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Login — LTMS</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Poppins', system-ui, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #1e3a8a 0%, #3b5fc0 50%, #4B6EF6 100%);
      padding: 20px;
    }
    .login-card {
      background: #fff;
      border-radius: 20px;
      padding: 48px 40px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 24px 60px rgba(0,0,0,0.18);
    }
    .login-logo { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
    .login-logo span { font-size:22px; font-weight:800; color:#07103a; }
    .login-card h1 { font-size:26px; font-weight:800; color:#07103a; margin-bottom:4px; }
    .login-card p.sub { color:#6b7280; font-size:14px; margin-bottom:28px; }
    .admin-badge {
      display:inline-flex; align-items:center; gap:6px;
      background:rgba(75,110,246,0.08); color:#4B6EF6;
      font-size:12px; font-weight:700; padding:4px 10px;
      border-radius:999px; margin-bottom:16px;
    }
    .form-group { margin-bottom:18px; }
    .form-group label { display:block; font-weight:600; font-size:14px; color:#374151; margin-bottom:6px; }
    .input-wrap { position:relative; }
    .input-wrap i {
      position:absolute; left:14px; top:50%;
      transform:translateY(-50%);
      font-size:18px; color:#9ca3af; pointer-events:none;
    }
    .input-wrap input {
      width:100%; padding:12px 14px 12px 42px;
      border:1.5px solid #e5e7eb; border-radius:10px;
      font-size:15px; font-family:inherit; color:#111827;
      outline:none; transition:border-color .15s;
    }
    .input-wrap input:focus {
      border-color:#4B6EF6;
      box-shadow:0 0 0 3px rgba(75,110,246,0.12);
    }
    .error-box {
      background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;
      border-radius:10px; padding:12px 14px; font-size:14px;
      margin-bottom:18px; display:flex; align-items:center; gap:8px; font-weight:600;
    }
    .btn-login {
      width:100%; padding:13px;
      background:linear-gradient(90deg,#4B6EF6,#7B9CFF);
      color:#fff; border:none; border-radius:10px;
      font-size:16px; font-weight:700; font-family:inherit;
      cursor:pointer; box-shadow:0 6px 20px rgba(75,110,246,0.25);
      display:flex; align-items:center; justify-content:center; gap:8px;
      transition:opacity .15s;
    }
    .btn-login:hover { opacity:.9; }
    .back-link {
      display:flex; align-items:center; gap:6px; margin-top:20px;
      color:#6b7280; font-size:13px; font-weight:600;
      text-decoration:none; justify-content:center;
    }
    .back-link:hover { color:#4B6EF6; }
  </style>
</head>
<body>
  <div class="login-card">

    <div class="login-logo">
      <svg width="36" height="36" viewBox="0 0 24 24">
        <path fill="#4B6EF6" d="M12 2C8 2 4 4 4 8v8c0 4 4 6 8 6s8-2 8-6V8c0-4-4-6-8-6zm0 2c2.8 0 5 1.2 5 4v8c0 2.8-2.2 4-5 4s-5-1.2-5-4V8c0-2.8 2.2-4 5-4z"/>
        <circle cx="12" cy="12" r="2.5" fill="#fff"/>
      </svg>
      <span>LTMS</span>
    </div>

    <div class="admin-badge">
      <i class='bx bx-shield-quarter'></i> Admin Portal
    </div>

    <h1>Welcome back</h1>
    <p class="sub">Sign in to manage lost &amp; found items and claims.</p>

    <?php if ($error !== ''): ?>
      <div class="error-box">
        <i class='bx bx-error-circle'></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- action="" submits to the same file; avoids any filename case issues -->
    <form method="POST" action="">
      <div class="form-group">
        <label for="email">Email Address</label>
        <div class="input-wrap">
          <i class='bx bx-envelope'></i>
          <input id="email" name="email" type="email"
                 placeholder="admin@gmail.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 required autocomplete="email">
        </div>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap">
          <i class='bx bx-lock-alt'></i>
          <input id="password" name="password" type="password"
                 placeholder="••••••••"
                 required autocomplete="current-password">
        </div>
      </div>

      <button class="btn-login" type="submit">
        <i class='bx bx-log-in'></i> Sign In
      </button>
    </form>

    <a class="back-link" href="index.php">
      <i class='bx bx-arrow-back'></i> Back to main site
    </a>

  </div>
</body>
</html>
<?php ob_end_flush(); ?>