<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$mine   = $u['role'] === 'rep';
$status = (string)get('status','');
$type   = (string)get('type','');

$where = []; $args = [];
if ($mine)        { $where[] = 'f.rep_id = ?';     $args[] = $u['id']; }
if ($status!=='') { $where[] = 'f.status = ?';     $args[] = $status; }
if ($type!=='')   { $where[] = 'f.type = ?';       $args[] = $type; }

$sql = "SELECT f.*, c.name AS client, u.name AS rep
          FROM feedback f
          JOIN clients c ON c.id = f.client_id
          JOIN users   u ON u.id = f.rep_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY f.created_at DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($args);
$rows = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    if ($op === 'update_status') {
        $fid = (int)post('id');
        $st  = post('status');
        if (in_array($st, ['open','in_progress','resolved','closed'], true)) {
            $pdo->prepare("UPDATE feedback SET status=?, follow_up=? WHERE id=?")
                ->execute([$st, clean(post('follow_up')), $fid]);
            audit($pdo,'feedback.update','feedback',$fid,$st);
            flash('success','Updated.');
        }
        redirect(url('feedback/index.php'));
    }
}

$page_title = 'Feedback & Complaints';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Feedback &amp; Complaints</h3>
  <a class="btn btn-primary" href="<?= url('feedback/add.php') ?>"><i class="bi bi-plus"></i> New entry</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-3">
    <select class="form-select" name="status">
      <option value="">— Any status —</option>
      <?php foreach (['open','in_progress','resolved','closed'] as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <select class="form-select" name="type">
      <option value="">— Any type —</option>
      <?php foreach (['praise','suggestion','complaint','adverse_event'] as $s): ?>
        <option value="<?= $s ?>" <?= $type===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button></div>
</form>

<div class="card"><div class="table-responsive">
<table class="table table-clean align-middle mb-0">
<thead><tr><th>Date</th><th>Client</th><th>Type</th><th>Severity</th><th>Message</th><th>Rep</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $f): ?>
<tr>
  <td class="small"><?= e(fdate($f['created_at'],'d M Y')) ?></td>
  <td><?= e($f['client']) ?></td>
  <td><span class="badge badge-soft"><?= e(ucfirst(str_replace('_',' ',$f['type']))) ?></span></td>
  <td><?php $sev = ['low'=>'badge-soft','medium'=>'badge-info','high'=>'badge-warning','critical'=>'badge-danger'][$f['severity']]; ?>
      <span class="badge <?= $sev ?>"><?= e(ucfirst($f['severity'])) ?></span></td>
  <td><?= e(mb_strimwidth($f['message'], 0, 80, '…')) ?>
      <?php if ($f['follow_up']): ?><div class="small text-muted">↳ <?= e($f['follow_up']) ?></div><?php endif; ?></td>
  <td><?= e($f['rep']) ?></td>
  <td>
    <?php $cls = ['open'=>'badge-warning','in_progress'=>'badge-info','resolved'=>'badge-soft','closed'=>'badge-secondary'][$f['status']] ?? 'badge-secondary'; ?>
    <span class="badge <?= $cls ?>"><?= e(ucfirst(str_replace('_',' ',$f['status']))) ?></span>
  </td>
  <td class="text-end">
    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#fb_<?= (int)$f['id'] ?>">
      <i class="bi bi-pencil"></i></button>
  </td>
</tr>
<tr class="collapse" id="fb_<?= (int)$f['id'] ?>">
  <td colspan="8" class="bg-light">
    <form class="row g-2 align-items-end" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="update_status">
      <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
      <div class="col-md-3"><label class="form-label small">New status</label>
        <select class="form-select form-select-sm" name="status">
          <?php foreach (['open','in_progress','resolved','closed'] as $s): ?>
            <option value="<?= $s ?>" <?= $f['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-7"><label class="form-label small">Follow-up note</label>
        <input class="form-control form-control-sm" name="follow_up" value="<?= e($f['follow_up']) ?>"></div>
      <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Update</button></div>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
