<?php
include_once(__DIR__ . '/db_config.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $eventID = $_POST['eventID'];

    // Connect to the 'accounting' database
    $connAccounting = new mysqli($servername, $username, $password, "accounting");
    if ($connAccounting->connect_error) {
        die("Connection to accounting database failed: " . $connAccounting->connect_error);
    }

    // Connect to the 'accounts_network' database
    $connAccountsNetwork = new mysqli($servername, $username, $password, "accounts_network");
    if ($connAccountsNetwork->connect_error) {
        die("Connection to accounts_network database failed: " . $connAccountsNetwork->connect_error);
    }

    $eventQuery = "SELECT transaction_id FROM transaction_events WHERE event_id = '$eventID'";
    $eventResult = $connAccounting->query($eventQuery);

    if ($eventResult) {
        $transactionIDs = [];
        while ($row = $eventResult->fetch_assoc()) {
            $transactionIDs[] = $row['transaction_id'];
        }

        if (count($transactionIDs) > 0) {
            $inClause = "'" . implode("','", $transactionIDs) . "'";

            $mainQuery = "SELECT * FROM Main_table WHERE transaction_id IN ($inClause)";
            $accNetworkQuery = "SELECT * FROM acc_network_main WHERE transaction_id IN ($inClause)";

            $mainResult = $connAccounting->query($mainQuery);
            $accNetworkResult = $connAccountsNetwork->query($accNetworkQuery);

            $results = [
                'main_table' => [],
                'acc_network_main' => []
            ];

            while ($row = $mainResult->fetch_assoc()) {
                $results['main_table'][] = $row;
            }

            while ($row = $accNetworkResult->fetch_assoc()) {
                $results['acc_network_main'][] = $row;
            }

            echo json_encode($results);
        } else {
            echo json_encode(['error' => 'No transactions found for this Event ID.']);
        }
    } else {
        echo json_encode(['error' => $connAccounting->error]);
    }

    $connAccounting->close();
    $connAccountsNetwork->close();
}
?>