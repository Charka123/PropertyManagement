<!DOCTYPE html>
<?php
session_start();
require("connect-db.php");

// Admin login handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM admin WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['role'] = 'admin';
        header("Location: admin_dashboard.php");
        exit();
    } else {
        $login_error = "Invalid admin credentials.";
    }
}

// Require admin session
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    // Show login form
    ?>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login | Property Management</title>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; font-family: 'DM Sans', sans-serif; }
            body { min-height: 100vh; background: linear-gradient(135deg, #0f172a, #1e293b, #334155); display: flex; align-items: center; justify-content: center; padding: 30px; }
            .card { background: #fff; border-radius: 18px; padding: 36px; width: 100%; max-width: 420px; box-shadow: 0 20px 50px rgba(0,0,0,0.25); }
            .brand { text-align: center; margin-bottom: 28px; }
            .brand-icon { width: 52px; height: 52px; background: #fef3c7; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 14px; }
            .brand h1 { font-size: 24px; font-weight: 700; color: #111827; margin-bottom: 6px; }
            .brand p { font-size: 14px; color: #6b7280; }
            .field { margin-bottom: 18px; }
            .field label { display: block; font-size: 13px; font-weight: 600; color: #1f2937; margin-bottom: 7px; }
            .input-wrap { position: relative; }
            .input-wrap .icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 14px; color: #9ca3af; }
            .field input { width: 100%; padding: 13px 14px 13px 38px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 15px; background: #f9fafb; outline: none; transition: 0.2s; color: #111827; }
            .field input:focus { border-color: #d97706; background: #fff; box-shadow: 0 0 0 3px rgba(217,119,6,0.12); }
            .btn { width: 100%; padding: 14px; border: none; border-radius: 12px; background: #d97706; color: white; font-size: 15px; font-weight: 700; cursor: pointer; transition: 0.2s; margin-top: 8px; }
            .btn:hover { background: #b45309; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(217,119,6,0.3); }
            .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 10px; padding: 12px 14px; font-size: 14px; margin-top: 16px; }
            .back-link { text-align: center; margin-top: 18px; font-size: 14px; color: #6b7280; }
            .back-link a { color: #d97706; text-decoration: none; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="brand">
                <div class="brand-icon">🛡️</div>
                <h1>Admin Portal</h1>
                <p>Property Management Platform</p>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="field">
                    <label>Email Address</label>
                    <div class="input-wrap">
                        <span class="icon">✉️</span>
                        <input type="email" name="email" placeholder="admin@property.com" required>
                    </div>
                </div>
                <div class="field">
                    <label>Password</label>
                    <div class="input-wrap">
                        <span class="icon">🔒</span>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>
                <button type="submit" class="btn">→ Sign In as Admin</button>
            </form>
            <?php if (!empty($login_error)): ?>
                <div class="alert-error">⚠️ <?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            <div class="back-link"><a href="login.php">← Back to main login</a></div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// ---- ADMIN IS LOGGED IN ----

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve_property' || $action === 'reject_property') {
        $property_id = $_POST['property_id'];
        $new_status = $action === 'approve_property' ? 'Approved' : 'Rejected';
        $upd = $db->prepare("UPDATE property SET approval_status = ? WHERE property_id = ?");
        $upd->execute([$new_status, $property_id]);
        $success = "Property " . strtolower($new_status) . ".";
    }

    if ($action === 'logout') {
        session_destroy();
        header("Location: admin_dashboard.php");
        exit();
    }
}

// Fetch admin info
$admin_stmt = $db->prepare("SELECT * FROM admin WHERE admin_id = ?");
$admin_stmt->execute([$_SESSION['admin_id']]);
$admin = $admin_stmt->fetch();

// Fetch all properties with owner info
$props_stmt = $db->query("
    SELECT p.*, po.name AS owner_name, po.email AS owner_email,
           COUNT(DISTINCT l.tenant_id) AS tenant_count,
           COUNT(DISTINCT r.request_id) AS request_count
    FROM property p
    LEFT JOIN owns o ON p.property_id = o.property_id
    LEFT JOIN property_owner po ON o.owner_id = po.owner_id
    LEFT JOIN lives l ON p.property_id = l.property_id
    LEFT JOIN request r ON p.property_id = r.property_id
    GROUP BY p.property_id
    ORDER BY p.property_id DESC
");
$all_properties = $props_stmt->fetchAll();

$pending_props = array_filter($all_properties, fn($p) => $p['approval_status'] === 'Pending');
$approved_props = array_filter($all_properties, fn($p) => $p['approval_status'] === 'Approved');
$rejected_props = array_filter($all_properties, fn($p) => $p['approval_status'] === 'Rejected');

// Platform stats
$total_tenants = $db->query("SELECT COUNT(*) FROM tenant")->fetchColumn();
$total_owners = $db->query("SELECT COUNT(*) FROM property_owner")->fetchColumn();
$total_workers = $db->query("SELECT COUNT(*) FROM workers")->fetchColumn();
$total_requests = $db->query("SELECT COUNT(*) FROM request")->fetchColumn();
$open_jobs = $db->query("SELECT COUNT(*) FROM request WHERE status = 'In Progress'")->fetchColumn();
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Property Management</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #fafaf9;
            --surface: #ffffff;
            --surface2: #f5f5f4;
            --border: #e7e5e4;
            --text: #1c1917;
            --muted: #78716c;
            --accent: #d97706;
            --accent-light: #fef3c7;
            --green: #16a34a;
            --green-light: #f0fdf4;
            --red: #dc2626;
            --red-light: #fef2f2;
            --blue: #2563eb;
            --blue-light: #eff6ff;
            --radius: 12px;
        }

        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

        nav {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 60px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }

        .nav-brand { font-weight: 700; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .nav-brand span { color: var(--accent); }
        .admin-badge { background: var(--accent-light); color: var(--accent); font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .nav-right { display: flex; align-items: center; gap: 14px; }
        .nav-user { font-size: 14px; color: var(--muted); }
        .nav-user strong { color: var(--text); }

        .logout-btn {
            font-size: 13px; color: var(--muted); border: 1px solid var(--border);
            border-radius: 8px; padding: 6px 12px; cursor: pointer;
            background: none; font-family: 'DM Sans', sans-serif; transition: 0.15s;
        }
        .logout-btn:hover { background: var(--surface2); color: var(--text); }

        .main { max-width: 1000px; margin: 0 auto; padding: 32px 24px; }

        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 24px; font-weight: 700; }
        .page-header p { color: var(--muted); font-size: 14px; margin-top: 4px; }

        /* STATS GRID */
        .stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 32px;
        }

        .stat { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 18px; }
        .stat-label { font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; }
        .stat-value { font-size: 24px; font-weight: 700; margin-top: 6px; font-family: 'DM Mono', monospace; }
        .stat-value.orange { color: var(--accent); }
        .stat-value.green { color: var(--green); }
        .stat-value.red { color: var(--red); }

        /* TABS */
        .tabs { display: flex; border-bottom: 2px solid var(--border); margin-bottom: 24px; }
        .tab { padding: 10px 20px; font-size: 14px; font-weight: 600; color: var(--muted); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: 0.15s; }
        .tab.active { color: var(--accent); border-bottom-color: var(--accent); }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        .section-title { font-size: 16px; font-weight: 700; margin-bottom: 16px; }

        /* PROPERTY CARDS */
        .card-list { display: flex; flex-direction: column; gap: 12px; }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 20px;
        }

        .card.pending { border-left: 4px solid var(--accent); }
        .card.approved { border-left: 4px solid var(--green); }
        .card.rejected { border-left: 4px solid var(--red); }

        .card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .card-title { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
        .card-sub { font-size: 13px; color: var(--muted); }
        .card-meta { font-size: 12px; color: var(--muted); margin-top: 8px; display: flex; gap: 16px; }

        .card-actions { display: flex; gap: 8px; flex-shrink: 0; }

        .badge { padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: var(--accent-light); color: var(--accent); }
        .badge-approved { background: var(--green-light); color: var(--green); }
        .badge-rejected { background: var(--red-light); color: var(--red); }

        .btn-sm {
            padding: 6px 14px; font-size: 13px; font-weight: 600;
            border-radius: 8px; border: none; cursor: pointer;
            font-family: 'DM Sans', sans-serif; transition: 0.15s;
        }
        .btn-approve { background: var(--green-light); color: var(--green); border: 1px solid #bbf7d0; }
        .btn-approve:hover { background: #dcfce7; }
        .btn-reject { background: var(--red-light); color: var(--red); border: 1px solid #fecaca; }
        .btn-reject:hover { background: #fee2e2; }

        .empty-state { text-align: center; padding: 48px 24px; color: var(--muted); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); }
        .empty-state .icon { font-size: 36px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; }

        .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background: var(--green-light); color: var(--green); border: 1px solid #bbf7d0; }

        /* USERS TABLE */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        thead th { text-align: left; padding: 10px 14px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); border-bottom: 1px solid var(--border); background: var(--surface2); }
        tbody td { padding: 12px 14px; border-bottom: 1px solid var(--border); color: var(--text); }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: var(--surface2); }
        .table-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }

        @media (max-width: 768px) {
            .stats { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 500px) {
            .stats { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<nav>
    <div class="nav-brand">🏢 <span>Property</span>Management <span class="admin-badge">Admin</span></div>
    <div class="nav-right">
        <span class="nav-user">Logged in as <strong><?php echo htmlspecialchars($admin['name']); ?></strong></span>
        <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="logout-btn">Log out</button>
        </form>
    </div>
</nav>

<div class="main">
    <div class="page-header">
        <h1>Admin Dashboard</h1>
        <p>Platform overview and property approvals</p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats">
        <div class="stat">
            <div class="stat-label">Pending Approvals</div>
            <div class="stat-value orange"><?php echo count($pending_props); ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Properties</div>
            <div class="stat-value"><?php echo count($all_properties); ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Owners</div>
            <div class="stat-value"><?php echo $total_owners; ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Tenants</div>
            <div class="stat-value"><?php echo $total_tenants; ?></div>
        </div>
        <div class="stat">
            <div class="stat-label">Open Jobs</div>
            <div class="stat-value green"><?php echo $open_jobs; ?></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <div class="tab active" onclick="switchTab('pending')">
            Pending (<?php echo count($pending_props); ?>)
        </div>
        <div class="tab" onclick="switchTab('approved')">Approved (<?php echo count($approved_props); ?>)</div>
        <div class="tab" onclick="switchTab('rejected')">Rejected (<?php echo count($rejected_props); ?>)</div>
        <div class="tab" onclick="switchTab('users')">Users</div>
    </div>

    <!-- PENDING TAB -->
    <div class="tab-panel active" id="tab-pending">
        <div class="section-title">Properties Awaiting Approval</div>
        <div class="card-list">
            <?php if (empty($pending_props)): ?>
            <div class="empty-state">
                <div class="icon">✅</div>
                <p>No pending properties — all caught up!</p>
            </div>
            <?php else: ?>
            <?php foreach ($pending_props as $prop): ?>
            <div class="card pending">
                <div class="card-top">
                    <div>
                        <div class="card-title">🏠 <?php echo htmlspecialchars($prop['street']); ?><?php if ($prop['apartment']): ?>, Apt <?php echo htmlspecialchars($prop['apartment']); ?><?php endif; ?></div>
                        <div class="card-sub">Owner: <?php echo htmlspecialchars($prop['owner_name'] ?? 'Unknown'); ?> · <?php echo htmlspecialchars($prop['owner_email'] ?? ''); ?></div>
                        <div class="card-meta">
                            <span>👥 <?php echo $prop['tenant_count']; ?> tenant(s)</span>
                            <span>🔧 <?php echo $prop['request_count']; ?> request(s)</span>
                        </div>
                    </div>
                    <div class="card-actions">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="approve_property">
                            <input type="hidden" name="property_id" value="<?php echo $prop['property_id']; ?>">
                            <button type="submit" class="btn-sm btn-approve">✓ Approve</button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="reject_property">
                            <input type="hidden" name="property_id" value="<?php echo $prop['property_id']; ?>">
                            <button type="submit" class="btn-sm btn-reject">✕ Reject</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- APPROVED TAB -->
    <div class="tab-panel" id="tab-approved">
        <div class="section-title">Approved Properties</div>
        <div class="card-list">
            <?php if (empty($approved_props)): ?>
            <div class="empty-state"><div class="icon">🏠</div><p>No approved properties yet.</p></div>
            <?php else: ?>
            <?php foreach ($approved_props as $prop): ?>
            <div class="card approved">
                <div class="card-top">
                    <div>
                        <div class="card-title">🏠 <?php echo htmlspecialchars($prop['street']); ?><?php if ($prop['apartment']): ?>, Apt <?php echo htmlspecialchars($prop['apartment']); ?><?php endif; ?></div>
                        <div class="card-sub">Owner: <?php echo htmlspecialchars($prop['owner_name'] ?? 'Unknown'); ?> · <?php echo htmlspecialchars($prop['owner_email'] ?? ''); ?></div>
                        <div class="card-meta">
                            <span>👥 <?php echo $prop['tenant_count']; ?> tenant(s)</span>
                            <span>🔧 <?php echo $prop['request_count']; ?> request(s)</span>
                        </div>
                    </div>
                    <span class="badge badge-approved">Approved</span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- REJECTED TAB -->
    <div class="tab-panel" id="tab-rejected">
        <div class="section-title">Rejected Properties</div>
        <div class="card-list">
            <?php if (empty($rejected_props)): ?>
            <div class="empty-state"><div class="icon">🚫</div><p>No rejected properties.</p></div>
            <?php else: ?>
            <?php foreach ($rejected_props as $prop): ?>
            <div class="card rejected">
                <div class="card-top">
                    <div>
                        <div class="card-title">🏠 <?php echo htmlspecialchars($prop['street']); ?><?php if ($prop['apartment']): ?>, Apt <?php echo htmlspecialchars($prop['apartment']); ?><?php endif; ?></div>
                        <div class="card-sub">Owner: <?php echo htmlspecialchars($prop['owner_name'] ?? 'Unknown'); ?></div>
                    </div>
                    <div class="card-actions">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="approve_property">
                            <input type="hidden" name="property_id" value="<?php echo $prop['property_id']; ?>">
                            <button type="submit" class="btn-sm btn-approve">✓ Approve</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- USERS TAB -->
    <div class="tab-panel" id="tab-users">
        <div class="section-title">Platform Users</div>

        <p style="font-size:13px; color:var(--muted); margin-bottom:14px;">Property Owners</p>
        <div class="table-card" style="margin-bottom:24px;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Properties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $owners_list = $db->query("
                            SELECT po.*, COUNT(o.property_id) AS prop_count
                            FROM property_owner po
                            LEFT JOIN owns o ON po.owner_id = o.owner_id
                            GROUP BY po.owner_id
                        ")->fetchAll();
                        foreach ($owners_list as $o): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($o['name']); ?></td>
                            <td><?php echo htmlspecialchars($o['email']); ?></td>
                            <td><?php echo $o['prop_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p style="font-size:13px; color:var(--muted); margin-bottom:14px;">Tenants</p>
        <div class="table-card" style="margin-bottom:24px;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Property</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $tenants_list = $db->query("
                            SELECT t.name, t.email, p.street
                            FROM tenant t
                            LEFT JOIN lives l ON t.tenant_id = l.tenant_id
                            LEFT JOIN property p ON l.property_id = p.property_id
                        ")->fetchAll();
                        foreach ($tenants_list as $t): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t['name']); ?></td>
                            <td><?php echo htmlspecialchars($t['email']); ?></td>
                            <td><?php echo $t['street'] ? htmlspecialchars($t['street']) : '<span style="color:#9ca3af">Unassigned</span>'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p style="font-size:13px; color:var(--muted); margin-bottom:14px;">Maintenance Workers</p>
        <div class="table-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Specialization</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $workers_list = $db->query("SELECT * FROM workers")->fetchAll();
                        foreach ($workers_list as $w): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($w['name']); ?></td>
                            <td><?php echo htmlspecialchars($w['email']); ?></td>
                            <td><?php echo htmlspecialchars($w['specialization']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(name) {
    const names = ['pending', 'approved', 'rejected', 'users'];
    document.querySelectorAll('.tab').forEach((t, i) => t.classList.toggle('active', names[i] === name));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
}
</script>
</body>
</html>