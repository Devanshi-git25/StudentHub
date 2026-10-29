<?php
// Practical 11: student management - list, search, filter, pagination, soft delete / restore / permanent delete
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/student_lib.php';
require_admin();
$pageTitle = 'Manage Students';
$conn = db();

// Keep only the query-string keys we expect (used for links and redirects)
$filters = [
    'search' => trim((string)($_GET['search'] ?? '')),
    'course' => (string)($_GET['course'] ?? ''),
    'status' => (string)($_GET['status'] ?? ''),
];
$query = array_filter($filters, fn($v) => $v !== '');

/* ---------- Actions (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'manage_students.php';
    parse_str((string)($_POST['return'] ?? ''), $ret);
    $retQuery = array_intersect_key($ret, array_flip(['search', 'course', 'status', 'page']));
    $retQuery = array_filter(array_map('strval', array_map(fn($v) => is_scalar($v) ? $v : '', $retQuery)), fn($v) => $v !== '');
    if ($retQuery) {
        $back .= '?' . http_build_query($retQuery);
    }

    if (!csrf_valid()) {
        flash_set('error', 'Your form expired. Please try again.');
        redirect($back);
    }

    $id      = (int)($_POST['id'] ?? 0);
    $action  = (string)($_POST['action'] ?? '');
    $student = $id > 0 ? student_find($conn, $id) : null;

    if (!$student) {
        flash_set('error', 'That student no longer exists.');
        redirect($back);
    }

    if ($action === 'deactivate' && student_set_status($conn, $id, 'inactive')) {
        audit_log($conn, (int)$_SESSION['user_id'], 'student_deactivated', $student['enrollment_no'] . ' ' . $student['name']);
        flash_set('success', $student['name'] . ' was deactivated (soft delete). You can restore them any time.');
    } elseif ($action === 'restore' && student_set_status($conn, $id, 'active')) {
        audit_log($conn, (int)$_SESSION['user_id'], 'student_restored', $student['enrollment_no'] . ' ' . $student['name']);
        flash_set('success', $student['name'] . ' was restored.');
    } elseif ($action === 'delete' && $student['status'] === 'inactive' && student_delete($conn, $id)) {
        audit_log($conn, (int)$_SESSION['user_id'], 'student_deleted', $student['enrollment_no'] . ' ' . $student['name']);
        flash_set('success', $student['name'] . ' was permanently deleted.');
    } else {
        flash_set('error', 'That action could not be completed.');
    }
    redirect($back);
}

/* ---------- List (GET) ---------- */
$result = student_list($conn, $filters, (int)($_GET['page'] ?? 1), 8);
$returnQuery = http_build_query(array_merge($query, ['page' => $result['page']]));

require __DIR__ . '/includes/header.php';
?>
    <main>
        <section>
            <h2>Manage Students</h2>

            <form method="get" action="manage_students.php" class="controls">
                <div>
                    <label for="search">Search</label>
                    <input type="search" id="search" name="search" placeholder="Name, email, enrollment..." value="<?= e($filters['search']) ?>">
                </div>
                <div>
                    <label for="course">Course</label>
                    <select id="course" name="course">
                        <option value="">All courses</option>
<?php foreach (STUDENT_COURSES as $c): ?>
                        <option value="<?= e($c) ?>"<?= $filters['course'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All</option>
                        <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>>Active</option>
                        <option value="inactive"<?= $filters['status'] === 'inactive' ? ' selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="controls-buttons">
                    <button type="submit">Search</button>
                    <a class="button-link" href="manage_students.php">Reset</a>
                </div>
            </form>

            <p class="toolbar">
                <a class="button-link" href="student_form.php">+ Add Student</a>
                <a class="button-link" href="students_export.php<?= $query ? '?' . e(http_build_query($query)) : '' ?>">Export CSV</a>
                <span><?= $result['total'] ?> student(s) found</span>
            </p>

<?php if (!$result['rows']): ?>
            <p class="empty">No students match your search.</p>
<?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Enrollment</th><th>Name</th><th>Email</th><th>Course</th><th>Year</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
<?php foreach ($result['rows'] as $s): ?>
                        <tr class="<?= $s['status'] === 'inactive' ? 'row-inactive' : '' ?>">
                            <td><?= e($s['enrollment_no']) ?></td>
                            <td><?= e($s['name']) ?></td>
                            <td><?= e($s['email']) ?></td>
                            <td><?= e($s['course']) ?></td>
                            <td><?= e($s['year']) ?></td>
                            <td><span class="tag tag-<?= e($s['status']) ?>"><?= e(ucfirst($s['status'])) ?></span></td>
                            <td class="actions">
                                <a href="student_form.php?id=<?= (int)$s['id'] ?>">Edit</a>
<?php if ($s['status'] === 'active'): ?>
                                <form method="post" action="manage_students.php" data-confirm="Deactivate <?= e($s['name']) ?>? You can restore them later.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="return" value="<?= e($returnQuery) ?>">
                                    <button type="submit" class="link-button">Delete</button>
                                </form>
<?php else: ?>
                                <form method="post" action="manage_students.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="return" value="<?= e($returnQuery) ?>">
                                    <button type="submit" class="link-button">Restore</button>
                                </form>
                                <form method="post" action="manage_students.php" data-confirm="Permanently delete <?= e($s['name']) ?>? This cannot be undone.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="return" value="<?= e($returnQuery) ?>">
                                    <button type="submit" class="link-button danger">Delete forever</button>
                                </form>
<?php endif; ?>
                            </td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= render_pagination($result['page'], $result['pages'], $query, 'manage_students.php') ?>
<?php endif; ?>
        </section>
    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
