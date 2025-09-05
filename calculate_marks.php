<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
// Ensure instructor is logged in
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

include "database.php"; // Database connection

$quiz_number = isset($_GET['quiz_number']) ? intval($_GET['quiz_number']) : 0;
$roll_number = isset($_GET['roll_number']) ? intval($_GET['roll_number']) : 0;

$message = "";

if ($quiz_number > 0 && $roll_number > 0) {
    
    // Check if the student has submitted this quiz
    $check_submission_sql = sprintf("SELECT submit FROM result WHERE quiznumber = %d AND rollnumber = %d", $quiz_number, $roll_number);
    $submission_result = $conn->query($check_submission_sql);

    if ($submission_result && $submission_result->num_rows > 0) {
        $submission_row = $submission_result->fetch_assoc();
        if ($submission_row['submit'] != 1) {
            $message = "Error: This quiz has not been submitted by the student yet.";
        } else {
            // Fetch student's responses
            $responses_sql = sprintf(
                "SELECT quesid, type, ans, quesmarks FROM response WHERE quiznumber = %d AND rollnumber = %d ORDER BY serialnumber ASC",
                $quiz_number,
                $roll_number
            );
            $responses_result = $conn->query($responses_sql);

            $total_obtained_marks = 0;

            if ($responses_result && $responses_result->num_rows > 0) {
                while ($response_row = $responses_result->fetch_assoc()) {
                    $ques_id = intval($response_row['quesid']);
                    $ques_type = $response_row['type'];
                    $student_answer = trim($response_row['ans']);
                    $question_marks = intval($response_row['quesmarks']);
                    $correct_answer = null;
                    $is_correct = false;

                    // Fetch correct answer based on question type
                    switch ($ques_type) {
                        case 'a': // MCQ
                            $q_table = "mcqdb";
                            $q_sql = sprintf("SELECT answer FROM %s WHERE id = %d", $q_table, $ques_id);
                            $q_res = $conn->query($q_sql);
                            if ($q_res && $q_res->num_rows > 0) {
                                $correct_answer_row = $q_res->fetch_assoc();
                                $correct_answer = trim($correct_answer_row['answer']);
                                if (strtoupper($student_answer) === strtoupper($correct_answer)) {
                                    $is_correct = true;
                                }
                            }
                            break;
                        case 'b': // Numerical
                            $q_table = "numericaldb";
                            $q_sql = sprintf("SELECT answer FROM %s WHERE id = %d", $q_table, $ques_id);
                            $q_res = $conn->query($q_sql);
                            if ($q_res && $q_res->num_rows > 0) {
                                $correct_answer_row = $q_res->fetch_assoc();
                                $correct_answer = trim($correct_answer_row['answer']);
                                // Basic numeric comparison, can be enhanced for ranges or precision
                                if ($student_answer === $correct_answer) { 
                                    $is_correct = true;
                                }
                            }
                            break;
                        case 'c': // Dropdown
                            $q_table = "dropdown";
                             // Assuming 'answer' stores the serial number (1-indexed) of the correct option
                            $q_sql = sprintf("SELECT answer FROM %s WHERE id = %d", $q_table, $ques_id);
                            $q_res = $conn->query($q_sql);
                            if ($q_res && $q_res->num_rows > 0) {
                                $correct_answer_row = $q_res->fetch_assoc();
                                $correct_answer = trim($correct_answer_row['answer']);
                                if ($student_answer === $correct_answer) {
                                    $is_correct = true;
                                }
                            }
                            break;
                        case 'd': // Fill in the Blanks
                            $q_table = "fillintheblanks";
                            $q_sql = sprintf("SELECT answer FROM %s WHERE id = %d", $q_table, $ques_id);
                            $q_res = $conn->query($q_sql);
                            if ($q_res && $q_res->num_rows > 0) {
                                $correct_answer_row = $q_res->fetch_assoc();
                                $correct_answer = trim($correct_answer_row['answer']);
                                // Case-insensitive comparison for fill in the blanks
                                if (strtolower($student_answer) === strtolower($correct_answer)) {
                                    $is_correct = true;
                                }
                            }
                            break;
                        case 'e': // Short Answer - Skip auto-grading
                        case 'f': // Essay - Skip auto-grading
                            $is_correct = false; // Award 0 for now
                            break;
                        default:
                            // Unknown question type
                            break;
                    }

                    if ($is_correct) {
                        $total_obtained_marks += $question_marks;
                    }
                } // End while loop for responses

                // Update the result table
                $update_result_sql = sprintf(
                    "UPDATE result SET obtained_marks = %d WHERE quiznumber = %d AND rollnumber = %d",
                    $total_obtained_marks,
                    $quiz_number,
                    $roll_number
                );

                if ($conn->query($update_result_sql) === TRUE) {
                    $message = "Marks calculated and updated successfully for Roll No: $roll_number, Quiz No: $quiz_number. Obtained Marks: $total_obtained_marks";
                } else {
                    $message = "Error updating record: " . $conn->error;
                }

            } else {
                $message = "No responses found for this student and quiz.";
            }
        }
    } else {
         $message = "Error: No submission record found for this student and quiz in the result table. Ensure the student has submitted the quiz.";
    }
} else {
    $message = "Error: Quiz Number or Roll Number not provided correctly.";
}

$conn->close(); // Close connection here before outputting HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculate Marks</title>
    <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <style>
        body { padding: 20px; font-family: Roboto, sans-serif; }
        .message { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
        .message-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .message-error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
    </style>
<link id="dark-mode-style" rel="stylesheet" href="./assets/css/dark-mode.css" />
</head>
<body class="landing-page sidebar-collapse">
    <div class="container">
        <h3>Mark Calculation Status</h3>
        <div class="message <?php echo (strpos($message, 'Error:') === 0 || strpos($message, 'No responses') === 0 || strpos($message, 'No submission') === 0) ? 'message-error' : 'message-success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <a href="javascript:history.back()" class="btn btn-primary">Go Back</a>
        <?php if (isset($_SESSION['instructor_results_page_url'])): ?>
            <a href="<?php echo htmlspecialchars($_SESSION['instructor_results_page_url']); ?>" class="btn btn-info">Back to Results</a>
        <?php endif; ?>
    </div>
<script src="./assets/js/dark-mode.js"></script>
</body>
</html> 