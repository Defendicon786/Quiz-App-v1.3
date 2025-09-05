<?php
session_start();
ob_start();
if (!isset($_SESSION['paperloggedin']) || $_SESSION['paperloggedin'] !== true) {
    header('Location: paper_login.php');
    exit;
}

require_once __DIR__ . '/lib/mpdf/vendor/autoload.php';

include 'database.php';

$paperName = trim($_POST['paper_name'] ?? 'Question Paper');
$classId = intval($_POST['class_id'] ?? 0);
$subjectId = intval($_POST['subject_id'] ?? 0);
$chapterId = intval($_POST['chapter_id'] ?? 0);
$topicId = isset($_POST['topic_id']) && $_POST['topic_id'] !== '' ? intval($_POST['topic_id']) : null;
$mcq = intval($_POST['mcq'] ?? 0);
$short = intval($_POST['short'] ?? 0);
$essay = intval($_POST['essay'] ?? 0);
$fill = intval($_POST['fill'] ?? 0);
$numerical = intval($_POST['numerical'] ?? 0);
$paperDate = trim($_POST['paper_date'] ?? '');
$mode = $_POST['mode'] ?? 'random';
$logo = $_SESSION['paper_logo'] ?? '';
$header = $_SESSION['paper_header'] ?? '';

function fetch_questions($conn, $table, $fields, $chapterId, $topicId, $limit) {
    if ($limit <= 0 || !$conn) return [];
    $sql = "SELECT $fields FROM $table WHERE chapter_id=?";
    $types = 'i';
    $params = [$chapterId];
    if ($topicId) { $sql .= " AND topic_id=?"; $types .= 'i'; $params[] = $topicId; }
    $sql .= " ORDER BY RAND() LIMIT ?"; $types .= 'i'; $params[] = $limit;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $qs = [];
    while($row = $res->fetch_assoc()) { $qs[] = $row; }
    $stmt->close();
    return $qs;
}

$sections = [];

if ($mode === 'manual') {
    $selected = [
        'MCQs' => ['ids' => $_POST['selected_mcq'] ?? '', 'table' => 'mcqdb', 'fields' => 'question, optiona, optionb, optionc, optiond'],
        'Short Questions' => ['ids' => $_POST['selected_short'] ?? '', 'table' => 'shortanswer', 'fields' => 'question'],
        'Long Questions' => ['ids' => $_POST['selected_essay'] ?? '', 'table' => 'essay', 'fields' => 'question'],
        'Fill in the Blanks' => ['ids' => $_POST['selected_fill'] ?? '', 'table' => 'fillintheblanks', 'fields' => 'question'],
        'Numerical' => ['ids' => $_POST['selected_numerical'] ?? '', 'table' => 'numericaldb', 'fields' => 'question']
    ];
    foreach ($selected as $title => $info) {
        $ids = array_filter(array_map('intval', array_filter(explode(',', $info['ids']))));
        $sections[$title] = [];
        if (!empty($ids) && $conn) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT {$info['fields']} FROM {$info['table']} WHERE id IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $types = str_repeat('i', count($ids));
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) { $sections[$title][] = $row; }
                $stmt->close();
            }
        }
    }
} else {
    $sections['MCQs'] = fetch_questions($conn, 'mcqdb', 'question, optiona, optionb, optionc, optiond', $chapterId, $topicId, $mcq);
    $sections['Short Questions'] = fetch_questions($conn, 'shortanswer', 'question', $chapterId, $topicId, $short);
    $sections['Long Questions'] = fetch_questions($conn, 'essay', 'question', $chapterId, $topicId, $essay);
    $sections['Fill in the Blanks'] = fetch_questions($conn, 'fillintheblanks', 'question', $chapterId, $topicId, $fill);
    $sections['Numerical'] = fetch_questions($conn, 'numericaldb', 'question', $chapterId, $topicId, $numerical);
}
if ($conn) {
    $conn->close();
}

$html = '<div style="text-align:center;">';
if ($logo) $html .= '<img src="'.htmlspecialchars($logo).'" height="80"><br>';
$html .= '<h2>'.htmlspecialchars($header).'</h2>';
$html .= '<h3>'.htmlspecialchars($paperName).'</h3>';
if ($paperDate) $html .= '<div>Date: '.htmlspecialchars($paperDate).'</div>';
$html .= '</div>';

foreach ($sections as $title => $questions) {
    if (count($questions) === 0) continue;
    $html .= '<h4>'.htmlspecialchars($title).'</h4><ol>';
    foreach ($questions as $q) {
        if ($title === 'MCQs') {
            $html .= '<li>'.htmlspecialchars($q['question']).'<br>';
            $html .= 'A. '.htmlspecialchars($q['optiona']).'<br>';
            $html .= 'B. '.htmlspecialchars($q['optionb']).'<br>';
            $html .= 'C. '.htmlspecialchars($q['optionc']).'<br>';
            $html .= 'D. '.htmlspecialchars($q['optiond']).'</li>';
        } else {
            $html .= '<li>'.htmlspecialchars($q['question']).'</li>';
        }
    }
    $html .= '</ol>';
}

$mpdf = new \Mpdf\Mpdf();
if (ob_get_length()) {
    ob_end_clean();
}
$mpdf->WriteHTML($html);
$mpdf->Output('paper.pdf', 'I');
exit;
?>
