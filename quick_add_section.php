<?php
session_start();
// Ensure instructor is logged in
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');
include "database.php"; // Database connection

// Check if we have the required data
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'quick_add_section') {
    if (empty($_POST['class_id']) || empty($_POST['section_name'])) {
        echo json_encode(['success' => false, 'message' => 'Class ID and Section Name are required']);
        exit;
    }
    
    $class_id = intval($_POST['class_id']);
    $section_name = trim($conn->real_escape_string($_POST['section_name']));
    
    // Check if the class exists
    $check_class_sql = "SELECT class_id FROM classes WHERE class_id = ?";
    $check_class_stmt = $conn->prepare($check_class_sql);
    $check_class_stmt->bind_param("i", $class_id);
    $check_class_stmt->execute();
    $check_class_result = $check_class_stmt->get_result();
    
    if ($check_class_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Class does not exist']);
        $check_class_stmt->close();
        exit;
    }
    $check_class_stmt->close();
    
    // Check if section already exists for this class
    $check_section_sql = "SELECT id FROM class_sections WHERE class_id = ? AND section_name = ?";
    $check_section_stmt = $conn->prepare($check_section_sql);
    $check_section_stmt->bind_param("is", $class_id, $section_name);
    $check_section_stmt->execute();
    $check_section_result = $check_section_stmt->get_result();
    
    if ($check_section_result->num_rows > 0) {
        // Section already exists
        $row = $check_section_result->fetch_assoc();
        $section_id = $row['id'];
        echo json_encode([
            'success' => true, 
            'message' => 'Section "' . htmlspecialchars($section_name) . '" already exists',
            'section_id' => $section_id,
            'existed' => true
        ]);
        $check_section_stmt->close();
        exit;
    }
    $check_section_stmt->close();
    
    // Insert the new section
    $insert_sql = "INSERT INTO class_sections (class_id, section_name) VALUES (?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("is", $class_id, $section_name);
    
    if ($insert_stmt->execute()) {
        $section_id = $insert_stmt->insert_id;
        echo json_encode([
            'success' => true, 
            'message' => 'Section "' . htmlspecialchars($section_name) . '" added successfully',
            'section_id' => $section_id,
            'existed' => false
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding section: ' . $insert_stmt->error]);
    }
    
    $insert_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?> 