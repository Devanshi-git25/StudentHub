<?php
// Practical 13: students CRUD without page reloads (talks to api/students.php with Fetch)
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/student_lib.php';
require_admin();
$pageTitle = 'Students (AJAX)';
$extraScripts = ['js/students_ajax.js'];
require __DIR__ . '/includes/header.php';
?>
    <main>
        <section id="ajaxApp" data-api="api/students.php" data-csrf="<?= e(csrf_token()) ?>">
            <h2>Students (AJAX, no page reload)</h2>

            <div id="message" class="alert" role="status" hidden></div>

            <div class="controls">
                <div>
                    <label for="searchBox">Search</label>
                    <input type="search" id="searchBox" placeholder="Type to search...">
                </div>
                <div>
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="controls-buttons">
                    <button type="button" id="addButton">+ Add Student</button>
                </div>
            </div>

            <form id="studentForm" class="ajax-form" hidden novalidate>
                <h3 id="formTitle">Add Student</h3>
                <input type="hidden" id="studentId">

                <div class="form-group">
                    <label for="f_enrollment_no">Enrollment No. * (e.g. 23CS001)</label>
                    <input type="text" id="f_enrollment_no" maxlength="7">
                    <span class="error" id="err_enrollment_no" role="alert"></span>
                </div>
                <div class="form-group">
                    <label for="f_name">Full Name *</label>
                    <input type="text" id="f_name" maxlength="60">
                    <span class="error" id="err_name" role="alert"></span>
                </div>
                <div class="form-group">
                    <label for="f_email">Email *</label>
                    <input type="email" id="f_email" maxlength="100">
                    <span class="error" id="err_email" role="alert"></span>
                </div>
                <div class="form-group">
                    <label for="f_course">Course *</label>
                    <select id="f_course">
                        <option value="">-- Select Course --</option>
<?php foreach (STUDENT_COURSES as $c): ?>
                        <option value="<?= e($c) ?>"><?= e($c) ?></option>
<?php endforeach; ?>
                    </select>
                    <span class="error" id="err_course" role="alert"></span>
                </div>
                <div class="form-group">
                    <label for="f_year">Year *</label>
                    <select id="f_year">
                        <option value="">-- Select Year --</option>
<?php foreach (STUDENT_YEARS as $y): ?>
                        <option value="<?= e($y) ?>"><?= e($y) ?></option>
<?php endforeach; ?>
                    </select>
                    <span class="error" id="err_year" role="alert"></span>
                </div>
                <div class="form-group">
                    <label for="f_status">Status</label>
                    <select id="f_status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <span class="error" id="err_status" role="alert"></span>
                </div>

                <button type="submit" id="saveButton">Save</button>
                <button type="button" id="cancelButton">Cancel</button>
            </form>

            <p id="loading" role="status">Loading...</p>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Enrollment</th><th>Name</th><th>Email</th><th>Course</th><th>Year</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody id="studentRows"></tbody>
                </table>
            </div>

            <div id="pager" class="pagination" role="navigation" aria-label="Pagination"></div>
        </section>
    </main>
<?php require __DIR__ . '/includes/footer.php'; ?>
