<?php
include_once(__DIR__ . '/db_config.php'); // Include database configuration

try {
    // Connect to the database
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Start transaction
    $conn->begin_transaction();

    // Step 1: Reset IDs
    // Check if the id column is both PRIMARY KEY and AUTO_INCREMENT
    $primaryKeyCheck = $conn->query("SHOW KEYS FROM Main_table WHERE Key_name = 'PRIMARY'");
    $isAutoIncrement = $conn->query("SHOW COLUMNS FROM Main_table WHERE Field = 'id' AND Extra LIKE '%auto_increment%'");

    if (!$primaryKeyCheck || $primaryKeyCheck->num_rows === 0 || !$isAutoIncrement || $isAutoIncrement->num_rows === 0) {
        // Fix if PRIMARY KEY or AUTO_INCREMENT is missing
        $conn->query("ALTER TABLE Main_table MODIFY id INT NOT NULL");
        $conn->query("ALTER TABLE Main_table ADD PRIMARY KEY (id)");
        $conn->query("ALTER TABLE Main_table MODIFY id INT NOT NULL AUTO_INCREMENT");
    }

    // Renumber IDs
    $conn->query("SET @new_id = 0");
    $conn->query("UPDATE Main_table SET id = (@new_id := @new_id + 1) ORDER BY id");
    $conn->query("ALTER TABLE Main_table AUTO_INCREMENT = 1");

    // Step 2: Check if recalculation is needed
    $recalculate = isset($_GET['recalculate']) && $_GET['recalculate'] === 'true';

    if ($recalculate) {
        // Recalculate general fields
        $updateQueries = [
            "UPDATE Main_table SET net_total_collectable = (SELECT SUM(bill_amount) FROM Main_table)",
            "UPDATE Main_table SET net_amount_received = (SELECT SUM(amount_received) FROM Main_table)",
            "UPDATE Main_table SET total_collection_due = net_total_collectable - net_amount_received",
            "UPDATE Main_table SET net_total_profit = (SELECT SUM(profit) FROM Main_table)",
            "UPDATE Main_table SET net_margin = IF(net_total_collectable > 0, (net_total_profit / net_total_collectable) * 100, 0)"
        ];

        foreach ($updateQueries as $query) {
            if (!$conn->query($query)) {
                throw new Exception("Error recalculating fields: " . $conn->error);
            }
        }

        // Recalculate customer-specific fields
        $customerSpecificQueries = [
            // Update net_total_collectable_from_customer
            "UPDATE Main_table AS mt 
                INNER JOIN (
                    SELECT customer_id, SUM(bill_amount) AS customer_total 
                    FROM Main_table GROUP BY customer_id
                ) AS sub ON mt.customer_id = sub.customer_id 
                SET mt.net_total_collectable_from_customer = sub.customer_total",

            // Update net_amount_received_from_customer
            "UPDATE Main_table AS mt 
                INNER JOIN (
                    SELECT customer_id, SUM(amount_received) AS customer_total 
                    FROM Main_table GROUP BY customer_id
                ) AS sub ON mt.customer_id = sub.customer_id 
                SET mt.net_amount_received_from_customer = sub.customer_total",

            // Update net_total_profit_from_customer
            "UPDATE Main_table AS mt 
                INNER JOIN (
                    SELECT customer_id, SUM(profit) AS customer_total 
                    FROM Main_table GROUP BY customer_id
                ) AS sub ON mt.customer_id = sub.customer_id 
                SET mt.net_total_profit_from_customer = sub.customer_total",

            // Update net_total_collectable_due_from_customer
            "UPDATE Main_table AS mt 
                INNER JOIN (
                    SELECT customer_id, 
                        SUM(bill_amount) AS net_total_collectable_from_customer, 
                        SUM(amount_received) AS net_amount_received_from_customer 
                    FROM Main_table GROUP BY customer_id
                ) AS sub ON mt.customer_id = sub.customer_id 
                SET mt.net_total_collectable_due_from_customer = 
                    sub.net_total_collectable_from_customer - sub.net_amount_received_from_customer"
        ];

        foreach ($customerSpecificQueries as $query) {
            if (!$conn->query($query)) {
                throw new Exception("Error recalculating customer-specific fields: " . $conn->error);
            }
        }

        echo "IDs reset and all fields recalculated successfully.";
    } else {
        // Skip recalculation for efficiency
        echo "IDs reset successfully without recalculating fields.";
    }

    // Commit transaction
    $conn->commit();
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "Error resetting IDs: " . $e->getMessage();
} finally {
    $conn->close();
}
?>
