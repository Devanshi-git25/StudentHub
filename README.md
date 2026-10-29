# StudentHub Portal

A simple student portal built for **Web Development Frameworks (ITUE203)**, CHARUSAT, Semester 3, 2026-27.
Students can register, log in, browse events, register for events and view their profile. Admins can manage students, events and users, and see a dashboard with statistics and an audit log.

**Tech:** HTML5, CSS3 (Flexbox/Grid), JavaScript (ES6, Fetch API), PHP 8 (MySQLi), MySQL, Apache (XAMPP)

## Features
- Responsive, accessible pages with a light/dark theme (remembered in localStorage)
- Registration with client-side (regex, password strength) and server-side validation
- Secure login: `password_hash`, sessions, session timeout, remember-me token, role-based access (admin / student)
- Events from MySQL with poster upload, search, filters, pagination and student registration
- Student management: CRUD, search, filter, soft delete, CSV export
- JSON API + Fetch (AJAX) CRUD for students without page reload
- Admin dashboard: statistics, registrations-per-month chart, user activate/deactivate, audit log
- Feedback form saved to CSV and JSON

## Run locally (XAMPP)
1. Install XAMPP and start **Apache** and **MySQL**.
2. Copy this folder to `C:\xampp\htdocs\StudentHub`.
3. Open `http://localhost/phpmyadmin` > **Import** > choose `sql/studenthub.sql` > **Import**.
4. Open `http://localhost/StudentHub/index.html`.
5. Check the database connection at `http://localhost/StudentHub/db_test.php`.

Always use `http://localhost/...`. Do not double-click the files (PHP and Fetch need the server).
Database settings are in `includes/config.php` (XAMPP defaults: user `root`, empty password).

## Test accounts
| Role | Email | Password |
|------|-------|----------|
| Admin | admin@studenthub.com | Admin@123 |
| Student | student@studenthub.com | Student@123 |

## Testing
Full test cases, the security checklist and the accessibility checklist are in **[docs/TEST_REPORT.md](docs/TEST_REPORT.md)**.
Quick check: log in as admin, open the dashboard, then add a student, add an event with a poster, and read the audit log.
To demo the session timeout quickly, set `SESSION_TIMEOUT` to `30` in `includes/config.php`.

## Project structure
```
index.html, about.html, ...   static pages (Practicals 2-4)
events.php, profile.php       events from MySQL + student registration, profile
register.php, login.php       registration and login (Practicals 9-10)
dashboard.php                 student dashboard
admin.php                     admin dashboard (Practical 14)
manage_students.php, student_form.php, students_export.php   Practical 11
manage_events.php, event_form.php                            Practical 12
students_ajax.php, api/students.php, js/students_ajax.js     Practical 13
users_admin.php, audit_logs.php                              Practical 14
feedback.php, feedback_list.php                              Practical 7
events.html, students.html, faq.html + js/list.js + data/    Practical 6 (JSON + Fetch)
includes/                     shared PHP (init, db, header, footer, helpers)
sql/studenthub.sql            database dump (schema + sample data)
docs/TEST_REPORT.md           test, security and accessibility report
screenshots/                  proof screenshots
```

## Database (ER summary)
```
users 1---0..1 students 1---* registrations *---1 events
users 1---* audit_logs        users 1---* events (created_by)
```
Tables: `users`, `students`, `events`, `registrations`, `audit_logs`.

## Practicals covered
| No | Topic |
|----|-------|
| 1-4 | Planning, semantic HTML, responsive CSS, JavaScript interactivity |
| 5 | Registration form validation (`js/register.js`) |
| 6 | JSON + Fetch, search, filter, pagination (`js/list.js`) |
| 7 | PHP form processing to CSV/JSON (`feedback.php`) |
| 8 | MySQL schema and connection (`sql/studenthub.sql`, `includes/db.php`) |
| 9 | Secure registration (`register.php`) |
| 10 | Login, sessions, RBAC, timeout, remember-me |
| 11-14 | Student CRUD, event CRUD + upload, JSON API + AJAX, admin dashboard |
| 15 | Integration, testing, documentation, deployment |

## Known limitations
- No login rate limiting or email verification; no password reset (contact the admin).
- Runs over plain HTTP locally. On HTTPS the session cookies are automatically marked `Secure`.
- Upload-folder protection relies on Apache `.htaccess` files.
- `db_test.php` is for lab use only; remove it before any real deployment.
