<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_super_admin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}

function require_super_admin(): void {
    if (!is_super_admin()) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied';
        exit;
    }
}

function require_permission(string $page): void {
    if (is_super_admin()) {
        return; // super admin has full access
    }
    $permissionsFile = __DIR__ . '/permissions.json';
    $permissions = [];
    if (file_exists($permissionsFile)) {
        $json = file_get_contents($permissionsFile);
        $permissions = json_decode($json, true) ?: [];
    }
    $email = $_SESSION['email'] ?? '';
    $allowed = $permissions[$email] ?? [];
    if (!in_array('*', $allowed, true) && !in_array($page, $allowed, true)) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied';
        exit;
    }
}
?>
