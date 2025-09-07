<?php
require_once 'includes/auth.php';
requirePermission();
include 'database.php';

$available_pages = [
    'instructorhome.php' => 'Instructor Home',
    'manage_classes_subjects.php' => 'Manage Classes & Subjects',
    'questionfeed.php' => 'Feed Questions',
    'view_questions.php' => 'Questions Bank',
    'quizconfig.php' => 'Set Quiz',
    'manage_quizzes.php' => 'Manage Quizzes',
    'view_quiz_results.php' => 'View Results',
    'manage_instructors.php' => 'Manage Instructors',
    'manage_students.php' => 'Manage Students',
    'manage_notifications.php' => 'Manage Notifications',
    'paper_home.php' => 'Generate Paper',
    'paper_manage.php' => 'Manage Paper Generator App',
    'my_profile.php' => 'My Profile'
];

$permissions = loadPermissions();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPerms = [];
    if (isset($_POST['perm']) && is_array($_POST['perm'])) {
        foreach ($_POST['perm'] as $email => $pages) {
            $safePages = array_values(array_intersect(array_keys($available_pages), $pages));
            if ($safePages) {
                $newPerms[$email] = $safePages;
            }
        }
    }
    file_put_contents(__DIR__ . '/permissions.json', json_encode($newPerms, JSON_PRETTY_PRINT));
    $permissions = $newPerms;
    $message = '<div class="alert alert-success">Permissions updated successfully.</div>';
}

$instructors = [];
$result = $conn->query('SELECT name, email FROM instructorinfo ORDER BY email');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $instructors[] = $row;
    }
    $result->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Manage Permissions</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,700|Material+Icons" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/sidebar.css" rel="stylesheet" />
</head>
<body>
<div class="layout">
    <?php include './includes/sidebar.php'; ?>
    <div class="main">
        <?php include './includes/header.php'; ?>
        <main class="content">
            <h2>Manage Permissions</h2>
            <?php echo $message; ?>
            <form method="post">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Instructor</th>
                            <th>Allowed Pages</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($instructors as $inst): $email = $inst['email']; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($inst['email']); ?></td>
                            <td>
                                <?php foreach ($available_pages as $file => $label): ?>
                                    <label style="display:block">
                                        <input type="checkbox" name="perm[<?php echo htmlspecialchars($email); ?>][]" value="<?php echo $file; ?>" <?php echo (isset($permissions[$email]) && in_array($file, $permissions[$email])) ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        </main>
        <footer class="footer-text">
            <p>Narowal Public School and College</p>
            <p>Developed and Maintained by Sir Hassan Tariq</p>
        </footer>
    </div>
</div>
<script src="./assets/js/sidebar.js"></script>
</body>
</html>
