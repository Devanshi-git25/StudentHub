<?php
// Practical 10: protected student dashboard
require_once __DIR__ . '/includes/init.php';
require_login();
$pageTitle = 'Dashboard';

$stmt = db()->prepare('SELECT full_name, email, course, year, created_at FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {                                          // account was deleted while logged in
    destroy_session();
    redirect('login.php?auth=1');
}

$previous = $_SESSION['previous_login'] ?? null;

require __DIR__ . '/includes/header.php';
?>
    <main>

        <section>
            <h2>Student Dashboard</h2>
            <p>Welcome back, <strong><?= e($user['full_name']) ?></strong>!
               <?= $previous ? 'Your previous login was on ' . e($previous) . '.' : 'This is your first login.' ?></p>

            <article>
                <h3>Your Details</h3>
                <ul>
                    <li>Email: <?= e($user['email']) ?></li>
                    <li>Course: <?= e($user['course'] ?? 'Not set') ?></li>
                    <li>Year: <?= e($user['year'] ?? 'Not set') ?></li>
                    <li>Member since: <?= e($user['created_at']) ?></li>
                </ul>
            </article>

            <p><a href="profile.php">View full profile and my event registrations</a></p>

            <article>
                <h3>Upcoming Tasks</h3>
                <ul>
                    <li>Assignment Submission - 12 November</li>
                    <li>Database Quiz - 15 November</li>
                    <li>Web Technology Lab - 18 November</li>
                </ul>
            </article>
        </section>

        <aside>
            <h3>Notifications</h3>
            <ul>
                <li>New event registration is open.</li>
                <li>Exam timetable has been published.</li>
            </ul>
            <p><small>You are signed out automatically after <?= (int)(SESSION_TIMEOUT / 60) ?> minutes of inactivity.</small></p>
        </aside>

    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
