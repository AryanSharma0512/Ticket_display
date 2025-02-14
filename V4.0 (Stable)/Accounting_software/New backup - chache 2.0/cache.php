<?php
include 'db_config.php'; // Make sure this points to your actual DB config file.

try {
    // Aggregate calculations
    $sql = "UPDATE Main_table SET 
                net_total_collectable = (SELECT SUM(bill_amount) FROM Main_table),
                net_amount_received = (SELECT SUM(amount_received) FROM Main_table),
                total_collection_due = (SELECT SUM(bill_amount) - SUM(amount_received) FROM Main_table),
                net_total_profit = (SELECT SUM(profit) FROM Main_table),
                net_margin = (SELECT CASE WHEN SUM(bill_amount) > 0 THEN SUM(profit) / SUM(bill_amount) * 100 ELSE 0 END FROM Main_table)";

    // Execute the query
    $result = $conn->query($sql);
    if ($result) {
        echo "Aggregates updated successfully.";
    } else {
        throw new Exception("Error updating aggregates: " . $conn->error);
    }
} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage();
} finally {
    $conn->close();
}
?>
