<?php
header('Content-Type: application/json');

if (!isset($_POST['accountHolder']) || trim($_POST['accountHolder']) === '') {
    echo json_encode(['error' => 'Account holder name is required']);
    exit;
}
$accountHolder = trim($_POST['accountHolder']);
$fromDate = $_POST['fromDate'] ?? '';
$toDate = $_POST['toDate'] ?? '';

require_once __DIR__ . '/db_config_acc.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($fromDate === '') {
    $stmt = $conn->prepare("SELECT entry_date FROM acc_network_main WHERE account_holder = ? ORDER BY entry_date ASC LIMIT 1");
    $stmt->bind_param('s', $accountHolder);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $fromDate = $row['entry_date'];
    }
    $stmt->close();
}

if ($toDate === '') {
    $stmt = $conn->prepare("SELECT entry_date FROM acc_network_main WHERE account_holder = ? ORDER BY entry_date DESC LIMIT 1");
    $stmt->bind_param('s', $accountHolder);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $toDate = $row['entry_date'];
    }
    $stmt->close();
}

if ($fromDate === '' || $toDate === '') {
    echo json_encode(['error' => 'No transactions found for this account holder']);
    $conn->close();
    exit;
}

$stmt = $conn->prepare("SELECT transaction_id, product_category, amount_used, amount_paid, entry_date FROM acc_network_main WHERE account_holder = ? AND entry_date BETWEEN ? AND ? ORDER BY entry_date ASC");
$stmt->bind_param('sss', $accountHolder, $fromDate, $toDate);
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT SUM(amount_used) AS total_used, SUM(amount_paid) AS total_paid FROM acc_network_main WHERE account_holder = ? AND entry_date BETWEEN ? AND ?");
$stmt->bind_param('sss', $accountHolder, $fromDate, $toDate);
$stmt->execute();
$totalsRes = $stmt->get_result();
$totals = ['total_used' => 0, 'total_paid' => 0];
if ($row = $totalsRes->fetch_assoc()) {
    $totals['total_used'] = (float)($row['total_used'] ?? 0);
    $totals['total_paid'] = (float)($row['total_paid'] ?? 0);
}
$stmt->close();

$stmt = $conn->prepare("SELECT SUM(amount_used) AS used_before, SUM(amount_paid) AS paid_before FROM acc_network_main WHERE account_holder = ? AND entry_date < ?");
$stmt->bind_param('ss', $accountHolder, $fromDate);
$stmt->execute();
$balRes = $stmt->get_result();
$openingBalance = 0;
if ($row = $balRes->fetch_assoc()) {
    $openingBalance = (float)($row['used_before'] ?? 0) - (float)($row['paid_before'] ?? 0);
}
$stmt->close();
$netBalance = $openingBalance + $totals['total_used'] - $totals['total_paid'];
$conn->close();

echo json_encode([
    'transactions' => $transactions,
    'total_used' => $totals['total_used'],
    'total_paid' => $totals['total_paid'],
    'opening_balance' => $openingBalance,
    'net_balance' => $netBalance
]);
