<?php
// Practical 10: secure login (password_verify, session regeneration, role redirect, remember-me)
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin.php' : 'dashboard.php');
}

$pageTitle = 'Login';
$error     = '';
$email     = '';

// Messages passed from other pages
$notice = '';
if (isset($_GET['timeout']))  { $notice = 'Your session expired due to inactivity. Please log in again.'; }
if (isset($_GET['auth']))     { $notice = 'Please log in to access that page.'; }
if (isset($_GET['loggedout'])) { $notice = 'You have been logged out successfully.'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = 'Your form expired or was invalid. Please try again.';
    } else {
        $email    = strtolower(clean_text((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        if ($email === '' || $password === '') {
            $error = 'Please enter both email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $conn = db();
            $stmt = $conn->prepare('SELECT id, full_name, email, password_hash, role, status, last_login FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Always run password_verify (even for unknown emails) so response time does not reveal which emails exist
            $passwordOk = password_verify($password, $user['password_hash'] ?? DUMMY_HASH);

            if (!$user || !$passwordOk) {
                $error = 'Invalid email or password.';             // same message for both cases
                audit_log($conn, $user ? (int)$user['id'] : null, 'login_failed', substr($email, 0, 100));
            } elseif ($user['status'] !== 'active') {
                $error = 'Your account is inactive. Please contact the administrator.';
            } else {
                $previousLogin = $user['last_login'];
                login_user($user);                                  // regenerates the session ID
                $_SESSION['previous_login'] = $previousLogin;

                $stmt = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                $stmt->bind_param('i', $user['id']);
                $stmt->execute();
                $stmt->close();

                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                    $stmt->bind_param('si', $newHash, $user['id']);
                    $stmt->execute();
                    $stmt->close();
                }

                if ($remember) {
                    issue_remember_token((int)$user['id']);
                } else {
                    revoke_remember_token((int)$user['id']);
                    clear_remember_cookie();
                }

                audit_log($conn, (int)$user['id'], 'login', $remember ? 'with remember-me' : '');
                redirect($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php');   // role-based redirect
            }
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
    <main>

        <section>
            <h2>Student Login</h2>
            <p>Sign in to access your StudentHub account.</p>

<?php if ($notice): ?>
            <div class="alert alert-info" role="status"><?= e($notice) ?></div>
<?php endif; ?>
<?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

            <form method="post" action="login.php">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" autocomplete="username" required value="<?= e($email) ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                </div>

                <div class="form-group">
                    <label><input type="checkbox" name="remember" value="1"> Remember me for <?= (int)REMEMBER_DAYS ?> days</label>
                </div>

                <button type="submit">Login</button>
            </form>

            <p>New here? <a href="register.php">Create an account</a></p>
        </section>

        <aside>
            <h3>Need Help?</h3>
            <p>If you've forgotten your password, contact the administrator.</p>
        </aside>

    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
