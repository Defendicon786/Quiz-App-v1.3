<?php
session_start();
// Ensure instructor is logged in
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

include "database.php";

// Support both GET and POST
$class_id = 0;
$section_id = 0;
if (isset($_GET['class_id'])) {
    $class_id = intval($_GET['class_id']);
} elseif (isset($_POST['class_id'])) {
    $class_id = intval($_POST['class_id']);
}
if (isset($_GET['section_id'])) {
    $section_id = intval($_GET['section_id']);
} elseif (isset($_POST['section_id'])) {
    $section_id = intval($_POST['section_id']);
}

if ($class_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$sql = "SELECT s.rollnumber, s.name FROM studentinfo s
        LEFT JOIN class_sections cs ON s.section_id = cs.id
        WHERE cs.class_id = ?";
$params = [$class_id];
$types = "i";

if ($section_id > 0) {
    $sql .= " AND cs.id = ?";
    $params[] = $section_id;
    $types .= "i";
}

$sql .= " ORDER BY s.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = [
        'rollnumber' => $row['rollnumber'],
        'name' => $row['name']
    ];
}
$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($students);
?>
