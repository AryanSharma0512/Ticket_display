<?php
header('Content-Type: application/json');

$transactionID = $_POST['transactionID'] ?? '';
if (!$transactionID) {
    echo json_encode(["error" => "Transaction ID is required."]);
    exit;
}

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'accounting';
$db_host_acc = '127.0.0.1';
$db_user_acc = 'root';
$db_pass_acc = '';
$db_name_acc = 'accounts_network';

$connAccounting = new mysqli($db_host, $db_user, $db_pass, $db_name);
$connAccounts = new mysqli($db_host_acc, $db_user_acc, $db_pass_acc, $db_name_acc);

if ($connAccounting->connect_error || $connAccounts->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $connAccounting->connect_error]);
    exit;
}

$eventID = null;
$result = $connAccounting->query("SELECT event_id FROM transaction_events WHERE transaction_id = '$transactionID'");
if ($row = $result->fetch_assoc()) {
    $eventID = $row['event_id'];
}

$transactionIDs = [$transactionID];
if ($eventID) {
    $result = $connAccounting->query("SELECT transaction_id FROM transaction_events WHERE event_id = '$eventID'");
    while ($row = $result->fetch_assoc()) {
        if (!in_array($row['transaction_id'], $transactionIDs)) {
            $transactionIDs[] = $row['transaction_id'];
        }
    }
}

$transactionIDList = "'" . implode("','", $transactionIDs) . "'";

$mainTableData = [];
$result = $connAccounting->query("SELECT transaction_id, customer_name, customer_id, product, price, quantity, bill_amount, amount_received, our_cost, channel, profit, margin, entry_date FROM main_table WHERE transaction_id IN ($transactionIDList) ORDER BY entry_date ASC");
while ($row = $result->fetch_assoc()) {
    $mainTableData[] = $row;
}

$accNetworkData = [];
$result = $connAccounts->query("SELECT transaction_id, customer_id, account_holder, product_category, amount_used, amount_paid, entry_date, channel FROM acc_network_main WHERE transaction_id IN ($transactionIDList) ORDER BY entry_date ASC");
while ($row = $result->fetch_assoc()) {
    $accNetworkData[] = $row;
}

$connAccounting->close();
$connAccounts->close();

echo json_encode([
    "main_table" => $mainTableData,
    "acc_network_main" => $accNetworkData
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>