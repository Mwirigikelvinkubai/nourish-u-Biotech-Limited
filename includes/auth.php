<?php
/**
 * Authentication and role guards.
 */

function current_user_id(): ?int {
    return $_SESSION['uid'] ?? null;
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return !empty($_SESSION['uid']);
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['uid']  = (int)$user['id'];
    $_SESSION['user'] = [
        'id'    => (int)$user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): void {
    if (!is_logged_in()) {
        redirect(url('login.php'));
    }
}

/**
 * @param string|string[] $roles  Allowed role(s)
 */
function require_role($roles): void {
    require_login();
    $allowed = (array)$roles;
    $u = current_user();
    if (!$u || !in_array($u['role'], $allowed, true)) {
        http_response_code(403);
        die('<h3 style="font-family:sans-serif;padding:2rem;">Access denied — your role does not have permission to view this page.</h3>');
    }
}

function role_label(string $role): string {
    return match ($role) {
        'admin'      => 'Administrator',
        'rep'        => 'Medical Rep',
        'accountant' => 'Accountant',
        default      => ucfirst($role),
    };
}
