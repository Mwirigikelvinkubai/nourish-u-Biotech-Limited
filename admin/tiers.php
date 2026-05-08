<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');

    if ($op === 'save') {
        $id    = (int)post('id', 0);
        $label = clean(post('label'));
        $min   = (float)post('min_amount');
        $max   = post('max_amount') !== '' && post('max_amount') !== null ? (float)post('max_amount') : null;
        $bonus = (float)post('bonus_pct');

        if ($id) {
            $pdo->prepare("UPDATE commission_tiers SET label=?, min_amount=?, max_amount=?, bonus_pct=? WHERE id=?")
                ->execute([$label,$min,$max,$bonus,$id]);
            flash('success','Tier updated.');
        } else {
            $pdo->prepare("INSERT INTO commission_tiers (label,min_amount,max_amount,bonus_pct) VALUES (?,?,?,?)")
                ->execute([$label,$min,$max,$bonus]);
            flash('success','Tier added.');
        }
        redirect(url('admin/tiers.php'));
    }

    if ($op === 'delete') {
        $pdo->prepare("DELETE FROM commission_tiers WHERE id=?")->execute([(int)post('id')]);
        flash('info','Tier deleted.');
        redirect(url('admin/tiers.php'));
    }
}

$tiers = $pdo->query("SELECT * FROM commission_tiers ORDER BY min_amount")->fetchAll();

$page_title = 'Commission Tiers';
require __DIR__ . '/../includes/header.php';
?>
<h3 class="mb-3">Commission Tiers (Monthly Volume Bonus)</h3>
<p class="text-muted small">
  Each rep's <strong>total monthly sales</strong> is matched against these tiers to find the bonus percentage.
  The bonus % is then applied to the rep's monthly sales to compute the bonus, on top of the per-product base commission.
</p>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card"><div class="table-responsive"><table class="table table-clean mb-0">
    <thead><tr><th>Label</th><th class="text-end">From</th><th class="text-end">To</th><th class="text-end">Bonus %</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tiers as $t): ?>
      <tr>
        <td><?= e($t['label']) ?></td>
        <td class="text-end"><?= money($t['min_amount']) ?></td>
        <td class="text-end"><?= $t['max_amount']===null ? '∞' : money($t['max_amount']) ?></td>
        <td class="text-end"><?= e($t['bonus_pct']) ?>%</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary" type="button"
                  onclick='loadTier(<?= json_encode($t) ?>)'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete tier?');">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="delete">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
  </div>

  <div class="col-lg-5">
    <div class="card p-3">
      <h6 id="form-heading">Add tier</h6>
      <form method="post" id="tierForm">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="save">
        <input type="hidden" name="id" id="f_id" value="0">
        <div class="mb-2"><label class="form-label">Label</label>
          <input class="form-control" name="label" id="f_label" required></div>
        <div class="mb-2"><label class="form-label">Min monthly sales (<?= e(APP_CURRENCY) ?>)</label>
          <input class="form-control" type="number" min="0" step="0.01" name="min_amount" id="f_min" required></div>
        <div class="mb-2"><label class="form-label">Max monthly sales (blank = no cap)</label>
          <input class="form-control" type="number" min="0" step="0.01" name="max_amount" id="f_max"></div>
        <div class="mb-2"><label class="form-label">Bonus %</label>
          <input class="form-control" type="number" min="0" step="0.01" name="bonus_pct" id="f_bonus" required></div>
        <div class="d-flex gap-2">
          <button class="btn btn-primary"><i class="bi bi-save"></i> Save tier</button>
          <button class="btn btn-outline-secondary" type="button" onclick="resetForm()">Reset</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function loadTier(t) {
  document.getElementById('form-heading').innerText = 'Edit tier';
  document.getElementById('f_id').value = t.id;
  document.getElementById('f_label').value = t.label;
  document.getElementById('f_min').value = t.min_amount;
  document.getElementById('f_max').value = t.max_amount ?? '';
  document.getElementById('f_bonus').value = t.bonus_pct;
}
function resetForm() {
  document.getElementById('form-heading').innerText = 'Add tier';
  document.getElementById('tierForm').reset();
  document.getElementById('f_id').value = 0;
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
