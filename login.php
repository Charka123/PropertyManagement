<!DOCTYPE html>
<?php
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

    if($user && password_verify($password, $user['password'])){
        session_start();
        $_SESSION['user_id'] = $user[$target['id']];
        $_SESSION['role'] = $role;
        header("Location: " . $target['dest']);
        exit();
    } else {
        $error = "Invalid email or password for " . ucfirst($role);
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1>Property Management</h1>
    <p>Sign in to your account</p>
    <form action="login.php" method="POST">
        <h3>Email address</h3>
        <input type="email" name="email" placeholder="Email" required>
        <h3>Password</h3>
        <input type="password" name="password" placeholder="Password" required>
        <h3>Login as</h3>
        <label>
            <input type="radio" name="user_role" value="tenant" checked>
            Tenant (submit and track maintenance requests)
        </label>
        <br>
        <label>
            <input type="radio" name="user_role" value="owner">
            Property Owner (manage property and maintenance)
        </label>
        <br>
        <label>
            <input type="radio" name="user_role" value="worker">
            Maintenance Company (browse and apply for jobs)
        </label>
        <br>
        <button type="submit">Sign in</button>
</form>
</body>
</html>