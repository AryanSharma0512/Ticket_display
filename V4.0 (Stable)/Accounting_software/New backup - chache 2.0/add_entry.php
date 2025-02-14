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
            // Update customer-specific aggregates
            updateCustomerAggregates($conn, $customerId);
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

function updateCustomerAggregates($conn, $customerId) {
    // Calculate and update customer-specific aggregates
    $updateSql = "UPDATE Main_table SET
                    net_total_profit_from_customer = (SELECT SUM(profit) FROM Main_table WHERE customer_id = ?), 
                    net_total_collectable_from_customer = (SELECT SUM(bill_amount) FROM Main_table WHERE customer_id = ?),
                    net_amount_received_from_customer = (SELECT SUM(amount_received) FROM Main_table WHERE customer_id = ?),
                    net_total_collectable_due_from_customer = (SELECT SUM(bill_amount) - SUM(amount_received) FROM Main_table WHERE customer_id = ?)
                  WHERE customer_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("sssss", $customerId, $customerId, $customerId, $customerId, $customerId);
    if (!$updateStmt->execute()) {
        echo "Error updating customer aggregates: " . $updateStmt->error;
    }
    $updateStmt->close();
}
?>
