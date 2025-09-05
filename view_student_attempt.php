<?php
session_start();
// Ensure instructor is logged in
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

include "database.php"; // Database connection

// Check if required parameters are provided
if (!isset($_GET['quiz_id']) || !isset($_GET['student']) || !isset($_GET['attempt'])) {
    $_SESSION['error_message'] = "Missing required parameters";
    header("Location: view_quiz_results.php");
    exit;
}

$quiz_id = intval($_GET['quiz_id']);
$student_rollnumber = intval($_GET['student']);
$student_attempt = intval($_GET['attempt']);

// Get quiz details
$quiz_sql = "SELECT qc.*, c.class_name, s.subject_name 
             FROM quizconfig qc
             LEFT JOIN classes c ON qc.class_id = c.class_id
             LEFT JOIN subjects s ON qc.subject_id = s.subject_id
             WHERE qc.quiznumber = ?";
$stmt = $conn->prepare($quiz_sql);
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quiz) {
    $_SESSION['error_message'] = "Quiz not found";
    header("Location: view_quiz_results.php");
    exit;
}

// Get student details
$student_sql = "SELECT name, department, section FROM studentinfo WHERE rollnumber = ?";
$stmt = $conn->prepare($student_sql);
$stmt->bind_param("i", $student_rollnumber);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    $_SESSION['error_message'] = "Student not found";
    header("Location: view_quiz_results.php");
    exit;
}

// Get student's responses with correct answers
$responses_sql = "
    SELECT 
        r.serialnumber,
        r.qid, 
        r.qtype,
        r.response,
        CASE
            WHEN r.qtype = 'a' THEN (SELECT question FROM mcqdb WHERE id = r.qid)
            WHEN r.qtype = 'b' THEN (SELECT question FROM numericaldb WHERE id = r.qid)
            WHEN r.qtype = 'c' THEN (SELECT question FROM dropdown WHERE id = r.qid)
            WHEN r.qtype = 'd' THEN (SELECT question FROM fillintheblanks WHERE id = r.qid)
            WHEN r.qtype = 'e' THEN (SELECT question FROM shortanswer WHERE id = r.qid)
            WHEN r.qtype = 'f' THEN (SELECT question FROM essay WHERE id = r.qid)
        END as question_text,
        CASE
            WHEN r.qtype = 'a' THEN (SELECT optiona FROM mcqdb WHERE id = r.qid)
            ELSE NULL
        END as optiona,
        CASE
            WHEN r.qtype = 'a' THEN (SELECT optionb FROM mcqdb WHERE id = r.qid)
            ELSE NULL
        END as optionb,
        CASE
            WHEN r.qtype = 'a' THEN (SELECT optionc FROM mcqdb WHERE id = r.qid)
            ELSE NULL
        END as optionc,
        CASE
            WHEN r.qtype = 'a' THEN (SELECT optiond FROM mcqdb WHERE id = r.qid)
            ELSE NULL
        END as optiond,
        CASE
            WHEN r.qtype = 'c' THEN (SELECT options FROM dropdown WHERE id = r.qid)
            ELSE NULL
        END as dropdown_options,
        CASE
            WHEN r.qtype = 'a' THEN (SELECT answer FROM mcqdb WHERE id = r.qid)
            WHEN r.qtype = 'b' THEN (SELECT answer FROM numericaldb WHERE id = r.qid)
            WHEN r.qtype = 'c' THEN (SELECT answer FROM dropdown WHERE id = r.qid)
            WHEN r.qtype = 'd' THEN (SELECT answer FROM fillintheblanks WHERE id = r.qid)
            WHEN r.qtype = 'e' THEN (SELECT answer FROM shortanswer WHERE id = r.qid)
            WHEN r.qtype = 'f' THEN (SELECT answer FROM essay WHERE id = r.qid)
        END as correct_answer,
        CASE
            WHEN r.qtype = 'a' THEN 'MCQ'
            WHEN r.qtype = 'b' THEN 'Numerical'
            WHEN r.qtype = 'c' THEN 'Dropdown'
            WHEN r.qtype = 'd' THEN 'Fill in the Blanks'
            WHEN r.qtype = 'e' THEN 'Short Answer'
            WHEN r.qtype = 'f' THEN 'Essay'
        END as question_type,
        CASE
            WHEN r.qtype = 'a' THEN qc.mcqmarks
            WHEN r.qtype = 'b' THEN qc.numericalmarks
            WHEN r.qtype = 'c' THEN qc.dropdownmarks
            WHEN r.qtype = 'd' THEN qc.fillmarks
            WHEN r.qtype = 'e' THEN qc.shortmarks
            WHEN r.qtype = 'f' THEN qc.essaymarks
        END as marks_per_question,
        CASE
            WHEN r.qtype IN ('a', 'b', 'c', 'd') AND UPPER(TRIM(r.response)) = UPPER(TRIM(
                CASE
                    WHEN r.qtype = 'a' THEN (SELECT answer FROM mcqdb WHERE id = r.qid)
                    WHEN r.qtype = 'b' THEN (SELECT answer FROM numericaldb WHERE id = r.qid)
                    WHEN r.qtype = 'c' THEN (SELECT answer FROM dropdown WHERE id = r.qid)
                    WHEN r.qtype = 'd' THEN (SELECT answer FROM fillintheblanks WHERE id = r.qid)
                END
            )) THEN 'correct'
            WHEN r.qtype IN ('a', 'b', 'c', 'd') THEN 'incorrect'
            ELSE 'manual_grading'
        END as status
    FROM 
        response r
    JOIN
        quizconfig qc ON r.quizid = qc.quizid
    WHERE 
        r.quizid = ? AND r.rollnumber = ? AND r.attempt = ?
    ORDER BY 
        r.serialnumber ASC";

$stmt = $conn->prepare($responses_sql);
$stmt->bind_param("iii", $quiz['quizid'], $student_rollnumber, $student_attempt);
$stmt->execute();
$responses = $stmt->get_result();
$stmt->close();

// Get student's result
$result_sql = "SELECT * FROM result WHERE quizid = ? AND rollnumber = ? AND attempt = ?";
$stmt = $conn->prepare($result_sql);
$stmt->bind_param("iii", $quiz['quizid'], $student_rollnumber, $student_attempt);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Student Attempt Details</title>
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
    <style>
        .main-raised { margin-top: 0; box-shadow: none; }
        .section { padding: 20px 0; }
        .title { margin-bottom: 20px; }
        .question-card { margin-bottom: 20px; border-radius: 8px; }
        .question-card .card-header { background: #1e1e2f; color: #fff; padding: 15px 20px; border-radius: 8px 8px 0 0; display:flex; justify-content:space-between; align-items:center; }
        .question-text { font-size: 1.1rem; font-weight: 500; margin-bottom: 20px; }
        .option-label { display:block; padding:10px 15px; border-radius:4px; margin-bottom:10px; background-color:#2a2a3c; color:#fff; }
        .selected-option { border-left:3px solid #90caf9; }
        .correct-option { background-color:#2e7d32; border-left:3px solid #43a047; color:#fff; }
        .incorrect-option { background-color:#c62828; border-left:3px solid #e53935; color:#fff; }
        .answer-info { margin-top:20px; padding:15px; border-radius:4px; background-color:#2a2a3c; color:#fff; }
        .student-info-card { margin-bottom:30px; }
        .summary-box { padding:15px; border-radius:4px; margin-bottom:15px; font-weight:500; background-color:#2a2a3c; color:#fff; }
        .summary-box.correct { background-color:#2e7d32; }
        .summary-box.incorrect { background-color:#c62828; }
        .summary-box.manual { background-color:#1565c0; }
        .back-button { margin-bottom:20px; }
        @media print { .no-print { display:none !important; } }
    </style>
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
                <div class="section text-center">
                    <h2 class="title">Student Quiz Attempt Details</h2>
                </div>
                <div class="section">
                    <div class="back-button d-flex justify-content-between no-print">
                        <a href="view_quiz_results.php?quiz_number=<?php echo $quiz_id; ?>" class="btn btn-sm btn-primary">
                            <i class="material-icons">arrow_back</i> Back to Results
                        </a>
                        <button onclick="window.print();" class="btn btn-sm btn-info">
                            <i class="material-icons">print</i> Print
                        </button>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card student-info-card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title mb-0">Student Information</h4>
                                </div>
                                <div class="card-body">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($student['name']); ?></p>
                                    <p><strong>Roll Number:</strong> <?php echo htmlspecialchars($student_rollnumber); ?></p>
                                    <p><strong>Class:</strong> <?php echo htmlspecialchars($student['department']); ?></p>
                                    <p><strong>Section:</strong> <?php echo htmlspecialchars($student['section']); ?></p>
                                    <p><strong>Attempt:</strong> <?php echo htmlspecialchars($student_attempt); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card student-info-card">
                                <div class="card-header card-header-primary">
                                    <h4 class="card-title mb-0">Quiz Information</h4>
                                </div>
                                <div class="card-body">
                                    <p><strong>Quiz Name:</strong> <?php echo htmlspecialchars($quiz['quizname']); ?></p>
                                    <p><strong>Quiz Number:</strong> <?php echo htmlspecialchars($quiz['quiznumber']); ?></p>
                                    <p><strong>Class:</strong> <?php echo htmlspecialchars($quiz['class_name'] ?? 'N/A'); ?></p>
                                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($quiz['subject_name'] ?? 'N/A'); ?></p>
                                    <p><strong>Max Marks:</strong> <?php echo htmlspecialchars($quiz['maxmarks']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($result): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card student-info-card">
                                <div class="card-header card-header-info">
                                    <h4 class="card-title mb-0">Score Summary</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <p><strong>MCQ Marks:</strong> <?php echo $result['mcqmarks']; ?></p>
                                            <p><strong>Numerical Marks:</strong> <?php echo $result['numericalmarks']; ?></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p><strong>Dropdown Marks:</strong> <?php echo $result['dropdownmarks']; ?></p>
                                            <p><strong>Fill-in-the-Blanks Marks:</strong> <?php echo $result['fillmarks']; ?></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p><strong>Short Answer Marks:</strong> <?php echo $result['shortmarks']; ?></p>
                                            <p><strong>Essay Marks:</strong> <?php echo $result['essaymarks']; ?></p>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h4>
                                                Total Score: 
                                                <?php 
                                                $total_marks = $result['mcqmarks'] + $result['numericalmarks'] + 
                                                              $result['dropdownmarks'] + $result['fillmarks'] + 
                                                              $result['shortmarks'] + $result['essaymarks'];
                                                $percentage = ($total_marks / $quiz['maxmarks']) * 100;
                                                echo $total_marks . "/" . $quiz['maxmarks'] . " (" . round($percentage, 1) . "%)"; 
                                                ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <h3 class="text-center mb-4">Questions & Answers</h3>
                            
                            <?php 
                            $correct_count = 0;
                            $incorrect_count = 0;
                            $manual_count = 0;
                            
                            if ($responses->num_rows > 0): 
                                while ($response = $responses->fetch_assoc()):
                                    if ($response['status'] == 'correct') $correct_count++;
                                    elseif ($response['status'] == 'incorrect') $incorrect_count++;
                                    else $manual_count++;
                                    
                                    $card_header_class = '';
                                    if ($response['status'] == 'correct') {
                                        $card_header_class = 'card-header-success';
                                    } elseif ($response['status'] == 'incorrect') {
                                        $card_header_class = 'card-header-danger';
                                    } else {
                                        $card_header_class = 'card-header-warning';
                                    }
                            ?>
                            <div class="question-card">
                                <div class="card-header <?php echo $card_header_class; ?>">
                                    <h4 class="mb-0">Question <?php echo $response['serialnumber']; ?> (<?php echo $response['question_type']; ?>)</h4>
                                    <span>
                                        <?php if ($response['status'] == 'correct'): ?>
                                            <i class="material-icons">check_circle</i> Correct
                                        <?php elseif ($response['status'] == 'incorrect'): ?>
                                            <i class="material-icons">cancel</i> Incorrect
                                        <?php else: ?>
                                            <i class="material-icons">help</i> Manual Grading
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="question-text">
                                        <?php echo nl2br(htmlspecialchars($response['question_text'])); ?>
                                    </div>
                                    
                                    <?php if ($response['qtype'] == 'a'): // MCQ ?>
                                        <div class="options-container">
                                            <?php 
                                            $options = ['A' => $response['optiona'], 'B' => $response['optionb'], 
                                                       'C' => $response['optionc'], 'D' => $response['optiond']];
                                            
                                            foreach ($options as $key => $option):
                                                $option_class = '';
                                                if ($response['response'] == $key && $key == $response['correct_answer']) {
                                                    $option_class = 'correct-option';
                                                } elseif ($response['response'] == $key && $key != $response['correct_answer']) {
                                                    $option_class = 'incorrect-option';
                                                } elseif ($key == $response['correct_answer']) {
                                                    $option_class = 'correct-option';
                                                }
                                            ?>
                                            <div class="option-label <?php echo $option_class; ?>">
                                                <strong><?php echo $key; ?>:</strong> <?php echo htmlspecialchars($option); ?>
                                                <?php if ($response['response'] == $key): ?>
                                                    <span class="float-right"><i class="material-icons">check</i> Selected</span>
                                                <?php endif; ?>
                                                <?php if ($key == $response['correct_answer']): ?>
                                                    <span class="float-right mr-3"><i class="material-icons">star</i> Correct</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif ($response['qtype'] == 'c'): // Dropdown ?>
                                        <div class="options-container">
                                            <p><strong>Available Options:</strong> <?php echo htmlspecialchars($response['dropdown_options']); ?></p>
                                            <div class="answer-info">
                                                <p><strong>Student Answer:</strong> <?php echo htmlspecialchars($response['response']); ?></p>
                                                <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars($response['correct_answer']); ?></p>
                                            </div>
                                        </div>
                                    <?php else: // Other question types ?>
                                        <div class="answer-info">
                                            <p><strong>Student Answer:</strong> <?php echo nl2br(htmlspecialchars($response['response'])); ?></p>
                                            <?php if (in_array($response['qtype'], ['a', 'b', 'c', 'd'])): ?>
                                                <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars($response['correct_answer']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3 text-right">
                                        <span class="badge badge-primary">Marks: <?php echo $response['marks_per_question']; ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                            
                            <div class="row mt-4">
                                <div class="col-md-4">
                                    <div class="summary-box correct">
                                        <i class="material-icons">check_circle</i> Correct Answers: <?php echo $correct_count; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="summary-box incorrect">
                                        <i class="material-icons">cancel</i> Incorrect Answers: <?php echo $incorrect_count; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="summary-box manual">
                                        <i class="material-icons">help</i> Manual Grading: <?php echo $manual_count; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php else: ?>
                            <div class="alert alert-info">
                                No response data found for this attempt.
                            </div>
                            <?php endif; ?>
                        </div>
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
<script src="./assets/js/sidebar.js"></script>
</body>
</html>
