<?php
$u = current_user();
$role = $u['role'] ?? '';
?>
<nav class="sidebar no-print">
  <div class="position-sticky">
    <ul class="nav flex-column">
      <li class="nav-item">
        <a class="nav-link <?= active_if('dashboard.php') ?>" href="<?= url('dashboard.php') ?>">
          <i class="bi bi-grid-1x2"></i> Dashboard
        </a>
      </li>

      <div class="heading">Field Operations</div>
      <li class="nav-item">
        <a class="nav-link <?= active_if('/clients/') ?>" href="<?= url('clients/index.php') ?>">
          <i class="bi bi-shop"></i> Clients (KYC + Map)
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= active_if('/sales/') ?>" href="<?= url('sales/index.php') ?>">
          <i class="bi bi-receipt"></i> Sales &amp; Invoices
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= active_if('/samples/') ?>" href="<?= url('samples/index.php') ?>">
          <i class="bi bi-box-seam"></i> Sample Drops &amp; Pickups
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= active_if('/feedback/') ?>" href="<?= url('feedback/index.php') ?>">
          <i class="bi bi-chat-dots"></i> Feedback &amp; Complaints
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= active_if('/expenses/') ?>" href="<?= url('expenses/index.php') ?>">
          <i class="bi bi-receipt-cutoff"></i> Expenses
        </a>
      </li>


      <?php if (in_array($role, ['admin','accountant'], true)): ?>
        <div class="heading">Catalogue &amp; Stock</div>
        <li class="nav-item">
          <a class="nav-link <?= active_if('/inventory/') ?>" href="<?= url('inventory/index.php') ?>">
            <i class="bi bi-boxes"></i> Inventory &amp; Expiry
          </a>
        </li>
      <?php endif; ?>

      <?php if (in_array($role, ['admin','accountant'], true)): ?>
        <div class="heading">Finance</div>
        <li class="nav-item">
          <a class="nav-link <?= active_if('/commissions/') ?>" href="<?= url('commissions/index.php') ?>">
            <i class="bi bi-cash-coin"></i> Commissions
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= active_if('/reports/index') ?>" href="<?= url('reports/index.php') ?>">
            <i class="bi bi-graph-up"></i> Sales Reports
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= active_if('/reports/contacts') ?>" href="<?= url('reports/contacts.php') ?>">
            <i class="bi bi-person-lines-fill"></i> Client Contacts
          </a>
        </li>
      <?php endif; ?>

      <?php if ($role === 'rep'): ?>
        <div class="heading">My Workspace</div>
        <li class="nav-item">
          <a class="nav-link <?= active_if('commissions/index.php') ?>" href="<?= url('commissions/index.php') ?>">
            <i class="bi bi-cash-coin"></i> My Commissions
          </a>
        </li>
      <?php endif; ?>

      <?php if ($role === 'admin'): ?>
        <div class="heading">Administration</div>
        <li class="nav-item">
          <a class="nav-link <?= active_if('/admin/users') ?>" href="<?= url('admin/users.php') ?>">
            <i class="bi bi-people"></i> Users &amp; Reps
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= active_if('/admin/products') ?>" href="<?= url('admin/products.php') ?>">
            <i class="bi bi-capsule-pill"></i> Products
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= active_if('/admin/tiers') ?>" href="<?= url('admin/tiers.php') ?>">
            <i class="bi bi-bar-chart-steps"></i> Commission Tiers
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= active_if('/admin/settings') ?>" href="<?= url('admin/settings.php') ?>">
            <i class="bi bi-gear"></i> Settings
          </a>
        </li>
      <?php endif; ?>
    </ul>
  </div>
</nav>
