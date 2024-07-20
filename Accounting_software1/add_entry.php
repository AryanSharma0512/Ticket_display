<?php

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "acc_network";

$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data (sanitize to prevent SQL injection)
$customer = $conn->real_escape_string($_POST["customer"]);
$product = $conn->real_escape_string($_POST["product"]);
$amountUsed = floatval($_POST["amount_used"]); // Ensure amount used is treated as float
$channel = $conn->real_escape_string($_POST["channel"]);
$amountPaid = floatval($_POST["amount_paid"]); // Ensure amount paid is treated as float

// Create table for customer if not exists
$tableName = strtolower(str_replace('','_', $customer)); 
$sqlCreateTable = "CREATE TABLE IF NOT EXISTS `$tableName` (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product VARCHAR(255) NOT NULL,
                    amount_used FLOAT NOT NULL,
                    net_total_used FLOAT NOT NULL DEFAULT 0,
                    amount_paid FLOAT NOT NULL,
                    net_amount_paid FLOAT NOT NULL DEFAULT 0,
                    total_debt_due FLOAT NOT NULL DEFAULT 0,
                    channel VARCHAR(255) NOT NULL,
                    entry_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";

if ($conn->query($sqlCreateTable) === TRUE) {
    // Table created successfully or already exists

    // Insert entry into customer's table
    $sqlInsertEntry = "INSERT INTO `$tableName` (product, amount_used, amount_paid, net_amount_paid, total_debt_due, channel) 
                     VALUES (?, ?, ?, 0, ?, ?)";
    $stmt = $conn->prepare($sqlInsertEntry);    
    $stmt->bind_param("sdiis", $product, $amountUsed, $amountPaid, $amountPaid, $channel);
    
    if ($stmt->execute()) {
        echo "Entry created successfully! <br>";
        echo "Channel: " .$channel;
    } else {
        echo "Error inserting entry: " . $stmt->error . "<br>";
    }

    $stmt->close();

    // Update net total used for the customer
    $sqlUpdateNetTotalUsed = "UPDATE `$tableName` SET net_total_used = (SELECT SUM(amount_used) FROM `$tableName`)";
    if ($conn->query($sqlUpdateNetTotalUsed) === FALSE) {
        echo "Error updating net total used: " . $conn->error;
    }

    // Update net total amount paid for the customer
    $sqlUpdateNetAmountPaid = "UPDATE `$tableName` SET net_amount_paid = (SELECT SUM(amount_paid) FROM `$tableName`)";
    if ($conn->query($sqlUpdateNetAmountPaid) === FALSE) {
        echo "Error updating net amount paid: " . $conn->error;
    }

    // Fetch the updated net total used to calculate total debt due
    $sqlFetchNetTotalUsed = "SELECT MAX(net_total_used) AS net_total_used FROM `$tableName`";
    $result = $conn->query($sqlFetchNetTotalUsed);
    if ($result) {
        $row = $result->fetch_assoc();
        $netTotalUsed = $row['net_total_used'];

        // Update total debt due
        $sqlUpdateTotalDebtDue = "UPDATE `$tableName` SET total_debt_due = $netTotalUsed - (SELECT SUM(amount_paid) FROM `$tableName`)";
        if ($conn->query($sqlUpdateTotalDebtDue) === FALSE) {
            echo "Error updating total Debt due: " . $conn->error;
        }
    } else {
        echo "Error fetching net total used: " . $conn->error;
    }

} else {
    echo "Error creating table: " . $conn->error;
}

// Close database connection
$conn->close();

?>
