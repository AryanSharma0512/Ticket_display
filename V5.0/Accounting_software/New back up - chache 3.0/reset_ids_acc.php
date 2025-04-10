<?php
include_once(__DIR__ . '/db_config_acc.php'); // Include database configuration

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
    $primaryKeyCheck = $conn->query("SHOW KEYS FROM acc_network_main WHERE Key_name = 'PRIMARY'");
    $isAutoIncrement = $conn->query("SHOW COLUMNS FROM acc_network_main WHERE Field = 'id' AND Extra LIKE '%auto_increment%'");

    if (!$primaryKeyCheck || $primaryKeyCheck->num_rows === 0 || !$isAutoIncrement || $isAutoIncrement->num_rows === 0) {
        // Fix if PRIMARY KEY or AUTO_INCREMENT is missing
        $conn->query("ALTER TABLE acc_network_main MODIFY id INT NOT NULL");
        $conn->query("ALTER TABLE acc_network_main ADD PRIMARY KEY (id)");
        $conn->query("ALTER TABLE acc_network_main MODIFY id INT NOT NULL AUTO_INCREMENT");
    }

    // Renumber IDs
    $conn->query("SET @new_id = 0");
    $conn->query("UPDATE acc_network_main SET id = (@new_id := @new_id + 1) ORDER BY id");
    $conn->query("ALTER TABLE acc_network_main AUTO_INCREMENT = 1");

    // Step 2: Check if recalculation is needed
    $recalculate = isset($_GET['recalculate']) && $_GET['recalculate'] === 'true';

    if ($recalculate) {
        // Recalculate aggregated fields
        $updateQueries = [
            "UPDATE acc_network_main SET net_amount_used = (SELECT SUM(amount_used) FROM acc_network_main)",
            "UPDATE acc_network_main SET net_amount_paid = (SELECT SUM(amount_paid) FROM acc_network_main)",
            "UPDATE acc_network_main SET total_debt_due = (SELECT SUM(amount_used) - SUM(amount_paid) FROM acc_network_main)"
        ];

        foreach ($updateQueries as $query) {
            if (!$conn->query($query)) {
                throw new Exception("Error recalculating fields: " . $conn->error);
            }
        }

        // Recalculate customer-specific fields
        $customerSpecificQueries = [
            "UPDATE acc_network_main AS am 
                INNER JOIN (
                    SELECT customer_id, SUM(amount_used) AS total_used, SUM(amount_paid) AS total_paid 
                    FROM acc_network_main GROUP BY customer_id
                ) AS sub ON am.customer_id = sub.customer_id 
                SET am.net_amount_used_from_customer = sub.total_used, 
                    am.net_amount_paid_to_customer = sub.total_paid, 
                    am.total_debt_due_to_customer = sub.total_used - sub.total_paid"
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
