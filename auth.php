<?php
require __DIR__ . '/config.php';

// Ensure default admin exists if no users yet
ensure_default_admin();

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function normalize_role(string $role): string
{
    // Map custom roles to capabilities
    $map = [
        'mid' => 'editor',
        'offlane' => 'editor',
        'carry' => 'cashier',
        'support' => 'admin',
        'soft support' => 'editor',
    ];
    $key = strtolower(trim($role));
    return $map[$key] ?? $role;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    if (!empty($_SESSION['force_password_change']) && basename($_SERVER['PHP_SELF']) !== 'change_password.php') {
        header('Location: change_password.php');
        exit;
    }
}

function login(string $username, string $password): bool
{
    $user = find_user_by_username($username);
    if ($user && verify_password($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
        ];
        if (user_must_change_password($user)) {
            $_SESSION['force_password_change'] = true;
        } else {
            unset($_SESSION['force_password_change']);
        }
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function accessible_modules_for_role(string $role): array
{
    $role = normalize_role($role);
    $modules = [
        ['label' => 'Dashboard', 'url' => 'admin.php', 'roles' => ['admin', 'editor', 'cashier']],
        ['label' => 'Cashier', 'url' => 'pos.php', 'roles' => ['admin', 'cashier']],
        ['label' => 'Ecommerce', 'url' => 'ecommerce.php', 'roles' => ['admin']],
        ['label' => 'Users', 'url' => 'users.php', 'roles' => ['admin']],
        ['label' => 'Suppliers', 'url' => 'suppliers.php', 'roles' => ['admin']],
        ['label' => 'Purchase Orders', 'url' => 'purchase_orders.php', 'roles' => ['admin']],
        ['label' => 'Expenses', 'url' => 'expenses.php', 'roles' => ['admin']],
        ['label' => 'Exports', 'url' => 'exports.php', 'roles' => ['admin']],
        ['label' => 'Reports', 'url' => 'reports.php', 'roles' => ['admin']],
        ['label' => 'Backups', 'url' => 'backup.php', 'roles' => ['admin']],
        ['label' => 'Media', 'url' => 'media.php', 'roles' => ['admin']],
        ['label' => 'Site settings', 'url' => 'site_settings.php', 'roles' => ['admin']],
        ['label' => 'Reusable parts', 'url' => 'parts.php', 'roles' => ['admin', 'editor']],
        ['label' => 'Code snippets', 'url' => 'snippets.php', 'roles' => ['admin', 'editor']],
        ['label' => 'Shop (public)', 'url' => 'shop.php', 'roles' => ['admin', 'editor', 'cashier']],
    ];
    return array_values(array_filter($modules, function ($mod) use ($role) {
        return in_array($role, $mod['roles'], true);
    }));
}

function require_role(array $roles): void
{
    if (!is_logged_in()) {
        require_login();
        return;
    }
    $user = current_user();
    $role = $user ? normalize_role($user['role']) : null;
    $normalizedAllowed = array_map('normalize_role', $roles);
    if (!$user || !in_array($role, $normalizedAllowed, true)) {
        http_response_code(403);
        $allowed = $user ? accessible_modules_for_role($role ?? '') : [];
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access denied</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a;padding:40px;}';
        echo '.card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;max-width:420px;}';
        echo 'a{color:#2563eb;text-decoration:none;} a:hover{text-decoration:underline;}';
        echo 'ul{list-style:none;padding:0;margin:10px 0 0 0;} li{margin:6px 0;}';
        echo '</style></head><body><div class="card">';
        echo '<h2 style="margin-top:0;">Access denied</h2>';
        echo '<p>You do not have permission to view this page.</p>';
        if (!empty($allowed)) {
            echo '<p>You can access:</p><ul>';
            foreach ($allowed as $mod) {
                echo '<li><a href="' . htmlspecialchars($mod['url'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($mod['label'], ENT_QUOTES, 'UTF-8') . '</a></li>';
            }
            echo '</ul>';
        } else {
            echo '<p><a href="login.php?logout=1">Login with a different user</a></p>';
        }
        echo '</div></body></html>';
        exit;
    }
}
