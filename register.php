<?php
// Practical 9: secure registration (MySQLi prepared statements, duplicate check, password_hash, CSRF, audit log)
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin.php' : 'dashboard.php');
}

$pageTitle = 'Register';
$courses   = ['BTech CSE', 'BTech IT', 'BTech Electronics', 'BTech Mechanical'];
$years     = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
$genders   = ['Male', 'Female', 'Other'];

$errors = [];
$old    = ['fullName' => '', 'email' => '', 'mobile' => '', 'course' => '', 'year' => '', 'gender' => '', 'terms' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors['form'] = 'Your form expired or was invalid. Please try again.';
    } else {
        // Sanitize
        $old['fullName'] = preg_replace('/\s+/', ' ', clean_text((string)($_POST['fullName'] ?? '')));
        $old['email']    = strtolower(clean_text((string)($_POST['email'] ?? '')));
        $old['mobile']   = clean_text((string)($_POST['mobile'] ?? ''));
        $old['course']   = (string)($_POST['course'] ?? '');
        $old['year']     = (string)($_POST['year'] ?? '');
        $old['gender']   = (string)($_POST['gender'] ?? '');
        $old['terms']    = isset($_POST['terms']) ? '1' : '';
        $password        = (string)($_POST['password'] ?? '');
        $confirm         = (string)($_POST['confirmPassword'] ?? '');

        // Validate (same rules as js/register.js - the server never trusts the browser)
        if ($old['fullName'] === '') {
            $errors['fullName'] = 'Full name is required.';
        } elseif (!preg_match('/^[A-Za-z]+(?: [A-Za-z]+)+$/', $old['fullName']) || strlen($old['fullName']) > 60) {
            $errors['fullName'] = 'Enter first and last name (letters only, max 60 characters).';
        }

        if ($old['email'] === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 100) {
            $errors['email'] = 'Enter a valid email, e.g. name@example.com.';
        }

        if ($old['mobile'] === '') {
            $errors['mobile'] = 'Mobile number is required.';
        } elseif (!preg_match('/^[6-9]\d{9}$/', $old['mobile'])) {
            $errors['mobile'] = 'Enter a valid 10-digit number starting with 6-9.';
        }

        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password) || strlen($password) > 72) {
            $errors['password'] = 'Use 8-72 characters with uppercase, lowercase, a number and a symbol.';
        }

        if ($confirm === '') {
            $errors['confirmPassword'] = 'Please confirm your password.';
        } elseif ($password !== $confirm) {
            $errors['confirmPassword'] = 'Passwords do not match.';
        }

        if (!in_array($old['course'], $courses, true)) {
            $errors['course'] = 'Please select your course.';
        }
        if (!in_array($old['year'], $years, true)) {
            $errors['year'] = 'Please select your year.';
        }
        if (!in_array($old['gender'], $genders, true)) {
            $errors['gender'] = 'Please select a gender.';
        }
        if ($old['terms'] !== '1') {
            $errors['terms'] = 'You must accept the terms.';
        }

        // Duplicate email check
        if (!$errors) {
            $conn = db();
            $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $old['email']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors['email'] = 'This email is already registered. Try logging in instead.';
            }
            $stmt->close();
        }

        // Insert
        if (!$errors) {
            $hash = password_hash($password, PASSWORD_DEFAULT);   // never store the plain password
            $stmt = $conn->prepare('INSERT INTO users (full_name, email, mobile, password_hash, course, year, gender) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('sssssss', $old['fullName'], $old['email'], $old['mobile'], $hash, $old['course'], $old['year'], $old['gender']);

            if ($stmt->execute()) {
                $newId = (int)$conn->insert_id;
                $stmt->close();
                audit_log($conn, $newId, 'register', 'New account: ' . $old['email']);
                flash_set('success', 'Registration successful! You can now log in.');
                redirect('login.php');
            } elseif ($conn->errno === 1062) {                     // duplicate key (two people registered at the same moment)
                $errors['email'] = 'This email is already registered. Try logging in instead.';
            } else {
                error_log('Registration insert failed: ' . $conn->error);
                $errors['form'] = 'Something went wrong while creating your account. Please try again.';
            }
            $stmt->close();
        }
    }
}

$extraScripts = ['js/register.js'];
require __DIR__ . '/includes/header.php';
?>
    <main>

        <section>
            <h2>Student Registration</h2>
            <p>Create your StudentHub account. Fields marked * are required.</p>

<?php if (isset($errors['form'])): ?>
            <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

            <form id="registerForm" method="post" action="register.php" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="fullName">Full Name *</label>
                    <input type="text" id="fullName" name="fullName" autocomplete="name" required maxlength="60"
                           value="<?= e($old['fullName']) ?>" class="<?= isset($errors['fullName']) ? 'invalid' : '' ?>"
                           aria-describedby="fullName-error">
                    <span class="error" id="fullName-error" role="alert"><?= e($errors['fullName'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="regEmail">Email *</label>
                    <input type="email" id="regEmail" name="email" autocomplete="email" required maxlength="100"
                           value="<?= e($old['email']) ?>" class="<?= isset($errors['email']) ? 'invalid' : '' ?>"
                           aria-describedby="regEmail-error">
                    <span class="error" id="regEmail-error" role="alert"><?= e($errors['email'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="mobile">Mobile Number *</label>
                    <input type="tel" id="mobile" name="mobile" maxlength="10" placeholder="10-digit number" required
                           value="<?= e($old['mobile']) ?>" class="<?= isset($errors['mobile']) ? 'invalid' : '' ?>"
                           aria-describedby="mobile-error">
                    <span class="error" id="mobile-error" role="alert"><?= e($errors['mobile'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="regPassword">Password *</label>
                    <input type="password" id="regPassword" name="password" autocomplete="new-password" required
                           class="<?= isset($errors['password']) ? 'invalid' : '' ?>"
                           aria-describedby="regPassword-error strengthText">
                    <div class="strength-bar"><div id="strengthFill"></div></div>
                    <small id="strengthText">Use 8+ characters with upper, lower, number and symbol.</small>
                    <span class="error" id="regPassword-error" role="alert"><?= e($errors['password'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password *</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" autocomplete="new-password" required
                           class="<?= isset($errors['confirmPassword']) ? 'invalid' : '' ?>"
                           aria-describedby="confirmPassword-error">
                    <span class="error" id="confirmPassword-error" role="alert"><?= e($errors['confirmPassword'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="course">Course *</label>
                    <select id="course" name="course" required class="<?= isset($errors['course']) ? 'invalid' : '' ?>"
                            aria-describedby="course-error">
                        <option value="">-- Select Course --</option>
<?php foreach ($courses as $c): ?>
                        <option value="<?= e($c) ?>"<?= $old['course'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
<?php endforeach; ?>
                    </select>
                    <span class="error" id="course-error" role="alert"><?= e($errors['course'] ?? '') ?></span>
                </div>

                <div class="form-group">
                    <label for="year">Year *</label>
                    <select id="year" name="year" required class="<?= isset($errors['year']) ? 'invalid' : '' ?>"
                            aria-describedby="year-error">
                        <option value="">-- Select Year --</option>
<?php foreach ($years as $y): ?>
                        <option value="<?= e($y) ?>"<?= $old['year'] === $y ? ' selected' : '' ?>><?= e($y) ?></option>
<?php endforeach; ?>
                    </select>
                    <span class="error" id="year-error" role="alert"><?= e($errors['year'] ?? '') ?></span>
                </div>

                <fieldset class="form-group" aria-describedby="gender-error">
                    <legend>Gender *</legend>
<?php foreach ($genders as $g): ?>
                    <label><input type="radio" name="gender" value="<?= e($g) ?>"<?= $old['gender'] === $g ? ' checked' : '' ?>> <?= e($g) ?></label>
<?php endforeach; ?>
                    <span class="error" id="gender-error" role="alert"><?= e($errors['gender'] ?? '') ?></span>
                </fieldset>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="terms" name="terms" value="1"<?= $old['terms'] === '1' ? ' checked' : '' ?>
                               aria-describedby="terms-error">
                        I accept the terms and conditions *
                    </label>
                    <span class="error" id="terms-error" role="alert"><?= e($errors['terms'] ?? '') ?></span>
                </div>

                <button type="submit">Register</button>
                <button type="reset">Reset</button>
            </form>
        </section>

        <aside>
            <h3>Already registered?</h3>
            <p><a href="login.php">Go to Login</a></p>
        </aside>

    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
