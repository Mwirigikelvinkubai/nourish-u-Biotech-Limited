<?php
/**
 * Soft-delete helpers used across the app.
 *
 * Tables that support soft-delete have these columns:
 *   deleted_at    DATETIME      NULL
 *   deleted_by    INT UNSIGNED  NULL
 *   delete_reason VARCHAR(255)  NULL
 *
 * Use sd_soft_delete() to mark a row deleted (records who/why/when).
 * Use sd_restore()     to unmark.
 * Use sd_filter()      to add 'AND tbl.deleted_at IS NULL' fragment.
 */

function sd_soft_delete(PDO $pdo, string $table, int $id, string $reason = ''): bool
{
    $allowed = ['users','products','clients','sales','feedback','sample_drops','expenses'];
    if (!in_array($table, $allowed, true)) return false;

    $reason = trim($reason);
    if ($reason === '') $reason = 'No reason provided';

    $by = current_user_id();
    $stmt = $pdo->prepare(
        "UPDATE `$table`
            SET deleted_at = NOW(),
                deleted_by = ?,
                delete_reason = ?
          WHERE id = ?
            AND deleted_at IS NULL"
    );
    $ok = $stmt->execute([$by, $reason, $id]);
    if ($ok && $stmt->rowCount() > 0) {
        audit($pdo, $table . '.soft_delete', $table, $id, $reason);
        return true;
    }
    return false;
}

function sd_restore(PDO $pdo, string $table, int $id): bool
{
    $allowed = ['users','products','clients','sales','feedback','sample_drops','expenses'];
    if (!in_array($table, $allowed, true)) return false;
    $stmt = $pdo->prepare(
        "UPDATE `$table`
            SET deleted_at = NULL,
                deleted_by = NULL,
                delete_reason = NULL
          WHERE id = ?"
    );
    $ok = $stmt->execute([$id]);
    if ($ok && $stmt->rowCount() > 0) {
        audit($pdo, $table . '.restore', $table, $id);
        return true;
    }
    return false;
}

/**
 * Returns a SQL fragment to filter out deleted rows.
 * Usage: $sql .= " WHERE " . sd_filter('s');     -- alias-prefixed
 *        $sql .= " WHERE " . sd_filter();         -- no alias
 */
function sd_filter(string $alias = ''): string
{
    $a = $alias === '' ? '' : ($alias . '.');
    return "({$a}deleted_at IS NULL)";
}

/**
 * Modal HTML for delete-with-reason. Renders once per page.
 * Submits POST to current URL with op=soft_delete + id + reason.
 */
function sd_modal_html(): string
{
    $csrf = csrf_field();
    return <<<HTML
<div class="modal fade" id="sdModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      $csrf
      <input type="hidden" name="op" value="soft_delete">
      <input type="hidden" name="id" id="sd_id">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-trash text-danger"></i> Confirm delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2">
          This will mark the record as deleted. It's recoverable from <em>Archived</em> by an admin.
        </p>
        <label class="form-label small">Reason for deletion <span class="text-danger">*</span></label>
        <textarea class="form-control" name="reason" rows="3" required minlength="3"
                  placeholder="Why are you deleting this?"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Delete</button>
      </div>
    </form>
  </div>
</div>
<script>
function sdConfirm(id){
  document.getElementById('sd_id').value = id;
  new bootstrap.Modal(document.getElementById('sdModal')).show();
}
</script>
HTML;
}
