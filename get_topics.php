<?php
// Buffer output to avoid corrupting JSON with notices or warnings.
ob_start();

header('Content-Type: application/json');
include "database.php";

function send_json($data) {
    // Use JSON_INVALID_UTF8_SUBSTITUTE to replace any invalid UTF-8 sequences
    // instead of failing outright, which previously caused errors when
    // topics contained characters stored with a different encoding.
    $json = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        $json = json_encode(['error' => 'JSON encoding failed: ' . json_last_error_msg()]);
    }

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/json');
    echo $json;
    exit;
}

if (!isset($conn) || $conn === null) {
    send_json(['error' => 'Database connection failed']);
}

if ($conn->connect_errno) {
    send_json(['error' => 'Database connection failed: ' . $conn->connect_error]);
}

$chapter_ids = [];
if (isset($_GET['chapter_ids'])) {
    $chapter_ids = array_map('intval', explode(',', $_GET['chapter_ids']));
} elseif (isset($_GET['chapter_id'])) {
    $chapter_ids = [intval($_GET['chapter_id'])];
}
$topics = [];
if (!empty($chapter_ids)) {
    $placeholders = implode(',', array_fill(0, count($chapter_ids), '?'));
    $types = str_repeat('i', count($chapter_ids));
    $sql = "SELECT topic_id, topic_name FROM topics WHERE chapter_id IN ($placeholders) ORDER BY topic_name ASC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$chapter_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $topics[] = $row;
        }
        $stmt->close();
    }
}
send_json($topics);

