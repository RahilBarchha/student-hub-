<?php
session_start();
require_once __DIR__ . '/db.php';

if (isset($_GET['download']) && $_GET['download'] === 'sql') {
    $file = __DIR__ . '/studenthub_p13.sql';
    if (file_exists($file)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="studenthub_p13.sql"');
        readfile($file);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asynchronous API & Fetch CRUD - Student Hub</title>
    <link rel="stylesheet" href="../practical 3/style.css">
    <style>
        .api-stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 20px 0;
            width: 100%;
        }
        .api-stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .api-stat-number {
            font-size: 26px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }
        .api-stat-label {
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .async-action-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px 20px;
            margin: 20px 0;
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .search-box-wrapper {
            position: relative;
            flex: 1;
            min-width: 260px;
        }
        .search-box-wrapper input {
            width: 100%;
            padding: 10px 14px 10px 36px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
        }
        .search-icon-placeholder {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 14px;
            pointer-events: none;
        }
        .action-button-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-portal {
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-portal-primary {
            background: var(--primary);
            color: #ffffff;
        }
        .btn-portal-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .btn-portal-secondary {
            background: #e2e8f0;
            color: #334155;
        }
        .btn-portal-secondary:hover {
            background: #cbd5e1;
        }
        .btn-portal-success {
            background: #16a34a;
            color: #ffffff;
        }
        .btn-portal-danger {
            background: #dc2626;
            color: #ffffff;
        }
        .table-responsive-box {
            width: 100%;
            overflow-x: auto;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin: 20px 0;
            position: relative;
            min-height: 200px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .data-table th, .data-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .data-table th {
            background: var(--primary);
            color: #ffffff;
            font-weight: 600;
            white-space: nowrap;
        }
        .data-table tr:hover {
            background: rgba(74, 144, 226, 0.08);
        }
        .badge-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }
        .badge-course {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(74, 144, 226, 0.15);
            color: var(--primary);
        }
        .btn-action-icon {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            margin-right: 4px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-edit-async {
            background: #f59e0b;
            color: #ffffff;
        }
        .btn-edit-async:hover {
            background: #d97706;
        }
        .btn-del-async {
            background: #ef4444;
            color: #ffffff;
        }
        .btn-del-async:hover {
            background: #dc2626;
        }
        .form-async-card {
            background: var(--surface);
            border: 2px solid var(--primary);
            border-radius: 8px;
            padding: 24px;
            margin: 20px 0;
            width: 100%;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        .form-grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 768px) {
            .form-grid-2col {
                grid-template-columns: 1fr;
            }
        }
        .feedback-banner {
            display: none;
            padding: 12px 16px;
            border-radius: 6px;
            margin: 16px 0;
            font-size: 14px;
            font-weight: 500;
        }
        .feedback-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .feedback-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .feedback-loading {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        .loading-overlay {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            z-index: 10;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--primary);
            font-size: 15px;
        }
        body.dark .loading-overlay {
            background: rgba(15, 23, 42, 0.75);
        }
        .api-inspector-card {
            background: #0f172a;
            color: #f8fafc;
            border-radius: 8px;
            padding: 18px;
            margin: 24px 0;
            border: 1px solid #334155;
            font-family: Consolas, monospace;
        }
        .api-inspector-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            border-bottom: 1px solid #334155;
            padding-bottom: 10px;
        }
        .api-badge-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .code-preview-box {
            background: #020617;
            border: 1px solid #1e293b;
            padding: 12px;
            border-radius: 6px;
            max-height: 240px;
            overflow-y: auto;
            color: #38bdf8;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .quick-api-test-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
        .quick-api-test-btns button {
            background: #1e293b;
            color: #e2e8f0;
            border: 1px solid #475569;
            border-radius: 4px;
            padding: 6px 10px;
            font-size: 12px;
            cursor: pointer;
            font-family: inherit;
        }
        .quick-api-test-btns button:hover {
            background: #334155;
            color: #ffffff;
        }
    </style>
</head>
<body>

    <header>
        <div class="header">
            <h1>Student Hub Portal</h1>
            <p>Asynchronous JSON API & Fetch-Based CRUD</p>
        </div>
    </header>

    <div class="topbar">
        <button id="menuButton" class="menu-button" type="button" aria-label="Open navigation menu">☰</button>
        <div class="site-mini-title">Asynchronous API Portal</div>
        <label class="theme-switch" title="Toggle light/dark theme">
            <input type="checkbox" id="themeToggle">
            <span>Dark Mode</span>
        </label>
    </div>

    <nav id="navMenu" class="navbar" aria-label="Main navigation">
        <a href="../practical 2/index.html" class="nav-btn">Home</a>
        <a href="index.php" class="nav-btn active">Fetch API CRUD</a>
        <a href="../practical 14/index.php" class="nav-btn">Admin RBAC</a>
        <a href="../practical 11/index.php" class="nav-btn">Student CRUD</a>
        <a href="../practical 12/index.php" class="nav-btn">Events CRUD</a>
        <a href="../practical 10/admin_dashboard.php" class="nav-btn">Admin Portal</a>
        <a href="../practical 9/index.php" class="nav-btn">Register</a>
        <a href="../practical 2/contact.html" class="nav-btn">Contact</a>
    </nav>

    <main class="container">

        <div id="feedbackBanner" class="feedback-banner"></div>

        <div class="api-stats-bar">
            <div class="api-stat-card">
                <div id="statTotalStudents" class="api-stat-number">-</div>
                <div class="api-stat-label">Total Students</div>
            </div>
            <div class="api-stat-card">
                <div id="statActiveStudents" class="api-stat-number">-</div>
                <div class="api-stat-label">Active Records</div>
            </div>
            <div class="api-stat-card">
                <div id="statApiStatus" class="api-stat-number" style="font-size: 18px; color: #16a34a;">200 OK</div>
                <div class="api-stat-label">API Health</div>
            </div>
            <div class="api-stat-card">
                <div id="statLatency" class="api-stat-number">- ms</div>
                <div class="api-stat-label">Round-Trip Latency</div>
            </div>
        </div>

        <div class="async-action-bar">
            <div class="search-box-wrapper">
                <span class="search-icon-placeholder">🔍</span>
                <input type="text" id="asyncSearchInput" placeholder="Live search by name, email, course... (300ms debounce)">
            </div>
            <div class="action-button-group">
                <button type="button" id="toggleFormBtn" class="btn-portal btn-portal-primary" onclick="toggleFormVisibility()">+ Add Student Asynchronously</button>
                <button type="button" class="btn-portal btn-portal-secondary" onclick="fetchStudentsList()">↻ Reload Data</button>
                <a href="index.php?download=sql" class="btn-portal btn-portal-secondary">⬇ Download SQL</a>
            </div>
        </div>

        <div id="asyncFormSection" class="form-async-card" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                <h3 id="formTitleHeading" style="margin: 0; color: var(--primary);">Add New Student Record</h3>
                <button type="button" class="btn-portal btn-portal-secondary" onclick="cancelFormEdit()" style="padding: 4px 10px; font-size: 12px;">✕ Close</button>
            </div>

            <form id="studentAsyncForm" onsubmit="handleFormSubmit(event)">
                <input type="hidden" id="formStudentId" value="">
                <input type="hidden" id="formActionType" value="create">

                <div class="form-grid-2col">
                    <div class="form-group">
                        <label for="formName">Full Name *</label>
                        <input type="text" id="formName" required placeholder="e.g. Rahul Sharma" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="formEmail">Email Address *</label>
                        <input type="email" id="formEmail" required placeholder="student@example.com" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="formMobile">Mobile Number *</label>
                        <input type="tel" id="formMobile" required pattern="[6-9][0-9]{9}" placeholder="10-digit mobile starting with 6-9" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="formCourse">Course Selection *</label>
                        <select id="formCourse" required class="form-control">
                            <option value="">-- Choose Course --</option>
                            <option value="BTech_IT">B.Tech Information Technology</option>
                            <option value="BTech_CSE">B.Tech Computer Science</option>
                            <option value="BTech_AI">B.Tech Artificial Intelligence</option>
                            <option value="BTech_Mech">B.Tech Mechanical</option>
                            <option value="BTech_Civil">B.Tech Civil</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="formYear">Academic Year *</label>
                        <select id="formYear" required class="form-control">
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="formGender">Gender *</label>
                        <select id="formGender" required class="form-control">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="formStatus">Enrollment Status</label>
                        <select id="formStatus" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" id="formSubmitBtn" class="btn-portal btn-portal-primary">
                        <span id="submitBtnText">Save Student via API</span>
                    </button>
                    <button type="button" class="btn-portal btn-portal-secondary" onclick="cancelFormEdit()">Cancel</button>
                </div>
            </form>
        </div>

        <div class="table-responsive-box">
            <div id="tableLoadingOverlay" class="loading-overlay">
                <span>Connecting to JSON API...</span>
            </div>

            <table class="data-table" id="studentsDataTable">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Gender</th>
                        <th>Status</th>
                        <th style="width: 150px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody">
                </tbody>
            </table>
        </div>

        <div class="api-inspector-card">
            <div class="api-inspector-header">
                <div>
                    <span style="color: #94a3b8; font-size: 13px;">API Console & Network Inspector</span>
                    <span id="inspectorEndpoint" style="color: #38bdf8; font-weight: 600; margin-left: 10px; font-size: 13px;">GET api.php</span>
                </div>
                <div>
                    <span id="inspectorStatusBadge" class="api-badge-status" style="background: #166534; color: #dcfce7;">200 OK</span>
                    <span id="inspectorLatencyBadge" style="color: #94a3b8; font-size: 12px; margin-left: 8px;">0 ms</span>
                </div>
            </div>
            <div id="inspectorJsonPreview" class="code-preview-box">Waiting for request...</div>
            <div class="quick-api-test-btns">
                <span style="color: #94a3b8; font-size: 12px; align-self: center;">Quick API Tests:</span>
                <button type="button" onclick="testManualApi('api.php')">GET All</button>
                <button type="button" onclick="testManualApi('api.php?id=1')">GET ID #1</button>
                <button type="button" onclick="testManualApi('api.php?id=99999')">Test 404 (Not Found)</button>
                <button type="button" onclick="testManualPostInvalid()">Test 400 (Bad Request)</button>
            </div>
        </div>

    </main>

    <script>
        const API_URL = 'api.php';
        let debounceTimer = null;

        function showBanner(message, type = 'success') {
            const banner = document.getElementById('feedbackBanner');
            banner.className = 'feedback-banner feedback-' + type;
            banner.innerHTML = message;
            banner.style.display = 'block';

            if (type !== 'loading') {
                setTimeout(() => {
                    if (banner.className.includes('feedback-' + type)) {
                        banner.style.display = 'none';
                    }
                }, 4000);
            }
        }

        function hideBanner() {
            document.getElementById('feedbackBanner').style.display = 'none';
        }

        function setTableLoading(isLoading) {
            const overlay = document.getElementById('tableLoadingOverlay');
            overlay.style.display = isLoading ? 'flex' : 'none';
        }

        function updateInspector(endpoint, statusCode, statusText, responseData, durationMs) {
            document.getElementById('inspectorEndpoint').textContent = endpoint;
            const badge = document.getElementById('inspectorStatusBadge');
            badge.textContent = statusCode + ' ' + statusText;

            if (statusCode >= 200 && statusCode < 300) {
                badge.style.background = '#166534';
                badge.style.color = '#dcfce7';
            } else if (statusCode >= 400 && statusCode < 500) {
                badge.style.background = '#991b1b';
                badge.style.color = '#fee2e2';
            } else {
                badge.style.background = '#854d0e';
                badge.style.color = '#fef9c3';
            }

            document.getElementById('inspectorLatencyBadge').textContent = durationMs + ' ms';
            document.getElementById('inspectorJsonPreview').textContent = JSON.stringify(responseData, null, 2);
            document.getElementById('statLatency').textContent = durationMs + ' ms';
            document.getElementById('statApiStatus').textContent = statusCode + ' ' + (statusCode === 200 || statusCode === 201 ? 'OK' : 'ERR');
            document.getElementById('statApiStatus').style.color = statusCode < 400 ? '#16a34a' : '#dc2626';
        }

        async function fetchStudentsList(query = '') {
            const startTime = performance.now();
            setTableLoading(true);
            const endpoint = query ? (API_URL + '?q=' + encodeURIComponent(query)) : API_URL;

            try {
                const response = await fetch(endpoint, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });
                const duration = Math.round(performance.now() - startTime);
                const data = await response.json();

                updateInspector('GET ' + endpoint, response.status, response.statusText || (response.ok ? 'OK' : 'Error'), data, duration);

                if (data.success && Array.isArray(data.data)) {
                    renderTable(data.data);
                    updateCounters(data.data);
                } else {
                    renderEmptyTable(data.message || 'No students found.');
                }
            } catch (err) {
                const duration = Math.round(performance.now() - startTime);
                updateInspector('GET ' + endpoint, 500, 'Network Error', { error: err.message }, duration);
                showBanner('Failed to connect to PHP API: ' + err.message, 'error');
                renderEmptyTable('Connection error occurred.');
            } finally {
                setTableLoading(false);
            }
        }

        function renderTable(students) {
            const tbody = document.getElementById('studentsTableBody');
            tbody.innerHTML = '';

            if (students.length === 0) {
                renderEmptyTable('No student records found.');
                return;
            }

            students.forEach(s => {
                const tr = document.createElement('tr');
                tr.id = 'student-row-' + s.student_id;

                const statusClass = s.status === 'Active' ? 'status-active' : 'status-inactive';

                tr.innerHTML = `
                    <td><strong>#${s.student_id}</strong></td>
                    <td style="font-weight: 600;">${escapeHtml(s.name)}</td>
                    <td>${escapeHtml(s.email)}</td>
                    <td>${escapeHtml(s.mobile)}</td>
                    <td><span class="badge-course">${escapeHtml(s.course)}</span></td>
                    <td>Year ${s.year}</td>
                    <td>${escapeHtml(s.gender)}</td>
                    <td><span class="badge-status ${statusClass}">${escapeHtml(s.status)}</span></td>
                    <td style="text-align: center; white-space: nowrap;">
                        <button type="button" class="btn-action-icon btn-edit-async" onclick="loadStudentForEdit(${s.student_id})">Edit</button>
                        <button type="button" class="btn-action-icon btn-del-async" onclick="deleteStudentAsync(${s.student_id}, '${escapeHtml(s.name)}')">Delete</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function renderEmptyTable(message) {
            const tbody = document.getElementById('studentsTableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align: center; padding: 30px; color: var(--muted);">
                        ${escapeHtml(message)}
                    </td>
                </tr>
            `;
            document.getElementById('statTotalStudents').textContent = '0';
            document.getElementById('statActiveStudents').textContent = '0';
        }

        function updateCounters(students) {
            document.getElementById('statTotalStudents').textContent = students.length;
            const activeCount = students.filter(s => s.status === 'Active').length;
            document.getElementById('statActiveStudents').textContent = activeCount;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        document.getElementById('asyncSearchInput').addEventListener('input', function(e) {
            const value = e.target.value.trim();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                fetchStudentsList(value);
            }, 300);
        });

        function toggleFormVisibility(show) {
            const section = document.getElementById('asyncFormSection');
            if (show !== undefined) {
                section.style.display = show ? 'block' : 'none';
            } else {
                section.style.display = section.style.display === 'none' ? 'block' : 'none';
            }
            if (section.style.display === 'block') {
                section.scrollIntoView({ behavior: 'smooth' });
            }
        }

        async function loadStudentForEdit(id) {
            showBanner('Loading student #' + id + ' details...', 'loading');
            const startTime = performance.now();

            try {
                const response = await fetch(API_URL + '?id=' + id);
                const duration = Math.round(performance.now() - startTime);
                const resData = await response.json();

                updateInspector('GET api.php?id=' + id, response.status, response.statusText, resData, duration);

                if (resData.success && resData.data) {
                    const s = resData.data;
                    document.getElementById('formStudentId').value = s.student_id;
                    document.getElementById('formActionType').value = 'update';
                    document.getElementById('formName').value = s.name;
                    document.getElementById('formEmail').value = s.email;
                    document.getElementById('formMobile').value = s.mobile;
                    document.getElementById('formCourse').value = s.course;
                    document.getElementById('formYear').value = s.year;
                    document.getElementById('formGender').value = s.gender;
                    document.getElementById('formStatus').value = s.status;

                    document.getElementById('formTitleHeading').textContent = 'Edit Student #' + s.student_id;
                    document.getElementById('submitBtnText').textContent = 'Update Student via API';
                    toggleFormVisibility(true);
                    hideBanner();
                } else {
                    showBanner(resData.message || 'Student not found.', 'error');
                }
            } catch (err) {
                showBanner('Error loading student: ' + err.message, 'error');
            }
        }

        function cancelFormEdit() {
            document.getElementById('studentAsyncForm').reset();
            document.getElementById('formStudentId').value = '';
            document.getElementById('formActionType').value = 'create';
            document.getElementById('formTitleHeading').textContent = 'Add New Student Record';
            document.getElementById('submitBtnText').textContent = 'Save Student via API';
            toggleFormVisibility(false);
        }

        async function handleFormSubmit(e) {
            e.preventDefault();

            const actionType = document.getElementById('formActionType').value;
            const studentId = document.getElementById('formStudentId').value;
            const name = document.getElementById('formName').value.trim();
            const email = document.getElementById('formEmail').value.trim();
            const mobile = document.getElementById('formMobile').value.trim();
            const course = document.getElementById('formCourse').value;
            const year = parseInt(document.getElementById('formYear').value, 10);
            const gender = document.getElementById('formGender').value;
            const status = document.getElementById('formStatus').value;

            if (!/^[a-zA-Z\s]{2,50}$/.test(name)) {
                showBanner('Please provide a valid name (letters & spaces only).', 'error');
                return;
            }
            if (!/^[6-9]\d{9}$/.test(mobile)) {
                showBanner('Mobile number must be 10 digits starting with 6, 7, 8, or 9.', 'error');
                return;
            }

            const payload = {
                action: actionType,
                student_id: studentId ? parseInt(studentId, 10) : undefined,
                name: name,
                email: email,
                mobile: mobile,
                course: course,
                year: year,
                gender: gender,
                status: status
            };

            const submitBtn = document.getElementById('formSubmitBtn');
            submitBtn.disabled = true;
            showBanner('Sending request to PHP API...', 'loading');
            const startTime = performance.now();

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const duration = Math.round(performance.now() - startTime);
                const resData = await response.json();

                updateInspector('POST api.php (' + actionType + ')', response.status, response.statusText, resData, duration);

                if (response.ok && resData.success) {
                    showBanner(resData.message, 'success');
                    cancelFormEdit();
                    fetchStudentsList();
                } else {
                    showBanner(resData.message || 'Operation failed with code ' + response.status, 'error');
                }
            } catch (err) {
                showBanner('Network/server error: ' + err.message, 'error');
            } finally {
                submitBtn.disabled = false;
            }
        }

        async function deleteStudentAsync(id, name) {
            if (!confirm('Are you sure you want to delete student #' + id + ' (' + name + ')? This will be executed asynchronously without full page reload.')) {
                return;
            }

            showBanner('Deleting student #' + id + ' via API...', 'loading');
            const startTime = performance.now();

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ action: 'delete', student_id: id })
                });
                const duration = Math.round(performance.now() - startTime);
                const resData = await response.json();

                updateInspector('POST api.php (delete #' + id + ')', response.status, response.statusText, resData, duration);

                if (response.ok && resData.success) {
                    showBanner(resData.message, 'success');
                    const row = document.getElementById('student-row-' + id);
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(20px)';
                        setTimeout(() => {
                            fetchStudentsList(document.getElementById('asyncSearchInput').value.trim());
                        }, 300);
                    } else {
                        fetchStudentsList();
                    }
                } else {
                    showBanner(resData.message || 'Deletion failed.', 'error');
                }
            } catch (err) {
                showBanner('Deletion error: ' + err.message, 'error');
            }
        }

        async function testManualApi(endpoint) {
            const startTime = performance.now();
            try {
                const res = await fetch(endpoint);
                const duration = Math.round(performance.now() - startTime);
                const data = await res.json();
                updateInspector('GET ' + endpoint, res.status, res.statusText, data, duration);
            } catch (err) {
                updateInspector('GET ' + endpoint, 500, 'Error', { error: err.message }, 0);
            }
        }

        async function testManualPostInvalid() {
            const startTime = performance.now();
            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create', name: '' })
                });
                const duration = Math.round(performance.now() - startTime);
                const data = await res.json();
                updateInspector('POST api.php (empty test)', res.status, res.statusText, data, duration);
            } catch (err) {
                updateInspector('POST api.php (empty test)', 500, 'Error', { error: err.message }, 0);
            }
        }

        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark');
                themeToggle.checked = true;
            }
            themeToggle.addEventListener('change', () => {
                if (themeToggle.checked) {
                    document.body.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                }
            });
        }

        const menuButton = document.getElementById('menuButton');
        const navMenu = document.getElementById('navMenu');
        if (menuButton && navMenu) {
            menuButton.addEventListener('click', () => {
                navMenu.classList.toggle('nav-open');
            });
        }

        window.addEventListener('DOMContentLoaded', () => {
            fetchStudentsList();
        });
    </script>
</body>
</html>
