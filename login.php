<!DOCTYPE html>
<?php
session_start();
require("connect-db.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
    $role = $_POST['user_role'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    $mapping = [
        'tenant' => ['table' => 'tenant', 'id' => 'tenant_id', 'dest' => 'tenant_dashboard.php'],
        'owner' => ['table' => 'property_owner', 'id' => 'owner_id', 'dest' => 'owner_dashboard.php'],
        'worker' => ['table' => 'workers', 'id' => 'worker_id', 'dest' => 'worker_dashboard.php'],
    ];

    $target = $mapping[$role];

    $stmt = $db->prepare("SELECT * FROM {$target['table']} WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user[$target['id']];
        $_SESSION['role'] = $role;
        header("Location: " . $target['dest']);
        exit();
    } else {
        $error = "Invalid email or password for " . ucfirst($role) . ".";
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Property Management</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'DM Sans', sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a, #1e293b, #334155);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            color: #0f172a;
        }

        .container {
            width: 100%;
            max-width: 480px;
        }

        .card {
            background: #ffffff;
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.22);
        }

        .brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-icon {
            width: 52px;
            height: 52px;
            background: #eff6ff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 14px;
        }

        .brand h1 {
            font-size: 26px;
            color: #111827;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .brand p {
            color: #6b7280;
            font-size: 14px;
        }

        .field {
            margin-bottom: 18px;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #1f2937;
            font-size: 14px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap .icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            color: #9ca3af;
        }

        .field input[type="email"],
        .field input[type="password"] {
            width: 100%;
            padding: 13px 14px 13px 38px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            transition: 0.2s ease;
            background: #f9fafb;
            color: #111827;
        }

        .field input:focus {
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .role-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #1f2937;
            font-size: 14px;
        }

        .role-group {
            display: grid;
            gap: 10px;
        }

        .role-option {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border: 1px solid #dbeafe;
            background: #f8fbff;
            padding: 13px 14px;
            border-radius: 12px;
            transition: 0.2s ease;
            cursor: pointer;
        }

        .role-option:hover {
            border-color: #93c5fd;
            background: #eff6ff;
        }

        .role-option input[type="radio"] {
            margin-top: 3px;
            accent-color: #2563eb;
        }

        .role-option input[type="radio"]:checked + .role-text {
            color: #1d4ed8;
        }

        .role-text strong {
            display: block;
            color: #111827;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .role-text span {
            color: #6b7280;
            font-size: 13px;
            line-height: 1.4;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: #2563eb;
            color: white;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            color: #9ca3af;
            font-size: 13px;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #6b7280;
        }

        .footer-text a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 14px;
            margin-top: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .success-banner {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 520px) {
            .card { padding: 24px; }
            .brand h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="brand">
                <div class="brand-icon">🏢</div>
                <h1>Property Management</h1>
                <p>Sign in to your account</p>
            </div>

            <?php if (isset($_GET['signup']) && $_GET['signup'] === 'success'): ?>
                <div class="success-banner">✅ Account created! You can now sign in.</div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="field">
                    <label for="email">Email Address</label>
                    <div class="input-wrap">
                        <span class="icon">✉️</span>
                        <input type="email" id="email" name="email" placeholder="you@example.com" required>
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span class="icon">🔒</span>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="field">
                    <span class="role-label">Login As</span>
                    <div class="role-group">
                        <label class="role-option">
                            <input type="radio" name="user_role" value="tenant" checked>
                            <div class="role-text">
                                <strong>Tenant</strong>
                                <span>Submit and track maintenance requests</span>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="user_role" value="owner">
                            <div class="role-text">
                                <strong>Property Owner</strong>
                                <span>Manage properties and maintenance</span>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="user_role" value="worker">
                            <div class="role-text">
                                <strong>Maintenance Company</strong>
                                <span>Browse and apply for jobs</span>
                            </div>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn">→ Sign In</button>
            </form>

            <?php if (!empty($error)): ?>
                <div class="alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="footer-text">
                Don't have an account? <a href="register.php">Sign up</a>
            </div>
        </div>
    </div>
</body>
</html>
