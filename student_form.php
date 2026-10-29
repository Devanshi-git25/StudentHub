<?php
// Practical 11: add / edit a student (same form for both)
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/student_lib.php';
require_admin();
$conn = db();

$id       = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
$existing = $id > 0 ? student_find($conn, $id) : null;
if ($id > 0 && !$existing) {
    flash_set('error', 'That student no longer exists.');
    redirect('manage_students.php');
}

$pageTitle = $existing ? 'Edit Student' : 'Add Student';
$errors    = [];
$old       = $existing ?? ['enrollment_no' => '', 'name' => '', 'email' => '', 'course' => '', 'year' => '', 'status' => 'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors['form'] = 'Your form expired. Please try again.';
    } else {
        [$clean, $errors] = validate_student($_POST, $existing ? (int)$existing['id'] : null, $conn);
        $old = $clean;

        if (!$errors) {
            $savedId = student_save($conn, $clean, $existing ? (int)$existing['id'] : null);
            if ($savedId > 0) {
                audit_log($conn, (int)$_SESSION['user_id'], $existing ? 'student_updated' : 'student_created', $clean['enrollment_no'] . ' ' . $clean['name']);
                flash_set('success', $existing ? 'Student updated successfully.' : 'Student added successfully.');
                redirect('manage_students.php');
            }
            if ($conn->errno === 1062) {
                $errors['enrollment_no'] = 'That enrollment number is already in use.';
            } else {
                error_log('Student save failed: ' . $conn->error);
                $errors['form'] = 'Could not save the student. Please try again.';
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
    <main>
        <section>
            <h2><?= e($pageTitle) ?></h2>

<?php if (isset($errors['form'])): ?>
            <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

            <form method="post" action="student_form.php<?= $existing ? '?id=' . (int)$existing['id'] : '' ?>" novalidate>
                <?= csrf_field() ?>
                <?php if ($existing): ?><input type="hidden" name="id" value="<?= (int)$existing['id'] ?>"><?php endif; ?>

                <div class="form-group">
                    <label for="enrollment_no">Enrollment No. * (e.g. 23CS001)</label>
                    <input type="text" id="enrollment_no" name="enrollment_no" maxlength="7" required value="<?= e($old['enrollment_no']) ?>"
                           class="<?= isset($errors['enrollment_no']) ? 'invalid' : '' ?>">
                    <span class="error" role="alert"><?= e($errors['enrollment_no'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" maxlength="60" required value="<?= e($old['name']) ?>"
                           class="<?= isset($errors['name']) ? 'invalid' : '' ?>">
                    <span class="error" role="alert"><?= e($errors['name'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" maxlength="100" required value="<?= e($old['email']) ?>"
                           class="<?= isset($errors['email']) ? 'invalid' : '' ?>">
                    <span class="error" role="alert"><?= e($errors['email'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="course">Course *</label>
                    <select id="course" name="course" class="<?= isset($errors['course']) ? 'invalid' : '' ?>">
                        <option value="">-- Select Course --</option>
<?php foreach (STUDENT_COURSES as $c): ?>
                        <option value="<?= e($c) ?>"<?= $old['course'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
<?php endforeach; ?>
                    </select>
                    <span class="error" role="alert"><?= e($errors['course'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="year">Year *</label>
                    <select id="year" name="year" class="<?= isset($errors['year']) ? 'invalid' : '' ?>">
                        <option value="">-- Select Year --</option>
<?php foreach (STUDENT_YEARS as $y): ?>
                        <option value="<?= e($y) ?>"<?= $old['year'] === $y ? ' selected' : '' ?>><?= e($y) ?></option>
<?php endforeach; ?>
                    </select>
                    <span class="error" role="alert"><?= e($errors['year'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
<?php foreach (STUDENT_STATUS as $st): ?>
                        <option value="<?= e($st) ?>"<?= $old['status'] === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
<?php endforeach; ?>
                    </select>
                </div>

                <button type="submit"><?= $existing ? 'Save Changes' : 'Add Student' ?></button>
                <a class="button-link" href="manage_students.php">Cancel</a>
            </form>
        </section>
    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
