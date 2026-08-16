<?php
// On the Hostinger host (detected by its account home directory), the real
// credentials live in config.production.php — a file that is gitignored and
// uploaded to the server only, so the production password never enters git.
if (is_dir('/home/u670910047') && is_file(__DIR__ . '/config.production.php')) {
    require __DIR__ . '/config.production.php';
    return;
}

// Local XAMPP development.
// Use 127.0.0.1 (not localhost) on macOS/XAMPP — localhost tries a socket path PHP cannot find.
$DB_HOST = '127.0.0.1';
$DB_NAME = 'construction_management';
$DB_USER = 'root';
$DB_PASS = '';

// This codebase is written for mysqli's classic "return false / set ->error"
// behaviour (it checks execute() return values and $stmt->error throughout).
// PHP 8.1+ changed the default to throw mysqli_sql_exception, which turned
// ordinary failures (e.g. an FK violation on a bad role_id) into uncaught
// fatal 500s. Restore the return-false mode the code actually handles.
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    die('A system error occurred. Please try again later.');
}
$conn->set_charset('utf8mb4');
