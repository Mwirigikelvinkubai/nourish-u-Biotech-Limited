<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();
$preselect = (int)get('client_id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $clientId = (int)post('client_id');
    $repId    = $u['role']==='rep' ? (int)$u['id'] : (int)post('rep_id');
    $sched    = post('scheduled_date');
    $notes    = clean(post('notes'));

    $items = [];
    foreach ((array)post('items', []) as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        $qty = (int)($it['qty'] ?? 0);
        if ($pid > 0 && $qty > 0) $items[] = [$pid, $qty];
    }
    if (!$clientId || !$repId || !$sched || !$items) {
        flash('danger','Please pick a client, a rep, a date, and at least one product.');
        redirect(url('samples/add.php'));
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO sample_drops (client_id, rep_id, scheduled_date, status, notes) VALUES (?,?,?, 'scheduled', ?)")
            ->execute([$clientId, $repId, $sched, $notes]);
        $dropId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO sample_drop_items (drop_id, product_id, qty_dropped) VALUES (?,?,?)");
        foreach ($items as [$pid, $qty]) $stmt->execute([$dropId, $pid, $qty]);
        $pdo->commit();
        audit($pdo,'sample_drop.create','sample_drop',$dropId);
        flash('success','Sample drop scheduled.');
        redirect(url('samples/view.php?id=' . $dropId));
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('danger','Could not schedule: ' . $e->getMessage());
        redirect(url('samples/add.php'));
    }
}

$clients = $pdo->query("SELECT id,name FROM clients ORDER BY name")->fetchAll();
$reps    = $pdo->query("SELECT id,name FROM users WHERE role='rep' AND status='active' ORDER BY name")->fetchAll();
$products= $pdo->query("SELECT id,sku,name,stock_qty FROM products WHERE status='active' ORDER BY name")->fetchAll();

$page_title = 'New sample drop';
require __DIR__ . '/../includes/header.php';
?>
<h3 class="mb-3">Schedule sample drop</h3>
<form method="post" class="card p-3">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-5"><label class="form-label">Client</label>
      <select class="form-select" name="client_id" required>
        <option value="">— Select —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $preselect===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php if ($u['role']!=='rep'): ?>
    <div class="col-md-4"><label class="form-label">Rep</label>
      <select class="form-select" name="rep_id" required>
        <option value="">— Select —</option>
        <?php foreach ($reps as $r): ?>
          <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="col-md-3"><label class="form-label">Scheduled date</label>
      <input class="form-control" type="date" name="scheduled_date" value="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" required></div>
    <div class="col-12"><label class="form-label">Notes</label>
      <textarea class="form-control" name="notes" rows="2"></textarea></div>
  </div>
  <hr>
  <h6>Sample items</h6>
  <div id="items"></div>
  <button type="button" class="btn btn-outline-primary btn-sm" onclick="addItem()">
    <i class="bi bi-plus"></i> Add item</button>
  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary"><i class="bi bi-save"></i> Schedule drop</button>
    <a class="btn btn-outline-secondary" href="<?= url('samples/index.php') ?>">Cancel</a>
  </div>
</form>

<script>
const PRODS = <?= json_encode($products) ?>;
function addItem(){
  const i = document.querySelectorAll('.sline').length;
  const opts = PRODS.map(p => `<option value="${p.id}">${p.sku} — ${p.name} (${p.stock_qty} in stock)</option>`).join('');
  const div = document.createElement('div');
  div.className = 'sline row g-2 align-items-end mb-2';
  div.innerHTML = `
    <div class="col-md-8"><label class="form-label small">Product</label>
      <select class="form-select" name="items[${i}][product_id]" required>
        <option value="">— Choose —</option>${opts}
      </select></div>
    <div class="col-md-3"><label class="form-label small">Qty to drop</label>
      <input class="form-control" type="number" min="1" value="1" name="items[${i}][qty]"></div>
    <div class="col-md-1 text-end">
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.sline').remove();">
        <i class="bi bi-trash"></i></button></div>`;
  document.getElementById('items').appendChild(div);
}
addItem();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
