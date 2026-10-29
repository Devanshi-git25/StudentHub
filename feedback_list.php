<?php
// Practical 7 (intermediate): show stored feedback on a webpage - admin only
require_once __DIR__ . '/includes/init.php';
require_admin();
$pageTitle = 'Stored Feedback';

$file    = __DIR__ . '/storage/feedback.json';
$records = [];
if (is_file($file)) {
    $decoded = json_decode((string)file_get_contents($file), true);
    if (is_array($decoded)) {
        $records = array_reverse($decoded);           // newest first
    }
}

require __DIR__ . '/includes/header.php';
?>
    <main>
        <section>
            <h2>Stored Feedback (<?= count($records) ?>)</h2>
<?php if (!$records): ?>
            <p>No feedback has been submitted yet.</p>
<?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Date</th><th>Name</th><th>Email</th><th>Rating</th><th>Comments</th></tr>
                    </thead>
                    <tbody>
<?php foreach ($records as $r): ?>
                        <tr>
                            <td><?= e($r['submitted_at'] ?? '') ?></td>
                            <td><?= e($r['name'] ?? '') ?></td>
                            <td><?= e($r['email'] ?? '') ?></td>
                            <td><?= e($r['rating'] ?? '') ?></td>
                            <td><?= nl2br(e($r['comments'] ?? '')) ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
<?php endif; ?>
        </section>
    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
