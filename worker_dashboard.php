<!DOCTYPE html>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require("connect-db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'worker') {
    header("Location: login.php");
    exit();
}

$worker_id = $_SESSION['user_id'];

// Handle job application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply') {
    $request_id = $_POST['request_id'];
    // Check if already applied
    $check = $db->prepare("SELECT 1 FROM apply WHERE worker_id = ? AND request_id = ?");
    $check->execute([$worker_id, $request_id]);
    if ($check->fetch()) {
        $error = "You have already applied for this job.";
    } else {
        $ins = $db->prepare("INSERT INTO apply (worker_id, request_id, status) VALUES (?, ?, 'Submitted')");
        $ins->execute([$worker_id, $request_id]);
        $success = "Application submitted!";
    }
}

// Handle job completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_job') {
    $request_id = $_POST['request_id'];

    $del = $db->prepare("DELETE FROM request WHERE request_id = ?");

    if ($del->execute([$request_id])){
        $success = "Job marked as completed and removed from system.";

        header("Location: worker_dashboard.php?success=Job completed!");
        exit();
    } else {
        $error = "Failed to complete the job. Please try again.";
    }
}

// Fetch worker info
$stmt = $db->prepare("SELECT * FROM workers WHERE worker_id = ?");
$stmt->execute([$worker_id]);
$worker = $stmt->fetch();

// Search/filter params
$search = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_urgency = $_GET['urgency'] ?? '';

// Build query for available jobs (In Progress status means owner approved → open for vendors)
$sql = "
    SELECT r.*, p.street, p.apartment
    FROM request r
    JOIN property p ON r.property_id = p.property_id
    WHERE r.status = 'In Progress'
    AND r.request_id NOT IN (SELECT request_id FROM assigned)
";
$params = [];

if ($search) {
    $sql .= " AND (r.description LIKE ? OR p.street LIKE ? OR r.type LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_type) {
    $sql .= " AND r.type = ?";
    $params[] = $filter_type;
}
if ($filter_urgency) {
    $sql .= " AND r.urgency = ?";
    $params[] = $filter_urgency;
}
$sql .= " ORDER BY r.date DESC";

$jobs_stmt = $db->prepare($sql);
$jobs_stmt->execute($params);
$jobs = $jobs_stmt->fetchAll();

// Worker's own applications
$apps_stmt = $db->prepare("
    SELECT a.*, r.type, r.urgency, r.description, r.status AS req_status, p.street
    FROM apply a
    JOIN request r ON a.request_id = r.request_id
    JOIN property p ON r.property_id = p.property_id
    WHERE a.worker_id = ?
    ORDER BY r.date DESC
");
$apps_stmt->execute([$worker_id]);
$applications = $apps_stmt->fetchAll();

// Worker's assigned jobs
$assigned_stmt = $db->prepare("
    SELECT r.*, p.street FROM assigned ass
    JOIN request r ON ass.request_id = r.request_id
    JOIN property p ON r.property_id = p.property_id
    WHERE ass.worker_id = ?
");
$assigned_stmt->execute([$worker_id]);
$assigned_jobs = $assigned_stmt->fetchAll();

// Applied request IDs for UI
$applied_ids = array_column($applications, 'request_id');
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Dashboard | Property Management</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #f4f6fb;
            --surface: #ffffff;
            --surface2: #f8f9fc;
            --border: #e5e9f2;
            --text: #111827;
            --muted: #6b7280;
            --accent: #2563eb;
            --accent-light: #eff6ff;
            --green: #16a34a;
            --green-light: #f0fdf4;
            --orange: #d97706;
            --orange-light: #fffbeb;
            --red: #dc2626;
            --red-light: #fef2f2;
            --radius: 12px;
        }

        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

        nav {
            background: var(--surface); border-bottom: 1px solid var(--border);
            padding: 0 32px; height: 60px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }
        .nav-brand { font-weight: 700; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .nav-brand span { color: var(--accent); }
        .nav-right { display: flex; align-items: center; gap: 16px; }
        .nav-user { font-size: 14px; color: var(--muted); }
        .nav-user strong { color: var(--text); }
        .logout-btn { font-size: 13px; color: var(--muted); text-decoration: none; padding: 6px 12px; border: 1px solid var(--border); border-radius: 8px; transition: 0.15s; }
        .logout-btn:hover { background: var(--surface2); }

        .main { max-width: 960px; margin: 0 auto; padding: 32px 24px; }
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 24px; font-weight: 700; }
        .page-header p { color: var(--muted); font-size: 14px; margin-top: 4px; }

        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px; }
        .stat-label { font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; }
        .stat-value { font-size: 26px; font-weight: 700; margin-top: 6px; font-family: 'DM Mono', monospace; }

        .tabs { display: flex; gap: 0; border-bottom: 2px solid var(--border); margin-bottom: 24px; }
        .tab { padding: 10px 20px; font-size: 14px; font-weight: 600; color: var(--muted); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: 0.15s; }
        .tab.active { color: var(--accent); border-bottom-color: var(--accent); }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* SEARCH BAR */
        .search-bar {
            display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;
        }

        .search-input-wrap { flex: 1; min-width: 200px; position: relative; }
        .search-input-wrap input {
            width: 100%; padding: 10px 12px 10px 36px;
            border: 1px solid var(--border); border-radius: 8px;
            font-size: 14px; font-family: 'DM Sans', sans-serif;
            background: var(--surface); outline: none; transition: 0.15s;
        }
        .search-input-wrap input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 15px; }

        .filter-select {
            padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px;
            font-size: 14px; font-family: 'DM Sans', sans-serif;
            background: var(--surface); outline: none; cursor: pointer; transition: 0.15s;
        }
        .filter-select:focus { border-color: var(--accent); }

        .btn-search {
            background: var(--accent); color: white; border: none;
            padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 600;
            cursor: pointer; font-family: 'DM Sans', sans-serif; transition: 0.15s;
        }
        .btn-search:hover { background: #1d4ed8; }

        .card-list { display: flex; flex-direction: column; gap: 12px; }

        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px; }
        .card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }

        .badge { padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-type { background: #e0e7ff; color: #4338ca; }
        .badge-high { background: var(--red-light); color: var(--red); }
        .badge-medium { background: #ffedd5; color: #c2410c; }
        .badge-low { background: var(--green-light); color: var(--green); }
        .badge-submitted { background: #fef9c3; color: #854d0e; }
        .badge-approved { background: var(--green-light); color: var(--green); }
        .badge-rejected { background: var(--red-light); color: var(--red); }

        .job-desc { font-size: 13px; color: var(--text); margin: 8px 0 6px; }
        .job-meta { font-size: 12px; color: var(--muted); }
        .job-location { font-size: 13px; color: var(--accent); margin-top: 4px; display: flex; align-items: center; gap: 4px; }

        .btn-apply {
            background: var(--accent); color: white; border: none;
            padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
            cursor: pointer; font-family: 'DM Sans', sans-serif; transition: 0.15s;
            white-space: nowrap; flex-shrink: 0;
        }
        .btn-apply:hover { background: #1d4ed8; }
        .btn-applied { background: var(--green-light); color: var(--green); border: 1px solid #bbf7d0; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; white-space: nowrap; flex-shrink: 0; cursor: default; }

        .empty-state { text-align: center; padding: 48px 24px; color: var(--muted); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); }
        .empty-state .icon { font-size: 36px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; }

        .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background: var(--green-light); color: var(--green); border: 1px solid #bbf7d0; }
        .alert-error { background: var(--red-light); color: var(--red); border: 1px solid #fecaca; }

        .spec-tag { display: inline-block; background: var(--accent-light); color: var(--accent); padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 4px; }

        .btn-complete {
        background: var(--green);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.15s;
        margin-top: 12px;
        }

        .btn-complete:hover {
        background: #15803d;
        }

        @media (max-width: 640px) {
            .stats { grid-template-columns: repeat(2, 1fr); }
            .search-bar { flex-direction: column; }
        }
    </style>
</head>
<body>

<nav>
    <div class="nav-brand">🏢 <span>Property</span>Management</div>
    <div class="nav-right">
        <span class="nav-user">Logged in as <strong><?php echo htmlspecialchars($worker['name']); ?></strong></span>
        <a href="login.php" class="logout-btn">Log out</a>
    </div>
</nav>

<div class="main">
    <div class="page-header">
        <h1>Maintenance Worker Dashboard</h1>
        <p>Browse and apply for maintenance jobs · Specialization: <span class="spec-tag"><?php echo htmlspecialchars($worker['specialization']); ?></span></p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat">
            <div class="stat-label">Available Jobs</div>
            <div class="stat-value"><?php echo count($jobs); ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Applied Jobs</div>
            <div class="stat-value"><?php echo count($applications); ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Assigned Jobs</div>
            <div class="stat-value"><?php echo count($assigned_jobs); ?></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <div class="tab active" onclick="switchTab('marketplace')">Job Marketplace</div>
        <div class="tab" onclick="switchTab('applications')">My Applications (<?php echo count($applications); ?>)</div>
        <div class="tab" onclick="switchTab('assigned')">Assigned (<?php echo count($assigned_jobs); ?>)</div>
    </div>

    <!-- MARKETPLACE TAB -->
    <div class="tab-panel active" id="tab-marketplace">
        <!-- Search & Filter -->
        <form method="GET" class="search-bar">
            <div class="search-input-wrap">
                <span class="search-icon">🔍</span>
                <input type="text" name="search" placeholder="Search by address, category, or description..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select name="type" class="filter-select">
                <option value="">All Types</option>
                <option <?php if ($filter_type === 'Plumbing') echo 'selected'; ?>>Plumbing</option>
                <option <?php if ($filter_type === 'Electrical') echo 'selected'; ?>>Electrical</option>
                <option <?php if ($filter_type === 'HVAC') echo 'selected'; ?>>HVAC</option>
                <option <?php if ($filter_type === 'Carpentry') echo 'selected'; ?>>Carpentry</option>
                <option <?php if ($filter_type === 'Appliances') echo 'selected'; ?>>Appliances</option>
                <option <?php if ($filter_type === 'Other') echo 'selected'; ?>>Other</option>
            </select>
            <select name="urgency" class="filter-select">
                <option value="">All Urgencies</option>
                <option value="High" <?php if ($filter_urgency === 'High') echo 'selected'; ?>>High</option>
                <option value="Medium" <?php if ($filter_urgency === 'Medium') echo 'selected'; ?>>Medium</option>
                <option value="Low" <?php if ($filter_urgency === 'Low') echo 'selected'; ?>>Low</option>
            </select>
            <button type="submit" class="btn-search">Filter</button>
            <?php if ($search || $filter_type || $filter_urgency): ?>
            <a href="worker_dashboard.php" style="align-self:center; font-size:13px; color:var(--muted); text-decoration:none;">Clear</a>
            <?php endif; ?>
        </form>

        <div style="font-size:13px; color:var(--muted); margin-bottom:14px;">
            Showing <?php echo count($jobs); ?> available job<?php echo count($jobs) != 1 ? 's' : ''; ?>
        </div>

        <div class="card-list">
            <?php if (empty($jobs)): ?>
            <div class="empty-state">
                <div class="icon">🔧</div>
                <p>No available jobs match your search.</p>
            </div>
            <?php else: ?>
            <?php foreach ($jobs as $job): ?>
            <div class="card">
                <div class="card-top">
                    <div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                            <span class="badge badge-type"><?php echo htmlspecialchars($job['type']); ?></span>
                            <?php $urg = strtolower($job['urgency']); ?>
                            <span class="badge badge-<?php echo in_array($urg, ['high','red']) ? 'high' : (in_array($urg, ['medium','orange']) ? 'medium' : 'low'); ?>"><?php echo htmlspecialchars($job['urgency']); ?> urgency</span>
                        </div>
                        <div class="job-location">📍 <?php echo htmlspecialchars($job['street']); ?><?php if ($job['apartment']): ?>, Apt <?php echo htmlspecialchars($job['apartment']); ?><?php endif; ?></div>
                        <div class="job-desc"><?php echo htmlspecialchars($job['description']); ?></div>
                        <div class="job-meta">Posted: <?php echo $job['date']; ?></div>
                    </div>
                    <div>
                        <?php if (in_array($job['request_id'], $applied_ids)): ?>
                            <span class="btn-applied">✓ Applied</span>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="apply">
                                <input type="hidden" name="request_id" value="<?php echo $job['request_id']; ?>">
                                <button type="submit" class="btn-apply">Apply for Job</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MY APPLICATIONS TAB -->
    <div class="tab-panel" id="tab-applications">
        <div class="section-title" style="margin-bottom:16px;">My Applications</div>
        <div class="card-list">
            <?php if (empty($applications)): ?>
            <div class="empty-state"><div class="icon">📋</div><p>You haven't applied to any jobs yet.</p></div>
            <?php else: ?>
            <?php foreach ($applications as $app): ?>
            <div class="card">
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
                    <span class="badge badge-type"><?php echo htmlspecialchars($app['type']); ?></span>
                    <?php $as = strtolower($app['status']); ?>
                    <span class="badge badge-<?php echo $as; ?>"><?php echo htmlspecialchars($app['status']); ?></span>
                </div>
                <div class="job-location">📍 <?php echo htmlspecialchars($app['street']); ?></div>
                <div class="job-desc"><?php echo htmlspecialchars($app['description']); ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ASSIGNED TAB -->
    <div class="tab-panel" id="tab-assigned">
        <div class="section-title" style="margin-bottom:16px;">Assigned Jobs</div>
        <div class="card-list">
            <?php if (empty($assigned_jobs)): ?>
            <div class="empty-state"><div class="icon">📅</div><p>No jobs assigned to you yet.</p></div>
            <?php else: ?>
            <?php foreach ($assigned_jobs as $job): ?>
            <div class="card">
                <div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
                    <span class="badge badge-type"><?php echo htmlspecialchars($job['type']); ?></span>
                </div>
                <div class="job-location">📍 <?php echo htmlspecialchars($job['street']); ?></div>
                <div class="job-desc"><?php echo htmlspecialchars($job['description']); ?></div>
                <div class="job-meta">Status: <?php echo htmlspecialchars($job['status']); ?> · Date: <?php echo $job['date']; ?></div>
                </div>

                <form method="POST" onsubmit="return confirm('Mark this job as completed? This will remove it from the system.');">
                    <input type="hidden" name="action" value="complete_job">
                    <input type="hidden" name="request_id" value="<?php echo $job['request_id']; ?>">
                    <button type="submit" class="btn-complete">✓ Mark Completed</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function switchTab(name) {
    const names = ['marketplace', 'applications', 'assigned'];
    document.querySelectorAll('.tab').forEach((t, i) => t.classList.toggle('active', names[i] === name));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
}
</script>
</body>
</html>
