<?php 
if (file_exists(__DIR__ . '/db-config.php')) {
    require_once __DIR__ . '/db-config.php';
} else {
    // 2. Fallback: Your local machine setup (XAMPP / MAMP)
    if (!defined('DB_SERVER')) define('DB_SERVER', 'localhost');
    if (!defined('DB_USERNAME')) define('DB_USERNAME', 'root');
    if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '');
    if (!defined('DB_NAME')) define('DB_NAME', 'Project'); // Your local DB name
}

$username = DB_USERNAME;
$password = DB_PASSWORD;
$host     = DB_SERVER;
$dbname   = DB_NAME;
$dsn      = "mysql:host=$host;dbname=$dbname;charset=utf8";

try {
    $db = new PDO($dsn, $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Fixed: Defined $error_message before echoing it
    $error_message = $e->getMessage();
    echo "<p>An error occurred while connecting to the database: $error_message </p>";
} catch (Exception $e) {
    $error_message = $e->getMessage();
    echo "<p>Error message: $error_message </p>";
}
?>
