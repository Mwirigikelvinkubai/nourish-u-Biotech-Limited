<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$id = (int)get('id');
$stmt = $pdo->prepare("SELECT s.*, c.name AS client, c.address, c.postal_address, c.phone AS cphone,
                              c.email AS cemail, c.kra_pin, u.name AS rep, c.payment_terms, c.credit_period_days
                         FROM sales s
                         JOIN clients c ON c.id = s.client_id
                         JOIN users   u ON u.id = s.rep_id
                        WHERE s.id = ?");
$stmt->execute([$id]); $s = $stmt->fetch();
if (!$s) { http_response_code(404); die('Sale not found.'); }
if ($u['role'] === 'rep' && (int)$s['rep_id'] !== (int)$u['id']) { http_response_code(403); die('Not allowed.'); }

$items = $pdo->prepare("SELECT si.*, p.sku, p.name FROM sale_items si JOIN products p ON p.id=si.product_id WHERE sale_id=?");
$items->execute([$id]); $items = $items->fetchAll();

$company  = setting($pdo,'company_name',    'Nourish U Biotech Limited');
$tagline  = setting($pdo,'company_tagline', 'Your Partner in Natural Wellness');
$cAddr    = setting($pdo,'company_address', '');
$cPhone   = setting($pdo,'company_phone',   '');
$cEmail   = setting($pdo,'company_email',   '');

$bankName = setting($pdo,'bank_name',         '');
$bankBr   = setting($pdo,'bank_branch',       '');
$bankAcN  = setting($pdo,'bank_account_name', '');
$bankKes  = setting($pdo,'bank_account_kes',  '');
$bankUsd  = setting($pdo,'bank_account_usd',  '');
$bankSw   = setting($pdo,'bank_swift',        '');
$mPayBill = setting($pdo,'mpesa_paybill',     '');
$mAcct    = setting($pdo,'mpesa_account',     '');

$dueDate  = $s['credit_period_days']
    ? date('Y-m-d', strtotime($s['sale_date'] . ' +' . (int)$s['credit_period_days'] . ' days'))
    : null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice <?= e($s['invoice_no']) ?> - <?= e($company) ?></title>
<link rel="icon" type="image/png" href="<?= url('assets/img/favicon.png') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
<style>
  body{ background:#f1f3f4; }
  .totals th{font-weight:600;}
  .lh-head img{ height: 70px; }
  .lh-head .co-name { font-size: 1.6rem; font-weight: 700; color: #594396; margin: 0; }
  .lh-head .co-tag  { font-size: .78rem; letter-spacing: .14em; color: #2a8fa8; text-transform: uppercase; }
  .lh-head .meta    { font-size: .8rem; color: #555; }
  .invoice-title    { background: linear-gradient(135deg,#3dc9d9 0%,#594396 100%); color:#fff; padding: 8px 14px; border-radius: 4px; font-weight: 700; letter-spacing:.05em; display: inline-block; }
  table.lines thead th { background: #eaf6ff; color: #594396; font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; }
  .pay-block { background: #fafcff; border: 1px solid #e3eaf3; border-radius: 8px; padding: 12px 16px; }
  .pay-block h6 { color: #594396; margin: 0 0 6px; }
  .pay-block dl { display: grid; grid-template-columns: 130px 1fr; row-gap: 4px; column-gap: 10px; margin: 0; font-size: .8rem; }
  .pay-block dt { color: #555; font-weight: 500; }
  .pay-block dd { margin: 0; font-weight: 600; color: #1d2b27; }
  @media print{
    body{ background:#fff; }
    .printable{ box-shadow:none; border:0; padding:0; }
    .no-print{ display:none!important; }
  }
</style>
</head>
<body>

<div class="printable">
  <div class="lh-bar"></div>

  <div class="lh-head d-flex align-items-center mb-4">
    <img src="<?= url('assets/img/logo_full.png') ?>" alt="Nourish U Biotech" class="me-3">
    <div class="flex-grow-1">
      <p class="co-name"><?= e($company) ?></p>
      <div class="co-tag"><?= e($tagline) ?></div>
      <div class="meta mt-1"><?= e($cAddr) ?> &middot; Tel: <?= e($cPhone) ?> &middot; <?= e($cEmail) ?></div>
    </div>
    <div class="text-end">
      <span class="invoice-title">INVOICE</span>
      <div class="mt-2 small"><strong><?= e($s['invoice_no']) ?></strong></div>
      <div class="small text-muted">Issued <?= e(fdate($s['sale_date'])) ?></div>
      <?php if ($dueDate): ?>
        <div class="small text-muted">Due <?= e(fdate($dueDate)) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-6">
      <div class="text-muted small text-uppercase">Bill to</div>
      <div class="fw-semibold"><?= e($s['client']) ?></div>
      <div class="small text-muted"><?= e($s['address']) ?></div>
      <?php if (!empty($s['postal_address'])): ?>
        <div class="small text-muted"><?= e($s['postal_address']) ?></div>
      <?php endif; ?>
      <div class="small text-muted">Tel: <?= e($s['cphone']) ?></div>
      <?php if ($s['kra_pin']): ?>
        <div class="small text-muted">KRA PIN: <?= e($s['kra_pin']) ?></div>
      <?php endif; ?>
    </div>
    <div class="col-6 text-end">
      <div class="text-muted small text-uppercase">Med Rep</div>
      <div class="fw-semibold"><?= e($s['rep']) ?></div>
      <div class="small">Status:
        <?php $cls = ['unpaid'=>'badge-danger','partial'=>'badge-warning','paid'=>'badge-soft'][$s['payment_status']] ?? 'badge-secondary'; ?>
        <span class="badge <?= $cls ?>"><?= e(ucfirst($s['payment_status'])) ?></span>
      </div>
      <?php if (!empty($s['payment_terms'])): ?>
        <div class="small text-muted">Terms: <?= e($s['payment_terms']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <table class="table table-sm table-bordered lines">
    <thead>
      <tr><th style="width:30px">#</th><th>SKU</th><th>Description</th>
          <th class="text-end">Qty</th>
          <th class="text-end">Unit price</th><th class="text-end">Line total</th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $i => $it): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td class="font-monospace small"><?= e($it['sku']) ?></td>
        <td><?= e($it['name']) ?></td>
        <td class="text-end"><?= (int)$it['qty'] ?></td>
        <td class="text-end"><?= money($it['unit_price']) ?></td>
        <td class="text-end"><?= money($it['line_total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="row">
    <div class="col-7">
      <?php if (!empty($s['notes'])): ?>
        <div class="text-muted small text-uppercase">Notes</div>
        <p class="small"><?= nl2br(e($s['notes'])) ?></p>
      <?php endif; ?>
      <p class="small mb-0"><strong>Payment method:</strong> <?= e($s['payment_method'] ?: '-') ?></p>
    </div>
    <div class="col-5">
      <table class="table table-sm totals">
        <tr><th>Subtotal</th><td class="text-end"><?= money($s['subtotal']) ?></td></tr>
        <tr><th>Discount</th><td class="text-end">-<?= money($s['discount']) ?></td></tr>
        <tr><th>VAT (<?= e($s['tax_pct']) ?>%)</th><td class="text-end"><?= money($s['tax_amount']) ?></td></tr>
        <tr><th>Total</th><td class="text-end fw-bold"><?= money($s['total']) ?></td></tr>
        <tr><th>Paid</th><td class="text-end"><?= money($s['paid_amount']) ?></td></tr>
        <tr><th class="text-success">Balance due</th><td class="text-end fw-bold"><?= money($s['total'] - $s['paid_amount']) ?></td></tr>
      </table>
    </div>
  </div>

  <div class="row g-3 mt-2">
    <?php if ($bankName): ?>
    <div class="col-md-7">
      <div class="pay-block h-100">
        <h6><i class="bi bi-bank"></i> Bank payment</h6>
        <dl>
          <dt>Bank</dt><dd><?= e($bankName) ?><?= $bankBr ? ' &middot; ' . e($bankBr) : '' ?></dd>
          <dt>Account name</dt><dd><?= e($bankAcN) ?></dd>
          <?php if ($bankKes): ?><dt>A/C (KES)</dt><dd><?= e($bankKes) ?></dd><?php endif; ?>
          <?php if ($bankUsd): ?><dt>A/C (USD)</dt><dd><?= e($bankUsd) ?></dd><?php endif; ?>
          <?php if ($bankSw):  ?><dt>SWIFT</dt><dd><?= e($bankSw) ?></dd><?php endif; ?>
        </dl>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($mPayBill): ?>
    <div class="col-md-5">
      <div class="pay-block h-100">
        <h6><i class="bi bi-phone"></i> M-Pesa Pay Bill</h6>
        <dl>
          <dt>Pay Bill</dt><dd><?= e($mPayBill) ?></dd>
          <dt>Account #</dt><dd><?= e($mAcct) ?></dd>
        </dl>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="lh-foot">
    <div class="d-flex justify-content-between flex-wrap">
      <div><strong><?= e($company) ?></strong> &middot; <?= e($cAddr) ?></div>
      <div>Tel: <?= e($cPhone) ?> &middot; <?= e($cEmail) ?></div>
    </div>
    <div class="text-center mt-2">
      Thank you for your business. Goods once sold are governed by the agreed terms in the Account Opening Form.
    </div>
  </div>
</div>

<div class="no-print text-center my-3">
  <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save as PDF</button>
  <a class="btn btn-outline-secondary" href="<?= url('sales/view.php?id=' . $id) ?>">Back</a>
</div>

</body>
</html>
