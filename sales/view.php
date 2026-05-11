<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$id = (int)get('id');
$stmt = $pdo->prepare("SELECT s.*, c.name AS client, c.address, c.phone AS client_phone, c.kra_pin,
                              u.name AS rep
                         FROM sales s
                         JOIN clients c ON c.id = s.client_id
                         JOIN users   u ON u.id = s.rep_id
                        WHERE s.id = ?");
$stmt->execute([$id]); $s = $stmt->fetch();
if (!$s) { http_response_code(404); die('Sale not found.'); }
if ($u['role'] === 'rep' && (int)$s['rep_id'] !== (int)$u['id']) { http_response_code(403); die('Not allowed.'); }

$items = $pdo->prepare("SELECT si.*, p.sku, p.name FROM sale_items si JOIN products p ON p.id=si.product_id WHERE sale_id=?");
$items->execute([$id]); $items = $items->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    if ($op === 'soft_delete') {
        // Rep may delete own sale; admin/accountant any sale
        if ($u['role'] === 'rep' && (int)$s['rep_id'] !== (int)$u['id']) {
            flash('danger','Not allowed.'); redirect(url('sales/view.php?id=' . $id));
        }
        if (sd_soft_delete($pdo, 'sales', $id, (string)post('reason',''))) {
            flash('success','Sale archived.');
            redirect(url('sales/index.php'));
        }
        flash('danger','Could not delete.');
        redirect(url('sales/view.php?id=' . $id));
    }
    if ($op === 'restore' && $u['role'] === 'admin') {
        sd_restore($pdo, 'sales', $id);
        flash('success','Sale restored.');
        redirect(url('sales/view.php?id=' . $id));
    }

    if ($op === 'pay') {
        $add = (float)post('amount');
        $newPaid = round($s['paid_amount'] + $add, 2);
        $newStat = $newPaid >= $s['total'] ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
        $pdo->prepare("UPDATE sales SET paid_amount=?, payment_status=? WHERE id=?")
            ->execute([$newPaid, $newStat, $id]);
        audit($pdo,'sale.payment','sale',$id, money($add));
        flash('success','Payment recorded.');
        redirect(url('sales/view.php?id=' . $id));
    }
}

$page_title = $s['invoice_no'];
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-0">Invoice <?= e($s['invoice_no']) ?>
      <?php if (!empty($s['deleted_at'])): ?><span class="badge badge-danger">Archived</span><?php endif; ?>
    </h3>
    <div class="text-muted small"><?= e($s['client']) ?> · <?= e(fdate($s['sale_date'])) ?> · Rep <?= e($s['rep']) ?></div>
  </div>
  <div>
    <a class="btn btn-outline-primary" href="<?= url('sales/invoice.php?id=' . $id) ?>" target="_blank">
      <i class="bi bi-printer"></i> Print invoice
    </a>
    <?php if (empty($s['deleted_at']) && ($u['role']!=='rep' || (int)$s['rep_id']===(int)$u['id'])): ?>
      <button class="btn btn-outline-danger" onclick="sdConfirm(<?= (int)$s['id'] ?>)">
        <i class="bi bi-trash"></i> Delete sale</button>
    <?php elseif (!empty($s['deleted_at']) && $u['role']==='admin'): ?>
      <form method="post" class="d-inline" onsubmit="return confirm('Restore this sale?');">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="restore">
        <button class="btn btn-outline-success"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-3"><div class="table-responsive">
<table class="table table-clean mb-0">
<thead><tr><th>SKU</th><th>Product</th><th class="text-end">Qty</th>
<th class="text-end">Unit price</th><th class="text-end">Line total</th>
<th class="text-end">Comm %</th><th class="text-end">Commission</th></tr></thead>
<tbody>
<?php foreach ($items as $it): ?>
<tr>
  <td class="font-monospace small"><?= e($it['sku']) ?></td>
  <td><?= e($it['name']) ?></td>
  <td class="text-end"><?= (int)$it['qty'] ?></td>
  <td class="text-end"><?= money($it['unit_price']) ?></td>
  <td class="text-end"><?= money($it['line_total']) ?></td>
  <td class="text-end"><?= e($it['commission_pct']) ?>%</td>
  <td class="text-end"><?= money($it['commission_amount']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
  <tr><th colspan="4" class="text-end">Subtotal</th><th class="text-end"><?= money($s['subtotal']) ?></th><th colspan="2"></th></tr>
  <tr><th colspan="4" class="text-end">Discount</th><th class="text-end">- <?= money($s['discount']) ?></th><th colspan="2"></th></tr>
  <tr><th colspan="4" class="text-end">VAT (<?= e($s['tax_pct']) ?>%)</th><th class="text-end"><?= money($s['tax_amount']) ?></th><th colspan="2"></th></tr>
  <tr><th colspan="4" class="text-end">Total</th><th class="text-end"><?= money($s['total']) ?></th><th colspan="2"></th></tr>
  <tr><th colspan="4" class="text-end">Paid</th><th class="text-end"><?= money($s['paid_amount']) ?></th><th colspan="2"></th></tr>
  <tr><th colspan="4" class="text-end">Balance</th><th class="text-end fw-bold"><?= money($s['total'] - $s['paid_amount']) ?></th><th colspan="2"></th></tr>
</tfoot>
</table>
</div></div>

<?php if ($s['payment_status'] !== 'paid'): ?>
<div class="card p-3" style="max-width:520px;">
  <h6>Record a payment</h6>
  <form method="post" class="row g-2 align-items-end">
    <?= csrf_field() ?>
    <input type="hidden" name="op" value="pay">
    <div class="col-md-7"><label class="form-label small">Amount paid</label>
      <input class="form-control" type="number" step="0.01" min="0.01" name="amount"
             max="<?= e($s['total'] - $s['paid_amount']) ?>" required></div>
    <div class="col-md-5"><button class="btn btn-primary w-100"><i class="bi bi-cash"></i> Add payment</button></div>
  </form>
</div>
<?php endif; ?>

<?= sd_modal_html() ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
