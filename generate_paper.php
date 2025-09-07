<?php
require_once 'includes/auth.php';
requirePermission();
ob_start();

// Serve the previously generated PDF when requested
if (isset($_GET['pdf'])) {
    if (!isset($_SESSION['paperloggedin']) || $_SESSION['paperloggedin'] !== true) {
        header('Location: paper_login.php');
        exit;
    }

    $token = $_GET['token'] ?? '';
    $pdfInfo = $_SESSION['generated_pdf'] ?? [];
    $pdfPath = $pdfInfo['path'] ?? '';
    $pdfToken = $pdfInfo['token'] ?? '';
    $pdfName = $pdfInfo['filename'] ?? 'paper.pdf';
    if (!$token || $token !== $pdfToken || !$pdfPath || !file_exists($pdfPath)) {
        exit('PDF not found');
    }

    // Update remaining uses before streaming
    $remaining = ($pdfInfo['uses'] ?? 1) - 1;
    $deleteAfter = $remaining <= 0;
    if ($deleteAfter) {
        unset($_SESSION['generated_pdf']);
    } else {
        $_SESSION['generated_pdf']['uses'] = $remaining;
    }

    // Release the session lock for concurrent requests
    session_write_close();

    // Clean any existing output buffers to prevent corruption
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Send PDF headers including size for efficient streaming
    header('Content-Type: application/pdf');
    $disposition = isset($_GET['download']) ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disposition . '; filename="' . $pdfName . '"');
    header('Content-Length: ' . filesize($pdfPath));

    // Stream the PDF to the client
    readfile($pdfPath);

    // Remove the file if it has no remaining uses
    if ($deleteAfter) {
        unlink($pdfPath);
    }
    exit;
}

if (!isset($_SESSION['paperloggedin']) || $_SESSION['paperloggedin'] !== true) {
    header('Location: paper_login.php');
    exit;
}

require_once __DIR__ . '/logger.php';

// Possible autoloader paths in priority order
$autoloadPaths = [
    __DIR__ . '/lib/mpdf/vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php'
];
$autoloadLoaded = false;
foreach ($autoloadPaths as $autoload) {
    if (file_exists($autoload)) {
        require_once $autoload;
        $autoloadLoaded = true;
        break;
    }
}
if (!$autoloadLoaded) {
    $message = 'Unable to load required autoloader. Checked paths: lib/mpdf/vendor/autoload.php and vendor/autoload.php';
    if (isset($logger)) {
        $logger->error($message);
    }
    exit($message);
}

include 'database.php';

$paperName = trim($_POST['paper_name'] ?? 'Question Paper');
$fileBase = preg_replace('/[^A-Za-z0-9 _-]/', '_', $paperName);
$fileBase = trim($fileBase);
if ($fileBase === '') { $fileBase = 'paper'; }
$pdfFileName = $fileBase . '.pdf';
$classId = intval($_POST['class_id'] ?? 0);
$subjectId = intval($_POST['subject_id'] ?? 0);
$chapterIds = isset($_POST['chapter_ids']) ? array_filter(array_map('intval', (array)$_POST['chapter_ids'])) : [];
$topicIds = isset($_POST['topic_ids']) ? array_filter(array_map('intval', (array)$_POST['topic_ids'])) : [];
$mcq = intval($_POST['mcq'] ?? 0);
$short = intval($_POST['short'] ?? 0);
$essay = intval($_POST['essay'] ?? 0);
$fill = intval($_POST['fill'] ?? 0);
$numerical = intval($_POST['numerical'] ?? 0);
$paperDate = trim($_POST['paper_date'] ?? '');
$mode = $_POST['mode'] ?? 'random';
$logo = $_SESSION['paper_logo'] ?? '';
$header = $_SESSION['paper_header'] ?? '';

function fetch_questions($conn, $table, $fields, $chapterIds, $topicIds, $limit) {
    if ($limit <= 0 || !$conn || empty($chapterIds)) return [];

    $chapterPlaceholders = implode(',', array_fill(0, count($chapterIds), '?'));
    $types = str_repeat('i', count($chapterIds));
    $params = $chapterIds;
    $sql = "SELECT $fields FROM $table WHERE chapter_id IN ($chapterPlaceholders)";
    if (!empty($topicIds)) {
        $topicPlaceholders = implode(',', array_fill(0, count($topicIds), '?'));
        $sql .= " AND topic_id IN ($topicPlaceholders)";
        $types .= str_repeat('i', count($topicIds));
        $params = array_merge($params, $topicIds);
    }
    $sql .= " ORDER BY RAND() LIMIT ?";
    $types .= 'i';
    $params[] = $limit;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $qs = [];
    while ($row = $res->fetch_assoc()) { $qs[] = $row; }
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
    $sections['MCQs'] = fetch_questions($conn, 'mcqdb', 'question, optiona, optionb, optionc, optiond', $chapterIds, $topicIds, $mcq);
    $sections['Short Questions'] = fetch_questions($conn, 'shortanswer', 'question', $chapterIds, $topicIds, $short);
    $sections['Long Questions'] = fetch_questions($conn, 'essay', 'question', $chapterIds, $topicIds, $essay);
    $sections['Fill in the Blanks'] = fetch_questions($conn, 'fillintheblanks', 'question', $chapterIds, $topicIds, $fill);
    $sections['Numerical'] = fetch_questions($conn, 'numericaldb', 'question', $chapterIds, $topicIds, $numerical);
}
if ($conn) {
    $conn->close();
}

$html = '<table style="width:100%;border:0;margin-bottom:5px;">';
$html .= '<tr>';
if ($logo) {
    $html .= '<td style="width:20%;text-align:left;"><img src="'.htmlspecialchars($logo).'" height="50"></td>';
} else {
    $html .= '<td style="width:20%;"></td>';
}
$html .= '<td style="width:60%;text-align:center;">';
$html .= '<div style="margin:0;font-size:20px;font-weight:bold;">'.htmlspecialchars($header).'</div>';
$html .= '<div style="margin:0;font-size:16px;">'.htmlspecialchars($paperName).'</div>';
if ($paperDate) $html .= '<div style="margin-top:4px;font-size:14px;">Date: '.htmlspecialchars($paperDate).'</div>';
$html .= '</td>';
$html .= '<td style="width:20%;"></td>';
$html .= '</tr>';
$html .= '</table>';

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

$mpdf = new \Mpdf\Mpdf(['margin_top' => 5]);
$mpdf->WriteHTML($html);

// Remove previously generated PDF if it exists
if (isset($_SESSION['generated_pdf']['path'])) {
    $oldPath = $_SESSION['generated_pdf']['path'];
    if ($oldPath && file_exists($oldPath)) {
        unlink($oldPath);
    }
    unset($_SESSION['generated_pdf']);
}

// Write PDF to a temporary file and store reference in session
$tmpFile = tempnam(sys_get_temp_dir(), 'paper_');
$mpdf->Output($tmpFile, \Mpdf\Output\Destination::FILE);
$token = bin2hex(random_bytes(16));
$_SESSION['generated_pdf'] = [
    'path' => $tmpFile,
    'token' => $token,
    'uses'  => 2,
    'filename' => $pdfFileName
];

// Clean any existing output buffers to prevent corrupting the output
if (ob_get_length()) {
    ob_end_clean();
}

// Display HTML with download button and embedded PDF
echo '<!DOCTYPE html><html><head><title>' . htmlspecialchars($paperName) . '</title></head><body>';
echo '<div style="text-align:center; margin-bottom:10px;">';
echo '<a href="generate_paper.php?pdf=1&download=1&token=' . urlencode($token) . '" style="padding:10px 20px;background:#007bff;color:#fff;text-decoration:none;border-radius:4px;">Download PDF</a>';
echo '</div>';
echo '<iframe src="generate_paper.php?pdf=1&token=' . urlencode($token) . '" style="width:100%;height:90vh;" frameborder="0"></iframe>';
echo '</body></html>';
exit;
?>
