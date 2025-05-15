<?php
header('Content-Type: application/json');

// Database credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "accounting";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// SQL query to group profits by month
$sql = "SELECT DATE_FORMAT(entry_date, '%Y-%m') as month, SUM(profit) as total_profit
        FROM main_table
        GROUP BY DATE_FORMAT(entry_date, '%Y-%m')
        ORDER BY month ASC";

$result = $conn->query($sql);

$data = array();
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()){
        $data[] = $row;
    }
}

echo json_encode($data);
$conn->close();
?>
