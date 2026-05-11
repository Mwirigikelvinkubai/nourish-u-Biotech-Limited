<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();
$id = (int)get('id');

$stmt = $pdo->prepare("SELECT e.*, u.name AS rep, c.name AS client,
                              a.name AS approver
                         FROM expenses e
                         JOIN users u ON u.id = e.rep_id
                         LEFT JOIN clients c ON c.id = e.client_id
                         LEFT JOIN users a ON a.id = e.approved_by
                        WHERE e.id = ?");
$stmt->execute([$id]); $exp = $stmt->fetch();
if (!$exp) { http_response_code(404); die('Expense not found.'); }
if ($u['role'] === 'rep' && (int)$exp['rep_id'] !== (int)$u['id']) {
    http_response_code(403); die('Not allowed.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    if (in_array($u['role'], ['admin','accountant'], true)) {
        if ($op === 'approve') {
            $pdo->prepare("UPDATE expenses SET status='approved', approved_by=?, approved_at=NOW(), review_notes=? WHERE id=?")
                ->execute([$u['id'], clean(post('review_notes')), $id]);
            audit($pdo,'expense.approve','expense',$id);
            flash('success','Approved.');
        } elseif ($op === 'reject') {
            $pdo->prepare("UPDATE expenses SET status='rejected', approved_by=?, approved_at=NOW(), review_notes=? WHERE id=?")
                ->execute([$u['id'], clean(post('review_notes')), $id]);
            audit($pdo,'expense.reject','expense',$id);
            flash('warning','Rejected.');
        } elseif ($op === 'pay') {
            $pdo->prepare("UPDATE expenses SET status='paid', paid_at=NOW() WHERE id=? AND status='approved'")
                ->execute([$id]);
            audit($pdo,'expense.paid','expense',$id);
            flash('success','Reimbursed.');
        }
    }
    if ($op === 'soft_delete') {
        sd_soft_delete($pdo, 'expenses', $id, (string)post('reason',''));
        flash('success','Archived.');
        redirect(url('expenses/index.php'));
    }
    if ($op === 'restore' && $u['role']==='admin') {
        sd_restore($pdo, 'expenses', $id);
        flash('success','Restored.');
    }
    redirect(url('expenses/view.php?id=' . $id));
}

$page_title = 'Expense #' . $exp['id'];
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <h3 class="mb-0">Expense #<?= (int)$exp['id'] ?>
    <?php $cls = ['pending'=>'badge-warning','approved'=>'badge-info','rejected'=>'badge-danger','paid'=>'badge-soft'][$exp['status']] ?? 'badge-secondary'; ?>
    <span class="badge <?= $cls ?>"><?= e(ucfirst($exp['status'])) ?></span>
    <?php if (!empty($exp['deleted_at'])): ?><span class="badge badge-danger">Archived</span><?php endif; ?>
  </h3>
  <div>
    <a class="btn btn-outline-secondary" href="<?= url('expenses/index.php') ?>"><i class="bi bi-arrow-left"></i> All expenses</a>
    <?php if (empty($exp['deleted_at']) && ($u['role']==='admin' || (int)$exp['rep_id']===(int)$u['id'] && $exp['status']==='pending')): ?>
      <button class="btn btn-outline-danger" onclick="sdConfirm(<?= (int)$exp['id'] ?>)"><i class="bi bi-trash"></i> Delete</button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="card mb-3">
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <p class="mb-1"><strong>Rep:</strong> <?= e($exp['rep']) ?></p>
            <p class="mb-1"><strong>Date spent:</strong> <?= e(fdate($exp['spent_on'])) ?></p>
            <p class="mb-1"><strong>Category:</strong> <span class="badge badge-soft"><?= e($exp['category']) ?></span></p>
            <p class="mb-1"><strong>Amount:</strong> <span class="fs-5 fw-bold text-primary"><?= money($exp['amount']) ?></span></p>
          </div>
          <div class="col-md-6">
            <p class="mb-1"><strong>Client:</strong> <?= e($exp['client'] ?: '-') ?></p>
            <p class="mb-1"><strong>Logged:</strong> <?= e(fdate($exp['created_at'],'d M Y H:i')) ?></p>
            <?php if ($exp['approver']): ?>
              <p class="mb-1"><strong>Reviewed by:</strong> <?= e($exp['approver']) ?>
                <span class="small text-muted">on <?= e(fdate($exp['approved_at'],'d M Y H:i')) ?></span></p>
            <?php endif; ?>
            <?php if ($exp['paid_at']): ?>
              <p class="mb-1"><strong>Paid:</strong> <?= e(fdate($exp['paid_at'],'d M Y H:i')) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <hr>
        <h6 class="text-muted small text-uppercase">Description</h6>
        <p class="mb-0"><?= nl2br(e($exp['description'])) ?></p>
        <?php if (!empty($exp['review_notes'])): ?>
          <hr>
          <h6 class="text-muted small text-uppercase">Reviewer note</h6>
          <p class="mb-0 small"><?= nl2br(e($exp['review_notes'])) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if (in_array($u['role'],['admin','accountant'],true) && empty($exp['deleted_at'])): ?>
      <div class="card mb-3">
        <div class="card-header">Review</div>
        <div class="card-body">
          <form method="post" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-12"><label class="form-label small">Reviewer notes (optional)</label>
              <input class="form-control" name="review_notes" value="<?= e($exp['review_notes']) ?>"></div>
            <div class="col-auto"><button class="btn btn-success" name="op" value="approve"
                <?= $exp['status'] !== 'pending' ? 'disabled' : '' ?>><i class="bi bi-check2"></i> Approve</button></div>
            <div class="col-auto"><button class="btn btn-outline-danger" name="op" value="reject"
                <?= $exp['status'] !== 'pending' ? 'disabled' : '' ?>><i class="bi bi-x"></i> Reject</button></div>
            <div class="col-auto"><button class="btn btn-primary" name="op" value="pay"
                <?= $exp['status'] !== 'approved' ? 'disabled' : '' ?>><i class="bi bi-cash"></i> Mark as reimbursed</button></div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-md-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-paperclip"></i> Receipt</div>
      <div class="card-body text-center">
        <?php if (empty($exp['receipt_path'])): ?>
          <p class="text-muted small mb-0">No receipt attached.</p>
        <?php else:
          $ext = strtolower(pathinfo($exp['receipt_path'], PATHINFO_EXTENSION));
        ?>
          <?php if (in_array($ext, ['jpg','jpeg','png'])): ?>
            <a href="<?= url('expenses/receipt.php?id=' . $id) ?>" target="_blank">
              <img src="<?= url('expenses/receipt.php?id=' . $id) ?>" class="img-fluid rounded" style="max-height:340px;">
            </a>
          <?php else: ?>
            <a class="btn btn-outline-primary" href="<?= url('expenses/receipt.php?id=' . $id) ?>" target="_blank">
              <i class="bi bi-file-earmark-pdf"></i> View receipt</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?= sd_modal_html() ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
