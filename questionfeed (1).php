<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Start session
session_start();

// Authentication check
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

include "database.php"; // Include database connection

// Initialize variables
$feedback_message = "";
$q_type_active = 'a'; // Default active tab (mcq)
$js_for_chapters = ""; // Will store JavaScript for dynamic chapter loading

// Initialize feedback message
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $feedback_message = '<p class="h6 text-center" style="color:green;">Question added successfully! You can add another question.</p>';
    if (isset($_GET['type'])) {
        $q_type_active = $_GET['type'];
    }
}

// Initialize variables for form fields and edit mode
$edit_mode = false;
$question_id = null;
$question_text = "";
$mcq_option_a = "";
$mcq_option_b = "";
$mcq_option_c = "";
$mcq_option_d = "";
$mcq_answer = ""; // For MCQ (A, B, C, D)
$numerical_answer = "";
$dropdown_options = "";
$dropdown_answer_serial = ""; // For Dropdown (1, 2, 3...)
$fill_answer = "";
$fill_options = ""; // Added for fill in the blanks options
$short_answer_keywords = ""; // Assuming keywords for short answers might be stored
$essay_answer_keywords = ""; // Assuming keywords for essay answers might be stored

// Add new variables for class and chapter
$class_text = "";
$chapter_text = "";

// Add new variables for chapter
$chapter_id = null;
$topic_id = null;

// Define options for Class and Chapter dropdowns by fetching from DB
$class_options = []; // Initialize as empty array
$subject_options = []; // Initialize as empty array

if ($conn) {
    // Fetch Classes from dedicated 'classes' table
    $sql_fetch_classes = "SELECT class_id, class_name FROM `classes` WHERE class_name IS NOT NULL AND class_name <> '' ORDER BY class_name ASC";
    
    $result_classes = $conn->query($sql_fetch_classes);
    if ($result_classes) {
        while ($row = $result_classes->fetch_assoc()) {
            $class_options[] = [
                'id' => $row['class_id'],
                'name' => $row['class_name']
            ];
        }
        $result_classes->free();
    }

    // Fetch Subjects
    $sql_fetch_subjects = "SELECT subject_id, subject_name FROM `subjects` WHERE subject_name IS NOT NULL AND subject_name <> '' ORDER BY subject_name ASC";
    $result_subjects = $conn->query($sql_fetch_subjects);
    if ($result_subjects) {
        while ($row = $result_subjects->fetch_assoc()) {
            $subject_options[] = [
                'id' => $row['subject_id'],
                'name' => $row['subject_name']
            ];
        }
        $result_subjects->free();
    }

    // Prepare JavaScript for dynamic chapter and topic loading
    $js_for_chapters = "<script>
    function loadQuestionFeedChapters(type) {
        var classId = document.getElementById('class_id_' + type).value;
        var subjectId = document.getElementById('subject_id_' + type).value;

        if(classId && subjectId) {
            fetch('get_chapters.php?class_id=' + classId + '&subject_id=' + subjectId)
                .then(response => response.json())
                .then(data => {
                    var chapterSelect = document.getElementById('chapter_id_' + type);
                    chapterSelect.innerHTML = '<option value=\"\">Select Chapter</option>';
                    data.forEach(function(chapter) {
                        chapterSelect.innerHTML += '<option value=\"' + chapter.chapter_id + '\">' + chapter.chapter_name + '</option>';
                    });
                    var topicSelect = document.getElementById('topic_id_' + type);
                    if(topicSelect){
                        topicSelect.innerHTML = '<option value=\"\">Select Topic</option>';
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    }

    function loadQuestionFeedTopics(type, selected) {
        var chapterId = document.getElementById('chapter_id_' + type).value;
        var topicSelect = document.getElementById('topic_id_' + type);
        if(!topicSelect) return;
        topicSelect.innerHTML = '<option value=\"\">Select Topic</option>';
        if(chapterId){
            fetch('get_topics.php?chapter_id=' + chapterId)
                .then(response => response.json())
                .then(data => {
                    data.forEach(function(topic){
                        var opt = document.createElement('option');
                        opt.value = topic.topic_id;
                        opt.text = topic.topic_name;
                        if(selected && parseInt(selected) === parseInt(topic.topic_id)){
                            opt.selected = true;
                        }
                        topicSelect.appendChild(opt);
                    });
                })
                .catch(error => console.error('Error:', error));
        }
    }
    </script>";
} else {
    // Fallback to some default options if DB connection fails
    if(empty($class_options)) $class_options = [["id" => 0, "name" => "Default Class (DB Error)"]];
    if(empty($subject_options)) $subject_options = [["id" => 0, "name" => "Default Subject (DB Error)"]];
}

$page_title = "Question Feed";
$form_action = "questionfeed.php"; // Default action for new question
$submit_button_text = "Feed";
$hidden_action_field = '<input type="hidden" name="action" value="insert"/>';


if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['q_type']) && isset($_GET['id'])) {
    $edit_mode = true;
    $question_id = intval($_GET['id']);
    $q_type_param = $_GET['q_type'];
    $page_title = "Edit Question";
    $form_action = "questionfeed.php?action=edit&q_type=" . htmlspecialchars($q_type_param) . "&id=" . $question_id; // Action for update
    $submit_button_text = "Update";
    $hidden_action_field = '<input type="hidden" name="action" value="update"/>\n<input type="hidden" name="question_id" value="' . $question_id . '"/>';


    // Determine active tab and table name based on q_type
    $table_name = "";
    switch ($q_type_param) {
        case 'mcq':
            $table_name = "mcqdb";
            $q_type_active = 'a';
            break;
        case 'numerical':
            $table_name = "numericaldb";
            $q_type_active = 'b';
            break;
        case 'dropdown':
            $table_name = "dropdown";
            $q_type_active = 'c';
            break;
        case 'fill':
            $table_name = "fillintheblanks";
            $q_type_active = 'd';
            break;
        case 'short':
            $table_name = "shortanswer";
            $q_type_active = 'e';
            break;
        case 'essay':
            $table_name = "essay";
            $q_type_active = 'f';
            break;
        default:
            // Invalid q_type, redirect or show error
            header("location: instructorhome.php?error=invalid_q_type");
            exit;
    }

    if ($conn && !empty($table_name) && $question_id > 0) {
        $stmt = null;
        if ($q_type_param == 'mcq') {
            $sql = "SELECT question, optiona, optionb, optionc, optiond, answer, chapter_id, topic_id FROM mcqdb WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $question_id);
            $stmt->execute();
            $stmt->bind_result($question_text, $mcq_option_a, $mcq_option_b, $mcq_option_c, $mcq_option_d, $mcq_answer, $chapter_id, $topic_id);
            $stmt->fetch();
        } elseif ($q_type_param == 'numerical') {
            $sql = "SELECT question, answer, chapter_id, topic_id FROM numericaldb WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $question_id);
            $stmt->execute();
            $stmt->bind_result($question_text, $numerical_answer, $chapter_id, $topic_id);
            $stmt->fetch();
        } elseif ($q_type_param == 'dropdown') {
            $sql = "SELECT question, options, answer, chapter_id, topic_id FROM dropdown WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $question_id);
            $stmt->execute();
            $stmt->bind_result($question_text, $dropdown_options, $dropdown_answer_serial, $chapter_id, $topic_id);
            $stmt->fetch();
        } elseif ($q_type_param == 'fill') {
            $sql = "SELECT question, options, answer, chapter_id, topic_id FROM fillintheblanks WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $question_id);
            $stmt->execute();
            $stmt->bind_result($question_text, $fill_options, $fill_answer, $chapter_id, $topic_id);
            $stmt->fetch();
        } elseif ($q_type_param == 'short') { // Assuming 'answer' column for short answers
            $sql = "SELECT question, answer, chapter_id, topic_id FROM shortanswer WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $question_id);
            $stmt->execute();
            $stmt->bind_result($question_text, $short_answer_keywords, $chapter_id, $topic_id); // Use appropriate variable
            $stmt->fetch();
        } elseif ($q_type_param == 'essay') { // Assuming 'answer' column for essay
            $sql = "SELECT question, answer, chapter_id, topic_id FROM essay WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $question_id);
            $stmt->execute();
            $stmt->bind_result($question_text, $essay_answer_keywords, $chapter_id, $topic_id); // Use appropriate variable
            $stmt->fetch();
        }
        if ($stmt) {
            $stmt->close();
        }
    }
}

// Process form submission first, before any HTML output
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_type = $_POST['action'] ?? 'insert';
    $current_q_type_posted = $_POST['type'] ?? '';
    
    // Debug the incoming string to see what's in it
    error_log("Raw question data: " . bin2hex($_POST['question'] ?? ''));
    
    // Function to fix backslash escape sequences in strings
    function fixEscapeSequences($text) {
        // Log the raw string for debugging
        error_log("Before fix: " . $text);
        
        // First, handle escaped sequences (backslash-escaped)
        $text = str_replace(['\\r\\n', '\\n', '\\r'], ["\r\n", "\n", "\r"], $text);
        
        // Next, handle literal "\r\n" strings that might be copied from PDF
        $text = str_replace(['\r\n', '\n', '\r'], ["\r\n", "\n", "\r"], $text);
        
        // Direct pattern matching for PDF line break pattern (non-escaped literal characters)
        $text = str_replace("\r\n", "\n", $text);
        
        // For PDF text where "\r\n" is literal characters, not escape sequences
        // Common in PDF copy-paste where line breaks are represented as literal "\r\n"
        $text = str_replace("\\r\\n", " ", $text);  // Two literal backslashes
        $text = preg_replace('/,\\\\r\\\\n/', ", ", $text); // Match comma followed by literal \r\n
        $text = preg_replace('/([a-z])\\\\r\\\\n([a-z])/i', '$1 $2', $text); // Letter followed by \r\n then letter
        
        // Special case for the pattern "unicellular,\r\neukaryotic" as mentioned in the example
        $text = preg_replace('/unicellular\\\\r\\\\neukaryotic/i', "unicellular, eukaryotic", $text);
        // More generalized versions for similar patterns
        $text = preg_replace('/([a-z]),\\s*\\\\r\\\\n\\s*([a-z])/i', '$1, $2', $text); 
        $text = preg_replace('/([a-z])\\s*\\\\r\\\\n\\s*([a-z])/i', '$1 $2', $text);
        
        // Also handle unicode literal "\r\n" (backslash + r + backslash + n as 4 characters)
        $text = str_replace("\\r\\n", " ", $text);
        
        // Also handle Unicode representation of newlines in copied text
        $text = preg_replace('/(?:\\\\u[\da-fA-F]{4})+/', "\n", $text);
        
        // Clean up any remaining literal newline placeholders
        $text = str_replace(['[NEWLINE]', '[BR]', '<br>', '<br/>'], ["\n", "\n", "\n", "\n"], $text);
        
        // One last general fix to replace any literal "\r\n" text (as 4 characters)
        $text = str_replace('\\r\\n', ' ', $text); 
        $text = str_replace('\\n', ' ', $text);
        $text = str_replace('\\r', ' ', $text);
        
        // Fix for weird non-breaking spaces and normalize all whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Log the fixed string
        error_log("After fix: " . $text);
        
        return $text;
    }
    
    // Convert literal \r\n and \n into actual newlines for question
    $question_text_raw = $_POST['question'] ?? '';
    $question_text_fixed = fixEscapeSequences($question_text_raw);
    $posted_question = $conn->real_escape_string(trim($question_text_fixed));
    
    $chapter_id = isset($_POST['chapter_id']) ? intval($_POST['chapter_id']) : null;
    $topic_id = isset($_POST['topic_id']) && $_POST['topic_id'] !== '' ? intval($_POST['topic_id']) : null;

    $success = false;
    $error_message = '';

    try {
        if ($action_type == 'update') {
            // Get question_id and q_type from POST
            $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
            $q_type = $_POST['type'] ?? '';
            
            if ($question_id > 0) {
                // Update logic based on question type
                switch($q_type) {
                    case 'a': // MCQ
                        $posted_option_a = $conn->real_escape_string(trim(fixEscapeSequences($_POST['optiona'])));
                        $posted_option_b = $conn->real_escape_string(trim(fixEscapeSequences($_POST['optionb'])));
                        $posted_option_c = $conn->real_escape_string(trim(fixEscapeSequences($_POST['optionc'])));
                        $posted_option_d = $conn->real_escape_string(trim(fixEscapeSequences($_POST['optiond'])));
                        $posted_answer = $conn->real_escape_string(trim($_POST['answer']));
                        
                        $sql = "UPDATE mcqdb SET question = ?, optiona = ?, optionb = ?, optionc = ?, optiond = ?, answer = ?, chapter_id = ?, topic_id = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("ssssssisi", $posted_question, $posted_option_a, $posted_option_b,
                                            $posted_option_c, $posted_option_d, $posted_answer, $chapter_id, $topic_id, $question_id);
                            $success = $stmt->execute();
                            if (!$success) {
                                $error_message = $stmt->error;
                            }
                            $stmt->close();
                        }
                        break;
                        
                    case 'b': // Numerical
                        $posted_answer = $conn->real_escape_string(trim(fixEscapeSequences($_POST['answer'])));
                        $sql = "UPDATE numericaldb SET question = ?, answer = ?, chapter_id = ?, topic_id = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("ssisi", $posted_question, $posted_answer, $chapter_id, $topic_id, $question_id);
                            $success = $stmt->execute();
                            if (!$success) {
                                $error_message = $stmt->error;
                            }
                            $stmt->close();
                        }
                        break;
                        
                    case 'c': // Dropdown
                        $posted_option = $conn->real_escape_string(trim(fixEscapeSequences($_POST['option'] ?? '')));
                        $posted_answer = $conn->real_escape_string(trim(fixEscapeSequences($_POST['answer'] ?? '')));
                        $sql = "UPDATE dropdown SET question = ?, options = ?, answer = ?, chapter_id = ?, topic_id = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("sssisi", $posted_question, $posted_option, $posted_answer, $chapter_id, $topic_id, $question_id);
                            $success = $stmt->execute();
                            if (!$success) {
                                $error_message = $stmt->error;
                            }
                            $stmt->close();
                        }
                        break;
                        
                    case 'd': // Fill in the blanks
                        $posted_option = $conn->real_escape_string(trim(fixEscapeSequences($_POST['option'] ?? '')));
                        $posted_answer = $conn->real_escape_string(trim(fixEscapeSequences($_POST['answer'] ?? '')));
                        $sql = "UPDATE fillintheblanks SET question = ?, options = ?, answer = ?, chapter_id = ?, topic_id = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("sssisi", $posted_question, $posted_option, $posted_answer, $chapter_id, $topic_id, $question_id);
                            $success = $stmt->execute();
                            if (!$success) {
                                $error_message = $stmt->error;
                            }
                            $stmt->close();
                        }
                        break;
                        
                    case 'e': // Short answer
                        $posted_answer = $conn->real_escape_string(trim(fixEscapeSequences($_POST['answer'] ?? '')));
                        $sql = "UPDATE shortanswer SET question = ?, answer = ?, chapter_id = ?, topic_id = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("ssisi", $posted_question, $posted_answer, $chapter_id, $topic_id, $question_id);
                            $success = $stmt->execute();
                            if (!$success) {
                                $error_message = $stmt->error;
                            }
                            $stmt->close();
                        }
                        break;
                        
                    case 'f': // Essay
                        $posted_answer = $conn->real_escape_string(trim(fixEscapeSequences($_POST['answer'] ?? '')));
                        $sql = "UPDATE essay SET question = ?, answer = ?, chapter_id = ?, topic_id = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("ssisi", $posted_question, $posted_answer, $chapter_id, $topic_id, $question_id);
                            $success = $stmt->execute();
                            if (!$success) {
                                $error_message = $stmt->error;
                            }
                            $stmt->close();
                        }
                        break;
                        
                    default:
                        $error_message = "Invalid question type for update.";
                        break;
                }
            } else {
                $error_message = "Invalid question ID for update.";
            }
        } else {
            // Insert logic based on question type
            switch($current_q_type_posted) {
                case 'a': // MCQ
                    $posted_option_a = $conn->real_escape_string(trim(fixEscapeSequences($_POST['optiona'])));
                    $posted_option_b = $conn->real_escape_string(trim(fixEscapeSequences($_POST['optionb'])));
                    $posted_option_c = $conn->real_escape_string(trim(fixEscapeSequences($_POST['optionc'])));
                    $posted_option_d = $conn->real_escape_string(trim(fixEscapeSequences($_POST['optiond'])));
                    $posted_answer = $conn->real_escape_string(trim($_POST['answer']));
                    
                    $sql = "INSERT INTO mcqdb (question, optiona, optionb, optionc, optiond, answer, chapter_id, topic_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssssssii", $posted_question, $posted_option_a, $posted_option_b,
                                        $posted_option_c, $posted_option_d, $posted_answer, $chapter_id, $topic_id);
                        $success = $stmt->execute();
                        if (!$success) {
                            $error_message = $stmt->error;
                        }
                        $stmt->close();
                    }
                    break;

                case 'b': // Numerical
                    $posted_answer = $conn->real_escape_string(trim(fixEscapeSequences($_POST['answer'])));
                    $sql = "INSERT INTO numericaldb (question, answer, chapter_id, topic_id) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssii", $posted_question, $posted_answer, $chapter_id, $topic_id);
                        $success = $stmt->execute();
                        if (!$success) {
                            $error_message = $stmt->error;
                        }
                        $stmt->close();
                    }
                    break;

                case 'c': // Dropdown
                    $posted_option = $conn->real_escape_string(trim(fixEscapeSequences($_POST['option'] ?? '')));
                    $posted_answer = $conn->real_escape_string(trim(fixEscapeSequences($_POST['answer'] ?? '')));
                    $sql = "INSERT INTO dropdown (question, options, answer, chapter_id, topic_id) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("sssii", $posted_question, $posted_option, $posted_answer, $chapter_id, $topic_id);
                        $success = $stmt->execute();
                        if (!$success) {
                            $error_message = $stmt->error;
                        }
                        $stmt->close();
                    }
                    break;
                    
                case 'd': // Fill in the blanks
                    $posted_option = $conn->real_escape_string(trim(fixEscapeSequences($_POST['option'] ?? '')));
                    $posted_answer = $conn->real_escape_string(trim(fixEscapeSequences($_POST['answer'] ?? '')));
                    $sql = "INSERT INTO fillintheblanks (question, options, answer, chapter_id, topic_id) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("sssii", $posted_question, $posted_option, $posted_answer, $chapter_id, $topic_id);
                        $success = $stmt->execute();
                        if (!$success) {
                            $error_message = $stmt->error;
                        }
                        $stmt->close();
                    }
                    break;
                    
                case 'e': // Short answer
                    $posted_answer = $conn->real_escape_string(trim(fixEscapeSequences($_POST['answer'] ?? '')));
                    $sql = "INSERT INTO shortanswer (question, answer, chapter_id, topic_id) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssii", $posted_question, $posted_answer, $chapter_id, $topic_id);
                        $success = $stmt->execute();
                        if (!$success) {
                            $error_message = $stmt->error;
                        }
                        $stmt->close();
                    }
                    break;
                    
                case 'f': // Essay
                    $posted_answer = $conn->real_escape_string(trim(fixEscapeSequences($_POST['answer'] ?? '')));
                    $sql = "INSERT INTO essay (question, answer, chapter_id, topic_id) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssii", $posted_question, $posted_answer, $chapter_id, $topic_id);
                        $success = $stmt->execute();
                        if (!$success) {
                            $error_message = $stmt->error;
                        }
                        $stmt->close();
                    }
                    break;

                // Add other question types here...
            }
        }

        // Store message in session and redirect
        if ($success) {
            $_SESSION['feedback_message'] = '<div class="alert alert-success">Question added successfully! You can add another question.</div>';
            $_SESSION['active_tab'] = $current_q_type_posted;
            header("Location: " . $_SERVER['PHP_SELF']);
            ob_end_clean(); // Clean output buffer
            exit();
        } else {
            $_SESSION['feedback_message'] = '<div class="alert alert-danger">Error: ' . $error_message . '</div>';
            $_SESSION['active_tab'] = $current_q_type_posted;
            header("Location: " . $_SERVER['PHP_SELF']);
            ob_end_clean(); // Clean output buffer
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['feedback_message'] = '<div class="alert alert-danger">An error occurred: ' . $e->getMessage() . '</div>';
        $_SESSION['active_tab'] = $current_q_type_posted;
        header("Location: " . $_SERVER['PHP_SELF']);
        ob_end_clean(); // Clean output buffer
        exit();
    }
}

// Get feedback message from session if exists
if (isset($_SESSION['feedback_message'])) {
    $feedback_message = $_SESSION['feedback_message'];
    unset($_SESSION['feedback_message']);
}

// Get active tab from session if exists
if (isset($_SESSION['active_tab'])) {
    $q_type_active = $_SESSION['active_tab'];
    unset($_SESSION['active_tab']);
}

// This part is for setting active tab based on POST (after submission) or GET (edit mode)
// The $q_type_active variable is already set correctly above based on GET or POST.
$active1 = ($q_type_active == "a") ? "active" : "";
$active2 = ($q_type_active == "b") ? "active" : "";
$active3 = ($q_type_active == "c") ? "active" : "";
$active4 = ($q_type_active == "d") ? "active" : "";
$active5 = ($q_type_active == "e") ? "active" : "";
$active6 = ($q_type_active == "f") ? "active" : "";

// If no specific type is active (e.g., initial load without GET/POST), default to 'a'
if(empty($active1) && empty($active2) && empty($active3) && empty($active4) && empty($active5) && empty($active6) && !$edit_mode) {
    $active1 = "active";
}

// Update the class and chapter queries
$class_query = "SELECT class_id, class_name FROM classes";
$class_result = mysqli_query($conn, $class_query);

$subject_query = "SELECT subject_id, subject_name FROM subjects";
$subject_result = mysqli_query($conn, $subject_query);

// Add this function for getting chapters
function getChapters($conn, $class_id, $subject_id) {
    $chapters_query = "SELECT chapter_id, chapter_name FROM chapters WHERE class_id = ? AND subject_id = ?";
    $stmt = mysqli_prepare($conn, $chapters_query);
    mysqli_stmt_bind_param($stmt, "ii", $class_id, $subject_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return $result;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <link rel="apple-touch-icon" sizes="76x76" href="./assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="./assets/img/favicon.png">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
  <title><?php echo $page_title; ?></title>
  <meta content='width=device-width, initial-scale=1.0, shrink-to-fit=no' name='viewport' />
  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="./assets/css/material-kit.css?v=2.0.4" rel="stylesheet" />
    <link href="./assets/css/modern.css" rel="stylesheet" />
    <link href="./assets/css/navbar.css" rel="stylesheet" />
    <link href="./assets/css/portal.css" rel="stylesheet" />
  <style>
    html, body {
        overflow-x: hidden; /* Prevent horizontal scroll at page level */
    }
    html {
        overflow-y: scroll; /* Ensures scrollbar is present if content overflows vertically */
    }
    body {
        position: relative; /* Good for some positioning contexts */
        background-color: #f5f5f5; /* Light background for better contrast */
    }
    
    /* Clean fix for z-index stacking */
    nav.navbar {
      z-index: 1050 !important; /* Higher than anything else */
    }

    .page-header {
      z-index: 1 !important; /* Lower than navbar but higher than default */
    }

    .page-header .container {
      position: relative !important;
      z-index: 3 !important; /* Higher than overlay */
    }

    .card-login {
      z-index: 4 !important; /* Highest in page-header */
    }

    /* Important fix for desktop navbar and header */
    @media (min-width: 992px) {
      body {
        padding-top: 60px; /* Account for fixed navbar height */
      }

      .page-header {
        min-height: 100vh;
        height: auto !important;
        display: flex !important;
        align-items: center;
        background-attachment: fixed;
      }
    }
    
    /* Decorative element for desktop */
    .page-header::before {
      display: none; /* Remove purple overlay */
    }
    
    /* Fix for header content visibility */
    .page-header .container {
      position: relative;
      z-index: 2; /* Higher than the overlay */
    }
    
    .page-header {
      position: relative;
      overflow: visible !important;
    }
    
    /* Enhanced card styling */
    .card-login {
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
      transition: all 0.3s ease;
      border-radius: 12px !important;
      overflow: hidden;
      position: relative;
      z-index: 3; /* Ensure card is above overlay */
    }
    
    @media (min-width: 992px) {
      .card-login:hover {
        box-shadow: 0 18px 35px rgba(0, 0, 0, 0.15), 0 8px 20px rgba(0, 0, 0, 0.1);
        transform: translateY(-5px);
      }
      
      /* Custom scroll styling for desktop */
      ::-webkit-scrollbar {
        width: 10px;
      }
      
      ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
      }
      
      ::-webkit-scrollbar-thumb {
        background: #9c27b0;
        border-radius: 10px;
      }
      
      ::-webkit-scrollbar-thumb:hover {
        background: #7b1fa2; 
      }
      
      /* Animated tab indicator */
      .nav-pills .nav-link.active:after {
        content: '';
        position: absolute;
        right: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 70%;
        background-color: #9c27b0;
        border-radius: 4px 0 0 4px;
        animation: fadeInRight 0.3s ease-in-out;
      }
      
      @keyframes fadeInRight {
        from {
          opacity: 0;
          transform: translate3d(20px, -50%, 0);
        }
        to {
          opacity: 1;
          transform: translate3d(0, -50%, 0);
        }
      }
    }
    
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

    /* Custom Responsive Styles */
    .tab-structure-row {
        padding: 20px;
    }
    .tab-content-pane {
        padding-right: 40px; /* Default for larger screens */
    }
    .form-submit-button {
        margin: 30px 30px 15px 30px; /* Default margins */
        min-width: 120px;
    }
    
    /* Card Responsive Styles */
    .card {
      border-radius: 8px;
      box-shadow: 0 4px 20px 0px rgba(0, 0, 0, 0.14), 0 7px 10px -5px rgba(156, 39, 176, 0.4);
      border: 0;
      margin-bottom: 30px;
      transition: all 0.3s ease;
    }
    
    .card-header {
      border-radius: 6px;
      margin: 0;
      padding: 15px;
    }
    
    .card-title {
      margin-top: 0;
      margin-bottom: 10px;
      color: #fff;
      font-weight: 500;
    }
    
    /* Form Element Improvements */
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-control {
      height: auto;
      padding: 12px 15px;
      font-size: 16px;
      line-height: 1.5;
      border-radius: 4px;
      border: 1px solid #ddd;
      box-shadow: none;
      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    textarea.form-control {
      min-height: 100px;
    }
    
    select.form-control {
      height: 45px;
      padding: 0 15px;
    }
    
    .form-check {
      margin-top: 15px;
      margin-bottom: 15px;
    }

    /* Desktop Specific Enhancements */
    @media (min-width: 992px) {
      .page-header {
        padding-top: 150px !important; /* Increased spacing below navbar */
        padding-bottom: 60px !important;
        min-height: 100vh !important; /* Ensure full height */
      }
      
      .card-login {
        max-width: 900px;
        margin: 0 auto;
        margin-top: -40px;
        margin-bottom: 60px;
      }
      
      .card-header {
        padding: 20px;
        margin: 0;
      }
      
      .card-title {
        font-size: 1.8rem;
        font-weight: 600;
      }
      
      .tab-structure-row {
        padding: 30px 40px;
      }
      
      .tab-content-pane {
        padding-right: 50px;
        padding-left: 20px;
        border-left: 1px solid rgba(0,0,0,0.05);
      }
      
      .nav-pills-rose .nav-link {
        white-space: nowrap;
        padding: 15px 25px !important;
        margin-bottom: 10px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
      }
      
      .nav-pills-rose .nav-link:not(.active):hover {
        background-color: rgba(156, 39, 176, 0.05);
      }
      
      .nav-pills-rose .nav-link.active {
        padding-left: 30px !important;
      }
      
      .form-group label {
        font-weight: 500;
        margin-bottom: 8px;
        color: #333;
      }
      
      .responsive-form-container {
        padding: 20px 30px;
      }
      
      /* Three-column layout for class/subject/chapter looks better with more space */
      .row.align-items-end .col-md-4 .form-group {
        margin-right: 15px;
      }
      
      .form-submit-button {
        padding: 12px 30px;
        font-size: 1rem;
        margin-top: 40px;
      }
      
      /* Enhanced styling for form controls on desktop */
      .form-control:focus {
        border-color: #9c27b0;
        box-shadow: 0 0 0 0.2rem rgba(156, 39, 176, 0.25);
      }
      
      /* Improvement for radio button groups spacing */
      .form-check-radio.form-check-inline {
        margin-right: 20px;
      }
      
      /* Tabbed content height consistency */
      .tab-content {
        min-height: 500px;
      }
      
      /* Enhanced desktop form styling */
      .form-control {
        font-size: 1rem;
        padding: 10px 15px;
        border-width: 1px;
        transition: all 0.3s ease;
      }
      
      .form-control:hover:not(:focus) {
        border-color: #aaa;
      }
      
      textarea.form-control {
        padding: 15px;
        line-height: 1.6;
        min-height: 120px;
      }
      
      /* Custom styling for select dropdowns */
      select.form-control {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%239c27b0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 16px;
        padding-right: 30px !important;
      }
      
      /* Elegant radio buttons */
      .form-check-radio .form-check-label .circle {
        border-width: 2px;
        transition: all 0.2s ease;
      }
      
      .form-check-radio .form-check-input:checked ~ .circle {
        border-color: #9c27b0;
      }
      
      .form-check-radio .form-check-input:checked ~ .circle .check {
        transform: scale(0.8);
        transition: all 0.2s ease;
      }
      
      /* Better form groups with animated labels */
      .form-group {
        position: relative;
        margin-bottom: 30px;
      }
      
      /* Animation for form fields */
      @keyframes formFieldFocus {
        0% { transform: translateY(0); }
        50% { transform: translateY(-2px); }
        100% { transform: translateY(0); }
      }
      
      .form-control:focus {
        animation: formFieldFocus 0.3s ease;
      }
      
      /* Button enhancements */
      .btn-primary.btn-round {
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
      }
      
      .btn-primary.btn-round:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
      }
      
      .btn-primary.btn-round:active {
        transform: translateY(-1px);
        box-shadow: 0 3px 6px rgba(0,0,0,0.2);
      }
    }
    
    @media (min-width: 1200px) {
      .container {
        max-width: 1280px;
      }
      
      .tab-structure-row {
        padding: 40px 50px;
      }
      
      .col-md-4 {
        flex: 0 0 30%;
        max-width: 30%;
      }
      
      .col-md-8.tab-content-pane {
        flex: 0 0 70%;
        max-width: 70%;
      }
    }

    @media (max-width: 991px) { /* Tablets and below */
        .tab-content-pane {
            padding-right: 15px;
        }
        
        .nav-pills-rose .nav-item {
          width: auto; /* Allow nav items to take natural width on tablets */
        }
        
        .card-login {
          margin-top: 60px; /* Adjust card position on tablets */
        }
    }

    @media (max-width: 767px) { /* Mobile devices */
      .tab-structure-row {
          padding: 10px;
      }
      
      .col-md-4, .col-md-8 { /* Ensure stacking on mobile for tab navigation and content */
          flex: 0 0 100%;
          max-width: 100%;
          overflow-y: visible; /* Rely on page scroll */
      }
      
      .tab-content-pane {
          padding-right: 0;
          padding-left: 0; /* Adjust if needed for symmetry */
          z-index: 1; /* Lower than tab nav */
      }
      
      .nav-pills-rose.flex-column {
        background-color: rgba(156, 39, 176, 0.1);
        border-radius: 6px;
        padding: 10px;
        margin-bottom: 20px;
      }
      
      .nav-pills-rose.flex-column .nav-item {
          width: 100%;
          margin-bottom: 5px;
      }
      
      .nav-pills-rose.flex-column .nav-link {
        white-space: nowrap;
          text-align: center;
          padding: 10px !important;
          border-radius: 4px;
      }
      
      .form-submit-button {
          margin: 20px auto 15px auto;
          display: block; /* For auto horizontal margins to work */
          width: calc(100% - 20px); /* Make button wider */
      }

      /* Prevent text overflow in form labels and ensure inputs/textareas fit */
      .form-group label,
      .form-check label,
      p.description, /* Targeting the specific description class */
      .alert,
      .h4.card-title /* Targeting the card title */
      {
          word-wrap: break-word; /* Fallback */
          overflow-wrap: break-word; /* Standard */
          -webkit-hyphens: auto;
          -ms-hyphens: auto;
          hyphens: auto; /* Allow hyphenation for very long words */
          white-space: normal !important; /* Force normal wrapping */
          display: block; /* Ensure labels take full width to allow wrapping */
      }

      input.form-control,
      textarea.form-control,
      select.form-control {
          width: 100% !important; /* Force full width */
          max-width: 100% !important; /* Ensure it doesn't exceed parent */
          box-sizing: border-box !important; /* Crucial */
          /* font-size: 14px; */ /* Optionally adjust font size if text is too large */
          height: auto; /* Adjust height automatically */
      }
      
      textarea.form-control {
          overflow-y: auto; /* Ensure scrollbar if content exceeds height */
      }

      .form-check-label {
          display: inline-flex; /* Helps align radio/checkbox with multi-line text */
          align-items: flex-start;
          word-break: break-word;
          overflow-wrap: break-word;
          white-space: normal;
          flex-wrap: wrap; /* Allow content within label to wrap */
      }
      
      .form-check-label input[type="radio"] {
          margin-top: 0.3em; /* Adjust alignment of radio button itself if text wraps */
      }

      /* Ensure tab navigation is not obscured */
      .tab-structure-row .col-md-4 { /* This is the tab navigation column */
          position: relative; 
          z-index: 10; /* Higher z-index for tab navigation */
          /* min-height: 50px; */ /* Removed to let content dictate height */
      }
      
      .tab-structure-row .col-md-8.tab-content-pane { /* This is the tab content column */
          position: relative; 
          z-index: 1; /* Lower z-index than tab navigation */
          overflow-x: hidden; /* Prevent horizontal scroll within tab content */
      }

      .card-login {
        overflow: visible; /* Ensure card content isn't clipped on mobile */
        margin-top: 0; /* Adjust if page-header has significant top padding */
        height: auto; /* Allow card to grow based on its content */
        max-height: none; /* Remove any max-height restriction */
      }

      .page-header { /* Main content container below navbar */
        height: auto !important; /* Allow it to grow with content */
        min-height: 0 !important; /* Override any vh-based min-height */
        padding-top: 100px !important; /* Extra space to prevent overlap */
        padding-bottom: 20px !important; /* Space before footer */
        overflow: visible !important; /* Ensure its content can overflow and make page scroll */
        display: block !important; /* Override flex if it's causing issues */
      }
      
      /* Improve form row layout on mobile */
      .row.align-items-end {
        margin-top: 15px !important;
      }
      
      .row.align-items-end .col-md-4 {
        margin-bottom: 15px;
      }
      
      /* Adjust radio button groups to stack better on mobile */
      .form-check-radio.form-check-inline {
        display: inline-block;
        width: auto;
        margin-right: 15px !important;
        margin-bottom: 10px !important;
      }
    }
    
    /* Additional styles for all screen sizes */
    .card-login .description {
      padding: 15px 20px 0;
    }
    
    .tab-pane {
      padding: 15px;
    }
    
    .nav-pills .nav-item .nav-link.active {
      background-color: #9c27b0;
      box-shadow: 0 5px 20px 0px rgba(0, 0, 0, 0.2), 0 13px 24px -11px rgba(156, 39, 176, 0.6);
      color: #fff !important;
      transition: all 0.3s ease;
    }
    
    /* Responsive form container style */
    .responsive-form-container {
      padding: 10px;
      width: 100%;
    }
    
    /* Desktop interaction styles */
    @media (min-width: 992px) {
      .nav-pills .nav-link {
        white-space: nowrap;
        transition: transform 0.2s ease;
      }
      
      .form-group.focused {
        background-color: rgba(156, 39, 176, 0.03);
        border-radius: 4px;
      }
      
      .form-check-radio.selected-option {
        background-color: rgba(156, 39, 176, 0.05);
        border-radius: 8px;
        padding: 5px;
        margin-left: -5px;
      }
      
      .form-control.has-value {
        border-color: #9c27b0;
        background-color: rgba(156, 39, 176, 0.02);
      }
      
      .form-submit-button {
        position: relative;
        overflow: hidden;
      }
      
      .form-submit-button:after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 5px;
        height: 5px;
        background: rgba(255, 255, 255, 0.4);
        opacity: 0;
        border-radius: 100%;
        transform: scale(1, 1) translate(-50%, -50%);
        transform-origin: 50% 50%;
      }
      
      .form-submit-button:hover:after {
        animation: ripple 0.6s ease-out;
      }
      
      @keyframes ripple {
        0% {
          transform: scale(0, 0);
          opacity: 0.5;
        }
        100% {
          transform: scale(20, 20);
          opacity: 0;
        }
      }
      
      /* Add a subtle effect to form row on hover */
      .row.align-items-end:hover {
        background-color: rgba(156, 39, 176, 0.02);
        border-radius: 8px;
      }
      
      /* Show a hint for required fields */
      .form-control[required] + label::after {
        content: '*';
        color: #9c27b0;
        margin-left: 4px;
      }
    }
    
    @media (max-width: 767px) {
      .responsive-form-container {
        padding: 5px;
      }
      
      .responsive-form-container .form-group {
        margin-bottom: 15px;
      }
    }
  </style>
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
            <a class="nav-link" rel="tooltip" title="" data-placement="bottom" href="instructorlogout.php" data-original-title="Get back to Login Page">
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
      <div class="container" style="padding-top: 20px;">
      <div class="row" style="margin-bottom: 50px; position: relative; z-index: 2;">
        <div class="col-lg-10 col-md-10 ml-auto mr-auto">
          <div class="card card-login">
              <div class="card-header card-header-primary text-center">
                <h4 class="card-title"><?php echo htmlspecialchars($page_title); ?></h4>
              </div>
              <p class="description text-center">Select the type of question, input the required fields and then press <?php echo strtolower($submit_button_text); ?>.</p>
              <?php if (!empty($feedback_message)) echo $feedback_message; // Display feedback message here ?>
              <div class="row tab-structure-row">
                <div class="col-md-4">
                  <ul class="nav nav-pills nav-pills-rose flex-column">
                    <li class="nav-item"><a class="nav-link <?php echo $active1;?>" href="#tab1" data-toggle="tab" data-qtype="a">MCQ Questions</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $active2;?>" href="#tab2" data-toggle="tab" data-qtype="b">Numerical Type</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $active3;?>" href="#tab3" data-toggle="tab" data-qtype="c">Drop Down</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $active4;?>" href="#tab4" data-toggle="tab" data-qtype="d">Fill in the blank</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $active5;?>" href="#tab5" data-toggle="tab" data-qtype="e">Short Answer Type</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $active6;?>" href="#tab6" data-toggle="tab" data-qtype="f">Essay Type</a></li>
                  </ul>
                </div>
                <div class="col-md-8 tab-content-pane">
                  <div class="tab-content">
                      <div class="tab-pane <?php echo $active1;?>" id="tab1">
                        <form name="typea" action="<?php echo $form_action; ?>" method="post">
                          <div class="responsive-form-container">
                            <?php echo $hidden_action_field; ?>
                            <input type="hidden" name="type" value="a"/>
                            <div class="form-group">
                              <label >Question</label>
                              <textarea name="question" class="form-control" rows="3" required><?php echo htmlspecialchars($question_text); ?></textarea>
                            </div>
                            <div class="form-group" style="padding:0px ">
                              <input class="form-control" type="text" name="optiona" placeholder="Option A" value="<?php echo htmlspecialchars($mcq_option_a); ?>" required>
                            </div>
                            <div class="form-group" style="padding:0px ">
                              <input class="form-control" type="text" name="optionb" placeholder="Option B" value="<?php echo htmlspecialchars($mcq_option_b); ?>" required>
                            </div>
                            <div class="form-group" style="padding:0px ">
                              <input class="form-control" type="text" name="optionc" placeholder="Option C" value="<?php echo htmlspecialchars($mcq_option_c); ?>" required>
                            </div>
                            <div class="form-group" style="padding:0px ">
                              <input class="form-control" type="text" name="optiond" placeholder="Option D" value="<?php echo htmlspecialchars($mcq_option_d); ?>" required>
                            </div>
                            Correct Option
                            <div class="form-check form-check-radio form-check-inline" style="margin: 0px;padding: 0px">
                              <label class="form-check-label">
                                <input class="form-check-input position-static" type="radio" name="answer" value="A" <?php if ($mcq_answer == 'A') echo 'checked'; ?>> A
                                <span class="circle"><span class="check"></span></span>
                              </label>
                            </div>
                            <div class="form-check form-check-radio form-check-inline" style="margin: 0px;padding: 0px">
                              <label class="form-check-label">
                                <input class="form-check-input position-static" type="radio" name="answer" value="B" <?php if ($mcq_answer == 'B') echo 'checked'; ?>> B
                                <span class="circle"><span class="check"></span></span>
                              </label>
                            </div>
                            <div class="form-check form-check-radio form-check-inline" style="margin: 0px;padding: 0px">
                              <label class="form-check-label">
                                <input class="form-check-input position-static" type="radio" name="answer" value="C" <?php if ($mcq_answer == 'C') echo 'checked'; ?>> C
                                <span class="circle"><span class="check"></span></span>
                              </label>
                            </div>
                            <div class="form-check form-check-radio form-check-inline" style="margin: 0px;padding: 0px">
                              <label class="form-check-label">
                                <input class="form-check-input position-static" type="radio" name="answer" value="D" <?php if ($mcq_answer == 'D') echo 'checked'; ?>> D
                                <span class="circle"><span class="check"></span></span>
                              </label>
                            </div>

                            <!-- Group Class, Chapter, and Submit Button in a row -->
                            <div class="row align-items-end mt-3"> <!-- mt-3 for a little margin-top -->
                              <div class="col-md-4">
                                  <div class="form-group mb-0"> <!-- mb-0 to reduce bottom margin if not needed in a row -->
                                      <label for="class_id_mcq">Class</label>
                                      <select name="class_id" id="class_id_mcq" class="form-control" onchange="loadQuestionFeedChapters('mcq')" required>
                                          <option value="">Select Class</option>
                                          <?php foreach ($class_options as $class): ?>
                                              <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                                  <?php echo htmlspecialchars($class['name']); ?>
                                              </option>
                                          <?php endforeach; ?>
                                      </select>
                                  </div>
                              </div>
                              <div class="col-md-4">
                                  <div class="form-group mb-0">
                                      <label for="subject_id_mcq">Subject</label>
                                      <select name="subject_id" id="subject_id_mcq" class="form-control" onchange="loadQuestionFeedChapters('mcq')" required>
                                          <option value="">Select Subject</option>
                                          <?php foreach ($subject_options as $subject): ?>
                                              <option value="<?php echo htmlspecialchars($subject['id']); ?>">
                                                  <?php echo htmlspecialchars($subject['name']); ?>
                                              </option>
                                          <?php endforeach; ?>
                                      </select>
                                  </div>
                              </div>
                              <div class="col-md-4">
                                  <div class="form-group mb-0">
                                      <label for="chapter_id_mcq">Chapter</label>
                                        <select name="chapter_id" id="chapter_id_mcq" class="form-control" onchange="loadQuestionFeedTopics('mcq')" required>
                                          <option value="">Select Chapter</option>
                                          <!-- Chapters will be loaded dynamically -->
                                          <?php if ($edit_mode && isset($chapter_id)): ?>
                                          <option value="<?php echo htmlspecialchars($chapter_id); ?>" selected>Current Chapter</option>
                                          <?php endif; ?>
                                      </select>
                                  </div>
                              </div>
                            </div>
                            <div class="row mt-2">
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="topic_id_mcq">Topic (Optional)</label>
                                  <select name="topic_id" id="topic_id_mcq" class="form-control">
                                    <option value="">Select Topic</option>
                                    <?php if ($edit_mode && isset($topic_id)): ?>
                                    <option value="<?php echo htmlspecialchars($topic_id); ?>" selected>Current Topic</option>
                                    <?php endif; ?>
                                  </select>
                                </div>
                              </div>
                            </div>
                            <div class="text-center mt-3">
                                <button type="submit" class="btn btn-primary btn-round form-submit-button"><?php echo $submit_button_text; ?></button>
                            </div>
                          </div>
                        </form>
                      </div>
                      <div class="tab-pane <?php echo $active2;?>" id="tab2">
                        <form name="typeb" action="<?php echo $form_action; ?>" method="post">
                          <div class="responsive-form-container">
                            <?php echo $hidden_action_field; ?>
                            <input type="hidden" name="type" value="b"/>
                            <div class="form-group">
                              <label >Question</label>
                              <textarea name="question" class="form-control" rows="5" required><?php echo htmlspecialchars($question_text); ?></textarea>
                            </div>
                            <div class="form-group">
                              <label >Answer</label>
                              <input type="text" name="answer" class="form-control" value="<?php echo htmlspecialchars($numerical_answer); ?>" required>
                            </div>
                            
                            <!-- Group Class, Subject, and Chapter in a row -->
                            <div class="row align-items-end mt-3">
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="class_id_num">Class</label>
                                  <select name="class_id" id="class_id_num" class="form-control" onchange="loadQuestionFeedChapters('num')" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($class_options as $class): ?>
                                      <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                        <?php echo htmlspecialchars($class['name']); ?>
                                      </option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>
                              </div>
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="subject_id_num">Subject</label>
                                  <select name="subject_id" id="subject_id_num" class="form-control" onchange="loadQuestionFeedChapters('num')" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subject_options as $subject): ?>
                                      <option value="<?php echo htmlspecialchars($subject['id']); ?>">
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                      </option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>
                              </div>
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="chapter_id_num">Chapter</label>
                                  <select name="chapter_id" id="chapter_id_num" class="form-control" onchange="loadQuestionFeedTopics('num')" required>
                                    <option value="">Select Chapter</option>
                                    <!-- Chapters will be loaded dynamically -->
                                    <?php if ($edit_mode && isset($chapter_id)): ?>
                                      <option value="<?php echo htmlspecialchars($chapter_id); ?>" selected>Current Chapter</option>
                                    <?php endif; ?>
                                  </select>
                                </div>
                              </div>
                            </div>
                            <div class="row mt-2">
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="topic_id_num">Topic (Optional)</label>
                                  <select name="topic_id" id="topic_id_num" class="form-control">
                                    <option value="">Select Topic</option>
                                    <?php if ($edit_mode && isset($topic_id)): ?>
                                    <option value="<?php echo htmlspecialchars($topic_id); ?>" selected>Current Topic</option>
                                    <?php endif; ?>
                                  </select>
                                </div>
                              </div>
                            </div>
                            <div class="text-center mt-3">
                              <button type="submit" class="btn btn-primary btn-round form-submit-button"><?php echo $submit_button_text; ?></button>
                            </div>
                          </div>
                        </form>
                      </div>
                      <div class="tab-pane <?php echo $active3;?>" id="tab3">
                        <form name="typec" action="<?php echo $form_action; ?>" method="post">
                          <div class="responsive-form-container">
                            <?php echo $hidden_action_field; ?>
                            <input type="hidden" name="type" value="c"/>
                            <div class="form-group">
                              <label>Question</label>
                              <textarea name="question" class="form-control" rows="5" required><?php echo htmlspecialchars($question_text); ?></textarea>
                            </div>
                            <div class="form-group">
                              <label>Options (Comma separated)</label>
                              <textarea name="option" class="form-control" rows="2" required><?php echo htmlspecialchars($dropdown_options); ?></textarea>
                            </div>
                            <div class="form-group">
                              <label>Answer (Serial of correct option, starting with 1)</label>
                              <input type="number" min="1" name="answer" class="form-control" value="<?php echo htmlspecialchars($dropdown_answer_serial); ?>" required>
                            </div>
                            
                            <!-- Group Class, Subject, and Chapter in a row -->
                            <div class="row align-items-end mt-3">
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="class_id_c">Class</label>
                                  <select name="class_id" id="class_id_c" class="form-control" onchange="loadQuestionFeedChapters('c')" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($class_options as $class): ?>
                                      <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                        <?php echo htmlspecialchars($class['name']); ?>
                                      </option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>
                              </div>
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="subject_id_c">Subject</label>
                                  <select name="subject_id" id="subject_id_c" class="form-control" onchange="loadQuestionFeedChapters('c')" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subject_options as $subject): ?>
                                      <option value="<?php echo htmlspecialchars($subject['id']); ?>">
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                      </option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>
                              </div>
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="chapter_id_c">Chapter</label>
                                  <select name="chapter_id" id="chapter_id_c" class="form-control" onchange="loadQuestionFeedTopics('c')" required>
                                    <option value="">Select Chapter</option>
                                    <!-- Chapters will be loaded dynamically -->
                                    <?php if ($edit_mode && isset($chapter_id)): ?>
                                      <option value="<?php echo htmlspecialchars($chapter_id); ?>" selected>Current Chapter</option>
                                    <?php endif; ?>
                                  </select>
                                </div>
                              </div>
                            </div>
                            <div class="row mt-2">
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="topic_id_c">Topic (Optional)</label>
                                  <select name="topic_id" id="topic_id_c" class="form-control">
                                    <option value="">Select Topic</option>
                                    <?php if ($edit_mode && isset($topic_id)): ?>
                                    <option value="<?php echo htmlspecialchars($topic_id); ?>" selected>Current Topic</option>
                                    <?php endif; ?>
                                  </select>
                                </div>
                              </div>
                            </div>
                            <div class="text-center mt-3">
                              <button type="submit" class="btn btn-primary btn-round form-submit-button"><?php echo $submit_button_text; ?></button>
                            </div>
                          </div>
                        </form>
                      </div>
                      <div class="tab-pane <?php echo $active4;?>" id="tab4">
                        <form name="typed" action="<?php echo $form_action; ?>" method="post">
                          <div class="responsive-form-container">
                            <?php echo $hidden_action_field; ?>
                            <input type="hidden" name="type" value="d"/>
                            <div class="form-group">
                              <label>Question (Use ___ where the blank should be)</label>
                              <textarea name="question" class="form-control" rows="5" required><?php echo htmlspecialchars($question_text); ?></textarea>
                            </div>
                            <div class="form-group">
                              <label>Options (Comma separated list of possible answers)</label>
                              <textarea name="option" class="form-control" rows="2"><?php echo htmlspecialchars($fill_options); ?></textarea>
                            </div>
                            <div class="form-group">
                              <label>Correct Answer</label>
                              <input type="text" name="answer" class="form-control" value="<?php echo htmlspecialchars($fill_answer); ?>" required>
                            </div>
                            
                            <!-- Group Class, Subject, and Chapter in a row -->
                            <div class="row align-items-end mt-3">
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="class_id_d">Class</label>
                                  <select name="class_id" id="class_id_d" class="form-control" onchange="loadQuestionFeedChapters('d')" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($class_options as $class): ?>
                                      <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                        <?php echo htmlspecialchars($class['name']); ?>
                                      </option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>
                              </div>
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="subject_id_d">Subject</label>
                                  <select name="subject_id" id="subject_id_d" class="form-control" onchange="loadQuestionFeedChapters('d')" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subject_options as $subject): ?>
                                      <option value="<?php echo htmlspecialchars($subject['id']); ?>">
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                      </option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>
                              </div>
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="chapter_id_d">Chapter</label>
                                  <select name="chapter_id" id="chapter_id_d" class="form-control" onchange="loadQuestionFeedTopics('d')" required>
                                    <option value="">Select Chapter</option>
                                    <!-- Chapters will be loaded dynamically -->
                                    <?php if ($edit_mode && isset($chapter_id)): ?>
                                      <option value="<?php echo htmlspecialchars($chapter_id); ?>" selected>Current Chapter</option>
                                    <?php endif; ?>
                                  </select>
                                </div>
                              </div>
                            </div>
                            <div class="row mt-2">
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="topic_id_d">Topic (Optional)</label>
                                  <select name="topic_id" id="topic_id_d" class="form-control">
                                    <option value="">Select Topic</option>
                                    <?php if ($edit_mode && isset($topic_id)): ?>
                                    <option value="<?php echo htmlspecialchars($topic_id); ?>" selected>Current Topic</option>
                                    <?php endif; ?>
                                  </select>
                                </div>
                              </div>
                            </div>
                            <div class="text-center mt-3">
                              <button type="submit" class="btn btn-primary btn-round form-submit-button"><?php echo $submit_button_text; ?></button>
                            </div>
                          </div>
                        </form>
                      </div>
                      <div class="tab-pane <?php echo $active5;?>" id="tab5">
                        <form name="typee" action="<?php echo $form_action; ?>" method="post">
                          <div class="responsive-form-container">
                            <?php echo $hidden_action_field; ?>
                            <input type="hidden" name="type" value="e"/>
                            <div class="form-group">
                              <label>Question</label>
                              <textarea name="question" class="form-control" rows="5" required><?php echo htmlspecialchars($question_text); ?></textarea>
                            </div>
                            <div class="form-group">
                              <label>Answer / Keywords (Comma separated)</label>
                              <textarea name="answer" class="form-control" rows="3" required><?php echo htmlspecialchars($short_answer_keywords); ?></textarea>
                            </div>
                            
                            <!-- Group Class, Subject, and Chapter in a row -->
                            <div class="row align-items-end mt-3">
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="class_id_e">Class</label>
                                  <select name="class_id" id="class_id_e" class="form-control" onchange="loadQuestionFeedChapters('e')" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($class_options as $class): ?>
                                      <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                        <?php echo htmlspecialchars($class['name']); ?>
                                      </option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>
                              </div>
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="subject_id_e">Subject</label>
                                  <select name="subject_id" id="subject_id_e" class="form-control" onchange="loadQuestionFeedChapters('e')" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subject_options as $subject): ?>
                                      <option value="<?php echo htmlspecialchars($subject['id']); ?>">
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                      </option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>
                              </div>
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="chapter_id_e">Chapter</label>
                                  <select name="chapter_id" id="chapter_id_e" class="form-control" onchange="loadQuestionFeedTopics('e')" required>
                                    <option value="">Select Chapter</option>
                                    <!-- Chapters will be loaded dynamically -->
                                    <?php if ($edit_mode && isset($chapter_id)): ?>
                                      <option value="<?php echo htmlspecialchars($chapter_id); ?>" selected>Current Chapter</option>
                                    <?php endif; ?>
                                  </select>
                                </div>
                              </div>
                            </div>
                            <div class="row mt-2">
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="topic_id_e">Topic (Optional)</label>
                                  <select name="topic_id" id="topic_id_e" class="form-control">
                                    <option value="">Select Topic</option>
                                    <?php if ($edit_mode && isset($topic_id)): ?>
                                    <option value="<?php echo htmlspecialchars($topic_id); ?>" selected>Current Topic</option>
                                    <?php endif; ?>
                                  </select>
                                </div>
                              </div>
                            </div>
                            <div class="text-center mt-3">
                              <button type="submit" class="btn btn-primary btn-round form-submit-button"><?php echo $submit_button_text; ?></button>
                            </div>
                          </div>
                        </form>
                      </div>
                      <div class="tab-pane <?php echo $active6;?>" id="tab6">
                        <form name="typef" action="<?php echo $form_action; ?>" method="post">
                          <div class="responsive-form-container">
                            <?php echo $hidden_action_field; ?>
                            <input type="hidden" name="type" value="f"/>
                            <div class="form-group">
                              <label>Question</label>
                              <textarea name="question" class="form-control" rows="5" required><?php echo htmlspecialchars($question_text); ?></textarea>
                            </div>
                            <div class="form-group">
                              <label>Answer (Keywords and guidelines for grading)</label>
                              <textarea name="answer" class="form-control" rows="5" required><?php echo htmlspecialchars($essay_answer_keywords); ?></textarea>
                            </div>
                            
                            <!-- Group Class, Subject, and Chapter in a row -->
                            <div class="row align-items-end mt-3">
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="class_id_f">Class</label>
                                  <select name="class_id" id="class_id_f" class="form-control" onchange="loadQuestionFeedChapters('f')" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($class_options as $class): ?>
                                      <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                        <?php echo htmlspecialchars($class['name']); ?>
                                      </option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>
                              </div>
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="subject_id_f">Subject</label>
                                  <select name="subject_id" id="subject_id_f" class="form-control" onchange="loadQuestionFeedChapters('f')" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subject_options as $subject): ?>
                                      <option value="<?php echo htmlspecialchars($subject['id']); ?>">
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                      </option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>
                              </div>
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="chapter_id_f">Chapter</label>
                                  <select name="chapter_id" id="chapter_id_f" class="form-control" onchange="loadQuestionFeedTopics('f')" required>
                                    <option value="">Select Chapter</option>
                                    <!-- Chapters will be loaded dynamically -->
                                    <?php if ($edit_mode && isset($chapter_id)): ?>
                                      <option value="<?php echo htmlspecialchars($chapter_id); ?>" selected>Current Chapter</option>
                                    <?php endif; ?>
                                  </select>
                                </div>
                              </div>
                            </div>
                            <div class="row mt-2">
                              <div class="col-md-4">
                                <div class="form-group mb-0">
                                  <label for="topic_id_f">Topic (Optional)</label>
                                  <select name="topic_id" id="topic_id_f" class="form-control">
                                    <option value="">Select Topic</option>
                                    <?php if ($edit_mode && isset($topic_id)): ?>
                                    <option value="<?php echo htmlspecialchars($topic_id); ?>" selected>Current Topic</option>
                                    <?php endif; ?>
                                  </select>
                                </div>
                              </div>
                            </div>
                            <div class="text-center mt-3">
                              <button type="submit" class="btn btn-primary btn-round form-submit-button"><?php echo $submit_button_text; ?></button>
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
  <script src="./assets/js/material-kit.js?v=2.0.4" type="text/javascript"></script>
  <?php echo $js_for_chapters; ?>
  
  <!-- Combined tab navigation and form handling script -->
  <script>
    $(document).ready(function() {
      // Handle tab click on mobile - scroll to content
      $('.nav-pills .nav-link').on('click', function() {
        if (window.innerWidth < 768) {
          setTimeout(function() {
            // Get active tab pane
            var activeTab = $('.tab-pane.active');
            
            // Scroll to it with a small delay to ensure tab has become active
            if (activeTab.length) {
              $('html, body').animate({
                scrollTop: activeTab.offset().top - 100 // Adjust offset to account for fixed navbar
              }, 300);
            }
          }, 300);
        }
      });
      
      // Adjust form layout on smaller screens
      function adjustFormLayout() {
        if (window.innerWidth < 768) {
          // Add responsive classes to appropriate elements
          $('.row.align-items-end').addClass('mobile-row');
          $('.form-submit-button').addClass('mobile-button');
        } else {
          // Remove responsive classes when screen is larger
          $('.row.align-items-end').removeClass('mobile-row');
          $('.form-submit-button').removeClass('mobile-button');
        }
        
        // Desktop enhancements
        if (window.innerWidth >= 992) {
          // Add subtle hover effect to tab navigation items for desktop
          $('.nav-pills .nav-link').hover(
            function() {
              if (!$(this).hasClass('active')) {
                $(this).css('transform', 'translateX(5px)');
              }
            },
            function() {
              if (!$(this).hasClass('active')) {
                $(this).css('transform', 'translateX(0)');
              }
            }
          );
          
          // Add smooth transitions for tab content
          $('.tab-pane').css('transition', 'opacity 0.3s ease');
          
          // Add focus and blur effects for form inputs
          $('.form-control').focus(function() {
            $(this).closest('.form-group').addClass('focused');
          }).blur(function() {
            $(this).closest('.form-group').removeClass('focused');
          });
          
          // Enhance radio button interaction
          $('.form-check-input[type="radio"]').change(function() {
            // Highlight the selected option
            $('.form-check-radio').removeClass('selected-option');
            $(this).closest('.form-check-radio').addClass('selected-option');
          });
        }
      }
      
      // Run on page load and window resize
      adjustFormLayout();
      $(window).on('resize', adjustFormLayout);
      
      // Add a CSS class to the body for detecting desktop vs mobile styling
      function updateViewportClass() {
        $('body').removeClass('is-desktop is-tablet is-mobile');
        if (window.innerWidth >= 992) {
          $('body').addClass('is-desktop');
        } else if (window.innerWidth >= 768) {
          $('body').addClass('is-tablet');
        } else {
          $('body').addClass('is-mobile');
        }
      }
      
      // Run on load and resize
      updateViewportClass();
      $(window).on('resize', updateViewportClass);
      
      // When a tab link is clicked
      $('.nav-pills-rose a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var qtype = $(e.target).data('qtype'); // Get qtype from data-qtype attribute
        
        // Ensure correct chapters are loaded if class/subject already selected for the new active tab
        if (typeof loadQuestionFeedChapters === 'function') {
          var activeTabQType = $(e.target).attr('data-qtype');
          if (activeTabQType) {
            var typeForJS = (activeTabQType === 'a') ? 'mcq' : 
                           (activeTabQType === 'b') ? 'num' : activeTabQType;
            var classDropdown = $('#class_id_' + typeForJS);
            var subjectDropdown = $('#subject_id_' + typeForJS);

            if(classDropdown.length && subjectDropdown.length && classDropdown.val() && subjectDropdown.val()){
              loadQuestionFeedChapters(typeForJS);
            }
          }
        }
        
        // For desktop: Add animation for tab content change
        if (window.innerWidth >= 992) {
          $(e.target.hash).css({opacity: 0}).animate({opacity: 1}, 300);
        }
      });

      <?php if ($edit_mode && !empty($q_type_active)): ?>
      // Ensure the correct tab is shown if in edit mode
      // q_type_active can be 'a', 'b', 'c', 'd', 'e', 'f'
      var activeTabSelector = '.nav-pills-rose a[data-qtype="<?php echo $q_type_active; ?>"]';
      $(activeTabSelector).tab('show');
      
      // Also attempt to load chapters if in edit mode and class/subject are pre-filled
      setTimeout(function() {
        var typeForJSOnLoad = ('<?php echo $q_type_active; ?>' === 'a') ? 'mcq' : 
                             ('<?php echo $q_type_active; ?>' === 'b') ? 'num' : '<?php echo $q_type_active; ?>';
        var classDropdown = $('#class_id_' + typeForJSOnLoad);
        var subjectDropdown = $('#subject_id_' + typeForJSOnLoad);
        var chapterDropdown = $('#chapter_id_' + typeForJSOnLoad);
        var topicDropdown = $('#topic_id_' + typeForJSOnLoad);
        var chapterValue = '<?php echo isset($chapter_id) ? htmlspecialchars($chapter_id) : ""; ?>';
        var topicValue = '<?php echo isset($topic_id) ? htmlspecialchars($topic_id) : ""; ?>';

        if (chapterValue) {
          <?php if ($edit_mode && isset($chapter_id) && $chapter_id > 0): ?>
          // Fetch chapter details to get class and subject
          $.ajax({
            url: 'get_chapter_details.php',
            method: 'GET',
            data: { chapter_id: <?php echo $chapter_id; ?> },
            dataType: 'json',
            success: function(response) {
              if (response && response.class_id && response.subject_id) {
                // Set the dropdowns to the right values
                classDropdown.val(response.class_id);
                subjectDropdown.val(response.subject_id);
                
                // Now load chapters with these values
                loadQuestionFeedChapters(typeForJSOnLoad);
                
                // After chapters load, set the selected chapter and topics
                setTimeout(function() {
                  chapterDropdown.val(chapterValue);
                  loadQuestionFeedTopics(typeForJSOnLoad, topicValue);
                }, 500);
              }
            },
            error: function(xhr, status, error) {
              console.error('Error fetching chapter details:', error);
            }
          });
          <?php endif; ?>
        }
        else if(classDropdown.length && subjectDropdown.length && classDropdown.val() && subjectDropdown.val()){
          if (typeof loadQuestionFeedChapters === 'function') {
            // Temporarily store the chapter to be selected
            chapterDropdown.data('selected-chapter', chapterValue);
            loadQuestionFeedChapters(typeForJSOnLoad);

            // Add a slight delay for the AJAX to complete and then select the chapter
            setTimeout(function(){
              var selectedChapter = chapterDropdown.data('selected-chapter');
              if(selectedChapter){
                chapterDropdown.val(selectedChapter);
                loadQuestionFeedTopics(typeForJSOnLoad, topicValue);
              }
            }, 500);
          }
        }
      }, 100); // Delay to ensure tab is shown and elements are ready
      <?php endif; ?>
      
      // Desktop-only form validation enhancement
      if (window.innerWidth >= 992) {
        // Add custom validation styles
        $('.form-control').on('input', function() {
          if ($(this).val().trim() !== '') {
            $(this).addClass('has-value');
          } else {
            $(this).removeClass('has-value');
          }
        });
        
        // Initialize the state for existing values
        $('.form-control').each(function() {
          if ($(this).val().trim() !== '') {
            $(this).addClass('has-value');
          }
        });
      }
    });
  </script>
<script src="./assets/js/dark-mode.js"></script>
</body>
</html>
