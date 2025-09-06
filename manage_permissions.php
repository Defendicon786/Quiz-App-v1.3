<?php
require_once 'auth.php';
require_super_admin();

$permissionsFile = __DIR__ . '/permissions.json';
$permissions = [];
if (file_exists($permissionsFile)) {
    $permissions = json_decode(file_get_contents($permissionsFile), true) ?: [];
}
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pagesRaw = trim($_POST['pages'] ?? '');
    if ($email !== '') {
        $pages = array_filter(array_map('trim', explode(',', $pagesRaw)));
        $permissions[$email] = $pages;
        file_put_contents($permissionsFile, json_encode($permissions, JSON_PRETTY_PRINT));
        $message = 'Permissions updated for ' . htmlspecialchars($email);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Permissions</title>
    <link rel="stylesheet" href="./assets/css/material-kit.css?v=2.0.4" />
    <link rel="stylesheet" href="./assets/css/modern.css" />
</head>
<body>
<div class="container" style="margin-top:40px;">
    <h2>Manage User Permissions</h2>
    <?php if($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Allowed Pages (comma separated, use * for all)</label>
            <input type="text" name="pages" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
    <h3 class="mt-5">Current Permissions</h3>
    <pre><?php echo htmlspecialchars(json_encode($permissions, JSON_PRETTY_PRINT)); ?></pre>
</div>
</body>
</html>
