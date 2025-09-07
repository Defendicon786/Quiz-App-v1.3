<?php
// Centralized authorization helper functions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SUPER_ADMIN_EMAIL', 'superadmin@example.com');

function isSuperAdmin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
}

function loadPermissions(): array
{
    $file = __DIR__ . '/../permissions.json';
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function canAccess(string $page): bool
{
    if (isSuperAdmin()) {
        return true;
    }
    if (!isset($_SESSION['email'])) {
        return false;
    }
    $permissions = loadPermissions();
    $email = $_SESSION['email'];
    return isset($permissions[$email]) && in_array($page, $permissions[$email]);
}

function requirePermission(?string $page = null): void
{
    if (!isset($_SESSION['instructorloggedin']) || $_SESSION['instructorloggedin'] !== true) {
        header('Location: instructorlogin.php');
        exit;
    }
    $page = $page ?: basename($_SERVER['PHP_SELF']);
    if (!canAccess($page)) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied';
        exit;
    }
}
?>
