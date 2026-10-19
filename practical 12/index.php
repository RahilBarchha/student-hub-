<?php
session_start();
require_once __DIR__ . '/db.php';

$success = "";
$error = "";

$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (isset($_GET['download']) && $_GET['download'] === 'sql') {
    $file = __DIR__ . '/studenthub_p12.sql';
    if (file_exists($file)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="studenthub_p12.sql"');
        readfile($file);
        exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $deleteId = (int)($_GET['id'] ?? 0);
    if ($deleteId > 0) {
        $findStmt = $conn->prepare("SELECT title, poster FROM events WHERE event_id = ?");
        $findStmt->bind_param("i", $deleteId);
        $findStmt->execute();
        $targetEvent = $findStmt->get_result()->fetch_assoc();
        $findStmt->close();

        if ($targetEvent) {
            $delStmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
            $delStmt->bind_param("i", $deleteId);
            if ($delStmt->execute()) {
                if (!empty($targetEvent['poster'])) {
                    $posterPath = $uploadDir . $targetEvent['poster'];
                    if (file_exists($posterPath)) {
                        @unlink($posterPath);
                    }
                }

                $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                $logAction = "EVENT_DELETED";
                $logDetails = "Deleted event #" . $deleteId . " ('" . $targetEvent['title'] . "').";
                $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $uid = $_SESSION['user_id'] ?? null;
                $logStmt->bind_param("isss", $uid, $logAction, $logDetails, $clientIp);
                $logStmt->execute();
                $logStmt->close();

                $success = "Event #" . $deleteId . " ('" . htmlspecialchars($targetEvent['title']) . "') deleted successfully.";
            } else {
                $error = "Deletion error: " . $conn->error;
            }
            $delStmt->close();
        }
    }
}

$editEvent = null;
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmtEdit = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
    $stmtEdit->bind_param("i", $editId);
    $stmtEdit->execute();
    $editEvent = $stmtEdit->get_result()->fetch_assoc();
    $stmtEdit->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formAction = $_POST["form_action"] ?? "add";
    $title = trim($_POST["title"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $event_date = trim($_POST["event_date"] ?? "");
    $venue = trim($_POST["venue"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $status = trim($_POST["status"] ?? "Open");

    if (empty($title)) {
        $error = "Event title is required.";
    } elseif (empty($category)) {
        $error = "Please select an event category.";
    } elseif (empty($event_date)) {
        $error = "Event date is required.";
    } elseif (empty($venue)) {
        $error = "Event venue is required.";
    } else {
        $posterFilename = "";
        $hasNewUpload = isset($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE;

        if ($hasNewUpload) {
            $file = $_FILES['poster'];
            $fileSize = $file['size'];
            $fileError = $file['error'];
            $fileTmp = $file['tmp_name'];

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($fileError !== UPLOAD_ERR_OK) {
                $error = "Upload error occurred. Error code: " . $fileError;
            } elseif ($fileSize > 2097152) {
                $error = "Poster file size exceeds the 2MB limit. (Your file: " . round($fileSize / 1048576, 2) . " MB)";
            } elseif (!in_array($fileExt, $allowedExtensions)) {
                $error = "Invalid poster format. Allowed extensions: JPG, JPEG, PNG, WEBP.";
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $fileTmp);
                finfo_close($finfo);

                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!in_array($mimeType, $allowedMimes)) {
                    $error = "Invalid image MIME type: $mimeType. Upload must be a valid image file.";
                } else {
                    $posterFilename = "poster_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $fileExt;
                    $targetPath = $uploadDir . $posterFilename;
                    if (!move_uploaded_file($fileTmp, $targetPath)) {
                        $error = "Failed to save the uploaded poster file.";
                    }
                }
            }
        }

        if (empty($error)) {
            if ($formAction === "edit") {
                $eventId = (int)($_POST["event_id"] ?? 0);

                if (empty($posterFilename)) {
                    $posterFilename = $_POST["existing_poster"] ?? "";
                } else {
                    $oldPoster = $_POST["existing_poster"] ?? "";
                    if (!empty($oldPoster) && file_exists($uploadDir . $oldPoster)) {
                        @unlink($uploadDir . $oldPoster);
                    }
                }

                $upStmt = $conn->prepare("UPDATE events SET title = ?, category = ?, event_date = ?, venue = ?, description = ?, status = ?, poster = ? WHERE event_id = ?");
                $upStmt->bind_param("sssssssi", $title, $category, $event_date, $venue, $description, $status, $posterFilename, $eventId);
                if ($upStmt->execute()) {
                    $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                    $logAction = "EVENT_UPDATED";
                    $logDetails = "Updated event #" . $eventId . " ('" . $title . "').";
                    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $uid = $_SESSION['user_id'] ?? null;
                    $logStmt->bind_param("isss", $uid, $logAction, $logDetails, $clientIp);
                    $logStmt->execute();
                    $logStmt->close();

                    $success = "Event #" . $eventId . " updated successfully.";
                    $editEvent = null;
                } else {
                    $error = "Update failed: " . $conn->error;
                }
                $upStmt->close();
            } else {
                $insStmt = $conn->prepare("INSERT INTO events (title, category, event_date, venue, description, status, poster) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insStmt->bind_param("sssssss", $title, $category, $event_date, $venue, $description, $status, $posterFilename);
                if ($insStmt->execute()) {
                    $newId = $insStmt->insert_id;

                    $logStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                    $logAction = "EVENT_CREATED";
                    $logDetails = "Created new event #" . $newId . " ('" . $title . "') in category " . $category . ".";
                    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    $uid = $_SESSION['user_id'] ?? null;
                    $logStmt->bind_param("isss", $uid, $logAction, $logDetails, $clientIp);
                    $logStmt->execute();
                    $logStmt->close();

                    $success = "Event #" . $newId . " ('" . htmlspecialchars($title) . "') created successfully.";
                } else {
                    $error = "Failed to insert event: " . $conn->error;
                }
                $insStmt->close();
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$sql = "SELECT * FROM events WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR venue LIKE ? OR category LIKE ? OR description LIKE ?)";
    $term = "%" . $search . "%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= "ssss";
}

if (!empty($filterCategory)) {
    $sql .= " AND category = ?";
    $params[] = $filterCategory;
    $types .= "s";
}

if (!empty($filterStatus)) {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

$sql .= " ORDER BY event_date ASC, event_id DESC";
$stmtList = $conn->prepare($sql);
if (!empty($types)) {
    $stmtList->bind_param($types, ...$params);
}
$stmtList->execute();
$eventsResult = $stmtList->get_result();
$events = [];
while ($row = $eventsResult->fetch_assoc()) {
    $events[] = $row;
}
$stmtList->close();

$totRes = $conn->query("SELECT COUNT(*) AS total FROM events");
$totalCount = $totRes ? (int)$totRes->fetch_assoc()['total'] : 0;

$openRes = $conn->query("SELECT COUNT(*) AS open_cnt FROM events WHERE status = 'Open'");
$openCount = $openRes ? (int)$openRes->fetch_assoc()['open_cnt'] : 0;

$closedRes = $conn->query("SELECT COUNT(*) AS closed_cnt FROM events WHERE status = 'Closed'");
$closedCount = $closedRes ? (int)$closedRes->fetch_assoc()['closed_cnt'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - Student Hub</title>
    <link rel="stylesheet" href="../practical 3/style.css">
    <style>
        .filter-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px 20px;
            margin: 20px 0;
            width: 100%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1.2fr 1fr auto;
            gap: 12px;
            align-items: end;
        }
        @media (max-width: 850px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            width: 100%;
            margin: 20px 0;
        }
        .stat-card {
            background: var(--surface);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-card h3 {
            font-size: 26px;
            color: var(--primary);
            margin-bottom: 4px;
        }
        .stat-card p {
            font-size: 13px;
            color: var(--muted);
            font-weight: 600;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-top: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--surface);
            font-size: 13px;
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
        .poster-thumb {
            width: 54px;
            height: 54px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        .poster-thumb:hover {
            transform: scale(1.15);
        }
        .no-poster-box {
            width: 54px;
            height: 54px;
            border-radius: 6px;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            border: 1px dashed #cbd5e1;
        }
        .badge-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-open { background: #dcfce7; color: #166534; }
        .status-closed { background: #fee2e2; color: #991b1b; }
        .badge-category {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            background: #e0f2fe;
            color: #0369a1;
        }
        .btn-action {
            display: inline-block;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .btn-edit { background: #3b82f6; color: #fff; }
        .btn-delete { background: #ef4444; color: #fff; }
        .form-modal-box {
            background: var(--surface);
            border: 2px solid var(--primary);
            border-radius: 8px;
            padding: 24px;
            margin: 20px 0;
            width: 100%;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        .file-hint {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
            display: block;
        }
    </style>
</head>
<body>

    <header>
        <div class="header">
            <h1>Student Hub Portal</h1>
            <p>Event Management & Campus Highlights</p>
        </div>
    </header>

    <div class="topbar">
        <button id="menuButton" class="menu-button" type="button" aria-label="Open navigation menu">☰</button>
        <div class="site-mini-title">Event Management Portal</div>
        <label class="theme-switch" title="Toggle light/dark theme">
            <input type="checkbox" id="themeToggle">
            <span>Dark Mode</span>
        </label>
    </div>

    <nav id="navMenu" class="navbar" aria-label="Main navigation">
        <a href="../practical 2/index.html" class="nav-btn">Home</a>
        <a href="index.php" class="nav-btn active">Events CRUD</a>
        <a href="../practical 13/index.php" class="nav-btn">Fetch API CRUD</a>
        <a href="../practical 11/index.php" class="nav-btn">Student CRUD</a>
        <a href="../practical 10/admin_dashboard.php" class="nav-btn">Admin Portal</a>
        <a href="../practical 9/index.php" class="nav-btn">Register</a>
        <a href="../practical 2/contact.html" class="nav-btn">Contact</a>
    </nav>

    <main class="container">

        <?php if (!empty($success)): ?>
            <div class="form-message success" style="display: block; margin-bottom: 20px;">
                <strong>✓ Success:</strong> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="form-message error" style="display: block; margin-bottom: 20px;">
                <strong>✗ Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= $totalCount ?></h3>
                <p>Total University Events</p>
            </div>
            <div class="stat-card">
                <h3 style="color: #166534;"><?= $openCount ?></h3>
                <p>Open for Registration</p>
            </div>
            <div class="stat-card">
                <h3 style="color: #991b1b;"><?= $closedCount ?></h3>
                <p>Completed / Closed</p>
            </div>
            <div class="stat-card">
                <h3><?= count($events) ?></h3>
                <p>Matching Filter Results</p>
            </div>
        </div>

        <section class="form-modal-box" id="eventFormSection">
            <h3 style="color: var(--primary); margin-bottom: 15px;">
                <?= $editEvent ? "Edit Event #" . htmlspecialchars($editEvent['event_id']) : "Create New Campus Event" ?>
            </h3>
            <form action="index.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="form_action" value="<?= $editEvent ? 'edit' : 'add' ?>">
                <?php if ($editEvent): ?>
                    <input type="hidden" name="event_id" value="<?= htmlspecialchars($editEvent['event_id']) ?>">
                    <input type="hidden" name="existing_poster" value="<?= htmlspecialchars($editEvent['poster'] ?? '') ?>">
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
                    <div class="form-group">
                        <label for="title">Event Title <span class="required-star">*</span></label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            class="form-control" 
                            placeholder="e.g. Annual Technical Hackathon"
                            value="<?= htmlspecialchars($editEvent['title'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="category">Category <span class="required-star">*</span></label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">-- Select Category --</option>
                            <?php
                            $categories = ['Technical', 'Cultural', 'Sports', 'Academic', 'Workshop'];
                            $selCat = $editEvent['category'] ?? '';
                            foreach ($categories as $cat) {
                                $selected = ($selCat === $cat) ? 'selected' : '';
                                echo "<option value=\"$cat\" $selected>$cat</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="event_date">Event Date <span class="required-star">*</span></label>
                        <input 
                            type="date" 
                            id="event_date" 
                            name="event_date" 
                            class="form-control" 
                            value="<?= htmlspecialchars($editEvent['event_date'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="venue">Event Venue <span class="required-star">*</span></label>
                        <input 
                            type="text" 
                            id="venue" 
                            name="venue" 
                            class="form-control" 
                            placeholder="e.g. Auditorium / Sports Complex"
                            value="<?= htmlspecialchars($editEvent['venue'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="status">Registration Status <span class="required-star">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <?php
                            $selStatus = $editEvent['status'] ?? 'Open';
                            foreach (['Open', 'Closed'] as $st) {
                                $selected = ($selStatus === $st) ? 'selected' : '';
                                echo "<option value=\"$st\" $selected>$st</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="poster">
                            Event Poster Upload
                            <?php if ($editEvent && !empty($editEvent['poster'])): ?>
                                <span style="font-size: 11px; color: #166534;">(Has current poster)</span>
                            <?php endif; ?>
                        </label>
                        <input 
                            type="file" 
                            id="poster" 
                            name="poster" 
                            class="form-control" 
                            accept=".jpg,.jpeg,.png,.webp"
                        >
                        <span class="file-hint">JPG, PNG, WEBP allowed (Max size: 2MB). Auto-renamed securely.</span>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label for="description">Event Description & Instructions</label>
                    <textarea 
                        id="description" 
                        name="description" 
                        class="form-control" 
                        rows="3" 
                        placeholder="Provide event overview, schedule, rules, and student guidelines..."
                    ><?= htmlspecialchars($editEvent['description'] ?? '') ?></textarea>
                </div>

                <div class="button-area" style="margin-top: 20px;">
                    <button type="submit" class="btn" style="flex: 2;">
                        <?= $editEvent ? "Update Event Details" : "Publish Event" ?>
                    </button>
                    <?php if ($editEvent): ?>
                        <a href="index.php" class="btn" style="flex: 1; background: #64748b; text-align: center;">Cancel Edit</a>
                    <?php else: ?>
                        <button type="reset" class="btn" style="flex: 1; background: #64748b;">Reset Form</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="filter-panel">
            <h3 style="color: var(--primary); margin-bottom: 12px; font-size: 16px;">🔍 Filter & Search Events</h3>
            <form action="index.php" method="GET" class="filter-grid">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="q" style="font-size: 12px;">Search Keyword</label>
                    <input 
                        type="text" 
                        id="q" 
                        name="q" 
                        class="form-control" 
                        placeholder="Search by title, venue, or description..."
                        value="<?= htmlspecialchars($search) ?>"
                    >
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_category" style="font-size: 12px;">Category</label>
                    <select id="filter_category" name="category" class="form-control">
                        <option value="">All Categories</option>
                        <option value="Technical" <?= $filterCategory === 'Technical' ? 'selected' : '' ?>>Technical</option>
                        <option value="Cultural" <?= $filterCategory === 'Cultural' ? 'selected' : '' ?>>Cultural</option>
                        <option value="Sports" <?= $filterCategory === 'Sports' ? 'selected' : '' ?>>Sports</option>
                        <option value="Academic" <?= $filterCategory === 'Academic' ? 'selected' : '' ?>>Academic</option>
                        <option value="Workshop" <?= $filterCategory === 'Workshop' ? 'selected' : '' ?>>Workshop</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_status" style="font-size: 12px;">Status</label>
                    <select id="filter_status" name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="Open" <?= $filterStatus === 'Open' ? 'selected' : '' ?>>Open</option>
                        <option value="Closed" <?= $filterStatus === 'Closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>

                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn" style="padding: 10px 16px;">Filter</button>
                    <a href="index.php" class="btn" style="padding: 10px 14px; background: #64748b; text-decoration: none;">Reset</a>
                </div>
            </form>
        </section>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px; flex-wrap: wrap; gap: 10px; width: 100%;">
            <h3 style="color: var(--primary);">Campus Events Directory (Showing <?= count($events) ?>)</h3>
            <a href="index.php?download=sql" class="btn" style="background: #475569; font-size: 13px;">💾 Download SQL Dump</a>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Poster</th>
                        <th>ID</th>
                        <th>Event Title</th>
                        <th>Category</th>
                        <th>Event Date</th>
                        <th>Venue</th>
                        <th>Status</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--muted); padding: 25px;">
                                No campus events found matching your filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($events as $ev): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($ev['poster']) && file_exists($uploadDir . $ev['poster'])): ?>
                                        <a href="uploads/<?= htmlspecialchars($ev['poster']) ?>" target="_blank" title="Click to view full poster">
                                            <img src="uploads/<?= htmlspecialchars($ev['poster']) ?>" alt="Poster" class="poster-thumb">
                                        </a>
                                    <?php else: ?>
                                        <div class="no-poster-box">NO POSTER</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($ev['event_id']) ?></td>
                                <td><strong><?= htmlspecialchars($ev['title']) ?></strong></td>
                                <td><span class="badge-category"><?= htmlspecialchars($ev['category']) ?></span></td>
                                <td><?= htmlspecialchars($ev['event_date']) ?></td>
                                <td><?= htmlspecialchars($ev['venue']) ?></td>
                                <td>
                                    <span class="badge-status <?= $ev['status'] === 'Open' ? 'status-open' : 'status-closed' ?>">
                                        <?= htmlspecialchars($ev['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <small style="color: var(--muted); display: block; max-width: 260px;">
                                        <?= htmlspecialchars(substr($ev['description'] ?? 'No description provided.', 0, 75)) ?><?= strlen($ev['description'] ?? '') > 75 ? '...' : '' ?>
                                    </small>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                        <a href="index.php?edit_id=<?= $ev['event_id'] ?>#eventFormSection" class="btn-action btn-edit">Edit</a>
                                        <a 
                                            href="index.php?action=delete&id=<?= $ev['event_id'] ?>" 
                                            class="btn-action btn-delete" 
                                            onclick="return confirm('Are you sure you want to permanently delete event #<?= $ev['event_id'] ?> (<?= htmlspecialchars(addslashes($ev['title'])) ?>)?');"
                                        >Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <footer>
        <div class="footer">
            <p>&copy; 2026 Student Hub Portal | All Rights Reserved.</p>
        </div>
    </footer>

    <script src="../practical 4/script.js"></script>
</body>
</html>
