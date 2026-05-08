<?php
require_once __DIR__ . '/../config/config.php';
require_role(['admin','accountant']);

$only = (string)get('only',''); // 'low' | 'expiring'

$where = ["status='active'"]; $args = [];
if ($only === 'low')      { $where[] = 'stock_qty <= reorder_level'; }
if ($only === 'expiring') { $where[] = 'expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)'; }

$sql  = "SELECT * FROM products WHERE " . implode(' AND ', $where) . " ORDER BY expiry_date IS NULL, expiry_date, name";
$stmt = $pdo->prepare($sql); $stmt->execute($args);
$rows = $stmt->fetchAll();

$mLow = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='active' AND stock_qty <= reorder_level")->fetchColumn();
$mExp = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    if ($op === 'restock') {
        $pid = (int)post('id');
        $add = (int)post('qty');
        $batch = clean(post('batch_no'));
        $exp   = post('expiry_date') ?: null;
        if ($pid && $add > 0) {
            $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ?,
                           batch_no = COALESCE(NULLIF(?,''), batch_no),
                           expiry_date = COALESCE(?, expiry_date)
                           WHERE id=?")->execute([$add, $batch, $exp, $pid]);
            audit($pdo,'product.restock','product',$pid,(string)$add);
            flash('success','Stock updated.');
        }
        redirect(url('inventory/index.php'));
    }
}

$page_title = 'Inventory & Expiry';
require __DIR__ . '/../includes/header.php';
?>
<h3 class="mb-3">Inventory &amp; Expiry</h3>

<div class="row g-3 mb-3">
  <div class="col-md-3"><a class="text-decoration-none" href="?only=low">
    <div class="metric"><h6>Low-stock items</h6><div class="v text-danger"><?= $mLow ?></div></div></a></div>
  <div class="col-md-3"><a class="text-decoration-none" href="?only=expiring">
    <div class="metric"><h6>Expiring within 90 days</h6><div class="v text-warning"><?= $mExp ?></div></div></a></div>
  <div class="col-md-3"><a class="text-decoration-none" href="?">
    <div class="metric"><h6>All active SKUs</h6><div class="v"><?= count($rows) ?></div></div></a></div>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-clean align-middle mb-0">
<thead><tr><th>SKU</th><th>Product</th><th class="text-end">Stock</th><th class="text-end">Reorder lvl</th>
  <th>Batch</th><th>Expiry</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $p):
  $low  = $p['stock_qty'] <= $p['reorder_level'];
  $exp  = $p['expiry_date'] && strtotime($p['expiry_date']) < strtotime('+90 days');
  $exp30= $p['expiry_date'] && strtotime($p['expiry_date']) < strtotime('+30 days');
?>
<tr class="<?= $low?'table-danger':'' ?>">
  <td class="font-monospace small"><?= e($p['sku']) ?></td>
  <td><?= e($p['name']) ?><div class="small text-muted"><?= e($p['manufacturer']) ?></div></td>
  <td class="text-end fw-bold"><?= (int)$p['stock_qty'] ?> <?= e($p['unit']) ?></td>
  <td class="text-end"><?= (int)$p['reorder_level'] ?></td>
  <td class="small"><?= e($p['batch_no']) ?></td>
  <td class="<?= $exp30?'text-danger fw-bold':($exp?'text-warning':'') ?>"><?= e(fdate($p['expiry_date'])) ?></td>
  <td class="text-end">
    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#rs_<?= (int)$p['id'] ?>">
      <i class="bi bi-box-arrow-in-down"></i> Restock</button>
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('admin/products.php?action=edit&id=' . (int)$p['id']) ?>">
      <i class="bi bi-pencil"></i></a>
  </td>
</tr>
<tr class="collapse" id="rs_<?= (int)$p['id'] ?>">
  <td colspan="7" class="bg-light">
    <form class="row g-2 align-items-end" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="restock">
      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <div class="col-md-2"><label class="form-label small">Qty added</label>
        <input class="form-control form-control-sm" type="number" min="1" name="qty" required></div>
      <div class="col-md-3"><label class="form-label small">New batch # (optional)</label>
        <input class="form-control form-control-sm" name="batch_no"></div>
      <div class="col-md-3"><label class="form-label small">Expiry (optional)</label>
        <input class="form-control form-control-sm" type="date" name="expiry_date"></div>
      <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Save</button></div>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
