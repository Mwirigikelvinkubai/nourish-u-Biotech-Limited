<?php
/**
 * Printable / Save-as-PDF version of the reports page.
 * Re-runs the same queries as reports/index.php and renders a clean
 * letterhead-style document, then auto-opens the browser print dialog.
 */
require_once __DIR__ . '/../config/config.php';
require_role(['admin','accountant']);

$from = (string)(get('from') ?: date('Y-m-01', strtotime('-2 months')));
$to   = (string)(get('to')   ?: date('Y-m-d'));
$grp  = (string)(get('grp')  ?: 'month');
$soft = "s.deleted_at IS NULL AND s.sale_date BETWEEN ? AND ?";

$byRep = $pdo->prepare(
    "SELECT u.name AS rep, COUNT(DISTINCT s.id) AS invoices,
            SUM(s.subtotal) AS subtotal, SUM(s.total) AS total, SUM(s.paid_amount) AS paid
       FROM sales s JOIN users u ON u.id = s.rep_id
      WHERE $soft GROUP BY s.rep_id ORDER BY total DESC"
);
$byRep->execute([$from,$to]); $byRep = $byRep->fetchAll();

$byProduct = $pdo->prepare(
    "SELECT p.sku, p.name, SUM(si.qty) AS qty, SUM(si.line_total) AS total
       FROM sale_items si JOIN sales s ON s.id=si.sale_id JOIN products p ON p.id=si.product_id
      WHERE $soft GROUP BY p.id ORDER BY total DESC LIMIT 25"
);
$byProduct->execute([$from,$to]); $byProduct = $byProduct->fetchAll();

$byClient = $pdo->prepare(
    "SELECT c.name, c.kyc_status, COUNT(s.id) AS invoices,
            SUM(s.total) AS total, SUM(s.total - s.paid_amount) AS balance
       FROM sales s JOIN clients c ON c.id=s.client_id
      WHERE $soft GROUP BY c.id ORDER BY total DESC LIMIT 20"
);
$byClient->execute([$from,$to]); $byClient = $byClient->fetchAll();

$byRegion = $pdo->prepare(
    "SELECT COALESCE(NULLIF(c.region,''),'Unassigned') AS region,
            COUNT(s.id) AS invoices, SUM(s.total) AS total
       FROM sales s JOIN clients c ON c.id=s.client_id
      WHERE $soft GROUP BY region ORDER BY total DESC"
);
$byRegion->execute([$from,$to]); $byRegion = $byRegion->fetchAll();

$expByRep = $pdo->prepare(
    "SELECT u.name, SUM(e.amount) AS total,
            SUM(CASE WHEN e.status='pending' THEN e.amount ELSE 0 END) AS pending,
            SUM(CASE WHEN e.status='paid'    THEN e.amount ELSE 0 END) AS paid
       FROM expenses e JOIN users u ON u.id=e.rep_id
      WHERE e.deleted_at IS NULL AND e.spent_on BETWEEN ? AND ?
      GROUP BY u.id ORDER BY total DESC"
);
$expByRep->execute([$from,$to]); $expByRep = $expByRep->fetchAll();

$grandSales = array_sum(array_map(fn($r)=>(float)$r['total'], $byRep));
$grandPaid  = array_sum(array_map(fn($r)=>(float)$r['paid'],  $byRep));
$grandExp   = array_sum(array_map(fn($r)=>(float)$r['total'], $expByRep));

$company  = setting($pdo,'company_name',    'Nourish U Biotech Limited');
$tagline  = setting($pdo,'company_tagline', 'Your Partner in Natural Wellness');
$cAddr    = setting($pdo,'company_address', '');
$cPhone   = setting($pdo,'company_phone',   '');
$cEmail   = setting($pdo,'company_email',   '');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Report <?= e($from) ?> - <?= e($to) ?> - <?= e($company) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
<style>
  body { background:#f1f3f4; }
  .printable h2 { color:#594396; }
  .printable .lh-head img { height:64px; }
  .printable .lh-head .co-name { color:#594396; font-weight:700; font-size:1.5rem; margin:0; }
  .printable .lh-head .co-tag  { color:#2a8fa8; letter-spacing:.16em; font-size:.7rem; text-transform:uppercase; }
  .printable table th { background:#eaf6ff; color:#594396; font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; }
  .printable table td, .printable table th { padding:.4rem .55rem; }
  .section-title { color:#594396; border-bottom:2px solid #3dc9d9; padding-bottom:4px; margin-top:1.6rem; }
  .summary-card { background:#fafcff; border:1px solid #e3eaf3; border-radius:8px; padding:10px 14px; }
  .summary-card .lab { font-size:.7rem; color:#6b7572; text-transform:uppercase; letter-spacing:.06em; }
  .summary-card .val { font-size:1.25rem; font-weight:700; color:#1d2b27; }
  @media print { body{background:#fff;} .no-print{display:none!important;} .printable{box-shadow:none;border:0;padding:0;} }
</style>
</head>
<body>

<div class="printable">
  <div class="lh-bar"></div>

  <div class="lh-head d-flex align-items-center mb-3">
    <img src="<?= url('assets/img/logo_full.png') ?>" alt="" class="me-3">
    <div class="flex-grow-1">
      <p class="co-name"><?= e($company) ?></p>
      <div class="co-tag"><?= e($tagline) ?></div>
      <div class="small text-muted mt-1"><?= e($cAddr) ?> &middot; <?= e($cPhone) ?> &middot; <?= e($cEmail) ?></div>
    </div>
    <div class="text-end">
      <h2 class="mb-0">SALES REPORT</h2>
      <div class="small text-muted">Period: <strong><?= e(fdate($from)) ?></strong> &mdash; <strong><?= e(fdate($to)) ?></strong></div>
      <div class="small text-muted">Generated <?= e(date('d M Y, H:i')) ?></div>
    </div>
  </div>

  <div class="row g-2 mb-3">
    <div class="col-4"><div class="summary-card"><div class="lab">Total invoiced</div><div class="val"><?= money($grandSales) ?></div></div></div>
    <div class="col-4"><div class="summary-card"><div class="lab">Cash collected</div><div class="val"><?= money($grandPaid) ?></div></div></div>
    <div class="col-4"><div class="summary-card"><div class="lab">Rep expenses</div><div class="val"><?= money($grandExp) ?></div></div></div>
  </div>

  <h5 class="section-title">Sales by med rep</h5>
  <table class="table table-sm">
    <thead><tr><th>Rep</th><th class="text-end">Invoices</th>
      <th class="text-end">Subtotal</th><th class="text-end">Total</th><th class="text-end">Paid</th></tr></thead>
    <tbody>
    <?php foreach ($byRep as $r): ?>
      <tr><td><?= e($r['rep']) ?></td>
          <td class="text-end"><?= (int)$r['invoices'] ?></td>
          <td class="text-end"><?= money($r['subtotal']) ?></td>
          <td class="text-end fw-bold"><?= money($r['total']) ?></td>
          <td class="text-end"><?= money($r['paid']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h5 class="section-title">Sales by region</h5>
  <table class="table table-sm">
    <thead><tr><th>Region</th><th class="text-end">Invoices</th><th class="text-end">Total</th></tr></thead>
    <tbody>
    <?php foreach ($byRegion as $r): ?>
      <tr><td><?= e($r['region']) ?></td>
          <td class="text-end"><?= (int)$r['invoices'] ?></td>
          <td class="text-end fw-bold"><?= money($r['total']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h5 class="section-title">Top products</h5>
  <table class="table table-sm">
    <thead><tr><th>SKU</th><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
    <tbody>
    <?php foreach ($byProduct as $p): ?>
      <tr><td class="font-monospace small"><?= e($p['sku']) ?></td>
          <td><?= e($p['name']) ?></td>
          <td class="text-end"><?= (int)$p['qty'] ?></td>
          <td class="text-end fw-bold"><?= money($p['total']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h5 class="section-title">Top clients</h5>
  <table class="table table-sm">
    <thead><tr><th>Client</th><th>KYC</th><th class="text-end">Invoices</th>
      <th class="text-end">Total</th><th class="text-end">Balance</th></tr></thead>
    <tbody>
    <?php foreach ($byClient as $c): ?>
      <tr><td><?= e($c['name']) ?></td>
          <td><?= e(ucfirst($c['kyc_status'])) ?></td>
          <td class="text-end"><?= (int)$c['invoices'] ?></td>
          <td class="text-end fw-bold"><?= money($c['total']) ?></td>
          <td class="text-end"><?= money($c['balance']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h5 class="section-title">Rep expenses</h5>
  <table class="table table-sm">
    <thead><tr><th>Rep</th><th class="text-end">Total logged</th>
      <th class="text-end">Pending</th><th class="text-end">Paid</th></tr></thead>
    <tbody>
    <?php foreach ($expByRep as $r): ?>
      <tr><td><?= e($r['name']) ?></td>
          <td class="text-end fw-bold"><?= money($r['total']) ?></td>
          <td class="text-end"><?= money($r['pending']) ?></td>
          <td class="text-end"><?= money($r['paid']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="lh-foot">
    <strong><?= e($company) ?></strong> &middot; <?= e($cAddr) ?> &middot; Tel: <?= e($cPhone) ?>
  </div>
</div>

<div class="no-print text-center my-3">
  <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save as PDF</button>
  <a class="btn btn-outline-secondary" href="<?= url('reports/index.php?from=' . urlencode($from) . '&to=' . urlencode($to) . '&grp=' . urlencode($grp)) ?>">Back</a>
</div>

<script>
// auto-open print dialog after assets settle (skip if user came back via Back)
window.addEventListener('load', () => setTimeout(() => window.print(), 600));
</script>

</body>
</html>
