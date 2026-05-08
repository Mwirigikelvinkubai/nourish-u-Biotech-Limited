<?php
/**
 * General-purpose helpers used everywhere.
 */

/* ------------------------------------------------------------------ */
/* Output / input safety                                               */
/* ------------------------------------------------------------------ */

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function clean(?string $s): string {
    return trim(strip_tags($s ?? ''));
}

function post(string $key, $default = null) {
    return $_POST[$key] ?? $default;
}

function get(string $key, $default = null) {
    return $_GET[$key] ?? $default;
}

/* ------------------------------------------------------------------ */
/* CSRF                                                                */
/* ------------------------------------------------------------------ */

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
    $tok = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $tok)) {
        http_response_code(419);
        die('CSRF token mismatch. Please go back and resubmit the form.');
    }
}

/* ------------------------------------------------------------------ */
/* Flash messages                                                      */
/* ------------------------------------------------------------------ */

function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_render(): string {
    if (empty($_SESSION['flash'])) return '';
    $out = '';
    foreach ($_SESSION['flash'] as $f) {
        $type = e($f['type']);
        $out .= "<div class=\"alert alert-{$type} alert-dismissible fade show\" role=\"alert\">"
              . e($f['msg'])
              . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

/* ------------------------------------------------------------------ */
/* Money / dates                                                       */
/* ------------------------------------------------------------------ */

function money($v): string {
    return APP_CURRENCY . ' ' . number_format((float)$v, 2);
}

function fdate(?string $d, string $fmt = 'd M Y'): string {
    if (!$d || $d === '0000-00-00' || $d === '0000-00-00 00:00:00') return '—';
    return date($fmt, strtotime($d));
}

/* ------------------------------------------------------------------ */
/* Misc                                                                */
/* ------------------------------------------------------------------ */

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

function active_if(string $needle, string $haystack = ''): string {
    $haystack = $haystack ?: ($_SERVER['REQUEST_URI'] ?? '');
    return str_contains($haystack, $needle) ? 'active' : '';
}

function setting(PDO $pdo, string $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach ($pdo->query("SELECT `key`, `value` FROM settings") as $row) {
                $cache[$row['key']] = $row['value'];
            }
        } catch (Throwable $e) { /* table may not exist yet */ }
    }
    return $cache[$key] ?? $default;
}

function audit(PDO $pdo, string $action, ?string $entity = null, ?int $entityId = null, ?string $details = null): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO audit_log (user_id, action, entity, entity_id, details, ip)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            current_user_id(),
            $action,
            $entity,
            $entityId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) { /* never break app on audit failure */ }
}

function next_invoice_no(PDO $pdo): string {
    $prefix = setting($pdo, 'invoice_prefix', 'INV-' . date('Y') . '-');
    $stmt   = $pdo->query("SELECT invoice_no FROM sales ORDER BY id DESC LIMIT 1");
    $last   = $stmt->fetchColumn();
    $n = 1;
    if ($last && preg_match('/(\d+)$/', $last, $m)) {
        $n = (int)$m[1] + 1;
    }
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}
