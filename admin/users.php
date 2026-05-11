<?php
require_once __DIR__ . '/../config/config.php';
require_role('admin');

$action = get('action', 'list');
$id     = (int)get('id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');

    if ($op === 'save') {
        $editId = (int)post('id', 0);
        $name   = clean(post('name'));
        $email  = clean(post('email'));
        $phone  = clean(post('phone'));
        $role   = post('role');
        $status = post('status') ?: 'active';
        $pw     = (string)post('password');

        if (!in_array($role, ['admin','rep','accountant'], true)) {
            flash('danger','Invalid role.'); redirect(url('admin/users.php'));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger','Invalid email.'); redirect(url('admin/users.php?action=' . ($editId?'edit&id='.$editId:'add')));
        }

        if ($editId) {
            $sql = "UPDATE users SET name=?, email=?, phone=?, role=?, status=?";
            $args = [$name, $email, $phone, $role, $status];
            if ($pw !== '') { $sql .= ", password_hash=?"; $args[] = password_hash($pw, PASSWORD_BCRYPT); }
            $sql .= " WHERE id=?"; $args[] = $editId;
            $pdo->prepare($sql)->execute($args);
            audit($pdo,'user.update','user',$editId);
            flash('success','User updated.');
        } else {
            if ($pw === '') { flash('danger','Password required for new user.'); redirect(url('admin/users.php?action=add')); }
            $stmt = $pdo->prepare("INSERT INTO users (name,email,phone,role,status,password_hash) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$name,$email,$phone,$role,$status,password_hash($pw, PASSWORD_BCRYPT)]);
            $newId = (int)$pdo->lastInsertId();
            if ($role === 'rep') {
                $pdo->prepare("INSERT INTO rep_profiles (user_id) VALUES (?)")->execute([$newId]);
            }
            audit($pdo,'user.create','user',$newId);
            flash('success','User created.');
        }
        redirect(url('admin/users.php'));
    }


    if ($op === 'soft_delete') {
        $tid = (int)post('id');
        if ($tid === (int)current_user_id()) {
            flash('danger','You cannot delete your own account.');
            redirect(url('admin/users.php'));
        }
        if (sd_soft_delete($pdo, 'users', $tid, (string)post('reason',''))) {
            flash('success','User archived.');
        } else {
            flash('danger','Could not delete.');
        }
        redirect(url('admin/users.php'));
    }

    if ($op === 'restore') {
        sd_restore($pdo, 'users', (int)post('id'));
        flash('success','User restored.');
        redirect(url('admin/users.php?show=archived'));
    }

    if ($op === 'toggle') {
        $tid = (int)post('id');
        $stmt = $pdo->prepare("UPDATE users SET status = IF(status='active','suspended','active') WHERE id=?");
        $stmt->execute([$tid]);
        audit($pdo,'user.toggle','user',$tid);
        flash('info','User status toggled.');
        redirect(url('admin/users.php'));
    }
}

$page_title = 'Users & Reps';
require __DIR__ . '/../includes/header.php';

if (in_array($action, ['add','edit'], true)) {
    $row = ['id'=>0,'name'=>'','email'=>'','phone'=>'','role'=>'rep','status'=>'active'];
    if ($action === 'edit' && $id) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch() ?: $row;
    } ?>
    <h3 class="mb-3"><?= $action==='edit' ? 'Edit user' : 'New user' ?></h3>
    <form method="post" class="card p-3" style="max-width:760px;">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="save">
      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Full name</label>
          <input class="form-control" name="name" required value="<?= e($row['name']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required value="<?= e($row['email']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Phone</label>
          <input class="form-control" name="phone" value="<?= e($row['phone']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Role</label>
          <select class="form-select" name="role">
            <?php foreach (['admin'=>'Administrator','rep'=>'Medical Rep','accountant'=>'Accountant'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $row['role']===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="col-md-4"><label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="active"     <?= $row['status']==='active'?'selected':'' ?>>Active</option>
            <option value="suspended"  <?= $row['status']==='suspended'?'selected':'' ?>>Suspended</option>
          </select></div>
        <div class="col-md-6"><label class="form-label">Password <?= $action==='edit'?'(leave blank to keep current)':'(required)' ?></label>
          <input class="form-control" type="password" name="password" autocomplete="new-password"
                 minlength="6" <?= $action==='edit'?'':'required' ?>></div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
        <a class="btn btn-outline-secondary" href="<?= url('admin/users.php') ?>">Cancel</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

/* List */
$showArchived = get('show') === 'archived';
$where = $showArchived ? 'u.deleted_at IS NOT NULL' : 'u.deleted_at IS NULL';
$users = $pdo->query("SELECT u.*, rp.region, rp.monthly_target
                       FROM users u
                       LEFT JOIN rep_profiles rp ON rp.user_id = u.id
                       WHERE $where
                       ORDER BY u.role, u.name")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Users &amp; Reps
    <?php if ($showArchived): ?><span class="badge badge-secondary">Archived</span><?php endif; ?>
  </h3>
  <div>
    <?php if ($showArchived): ?>
      <a class="btn btn-outline-secondary" href="<?= url('admin/users.php') ?>"><i class="bi bi-arrow-left"></i> Active users</a>
    <?php else: ?>
      <a class="btn btn-outline-secondary" href="<?= url('admin/users.php?show=archived') ?>"><i class="bi bi-archive"></i> Archived</a>
      <a class="btn btn-primary" href="<?= url('admin/users.php?action=add') ?>"><i class="bi bi-person-plus"></i> New user</a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
<div class="table-responsive">
<table class="table table-clean align-middle mb-0">
  <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Region / target</th><th>Status</th><th>Last login</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><?= e($u['name']) ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= e($u['phone']) ?></td>
      <td><span class="badge badge-soft"><?= e(role_label($u['role'])) ?></span></td>
      <td>
        <?php if ($u['role']==='rep'): ?>
          <?= e($u['region'] ?: '—') ?><div class="small text-muted">Target <?= money($u['monthly_target']) ?></div>
        <?php else: ?>—<?php endif; ?>
      </td>
      <td>
        <?= $u['status']==='active'
            ? '<span class="badge badge-soft">Active</span>'
            : '<span class="badge badge-danger">Suspended</span>' ?>
      </td>
      <td class="small text-muted"><?= e(fdate($u['last_login_at'], 'd M Y H:i')) ?></td>
      <td class="text-end">
        <?php if ($showArchived): ?>
          <form method="post" class="d-inline" onsubmit="return confirm('Restore this user?');">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="restore">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-sm btn-outline-success"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>
          </form>
        <?php else: ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= url('admin/users.php?action=edit&id=' . (int)$u['id']) ?>">
            <i class="bi bi-pencil"></i></a>
          <form method="post" class="d-inline" onsubmit="return confirm('Toggle status?');">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-power"></i></button>
          </form>
          <button class="btn btn-sm btn-outline-danger" onclick="sdConfirm(<?= (int)$u['id'] ?>)">
            <i class="bi bi-trash"></i></button>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

<?= sd_modal_html() ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
