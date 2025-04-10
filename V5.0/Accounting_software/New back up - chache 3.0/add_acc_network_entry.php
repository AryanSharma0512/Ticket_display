<?php
$servername = "localhost";
$username = "root"; // your MySQL username
$password = ""; // your MySQL password
$dbname = "accounts_network";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve data from POST request
$accountHolder = $_POST['accountHolder'];
$productCategory = $_POST['productCategory'];   
$amountUsed = $_POST['amountUsed'];
$amountPaid = $_POST['amountPaid'];
$transactionID = $_POST['transactionID'];

// Check if the account holder already has a customer ID
$customerID = getOrCreateCustomerId($conn, $accountHolder);

// Prepare and bind insert statement
$stmt = $conn->prepare("INSERT INTO Acc_network_main (customer_id, account_holder, product_category, amount_used, amount_paid, transaction_id) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssdss", $customerID, $accountHolder, $productCategory, $amountUsed, $amountPaid, $transactionID);
$stmt->execute();

// Update aggregate values after insert
updateAggregates($conn, $customerID);

echo "New records created successfully";

$stmt->close();
$conn->close();

function getOrCreateCustomerId($conn, $accountHolder) {
    $stmt = $conn->prepare("SELECT customer_id FROM acc_network_main WHERE account_holder = ? LIMIT 1");
    $stmt->bind_param("s", $accountHolder);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['customer_id'];  // Return existing customer ID
    } else {
        // If no existing customer ID, generate a new one
        return generateCustomerId($conn, $accountHolder);
    }
}

function generateCustomerId($conn, $accountHolder) {
    $newId = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 5);
    return $newId;  // Return the new ID
}

function updateAggregates($conn, $customerID) {
    // Update customer-specific aggregates
    $sql = "SELECT SUM(amount_used) AS total_used, SUM(amount_paid) AS total_paid FROM acc_network_main WHERE customer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $customerID);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    $totalUsed = $data['total_used'];
    $totalPaid = $data['total_paid'];
    $debtDue = $totalUsed - $totalPaid;

    // Update the total and debt due for specific customer
    $updateSql = "UPDATE acc_network_main SET net_amount_used_from_customer = ?, net_amount_paid_to_customer = ?, total_debt_due_to_customer = ? WHERE customer_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("ddds", $totalUsed, $totalPaid, $debtDue, $customerID);
    $updateStmt->execute();

    // Global aggregates (for all customers)
    $globalSql = "SELECT SUM(amount_used) AS global_used, SUM(amount_paid) AS global_paid FROM acc_network_main";
    $globalResult = $conn->query($globalSql);
    $globalData = $globalResult->fetch_assoc();

    $globalUsed = $globalData['global_used'];
    $globalPaid = $globalData['global_paid'];
    $globalDebtDue = $globalUsed - $globalPaid;

    // Update the totals for all entries
    $updateGlobalSql = "UPDATE acc_network_main SET net_amount_used = ?, net_amount_paid = ?, total_debt_due = ?";
    $updateGlobalStmt = $conn->prepare($updateGlobalSql);
    $updateGlobalStmt->bind_param("ddd", $globalUsed, $globalPaid, $globalDebtDue);
    $updateGlobalStmt->execute();
}
?>
