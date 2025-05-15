<?php

include_once(__DIR__ . '/db_config.php'); // ✅ Include database config

// Database details for accounts_network
$servernameAccountsNetwork = "localhost";
$usernameAccountsNetwork = "root";
$passwordAccountsNetwork = "";
$dbnameAccountsNetwork = "accounts_network";

// ✅ Function to connect to a database
function connectToDatabase($servername, $username, $password, $dbname) {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die(json_encode(['error' => "Connection failed: " . $conn->connect_error]));
    }
    return $conn;
}

// ✅ Connect to both databases
$connAccounting = connectToDatabase($servername, $username, $password, $dbname);
$connAccountsNetwork = connectToDatabase($servernameAccountsNetwork, $usernameAccountsNetwork, $passwordAccountsNetwork, $dbnameAccountsNetwork);

// ✅ Fetch last 10 transactions from `main_table`
$sqlMain = "SELECT transaction_id, customer_name, customer_id, bill_amount, entry_date FROM main_table ORDER BY entry_date DESC LIMIT 10";
$resultMain = $connAccounting->query($sqlMain);

// ✅ Fetch last 10 transactions from `acc_network_main`
$sqlAccNetwork = "SELECT transaction_id, account_holder, amount_used, entry_date FROM acc_network_main ORDER BY entry_date DESC LIMIT 10";
$resultAccNetwork = $connAccountsNetwork->query($sqlAccNetwork);

$transactions = [
    'mainTable' => [],
    'accNetworkMain' => []
];

if ($resultMain) {
    while ($row = $resultMain->fetch_assoc()) {
        $transactions['mainTable'][] = $row;
    }
} else {
    $transactions['mainTable'] = [];
    error_log("Error in main_table query: " . $connAccounting->error);
}

if ($resultAccNetwork) {
    while ($row = $resultAccNetwork->fetch_assoc()) {
        $transactions['accNetworkMain'][] = $row;
    }
} else {
    $transactions['accNetworkMain'] = [];
    error_log("Error in acc_network_main query: " . $connAccountsNetwork->error);
}

header('Content-Type: application/json');
echo json_encode(['transactions' => $transactions]);

$connAccounting->close();
$connAccountsNetwork->close();

?>
