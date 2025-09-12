<?php
session_start();
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

require 'database.php';
require 'vendor/autoload.php';

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
            $spreadsheet = IOFactory::load($file_tmp);
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

header("Location: questionfeed.php?error=1&type=a");
exit;
?>
