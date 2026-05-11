<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$mine    = $u['role'] === 'rep';
$status  = (string)get('status','');
$cat     = (string)get('cat','');
$from    = (string)get('from','');
$to      = (string)get('to','');
$showArchived = get('show') === 'archived';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $eid = (int)post('id');

    if ($op === 'approve' && in_array($u['role'], ['admin','accountant'], true)) {
        $pdo->prepare("UPDATE expenses
                          SET status='approved', approved_by=?, approved_at=NOW(),
                              review_notes=?
                        WHERE id=?")
            ->execute([$u['id'], clean(post('review_notes')), $eid]);
        audit($pdo,'expense.approve','expense',$eid);
        flash('success','Expense approved.');
    }
    elseif ($op === 'reject' && in_array($u['role'], ['admin','accountant'], true)) {
        $pdo->prepare("UPDATE expenses SET status='rejected', approved_by=?, approved_at=NOW(), review_notes=? WHERE id=?")
            ->execute([$u['id'], clean(post('review_notes')), $eid]);
        audit($pdo,'expense.reject','expense',$eid);
        flash('warning','Expense rejected.');
    }
    elseif ($op === 'pay' && in_array($u['role'], ['admin','accountant'], true)) {
        $pdo->prepare("UPDATE expenses SET status='paid', paid_at=NOW() WHERE id=? AND status='approved'")
            ->execute([$eid]);
        audit($pdo,'expense.paid','expense',$eid);
        flash('success','Marked as reimbursed.');
    }
    elseif ($op === 'soft_delete') {
        sd_soft_delete($pdo, 'expenses', $eid, (string)post('reason',''));
        flash('success','Expense archived.');
    }
    elseif ($op === 'restore' && $u['role']==='admin') {
        sd_restore($pdo, 'expenses', $eid);
        flash('success','Expense restored.');
    }
    redirect(url('expenses/index.php' . ($showArchived ? '?show=archived' : '')));
}

$where = [$showArchived ? 'e.deleted_at IS NOT NULL' : 'e.deleted_at IS NULL'];
$args  = [];
if ($mine)         { $where[] = 'e.rep_id = ?';  $args[] = $u['id']; }
if ($status!=='')  { $where[] = 'e.status = ?';  $args[] = $status; }
if ($cat!=='')     { $where[] = 'e.category = ?';$args[] = $cat; }
if ($from)         { $where[] = 'e.spent_on >= ?';$args[]= $from; }
if ($to)           { $where[] = 'e.spent_on <= ?';$args[]= $to; }

$sql = "SELECT e.*, u.name AS rep, c.name AS client
          FROM expenses e
          JOIN users u   ON u.id = e.rep_id
          LEFT JOIN clients c ON c.id = e.client_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY e.spent_on DESC, e.id DESC LIMIT 500";
$stmt = $pdo->prepare($sql); $stmt->execute($args);
$rows = $stmt->fetchAll();

$totals = ['n'=>0,'amount'=>0,'pending'=>0,'paid'=>0];
foreach ($rows as $r) {
    $totals['n']++;
    $totals['amount']  += $r['amount'];
    if ($r['status']==='pending')   $totals['pending'] += $r['amount'];
    if ($r['status']==='paid')      $totals['paid']    += $r['amount'];
}

$page_title = 'Expenses';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h3 class="mb-0">Rep expenses
    <?php if ($showArchived): ?><span class="badge badge-secondary">Archived</span><?php endif; ?>
  </h3>
  <div>
    <?php if ($showArchived): ?>
      <a class="btn btn-outline-secondary" href="<?= url('expenses/index.php') ?>"><i class="bi bi-arrow-left"></i> Active</a>
    <?php else: ?>
      <?php if (in_array($u['role'],['admin','accountant'],true)): ?>
        <a class="btn btn-outline-secondary" href="<?= url('expenses/index.php?show=archived') ?>"><i class="bi bi-archive"></i> Archived</a>
      <?php endif; ?>
      <a class="btn btn-primary" href="<?= url('expenses/add.php') ?>"><i class="bi bi-plus"></i> Log expense</a>
    <?php endif; ?>
  </div>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-2"><select class="form-select" name="status">
    <option value="">— Any status —</option>
    <?php foreach (['pending','approved','rejected','paid'] as $s): ?>
      <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select></div>
  <div class="col-md-2"><select class="form-select" name="cat">
    <option value="">— Any category —</option>
    <?php foreach (['Fuel','Transport','Meals','Airtime','Accommodation','Stationery','Client gifts','Other'] as $c): ?>
      <option value="<?= e($c) ?>" <?= $cat===$c?'selected':'' ?>><?= e($c) ?></option>
    <?php endforeach; ?>
  </select></div>
  <div class="col-md-2"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="col-md-2"><input class="form-control" type="date" name="to"   value="<?= e($to) ?>"></div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button></div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="metric"><h6>Logged</h6><div class="v"><?= money($totals['amount']) ?></div><div class="sub"><?= (int)$totals['n'] ?> entries</div></div></div>
  <div class="col-md-3"><div class="metric"><h6>Pending</h6><div class="v text-warning"><?= money($totals['pending']) ?></div></div></div>
  <div class="col-md-3"><div class="metric"><h6>Paid</h6><div class="v text-success"><?= money($totals['paid']) ?></div></div></div>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-clean align-middle mb-0">
<thead><tr>
  <th>Date</th><th>Rep</th><th>Category</th><th>Description</th><th>Client</th>
  <th class="text-end">Amount</th><th>Status</th><th>Receipt</th><th></th>
</tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e(fdate($r['spent_on'])) ?></td>
    <td><?= e($r['rep']) ?></td>
    <td><span class="badge badge-soft"><?= e($r['category']) ?></span></td>
    <td><?= e(mb_strimwidth((string)$r['description'], 0, 60, '…')) ?></td>
    <td class="small text-muted"><?= e($r['client'] ?: '-') ?></td>
    <td class="text-end fw-bold"><?= money($r['amount']) ?></td>
    <td>
      <?php $cls = ['pending'=>'badge-warning','approved'=>'badge-info','rejected'=>'badge-danger','paid'=>'badge-soft'][$r['status']] ?? 'badge-secondary'; ?>
      <span class="badge <?= $cls ?>"><?= e(ucfirst($r['status'])) ?></span>
    </td>
    <td>
      <?php if ($r['receipt_path']): ?>
        <a class="small" href="<?= url('expenses/receipt.php?id=' . (int)$r['id']) ?>" target="_blank">
          <i class="bi bi-paperclip"></i> View</a>
      <?php else: ?>—<?php endif; ?>
    </td>
    <td class="text-end">
      <a class="btn btn-sm btn-outline-secondary" href="<?= url('expenses/view.php?id=' . (int)$r['id']) ?>"><i class="bi bi-eye"></i></a>
    </td>
  </tr>
<?php endforeach; ?>
<?php if (!$rows): ?>
  <tr><td colspan="9" class="text-center text-muted py-4">No expenses logged yet.</td></tr>
<?php endif; ?>
</tbody></table>
</div></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
