<?php
// Practical 7: PHP form processing, server-side validation, CSV + JSON storage
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'Feedback';

const STORAGE_DIR = __DIR__ . '/storage';
const CSV_FILE    = STORAGE_DIR . '/feedback.csv';
const JSON_FILE   = STORAGE_DIR . '/feedback.json';

function save_feedback(array $record): bool
{
    if (!is_dir(STORAGE_DIR) && !mkdir(STORAGE_DIR, 0755, true)) {
        return false;
    }

    // --- CSV (append one row; lock the file so two submissions cannot mix) ---
    $csv = fopen(CSV_FILE, 'a');
    if (!$csv) {
        return false;
    }
    flock($csv, LOCK_EX);
    if (filesize(CSV_FILE) === 0) {
        fputcsv($csv, ['id', 'name', 'email', 'rating', 'comments', 'submitted_at'], ',', '"', '\\');
    }
    fputcsv($csv, [
        $record['id'],
        csv_safe($record['name']),
        csv_safe($record['email']),
        $record['rating'],
        csv_safe($record['comments']),
        $record['submitted_at'],
    ], ',', '"', '\\');
    fflush($csv);
    flock($csv, LOCK_UN);
    fclose($csv);

    // --- JSON (read, add, write back while holding the lock) ---
    $json = fopen(JSON_FILE, 'c+');
    if (!$json) {
        return false;
    }
    flock($json, LOCK_EX);
    $existing = json_decode(stream_get_contents($json), true);
    if (!is_array($existing)) {
        $existing = [];
    }
    $existing[] = $record;
    ftruncate($json, 0);
    rewind($json);
    fwrite($json, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($json);
    flock($json, LOCK_UN);
    fclose($json);

    return true;
}

$ratings = ['Excellent', 'Good', 'Average', 'Poor'];
$errors  = [];
$old     = ['name' => '', 'email' => '', 'rating' => 'Excellent', 'comments' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors['form'] = 'Your form expired or was invalid. Please try again.';
    } else {
        // Sanitize
        $old['name']     = preg_replace('/\s+/', ' ', clean_text((string)($_POST['name'] ?? '')));
        $old['email']    = strtolower(clean_text((string)($_POST['email'] ?? '')));
        $old['rating']   = (string)($_POST['rating'] ?? '');
        $old['comments'] = clean_text((string)($_POST['comments'] ?? ''));

        // Validate
        if ($old['name'] === '') {
            $errors['name'] = 'Name is required.';
        } elseif (!preg_match("/^[A-Za-z][A-Za-z .'-]{1,59}$/", $old['name'])) {
            $errors['name'] = 'Name must be 2-60 characters (letters, spaces, . \' - only).';
        }

        if ($old['email'] === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 100) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (!in_array($old['rating'], $ratings, true)) {
            $errors['rating'] = 'Please choose a rating from the list.';
        }

        $length = mb_strlen($old['comments']);
        if ($length < 10) {
            $errors['comments'] = 'Comments must be at least 10 characters.';
        } elseif ($length > 1000) {
            $errors['comments'] = 'Comments must be 1000 characters or fewer.';
        }

        // Store
        if (!$errors) {
            $record = [
                'id'           => bin2hex(random_bytes(6)),
                'name'         => $old['name'],
                'email'        => $old['email'],
                'rating'       => $old['rating'],
                'comments'     => $old['comments'],
                'submitted_at' => date('Y-m-d H:i:s'),
            ];

            if (save_feedback($record)) {
                flash_set('success', 'Thank you! Your feedback has been saved.');
                redirect('feedback.php');             // Post/Redirect/Get: refreshing will not resubmit
            }
            $errors['form'] = 'Sorry, we could not save your feedback. Please try again later.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
    <main>

        <section>
            <h2>Feedback Form</h2>
            <p>We value your feedback. Please share your experience with StudentHub.</p>

<?php if (isset($errors['form'])): ?>
            <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

            <form method="post" action="feedback.php" novalidate>
                <?= csrf_field() ?>

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
                    <label for="rating">Rating *</label>
                    <select id="rating" name="rating" class="<?= isset($errors['rating']) ? 'invalid' : '' ?>">
<?php foreach ($ratings as $rating): ?>
                        <option value="<?= e($rating) ?>"<?= $old['rating'] === $rating ? ' selected' : '' ?>><?= e($rating) ?></option>
<?php endforeach; ?>
                    </select>
                    <span class="error" role="alert"><?= e($errors['rating'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="comments">Comments * (10-1000 characters)</label>
                    <textarea id="comments" name="comments" rows="5" maxlength="1000" required
                              class="<?= isset($errors['comments']) ? 'invalid' : '' ?>"><?= e($old['comments']) ?></textarea>
                    <span class="error" role="alert"><?= e($errors['comments'] ?? '') ?></span>
                </div>

                <button type="submit">Submit Feedback</button>
            </form>
        </section>

        <aside>
            <h3>Thank You!</h3>
            <p>Your suggestions help us improve StudentHub for all students.</p>
<?php if (is_admin()): ?>
            <p><a href="feedback_list.php">View stored feedback</a></p>
<?php endif; ?>
        </aside>

    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
