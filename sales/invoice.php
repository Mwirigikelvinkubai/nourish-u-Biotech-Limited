<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$id = (int)get('id');
$stmt = $pdo->prepare(
    "SELECT s.*, c.name AS client, c.address, c.postal_address,
            c.phone AS cphone, c.email AS cemail, c.kra_pin,
            u.name AS rep, u.phone AS rep_phone,
            c.payment_terms, c.credit_period_days
       FROM sales s
       JOIN clients c ON c.id = s.client_id
       JOIN users   u ON u.id = s.rep_id
      WHERE s.id = ?"
);
$stmt->execute([$id]); $s = $stmt->fetch();
if (!$s) { http_response_code(404); die('Sale not found.'); }
if ($u['role']==='rep' && (int)$s['rep_id']!==(int)$u['id']) {
    http_response_code(403); die('Not allowed.');
}

/* ── Items — try with disc_pct, fall back gracefully ─────────────── */
try {
    $iStmt = $pdo->prepare(
        "SELECT si.*, si.disc_pct, p.sku, p.name AS pname, p.batch_no, p.expiry_date
           FROM sale_items si JOIN products p ON p.id = si.product_id
          WHERE si.sale_id = ?"
    );
    $iStmt->execute([$id]); $items = $iStmt->fetchAll();
} catch (PDOException $e) {
    $iStmt = $pdo->prepare(
        "SELECT si.*, p.sku, p.name AS pname, p.batch_no, p.expiry_date
           FROM sale_items si JOIN products p ON p.id = si.product_id
          WHERE si.sale_id = ?"
    );
    $iStmt->execute([$id]); $items = $iStmt->fetchAll();
    foreach ($items as &$it) {
        $up = (float)$it['unit_price']; $lt = (float)$it['line_total']; $q = (int)$it['qty'];
        $it['disc_pct'] = ($q > 0 && $up > 0) ? round((1 - $lt / ($q * $up)) * 100, 2) : 0;
    }
    unset($it);
}

/* ── Settings ─────────────────────────────────────────────────────── */
$company  = setting($pdo,'company_name',     'Nourish U Biotech Limited');
$tagline  = setting($pdo,'company_tagline',  'Your Partner in Natural Wellness');
$cAddr    = setting($pdo,'company_address',  'P.O. Box 761 – 00515, Nairobi, Kenya');
$cPhone   = setting($pdo,'company_phone',    '+254 720 089 063');
$cEmail   = 'Info@nourishu.co.ke';          // primary contact email
$bankName = setting($pdo,'bank_name',        'NCBA Bank');
$bankBr   = setting($pdo,'bank_branch',      'ABC Place');
$bankAcN  = setting($pdo,'bank_account_name','Nourish U Biotech Limited');
$bankKes  = setting($pdo,'bank_account_kes', '1005858439');
$mPayBill = setting($pdo,'mpesa_paybill',    '880100');
$mAcct    = setting($pdo,'mpesa_account',    '606264');

$dueDate = $s['credit_period_days']
    ? date('d M Y', strtotime($s['sale_date'].' +'.(int)$s['credit_period_days'].' days'))
    : null;

/* Show disc% column only when at least one line carries a discount */
$showDisc = false;
foreach ($items as $it) { if ((float)($it['disc_pct']??0) > 0) { $showDisc = true; break; } }

$statusColors = [
    'paid'    => ['bg'=>'#d1fae5','txt'=>'#065f46'],
    'partial' => ['bg'=>'#fef3c7','txt'=>'#92400e'],
    'unpaid'  => ['bg'=>'#fee2e2','txt'=>'#991b1b'],
];
$sc = $statusColors[$s['payment_status']] ?? ['bg'=>'#f3f4f6','txt'=>'#374151'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice <?= e($s['invoice_no']) ?> — <?= e($company) ?></title>
<link rel="icon" type="image/png" href="<?= url('assets/img/favicon.png') ?>">
<style>
/* ═══════════════════════════════════════════════════════════════════
   NOURISH U BIOTECH — INVOICE  (brand: purple #4A3C8C + cyan #00C8D4)
═══════════════════════════════════════════════════════════════════ */
:root {
  --pu:   #4A3C8C;   /* logo purple  */
  --pu2:  #342B6B;   /* darker purple */
  --pu3:  #6C5FC7;   /* mid purple   */
  --cy:   #00C8D4;   /* logo cyan    */
  --cy2:  #00A8B3;   /* darker cyan  */
  --grad: linear-gradient(135deg, #00C8D4 0%, #4A3C8C 100%);
  --gl:   #F0ECFF;   /* purple tint bg */
  --bdr:  #C5B8E8;   /* purple border  */
  --txt:  #1a1a1a;
  --muted:#555;
}
*  { box-sizing: border-box; margin:0; padding:0; }
body { background:#ECEAF5; font-family: 'Segoe UI', Arial, sans-serif;
       font-size:13px; color:var(--txt); }

/* ── Toolbar (screen only) ── */
.toolbar {
  background: var(--pu2);
  padding: 10px 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  position: sticky;
  top: 0;
  z-index: 100;
}
.toolbar button, .toolbar a {
  padding: 7px 20px;
  border-radius: 4px;
  font-size:13px;
  font-weight:700;
  cursor:pointer;
  text-decoration:none;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.btn-print { background: var(--grad); color:#fff; }
.btn-back  { background:#fff; color:var(--pu); }

/* ── Invoice wrapper ── */
.inv {
  max-width: 880px;
  margin: 22px auto 40px;
  background: #fff;
  border: 2px solid var(--pu);
  border-radius: 6px;
  box-shadow: 0 6px 28px rgba(74,60,140,.18);
  overflow: hidden;
}

/* ── Gradient top bar ── */
.inv-bar { background: var(--grad); height: 9px; }

/* ══════════════════════════════════════════════════════════════════
   HEADER
══════════════════════════════════════════════════════════════════ */
.inv-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 20px 26px 14px;
  border-bottom: 2px solid var(--bdr);
  background: linear-gradient(180deg,#faf9ff 0%,#fff 100%);
}
.inv-head .logo { height: 72px; width: auto; }
.co-block { flex: 1; padding-left: 14px; }
.co-name  { font-size:1.25rem; font-weight:900; color:var(--pu); letter-spacing:.02em; }
.co-tag   { font-size:.7rem; letter-spacing:.14em; color:var(--cy2);
            text-transform:uppercase; margin-top:2px; font-weight:600; }
.co-meta  { margin-top:6px; font-size:.76rem; color:var(--muted); line-height:1.7; }
.co-meta a { color:var(--pu3); text-decoration:none; }

/* SALES INVOICE badge + meta */
.inv-title-block { text-align:right; }
.inv-badge {
  background: var(--grad);
  color:#fff;
  font-size:1.05rem;
  font-weight:900;
  letter-spacing:.1em;
  padding: 7px 22px;
  border-radius: 4px;
  display: inline-block;
  box-shadow: 0 3px 10px rgba(74,60,140,.35);
}
.inv-meta-tbl { margin-top:10px; margin-left:auto; border-collapse:collapse; }
.inv-meta-tbl td { padding:2px 6px; font-size:.78rem; }
.inv-meta-tbl td:first-child { font-weight:700; color:#444; text-align:right; }
.inv-meta-tbl td:last-child  { color:var(--pu); font-weight:700; }
.st-pill {
  display:inline-block;
  padding:2px 11px;
  border-radius:20px;
  font-size:.7rem;
  font-weight:800;
  letter-spacing:.07em;
  text-transform:uppercase;
}

/* ══════════════════════════════════════════════════════════════════
   BODY
══════════════════════════════════════════════════════════════════ */
.inv-body { padding: 16px 26px; }

/* Bill-to / Rep block */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 16px;
}
.info-box {
  border: 1.5px solid var(--bdr);
  border-radius: 5px;
  padding: 10px 14px;
  background: var(--gl);
}
.info-box .box-title {
  font-size:.68rem;
  font-weight:900;
  text-transform:uppercase;
  letter-spacing:.1em;
  color:var(--cy2);
  margin-bottom:6px;
  padding-bottom:4px;
  border-bottom:1px solid var(--bdr);
}
.info-box .row1 { font-weight:800; font-size:.9rem; color:var(--pu); }
.info-box .row2 { font-size:.78rem; color:var(--muted); margin-top:3px; line-height:1.6; }

/* ── Editable fields ── */
[contenteditable] {
  outline:none;
  border-bottom:1.5px dashed var(--cy);
  cursor:text;
  border-radius:2px;
  padding:0 3px;
  display:inline-block;
  min-width:50px;
  transition:background .15s;
}
[contenteditable]:hover { background:#E8F9FA; }
[contenteditable]:focus { background:#D0F5F7; border-bottom-color:var(--pu); }
.edit-hint {
  font-size:.65rem;
  color:var(--pu3);
  margin-top:4px;
  opacity:.75;
  font-style:italic;
}

/* ══════════════════════════════════════════════════════════════════
   ITEMS TABLE
══════════════════════════════════════════════════════════════════ */
table.itbl {
  width:100%;
  border-collapse:collapse;
  font-size:.78rem;
  margin-bottom:14px;
}
table.itbl thead th {
  background: var(--pu);
  color:#fff;
  padding:7px 8px;
  text-align:center;
  font-size:.7rem;
  letter-spacing:.05em;
  text-transform:uppercase;
  border:1px solid var(--pu2);
}
table.itbl thead th.left { text-align:left; }
table.itbl tbody td {
  border:1px solid var(--bdr);
  padding:6px 8px;
  vertical-align:middle;
}
table.itbl tbody td.num { text-align:right; }
table.itbl tbody td.ctr { text-align:center; }
table.itbl tbody tr:nth-child(even) { background:#F8F6FF; }
table.itbl tbody tr:hover           { background:#EDE9FF; }
table.itbl tfoot td {
  border:1px solid var(--bdr);
  background:var(--gl);
  padding:5px 8px;
  font-weight:700;
  font-size:.78rem;
}

/* ══════════════════════════════════════════════════════════════════
   TOTALS BOX
══════════════════════════════════════════════════════════════════ */
.totals-wrap { display:flex; justify-content:flex-end; margin-bottom:18px; }
.totals-box {
  border:2px solid var(--pu);
  border-radius:5px;
  overflow:hidden;
  min-width:230px;
  box-shadow:0 3px 10px rgba(74,60,140,.12);
}
.totals-box table { width:100%; border-collapse:collapse; }
.totals-box tr    { border-bottom:1px solid var(--bdr); }
.totals-box td    { padding:6px 13px; font-size:.82rem; }
.totals-box td:first-child { background:var(--gl); font-weight:700; color:#333; }
.totals-box td:last-child  { text-align:right; font-weight:600; }
.totals-box tr.grand td {
  background:var(--grad) !important;
  color:#fff !important;
  font-size:.98rem;
  font-weight:900;
  letter-spacing:.02em;
  border:none;
}

/* ══════════════════════════════════════════════════════════════════
   BOTTOM — TERMS + PAYMENT
══════════════════════════════════════════════════════════════════ */
.inv-bottom {
  display:grid;
  grid-template-columns:1fr 1fr;
  border-top:2px solid var(--pu);
  margin:0 26px;
  padding-bottom:4px;
}
.terms-col { padding:13px 18px 14px 0; }
.pay-col   { padding:13px 0 14px 18px; border-left:1.5px solid var(--bdr); }
.sec-head  {
  font-size:.68rem;
  font-weight:900;
  text-transform:uppercase;
  letter-spacing:.1em;
  color:var(--pu);
  margin-bottom:7px;
  padding-bottom:4px;
  border-bottom:1px solid var(--bdr);
}
.terms-col ol {
  padding-left:16px;
  font-size:.73rem;
  color:#444;
  margin:0;
}
.terms-col ol li { margin-bottom:4px; line-height:1.5; }
.prow { display:flex; gap:8px; font-size:.76rem; margin-bottom:4px; align-items:baseline; }
.plbl { font-weight:700; color:#444; min-width:110px; flex-shrink:0; }
.pval { color:var(--pu); font-weight:700; }
.pay-divider { border:none; border-top:1px dashed var(--bdr); margin:7px 0; }

/* ══════════════════════════════════════════════════════════════════
   FOOTER
══════════════════════════════════════════════════════════════════ */
.inv-foot {
  background:var(--grad);
  color:rgba(255,255,255,.9);
  font-size:.7rem;
  padding:8px 26px;
  display:flex;
  justify-content:space-between;
  flex-wrap:wrap;
  gap:4px;
  margin-top:10px;
  letter-spacing:.02em;
}
.inv-foot strong { color:#fff; }

/* ══════════════════════════════════════════════════════════════════
   PRINT
══════════════════════════════════════════════════════════════════ */
@media print {
  body     { background:#fff; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .toolbar { display:none !important; }
  .inv     { box-shadow:none; border:2px solid var(--pu); margin:0; max-width:100%;
             border-radius:0; }
  [contenteditable] { border-bottom:none !important; background:transparent !important; }
  .edit-hint { display:none !important; }
}
</style>
</head>
<body>

<!-- ── TOOLBAR ── -->
<div class="toolbar no-print">
  <button class="btn-print" onclick="window.print()">
    &#128424; Print / Save PDF
  </button>
  <a class="btn-back" href="<?= url('sales/view.php?id='.$id) ?>">
    &#8592; Back to Sale
  </a>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     INVOICE
══════════════════════════════════════════════════════════════════ -->
<div class="inv">

  <div class="inv-bar"></div>

  <!-- ── HEADER ── -->
  <div class="inv-head">
    <img src="<?= url('assets/img/logo_full.png') ?>" alt="Nourish U Biotech" class="logo">

    <div class="co-block">
      <div class="co-name"><?= e($company) ?></div>
      <div class="co-tag"><?= e($tagline) ?></div>
      <div class="co-meta">
        &#128205; <?= e($cAddr) ?><br>
        &#128222; <?= e($cPhone) ?> &nbsp;|&nbsp;
        &#9993; <a href="mailto:<?= e($cEmail) ?>"><?= e($cEmail) ?></a>
      </div>
    </div>

    <div class="inv-title-block">
      <div class="inv-badge">SALES INVOICE</div>
      <table class="inv-meta-tbl">
        <tr>
          <td>Invoice No:</td>
          <td><?= e($s['invoice_no']) ?></td>
        </tr>
        <tr>
          <td>Invoice Date:</td>
          <td><?= e(fdate($s['sale_date'],'d M Y')) ?></td>
        </tr>
        <?php if ($dueDate): ?>
        <tr>
          <td>Due Date:</td>
          <td><?= e($dueDate) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
          <td>Status:</td>
          <td>
            <span class="st-pill"
                  style="background:<?= $sc['bg'] ?>;color:<?= $sc['txt'] ?>">
              <?= e(ucfirst($s['payment_status'])) ?>
            </span>
          </td>
        </tr>
      </table>
    </div>
  </div><!-- /inv-head -->

  <!-- ── BODY ── -->
  <div class="inv-body">

    <!-- Bill-To + Rep -->
    <div class="info-grid">

      <!-- Bill To -->
      <div class="info-box">
        <div class="box-title">&#128196; Bill To</div>
        <div class="row1"><?= e($s['client']) ?></div>
        <div class="row2">
          <?php if ($s['address']): ?>
            &#128205; <?= e($s['address']) ?><br>
          <?php endif; ?>
          <?php if ($s['cphone']): ?>
            &#128222; <?= e($s['cphone']) ?><br>
          <?php endif; ?>
          <?php if ($s['cemail']): ?>
            &#9993; <?= e($s['cemail']) ?><br>
          <?php endif; ?>
          <?php if ($s['kra_pin']): ?>
            PIN: <strong><?= e($s['kra_pin']) ?></strong>
          <?php endif; ?>
          <?php if ($s['payment_terms']): ?>
            <br>Terms: <?= e($s['payment_terms']) ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Med Rep -->
      <div class="info-box">
        <div class="box-title">&#128100; Med Rep</div>
        <div class="row1">
          <span contenteditable="true" id="rep_name"
                title="Click to edit"><?= e($s['rep']) ?></span>
        </div>
        <div class="row2">
          &#128222;
          <span contenteditable="true" id="rep_phone"
                title="Click to edit"><?= e($s['rep_phone'] ?: 'N/A') ?></span>
          <?php if ($s['notes']): ?>
            <br><em><?= nl2br(e($s['notes'])) ?></em>
          <?php endif; ?>
        </div>
        <div class="edit-hint no-print">&#9998; Click name or phone to edit before printing</div>
      </div>

    </div><!-- /info-grid -->

    <!-- Items table -->
    <table class="itbl">
      <thead>
        <tr>
          <th class="left" style="width:3%">#</th>
          <th class="left" style="width:26%">Description</th>
          <th style="width:9%">SKU</th>
          <th style="width:9%">Batch</th>
          <th style="width:8%">Expiry</th>
          <th style="width:5%">Qty</th>
          <th style="width:9%">Unit Price</th>
          <?php if ($showDisc): ?>
          <th style="width:6%">Disc %</th>
          <th style="width:9%">Net Price</th>
          <?php endif; ?>
          <th style="width:10%">VAT %</th>
          <th style="width:10%">Total</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $n => $it):
        $disc  = (float)($it['disc_pct'] ?? 0);
        $netUp = (float)$it['unit_price'] * (1 - $disc / 100);
        $vat   = $s['tax_pct'];
      ?>
        <tr>
          <td class="ctr" style="color:var(--pu3);font-weight:700"><?= $n + 1 ?></td>
          <td style="font-weight:600"><?= e($it['pname']) ?></td>
          <td class="ctr" style="font-family:monospace;font-size:.72rem;color:var(--pu3)">
            <?= e($it['sku']) ?>
          </td>
          <td class="ctr"><?= e($it['batch_no'] ?: '—') ?></td>
          <td class="ctr">
            <?= ($it['expiry_date'] && $it['expiry_date'] !== '0000-00-00')
                ? e(date('m/Y', strtotime($it['expiry_date']))) : '—' ?>
          </td>
          <td class="ctr" style="font-weight:800;color:var(--pu)"><?= (int)$it['qty'] ?></td>
          <td class="num"><?= number_format((float)$it['unit_price'], 2) ?></td>
          <?php if ($showDisc): ?>
          <td class="ctr" style="color:#c0392b">
            <?= $disc > 0 ? number_format($disc, 2).'%' : '—' ?>
          </td>
          <td class="num"><?= number_format($netUp, 2) ?></td>
          <?php endif; ?>
          <td class="ctr"><?= $vat > 0 ? number_format((float)$vat,2).'%' : '0%' ?></td>
          <td class="num" style="font-weight:800;color:var(--pu)">
            <?= number_format((float)$it['line_total'], 2) ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="<?= 7 + ($showDisc ? 2 : 0) ?>" style="text-align:right;color:var(--pu)">
            Subtotal
          </td>
          <td colspan="2" style="text-align:right">
            <?= number_format((float)$s['subtotal'], 2) ?>
          </td>
        </tr>
      </tfoot>
    </table>

    <!-- Totals -->
    <div class="totals-wrap">
      <div class="totals-box">
        <table>
          <tr>
            <td>Sub Total</td>
            <td><?= number_format((float)$s['subtotal'], 2) ?></td>
          </tr>
          <?php if ((float)$s['discount'] > 0): ?>
          <tr>
            <td style="color:#c0392b">Discount</td>
            <td style="color:#c0392b">&#8722; <?= number_format((float)$s['discount'], 2) ?></td>
          </tr>
          <?php endif; ?>
          <tr>
            <td>VAT (<?= e($s['tax_pct']) ?>%)</td>
            <td><?= number_format((float)$s['tax_amount'], 2) ?></td>
          </tr>
          <tr class="grand">
            <td>TOTAL (<?= APP_CURRENCY ?>)</td>
            <td><?= number_format((float)$s['total'], 2) ?></td>
          </tr>
          <tr>
            <td>Amount Paid</td>
            <td><?= number_format((float)$s['paid_amount'], 2) ?></td>
          </tr>
          <?php $bal = max(0,(float)$s['total']-(float)$s['paid_amount']); ?>
          <tr>
            <td style="color:<?= $bal > 0 ? '#c0392b' : '#065f46' ?>">Balance Due</td>
            <td style="color:<?= $bal > 0 ? '#c0392b' : '#065f46' ?>;font-weight:900">
              <?= number_format($bal, 2) ?>
            </td>
          </tr>
        </table>
      </div>
    </div>

  </div><!-- /inv-body -->

  <!-- ── TERMS + PAYMENT ── -->
  <div class="inv-bottom">

    <div class="terms-col">
      <div class="sec-head">&#128203; Terms &amp; Conditions</div>
      <ol>
        <li>Due on Demand.</li>
        <li>Goods once sold cannot be returnable without prior arrangements.</li>
        <li>Goods remain the property of <?= e($company) ?> until fully paid for.</li>
        <li>No Cash payment allowed to our delivery persons. All payments to be
            made via the payment details provided.</li>
      </ol>
    </div>

    <div class="pay-col">
      <div class="sec-head">&#128179; Payment Details</div>

      <?php if ($bankName): ?>
        <div class="prow">
          <span class="plbl">Bank:</span>
          <span class="pval"><?= e($bankName) ?><?= $bankBr ? ' — '.e($bankBr) : '' ?></span>
        </div>
        <div class="prow">
          <span class="plbl">Account Name:</span>
          <span class="pval"><?= e($bankAcN) ?></span>
        </div>
        <?php if ($bankKes): ?>
        <div class="prow">
          <span class="plbl">A/C No (KES):</span>
          <span class="pval"><?= e($bankKes) ?></span>
        </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($mPayBill): ?>
        <hr class="pay-divider">
        <div class="prow">
          <span class="plbl">M-Pesa Paybill:</span>
          <span class="pval"><?= e($mPayBill) ?></span>
        </div>
        <div class="prow">
          <span class="plbl">Account No:</span>
          <span class="pval"><?= e($mAcct) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($s['payment_method']): ?>
        <hr class="pay-divider">
        <div class="prow" style="font-size:.72rem;color:#666">
          <span class="plbl">Paid via:</span>
          <span class="pval" style="color:#333"><?= e($s['payment_method']) ?></span>
        </div>
      <?php endif; ?>
    </div>

  </div><!-- /inv-bottom -->

  <!-- ── FOOTER ── -->
  <div class="inv-foot">
    <span><strong><?= e($company) ?></strong> &nbsp;&#183;&nbsp; <?= e($cAddr) ?></span>
    <span>&#128222; <?= e($cPhone) ?> &nbsp;&#183;&nbsp; &#9993; <?= e($cEmail) ?></span>
    <span>Printed: <?= date('d M Y, H:i') ?></span>
  </div>

</div><!-- /inv -->

<div class="toolbar no-print" style="position:static;margin-top:6px;margin-bottom:30px">
  <button class="btn-print" onclick="window.print()">&#128424; Print / Save PDF</button>
  <a class="btn-back" href="<?= url('sales/view.php?id='.$id) ?>">&#8592; Back to Sale</a>
</div>

</body>
</html>
