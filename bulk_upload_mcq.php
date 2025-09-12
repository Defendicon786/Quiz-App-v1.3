<?php
// Bulk upload handler for MCQ questions

session_start();
if (!isset($_SESSION["instructorloggedin"]) || $_SESSION["instructorloggedin"] !== true) {
    header("location: instructorlogin.php");
    exit;
}

require_once __DIR__ . "/logger.php";

$redirect = "questionfeed.php";
$autoload = __DIR__ . "/vendor/autoload.php";
if (!file_exists($autoload)) {
    $logger->error("Composer autoload not found during bulk MCQ upload.");
    header("Location: {$redirect}?error=missing_autoload");
    exit;
}
require_once $autoload;

try {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Upload failed or no file provided");
    }

    $content = file_get_contents($_FILES['file']['tmp_name']);
    $questions = json_decode($content, true);
    if ($questions === null) {
        throw new RuntimeException("JSON parse error: " . json_last_error_msg());
    }

    if (empty($questions)) {
        header("Location: {$redirect}?error=no_questions");
        exit;
    }

    // TODO: Insert questions into database here.

    header("Location: {$redirect}?success=1&type=mcq");
    exit;
} catch (Throwable $e) {
    $logger->error("Bulk MCQ upload parse failed: " . $e->getMessage());
    header("Location: {$redirect}?error=parse_failed");
    exit;
}

