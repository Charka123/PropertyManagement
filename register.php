<!DOCTYPE html>
<?php
require("connect-db.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
    $role = $_POST['user_role'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $specialization = $_POST['specialization'] ?? null;

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    if ($role == 'worker'){
        $table = 'workers';
    } else if ($role == 'tenant'){
        $table = 'tenant';
    } else {
        $table = 'property_owner';
    }

    $check_sql = "SELECT email FROM $table WHERE email = ?";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute([$email]);

    if ($check_stmt->rowCount() > 0){
        $error_message = "This email is already registered as a " . ucfirst($role) . ".";
    } else {
        try {
            if ($role == 'worker'){
                $sql = "INSERT INTO workers (name, email, password, specialization) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$name, $email, $hashed_password, $specialization]);
            } else {
                $sql = "INSERT INTO $table (name, email, password) VALUES (?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$name, $email, $hashed_password]);
            }
            header("Location: login.php?signup=success");
            exit();
        } catch (PDOException $e) {
            $error_message = "An unexpected error has occurred.";
        }
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Property Management</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
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
            max-width: 520px;
        }

        .card {
            background: #ffffff;
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.22);
        }

        .brand {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand h1 {
            font-size: 30px;
            color: #111827;
            margin-bottom: 8px;
        }

        .brand p {
            color: #6b7280;
            font-size: 15px;
        }

        .field {
            margin-bottom: 18px;
        }

        .field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1f2937;
            font-size: 14px;
        }

        .field input[type="text"],
        .field input[type="email"],
        .field input[type="password"] {
            width: 100%;
            padding: 14px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            transition: 0.2s ease;
            background: #f9fafb;
        }

        .field input:focus {
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .role-group {
            display: grid;
            gap: 10px;
            margin-top: 8px;
        }

        .role-option {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border: 1px solid #dbeafe;
            background: #f8fbff;
            padding: 14px;
            border-radius: 12px;
            transition: 0.2s ease;
            cursor: pointer;
        }

        .role-option:hover {
            border-color: #93c5fd;
            background: #eff6ff;
        }

        .role-option input {
            margin-top: 3px;
        }

        .role-text strong {
            display: block;
            color: #111827;
            margin-bottom: 3px;
        }

        .role-text span {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.4;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
            margin-top: 8px;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .message {
            margin-top: 18px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 14px;
        }

        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .footer-text {
            text-align: center;
            margin-top: 18px;
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

        #specialization-wrapper {
            display: none;
        }

        @media (max-width: 600px) {
            .card {
                padding: 24px;
            }

            .brand h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="brand">
                <h1>Create Account</h1>
                <p>Join the Property Management platform</p>
            </div>

            <form action="register.php" method="POST">
                <div class="field">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>

                <div class="field">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" placeholder="Enter your full name" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Create a password" required>
                </div>

                <div class="field">
                    <label>Register as</label>
                    <div class="role-group">
                        <label class="role-option">
                            <input type="radio" name="user_role" value="tenant" checked>
                            <div class="role-text">
                                <strong>Tenant</strong>
                                <span>Submit and track maintenance requests.</span>
                            </div>
                        </label>

                        <label class="role-option">
                            <input type="radio" name="user_role" value="owner">
                            <div class="role-text">
                                <strong>Property Owner</strong>
                                <span>Manage properties, tenants, and maintenance activity.</span>
                            </div>
                        </label>

                        <label class="role-option">
                            <input type="radio" name="user_role" value="worker">
                            <div class="role-text">
                                <strong>Maintenance Company</strong>
                                <span>Browse open jobs and apply for work opportunities.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="field" id="specialization-wrapper">
                    <label for="specialization">Specialization</label>
                    <input type="text" id="specialization" name="specialization" placeholder="Ex: Plumbing, Electrical, Carpentry">
                </div>

                <button type="submit" class="btn">Sign Up</button>
            </form>

            <?php if (!empty($error_message)): ?>
                <div class="message error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="footer-text">
                Already have an account? <a href="login.php">Log in</a>
            </div>
        </div>
    </div>

    <script>
        const radioButtons = document.querySelectorAll('input[name="user_role"]');
        const specWrapper = document.getElementById('specialization-wrapper');

        function toggleSpec() {
            const isWorker = document.querySelector('input[value="worker"]').checked;
            specWrapper.style.display = isWorker ? 'block' : 'none';
        }

        radioButtons.forEach(radio => radio.addEventListener('change', toggleSpec));
        toggleSpec();
    </script>
</body>
</html>