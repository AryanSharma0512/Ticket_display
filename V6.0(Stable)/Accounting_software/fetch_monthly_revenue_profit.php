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
            'month' => $row['month'],
            'total_sales' => (float)$row['total_sales'],
            'total_profit' => (float)$row['total_profit']
        ];
    }
}

// Guarantee current month entry exists even with no transactions
date_default_timezone_set('Asia/Kolkata');
$currentMonth = date('Y-m');
$foundCurrent = false;
foreach ($monthlyData as $entry) {
    if ($entry['month'] === $currentMonth) {
        $foundCurrent = true;
        break;
    }
}
if (!$foundCurrent) {
    $monthlyData[] = [
        'month' => $currentMonth,
        'total_sales' => 0,
        'total_profit' => 0
    ];
}

// Sort in ascending order in case we appended the current month
usort($monthlyData, function($a, $b){
    return strcmp($a['month'], $b['month']);
});

echo json_encode($monthlyData);
if (!$result) {
    // In case of query failure, still output an error
    error_log('Query failed: ' . $conn->error);
}
$conn->close();
?>
