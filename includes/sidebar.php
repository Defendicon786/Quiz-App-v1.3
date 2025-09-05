<?php
$logout = isset($_SESSION['studentloggedin']) ? 'studentlogout.php' : 'instructorlogout.php';

// Determine where the "Take Quiz" link should point for students
$quiz_link = 'quizhome.php';
if (isset($_SESSION['studentloggedin']) && $_SESSION['studentloggedin'] === true) {
    // Check if an active quiz is available for the logged-in student
    include_once __DIR__ . '/../database.php';
    if (
        isset($_SESSION['class_id']) &&
        isset($_SESSION['rollnumber']) &&
        $conn
    ) {
        $class_id   = $_SESSION['class_id'];
        $section    = isset($_SESSION['section']) ? $_SESSION['section'] : null;
        $rollnumber = $_SESSION['rollnumber'];

        $quiz_sql = "SELECT quizid FROM quizconfig
                     WHERE starttime <= NOW() AND endtime >= NOW()
                       AND class_id = ?
                       AND (section IS NULL OR LOWER(section) = LOWER(?))
                       AND (SELECT COUNT(*) FROM quizrecord qr
                            WHERE qr.quizid = quizconfig.quizid
                              AND qr.rollnumber = ?) < attempts
                     LIMIT 1";

        if ($stmt = $conn->prepare($quiz_sql)) {
            $section_param = $section ? $section : '';
            $stmt->bind_param('isi', $class_id, $section_param, $rollnumber);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $quiz_link = 'quizpage.php';
                }
            }
            $stmt->close();
        }
    }
}
?>
<aside class="sidebar">
    <button class="toggle-sidebar close-sidebar" type="button"><i class="fas fa-times"></i></button>
    <div class="logo"><span>QuizApp</span></div>
    <ul>
        <?php if(isset($_SESSION['studentloggedin']) && $_SESSION['studentloggedin'] === true): ?>
            <li><a href="<?php echo $quiz_link; ?>"><i class="fas fa-pencil-alt"></i><span>Take Quiz</span></a></li>
            <li><a href="my_results.php"><i class="fas fa-chart-line"></i><span>My Result</span></a></li>
        <?php else: ?>
            <li><a href="manage_classes_subjects.php"><i class="fas fa-book"></i><span>Manage Classes &amp; Subjects</span></a></li>
            <li><a href="questionfeed.php"><i class="fas fa-upload"></i><span>Feed Questions</span></a></li>
            <li><a href="view_questions.php"><i class="fas fa-database"></i><span>Questions Bank</span></a></li>
            <li><a href="quizconfig.php"><i class="fas fa-cog"></i><span>Set Quiz</span></a></li>
            <li><a href="manage_quizzes.php"><i class="fas fa-tasks"></i><span>Manage Quizzes</span></a></li>
            <li><a href="view_quiz_results.php"><i class="fas fa-chart-bar"></i><span>View Results</span></a></li>
            <li><a href="manage_instructors.php"><i class="fas fa-user-tie"></i><span>Manage Instructors</span></a></li>
            <li><a href="manage_students.php"><i class="fas fa-user-graduate"></i><span>Manage Students</span></a></li>
            <li><a href="manage_notifications.php"><i class="fas fa-bell"></i><span>Manage Notifications</span></a></li>
            <li><a href="paper_home.php"><i class="fas fa-file-alt"></i><span>Generate Paper</span></a></li>
            <li><a href="paper_manage.php"><i class="fas fa-users-cog"></i><span>Manage Paper Generator App</span></a></li>
            <li><a href="my_profile.php"><i class="fas fa-user"></i><span>My Profile</span></a></li>
        <?php endif; ?>
        <li><a href="<?php echo $logout; ?>"><i class="fas fa-sign-out-alt"></i><span>Log Out</span></a></li>
    </ul>
    <footer class="sidebar-footer">&copy; <?php echo date('Y'); ?> QuizApp</footer>
</aside>
