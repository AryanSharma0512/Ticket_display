<?php
include_once(__DIR__ . '/db_config.php');
header('Content-Type: application/json');

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

// Fetch year-to-date performance
$sql = "
    SELECT 
        SUM(bill_amount) AS net_sales, 
        SUM(profit) AS net_profit 
    FROM Main_table 
    WHERE YEAR(entry_date) = YEAR(CURDATE())
";

$result = $conn->query($sql);
if ($result) {
    $data = $result->fetch_assoc();
    $response = [
        "net_sales" => (float)$data["net_sales"] ?? 0,
        "net_profit" => (float)$data["net_profit"] ?? 0
    ];
    echo json_encode($response);
} else {
    echo json_encode(["error" => "Query failed: " . $conn->error]);
}
$conn->close();
?>
