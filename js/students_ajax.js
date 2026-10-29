// ===== Practical 13: Fetch-based CRUD against api/students.php =====
(function () {
    const app = document.getElementById("ajaxApp");
    if (!app) return;

    const API = app.dataset.api;
    const CSRF = app.dataset.csrf;
    const FIELDS = ["enrollment_no", "name", "email", "course", "year", "status"];

    const rowsEl = document.getElementById("studentRows");
    const pagerEl = document.getElementById("pager");
    const loadingEl = document.getElementById("loading");
    const messageEl = document.getElementById("message");
    const searchBox = document.getElementById("searchBox");
    const statusFilter = document.getElementById("statusFilter");
    const form = document.getElementById("studentForm");

    let page = 1;
    let pages = 1;
    let rows = [];
    let requestCounter = 0;                           // lets us ignore out-of-date responses

    // ---------- helpers ----------
    function esc(text) {
        return String(text).replace(/[&<>"']/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
        });
    }

    function debounce(fn, delay) {
        let timer;
        return function () {
            const args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, delay);
        };
    }

    function showMessage(text, type) {
        messageEl.textContent = text;
        messageEl.className = "alert alert-" + type;
        messageEl.hidden = false;
    }

    function clearFieldErrors() {
        FIELDS.forEach(function (f) {
            document.getElementById("err_" + f).textContent = "";
            document.getElementById("f_" + f).classList.remove("invalid");
        });
    }

    // ---------- ONE place that talks to the server and handles every error ----------
    class ApiError extends Error {
        constructor(status, message, errors) {
            super(message);
            this.status = status;
            this.errors = errors || {};
        }
    }

    async function api(method, url, body) {
        const options = {
            method: method,
            credentials: "same-origin",
            headers: { "Accept": "application/json", "X-CSRF-Token": CSRF }
        };
        if (body !== undefined) {
            options.headers["Content-Type"] = "application/json";
            options.body = JSON.stringify(body);
        }

        let response;
        try {
            response = await fetch(url, options);
        } catch (networkError) {
            throw new ApiError(0, "Network error. Is the server running?");
        }

        let data = null;
        try { data = await response.json(); } catch (parseError) { /* not JSON */ }

        if (!response.ok || !data || data.ok === false) {
            const message = (data && data.message) || "Request failed (" + response.status + ").";
            throw new ApiError(response.status, message, data && data.errors);
        }
        return data;
    }

    function handleError(error) {
        if (error.status === 401) {
            showMessage("Your session has ended. Please log in again.", "error");
        } else {
            showMessage(error.message, "error");
        }
    }

    // ---------- list ----------
    async function loadStudents() {
        const myRequest = ++requestCounter;
        loadingEl.textContent = "Loading...";
        const params = new URLSearchParams({ page: page, search: searchBox.value.trim(), status: statusFilter.value });

        try {
            const result = await api("GET", API + "?" + params.toString());
            if (myRequest !== requestCounter) return;           // a newer request already replaced this one
            rows = result.data;
            page = result.meta.page;
            pages = result.meta.pages;
            renderRows();
            renderPager();
            loadingEl.textContent = result.meta.total + " student(s) found";
        } catch (error) {
            if (myRequest !== requestCounter) return;
            loadingEl.textContent = "";
            handleError(error);
        }
    }

    function renderRows() {
        if (rows.length === 0) {
            rowsEl.innerHTML = '<tr><td colspan="7">No students found.</td></tr>';
            return;
        }
        rowsEl.innerHTML = rows.map(function (s) {
            return "<tr" + (s.status === "inactive" ? ' class="row-inactive"' : "") + ">" +
                "<td>" + esc(s.enrollment_no) + "</td>" +
                "<td>" + esc(s.name) + "</td>" +
                "<td>" + esc(s.email) + "</td>" +
                "<td>" + esc(s.course) + "</td>" +
                "<td>" + esc(s.year) + "</td>" +
                '<td><span class="tag tag-' + esc(s.status) + '">' + esc(s.status) + "</span></td>" +
                '<td class="actions">' +
                '<button type="button" class="link-button" data-action="edit" data-id="' + s.id + '">Edit</button> ' +
                '<button type="button" class="link-button danger" data-action="delete" data-id="' + s.id + '">Delete</button>' +
                "</td></tr>";
        }).join("");
    }

    function renderPager() {
        if (pages <= 1) { pagerEl.innerHTML = ""; return; }
        let html = '<button type="button" data-page="' + (page - 1) + '"' + (page <= 1 ? " disabled" : "") + ">Previous</button>";
        for (let i = 1; i <= pages; i++) {
            html += '<button type="button" data-page="' + i + '"' + (i === page ? ' class="active" aria-current="page"' : "") + ">" + i + "</button>";
        }
        html += '<button type="button" data-page="' + (page + 1) + '"' + (page >= pages ? " disabled" : "") + ">Next</button>";
        pagerEl.innerHTML = html;
    }

    // ---------- form ----------
    function openForm(student) {
        clearFieldErrors();
        document.getElementById("formTitle").textContent = student ? "Edit Student" : "Add Student";
        document.getElementById("studentId").value = student ? student.id : "";
        FIELDS.forEach(function (f) {
            document.getElementById("f_" + f).value = student ? student[f] : (f === "status" ? "active" : "");
        });
        form.hidden = false;
        document.getElementById("f_enrollment_no").focus();
    }

    function closeForm() {
        form.hidden = true;
        clearFieldErrors();
    }

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        clearFieldErrors();

        const id = document.getElementById("studentId").value;
        const payload = {};
        FIELDS.forEach(function (f) { payload[f] = document.getElementById("f_" + f).value; });

        const saveButton = document.getElementById("saveButton");
        saveButton.disabled = true;
        saveButton.textContent = "Saving...";

        try {
            const result = id
                ? await api("PUT", API + "?id=" + encodeURIComponent(id), payload)
                : await api("POST", API, payload);
            showMessage(result.message, "success");
            closeForm();
            await loadStudents();
        } catch (error) {
            if (error.status === 422) {                          // validation errors: show them next to the fields
                Object.keys(error.errors).forEach(function (field) {
                    const span = document.getElementById("err_" + field);
                    if (span) {
                        span.textContent = error.errors[field];
                        document.getElementById("f_" + field).classList.add("invalid");
                    }
                });
                showMessage(error.message, "error");
            } else {
                handleError(error);
            }
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = "Save";
        }
    });

    // ---------- events ----------
    document.getElementById("addButton").addEventListener("click", function () { openForm(null); });
    document.getElementById("cancelButton").addEventListener("click", closeForm);

    searchBox.addEventListener("input", debounce(function () { page = 1; loadStudents(); }, 400));   // wait 400ms after typing stops
    statusFilter.addEventListener("change", function () { page = 1; loadStudents(); });

    pagerEl.addEventListener("click", function (event) {
        const button = event.target.closest("button[data-page]");
        if (!button || button.disabled) return;
        page = Number(button.dataset.page);
        loadStudents();
    });

    rowsEl.addEventListener("click", async function (event) {
        const button = event.target.closest("button[data-action]");
        if (!button) return;
        const id = Number(button.dataset.id);
        const student = rows.find(function (s) { return s.id === id || Number(s.id) === id; });

        if (button.dataset.action === "edit" && student) {
            openForm(student);
        } else if (button.dataset.action === "delete" && student) {
            if (!window.confirm("Permanently delete " + student.name + "?")) return;
            button.disabled = true;
            try {
                const result = await api("DELETE", API + "?id=" + id);
                showMessage(result.message, "success");
                await loadStudents();
            } catch (error) {
                button.disabled = false;
                handleError(error);
                if (error.status === 404) loadStudents();        // already gone: refresh the list
            }
        }
    });

    loadStudents();
})();
