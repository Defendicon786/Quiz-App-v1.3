<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON before any output
header('Content-Type: application/json');

// Include database connection
include "database.php";

// Log received parameters
error_log("GET_CHAPTERS: Request received with method: " . $_SERVER['REQUEST_METHOD']);
error_log("GET_CHAPTERS: Received class_id: " . (isset($_GET['class_id']) ? $_GET['class_id'] : 'not set'));
error_log("GET_CHAPTERS: Received subject_id: " . (isset($_GET['subject_id']) ? $_GET['subject_id'] : 'not set'));

// Get class_id and subject_id from GET/POST parameters
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

// Log parsed values
error_log("GET_CHAPTERS: Parsed class_id: " . $class_id);
error_log("GET_CHAPTERS: Parsed subject_id: " . $subject_id);

// Validate input
if ($class_id <= 0 || $subject_id <= 0) {
    echo json_encode(['error' => 'Invalid class_id or subject_id', 'class_id' => $class_id, 'subject_id' => $subject_id]);
    exit;
}

try {
    // Log connection status
    error_log("GET_CHAPTERS: Database connection state: " . ($conn->connect_error ? "Failed: " . $conn->connect_error : "Connected"));
    
    // Check if chapters table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'chapters'");
    error_log("GET_CHAPTERS: 'chapters' table exists: " . ($table_check->num_rows > 0 ? "Yes" : "No"));
    
    // Check if chapter_number column exists
    $column_check = $conn->query("SHOW COLUMNS FROM chapters LIKE 'chapter_number'");
    $chapter_number_exists = $column_check->num_rows > 0;
    error_log("GET_CHAPTERS: 'chapter_number' column exists: " . ($chapter_number_exists ? "Yes" : "No"));
    
    // Log SQL query for debugging
    $sql = "SELECT chapter_id, chapter_name FROM chapters WHERE class_id = ? AND subject_id = ? ORDER BY " . 
           ($chapter_number_exists ? "chapter_number" : "chapter_name") . " ASC";
    error_log("GET_CHAPTERS: SQL Query: " . $sql);
    error_log("GET_CHAPTERS: Parameters: class_id = $class_id, subject_id = $subject_id");
    
    // Check for records with these parameters
    $check_sql = "SELECT COUNT(*) as count FROM chapters WHERE class_id = $class_id AND subject_id = $subject_id";
    $check_result = $conn->query($check_sql);
    $check_row = $check_result->fetch_assoc();
    error_log("GET_CHAPTERS: Number of matching chapters in database: " . $check_row['count']);

    // Prepare SQL statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $class_id, $subject_id);
    
    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    // Get results
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Get result failed: " . $stmt->error);
    }
    
    // Fetch all chapters
    $chapters = [];
    while ($row = $result->fetch_assoc()) {
        $chapters[] = [
            'chapter_id' => $row['chapter_id'],
            'chapter_name' => $row['chapter_name']
        ];
    }
    
    // Log number of chapters found
    error_log("GET_CHAPTERS: Found " . count($chapters) . " chapters");
    
    // Return JSON response
    echo json_encode($chapters);
    
} catch (Exception $e) {
    // Log the error
    error_log("GET_CHAPTERS ERROR: " . $e->getMessage());
    
    // Return error message in JSON
    echo json_encode(['error' => $e->getMessage()]);
}

// Close statement and connection
if (isset($stmt)) {
    $stmt->close();
}
if (isset($conn)) {
    $conn->close();
}
?> 