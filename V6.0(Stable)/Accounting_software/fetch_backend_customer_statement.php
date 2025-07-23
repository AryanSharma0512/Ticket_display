<?php
header('Content-Type: application/json');

if (!isset($_POST['accountHolder']) || trim($_POST['accountHolder']) === '') {
    echo json_encode(['error' => 'Account holder name is required']);
    exit;
}
$accountHolder = trim($_POST['accountHolder']);

require_once __DIR__ . '/db_config_acc.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$stmt = $conn->prepare("SELECT transaction_id, product_category, amount_used, amount_paid, entry_date FROM acc_network_main WHERE account_holder = ? ORDER BY entry_date ASC");
$stmt->bind_param('s', $accountHolder);
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT SUM(amount_used) AS total_used, SUM(amount_paid) AS total_paid FROM acc_network_main WHERE account_holder = ?");
$stmt->bind_param('s', $accountHolder);
$stmt->execute();
$totalsRes = $stmt->get_result();
$totals = ['total_used' => 0, 'total_paid' => 0];
if ($row = $totalsRes->fetch_assoc()) {
    $totals['total_used'] = (float)($row['total_used'] ?? 0);
    $totals['total_paid'] = (float)($row['total_paid'] ?? 0);
}
$stmt->close();
$conn->close();

echo json_encode([
    'transactions' => $transactions,
    'total_used' => $totals['total_used'],
    'total_paid' => $totals['total_paid']
]);
