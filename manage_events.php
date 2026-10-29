<?php
// Practical 12: event list with search, category + open/closed filter, delete
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/event_lib.php';
require_once __DIR__ . '/includes/upload.php';
require_admin();
$pageTitle = 'Manage Events';
$conn = db();

/* ---------- Delete (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        flash_set('error', 'Your form expired. Please try again.');
        redirect('manage_events.php');
    }

    $event = event_find($conn, (int)($_POST['id'] ?? 0));
    if (!$event) {
        flash_set('error', 'That event no longer exists.');
    } else {
        $stmt = $conn->prepare('DELETE FROM events WHERE id = ?');
        $stmt->bind_param('i', $event['id']);
        if ($stmt->execute()) {
            delete_poster_file($event['poster']);
            audit_log($conn, (int)$_SESSION['user_id'], 'event_deleted', $event['title']);
            flash_set('success', '"' . $event['title'] . '" was deleted.');
        } else {
            flash_set('error', 'The event could not be deleted.');
        }
        $stmt->close();
    }
    redirect('manage_events.php');
}

/* ---------- List (GET) ---------- */
$search   = trim((string)($_GET['search'] ?? ''));
$category = in_array($_GET['category'] ?? '', EVENT_CATEGORIES, true) ? $_GET['category'] : '';
$status   = in_array($_GET['status'] ?? '', EVENT_STATUS, true) ? $_GET['status'] : '';

$where = []; $types = ''; $params = [];
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = '(title LIKE ? OR venue LIKE ?)';
    $types .= 'ss';
    array_push($params, $like, $like);
}
if ($category !== '') { $where[] = 'category = ?'; $types .= 's'; $params[] = $category; }
if ($status !== '')   { $where[] = 'status = ?';   $types .= 's'; $params[] = $status; }
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$stmt = $conn->prepare('SELECT COUNT(*) FROM events' . $sqlWhere);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$perPage = 8;
$pages   = max(1, (int)ceil($total / $perPage));
$page    = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset  = ($page - 1) * $perPage;

$stmt = $conn->prepare('SELECT id, title, event_date, venue, category, poster, status FROM events' . $sqlWhere . ' ORDER BY event_date DESC LIMIT ? OFFSET ?');
$stmt->bind_param($types . 'ii', ...array_merge($params, [$perPage, $offset]));
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$query = array_filter(['search' => $search, 'category' => $category, 'status' => $status], fn($v) => $v !== '');

require __DIR__ . '/includes/header.php';
?>
    <main>
        <section>
            <h2>Manage Events</h2>

            <form method="get" action="manage_events.php" class="controls">
                <div>
                    <label for="search">Search</label>
                    <input type="search" id="search" name="search" placeholder="Title or venue..." value="<?= e($search) ?>">
                </div>
                <div>
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="">All categories</option>
<?php foreach (EVENT_CATEGORIES as $c): ?>
                        <option value="<?= e($c) ?>"<?= $category === $c ? ' selected' : '' ?>><?= e($c) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Open and closed</option>
                        <option value="open"<?= $status === 'open' ? ' selected' : '' ?>>Open</option>
                        <option value="closed"<?= $status === 'closed' ? ' selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="controls-buttons">
                    <button type="submit">Filter</button>
                    <a class="button-link" href="manage_events.php">Reset</a>
                </div>
            </form>

            <p class="toolbar">
                <a class="button-link" href="event_form.php">+ Add Event</a>
                <span><?= (int)$total ?> event(s) found</span>
            </p>

<?php if (!$events): ?>
            <p class="empty">No events match your filters.</p>
<?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Poster</th><th>Title</th><th>Date</th><th>Venue</th><th>Category</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
<?php foreach ($events as $ev): ?>
                        <tr>
                            <td>
<?php if ($ev['poster']): ?>
                                <img class="poster-thumb" src="uploads/posters/<?= e($ev['poster']) ?>" alt="Poster for <?= e($ev['title']) ?>">
<?php else: ?>
                                <small>No poster</small>
<?php endif; ?>
                            </td>
                            <td><?= e($ev['title']) ?></td>
                            <td><?= e($ev['event_date']) ?></td>
                            <td><?= e($ev['venue']) ?></td>
                            <td><?= e($ev['category']) ?></td>
                            <td><span class="tag tag-<?= $ev['status'] === 'open' ? 'active' : 'inactive' ?>"><?= e(ucfirst($ev['status'])) ?></span></td>
                            <td class="actions">
                                <a href="event_form.php?id=<?= (int)$ev['id'] ?>">Edit</a>
                                <form method="post" action="manage_events.php" data-confirm="Delete '<?= e($ev['title']) ?>'? Its registrations will also be removed.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
                                    <button type="submit" class="link-button danger">Delete</button>
                                </form>
                            </td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= render_pagination($page, $pages, $query, 'manage_events.php') ?>
<?php endif; ?>
        </section>
    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
