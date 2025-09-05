<?php
	session_start();
    if(!isset($_SESSION["studentloggedin"]) || $_SESSION["studentloggedin"] !== true){
      header("location: studentlogin.php");
      exit;
    }

    include "database.php";

    // Check if quiz is actually started
    if (!isset($_SESSION['quiz_started']) || !isset($_SESSION['quiz_id'])) {
        $_SESSION['error'] = "Koi active quiz nahi mila.";
        header("location: quizhome.php");
        exit;
    }

    // Debug log the session information
    error_log("SUBMIT: Session data: quiz_id=" . $_SESSION['quiz_id'] . ", quiznumber=" . $_SESSION['quiznumber'] . ", attempt=" . $_SESSION['current_attempt']);

    $quizid = $_SESSION['quiz_id'];
    $rollnumber = $_SESSION['rollnumber'];
    $attempt = $_SESSION['current_attempt'];
    $is_auto = isset($_GET['auto']) && $_GET['auto'] == '1';
    
    // Verify that the quizid actually exists in the database before proceeding
    $verify_quiz_sql = "SELECT quizid, quiznumber FROM quizconfig WHERE quizid = ?";
    $verify_stmt = $conn->prepare($verify_quiz_sql);
    $verify_stmt->bind_param("i", $quizid);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows == 0) {
        // Quiz doesn't exist with this quizid - try finding it by quiznumber instead
        error_log("SUBMIT ERROR: Quiz with ID $quizid not found in quizconfig. Trying to find by quiznumber " . $_SESSION['quiznumber']);
        
        $find_by_number_sql = "SELECT quizid FROM quizconfig WHERE quiznumber = ?";
        $find_stmt = $conn->prepare($find_by_number_sql);
        $find_stmt->bind_param("i", $_SESSION['quiznumber']);
        $find_stmt->execute();
        $find_result = $find_stmt->get_result();
        
        if ($find_result->num_rows > 0) {
            $find_row = $find_result->fetch_assoc();
            $quizid = $find_row['quizid'];
            error_log("SUBMIT: Found quiz by quiznumber. Using quizid = $quizid instead");
        } else {
            // Can't find the quiz at all
            error_log("SUBMIT ERROR: Cannot find quiz with quiznumber " . $_SESSION['quiznumber'] . " either");
            $_SESSION['error'] = "Quiz ko submit nahi kiya ja sakta - quiz ID not found";
            // Clear quiz session
            unset($_SESSION['quiz_started']);
            unset($_SESSION['quiz_id']);
            unset($_SESSION['start_time']);
            unset($_SESSION['end_time']);
            unset($_SESSION['current_attempt']);
            header("location: quizhome.php");
            exit;
        }
    } else {
        $verify_row = $verify_result->fetch_assoc();
        error_log("SUBMIT: Verified quiz exists with ID $quizid (quiznumber: " . $verify_row['quiznumber'] . ")");
    }

    // Update quiz end time
    $sql = "UPDATE quizrecord SET endtime = NOW() WHERE quizid = ? AND rollnumber = ? AND attempt = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $quizid, $rollnumber, $attempt);
    $stmt->execute();

    // Calculate marks for each question type
    $marks = array(
        'mcqmarks' => 0,
        'numericalmarks' => 0,
        'dropdownmarks' => 0,
        'fillmarks' => 0,
        'shortmarks' => 0,
        'essaymarks' => 0
    );

    // Get all responses
    $sql = "SELECT r.*, 
            CASE r.qtype 
                WHEN 'a' THEN m.answer 
                WHEN 'b' THEN n.answer
                WHEN 'c' THEN d.answer
                WHEN 'd' THEN f.answer
            END as correct_answer,
            CASE r.qtype
                WHEN 'a' THEN qc.typeamarks
                WHEN 'b' THEN qc.typebmarks
                WHEN 'c' THEN qc.typecmarks
                WHEN 'd' THEN qc.typedmarks
                WHEN 'e' THEN qc.typeemarks
                WHEN 'f' THEN qc.typefmarks
            END as marks_per_question
            FROM response r 
            LEFT JOIN quizconfig qc ON r.quizid = qc.quizid
            LEFT JOIN mcqdb m ON r.qtype = 'a' AND r.qid = m.id
            LEFT JOIN numericaldb n ON r.qtype = 'b' AND r.qid = n.id
            LEFT JOIN dropdown d ON r.qtype = 'c' AND r.qid = d.id
            LEFT JOIN fillintheblanks f ON r.qtype = 'd' AND r.qid = f.id
            WHERE r.quizid = ? AND r.rollnumber = ? AND r.attempt = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $quizid, $rollnumber, $attempt);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $response = strtoupper(trim($row['response']));
        $correct = strtoupper(trim($row['correct_answer']));
        $marks_per_question = $row['marks_per_question'];
        
        switch ($row['qtype']) {
            case 'a': // MCQ
                if ($response === $correct) {
                    $marks['mcqmarks'] += $marks_per_question;
                }
                break;
                
            case 'b': // Numerical
                if ($response === $correct) {
                    $marks['numericalmarks'] += $marks_per_question;
                }
                break;
                
            case 'c': // Dropdown
                if ($response === $correct) {
                    $marks['dropdownmarks'] += $marks_per_question;
                }
                break;
                
            case 'd': // Fill in the blanks
                if ($response === $correct) {
                    $marks['fillmarks'] += $marks_per_question;
                }
                break;
                
            // Short answer and essay will be marked by instructor
        }
    }

    // Check if result already exists to avoid duplicates
    $check_result_sql = "SELECT COUNT(*) as count FROM result WHERE quizid = ? AND rollnumber = ? AND attempt = ?";
    $check_stmt = $conn->prepare($check_result_sql);
    $check_stmt->bind_param("iii", $quizid, $rollnumber, $attempt);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $row = $check_result->fetch_assoc();
    
    // Only insert if the result doesn't already exist
    if ($row['count'] == 0) {
        // Save marks
        $sql = "INSERT INTO result (quizid, rollnumber, attempt, mcqmarks, numericalmarks, dropdownmarks, fillmarks, shortmarks, essaymarks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiiii", 
            $quizid, 
            $rollnumber, 
            $attempt,
            $marks['mcqmarks'],
            $marks['numericalmarks'],
            $marks['dropdownmarks'],
            $marks['fillmarks']
        );
        
        error_log("Attempting to insert quiz result - quizid: $quizid, rollnumber: $rollnumber, attempt: $attempt");
        
        if ($stmt->execute()) {
            // Clear quiz session
            unset($_SESSION['quiz_started']);
            unset($_SESSION['quiz_id']);
            unset($_SESSION['start_time']);
            unset($_SESSION['end_time']);
            unset($_SESSION['current_attempt']);
    
            if ($is_auto) {
                $_SESSION['success'] = "Quiz ka time khatam ho gaya hai. Quiz auto-submit ho gaya hai.";
            } else {
                $_SESSION['success'] = "Quiz successfully submit ho gaya hai!";
            }
            
            header("location: my_results.php");
            exit;
        } else {
            // Log the error for debugging
            error_log("Error executing INSERT into result table: " . $stmt->error . " - quizid: $quizid, rollnumber: $rollnumber, attempt: $attempt");
            
            // Check if quizid exists in quizconfig
            $check_quiz_sql = "SELECT COUNT(*) as count FROM quizconfig WHERE quizid = ?";
            $check_quiz_stmt = $conn->prepare($check_quiz_sql);
            $check_quiz_stmt->bind_param("i", $quizid);
            $check_quiz_stmt->execute();
            $check_quiz_result = $check_quiz_stmt->get_result();
            $quiz_row = $check_quiz_result->fetch_assoc();
            
            if ($quiz_row['count'] == 0) {
                error_log("Quiz with ID $quizid does not exist in quizconfig table!");
            }
            
            $_SESSION['error'] = "Quiz submit karne mein error aaya hai. Dubara koshish karein.";
            header("location: quizhome.php");
            exit;
        }
    } else {
        // Result already exists
        error_log("Result already exists for quizid: $quizid, rollnumber: $rollnumber, attempt: $attempt - no new record inserted");
        
        // Clear quiz session anyway
        unset($_SESSION['quiz_started']);
        unset($_SESSION['quiz_id']);
        unset($_SESSION['start_time']);
        unset($_SESSION['end_time']);
        unset($_SESSION['current_attempt']);
        
        $_SESSION['success'] = "Quiz pehle hi submit ho chuka hai.";
        header("location: my_results.php");
        exit;
    }
?>