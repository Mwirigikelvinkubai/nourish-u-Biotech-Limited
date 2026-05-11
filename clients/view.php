<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$id = (int)get('id');
$stmt = $pdo->prepare("SELECT c.*, u.name AS rep_name FROM clients c
                       LEFT JOIN users u ON u.id = c.rep_id WHERE c.id=?");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { http_response_code(404); die('Client not found.'); }

if ($u['role'] === 'rep' && (int)$c['rep_id'] !== (int)$u['id']) {
    http_response_code(403); die('Not allowed.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');

    if ($op === 'soft_delete' && $u['role'] === 'admin') {
        if (sd_soft_delete($pdo, 'clients', $id, (string)post('reason',''))) {
            flash('success','Client archived.');
            redirect(url('clients/index.php'));
        }
        flash('danger','Could not delete.');
        redirect(url('clients/view.php?id=' . $id));
    }
    if ($op === 'restore' && $u['role'] === 'admin') {
        sd_restore($pdo, 'clients', $id);
        flash('success','Client restored.');
        redirect(url('clients/view.php?id=' . $id));
    }


    if ($op === 'upload' && !empty($_FILES['doc']['name'])) {
        $type = clean(post('doc_type')) ?: 'Document';
        $f = $_FILES['doc'];
        if ($f['error'] === UPLOAD_ERR_OK) {
            $allow = ['pdf','jpg','jpeg','png'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allow, true)) {
                flash('danger', 'Only PDF / JPG / PNG allowed.');
            } elseif ($f['size'] > 5 * 1024 * 1024) {
                flash('danger', 'Max 5MB per file.');
            } else {
                $dir = UPLOAD_DIR . DIRECTORY_SEPARATOR . 'clients' . DIRECTORY_SEPARATOR . $id;
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                $safe = preg_replace('/[^A-Za-z0-9._-]/','_', $f['name']);
                $fname = time() . '_' . $safe;
                if (move_uploaded_file($f['tmp_name'], $dir . DIRECTORY_SEPARATOR . $fname)) {
                    $rel = "uploads/clients/$id/$fname";
                    $pdo->prepare("INSERT INTO client_documents (client_id, doc_type, file_path, uploaded_by)
                                   VALUES (?,?,?,?)")->execute([$id, $type, $rel, $u['id']]);
                    audit($pdo,'client.document.upload','client',$id,$type);
                    flash('success','Document uploaded.');
                } else {
                    flash('danger','Could not save file.');
                }
            }
        }
        redirect(url('clients/view.php?id=' . $id));
    }

    if ($op === 'kyc' && $u['role'] === 'admin') {
        $st = post('kyc_status');
        if (in_array($st, ['pending','verified','rejected'], true)) {
            $pdo->prepare("UPDATE clients SET kyc_status=?, kyc_notes=? WHERE id=?")
                ->execute([$st, clean(post('kyc_notes')), $id]);
            audit($pdo,'client.kyc.update','client',$id,$st);
            flash('success','KYC status updated.');
        }
        redirect(url('clients/view.php?id=' . $id));
    }
}

$docs = $pdo->prepare("SELECT * FROM client_documents WHERE client_id=? ORDER BY uploaded_at DESC");
$docs->execute([$id]); $docs = $docs->fetchAll();

$sales = $pdo->prepare("SELECT s.*, u.name AS rep
                          FROM sales s JOIN users u ON u.id = s.rep_id
                         WHERE s.client_id=? ORDER BY s.sale_date DESC LIMIT 10");
$sales->execute([$id]); $sales = $sales->fetchAll();

$page_title = $c['name'];
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start mb-3">
  <div>
    <h3 class="mb-1"><?= e($c['name']) ?>
      <small class="text-muted small"><?= e(ucfirst($c['type'])) ?></small></h3>
    <div class="text-muted small">
      <?= e($c['address']) ?><?= $c['city'] ? ' &middot; ' . e($c['city']) : '' ?>
      <?= $c['region'] ? ' &middot; ' . e($c['region']) : '' ?>
    </div>
  </div>
  <div>
    <a class="btn btn-outline-primary" href="<?= url('clients/edit.php?id=' . $id) ?>"><i class="bi bi-pencil"></i> Edit</a>
    <a class="btn btn-purple" href="<?= url('clients/kyc_pdf.php?id=' . $id) ?>" target="_blank">
      <i class="bi bi-file-earmark-pdf"></i> Account Opening Form</a>
    <a class="btn btn-primary" href="<?= url('sales/add.php?client_id=' . $id) ?>"><i class="bi bi-receipt"></i> New sale</a>
    <a class="btn btn-outline-secondary" href="<?= url('samples/add.php?client_id=' . $id) ?>"><i class="bi bi-box-seam"></i> Schedule sample drop</a>
    <?php if ($u['role']==='admin' && empty($c['deleted_at'])): ?>
      <button class="btn btn-outline-danger" onclick="sdConfirm(<?= (int)$c['id'] ?>)"><i class="bi bi-trash"></i> Delete</button>
    <?php elseif ($u['role']==='admin' && !empty($c['deleted_at'])): ?>
      <form method="post" class="d-inline" onsubmit="return confirm('Restore this client?');">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="restore">
        <button class="btn btn-outline-success"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header">Profile &amp; KYC</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <p class="mb-1"><strong>Contact:</strong> <?= e($c['contact_person'] ?: '-') ?>
              <?php if ($c['contact_role']): ?> <span class="text-muted">(<?= e($c['contact_role']) ?>)</span><?php endif; ?></p>
            <p class="mb-1"><strong>Phone:</strong> <?= e($c['phone']) ?></p>
            <p class="mb-1"><strong>Email:</strong> <?= e($c['email']) ?></p>
            <p class="mb-1"><strong>Postal address:</strong> <?= e($c['postal_address'] ?? '') ?: '-' ?></p>
            <p class="mb-1"><strong>Rep:</strong> <?= e($c['rep_name'] ?: '-') ?></p>
          </div>
          <div class="col-md-6">
            <p class="mb-1"><strong>Licence #:</strong> <?= e($c['license_no'] ?: '-') ?></p>
            <p class="mb-1"><strong>KRA PIN:</strong> <?= e($c['kra_pin'] ?: '-') ?></p>
            <p class="mb-1"><strong>KYC status:</strong>
              <?php $cls = ['pending'=>'badge-warning','verified'=>'badge-soft','rejected'=>'badge-danger'][$c['kyc_status']]; ?>
              <span class="badge <?= $cls ?>"><?= e(ucfirst($c['kyc_status'])) ?></span></p>
            <p class="mb-1"><strong>KYC notes:</strong> <?= e($c['kyc_notes'] ?: '-') ?></p>
          </div>
        </div>

        <hr class="my-3">
        <div class="row">
          <div class="col-md-6">
            <h6 class="text-muted small text-uppercase">Directors / partners</h6>
            <p class="small"><?= nl2br(e($c['directors'] ?? '')) ?: '<span class="text-muted">-</span>' ?></p>

            <h6 class="text-muted small text-uppercase mt-2">Accountant</h6>
            <p class="mb-1 small"><?= e($c['accountant_name'] ?? '') ?: '-' ?>
              <?php if (!empty($c['accountant_phone'])): ?> &middot; <?= e($c['accountant_phone']) ?><?php endif; ?></p>
          </div>
          <div class="col-md-6">
            <h6 class="text-muted small text-uppercase">Banking &amp; credit</h6>
            <p class="mb-1 small"><strong>Bank:</strong> <?= e($c['bank_name'] ?? '') ?: '-' ?>
              <?php if (!empty($c['bank_branch'])): ?> (<?= e($c['bank_branch']) ?>)<?php endif; ?></p>
            <p class="mb-1 small"><strong>Credit limit:</strong> <?= money($c['credit_limit']) ?></p>
            <p class="mb-1 small"><strong>Payment terms:</strong> <?= e($c['payment_terms'] ?? '') ?: '-' ?></p>
            <p class="mb-1 small"><strong>Credit period:</strong>
              <?= isset($c['credit_period_days']) && $c['credit_period_days']!==null
                  ? (int)$c['credit_period_days'] . ' days' : '-' ?></p>
          </div>
        </div>

        <?php if (!empty($c['trade_ref_1']) || !empty($c['trade_ref_2']) || !empty($c['trade_ref_3'])): ?>
          <hr class="my-3">
          <h6 class="text-muted small text-uppercase">Trade references</h6>
          <ol class="small mb-0">
            <?php foreach (['trade_ref_1','trade_ref_2','trade_ref_3'] as $k):
              if (!empty($c[$k])): ?>
              <li><?= nl2br(e($c[$k])) ?></li>
            <?php endif; endforeach; ?>
          </ol>
        <?php endif; ?>

        <?php if (!empty($c['signed_name']) || !empty($c['signed_at'])): ?>
          <hr class="my-3">
          <p class="small text-muted mb-0">
            <i class="bi bi-pen"></i> Signed by <strong><?= e($c['signed_name'] ?? '') ?></strong>
            <?php if (!empty($c['signed_position'])): ?> (<?= e($c['signed_position']) ?>)<?php endif; ?>
            <?php if (!empty($c['signed_at'])): ?> on <?= e(fdate($c['signed_at'])) ?><?php endif; ?>
          </p>
        <?php endif; ?>

        <?php if ($u['role'] === 'admin'): ?>
        <hr>
        <form method="post" class="row g-2 align-items-end">
          <?= csrf_field() ?>
          <input type="hidden" name="op" value="kyc">
          <div class="col-md-3">
            <label class="form-label small">Update KYC</label>
            <select class="form-select" name="kyc_status">
              <?php foreach (['pending','verified','rejected'] as $k): ?>
                <option value="<?= $k ?>" <?= $c['kyc_status']===$k?'selected':'' ?>><?= ucfirst($k) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-7">
            <label class="form-label small">Reviewer notes</label>
            <input class="form-control" name="kyc_notes" value="<?= e($c['kyc_notes']) ?>">
          </div>
          <div class="col-md-2"><button class="btn btn-primary w-100">Update</button></div>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-paperclip"></i> KYC documents</div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="row g-2 mb-3">
          <?= csrf_field() ?>
          <input type="hidden" name="op" value="upload">
          <div class="col-md-4">
            <select class="form-select" name="doc_type">
              <option>Pharmacy License</option>
              <option>KRA PIN Certificate</option>
              <option>Director ID</option>
              <option>Premises Photo</option>
              <option>Other</option>
            </select>
          </div>
          <div class="col-md-6"><input type="file" class="form-control" name="doc" required></div>
          <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-upload"></i> Upload</button></div>
        </form>
        <?php if (!$docs): ?>
          <div class="text-muted small">No documents uploaded yet.</div>
        <?php else: ?>
          <ul class="list-group list-group-flush">
          <?php foreach ($docs as $d): ?>
            <li class="list-group-item d-flex justify-content-between">
              <div>
                <span class="badge badge-soft"><?= e($d['doc_type']) ?></span>
                <a href="<?= url('download.php?doc=' . (int)$d['id']) ?>" target="_blank"><?= e(basename($d['file_path'])) ?></a>
              </div>
              <span class="small text-muted"><?= e(fdate($d['uploaded_at'],'d M Y H:i')) ?></span>
            </li>
          <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Recent sales</div>
      <div class="table-responsive">
      <table class="table table-clean mb-0">
        <thead><tr><th>Invoice</th><th>Date</th><th>Rep</th><th class="text-end">Total</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (!$sales): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No sales yet.</td></tr>
          <?php else: foreach ($sales as $s): ?>
            <tr>
              <td><a href="<?= url('sales/view.php?id=' . (int)$s['id']) ?>"><?= e($s['invoice_no']) ?></a></td>
              <td><?= e(fdate($s['sale_date'])) ?></td>
              <td><?= e($s['rep']) ?></td>
              <td class="text-end"><?= money($s['total']) ?></td>
              <td><?= e(ucfirst($s['payment_status'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">Location</div>
      <?php if ($c['lat'] && $c['lng']): ?>
        <div id="map"></div>
      <?php else: ?>
        <div class="card-body text-muted small">No GPS location set yet. Click <a href="<?= url('clients/edit.php?id=' . $id) ?>">Edit</a> to add one.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($c['lat'] && $c['lng']): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
  var lat = <?= json_encode((float)$c['lat']) ?>;
  var lng = <?= json_encode((float)$c['lng']) ?>;
  var map = L.map('map').setView([lat,lng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution:'(c) OpenStreetMap', maxZoom:19 }).addTo(map);
  L.marker([lat,lng]).addTo(map).bindPopup(<?= json_encode($c['name']) ?>).openPopup();
})();
</script>
<?php endif; ?>

<?= sd_modal_html() ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
