<?php
require_once __DIR__ . '/config/config.php';
require_login();

$u = current_user();

/* Load user + rep profile if any */
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$u['id']]);
$user = $user->fetch();

$rep = null;
if ($u['role'] === 'rep') {
    $rep = $pdo->prepare("SELECT * FROM rep_profiles WHERE user_id = ?");
    $rep->execute([$u['id']]);
    $rep = $rep->fetch() ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = clean(post('name'));
    $phone = clean(post('phone'));
    $newPw = (string)post('new_password');

    $sql = "UPDATE users SET name=?, phone=?";
    $args = [$name, $phone];
    if ($newPw !== '') {
        if (strlen($newPw) < 6) { flash('danger', 'Password must be at least 6 characters.'); redirect(url('profile.php')); }
        $sql .= ", password_hash=?";
        $args[] = password_hash($newPw, PASSWORD_BCRYPT);
    }
    $sql .= " WHERE id = ?";
    $args[] = $u['id'];
    $pdo->prepare($sql)->execute($args);

    if ($u['role'] === 'rep') {
        $existing = $pdo->prepare("SELECT user_id FROM rep_profiles WHERE user_id=?");
        $existing->execute([$u['id']]);
        $hasRow = (bool)$existing->fetchColumn();

        $fields = [
            'id_number'        => clean(post('id_number')),
            'license_no'       => clean(post('license_no')),
            'region'           => clean(post('region')),
            'monthly_target'   => (float)post('monthly_target'),
            'hire_date'        => post('hire_date') ?: null,
            'bank_name'        => clean(post('bank_name')),
            'bank_account'     => clean(post('bank_account')),
            'next_of_kin'      => clean(post('next_of_kin')),
            'next_of_kin_phone'=> clean(post('next_of_kin_phone')),
            'bio'              => clean(post('bio')),
        ];

        if ($hasRow) {
            $sets = implode(',', array_map(fn($k)=>"$k=?", array_keys($fields)));
            $args = array_values($fields);
            $args[] = $u['id'];
            $pdo->prepare("UPDATE rep_profiles SET $sets WHERE user_id=?")->execute($args);
        } else {
            $cols = 'user_id,' . implode(',', array_keys($fields));
            $marks = '?,' . implode(',', array_fill(0, count($fields), '?'));
            $args = array_merge([$u['id']], array_values($fields));
            $pdo->prepare("INSERT INTO rep_profiles ($cols) VALUES ($marks)")->execute($args);
        }
    }

    /* refresh session display name */
    $_SESSION['user']['name'] = $name;
    flash('success', 'Profile updated.');
    redirect(url('profile.php'));
}

$page_title = 'My Profile';
require __DIR__ . '/includes/header.php';
?>
<h3 class="mb-3">My Profile</h3>

<form method="post" class="card p-3">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Full name</label>
      <input class="form-control" name="name" required value="<?= e($user['name']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Email</label>
      <input class="form-control" value="<?= e($user['email']) ?>" disabled>
    </div>
    <div class="col-md-6">
      <label class="form-label">Phone</label>
      <input class="form-control" name="phone" value="<?= e($user['phone']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Role</label>
      <input class="form-control" value="<?= e(role_label($user['role'])) ?>" disabled>
    </div>

    <?php if ($u['role'] === 'rep'): ?>
      <div class="col-12"><hr><h6 class="text-muted">Rep details</h6></div>
      <div class="col-md-4"><label class="form-label">National ID</label>
        <input class="form-control" name="id_number" value="<?= e($rep['id_number'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Pharmacy &amp; Poisons Board licence</label>
        <input class="form-control" name="license_no" value="<?= e($rep['license_no'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Region / Territory</label>
        <input class="form-control" name="region" value="<?= e($rep['region'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Monthly target (<?= e(APP_CURRENCY) ?>)</label>
        <input class="form-control" name="monthly_target" type="number" step="0.01" min="0"
               value="<?= e($rep['monthly_target'] ?? '0') ?>"></div>
      <div class="col-md-4"><label class="form-label">Hire date</label>
        <input class="form-control" name="hire_date" type="date" value="<?= e($rep['hire_date'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Next of kin</label>
        <input class="form-control" name="next_of_kin" value="<?= e($rep['next_of_kin'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Next of kin phone</label>
        <input class="form-control" name="next_of_kin_phone" value="<?= e($rep['next_of_kin_phone'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Bank name</label>
        <input class="form-control" name="bank_name" value="<?= e($rep['bank_name'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Bank account</label>
        <input class="form-control" name="bank_account" value="<?= e($rep['bank_account'] ?? '') ?>"></div>
      <div class="col-12"><label class="form-label">Bio</label>
        <textarea class="form-control" name="bio" rows="2"><?= e($rep['bio'] ?? '') ?></textarea></div>
    <?php endif; ?>

    <div class="col-12"><hr><h6 class="text-muted">Change password (optional)</h6></div>
    <div class="col-md-6">
      <label class="form-label">New password</label>
      <input class="form-control" type="password" name="new_password" autocomplete="new-password" minlength="6">
    </div>
  </div>
  <div class="mt-3">
    <button class="btn btn-primary"><i class="bi bi-save"></i> Save changes</button>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
