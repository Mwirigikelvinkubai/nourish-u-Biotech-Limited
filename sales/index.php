<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$mine   = $u['role'] === 'rep';
$status = (string)get('status','');
$from   = (string)get('from','');
$to     = (string)get('to','');

$where = []; $args = [];
if ($mine)        { $where[]='s.rep_id = ?';        $args[]=$u['id']; }
if ($status!=='') { $where[]='s.payment_status = ?'; $args[]=$status; }
if ($from)        { $where[]='s.sale_date >= ?';    $args[]=$from; }
if ($to)          { $where[]='s.sale_date <= ?';    $args[]=$to; }

$sql = "SELECT s.*, c.name AS client, u.name AS rep
          FROM sales s
          JOIN clients c ON c.id = s.client_id
          JOIN users u ON u.id = s.rep_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY s.sale_date DESC, s.id DESC LIMIT 500';

$stmt = $pdo->prepare($sql); $stmt->execute($args);
$rows = $stmt->fetchAll();

$totals = ['count'=>0,'total'=>0,'paid'=>0,'due'=>0];
foreach ($rows as $r) { $totals['count']++; $totals['total']+=$r['total']; $totals['paid']+=$r['paid_amount']; $totals['due']+=$r['total']-$r['paid_amount']; }

$page_title = 'Sales & Invoices';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Sales &amp; Invoices</h3>
  <a class="btn btn-primary" href="<?= url('sales/add.php') ?>"><i class="bi bi-plus"></i> New sale</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-3">
    <select class="form-select" name="status">
      <option value="">— Any payment status —</option>
      <?php foreach (['unpaid','partial','paid'] as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><input class="form-control" type="date" name="from" value="<?= e($from) ?>" placeholder="From"></div>
  <div class="col-md-2"><input class="form-control" type="date" name="to"   value="<?= e($to)   ?>" placeholder="To"></div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button></div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="metric"><h6>Sales (filtered)</h6><div class="v"><?= money($totals['total']) ?></div><div class="sub"><?= (int)$totals['count'] ?> invoices</div></div></div>
  <div class="col-md-3"><div class="metric"><h6>Collected</h6><div class="v"><?= money($totals['paid']) ?></div></div></div>
  <div class="col-md-3"><div class="metric"><h6>Outstanding</h6><div class="v text-danger"><?= money($totals['due']) ?></div></div></div>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-clean align-middle mb-0">
<thead><tr><th>Invoice</th><th>Date</th><th>Client</th><th>Rep</th>
  <th class="text-end">Subtotal</th><th class="text-end">VAT</th><th class="text-end">Total</th>
  <th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><a href="<?= url('sales/view.php?id=' . (int)$r['id']) ?>"><?= e($r['invoice_no']) ?></a></td>
  <td><?= e(fdate($r['sale_date'])) ?></td>
  <td><?= e($r['client']) ?></td>
  <td><?= e($r['rep']) ?></td>
  <td class="text-end"><?= money($r['subtotal']) ?></td>
  <td class="text-end"><?= money($r['tax_amount']) ?></td>
  <td class="text-end fw-bold"><?= money($r['total']) ?></td>
  <td>
    <?php $cls = ['unpaid'=>'badge-danger','partial'=>'badge-warning','paid'=>'badge-soft'][$r['payment_status']] ?? 'badge-secondary'; ?>
    <span class="badge <?= $cls ?>"><?= e(ucfirst($r['payment_status'])) ?></span>
  </td>
  <td class="text-end">
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('sales/view.php?id=' . (int)$r['id']) ?>"><i class="bi bi-eye"></i></a>
    <a class="btn btn-sm btn-outline-primary"   href="<?= url('sales/invoice.php?id=' . (int)$r['id']) ?>" target="_blank"><i class="bi bi-printer"></i></a>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
