<?php $username = 'PASSWORD';
$password = 'PASSWORD';
$host = 'localhost';
$dbname = 'Project';
$dsn = "mysql:host=$host;dbname=$dbname";

try{
    $db = new PDO($dsn, $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "<p>An error occurred while connecting to the database: $error_message </p>";
} catch (Exception $e)       // handle any type of exception
{
   $error_message = $e->getMessage();
   echo "<p>Error message: $error_message </p>";
}
?>