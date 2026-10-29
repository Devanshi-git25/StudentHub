<?php
// Practical 14 (intermediate): list users and switch them active / inactive - admin only
require_once __DIR__ . '/includes/init.php';
require_admin();
$pageTitle = 'Users';
$conn = db();
$me   = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        flash_set('error', 'Your form expired. Please try again.');
        redirect('users_admin.php');
    }

    $targetId = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare('SELECT id, full_name, email, status FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $targetId);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$target) {
        flash_set('error', 'That user no longer exists.');
    } elseif ($targetId === $me) {
        flash_set('error', 'You cannot deactivate your own account.');
    } else {
        $newStatus = $target['status'] === 'active' ? 'inactive' : 'active';
        $stmt = $conn->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $newStatus, $targetId);
        if ($stmt->execute()) {
            if ($newStatus === 'inactive') {
                revoke_remember_token($targetId);        // stop remember-me logins straight away
            }
            audit_log($conn, $me, 'user_' . ($newStatus === 'active' ? 'activated' : 'deactivated'), $target['email']);
            flash_set('success', $target['full_name'] . ' is now ' . $newStatus . '.');
        } else {
            flash_set('error', 'Could not change the user status.');
        }
        $stmt->close();
    }
    redirect('users_admin.php');
}

$search = trim((string)($_GET['search'] ?? ''));
$where = ''; $types = ''; $params = [];
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where = ' WHERE full_name LIKE ? OR email LIKE ?';
    $types = 'ss';
    $params = [$like, $like];
}

$stmt = $conn->prepare('SELECT COUNT(*) FROM users' . $where);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$perPage = 10;
$pages   = max(1, (int)ceil($total / $perPage));
$page    = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset  = ($page - 1) * $perPage;

$stmt = $conn->prepare('SELECT id, full_name, email, role, status, last_login, created_at FROM users' . $where . ' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?');
$stmt->bind_param($types . 'ii', ...array_merge($params, [$perPage, $offset]));
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require __DIR__ . '/includes/header.php';
?>
    <main>
        <section>
            <h2>Users</h2>

            <form method="get" action="users_admin.php" class="controls">
                <div>
                    <label for="search">Search</label>
                    <input type="search" id="search" name="search" placeholder="Name or email..." value="<?= e($search) ?>">
                </div>
                <div class="controls-buttons">
                    <button type="submit">Search</button>
                    <a class="button-link" href="users_admin.php">Reset</a>
                </div>
            </form>

            <p class="toolbar"><span><?= (int)$total ?> user(s)</span></p>

            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Joined</th><th>Action</th></tr></thead>
                    <tbody>
<?php foreach ($users as $u): ?>
                        <tr class="<?= $u['status'] === 'inactive' ? 'row-inactive' : '' ?>">
                            <td><?= e($u['full_name']) ?></td>
                            <td><?= e($u['email']) ?></td>
                            <td><?= e($u['role']) ?></td>
                            <td><span class="tag tag-<?= e($u['status']) ?>"><?= e(ucfirst($u['status'])) ?></span></td>
                            <td><?= e($u['last_login'] ?? 'Never') ?></td>
                            <td><?= e($u['created_at']) ?></td>
                            <td class="actions">
<?php if ((int)$u['id'] === $me): ?>
                                <small>(you)</small>
<?php else: ?>
                                <form method="post" action="users_admin.php" data-confirm="<?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?> <?= e($u['full_name']) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="link-button"><?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                </form>
<?php endif; ?>
                            </td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= render_pagination($page, $pages, array_filter(['search' => $search]), 'users_admin.php') ?>
        </section>
    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
