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
        $data = [
            'sku'                 => clean(post('sku')),
            'name'                => clean(post('name')),
            'category'            => clean(post('category')),
            'manufacturer'        => clean(post('manufacturer')),
            'country_of_origin'   => clean(post('country_of_origin')),
            'unit'                => clean(post('unit')) ?: 'pack',
            'price'               => (float)post('price'),
            'cost'                => (float)post('cost'),
            'base_commission_pct' => (float)post('base_commission_pct'),
            'stock_qty'           => (int)post('stock_qty'),
            'reorder_level'       => (int)post('reorder_level'),
            'batch_no'            => clean(post('batch_no')),
            'expiry_date'         => post('expiry_date') ?: null,
            'status'              => post('status') === 'inactive' ? 'inactive' : 'active',
            'description'         => clean(post('description')),
        ];

        if ($editId) {
            $sets = implode(',', array_map(fn($k)=>"$k=?", array_keys($data)));
            $args = array_values($data); $args[] = $editId;
            $pdo->prepare("UPDATE products SET $sets WHERE id=?")->execute($args);
            audit($pdo,'product.update','product',$editId);
            flash('success','Product updated.');
        } else {
            $cols = implode(',', array_keys($data));
            $marks= implode(',', array_fill(0, count($data), '?'));
            $pdo->prepare("INSERT INTO products ($cols) VALUES ($marks)")->execute(array_values($data));
            audit($pdo,'product.create','product',(int)$pdo->lastInsertId());
            flash('success','Product added.');
        }
        redirect(url('admin/products.php'));
    }
}

$page_title = 'Products';
require __DIR__ . '/../includes/header.php';

if (in_array($action, ['add','edit'], true)) {
    $row = ['id'=>0,'sku'=>'','name'=>'','category'=>'','manufacturer'=>'','country_of_origin'=>'',
            'unit'=>'pack','price'=>0,'cost'=>0,'base_commission_pct'=>5,'stock_qty'=>0,'reorder_level'=>0,
            'batch_no'=>'','expiry_date'=>'','status'=>'active','description'=>''];
    if ($action==='edit' && $id) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
        $stmt->execute([$id]); $row = $stmt->fetch() ?: $row;
    }
    ?>
    <h3 class="mb-3"><?= $action==='edit' ? 'Edit product' : 'New product' ?></h3>
    <form method="post" class="card p-3">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="save">
      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">SKU</label>
          <input class="form-control" name="sku" required value="<?= e($row['sku']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Name</label>
          <input class="form-control" name="name" required value="<?= e($row['name']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Category</label>
          <input class="form-control" name="category" value="<?= e($row['category']) ?>"
                 placeholder="OTC Medicine, Supplement, Prescription…"></div>
        <div class="col-md-4"><label class="form-label">Manufacturer</label>
          <input class="form-control" name="manufacturer" value="<?= e($row['manufacturer']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Country of origin</label>
          <input class="form-control" name="country_of_origin" value="<?= e($row['country_of_origin']) ?>"></div>
        <div class="col-md-2"><label class="form-label">Unit</label>
          <input class="form-control" name="unit" value="<?= e($row['unit']) ?>"></div>
        <div class="col-md-2"><label class="form-label">Status</label>
          <select class="form-select" name="status">
            <option value="active"   <?= $row['status']==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $row['status']==='inactive'?'selected':'' ?>>Inactive</option>
          </select></div>

        <div class="col-md-3"><label class="form-label">Price (<?= e(APP_CURRENCY) ?>)</label>
          <input class="form-control" type="number" step="0.01" min="0" name="price" value="<?= e($row['price']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Cost (<?= e(APP_CURRENCY) ?>)</label>
          <input class="form-control" type="number" step="0.01" min="0" name="cost" value="<?= e($row['cost']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Base commission %</label>
          <input class="form-control" type="number" step="0.01" min="0" max="100" name="base_commission_pct" value="<?= e($row['base_commission_pct']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Stock qty</label>
          <input class="form-control" type="number" min="0" name="stock_qty" value="<?= e($row['stock_qty']) ?>"></div>

        <div class="col-md-3"><label class="form-label">Reorder level</label>
          <input class="form-control" type="number" min="0" name="reorder_level" value="<?= e($row['reorder_level']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Batch no.</label>
          <input class="form-control" name="batch_no" value="<?= e($row['batch_no']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Expiry date</label>
          <input class="form-control" type="date" name="expiry_date" value="<?= e($row['expiry_date']) ?>"></div>

        <div class="col-12"><label class="form-label">Description</label>
          <textarea class="form-control" name="description" rows="2"><?= e($row['description']) ?></textarea></div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
        <a class="btn btn-outline-secondary" href="<?= url('admin/products.php') ?>">Cancel</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$rows = $pdo->query("SELECT * FROM products ORDER BY status DESC, name")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Products</h3>
  <a class="btn btn-primary" href="<?= url('admin/products.php?action=add') ?>"><i class="bi bi-plus"></i> New product</a>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-clean align-middle mb-0">
<thead><tr>
  <th>SKU</th><th>Name</th><th>Category</th><th class="text-end">Price</th>
  <th class="text-end">Stock</th><th>Expiry</th><th class="text-end">Comm %</th><th>Status</th><th></th>
</tr></thead><tbody>
<?php foreach ($rows as $p):
  $expSoon = $p['expiry_date'] && strtotime($p['expiry_date']) < strtotime('+90 days');
  $low = $p['stock_qty'] <= $p['reorder_level'];
?>
<tr>
  <td class="font-monospace small"><?= e($p['sku']) ?></td>
  <td>
    <div class="fw-semibold"><?= e($p['name']) ?></div>
    <div class="small text-muted"><?= e($p['manufacturer']) ?> · <?= e($p['country_of_origin']) ?></div>
  </td>
  <td><?= e($p['category']) ?></td>
  <td class="text-end"><?= money($p['price']) ?></td>
  <td class="text-end <?= $low?'text-danger fw-bold':'' ?>"><?= (int)$p['stock_qty'] ?> <?= e($p['unit']) ?></td>
  <td class="<?= $expSoon?'text-warning':'' ?>"><?= e(fdate($p['expiry_date'])) ?></td>
  <td class="text-end"><?= e($p['base_commission_pct']) ?>%</td>
  <td><?= $p['status']==='active'
       ? '<span class="badge badge-soft">Active</span>'
       : '<span class="badge badge-secondary">Inactive</span>' ?></td>
  <td class="text-end">
    <a class="btn btn-sm btn-outline-primary" href="<?= url('admin/products.php?action=edit&id=' . (int)$p['id']) ?>">
      <i class="bi bi-pencil"></i></a>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
