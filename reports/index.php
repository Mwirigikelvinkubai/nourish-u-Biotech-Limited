<?php
require_once __DIR__ . '/../config/config.php';
require_role(['admin','accountant']);

$from = (string)(get('from') ?: date('Y-m-01', strtotime('-2 months')));
$to   = (string)(get('to')   ?: date('Y-m-d'));
$grp  = (string)(get('grp')  ?: 'month'); // month|week
$grp  = in_array($grp, ['month','week'], true) ? $grp : 'month';

// Helper: same query slice everywhere
$soft = "s.deleted_at IS NULL AND s.sale_date BETWEEN ? AND ?";

/* --------- BY REP --------- */
$byRep = $pdo->prepare(
    "SELECT u.name AS rep, COUNT(DISTINCT s.id) AS invoices,
            COALESCE(SUM(s.subtotal),0) AS subtotal,
            COALESCE(SUM(s.total),0)    AS total,
            COALESCE(SUM(s.paid_amount),0) AS paid
       FROM sales s JOIN users u ON u.id = s.rep_id
      WHERE $soft
      GROUP BY s.rep_id
      ORDER BY total DESC"
);
$byRep->execute([$from, $to]); $byRep = $byRep->fetchAll();

/* --------- BY PRODUCT --------- */
$byProduct = $pdo->prepare(
    "SELECT p.sku, p.name, SUM(si.qty) AS qty, SUM(si.line_total) AS total
       FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
      WHERE $soft
      GROUP BY p.id ORDER BY total DESC LIMIT 25"
);
$byProduct->execute([$from, $to]); $byProduct = $byProduct->fetchAll();

/* --------- BY CLIENT --------- */
$byClient = $pdo->prepare(
    "SELECT c.id, c.name, c.kyc_status, COUNT(s.id) AS invoices,
            SUM(s.total)         AS total,
            SUM(s.paid_amount)   AS paid,
            SUM(s.total - s.paid_amount) AS balance
       FROM sales s JOIN clients c ON c.id = s.client_id
      WHERE $soft
      GROUP BY c.id ORDER BY total DESC LIMIT 25"
);
$byClient->execute([$from, $to]); $byClient = $byClient->fetchAll();

/* --------- BY REGION --------- */
$byRegion = $pdo->prepare(
    "SELECT COALESCE(NULLIF(c.region,''), 'Unassigned') AS region,
            COUNT(s.id) AS invoices, SUM(s.total) AS total
       FROM sales s JOIN clients c ON c.id = s.client_id
      WHERE $soft
      GROUP BY region ORDER BY total DESC"
);
$byRegion->execute([$from, $to]); $byRegion = $byRegion->fetchAll();

/* --------- TIME TREND (month or week) --------- */
$groupExpr = $grp === 'week' ? "YEARWEEK(s.sale_date, 3)" : "DATE_FORMAT(s.sale_date,'%Y-%m')";
$labelExpr = $grp === 'week' ? "DATE_FORMAT(MIN(s.sale_date),'Wk %v %x')" : "DATE_FORMAT(MIN(s.sale_date),'%b %Y')";
$trend = $pdo->prepare(
    "SELECT $groupExpr AS bucket, $labelExpr AS label,
            COUNT(s.id) AS invoices,
            SUM(s.total) AS total,
            SUM(s.paid_amount) AS paid
       FROM sales s
      WHERE $soft
      GROUP BY bucket
      ORDER BY MIN(s.sale_date)"
);
$trend->execute([$from, $to]); $trend = $trend->fetchAll();

/* --------- SAMPLE-DROP CONVERSION --------- */
// For each drop in the range, check if the client made a paid purchase within 30 days.
$drops = $pdo->prepare(
    "SELECT sd.id, sd.client_id, sd.rep_id, sd.drop_date, sd.payment_collected,
            sd.status,
            u.name AS rep
       FROM sample_drops sd
       JOIN users u ON u.id = sd.rep_id
      WHERE sd.deleted_at IS NULL
        AND sd.drop_date BETWEEN ? AND ?"
);
$drops->execute([$from, $to]); $drops = $drops->fetchAll();

$convCheck = $pdo->prepare(
    "SELECT COUNT(*) FROM sales
      WHERE client_id = ? AND deleted_at IS NULL
        AND sale_date BETWEEN ? AND DATE_ADD(?, INTERVAL 30 DAY)
        AND payment_status IN ('partial','paid')"
);
$conv = ['total'=>0,'converted'=>0,'collected'=>0,'byRep'=>[]];
foreach ($drops as $d) {
    $conv['total']++;
    $conv['collected'] += (float)$d['payment_collected'];
    $convCheck->execute([$d['client_id'], $d['drop_date'], $d['drop_date']]);
    $hit = (int)$convCheck->fetchColumn() > 0 || (float)$d['payment_collected'] > 0;
    if ($hit) $conv['converted']++;
    $r = $d['rep'];
    if (!isset($conv['byRep'][$r])) $conv['byRep'][$r] = ['drops'=>0,'conv'=>0,'cash'=>0];
    $conv['byRep'][$r]['drops']++;
    if ($hit) $conv['byRep'][$r]['conv']++;
    $conv['byRep'][$r]['cash'] += (float)$d['payment_collected'];
}

/* --------- FEEDBACK ANALYTICS --------- */
$fbCounts = $pdo->prepare(
    "SELECT status, COUNT(*) AS n FROM feedback
      WHERE deleted_at IS NULL AND created_at BETWEEN ? AND ?
      GROUP BY status"
);
$fbCounts->execute([$from, $to . ' 23:59:59']);
$fbStatus = ['open'=>0,'in_progress'=>0,'resolved'=>0,'closed'=>0];
foreach ($fbCounts as $r) $fbStatus[$r['status']] = (int)$r['n'];

$fbSeverity = $pdo->prepare(
    "SELECT severity, COUNT(*) AS n FROM feedback
      WHERE deleted_at IS NULL AND created_at BETWEEN ? AND ?
      GROUP BY severity"
);
$fbSeverity->execute([$from, $to . ' 23:59:59']);
$fbSev = ['low'=>0,'medium'=>0,'high'=>0,'critical'=>0];
foreach ($fbSeverity as $r) $fbSev[$r['severity']] = (int)$r['n'];

$fbByRep = $pdo->prepare(
    "SELECT u.name, COUNT(*) AS total,
            SUM(f.status IN ('resolved','closed')) AS resolved,
            AVG(CASE WHEN f.status IN ('resolved','closed')
                     THEN TIMESTAMPDIFF(HOUR, f.created_at, f.updated_at) END) AS avg_hrs
       FROM feedback f JOIN users u ON u.id = f.rep_id
      WHERE f.deleted_at IS NULL AND f.created_at BETWEEN ? AND ?
      GROUP BY u.id ORDER BY total DESC"
);
$fbByRep->execute([$from, $to . ' 23:59:59']);
$fbByRep = $fbByRep->fetchAll();



/* --------- EXPENSES --------- */
$expByCat = $pdo->prepare(
    "SELECT category, COUNT(*) AS n, SUM(amount) AS total
       FROM expenses
      WHERE deleted_at IS NULL AND status IN ('approved','paid')
        AND spent_on BETWEEN ? AND ?
      GROUP BY category ORDER BY total DESC"
);
$expByCat->execute([$from, $to]); $expByCat = $expByCat->fetchAll();

$expByRep = $pdo->prepare(
    "SELECT u.name, COUNT(*) AS n, SUM(e.amount) AS total,
            SUM(e.status='pending') AS pending_n,
            SUM(CASE WHEN e.status='pending' THEN e.amount ELSE 0 END) AS pending_amt
       FROM expenses e JOIN users u ON u.id = e.rep_id
      WHERE e.deleted_at IS NULL AND e.spent_on BETWEEN ? AND ?
      GROUP BY u.id ORDER BY total DESC"
);
$expByRep->execute([$from, $to]); $expByRep = $expByRep->fetchAll();

$expTotals = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN status='pending'  THEN amount END),0) AS pending,
        COALESCE(SUM(CASE WHEN status='approved' THEN amount END),0) AS approved,
        COALESCE(SUM(CASE WHEN status='paid'     THEN amount END),0) AS paid,
        COALESCE(SUM(CASE WHEN status='rejected' THEN amount END),0) AS rejected
       FROM expenses WHERE deleted_at IS NULL AND spent_on BETWEEN ? AND ?"
);
$expTotals->execute([$from, $to]); $expTotals = $expTotals->fetch() ?: ['pending'=>0,'approved'=>0,'paid'=>0,'rejected'=>0];

$page_title = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<h3 class="mb-3">Reports</h3>

<form class="row g-2 mb-4" method="get">
  <div class="col-md-2"><label class="form-label small">From</label>
    <input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="col-md-2"><label class="form-label small">To</label>
    <input class="form-control" type="date" name="to"   value="<?= e($to) ?>"></div>
  <div class="col-md-2"><label class="form-label small">Time bucket</label>
    <select class="form-select" name="grp">
      <option value="month" <?= $grp==='month'?'selected':'' ?>>Monthly</option>
      <option value="week"  <?= $grp==='week' ?'selected':'' ?>>Weekly</option>
    </select></div>
  <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Generate</button></div>
  <div class="col-md-2 d-flex align-items-end">
    <a class="btn btn-purple w-100" target="_blank"
       href="<?= url('reports/print.php?from=' . urlencode($from) . '&to=' . urlencode($to) . '&grp=' . urlencode($grp)) ?>">
      <i class="bi bi-file-earmark-pdf"></i> Download PDF
    </a>
  </div>
</form>

<!-- Time trend -->
<div class="card mb-3">
  <div class="card-header"><i class="bi bi-graph-up"></i> Sales trend (<?= e($grp) ?>ly)</div>
  <div class="card-body">
    <canvas id="trendChart" height="90"></canvas>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card"><div class="card-header"><i class="bi bi-person-badge"></i> Sales by med rep</div>
      <div class="table-responsive"><table class="table table-clean mb-0">
        <thead><tr><th>Rep</th><th class="text-end">Invoices</th>
          <th class="text-end">Subtotal</th><th class="text-end">Total</th><th class="text-end">Paid</th></tr></thead>
        <tbody>
        <?php foreach ($byRep as $r): ?>
          <tr><td><?= e($r['rep']) ?></td>
              <td class="text-end"><?= (int)$r['invoices'] ?></td>
              <td class="text-end"><?= money($r['subtotal']) ?></td>
              <td class="text-end fw-bold"><?= money($r['total']) ?></td>
              <td class="text-end"><?= money($r['paid']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
  </div>

  <div class="col-lg-6">
    <div class="card"><div class="card-header"><i class="bi bi-geo-alt"></i> Sales by region</div>
      <div class="table-responsive"><table class="table table-clean mb-0">
        <thead><tr><th>Region</th><th class="text-end">Invoices</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        <?php foreach ($byRegion as $r): ?>
          <tr><td><?= e($r['region']) ?></td><td class="text-end"><?= (int)$r['invoices'] ?></td>
              <td class="text-end fw-bold"><?= money($r['total']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
  </div>

  <div class="col-lg-6">
    <div class="card"><div class="card-header"><i class="bi bi-capsule"></i> Top products</div>
      <div class="table-responsive"><table class="table table-clean mb-0">
        <thead><tr><th>SKU</th><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
        <tbody>
        <?php foreach ($byProduct as $p): ?>
          <tr><td class="font-monospace small"><?= e($p['sku']) ?></td>
              <td><?= e($p['name']) ?></td>
              <td class="text-end"><?= (int)$p['qty'] ?></td>
              <td class="text-end fw-bold"><?= money($p['total']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
  </div>

  <div class="col-lg-6">
    <div class="card"><div class="card-header"><i class="bi bi-shop"></i> Top clients (with balance)</div>
      <div class="table-responsive"><table class="table table-clean mb-0">
        <thead><tr><th>Client</th><th>KYC</th><th class="text-end">Invoices</th>
          <th class="text-end">Total</th><th class="text-end">Balance</th></tr></thead>
        <tbody>
        <?php foreach ($byClient as $c):
          $kc = ['pending'=>'badge-warning','verified'=>'badge-soft','rejected'=>'badge-danger'][$c['kyc_status']] ?? 'badge-secondary';
        ?>
          <tr>
            <td><a href="<?= url('clients/view.php?id=' . (int)$c['id']) ?>"><?= e($c['name']) ?></a></td>
            <td><span class="badge <?= $kc ?>"><?= e(ucfirst($c['kyc_status'])) ?></span></td>
            <td class="text-end"><?= (int)$c['invoices'] ?></td>
            <td class="text-end fw-bold"><?= money($c['total']) ?></td>
            <td class="text-end <?= $c['balance']>0?'text-danger':'' ?>"><?= money($c['balance']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
  </div>

  <!-- Sample drop conversion -->
  <div class="col-lg-6">
    <div class="card"><div class="card-header"><i class="bi bi-box-seam"></i> Sample-drop conversion</div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-4"><div class="metric"><h6>Drops</h6><div class="v"><?= (int)$conv['total'] ?></div></div></div>
          <div class="col-4"><div class="metric"><h6>Converted</h6><div class="v text-success"><?= (int)$conv['converted'] ?></div>
            <div class="sub"><?= $conv['total']>0 ? round($conv['converted']*100/$conv['total'],1) : 0 ?>%</div></div></div>
          <div class="col-4"><div class="metric"><h6>Cash at pickup</h6><div class="v"><?= money($conv['collected']) ?></div></div></div>
        </div>
        <table class="table table-sm table-clean mb-0">
          <thead><tr><th>Rep</th><th class="text-end">Drops</th><th class="text-end">Converted</th><th class="text-end">% conv</th><th class="text-end">Cash</th></tr></thead>
          <tbody>
          <?php foreach ($conv['byRep'] as $rep => $row):
            $pct = $row['drops'] > 0 ? round($row['conv']*100/$row['drops'], 1) : 0;
          ?>
            <tr>
              <td><?= e($rep) ?></td>
              <td class="text-end"><?= (int)$row['drops'] ?></td>
              <td class="text-end"><?= (int)$row['conv'] ?></td>
              <td class="text-end fw-bold"><?= $pct ?>%</td>
              <td class="text-end"><?= money($row['cash']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Feedback analytics -->
  <div class="col-lg-6">
    <div class="card"><div class="card-header"><i class="bi bi-chat-dots"></i> Feedback &amp; complaints</div>
      <div class="card-body">
        <div class="row g-2 mb-3">
          <?php foreach ($fbStatus as $k=>$n): ?>
            <div class="col-3"><div class="metric"><h6><?= e(ucfirst(str_replace('_',' ',$k))) ?></h6><div class="v"><?= (int)$n ?></div></div></div>
          <?php endforeach; ?>
        </div>
        <div class="row g-2 mb-3">
          <?php foreach ($fbSev as $k=>$n):
            $cls=['low'=>'badge-soft','medium'=>'badge-info','high'=>'badge-warning','critical'=>'badge-danger'][$k];
          ?>
            <div class="col-3 text-center"><span class="badge <?= $cls ?>"><?= ucfirst($k) ?></span><div class="fw-bold mt-1"><?= (int)$n ?></div></div>
          <?php endforeach; ?>
        </div>
        <table class="table table-sm table-clean mb-0">
          <thead><tr><th>Rep</th><th class="text-end">Logged</th><th class="text-end">Resolved</th><th class="text-end">Avg time</th></tr></thead>
          <tbody>
          <?php foreach ($fbByRep as $r): ?>
            <tr>
              <td><?= e($r['name']) ?></td>
              <td class="text-end"><?= (int)$r['total'] ?></td>
              <td class="text-end"><?= (int)$r['resolved'] ?></td>
              <td class="text-end small text-muted">
                <?= $r['avg_hrs'] !== null ? round((float)$r['avg_hrs'],1) . ' hrs' : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Expenses -->
  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="bi bi-receipt-cutoff"></i> Rep expenses</div>
      <div class="card-body">
        <div class="row g-2 mb-3">
          <div class="col-md-3"><div class="metric"><h6>Pending</h6><div class="v text-warning"><?= money($expTotals['pending']) ?></div></div></div>
          <div class="col-md-3"><div class="metric"><h6>Approved</h6><div class="v"><?= money($expTotals['approved']) ?></div></div></div>
          <div class="col-md-3"><div class="metric"><h6>Paid</h6><div class="v text-success"><?= money($expTotals['paid']) ?></div></div></div>
          <div class="col-md-3"><div class="metric"><h6>Rejected</h6><div class="v text-danger"><?= money($expTotals['rejected']) ?></div></div></div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <h6 class="text-muted small text-uppercase">By category</h6>
            <table class="table table-sm table-clean mb-0">
              <thead><tr><th>Category</th><th class="text-end">Count</th><th class="text-end">Approved/Paid</th></tr></thead>
              <tbody>
              <?php foreach ($expByCat as $r): ?>
                <tr><td><?= e($r['category']) ?></td>
                    <td class="text-end"><?= (int)$r['n'] ?></td>
                    <td class="text-end fw-bold"><?= money($r['total']) ?></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="col-md-6">
            <h6 class="text-muted small text-uppercase">By rep</h6>
            <table class="table table-sm table-clean mb-0">
              <thead><tr><th>Rep</th><th class="text-end">Logged</th><th class="text-end">Pending</th></tr></thead>
              <tbody>
              <?php foreach ($expByRep as $r): ?>
                <tr><td><?= e($r['name']) ?></td>
                    <td class="text-end fw-bold"><?= money($r['total']) ?></td>
                    <td class="text-end"><?= money($r['pending_amt']) ?> <span class="small text-muted">(<?= (int)$r['pending_n'] ?>)</span></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const ctx = document.getElementById('trendChart').getContext('2d');
  const labels = <?= json_encode(array_column($trend, 'label')) ?>;
  const totals = <?= json_encode(array_map(fn($r)=>(float)$r['total'], $trend)) ?>;
  const paid   = <?= json_encode(array_map(fn($r)=>(float)$r['paid'],  $trend)) ?>;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        { label: 'Invoiced', data: totals, backgroundColor: 'rgba(89,67,150,.75)' },
        { label: 'Collected', data: paid,  backgroundColor: 'rgba(61,201,217,.85)' }
      ]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
  });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
