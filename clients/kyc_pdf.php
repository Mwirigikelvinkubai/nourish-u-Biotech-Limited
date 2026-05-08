<?php
/**
 * Pre-filled Supplier KYC Profile / Account Opening Form
 * (HTML printable — use the browser's Print → Save as PDF for a real PDF.)
 */
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$id = (int)get('id');
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { http_response_code(404); die('Client not found.'); }
if ($u['role'] === 'rep' && (int)$c['rep_id'] !== (int)$u['id']) {
    http_response_code(403); die('Not allowed.');
}

$company  = setting($pdo,'company_name',    'Nourish U Biotech Limited');
$tagline  = setting($pdo,'company_tagline', 'Your Partner in Natural Wellness');
$cAddr    = setting($pdo,'company_address', '');
$cPhone   = setting($pdo,'company_phone',   '');
$cEmail   = setting($pdo,'company_email',   '');

/** Render a "filled blank line" with the value (or blank line). */
function fillLine(string $label, ?string $value, int $cols = 60): string {
    $val = trim((string)$value);
    if ($val === '') {
        return '<span class="line">&nbsp;</span>';
    }
    return '<span class="filled">' . htmlspecialchars($val, ENT_QUOTES) . '</span>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Supplier KYC – <?= e($c['name']) ?></title>
<link rel="icon" type="image/png" href="<?= url('assets/img/favicon.png') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
<style>
  body { background:#f1f3f4; font-family:'Segoe UI',Roboto,system-ui,sans-serif; }
  .printable h2 { color: #594396; font-weight: 700; margin: 0; }
  .printable h5 { color: #594396; margin-top: 1.2rem; margin-bottom: .5rem; font-size: 1rem; }
  .field-row    { padding: 4px 0; border-bottom: 1px dotted #ccd; line-height: 1.6; }
  .field-row    > strong { display: inline-block; min-width: 230px; color: #555; font-weight: 600; }
  .filled       { color: #1d2b27; font-weight: 600; }
  .line         { display: inline-block; min-width: 250px; border-bottom: 1px dotted #888; }
  ol.refs li    { margin-bottom: .9rem; }
  .terms        { background:#fafcff; border:1px solid #e3eaf3; border-radius:8px; padding:14px 18px; font-size:.84rem; }
  .terms ol     { margin-bottom: 0; }
  .signbox      { border:1px solid #c3c8cf; border-radius:6px; padding: 12px 16px; min-height:80px; }
  .signbox .lab { font-size:.7rem; color:#6b7572; text-transform:uppercase; letter-spacing:.04em; }
  .lh-head img { height: 80px; }
  .co-name     { font-size: 1.55rem; font-weight: 700; color: #594396; margin: 0; }
  .co-tag      { font-size: .76rem; letter-spacing: .14em; color: #2a8fa8; text-transform: uppercase; }
  @media print {
    body{ background:#fff; }
    .printable{ box-shadow:none; border:0; padding:0; }
    .no-print{ display:none!important; }
    .page-break{ page-break-before: always; }
  }
</style>
</head>
<body>

<div class="printable">
  <div class="lh-bar"></div>

  <!-- Letterhead -->
  <div class="lh-head d-flex align-items-center mb-3">
    <img src="<?= url('assets/img/logo_full.png') ?>" alt="Nourish U Biotech" class="me-3">
    <div class="flex-grow-1">
      <p class="co-name"><?= e($company) ?></p>
      <div class="co-tag"><?= e($tagline) ?></div>
      <div class="small text-muted mt-1"><?= e($cAddr) ?></div>
      <div class="small text-muted">Tel: <?= e($cPhone) ?> &middot; <?= e($cEmail) ?></div>
    </div>
  </div>

  <h2 class="text-center mb-1">SUPPLIER KYC PROFILE</h2>
  <p class="text-center small text-muted mb-4">Account Opening Form</p>

  <p>I/we wish to open an account with you. The following are my/our details:</p>

  <div class="field-row"><strong>1. Name of the Company</strong>: <?= fillLine('name', $c['name']) ?></div>
  <div class="field-row"><strong>2. Postal Address</strong>: <?= fillLine('post', $c['postal_address']) ?></div>
  <div class="field-row"><strong>3. Physical Address</strong>: <?= fillLine('addr', $c['address']) ?></div>
  <div class="field-row"><strong>4. Email</strong>: <?= fillLine('email', $c['email']) ?></div>
  <div class="field-row"><strong>5. Telephone Nos</strong>: <?= fillLine('phone', $c['phone']) ?></div>

  <h5 class="mt-3">6. Name of Directors / Partners / Individuals</h5>
  <?php
    $dirs = trim((string)$c['directors']);
    if ($dirs === '') {
        echo '<div class="field-row"><span class="line">&nbsp;</span></div>';
        echo '<div class="field-row"><span class="line">&nbsp;</span></div>';
        echo '<div class="field-row"><span class="line">&nbsp;</span></div>';
    } else {
        foreach (preg_split('/\R/', $dirs) as $i => $d) {
            $d = trim($d);
            if ($d !== '') echo '<div class="field-row"><strong>' . ($i+1) . '.</strong> <span class="filled">' . e($d) . '</span></div>';
        }
    }
  ?>

  <div class="field-row mt-3">
    <strong>7. Name &amp; Phone of Purchaser's Accountant</strong>:
    <?= fillLine('acc', trim(($c['accountant_name'] ?? '') . ' / ' . ($c['accountant_phone'] ?? ''), ' /')) ?>
  </div>
  <div class="field-row"><strong>8. Credit Limit Requested</strong>:
    <?= fillLine('cl', $c['credit_limit'] ? money($c['credit_limit']) : '') ?>
  </div>
  <div class="field-row"><strong>9. Bank</strong>: <?= fillLine('bk', $c['bank_name']) ?>
    &nbsp; <strong style="min-width:60px">Branch</strong>: <?= fillLine('br', $c['bank_branch']) ?>
    &nbsp; <strong style="min-width:60px">PIN No.</strong>: <?= fillLine('pin', $c['kra_pin']) ?>
  </div>
  <div class="field-row"><strong>10. Payment Terms</strong>:
    <?= fillLine('pt', $c['payment_terms']) ?>
    <?php if ($c['credit_period_days'] !== null): ?>
      &nbsp; (<span class="filled"><?= (int)$c['credit_period_days'] ?> days from invoice</span>)
    <?php endif; ?>
  </div>

  <h5 class="mt-3">11. Trade References</h5>
  <ol class="refs">
    <?php foreach (['trade_ref_1','trade_ref_2','trade_ref_3'] as $k): ?>
      <li>
        <?php $val = trim((string)($c[$k] ?? '')); ?>
        <?php if ($val === ''): ?>
          <span class="line">&nbsp;</span><br><span class="line">&nbsp;</span>
        <?php else: ?>
          <span class="filled"><?= nl2br(e($val)) ?></span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>

  <p class="small mt-3">
    I/We confirm that the sums due to <strong><?= e($company) ?></strong> are payable in accordance with the
    terms stated above, and that in the event of default, <strong><?= e($company) ?></strong> shall be
    entitled to recover the outstanding amounts through appropriate legal or other lawful means, with all
    associated costs payable by me/us.
  </p>

  <div class="row g-3 mt-3">
    <div class="col-7">
      <div class="signbox">
        <div class="lab">Signature</div>
      </div>
    </div>
    <div class="col-5">
      <div class="signbox">
        <div class="lab">Company stamp / seal</div>
      </div>
    </div>
    <div class="col-6">
      <div class="field-row"><strong>Name</strong>: <?= fillLine('sn', $c['signed_name']) ?></div>
      <div class="field-row"><strong>Position</strong>: <?= fillLine('sp', $c['signed_position']) ?></div>
      <div class="field-row"><strong>Date</strong>: <?= fillLine('sd', $c['signed_at'] ? fdate($c['signed_at']) : '') ?></div>
    </div>
    <div class="col-6">
      <div class="field-row"><strong>Witnessed by</strong>: <span class="line">&nbsp;</span></div>
      <div class="field-row"><strong>Signature</strong>: <span class="line">&nbsp;</span></div>
      <div class="field-row"><strong>Position</strong>: <span class="line">&nbsp;</span></div>
      <div class="field-row"><strong>Date</strong>: <span class="line">&nbsp;</span></div>
    </div>
  </div>

  <p class="small mt-4 mb-0"><strong>Please enclose:</strong></p>
  <ul class="small">
    <li>Certificate of Incorporation</li>
    <li>KRA PIN of the Company</li>
    <li>Pharmacist licence</li>
    <li>Wholesale licence (if applicant is wholesaler)</li>
  </ul>

  <!-- Page 2: Terms -->
  <div class="page-break"></div>
  <div class="lh-bar mt-4"></div>
  <h5 class="mt-3 text-center">TERMS &amp; CONDITIONS</h5>
  <div class="terms">
    <ol>
      <li>The information input in this credit application is in all respect complete, accurate and truthful.</li>
      <li>Account must be settled within 30 days from the date of invoice. The credit period may be extended to 45 days upon request.</li>
      <li>Account must be settled within agreed period failure to which further credit shall be stopped without notice.</li>
      <li>Customer shall immediately notify <?= e($company) ?> of any changes of physical, postal and email address.</li>
      <li>Any complaint of goods delivered shall be notified to <?= e($company) ?> within 24 hours.</li>
    </ol>
  </div>

  <h5 class="mt-4">For official use only</h5>
  <div class="field-row"><strong>Payment terms agreed</strong>: <span class="line">&nbsp;</span></div>
  <div class="field-row"><strong>Credit limit approved</strong>: <span class="line">&nbsp;</span></div>
  <div class="field-row"><strong>Credit period (days)</strong>: <span class="line">&nbsp;</span></div>

  <p class="mt-3 mb-2 small text-muted">Management approval</p>
  <div class="field-row"><strong>Name</strong>: <span class="line">&nbsp;</span></div>
  <div class="field-row"><strong>Date</strong>: <span class="line">&nbsp;</span></div>
  <div class="field-row"><strong>Signature</strong>: <span class="line">&nbsp;</span></div>
  <div class="field-row"><strong>Remarks</strong>: <span class="line">&nbsp;</span></div>

  <!-- Page 3: payment details -->
  <div class="page-break"></div>
  <div class="lh-bar mt-4"></div>
  <h5 class="mt-3">Banking Payment Details</h5>
  <?php
    $bankName = setting($pdo,'bank_name',         '');
    $bankBr   = setting($pdo,'bank_branch',       '');
    $bankAcN  = setting($pdo,'bank_account_name', '');
    $bankKes  = setting($pdo,'bank_account_kes',  '');
    $bankUsd  = setting($pdo,'bank_account_usd',  '');
    $bankSw   = setting($pdo,'bank_swift',        '');
    $mPayBill = setting($pdo,'mpesa_paybill',     '');
    $mAcct    = setting($pdo,'mpesa_account',     '');
  ?>
  <div class="field-row"><strong>Bank Name</strong>: <span class="filled"><?= e($bankName) ?></span></div>
  <div class="field-row"><strong>Account Name</strong>: <span class="filled"><?= e($bankAcN) ?></span></div>
  <div class="field-row"><strong>Account Number KES</strong>: <span class="filled"><?= e($bankKes) ?></span></div>
  <div class="field-row"><strong>Account Number USD</strong>: <span class="filled"><?= e($bankUsd) ?></span></div>
  <div class="field-row"><strong>Branch</strong>: <span class="filled"><?= e($bankBr) ?></span></div>
  <div class="field-row"><strong>Swift Code</strong>: <span class="filled"><?= e($bankSw) ?></span></div>

  <h5 class="mt-3">M-Pesa Payment Details</h5>
  <div class="field-row"><strong>Pay Bill No</strong>: <span class="filled"><?= e($mPayBill) ?></span></div>
  <div class="field-row"><strong>Account No</strong>: <span class="filled"><?= e($mAcct) ?></span></div>

  <div class="lh-foot">
    <strong><?= e($company) ?></strong> &middot; <?= e($cAddr) ?> &middot; Tel: <?= e($cPhone) ?>
  </div>
</div>

<div class="no-print text-center my-3">
  <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save as PDF</button>
  <a class="btn btn-outline-secondary" href="<?= url('clients/view.php?id=' . $id) ?>">Back</a>
</div>

</body>
</html>
