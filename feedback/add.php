<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $clientId = (int)post('client_id');
    $repId    = $u['role']==='rep' ? (int)$u['id'] : (int)post('rep_id');
    $saleId   = (int)post('sale_id') ?: null;
    $type     = post('type');
    $sev      = post('severity');
    $msg      = clean(post('message'));

    if (!$clientId || !$repId || !$msg) {
        flash('danger','Client, rep and message are required.');
        redirect(url('feedback/add.php'));
    }
    $pdo->prepare("INSERT INTO feedback (client_id, rep_id, sale_id, type, severity, message, status)
                   VALUES (?,?,?,?,?,?, 'open')")
        ->execute([$clientId, $repId, $saleId, $type, $sev, $msg]);
    audit($pdo,'feedback.create','feedback',(int)$pdo->lastInsertId());
    flash('success','Feedback recorded.');
    redirect(url('feedback/index.php'));
}

$clients = $pdo->query("SELECT id,name FROM clients ORDER BY name")->fetchAll();
$reps    = $pdo->query("SELECT id,name FROM users WHERE role='rep' AND status='active' ORDER BY name")->fetchAll();

$page_title = 'New feedback';
require __DIR__ . '/../includes/header.php';
?>
<h3 class="mb-3">Capture feedback / complaint</h3>
<form method="post" class="card p-3" style="max-width:760px;">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Client *</label>
      <select class="form-select" name="client_id" required>
        <option value="">— Select —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php if ($u['role']!=='rep'): ?>
    <div class="col-md-6"><label class="form-label">Rep *</label>
      <select class="form-select" name="rep_id" required>
        <option value="">— Select —</option>
        <?php foreach ($reps as $r): ?>
          <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="col-md-3"><label class="form-label">Type</label>
      <select class="form-select" name="type">
        <option value="suggestion">Suggestion</option>
        <option value="praise">Praise</option>
        <option value="complaint">Complaint</option>
        <option value="adverse_event">Adverse event</option>
      </select></div>
    <div class="col-md-3"><label class="form-label">Severity</label>
      <select class="form-select" name="severity">
        <option value="low">Low</option>
        <option value="medium">Medium</option>
        <option value="high">High</option>
        <option value="critical">Critical</option>
      </select></div>
    <div class="col-md-3"><label class="form-label">Linked invoice (optional)</label>
      <input class="form-control" name="sale_id" placeholder="Sale ID if known"></div>
    <div class="col-12"><label class="form-label">Message *</label>
      <textarea class="form-control" name="message" rows="4" required></textarea></div>
  </div>
  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
    <a class="btn btn-outline-secondary" href="<?= url('feedback/index.php') ?>">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
