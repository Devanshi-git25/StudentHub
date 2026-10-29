<?php
// Practical 14: role-based dynamic menu. Add or remove items here and every page updates.
function role_menu_items(string $role): array
{
    $menus = [
        'student' => [
            ['dashboard.php', 'My Dashboard'],
            ['events.php',     'Browse Events'],
            ['profile.php',    'My Profile'],
            ['feedback.php',   'Send Feedback'],
        ],
        'admin' => [
            ['admin.php',           'Admin Dashboard'],
            ['manage_students.php', 'Students'],
            ['students_ajax.php',   'Students (AJAX)'],
            ['manage_events.php',   'Events'],
            ['users_admin.php',     'Users'],
            ['feedback_list.php',   'Feedback'],
            ['audit_logs.php',      'Audit Log'],
        ],
    ];
    return $menus[$role] ?? [];
}
