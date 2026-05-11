<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) redirect(url('dashboard.php'));

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string)post('email'));
    $pass  = (string)post('password');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND (deleted_at IS NULL) LIMIT 1");
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
<title>Sign in - <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="<?= url('assets/img/favicon.png') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
<style>
  :root{
    --cyan:#3dc9d9; --purple:#594396; --purple-d:#3f2f6c; --ink:#1d2b27;
  }
  body { margin:0; min-height:100vh; background:#f0f3f5; font-family:'Segoe UI',Roboto,system-ui,sans-serif; color:var(--ink); }
  .auth-shell { display:flex; min-height:100vh; }

  /* LEFT: brand panel with a curved right edge */
  .auth-brand {
    flex: 1 1 55%;
    background: linear-gradient(135deg,var(--cyan) 0%, var(--purple) 100%);
    color:#fff;
    position:relative;
    display:flex; align-items:center; justify-content:center;
    overflow:hidden;
    clip-path: polygon(0 0, 100% 0, 88% 50%, 100% 100%, 0 100%);
  }
  .auth-brand .bubble { position:absolute; border-radius:50%; background:rgba(255,255,255,.08); }
  .auth-brand .b1 { width:260px; height:260px; top:-80px; left:-80px; }
  .auth-brand .b2 { width:160px; height:160px; bottom:-50px; right:8%; }
  .auth-brand .b3 { width:90px;  height:90px;  top:40%; left:65%; background:rgba(255,255,255,.05); }
  .brand-content { text-align:center; padding: 24px 36px; z-index:1; max-width: 460px; }
  .brand-content img { max-width: 230px; filter: drop-shadow(0 6px 18px rgba(0,0,0,.25)); }
  .brand-content h1 { color:#fff; font-weight:700; font-size: 1.8rem; margin: 1rem 0 .35rem; letter-spacing:.02em; }
  .brand-content .tag { color:#e7feff; font-size:.78rem; letter-spacing:.22em; text-transform:uppercase; }
  .brand-content .lede { color:rgba(255,255,255,.85); font-size:.95rem; margin-top:1.5rem; line-height:1.55; }

  /* RIGHT: sign-in form */
  .auth-form {
    flex: 1 1 45%;
    display:flex; align-items:center; justify-content:center;
    background:#ffffff;
    padding: 2rem;
  }
  .form-card { width: 100%; max-width: 380px; }
  .form-card h2 { color: var(--purple-d); font-weight:700; margin-bottom:.25rem; }
  .form-card .muted { color:#6b7572; font-size:.9rem; margin-bottom:1.4rem; }
  .form-card .form-floating > label { color:#6b7572; }
  .form-card .input-icon { position:relative; }
  .form-card .input-icon i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#999; font-size:1rem; pointer-events:none; }
  .form-card .input-icon input { padding-left: 2.4rem; height: 44px; border-radius: 22px; border: 1px solid #e0e4e6; }
  .form-card .input-icon input:focus { border-color: var(--cyan); box-shadow: 0 0 0 0.18rem rgba(61,201,217,.18); }
  .form-card .btn-signin {
    width:100%; height: 44px; border-radius: 22px; border:0;
    background: linear-gradient(135deg,var(--cyan) 0%, var(--purple) 100%);
    color:#fff; font-weight:600; letter-spacing:.04em; text-transform:uppercase; font-size:.85rem;
  }
  .form-card .btn-signin:hover { filter: brightness(1.08); }
  .form-card .small-foot { font-size:.75rem; color:#6b7572; margin-top:1.4rem; text-align:center; }
  .form-card .small-foot a { color: var(--purple); }

  @media (max-width: 900px) {
    .auth-shell { flex-direction: column; }
    .auth-brand { flex: 0 0 240px; clip-path: polygon(0 0, 100% 0, 100% 86%, 50% 100%, 0 86%); }
    .auth-form  { flex: 1 1 auto; padding: 1.5rem; }
    .brand-content img { max-width: 110px; }
    .brand-content h1 { font-size: 1.2rem; }
    .brand-content .lede { display:none; }
  }
</style>
</head>
<body>

<div class="auth-shell">
  <section class="auth-brand">
    <div class="bubble b1"></div>
    <div class="bubble b2"></div>
    <div class="bubble b3"></div>
    <div class="brand-content">
      <img src="<?= url('assets/img/logo_login.png') ?>" alt="Nourish U Biotech Limited">
      <h1>Nourish U Biotech</h1>
      <div class="tag">Your Partner in Natural Wellness</div>
      <p class="lede">
        Med Distribution Management System &mdash; clients, KYC, sales,
        commissions and sample drops, all in one place.
      </p>
    </div>
  </section>

  <section class="auth-form">
    <div class="form-card">
      <h2>Welcome back</h2>
      <div class="muted">Sign in to continue to your dashboard.</div>

      <?php if ($err): ?>
        <div class="alert alert-danger py-2 small"><?= e($err) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="on">
        <?= csrf_field() ?>
        <div class="input-icon mb-3">
          <i class="bi bi-envelope"></i>
          <input class="form-control" name="email" type="email" required autofocus
                 value="<?= e((string)post('email')) ?>">
        </div>
        <div class="input-icon mb-3">
          <i class="bi bi-lock"></i>
          <input class="form-control" name="password" type="password" required>
        </div>
        <button class="btn-signin">Sign in</button>
      </form>

      <div class="small-foot">
        &copy; <?= date('Y') ?> Nourish U Biotech Limited &middot; v<?= e(APP_VERSION) ?>
        <br>
        Software by <a href="#" target="_blank">Kimiru Ventures</a>
      </div>
    </div>
  </section>
</div>

</body>
</html>
