<?php
// Practical 13: JSON API for students
//   GET    api/students.php?search=&course=&status=&page=   -> list (paginated)
//   GET    api/students.php?id=5                           -> one student
//   POST   api/students.php            {JSON body}         -> create   (201)
//   PUT    api/students.php?id=5       {JSON body}         -> update   (200)
//   DELETE api/students.php?id=5                           -> delete   (200)
define('API_REQUEST', true);                       // makes session timeout answer with JSON
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/student_lib.php';
require_once __DIR__ . '/../includes/api.php';

api_require_admin();
$conn   = db();
$method = $_SERVER['REQUEST_METHOD'];
$userId = (int)$_SESSION['user_id'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch ($method) {
    case 'GET':
        if ($id > 0) {
            $student = student_find($conn, $id);
            if (!$student) {
                api_error(404, 'Student not found.');
            }
            api_ok(['data' => $student]);
        }

        $result = student_list($conn, [
            'search' => trim((string)($_GET['search'] ?? '')),
            'course' => (string)($_GET['course'] ?? ''),
            'status' => (string)($_GET['status'] ?? ''),
        ], (int)($_GET['page'] ?? 1), 5);

        api_ok([
            'data' => $result['rows'],
            'meta' => ['page' => $result['page'], 'pages' => $result['pages'], 'total' => $result['total']],
        ]);
        break;

    case 'POST':
        [$clean, $errors] = validate_student(api_body(), null, $conn);
        if ($errors) {
            api_error(422, 'Please fix the highlighted fields.', $errors);
        }
        $newId = student_save($conn, $clean);
        if ($newId === 0) {
            api_error($conn->errno === 1062 ? 409 : 500, $conn->errno === 1062 ? 'That student already exists.' : 'Could not save the student.');
        }
        audit_log($conn, $userId, 'student_created', 'API: ' . $clean['enrollment_no'] . ' ' . $clean['name']);
        api_ok(['message' => 'Student added.', 'data' => student_find($conn, $newId)], 201);
        break;

    case 'PUT':
        $body = api_body();
        $id   = $id > 0 ? $id : (int)($body['id'] ?? 0);
        if ($id <= 0 || !student_find($conn, $id)) {
            api_error(404, 'Student not found.');
        }
        [$clean, $errors] = validate_student($body, $id, $conn);
        if ($errors) {
            api_error(422, 'Please fix the highlighted fields.', $errors);
        }
        if (student_save($conn, $clean, $id) === 0) {
            api_error($conn->errno === 1062 ? 409 : 500, $conn->errno === 1062 ? 'That enrollment number is already in use.' : 'Could not update the student.');
        }
        audit_log($conn, $userId, 'student_updated', 'API: ' . $clean['enrollment_no'] . ' ' . $clean['name']);
        api_ok(['message' => 'Student updated.', 'data' => student_find($conn, $id)]);
        break;

    case 'DELETE':
        $student = $id > 0 ? student_find($conn, $id) : null;
        if (!$student) {
            api_error(404, 'Student not found.');
        }
        if (!student_delete($conn, $id)) {
            api_error(500, 'Could not delete the student.');
        }
        audit_log($conn, $userId, 'student_deleted', 'API: ' . $student['enrollment_no'] . ' ' . $student['name']);
        api_ok(['message' => 'Student deleted.']);
        break;

    default:
        header('Allow: GET, POST, PUT, DELETE');
        api_error(405, 'Method not allowed.');
}
