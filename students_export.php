<?php
// Practical 11 (intermediate): export the current student list (with the same search/filters) as CSV
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/student_lib.php';
require_admin();

$conn   = db();
$result = student_list($conn, [
    'search' => trim((string)($_GET['search'] ?? '')),
    'course' => (string)($_GET['course'] ?? ''),
    'status' => (string)($_GET['status'] ?? ''),
], 1, 100000);

audit_log($conn, (int)$_SESSION['user_id'], 'students_exported', $result['total'] . ' rows');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="students_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");                                  // UTF-8 BOM so Excel shows names correctly
fputcsv($out, ['Enrollment No', 'Name', 'Email', 'Course', 'Year', 'Status'], ',', '"', '\\');
foreach ($result['rows'] as $s) {
    fputcsv($out, [
        csv_safe($s['enrollment_no']), csv_safe($s['name']), csv_safe($s['email']),
        $s['course'], $s['year'], $s['status'],
    ], ',', '"', '\\');
}
fclose($out);
