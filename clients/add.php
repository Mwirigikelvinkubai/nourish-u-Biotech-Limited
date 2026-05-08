<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $data = [
        'name'           => clean(post('name')),
        'type'           => in_array(post('type'), ['pharmacy','clinic','hospital','wholesaler','other'], true) ? post('type') : 'pharmacy',
        'license_no'     => clean(post('license_no')),
        'kra_pin'        => clean(post('kra_pin')),
        'contact_person' => clean(post('contact_person')),
        'contact_role'   => clean(post('contact_role')),
        'phone'          => clean(post('phone')),
        'email'          => clean(post('email')),
        'address'        => clean(post('address')),
        'postal_address' => clean(post('postal_address')),
        'region'         => clean(post('region')),
        'city'           => clean(post('city')),
        'lat'            => post('lat') !== '' ? (float)post('lat') : null,
        'lng'            => post('lng') !== '' ? (float)post('lng') : null,
        'kyc_status'     => $u['role']==='admin' ? (post('kyc_status') ?: 'pending') : 'pending',
        'kyc_notes'      => clean(post('kyc_notes')),
        'rep_id'         => $u['role']==='rep' ? $u['id'] : ((int)post('rep_id') ?: null),
        'credit_limit'   => (float)post('credit_limit'),
        'directors'           => clean(post('directors')),
        'accountant_name'     => clean(post('accountant_name')),
        'accountant_phone'    => clean(post('accountant_phone')),
        'bank_name'           => clean(post('bank_name')),
        'bank_branch'         => clean(post('bank_branch')),
        'payment_terms'       => clean(post('payment_terms')),
        'credit_period_days'  => post('credit_period_days') !== '' ? (int)post('credit_period_days') : null,
        'trade_ref_1'         => clean(post('trade_ref_1')),
        'trade_ref_2'         => clean(post('trade_ref_2')),
        'trade_ref_3'         => clean(post('trade_ref_3')),
        'signed_name'         => clean(post('signed_name')),
        'signed_position'     => clean(post('signed_position')),
        'signed_at'           => post('signed_at') ?: null,
        'notes'               => clean(post('notes')),
    ];
    $cols  = '`' . implode('`,`', array_keys($data)) . '`';
    $marks = implode(',', array_fill(0, count($data), '?'));
    $pdo->prepare("INSERT INTO clients ($cols) VALUES ($marks)")->execute(array_values($data));
    $newId = (int)$pdo->lastInsertId();
    audit($pdo,'client.create','client',$newId);
    flash('success','Client created.');
    redirect(url('clients/view.php?id=' . $newId));
}

$reps = $pdo->query("SELECT id,name FROM users WHERE role='rep' AND status='active' ORDER BY name")->fetchAll();

$page_title = 'New client';
require __DIR__ . '/../includes/header.php';
?>
<h3 class="mb-3">New client – Supplier KYC Profile</h3>
<form method="post" class="card p-3">
  <?php include __DIR__ . '/_form_fields.php'; ?>
  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary"><i class="bi bi-save"></i> Save client</button>
    <a class="btn btn-outline-secondary" href="<?= url('clients/index.php') ?>">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
