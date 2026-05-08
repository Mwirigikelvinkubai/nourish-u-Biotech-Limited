<?php
require_once __DIR__ . '/../config/config.php';
require_role(['admin','accountant']);

$from = (string)(get('from') ?: date('Y-m-01'));
$to   = (string)(get('to')   ?: date('Y-m-d'));

$byRep = $pdo->prepare(
    "SELECT u.name AS rep, COUNT(DISTINCT s.id) AS invoices,
            COALESCE(SUM(s.subtotal),0) AS subtotal,
            COALESCE(SUM(s.total),0)    AS total,
            COALESCE(SUM(s.paid_amount),0) AS paid
       FROM sales s
       JOIN users u ON u.id = s.rep_id
      WHERE s.sale_date BETWEEN ? AND ?
      GROUP BY s.rep_id
      ORDER BY total DESC"
);
$byRep->execute([$from, $to]);
$byRep = $byRep->fetchAll();

$byProduct = $pdo->prepare(
    "SELECT p.sku, p.name, SUM(si.qty) AS qty, SUM(si.line_total) AS total
       FROM sale_items si
       JOIN sales s ON s.id = si.sale_id
       JOIN products p ON p.id = si.product_id
      WHERE s.sale_date BETWEEN ? AND ?
      GROUP BY p.id
      ORDER BY total DESC LIMIT 25"
);
$byProduct->execute([$from, $to]);
$byProduct = $byProduct->fetchAll();

$byClient = $pdo->prepare(
    "SELECT c.name, COUNT(s.id) AS invoices, SUM(s.total) AS total
       FROM sales s JOIN clients c ON c.id = s.client_id
      WHERE s.sale_date BETWEEN ? AND ?
      GROUP BY c.id ORDER BY total DESC LIMIT 15"
);
$byClient->execute([$from, $to]);
$byClient = $byClient->fetchAll();

$byRegion = $pdo->prepare(
    "SELECT COALESCE(NULLIF(c.region,''), 'Unassigned') AS region,
            COUNT(s.id) AS invoices, SUM(s.total) AS total
       FROM sales s JOIN clients c ON c.id = s.client_id
      WHERE s.sale_date BETWEEN ? AND ?
      GROUP BY region ORDER BY total DESC"
);
$byRegion->execute([$from, $to]);
$byRegion = $byRegion->fetchAll();

$page_title = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<h3 class="mb-3">Reports</h3>

<form class="row g-2 mb-4" method="get">
  <div class="col-md-3"><label class="form-label small">From</label>
    <input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="col-md-3"><label class="form-label small">To</label>
    <input class="form-control" type="date" name="to"   value="<?= e($to) ?>"></div>
  <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Generate</button></div>
</form>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card"><div class="card-header">Sales by rep</div>
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
        </tbody>
      </table></div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card"><div class="card-header">Sales by region</div>
      <div class="table-responsive"><table class="table table-clean mb-0">
        <thead><tr><th>Region</th><th class="text-end">Invoices</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        <?php foreach ($byRegion as $r): ?>
          <tr><td><?= e($r['region']) ?></td><td class="text-end"><?= (int)$r['invoices'] ?></td>
              <td class="text-end fw-bold"><?= money($r['total']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card"><div class="card-header">Top products</div>
      <div class="table-responsive"><table class="table table-clean mb-0">
        <thead><tr><th>SKU</th><th>Product</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
        <tbody>
        <?php foreach ($byProduct as $p): ?>
          <tr><td class="font-monospace small"><?= e($p['sku']) ?></td>
              <td><?= e($p['name']) ?></td>
              <td class="text-end"><?= (int)$p['qty'] ?></td>
              <td class="text-end fw-bold"><?= money($p['total']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card"><div class="card-header">Top clients</div>
      <div class="table-responsive"><table class="table table-clean mb-0">
        <thead><tr><th>Client</th><th class="text-end">Invoices</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        <?php foreach ($byClient as $c): ?>
          <tr><td><?= e($c['name']) ?></td>
              <td class="text-end"><?= (int)$c['invoices'] ?></td>
              <td class="text-end fw-bold"><?= money($c['total']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
