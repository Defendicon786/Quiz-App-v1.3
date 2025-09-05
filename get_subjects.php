<?php
header('Content-Type: application/json');
include "database.php";
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
if ($class_id <= 0) {
    echo json_encode([]);
    exit;
}
$sql = "SELECT DISTINCT s.subject_id, s.subject_name
        FROM subjects s
        JOIN chapters c ON s.subject_id = c.subject_id
        WHERE c.class_id = ?
        ORDER BY s.subject_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();
$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subjects[] = [
        'subject_id' => $row['subject_id'],
        'subject_name' => $row['subject_name']
    ];
}
$stmt->close();
$conn->close();
echo json_encode($subjects);
?>
