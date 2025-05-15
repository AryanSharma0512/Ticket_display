<?php
// Database configuration settings
$servername = "localhost";
$username = "root"; // Change to your MySQL username
$password = "";     // Change to your MySQL password, if any
$dbname = "accounts_network"; // Your database name

// Create database connection using MySQLi object-oriented approach
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
