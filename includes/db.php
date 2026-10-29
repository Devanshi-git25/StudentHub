<?php
// Practical 8: database connection (MySQLi, connects lazily the first time db() is called)
require_once __DIR__ . '/config.php';

function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_OFF);               // handle errors ourselves
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_errno) {
        error_log('StudentHub DB connection failed: ' . $conn->connect_error);   // details only in the log
        http_response_code(500);
        die('Database connection failed. Make sure MySQL is running and sql/studenthub.sql is imported.');
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

// Records an important action in audit_logs
function audit_log(mysqli $conn, ?int $userId, string $action, string $details = ''): void
{
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isss', $userId, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}
