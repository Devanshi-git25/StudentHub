<?php
// Database settings (XAMPP defaults)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'studenthub');

// Session / login settings
define('SESSION_TIMEOUT', 900);   // seconds of inactivity (900 = 15 min). Use 30 to demo timeout quickly.
define('REMEMBER_DAYS', 30);      // remember-me cookie lifetime

// Valid bcrypt hash used to equalise timing when an email is not found
define('DUMMY_HASH', '$2y$10$tges5ZNJUkbO.hSDov0KUOV0P/d7qXVhOKOjbxfwzln6jfKC7Di7q');
