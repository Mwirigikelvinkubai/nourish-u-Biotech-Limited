<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$keys = [
    'company_name','company_tagline','company_address','company_phone','company_email',
    'vat_default_pct','invoice_prefix',
    'bank_name','bank_branch','bank_account_name','bank_account_kes','bank_account_usd','bank_swift',
    'mpesa_paybill','mpesa_account',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($keys as $k) {
        $v = (string)post($k, '');
        $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES (?, ?)
                       ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
            ->execute([$k, $v]);
    }
    audit($pdo,'settings.update','settings');
    flash('success','Settings saved.');
    redirect(url('admin/settings.php'));
}

$current = [];
foreach ($pdo->query("SELECT `key`,`value` FROM settings") as $row) {
    $current[$row['key']] = $row['value'];
}

$page_title = 'Settings';
require __DIR__ . '/../includes/header.php';
?>
<h3 class="mb-3">System Settings</h3>
<form method="post">
  <?= csrf_field() ?>

  <div class="card p-3 mb-3">
    <h6 class="text-muted text-uppercase small mb-3">Company identity</h6>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Company name</label>
        <input class="form-control" name="company_name" value="<?= e($current['company_name'] ?? 'Nourish U Biotech Limited') ?>"></div>
      <div class="col-md-6"><label class="form-label">Tagline</label>
        <input class="form-control" name="company_tagline" value="<?= e($current['company_tagline'] ?? 'Your Partner in Natural Wellness') ?>"></div>
      <div class="col-md-6"><label class="form-label">Email</label>
        <input class="form-control" type="email" name="company_email" value="<?= e($current['company_email'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label">Phone</label>
        <input class="form-control" name="company_phone" value="<?= e($current['company_phone'] ?? '') ?>"></div>
      <div class="col-12"><label class="form-label">Postal / physical address</label>
        <input class="form-control" name="company_address" value="<?= e($current['company_address'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">Default VAT %</label>
        <input class="form-control" type="number" step="0.01" name="vat_default_pct" value="<?= e($current['vat_default_pct'] ?? '16.00') ?>"></div>
      <div class="col-md-3"><label class="form-label">Invoice prefix</label>
        <input class="form-control" name="invoice_prefix" value="<?= e($current['invoice_prefix'] ?? 'INV-') ?>"></div>
    </div>
  </div>

  <div class="card p-3 mb-3">
    <h6 class="text-muted text-uppercase small mb-3"><i class="bi bi-bank"></i> NCBA bank payment details (printed on every invoice)</h6>
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Bank name</label>
        <input class="form-control" name="bank_name" value="<?= e($current['bank_name'] ?? 'NCBA Bank') ?>"></div>
      <div class="col-md-4"><label class="form-label">Branch</label>
        <input class="form-control" name="bank_branch" value="<?= e($current['bank_branch'] ?? 'ABC Place') ?>"></div>
      <div class="col-md-4"><label class="form-label">Account name</label>
        <input class="form-control" name="bank_account_name" value="<?= e($current['bank_account_name'] ?? 'Nourish U Biotech Limited') ?>"></div>
      <div class="col-md-4"><label class="form-label">Account number (KES)</label>
        <input class="form-control" name="bank_account_kes" value="<?= e($current['bank_account_kes'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Account number (USD)</label>
        <input class="form-control" name="bank_account_usd" value="<?= e($current['bank_account_usd'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Swift code</label>
        <input class="form-control" name="bank_swift" value="<?= e($current['bank_swift'] ?? '') ?>"></div>
    </div>
  </div>

  <div class="card p-3 mb-3">
    <h6 class="text-muted text-uppercase small mb-3"><i class="bi bi-phone"></i> M-Pesa Pay Bill</h6>
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Pay Bill number</label>
        <input class="form-control" name="mpesa_paybill" value="<?= e($current['mpesa_paybill'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Account number</label>
        <input class="form-control" name="mpesa_account" value="<?= e($current['mpesa_account'] ?? '') ?>"></div>
    </div>
  </div>

  <button class="btn btn-primary"><i class="bi bi-save"></i> Save settings</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
