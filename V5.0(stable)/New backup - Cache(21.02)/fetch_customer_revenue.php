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
        customer_name, 
        SUM(bill_amount) AS total_sales 
    FROM Main_table 
    GROUP BY customer_name 
    ORDER BY total_sales DESC
";

$result = $conn->query($sql);
if ($result) {
    $customerData = [];
    while ($row = $result->fetch_assoc()) {
        $customerData[] = [
            "customer_name" => $row["customer_name"],
            "total_sales" => (float)$row["total_sales"]
        ];
    }
    echo json_encode($customerData);
} else {
    echo json_encode(["error" => "Query failed: " . $conn->error]);
}
$conn->close();
?>
