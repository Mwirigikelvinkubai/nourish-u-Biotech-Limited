<?php
require_once __DIR__ . '/config/config.php';
require_login();

$u    = current_user();
$role = $u['role'];

/* ---- Common metrics ---- */
$today        = date('Y-m-d');
$monthStart   = date('Y-m-01');
$nextMonth    = date('Y-m-01', strtotime('first day of next month'));

if ($role === 'rep') {
    $repFilter = ' AND rep_id = ' . (int)$u['id'];
} else {
    $repFilter = '';
}

$mSales = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS s
                         FROM sales WHERE sale_date >= '$monthStart' AND sale_date < '$nextMonth' $repFilter")->fetch();

$openInvoices = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(total - paid_amount),0) AS s
                              FROM sales WHERE payment_status <> 'paid' $repFilter")->fetch();

$openFeedback = $pdo->query("SELECT COUNT(*) AS n FROM feedback WHERE status IN ('open','in_progress')"
                            . ($role === 'rep' ? " AND rep_id = " . (int)$u['id'] : ''))->fetch();

$expiringSoon = $pdo->query("SELECT COUNT(*) AS n FROM products
                             WHERE status='active' AND expiry_date IS NOT NULL
                               AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)")->fetch();

$lowStock = $pdo->query("SELECT COUNT(*) AS n FROM products
                          WHERE status='active' AND stock_qty <= reorder_level")->fetch();

$pendingKyc = $pdo->query("SELECT COUNT(*) AS n FROM clients WHERE kyc_status='pending'"
                          . ($role === 'rep' ? " AND rep_id = " . (int)$u['id'] : ''))->fetch();

$upcomingDrops = $pdo->query("SELECT sd.*, c.name AS client, u.name AS rep
                                FROM sample_drops sd
                                JOIN clients c ON c.id = sd.client_id
                                JOIN users   u ON u.id = sd.rep_id
                               WHERE sd.status IN ('scheduled','dropped')
                                 AND sd.scheduled_date >= CURDATE()"
                              . ($role === 'rep' ? " AND sd.rep_id = " . (int)$u['id'] : '')
                              . " ORDER BY sd.scheduled_date ASC LIMIT 6")->fetchAll();

$recentSales = $pdo->query("SELECT s.*, c.name AS client, u.name AS rep
                              FROM sales s
                              JOIN clients c ON c.id = s.client_id
                              JOIN users   u ON u.id = s.rep_id"
                            . ($role === 'rep' ? " WHERE s.rep_id = " . (int)$u['id'] : '')
                            . " ORDER BY s.sale_date DESC, s.id DESC LIMIT 6")->fetchAll();

$page_title = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-0">Welcome back, <?= e(explode(' ', $u['name'])[0]) ?> 👋</h3>
    <div class="text-muted small">Today is <?= e(date('l, j F Y')) ?></div>
  </div>
  <?php if ($role === 'rep'): ?>
    <div>
      <a class="btn btn-primary" href="<?= url('sales/add.php') ?>"><i class="bi bi-plus-circle"></i> New Sale</a>
      <a class="btn btn-outline-primary" href="<?= url('clients/add.php') ?>"><i class="bi bi-shop-window"></i> New Client</a>
    </div>
  <?php endif; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3 col-6">
    <div class="metric">
      <h6>Sales this month</h6>
      <div class="v"><?= money($mSales['s']) ?></div>
      <div class="sub"><?= (int)$mSales['n'] ?> invoice(s)</div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="metric">
      <h6>Outstanding receivables</h6>
      <div class="v"><?= money($openInvoices['s']) ?></div>
      <div class="sub"><?= (int)$openInvoices['n'] ?> unpaid</div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="metric">
      <h6>Open feedback</h6>
      <div class="v"><?= (int)$openFeedback['n'] ?></div>
      <div class="sub">complaints &amp; suggestions</div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="metric">
      <h6>Pending KYC</h6>
      <div class="v"><?= (int)$pendingKyc['n'] ?></div>
      <div class="sub">clients awaiting verification</div>
    </div>
  </div>

  <?php if (in_array($role, ['admin','accountant'], true)): ?>
  <div class="col-md-3 col-6">
    <div class="metric">
      <h6>Expiring within 90 days</h6>
      <div class="v text-warning"><?= (int)$expiringSoon['n'] ?></div>
      <div class="sub">products in stock</div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="metric">
      <h6>Low-stock items</h6>
      <div class="v text-danger"><?= (int)$lowStock['n'] ?></div>
      <div class="sub">at or below reorder level</div>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-receipt"></i> Recent sales</span>
        <a class="small" href="<?= url('sales/index.php') ?>">View all →</a>
      </div>
      <div class="table-responsive">
        <table class="table table-clean mb-0">
          <thead><tr><th>Invoice</th><th>Client</th><th>Rep</th><th>Date</th><th class="text-end">Total</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (!$recentSales): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No sales yet.</td></tr>
            <?php else: foreach ($recentSales as $s): ?>
              <tr>
                <td><a href="<?= url('sales/view.php?id=' . (int)$s['id']) ?>"><?= e($s['invoice_no']) ?></a></td>
                <td><?= e($s['client']) ?></td>
                <td><?= e($s['rep']) ?></td>
                <td><?= e(fdate($s['sale_date'])) ?></td>
                <td class="text-end"><?= money($s['total']) ?></td>
                <td>
                  <?php $cls = ['unpaid'=>'badge-danger','partial'=>'badge-warning','paid'=>'badge-soft'][$s['payment_status']] ?? 'badge-secondary'; ?>
                  <span class="badge <?= $cls ?>"><?= e(ucfirst($s['payment_status'])) ?></span>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-calendar-event"></i> Upcoming sample drops</span>
        <a class="small" href="<?= url('samples/index.php') ?>">View all →</a>
      </div>
      <ul class="list-group list-group-flush">
        <?php if (!$upcomingDrops): ?>
          <li class="list-group-item text-center text-muted py-4">Nothing scheduled.</li>
        <?php else: foreach ($upcomingDrops as $d): ?>
          <li class="list-group-item d-flex justify-content-between">
            <div>
              <div class="fw-semibold"><?= e($d['client']) ?></div>
              <div class="small text-muted">Rep: <?= e($d['rep']) ?> · <?= e(ucfirst($d['status'])) ?></div>
            </div>
            <div class="text-end small">
              <?= e(fdate($d['scheduled_date'])) ?>
            </div>
          </li>
        <?php endforeach; endif; ?>
      </ul>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
