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
        try{
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
    <title>Document</title>
</head>
<body>
    <h1>Sign up</h1>
    <form action="register.php" method="POST">
        <h3>Email address</h3>
        <input type="email" name="email" placeholder="Email" required>
        <h3>Name</h3>
        <input type="text" name="name" placeholder="Name" required>
        <h3>Password</h3>
        <input type="password" name="password" placeholder="Password" required>
        <h3>Register as</h3>
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
        <h3>Specialization</h3>
        <input type="text" name="specialization" placeholder="Specialization">
        <br>
        <button type="submit">Sign up</button>
</form>
<?php if (!empty($error_message)): ?>
        <p style="color: red; font-weight: bold;"><?php echo $error_message; ?></p>
<?php endif; ?>
</body>
<script>
    const radioButtons = document.querySelectorAll('input[name="user_role"]');
    const specInput = document.querySelector('input[name="specialization"]');
    const specHeader = specInput.previousElementSibling;
    function toggleSpec() {
        const isWorker = document.querySelector('input[value="worker"]').checked;
        specInput.style.display = isWorker ? 'block' : 'none';
        specHeader.style.display = isWorker ? 'block' : 'none';
    }
    radioButtons.forEach(radio => radio.addEventListener('change', toggleSpec));
    toggleSpec();
</script>
</html>