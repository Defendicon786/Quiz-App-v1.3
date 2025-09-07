<?php
require_once 'includes/auth.php';
requirePermission();
include 'database.php';

$available_pages = [
    'instructorhome.php' => 'Instructor Home',
    'manage_classes_subjects.php' => 'Manage Classes & Subjects',
    'questionfeed.php' => 'Question Feed',
    'view_questions.php' => 'View Questions',
    'quizconfig.php' => 'Set Quiz',
    'manage_quizzes.php' => 'Manage Quizzes',
    'view_quiz_results.php' => 'View Quiz Results',
    'view_student_attempt.php' => 'View Student Attempt',
    'manage_instructors.php' => 'Manage Instructors',
    'manage_students.php' => 'Manage Students',
    'manage_notifications.php' => 'Manage Notifications',
    'paper_home.php' => 'Generate Paper',
    'generate_paper.php' => 'Generate Paper PDF',
    'paper_manage.php' => 'Manage Paper Generator App',
    'manage_permissions.php' => 'Manage Permissions',
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
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <link href="./assets/css/navbar.css" rel="stylesheet" />
    <link href="./assets/css/portal.css" rel="stylesheet" />
    <link href="./assets/css/manage.css" rel="stylesheet" />
    <link href="./assets/css/sidebar.css" rel="stylesheet" />
    <link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
</head>
<body class="dark-mode">
<div class="layout">
    <?php include './includes/sidebar.php'; ?>
    <div class="main">
        <?php include './includes/header.php'; ?>
        <main class="content">
            <h2>Manage Permissions</h2>
            <?php echo $message; ?>
            <form method="post">
                <div class="accordion" id="permAccordion">
                    <?php foreach ($instructors as $index => $inst): $email = $inst['email']; ?>
                    <div class="card">
                        <div class="card-header card-header-primary" id="heading<?php echo $index; ?>">
                            <h4 class="card-title mb-0">
                                <a class="d-flex justify-content-between align-items-center collapsed" data-toggle="collapse" href="#collapse<?php echo $index; ?>" aria-expanded="false" aria-controls="collapse<?php echo $index; ?>">
                                    <span><?php echo htmlspecialchars($inst['name']); ?> (<?php echo htmlspecialchars($email); ?>)</span>
                                    <i class="fas fa-chevron-down toggle-arrow"></i>
                                </a>
                            </h4>
                        </div>
                        <div id="collapse<?php echo $index; ?>" class="collapse" aria-labelledby="heading<?php echo $index; ?>" data-parent="#permAccordion">
                            <div class="card-body">
                                <?php foreach ($available_pages as $file => $label): ?>
                                    <label class="d-block">
                                        <input type="checkbox" name="perm[<?php echo htmlspecialchars($email); ?>][]" value="<?php echo $file; ?>" <?php echo (isset($permissions[$email]) && in_array($file, $permissions[$email])) ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Save</button>
            </form>
        </main>
        <footer class="footer-text">
            <p>Narowal Public School and College</p>
            <p>Developed and Maintained by Sir Hassan Tariq</p>
        </footer>
    </div>
</div>
    <!--   Core JS Files   -->
    <script src="./assets/js/core/jquery.min.js" type="text/javascript"></script>
    <script src="./assets/js/core/popper.min.js" type="text/javascript"></script>
    <script src="./assets/js/core/bootstrap-material-design.min.js" type="text/javascript"></script>
    <script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
    <script src="./assets/js/dark-mode.js"></script>
    <script src="./assets/js/sidebar.js"></script>
</body>
</html>
