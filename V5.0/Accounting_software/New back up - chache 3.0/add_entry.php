<?php
include_once(__DIR__ . '/db_config.php'); // Ensure the correct path to the database configuration file

// Function to generate a unique 5-character customer ID
function generateCustomerID($conn) {
    while (true) {
        // Generate a random 5-character alphanumeric ID
        $customerID = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5);

        // Check if the generated ID already exists
        $stmt = $conn->prepare("SELECT customer_id FROM Main_table WHERE customer_id = ?");
        $stmt->bind_param("s", $customerID);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // If the ID is unique, return it
            return $customerID;
        }
        // If the ID exists, repeat the process
    }
}

// Function to get or create a customer ID
function getCustomerId($customerName, $conn) {
    $stmt = $conn->prepare("SELECT customer_id FROM Main_table WHERE customer_name = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $customerName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['customer_id']; // Return the existing customer ID
    } else {
        // Generate a new unique customer ID
        return generateCustomerID($conn);
    }
}

function updateAggregates($conn) {
    // Update general aggregates
    $generalAggregates = [
        "UPDATE Main_table SET net_total_collectable = (SELECT SUM(bill_amount) FROM Main_table)",
        "UPDATE Main_table SET net_amount_received = (SELECT SUM(amount_received) FROM Main_table)",
        "UPDATE Main_table SET total_collection_due = net_total_collectable - net_amount_received",
        "UPDATE Main_table SET net_total_profit = (SELECT SUM(profit) FROM Main_table)",
        "UPDATE Main_table SET net_margin = IF(net_total_collectable > 0, (net_total_profit / net_total_collectable) * 100, 0)"
    ];

    foreach ($generalAggregates as $query) {
        if ($conn->query($query) === FALSE) {
            echo "Error updating general aggregates: " . $conn->error;
            return false;
        }
    }

    // Update customer-specific aggregates
    $customerSpecificAggregates = [
        "UPDATE Main_table AS mt 
            INNER JOIN (
                SELECT customer_id, SUM(bill_amount) AS customer_total 
                FROM Main_table GROUP BY customer_id
            ) AS sub ON mt.customer_id = sub.customer_id 
            SET mt.net_total_collectable_from_customer = sub.customer_total",

        "UPDATE Main_table AS mt 
            INNER JOIN (
                SELECT customer_id, SUM(amount_received) AS customer_total 
                FROM Main_table GROUP BY customer_id
            ) AS sub ON mt.customer_id = sub.customer_id 
            SET mt.net_amount_received_from_customer = sub.customer_total",

        "UPDATE Main_table AS mt 
            INNER JOIN (
                SELECT customer_id, SUM(profit) AS customer_total 
                FROM Main_table GROUP BY customer_id
            ) AS sub ON mt.customer_id = sub.customer_id 
            SET mt.net_total_profit_from_customer = sub.customer_total",

        // Calculate net_total_collectable_due_from_customer
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

    foreach ($customerSpecificAggregates as $query) {
        if ($conn->query($query) === FALSE) {
            echo "Error updating customer-specific aggregates: " . $conn->error;
            return false;
        }
    }

    return true;
}

// Function to generate a unique 11-character transaction ID
function generateTransactionID() {
    $timestamp = microtime(true); // Current timestamp in microseconds
    $randomChars = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5); // Random alphanumeric
    $hash = substr(hash('sha256', $timestamp . $randomChars), 0, 11); // Generate hash and truncate to 11 chars
    return strtoupper($hash); // Ensure uppercase
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $customerName = trim($_POST['customer']);
    $product = trim($_POST['product']);
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);
    $ourCost = floatval($_POST['our_cost']);
    $channel = trim($_POST['channel']);
    $amountReceived = floatval($_POST['amount_received']);

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $customerId = getCustomerId($customerName, $conn); // Get or generate a customer ID

    $billAmount = $price * $quantity;
    $profit = $billAmount - $ourCost;
    $margin = $billAmount != 0 ? ($profit / $billAmount) * 100 : 0; // Calculate the margin

    // Generate a unique transaction ID
    $transactionID = generateTransactionID();

    $sql = "INSERT INTO Main_table (customer_id, customer_name, product, price, quantity, bill_amount, our_cost, profit, margin, channel, amount_received, transaction_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sssiiddissss", $customerId, $customerName, $product, $price, $quantity, $billAmount, $ourCost, $profit, $margin, $channel, $amountReceived, $transactionID);
        if ($stmt->execute()) {
            echo "New entry created successfully with Transaction ID: " . $transactionID . " and Customer ID: " . $customerId . "\n";
            // Update aggregates after the insert operation
            if (!updateAggregates($conn)) {
                echo "Failed to update aggregates.";
            }
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Error preparing statement: " . $conn->error;
    }
    $conn->close();
} else {
    echo "No data submitted";
}
?>
