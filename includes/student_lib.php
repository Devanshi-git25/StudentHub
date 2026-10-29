<?php
// Shared student logic: used by manage_students.php (Practical 11) and api/students.php (Practical 13)

const STUDENT_COURSES = ['BTech CSE', 'BTech IT', 'BTech Electronics', 'BTech Mechanical'];
const STUDENT_YEARS   = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
const STUDENT_STATUS  = ['active', 'inactive'];

// Reads a text field safely (ignores arrays or other unexpected types)
function student_input(array $in, string $key): string
{
    $value = $in[$key] ?? '';
    return is_string($value) ? clean_text($value) : '';
}

// Cleans and validates input. Returns [cleanData, errors]. $ignoreId = the student being edited.
function validate_student(array $in, ?int $ignoreId, mysqli $conn): array
{
    $d = [
        'enrollment_no' => strtoupper(student_input($in, 'enrollment_no')),
        'name'          => preg_replace('/\s+/', ' ', student_input($in, 'name')),
        'email'         => strtolower(student_input($in, 'email')),
        'course'        => student_input($in, 'course'),
        'year'          => student_input($in, 'year'),
        'status'        => student_input($in, 'status') ?: 'active',
    ];
    $errors = [];

    if ($d['enrollment_no'] === '') {
        $errors['enrollment_no'] = 'Enrollment number is required.';
    } elseif (!preg_match('/^\d{2}[A-Z]{2}\d{3}$/', $d['enrollment_no'])) {
        $errors['enrollment_no'] = 'Use the format 23CS001 (2 digits, 2 letters, 3 digits).';
    }

    if ($d['name'] === '') {
        $errors['name'] = 'Name is required.';
    } elseif (!preg_match("/^[A-Za-z][A-Za-z .'-]{1,59}$/", $d['name'])) {
        $errors['name'] = 'Name must be 2-60 characters (letters, spaces, . \' - only).';
    }

    if ($d['email'] === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 100) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (!in_array($d['course'], STUDENT_COURSES, true)) {
        $errors['course'] = 'Please select a valid course.';
    }
    if (!in_array($d['year'], STUDENT_YEARS, true)) {
        $errors['year'] = 'Please select a valid year.';
    }
    if (!in_array($d['status'], STUDENT_STATUS, true)) {
        $errors['status'] = 'Status must be active or inactive.';
    }

    // Duplicate checks (skipped for a field that already failed)
    $ignore = $ignoreId ?? 0;
    foreach (['enrollment_no' => 'Enrollment number', 'email' => 'Email'] as $field => $label) {
        if (isset($errors[$field])) {
            continue;
        }
        $stmt = $conn->prepare("SELECT id FROM students WHERE $field = ? AND id <> ? LIMIT 1");   // $field comes from the fixed list above
        $stmt->bind_param('si', $d[$field], $ignore);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[$field] = $label . ' is already used by another student.';
        }
        $stmt->close();
    }

    return [$d, $errors];
}

function student_find(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT id, enrollment_no, name, email, course, year, status FROM students WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Builds the WHERE part for search + filters. Returns [sql, types, params]
function student_where(array $f): array
{
    $where  = [];
    $types  = '';
    $params = [];

    $search = trim((string)($f['search'] ?? ''));
    if ($search !== '') {
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where[] = '(name LIKE ? OR email LIKE ? OR enrollment_no LIKE ? OR course LIKE ?)';
        $types  .= 'ssss';
        array_push($params, $like, $like, $like, $like);
    }
    if (in_array($f['course'] ?? '', STUDENT_COURSES, true)) {
        $where[] = 'course = ?';
        $types  .= 's';
        $params[] = $f['course'];
    }
    if (in_array($f['status'] ?? '', STUDENT_STATUS, true)) {
        $where[] = 'status = ?';
        $types  .= 's';
        $params[] = $f['status'];
    }

    return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $types, $params];
}

// Returns ['rows' => [...], 'total' => n, 'pages' => n, 'page' => n]
function student_list(mysqli $conn, array $filters, int $page, int $perPage): array
{
    [$sqlWhere, $types, $params] = student_where($filters);

    $stmt = $conn->prepare('SELECT COUNT(*) FROM students' . $sqlWhere);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    $pages  = max(1, (int)ceil($total / $perPage));
    $page   = min(max(1, $page), $pages);
    $offset = ($page - 1) * $perPage;

    $stmt = $conn->prepare('SELECT id, enrollment_no, name, email, course, year, status FROM students' . $sqlWhere . ' ORDER BY name ASC LIMIT ? OFFSET ?');
    $stmt->bind_param($types . 'ii', ...array_merge($params, [$perPage, $offset]));
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['rows' => $rows, 'total' => (int)$total, 'pages' => $pages, 'page' => $page];
}

// INSERT (id = null) or UPDATE. Returns the student id, or 0 on failure.
function student_save(mysqli $conn, array $d, ?int $id = null): int
{
    if ($id === null) {
        $stmt = $conn->prepare('INSERT INTO students (enrollment_no, name, email, course, year, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssss', $d['enrollment_no'], $d['name'], $d['email'], $d['course'], $d['year'], $d['status']);
        $ok = $stmt->execute();
        $newId = (int)$conn->insert_id;
        $stmt->close();
        return $ok ? $newId : 0;
    }

    $stmt = $conn->prepare('UPDATE students SET enrollment_no = ?, name = ?, email = ?, course = ?, year = ?, status = ? WHERE id = ?');
    $stmt->bind_param('ssssssi', $d['enrollment_no'], $d['name'], $d['email'], $d['course'], $d['year'], $d['status'], $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok ? $id : 0;
}

function student_set_status(mysqli $conn, int $id, string $status): bool   // soft delete / restore
{
    $stmt = $conn->prepare('UPDATE students SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function student_delete(mysqli $conn, int $id): bool                       // permanent delete
{
    $stmt = $conn->prepare('DELETE FROM students WHERE id = ?');
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
