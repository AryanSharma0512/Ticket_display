<?php
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $transactionID = $_POST['transactionID'];

    // Database configuration for main_table (accounting)
    $servername_main = "localhost";  // Update if different
    $username_main = "root";       // Update if different
    $password_main = "";           // Update if different
    $dbname_main = "accounting";    // Update if different

    $connMain = new mysqli($servername_main, $username_main, $password_main, $dbname_main);

    if ($connMain->connect_error) {
        echo json_encode(['error' => 'Connection to main database failed: ' . $connMain->connect_error]);
        exit;
    }


    // Database configuration for acc_network_main (accounts_network)
    $servername_acc = "localhost"; // Update if different
    $username_acc = "root";      // Update if different
    $password_acc = "";          // Update if different
    $dbname_acc = "accounts_network"; // Update if different

    $connAcc = new mysqli($servername_acc, $username_acc, $password_acc, $dbname_acc);

    if ($connAcc->connect_error) {
        echo json_encode(['error' => 'Connection to accounts_network database failed: ' . $connAcc->connect_error]);
        $connMain->close(); // Close the first connection before exiting.
        exit;
    }


    $results = [
        'main_table' => [],
        'acc_network_main' => []
    ];

    $query1 = "SELECT * FROM main_table WHERE transaction_id = '$transactionID'";
    $result1 = $connMain->query($query1);

    if ($result1) {
        while ($row = $result1->fetch_assoc()) {
            $results['main_table'][] = $row;
        }
    } else {
        $results['main_table']['error'] = $connMain->error;
    }

    $query2 = "SELECT * FROM acc_network_main WHERE transaction_id = '$transactionID'"; // Corrected table name
    $result2 = $connAcc->query($query2);

    if ($result2) {
        while ($row = $result2->fetch_assoc()) {
            $results['acc_network_main'][] = $row;
        }
    } else {
        $results['acc_network_main']['error'] = $connAcc->error;
    }

    $connMain->close();
    $connAcc->close();

    echo json_encode($results);
}
?>