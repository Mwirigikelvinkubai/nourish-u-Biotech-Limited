<?php
require_once __DIR__ . '/../config/config.php';
require_role(['admin','accountant']);

/* ── Filters ── */
$q      = trim((string)get('q', ''));
$region = trim((string)get('region', ''));
$kyc    = trim((string)get('kyc', ''));
$type   = trim((string)get('type', ''));
$rep_id = (int)get('rep_id', 0);

/* ── CSV export ── */
$export = get('export') === 'csv';

/* ── Build query ── */
$where = ['c.deleted_at IS NULL'];
$args  = [];

if ($q !== '') {
    $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.contact_person LIKE ?)';
    $like = "%$q%";
    array_push($args, $like, $like, $like, $like);
}
if ($region !== '') { $where[] = 'c.region = ?';     $args[] = $region; }
if ($kyc    !== '') { $where[] = 'c.kyc_status = ?'; $args[] = $kyc; }
if ($type   !== '') { $where[] = 'c.type = ?';       $args[] = $type; }
if ($rep_id > 0)    { $where[] = 'c.rep_id = ?';     $args[] = $rep_id; }

$sql = "SELECT c.id, c.name, c.type, c.contact_person, c.contact_role,
               c.phone, c.email, c.address, c.city, c.region,
               c.kyc_status, c.credit_limit, c.license_no, c.kra_pin,
               c.accountant_name, c.accountant_phone,
               c.payment_terms, c.credit_period_days,
               c.created_at,
               u.name AS rep_name, u.phone AS rep_phone
          FROM clients c
          LEFT JOIN users u ON u.id = c.rep_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY c.region, c.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

/* ── Filter options (distinct regions / reps) ── */
$regions = $pdo->query("SELECT DISTINCT region FROM clients WHERE deleted_at IS NULL AND region IS NOT NULL AND region != '' ORDER BY region")->fetchAll(PDO::FETCH_COLUMN);
$reps    = $pdo->query("SELECT id, name FROM users WHERE role='rep' AND deleted_at IS NULL ORDER BY name")->fetchAll();
$types   = ['pharmacy','clinic','hospital','wholesaler','other'];

/* ── CSV export ── */
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="client_contacts_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#','Name','Type','Contact Person','Role','Phone','Email','City','Region',
                   'KYC Status','License No','KRA PIN','Assigned Rep','Rep Phone',
                   'Credit Limit','Payment Terms','Credit Period (Days)',
                   'Accountant','Accountant Phone','Registered']);
    foreach ($rows as $i => $c) {
        fputcsv($out, [
            $i + 1,
            $c['name'],
            ucfirst($c['type']),
            $c['contact_person'],
            $c['contact_role'],
            $c['phone'],
            $c['email'],
            $c['city'],
            $c['region'],
            ucfirst($c['kyc_status']),
            $c['license_no'],
            $c['kra_pin'],
            $c['rep_name'],
            $c['rep_phone'],
            number_format((float)$c['credit_limit'], 2),
            $c['payment_terms'],
            $c['credit_period_days'],
            $c['accountant_name'],
            $c['accountant_phone'],
            $c['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

/* ── Page ── */
$page_title = 'Client Contacts Report';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0"><i class="bi bi-person-lines-fill"></i> Client Contacts Report</h3>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-success"
       href="<?= url('reports/contacts.php?' . http_build_query(array_filter([
           'q'       => $q,
           'region'  => $region,
           'kyc'     => $kyc,
           'type'    => $type,
           'rep_id'  => $rep_id ?: '',
           'export'  => 'csv',
       ]))) ?>">
      <i class="bi bi-filetype-csv"></i> Export CSV
    </a>
    <button class="btn btn-outline-secondary" onclick="window.print()">
      <i class="bi bi-printer"></i> Print
    </button>
    <a class="btn btn-outline-primary" href="<?= url('reports/index.php') ?>">
      <i class="bi bi-graph-up"></i> Sales Reports
    </a>
  </div>
</div>

<!-- Filters -->
<form class="row g-2 mb-4" method="get">
  <div class="col-md-3">
    <input class="form-control" type="text" name="q" placeholder="Search name, phone, email…" value="<?= e($q) ?>">
  </div>
  <div class="col-md-2">
    <select class="form-select" name="region">
      <option value="">— All regions —</option>
      <?php foreach ($regions as $r): ?>
        <option value="<?= e($r) ?>" <?= $region === $r ? 'selected' : '' ?>><?= e($r) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select class="form-select" name="kyc">
      <option value="">— Any KYC —</option>
      <?php foreach (['pending','verified','rejected'] as $k): ?>
        <option value="<?= $k ?>" <?= $kyc === $k ? 'selected' : '' ?>><?= ucfirst($k) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select class="form-select" name="type">
      <option value="">— All types —</option>
      <?php foreach ($types as $t): ?>
        <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select class="form-select" name="rep_id">
      <option value="">— All reps —</option>
      <?php foreach ($reps as $r): ?>
        <option value="<?= (int)$r['id'] ?>" <?= $rep_id === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-1">
    <button class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i></button>
  </div>
</form>

<!-- Summary badges -->
<div class="d-flex gap-3 mb-3 flex-wrap">
  <?php
    $total     = count($rows);
    $verified  = count(array_filter($rows, fn($r) => $r['kyc_status'] === 'verified'));
    $pending   = count(array_filter($rows, fn($r) => $r['kyc_status'] === 'pending'));
    $rejected  = count(array_filter($rows, fn($r) => $r['kyc_status'] === 'rejected'));
  ?>
  <span class="badge badge-soft fs-6">Total: <?= $total ?></span>
  <span class="badge badge-soft fs-6 text-success">Verified: <?= $verified ?></span>
  <span class="badge badge-warning fs-6">Pending KYC: <?= $pending ?></span>
  <?php if ($rejected): ?>
    <span class="badge badge-danger fs-6">Rejected: <?= $rejected ?></span>
  <?php endif; ?>
</div>

<!-- Table -->
<div class="card">
  <div class="table-responsive">
    <table class="table table-clean align-middle mb-0" id="contactsTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Client</th>
          <th>Type</th>
          <th>Contact Person</th>
          <th>Phone</th>
          <th>Email</th>
          <th>Region / City</th>
          <th>Assigned Rep</th>
          <th>KYC</th>
          <th class="text-end">Credit Limit</th>
          <th class="no-print"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">No clients match the selected filters.</td></tr>
        <?php else: foreach ($rows as $i => $c):
          $kc = ['pending'=>'badge-warning','verified'=>'badge-soft','rejected'=>'badge-danger'][$c['kyc_status']] ?? 'badge-secondary';
        ?>
          <tr>
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td>
              <a href="<?= url('clients/view.php?id=' . (int)$c['id']) ?>" class="fw-semibold"><?= e($c['name']) ?></a>
              <?php if ($c['license_no']): ?>
                <div class="small text-muted">Lic: <?= e($c['license_no']) ?></div>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-soft"><?= e(ucfirst($c['type'])) ?></span></td>
            <td>
              <?= e($c['contact_person'] ?: '—') ?>
              <?php if ($c['contact_role']): ?>
                <div class="small text-muted"><?= e($c['contact_role']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($c['phone']): ?>
                <a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <?php if ($c['email']): ?>
                <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <?= e($c['region'] ?: '—') ?>
              <?php if ($c['city']): ?><div class="small text-muted"><?= e($c['city']) ?></div><?php endif; ?>
            </td>
            <td><?= e($c['rep_name'] ?: '—') ?></td>
            <td><span class="badge <?= $kc ?>"><?= e(ucfirst($c['kyc_status'])) ?></span></td>
            <td class="text-end"><?= money($c['credit_limit']) ?></td>
            <td class="text-end no-print">
              <a class="btn btn-sm btn-outline-secondary" href="<?= url('clients/view.php?id=' . (int)$c['id']) ?>">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
@media print {
  .no-print { display: none !important; }
  .sidebar, .navbar, form, .btn { display: none !important; }
  .card { border: none !important; box-shadow: none !important; }
}
</style>

<?php require __DIR__ . '/../includes/footer.php'; ?>
