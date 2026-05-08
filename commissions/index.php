<?php
require_once __DIR__ . '/../config/config.php';
require_login();
require_once ROOT_PATH . '/includes/commission.php';
$u = current_user();

$year  = (int)(get('year')  ?: date('Y'));
$month = (int)(get('month') ?: date('n'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');

    if ($op === 'recalculate' && in_array($u['role'], ['admin','accountant'], true)) {
        $reps = $pdo->query("SELECT id FROM users WHERE role='rep' AND status='active'")->fetchAll();
        foreach ($reps as $r) {
            save_rep_month($pdo, compute_rep_month($pdo, (int)$r['id'], $year, $month));
        }
        flash('success','Commissions recalculated for ' . date('F Y', mktime(0,0,0,$month,1,$year)));
        redirect(url('commissions/index.php?year=' . $year . '&month=' . $month));
    }

    if ($op === 'set_status' && $u['role'] === 'admin') {
        $cid = (int)post('id');
        $st  = post('status');
        if (in_array($st, ['pending','approved','paid'], true)) {
            $extra = '';
            if ($st === 'approved') $extra = ", approved_at = NOW(), approved_by = " . (int)$u['id'];
            if ($st === 'paid')     $extra = ", paid_at = NOW()";
            $pdo->prepare("UPDATE commissions SET status=? $extra WHERE id=?")->execute([$st, $cid]);
            audit($pdo,'commission.' . $st,'commission',$cid);
            flash('success','Status updated.');
        }
        redirect(url('commissions/index.php?year=' . $year . '&month=' . $month));
    }
}

if ($u['role'] === 'rep') {
    $rows = $pdo->prepare(
        "SELECT c.* FROM commissions c
          WHERE c.rep_id=? AND c.period_year=? AND c.period_month=?"
    );
    $rows->execute([$u['id'], $year, $month]);
    $rows = $rows->fetchAll();

    /* even if not yet generated, show a live computation */
    if (!$rows) {
        $live = compute_rep_month($pdo, (int)$u['id'], $year, $month);
        $rows = [[
            'id' => 0, 'rep_id' => $u['id'], 'rep_name' => $u['name'],
            'period_year' => $year, 'period_month' => $month,
            'sales_total' => $live['sales_total'], 'base_total' => $live['base_total'],
            'bonus_pct'   => $live['bonus_pct'],   'bonus_total' => $live['bonus_total'],
            'total_commission' => $live['total_commission'], 'status' => 'pending (live)',
        ]];
    } else {
        foreach ($rows as &$r) $r['rep_name'] = $u['name']; unset($r);
    }
} else {
    $rows = $pdo->prepare(
        "SELECT c.*, u.name AS rep_name
           FROM commissions c
           JOIN users u ON u.id = c.rep_id
          WHERE c.period_year=? AND c.period_month=?
          ORDER BY u.name"
    );
    $rows->execute([$year, $month]);
    $rows = $rows->fetchAll();
}

$page_title = 'Commissions';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Commissions
    <small class="text-muted">– <?= e(date('F Y', mktime(0,0,0,$month,1,$year))) ?></small></h3>
  <?php if (in_array($u['role'], ['admin','accountant'], true)): ?>
    <form method="post" class="d-flex gap-2 align-items-center">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="recalculate">
      <button class="btn btn-outline-primary"><i class="bi bi-arrow-clockwise"></i> Recalculate this month</button>
    </form>
  <?php endif; ?>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-auto"><select class="form-select" name="year">
    <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 4; $y--): ?>
      <option value="<?= $y ?>" <?= $y===$year?'selected':'' ?>><?= $y ?></option>
    <?php endfor; ?>
  </select></div>
  <div class="col-auto"><select class="form-select" name="month">
    <?php for ($m=1; $m<=12; $m++): ?>
      <option value="<?= $m ?>" <?= $m===$month?'selected':'' ?>><?= e(date('F', mktime(0,0,0,$m,1))) ?></option>
    <?php endfor; ?>
  </select></div>
  <div class="col-auto"><button class="btn btn-outline-primary">Go</button></div>
</form>

<div class="card mb-3 p-3">
  <h6>How commissions are computed (hybrid)</h6>
  <p class="mb-1 small">
    <strong>Base:</strong> Each line of every sale earns <em>line_total × product.base_commission_pct</em>.
    <strong>Bonus:</strong> The rep's <em>monthly subtotal</em> is matched against the commission tiers; the tier's
    <em>bonus %</em> is multiplied by the same monthly subtotal.
    <strong>Total:</strong> base + bonus.
  </p>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-clean align-middle mb-0">
<thead><tr><th>Rep</th><th class="text-end">Sales (subtotal)</th><th class="text-end">Base commission</th>
  <th class="text-end">Bonus %</th><th class="text-end">Bonus amount</th><th class="text-end">Total</th>
  <th>Status</th><?php if ($u['role']==='admin') echo '<th></th>'; ?></tr></thead>
<tbody>
<?php if (!$rows): ?>
  <tr><td colspan="8" class="text-center text-muted py-4">No data — click <em>Recalculate</em>.</td></tr>
<?php else: foreach ($rows as $r): ?>
<tr>
  <td><?= e($r['rep_name']) ?></td>
  <td class="text-end"><?= money($r['sales_total']) ?></td>
  <td class="text-end"><?= money($r['base_total']) ?></td>
  <td class="text-end"><?= e($r['bonus_pct']) ?>%</td>
  <td class="text-end"><?= money($r['bonus_total']) ?></td>
  <td class="text-end fw-bold"><?= money($r['total_commission']) ?></td>
  <td>
    <?php $cls = ['pending'=>'badge-warning','approved'=>'badge-info','paid'=>'badge-soft'][$r['status']] ?? 'badge-secondary'; ?>
    <span class="badge <?= $cls ?>"><?= e(ucfirst((string)$r['status'])) ?></span>
  </td>
  <?php if ($u['role']==='admin' && (int)$r['id'] > 0): ?>
  <td class="text-end">
    <form method="post" class="d-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="set_status">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <select class="form-select form-select-sm d-inline-block" name="status" style="width:120px;"
              onchange="this.form.submit()">
        <option value="pending"  <?= $r['status']==='pending' ?'selected':'' ?>>Pending</option>
        <option value="approved" <?= $r['status']==='approved'?'selected':'' ?>>Approved</option>
        <option value="paid"     <?= $r['status']==='paid'    ?'selected':'' ?>>Paid</option>
      </select>
    </form>
  </td>
  <?php elseif ($u['role']==='admin'): ?><td></td><?php endif; ?>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
