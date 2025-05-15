<?php
// check_invoice.php
header('Content-Type: application/json; charset=utf-8');

// 1) Validate input:
if (empty($_GET['transaction_id'])) {
    echo json_encode(['found' => false]);
    exit;
}
$tx = $_GET['transaction_id'];

// 2) Connect to the taxes DB:
$servername = "localhost";
$username   = "root";      // adjust if needed
$password   = "";          // adjust if needed
$dbname     = "taxes";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    // On connection error, return JSON with found=false
    echo json_encode(['found' => false]);
    exit;
}

// 3) Safely query for that Transaction_ID:
$tx_safe = $conn->real_escape_string($tx);
$sql     = "SELECT 1 FROM `Yes_Bank_Records` WHERE `Transaction_ID` = '$tx_safe' LIMIT 1";
$res     = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    echo json_encode(['found' => true]);
} else {
    echo json_encode(['found' => false]);
}

$conn->close();
