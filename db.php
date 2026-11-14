<?php
// Database connection for Ghorebaire Fix
$host = "localhost";
$user = "root";
$password = "";
$database = "ghorebaire_fix";

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    // Do not show database credentials or technical details to visitors.
    die("Database connection failed. Please check your XAMPP MySQL settings.");
}
$conn->set_charset("utf8mb4");
?>
