<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$id = (int)get('id');
$stmt = $pdo->prepare("SELECT sd.*, c.name AS client, u.name AS rep
                         FROM sample_drops sd
                         JOIN clients c ON c.id = sd.client_id
                         JOIN users u ON u.id = sd.rep_id
                        WHERE sd.id=?");
$stmt->execute([$id]); $sd = $stmt->fetch();
if (!$sd) { http_response_code(404); die('Not found.'); }
if ($u['role']==='rep' && (int)$sd['rep_id'] !== (int)$u['id']) { http_response_code(403); die('Not allowed.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    if ($op === 'mark_dropped') {
        $pdo->prepare("UPDATE sample_drops SET status='dropped', drop_date=? WHERE id=?")
            ->execute([post('drop_date') ?: date('Y-m-d'), $id]);
        audit($pdo,'sample_drop.dropped','sample_drop',$id);
        flash('success','Marked as dropped.');
    } elseif ($op === 'mark_pickedup') {
        $pdo->prepare("UPDATE sample_drops SET status='picked_up', pickup_date=? WHERE id=?")
            ->execute([post('pickup_date') ?: date('Y-m-d'), $id]);
        // record returns/used qty per item
        foreach ((array)post('items', []) as $itemId => $vals) {
            $used     = (int)($vals['used'] ?? 0);
            $returned = (int)($vals['returned'] ?? 0);
            $pdo->prepare("UPDATE sample_drop_items SET qty_used=?, qty_returned=? WHERE id=? AND drop_id=?")
                ->execute([$used, $returned, (int)$itemId, $id]);
            // return unused to product stock
            if ($returned > 0) {
                $pdo->prepare("UPDATE products p
                                  JOIN sample_drop_items si ON si.product_id = p.id
                                 SET p.stock_qty = p.stock_qty + ?
                                WHERE si.id = ?")->execute([$returned, (int)$itemId]);
            }
        }
        audit($pdo,'sample_drop.picked_up','sample_drop',$id);
        flash('success','Marked as picked up. Unused units returned to stock.');
    } elseif ($op === 'cancel') {
        $pdo->prepare("UPDATE sample_drops SET status='cancelled' WHERE id=?")->execute([$id]);
        audit($pdo,'sample_drop.cancel','sample_drop',$id);
        flash('info','Drop cancelled.');
    }
    redirect(url('samples/view.php?id=' . $id));
}

$items = $pdo->prepare("SELECT si.*, p.sku, p.name FROM sample_drop_items si JOIN products p ON p.id=si.product_id WHERE drop_id=?");
$items->execute([$id]); $items = $items->fetchAll();

$page_title = 'Sample drop #' . $sd['id'];
require __DIR__ . '/../includes/header.php';
?>
<h3 class="mb-3">Sample drop #<?= (int)$sd['id'] ?></h3>

<div class="card mb-3"><div class="card-body">
  <div class="row">
    <div class="col-md-6">
      <p class="mb-1"><strong>Client:</strong> <a href="<?= url('clients/view.php?id=' . (int)$sd['client_id']) ?>"><?= e($sd['client']) ?></a></p>
      <p class="mb-1"><strong>Rep:</strong> <?= e($sd['rep']) ?></p>
    </div>
    <div class="col-md-6">
      <p class="mb-1"><strong>Scheduled:</strong> <?= e(fdate($sd['scheduled_date'])) ?></p>
      <p class="mb-1"><strong>Dropped:</strong> <?= e(fdate($sd['drop_date'])) ?></p>
      <p class="mb-1"><strong>Picked up:</strong> <?= e(fdate($sd['pickup_date'])) ?></p>
      <p class="mb-1"><strong>Status:</strong>
        <?php $cls = ['scheduled'=>'badge-info','dropped'=>'badge-warning','picked_up'=>'badge-soft','cancelled'=>'badge-secondary'][$sd['status']] ?? 'badge-secondary'; ?>
        <span class="badge <?= $cls ?>"><?= e(ucfirst(str_replace('_',' ',$sd['status']))) ?></span></p>
    </div>
  </div>
  <p class="mb-0 small text-muted"><?= nl2br(e($sd['notes'])) ?></p>
</div></div>

<div class="card mb-3"><div class="table-responsive">
<form method="post">
  <?= csrf_field() ?>
  <table class="table table-clean mb-0">
  <thead><tr><th>SKU</th><th>Product</th><th class="text-end">Dropped</th>
    <?php if ($sd['status']==='dropped'): ?><th class="text-end">Used</th><th class="text-end">Returned</th><?php endif; ?>
    <?php if ($sd['status']==='picked_up'): ?><th class="text-end">Used</th><th class="text-end">Returned</th><?php endif; ?>
  </tr></thead>
  <tbody>
  <?php foreach ($items as $it): ?>
    <tr>
      <td class="font-monospace small"><?= e($it['sku']) ?></td>
      <td><?= e($it['name']) ?></td>
      <td class="text-end"><?= (int)$it['qty_dropped'] ?></td>
      <?php if ($sd['status']==='dropped'): ?>
        <td class="text-end" style="width:120px;">
          <input class="form-control form-control-sm" type="number" min="0" max="<?= (int)$it['qty_dropped'] ?>" name="items[<?= (int)$it['id'] ?>][used]" value="<?= (int)$it['qty_used'] ?>"></td>
        <td class="text-end" style="width:120px;">
          <input class="form-control form-control-sm" type="number" min="0" max="<?= (int)$it['qty_dropped'] ?>" name="items[<?= (int)$it['id'] ?>][returned]" value="<?= (int)$it['qty_returned'] ?>"></td>
      <?php elseif ($sd['status']==='picked_up'): ?>
        <td class="text-end"><?= (int)$it['qty_used'] ?></td>
        <td class="text-end"><?= (int)$it['qty_returned'] ?></td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody></table>

  <div class="p-3 d-flex gap-2 flex-wrap">
    <?php if ($sd['status']==='scheduled'): ?>
      <input type="hidden" name="op" value="mark_dropped">
      <input class="form-control" type="date" name="drop_date" value="<?= e(date('Y-m-d')) ?>" style="max-width:200px;">
      <button class="btn btn-warning"><i class="bi bi-truck"></i> Mark as dropped</button>
      <button class="btn btn-outline-danger" type="submit" formaction="" name="op" value="cancel"
              onclick="return confirm('Cancel this drop?');"><i class="bi bi-x-circle"></i> Cancel</button>
    <?php elseif ($sd['status']==='dropped'): ?>
      <input type="hidden" name="op" value="mark_pickedup">
      <input class="form-control" type="date" name="pickup_date" value="<?= e(date('Y-m-d')) ?>" style="max-width:200px;">
      <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Confirm pickup</button>
    <?php endif; ?>
  </div>
</form>
</div></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
