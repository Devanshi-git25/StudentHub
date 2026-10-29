<?php
// Practical 14: audit log viewer - admin only
require_once __DIR__ . '/includes/init.php';
require_admin();
$pageTitle = 'Audit Log';
$conn = db();

// Action filter list
$actions = array_column($conn->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetch_all(MYSQLI_ASSOC), 'action');
$action  = in_array($_GET['action'] ?? '', $actions, true) ? $_GET['action'] : '';

$where = ''; $types = ''; $params = [];
if ($action !== '') {
    $where = ' WHERE a.action = ?';
    $types = 's';
    $params = [$action];
}

$stmt = $conn->prepare('SELECT COUNT(*) FROM audit_logs a' . $where);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$perPage = 15;
$pages   = max(1, (int)ceil($total / $perPage));
$page    = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset  = ($page - 1) * $perPage;

$stmt = $conn->prepare(
    'SELECT a.created_at, a.action, a.details, a.ip_address, u.full_name
       FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id' . $where . '
   ORDER BY a.id DESC LIMIT ? OFFSET ?'
);
$stmt->bind_param($types . 'ii', ...array_merge($params, [$perPage, $offset]));
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require __DIR__ . '/includes/header.php';
?>
    <main>
        <section>
            <h2>Audit Log</h2>

            <form method="get" action="audit_logs.php" class="controls">
                <div>
                    <label for="action">Action</label>
                    <select id="action" name="action">
                        <option value="">All actions</option>
<?php foreach ($actions as $a): ?>
                        <option value="<?= e($a) ?>"<?= $action === $a ? ' selected' : '' ?>><?= e($a) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <div class="controls-buttons">
                    <button type="submit">Filter</button>
                    <a class="button-link" href="audit_logs.php">Reset</a>
                </div>
            </form>

            <p class="toolbar"><span><?= (int)$total ?> entr<?= $total === 1 ? 'y' : 'ies' ?></span></p>

            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
                    <tbody>
<?php foreach ($logs as $l): ?>
                        <tr>
                            <td><?= e($l['created_at']) ?></td>
                            <td><?= e($l['full_name'] ?? 'Guest / deleted') ?></td>
                            <td><?= e($l['action']) ?></td>
                            <td><?= e($l['details'] ?? '') ?></td>
                            <td><?= e($l['ip_address'] ?? '') ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= render_pagination($page, $pages, array_filter(['action' => $action]), 'audit_logs.php') ?>
        </section>
    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
