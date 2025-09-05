<?php
// Direct export file that doesn't use the QuizExporter class
// Uses inline HTML generation to avoid any caching issues

session_start();
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

include "database.php"; // Database connection

// Check if quiz_id is provided
if (!isset($_GET['quiz_id'])) {
    header("Location: manage_quizzes.php");
    exit;
}

$quiz_id = intval($_GET['quiz_id']);
$instructor_email = $_SESSION['email']; // Get current instructor's email
$student_specific = isset($_GET['student_specific']) && $_GET['student_specific'] == '1';

// Get quiz data
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
    $_SESSION['export_error'] = "Quiz not found";
    header("Location: manage_quizzes.php");
    exit;
}

// Check if a specific student is targeted
$student_rollnumber = isset($_GET['student']) ? intval($_GET['student']) : null;
$student_name = "All Students";
$student_info = null;
$student_attempt = isset($_GET['attempt']) ? intval($_GET['attempt']) : null;

if ($student_rollnumber) {
    $sql_student = "SELECT name, department FROM studentinfo WHERE rollnumber = ?";
    $stmt_student = $conn->prepare($sql_student);
    $stmt_student->bind_param("i", $student_rollnumber);
    $stmt_student->execute();
    $student_result = $stmt_student->get_result();
    $student_info = $student_result->fetch_assoc();
    $stmt_student->close();
    
    if ($student_info) {
        $student_name = $student_info['name'];
    }
}

// Helper to run a query and merge the results
function fetch_question_set($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return $rows;
}

// Prepare questions array
$questions = [];

// Check if we should get student-specific questions
if ($student_specific && $student_rollnumber && $student_attempt) {
    // Get questions from student's attempt in response table
    $sql_student_questions = "
        SELECT 
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
            END as questiontext,
            CASE
                WHEN r.qtype = 'a' THEN CONCAT('[\"', (SELECT optiona FROM mcqdb WHERE id = r.qid), '\",\"', 
                                            (SELECT optionb FROM mcqdb WHERE id = r.qid), '\",\"', 
                                            (SELECT optionc FROM mcqdb WHERE id = r.qid), '\",\"', 
                                            (SELECT optiond FROM mcqdb WHERE id = r.qid), '\"]')
                WHEN r.qtype = 'c' THEN (SELECT options FROM dropdown WHERE id = r.qid)
                ELSE ''
            END as options,
            CASE
                WHEN r.qtype = 'a' THEN (SELECT answer FROM mcqdb WHERE id = r.qid)
                WHEN r.qtype = 'b' THEN (SELECT answer FROM numericaldb WHERE id = r.qid)
                WHEN r.qtype = 'c' THEN (SELECT answer FROM dropdown WHERE id = r.qid)
                WHEN r.qtype = 'd' THEN (SELECT answer FROM fillintheblanks WHERE id = r.qid)
                WHEN r.qtype = 'e' THEN (SELECT answer FROM shortanswer WHERE id = r.qid)
                WHEN r.qtype = 'f' THEN (SELECT answer FROM essay WHERE id = r.qid)
            END as answer,
            CASE
                WHEN r.qtype = 'a' THEN 'mcq'
                WHEN r.qtype = 'b' THEN 'numerical'
                WHEN r.qtype = 'c' THEN 'dropdown'
                WHEN r.qtype = 'd' THEN 'fill'
                WHEN r.qtype = 'e' THEN 'short'
                WHEN r.qtype = 'f' THEN 'essay'
            END as questiontype,
            CASE
                WHEN r.qtype = 'a' THEN ?
                WHEN r.qtype = 'b' THEN ?
                WHEN r.qtype = 'c' THEN ?
                WHEN r.qtype = 'd' THEN ?
                WHEN r.qtype = 'e' THEN ?
                WHEN r.qtype = 'f' THEN ?
            END as marks
        FROM 
            response r
        WHERE 
            r.quizid = ? AND r.rollnumber = ? AND r.attempt = ?
        ORDER BY 
            r.serialnumber ASC";
    
    $stmt = $conn->prepare($sql_student_questions);
    if (!$stmt) {
        $_SESSION['export_error'] = "Failed to prepare statement: " . $conn->error;
        header("Location: manage_quizzes.php");
        exit;
    }
    
    $stmt->bind_param("ddddddiii", 
        $quiz['mcqmarks'], 
        $quiz['numericalmarks'], 
        $quiz['dropdownmarks'], 
        $quiz['fillmarks'], 
        $quiz['shortmarks'], 
        $quiz['essaymarks'],
        $quiz['quizid'], 
        $student_rollnumber, 
        $student_attempt
    );
    
    $stmt->execute();
    $student_questions_result = $stmt->get_result();
    
    while ($row = $student_questions_result->fetch_assoc()) {
        $questions[] = $row;
    }
    $stmt->close();
}
else if ($quiz['is_random'] == 1) {
    // For a random quiz, use the preselected questions from random_quiz_questions table
    $sql_preselected = "
        SELECT
            r.qid,
            r.qtype,
            '' as response,
            CASE
                WHEN r.qtype = 'a' THEN (SELECT question FROM mcqdb WHERE id = r.qid)
                WHEN r.qtype = 'b' THEN (SELECT question FROM numericaldb WHERE id = r.qid)
                WHEN r.qtype = 'c' THEN (SELECT question FROM dropdown WHERE id = r.qid)
                WHEN r.qtype = 'd' THEN (SELECT question FROM fillintheblanks WHERE id = r.qid)
                WHEN r.qtype = 'e' THEN (SELECT question FROM shortanswer WHERE id = r.qid)
                WHEN r.qtype = 'f' THEN (SELECT question FROM essay WHERE id = r.qid)
            END as questiontext,
            CASE
                WHEN r.qtype = 'a' THEN CONCAT('[\"', (SELECT optiona FROM mcqdb WHERE id = r.qid), '\",\"',
                                            (SELECT optionb FROM mcqdb WHERE id = r.qid), '\",\"',
                                            (SELECT optionc FROM mcqdb WHERE id = r.qid), '\",\"',
                                            (SELECT optiond FROM mcqdb WHERE id = r.qid), '\"]')
                WHEN r.qtype = 'c' THEN (SELECT options FROM dropdown WHERE id = r.qid)
                ELSE ''
            END as options,
            CASE
                WHEN r.qtype = 'a' THEN (SELECT answer FROM mcqdb WHERE id = r.qid)
                WHEN r.qtype = 'b' THEN (SELECT answer FROM numericaldb WHERE id = r.qid)
                WHEN r.qtype = 'c' THEN (SELECT answer FROM dropdown WHERE id = r.qid)
                WHEN r.qtype = 'd' THEN (SELECT answer FROM fillintheblanks WHERE id = r.qid)
                WHEN r.qtype = 'e' THEN (SELECT answer FROM shortanswer WHERE id = r.qid)
                WHEN r.qtype = 'f' THEN (SELECT answer FROM essay WHERE id = r.qid)
            END as answer,
            CASE
                WHEN r.qtype = 'a' THEN 'mcq'
                WHEN r.qtype = 'b' THEN 'numerical'
                WHEN r.qtype = 'c' THEN 'dropdown'
                WHEN r.qtype = 'd' THEN 'fill'
                WHEN r.qtype = 'e' THEN 'short'
                WHEN r.qtype = 'f' THEN 'essay'
            END as questiontype,
            CASE
                WHEN r.qtype = 'a' THEN ?
                WHEN r.qtype = 'b' THEN ?
                WHEN r.qtype = 'c' THEN ?
                WHEN r.qtype = 'd' THEN ?
                WHEN r.qtype = 'e' THEN ?
                WHEN r.qtype = 'f' THEN ?
            END as marks
        FROM
            random_quiz_questions r
        WHERE
            r.quizid = ?
        ORDER BY
            r.serialnumber ASC";

    $stmt = $conn->prepare($sql_preselected);
    if (!$stmt) {
        $_SESSION['export_error'] = "Failed to prepare statement: " . $conn->error;
        header("Location: manage_quizzes.php");
        exit;
    }

    $stmt->bind_param("ddddddi",
        $quiz['mcqmarks'],
        $quiz['numericalmarks'],
        $quiz['dropdownmarks'],
        $quiz['fillmarks'],
        $quiz['shortmarks'],
        $quiz['essaymarks'],
        $quiz['quizid']
    );

    $stmt->execute();
    $preselected_questions_result = $stmt->get_result();

    while ($row = $preselected_questions_result->fetch_assoc()) {
        $questions[] = $row;
    }
    $stmt->close();
}
else {
    // Regular question fetching for general export
    $chapter_ids = [];
    if (!empty($quiz['chapter_ids'])) {
        $chapter_ids = array_map('intval', explode(',', $quiz['chapter_ids']));
    }

    // Helper variables for prepared statements
    $placeholders = '';
    $types = '';
    if (!empty($chapter_ids)) {
        $placeholders = str_repeat('?,', count($chapter_ids) - 1) . '?';
        $types = str_repeat('i', count($chapter_ids));
    }

    // MCQ questions
    if ($quiz['mcq'] > 0) {
        if (!empty($chapter_ids)) {
            $sql = "SELECT id, question as questiontext,
                        CONCAT('[\"', optiona, '\",\"', optionb, '\",\"', optionc, '\",\"', optiond, '\"]') as options,
                        answer, 'mcq' as questiontype, " . $quiz['mcqmarks'] . " as marks
                    FROM mcqdb
                    WHERE chapter_id IN ($placeholders)
                    LIMIT " . $quiz['mcq'];
            $questions = array_merge($questions, fetch_question_set($conn, $sql, $types, $chapter_ids));
        } else {
            $sql = "SELECT id, question as questiontext,
                        CONCAT('[\"', optiona, '\",\"', optionb, '\",\"', optionc, '\",\"', optiond, '\"]') as options,
                        answer, 'mcq' as questiontype, " . $quiz['mcqmarks'] . " as marks
                    FROM mcqdb
                    LIMIT " . $quiz['mcq'];
            $questions = array_merge($questions, fetch_question_set($conn, $sql));
        }
    }

    // Numerical questions
    if ($quiz['numerical'] > 0) {
        if (!empty($chapter_ids)) {
            $sql = "SELECT id, question as questiontext,
                        answer, 'numerical' as questiontype, " . $quiz['numericalmarks'] . " as marks,
                        '' as options
                    FROM numericaldb
                    WHERE chapter_id IN ($placeholders)
                    LIMIT " . $quiz['numerical'];
            $questions = array_merge($questions, fetch_question_set($conn, $sql, $types, $chapter_ids));
        } else {
            $sql = "SELECT id, question as questiontext,
                        answer, 'numerical' as questiontype, " . $quiz['numericalmarks'] . " as marks,
                        '' as options
                    FROM numericaldb
                    LIMIT " . $quiz['numerical'];
            $questions = array_merge($questions, fetch_question_set($conn, $sql));
        }
    }

    // Dropdown questions
    if ($quiz['dropdown'] > 0) {
        if (!empty($chapter_ids)) {
            $sql = "SELECT id, question as questiontext,
                        options, answer, 'dropdown' as questiontype, " . $quiz['dropdownmarks'] . " as marks
                    FROM dropdown
                    WHERE chapter_id IN ($placeholders)
                    LIMIT " . $quiz['dropdown'];
            $questions = array_merge($questions, fetch_question_set($conn, $sql, $types, $chapter_ids));
        } else {
            $sql = "SELECT id, question as questiontext,
                        options, answer, 'dropdown' as questiontype, " . $quiz['dropdownmarks'] . " as marks
                    FROM dropdown
                    LIMIT " . $quiz['dropdown'];
            $questions = array_merge($questions, fetch_question_set($conn, $sql));
        }
    }

    // Fill in the blanks questions
    if ($quiz['fill'] > 0) {
        if (!empty($chapter_ids)) {
            $sql = "SELECT id, question as questiontext,
                        answer, 'fill' as questiontype, " . $quiz['fillmarks'] . " as marks,
                        '' as options
                    FROM fillintheblanks
                    WHERE chapter_id IN ($placeholders)
                    LIMIT " . $quiz['fill'];
            $questions = array_merge($questions, fetch_question_set($conn, $sql, $types, $chapter_ids));
        } else {
            $sql = "SELECT id, question as questiontext,
                        answer, 'fill' as questiontype, " . $quiz['fillmarks'] . " as marks,
                        '' as options
                    FROM fillintheblanks
                    LIMIT " . $quiz['fill'];
            $questions = array_merge($questions, fetch_question_set($conn, $sql));
        }
    }

    // Short answer questions
    if ($quiz['short'] > 0) {
        if (!empty($chapter_ids)) {
            $sql = "SELECT id, question as questiontext,
                        answer, 'short' as questiontype, " . $quiz['shortmarks'] . " as marks,
                        '' as options
                    FROM shortanswer
                    WHERE chapter_id IN ($placeholders)
                    LIMIT " . $quiz['short'];
            $questions = array_merge($questions, fetch_question_set($conn, $sql, $types, $chapter_ids));
        } else {
            $sql = "SELECT id, question as questiontext,
                        answer, 'short' as questiontype, " . $quiz['shortmarks'] . " as marks,
                        '' as options
                    FROM shortanswer
                    LIMIT " . $quiz['short'];
            $questions = array_merge($questions, fetch_question_set($conn, $sql));
        }
    }

    // Essay questions
    if ($quiz['essay'] > 0) {
        if (!empty($chapter_ids)) {
            $sql = "SELECT id, question as questiontext,
                        answer, 'essay' as questiontype, " . $quiz['essaymarks'] . " as marks,
                        '' as options
                    FROM essay
                    WHERE chapter_id IN ($placeholders)
                    LIMIT " . $quiz['essay'];
            $questions = array_merge($questions, fetch_question_set($conn, $sql, $types, $chapter_ids));
        } else {
            $sql = "SELECT id, question as questiontext,
                        answer, 'essay' as questiontype, " . $quiz['essaymarks'] . " as marks,
                        '' as options
                    FROM essay
                    LIMIT " . $quiz['essay'];
            $questions = array_merge($questions, fetch_question_set($conn, $sql));
        }
    }
}

// Generate HTML directly without using QuizExporter class
$title = htmlspecialchars($quiz['quizname']) . ' - Quiz #' . htmlspecialchars($quiz['quiznumber']);
if ($student_specific && !empty($student_name)) {
    $title .= ' - ' . htmlspecialchars($student_name);
}
$totalMarks = $quiz['maxmarks'] ?? 0;
$duration = $quiz['duration'] ?? 0;

// Start building the HTML
$html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Narowal Public School and College - ' . $title . '</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 5px;
                    line-height: 1.2;
                    color: #333;
                    background-color: #fff;
                    font-size: 10pt;
                }
                .exam-header {
                    text-align: center;
                    margin-bottom: 5px;
                    border-bottom: 1px solid #333;
                    padding-bottom: 5px;
                }
                .exam-logo {
                    font-size: 16pt;
                    font-weight: bold;
                    margin-bottom: 2px;
                    letter-spacing: 0px;
                    color: #222;
                }
                .exam-title {
                    font-size: 14pt;
                    font-weight: bold;
                    margin-bottom: 2px;
                    text-transform: uppercase;
                }
                .exam-subtitle {
                    font-size: 11pt;
                    margin-bottom: 2px;
                }
                .exam-info-box {
                    display: flex;
                    justify-content: space-between;
                    margin: 5px 0;
                    border: 1px solid #ddd;
                    padding: 3px;
                    background-color: #f9f9f9;
                    font-size: 9pt;
                }
                .exam-info-section {
                    flex: 1;
                    padding: 0 3px;
                }
                .exam-info-title {
                    font-weight: bold;
                    margin-bottom: 2px;
                }
                .instructions {
                    margin: 5px 0;
                    padding: 4px;
                    border: 1px solid #ddd;
                    background-color: #f5f5f5;
                    page-break-inside: avoid;
                    font-size: 9pt;
                }
                .instructions-title {
                    font-weight: bold;
                    font-size: 10pt;
                    margin-bottom: 2px;
                    text-transform: uppercase;
                }
                .instructions ol {
                    margin: 2px 0 2px 15px;
                    padding: 0;
                }
                .instructions li {
                    margin-bottom: 1px;
                }
                .questions-section {
                    margin-top: 5px;
                }
                .question { 
                    margin-bottom: 6px; 
                    border: 1px solid #eee; 
                    padding: 5px;
                    page-break-inside: avoid;
                    background-color: #fff;
                    box-shadow: none;
                }
                .question-number { 
                    font-weight: bold;
                    margin-bottom: 3px;
                    display: flex;
                    justify-content: space-between;
                    font-size: 10pt;
                }
                .question-marks {
                    color: #777;
                    font-size: 9pt;
                }
                .question-text { 
                    margin-bottom: 5px; 
                    font-size: 10.5pt;
                }
                .options { 
                    margin-left: 10px; 
                }
                .option-item {
                    margin-bottom: 1px;
                    display: flex;
                    font-size: 10pt;
                }
                .option-letter {
                    min-width: 15px;
                    font-weight: bold;
                }
                .footer { 
                    text-align: center; 
                    font-size: 8pt;
                    margin-top: 8px;
                    border-top: 1px solid #eee;
                    padding-top: 3px;
                    color: #999;
                }
                .answer-space {
                    height: 15px;
                    border-bottom: 1px dashed #eee;
                    margin-top: 3px;
                }
                @media print {
                    body { 
                        margin: 0;
                        padding: 5px;
                        font-size: 10pt;
                    }
                    .no-print {
                        display: none;
                    }
                    .question {
                        break-inside: avoid;
                        box-shadow: none;
                        border: 1px solid #eee;
                        margin-bottom: 5px;
                        padding: 4px;
                    }
                    .exam-info-box, .instructions {
                        margin: 4px 0;
                        padding: 3px;
                    }
                }
            </style>
        </head>
        <body>';

// Add print button (only shows on screen, not when printing)
$html .= '<div class="no-print" style="text-align: right; margin-bottom: 10px;">
            <button onclick="window.print();" style="padding: 6px 12px; background-color: #4CAF50; color: white; border: none; cursor: pointer; font-size: 14px;">
                Print / Save as PDF
            </button>
          </div>';

// Exam header section
$html .= '<div class="exam-header">
            <div class="exam-logo">Narowal Public School and College</div>
            <div class="exam-title">' . htmlspecialchars($quiz['quizname']) . '</div>
            <div class="exam-subtitle">Class: ' . htmlspecialchars($quiz['class_name'] ?? 'N/A') . ' | Subject: ' . htmlspecialchars($quiz['subject_name'] ?? 'N/A') . '</div>
          </div>';

// Quiz information in single row
$html .= '<div class="exam-info-box">
            <div class="exam-info-section">
                <div class="exam-info-title">Quiz Info</div>
                <div>Quiz: #' . htmlspecialchars($quiz['quiznumber']) . '</div>
                <div>Time: ' . htmlspecialchars($duration) . ' min</div>
                <div>Marks: ' . htmlspecialchars($totalMarks) . '</div>
            </div>
            <div class="exam-info-section">
                <div class="exam-info-title">Class Info</div>
                <div>Class: ' . htmlspecialchars($quiz['class_name'] ?? 'N/A') . '</div>
                <div>Subject: ' . htmlspecialchars($quiz['subject_name'] ?? 'N/A') . '</div>
                <div>Section: ' . htmlspecialchars($quiz['section'] ?? 'N/A') . '</div>
            </div>
            <div class="exam-info-section">
                <div class="exam-info-title">Student Info</div>
                <div>Name:_________________ Roll#:_______</div>
            </div>
          </div>';

// Instructions section
$html .= '<div class="instructions">
            <div class="instructions-title">Instructions:</div>
            <ol>
                <li>Total Questions: ' . array_sum([$quiz['mcq'], $quiz['numerical'], $quiz['dropdown'], $quiz['fill'], $quiz['short'], $quiz['essay']]) . ' | Marks: ' . $totalMarks . ' | Time: ' . $duration . ' min</li>
                <li>Read each question carefully. Circle the correct answer for MCQs.</li>
            </ol>
          </div>';

// Questions section
$html .= '<div class="questions-section">';

if (!empty($questions)) {
    $questionNumber = 1;
    
    foreach ($questions as $question) {
        $html .= '<div class="question">
                    <div class="question-number">
                        <div>Q' . $questionNumber . ' <span style="font-weight: normal; font-style: italic; color: #666;">(' . htmlspecialchars($question['questiontype']) . ')</span></div>
                        <div class="question-marks">' . htmlspecialchars($question['marks']) . ' marks</div>
                    </div>
                    <div class="question-text">' . htmlspecialchars($question['questiontext']) . '</div>';
        
        // Different question types
        if ($question['questiontype'] == 'mcq') {
            $html .= '<div class="options">';
            $options = json_decode($question['options'], true);
            if (is_array($options)) {
                $optionLetters = ['a', 'b', 'c', 'd'];
                foreach ($options as $index => $option) {
                    if (isset($optionLetters[$index])) {
                        $html .= '<div class="option-item">
                                    <div class="option-letter">' . $optionLetters[$index] . ')</div>
                                    <div>' . htmlspecialchars($option) . '</div>
                                  </div>';
                    }
                }
            }
            $html .= '</div>';
        } else if ($question['questiontype'] == 'dropdown') {
            $html .= '<div class="options">';
            $options = json_decode($question['options'], true);
            if (is_array($options)) {
                $html .= 'Options: ';
                $html .= implode(', ', array_map('htmlspecialchars', $options));
            }
            $html .= '</div>';
        } else {
            // For other question types, add space for answer
            $html .= '<div class="answer-space"></div>';
        }
        
        $html .= '</div>';
        $questionNumber++;
    }
} else {
    $html .= '<p>No questions available for this quiz.</p>';
}

$html .= '</div>'; // End of questions section

// Footer
$html .= '<div class="footer">
            <div>End of Question Paper</div>
            <div>Generated by studyht.com Quiz System on ' . date('Y-m-d H:i:s') . '</div>
            <div>Designed & Maintained By Sir Hassan Tariq</div>
          </div>';

$html .= '</body></html>';

// Output the generated HTML
echo $html;
?> 