<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$u = current_user();
$mine = $u['role'] === 'rep';
$kyc  = (string)get('kyc', '');
$q    = trim((string)get('q', ''));

$where = []; $args = [];
if ($mine)            { $where[] = 'c.rep_id = ?';      $args[] = $u['id']; }
if ($kyc !== '')      { $where[] = 'c.kyc_status = ?';  $args[] = $kyc; }
if ($q !== '')        { $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.contact_person LIKE ?)';
                        $like = "%$q%"; array_push($args, $like, $like, $like); }

$sql = "SELECT c.*, u.name AS rep_name FROM clients c
        LEFT JOIN users u ON u.id = c.rep_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY c.name';
$stmt = $pdo->prepare($sql); $stmt->execute($args);
$rows = $stmt->fetchAll();

$page_title = 'Clients';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Clients</h3>
  <a class="btn btn-primary" href="<?= url('clients/add.php') ?>"><i class="bi bi-plus"></i> New client</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input class="form-control" name="q" placeholder="Search name / phone / contact" value="<?= e($q) ?>"></div>
  <div class="col-md-3">
    <select class="form-select" name="kyc">
      <option value="">— Any KYC status —</option>
      <?php foreach (['pending','verified','rejected'] as $k): ?>
        <option value="<?= $k ?>" <?= $kyc===$k?'selected':'' ?>><?= ucfirst($k) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button></div>
</form>

<div class="card mb-3">
  <div class="card-header"><i class="bi bi-geo-alt"></i> Client locations</div>
  <div id="map"></div>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-clean align-middle mb-0">
<thead><tr><th>Name</th><th>Type</th><th>Region</th><th>Contact</th><th>Rep</th><th>KYC</th><th></th></tr></thead>
<tbody>
<?php if (!$rows): ?>
  <tr><td colspan="7" class="text-center text-muted py-4">No clients yet.</td></tr>
<?php else: foreach ($rows as $c): ?>
  <tr>
    <td><a href="<?= url('clients/view.php?id=' . (int)$c['id']) ?>"><?= e($c['name']) ?></a>
        <div class="small text-muted"><?= e($c['address']) ?></div></td>
    <td><span class="badge badge-soft"><?= e(ucfirst($c['type'])) ?></span></td>
    <td><?= e($c['region']) ?></td>
    <td><?= e($c['contact_person']) ?><div class="small text-muted"><?= e($c['phone']) ?></div></td>
    <td><?= e($c['rep_name'] ?: '—') ?></td>
    <td>
      <?php $cls = ['pending'=>'badge-warning','verified'=>'badge-soft','rejected'=>'badge-danger'][$c['kyc_status']]; ?>
      <span class="badge <?= $cls ?>"><?= e(ucfirst($c['kyc_status'])) ?></span>
    </td>
    <td class="text-end">
      <a class="btn btn-sm btn-outline-primary" href="<?= url('clients/edit.php?id=' . (int)$c['id']) ?>"><i class="bi bi-pencil"></i></a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= url('clients/view.php?id=' . (int)$c['id']) ?>"><i class="bi bi-eye"></i></a>
    </td>
  </tr>
<?php endforeach; endif; ?>
</tbody></table>
</div></div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  var pts = <?php
    $pts = array_values(array_filter(array_map(fn($r)=>[
      'id' => (int)$r['id'],
      'name' => $r['name'],
      'lat' => $r['lat'],
      'lng' => $r['lng'],
      'kyc' => $r['kyc_status'],
    ], $rows), fn($p)=>$p['lat'] !== null && $p['lng'] !== null));
    echo json_encode($pts);
  ?>;
  var center = pts.length ? [pts[0].lat, pts[0].lng] : [-1.286389, 36.817223];
  var map = L.map('map').setView(center, 11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
  }).addTo(map);
  pts.forEach(function(p){
    L.marker([p.lat, p.lng]).addTo(map)
      .bindPopup('<strong>'+p.name+'</strong><br><small>KYC: '+p.kyc+'</small>'
                 +'<br><a href="<?= url('clients/view.php?id=') ?>'+p.id+'">View →</a>');
  });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
