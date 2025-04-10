<?php
include_once('db_config_acc.php'); // Make sure this path is correct

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$query = "SELECT customer_id, account_holder, SUM(amount_used) - SUM(amount_paid) AS amount_due
          FROM acc_network_main
          GROUP BY customer_id
          HAVING amount_due != 0";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    $data = [];
    while($row = $result->fetch_assoc()) {
        $data[] = [
            'customer_id' => $row['customer_id'],
            'account_holder' => $row['account_holder'],
            'amount_due' => $row['amount_due']
        ];
    }
    echo json_encode($data);
} else {
    echo json_encode(['error' => 'No data found']);
}

$conn->close();
?>
