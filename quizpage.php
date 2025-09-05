<?php
// Enable error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off display errors in production

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_message = date('Y-m-d H:i:s') . " Error [$errno]: $errstr in $errfile on line $errline";
    error_log($error_message, 3, "quiz_errors.log");
    return false;
}
set_error_handler("customErrorHandler");

// Start output buffering to prevent headers sent error
ob_start();

session_start();

// Debug logging function - keep this for backend logging but remove visual output
function logDebug($message, $data = null) {
    $log_message = date('Y-m-d H:i:s') . " DEBUG: $message";
    if ($data !== null) {
        $log_message .= " Data: " . print_r($data, true);
    }
    error_log($log_message, 3, "quiz_debug.log");
}

// Validate session
if(!isset($_SESSION["studentloggedin"]) || $_SESSION["studentloggedin"] !== true) {
    logDebug("Student not logged in - redirecting to login");
    header("location: studentlogin.php");
    exit;
}

// Student's section from session
$student_section = isset($_SESSION['section']) ? $_SESSION['section'] : null;
logDebug("Student section from session: " . $student_section);

// Include database connection
include "database.php";
if (!$conn) {
    logDebug("Database connection failed");
    die("Connection failed: " . mysqli_connect_error());
}

// Get student info
$rollnumber = $_SESSION["rollnumber"];
logDebug("Processing for rollnumber: " . $rollnumber);

// Check if quiz is already in progress
if (isset($_SESSION['quiz_started']) && $_SESSION['quiz_started'] === true) {
    logDebug("Quiz already in progress", $_SESSION);
    
    // Validate required session variables
    $required_session_vars = [
        'quiz_id', 'total_questions', 'numques', 'quiznumber',
        'start_time', 'end_time', 'current_attempt'
    ];
    
    $missing_vars = array_filter($required_session_vars, function($var) {
        return !isset($_SESSION[$var]);
    });
    
    if (!empty($missing_vars)) {
        logDebug("Missing required session variables, destroying session and redirecting to quizhome.", $missing_vars);
        $_SESSION['error'] = "Quiz session is incomplete. Please start a new quiz.";
        session_destroy();
        header("location: quizhome.php");
        exit;
    }
    
    // Continue with existing quiz
    if (!isset($_GET['n'])) {
        logDebug("Quiz in progress, but question number (n) not set in GET. Redirecting to n=1.", $_SESSION);
        header("Location: quizpage.php?n=1");
        exit;
    }
} else {
    echo "<pre style='background-color: #ddddff; border: 1px solid #0000cc; padding: 10px; margin: 10px;'><strong>DEBUG: Quiz not started in session. Attempting to initialize a new quiz.</strong><br>SESSION: " . htmlspecialchars(print_r($_SESSION, true)) . "</pre>";
    try {
        // Check for active quiz
        $sql = "SELECT qc.*, qc.topic_ids, c.class_name, s.subject_name,
                (SELECT COUNT(*) FROM quizrecord qr WHERE qr.quizid = qc.quizid AND qr.rollnumber = ?) as attempt_count,
                (SELECT COUNT(*) FROM response r WHERE r.quizid = qc.quizid AND r.rollnumber = ? AND r.attempt = (
                    SELECT COUNT(*) FROM quizrecord qr2 WHERE qr2.quizid = qc.quizid AND qr2.rollnumber = ?
                ) + 1) as existing_responses,
                (SELECT UPPER(TRIM(section)) FROM studentinfo WHERE rollnumber = ?) as student_section
                FROM quizconfig qc
                LEFT JOIN classes c ON qc.class_id = c.class_id
                LEFT JOIN subjects s ON qc.subject_id = s.subject_id
                WHERE qc.starttime <= NOW() 
                AND qc.endtime >= NOW()
                -- Student's class must match quiz class
                AND qc.class_id = (SELECT c.class_id FROM studentinfo s 
                                  JOIN classes c ON s.department = c.class_name 
                                  WHERE s.rollnumber = ?)
                -- Section matching: standard comparison
                AND (
                    qc.section IS NULL
                    OR 
                    UPPER(TRIM(qc.section)) = UPPER(TRIM((SELECT section FROM studentinfo WHERE rollnumber = ?)))
                )
                -- Check attempt count
                AND (
                    SELECT COUNT(*) 
                    FROM quizrecord qr 
                    WHERE qr.quizid = qc.quizid 
                    AND qr.rollnumber = ?
                ) < qc.attempts
                ORDER BY qc.starttime DESC 
                LIMIT 1";
        
        // Add more detailed logging
        $student_info_sql = "SELECT name, department, section FROM studentinfo WHERE rollnumber = ?";
        $stmt_student = $conn->prepare($student_info_sql);
        $stmt_student->bind_param("i", $rollnumber);
        $stmt_student->execute();
        $student_result = $stmt_student->get_result();
        $student_data = $student_result->fetch_assoc();
        
        // Log quizzes with their sections 
        $available_quizzes_sql = "SELECT quizid, quizname, section, class_id, 
                                  (SELECT class_name FROM classes WHERE class_id = qc.class_id) as class_name,
                                  (SELECT section FROM studentinfo WHERE rollnumber = ?) as student_section 
                                  FROM quizconfig qc 
                                  WHERE starttime <= NOW() AND endtime >= NOW()";
        $available_stmt = $conn->prepare($available_quizzes_sql);
        $available_stmt->bind_param("i", $rollnumber);
        $available_stmt->execute();
        $available_result = $available_stmt->get_result();
        $available_quizzes = [];
        while ($row = $available_result->fetch_assoc()) {
            // Add section comparison result
            $row['section_match'] = ($row['section'] === null || 
                                    strtolower(trim($row['section'])) === strtolower(trim($row['student_section'])));
            $available_quizzes[] = $row;
        }
        
        logDebug("Student info", $student_data);
        logDebug("Available quizzes with section match info", $available_quizzes);
        logDebug("Checking for active quiz with class and section filters. SQL: " . preg_replace('/\s+/', ' ', $sql), array('rollnumber' => $rollnumber, 'student_section' => $student_section));

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

        $stmt->bind_param("iiiiiii", $rollnumber, $rollnumber, $rollnumber, $rollnumber, $rollnumber, $rollnumber, $rollnumber);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }

        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $quiz = $result->fetch_assoc();
            logDebug("Found active quiz", $quiz);
            
            // Check if there are existing responses
            if ($quiz['existing_responses'] > 0) {
                logDebug("Existing responses found for this attempt. Preventing new quiz start.", array('quiz_id' => $quiz['quizid'], 'rollnumber' => $rollnumber, 'attempt' => ($quiz['attempt_count'] ?? 0) + 1));
                throw new Exception("You have already started this quiz attempt. Please contact administrator.");
            }
            
            // Validate quiz data
            if (!isset($quiz['quizid']) || !isset($quiz['total_questions']) || !isset($quiz['duration'])) {
                logDebug("Invalid quiz configuration from DB.", $quiz);
                throw new Exception("Invalid quiz configuration");
            }
            
            // Initialize quiz session
            $_SESSION['quiz_started'] = true;
            $_SESSION['quiz_id'] = $quiz['quizid'];
            
            // Add explicit debug to verify the quizid
            error_log("QUIZPAGE: Initializing quiz session with quizid=" . $quiz['quizid'] . ", quiznumber=" . $quiz['quiznumber']);
            
            // Check if total_questions is zero, which suggests manually selected questions
            // might not have been properly counted in the quizconfig table
            if ($quiz['total_questions'] == 0) {
                // Count the manually selected questions in the response table
                $count_sql = "SELECT COUNT(*) as question_count FROM response 
                             WHERE quizid = ? AND rollnumber IS NULL";
                $count_stmt = $conn->prepare($count_sql);
                if ($count_stmt) {
                    $count_stmt->bind_param("i", $quiz['quizid']);
                    $count_stmt->execute();
                    $count_result = $count_stmt->get_result();
                    if ($count_row = $count_result->fetch_assoc()) {
                        $manual_count = $count_row['question_count'];
                        if ($manual_count > 0) {
                            // Update session and quizconfig with the actual count
                            $quiz['total_questions'] = $manual_count;
                            logDebug("Found $manual_count manually selected questions, updating total_questions");
                            
                            // Update quizconfig table
                            $update_sql = "UPDATE quizconfig SET total_questions = ? WHERE quizid = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            if ($update_stmt) {
                                $update_stmt->bind_param("ii", $manual_count, $quiz['quizid']);
                                $update_stmt->execute();
                                $update_stmt->close();
                            }
                        }
                    }
                    $count_stmt->close();
                }
            }
            
            $_SESSION['total_questions'] = $quiz['total_questions'];
            $_SESSION['numques'] = $quiz['total_questions'];
            $_SESSION['quiznumber'] = $quiz['quiznumber'];
            $_SESSION['start_time'] = time();
            $_SESSION['end_time'] = time() + ($quiz['duration'] * 60);
            $_SESSION['current_attempt'] = ($quiz['attempt_count'] ?? 0) + 1;
            
            logDebug("New quiz session initialized.", $_SESSION);
            
            // Record quiz start
            $sql_record = "INSERT INTO quizrecord (quizid, rollnumber, attempt, starttime) VALUES (?, ?, ?, NOW())";
            logDebug("Recording quiz start. SQL: $sql_record", array('quizid' => $quiz['quizid'], 'rollnumber' => $rollnumber, 'attempt' => $_SESSION['current_attempt']));
            $stmt_record = $conn->prepare($sql_record);
            if (!$stmt_record) {
                throw new Exception("Failed to prepare record statement: " . $conn->error);
            }

            $stmt_record->bind_param("iii", $quiz['quizid'], $rollnumber, $_SESSION['current_attempt']);
            if (!$stmt_record->execute()) {
                throw new Exception("Failed to record quiz start: " . $stmt_record->error);
            }
            
            // Initialize questions array
            $questions = array();
            
            // Get chapter IDs array
            $chapter_ids = !empty($quiz['chapter_ids']) ? explode(',', $quiz['chapter_ids']) : array();
            if (empty($chapter_ids)) {
                throw new Exception("No chapters configured for this quiz");
            }
            
            $chapter_ids = array_map('trim', $chapter_ids);
            $chapter_ids = array_filter($chapter_ids, 'is_numeric'); // Only keep numeric IDs
            logDebug("Filtered chapter IDs", $chapter_ids);
            
            if (empty($chapter_ids)) {
                throw new Exception("Invalid chapter configuration after filtering");
            }
            
            $chapter_ids_str = implode(',', $chapter_ids);

            $topic_ids = !empty($quiz['topic_ids']) ? explode(',', $quiz['topic_ids']) : array();
            $topic_ids = array_filter($topic_ids, 'is_numeric');
            $topic_ids_str = implode(',', $topic_ids);
            
            logDebug("Quiz is_random flag", ['is_random' => $quiz['is_random']]);
            
            // Function to get questions of a specific type
            function getQuestions($conn, $type, $count, $chapter_ids_str, $topic_ids_str = '') {
                $questions = array();
                if ($count > 0) {
                    $table = '';
                    switch($type) {
                        case 'a': $table = 'mcqdb'; break;
                        case 'b': $table = 'numericaldb'; break;
                        case 'c': $table = 'dropdown'; break;
                        case 'd': $table = 'fillintheblanks'; break;
                        case 'e': $table = 'shortanswer'; break;
                        case 'f': $table = 'essay'; break;
                        default: return array();
                    }
                    
                    // Validate chapter_ids_str
                    if (empty($chapter_ids_str)) {
                        throw new Exception("No valid chapters found");
                    }
                    
                    // Use DISTINCT to avoid retrieving the same question multiple times
                    $sql = "SELECT DISTINCT id FROM $table WHERE chapter_id IN ($chapter_ids_str)";
                    if (!empty($topic_ids_str)) {
                        $sql .= " AND topic_id IN ($topic_ids_str)";
                    }
                    $sql .= " ORDER BY RAND() LIMIT ?";
                    logDebug("Fetching questions for type: $type. SQL: " . preg_replace('/\s+/', ' ', $sql), array('count' => $count, 'chapters' => $chapter_ids_str));
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Failed to prepare questions statement: " . $conn->error);
                    }
                    
                    $stmt->bind_param("i", $count);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to get questions: " . $stmt->error);
                    }
                    
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $questions[] = array('type' => $type, 'id' => $row['id']);
                    }
                    
                    if (count($questions) < $count) {
                        logDebug("Warning: Not enough questions found", array(
                            'type' => $type,
                            'requested' => $count,
                            'found' => count($questions),
                            'chapter_ids_str' => $chapter_ids_str
                        ));
                    }
                }
                return $questions;
            }
            
            // Get questions for each type
            try {
                $questions = array();
                
                // Check if the quiz has random questions preselected
                if ($quiz['is_random']) {
                    // For random quizzes, get the preselected random questions from random_quiz_questions table
                    $sql = "SELECT qtype, qid, serialnumber 
                           FROM random_quiz_questions 
                           WHERE quizid = ?
                           ORDER BY serialnumber ASC";
                    
                    logDebug("SQL query for preselected random questions: " . $sql, array('quizid' => $quiz['quizid']));
                    
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Failed to prepare preselected questions statement: " . $conn->error);
                    }
                    
                    $stmt->bind_param("i", $quiz['quizid']);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to get preselected questions: " . $stmt->error);
                    }
                    
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        logDebug("Using preselected random questions for this quiz: found " . $result->num_rows . " questions");
                        $preselected_questions = array();
                        while ($row = $result->fetch_assoc()) {
                            $questions[] = array('type' => $row['qtype'], 'id' => $row['qid']);
                            $preselected_questions[] = array('type' => $row['qtype'], 'id' => $row['qid'], 'serial' => $row['serialnumber']);
                        }
                        logDebug("Preselected questions details", $preselected_questions);
                    } else {
                        // Fallback to generating random questions if none are preselected
                        logDebug("No preselected questions found, generating random questions");
                        $questions = array_merge(
                            getQuestions($conn, 'a', $quiz['typea'], $chapter_ids_str, $topic_ids_str),
                            getQuestions($conn, 'b', $quiz['typeb'], $chapter_ids_str, $topic_ids_str),
                            getQuestions($conn, 'c', $quiz['typec'], $chapter_ids_str, $topic_ids_str),
                            getQuestions($conn, 'd', $quiz['typed'], $chapter_ids_str, $topic_ids_str),
                            getQuestions($conn, 'e', $quiz['typee'], $chapter_ids_str, $topic_ids_str),
                            getQuestions($conn, 'f', $quiz['typef'], $chapter_ids_str, $topic_ids_str)
                        );
                    }
                } else {
                    // For non-random quizzes, get the manually selected questions from the random_quiz_questions table
                    // (same as random quizzes, but we distinguish them using the is_random flag in quizconfig)
                    $sql = "SELECT qtype, qid, serialnumber 
                           FROM random_quiz_questions 
                           WHERE quizid = ?
                           ORDER BY serialnumber ASC";
                    
                    logDebug("SQL query for manually selected questions: " . $sql, array('quizid' => $quiz['quizid']));
                    
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Failed to prepare manually selected questions statement: " . $conn->error);
                    }
                    
                    $stmt->bind_param("i", $quiz['quizid']);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to get manually selected questions: " . $stmt->error);
                    }
                    
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        logDebug("Using manually selected questions for this quiz: found " . $result->num_rows . " questions");
                        $manual_questions = array();
                        while ($row = $result->fetch_assoc()) {
                            $questions[] = array('type' => $row['qtype'], 'id' => $row['qid']);
                            $manual_questions[] = array('type' => $row['qtype'], 'id' => $row['qid'], 'serial' => $row['serialnumber']);
                        }
                        logDebug("Manually selected questions details", $manual_questions);
                    } else {
                        // Fallback to generating random questions if none are found (this should not happen for manual selection)
                        logDebug("No manually selected questions found, falling back to random selection");
                        $questions = array_merge(
                            getQuestions($conn, 'a', $quiz['typea'], $chapter_ids_str, $topic_ids_str),
                            getQuestions($conn, 'b', $quiz['typeb'], $chapter_ids_str, $topic_ids_str),
                            getQuestions($conn, 'c', $quiz['typec'], $chapter_ids_str, $topic_ids_str),
                            getQuestions($conn, 'd', $quiz['typed'], $chapter_ids_str, $topic_ids_str),
                            getQuestions($conn, 'e', $quiz['typee'], $chapter_ids_str, $topic_ids_str),
                            getQuestions($conn, 'f', $quiz['typef'], $chapter_ids_str, $topic_ids_str)
                        );
                    }
                }
            } catch (Exception $e) {
                logDebug("Error getting questions during array_merge or getQuestions internal error.", array('error' => $e->getMessage()));
                throw new Exception("Failed to get required questions: " . $e->getMessage());
            }
            
            // Remove any duplicate questions that might have been selected
            $unique_questions = [];
            $seen = [];
            foreach ($questions as $q) {
                $key = $q['type'] . '-' . $q['id'];
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $unique_questions[] = $q;
                }
            }
            $questions = $unique_questions;

            // Check if we got enough questions
            if (count($questions) > 0) {
                logDebug("Got questions", array('count' => count($questions)));
                
                // Begin transaction for storing questions
                $conn->begin_transaction();
                
                try {
                    // Store questions in response table
                    foreach ($questions as $index => $q) {
                        $sql = "INSERT INTO response (quizid, rollnumber, attempt, qtype, qid, serialnumber, response) 
                               VALUES (?, ?, ?, ?, ?, ?, '')";
                        $stmt = $conn->prepare($sql);
                        if (!$stmt) {
                            throw new Exception("Failed to prepare response statement: " . $conn->error);
                        }
                        
                        $serialnumber = $index + 1;
                        logDebug("Storing question in response table.", array('quizid' => $quiz['quizid'], 'rollnumber' => $rollnumber, 'attempt' => $_SESSION['current_attempt'], 'qtype' => $q['type'], 'qid' => $q['id'], 'serialnumber' => $serialnumber));
                        $stmt->bind_param("iiisii", 
                            $quiz['quizid'], 
                            $rollnumber, 
                            $_SESSION['current_attempt'],
                            $q['type'],
                            $q['id'],
                            $serialnumber
                        );
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to store response: " . $stmt->error);
                        }
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    logDebug("Questions stored in response table, redirecting to first question.", array('quiz_id' => $quiz['quizid'], 'target_n' => 1));
                    header("Location: quizpage.php?n=1");
                    exit;
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    throw $e;
                }
            } else {
                logDebug("No questions available after fetching, total_questions in quiz config was: " . $quiz['total_questions'], array('quiz_data' => $quiz));
                throw new Exception("No questions available for this quiz");
            }
        } else {
            logDebug("No active quiz found for rollnumber: $rollnumber. Redirecting to studenthome.");
            $_SESSION['error'] = "No available quiz found. Please check back later.";
            header("location: studenthome.php");
            exit;
        }
    } catch (Exception $e) {
        logDebug("Error during quiz initialization", array(
            'error' => $e->getMessage(),
            'session' => $_SESSION,
            'quiz_data' => isset($quiz) ? $quiz : null
        ));
        $_SESSION['error'] = "Error initializing quiz: " . $e->getMessage() . ". Please try again or contact administrator.";
        logDebug("Redirecting to studenthome due to error during quiz initialization.", array('error_message' => $_SESSION['error']));
        ob_end_clean();
        header("location: studenthome.php");
        exit;
    }
}

// Get current question number
$current_n = isset($_GET['n']) ? intval($_GET['n']) : 1;
if ($current_n < 1) $current_n = 1;
if ($current_n > $_SESSION['total_questions']) $current_n = $_SESSION['total_questions'];

logDebug("Displaying question number: " . $current_n, array('session_total_questions' => $_SESSION['total_questions'], 'current_attempt' => $_SESSION['current_attempt']));

// Check if time is up
$time_remaining = $_SESSION['end_time'] - time();
logDebug("Time check", array('end_time' => $_SESSION['end_time'], 'current_time' => time(), 'time_remaining' => $time_remaining));
if ($time_remaining <= 0) {
    logDebug("Quiz time is up - redirecting to submit.php?auto=1", $_SESSION);
    ob_end_clean();
    header("location: submit.php?auto=1");
    exit;
}

// Handle POST request for saving answers
if ($_POST) {
    try {
        logDebug("Processing POST request to save answer.", array('POST' => $_POST, 'current_n_on_page_load' => $current_n, 'SESSION' => $_SESSION));
        $n_post = isset($_POST['serialnumber']) ? intval($_POST['serialnumber']) : $current_n;
        $ans_post = isset($_POST['answer']) ? $conn->real_escape_string($_POST['answer']) : '';
        
        if (!is_numeric($rollnumber) || !is_numeric($_SESSION['quiz_id'])) {
            logDebug("Invalid parameters for saving answer.", array('rollnumber' => $rollnumber, 'session_quiz_id' => $_SESSION['quiz_id']));
            throw new Exception("Invalid parameters for saving answer");
        }
        
        // Update response
        $sql_update = "UPDATE response SET response = ? WHERE rollnumber = ? AND quizid = ? AND attempt = ? AND serialnumber = ?";
        logDebug("Updating answer. SQL: $sql_update", array('ans_post' => $ans_post, 'rollnumber' => $rollnumber, 'quiz_id' => $_SESSION['quiz_id'], 'attempt' => $_SESSION['current_attempt'], 'serialnumber' => $n_post));
        $stmt = $conn->prepare($sql_update);
        if (!$stmt) {
            throw new Exception("Failed to prepare update statement: " . $conn->error);
        }
        
        $stmt->bind_param("siiii", $ans_post, $rollnumber, $_SESSION['quiz_id'], $_SESSION['current_attempt'], $n_post);
        if (!$stmt->execute()) {
            throw new Exception("Failed to save answer: " . $stmt->error);
        }
        
        logDebug("Answer saved successfully", array(
            'question' => $n_post,
            'answer' => $ans_post
        ));
        
        // Handle navigation
        $redirect_to_n = $n_post; // Default: stay on the same question if no specific navigation
        if (isset($_POST['button'])) {
            logDebug("Button pressed for navigation.", array('button' => $_POST['button'], 'current_n_post' => $n_post));
            switch ($_POST['button']) {
                case 'savenext':
                    $redirect_to_n = ($n_post % $_SESSION['total_questions']) + 1;
                    break;
                case 'saveprev':
                    $redirect_to_n = ($n_post - 2 + $_SESSION['total_questions']) % $_SESSION['total_questions'] + 1;
                    break;
                case 'submitquiz':
                    logDebug("Submit quiz button pressed. Redirecting to submit.php.", $_SESSION);
                    header("Location: submit.php");
                    exit;
                case 'save': // Just save, stay on current question
                    // $redirect_to_n is already $n_post
                    break;
            }
            logDebug("Navigation decision made.", array('redirect_to_n' => $redirect_to_n));
        }
        
        logDebug("Redirecting after saving answer.", array('target_n' => $redirect_to_n));
        header("Location: quizpage.php?n=" . $redirect_to_n);
        exit;
    } catch (Exception $e) {
        logDebug("Error saving answer", array('error' => $e->getMessage(), 'POST' => $_POST, 'SESSION' => $_SESSION));
        $_SESSION['error'] = "Failed to save answer. Please try again.";
        logDebug("Redirecting to current question due to error saving answer.", array('current_n' => $current_n));
        header("Location: quizpage.php?n=" . $current_n);
        exit;
    }
}

// Get current question details
try {
    $sql = "SELECT r.*, 
            r.response as ans,
            CASE r.qtype 
                WHEN 'a' THEN m.question 
                WHEN 'b' THEN n.question
                WHEN 'c' THEN d.question
                WHEN 'd' THEN f.question
                WHEN 'e' THEN s.question
                WHEN 'f' THEN e.question
            END as question_text,
            CASE r.qtype
                WHEN 'a' THEN m.optiona
                ELSE NULL
            END as optiona,
            CASE r.qtype
                WHEN 'a' THEN m.optionb
                ELSE NULL
            END as optionb,
            CASE r.qtype
                WHEN 'a' THEN m.optionc
                ELSE NULL
            END as optionc,
            CASE r.qtype
                WHEN 'a' THEN m.optiond
                ELSE NULL
            END as optiond,
            CASE r.qtype
                WHEN 'c' THEN d.options
                ELSE NULL
            END as dropdown_options
        FROM response r
        LEFT JOIN mcqdb m ON r.qtype = 'a' AND r.qid = m.id
        LEFT JOIN numericaldb n ON r.qtype = 'b' AND r.qid = n.id
        LEFT JOIN dropdown d ON r.qtype = 'c' AND r.qid = d.id
        LEFT JOIN fillintheblanks f ON r.qtype = 'd' AND r.qid = f.id
        LEFT JOIN shortanswer s ON r.qtype = 'e' AND r.qid = s.id
        LEFT JOIN essay e ON r.qtype = 'f' AND r.qid = e.id
        WHERE r.rollnumber = ? 
        AND r.quizid = ? 
        AND r.attempt = ?
        AND r.serialnumber = ?";

    logDebug("Fetching current question details. SQL: " . preg_replace('/\s+/', ' ', $sql), array('rollnumber' => $rollnumber, 'quiz_id' => $_SESSION['quiz_id'], 'attempt' => $_SESSION['current_attempt'], 'serialnumber' => $current_n));
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare question query: " . $conn->error);
    }
    
    $stmt->bind_param("iiii", $rollnumber, $_SESSION['quiz_id'], $_SESSION['current_attempt'], $current_n);
    if (!$stmt->execute()) {
        throw new Exception("Failed to get question: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) {
        logDebug("Question not found in response table for serial number: $current_n", array('rollnumber' => $rollnumber, 'quiz_id' => $_SESSION['quiz_id'], 'attempt' => $_SESSION['current_attempt']));
        throw new Exception("Question not found");
    }
    
    $question = $result->fetch_assoc();
    logDebug("Retrieved question", array(
        'type' => $question['qtype'],
        'id' => $question['qid']
    ));
    
    // Set active tab based on question type
    $active_tabs = array_fill(0, 6, '');
    switch ($question['qtype']) {
        case 'a': $active_tabs[0] = 'active'; break;
        case 'b': $active_tabs[1] = 'active'; break;
        case 'c': $active_tabs[2] = 'active'; break;
        case 'd': $active_tabs[3] = 'active'; break;
        case 'e': $active_tabs[4] = 'active'; break;
        case 'f': $active_tabs[5] = 'active'; break;
    }
    
    // Set radio button states for MCQ
    $radio_states = array_fill(0, 4, '');
    if ($question['qtype'] === 'a' && !empty($question['ans'])) {
        $radio_states[ord(strtoupper($question['ans'])) - ord('A')] = 'checked';
    }
    
} catch (Exception $e) {
    logDebug("Error getting question details.", array('error' => $e->getMessage(), 'SESSION' => $_SESSION));
    $_SESSION['error'] = "Error loading question: " . $e->getMessage();
    logDebug("Redirecting to quizhome due to error getting question.", array('error_message' => $_SESSION['error']));
    ob_end_clean();
    header("location: quizhome.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Page</title>
    <!-- Material Dashboard CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@material/material-dashboard@1.0.0/dist/css/material-dashboard.min.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- Animation library -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Roboto', sans-serif;
        }
        .card {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            margin-top: 20px;
        }

        /* Ensure main content starts below the navbar */
        .content {
            margin-top: 80px;
        }
        .card-header-primary {
            background: linear-gradient(60deg, #ab47bc, #8e24aa);
            box-shadow: 0 4px 20px 0px rgba(0, 0, 0, 0.14), 0 7px 10px -5px rgba(156, 39, 176, 0.4);
            border-radius: 8px 8px 0 0;
            padding: 15px;
        }
        .question-nav {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .question-text {
            padding: 20px;
            background-color: #fff;
            border-radius: 4px;
        }
        .answer-section {
            padding: 20px;
        }
        .form-check {
            margin: 10px 0;
            padding-left: 30px;
        }
        .btn {
            text-transform: uppercase;
            padding: 12px 30px;
            margin: 5px;
        }
        .btn-primary {
            background-color: #9c27b0;
            box-shadow: 0 2px 2px 0 rgba(156, 39, 176, 0.14), 0 3px 1px -2px rgba(156, 39, 176, 0.2), 0 1px 5px 0 rgba(156, 39, 176, 0.12);
        }
        .btn-danger {
            background-color: #f44336;
            box-shadow: 0 2px 2px 0 rgba(244, 67, 54, 0.14), 0 3px 1px -2px rgba(244, 67, 54, 0.2), 0 1px 5px 0 rgba(244, 67, 54, 0.12);
        }
        #timer {
            font-size: 1.2em;
            font-weight: bold;
            color: white;
        }
        .form-control {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        textarea.form-control {
            min-height: 120px;
        }
    </style>
    <script>
    function updateTimer() {
        var endTime = <?php echo $_SESSION['end_time']; ?> * 1000;
        var now = new Date().getTime();
        var timeLeft = endTime - now;
        
        if (timeLeft <= 0) {
            document.getElementById('timer').innerHTML = 'Time Up!';
            // Add a small delay before redirect to ensure timer message is shown
            setTimeout(function() {
                window.location.href = 'submit.php?auto=1';
            }, 1000);
        } else {
            var minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
            document.getElementById('timer').innerHTML = 'Time Remaining: ' + 
                minutes + 'm ' + seconds + 's';
        }
    }
    
    function confirmSubmit() {
        if (confirm('Kya aap quiz submit karna chahte hain? Is action ko undo nahi kiya ja sakta.')) {
            window.location.href = 'submit.php';
        }
    }
    
    window.onload = function() {
        // Check time remaining immediately on page load
        var endTime = <?php echo $_SESSION['end_time']; ?> * 1000;
        var now = new Date().getTime();
        var timeLeft = endTime - now;
        
        if (timeLeft <= 0) {
            document.getElementById('timer').innerHTML = 'Time Up!';
            window.location.href = 'submit.php?auto=1';
        } else {
            updateTimer();
            setInterval(updateTimer, 1000);
        }
    };
    </script>
<link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
    <link href="./assets/css/portal.css" rel="stylesheet" />
</head>
<body class="landing-page sidebar-collapse">
    <div class="wrapper">
        <div class="content">
            <div class="container-fluid">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <i class="material-icons">close</i>
                        </button>
                        <span><b>Error:</b> <?php echo htmlspecialchars($_SESSION['error']); ?></span>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card" data-aos="fade-up">
                            <div class="card-header card-header-primary">
                                <div class="row">
                                    <div class="col-md-4">
                                        <h4 class="card-title">Question <?php echo htmlspecialchars($current_n); ?> of <?php echo htmlspecialchars($_SESSION['total_questions']); ?></h4>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div id="timer" class="text-white">Time Remaining: Loading...</div>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <button type="button" class="btn btn-danger" onclick="confirmSubmit()">
                                            Submit Quiz
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <!-- Question Navigation -->
                                <div class="question-nav mb-4">
                                    <?php for ($i = 1; $i <= $_SESSION['total_questions']; $i++): 
                                        $btnClass = 'btn-outline-primary';
                                        if ($i == $current_n) {
                                            $btnClass = 'btn-primary';
                                        } elseif (!empty($question['ans'])) {
                                            $btnClass = 'btn-success';
                                        }
                                    ?>
                                        <a href="quizpage.php?n=<?php echo $i; ?>" 
                                           class="btn <?php echo $btnClass; ?> btn-sm m-1">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                                
                                <!-- Question Content -->
                                <form method="post" action="quizpage.php?n=<?php echo $current_n; ?>">
                                    <input type="hidden" name="serialnumber" value="<?php echo $current_n; ?>">
                                    
                                    <div class="question-text mb-4" data-aos="fade-right">
                                        <h4><?php echo htmlspecialchars($question['question_text']); ?></h4>
                                    </div>
                                    
                                    <div class="answer-section" data-aos="fade-left">
                                        <?php switch($question['qtype']): 
                                            case 'a': // MCQ ?>
                                                <div class="form-group">
                                                    <?php foreach(['A' => 'optiona', 'B' => 'optionb', 'C' => 'optionc', 'D' => 'optiond'] as $opt => $field): ?>
                                                        <div class="form-check">
                                                            <label class="form-check-label">
                                                                <input class="form-check-input" type="radio" 
                                                                       name="answer" value="<?php echo $opt; ?>"
                                                                       <?php echo ($question['ans'] == $opt) ? 'checked' : ''; ?>>
                                                                <?php echo htmlspecialchars($question[$field]); ?>
                                                                <span class="circle"><span class="check"></span></span>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php break;
                                                
                                            case 'b': // Numerical ?>
                                                <div class="form-group">
                                                    <input type="number" class="form-control" name="answer" 
                                                           value="<?php echo htmlspecialchars($question['ans']); ?>"
                                                           placeholder="Enter your numerical answer">
                                                </div>
                                                <?php break;
                                                
                                            case 'c': // Dropdown ?>
                                                <div class="form-group">
                                                    <select class="form-control" name="answer">
                                                        <option value="">Select your answer</option>
                                                        <?php 
                                                        $options = explode(',', $question['dropdown_options']);
                                                        foreach ($options as $option): 
                                                            $option = trim($option);
                                                        ?>
                                                            <option value="<?php echo htmlspecialchars($option); ?>"
                                                                    <?php echo ($question['ans'] == $option) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($option); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <?php break;
                                                
                                            case 'd': // Fill in blanks ?>
                                                <div class="form-group">
                                                    <input type="text" class="form-control" name="answer"
                                                           value="<?php echo htmlspecialchars($question['ans']); ?>"
                                                           placeholder="Fill in the blank">
                                                </div>
                                                <?php break;
                                                
                                            case 'e': // Short answer ?>
                                                <div class="form-group">
                                                    <textarea class="form-control" name="answer" rows="3"
                                                              placeholder="Write your answer"><?php echo htmlspecialchars($question['ans']); ?></textarea>
                                                </div>
                                                <?php break;
                                                
                                            case 'f': // Essay ?>
                                                <div class="form-group">
                                                    <textarea class="form-control" name="answer" rows="6"
                                                              placeholder="Write your essay"><?php echo htmlspecialchars($question['ans']); ?></textarea>
                                                </div>
                                                <?php break;
                                        endswitch; ?>
                                    </div>
                                    
                                    <div class="button-group mt-4">
                                        <div class="row">
                                            <div class="col text-left">
                                                <?php if ($current_n > 1): ?>
                                                    <button type="submit" name="button" value="saveprev" class="btn btn-info">
                                                        <i class="material-icons">arrow_back</i> Previous
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="col text-center">
                                                <button type="submit" name="button" value="savenext" class="btn btn-success">
                                                    <i class="material-icons">save</i> Save Answer
                                                </button>
                                            </div>
                                            
                                            <div class="col text-right">
                                                <?php if ($current_n < $_SESSION['total_questions']): ?>
                                                    <button type="submit" name="button" value="savenext" class="btn btn-primary">
                                                        Next <i class="material-icons">arrow_forward</i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" name="button" value="submitquiz" class="btn btn-warning">
                                                        <i class="material-icons">check_circle</i> Submit Quiz
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- AOS animation library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        AOS.init({
            once: true
        });
    </script>
<script src="./assets/js/dark-mode.js"></script>
</body>
</html>