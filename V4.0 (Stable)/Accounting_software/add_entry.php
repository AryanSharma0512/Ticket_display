<?php
include_once(__DIR__ . '/db_config.php');

// Function to generate a unique 5-character customer ID
function generateCustomerID($conn) {
    while (true) {
        $customerID = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5);
        $stmt = $conn->prepare("SELECT customer_id FROM Main_table WHERE customer_id = ?");
        $stmt->bind_param("s", $customerID);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return $customerID;
        }
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
        return $row['customer_id'];
    } else {
        return generateCustomerID($conn);
    }
}

// Function to generate a unique 11-character transaction ID
function generateTransactionID() {
    $timestamp = microtime(true);
    $randomChars = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5);
    $hash = substr(hash('sha256', $timestamp . $randomChars), 0, 11);
    return strtoupper($hash);
}

// Function to generate a cryptographically secure 10-character event ID
function generateEventID() {
    $randomBytes = random_bytes(5);
    $eventID = substr(base64_encode($randomBytes), 0, 10);
    return strtoupper(str_replace(['+', '/','='], '', $eventID));
}

function logTransactionEvent($conn, $transactionID, $eventType, $eventDescription) {
    $eventID = generateEventID();
    $stmt = $conn->prepare("INSERT INTO transaction_events (transaction_id, event_id, event_type, event_description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $transactionID, $eventID, $eventType, $eventDescription);
    if ($stmt->execute()) {
        echo "Event logged successfully for Transaction ID: $transactionID\n";
    } else {
        echo "Error logging event: " . $stmt->error;
    }
    $stmt->close();
}

function updateCustomerAggregates($conn, $customerId) {
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

    $customerId = getCustomerId($customerName, $conn);

    $billAmount = $price * $quantity;
    $profit = $billAmount - $ourCost;
    $margin = $billAmount != 0 ? ($profit / abs($billAmount)) * 100 : 0;

    $transactionID = generateTransactionID();

    $sql = "INSERT INTO Main_table (customer_id, customer_name, product, price, quantity, bill_amount, our_cost, profit, margin, channel, amount_received, transaction_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sssiiddissss", $customerId, $customerName, $product, $price, $quantity, $billAmount, $ourCost, $profit, $margin, $channel, $amountReceived, $transactionID);
        if ($stmt->execute()) {
            echo "New entry created successfully with Transaction ID: " . $transactionID . " and Customer ID: " . $customerId . "\n";

            logTransactionEvent($conn, $transactionID, 'purchase', 'Initial transaction entry.');

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
?>