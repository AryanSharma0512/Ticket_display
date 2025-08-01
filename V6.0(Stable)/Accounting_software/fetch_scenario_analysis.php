<?php
header('Content-Type: application/json');

// Database credentials
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "accounting"; // Change to your DB name

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit;
}

// We'll retrieve data for each month, ignoring bill_amount < 3000
// Then compute sum of revenue, sum of profit, and gather all monthly amounts to compute median, min, max, etc.
$sql = "
    SELECT
        DATE_FORMAT(entry_date, '%Y-%m') AS month,
        bill_amount,
        profit
    FROM main_table
    WHERE bill_amount >= 3000
    ORDER BY entry_date ASC
";

$result = $conn->query($sql);
if (!$result) {
    echo json_encode(["error" => "Query failed: " . $conn->error]);
    $conn->close();
    exit;
}

// We'll group rows by month in a PHP array
$monthData = [];
while ($row = $result->fetch_assoc()) {
    $monthKey = $row['month'];
    if (!isset($monthData[$monthKey])) {
        $monthData[$monthKey] = [
            "month"       => $monthKey,
            "billAmounts" => [], // store individual amounts to compute median, min, max
            "profitVals"  => []
        ];
    }
    $monthData[$monthKey]["billAmounts"][] = (float)$row['bill_amount'];
    $monthData[$monthKey]["profitVals"][]  = (float)$row['profit'];
}
$result->free();

// Now compute the sum, min, max, median, average for each month
function median_of_array($arr) {
    sort($arr);
    $count = count($arr);
    if ($count === 0) return 0;
    $mid = (int) floor($count / 2);
    if ($count % 2) {
        return $arr[$mid];
    } else {
        return ($arr[$mid - 1] + $arr[$mid]) / 2.0;
    }
}

$finalData = [];
foreach ($monthData as $mKey => $dataArr) {
    $bills = $dataArr["billAmounts"];
    $profs = $dataArr["profitVals"];
    $sumBill = array_sum($bills);
    $sumProf = array_sum($profs);

    // min, max, median, average
    $minBill = count($bills) > 0 ? min($bills) : 0;
    $maxBill = count($bills) > 0 ? max($bills) : 0;
    $medianBill = median_of_array($bills);
    $avgBill = (count($bills) > 0) ? ($sumBill / count($bills)) : 0;

    $finalData[] = [
        "month"          => $mKey,
        "actual_revenue" => $sumBill,
        "actual_profit"  => $sumProf,
        "min_revenue"    => $minBill,
        "max_revenue"    => $maxBill,
        "median_revenue" => $medianBill,
        "avg_revenue"    => $avgBill
    ];
}

// Make sure the current month is represented even if there are no entries yet
// Use the server's timezone dynamically instead of a fixed location
date_default_timezone_set(date_default_timezone_get());
$currentMonth = date('Y-m');
$hasCurrent = false;
foreach ($finalData as $row) {
    if ($row['month'] === $currentMonth) {
        $hasCurrent = true;
        break;
    }
}
if (!$hasCurrent) {
    $finalData[] = [
        'month'          => $currentMonth,
        'actual_revenue' => 0,
        'actual_profit'  => 0,
        'min_revenue'    => 0,
        'max_revenue'    => 0,
        'median_revenue' => 0,
        'avg_revenue'    => 0
    ];
}

// Sort final data by month ascending
usort($finalData, function($a, $b){
    return strcmp($a['month'], $b['month']);
});

echo json_encode($finalData);
$conn->close();
?>
