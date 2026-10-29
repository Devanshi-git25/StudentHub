<?php
require_once __DIR__ . '/menu.php';
$pageTitle = $pageTitle ?? 'StudentHub';
$flash     = flash_get();
?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - <?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <header>
        <h1>StudentHub</h1>
    </header>

    <nav>
        <ul>
            <li><a href="index.html">Home</a></li>
            <li><a href="about.html">About</a></li>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="events.php">Events</a></li>
            <li><a href="students.html">Students</a></li>
            <li><a href="faq.html">FAQ</a></li>
            <li><a href="campus.html">Campus</a></li>
            <li><a href="research.html">Research</a></li>
            <li><a href="alumni.html">Alumni</a></li>
            <li><a href="feedback.php">Feedback</a></li>
<?php if (is_logged_in()): ?>
            <li class="nav-user">Hi, <?= e($_SESSION['name'] ?? '') ?></li>
            <li>
                <form method="post" action="logout.php" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Logout</button>
                </form>
            </li>
<?php else: ?>
            <li><a href="login.php">Login</a></li>
            <li><a href="register.php">Register</a></li>
<?php endif; ?>
        </ul>
    </nav>
<?php if (is_logged_in()): ?>
    <nav class="role-nav" aria-label="<?= e(ucfirst($_SESSION['role'] ?? 'user')) ?> menu">
        <ul>
<?php foreach (role_menu_items($_SESSION['role'] ?? '') as [$href, $label]): ?>
            <li><a href="<?= e($href) ?>"<?= basename($_SERVER['SCRIPT_NAME']) === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
<?php endforeach; ?>
        </ul>
    </nav>
<?php endif; ?>
<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div>
<?php endif; ?>

