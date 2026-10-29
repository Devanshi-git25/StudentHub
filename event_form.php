<?php
// Practical 12: add / edit event with poster upload
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/event_lib.php';
require_once __DIR__ . '/includes/upload.php';
require_admin();
$conn = db();

$id       = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
$existing = $id > 0 ? event_find($conn, $id) : null;
if ($id > 0 && !$existing) {
    flash_set('error', 'That event no longer exists.');
    redirect('manage_events.php');
}

$pageTitle = $existing ? 'Edit Event' : 'Add Event';
$errors    = [];
$old       = $existing ?? ['title' => '', 'description' => '', 'event_date' => '', 'venue' => '', 'category' => '', 'status' => 'open', 'poster' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors['form'] = 'Your form expired. Please try again.';
    } else {
        [$clean, $errors] = validate_event($_POST);
        $clean['poster'] = $existing['poster'] ?? null;
        $old = $clean;

        // Poster upload (validated before anything is saved)
        $newPoster = null;
        if (!$errors) {
            $uploadError = null;
            $newPoster   = handle_poster_upload($_FILES['poster'] ?? [], $uploadError);
            if ($uploadError) {
                $errors['poster'] = $uploadError;
            }
        }

        if (!$errors) {
            $oldPoster = $existing['poster'] ?? null;
            if ($newPoster) {
                $clean['poster'] = $newPoster;
            } elseif (isset($_POST['remove_poster']) && $existing) {
                $clean['poster'] = null;
            }

            if ($existing) {
                $stmt = $conn->prepare('UPDATE events SET title = ?, description = ?, event_date = ?, venue = ?, category = ?, status = ?, poster = ? WHERE id = ?');
                $stmt->bind_param('sssssssi', $clean['title'], $clean['description'], $clean['event_date'], $clean['venue'], $clean['category'], $clean['status'], $clean['poster'], $existing['id']);
            } else {
                $by   = (int)$_SESSION['user_id'];
                $stmt = $conn->prepare('INSERT INTO events (title, description, event_date, venue, category, status, poster, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssssssi', $clean['title'], $clean['description'], $clean['event_date'], $clean['venue'], $clean['category'], $clean['status'], $clean['poster'], $by);
            }

            if ($stmt->execute()) {
                $stmt->close();
                if ($oldPoster && $oldPoster !== $clean['poster']) {
                    delete_poster_file($oldPoster);                    // old image is no longer used
                }
                audit_log($conn, (int)$_SESSION['user_id'], $existing ? 'event_updated' : 'event_created', $clean['title']);
                flash_set('success', $existing ? 'Event updated successfully.' : 'Event added successfully.');
                redirect('manage_events.php');
            }

            error_log('Event save failed: ' . $conn->error);
            $stmt->close();
            delete_poster_file($newPoster);                            // do not keep an orphan file
            $errors['form'] = 'Could not save the event. Please try again.';
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

            <form method="post" action="event_form.php<?= $existing ? '?id=' . (int)$existing['id'] : '' ?>" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= POSTER_MAX_SIZE ?>">
                <?php if ($existing): ?><input type="hidden" name="id" value="<?= (int)$existing['id'] ?>"><?php endif; ?>

                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" maxlength="100" required value="<?= e($old['title']) ?>"
                           class="<?= isset($errors['title']) ? 'invalid' : '' ?>">
                    <span class="error" role="alert"><?= e($errors['title'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4" maxlength="1000"
                              class="<?= isset($errors['description']) ? 'invalid' : '' ?>"><?= e($old['description']) ?></textarea>
                    <span class="error" role="alert"><?= e($errors['description'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="event_date">Date *</label>
                    <input type="date" id="event_date" name="event_date" required value="<?= e($old['event_date']) ?>"
                           class="<?= isset($errors['event_date']) ? 'invalid' : '' ?>">
                    <span class="error" role="alert"><?= e($errors['event_date'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="venue">Venue *</label>
                    <input type="text" id="venue" name="venue" maxlength="100" required value="<?= e($old['venue']) ?>"
                           class="<?= isset($errors['venue']) ? 'invalid' : '' ?>">
                    <span class="error" role="alert"><?= e($errors['venue'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="category">Category *</label>
                    <select id="category" name="category" class="<?= isset($errors['category']) ? 'invalid' : '' ?>">
                        <option value="">-- Select Category --</option>
<?php foreach (EVENT_CATEGORIES as $c): ?>
                        <option value="<?= e($c) ?>"<?= $old['category'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
<?php endforeach; ?>
                    </select>
                    <span class="error" role="alert"><?= e($errors['category'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="status">Registration status</label>
                    <select id="status" name="status">
<?php foreach (EVENT_STATUS as $st): ?>
                        <option value="<?= e($st) ?>"<?= $old['status'] === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
<?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="poster">Poster (JPG, PNG or WEBP, max 2 MB)</label>
<?php if (!empty($old['poster'])): ?>
                    <p><img class="poster-thumb" src="uploads/posters/<?= e($old['poster']) ?>" alt="Current poster for <?= e($old['title']) ?>"></p>
                    <label><input type="checkbox" name="remove_poster" value="1"> Remove current poster</label><br>
<?php endif; ?>
                    <input type="file" id="poster" name="poster" accept="image/jpeg,image/png,image/webp"
                           class="<?= isset($errors['poster']) ? 'invalid' : '' ?>">
                    <span class="error" role="alert"><?= e($errors['poster'] ?? '') ?></span>
                </div>

                <button type="submit"><?= $existing ? 'Save Changes' : 'Add Event' ?></button>
                <a class="button-link" href="manage_events.php">Cancel</a>
            </form>
        </section>
    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
