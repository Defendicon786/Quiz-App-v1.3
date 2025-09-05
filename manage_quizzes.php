<?php
session_start();
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

include "database.php"; // Database connection
$instructor_email = $_SESSION["email"]; // Get current instructor's email

$feedback_message = "";

// Display export error messages if any
if (isset($_SESSION['export_error'])) {
    $feedback_message = '<div class="alert alert-danger text-center">' . $_SESSION['export_error'] . '</div>';
    unset($_SESSION['export_error']);
}

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['quiz_id'])) {
    $quiz_id_to_delete = intval($_GET['quiz_id']);

    // Start transaction for safe deletion
    $conn->begin_transaction();

    try {
        // Get quizid for the quiz to delete (no permission check now)
        $sql_get_quizid = "SELECT quizid FROM quizconfig WHERE quiznumber = ?";
        $stmt_get_quizid = $conn->prepare($sql_get_quizid);
        $stmt_get_quizid->bind_param("i", $quiz_id_to_delete);
        $stmt_get_quizid->execute();
        $result_quizid = $stmt_get_quizid->get_result();
        
        if ($result_quizid->num_rows > 0) {
            $row = $result_quizid->fetch_assoc();
            $quizid = $row['quizid'];
            $stmt_get_quizid->close();

            // Delete from result table
            $sql_delete_result = "DELETE FROM result WHERE quizid = ?";
            $stmt_result = $conn->prepare($sql_delete_result);
            $stmt_result->bind_param("i", $quizid);
            $stmt_result->execute();
            $stmt_result->close();

            // Delete from response table
            $sql_delete_response = "DELETE FROM response WHERE quizid = ?";
            $stmt_response = $conn->prepare($sql_delete_response);
            $stmt_response->bind_param("i", $quizid);
            $stmt_response->execute();
            $stmt_response->close();

            // Delete from quizconfig table
            $sql_delete_quiz = "DELETE FROM quizconfig WHERE quizid = ?";
            $stmt_quiz = $conn->prepare($sql_delete_quiz);
            $stmt_quiz->bind_param("i", $quizid);
            
            if ($stmt_quiz->execute()) {
                $conn->commit();
                $feedback_message = '<div class="alert alert-success text-center">Quiz #' . $quiz_id_to_delete . ' and its associated data deleted successfully!</div>';
            } else {
                $conn->rollback();
                $feedback_message = '<div class="alert alert-danger text-center">Error deleting quiz. Please try again.</div>';
            }
            $stmt_quiz->close();
        } else {
            $conn->rollback();
            $feedback_message = '<div class="alert alert-danger text-center">Quiz not found.</div>';
        }
    } catch (Exception $e) {
        $conn->rollback();
        $feedback_message = '<div class="alert alert-danger text-center">Error during deletion: ' . $e->getMessage() . '</div>';
    }
    // To prevent re-deletion on refresh, redirect
    // header("Location: manage_quizzes.php"); // Or show message and let user navigate
     echo '<script>history.pushState(null, null, "manage_quizzes.php");</script>'; // Update URL without reload

}

// Filter parameters
$class_filter = isset($_GET['class_filter']) ? intval($_GET['class_filter']) : '';
$section_filter = isset($_GET['section_filter']) ? $_GET['section_filter'] : '';
$quiz_name_filter = isset($_GET['quiz_name_filter']) ? $_GET['quiz_name_filter'] : '';

// Fetch all classes for filter dropdown - Show all classes
$classes = [];
$sql_classes = "SELECT class_id, class_name FROM classes ORDER BY class_name ASC";
$result_classes = $conn->query($sql_classes);
if ($result_classes && $result_classes->num_rows > 0) {
    while ($row = $result_classes->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Fetch all sections for filter dropdown - Show all sections
$sections = [];
if (!empty($class_filter)) {
    $sql_sections = "SELECT DISTINCT qc.section 
                     FROM quizconfig qc 
                     WHERE qc.class_id = ? AND qc.section IS NOT NULL";
    $stmt_sections = $conn->prepare($sql_sections);
    $stmt_sections->bind_param("i", $class_filter);
    $stmt_sections->execute();
    $result_sections = $stmt_sections->get_result();
    
    while ($row = $result_sections->fetch_assoc()) {
        if (!empty($row['section'])) {
            $sections[] = $row['section'];
        }
    }
    $stmt_sections->close();
} else {
    $sql_sections = "SELECT DISTINCT section FROM quizconfig WHERE section IS NOT NULL";
    $result_sections = $conn->query($sql_sections);
    
    if ($result_sections && $result_sections->num_rows > 0) {
        while ($row = $result_sections->fetch_assoc()) {
            if (!empty($row['section'])) {
                $sections[] = $row['section'];
            }
        }
    }
}

// Fetch all quizzes with filters - Now show all quizzes to all instructors
$quizzes = [];
$sql_quizzes = "SELECT qc.quiznumber, qc.quizname, qc.starttime, qc.endtime, qc.duration, qc.subject_id, qc.class_id, qc.section, c.class_name 
                FROM quizconfig qc 
                LEFT JOIN classes c ON qc.class_id = c.class_id
                WHERE 1=1";

$params = [];
$types = "";

if (!empty($class_filter)) {
    $sql_quizzes .= " AND qc.class_id = ?";
    $types .= "i";
    $params[] = $class_filter;
}

if (!empty($section_filter)) {
    $sql_quizzes .= " AND qc.section = ?";
    $types .= "s";
    $params[] = $section_filter;
}

if (!empty($quiz_name_filter)) {
    $sql_quizzes .= " AND qc.quizname LIKE ?";
    $types .= "s";
    $params[] = "%$quiz_name_filter%";
}

$sql_quizzes .= " ORDER BY qc.quiznumber DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql_quizzes);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result_quizzes = $stmt->get_result();
} else {
    $result_quizzes = $conn->query($sql_quizzes);
}

if ($result_quizzes && $result_quizzes->num_rows > 0) {
    while ($row = $result_quizzes->fetch_assoc()) {
        $quizzes[] = $row;
    }
}
if (isset($stmt)) {
    $stmt->close();
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
    <title>Manage Quizzes</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <link href="./assets/css/navbar.css" rel="stylesheet" />
    <link href="./assets/css/portal.css" rel="stylesheet" />
    <link href="./assets/css/manage.css" rel="stylesheet" />
    <link href="./assets/css/sidebar.css" rel="stylesheet" />
    <style>

        /* Additional Styles */
        .quiz-actions .btn {
            margin-right: 5px;
        }
        .page-header {
            margin-top: 60px;
            padding-top: 120px; /* Ensure content sits below fixed navbar */
            min-height: calc(100vh - 60px);
            height: auto; /* Allow page to grow with content */
            display: block; /* Disable flex centering */
            position: relative;
        }
        .card {
            margin-bottom: 30px;
        }
        
        .main-container {
            min-height: calc(100vh - 170px);
            padding-bottom: 50px;
            position: relative;
        }
        .quiz-card {
            background: linear-gradient(to right, #1e1e2f, #27293d);
            color: #fff;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .quiz-card:nth-child(even) {
            background: linear-gradient(to right, #27293d, #1e1e2f);
        }
    </style>
<link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
</head>
<body class="dark-mode">
<div class="layout">
  <?php include './includes/sidebar.php'; ?>
  <div class="main">
    <?php include './includes/header.php'; ?>
    <main class="content">
    <div class="wrapper">
        <div class="main main-raised">
            <div class="container">
                <div class="section" style="padding-top:0;">
                    <?php echo $feedback_message; ?>

                    <!-- Filter Form -->
                    <form method="GET" action="manage_quizzes.php" class="mb-4">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="quiz_name_filter">Quiz Name</label>
                                                <input type="text" class="form-control" id="quiz_name_filter" name="quiz_name_filter" value="<?php echo htmlspecialchars($quiz_name_filter); ?>" placeholder="Filter by quiz name">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="class_filter">Class</label>
                                                <select class="form-control" id="class_filter" name="class_filter">
                                                    <option value="">All Classes</option>
                                                    <?php foreach ($classes as $class): ?>
                                                        <option value="<?php echo $class['class_id']; ?>" <?php echo ($class_filter == $class['class_id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="section_filter">Section</label>
                                                <select class="form-control" id="section_filter" name="section_filter">
                                                    <option value="">All Sections</option>
                                                    <?php foreach ($sections as $section): ?>
                                                        <option value="<?php echo htmlspecialchars($section); ?>" <?php echo ($section_filter == $section) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($section); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <div class="btn-group w-100">
                                                <button type="submit" class="btn btn-primary">Filter</button>
                                                <a href="manage_quizzes.php" class="btn btn-secondary">Clear</a>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <?php if (empty($quizzes)): ?>
                                    <p class="text-center">No quizzes found.</p>
                                <?php else: ?>
                                    <div class="quiz-list">
                                        <?php foreach ($quizzes as $quiz): ?>
                                            <div class="quiz-card">
                                                <div class="quiz-info">
                                                    <h5 class="mb-1">#<?php echo htmlspecialchars($quiz['quiznumber']); ?> <?php echo htmlspecialchars($quiz['quizname'] ?? 'N/A'); ?></h5>
                                                    <div class="small">
                                                        Class: <?php echo htmlspecialchars($quiz['class_name'] ?? 'N/A'); ?> | Section: <?php echo htmlspecialchars($quiz['section'] ?? 'N/A'); ?>
                                                    </div>
                                                    <div class="small">
                                                        Start: <?php echo htmlspecialchars($quiz['starttime']); ?> | End: <?php echo htmlspecialchars($quiz['endtime']); ?> | Duration: <?php echo htmlspecialchars($quiz['duration']); ?> mins
                                                    </div>
                                                </div>
                                                <div class="quiz-actions text-right">
                                                    <a href="edit_quiz.php?quiz_id=<?php echo $quiz['quiznumber']; ?>" class="btn btn-info btn-sm">Edit</a>
                                                    <a href="direct_export.php?quiz_id=<?php echo $quiz['quiznumber']; ?>" class="btn btn-success btn-sm">PDF</a>
                                                    <a href="export.php?quiz_id=<?php echo $quiz['quiznumber']; ?>&export_type=word" class="btn btn-primary btn-sm">Word</a>
                                                    <a href="manage_quizzes.php?action=delete&quiz_id=<?php echo $quiz['quiznumber']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete Quiz #<?php echo $quiz['quiznumber']; ?>? This will also delete associated results and responses.');">Delete</a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                    <div class="text-center mt-4">
                        <a href="quizconfig.php" class="btn btn-primary">Add New Quiz</a>
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- wrapper -->
    </main>
  </div>
</div>

    <!--   Core JS Files   -->
    <script src="./assets/js/core/jquery.min.js" type="text/javascript"></script>
    <script src="./assets/js/core/popper.min.js" type="text/javascript"></script>
    <script src="./assets/js/core/bootstrap-material-design.min.js" type="text/javascript"></script>
    <script src="./assets/js/plugins/moment.min.js"></script>
    <script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
    
    <script>
        $(document).ready(function() {
            // Add event listener for class filter change
            $('#class_filter').on('change', function() {
                var classId = $(this).val();
                var sectionSelect = $('#section_filter');
                var currentSection = '<?php echo htmlspecialchars($section_filter); ?>';
                
                // Clear current options
                sectionSelect.empty();
                sectionSelect.append('<option value="">All Sections</option>');
                
                if (classId) {
                    // Fetch sections for the selected class
                    $.ajax({
                        url: 'get_sections.php?class_id=' + classId,
                        type: 'GET',
                        dataType: 'json',
                        success: function(data) {
                            // Add sections to dropdown
                            $.each(data, function(index, section) {
                                var selected = (section.section_name === currentSection) ? 'selected' : '';
                                sectionSelect.append('<option value="' + section.section_name + '" ' + selected + '>' + section.section_name + '</option>');
                            });
                        },
                        error: function(xhr, status, error) {
                            console.error('Error fetching sections:', error);
                        }
                    });
                }
            });
        });
    </script>
<script src="./assets/js/dark-mode.js"></script>
<script src="./assets/js/sidebar.js"></script>
</body>
</html>
