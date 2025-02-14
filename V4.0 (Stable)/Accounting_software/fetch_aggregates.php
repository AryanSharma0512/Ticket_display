<?php
include 'db_config.php';

header('Content-Type: application/json');

$conn = new mysqli($servername, $username, $password, $dbname); // Use MySQLi

if ($conn->connect_error) { // Check for connection errors
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $conn->connect_error]);
    exit;
}

try {
    $query = "SELECT 
                SUM(bill_amount) AS net_collectable,
                SUM(amount_received) AS net_amount_received,
                (SUM(bill_amount) - SUM(amount_received)) AS total_collection_due,
                SUM(profit) AS total_profit,
                (SUM(profit) / SUM(bill_amount) * 100) AS net_margin
              FROM Main_table"; // Double-check the table name!

    $result = $conn->query($query);

    if ($result) {
        $data = $result->fetch_assoc();

        // Check if any of the sums returned null (if the table is empty, for example)
        $net_collectable = $data['net_collectable'] ?? 0;
        $net_amount_received = $data['net_amount_received'] ?? 0;
        $total_collection_due = $data['total_collection_due'] ?? 0;
        $total_profit = $data['total_profit'] ?? 0;
        $net_margin = $data['net_margin'] ?? 0; // Handle potential division by zero

        echo json_encode([
            'success' => true,
            'net_collectable' => number_format($net_collectable, 2),
            'net_amount_received' => number_format($net_amount_received, 2),
            'total_collection_due' => number_format($total_collection_due, 2),
            'total_profit' => number_format($total_profit, 2),
            'net_margin' => number_format($net_margin, 2) // Format net_margin
        ]);
    } else {
        throw new Exception("Failed to calculate aggregates: " . $conn->error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>