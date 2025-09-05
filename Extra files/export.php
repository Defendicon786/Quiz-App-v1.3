<?php
session_start();
// Ensure instructor is logged in
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

// Define constant to prevent direct access to QuizExporter.php
define('QUIZ_PORTAL', true);

include "database.php"; // Database connection
include "export/QuizExporter.php"; // Quiz exporter class

// Check if quiz_id and export_type are provided
if (!isset($_GET['quiz_id']) || !isset($_GET['export_type'])) {
    header("Location: manage_quizzes.php");
    exit;
}

$quiz_id = intval($_GET['quiz_id']);
$export_type = $_GET['export_type'];
$student_specific = isset($_GET['student_specific']) && $_GET['student_specific'] == '1';
$instructor_email = $_SESSION['email']; // Get current instructor's email

// Get quiz data - Allow any instructor to access any quiz
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

// Get quiz questions
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
} else if ($quiz['is_random'] == 1) {
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
} else {
    // Use regular question fetching logic for general quiz export
    // Fetch MCQ questions if the quiz has MCQs
    if ($quiz['mcq'] > 0) {
        // Get chapter IDs for this quiz
        $chapter_ids = [];
        if (!empty($quiz['chapter_ids'])) {
            $chapter_ids = explode(',', $quiz['chapter_ids']);
        }
        
        if (!empty($chapter_ids)) {
            // For a quiz with specific chapters
            $placeholders = str_repeat('?,', count($chapter_ids) - 1) . '?';
            $mcq_sql = "SELECT id, question as questiontext, 
                        CONCAT('[\"', optiona, '\",\"', optionb, '\",\"', optionc, '\",\"', optiond, '\"]') as options, 
                        answer, 'mcq' as questiontype, " . $quiz['mcqmarks'] . " as marks
                        FROM mcqdb 
                        WHERE chapter_id IN ($placeholders)
                        LIMIT " . $quiz['mcq'];
            
            $stmt = $conn->prepare($mcq_sql);
            $types = str_repeat('i', count($chapter_ids));
            $stmt->bind_param($types, ...$chapter_ids);
            } else if (!$questions && $quiz['is_random'] == 1) {
        // If we didn't already get preselected questions, for a random quiz from all chapters
        $mcq_sql = "SELECT id, question as questiontext, 
                    CONCAT('[\"', optiona, '\",\"', optionb, '\",\"', optionc, '\",\"', optiond, '\"]') as options, 
                    answer, 'mcq' as questiontype, " . $quiz['mcqmarks'] . " as marks
                    FROM mcqdb 
                    ORDER BY RAND()
                    LIMIT " . $quiz['mcq'];
        
        $stmt = $conn->prepare($mcq_sql);
        } else {
            // For a quiz without specific chapters (likely all questions manually added)
            $mcq_sql = "SELECT id, question as questiontext, 
                        CONCAT('[\"', optiona, '\",\"', optionb, '\",\"', optionc, '\",\"', optiond, '\"]') as options, 
                        answer, 'mcq' as questiontype, " . $quiz['mcqmarks'] . " as marks
                        FROM mcqdb 
                        LIMIT " . $quiz['mcq'];
            
            $stmt = $conn->prepare($mcq_sql);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
        $stmt->close();
    }
    
    // Fetch Numerical questions if the quiz has them
    if ($quiz['numerical'] > 0) {
        $chapter_ids = [];
        if (!empty($quiz['chapter_ids'])) {
            $chapter_ids = explode(',', $quiz['chapter_ids']);
        }
        
        if (!empty($chapter_ids)) {
            $placeholders = str_repeat('?,', count($chapter_ids) - 1) . '?';
            $num_sql = "SELECT id, question as questiontext, 
                        answer, 'numerical' as questiontype, " . $quiz['numericalmarks'] . " as marks,
                        '' as options
                        FROM numericaldb 
                        WHERE chapter_id IN ($placeholders)
                        LIMIT " . $quiz['numerical'];
            
            $stmt = $conn->prepare($num_sql);
            $types = str_repeat('i', count($chapter_ids));
            $stmt->bind_param($types, ...$chapter_ids);
            } else if (!$questions && $quiz['is_random'] == 1) {
        $num_sql = "SELECT id, question as questiontext, 
                    answer, 'numerical' as questiontype, " . $quiz['numericalmarks'] . " as marks,
                    '' as options 
                    FROM numericaldb 
                    ORDER BY RAND()
                    LIMIT " . $quiz['numerical'];
        
        $stmt = $conn->prepare($num_sql);
        } else {
            $num_sql = "SELECT id, question as questiontext, 
                        answer, 'numerical' as questiontype, " . $quiz['numericalmarks'] . " as marks,
                        '' as options
                        FROM numericaldb 
                        LIMIT " . $quiz['numerical'];
            
            $stmt = $conn->prepare($num_sql);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
        $stmt->close();
    }
    
    // Fetch dropdown questions if the quiz has them
    if ($quiz['dropdown'] > 0) {
        $chapter_ids = [];
        if (!empty($quiz['chapter_ids'])) {
            $chapter_ids = explode(',', $quiz['chapter_ids']);
        }
        
        if (!empty($chapter_ids)) {
            $placeholders = str_repeat('?,', count($chapter_ids) - 1) . '?';
            $dropdown_sql = "SELECT id, question as questiontext, 
                             options, answer, 'dropdown' as questiontype, " . $quiz['dropdownmarks'] . " as marks
                             FROM dropdown 
                             WHERE chapter_id IN ($placeholders)
                             LIMIT " . $quiz['dropdown'];
            
            $stmt = $conn->prepare($dropdown_sql);
            $types = str_repeat('i', count($chapter_ids));
            $stmt->bind_param($types, ...$chapter_ids);
            } else if (!$questions && $quiz['is_random'] == 1) {
        $dropdown_sql = "SELECT id, question as questiontext, 
                         options, answer, 'dropdown' as questiontype, " . $quiz['dropdownmarks'] . " as marks
                         FROM dropdown 
                         ORDER BY RAND()
                         LIMIT " . $quiz['dropdown'];
        
        $stmt = $conn->prepare($dropdown_sql);
        } else {
            $dropdown_sql = "SELECT id, question as questiontext, 
                             options, answer, 'dropdown' as questiontype, " . $quiz['dropdownmarks'] . " as marks
                             FROM dropdown 
                             LIMIT " . $quiz['dropdown'];
            
            $stmt = $conn->prepare($dropdown_sql);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
        $stmt->close();
    }
    
    // Similarly fetch fill-in-the-blanks questions if the quiz has them
    if ($quiz['fill'] > 0) {
        $chapter_ids = [];
        if (!empty($quiz['chapter_ids'])) {
            $chapter_ids = explode(',', $quiz['chapter_ids']);
        }
        
        if (!empty($chapter_ids)) {
            $placeholders = str_repeat('?,', count($chapter_ids) - 1) . '?';
            $fill_sql = "SELECT id, question as questiontext, 
                         answer, 'fill' as questiontype, " . $quiz['fillmarks'] . " as marks,
                         '' as options
                         FROM fillintheblanks 
                         WHERE chapter_id IN ($placeholders)
                         LIMIT " . $quiz['fill'];
            
            $stmt = $conn->prepare($fill_sql);
            $types = str_repeat('i', count($chapter_ids));
            $stmt->bind_param($types, ...$chapter_ids);
            } else if (!$questions && $quiz['is_random'] == 1) {
        $fill_sql = "SELECT id, question as questiontext, 
                     answer, 'fill' as questiontype, " . $quiz['fillmarks'] . " as marks,
                     '' as options
                     FROM fillintheblanks 
                     ORDER BY RAND()
                     LIMIT " . $quiz['fill'];
        
        $stmt = $conn->prepare($fill_sql);
        } else {
            $fill_sql = "SELECT id, question as questiontext, 
                         answer, 'fill' as questiontype, " . $quiz['fillmarks'] . " as marks,
                         '' as options
                         FROM fillintheblanks 
                         LIMIT " . $quiz['fill'];
            
            $stmt = $conn->prepare($fill_sql);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
        $stmt->close();
    }
    
    // Fetch short answer questions if the quiz has them
    if ($quiz['short'] > 0) {
        $chapter_ids = [];
        if (!empty($quiz['chapter_ids'])) {
            $chapter_ids = explode(',', $quiz['chapter_ids']);
        }
        
        if (!empty($chapter_ids)) {
            $placeholders = str_repeat('?,', count($chapter_ids) - 1) . '?';
            $short_sql = "SELECT id, question as questiontext, 
                          answer, 'short' as questiontype, " . $quiz['shortmarks'] . " as marks,
                          '' as options
                          FROM shortanswer 
                          WHERE chapter_id IN ($placeholders)
                          LIMIT " . $quiz['short'];
            
            $stmt = $conn->prepare($short_sql);
            $types = str_repeat('i', count($chapter_ids));
            $stmt->bind_param($types, ...$chapter_ids);
            } else if (!$questions && $quiz['is_random'] == 1) {
        $short_sql = "SELECT id, question as questiontext, 
                      answer, 'short' as questiontype, " . $quiz['shortmarks'] . " as marks,
                      '' as options
                      FROM shortanswer 
                      ORDER BY RAND()
                      LIMIT " . $quiz['short'];
        
        $stmt = $conn->prepare($short_sql);
        } else {
            $short_sql = "SELECT id, question as questiontext, 
                          answer, 'short' as questiontype, " . $quiz['shortmarks'] . " as marks,
                          '' as options
                          FROM shortanswer 
                          LIMIT " . $quiz['short'];
            
            $stmt = $conn->prepare($short_sql);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
        $stmt->close();
    }
    
    // Fetch essay questions if the quiz has them
    if ($quiz['essay'] > 0) {
        $chapter_ids = [];
        if (!empty($quiz['chapter_ids'])) {
            $chapter_ids = explode(',', $quiz['chapter_ids']);
        }
        
        if (!empty($chapter_ids)) {
            $placeholders = str_repeat('?,', count($chapter_ids) - 1) . '?';
            $essay_sql = "SELECT id, question as questiontext, 
                          answer, 'essay' as questiontype, " . $quiz['essaymarks'] . " as marks,
                          '' as options
                          FROM essay 
                          WHERE chapter_id IN ($placeholders)
                          LIMIT " . $quiz['essay'];
            
            $stmt = $conn->prepare($essay_sql);
            $types = str_repeat('i', count($chapter_ids));
            $stmt->bind_param($types, ...$chapter_ids);
            } else if (!$questions && $quiz['is_random'] == 1) {
        $essay_sql = "SELECT id, question as questiontext, 
                      answer, 'essay' as questiontype, " . $quiz['essaymarks'] . " as marks,
                      '' as options
                      FROM essay 
                      ORDER BY RAND()
                      LIMIT " . $quiz['essay'];
        
        $stmt = $conn->prepare($essay_sql);
        } else {
            $essay_sql = "SELECT id, question as questiontext, 
                          answer, 'essay' as questiontype, " . $quiz['essaymarks'] . " as marks,
                          '' as options
                          FROM essay 
                          LIMIT " . $quiz['essay'];
            
            $stmt = $conn->prepare($essay_sql);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
        $stmt->close();
    }
}

// Export quiz based on export type
try {
    $html = '';
    switch ($export_type) {
        case 'pdf':
            $html = QuizExporter::exportToPDF($quiz, $questions);
            $filename = 'quiz_' . $quiz['quiznumber'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $quiz['quizname']) . '.html';
            break;
        case 'word':
            $html = QuizExporter::exportToWord($quiz, $questions);
            $filename = 'quiz_' . $quiz['quiznumber'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $quiz['quizname']) . '.html';
            break;
        default:
            $_SESSION['export_error'] = "Invalid export type";
            header("Location: manage_quizzes.php");
            exit;
    }

    if (empty($html)) {
        $_SESSION['export_error'] = "Failed to generate export content";
        header("Location: manage_quizzes.php");
        exit;
    }

    // For Word export, send as file download
    if ($export_type == 'word') {
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $html;
    } else {
        // For PDF, just display the HTML which has a print button
        echo $html;
    }
    exit;
    
} catch (Exception $e) {
    $_SESSION['export_error'] = "Export error: " . $e->getMessage();
    header("Location: manage_quizzes.php");
    exit;
} 