<?php
// Buffer any stray output so that we can emit clean JSON.
ob_start();

include 'database.php';

// Helper to emit a JSON response and terminate.
function send_json($data) {
    // Use JSON_INVALID_UTF8_SUBSTITUTE so invalid sequences do not cause
    // json_encode to fail when question text contains mixed encodings.
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

if (!isset($_GET['chapter_ids'])) {
    send_json(['error' => 'No chapter IDs provided']);
}

$chapter_ids = explode(',', $_GET['chapter_ids']);
$chapter_ids = array_map('intval', $chapter_ids); // Sanitize input
$chapter_ids_str = implode(',', $chapter_ids);

$topic_ids = [];
if (isset($_GET['topic_ids']) && strlen($_GET['topic_ids']) > 0) {
    $topic_ids = array_map('intval', explode(',', $_GET['topic_ids']));
}
$topic_filter = '';
if (!empty($topic_ids)) {
    $topic_ids_str = implode(',', $topic_ids);
    $topic_filter = " AND topic_id IN ($topic_ids_str)";
}

$counts = array(
    'mcq' => 0,
    'numerical' => 0,
    'dropdown' => 0,
    'fillblanks' => 0,
    'short' => 0,
    'essay' => 0
);

if (!empty($chapter_ids)) {
    // Count MCQs
    $sql = "SELECT COUNT(*) as count FROM mcqdb WHERE chapter_id IN ($chapter_ids_str)$topic_filter";
    $result = $conn->query($sql);
    $counts['mcq'] = (int)$result->fetch_assoc()['count'];

    // Fallback for Archaea topic if no MCQs found
    if ($counts['mcq'] === 0 && in_array(4, $topic_ids)) {
        $sql = "SELECT COUNT(*) as count FROM mcqdb WHERE chapter_id IN ($chapter_ids_str) AND question LIKE '%Archaea%'";
        $result = $conn->query($sql);
        $counts['mcq'] = (int)$result->fetch_assoc()['count'];
    }
    
    // Count Numerical
    $sql = "SELECT COUNT(*) as count FROM numericaldb WHERE chapter_id IN ($chapter_ids_str)$topic_filter";
    $result = $conn->query($sql);
    $counts['numerical'] = (int)$result->fetch_assoc()['count'];
    
    // Count Dropdown
    $sql = "SELECT COUNT(*) as count FROM dropdown WHERE chapter_id IN ($chapter_ids_str)$topic_filter";
    $result = $conn->query($sql);
    $counts['dropdown'] = (int)$result->fetch_assoc()['count'];
    
    // Count Fill in Blanks
    $sql = "SELECT COUNT(*) as count FROM fillintheblanks WHERE chapter_id IN ($chapter_ids_str)$topic_filter";
    $result = $conn->query($sql);
    $counts['fillblanks'] = (int)$result->fetch_assoc()['count'];
    
    // Count Short Answer
    $sql = "SELECT COUNT(*) as count FROM shortanswer WHERE chapter_id IN ($chapter_ids_str)$topic_filter";
    $result = $conn->query($sql);
    $counts['short'] = (int)$result->fetch_assoc()['count'];
    
    // Count Essay
    $sql = "SELECT COUNT(*) as count FROM essay WHERE chapter_id IN ($chapter_ids_str)$topic_filter";
    $result = $conn->query($sql);
    $counts['essay'] = (int)$result->fetch_assoc()['count'];
}

send_json($counts);
