<?php
session_start();
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

include "database.php";

// Get class ID from request (support both GET and POST)
$class_id = 0;
if (isset($_GET['class_id'])) {
    $class_id = intval($_GET['class_id']);
} elseif (isset($_POST['class_id'])) {
    $class_id = intval($_POST['class_id']);
}

if ($class_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// Get sections for the specified class
$sections = [];

// First get sections from class_sections table
$sql = "SELECT id, section_name FROM class_sections WHERE class_id = ? ORDER BY section_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $sections[] = [
        'id' => $row['id'],
        'section_name' => $row['section_name']
    ];
}
$stmt->close();

// Then get any additional sections from quizconfig table that aren't in class_sections
$sql = "SELECT DISTINCT section FROM quizconfig 
        WHERE class_id = ? AND section IS NOT NULL 
        AND section NOT IN (SELECT section_name FROM class_sections WHERE class_id = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $class_id, $class_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if (!empty($row['section'])) {
        $sections[] = [
            'id' => 0, // Not from class_sections table
            'section_name' => $row['section']
        ];
    }
}
$stmt->close();

// Return the sections as JSON
header('Content-Type: application/json');
echo json_encode($sections);

$conn->close();
?> 