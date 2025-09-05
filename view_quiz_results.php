<?php
session_start();
// Ensure instructor is logged in
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

include "database.php"; // Database connection

// Store current page URL in session to redirect back from calculate_marks.php
$_SESSION['instructor_results_page_url'] = $_SERVER['REQUEST_URI'];

$selected_quiz_number = isset($_GET['quiz_number']) ? intval($_GET['quiz_number']) : 0;
$quiz_results_html = "";

$instructor_email = $_SESSION["email"];

// COMPLETELY NEW APPROACH: Get all quizzes to show instructors - more permissive approach
$all_quizzes_sql = "SELECT DISTINCT qc.quiznumber, qc.quizname 
                    FROM quizconfig qc 
                    ORDER BY qc.quiznumber DESC";
$stmt = $conn->prepare($all_quizzes_sql);
$stmt->execute();
$quizzes_result = $stmt->get_result();

$quizzes_options_html = '<option value="0">Select Quiz</option>';
while ($quiz = $quizzes_result->fetch_assoc()) {
    $selected = ($quiz['quiznumber'] == $selected_quiz_number) ? 'selected' : '';
    $quizzes_options_html .= sprintf(
        '<option value="%d" %s>Quiz #%d - %s</option>',
        $quiz['quiznumber'],
        $selected,
        $quiz['quiznumber'],
        htmlspecialchars($quiz['quizname'])
    );
}

// Fetch classes for student results export
$class_options_html = '<option value="">Select Class</option>';
$classes_sql = "SELECT class_id, class_name FROM classes ORDER BY class_name ASC";
$classes_result = $conn->query($classes_sql);
if ($classes_result) {
    while ($class = $classes_result->fetch_assoc()) {
        $class_options_html .= sprintf(
            '<option value="%d">%s</option>',
            $class['class_id'],
            htmlspecialchars($class['class_name'])
        );
    }
}

if ($selected_quiz_number > 0) {
    // Get quiz details - now show any quiz without instructor restriction
    $quiz_sql = "SELECT qc.*, c.class_name, s.subject_name 
                 FROM quizconfig qc
                 LEFT JOIN classes c ON qc.class_id = c.class_id
                 LEFT JOIN subjects s ON qc.subject_id = s.subject_id
                 WHERE qc.quiznumber = ?";
    $stmt = $conn->prepare($quiz_sql);
    $stmt->bind_param("i", $selected_quiz_number);
    $stmt->execute();
    $quiz_info = $stmt->get_result()->fetch_assoc();

    if ($quiz_info) {
        // Get student results
        $results_sql = "SELECT 
                          s.name as student_name,
                          c.class_name,
                          IFNULL(cs.section_name, s.section) as section_name,
                          s.rollnumber,
                          r.attempt,
                          r.mcqmarks,
                          r.numericalmarks,
                          r.dropdownmarks,
                          r.fillmarks,
                          r.shortmarks,
                          r.essaymarks,
                          r.mcqmarks + r.numericalmarks + r.dropdownmarks + r.fillmarks + r.shortmarks + r.essaymarks as total_marks,
                          qr.starttime,
                          qr.endtime,
                          TIMESTAMPDIFF(MINUTE, qr.starttime, qr.endtime) as time_taken
                       FROM result r
                       JOIN studentinfo s ON r.rollnumber = s.rollnumber
                       LEFT JOIN class_sections cs ON s.section_id = cs.id
                       LEFT JOIN classes c ON cs.class_id = c.class_id
                       JOIN quizrecord qr ON r.quizid = qr.quizid AND r.rollnumber = qr.rollnumber AND r.attempt = qr.attempt
                       WHERE r.quizid = ?
                       ORDER BY r.attempt ASC, total_marks DESC";
        
        $stmt = $conn->prepare($results_sql);
        $stmt->bind_param("i", $quiz_info['quizid']);
        $stmt->execute();
        $results = $stmt->get_result();

        if ($results->num_rows > 0) {
            $quiz_results_html = '<div class="card mt-4">
                <div class="card-header card-header-primary d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-0">Quiz Results</h4>
                        <p class="card-category">
                            ' . htmlspecialchars($quiz_info['quizname']) . ' -
                            Class: ' . htmlspecialchars($quiz_info['class_name'] ?? 'N/A') . ',
                            Subject: ' . htmlspecialchars($quiz_info['subject_name'] ?? 'N/A') . '
                        </p>
                    </div>
                    <a href="results_export.php?quiz_id=' . $selected_quiz_number . '" class="btn btn-success btn-sm" target="_blank">Download All Results</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="text-primary">
                                <tr>
                                    <th>#</th>
                                    <th>Class</th>
                                    <th>Section</th>
                                    <th>Student Name</th>
                                    <th>Roll Number</th>
                                    <th>Attempt</th>
                                    <th>MCQ</th>
                                    <th>Numerical</th>
                                    <th>Dropdown</th>
                                    <th>Fill Blanks</th>
                                    <th>Short Answer</th>
                                    <th>Essay</th>
                                    <th>Total Score</th>
                                    <th>Time Taken</th>
                                    <th>Start Time / Actions</th>
                                </tr>
                            </thead>
                            <tbody>';

                            $serial_number = 1;
                            while ($row = $results->fetch_assoc()) {
                $percentage = ($row['total_marks'] / $quiz_info['maxmarks']) * 100;
                $row_class = '';
                if ($percentage >= 80) $row_class = 'table-success';
                else if ($percentage >= 60) $row_class = 'table-info';
                else if ($percentage >= 40) $row_class = 'table-warning';
                else $row_class = 'table-danger';

                // Add export links with student-specific parameter
                $student_pdf_link = 'direct_export.php?quiz_id=' . $selected_quiz_number . '&student=' . $row['rollnumber'] . '&attempt=' . $row['attempt'] . '&student_specific=1';

                $quiz_results_html .= '<tr class="' . $row_class . ' text-dark">
                    <td>' . $serial_number++ . '</td>
                    <td>' . htmlspecialchars($row['class_name'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($row['section_name'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($row['student_name']) . '</td>
                    <td>' . htmlspecialchars($row['rollnumber']) . '</td>
                    <td>' . htmlspecialchars($row['attempt']) . '</td>
                    <td>' . $row['mcqmarks'] . '</td>
                    <td>' . $row['numericalmarks'] . '</td>
                    <td>' . $row['dropdownmarks'] . '</td>
                    <td>' . $row['fillmarks'] . '</td>
                    <td>' . $row['shortmarks'] . '</td>
                    <td>' . $row['essaymarks'] . '</td>
                    <td><strong>' . $row['total_marks'] . '/' . $quiz_info['maxmarks'] . ' 
                        (' . round($percentage, 1) . '%)</strong></td>
                    <td>' . $row['time_taken'] . ' mins</td>
                    <td>' . date('d M Y, h:i A', strtotime($row['starttime'])) . '
                        <a href="' . $student_pdf_link . '" class="btn btn-sm btn-info ml-2" title="Export student quiz PDF">
                            <i class="material-icons">picture_as_pdf</i>
                        </a>
                        <a href="view_student_attempt.php?quiz_id=' . $selected_quiz_number . '&student=' . $row['rollnumber'] . '&attempt=' . $row['attempt'] . '" class="btn btn-sm btn-primary ml-2" title="View attempted questions">
                            <i class="material-icons">visibility</i>
                        </a>
                    </td>
                </tr>';
            }

            $quiz_results_html .= '</tbody></table></div></div></div>';
        } else {
            $quiz_results_html = '<div class="alert alert-info mt-4">
                No results found for this quiz.
            </div>';
        }
    } else {
        $quiz_results_html = '<div class="alert alert-warning mt-4">
            Quiz not found.
        </div>';
    }
} else if (isset($_GET['quiz_number'])) {
    $quiz_results_html = '<p class="text-muted mt-3">Please select a valid quiz to view results.</p>';
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
    <title>View Quiz Results</title>
    <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
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
    <div class="wrapper">
        <div class="main main-raised">
            <div class="container">
                <div class="section" style="padding-top:0;">
                    <div class="card mb-4">
                        <div class="card-header card-header-primary">
                            <h4 class="card-title mb-0">Download Student Results</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="col-md-4 mb-3">
                                    <select id="class_select" class="form-control">
                                        <?php echo $class_options_html; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <select id="section_select" class="form-control">
                                        <option value="">Select Section</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <select id="student_select" class="form-control">
                                        <option value="">Select Student</option>
                                    </select>
                                </div>
                            </div>
                            <button id="download_student_pdf" class="btn btn-success" type="button" disabled>Download Student Results PDF</button>
                        </div>
                    </div>

                    <form method="GET" action="view_quiz_results.php" class="form-inline justify-content-center mb-4">
                        <div class="form-group">
                            <label for="quiz_number_select" class="mr-2">Select Quiz:</label>
                            <select name="quiz_number" id="quiz_number_select" class="form-control mr-2" onchange="this.form.submit()">
                                <?php echo $quizzes_options_html; ?>
                            </select>
                        </div>
                        <noscript><button type="submit" class="btn btn-primary btn-sm">View Results</button></noscript>
                    </form>
                    
                    <div id="results_display_area">
                        <?php echo $quiz_results_html; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>
  </div>
</div>

    <!--   Core JS Files   -->
    <script src="./assets/js/core/jquery.min.js" type="text/javascript"></script>
    <script src="./assets/js/core/popper.min.js" type="text/javascript"></script>
    <script src="./assets/js/core/bootstrap-material-design.min.js" type="text/javascript"></script>
    <script src="./assets/js/plugins/moment.min.js"></script>
    <script src="./assets/js/plugins/bootstrap-datetimepicker.js" type="text/javascript"></script>
    <script src="./assets/js/plugins/nouislider.min.js" type="text/javascript"></script>
    <script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
<script src="./assets/js/dark-mode.js"></script>
    <script>
    $(document).ready(function() {
        $('#class_select').on('change', function() {
            var classId = $(this).val();
            $('#section_select').html('<option value="">Select Section</option>');
            $('#student_select').html('<option value="">Select Student</option>');
            $('#download_student_pdf').prop('disabled', true);
            if (classId) {
                $.getJSON('get_sections.php', { class_id: classId }, function(data) {
                    var options = '<option value="">Select Section</option>';
                    $.each(data, function(index, section) {
                        options += '<option value="' + section.id + '">' + section.section_name + '</option>';
                    });
                    $('#section_select').html(options);
                });
            }
        });

        $('#section_select').on('change', function() {
            var classId = $('#class_select').val();
            var sectionId = $(this).val();
            $('#student_select').html('<option value="">Select Student</option>');
            $('#download_student_pdf').prop('disabled', true);
            if (classId) {
                $.getJSON('get_students.php', { class_id: classId, section_id: sectionId }, function(data) {
                    var options = '<option value="">Select Student</option>';
                    $.each(data, function(index, student) {
                        options += '<option value="' + student.rollnumber + '">' + student.name + ' (' + student.rollnumber + ')</option>';
                    });
                    $('#student_select').html(options);
                });
            }
        });

        $('#student_select').on('change', function() {
            $('#download_student_pdf').prop('disabled', $(this).val() === '');
        });

        $('#download_student_pdf').on('click', function() {
            var roll = $('#student_select').val();
            if (roll) {
                var url = 'results_export.php?student=' + roll;
                window.open(url, '_blank');
            }
        });
    });
    </script>
<script src="./assets/js/sidebar.js"></script>
</body>
</html>
