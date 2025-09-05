<?php
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// If Monolog is available, configure a rotating file logger. Otherwise fall
// back to a very small PSR-3 style logger that writes to the same file using
// PHP's built-in error_log function. This keeps the application working when
// Composer dependencies haven't been installed yet.
if (class_exists('Monolog\\Logger') && class_exists('Monolog\\Handler\\RotatingFileHandler')) {
    $env = $_ENV['APP_ENV'] ?? 'production';
    $levelName = $_ENV['LOG_LEVEL'] ?? ($env === 'production' ? 'WARNING' : 'DEBUG');
    $level = Logger::toMonologLevel($levelName);
    if ($env === 'production' && $level < Logger::WARNING) {
        $level = Logger::WARNING;
    }

    $handler = new RotatingFileHandler($logDir . '/app.log', 30, $level, true);
    $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');

    $logger = new Logger('quizapp');
    $logger->pushHandler($handler);
} else {
    /**
     * Minimal logger used when Monolog isn't available. Methods like error(),
     * warning(), info(), etc. simply write the message to the application log
     * using PHP's error_log function.
     */
    class SimpleLogger {
        private string $file;

        public function __construct(string $file) {
            $this->file = $file;
        }

        public function __call(string $name, array $arguments): void {
            $level = strtoupper($name);
            $message = $arguments[0] ?? '';
            error_log("[$level] $message\n", 3, $this->file);
        }
    }

    $logger = new SimpleLogger($logDir . '/app.log');
}
