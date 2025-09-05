<?php
// Ensure a session is active before using \$_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set PHP default timezone to match your country's timezone
date_default_timezone_set('Asia/Karachi'); // Replace with your timezone

// Load Composer dependencies and environment variables if available.
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;

    if (class_exists('Dotenv\\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->safeLoad();
    }
}

require_once __DIR__ . '/logger.php';

$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? 'studyhtc_up';
$db_user = $_ENV['DB_USER'] ?? 'studyhtc_hassan';
$db_pass = $_ENV['DB_PASS'] ?? 'hassan@tariq786';

$conn = null;

// Attempt to create the database connection. Newer versions of PHP throw
// mysqli_sql_exception on connection failure. Catch the exception so that the
// calling script can handle the error and return a clean JSON response.
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
} catch (mysqli_sql_exception $e) {
    $logger->error('Database Connection Failed: ' . $e->getMessage());
    $conn = null;
}

// If the connection object exists but has an error, log it without producing
// any output that could corrupt API responses.
if ($conn && $conn->connect_error) {
    $logger->error('Database Connection Failed: ' . $conn->connect_error);
}

if ($conn) {
    // Ensure the connection uses UTF-8 so JSON encoding doesn't fail on
    // characters stored with a different encoding in the database.
    if (!$conn->set_charset('utf8mb4')) {
        $logger->warning('Failed to set database connection charset: ' . $conn->error);
    }

    // Set session timezone to match PHP timezone
    if (!$conn->query("SET time_zone = '+05:00'")) { // Replace +05:00 with your timezone offset
        // Failed to set timezone, log error.
        $logger->warning('Failed to set database session timezone: ' . $conn->error);
    }

    // Set session variables for timezone
    $_SESSION['timezone'] = 'Asia/Karachi';
    $_SESSION['timezone_offset'] = '+05:00';
}
