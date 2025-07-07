<?php
// check_invoice.php
header('Content-Type: application/json; charset=utf-8');

// 1) Validate input:
if (empty($_GET['transaction_id'])) {
    echo json_encode(['found' => false, 'b2b' => false]);
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
    echo json_encode(['found' => false, 'b2b' => false]);
    exit;
}

// 3) Safely query for that Transaction_ID:
$tx_safe = $conn->real_escape_string($tx);
$sql     = "SELECT 1 FROM `Yes_Bank_Records` WHERE `Transaction_ID` = '$tx_safe' LIMIT 1";
$res     = $conn->query($sql);
$foundB2C = ($res && $res->num_rows > 0);

$b2b_sql = "SELECT 1 FROM `B2B` WHERE `booking_id` = '$tx_safe' LIMIT 1";
$b2b_res = $conn->query($b2b_sql);
$foundB2B = ($b2b_res && $b2b_res->num_rows > 0);

echo json_encode(['found' => $foundB2C, 'b2b' => $foundB2B]);

$conn->close();
