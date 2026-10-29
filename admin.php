<?php
// Practical 14: admin dashboard (statistics, latest registrations, chart) - admin only
require_once __DIR__ . '/includes/init.php';
require_admin();
$pageTitle = 'Admin Dashboard';
$conn = db();

function scalar(mysqli $conn, string $sql): int            // SQL below is fixed text, never user input
{
    $result = $conn->query($sql);
    return $result ? (int)$result->fetch_row()[0] : 0;
}

$feedbackFile  = __DIR__ . '/storage/feedback.json';
$feedbackCount = 0;
if (is_file($feedbackFile)) {
    $decoded = json_decode((string)file_get_contents($feedbackFile), true);
    $feedbackCount = is_array($decoded) ? count($decoded) : 0;
}

$stats = [
    'Total users'          => scalar($conn, 'SELECT COUNT(*) FROM users'),
    'Active users'         => scalar($conn, "SELECT COUNT(*) FROM users WHERE status = 'active'"),
    'Active students'      => scalar($conn, "SELECT COUNT(*) FROM students WHERE status = 'active'"),
    'Open events'          => scalar($conn, "SELECT COUNT(*) FROM events WHERE status = 'open'"),
    'Event registrations'  => scalar($conn, 'SELECT COUNT(*) FROM registrations'),
    'Feedback received'    => $feedbackCount,
];

// Latest 5 user sign-ups
$latestUsers = $conn->query('SELECT full_name, email, role, created_at FROM users ORDER BY created_at DESC, id DESC LIMIT 5')->fetch_all(MYSQLI_ASSOC);

// Latest 5 event registrations
$latestRegs = $conn->query(
    'SELECT s.name AS student, e.title AS event, r.registered_at
       FROM registrations r
       JOIN students s ON s.id = r.student_id
       JOIN events   e ON e.id = r.event_id
   ORDER BY r.registered_at DESC, r.id DESC LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

// Event registrations per month (last 6 months, missing months shown as 0)
$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $monthly[date('Y-m', strtotime(date('Y-m-01') . " -$i month"))] = 0;
}
$result = $conn->query(
    "SELECT DATE_FORMAT(registered_at, '%Y-%m') AS ym, COUNT(*) AS total
       FROM registrations
      WHERE registered_at >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 5 MONTH)
   GROUP BY ym"
);
foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
    if (isset($monthly[$row['ym']])) {
        $monthly[$row['ym']] = (int)$row['total'];
    }
}
$maxMonth = max(1, max($monthly));

require __DIR__ . '/includes/header.php';
?>
    <main>
        <section>
            <h2>Admin Dashboard</h2>
            <p>Welcome, <?= e($_SESSION['name']) ?>. Figures below are read live from the database.</p>

            <div class="card-grid">
<?php foreach ($stats as $label => $total): ?>
                <article class="stat-card">
                    <h3><?= e($label) ?></h3>
                    <p class="stat-number"><?= (int)$total ?></p>
                </article>
<?php endforeach; ?>
            </div>

            <h3>Event registrations by month</h3>
            <div class="chart" role="img" aria-label="Bar chart of event registrations in the last six months">
<?php foreach ($monthly as $ym => $count): ?>
                <div class="chart-row">
                    <span class="chart-label"><?= e(date('M Y', strtotime($ym . '-01'))) ?></span>
                    <div class="chart-track"><div class="chart-bar" style="width: <?= (int)round($count / $maxMonth * 100) ?>%"></div></div>
                    <span class="chart-value"><?= (int)$count ?></span>
                </div>
<?php endforeach; ?>
            </div>

            <h3>Latest user sign-ups</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
                    <tbody>
<?php foreach ($latestUsers as $u): ?>
                        <tr><td><?= e($u['full_name']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['role']) ?></td><td><?= e($u['created_at']) ?></td></tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3>Latest event registrations</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Event</th><th>Registered on</th></tr></thead>
                    <tbody>
<?php foreach ($latestRegs as $r): ?>
                        <tr><td><?= e($r['student']) ?></td><td><?= e($r['event']) ?></td><td><?= e($r['registered_at']) ?></td></tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
