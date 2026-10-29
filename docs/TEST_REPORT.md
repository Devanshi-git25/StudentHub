# StudentHub - Test & Security Report

**Subject:** ITUE203 Web Development Frameworks | **Practical 15** | **Environment:** XAMPP (Apache, PHP 8, MySQL), Chrome

Mark each result after running the test (tick **Pass** or **Fail**, add notes). Take a screenshot for every test you want to use as proof and save it in `screenshots/` using the test ID (for example `T06-login-student.png`).

## 1. Functional test cases

| ID | Module | Steps | Expected result | Result |
|----|--------|-------|-----------------|--------|
| T01 | Registration | Fill the form with valid data and submit | Success message, redirected to login | ☐ Pass ☐ Fail |
| T02 | Registration | Submit with empty fields | Errors shown beside each field, first invalid field focused | ☐ Pass ☐ Fail |
| T03 | Registration | Type a weak password (`abc`) | Strength meter shows weak, password error shown | ☐ Pass ☐ Fail |
| T04 | Registration | Register with an existing email | "Already registered" error, no new user | ☐ Pass ☐ Fail |
| T05 | Registration (server) | Remove validation in browser dev tools and submit a bad mobile number | Server rejects it with an error message | ☐ Pass ☐ Fail |
| T06 | Login | Student logs in with correct details | Redirected to `dashboard.php` | ☐ Pass ☐ Fail |
| T07 | Login | Admin logs in with correct details | Redirected to `admin.php` | ☐ Pass ☐ Fail |
| T08 | Login | Wrong password | "Invalid email or password" (same message for unknown email) | ☐ Pass ☐ Fail |
| T09 | Access control | Open `dashboard.php` while logged out | Redirected to login with a message | ☐ Pass ☐ Fail |
| T10 | Access control | Student opens `admin.php` | 403 Access Denied page | ☐ Pass ☐ Fail |
| T11 | Session | Set `SESSION_TIMEOUT` to 30, log in (no remember-me), wait 40 s, reload | Redirected to login with "session expired" | ☐ Pass ☐ Fail |
| T12 | Session | Click Logout, then press the browser Back button | Login page; protected pages redirect to login | ☐ Pass ☐ Fail |
| T13 | Remember me | Log in with "Remember me", close and reopen the browser | Still logged in; after Logout the cookie no longer works | ☐ Pass ☐ Fail |
| T14 | Users | Admin deactivates a student user; that user tries to log in | "Account is inactive" message | ☐ Pass ☐ Fail |
| T15 | Students | Add a valid student | Appears in the list with a success message | ☐ Pass ☐ Fail |
| T16 | Students | Add a student with an existing enrollment number | Duplicate error shown | ☐ Pass ☐ Fail |
| T17 | Students | Search by name, email and course; apply course/status filters; use pagination | Correct results each time | ☐ Pass ☐ Fail |
| T18 | Students | Click Delete, press Cancel, then Delete again and OK | Cancel keeps the student; OK marks Inactive (soft delete) | ☐ Pass ☐ Fail |
| T19 | Students | Restore an inactive student; "Delete forever" another | Restored becomes Active; permanent delete removes the row | ☐ Pass ☐ Fail |
| T20 | Students | Click Export CSV with a filter applied | CSV downloads with only the filtered rows | ☐ Pass ☐ Fail |
| T21 | Events | Add an event with a valid JPG/PNG poster | Event and poster shown on `events.php` | ☐ Pass ☐ Fail |
| T22 | Events | Upload a `.txt`/`.php` file renamed to `.jpg`, and an image over 2 MB | Both rejected with a clear message | ☐ Pass ☐ Fail |
| T23 | Events | Edit an event and replace the poster | New poster shown, old file removed from `uploads/posters` | ☐ Pass ☐ Fail |
| T24 | Events | Delete an event | Event and its registrations removed | ☐ Pass ☐ Fail |
| T25 | Events | Search and filter by category and open/closed | Correct results | ☐ Pass ☐ Fail |
| T26 | Event registration | Student clicks Register on an open event | "You are registered", count increases, event listed in My Profile | ☐ Pass ☐ Fail |
| T27 | Event registration | Cancel the registration | Registration removed, count decreases | ☐ Pass ☐ Fail |
| T28 | Profile | New user opens My Profile and enters an enrollment number | Student record linked, Register button now works | ☐ Pass ☐ Fail |
| T29 | AJAX | Open Students (AJAX), type in the search box | List updates without page reload; Network tab shows JSON responses | ☐ Pass ☐ Fail |
| T30 | AJAX | Add a student with invalid data | Field errors shown (API returns 422) | ☐ Pass ☐ Fail |
| T31 | AJAX | Add, edit and delete a valid student | Table updates each time (201 / 200 / 200) | ☐ Pass ☐ Fail |
| T32 | API security | Open `api/students.php` in a private window (logged out) | JSON with 401 | ☐ Pass ☐ Fail |
| T33 | API security | In dev tools, send a POST/DELETE without the `X-CSRF-Token` header | JSON with 403 | ☐ Pass ☐ Fail |
| T34 | Dashboard | Open the admin dashboard | Counts match the database, chart shows registrations by month | ☐ Pass ☐ Fail |
| T35 | Users | Deactivate/activate another user; try on your own account | Status changes; own account is blocked | ☐ Pass ☐ Fail |
| T36 | Audit log | Open the audit log after the tests above, filter by action | Entries for login, student/event changes, registrations and denied access | ☐ Pass ☐ Fail |
| T37 | Feedback | Submit valid and invalid feedback; admin opens stored feedback | Valid data saved to `storage/feedback.csv` and `.json`; invalid shows errors | ☐ Pass ☐ Fail |
| T38 | Responsive and theme | View at about 375 px, 768 px and 1280 px wide; switch to Dark Mode | Layout adapts, nothing overflows, dark theme remembered after reload | ☐ Pass ☐ Fail |

## 2. Security checklist

| Area | Measure | Where |
|------|---------|-------|
| SQL injection | Prepared statements for all queries that use input | all PHP pages, `includes/*_lib.php` |
| XSS | Output escaped with `e()` (PHP) and `esc()` (JavaScript) | all pages, `js/list.js`, `js/students_ajax.js` |
| Passwords | `password_hash()` / `password_verify()`, automatic rehash | `register.php`, `login.php` |
| Login errors | Same message for wrong email and wrong password; dummy hash check evens out timing | `login.php` |
| Sessions | New session ID after login, HttpOnly and SameSite cookies, inactivity timeout, full destroy on logout | `includes/init.php`, `logout.php` |
| Remember me | Random token; only its SHA-256 hash is stored; rotated on each use; revoked on logout or deactivation | `includes/init.php` |
| CSRF | Token on every POST form; `X-CSRF-Token` header required for API changes | `includes/init.php`, `includes/api.php` |
| Access control | `require_login()` and `require_admin()` on protected pages and the API; role and status re-checked from the database on each request | `includes/init.php` |
| Validation | Same rules on the client and the server; server never trusts the browser | `register.php`, `includes/student_lib.php`, `includes/event_lib.php` |
| File upload | Real MIME check, 2 MB limit, random file name, scripts blocked in the upload folder | `includes/upload.php`, `uploads/posters/.htaccess` |
| Private data | `storage/`, `includes/` and `sql/` blocked from the web | `.htaccess` files |
| Error handling | Database details go to the server log, users see a generic message; API errors always return JSON | `includes/db.php`, `includes/api.php` |
| CSV injection | Cells starting with `=`, `+`, `-`, `@` are neutralised | `csv_safe()` in `includes/init.php` |
| Audit trail | Logins, failed logins, CRUD actions, exports and denied access are logged | `audit_logs` table |

**Known limitations:** no login rate limiting, no email verification or password reset, the registration form reveals whether an email already exists, plain HTTP on localhost, and `db_test.php` must be removed before real deployment.

## 3. Accessibility checklist

| Item | Status |
|------|--------|
| Semantic landmarks used on every page (`header`, `nav`, `main`, `section`, `aside`, `footer`) | ☐ Checked |
| `<html lang="en">` and a unique page title on each page | ☐ Checked |
| Every form field has a visible `<label>`; related radio buttons use `fieldset` + `legend` | ☐ Checked |
| Error messages sit next to fields, use `role="alert"` and are linked with `aria-describedby` | ☐ Checked |
| Images have meaningful `alt` text (event posters include the event title) | ☐ Checked |
| All features can be used with the keyboard only (Tab, Enter, Space) and the focus outline is visible | ☐ Checked |
| Current page is marked with `aria-current` in menus and pagination | ☐ Checked |
| Text and background colours have enough contrast in light and dark mode (check with Chrome DevTools or Lighthouse) | ☐ Checked |
| Layout works at 375 px, 768 px and 1280 px wide and text can be zoomed to 200% | ☐ Checked |
| Lighthouse Accessibility score recorded (screenshot) | ☐ Score: ____ |
| Not implemented: skip-to-content link | Noted |

## 4. Screenshots / demo checklist (save in `screenshots/`)

1. Home page and navigation (light and dark mode)
2. Registration: invalid input errors, then success
3. Login success (student and admin), failed login, logout
4. Session timeout message and the 403 / unauthorised access screens
5. Events page with posters, event registration, My Profile
6. Admin dashboard, Students list (search/filter), Events management with poster upload
7. Students (AJAX) page with the browser Network tab showing JSON responses
8. Audit log and Users page
9. phpMyAdmin showing the five tables, and `db_test.php`
10. GitHub repository page with the commit history

**Optional 2-3 minute demo video order:** register, login as student, register for an event, logout, login as admin, dashboard, add student, add event with poster, AJAX page, audit log.
