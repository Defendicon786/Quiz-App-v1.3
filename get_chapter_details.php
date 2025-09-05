<?php
header('Content-Type: application/json');

// Include database connection
include "database.php";

// Get chapter_id from request
$chapter_id = isset($_GET['chapter_id']) ? intval($_GET['chapter_id']) : 0;

// Check if chapter_id is valid
if ($chapter_id <= 0) {
    echo json_encode(['error' => 'Invalid chapter ID']);
    exit;
}

// Fetch chapter details
$sql = "SELECT class_id, subject_id FROM chapters WHERE chapter_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $chapter_id);
    $stmt->execute();
    $stmt->bind_result($class_id, $subject_id);
    
    if ($stmt->fetch()) {
        // Return class and subject IDs
        echo json_encode([
            'chapter_id' => $chapter_id,
            'class_id' => $class_id,
            'subject_id' => $subject_id
        ]);
    } else {
        echo json_encode(['error' => 'Chapter not found']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}

// Close the connection
$conn->close();
?> 