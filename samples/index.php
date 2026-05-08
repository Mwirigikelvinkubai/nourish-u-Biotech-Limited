<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$mine   = $u['role'] === 'rep';
$status = (string)get('status','');

$where = []; $args = [];
if ($mine)        { $where[] = 'sd.rep_id = ?'; $args[] = $u['id']; }
if ($status!=='') { $where[] = 'sd.status = ?'; $args[] = $status; }

$sql = "SELECT sd.*, c.name AS client, u.name AS rep
          FROM sample_drops sd
          JOIN clients c ON c.id = sd.client_id
          JOIN users u ON u.id = sd.rep_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY sd.scheduled_date DESC, sd.id DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($args);
$rows = $stmt->fetchAll();

$page_title = 'Sample drops';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Free sample drops &amp; pickups</h3>
  <a class="btn btn-primary" href="<?= url('samples/add.php') ?>"><i class="bi bi-plus"></i> Schedule drop</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-3">
    <select class="form-select" name="status">
      <option value="">— Any status —</option>
      <?php foreach (['scheduled','dropped','picked_up','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button></div>
</form>

<div class="card"><div class="table-responsive">
<table class="table table-clean align-middle mb-0">
<thead><tr><th>Scheduled</th><th>Client</th><th>Rep</th><th>Drop date</th><th>Pickup date</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e(fdate($r['scheduled_date'])) ?></td>
    <td><a href="<?= url('clients/view.php?id=' . (int)$r['client_id']) ?>"><?= e($r['client']) ?></a></td>
    <td><?= e($r['rep']) ?></td>
    <td><?= e(fdate($r['drop_date'])) ?></td>
    <td><?= e(fdate($r['pickup_date'])) ?></td>
    <td>
      <?php $cls = ['scheduled'=>'badge-info','dropped'=>'badge-warning','picked_up'=>'badge-soft','cancelled'=>'badge-secondary'][$r['status']] ?? 'badge-secondary'; ?>
      <span class="badge <?= $cls ?>"><?= e(ucfirst(str_replace('_',' ',$r['status']))) ?></span>
    </td>
    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('samples/view.php?id=' . (int)$r['id']) ?>"><i class="bi bi-eye"></i></a></td>
  </tr>
<?php endforeach; ?>
</tbody></table></div></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
