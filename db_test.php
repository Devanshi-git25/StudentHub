<?php
// Practical 8: connection test page (delete or protect this before any real deployment)
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'Database Test';

$conn   = db();   // stops with an error message if the connection fails
$tables = ['users', 'students', 'events', 'registrations', 'audit_logs'];
$counts = [];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) AS total FROM `$table`");   // table names are fixed above, not user input
    $counts[$table] = $result ? (int)$result->fetch_assoc()['total'] : null;
}

require __DIR__ . '/includes/header.php';
?>
    <main>
        <section>
            <h2>Database Connection Test</h2>
            <p class="success">Connected to <strong><?= e(DB_NAME) ?></strong> on <?= e(DB_HOST) ?> (server version <?= e($conn->server_info) ?>).</p>

            <table class="data-table">
                <thead><tr><th>Table</th><th>Rows</th></tr></thead>
                <tbody>
<?php foreach ($counts as $table => $total): ?>
                    <tr><td><?= e($table) ?></td><td><?= $total === null ? 'missing' : $total ?></td></tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
