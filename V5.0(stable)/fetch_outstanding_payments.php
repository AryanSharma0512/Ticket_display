<?php
include_once(__DIR__ . '/db_config.php');

header('Content-Type: application/json');

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

// Query to fetch customers with outstanding payments
$sql = "
    SELECT 
        customer_id, 
        customer_name, 
        SUM(bill_amount) AS total_bill_amount,
        SUM(amount_received) AS total_amount_received,
        SUM(bill_amount - amount_received) AS outstanding_amount
    FROM Main_table 
    GROUP BY customer_id, customer_name 
    HAVING outstanding_amount > 0
";

$result = $conn->query($sql);

if ($result) {
    $outstandingPayments = [];
    while ($row = $result->fetch_assoc()) {
        $outstandingPayments[] = [
            "customer_id" => $row['customer_id'],
            "customer_name" => $row['customer_name'],
            "outstanding_amount" => (float) $row['outstanding_amount']
        ];
    }
    echo json_encode($outstandingPayments);
} else {
    echo json_encode(["error" => "Query failed: " . $conn->error]);
}

$conn->close();
?>
