<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

/* ── Does disc_pct column exist? (migration_v1_4) ───────────────────── */
$hasDiscCol = false;
try { $pdo->query("SELECT disc_pct FROM sale_items LIMIT 0"); $hasDiscCol = true; }
catch (PDOException $e) { $hasDiscCol = false; }

/* ── AJAX: quick-add client ─────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'quick_client') {
    header('Content-Type: application/json');
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) {
        echo json_encode(['ok'=>false,'error'=>'Session expired — refresh and try again']); exit;
    }
    $name  = clean(post('name'));
    $phone = clean(post('phone'));
    $type  = in_array(post('type'), ['pharmacy','clinic','hospital','wholesaler','other'])
             ? post('type') : 'other';
    $repId = $u['role'] === 'rep' ? (int)$u['id'] : null;
    if (!$name) { echo json_encode(['ok'=>false,'error'=>'Client name is required']); exit; }
    $pdo->prepare(
        "INSERT INTO clients (name, phone, type, kyc_status, rep_id) VALUES (?,?,?,'pending',?)"
    )->execute([$name, $phone, $type, $repId]);
    echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId(),'name'=>$name]);
    exit;
}

/* ── AJAX: quick-add product ────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'quick_product') {
    header('Content-Type: application/json');
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) {
        echo json_encode(['ok'=>false,'error'=>'Session expired — refresh and try again']); exit;
    }
    $name  = clean(post('name'));
    $sku   = clean(post('sku'));
    $price = (float)post('price');
    if (!$name || !$sku)  { echo json_encode(['ok'=>false,'error'=>'Name and SKU are required']); exit; }
    if ($price <= 0)      { echo json_encode(['ok'=>false,'error'=>'Price must be greater than 0']); exit; }
    $dup = $pdo->prepare("SELECT id FROM products WHERE sku=?"); $dup->execute([$sku]);
    if ($dup->fetch())    { echo json_encode(['ok'=>false,'error'=>'SKU already exists — use a different one']); exit; }
    $pdo->prepare("INSERT INTO products (sku,name,price,status) VALUES (?,?,?,'active')")
        ->execute([$sku, $name, $price]);
    echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId(),'name'=>$name,'sku'=>$sku,'price'=>$price,'stock_qty'=>0]);
    exit;
}

$preselectClient = (int)get('client_id', 0);

/* ── Regular form POST ──────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $clientId = (int)post('client_id');
    $repId    = $u['role'] === 'rep' ? (int)$u['id'] : (int)post('rep_id');
    $saleDate = post('sale_date') ?: date('Y-m-d');
    $taxPct   = (float)post('tax_pct');
    $discount = (float)post('discount');
    $paidAmt  = (float)post('paid_amount');
    $payMth   = clean(post('payment_method'));
    $notes    = clean(post('notes'));

    $items = [];
    foreach ((array)post('items', []) as $it) {
        $pid     = (int)($it['product_id'] ?? 0);
        $qty     = (int)($it['qty'] ?? 0);
        $up      = (float)($it['unit_price'] ?? 0);
        $discPct = min(100, max(0, (float)($it['disc_pct'] ?? 0)));
        if ($pid > 0 && $qty > 0) {
            $items[] = ['product_id'=>$pid,'qty'=>$qty,'unit_price'=>$up,'disc_pct'=>$discPct];
        }
    }

    if (!$clientId || !$repId || !$items) {
        flash('danger','Please pick a client, a rep, and at least one product line.');
        redirect(url('sales/add.php' . ($preselectClient ? '?client_id='.$preselectClient : '')));
    }

    $subtotal = 0;
    foreach ($items as &$it) {
        $stmt = $pdo->prepare("SELECT base_commission_pct FROM products WHERE id=?");
        $stmt->execute([$it['product_id']]);
        $pct  = (float)$stmt->fetchColumn();
        $net  = $it['unit_price'] * (1 - $it['disc_pct'] / 100);
        $it['line_total']        = round($it['qty'] * $net, 2);
        $it['commission_pct']    = $pct;
        $it['commission_amount'] = round($it['line_total'] * $pct / 100, 2);
        $subtotal += $it['line_total'];
    }
    unset($it);

    $taxAmt = round(($subtotal - $discount) * $taxPct / 100, 2);
    $total  = round($subtotal - $discount + $taxAmt, 2);
    if ($paidAmt <= 0)         $payStat = 'unpaid';
    elseif ($paidAmt < $total) $payStat = 'partial';
    else                       $payStat = 'paid';

    $invoiceNo = next_invoice_no($pdo);

    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            "INSERT INTO sales (invoice_no,client_id,rep_id,sale_date,subtotal,tax_pct,
                                 tax_amount,discount,total,payment_status,paid_amount,payment_method,notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([$invoiceNo,$clientId,$repId,$saleDate,$subtotal,$taxPct,
                    $taxAmt,$discount,$total,$payStat,$paidAmt,$payMth,$notes]);
        $saleId = (int)$pdo->lastInsertId();

        if ($hasDiscCol) {
            $stmtItem = $pdo->prepare(
                "INSERT INTO sale_items (sale_id,product_id,qty,unit_price,disc_pct,
                                          line_total,commission_pct,commission_amount)
                 VALUES (?,?,?,?,?,?,?,?)"
            );
        } else {
            $stmtItem = $pdo->prepare(
                "INSERT INTO sale_items (sale_id,product_id,qty,unit_price,
                                          line_total,commission_pct,commission_amount)
                 VALUES (?,?,?,?,?,?,?)"
            );
        }
        $stmtStock = $pdo->prepare("UPDATE products SET stock_qty=GREATEST(0,stock_qty-?) WHERE id=?");

        foreach ($items as $it) {
            if ($hasDiscCol) {
                $stmtItem->execute([$saleId,$it['product_id'],$it['qty'],$it['unit_price'],
                                    $it['disc_pct'],$it['line_total'],$it['commission_pct'],$it['commission_amount']]);
            } else {
                $stmtItem->execute([$saleId,$it['product_id'],$it['qty'],$it['unit_price'],
                                    $it['line_total'],$it['commission_pct'],$it['commission_amount']]);
            }
            $stmtStock->execute([$it['qty'], $it['product_id']]);
        }

        $pdo->commit();
        audit($pdo,'sale.create','sale',$saleId,$invoiceNo);
        flash('success','Sale recorded — invoice '.$invoiceNo);
        redirect(url('sales/view.php?id='.$saleId));
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('danger','Failed to save sale: '.$e->getMessage());
        redirect(url('sales/add.php'));
    }
}

$clients  = $pdo->query("SELECT id,name FROM clients ORDER BY name")->fetchAll();
$reps     = $pdo->query("SELECT id,name FROM users WHERE role='rep' AND status='active' ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT id,sku,name,price,stock_qty FROM products WHERE status='active' ORDER BY name")->fetchAll();
$vatDef   = (float)setting($pdo,'vat_default_pct','16');

$page_title = 'New Sale / Invoice';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0"><i class="bi bi-receipt"></i> New Sale / Invoice</h3>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url('sales/index.php') ?>">
    <i class="bi bi-arrow-left"></i> Back
  </a>
</div>

<form method="post" class="card shadow-sm p-3" id="saleForm">
  <?= csrf_field() ?>

  <!-- ── CLIENT · REP · DATE · VAT ── -->
  <div class="row g-3 align-items-end">

    <div class="col-md-4">
      <label class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
      <div class="input-group">
        <select class="form-select" name="client_id" id="client_id" required>
          <option value="">— Select client —</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $preselectClient===(int)$c['id']?'selected':'' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-outline-success"
                data-bs-toggle="modal" data-bs-target="#quickClientModal"
                title="Quick-add new client">
          <i class="bi bi-person-plus-fill"></i>
        </button>
      </div>
      <div class="form-text text-success">
        <i class="bi bi-info-circle"></i> New client? Click <i class="bi bi-person-plus-fill"></i> — full KYC can be completed later.
      </div>
    </div>

    <?php if ($u['role'] !== 'rep'): ?>
    <div class="col-md-3">
      <label class="form-label fw-semibold">Rep <span class="text-danger">*</span></label>
      <select class="form-select" name="rep_id" required>
        <option value="">— Select rep —</option>
        <?php foreach ($reps as $r): ?>
          <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="col-md-2">
      <label class="form-label fw-semibold">Sale Date</label>
      <input class="form-control" type="date" name="sale_date" value="<?= e(date('Y-m-d')) ?>">
    </div>

    <div class="col-md-2">
      <label class="form-label fw-semibold">VAT %</label>
      <div class="input-group">
        <input class="form-control" type="number" step="0.01" min="0" name="tax_pct"
               id="tax_pct" value="<?= e($vatDef) ?>">
        <span class="input-group-text">%</span>
      </div>
    </div>
  </div>

  <hr class="my-3">

  <!-- ── LINE ITEMS ── -->
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul"></i> Line Items</h6>
    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addLine()">
      <i class="bi bi-plus-lg"></i> Add item
    </button>
  </div>

  <div id="lines"></div>

  <hr class="my-3">

  <!-- ── PAYMENT DETAILS ── -->
  <div class="row g-3">
    <div class="col-md-3">
      <label class="form-label fw-semibold">Invoice Discount (KES)</label>
      <div class="input-group">
        <span class="input-group-text">KES</span>
        <input class="form-control" type="number" step="0.01" min="0" name="discount" id="discount" value="0">
      </div>
      <div class="form-text">Flat discount off the whole invoice</div>
    </div>
    <div class="col-md-3">
      <label class="form-label fw-semibold">Amount Paid</label>
      <div class="input-group">
        <span class="input-group-text">KES</span>
        <input class="form-control" type="number" step="0.01" min="0" name="paid_amount" id="paid" value="0">
      </div>
    </div>
    <div class="col-md-3">
      <label class="form-label fw-semibold">Payment Method</label>
      <select class="form-select" name="payment_method">
        <option value="">— Select —</option>
        <option value="M-Pesa">M-Pesa</option>
        <option value="Bank Transfer">Bank Transfer</option>
        <option value="Cash">Cash</option>
        <option value="Cheque">Cheque</option>
        <option value="Credit">Credit</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label fw-semibold">Payment Status</label>
      <select class="form-select" name="payment_status">
        <option value="unpaid">Unpaid</option>
        <option value="partial">Partial</option>
        <option value="paid">Paid</option>
      </select>
    </div>
    <div class="col-12">
      <label class="form-label fw-semibold">Notes</label>
      <textarea class="form-control" name="notes" rows="2" placeholder="Optional internal notes…"></textarea>
    </div>
  </div>

  <!-- ── TOTALS ── -->
  <div class="row mt-3">
    <div class="col-md-5 ms-auto">
      <table class="table table-sm table-bordered bg-white">
        <tr>
          <th class="bg-light">Subtotal</th>
          <td class="text-end fw-semibold" id="t_sub">0.00</td>
        </tr>
        <tr>
          <th class="bg-light text-danger">Invoice Discount</th>
          <td class="text-end text-danger" id="t_dis">− 0.00</td>
        </tr>
        <tr>
          <th class="bg-light">VAT</th>
          <td class="text-end" id="t_tax">0.00</td>
        </tr>
        <tr class="table-dark">
          <th>TOTAL</th>
          <td class="text-end fw-bold fs-5" id="t_tot">0.00</td>
        </tr>
      </table>
    </div>
  </div>

  <div class="mt-2 d-flex gap-2">
    <button class="btn btn-primary px-4">
      <i class="bi bi-save"></i> Save &amp; Generate Invoice
    </button>
    <a class="btn btn-outline-secondary" href="<?= url('sales/index.php') ?>">Cancel</a>
  </div>
</form>

<!-- ══════════════════════════════════════════════════════════════════ -->
<!-- QUICK-ADD CLIENT MODAL                                            -->
<!-- ══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="quickClientModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:#1a6b3c; color:#fff;">
        <h5 class="modal-title"><i class="bi bi-person-plus-fill"></i> Quick-Add Client</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-success alert-sm py-2 small">
          <i class="bi bi-info-circle-fill"></i>
          Only a name is needed to start the sale. Full KYC details (PIN, licence, directors, etc.)
          can be completed later under <strong>Clients → Edit</strong>.
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Client Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="qc_name" placeholder="e.g. Sunrise Pharmacy">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Phone</label>
          <input type="text" class="form-control" id="qc_phone" placeholder="+254 700 000 000">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Type</label>
          <select class="form-select" id="qc_type">
            <option value="pharmacy">Pharmacy</option>
            <option value="clinic">Clinic</option>
            <option value="hospital">Hospital</option>
            <option value="wholesaler">Wholesaler</option>
            <option value="other" selected>Other</option>
          </select>
        </div>
        <div id="qc_error" class="alert alert-danger py-2 small d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="qc_btn" onclick="saveQuickClient()">
          <i class="bi bi-check-lg"></i> Add Client &amp; Select
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const PRODUCTS = <?= json_encode($products, JSON_HEX_TAG) ?>;
const CSRF     = <?= json_encode(csrf_token()) ?>;
const AJAXURL  = <?= json_encode(url('sales/add.php')) ?>;
const fmt = n  => Number(n||0).toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2});

/* ── Build product <option> list ───────────────────────────────────── */
function buildOpts() {
  let o = `<option value="">— Choose product —</option>
            <option value="__new__" style="color:#1a6b3c;font-weight:700;">
              ➕  New product (add to catalogue on the fly)…
            </option>`;
  PRODUCTS.forEach(p => {
    o += `<option value="${p.id}" data-price="${p.price}" data-stock="${p.stock_qty}">
            ${p.sku} — ${p.name}  (stock: ${p.stock_qty})
          </option>`;
  });
  return o;
}

let lineCount = 0;

function addLine() {
  const i   = lineCount++;
  const div = document.createElement('div');
  div.className = 'line card mb-2 border-secondary-subtle';
  div.id = `line_${i}`;
  div.innerHTML = `
    <div class="card-body p-2">
      <div class="row g-2 align-items-end">

        <div class="col-md-4 col-12">
          <label class="form-label small mb-1 fw-semibold">Product</label>
          <select class="form-select form-select-sm prod-sel"
                  name="items[${i}][product_id]"
                  onchange="onProdChange(this,${i})">
            ${buildOpts()}
          </select>
        </div>

        <div class="col-md-1 col-4">
          <label class="form-label small mb-1 fw-semibold">Qty</label>
          <input class="form-control form-control-sm qty" type="number" min="1"
                 value="1" name="items[${i}][qty]" oninput="recalc()">
        </div>

        <div class="col-md-2 col-4">
          <label class="form-label small mb-1 fw-semibold">Unit Price</label>
          <input class="form-control form-control-sm up" type="number" step="0.01" min="0"
                 placeholder="0.00" name="items[${i}][unit_price]" oninput="recalc()">
        </div>

        <div class="col-md-2 col-4">
          <label class="form-label small mb-1 fw-semibold">Line Disc %</label>
          <div class="input-group input-group-sm">
            <input class="form-control form-control-sm disc" type="number"
                   step="0.01" min="0" max="100" value="0"
                   name="items[${i}][disc_pct]" oninput="recalc()"
                   title="Discount for this line only">
            <span class="input-group-text">%</span>
          </div>
        </div>

        <div class="col-md-2 col-10">
          <label class="form-label small mb-1 fw-semibold">Line Total</label>
          <input class="form-control form-control-sm lt bg-light fw-semibold"
                 type="text" readonly placeholder="0.00">
        </div>

        <div class="col-md-1 col-2 text-end pt-3">
          <button type="button" class="btn btn-sm btn-outline-danger"
                  onclick="document.getElementById('line_${i}').remove();recalc();"
                  title="Remove line">
            <i class="bi bi-trash3"></i>
          </button>
        </div>
      </div>

      <!-- ── New product inline form ── -->
      <div class="d-none mt-2 p-2 rounded border border-success bg-success bg-opacity-10"
           id="npf_${i}">
        <p class="small mb-2 fw-semibold text-success">
          <i class="bi bi-plus-circle-fill"></i>
          New product — fill below and it will be saved to your catalogue immediately.
        </p>
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <input class="form-control form-control-sm" id="np_name_${i}"
                   placeholder="Product name *">
          </div>
          <div class="col-md-2">
            <input class="form-control form-control-sm" id="np_sku_${i}"
                   placeholder="SKU *  e.g. NU-XYZ-01">
          </div>
          <div class="col-md-2">
            <div class="input-group input-group-sm">
              <span class="input-group-text">KES</span>
              <input class="form-control form-control-sm" type="number" step="0.01"
                     id="np_price_${i}" placeholder="Price *">
            </div>
          </div>
          <div class="col-md-4 d-flex gap-2">
            <button type="button" class="btn btn-success btn-sm flex-fill"
                    onclick="saveNewProduct(${i})">
              <i class="bi bi-check-lg"></i> Save &amp; Select
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    onclick="cancelNewProd(${i})">Cancel</button>
          </div>
        </div>
        <div class="text-danger small mt-1 d-none" id="np_err_${i}"></div>
      </div>
    </div>`;
  document.getElementById('lines').appendChild(div);
}

function onProdChange(sel, i) {
  if (sel.value === '__new__') {
    document.getElementById(`npf_${i}`).classList.remove('d-none');
    sel.value = '';
    return;
  }
  document.getElementById(`npf_${i}`).classList.add('d-none');
  const opt = sel.options[sel.selectedIndex];
  if (opt && opt.dataset.price) {
    document.getElementById(`line_${i}`).querySelector('.up').value = opt.dataset.price;
  }
  recalc();
}

function cancelNewProd(i) {
  document.getElementById(`npf_${i}`).classList.add('d-none');
  ['np_name_','np_sku_','np_price_'].forEach(p => {
    const el = document.getElementById(p + i); if (el) el.value = '';
  });
  const err = document.getElementById(`np_err_${i}`);
  if (err) { err.textContent = ''; err.classList.add('d-none'); }
}

async function saveNewProduct(i) {
  const name  = document.getElementById(`np_name_${i}`).value.trim();
  const sku   = document.getElementById(`np_sku_${i}`).value.trim();
  const price = document.getElementById(`np_price_${i}`).value;
  const errEl = document.getElementById(`np_err_${i}`);

  if (!name || !sku || !price) {
    errEl.textContent = 'Name, SKU and price are all required.';
    errEl.classList.remove('d-none'); return;
  }
  errEl.classList.add('d-none');

  const btn = document.querySelector(`#line_${i} .btn-success`);
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';

  const fd = new FormData();
  fd.append('action','quick_product'); fd.append('_csrf',CSRF);
  fd.append('name',name); fd.append('sku',sku); fd.append('price',price);

  const res  = await fetch(AJAXURL, {method:'POST', body:fd});
  const data = await res.json();
  btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Save &amp; Select';

  if (!data.ok) {
    errEl.textContent = data.error; errEl.classList.remove('d-none'); return;
  }

  // Add to local list
  PRODUCTS.push({id:data.id, sku:data.sku, name:data.name, price:data.price, stock_qty:0});
  // Add option to ALL product selects
  document.querySelectorAll('.prod-sel').forEach(s => {
    const o = new Option(`${data.sku} — ${data.name}  (stock: 0)`, data.id);
    o.dataset.price = data.price; o.dataset.stock = 0;
    s.appendChild(o);
  });
  // Select it in this line
  const line = document.getElementById(`line_${i}`);
  line.querySelector('.prod-sel').value = data.id;
  line.querySelector('.up').value = data.price;
  cancelNewProd(i);
  recalc();
}

/* ── Recalculate totals ─────────────────────────────────────────── */
function recalc() {
  let sub = 0;
  document.querySelectorAll('.line').forEach(l => {
    const q    = parseFloat(l.querySelector('.qty')?.value)  || 0;
    const p    = parseFloat(l.querySelector('.up')?.value)   || 0;
    const disc = parseFloat(l.querySelector('.disc')?.value) || 0;
    const net  = p * (1 - disc / 100);
    const lt   = q * net;
    const ltEl = l.querySelector('.lt');
    if (ltEl) ltEl.value = fmt(lt);
    sub += lt;
  });
  const dis    = parseFloat(document.getElementById('discount').value) || 0;
  const taxPct = parseFloat(document.getElementById('tax_pct').value)  || 0;
  const tax    = (sub - dis) * taxPct / 100;
  const tot    = sub - dis + tax;
  document.getElementById('t_sub').textContent = fmt(sub);
  document.getElementById('t_dis').textContent = '− ' + fmt(dis);
  document.getElementById('t_tax').textContent = fmt(tax);
  document.getElementById('t_tot').textContent = fmt(tot);
}

['discount','paid','tax_pct'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', recalc);
});

/* ── Quick-add client ───────────────────────────────────────────── */
async function saveQuickClient() {
  const name  = document.getElementById('qc_name').value.trim();
  const phone = document.getElementById('qc_phone').value.trim();
  const type  = document.getElementById('qc_type').value;
  const errEl = document.getElementById('qc_error');
  const btn   = document.getElementById('qc_btn');

  if (!name) { errEl.textContent='Client name is required.'; errEl.classList.remove('d-none'); return; }
  errEl.classList.add('d-none');
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';

  const fd = new FormData();
  fd.append('action','quick_client'); fd.append('_csrf',CSRF);
  fd.append('name',name); fd.append('phone',phone); fd.append('type',type);

  const res  = await fetch(AJAXURL, {method:'POST', body:fd});
  const data = await res.json();
  btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Add Client &amp; Select';

  if (!data.ok) { errEl.textContent=data.error; errEl.classList.remove('d-none'); return; }

  const sel = document.getElementById('client_id');
  const opt = new Option(data.name, data.id, true, true);
  sel.appendChild(opt);

  bootstrap.Modal.getInstance(document.getElementById('quickClientModal')).hide();
  document.getElementById('qc_name').value = '';
  document.getElementById('qc_phone').value = '';
}

// Start with one blank line
addLine();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
