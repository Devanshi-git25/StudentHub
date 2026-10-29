<?php
// Practical 13: tiny helpers shared by every JSON endpoint (centralised responses and errors)

function api_json(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_ok(array $payload = [], int $status = 200): void
{
    api_json($status, array_merge(['ok' => true], $payload));
}

function api_error(int $status, string $message, array $errors = []): void
{
    api_json($status, ['ok' => false, 'message' => $message, 'errors' => (object)$errors]);
}

// Any uncaught problem becomes a clean JSON 500 instead of an HTML error page
set_exception_handler(function (Throwable $ex): void {
    error_log('API exception: ' . $ex->getMessage());
    api_error(500, 'Something went wrong on the server.');
});

// Reads and decodes a JSON request body
function api_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        api_error(400, 'Request body must be valid JSON.');
    }
    return $data;
}

// Logged-in admin required. Changing requests must also carry the CSRF token in X-CSRF-Token.
function api_require_admin(): void
{
    if (!is_logged_in()) {
        api_error(401, 'Please log in first.');
    }
    if (!refresh_session_user()) {
        destroy_session();
        api_error(401, 'Your account is no longer active.');
    }
    if (!is_admin()) {
        audit_log(db(), (int)$_SESSION['user_id'], 'access_denied', 'API ' . substr($_SERVER['REQUEST_URI'] ?? '', 0, 150));
        api_error(403, 'Administrator access is required.');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
            api_error(403, 'Invalid or missing CSRF token. Reload the page and try again.');
        }
    }
}
