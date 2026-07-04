<?php
// Use 127.0.0.1 (not localhost) on macOS/XAMPP — localhost tries a socket path PHP cannot find.
$DB_HOST = '127.0.0.1';
$DB_NAME = 'construction_management';
$DB_USER = 'root';
$DB_PASS = '';

// $DB_HOST = 'sql102.infinityfree.com';
// $DB_NAME = 'if0_41578228_construction_management';
// $DB_USER = 'if0_41578228';
// $DB_PASS = ''; // set via environment/secret store — never commit the real password

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
