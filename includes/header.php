<?php
/** Common HTML head + top navbar. Expects $page_title to be optionally set. */
$page_title = $page_title ?? '';
$u = current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#2a8fa8">
  <title><?= e($page_title ? "$page_title - " . APP_NAME : APP_NAME) ?></title>

  <link rel="icon" type="image/png" href="<?= url('assets/img/favicon.png') ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>

<nav class="navbar navbar-nu navbar-expand-lg sticky-top no-print">
  <div class="container-fluid">
    <?php if ($u): ?>
      <!-- Mobile menu trigger -->
      <button class="btn btn-sm btn-outline-light d-lg-none me-2" type="button"
              data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
        <i class="bi bi-list"></i>
      </button>
    <?php endif; ?>

    <a class="navbar-brand d-flex align-items-center" href="<?= url('dashboard.php') ?>">
      <img src="<?= url('assets/img/icon.png') ?>" alt="Nourish U">
      <span class="brand-name d-none d-md-inline">Nourish U Biotech</span>
      <span class="brand-tag  d-none d-xl-inline">- Med Distribution</span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto"></ul>
      <?php if ($u): ?>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button">
            <i class="bi bi-person-circle"></i> <?= e($u['name']) ?>
            <span class="badge bg-light text-dark ms-1 d-none d-sm-inline"><?= e(role_label($u['role'])) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="bi bi-person"></i> My Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Sign out</a></li>
          </ul>
        </li>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</nav>

<?php if ($u): ?>
<!-- Off-canvas sidebar for mobile -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar">
  <div class="offcanvas-header bg-grad text-white">
    <h5 class="offcanvas-title"><img src="<?= url('assets/img/icon.png') ?>" style="height:24px;margin-right:6px;"> Menu</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body p-0">
    <?php include __DIR__ . '/sidebar.php'; $_NU_SIDEBAR_RENDERED = true; ?>
  </div>
</div>
<?php endif; ?>

<div class="container-fluid">
  <div class="row">
    <?php if ($u): ?>
      <div class="d-none d-lg-block col-lg-2 px-0">
        <?php include __DIR__ . '/sidebar.php'; ?>
      </div>
    <?php endif; ?>
    <main class="<?= $u ? 'col-lg-10' : 'col-12' ?> px-3 px-md-4 py-3">
      <?= flash_render() ?>
