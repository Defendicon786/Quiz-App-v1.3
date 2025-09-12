<?php
session_start();
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

require 'database.php';

// Try loading Composer's autoloader from common locations
$autoloadPaths = [
    __DIR__ . '/lib/mpdf/vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php'
];
$autoloadLoaded = false;
foreach ($autoloadPaths as $autoload) {
    if (file_exists($autoload)) {
        require $autoload;
        $autoloadLoaded = true;
        break;
    }
}
if (!$autoloadLoaded) {
    error_log('Bulk upload failed: missing Composer autoload.');
    header("Location: questionfeed.php?error=missing_autoload&type=a");
    exit;
}

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chapter_id = isset($_POST['chapter_id']) ? intval($_POST['chapter_id']) : 0;
    $topic_id   = isset($_POST['topic_id']) ? intval($_POST['topic_id']) : 0;

    if (isset($_FILES['mcq_file']) && $_FILES['mcq_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['mcq_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['mcq_file']['name'], PATHINFO_EXTENSION));
        $questions = [];

        if ($ext === 'csv') {
            if (($handle = fopen($file_tmp, 'r')) !== false) {
                // Skip header row
                fgetcsv($handle);
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) < 6) {
                        continue; // insufficient columns
                    }
                    $questions[] = [
                        'question' => $data[0],
                        'optiona'  => $data[1],
                        'optionb'  => $data[2],
                        'optionc'  => $data[3],
                        'optiond'  => $data[4],
                        'answer'   => strtoupper(trim($data[5]))
                    ];
                }
                fclose($handle);
            }
        } else {
            // Try to read using PhpSpreadsheet for Excel files
            try {
                $reader = IOFactory::createReaderForFile($file_tmp);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($file_tmp);
            } catch (\Throwable $e) {
                error_log('Bulk upload parse error: ' . $e->getMessage());
                header("Location: questionfeed.php?error=parse_failed&type=a");
                exit;
            }
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            // Skip header (first row)
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty($row[0])) {
                    continue;
                }
                $questions[] = [
                    'question' => $row[0],
                    'optiona'  => $row[1] ?? '',
                    'optionb'  => $row[2] ?? '',
                    'optionc'  => $row[3] ?? '',
                    'optiond'  => $row[4] ?? '',
                    'answer'   => strtoupper(trim($row[5] ?? ''))
                ];
            }
        }

        if ($conn && !empty($questions) && $chapter_id) {
            $stmt = $conn->prepare("INSERT INTO mcqdb (question, optiona, optionb, optionc, optiond, answer, chapter_id, topic_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($questions as $q) {
                $stmt->bind_param(
                    "ssssssii",
                    $q['question'],
                    $q['optiona'],
                    $q['optionb'],
                    $q['optionc'],
                    $q['optiond'],
                    $q['answer'],
                    $chapter_id,
                    $topic_id
                );
                $stmt->execute();
            }
            $stmt->close();
            header("Location: questionfeed.php?success=1&type=a");
            exit;
        }
    }
}

error_log('Bulk upload failed: no valid questions processed.');
header("Location: questionfeed.php?error=no_questions&type=a");
exit;
?>
