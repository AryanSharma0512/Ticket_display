<?php
// fetch_customer_statement.php
header('Content-Type: application/json');

// 1) Validate POST data
if (!isset($_POST['customerID']) || empty($_POST['customerID'])) {
    echo json_encode(['error' => 'Customer ID is required']);
    exit;
}

$customerID = $_POST['customerID'];
$fromDate = $_POST['fromDate'] ?? '';
$toDate = $_POST['toDate'] ?? '';
$unsettledOnly = isset($_POST['unsettledOnly']) && $_POST['unsettledOnly'] == 1;

// 2) Database connection
require_once 'db_config.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Determine date range if not provided
if ($fromDate === '') {
    $stmt = $conn->prepare("SELECT entry_date FROM main_table WHERE customer_id = ? ORDER BY entry_date ASC LIMIT 1");
    $stmt->bind_param('s', $customerID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $fromDate = $row['entry_date'];
    }
    $stmt->close();
}

if ($toDate === '') {
    $stmt = $conn->prepare("SELECT entry_date FROM main_table WHERE customer_id = ? ORDER BY entry_date DESC LIMIT 1");
    $stmt->bind_param('s', $customerID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $toDate = $row['entry_date'];
    }
    $stmt->close();
}

if ($fromDate === '' || $toDate === '') {
    echo json_encode(['error' => 'No transactions found for this customer']);
    $conn->close();
    exit;
}

// 3) Fetch transactions
$sql = "SELECT
            transaction_id,
            product,
            price,
            quantity,
            bill_amount,
            amount_received,
            entry_date
        FROM main_table
        WHERE customer_id = ? AND entry_date BETWEEN ? AND ?
        ORDER BY entry_date ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'Database query preparation failed']);
    exit;
}

$stmt->bind_param("sss", $customerID, $fromDate, $toDate);
if (!$stmt->execute()) {
    echo json_encode(['error' => 'Database query execution failed']);
    exit;
}

$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 4) Enhanced unsettled transactions filtering
if ($unsettledOnly && !empty($transactions)) {
    $runningBalance = 0.0;
    $lastSettledIndex = -1;

    foreach ($transactions as $index => $transaction) {
        $bill = (float)$transaction['bill_amount'];
        $paid = (float)$transaction['amount_received'];
        
        $runningBalance += ($bill - $paid);
        
        if (abs($runningBalance) < 0.01) {
            $lastSettledIndex = $index;
        }
    }

    if ($lastSettledIndex === (count($transactions) - 1)) {
        $transactions = [];
    } else {
        $transactions = array_slice($transactions, $lastSettledIndex + 1);
    }
}

// 5) Calculate Due Amount dynamically within the selected range
$sqlDue = "SELECT SUM(bill_amount) - SUM(amount_received) AS due_from_customer
           FROM main_table WHERE customer_id = ? AND entry_date BETWEEN ? AND ?";
$stmtDue = $conn->prepare($sqlDue);
$stmtDue->bind_param("sss", $customerID, $fromDate, $toDate);
$stmtDue->execute();
$resultDue = $stmtDue->get_result();
$dueAmount = 0;

if ($row = $resultDue->fetch_assoc()) {
    $dueAmount = $row['due_from_customer'] ? floatval($row['due_from_customer']) : 0;
}

$stmtDue->close();
$conn->close();

// 6) Return response
if (empty($transactions)) {
    echo json_encode(['message' => 'No transactions found', 'transactions' => [], 'due_from_customer' => $dueAmount]);
} else {
    echo json_encode(['transactions' => $transactions, 'due_from_customer' => $dueAmount]);
}