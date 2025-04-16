<?php

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "accounting";

$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data (sanitize to prevent SQL injection)
$customer = $conn->real_escape_string($_POST["customer"]);
$product = $conn->real_escape_string($_POST["product"]);
$price = floatval($_POST["price"]); // Ensure price is treated as float
$quantity = intval($_POST["quantity"]); // Ensure quantity is treated as integer
$ourCost = floatval($_POST["our_cost"]); // Ensure our_cost is treated as float
$channel = $conn->real_escape_string($_POST["channel"]);
$amountReceived = floatval($_POST["amount_received"]); // Ensure amount received is treated as float

// Calculate bill amount
$billAmount = $price * $quantity;

// Create table for customer if not exists
$tableName = strtolower(str_replace('','_', $customer)); 
$sqlCreateTable = "CREATE TABLE IF NOT EXISTS `$tableName` (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product VARCHAR(255) NOT NULL,
                    price FLOAT NOT NULL,
                    quantity INT NOT NULL,
                    bill_amount FLOAT NOT NULL,
                    net_total_collectable FLOAT NOT NULL DEFAULT 0,
                    amount_received FLOAT NOT NULL,
                    net_amount_received FLOAT NOT NULL DEFAULT 0,
                    total_collection_due FLOAT NOT NULL DEFAULT 0,
                    our_cost FLOAT NOT NULL,
                    channel VARCHAR(255) NOT NULL,
                    profit FLOAT NOT NULL DEFAULT 0,
                    margin DECIMAL(10, 4) NOT NULL DEFAULT 0,
                    net_total_profit FLOAT NOT NULL DEFAULT 0,
                    net_margin DECIMAL(10,4) NOT NULL DEFAULT 0,
                    entry_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";

if ($conn->query($sqlCreateTable) === TRUE) {
    // Table created successfully or already exists

    // Calculate profit & margin
    $profit = $billAmount - $ourCost;
    $margin = ($billAmount != 0) ? ($profit / $billAmount) * 100 : 0;

    // Insert entry into customer's table
    $sqlInsertEntry = "INSERT INTO `$tableName` (product, price, quantity, bill_amount, amount_received, net_amount_received, total_collection_due, our_cost, channel, profit, margin, net_margin) 
                     VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 0)";
    $stmt = $conn->prepare($sqlInsertEntry);    
    $stmt->bind_param("sdiididsss", $product, $price, $quantity, $billAmount, $amountReceived, $amountReceived, $ourCost, $channel, $profit, $margin);
    
    
    if ($stmt->execute()) {
        echo "Entry created successfully! <br>";
        echo "Channel: " .$channel;
    } else {
        echo "Error inserting entry: " . $stmt->error . "<br>";
    }

    $stmt->close();

    // Update net total collectable for the customer
    $sqlUpdateNetTotalCollectable = "UPDATE `$tableName` SET net_total_collectable = (SELECT SUM(bill_amount) FROM `$tableName`)";
    if ($conn->query($sqlUpdateNetTotalCollectable) === FALSE) {
        echo "Error updating net total collectable: " . $conn->error;
    }

    // Update net total amount received for the customer
    $sqlUpdateNetAmountReceived = "UPDATE `$tableName` SET net_amount_received = (SELECT SUM(amount_received) FROM `$tableName`)";
    if ($conn->query($sqlUpdateNetAmountReceived) === FALSE) {
        echo "Error updating net amount received: " . $conn->error;
    }

    // Fetch the updated net total collectable to calculate total collection due
    $sqlFetchNetTotalCollectable = "SELECT MAX(net_total_collectable) AS net_total_collectable FROM `$tableName`";
    $result = $conn->query($sqlFetchNetTotalCollectable);
    if ($result) {
        $row = $result->fetch_assoc();
        $netTotalCollectable = $row['net_total_collectable'];

        // Update total collection due
        $sqlUpdateTotalCollectionDue = "UPDATE `$tableName` SET total_collection_due = $netTotalCollectable - (SELECT SUM(amount_received) FROM `$tableName`)";
        if ($conn->query($sqlUpdateTotalCollectionDue) === FALSE) {
            echo "Error updating total collection due: " . $conn->error;
        }
    } else {
        echo "Error fetching net total collectable: " . $conn->error;
    }

    // Update net total profit for the customer
    $sqlUpdateNetTotalProfit = "UPDATE `$tableName` SET net_total_profit = (SELECT SUM(profit) FROM `$tableName`)";
    if ($conn->query($sqlUpdateNetTotalProfit) === FALSE) {
        echo "Error updating net total profit: " . $conn->error;
    }

    // Calculate and update net margin
    $sqlFetchNetTotalProfit = "SELECT MAX(net_total_profit) AS net_total_profit FROM `$tableName`";
    $result = $conn->query($sqlFetchNetTotalProfit);
    if ($result) {
        $row = $result->fetch_assoc();
        $netTotalProfit = $row['net_total_profit'];

        $netMargin = ($netTotalCollectable != 0) ? $netTotalProfit / $netTotalCollectable : 0;
        $netMargin = $netMargin*100;

        $sqlUpdateNetMargin = "UPDATE `$tableName` SET net_margin = $netMargin";
        if ($conn->query($sqlUpdateNetMargin) === FALSE) {
            echo "Error updating net margin: " . $conn->error;
        }
    } else {
        echo "Error fetching net total profit: " . $conn->error;
    }

} else {
    echo "Error creating table: " . $conn->error;
}

// Close database connection
$conn->close();

?>