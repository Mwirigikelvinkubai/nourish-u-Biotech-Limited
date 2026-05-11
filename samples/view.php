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
        $pdo->prepare("UPDATE sample_drops SET status='dropped', drop_date=?, next_pickup_date=? WHERE id=?")
            ->execute([
                post('drop_date') ?: date('Y-m-d'),
                post('next_pickup_date') ?: null,
                $id
            ]);
        audit($pdo,'sample_drop.dropped','sample_drop',$id);
        flash('success','Marked as dropped.');
    }
    elseif ($op === 'reschedule') {
        $newDate = post('next_pickup_date') ?: null;
        $reason  = clean(post('reschedule_reason'));
        if (!$newDate) {
            flash('danger','Pick a new pickup date.');
            redirect(url('samples/view.php?id=' . $id));
        }
        $pdo->prepare("UPDATE sample_drops
                          SET status='rescheduled',
                              next_pickup_date=?,
                              reschedule_reason=?
                        WHERE id=?")
            ->execute([$newDate, $reason, $id]);
        audit($pdo,'sample_drop.rescheduled','sample_drop',$id,$reason);
        flash('info','Pickup rescheduled to ' . fdate($newDate));
    }
    elseif ($op === 'mark_no_show') {
        $pdo->prepare("UPDATE sample_drops SET status='no_show', reschedule_reason=? WHERE id=?")
            ->execute([clean(post('reschedule_reason')) ?: 'No show', $id]);
        audit($pdo,'sample_drop.no_show','sample_drop',$id);
        flash('warning','Marked as no-show.');
    }
    elseif ($op === 'mark_pickedup') {
        $payment = (float)post('payment_collected');
        $method  = clean(post('payment_method'));

        $pdo->prepare("UPDATE sample_drops
                          SET status='picked_up',
                              pickup_date=?,
                              payment_collected=?,
                              payment_method=?
                        WHERE id=?")
            ->execute([
                post('pickup_date') ?: date('Y-m-d'),
                $payment,
                $method,
                $id
            ]);
        // Record used + returned per item; return unused units to stock
        foreach ((array)post('items', []) as $itemId => $vals) {
            $used     = (int)($vals['used'] ?? 0);
            $returned = (int)($vals['returned'] ?? 0);
            $pdo->prepare("UPDATE sample_drop_items SET qty_used=?, qty_returned=? WHERE id=? AND drop_id=?")
                ->execute([$used, $returned, (int)$itemId, $id]);
            if ($returned > 0) {
                $pdo->prepare("UPDATE products p
                                  JOIN sample_drop_items si ON si.product_id = p.id
                                 SET p.stock_qty = p.stock_qty + ?
                                WHERE si.id = ?")->execute([$returned, (int)$itemId]);
            }
        }
        audit($pdo,'sample_drop.picked_up','sample_drop',$id, $payment > 0 ? money($payment) : null);
        flash('success', $payment > 0
            ? 'Pickup confirmed. Payment of ' . money($payment) . ' recorded.'
            : 'Pickup confirmed. Unused units returned to stock.');
    }
    elseif ($op === 'cancel') {
        $pdo->prepare("UPDATE sample_drops SET status='cancelled' WHERE id=?")->execute([$id]);
        audit($pdo,'sample_drop.cancel','sample_drop',$id);
        flash('info','Drop cancelled.');
    }
    elseif ($op === 'soft_delete') {
        if (sd_soft_delete($pdo, 'sample_drops', $id, (string)post('reason',''))) {
            flash('success','Drop archived.');
            redirect(url('samples/index.php'));
        }
    }
    elseif ($op === 'restore' && $u['role']==='admin') {
        sd_restore($pdo, 'sample_drops', $id);
        flash('success','Drop restored.');
    }
    redirect(url('samples/view.php?id=' . $id));
}

$items = $pdo->prepare("SELECT si.*, p.sku, p.name FROM sample_drop_items si JOIN products p ON p.id=si.product_id WHERE drop_id=?");
$items->execute([$id]); $items = $items->fetchAll();

$page_title = 'Sample drop #' . $sd['id'];
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start mb-3">
  <h3 class="mb-0">Sample drop #<?= (int)$sd['id'] ?>
    <?php if (!empty($sd['deleted_at'])): ?><span class="badge badge-danger">Archived</span><?php endif; ?>
  </h3>
  <div>
    <?php if (empty($sd['deleted_at'])): ?>
      <button class="btn btn-outline-danger" onclick="sdConfirm(<?= (int)$sd['id'] ?>)"><i class="bi bi-trash"></i> Delete</button>
    <?php elseif ($u['role']==='admin'): ?>
      <form method="post" class="d-inline" onsubmit="return confirm('Restore?');">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="restore">
        <button class="btn btn-outline-success"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3"><div class="card-body">
  <div class="row">
    <div class="col-md-6">
      <p class="mb-1"><strong>Client:</strong> <a href="<?= url('clients/view.php?id=' . (int)$sd['client_id']) ?>"><?= e($sd['client']) ?></a></p>
      <p class="mb-1"><strong>Rep:</strong> <?= e($sd['rep']) ?></p>
      <p class="mb-1"><strong>Status:</strong>
        <?php $cls = ['scheduled'=>'badge-info','dropped'=>'badge-warning','rescheduled'=>'badge-purple','no_show'=>'badge-danger','picked_up'=>'badge-soft','cancelled'=>'badge-secondary'][$sd['status']] ?? 'badge-secondary'; ?>
        <span class="badge <?= $cls ?>"><?= e(ucfirst(str_replace('_',' ',$sd['status']))) ?></span></p>
    </div>
    <div class="col-md-6">
      <p class="mb-1"><strong>Scheduled:</strong> <?= e(fdate($sd['scheduled_date'])) ?></p>
      <p class="mb-1"><strong>Dropped:</strong> <?= e(fdate($sd['drop_date'])) ?></p>
      <p class="mb-1"><strong>Next pickup:</strong>
        <?php if (!empty($sd['next_pickup_date'])): ?>
          <span class="fw-bold text-primary"><?= e(fdate($sd['next_pickup_date'])) ?></span>
        <?php else: ?>—<?php endif; ?>
      </p>
      <p class="mb-1"><strong>Picked up:</strong> <?= e(fdate($sd['pickup_date'])) ?>
        <?php if ((float)$sd['payment_collected'] > 0): ?>
          &middot; collected <span class="fw-semibold"><?= money($sd['payment_collected']) ?></span>
          <?php if ($sd['payment_method']): ?> via <?= e($sd['payment_method']) ?><?php endif; ?>
        <?php endif; ?>
      </p>
      <?php if (!empty($sd['reschedule_reason'])): ?>
        <p class="mb-1 small"><strong>Reschedule / no-show reason:</strong> <?= e($sd['reschedule_reason']) ?></p>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!empty($sd['notes'])): ?>
    <p class="mb-0 small text-muted mt-2"><?= nl2br(e($sd['notes'])) ?></p>
  <?php endif; ?>
</div></div>

<div class="card mb-3"><div class="table-responsive">
<form method="post">
  <?= csrf_field() ?>
  <table class="table table-clean mb-0">
  <thead><tr><th>SKU</th><th>Product</th><th class="text-end">Dropped</th>
    <?php if ($sd['status']==='dropped' || $sd['status']==='rescheduled'): ?>
      <th class="text-end">Used</th><th class="text-end">Returned</th>
    <?php else: ?>
      <th class="text-end">Used</th><th class="text-end">Returned</th>
    <?php endif; ?>
  </tr></thead>
  <tbody>
  <?php foreach ($items as $it): ?>
    <tr>
      <td class="font-monospace small"><?= e($it['sku']) ?></td>
      <td><?= e($it['name']) ?></td>
      <td class="text-end"><?= (int)$it['qty_dropped'] ?></td>
      <?php if (in_array($sd['status'], ['dropped','rescheduled'], true)): ?>
        <td class="text-end" style="width:120px;">
          <input class="form-control form-control-sm" type="number" min="0" max="<?= (int)$it['qty_dropped'] ?>"
                 name="items[<?= (int)$it['id'] ?>][used]" value="<?= (int)$it['qty_used'] ?>"></td>
        <td class="text-end" style="width:120px;">
          <input class="form-control form-control-sm" type="number" min="0" max="<?= (int)$it['qty_dropped'] ?>"
                 name="items[<?= (int)$it['id'] ?>][returned]" value="<?= (int)$it['qty_returned'] ?>"></td>
      <?php else: ?>
        <td class="text-end"><?= (int)$it['qty_used'] ?></td>
        <td class="text-end"><?= (int)$it['qty_returned'] ?></td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody></table>

  <div class="p-3">
    <?php if ($sd['status']==='scheduled'): ?>
      <h6 class="text-muted text-uppercase small mb-2">Step 1 - Mark as dropped</h6>
      <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">Drop date</label>
          <input class="form-control" type="date" name="drop_date" value="<?= e(date('Y-m-d')) ?>"></div>
        <div class="col-md-3"><label class="form-label small">Schedule pickup for</label>
          <input class="form-control" type="date" name="next_pickup_date"
                 value="<?= e(date('Y-m-d', strtotime('+7 days'))) ?>"></div>
        <div class="col-md-3">
          <button class="btn btn-warning w-100" name="op" value="mark_dropped">
            <i class="bi bi-truck"></i> Mark as dropped</button>
        </div>
        <div class="col-md-3">
          <button class="btn btn-outline-danger w-100" name="op" value="cancel"
                  onclick="return confirm('Cancel this drop?');">
            <i class="bi bi-x-circle"></i> Cancel drop</button>
        </div>
      </div>
    <?php elseif (in_array($sd['status'], ['dropped','rescheduled'], true)): ?>

      <h6 class="text-muted text-uppercase small mb-2">Pickup outcome</h6>

      <ul class="nav nav-pills mb-3" role="tablist">
        <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-pickup">
          <i class="bi bi-check2-circle"></i> Confirm pickup</button></li>
        <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-resched">
          <i class="bi bi-arrow-repeat"></i> Reschedule</button></li>
        <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-noshow">
          <i class="bi bi-person-x"></i> No-show</button></li>
      </ul>

      <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-pickup">
          <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small">Pickup date</label>
              <input class="form-control" type="date" name="pickup_date" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="col-md-3"><label class="form-label small">Payment collected (<?= e(APP_CURRENCY) ?>)</label>
              <input class="form-control" type="number" step="0.01" min="0" name="payment_collected" value="0"></div>
            <div class="col-md-3"><label class="form-label small">Payment method</label>
              <select class="form-select" name="payment_method">
                <option value="">— None —</option>
                <option>Cash</option>
                <option>M-Pesa</option>
                <option>Bank transfer</option>
                <option>Cheque</option>
              </select></div>
            <div class="col-md-3">
              <button class="btn btn-primary w-100" name="op" value="mark_pickedup">
                <i class="bi bi-check2-circle"></i> Confirm pickup</button>
            </div>
            <div class="col-12 small text-muted">
              The <em>Used</em> and <em>Returned</em> counts above will be saved. Returned units are added back to stock automatically.
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="tab-resched">
          <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small">New pickup date</label>
              <input class="form-control" type="date" name="next_pickup_date"
                     value="<?= e(date('Y-m-d', strtotime('+7 days'))) ?>"></div>
            <div class="col-md-6"><label class="form-label small">Reason</label>
              <input class="form-control" name="reschedule_reason" placeholder="Client requested, stock not finished, etc."></div>
            <div class="col-md-3">
              <button class="btn btn-purple w-100" name="op" value="reschedule">
                <i class="bi bi-arrow-repeat"></i> Reschedule</button>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="tab-noshow">
          <div class="row g-2 align-items-end">
            <div class="col-md-9"><label class="form-label small">Note</label>
              <input class="form-control" name="reschedule_reason" placeholder="Client unavailable, premises closed, etc."></div>
            <div class="col-md-3">
              <button class="btn btn-outline-danger w-100" name="op" value="mark_no_show"
                      onclick="return confirm('Mark this pickup as a no-show?');">
                <i class="bi bi-person-x"></i> Mark no-show</button>
            </div>
          </div>
        </div>
      </div>

    <?php endif; ?>
  </div>
</form>
</div></div>

<?= sd_modal_html() ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
