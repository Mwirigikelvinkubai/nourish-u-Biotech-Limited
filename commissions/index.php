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

    if ($op === 'snapshot' && in_array($u['role'], ['admin','accountant'], true)) {
        // Persist a snapshot (locks the figures so finance can approve & pay)
        $reps = $pdo->query("SELECT id FROM users WHERE role='rep' AND deleted_at IS NULL")->fetchAll();
        foreach ($reps as $r) {
            save_rep_month($pdo, compute_rep_month($pdo, (int)$r['id'], $year, $month));
        }
        flash('success','Snapshot saved for ' . date('F Y', mktime(0,0,0,$month,1,$year)));
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

/* -----------------------------------------------------------------
   LIVE compute: every page-load recomputes from current sales data.
   Stored snapshot rows (if any) provide the approval status.
   ----------------------------------------------------------------- */
$rows = [];

if ($u['role'] === 'rep') {
    $live = compute_rep_month($pdo, (int)$u['id'], $year, $month);
    // Look for a stored snapshot to surface status
    $snap = $pdo->prepare(
        "SELECT * FROM commissions WHERE rep_id=? AND period_year=? AND period_month=?"
    );
    $snap->execute([$u['id'], $year, $month]);
    $snap = $snap->fetch();

    $rows[] = [
        'id'              => $snap ? (int)$snap['id'] : 0,
        'rep_id'          => $u['id'],
        'rep_name'        => $u['name'],
        'sales_total'     => $live['sales_total'],
        'base_total'      => $live['base_total'],
        'bonus_pct'       => $live['bonus_pct'],
        'bonus_total'     => $live['bonus_total'],
        'total_commission'=> $live['total_commission'],
        'tier_label'      => $live['tier_label'],
        'status'          => $snap['status'] ?? 'live',
    ];
} else {
    $reps = $pdo->query(
        "SELECT id, name FROM users WHERE role='rep' AND deleted_at IS NULL ORDER BY name"
    )->fetchAll();

    foreach ($reps as $r) {
        $live = compute_rep_month($pdo, (int)$r['id'], $year, $month);
        $snap = $pdo->prepare(
            "SELECT * FROM commissions WHERE rep_id=? AND period_year=? AND period_month=?"
        );
        $snap->execute([$r['id'], $year, $month]);
        $snap = $snap->fetch();

        $rows[] = [
            'id'              => $snap ? (int)$snap['id'] : 0,
            'rep_id'          => (int)$r['id'],
            'rep_name'        => $r['name'],
            'sales_total'     => $live['sales_total'],
            'base_total'      => $live['base_total'],
            'bonus_pct'       => $live['bonus_pct'],
            'bonus_total'     => $live['bonus_total'],
            'total_commission'=> $live['total_commission'],
            'tier_label'      => $live['tier_label'],
            'status'          => $snap['status'] ?? 'live',
        ];
    }
}

$page_title = 'Commissions';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h3 class="mb-0">Commissions
    <small class="text-muted small">- <?= e(date('F Y', mktime(0,0,0,$month,1,$year))) ?></small>
  </h3>
  <?php if (in_array($u['role'], ['admin','accountant'], true)): ?>
    <form method="post" class="d-flex gap-2 align-items-center">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="snapshot">
      <button class="btn btn-outline-primary"
              title="Lock these numbers so finance can approve and pay them. The list is already live.">
        <i class="bi bi-bookmark-check"></i> Save snapshot
      </button>
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
  <h6 class="mb-1">How commissions are computed (hybrid &mdash; live)</h6>
  <p class="mb-0 small text-muted">
    <strong>Base:</strong> Each line of every (non-archived) sale earns
    <em>line_total &times; product.base_commission_pct</em>.
    <strong>Bonus:</strong> The rep's monthly subtotal is matched against the commission tiers; the matching tier's
    <em>bonus %</em> is then applied to that monthly subtotal.
    <strong>Total:</strong> base + bonus.
    Figures update <strong>automatically</strong> every time the page loads &mdash; new sales reflect immediately.
  </p>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-clean align-middle mb-0">
<thead><tr>
  <th>Rep</th>
  <th class="text-end">Sales (subtotal)</th>
  <th class="text-end">Base commission</th>
  <th>Tier</th>
  <th class="text-end">Bonus %</th>
  <th class="text-end">Bonus amount</th>
  <th class="text-end">Total</th>
  <th>Status</th>
  <?php if ($u['role']==='admin') echo '<th></th>'; ?>
</tr></thead>
<tbody>
<?php if (!$rows): ?>
  <tr><td colspan="9" class="text-center text-muted py-4">No active reps in the system.</td></tr>
<?php else: foreach ($rows as $r): ?>
<tr>
  <td><?= e($r['rep_name']) ?></td>
  <td class="text-end"><?= money($r['sales_total']) ?></td>
  <td class="text-end"><?= money($r['base_total']) ?></td>
  <td class="small text-muted"><?= e($r['tier_label']) ?></td>
  <td class="text-end"><?= e($r['bonus_pct']) ?>%</td>
  <td class="text-end"><?= money($r['bonus_total']) ?></td>
  <td class="text-end fw-bold"><?= money($r['total_commission']) ?></td>
  <td>
    <?php
      $cls = [
        'live'    => 'badge-info',
        'pending' => 'badge-warning',
        'approved'=> 'badge-purple',
        'paid'    => 'badge-soft',
      ][$r['status']] ?? 'badge-secondary';
      $label = $r['status'] === 'live' ? 'Live' : ucfirst((string)$r['status']);
    ?>
    <span class="badge <?= $cls ?>"><?= e($label) ?></span>
  </td>
  <?php if ($u['role']==='admin'): ?>
    <td class="text-end">
      <?php if ((int)$r['id'] > 0): ?>
        <form method="post" class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="op" value="set_status">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <select class="form-select form-select-sm d-inline-block" name="status"
                  style="width:120px;" onchange="this.form.submit()">
            <option value="pending"  <?= $r['status']==='pending' ?'selected':'' ?>>Pending</option>
            <option value="approved" <?= $r['status']==='approved'?'selected':'' ?>>Approved</option>
            <option value="paid"     <?= $r['status']==='paid'    ?'selected':'' ?>>Paid</option>
          </select>
        </form>
      <?php else: ?>
        <span class="small text-muted">Save snapshot to approve</span>
      <?php endif; ?>
    </td>
  <?php endif; ?>
</tr>
<?php endforeach; endif; ?>
</tbody>
<?php if ($rows && count($rows) > 1): ?>
<tfoot>
  <tr class="bg-light">
    <th>Total</th>
    <th class="text-end"><?= money(array_sum(array_column($rows, 'sales_total'))) ?></th>
    <th class="text-end"><?= money(array_sum(array_column($rows, 'base_total'))) ?></th>
    <th></th>
    <th></th>
    <th class="text-end"><?= money(array_sum(array_column($rows, 'bonus_total'))) ?></th>
    <th class="text-end fw-bold"><?= money(array_sum(array_column($rows, 'total_commission'))) ?></th>
    <th></th>
    <?php if ($u['role']==='admin') echo '<th></th>'; ?>
  </tr>
</tfoot>
<?php endif; ?>
</table></div></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
