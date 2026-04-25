<!DOCTYPE html>
<?php
session_start();
require("connect-db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit();
}

$owner_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_property') {
        $street = $_POST['street'];
        $apartment = $_POST['apartment'] ?: null;
        $insert = $db->prepare("INSERT INTO property (street, apartment) VALUES (?, ?)");
        $insert->execute([$street, $apartment]);
        $new_prop_id = $db->lastInsertId();
        $link = $db->prepare("INSERT INTO owns (owner_id, property_id) VALUES (?, ?)");
        $link->execute([$owner_id, $new_prop_id]);
        $success = "Property registered! Waiting for admin approval.";
    }

    if ($action === 'update_request') {
        $request_id = $_POST['request_id'];
        $new_status = $_POST['new_status'];
        $upd = $db->prepare("UPDATE request SET status = ? WHERE request_id = ?");
        $upd->execute([$new_status, $request_id]);
        $success = "Request updated to '$new_status'.";
    }

    // Approve tenant property request (Feature 3)
    if ($action === 'approve_tenant_request') {
        $pr_id = $_POST['pr_id'];
        $tenant_id = $_POST['tenant_id'];
        $property_id = $_POST['property_id'];
        // Add to lives table
        try {
            $ins = $db->prepare("INSERT INTO lives (tenant_id, property_id) VALUES (?, ?)");
            $ins->execute([$tenant_id, $property_id]);
        } catch (Exception $e) {}
        // Update request status
        $upd = $db->prepare("UPDATE property_request SET status = 'Approved' WHERE request_id = ?");
        $upd->execute([$pr_id]);
        $success = "Tenant approved and assigned to property!";
    }

    if ($action === 'reject_tenant_request') {
        $pr_id = $_POST['pr_id'];
        $upd = $db->prepare("UPDATE property_request SET status = 'Rejected' WHERE request_id = ?");
        $upd->execute([$pr_id]);
        $success = "Tenant request rejected.";
    }

    // Assign vendor to job (Feature 6)
    if ($action === 'assign_vendor') {
        $worker_id = $_POST['worker_id'];
        $request_id = $_POST['request_id'];
        // Check not already assigned
        $check = $db->prepare("SELECT 1 FROM assigned WHERE request_id = ?");
        $check->execute([$request_id]);
        if (!$check->fetch()) {
            $ins = $db->prepare("INSERT INTO assigned (worker_id, request_id) VALUES (?, ?)");
            $ins->execute([$worker_id, $request_id]);
            // Update application status
            $upd = $db->prepare("UPDATE apply SET status = 'Approved' WHERE worker_id = ? AND request_id = ?");
            $upd->execute([$worker_id, $request_id]);
            // Reject other applicants
            $reject = $db->prepare("UPDATE apply SET status = 'Rejected' WHERE request_id = ? AND worker_id != ?");
            $reject->execute([$request_id, $worker_id]);
            $success = "Vendor assigned to job!";
        } else {
            $error = "A vendor is already assigned to this job.";
        }
    }
}

$stmt = $db->prepare("SELECT * FROM property_owner WHERE owner_id = ?");
$stmt->execute([$owner_id]);
$owner = $stmt->fetch();

$props = $db->prepare("
    SELECT p.* FROM property p
    JOIN owns o ON p.property_id = o.property_id
    WHERE o.owner_id = ?
");
$props->execute([$owner_id]);
$properties = $props->fetchAll();
$property_ids = array_column($properties, 'property_id');

$tenants = [];
$requests = [];
$tenant_requests = [];
$job_applications = [];

if (!empty($property_ids)) {
    $placeholders = implode(',', array_fill(0, count($property_ids), '?'));

    $t_stmt = $db->prepare("
        SELECT t.tenant_id, t.name, t.email, l.property_id, p.street
        FROM lives l
        JOIN tenant t ON l.tenant_id = t.tenant_id
        JOIN property p ON l.property_id = p.property_id
        WHERE l.property_id IN ($placeholders)
    ");
    $t_stmt->execute($property_ids);
    $tenants = $t_stmt->fetchAll();

    $r_stmt = $db->prepare("
        SELECT r.*, t.name AS tenant_name, p.street
        FROM request r
        JOIN tenant t ON r.tenant_id = t.tenant_id
        JOIN property p ON r.property_id = p.property_id
        WHERE r.property_id IN ($placeholders)
        ORDER BY r.date DESC
    ");
    $r_stmt->execute($property_ids);
    $requests = $r_stmt->fetchAll();

    // Tenant property requests (Feature 3)
    try {
        $tr_stmt = $db->prepare("
            SELECT pr.*, t.name AS tenant_name, t.email AS tenant_email, p.street
            FROM property_request pr
            JOIN tenant t ON pr.tenant_id = t.tenant_id
            JOIN property p ON pr.property_id = p.property_id
            WHERE pr.property_id IN ($placeholders) AND pr.status = 'Pending'
            ORDER BY pr.date DESC
        ");
        $tr_stmt->execute($property_ids);
        $tenant_requests = $tr_stmt->fetchAll();
    } catch (Exception $e) { $tenant_requests = []; }

    // Job applications for owner review (Feature 6)
    try {
        $app_stmt = $db->prepare("
            SELECT a.*, w.name AS worker_name, w.specialization, w.email AS worker_email,
                   r.type, r.urgency, r.description, r.property_id, p.street,
                   (SELECT 1 FROM assigned ass WHERE ass.request_id = a.request_id LIMIT 1) AS already_assigned
            FROM apply a
            JOIN workers w ON a.worker_id = w.worker_id
            JOIN request r ON a.request_id = r.request_id
            JOIN property p ON r.property_id = p.property_id
            WHERE r.property_id IN ($placeholders) AND r.status = 'In Progress'
            ORDER BY a.request_id, a.status DESC
        ");
        $app_stmt->execute($property_ids);
        $job_applications = $app_stmt->fetchAll();
    } catch (Exception $e) { $job_applications = []; }
}

// Group applications by request_id
$apps_by_request = [];
foreach ($job_applications as $app) {
    $apps_by_request[$app['request_id']][] = $app;
}

$total_tenants = count($tenants);
$pending_requests = count(array_filter($requests, fn($r) => $r['status'] === 'Pending'));
$pending_tenant_reqs = count($tenant_requests);
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard | Property Management</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #f4f6fb; --surface: #ffffff; --surface2: #f8f9fc;
            --border: #e5e9f2; --text: #111827; --muted: #6b7280;
            --accent: #2563eb; --accent-light: #eff6ff;
            --green: #16a34a; --green-light: #f0fdf4;
            --orange: #d97706; --orange-light: #fffbeb;
            --red: #dc2626; --red-light: #fef2f2; --radius: 12px;
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        nav { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0 32px; height: 60px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
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
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px; }
        .stat-label { font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; }
        .stat-value { font-size: 26px; font-weight: 700; margin-top: 6px; font-family: 'DM Mono', monospace; }
        .tabs { display: flex; border-bottom: 2px solid var(--border); margin-bottom: 24px; flex-wrap: wrap; }
        .tab { padding: 10px 18px; font-size: 14px; font-weight: 600; color: var(--muted); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: 0.15s; white-space: nowrap; }
        .tab.active { color: var(--accent); border-bottom-color: var(--accent); }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .section-title { font-size: 16px; font-weight: 700; }
        .btn-primary { background: var(--accent); color: white; border: none; padding: 9px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; transition: 0.15s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-sm { padding: 5px 12px; font-size: 12px; font-weight: 600; border-radius: 6px; border: none; cursor: pointer; font-family: 'DM Sans', sans-serif; transition: 0.15s; }
        .btn-approve { background: var(--green-light); color: var(--green); border: 1px solid #bbf7d0; }
        .btn-approve:hover { background: #dcfce7; }
        .btn-reject { background: var(--red-light); color: var(--red); border: 1px solid #fecaca; }
        .btn-reject:hover { background: #fee2e2; }
        .btn-inprogress { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .btn-inprogress:hover { background: #bfdbfe; }
        .btn-assign { background: var(--accent); color: white; border: none; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; }
        .btn-assign:hover { background: #1d4ed8; }
        .card-list { display: flex; flex-direction: column; gap: 12px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px; }
        .card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .card-title { font-size: 15px; font-weight: 600; }
        .card-sub { font-size: 13px; color: var(--muted); margin-top: 3px; }
        .card-actions { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }
        .badge { padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-type { background: #e0e7ff; color: #4338ca; }
        .badge-pending { background: #fef9c3; color: #854d0e; }
        .badge-progress { background: #dbeafe; color: #1d4ed8; }
        .badge-completed { background: var(--green-light); color: var(--green); }
        .badge-cancelled { background: var(--red-light); color: var(--red); }
        .badge-high { background: var(--red-light); color: var(--red); }
        .badge-medium { background: #ffedd5; color: #c2410c; }
        .badge-low { background: var(--green-light); color: var(--green); }
        .badge-approved-prop { background: var(--green-light); color: var(--green); }
        .badge-pending-prop { background: var(--orange-light); color: var(--orange); }
        .badge-rejected-prop { background: var(--red-light); color: var(--red); }
        .request-meta { font-size: 12px; color: var(--muted); margin-top: 8px; }
        .request-desc { font-size: 13px; color: var(--text); margin-top: 6px; }
        .empty-state { text-align: center; padding: 48px 24px; color: var(--muted); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); }
        .empty-state .icon { font-size: 36px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; }
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background: var(--green-light); color: var(--green); border: 1px solid #bbf7d0; }
        .alert-error { background: var(--red-light); color: var(--red); border: 1px solid #fecaca; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--surface); border-radius: 16px; padding: 28px; width: 100%; max-width: 480px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
        .modal h2 { font-size: 18px; font-weight: 700; margin-bottom: 20px; }
        .form-field { margin-bottom: 16px; }
        .form-field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .form-field input { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; font-family: 'DM Sans', sans-serif; background: var(--surface2); outline: none; transition: 0.15s; }
        .form-field input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }
        .btn-secondary { background: var(--surface2); color: var(--text); border: 1px solid var(--border); padding: 9px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; }
        /* Applicant sub-cards */
        .applicant-list { margin-top: 12px; border-top: 1px solid var(--border); padding-top: 12px; display: flex; flex-direction: column; gap: 8px; }
        .applicant-card { background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .applicant-info { font-size: 13px; }
        .applicant-info strong { display: block; color: var(--text); }
        .applicant-info span { color: var(--muted); font-size: 12px; }
        .assigned-label { font-size: 12px; font-weight: 600; color: var(--green); background: var(--green-light); padding: 4px 10px; border-radius: 6px; border: 1px solid #bbf7d0; }
        @media (max-width: 640px) { .stats { grid-template-columns: repeat(2,1fr); } }
    </style>
</head>
<body>
<nav>
    <div class="nav-brand">🏢 <span>Property</span>Management</div>
    <div class="nav-right">
        <span class="nav-user">Logged in as <strong><?php echo htmlspecialchars($owner['name']); ?></strong></span>
        <a href="login.php" class="logout-btn">Log out</a>
    </div>
</nav>

<div class="main">
    <div class="page-header">
        <h1>Property Owner Dashboard</h1>
        <p>Manage your properties, tenants, and maintenance requests</p>
    </div>

    <?php if (!empty($success)): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="stats">
        <div class="stat"><div class="stat-label">Properties</div><div class="stat-value"><?php echo count($properties); ?></div></div>
        <div class="stat"><div class="stat-label">Tenants</div><div class="stat-value"><?php echo $total_tenants; ?></div></div>
        <div class="stat"><div class="stat-label">Pending Requests</div><div class="stat-value"><?php echo $pending_requests; ?></div></div>
        <div class="stat"><div class="stat-label">Tenant Requests</div><div class="stat-value"><?php echo $pending_tenant_reqs; ?></div></div>
    </div>

    <div class="tabs">
        <div class="tab active" onclick="switchTab('properties')">Properties</div>
        <div class="tab" onclick="switchTab('tenantreqs')">Tenant Requests <?php if($pending_tenant_reqs): ?>(<?php echo $pending_tenant_reqs; ?>)<?php endif; ?></div>
        <div class="tab" onclick="switchTab('tenants')">Tenants</div>
        <div class="tab" onclick="switchTab('requests')">Maintenance</div>
        <div class="tab" onclick="switchTab('vendors')">Job Applications</div>
    </div>

    <!-- PROPERTIES -->
    <div class="tab-panel active" id="tab-properties">
        <div class="section-header">
            <h2 class="section-title">Your Properties</h2>
            <button class="btn-primary" onclick="document.getElementById('addPropertyModal').classList.add('active')">+ Register Property</button>
        </div>
        <div class="card-list">
            <?php if (empty($properties)): ?>
            <div class="empty-state"><div class="icon">🏠</div><p>No properties registered yet.</p></div>
            <?php else: ?>
            <?php foreach ($properties as $prop): ?>
            <?php
                $t_count = count(array_filter($tenants, fn($t) => $t['property_id'] == $prop['property_id']));
                $r_count = count(array_filter($requests, fn($r) => $r['property_id'] == $prop['property_id']));
                $status = $prop['approval_status'] ?? 'Approved';
                $status_class = $status === 'Approved' ? 'badge-approved-prop' : ($status === 'Rejected' ? 'badge-rejected-prop' : 'badge-pending-prop');
            ?>
            <div class="card">
                <div class="card-top">
                    <div>
                        <div class="card-title">🏠 <?php echo htmlspecialchars($prop['street']); ?><?php if ($prop['apartment']): ?>, Apt <?php echo htmlspecialchars($prop['apartment']); ?><?php endif; ?></div>
                        <div class="card-sub"><?php echo $t_count; ?> tenant(s) · <?php echo $r_count; ?> request(s)</div>
                    </div>
                    <span class="badge <?php echo $status_class; ?>"><?php echo $status; ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- TENANT REQUESTS (Feature 3) -->
    <div class="tab-panel" id="tab-tenantreqs">
        <div class="section-header"><h2 class="section-title">Pending Tenant Requests</h2></div>
        <div class="card-list">
            <?php if (empty($tenant_requests)): ?>
            <div class="empty-state"><div class="icon">👥</div><p>No pending tenant requests.</p></div>
            <?php else: ?>
            <?php foreach ($tenant_requests as $tr): ?>
            <div class="card">
                <div class="card-top">
                    <div>
                        <div class="card-title">👤 <?php echo htmlspecialchars($tr['tenant_name']); ?></div>
                        <div class="card-sub"><?php echo htmlspecialchars($tr['tenant_email']); ?></div>
                        <div class="request-meta">Requesting to join: <?php echo htmlspecialchars($tr['street']); ?> · <?php echo $tr['date']; ?></div>
                    </div>
                    <div class="card-actions">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="approve_tenant_request">
                            <input type="hidden" name="pr_id" value="<?php echo $tr['request_id']; ?>">
                            <input type="hidden" name="tenant_id" value="<?php echo $tr['tenant_id']; ?>">
                            <input type="hidden" name="property_id" value="<?php echo $tr['property_id']; ?>">
                            <button class="btn-sm btn-approve" type="submit">✓ Approve</button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="reject_tenant_request">
                            <input type="hidden" name="pr_id" value="<?php echo $tr['request_id']; ?>">
                            <button class="btn-sm btn-reject" type="submit">✕ Reject</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- TENANTS -->
    <div class="tab-panel" id="tab-tenants">
        <div class="section-header"><h2 class="section-title">Current Tenants</h2></div>
        <div class="card-list">
            <?php if (empty($tenants)): ?>
            <div class="empty-state"><div class="icon">👥</div><p>No tenants assigned yet.</p></div>
            <?php else: ?>
            <?php foreach ($tenants as $t): ?>
            <div class="card">
                <div class="card-top">
                    <div>
                        <div class="card-title">👤 <?php echo htmlspecialchars($t['name']); ?></div>
                        <div class="card-sub"><?php echo htmlspecialchars($t['email']); ?> · <?php echo htmlspecialchars($t['street']); ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAINTENANCE REQUESTS -->
    <div class="tab-panel" id="tab-requests">
        <div class="section-header"><h2 class="section-title">Maintenance Requests</h2></div>
        <div class="card-list">
            <?php if (empty($requests)): ?>
            <div class="empty-state"><div class="icon">🔧</div><p>No maintenance requests yet.</p></div>
            <?php else: ?>
            <?php foreach ($requests as $req): ?>
            <div class="card">
                <div class="card-top">
                    <div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                            <span class="badge badge-type"><?php echo htmlspecialchars($req['type']); ?></span>
                            <?php $urg = strtolower($req['urgency']); ?>
                            <span class="badge badge-<?php echo in_array($urg,['high','red'])?'high':(in_array($urg,['medium','orange'])?'medium':'low'); ?>"><?php echo htmlspecialchars($req['urgency']); ?></span>
                            <?php $sc = match($req['status']){'Pending'=>'badge-pending','In Progress'=>'badge-progress','Completed'=>'badge-completed',default=>'badge-cancelled'}; ?>
                            <span class="badge <?php echo $sc; ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                        </div>
                        <div class="request-desc"><?php echo htmlspecialchars($req['description']); ?></div>
                        <div class="request-meta">Tenant: <?php echo htmlspecialchars($req['tenant_name']); ?> · <?php echo htmlspecialchars($req['street']); ?> · <?php echo $req['date']; ?></div>
                    </div>
                    <div class="card-actions">
                        <?php if ($req['status'] === 'Pending'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="update_request">
                            <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                            <input type="hidden" name="new_status" value="In Progress">
                            <button class="btn-sm btn-inprogress" type="submit">Approve</button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="update_request">
                            <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                            <input type="hidden" name="new_status" value="Cancelled">
                            <button class="btn-sm btn-reject" type="submit">Reject</button>
                        </form>
                        <?php elseif ($req['status'] === 'In Progress'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="update_request">
                            <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                            <input type="hidden" name="new_status" value="Completed">
                            <button class="btn-sm btn-approve" type="submit">Complete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- JOB APPLICATIONS (Feature 6) -->
    <div class="tab-panel" id="tab-vendors">
        <div class="section-header"><h2 class="section-title">Job Applications</h2></div>
        <p style="font-size:13px;color:var(--muted);margin-bottom:16px;">Review worker applications for approved maintenance jobs and assign a vendor.</p>
        <div class="card-list">
            <?php if (empty($apps_by_request)): ?>
            <div class="empty-state"><div class="icon">📋</div><p>No job applications yet.</p></div>
            <?php else: ?>
            <?php foreach ($apps_by_request as $req_id => $apps): ?>
            <?php $first = $apps[0]; $already = $first['already_assigned']; ?>
            <div class="card">
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                    <span class="badge badge-type"><?php echo htmlspecialchars($first['type']); ?></span>
                    <?php $urg = strtolower($first['urgency']); ?>
                    <span class="badge badge-<?php echo in_array($urg,['high','red'])?'high':(in_array($urg,['medium','orange'])?'medium':'low'); ?>"><?php echo htmlspecialchars($first['urgency']); ?></span>
                </div>
                <div style="font-size:13px;color:var(--text);margin-bottom:4px;"><?php echo htmlspecialchars($first['description']); ?></div>
                <div style="font-size:12px;color:var(--muted);margin-bottom:4px;">📍 <?php echo htmlspecialchars($first['street']); ?></div>
                <?php if ($already): ?>
                <div style="font-size:12px;color:var(--green);margin-top:4px;font-weight:600;">✓ Vendor already assigned</div>
                <?php endif; ?>
                <div class="applicant-list">
                    <div style="font-size:12px;font-weight:600;color:var(--muted);margin-bottom:4px;"><?php echo count($apps); ?> applicant(s):</div>
                    <?php foreach ($apps as $app): ?>
                    <div class="applicant-card">
                        <div class="applicant-info">
                            <strong><?php echo htmlspecialchars($app['worker_name']); ?></strong>
                            <span><?php echo htmlspecialchars($app['specialization']); ?> · <?php echo htmlspecialchars($app['worker_email']); ?></span>
                        </div>
                        <?php if ($app['status'] === 'Approved'): ?>
                            <span class="assigned-label">✓ Assigned</span>
                        <?php elseif ($app['status'] === 'Rejected'): ?>
                            <span style="font-size:12px;color:var(--red);font-weight:600;">Rejected</span>
                        <?php elseif (!$already): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="assign_vendor">
                                <input type="hidden" name="worker_id" value="<?php echo $app['worker_id']; ?>">
                                <input type="hidden" name="request_id" value="<?php echo $req_id; ?>">
                                <button type="submit" class="btn-assign">Assign</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Property Modal -->
<div class="modal-overlay" id="addPropertyModal">
    <div class="modal">
        <h2>Register New Property</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_property">
            <div class="form-field">
                <label>Street Address (include city, state, zip)</label>
                <input type="text" name="street" placeholder="e.g. 123 Main St, Charlottesville, VA 22903" required>
            </div>
            <div class="form-field">
                <label>Apartment / Unit Number (optional)</label>
                <input type="text" name="apartment" placeholder="e.g. 4B">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('addPropertyModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn-primary">Register</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(name) {
    const names = ['properties','tenantreqs','tenants','requests','vendors'];
    document.querySelectorAll('.tab').forEach((t,i) => t.classList.toggle('active', names[i]===name));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-'+name).classList.add('active');
}
</script>
</body>
</html>