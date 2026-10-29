<?php
// Event helpers (Practical 12)
const EVENT_CATEGORIES = ['Technical', 'Cultural', 'Sports', 'Workshop', 'Career'];
const EVENT_STATUS     = ['open', 'closed'];

function event_find(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT id, title, description, event_date, venue, category, poster, status FROM events WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Returns [cleanData, errors]
function validate_event(array $in): array
{
    $text = fn(string $k): string => is_string($in[$k] ?? null) ? clean_text($in[$k]) : '';

    $d = [
        'title'       => preg_replace('/\s+/', ' ', $text('title')),
        'description' => $text('description'),
        'event_date'  => $text('event_date'),
        'venue'       => preg_replace('/\s+/', ' ', $text('venue')),
        'category'    => $text('category'),
        'status'      => $text('status') ?: 'open',
    ];
    $errors = [];

    $len = mb_strlen($d['title']);
    if ($len < 3 || $len > 100) {
        $errors['title'] = 'Title must be 3-100 characters.';
    }
    if (mb_strlen($d['description']) > 1000) {
        $errors['description'] = 'Description must be 1000 characters or fewer.';
    }

    $date = DateTime::createFromFormat('Y-m-d', $d['event_date']);
    if (!$date || $date->format('Y-m-d') !== $d['event_date']) {
        $errors['event_date'] = 'Please choose a valid date.';
    }

    $len = mb_strlen($d['venue']);
    if ($len < 2 || $len > 100) {
        $errors['venue'] = 'Venue must be 2-100 characters.';
    }
    if (!in_array($d['category'], EVENT_CATEGORIES, true)) {
        $errors['category'] = 'Please choose a category.';
    }
    if (!in_array($d['status'], EVENT_STATUS, true)) {
        $errors['status'] = 'Status must be open or closed.';
    }
    return [$d, $errors];
}

// The students row linked to the logged-in user (null if none yet)
function linked_student(mysqli $conn): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    $stmt = $conn->prepare('SELECT id, enrollment_no, name, email, course, year, status FROM students WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
