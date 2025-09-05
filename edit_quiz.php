<?php
session_start();
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

include "database.php";

$quiz_id_to_edit = null;
$quiz_data = null;
$feedback_message = '';
$page_title = "Edit Quiz";

if (isset($_GET['quiz_id'])) {
    $quiz_id_to_edit = intval($_GET['quiz_id']);
    $stmt = $conn->prepare("SELECT * FROM quizconfig WHERE quiznumber = ?");
    $stmt->bind_param("i", $quiz_id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $quiz_data = $result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "Quiz not found.";
        header("Location: manage_quizzes.php");
        exit;
    }
    $stmt->close();
} else if (!isset($_POST['quiz_id_to_edit'])) { // If not GET and not POST with id, redirect
    $_SESSION['error_message'] = "No quiz ID provided.";
    header("Location: manage_quizzes.php");
    exit;
}

// Fetch subjects for dropdown
$subjects = [];
$sql_subjects = "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC";
$result_subjects = $conn->query($sql_subjects);
if ($result_subjects && $result_subjects->num_rows > 0) {
    while ($row_subject = $result_subjects->fetch_assoc()) {
        $subjects[] = $row_subject;
    }
}

// Fetch classes for dropdown
$classes = [];
$sql_classes = "SELECT class_id, class_name FROM classes ORDER BY class_name ASC";
$result_classes = $conn->query($sql_classes);
if ($result_classes && $result_classes->num_rows > 0) {
    while ($row_class = $result_classes->fetch_assoc()) {
        $classes[] = $row_class;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quiz_id_to_edit'])) {
    $quiz_id_to_edit = intval($_POST['quiz_id_to_edit']); // Get ID from hidden field

    // Fetch existing data again to be sure, or use $quiz_data if already fetched and this is the same request context
    // If $quiz_data is not set (e.g. direct POST attempt or refresh after error), re-fetch.
    if (!$quiz_data && $quiz_id_to_edit) {
        $stmt_refetch = $conn->prepare("SELECT * FROM quizconfig WHERE quiznumber = ?");
        $stmt_refetch->bind_param("i", $quiz_id_to_edit);
        $stmt_refetch->execute();
        $result_refetch = $stmt_refetch->get_result();
        if ($result_refetch->num_rows > 0) {
            $quiz_data = $result_refetch->fetch_assoc(); // Update $quiz_data with current db state before applying POST
        }
        $stmt_refetch->close();
    }

    // Validate chapter selection
    $selected_chapters_value = NULL;
    if(isset($_POST['chapter_ids'])){
        if(is_array($_POST['chapter_ids'])){
            $filtered_chapters = array_filter($_POST['chapter_ids'], function($value) {
                return $value !== 'all' && $value !== '' && is_numeric($value);
            });
            if(!empty($filtered_chapters)){
                // Make sure all values are integers
                $filtered_chapters = array_map('intval', $filtered_chapters);
                $selected_chapters_value = implode(',', $filtered_chapters);
            } else if (in_array('all', $_POST['chapter_ids'])){
                $selected_chapters_value = 'all';
            }
        } else if ($_POST['chapter_ids'] === 'all'){
            $selected_chapters_value = 'all';
        } else if(is_numeric($_POST['chapter_ids'])){
            $selected_chapters_value = intval($_POST['chapter_ids']);
        }
    }

    // If no chapters are selected despite the field being present, set as 'all'
    if (empty($selected_chapters_value) && isset($_POST['chapter_ids'])) {
        $selected_chapters_value = 'all';
    }

    if (empty($selected_chapters_value) && $selected_chapters_value !== 'all') {
        $feedback_message = '<p class="h6 text-center" style="color:red;">Error: Please select at least one chapter for the quiz.</p>';
    } else {
        $typeamarks = intval($_POST["typeamarks"]);
        $typea = intval($_POST["typea"]);
        $typebmarks = intval($_POST["typebmarks"]);
        $typeb = intval($_POST["typeb"]);
        $typecmarks = intval($_POST["typecmarks"]);
        $typec = intval($_POST["typec"]);
        $typedmarks = intval($_POST["typedmarks"]);
        $typed = intval($_POST["typed"]);
        $typeemarks = intval($_POST["typeemarks"]);
        $typee = intval($_POST["typee"]);
        $typefmarks = intval($_POST["typefmarks"]);
        $typef = intval($_POST["typef"]);
        $maxmarks = $typeamarks * $typea + $typebmarks * $typeb + $typecmarks * $typec + $typedmarks * $typed + $typeemarks * $typee + $typefmarks * $typef;
        
        $duration = intval($_POST["duration"]);
        $starttime = $_POST["starttime"]; // Format expected: DD/MM/YYYY hh:mm A
        $endtime = isset($_POST["endtime"]) ? $_POST["endtime"] : ''; // Format expected: DD/MM/YYYY hh:mm A
        
        // Debug the input datetime value
        error_log("EDIT_QUIZ RAW STARTTIME: " . $starttime);
        error_log("EDIT_QUIZ RAW ENDTIME: " . $endtime);
        
        // Convert date format from DD/MM/YYYY hh:mm A to YYYY-MM-DD HH:MM:SS for database
        if (!empty($starttime)) {
            // First try the expected format
            $dateObj = DateTime::createFromFormat('d/m/Y h:i A', $starttime);
            
            // If that fails, try other common formats
            if (!$dateObj) {
                $dateObj = DateTime::createFromFormat('d/m/Y H:i', $starttime);
            }
            if (!$dateObj) {
                $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $starttime);
            }
            if (!$dateObj) {
                $dateObj = DateTime::createFromFormat('Y-m-d h:i A', $starttime);
            }
            
            // If we have a valid date object, format it for the database
            if ($dateObj) {
                $starttime = $dateObj->format('Y-m-d H:i:s');
                error_log("EDIT_QUIZ CONVERTED STARTTIME: " . $starttime);
            } else {
                // If all parsing attempts failed, log the error and use a default
                error_log("EDIT_QUIZ ERROR: Failed to parse date: " . $starttime . ". DateTime errors: " . print_r(DateTime::getLastErrors(), true));
                
                // Set a valid default date/time
                $starttime = date('Y-m-d H:i:s');
                error_log("EDIT_QUIZ USING DEFAULT STARTTIME: " . $starttime);
            }
        } else {
            // If starttime is empty, use current date/time
            $starttime = date('Y-m-d H:i:s');
            error_log("EDIT_QUIZ EMPTY STARTTIME, USING CURRENT: " . $starttime);
        }

        // Process end time
        $endtime_sql = "NULL";
        if (!empty($endtime)) {
            // First try the expected format
            $dateObj = DateTime::createFromFormat('d/m/Y h:i A', $endtime);
            
            // If that fails, try other common formats
            if (!$dateObj) {
                $dateObj = DateTime::createFromFormat('d/m/Y H:i', $endtime);
            }
            if (!$dateObj) {
                $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $endtime);
            }
            if (!$dateObj) {
                $dateObj = DateTime::createFromFormat('Y-m-d h:i A', $endtime);
            }
            
            // If we have a valid date object, format it for the database
            if ($dateObj) {
                $endtime = $dateObj->format('Y-m-d H:i:s');
                $endtime_sql = "'" . $conn->real_escape_string($endtime) . "'";
                error_log("EDIT_QUIZ CONVERTED ENDTIME: " . $endtime);
            } else {
                // If all parsing attempts failed, calculate based on duration
                error_log("EDIT_QUIZ ERROR: Failed to parse end date: " . $endtime . ". DateTime errors: " . print_r(DateTime::getLastErrors(), true));
                
                // Calculate end time based on start time + duration
                $endtime = date('Y-m-d H:i:s', strtotime($starttime . ' +' . $duration . ' minutes'));
                $endtime_sql = "'" . $conn->real_escape_string($endtime) . "'";
                error_log("EDIT_QUIZ CALCULATED ENDTIME FROM DURATION: " . $endtime);
            }
        } else {
            // If endtime is empty, calculate based on start time + duration
            $endtime = date('Y-m-d H:i:s', strtotime($starttime . ' +' . $duration . ' minutes'));
            $endtime_sql = "'" . $conn->real_escape_string($endtime) . "'";
            error_log("EDIT_QUIZ EMPTY ENDTIME, CALCULATED FROM DURATION: " . $endtime);
        }
        
        // Escape the date string properly for SQL
        $starttime_sql = "'" . $conn->real_escape_string($starttime) . "'";
        error_log("EDIT_QUIZ ESCAPED STARTTIME FOR SQL: " . $starttime_sql);
        error_log("EDIT_QUIZ ESCAPED ENDTIME FOR SQL: " . $endtime_sql);
        
        $subject_id = !empty($_POST["subject_id"]) ? intval($_POST["subject_id"]) : NULL;
        $class_id = !empty($_POST["class_id"]) ? intval($_POST["class_id"]) : NULL;
        $total_questions = isset($_POST["total_questions"]) ? intval($_POST["total_questions"]) : 10;
        $random_quiz = isset($_POST["random_quiz"]) ? 1 : 0;
        $quiz_name = isset($_POST["quizname"]) ? $conn->real_escape_string(trim($_POST["quizname"])) : ($quiz_data['quizname'] ?? 'Quiz');
        $attempts = isset($_POST["attempts"]) ? intval($_POST["attempts"]) : ($quiz_data['attempts'] ?? 1);
        // Handle section selection
        $section = NULL;
        if (!empty($_POST['section_id'])) {
            $section_id_input = intval($_POST['section_id']);
            $section_stmt = $conn->prepare("SELECT section_name FROM sections WHERE id = ?");
            $section_stmt->bind_param("i", $section_id_input);
            $section_stmt->execute();
            $section_res = $section_stmt->get_result();
            if ($section_row = $section_res->fetch_assoc()) {
                $section = $section_row['section_name'];
            }
            $section_stmt->close();
        } elseif (!empty($_POST['section'])) {
            $section = $conn->real_escape_string(trim($_POST['section']));
        }

        // Handle topic selection
        $topic_ids = NULL;
        if (isset($_POST['topic_ids'])) {
            $topic_input = $_POST['topic_ids'];
            if (!is_array($topic_input)) {
                $topic_input = explode(',', $topic_input);
            }
            $topic_input = array_filter($topic_input, function($value) { return $value !== ''; });
            if (!empty($topic_input)) {
                $topic_ids = implode(',', array_map('intval', $topic_input));
            }
        }

        // Make sure all integer values are properly converted to integers
        $attempts = intval($attempts);
        $subject_id = $subject_id !== NULL ? intval($subject_id) : NULL;
        $class_id = $class_id !== NULL ? intval($class_id) : NULL;
        $duration = intval($duration);
        $maxmarks = intval($maxmarks);
        $typea = intval($typea);
        $typeamarks = intval($typeamarks);
        $typeb = intval($typeb);
        $typebmarks = intval($typebmarks);
        $typec = intval($typec);
        $typecmarks = intval($typecmarks);
        $typed = intval($typed);
        $typedmarks = intval($typedmarks);
        $typee = intval($typee);
        $typeemarks = intval($typeemarks);
        $typef = intval($typef);
        $typefmarks = intval($typefmarks);
        $total_questions = intval($total_questions);
        $random_quiz = intval($random_quiz);
        $quiz_id_to_edit = intval($quiz_id_to_edit);
        
        // Properly escape string values for SQL
        $quiz_name_sql = "'" . $conn->real_escape_string($quiz_name) . "'";
        $starttime_sql = "'" . $conn->real_escape_string($starttime) . "'";
        $selected_chapters_sql = "'" . $conn->real_escape_string($selected_chapters_value) . "'";
        $section_sql = $section !== NULL ? "'" . $conn->real_escape_string($section) . "'" : "NULL";
        $topic_ids_sql = $topic_ids !== NULL ? "'" . $conn->real_escape_string($topic_ids) . "'" : "NULL";
        $subject_id_sql = $subject_id !== NULL ? $subject_id : "NULL";
        $class_id_sql = $class_id !== NULL ? $class_id : "NULL";
        
        // Add debug logging to verify parameter values before binding
        error_log("EDIT_QUIZ DEBUG: Data for quiz #" . $quiz_id_to_edit);
        error_log("quiz_name: " . $quiz_name);
        error_log("attempts: " . $attempts);
        error_log("subject_id: " . ($subject_id ?? 'NULL'));
        error_log("class_id: " . ($class_id ?? 'NULL'));
        error_log("starttime: " . $starttime);
        error_log("starttime_sql: " . $starttime_sql);
        error_log("duration: " . $duration);
        error_log("maxmarks: " . $maxmarks);
        error_log("typea: " . $typea . ", typeamarks: " . $typeamarks);
        error_log("typeb: " . $typeb . ", typebmarks: " . $typebmarks);
        error_log("typec: " . $typec . ", typecmarks: " . $typecmarks);
        error_log("typed: " . $typed . ", typedmarks: " . $typedmarks);
        error_log("typee: " . $typee . ", typeemarks: " . $typeemarks);
        error_log("typef: " . $typef . ", typefmarks: " . $typefmarks);
        error_log("total_questions: " . $total_questions);
        error_log("is_random: " . $random_quiz);
        error_log("chapter_ids: " . $selected_chapters_value);
        error_log("section: " . ($section ?? 'NULL'));
        error_log("topic_ids: " . ($topic_ids ?? 'NULL'));
        
        // Build a direct SQL query instead of using prepared statements
        $sql_update = "UPDATE quizconfig SET 
                quizname = $quiz_name_sql, 
                attempts = $attempts, 
                subject_id = $subject_id_sql, 
                class_id = $class_id_sql, 
                starttime = $starttime_sql,
                endtime = $endtime_sql, 
                duration = $duration, 
                maxmarks = $maxmarks,
                typea = $typea, 
                typeamarks = $typeamarks, 
                typeb = $typeb, 
                typebmarks = $typebmarks, 
                typec = $typec, 
                typecmarks = $typecmarks,
                typed = $typed, 
                typedmarks = $typedmarks, 
                typee = $typee, 
                typeemarks = $typeemarks, 
                typef = $typef, 
                typefmarks = $typefmarks,
                total_questions = $total_questions, 
                is_random = $random_quiz, 
                chapter_ids = $selected_chapters_sql,
                topic_ids = $topic_ids_sql,
                section = $section_sql
            WHERE quiznumber = $quiz_id_to_edit";
        
        error_log("EDIT_QUIZ DIRECT SQL: " . $sql_update);
        
        try {
            // Execute the SQL directly
            if ($conn->query($sql_update)) {
                $feedback_message = '<p class="h6 text-center" style="color:green;">Quiz #' . $quiz_id_to_edit . ' updated successfully!</p>';
                // Refresh $quiz_data with new values
                $stmt_refresh = $conn->prepare("SELECT * FROM quizconfig WHERE quiznumber = ?");
                $stmt_refresh->bind_param("i", $quiz_id_to_edit);
                $stmt_refresh->execute();
                $result_refresh = $stmt_refresh->get_result();
                $quiz_data = $result_refresh->fetch_assoc();
                $stmt_refresh->close();
                echo '<script>setTimeout(function(){ window.location.href = "manage_quizzes.php"; }, 2000);</script>'; // Redirect after 2 seconds
            } else {
                $feedback_message = '<p class="h6 text-center" style="color:red;">Error updating quiz: ' . $conn->error . '</p>';
                error_log("EDIT_QUIZ ERROR (execute): " . $conn->error);
            }
        } catch (Exception $e) {
            $feedback_message = '<p class="h6 text-center" style="color:red;">Error updating quiz: ' . $e->getMessage() . '</p>';
            error_log("EDIT_QUIZ EXCEPTION: " . $e->getMessage());
            error_log("EDIT_QUIZ STACK TRACE: " . $e->getTraceAsString());
        }
    }
}

// Default values if $quiz_data is not set (e.g. new quiz, though this page is for edit)
// Or if some fields are nullable and not set in DB, provide defaults for form display.
$q_num = $quiz_data['quiznumber'] ?? $quiz_id_to_edit ?? '';
$q_duration = $quiz_data['duration'] ?? 10;
$q_starttime = $quiz_data['starttime'] ?? '01/01/2024 10:00 AM'; // Default format

// Convert DB format to display format if needed
if (!empty($q_starttime)) {
    // If it's in YYYY-MM-DD HH:MM:SS format, convert to DD/MM/YYYY hh:mm A
    $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $q_starttime);
    if ($dateObj) {
        $q_starttime = $dateObj->format('d/m/Y h:i A');
    }
}

$q_subject_id = $quiz_data['subject_id'] ?? '';
$q_class_id = $quiz_data['class_id'] ?? '';
$q_chapter_ids_str = $quiz_data['chapter_ids'] ?? '';
$q_topic_ids_str = $quiz_data['topic_ids'] ?? '';
$q_total_questions = $quiz_data['total_questions'] ?? 10;
$q_is_random = $quiz_data['is_random'] ?? 0;
$q_section = $quiz_data['section'] ?? '';

// Fetch pre-selected chapter names for display

$q_typea = $quiz_data['typea'] ?? 0;
$q_typeamarks = $quiz_data['typeamarks'] ?? 0;
$q_typeb = $quiz_data['typeb'] ?? 0;
$q_typebmarks = $quiz_data['typebmarks'] ?? 0;
$q_typec = $quiz_data['typec'] ?? 0;
$q_typecmarks = $quiz_data['typecmarks'] ?? 0;
$q_typed = $quiz_data['typed'] ?? 0;
$q_typedmarks = $quiz_data['typedmarks'] ?? 0;
$q_typee = $quiz_data['typee'] ?? 0;
$q_typeemarks = $quiz_data['typeemarks'] ?? 0;
$q_typef = $quiz_data['typef'] ?? 0;
$q_typefmarks = $quiz_data['typefmarks'] ?? 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="./assets/img/favicon.png">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
  <title><?php echo $page_title; ?> - Quiz Portal</title>
  <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <link href="./assets/css/navbar.css" rel="stylesheet" />
    <link href="./assets/css/portal.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
  <style>
    /* Fixed Navbar Styles are defined globally in navbar.css */
    .navbar.main-navbar .container {
      width: 100%;
      max-width: 100%;
      margin-right: auto;
      margin-left: auto;
      padding-left: 20px;
      padding-right: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: nowrap;
    }

    /* Footer Styles */
    .footer {
      padding: 30px 0;
      margin-top: 50px;
      background: #f8f9fa;
      border-top: 1px solid #eee;
    }

    .footer .copyright {
      color: #555;
      font-size: 14px;
      line-height: 1.8;
    }

    .footer .copyright strong {
      font-weight: 600;
      color: #333;
    }

    .footer .copyright .department {
      color: #1a73e8;
      font-weight: 500;
      margin-bottom: 5px;
    }

    .footer .copyright .designer {
      font-style: italic;
      margin: 5px 0;
    }

    .footer .copyright .year {
      background: #1a73e8;
      color: white;
      padding: 2px 8px;
      border-radius: 4px;
      display: inline-block;
      margin-top: 5px;
    }

    @media (max-width: 768px) {
      .footer {
        padding: 20px 0;
        margin-top: 30px;
      }

      .footer .copyright {
        font-size: 12px;
      }
    }

    /* Existing Styles */
    body {
      overflow-x: hidden;
      padding-top: 70px;
      background-color: #f5f5f5;
    }
    .navbar-translate {
      display: flex;
      align-items: center;
    }
    .page-header {
      min-height: auto !important;
      height: auto !important;
      margin: 60px 0 0 0 !important;
      padding: 90px 0 20px 0 !important;
      background-image: none !important;
      background-color: #f5f5f5 !important;
    }
    .container {
      width: 100%;
      max-width: 1140px;
      margin: 0 auto;
      padding: 0 15px;
    }
    .card {
      margin: 0;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .card-login {
      margin: 0 auto;
      max-width: 900px;
    }
    .card-body {
      padding: 20px !important;
    }
    .form-control {
      height: auto;
      padding: 8px 12px;
    }
    .select2-container {
      width: 100% !important;
    }
    .select2-container .select2-selection--single,
    .select2-container .select2-selection--multiple {
      height: 38px;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    .quiz-type-row {
      margin-bottom: 15px;
      padding: 10px;
      background-color: #f9f9f9;
      border-radius: 4px;
    }
    .btn-primary {
      margin: 20px 0;
      padding: 12px 30px;
    }
    @media (max-width: 991px) {
      .container {
        padding: 0 10px;
      }
      .card-body {
        padding: 15px !important;
      }
      .form-row-mobile {
        margin-bottom: 15px;
      }
      .form-control {
        font-size: 14px;
      }
      .h5 {
        font-size: 0.9rem;
        margin-bottom: 10px;
      }
      .h6 {
        font-size: 0.85rem;
      }
      .quiz-type-row {
        padding: 8px;
        margin-bottom: 10px;
      }
      .btn-primary {
        width: 100%;
        margin: 15px 0;
      }
    }
    /* Fix for Select2 on mobile */
    @media (max-width: 767px) {
      .select2-container {
        width: 100% !important;
      }
      .select2-container .select2-selection--single {
        height: 38px !important;
      }
      .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px !important;
      }
    }
  </style>
  <script>
    function marks() {
        var xa = parseInt(document.getElementById("typea").value) || 0;
        var ya = parseInt(document.getElementById("typeamarks").value) || 0;
        var ta = xa * ya;  
        document.getElementById("totala").innerHTML = ta;
        
        var xb = parseInt(document.getElementById("typeb").value) || 0;
        var yb = parseInt(document.getElementById("typebmarks").value) || 0; 
        var tb = xb * yb;     
        document.getElementById("totalb").innerHTML = tb;
        
        var xc = parseInt(document.getElementById("typec").value) || 0;
        var yc = parseInt(document.getElementById("typecmarks").value) || 0; 
        var tc = xc * yc;     
        document.getElementById("totalc").innerHTML = tc;
        
        var xd = parseInt(document.getElementById("typed").value) || 0;
        var yd = parseInt(document.getElementById("typedmarks").value) || 0; 
        var td = xd * yd;
        document.getElementById("totald").innerHTML = td;
        
        var xe = parseInt(document.getElementById("typee").value) || 0;
        var ye = parseInt(document.getElementById("typeemarks").value) || 0;
        var te = xe * ye;
        document.getElementById("totale").innerHTML = te;
        
        var xf = parseInt(document.getElementById("typef").value) || 0;
        var yf = parseInt(document.getElementById("typefmarks").value) || 0;
        var tf = xf * yf;     
        document.getElementById("totalf").innerHTML = tf;
        
        var totalMarks = ta + tb + tc + td + te + tf;
        document.getElementById("total").innerHTML = totalMarks;
    }
    
    // Add form validation function
    function validateQuizForm() {
        // Validate date field
        var starttimeField = document.getElementById('starttime');
        var starttime = starttimeField.value;
        
        if (!starttime || starttime.trim() === '') {
            alert('Please select a start date and time for the quiz');
            starttimeField.focus();
            return false;
        }
        
        // Try to parse the date to make sure it's valid
        var dateParts = starttime.match(/(\d+)\/(\d+)\/(\d+)\s+(\d+):(\d+)\s+([AP]M)/i);
        if (!dateParts) {
            alert('The date format should be DD/MM/YYYY hh:mm AM/PM. Please use the date picker.');
            starttimeField.focus();
            return false;
        }
        
        // Make sure at least one chapter is selected
        var chapterField = document.getElementById('chapter_ids');
        if (chapterField && chapterField.selectedOptions.length === 0) {
            alert('Please select at least one chapter or "All Chapters"');
            return false;
        }
        
        return true;
    }
  </script>
<link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
</head>
<body class="landing-page sidebar-collapse">
  <nav class="navbar main-navbar fixed-top navbar-expand-lg">
    <div class="container">
      <div class="navbar-translate">
        <a class="navbar-brand" href="instructorhome.php">Quiz Portal</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" aria-expanded="false" aria-label="Toggle navigation">
          <span class="sr-only">Toggle navigation</span>
          <span class="navbar-toggler-icon"></span>
          <span class="navbar-toggler-icon"></span>
          <span class="navbar-toggler-icon"></span>
          <span class="navbar-toggler-icon"></span>
        </button>
      </div>
      <div class="collapse navbar-collapse">
        <ul class="navbar-nav mx-auto">
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
            <a href="my_profile.php" class="nav-link">
              <i class="material-icons">person</i> My Profile
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" rel="tooltip" title="Logout" data-placement="bottom" href="instructorlogout.php">
              <i class="material-icons">power_settings_new</i> Log Out
            </a>
          </li>
          <li class="nav-item d-flex align-items-center">
            <div class="togglebutton mb-0">
                <label class="m-0">
                  <input type="checkbox" id="darkModeToggle">
                  <span class="toggle"></span>
                </label>
              </div>
          </li>
        </ul>
      </div>      
    </div>
  </nav>
  <div class="main-container">
    <div class="page-header header-filter" style="background-image: url('./assets/img/bg2.jpg'); background-size: cover; background-position: top center;">
      <div class="container-fluid" style="padding-top: 20px;">
        <div class="row">
          <div class="col-12">
            <div class="card card-login" >
            <form class="form" name="editQuizForm" action="edit_quiz.php?quiz_id=<?php echo $quiz_id_to_edit; ?>" method="post">
              <input type="hidden" name="quiz_id_to_edit" value="<?php echo $quiz_id_to_edit; ?>">
              <div class="card-header card-header-primary text-center">
                <h4 class="card-title"><?php echo $page_title; ?> #<?php echo htmlspecialchars($q_num); ?></h4>
              </div>
              <?php if(!empty($feedback_message)) echo $feedback_message; ?>
              <p class="description text-center">Update the details of the quiz.</p>
              <div class="card-body">
                <div class="row form-row-mobile">
                  <div class="col-md-6 col-12 mb-3">
                    <div class="row">
                      <div class="col-md-4 col-5">
                        <p class="h5 mobile-text-center">Quiz Number</p>
                      </div>
                      <div class="col-md-8 col-7">
                        <input type="number" name="quiznumber_display" id="quiznumber_display" class="form-control text-center" value="<?php echo htmlspecialchars($q_num); ?>" readonly>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3 col-6 mb-3">
                    <div class="row">
                      <div class="col-6">
                        <p class="h5 mobile-text-center">Duration</p>
                      </div>
                      <div class="col-6">
                        <input type="number" min="0" name="duration" class="form-control text-center" value="<?php echo htmlspecialchars($q_duration); ?>">
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3 col-6">
                    <div class="row">
                      <div class="col-6">
                        <p class="h5 mobile-text-center">Attempts</p>
                      </div>
                      <div class="col-6">
                        <input type="number" min="1" name="attempts" class="form-control text-center" value="<?php echo htmlspecialchars($quiz_data['attempts'] ?? 1); ?>" required>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Quiz Name</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <input type="text" name="quizname" class="form-control" value="<?php echo htmlspecialchars($quiz_data['quizname'] ?? ''); ?>" required>
                  </div>
                </div>

                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Class</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <select name="class_id" id="class_id" class="form-control mobile-full-width" onchange="loadChapters()">
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class_item): ?>
                            <option value="<?php echo htmlspecialchars($class_item['class_id']); ?>" <?php if($class_item['class_id'] == $q_class_id) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($class_item['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">First select class and subject to enable chapter selection</small>
                  </div>
                </div>

                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Subject</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <select name="subject_id" id="subject_id" class="form-control mobile-full-width" onchange="loadChapters()">
                        <option value="">Select Subject</option>
                        <?php foreach($subjects as $subject): ?>
                            <option value="<?php echo $subject['subject_id']; ?>" <?php if($q_subject_id == $subject['subject_id']) echo 'selected'; ?> >
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Section</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <select class="form-control" id="section_id" name="section_id">
                        <option value="">Select Section (Optional)</option>
                    </select>
                  </div>
                </div>

                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Chapters</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <select name="chapter_ids[]" id="chapter_ids" class="form-control mobile-full-width" multiple>
                        <option value="">Select Class and Subject first</option>
                    </select>
                    <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple chapters. Select 'All Chapters' to include all.</small>
                  </div>
                </div>

                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Topics</p>
                  </div>
                  <div class="col-md-9 col-12">
                    <select name="topic_ids[]" id="topic_ids" class="form-control mobile-full-width" multiple>
                        <option value="">All Topics</option>
                    </select>
                  </div>
                </div>

                <div class="row form-row-mobile">
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Total Questions</p>
                  </div>
                  <div class="col-md-3 col-12 mb-2">
                    <input type="number" min="1" name="total_questions" id="total_questions" class="form-control text-center" value="<?php echo htmlspecialchars($q_total_questions); ?>">
                  </div>
                  <div class="col-md-3 col-12 mb-2">
                    <p class="h5 mobile-text-center">Randomize Quiz?</p>
                  </div>
                  <div class="col-md-3 col-12">
                    <div class="form-check">
                        <label class="form-check-label">
                            <input class="form-check-input position-static" type="checkbox" name="random_quiz" value="1" <?php if($q_is_random) echo 'checked'; ?>>
                            Yes
                            <span class="form-check-sign"><span class="check"></span></span>
                        </label>
                    </div>
                  </div>
                </div>
              </div>
              <div class="card-body row form-group" style="padding-left: 20px;padding-right: 20px">
                <div class="col">
                  <p class="h5">Start Date and Time for the quiz:</p>
                </div>
                <div class="col">
                  <input type="text" class="form-control datetimepicker" id="starttime" name="starttime" 
                         value="<?php echo htmlspecialchars($q_starttime); ?>" placeholder="DD/MM/YYYY hh:mm AM" 
                         data-date-format="DD/MM/YYYY hh:mm A" autocomplete="off"/> 
                  <small class="form-text text-muted">Format: DD/MM/YYYY hh:mm AM/PM - use the calendar icon to select</small>
                </div>       
              </div>

              <div class="card-body row form-group" style="padding-left: 20px;padding-right: 20px">
                <div class="col">
                  <p class="h5">End Date and Time for the quiz:</p>
                </div>
                <div class="col">
                  <input type="text" class="form-control datetimepicker" id="endtime" name="endtime" 
                         value="<?php echo isset($quiz_data['endtime']) ? date('d/m/Y h:i A', strtotime($quiz_data['endtime'])) : date('d/m/Y h:i A', strtotime('+1 day')); ?>" 
                         placeholder="DD/MM/YYYY hh:mm AM" 
                         data-date-format="DD/MM/YYYY hh:mm A" autocomplete="off"/> 
                  <small class="form-text text-muted">Quiz will no longer be available after this time. Students who start the quiz before this time will still get the full duration.</small>
                </div>       
              </div>

              <div class="card-body" style="padding-left: 20px;padding-right: 20px">
                <div class="row">
                  <div class="col"><p class="h5 text-center">Type</p></div>
                  <div class="col"><p class="h5 text-center">Number</p></div>
                  <div class="col"><p class="h5 text-center">Marks for Each</p></div>
                  <div class="col"><p class="h5 text-center" style="font-style: italic;">Total</p></div>
                </div>
                <!-- Type A: MCQ -->
                <div class="row">
                  <div class="col"><p class="h6">MCQ :</p></div>
                  <div class="col"><input type="number" min="0" class="form-control text-center" name="typea" id="typea" value="<?php echo htmlspecialchars($q_typea); ?>" oninput="marks()"></div>
                  <div class="col"><input type="number" min="0" class="form-control text-center" name="typeamarks" id="typeamarks" value="<?php echo htmlspecialchars($q_typeamarks); ?>" oninput="marks()"></div>
                  <div class="col"><p class="text-center" id="totala" style="margin-top:15px;font-weight: bold;"></p><small id="mcq-available" class="text-info"></small></div>
                </div>
                <!-- Type B: Numerical -->
                <div class="row">
                  <div class="col"><p class="h6">Numerical :</p></div>
                  <div class="col"><input type="number" min="0" class="form-control text-center" name="typeb" id="typeb" value="<?php echo htmlspecialchars($q_typeb); ?>" oninput="marks()"></div>
                  <div class="col"><input type="number" min="0" class="form-control text-center" name="typebmarks" id="typebmarks" value="<?php echo htmlspecialchars($q_typebmarks); ?>" oninput="marks()"></div>
                  <div class="col"><p class="text-center" id="totalb" style="margin-top:15px;font-weight: bold;"></p><small id="numerical-available" class="text-info"></small></div>
                </div>
                <!-- Type C: Drop Down -->
                 <div class="row">
                  <div class="col"><p class="h6">Drop Down :</p></div>
                  <div class="col"><input type="number" min="0" class="form-control text-center" name="typec" id="typec" value="<?php echo htmlspecialchars($q_typec); ?>" oninput="marks()"></div>
                  <div class="col"><input type="number" min="0" class="form-control text-center" name="typecmarks" id="typecmarks" value="<?php echo htmlspecialchars($q_typecmarks); ?>" oninput="marks()"></div>
                  <div class="col"><p class="text-center" id="totalc" style="margin-top:15px;font-weight: bold;"></p><small id="dropdown-available" class="text-info"></small></div>
                </div>
                <!-- Type D: Fill in the Blanks -->
                <div class="row">
                  <div class="col"><p class="h6">Fill in the Blanks :</p></div>
                  <div class="col"><input type="number" min="0" class="form-control text-center" name="typed" id="typed" value="<?php echo htmlspecialchars($q_typed); ?>" oninput="marks()"></div>
                  <div class="col"><input type="number" min="0" class="form-control text-center" name="typedmarks" id="typedmarks" value="<?php echo htmlspecialchars($q_typedmarks); ?>" oninput="marks()"></div>
                  <div class="col"><p class="text-center" id="totald" style="margin-top:15px;font-weight: bold;"></p><small id="fillblanks-available" class="text-info"></small></div>
                </div>
                <!-- Type E: Short Answer -->
                <div class="row">
                  <div class="col"><p class="h6">Short Answer :</p></div>
                  <div class="col"><input type="number" min="0" class="form-control text-center" name="typee" id="typee" value="<?php echo htmlspecialchars($q_typee); ?>" oninput="marks()"></div>
                  <div class="col"><input type="number" min="0" class="form-control text-center" name="typeemarks" id="typeemarks" value="<?php echo htmlspecialchars($q_typeemarks); ?>" oninput="marks()"></div>
                  <div class="col"><p class="text-center" id="totale" style="margin-top:15px;font-weight: bold;"></p><small id="short-available" class="text-info"></small></div>
                </div>
                <!-- Type F: Essay -->
                 <div class="row">
                  <div class="col"><p class="h6">Essay :</p></div>
                  <div class="col"><input type="number" min="0" class="form-control text-center" name="typef" id="typef" value="<?php echo htmlspecialchars($q_typef); ?>" oninput="marks()"></div>
                  <div class="col"><input type="number" min="0" class="form-control text-center" name="typefmarks" id="typefmarks" value="<?php echo htmlspecialchars($q_typefmarks); ?>" oninput="marks()"></div>
                  <div class="col"><p class="text-center" id="totalf" style="margin-top:15px;font-weight: bold;"></p><small id="essay-available" class="text-info"></small></div>
                </div>
                <hr>
                <div class="row">
                  <div class="col"></div>
                  <div class="col"></div>
                  <div class="col"><p class="h5 text-center">Grand Total</p></div>
                  <div class="col"><p class="text-center" id="total" style="margin-top:15px;font-weight: bold;"></p></div>
                </div>
              </div>
              <div class="card-footer text-center">
                <button type="submit" class="btn btn-primary btn-wd btn-lg">Update Quiz</button>
                <a href="manage_quizzes.php" class="btn btn-danger btn-wd btn-lg">Cancel</a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<footer class="footer">
  <div class="container">
    <div class="copyright text-center w-100">
      &copy; <script>document.write(new Date().getFullYear())</script> A Project of StudyHT.com, Designed and Developed by Sir Hassan Tariq
    </div>
  </div>
</footer>
  <script src="./assets/js/core/jquery.min.js" type="text/javascript"></script>
  <script src="./assets/js/core/popper.min.js" type="text/javascript"></script>
  <script src="./assets/js/core/bootstrap-material-design.min.js" type="text/javascript"></script>
  <script src="./assets/js/plugins/moment.min.js"></script>
  <script src="./assets/js/plugins/bootstrap-datetimepicker.js" type="text/javascript"></script>
  <script src="./assets/js/plugins/nouislider.min.js" type="text/javascript"></script>
  <script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
  <script>
    $(document).ready(function() {
      // Ensure any stray navigation overlays are removed on page load
      $('#bodyClick').remove();
      $('html').removeClass('nav-open');

      marks();
      setTimeout(marks, 500);

      if($('.datetimepicker').length !== 0){
        $('.datetimepicker').datetimepicker({
          icons:{time:"fa fa-clock-o",date:"fa fa-calendar",up:"fa fa-chevron-up",down:"fa fa-chevron-down",previous:'fa fa-chevron-left',next:'fa fa-chevron-right',today:'fa fa-screenshot',clear:'fa fa-trash',close:'fa fa-remove'},
          format:'DD/MM/YYYY hh:mm A',timeZone:'Asia/Karachi',useStrict:true,keepLocalTime:true,sideBySide:true,showTodayButton:true,showClear:true,showClose:true,toolbarPlacement:'bottom',widgetPositioning:{horizontal:'auto',vertical:'bottom'}
        }).on('dp.change',function(e){var formattedDate=moment(e.date).format('DD/MM/YYYY hh:mm A');$(this).val(formattedDate);});
      }

      $('#subject_id, #class_id, #chapter_ids, #section_id, #topic_ids').select2({width:'100%',minimumResultsForSearch:10});

      var initialClassId = $('#class_id').val();
      var initialSubjectId = $('#subject_id').val();
      var chapterIdsStr = "<?php echo $q_chapter_ids_str; ?>";
      var initialChapterIds = chapterIdsStr && chapterIdsStr !== 'all' ? chapterIdsStr.split(',').map(s=>s.trim()).filter(s=>s!=='') : [];
      var allChaptersSelectedInitially = chapterIdsStr === 'all';
      var initialTopicIds = "<?php echo $q_topic_ids_str ?? ''; ?>".split(',').filter(s=>s);
      var initialSectionName = "<?php echo htmlspecialchars($q_section); ?>";

      if(initialClassId){
        loadChapters(initialClassId, initialSubjectId, initialChapterIds, allChaptersSelectedInitially, initialTopicIds);
        loadSections(initialClassId, initialSectionName);
      }

      $('#class_id, #subject_id').on('change', function(){
        var cId = $('#class_id').val();
        var sId = $('#subject_id').val();
        loadChapters(cId, sId, [], false, []);
        loadSections(cId, null);
      });

      $('#chapter_ids').on('change', function(){
        var cIds = $(this).val();
        loadTopics(cIds, []);
        updateAvailableQuestions();
      });

      $('#topic_ids').on('change', updateAvailableQuestions);

      $('form').on('submit', function(e){
        if(!validateQuizForm() || !validateQuestionCounts()) e.preventDefault();
      });
    });

    function loadChapters(classId, subjectId, selectedChapterIds, allChapters, topicIds){
      var chapterSelect=document.getElementById('chapter_ids');
      if(!classId || !subjectId){
        if(chapterSelect){
          chapterSelect.innerHTML='<option value="">Select Chapters</option>';
          $(chapterSelect).select2();
        }
        var topicSelect=document.getElementById('topic_ids');
        if(topicSelect){
          topicSelect.innerHTML='<option value="">All Topics</option>';
          $(topicSelect).select2();
        }
        return;
      }
      fetch('get_chapters.php?class_id='+classId+'&subject_id='+subjectId)
        .then(r=>r.json())
        .then(data=>{
          if(!Array.isArray(data)){
            console.error('Error loading chapters:', data);
            if(chapterSelect){
              chapterSelect.innerHTML='<option value="">Select Chapters</option>';
              $(chapterSelect).select2();
            }
            return;
          }
          chapterSelect.innerHTML='<option value="">Select Chapters</option>';
          if(data.length>0){
            var allOption=document.createElement('option');
            allOption.value='all';
            allOption.text='All Chapters';
            chapterSelect.add(allOption,1);
          }
          data.forEach(function(ch){
            chapterSelect.innerHTML+='<option value="'+ch.chapter_id+'">'+ch.chapter_name+'</option>';
          });
          $(chapterSelect).select2();
          setTimeout(function(){
            if(allChapters){
              $(chapterSelect).val('all').trigger('change');
            }else if(selectedChapterIds && selectedChapterIds.length>0){
              $(chapterSelect).val(selectedChapterIds).trigger('change');
            }
            loadTopics($(chapterSelect).val(), topicIds);
            updateAvailableQuestions();
          },100);
          $(chapterSelect).off('change.all').on('change.all',function(){
            var values=$(this).val();
            if(values && values.includes('all')){
              $(this).val('all').trigger('change');
            }
          });
        })
        .catch(err=>console.error('Error:',err));
    }

    function loadSections(classId, selectedName){
      if(!classId) return;
      fetch('get_sections.php?class_id='+classId)
        .then(r=>r.json())
        .then(data=>{
          var sectionSelect=document.getElementById('section_id');
          sectionSelect.innerHTML='<option value="">Select Section (Optional)</option>';
          data.forEach(function(sec){
            var opt=document.createElement('option');
            opt.value=sec.id;
            opt.text=sec.section_name;
            if(selectedName && sec.section_name===selectedName){ opt.selected=true; }
            sectionSelect.appendChild(opt);
          });
          $(sectionSelect).select2();
        })
        .catch(err=>console.error('Error:',err));
    }

    function loadTopics(chapterIds, selectedTopicIds){
      var topicSelect=document.getElementById('topic_ids');
      topicSelect.innerHTML='<option value="">All Topics</option>';
      if(chapterIds && chapterIds.length>0 && chapterIds!=='all'){
        fetch('get_topics.php?chapter_ids='+chapterIds.join(','))
          .then(r=>r.json())
          .then(data=>{
            data.forEach(function(topic){
              var opt=document.createElement('option');
              opt.value=topic.topic_id;
              opt.text=topic.topic_name;
              topicSelect.appendChild(opt);
            });
            $(topicSelect).select2();
            if(selectedTopicIds && selectedTopicIds.length>0){
              $(topicSelect).val(selectedTopicIds).trigger('change');
            }
          })
          .catch(err=>console.error('Error:',err));
      } else {
        $(topicSelect).select2();
      }
    }

    function updateAvailableQuestions(){
      var chapterIds=$('#chapter_ids').val();
      var topicIds=$('#topic_ids').val()||[];
      if(topicIds.length>0){
        topicIds=topicIds.filter(function(id){return id;});
        var totalTopics=$('#topic_ids option[value!=""]').length;
        if(topicIds.length===totalTopics){topicIds=[];}
      }
      if(chapterIds && chapterIds.length>0 && chapterIds!=='all'){
        var url='get_question_counts.php?chapter_ids='+chapterIds.join(',');
        if(topicIds && topicIds.length>0){url+='&topic_ids='+topicIds.join(',');}
        fetch(url)
          .then(response=>response.text())
          .then(text=>{var data=text.trim()===''?{}:JSON.parse(text);$('#typea').attr('max',data.mcq);$('#typeb').attr('max',data.numerical);$('#typec').attr('max',data.dropdown);$('#typed').attr('max',data.fillblanks);$('#typee').attr('max',data.short);$('#typef').attr('max',data.essay);$('#mcq-available').text('Available: '+data.mcq);$('#numerical-available').text('Available: '+data.numerical);$('#dropdown-available').text('Available: '+data.dropdown);$('#fillblanks-available').text('Available: '+data.fillblanks);$('#short-available').text('Available: '+data.short);$('#essay-available').text('Available: '+data.essay);})
          .catch(err=>console.error('Error:',err));
      }
    }

    function validateQuestionCounts(){
      var chapterIds=$('#chapter_ids').val();
      if(!chapterIds || chapterIds.length===0){alert('Please select at least one chapter');return false;}
      var typea=parseInt($('#typea').val())||0;
      var typeb=parseInt($('#typeb').val())||0;
      var typec=parseInt($('#typec').val())||0;
      var typed=parseInt($('#typed').val())||0;
      var typee=parseInt($('#typee').val())||0;
      var typef=parseInt($('#typef').val())||0;
      var maxMcq=parseInt($('#typea').attr('max'))||0;
      var maxNumerical=parseInt($('#typeb').attr('max'))||0;
      var maxDropdown=parseInt($('#typec').attr('max'))||0;
      var maxFill=parseInt($('#typed').attr('max'))||0;
      var maxShort=parseInt($('#typee').attr('max'))||0;
      var maxEssay=parseInt($('#typef').attr('max'))||0;
      var errors=[];
      if(typea>maxMcq) errors.push('MCQ questions requested ('+typea+') exceed available questions ('+maxMcq+')');
      if(typeb>maxNumerical) errors.push('Numerical questions requested ('+typeb+') exceed available questions ('+maxNumerical+')');
      if(typec>maxDropdown) errors.push('Dropdown questions requested ('+typec+') exceed available questions ('+maxDropdown+')');
      if(typed>maxFill) errors.push('Fill in blanks questions requested ('+typed+') exceed available questions ('+maxFill+')');
      if(typee>maxShort) errors.push('Short answer questions requested ('+typee+') exceed available questions ('+maxShort+')');
      if(typef>maxEssay) errors.push('Essay questions requested ('+typef+') exceed available questions ('+maxEssay+')');
      if(errors.length>0){alert('Error:\n'+errors.join('\n'));return false;}return true;
    }

    $(window).scroll(function(){
      if($(document).scrollTop()>50){$('.navbar').addClass('scrolled');}else{$('.navbar').removeClass('scrolled');}
    });
  </script>
<script src="./assets/js/dark-mode.js"></script>
</body>
</html>
