<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) redirect(url('dashboard.php'));

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string)post('email'));
    $pass  = (string)post('password');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['status'] === 'active' && password_verify($pass, $user['password_hash'])) {
        login_user($user);
        $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
        audit($pdo, 'login', 'user', (int)$user['id']);
        redirect(url('dashboard.php'));
    } else {
        $err = 'Invalid email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in – <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="<?= url('assets/img/favicon.png') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="nu-splash">

<div class="brand text-center pt-5">
  <img src="<?= url('assets/img/logo_login.png') ?>" alt="Nourish U Biotech Limited">
  <h1>Nourish U Biotech Limited</h1>
  <div class="tag">Your Partner in Natural Wellness</div>
</div>

<div class="card login-card shadow-lg">
  <div class="card-body p-4">
    <h5 class="card-title mb-1">Sign in</h5>
    <div class="text-muted small mb-3">Med Distribution Management System</div>

    <?php if ($err): ?>
      <div class="alert alert-danger py-2"><?= e($err) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input class="form-control" name="email" type="email" required autofocus
               value="<?= e((string)post('email')) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input class="form-control" name="password" type="password" required>
      </div>
      <button class="btn btn-primary w-100">Sign in</button>
    </form>
  </div>
</div>

<div class="text-center text-white-50 small pb-3">
  &copy; <?= date('Y') ?> Nourish U Biotech Limited &middot; v<?= e(APP_VERSION) ?>
</div>

</body>
</html>
