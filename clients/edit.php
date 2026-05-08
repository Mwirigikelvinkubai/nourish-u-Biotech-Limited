<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$u = current_user();

$id = (int)get('id');
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); die('Client not found.'); }

if ($u['role'] === 'rep' && (int)$row['rep_id'] !== (int)$u['id']) {
    http_response_code(403); die('Not allowed.');
}

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
        'notes'          => clean(post('notes')),
        'kyc_notes'      => clean(post('kyc_notes')),
    ];
    if ($u['role'] === 'admin') {
        $data['kyc_status'] = post('kyc_status') ?: 'pending';
        $data['rep_id']     = (int)post('rep_id') ?: null;
    }
    $sets = [];
    foreach (array_keys($data) as $k) { $sets[] = "`$k`=?"; }
    $sets = implode(',', $sets);
    $args = array_values($data);
    $args[] = $id;
    $pdo->prepare("UPDATE clients SET $sets WHERE id=?")->execute($args);
    audit($pdo,'client.update','client',$id);
    flash('success','Client updated.');
    redirect(url('clients/view.php?id=' . $id));
}

$reps = $pdo->query("SELECT id,name FROM users WHERE role='rep' AND status='active' ORDER BY name")->fetchAll();

$page_title = 'Edit ' . $row['name'];
require __DIR__ . '/../includes/header.php';
?>
<h3 class="mb-3">Edit client – <?= e($row['name']) ?></h3>
<form method="post" class="card p-3">
  <?php include __DIR__ . '/_form_fields.php'; ?>
  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary"><i class="bi bi-save"></i> Save changes</button>
    <a class="btn btn-outline-secondary" href="<?= url('clients/view.php?id=' . $id) ?>">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
