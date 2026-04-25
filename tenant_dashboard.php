<!DOCTYPE html>
<?php
session_start();
require("connect-db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant') {
    header("Location: login.php");
    exit();
}

$tenant_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'submit_request') {
        $type = $_POST['type'];
        $urgency = $_POST['urgency'];
        $description = $_POST['description'];
        $prop_stmt = $db->prepare("SELECT property_id FROM lives WHERE tenant_id = ?");
        $prop_stmt->execute([$tenant_id]);
        $prop = $prop_stmt->fetch();
        if ($prop) {
            $count_stmt = $db->prepare("SELECT COUNT(*) FROM request WHERE tenant_id = ?");
            $count_stmt->execute([$tenant_id]);
            $count = $count_stmt->fetchColumn() + 1;
            $insert = $db->prepare("INSERT INTO request (property_id, tenant_id, type, urgency, description, status, date, number) VALUES (?, ?, ?, ?, ?, 'Pending', CURDATE(), ?)");
            $insert->execute([$prop['property_id'], $tenant_id, $type, $urgency, $description, $count]);
            $success = "Maintenance request submitted!";
        } else {
            $error = "You are not assigned to any property yet.";
        }
    }

    if ($action === 'request_property') {
        $property_id = $_POST['property_id'];
        $already = $db->prepare("SELECT 1 FROM lives WHERE tenant_id = ?");
        $already->execute([$tenant_id]);
        if ($already->fetch()) {
            $error = "You are already assigned to a property.";
        } else {
            $ins = $db->prepare("INSERT INTO property_request (tenant_id, property_id, status, date) VALUES (?, ?, 'Pending', CURDATE()) ON DUPLICATE KEY UPDATE status='Pending', date=CURDATE()");
            $ins->execute([$tenant_id, $property_id]);
            $success = "Property request submitted! Waiting for owner approval.";
        }
    }
}

$stmt = $db->prepare("SELECT * FROM tenant WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch();

$prop_stmt = $db->prepare("
    SELECT p.*, po.name AS owner_name
    FROM lives l
    JOIN property p ON l.property_id = p.property_id
    JOIN owns o ON p.property_id = o.property_id
    JOIN property_owner po ON o.owner_id = po.owner_id
    WHERE l.tenant_id = ?
");
$prop_stmt->execute([$tenant_id]);
$property = $prop_stmt->fetch();

$my_requests_stmt = $db->prepare("
    SELECT pr.*, p.street, p.apartment
    FROM property_request pr
    JOIN property p ON pr.property_id = p.property_id
    WHERE pr.tenant_id = ?
    ORDER BY pr.date DESC
");
$my_requests_stmt->execute([$tenant_id]);
$my_prop_requests = $my_requests_stmt->fetchAll();

try {
    $avail_stmt = $db->query("SELECT * FROM property WHERE approval_status = 'Approved' ORDER BY street");
    $available_properties = $avail_stmt->fetchAll();
} catch (Exception $e) {
    $avail_stmt = $db->query("SELECT * FROM property ORDER BY street");
    $available_properties = $avail_stmt->fetchAll();
}

$req_stmt = $db->prepare("SELECT * FROM request WHERE tenant_id = ? ORDER BY date DESC");
$req_stmt->execute([$tenant_id]);
$requests = $req_stmt->fetchAll();

$active = count(array_filter($requests, fn($r) => $r['status'] === 'Pending'));
$in_progress = count(array_filter($requests, fn($r) => $r['status'] === 'In Progress'));
$completed = count(array_filter($requests, fn($r) => $r['status'] === 'Completed'));
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Dashboard | Property Management</title>
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
        .logout-btn:hover { background: var(--surface2); color: var(--text); }
        .main { max-width: 900px; margin: 0 auto; padding: 32px 24px; }
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 24px; font-weight: 700; }
        .page-header p { color: var(--muted); font-size: 14px; margin-top: 4px; }
        .property-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px 24px; margin-bottom: 24px; display: flex; align-items: center; gap: 16px; }
        .prop-icon { width: 44px; height: 44px; background: var(--accent-light); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .prop-info h3 { font-size: 15px; font-weight: 600; }
        .prop-info p { font-size: 13px; color: var(--muted); margin-top: 2px; }
        .no-property { background: var(--orange-light); border: 1px solid #fde68a; border-radius: var(--radius); padding: 16px 20px; font-size: 14px; color: var(--orange); margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; }
        .stat-label { font-size: 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-value { font-size: 28px; font-weight: 700; margin-top: 6px; font-family: 'DM Mono', monospace; }
        .tabs { display: flex; border-bottom: 2px solid var(--border); margin-bottom: 24px; }
        .tab { padding: 10px 20px; font-size: 14px; font-weight: 600; color: var(--muted); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: 0.15s; }
        .tab.active { color: var(--accent); border-bottom-color: var(--accent); }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .section-title { font-size: 16px; font-weight: 700; }
        .btn-primary { background: var(--accent); color: white; border: none; padding: 9px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; transition: 0.15s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary:hover { background: #1d4ed8; }
        .request-list { display: flex; flex-direction: column; gap: 12px; }
        .request-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px; }
        .request-top { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; }
        .badge { padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-type { background: #e0e7ff; color: #4338ca; }
        .badge-pending { background: #fef9c3; color: #854d0e; }
        .badge-progress { background: #dbeafe; color: #1d4ed8; }
        .badge-completed { background: var(--green-light); color: var(--green); }
        .badge-cancelled { background: var(--red-light); color: var(--red); }
        .badge-approved { background: var(--green-light); color: var(--green); }
        .badge-rejected { background: var(--red-light); color: var(--red); }
        .badge-high { background: var(--red-light); color: var(--red); }
        .badge-medium { background: #ffedd5; color: #c2410c; }
        .badge-low { background: var(--green-light); color: var(--green); }
        .request-desc { font-size: 14px; color: var(--text); margin-bottom: 6px; }
        .request-meta { font-size: 12px; color: var(--muted); }
        .empty-state { text-align: center; padding: 48px 24px; color: var(--muted); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); }
        .empty-state .icon { font-size: 36px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 200; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--surface); border-radius: 16px; padding: 28px; width: 100%; max-width: 480px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
        .modal h2 { font-size: 18px; font-weight: 700; margin-bottom: 20px; }
        .form-field { margin-bottom: 16px; }
        .form-field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .form-field select, .form-field textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; font-family: 'DM Sans', sans-serif; background: var(--surface2); outline: none; transition: 0.15s; }
        .form-field select:focus, .form-field textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .form-field textarea { resize: vertical; min-height: 100px; }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }
        .btn-secondary { background: var(--surface2); color: var(--text); border: 1px solid var(--border); padding: 9px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; }
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background: var(--green-light); color: var(--green); border: 1px solid #bbf7d0; }
        .alert-error { background: var(--red-light); color: var(--red); border: 1px solid #fecaca; }
    </style>
</head>
<body>
<nav>
    <div class="nav-brand">🏢 <span>Property</span>Management</div>
    <div class="nav-right">
        <span class="nav-user">Logged in as <strong><?php echo htmlspecialchars($tenant['name']); ?></strong></span>
        <a href="login.php" class="logout-btn">Log out</a>
    </div>
</nav>

<div class="main">
    <div class="page-header">
        <h1>Tenant Dashboard</h1>
        <p>Manage your property and maintenance requests</p>
    </div>

    <?php if (!empty($success)): ?><div class="alert alert-success">✅ <?php echo $success; ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-error">⚠️ <?php echo $error; ?></div><?php endif; ?>

    <?php if ($property): ?>
    <div class="property-card">
        <div class="prop-icon">🏠</div>
        <div class="prop-info">
            <h3><?php echo htmlspecialchars($property['street']); ?><?php if ($property['apartment']): ?>, Apt <?php echo htmlspecialchars($property['apartment']); ?><?php endif; ?></h3>
            <p>Owner: <?php echo htmlspecialchars($property['owner_name']); ?></p>
        </div>
    </div>
    <?php else: ?>
    <div class="no-property">
        <span>⚠️ You are not assigned to a property yet.</span>
        <button class="btn-primary" onclick="document.getElementById('propertyRequestModal').classList.add('active')">Request a Property</button>
    </div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat"><div class="stat-label">Pending</div><div class="stat-value"><?php echo $active; ?></div></div>
        <div class="stat"><div class="stat-label">In Progress</div><div class="stat-value"><?php echo $in_progress; ?></div></div>
        <div class="stat"><div class="stat-label">Completed</div><div class="stat-value"><?php echo $completed; ?></div></div>
    </div>

    <div class="tabs">
        <div class="tab active" onclick="switchTab('maintenance')">Maintenance Requests</div>
        <div class="tab" onclick="switchTab('proprequests')">Property Requests (<?php echo count($my_prop_requests); ?>)</div>
    </div>

    <div class="tab-panel active" id="tab-maintenance">
        <div class="section-header">
            <h2 class="section-title">Your Maintenance Requests</h2>
            <?php if ($property): ?>
            <button class="btn-primary" onclick="document.getElementById('requestModal').classList.add('active')">+ New Request</button>
            <?php endif; ?>
        </div>
        <div class="request-list">
            <?php if (empty($requests)): ?>
            <div class="empty-state"><div class="icon">🔧</div><p>No maintenance requests yet.</p></div>
            <?php else: ?>
            <?php foreach ($requests as $req): ?>
            <div class="request-card">
                <div class="request-top">
                    <span class="badge badge-type"><?php echo htmlspecialchars($req['type']); ?></span>
                    <?php $urg = strtolower($req['urgency']); ?>
                    <span class="badge badge-<?php echo in_array($urg,['high','red'])?'high':(in_array($urg,['medium','orange'])?'medium':'low'); ?>"><?php echo htmlspecialchars($req['urgency']); ?></span>
                    <?php $sc = match($req['status']){'Pending'=>'badge-pending','In Progress'=>'badge-progress','Completed'=>'badge-completed',default=>'badge-cancelled'}; ?>
                    <span class="badge <?php echo $sc; ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                </div>
                <div class="request-desc"><?php echo htmlspecialchars($req['description']); ?></div>
                <div class="request-meta">Submitted: <?php echo $req['date']; ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-panel" id="tab-proprequests">
        <div class="section-header">
            <h2 class="section-title">My Property Requests</h2>
            <?php if (!$property): ?>
            <button class="btn-primary" onclick="document.getElementById('propertyRequestModal').classList.add('active')">+ Request Property</button>
            <?php endif; ?>
        </div>
        <div class="request-list">
            <?php if (empty($my_prop_requests)): ?>
            <div class="empty-state"><div class="icon">🏠</div><p>No property requests yet.</p></div>
            <?php else: ?>
            <?php foreach ($my_prop_requests as $pr): ?>
            <div class="request-card">
                <div class="request-top">
                    <?php $prs = strtolower($pr['status']); ?>
                    <span class="badge badge-<?php echo $prs==='approved'?'approved':($prs==='rejected'?'rejected':'pending'); ?>"><?php echo htmlspecialchars($pr['status']); ?></span>
                </div>
                <div class="request-desc">🏠 <?php echo htmlspecialchars($pr['street']); ?><?php if ($pr['apartment']): ?>, Apt <?php echo htmlspecialchars($pr['apartment']); ?><?php endif; ?></div>
                <div class="request-meta">Requested: <?php echo $pr['date']; ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Maintenance Request Modal -->
<div class="modal-overlay" id="requestModal">
    <div class="modal">
        <h2>New Maintenance Request</h2>
        <form method="POST">
            <input type="hidden" name="action" value="submit_request">
            <div class="form-field"><label>Category</label>
                <select name="type" required>
                    <option value="">Select a category</option>
                    <option>Plumbing</option><option>Electrical</option><option>HVAC</option>
                    <option>Carpentry</option><option>Appliances</option><option>Other</option>
                </select>
            </div>
            <div class="form-field"><label>Urgency</label>
                <select name="urgency" required>
                    <option value="">Select urgency</option>
                    <option value="Low">Low</option><option value="Medium">Medium</option><option value="High">High</option>
                </select>
            </div>
            <div class="form-field"><label>Description</label>
                <textarea name="description" placeholder="Describe the issue..." required></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('requestModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

<!-- Property Request Modal -->
<div class="modal-overlay" id="propertyRequestModal">
    <div class="modal">
        <h2>Request to Join a Property</h2>
        <form method="POST">
            <input type="hidden" name="action" value="request_property">
            <div class="form-field"><label>Select Property</label>
                <select name="property_id" required>
                    <option value="">Choose a property...</option>
                    <?php foreach ($available_properties as $ap): ?>
                    <option value="<?php echo $ap['property_id']; ?>">
                        <?php echo htmlspecialchars($ap['street']); ?><?php if ($ap['apartment']): ?>, Apt <?php echo htmlspecialchars($ap['apartment']); ?><?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('propertyRequestModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn-primary">Send Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(name) {
    const names = ['maintenance','proprequests'];
    document.querySelectorAll('.tab').forEach((t,i) => t.classList.toggle('active', names[i]===name));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-'+name).classList.add('active');
}
</script>
</body>
</html>