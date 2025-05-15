<?php
header('Content-Type: application/json');
require_once 'db_config.php';

// Validate incoming POST data.
if (!isset($_POST['customerName']) || empty($_POST['customerName'])) {
    echo json_encode(['error' => 'Customer name is required']);
    exit;
}

$customerNamePayload = $_POST['customerName'];

// Connect to database.
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// 0. Retrieve the customer's ID and basic info based on the name.
$sqlInfo = "SELECT customer_id, customer_name FROM main_table WHERE customer_name = ? LIMIT 1";
$stmt = $conn->prepare($sqlInfo);
$stmt->bind_param("s", $customerNamePayload);
$stmt->execute();
$rowInfo = $stmt->get_result()->fetch_assoc();
if (!$rowInfo) {
    echo json_encode(['error' => 'Customer not found']);
    exit;
}
$customerID = $rowInfo['customer_id'];
$name = $rowInfo['customer_name'];
$stmt->close();

// 1. Customer Since (years since first transaction)
$sqlFirst = "SELECT MIN(entry_date) AS first_transaction FROM main_table WHERE customer_id = ?";
$stmt = $conn->prepare($sqlFirst);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$customerSince = isset($result['first_transaction'])
    ? (date('Y') - date('Y', strtotime($result['first_transaction'])))
    : 0;
$stmt->close();

// 2. Total Business (we use this as Total Sales)
$sqlBusiness = "SELECT SUM(bill_amount) AS total_business FROM main_table WHERE customer_id = ?";
$stmt = $conn->prepare($sqlBusiness);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$rowBusiness = $stmt->get_result()->fetch_assoc();
$totalBusiness = isset($rowBusiness['total_business']) ? $rowBusiness['total_business'] : 0;
$stmt->close();

// 3. Total Transactions
$sqlTransactions = "SELECT COUNT(*) AS total_transactions FROM main_table WHERE customer_id = ?";
$stmt = $conn->prepare($sqlTransactions);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$rowTransactions = $stmt->get_result()->fetch_assoc();
$totalTransactions = isset($rowTransactions['total_transactions']) ? $rowTransactions['total_transactions'] : 0;
$stmt->close();

// 4. Total Profit and Average Margin
$sqlProfit = "SELECT SUM(profit) AS total_profit, (SUM(profit)/SUM(bill_amount))*100 AS avg_margin FROM main_table WHERE customer_id = ?";
$stmt = $conn->prepare($sqlProfit);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$rowProfit = $stmt->get_result()->fetch_assoc();
$totalProfit = isset($rowProfit['total_profit']) ? $rowProfit['total_profit'] : 0;
$avgMargin = isset($rowProfit['avg_margin']) ? $rowProfit['avg_margin'] : 0;
$stmt->close();

$conn->close();

// Build a clean response. Email, Phone, and Address are omitted.
$response = [
    'name'                => $name,
    'total_sales'         => round($totalBusiness, 2),
    'customer_since'      => (int)$customerSince,
    'total_transactions'  => (int)$totalTransactions,
    'total_profit'        => round($totalProfit, 2),
    'average_margin'      => round($avgMargin, 2)
];

echo json_encode($response);
?>