<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION["instructorloggedin"]) && !isset($_SESSION["studentloggedin"])) {
    $_SESSION['error'] = "Please log in to access this page.";
    header("location: index.php");
    exit;
}

// Include database connection
try {
    include "database.php";
    // Test the connection
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    error_log("Database error in quizhome.php: " . $e->getMessage());
    $_SESSION['error'] = "System error. Please try again later or contact support.";
    header("location: index.php");
    exit;
}

// If a student is logged in, verify that a quiz is available
if (isset($_SESSION['studentloggedin']) && $_SESSION['studentloggedin'] === true) {
    $class_id = isset($_SESSION['class_id']) ? $_SESSION['class_id'] : null;
    $section = isset($_SESSION['section']) ? $_SESSION['section'] : null;
    $rollnumber = isset($_SESSION['rollnumber']) ? $_SESSION['rollnumber'] : null;

    if ($class_id && $rollnumber) {
        $quiz_sql = "SELECT quizid FROM quizconfig
                     WHERE endtime >= NOW()
                       AND class_id = ?
                       AND (section IS NULL OR LOWER(section) = LOWER(?))
                       AND (
                           SELECT COUNT(*) FROM quizrecord qr
                           WHERE qr.quizid = quizconfig.quizid
                             AND qr.rollnumber = ?
                       ) < attempts
                     LIMIT 1";
        $stmt_quiz = $conn->prepare($quiz_sql);
        $section_param = $section ? $section : '';
        $stmt_quiz->bind_param('isi', $class_id, $section_param, $rollnumber);
        $stmt_quiz->execute();
        $result_quiz = $stmt_quiz->get_result();
        $stmt_quiz->close();

        if (!$result_quiz || $result_quiz->num_rows === 0) {
            $_SESSION['no_quiz_message'] = 'No quiz is currently available.';
            header('Location: studenthome.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Quiz Portal - Home</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <link href="./assets/css/navbar.css" rel="stylesheet" />
    <link href="./assets/css/portal.css" rel="stylesheet" />
    <style>
        .navbar {
            background-color: #fff !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            height: 60px;
            padding-top: 20px !important;
        }
        .navbar-brand {
            color: #333 !important;
            font-weight: 600;
            font-size: 1.3rem;
        }
        .nav-link {
        white-space: nowrap;
            color: #333 !important;
            font-weight: 500;
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-title {
            color: #333;
            font-weight: 600;
        }
        .card-text {
            color: #666;
        }
        .btn-primary {
            background-color: #1a73e8;
            border-color: #1a73e8;
        }
        .btn-primary:hover {
            background-color: #1557b0;
            border-color: #1557b0;
        }
        .footer {
            background: #f8f9fa;
            padding: 30px 0;
            color: #666;
        }
        @media (max-width: 768px) {
            .card {
                margin-bottom: 20px;
            }
        }

        @media (max-width: 991px) {
            .navbar .navbar-nav {
                margin-top: 10px;
                background: #fff;
                border-radius: 4px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                padding: 10px;
                max-height: calc(100vh - 120px);
                overflow-y: auto;
            }

            .navbar .nav-item {
                margin: 5px 0;
            }

            .nav-link {
                white-space: nowrap;
                color: #333 !important;
                padding: 8px 15px !important;
            }
        }
    </style>
<link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
</head>
<body class="landing-page sidebar-collapse">
    <!-- Navbar -->
    <nav class="navbar main-navbar fixed-top navbar-expand-lg">
        <div class="container">
            <div class="navbar-translate">
                <a class="navbar-brand" href="quizhome.php">Quiz Portal</a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="navbar-toggler-icon"></span>
                    <span class="navbar-toggler-icon"></span>
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav mx-auto">
                    <?php if (isset($_SESSION["instructorloggedin"]) && $_SESSION["instructorloggedin"] === true): ?>
                    <!-- Instructor Navigation -->
                    <li class="nav-item">
                        <a href="manage_classes_subjects.php" class="nav-link">
                            <i class="material-icons">school</i> Manage Classes & Subjects
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="questionfeed.php" class="nav-link">
                            <i class="material-icons">input</i> Feed Questions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="view_questions.php" class="nav-link">
                            <i class="material-icons">list_alt</i> Questions Bank
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="quizconfig.php" class="nav-link">
                            <i class="material-icons">layers</i> Set Quiz
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage_quizzes.php" class="nav-link">
                            <i class="material-icons">settings</i> Manage Quizzes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="view_quiz_results.php" class="nav-link">
                            <i class="material-icons">assessment</i> View Results
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage_instructors.php" class="nav-link">
                            <i class="material-icons">people</i> Manage Instructors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage_students.php" class="nav-link">
                            <i class="material-icons">group</i> Manage Students
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage_notifications.php" class="nav-link">
                            <i class="material-icons">notifications</i> Manage Notifications
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="instructorlogout.php" class="nav-link">
                            <i class="material-icons">power_settings_new</i> Logout
                        </a>
                    </li>
                    <?php else: ?>
                    <!-- Student Navigation -->
                    <li class="nav-item">
                        <a href="quizpage.php" class="nav-link">
                            <i class="material-icons">assignment</i> Take Quiz
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="my_results.php" class="nav-link">
                            <i class="material-icons">assessment</i> My Results
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="studentlogout.php" class="nav-link">
                            <i class="material-icons">power_settings_new</i> Logout
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <!-- End Navbar -->

    <div class="page-header header-filter" style="background-image: url('./assets/img/bg3.jpg'); background-size: cover; background-position: top center; background-color: #1a73e8;">
        <div class="container" style="padding-top: 100px;">
            <div class="row">
                <?php if (isset($_SESSION["instructorloggedin"]) && $_SESSION["instructorloggedin"] === true): ?>
                <!-- Instructor Dashboard Cards -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="material-icons" style="font-size: 48px; color: #1a73e8;">question_answer</i>
                            <h4 class="card-title">Feed Questions</h4>
                            <p class="card-text">Add new questions to the question bank</p>
                            <a href="questionfeed.php" class="btn btn-primary">Go to Questions</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="material-icons" style="font-size: 48px; color: #1a73e8;">assignment</i>
                            <h4 class="card-title">Set Quiz</h4>
                            <p class="card-text">Create and configure new quizzes</p>
                            <a href="quizconfig.php" class="btn btn-primary">Create Quiz</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="material-icons" style="font-size: 48px; color: #1a73e8;">assessment</i>
                            <h4 class="card-title">View Results</h4>
                            <p class="card-text">Check student performance and results</p>
                            <a href="view_quiz_results.php" class="btn btn-primary">View Results</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Student Dashboard Cards -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="material-icons" style="font-size: 48px; color: #1a73e8;">assignment</i>
                            <h4 class="card-title">Take Quiz</h4>
                            <p class="card-text">Start your quiz now</p>
                            <a href="quizpage.php" class="btn btn-primary">Start Quiz</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="material-icons" style="font-size: 48px; color: #1a73e8;">assessment</i>
                            <h4 class="card-title">My Results</h4>
                            <p class="card-text">View your quiz results and performance</p>
                            <a href="my_results.php" class="btn btn-primary">View Results</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer footer-default">
        <div class="container">
            <div class="copyright text-center">
                <div class="department">A Project of StudyHT.com</div>
                <div class="designer">Designed and Developed by Sir Hassan Tariq</div>
                <div class="year">
                    &copy; <script>document.write(new Date().getFullYear())</script>
                </div>
            </div>
        </div>
    </footer>

    <!--   Core JS Files   -->
    <script src="./assets/js/core/jquery.min.js" type="text/javascript"></script>
    <script src="./assets/js/core/popper.min.js" type="text/javascript"></script>
    <script src="./assets/js/core/bootstrap-material-design.min.js" type="text/javascript"></script>
    <script src="./assets/js/plugins/moment.min.js"></script>
    <script src="./assets/js/plugins/bootstrap-datetimepicker.js" type="text/javascript"></script>
    <script src="./assets/js/plugins/nouislider.min.js" type="text/javascript"></script>
    <script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
<script src="./assets/js/dark-mode.js"></script>
</body>
</html>
