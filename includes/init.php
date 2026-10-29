<?php
// Loaded at the top of every PHP page: session, helpers, CSRF, authentication.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------- Helpers ---------- */

function e(?string $text): string          // escape output (prevents XSS)
{
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url)
{
    header('Location: ' . $url);
    exit;
}

function clean_text(string $value): string // strip control characters (keep newlines) and trim
{
    $value = preg_replace('/[^\P{C}\n]+/u', '', $value) ?? '';
    return trim($value);
}

// Stops spreadsheet apps from running a cell as a formula (CSV injection)
function csv_safe(string $value): string
{
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

// Builds Previous / 1 2 3 / Next links that keep the current search and filters
function render_pagination(int $page, int $pages, array $query, string $base): string
{
    if ($pages <= 1) {
        return '';
    }
    $link = function (int $p, string $label, bool $disabled = false, bool $active = false) use ($query, $base): string {
        if ($disabled) {
            return '<span class="page-link disabled">' . e($label) . '</span>';
        }
        $url = $base . '?' . http_build_query(array_merge($query, ['page' => $p]));
        return '<a class="page-link' . ($active ? ' active' : '') . '" href="' . e($url) . '"'
             . ($active ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
    };

    $html = '<div class="pagination" role="navigation" aria-label="Pagination">';
    $html .= $link($page - 1, 'Previous', $page <= 1);
    for ($i = 1; $i <= $pages; $i++) {
        $html .= $link($i, (string)$i, false, $i === $page);
    }
    $html .= $link($page + 1, 'Next', $page >= $pages);
    return $html . '</div>';
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $message];
}

function flash_get(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/* ---------- CSRF protection ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

/* ---------- Authentication ---------- */

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);                      // prevents session fixation
    $_SESSION['user_id']       = (int)$user['id'];
    $_SESSION['name']          = $user['full_name'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['last_activity'] = time();
}

function destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

function remember_cookie_options(int $expires): array
{
    return [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

// Remember-me: cookie = selector:validator. Only SHA-256(validator) is stored in the database.
function issue_remember_token(int $userId): void
{
    $selector  = bin2hex(random_bytes(9));            // 18 hex chars
    $validator = bin2hex(random_bytes(32));
    $hash      = hash('sha256', $validator);
    $days      = REMEMBER_DAYS;

    $stmt = db()->prepare('UPDATE users SET remember_selector = ?, remember_hash = ?, remember_expires = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?');
    $stmt->bind_param('ssii', $selector, $hash, $days, $userId);
    $stmt->execute();
    $stmt->close();

    setcookie('remember', $selector . ':' . $validator, remember_cookie_options(time() + REMEMBER_DAYS * 86400));
}

function revoke_remember_token(int $userId): void
{
    $stmt = db()->prepare('UPDATE users SET remember_selector = NULL, remember_hash = NULL, remember_expires = NULL WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function clear_remember_cookie(): void
{
    setcookie('remember', '', remember_cookie_options(time() - 3600));
    unset($_COOKIE['remember']);
}

function try_remember_login(): bool
{
    if (empty($_COOKIE['remember']) || !is_string($_COOKIE['remember'])) {
        return false;
    }

    $parts = explode(':', $_COOKIE['remember']);
    if (count($parts) !== 2 || !ctype_xdigit($parts[0]) || !ctype_xdigit($parts[1])) {
        clear_remember_cookie();
        return false;
    }
    [$selector, $validator] = $parts;

    $stmt = db()->prepare("SELECT id, full_name, role, remember_hash FROM users WHERE remember_selector = ? AND remember_expires > NOW() AND status = 'active' LIMIT 1");
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !hash_equals((string)$user['remember_hash'], hash('sha256', $validator))) {
        clear_remember_cookie();
        return false;
    }

    login_user($user);
    issue_remember_token((int)$user['id']);           // rotate the token on every use
    return true;
}

function enforce_timeout(): void
{
    if (!is_logged_in()) {
        return;
    }
    if (time() - (int)($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        unset($_SESSION['user_id'], $_SESSION['name'], $_SESSION['role'], $_SESSION['last_activity']);
        if (!try_remember_login()) {                  // a valid remember-me cookie logs the user back in
            destroy_session();
            if (defined('API_REQUEST')) {             // JSON clients get a 401, not a redirect to an HTML page
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => 'Session expired. Please log in again.', 'errors' => new stdClass()]);
                exit;
            }
            redirect('login.php?timeout=1');
        }
    } else {
        $_SESSION['last_activity'] = time();
    }
}

// Re-reads role/status so a deactivated or demoted user loses access immediately
function refresh_session_user(): bool
{
    $stmt = db()->prepare('SELECT full_name, role, status FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || $user['status'] !== 'active') {
        return false;
    }
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = $user['full_name'];
    return true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php?auth=1');
    }
    if (!refresh_session_user()) {
        destroy_session();
        redirect('login.php?auth=1');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        audit_log(db(), (int)$_SESSION['user_id'], 'access_denied', substr($_SERVER['REQUEST_URI'] ?? '', 0, 200));
        http_response_code(403);
        $pageTitle = 'Access Denied';
        require __DIR__ . '/header.php';
        echo '<main><section><h2>403 - Access Denied</h2><p>You do not have permission to view this page. <a href="dashboard.php">Back to your dashboard</a></p></section></main>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

// Runs on every page load
if (is_logged_in()) {
    enforce_timeout();
} else {
    try_remember_login();
}
