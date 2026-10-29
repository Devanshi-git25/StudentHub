<?php
// Integration: profile page from the database + link the account to a student record
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/event_lib.php';
require_login();
$pageTitle = 'My Profile';
$conn = db();
$uid  = (int)$_SESSION['user_id'];

$stmt = $conn->prepare('SELECT full_name, email, mobile, course, year, gender, role, created_at, last_login FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$student = linked_student($conn);
$error   = '';
$enroll  = '';

// Link this account to a student record (existing record with the same email, or create a new one)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'student' && !$student) {
    $enroll = strtoupper(clean_text((string)($_POST['enrollment_no'] ?? '')));

    if (!csrf_valid()) {
        $error = 'Your form expired. Please try again.';
    } elseif (!preg_match('/^\d{2}[A-Z]{2}\d{3}$/', $enroll)) {
        $error = 'Use the format 23CS001 (2 digits, 2 letters, 3 digits).';
    } else {
        $stmt = $conn->prepare('SELECT id, user_id, email FROM students WHERE enrollment_no = ? LIMIT 1');
        $stmt->bind_param('s', $enroll);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($found) {
            if ($found['user_id'] !== null) {
                $error = 'That enrollment number is already linked to another account.';
            } elseif (strtolower($found['email']) !== strtolower($user['email'])) {
                $error = 'That record uses a different email. Contact the administrator to link it.';
            } else {
                $stmt = $conn->prepare('UPDATE students SET user_id = ? WHERE id = ? AND user_id IS NULL');
                $stmt->bind_param('ii', $uid, $found['id']);
                $stmt->execute();
                $linked = $stmt->affected_rows > 0;
                $stmt->close();
                if (!$linked) {
                    $error = 'Could not link that record. Please try again.';
                }
            }
        } else {
            // No record with this enrollment number: create one from the account details
            $stmt = $conn->prepare('SELECT id FROM students WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $user['email']);
            $stmt->execute();
            $stmt->store_result();
            $emailUsed = $stmt->num_rows > 0;
            $stmt->close();

            if ($emailUsed) {
                $error = 'A student record already exists for your email. Enter its enrollment number instead.';
            } elseif (!$user['course'] || !$user['year']) {
                $error = 'Your account has no course/year. Contact the administrator.';
            } else {
                $stmt = $conn->prepare('INSERT INTO students (user_id, enrollment_no, name, email, course, year) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('isssss', $uid, $enroll, $user['full_name'], $user['email'], $user['course'], $user['year']);
                if (!$stmt->execute()) {
                    error_log('Student link failed: ' . $conn->error);
                    $error = 'Could not create your student record. Please try again.';
                }
                $stmt->close();
            }
        }

        if ($error === '') {
            audit_log($conn, $uid, 'student_profile_linked', $enroll);
            flash_set('success', 'Your student profile is ready. You can now register for events.');
            redirect('profile.php');
        }
    }
}

// My event registrations
$myEvents = [];
if ($student) {
    $stmt = $conn->prepare(
        'SELECT e.id, e.title, e.event_date, e.venue, e.status, r.registered_at
           FROM registrations r JOIN events e ON e.id = r.event_id
          WHERE r.student_id = ? ORDER BY e.event_date DESC'
    );
    $stmt->bind_param('i', $student['id']);
    $stmt->execute();
    $myEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$initials = '';
foreach (array_slice(preg_split('/\s+/', trim($user['full_name'])), 0, 2) as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}

require __DIR__ . '/includes/header.php';
?>
    <main>

        <section>
            <h2>My Profile</h2>

            <article class="profile-card">
                <div class="avatar" aria-hidden="true"><?= e($initials) ?></div>
                <div>
                    <h3><?= e($user['full_name']) ?></h3>
                    <p><span class="tag"><?= e(ucfirst($user['role'])) ?></span></p>
                </div>
            </article>

            <article>
                <h3>Account Details</h3>
                <ul>
                    <li>Email: <?= e($user['email']) ?></li>
                    <li>Mobile: <?= e($user['mobile']) ?></li>
                    <li>Course: <?= e($user['course'] ?? 'Not set') ?></li>
                    <li>Year: <?= e($user['year'] ?? 'Not set') ?></li>
                    <li>Gender: <?= e($user['gender'] ?? 'Not set') ?></li>
                    <li>Member since: <?= e($user['created_at']) ?></li>
                    <li>Last login: <?= e($_SESSION['previous_login'] ?? $user['last_login'] ?? 'First login') ?></li>
                </ul>
            </article>

<?php if ($user['role'] === 'student'): ?>
            <article>
                <h3>Student Record</h3>
<?php if ($student): ?>
                <ul>
                    <li>Enrollment No.: <?= e($student['enrollment_no']) ?></li>
                    <li>Status: <span class="tag tag-<?= e($student['status']) ?>"><?= e(ucfirst($student['status'])) ?></span></li>
                </ul>
<?php else: ?>
                <p>Enter your enrollment number to link your account to your student record. You need this to register for events.</p>
<?php if ($error): ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>
                <form method="post" action="profile.php" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="enrollment_no">Enrollment No. (e.g. 23CS001)</label>
                        <input type="text" id="enrollment_no" name="enrollment_no" maxlength="7" required value="<?= e($enroll) ?>">
                    </div>
                    <button type="submit">Save</button>
                </form>
<?php endif; ?>
            </article>

<?php if ($student): ?>
            <article>
                <h3>My Event Registrations (<?= count($myEvents) ?>)</h3>
<?php if (!$myEvents): ?>
                <p>You have not registered for any events yet. <a href="events.php">Browse events</a></p>
<?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Event</th><th>Date</th><th>Venue</th><th>Registered on</th></tr></thead>
                        <tbody>
<?php foreach ($myEvents as $ev): ?>
                            <tr>
                                <td><?= e($ev['title']) ?></td>
                                <td><?= e($ev['event_date']) ?></td>
                                <td><?= e($ev['venue']) ?></td>
                                <td><?= e($ev['registered_at']) ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
<?php endif; ?>
            </article>
<?php endif; ?>
<?php endif; ?>
        </section>

        <aside>
            <h3>Quick Links</h3>
            <ul>
                <li><a href="events.php">Browse events</a></li>
                <li><a href="dashboard.php">My dashboard</a></li>
                <li><a href="feedback.php">Send feedback</a></li>
            </ul>
        </aside>

    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
