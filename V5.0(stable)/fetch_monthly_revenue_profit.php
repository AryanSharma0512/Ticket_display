<?php
include_once(__DIR__ . '/db_config.php');
header('Content-Type: application/json');

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

// Query to group both revenue and profit by month.
// Adjust table/column names if necessary.
$sql = "
    SELECT 
        DATE_FORMAT(entry_date, '%Y-%m') AS month, 
        SUM(bill_amount) AS total_sales,
        SUM(profit) AS total_profit
    FROM Main_table 
    GROUP BY month 
    ORDER BY month ASC
";

$result = $conn->query($sql);
$monthlyData = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()){
        $monthlyData[] = [
            "month" => $row["month"],
            "total_sales" => (float)$row["total_sales"],
            "total_profit" => (float)$row["total_profit"]
        ];
    }
    echo json_encode($monthlyData);
} else {
    echo json_encode(["error" => "Query failed: " . $conn->error]);
}
$conn->close();
?>
