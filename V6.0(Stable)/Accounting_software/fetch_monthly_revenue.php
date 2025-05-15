<?php
include_once(__DIR__ . '/db_config.php');
header('Content-Type: application/json');

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

$sql = "
    SELECT 
        DATE_FORMAT(entry_date, '%Y-%m') AS month, 
        SUM(bill_amount) AS total_sales 
    FROM Main_table 
    GROUP BY month 
    ORDER BY month ASC
";

$result = $conn->query($sql);
if ($result) {
    $monthlyData = [];
    while ($row = $result->fetch_assoc()) {
        $monthlyData[] = [
            "month" => $row["month"],
            "total_sales" => (float)$row["total_sales"]
        ];
    }
    echo json_encode($monthlyData);
} else {
    echo json_encode(["error" => "Query failed: " . $conn->error]);
}
$conn->close();
?>
