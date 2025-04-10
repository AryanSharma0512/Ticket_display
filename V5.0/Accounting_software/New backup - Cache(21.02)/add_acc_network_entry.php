<?php
include_once(__DIR__ . '/db_config_acc.php');  // Ensure the file path and contents are correct for database connection

// Function to get or create a customer ID
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

// Function to generate a new customer ID
function generateCustomerId($conn, $accountHolder) {
    $newId = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 5);
    return $newId;  // Return the new ID
}

// Handle POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $accountHolder = $_POST['accountHolder'];
    $productCategory = $_POST['productCategory'];
    $otherCategory = isset($_POST['otherCategory']) ? $_POST['otherCategory'] : null; // Get 'otherCategory'
    
    // Use $otherCategory if 'Others' is selected, otherwise use the original category.
    if ($productCategory === 'Others' && $otherCategory !== null) {
        $productCategory = $otherCategory;
    }
    
    $amountUsed = $_POST['amountUsed'];
    $amountPaid = $_POST['amountPaid'];
    $transactionID = $_POST['transactionID'];

    $customerID = getOrCreateCustomerId($conn, $accountHolder);

    // Prepare and bind insert statement
    $stmt = $conn->prepare("INSERT INTO Acc_network_main (customer_id, account_holder, product_category, amount_used, amount_paid, transaction_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdss", $customerID, $accountHolder, $productCategory, $amountUsed, $amountPaid, $transactionID);
    $stmt->execute();

    // Update customer-specific aggregates
    updateCustomerAggregates($conn, $customerID);

    echo "New records created successfully";
    $stmt->close();
    $conn->close();
}

// Function to update customer-specific aggregates
function updateCustomerAggregates($conn, $customerID) {
    $sql = "SELECT SUM(amount_used) AS total_used, SUM(amount_paid) AS total_paid FROM acc_network_main WHERE customer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $customerID);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    $totalUsed = $data['total_used'] ?? 0;
    $totalPaid = $data['total_paid'] ?? 0;
    $debtDue = $totalUsed - $totalPaid;

    // Update the aggregates for this customer
    $updateSql = "UPDATE acc_network_main SET net_amount_used_from_customer = ?, net_amount_paid_to_customer = ?, total_debt_due_to_customer = ? WHERE customer_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("ddds", $totalUsed, $totalPaid, $debtDue, $customerID);
    $updateStmt->execute();
}
?>
