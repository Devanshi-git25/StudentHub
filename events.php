<?php
// Integration: public events page read from MySQL (posters included) + student event registration
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/event_lib.php';
$pageTitle = 'Events';
$conn = db();

// ---------- Register / cancel (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'events.php';
    parse_str((string)($_POST['return'] ?? ''), $ret);
    $keep = array_intersect_key($ret, array_flip(['search', 'category', 'status', 'page']));
    $keep = array_filter(array_map(fn($v) => is_scalar($v) ? (string)$v : '', $keep), fn($v) => $v !== '');
    if ($keep) {
        $back .= '?' . http_build_query($keep);
    }

    if (!csrf_valid()) {
        flash_set('error', 'Your form expired. Please try again.');
        redirect($back);
    }
    require_login();

    if (($_SESSION['role'] ?? '') !== 'student') {
        flash_set('error', 'Only student accounts can register for events.');
        redirect($back);
    }

    $student = linked_student($conn);
    if (!$student) {
        flash_set('info', 'Please complete your student profile (enrollment number) before registering for events.');
        redirect('profile.php');
    }
    if ($student['status'] !== 'active') {
        flash_set('error', 'Your student record is inactive. Please contact the administrator.');
        redirect($back);
    }

    $event  = event_find($conn, (int)($_POST['event_id'] ?? 0));
    $action = (string)($_POST['action'] ?? '');

    if (!$event) {
        flash_set('error', 'That event no longer exists.');
    } elseif ($event['status'] !== 'open' || $event['event_date'] < date('Y-m-d')) {
        flash_set('error', 'Registration for "' . $event['title'] . '" is closed.');
    } elseif ($action === 'register') {
        $stmt = $conn->prepare('INSERT INTO registrations (student_id, event_id) VALUES (?, ?)');
        $stmt->bind_param('ii', $student['id'], $event['id']);
        if ($stmt->execute()) {
            audit_log($conn, (int)$_SESSION['user_id'], 'event_registered', $student['enrollment_no'] . ' -> ' . $event['title']);
            flash_set('success', 'You are registered for "' . $event['title'] . '".');
        } elseif ($conn->errno === 1062) {
            flash_set('info', 'You are already registered for this event.');
        } else {
            error_log('Event registration failed: ' . $conn->error);
            flash_set('error', 'Could not complete your registration. Please try again.');
        }
        $stmt->close();
    } elseif ($action === 'cancel') {
        $stmt = $conn->prepare('DELETE FROM registrations WHERE student_id = ? AND event_id = ?');
        $stmt->bind_param('ii', $student['id'], $event['id']);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            audit_log($conn, (int)$_SESSION['user_id'], 'registration_cancelled', $student['enrollment_no'] . ' -> ' . $event['title']);
            flash_set('success', 'Your registration for "' . $event['title'] . '" was cancelled.');
        } else {
            flash_set('info', 'You were not registered for this event.');
        }
        $stmt->close();
    } else {
        flash_set('error', 'Unknown action.');
    }
    redirect($back);
}

// ---------- List (GET) ----------
$search   = trim((string)($_GET['search'] ?? ''));
$category = in_array($_GET['category'] ?? '', EVENT_CATEGORIES, true) ? $_GET['category'] : '';
$status   = in_array($_GET['status'] ?? '', EVENT_STATUS, true) ? $_GET['status'] : '';

$where = []; $types = ''; $params = [];
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = '(title LIKE ? OR venue LIKE ? OR description LIKE ?)';
    $types  .= 'sss';
    array_push($params, $like, $like, $like);
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

$perPage = 6;
$pages   = max(1, (int)ceil($total / $perPage));
$page    = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset  = ($page - 1) * $perPage;

$myStudent = (($_SESSION['role'] ?? '') === 'student') ? linked_student($conn) : null;
$studentId = $myStudent ? (int)$myStudent['id'] : 0;

$stmt = $conn->prepare(
    'SELECT e.id, e.title, e.description, e.event_date, e.venue, e.category, e.poster, e.status,
            (SELECT COUNT(*) FROM registrations r  WHERE r.event_id = e.id) AS reg_count,
            (SELECT COUNT(*) FROM registrations r2 WHERE r2.event_id = e.id AND r2.student_id = ?) AS joined
       FROM events e' . $sqlWhere . '
   ORDER BY (e.status = \'open\') DESC, e.event_date ASC
      LIMIT ? OFFSET ?'
);
$bind = array_merge([$studentId], $params, [$perPage, $offset]);
$stmt->bind_param('i' . $types . 'ii', ...$bind);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$query       = array_filter(['search' => $search, 'category' => $category, 'status' => $status], fn($v) => $v !== '');
$returnQuery = http_build_query(array_merge($query, ['page' => $page]));
$today       = date('Y-m-d');

require __DIR__ . '/includes/header.php';
?>
    <main>
        <section>
            <h2>Upcoming Events</h2>
            <p>Explore campus events, workshops, competitions and cultural activities.</p>

            <form method="get" action="events.php" class="controls">
                <div>
                    <label for="search">Search</label>
                    <input type="search" id="search" name="search" placeholder="Title, venue or keyword..." value="<?= e($search) ?>">
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
                    <label for="status">Registration</label>
                    <select id="status" name="status">
                        <option value="">Open and closed</option>
                        <option value="open"<?= $status === 'open' ? ' selected' : '' ?>>Open</option>
                        <option value="closed"<?= $status === 'closed' ? ' selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="controls-buttons">
                    <button type="submit">Filter</button>
                    <a class="button-link" href="events.php">Reset</a>
                </div>
            </form>

            <p class="toolbar">
                <span><?= (int)$total ?> event(s) found</span>
<?php if (is_admin()): ?>
                <a class="button-link" href="event_form.php">+ Add Event</a>
<?php endif; ?>
            </p>

<?php if (!$events): ?>
            <p class="empty">No events match your search.</p>
<?php else: ?>
            <div class="card-grid">
<?php foreach ($events as $ev):
          $isPast   = $ev['event_date'] < $today;
          $canJoin  = $ev['status'] === 'open' && !$isPast;
          $joined   = (int)$ev['joined'] > 0;
?>
                <article class="card event-card">
<?php if ($ev['poster']): ?>
                    <img class="event-poster" src="uploads/posters/<?= e($ev['poster']) ?>" alt="Poster for <?= e($ev['title']) ?>">
<?php endif; ?>
                    <p>
                        <span class="tag"><?= e($ev['category']) ?></span>
                        <span class="tag tag-<?= $canJoin ? 'active' : 'inactive' ?>"><?= $canJoin ? 'Open' : ($isPast ? 'Ended' : 'Closed') ?></span>
                    </p>
                    <h3><?= e($ev['title']) ?></h3>
                    <p><strong>Date:</strong> <?= e(date('j F Y', strtotime($ev['event_date']))) ?></p>
                    <p><strong>Venue:</strong> <?= e($ev['venue']) ?></p>
<?php if ($ev['description'] !== null && $ev['description'] !== ''): ?>
                    <p><?= e(mb_strimwidth($ev['description'], 0, 160, '...')) ?></p>
<?php endif; ?>
                    <p class="reg-count"><?= (int)$ev['reg_count'] ?> registered</p>

                    <div class="card-actions">
<?php if (!is_logged_in()): ?>
<?php if ($canJoin): ?>
                        <a class="button-link" href="login.php?auth=1">Login to register</a>
<?php endif; ?>
<?php elseif (is_admin()): ?>
                        <a class="button-link" href="event_form.php?id=<?= (int)$ev['id'] ?>">Edit</a>
<?php elseif ($joined): ?>
                        <span class="tag tag-active">&#10003; You are registered</span>
<?php if ($canJoin): ?>
                        <form method="post" action="events.php" data-confirm="Cancel your registration for <?= e($ev['title']) ?>?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="return" value="<?= e($returnQuery) ?>">
                            <button type="submit" class="link-button danger">Cancel</button>
                        </form>
<?php endif; ?>
<?php elseif ($canJoin): ?>
                        <form method="post" action="events.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
                            <input type="hidden" name="action" value="register">
                            <input type="hidden" name="return" value="<?= e($returnQuery) ?>">
                            <button type="submit">Register</button>
                        </form>
<?php else: ?>
                        <small>Registration closed</small>
<?php endif; ?>
                    </div>
                </article>
<?php endforeach; ?>
            </div>
            <?= render_pagination($page, $pages, $query, 'events.php') ?>
<?php endif; ?>
        </section>

        <aside>
            <h3>Event Reminder</h3>
            <p>Register early for workshops and competitions as seats are limited.</p>
<?php if (is_logged_in() && !is_admin()): ?>
            <p><a href="profile.php">See my registrations</a></p>
<?php endif; ?>
        </aside>
    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
