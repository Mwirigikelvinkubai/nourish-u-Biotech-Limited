<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $repId    = $u['role']==='rep' ? (int)$u['id'] : ((int)post('rep_id') ?: (int)$u['id']);
    $spentOn  = post('spent_on') ?: date('Y-m-d');
    $category = clean(post('category')) ?: 'Other';
    $amount   = (float)post('amount');
    $desc     = clean(post('description'));
    $clientId = (int)post('client_id') ?: null;

    if ($amount <= 0 || $desc === '') {
        flash('danger','Amount and description are required.');
        redirect(url('expenses/add.php'));
    }

    // Optional receipt upload
    $receiptRel = null;
    if (!empty($_FILES['receipt']['name']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['receipt'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf','jpg','jpeg','png'], true)) {
            flash('danger','Receipt must be PDF / JPG / PNG.');
            redirect(url('expenses/add.php'));
        }
        if ($f['size'] > 5 * 1024 * 1024) {
            flash('danger','Receipt max 5MB.');
            redirect(url('expenses/add.php'));
        }
        $dir = UPLOAD_DIR . DIRECTORY_SEPARATOR . 'expenses' . DIRECTORY_SEPARATOR . $repId;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $safe = preg_replace('/[^A-Za-z0-9._-]/','_', $f['name']);
        $fname = time() . '_' . $safe;
        if (move_uploaded_file($f['tmp_name'], $dir . DIRECTORY_SEPARATOR . $fname)) {
            $receiptRel = "uploads/expenses/$repId/$fname";
        }
    }

    $pdo->prepare(
        "INSERT INTO expenses (rep_id, spent_on, category, amount, description,
                                receipt_path, client_id, status)
         VALUES (?,?,?,?,?,?,?, 'pending')"
    )->execute([$repId, $spentOn, $category, $amount, $desc, $receiptRel, $clientId]);

    $newId = (int)$pdo->lastInsertId();
    audit($pdo,'expense.create','expense',$newId, money($amount));
    flash('success','Expense logged. Awaiting approval.');
    redirect(url('expenses/view.php?id=' . $newId));
}

$reps    = $pdo->query("SELECT id, name FROM users WHERE role='rep' AND deleted_at IS NULL ORDER BY name")->fetchAll();
$clients = $pdo->query("SELECT id, name FROM clients WHERE deleted_at IS NULL ORDER BY name")->fetchAll();

$page_title = 'Log expense';
require __DIR__ . '/../includes/header.php';
?>
<h3 class="mb-3">Log expense</h3>

<form method="post" enctype="multipart/form-data" class="card p-3" style="max-width:760px;">
  <?= csrf_field() ?>
  <div class="row g-3">
    <?php if ($u['role'] !== 'rep'): ?>
    <div class="col-md-6"><label class="form-label">Rep</label>
      <select class="form-select" name="rep_id" required>
        <option value="">— Select rep —</option>
        <?php foreach ($reps as $r): ?>
          <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php endif; ?>

    <div class="col-md-3"><label class="form-label">Date spent</label>
      <input class="form-control" type="date" name="spent_on" value="<?= e(date('Y-m-d')) ?>" required></div>

    <div class="col-md-3"><label class="form-label">Category</label>
      <select class="form-select" name="category">
        <?php foreach (['Fuel','Transport','Meals','Airtime','Accommodation','Stationery','Client gifts','Other'] as $c): ?>
          <option value="<?= e($c) ?>"><?= e($c) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="col-md-3"><label class="form-label">Amount (<?= e(APP_CURRENCY) ?>)</label>
      <input class="form-control" type="number" step="0.01" min="0.01" name="amount" required></div>

    <div class="col-md-6"><label class="form-label">Linked client (optional)</label>
      <select class="form-select" name="client_id">
        <option value="">— None —</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="col-12"><label class="form-label">Explanation / description</label>
      <textarea class="form-control" name="description" rows="3" required
                placeholder="What did you spend on, and why?"></textarea></div>

    <div class="col-md-6"><label class="form-label">Attach receipt (PDF / JPG / PNG, max 5MB)</label>
      <input class="form-control" type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png"></div>
  </div>

  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary"><i class="bi bi-save"></i> Submit for approval</button>
    <a class="btn btn-outline-secondary" href="<?= url('expenses/index.php') ?>">Cancel</a>
  </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
