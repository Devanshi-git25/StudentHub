// ===== Practical 6: render external JSON with Fetch, search, filter, sort, pagination =====
(function () {
    const root = document.getElementById("listApp");
    if (!root) return;                                   // page has no list

    const source = root.dataset.source;
    const type = root.dataset.type;
    const PAGE_SIZE = Number(root.dataset.pageSize) || 6;

    const searchBox = document.getElementById("searchBox");
    const filterSelect = document.getElementById("filterSelect");
    const sortSelect = document.getElementById("sortSelect");
    const container = document.getElementById("listContainer");
    const pagination = document.getElementById("pagination");
    const statusLine = document.getElementById("listStatus");

    // ---- Helpers ----
    function esc(text) {                                 // escape before putting data into HTML
        return String(text).replace(/[&<>"']/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
        });
    }

    function formatDate(iso) {
        return new Date(iso + "T00:00:00").toLocaleDateString("en-IN", { day: "numeric", month: "long", year: "numeric" });
    }

    // ---- One config per data type (keeps the logic modular) ----
    const configs = {
        events: {
            searchFields: ["title", "description", "venue"],
            filterField: "category",
            filterLabel: "All categories",
            sorts: {
                "date-asc": { label: "Date (earliest first)", fn: function (a, b) { return a.date.localeCompare(b.date); } },
                "date-desc": { label: "Date (latest first)", fn: function (a, b) { return b.date.localeCompare(a.date); } },
                "title-asc": { label: "Title (A-Z)", fn: function (a, b) { return a.title.localeCompare(b.title); } }
            },
            render: function (e) {
                return '<article class="card">' +
                    '<span class="tag">' + esc(e.category) + "</span>" +
                    "<h3>" + esc(e.title) + "</h3>" +
                    "<p><strong>Date:</strong> " + formatDate(e.date) + "</p>" +
                    "<p><strong>Venue:</strong> " + esc(e.venue) + "</p>" +
                    "<p>" + esc(e.description) + "</p></article>";
            }
        },
        students: {
            searchFields: ["name", "email", "enrollmentNo", "course"],
            filterField: "course",
            filterLabel: "All courses",
            sorts: {
                "name-asc": { label: "Name (A-Z)", fn: function (a, b) { return a.name.localeCompare(b.name); } },
                "name-desc": { label: "Name (Z-A)", fn: function (a, b) { return b.name.localeCompare(a.name); } },
                "year-asc": { label: "Year (1st to 4th)", fn: function (a, b) { return a.year.localeCompare(b.year); } }
            },
            render: function (s) {
                return '<article class="card">' +
                    '<span class="tag">' + esc(s.year) + "</span>" +
                    "<h3>" + esc(s.name) + "</h3>" +
                    "<p><strong>Enrollment:</strong> " + esc(s.enrollmentNo) + "</p>" +
                    "<p><strong>Course:</strong> " + esc(s.course) + "</p>" +
                    "<p>" + esc(s.email) + "</p></article>";
            }
        },
        faqs: {
            searchFields: ["question", "answer"],
            filterField: "category",
            filterLabel: "All topics",
            sorts: {
                "id-asc": { label: "Default order", fn: function (a, b) { return a.id - b.id; } },
                "q-asc": { label: "Question (A-Z)", fn: function (a, b) { return a.question.localeCompare(b.question); } }
            },
            render: function (f) {
                return '<details class="faq-details"><summary>' + esc(f.question) +
                    ' <span class="tag">' + esc(f.category) + "</span></summary>" +
                    "<p>" + esc(f.answer) + "</p></details>";
            }
        }
    };

    const config = configs[type];
    if (!config) return;

    // ---- State ----
    let allItems = [];
    let query = "";
    let filterValue = "";
    let sortKey = Object.keys(config.sorts)[0];
    let page = 1;

    // ---- Fetch + parse JSON (falls back to the last cached copy if the network/server fails) ----
    async function loadData() {
        const cacheKey = "studenthub_cache_" + source;
        try {
            const response = await fetch(source);
            if (!response.ok) throw new Error("Server returned " + response.status);
            const data = await response.json();
            if (!Array.isArray(data)) throw new Error("Unexpected data format");
            try { localStorage.setItem(cacheKey, JSON.stringify(data)); } catch (e) { /* storage full/blocked */ }
            return { data: data, fromCache: false };
        } catch (error) {
            const cached = localStorage.getItem(cacheKey);
            if (cached) {
                try { return { data: JSON.parse(cached), fromCache: true }; } catch (e) { /* ignore */ }
            }
            throw error;
        }
    }

    // ---- Controls ----
    function buildControls() {
        const values = Array.from(new Set(allItems.map(function (i) { return i[config.filterField]; }))).sort();
        filterSelect.innerHTML = '<option value="">' + esc(config.filterLabel) + "</option>" +
            values.map(function (v) { return '<option value="' + esc(v) + '">' + esc(v) + "</option>"; }).join("");

        sortSelect.innerHTML = Object.keys(config.sorts).map(function (k) {
            return '<option value="' + k + '">' + esc(config.sorts[k].label) + "</option>";
        }).join("");
    }

    // ---- Search + filter + sort + paginate ----
    function getResults() {
        const q = query.trim().toLowerCase();
        return allItems
            .filter(function (item) {
                const matchesFilter = filterValue === "" || item[config.filterField] === filterValue;
                const matchesSearch = q === "" || config.searchFields.some(function (f) {
                    return String(item[f]).toLowerCase().includes(q);
                });
                return matchesFilter && matchesSearch;
            })
            .sort(config.sorts[sortKey].fn);
    }

    function render() {
        const results = getResults();
        const totalPages = Math.max(1, Math.ceil(results.length / PAGE_SIZE));
        if (page > totalPages) page = totalPages;

        const start = (page - 1) * PAGE_SIZE;
        const visible = results.slice(start, start + PAGE_SIZE);

        if (results.length === 0) {
            container.innerHTML = '<p class="empty">No results found. Try a different search or filter.</p>';
            statusLine.textContent = "0 results";
        } else {
            container.innerHTML = visible.map(config.render).join("");
            statusLine.textContent = "Showing " + (start + 1) + "-" + (start + visible.length) + " of " + results.length + " results";
        }
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        if (totalPages <= 1) { pagination.innerHTML = ""; return; }

        let html = '<button type="button" data-page="' + (page - 1) + '"' + (page === 1 ? " disabled" : "") + ">Previous</button>";
        for (let i = 1; i <= totalPages; i++) {
            html += '<button type="button" data-page="' + i + '"' + (i === page ? ' class="active" aria-current="page"' : "") + ">" + i + "</button>";
        }
        html += '<button type="button" data-page="' + (page + 1) + '"' + (page === totalPages ? " disabled" : "") + ">Next</button>";
        pagination.innerHTML = html;
    }

    // ---- Events ----
    searchBox.addEventListener("input", function () { query = searchBox.value; page = 1; render(); });
    filterSelect.addEventListener("change", function () { filterValue = filterSelect.value; page = 1; render(); });
    sortSelect.addEventListener("change", function () { sortKey = sortSelect.value; page = 1; render(); });
    pagination.addEventListener("click", function (event) {
        const button = event.target.closest("button[data-page]");
        if (!button || button.disabled) return;
        page = Number(button.dataset.page);
        render();
        root.scrollIntoView({ behavior: "smooth", block: "start" });
    });

    // ---- Start ----
    statusLine.textContent = "Loading...";
    loadData()
        .then(function (result) {
            allItems = result.data;
            buildControls();
            render();
            if (result.fromCache) {
                statusLine.textContent += " (offline copy - could not reach the server)";
            }
        })
        .catch(function (error) {
            container.innerHTML = '<p class="error-box">Could not load data (' + esc(error.message) +
                "). Open this site through a local server such as XAMPP (http://localhost/StudentHub/), not by double-clicking the file.</p>";
            statusLine.textContent = "";
        });
})();
