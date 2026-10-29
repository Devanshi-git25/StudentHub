<?php
// Practical 10: logout (POST + CSRF so other sites cannot log a user out)
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    redirect('index.html');
}

$userId = $_SESSION['user_id'] ?? null;
if ($userId) {
    revoke_remember_token((int)$userId);              // remember-me token is deleted from the database
    audit_log(db(), (int)$userId, 'logout', '');
}
clear_remember_cookie();
destroy_session();
redirect('login.php?loggedout=1');
