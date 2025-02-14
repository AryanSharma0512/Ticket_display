<?php

include_once(__DIR__ . '/db_config.php'); // Include the db_config.php file

// db_config_acc1.php (Configuration for accounts_network database)
//  Make sure you create this file if you haven't already.  REPLACE THESE WITH YOUR ACTUAL CREDENTIALS.
$servernameAccountsNetwork = "localhost"; // Or the IP address of your MySQL server if it's not on the same machine
$usernameAccountsNetwork = "root"; // Your actual username for the accounts_network database
$passwordAccountsNetwork = ""; // Your actual password for the accounts_network database
$dbnameAccountsNetwork = "accounts_network"; // The name of your accounts_network database



// Function to connect to a database (to avoid repeated code)
function connectToDatabase($servername, $username, $password, $dbname) {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die(json_encode(['error' => "Connection failed: " . $conn->connect_error]));
    }
    return $conn;
}

// Connect to the databases
$connAccounting = connectToDatabase($servername, $username, $password, $dbname);  // Use variables from db_config.php
$connAccountsNetwork = connectToDatabase($servernameAccountsNetwork, $usernameAccountsNetwork, $passwordAccountsNetwork, $dbnameAccountsNetwork); // Use variables from db_config_acc1.php



// Query to fetch the last 10 transactions from main_table
$sqlMain = "SELECT transaction_id, customer_name, customer_id, bill_amount, entry_date FROM main_table ORDER BY entry_date DESC LIMIT 10";  // Corrected column name (customer_id)
$resultMain = $connAccounting->query($sqlMain);

// Query to fetch the last 10 transactions from acc_network_main
$sqlAccNetwork = "SELECT transaction_id, account_holder, amount_used, entry_date FROM acc_network_main ORDER BY entry_date DESC LIMIT 10"; // Corrected column names
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
    error_log("Error in main_table query: " . $connAccounting->error); // Log the error for debugging
}

if ($resultAccNetwork) {
    while ($row = $resultAccNetwork->fetch_assoc()) {
        $transactions['accNetworkMain'][] = $row;
    }
} else {
    $transactions['accNetworkMain'] = [];
    error_log("Error in acc_network_main query: " . $connAccountsNetwork->error); // Log the error
}

header('Content-Type: application/json');
echo json_encode(['transactions' => $transactions]);

$connAccounting->close();
$connAccountsNetwork->close();

?>