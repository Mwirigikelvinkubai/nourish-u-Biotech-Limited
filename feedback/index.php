<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$mine   = $u['role'] === 'rep';
$status = (string)get('status','');
$type   = (string)get('type','');

$showArchived = get('show') === 'archived';
$where = [$showArchived ? 'f.deleted_at IS NOT NULL' : 'f.deleted_at IS NULL']; $args = [];
if ($mine)        { $where[] = 'f.rep_id = ?';     $args[] = $u['id']; }
if ($status!=='') { $where[] = 'f.status = ?';     $args[] = $status; }
if ($type!=='')   { $where[] = 'f.type = ?';       $args[] = $type; }

$sql = "SELECT f.*, c.name AS client, u.name AS rep
          FROM feedback f
          JOIN clients c ON c.id = f.client_id
          JOIN users   u ON u.id = f.rep_id";
$sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY f.created_at DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($args);
$rows = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    if ($op === 'soft_delete' && in_array($u['role'], ['admin','accountant'], true)) {
        if (sd_soft_delete($pdo, 'feedback', (int)post('id'), (string)post('reason',''))) {
            flash('success','Feedback archived.');
        }
        redirect(url('feedback/index.php'));
    }
    if ($op === 'restore' && $u['role'] === 'admin') {
        sd_restore($pdo, 'feedback', (int)post('id'));
        flash('success','Feedback restored.');
        redirect(url('feedback/index.php?show=archived'));
    }

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
  <h3 class="mb-0">Feedback &amp; Complaints
    <?php if ($showArchived): ?><span class="badge badge-secondary">Archived</span><?php endif; ?>
  </h3>
  <div>
    <?php if ($showArchived): ?>
      <a class="btn btn-outline-secondary" href="<?= url('feedback/index.php') ?>"><i class="bi bi-arrow-left"></i> Active</a>
    <?php else: ?>
      <?php if (in_array($u['role'],['admin','accountant'],true)): ?>
        <a class="btn btn-outline-secondary" href="<?= url('feedback/index.php?show=archived') ?>"><i class="bi bi-archive"></i> Archived</a>
      <?php endif; ?>
      <a class="btn btn-primary" href="<?= url('feedback/add.php') ?>"><i class="bi bi-plus"></i> New entry</a>
    <?php endif; ?>
  </div>
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
    <?php if ($showArchived && $u['role']==='admin'): ?>
      <form method="post" class="d-inline" onsubmit="return confirm('Restore?');">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="restore">
        <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
        <button class="btn btn-sm btn-outline-success"><i class="bi bi-arrow-counterclockwise"></i></button>
      </form>
    <?php else: ?>
      <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#fb_<?= (int)$f['id'] ?>">
        <i class="bi bi-pencil"></i></button>
      <?php if (in_array($u['role'],['admin','accountant'],true)): ?>
        <button class="btn btn-sm btn-outline-danger" onclick="sdConfirm(<?= (int)$f['id'] ?>)"><i class="bi bi-trash"></i></button>
      <?php endif; ?>
    <?php endif; ?>
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

<?= sd_modal_html() ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
