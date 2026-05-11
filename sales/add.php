<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$preselectClient = (int)get('client_id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $clientId = (int)post('client_id');
    if ($u['role'] === 'rep') $repId = (int)$u['id'];
    else $repId = (int)post('rep_id');

    $saleDate = post('sale_date') ?: date('Y-m-d');
    $taxPct   = (float)post('tax_pct');
    $discount = (float)post('discount');
    $payStat  = post('payment_status') ?: 'unpaid';
    $paidAmt  = (float)post('paid_amount');
    $payMth   = clean(post('payment_method'));
    $notes    = clean(post('notes'));

    $items = [];
    foreach ((array)post('items', []) as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        $qty = (int)($it['qty'] ?? 0);
        $up  = (float)($it['unit_price'] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $items[] = ['product_id'=>$pid,'qty'=>$qty,'unit_price'=>$up];
        }
    }

    if (!$clientId || !$repId || !$items) {
        flash('danger','Please pick a client, a rep, and at least one product line.');
        redirect(url('sales/add.php' . ($preselectClient ? '?client_id=' . $preselectClient : '')));
    }

    /* Compute totals using current product commission % */
    $subtotal = 0;
    foreach ($items as &$it) {
        $stmt = $pdo->prepare("SELECT base_commission_pct FROM products WHERE id=?");
        $stmt->execute([$it['product_id']]);
        $pct = (float)$stmt->fetchColumn();
        $it['line_total']        = round($it['qty'] * $it['unit_price'], 2);
        $it['commission_pct']    = $pct;
        $it['commission_amount'] = round($it['line_total'] * $pct / 100, 2);
        $subtotal += $it['line_total'];
    }
    unset($it);

    $taxAmt = round(($subtotal - $discount) * $taxPct / 100, 2);
    $total  = round($subtotal - $discount + $taxAmt, 2);
    if ($paidAmt <= 0)            $payStat = 'unpaid';
    elseif ($paidAmt < $total)    $payStat = 'partial';
    else                          $payStat = 'paid';

    $invoiceNo = next_invoice_no($pdo);

    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            "INSERT INTO sales (invoice_no, client_id, rep_id, sale_date, subtotal, tax_pct,
                                 tax_amount, discount, total, payment_status, paid_amount,
                                 payment_method, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([$invoiceNo, $clientId, $repId, $saleDate, $subtotal, $taxPct,
                    $taxAmt, $discount, $total, $payStat, $paidAmt, $payMth, $notes]);
        $saleId = (int)$pdo->lastInsertId();

        $stmtItem = $pdo->prepare(
            "INSERT INTO sale_items (sale_id, product_id, qty, unit_price,
                                      line_total, commission_pct, commission_amount)
             VALUES (?,?,?,?,?,?,?)"
        );
        $stmtStock = $pdo->prepare("UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id=?");
        foreach ($items as $it) {
            $stmtItem->execute([$saleId, $it['product_id'], $it['qty'], $it['unit_price'],
                                 $it['line_total'], $it['commission_pct'], $it['commission_amount']]);
            $stmtStock->execute([$it['qty'], $it['product_id']]);
        }
        $pdo->commit();
        audit($pdo,'sale.create','sale',$saleId,$invoiceNo);
        flash('success','Sale recorded — invoice ' . $invoiceNo);
        redirect(url('sales/view.php?id=' . $saleId));
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('danger','Failed to save sale: ' . $e->getMessage());
        redirect(url('sales/add.php'));
    }
}

$clients = $pdo->query("SELECT id,name FROM clients ORDER BY name")->fetchAll();
$reps    = $pdo->query("SELECT id,name FROM users WHERE role='rep' AND status='active' ORDER BY name")->fetchAll();
$products= $pdo->query("SELECT id,sku,name,price,stock_qty,base_commission_pct FROM products WHERE status='active' ORDER BY name")->fetchAll();
$vatDef  = (float)setting($pdo,'vat_default_pct','16');

$page_title = 'New sale';
require __DIR__ . '/../includes/header.php';
?>
<h3 class="mb-3">New sale / invoice</h3>

<form method="post" class="card p-3" id="saleForm">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-5"><label class="form-label">Client *</label>
      <select class="form-select" name="client_id" required>
        <option value="">— Select client —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $preselectClient===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php if ($u['role'] !== 'rep'): ?>
    <div class="col-md-3"><label class="form-label">Rep *</label>
      <select class="form-select" name="rep_id" required>
        <option value="">— Select rep —</option>
        <?php foreach ($reps as $r): ?>
          <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="col-md-2"><label class="form-label">Date</label>
      <input class="form-control" type="date" name="sale_date" value="<?= e(date('Y-m-d')) ?>"></div>
    <div class="col-md-2"><label class="form-label">VAT %</label>
      <input class="form-control" type="number" step="0.01" min="0" name="tax_pct" id="tax_pct" value="<?= e($vatDef) ?>"></div>
  </div>

  <hr>

  <h6>Line items</h6>
  <div id="lines"></div>
  <button type="button" class="btn btn-outline-primary btn-sm" onclick="addLine()">
    <i class="bi bi-plus"></i> Add line</button>

  <hr>

  <div class="row g-3">
    <div class="col-md-3"><label class="form-label">Discount</label>
      <input class="form-control" type="number" step="0.01" min="0" name="discount" id="discount" value="0"></div>
    <div class="col-md-3"><label class="form-label">Amount paid</label>
      <input class="form-control" type="number" step="0.01" min="0" name="paid_amount" id="paid" value="0"></div>
    <div class="col-md-3"><label class="form-label">Payment method</label>
      <input class="form-control" name="payment_method"></div>
    <div class="col-md-3"><label class="form-label">Status</label>
      <select class="form-select" name="payment_status">
        <option value="unpaid">Unpaid</option>
        <option value="partial">Partial</option>
        <option value="paid">Paid</option>
      </select></div>
    <div class="col-12"><label class="form-label">Notes</label>
      <textarea class="form-control" name="notes" rows="2"></textarea></div>
  </div>

  <div class="row mt-3">
    <div class="col-md-6 ms-auto">
      <table class="table table-sm">
        <tr><th>Subtotal</th><td class="text-end" id="t_sub">0.00</td></tr>
        <tr><th>VAT</th><td class="text-end" id="t_tax">0.00</td></tr>
        <tr><th>Discount</th><td class="text-end" id="t_dis">0.00</td></tr>
        <tr><th>Total</th><td class="text-end fw-bold" id="t_tot">0.00</td></tr>
      </table>
    </div>
  </div>

  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary"><i class="bi bi-save"></i> Save sale</button>
    <a class="btn btn-outline-secondary" href="<?= url('sales/index.php') ?>">Cancel</a>
  </div>
</form>

<script>
const PRODUCTS = <?= json_encode($products) ?>;
const fmt = n => Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});

function addLine(){
  const i = document.querySelectorAll('.line').length;
  const opts = PRODUCTS.map(p =>
    `<option value="${p.id}" data-price="${p.price}" data-stock="${p.stock_qty}">
       ${p.sku} — ${p.name} (${p.stock_qty} in stock)
     </option>`).join('');
  const div = document.createElement('div');
  div.className = 'line row g-2 align-items-end mb-2';
  div.innerHTML = `
    <div class="col-md-6"><label class="form-label small">Product</label>
      <select class="form-select" name="items[${i}][product_id]" required onchange="onProd(this)">
        <option value="">— Choose —</option>${opts}
      </select></div>
    <div class="col-md-2"><label class="form-label small">Qty</label>
      <input class="form-control qty" type="number" min="1" value="1" name="items[${i}][qty]" oninput="recalc()"></div>
    <div class="col-md-3"><label class="form-label small">Unit price</label>
      <input class="form-control up" type="number" step="0.01" min="0" name="items[${i}][unit_price]" oninput="recalc()"></div>
    <div class="col-md-1 text-end">
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.line').remove(); recalc();">
        <i class="bi bi-trash"></i></button></div>`;
  document.getElementById('lines').appendChild(div);
}
function onProd(sel){
  const opt = sel.options[sel.selectedIndex];
  const up  = sel.closest('.line').querySelector('.up');
  if (opt && opt.dataset.price) up.value = opt.dataset.price;
  recalc();
}
function recalc(){
  let sub = 0;
  document.querySelectorAll('.line').forEach(l => {
    const q = parseFloat(l.querySelector('.qty').value)||0;
    const p = parseFloat(l.querySelector('.up').value)||0;
    sub += q*p;
  });
  const dis = parseFloat(document.getElementById('discount').value)||0;
  const taxPct = parseFloat(document.getElementById('tax_pct').value)||0;
  const tax = (sub - dis) * taxPct / 100;
  const tot = sub - dis + tax;
  document.getElementById('t_sub').innerText = fmt(sub);
  document.getElementById('t_tax').innerText = fmt(tax);
  document.getElementById('t_dis').innerText = fmt(dis);
  document.getElementById('t_tot').innerText = fmt(tot);
}
['discount','paid','tax_pct'].forEach(id => document.getElementById(id).addEventListener('input', recalc));
addLine();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
