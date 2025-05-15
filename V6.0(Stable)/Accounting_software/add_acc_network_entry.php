<?php
// add_acc_network_entry.php

// 1. Include database configurations
include_once __DIR__ . '/db_config_acc.php';    // Defines:    $servername, $username, $password, $dbname (accounts_network)
// db_config_acc.php should create $accConn = new mysqli(...)
include_once __DIR__ . '/db_config_taxes.php';  // Defines:    $tax_db_servername, $tax_db_username, $tax_db_password, $tax_db_name

// 2. Connect to accounts_network
$accConn = new mysqli($servername, $username, $password, $dbname);
if ($accConn->connect_error) {
    die("Accounts-Network DB connection failed: " . $accConn->connect_error);
}

// 3. Connect to Taxes database
$taxConn = new mysqli($tax_db_servername, $tax_db_username, $tax_db_password, $tax_db_name);
if ($taxConn->connect_error) {
    die("Taxes DB connection failed: " . $taxConn->connect_error);
}

// 4. Helper: generate a new 5‑char customer ID
function generateCustomerId($conn) {
    do {
        $newId = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 5);
        $stmt = $conn->prepare("SELECT 1 FROM acc_network_main WHERE customer_id = ? LIMIT 1");
        $stmt->bind_param("s", $newId);
        $stmt->execute();
        $stmt->store_result();
    } while ($stmt->num_rows > 0);
    $stmt->close();
    return $newId;
}

// 5. Helper: get or create a customer ID for this account holder
function getOrCreateCustomerId($conn, $accountHolder) {
    $stmt = $conn->prepare("SELECT customer_id FROM acc_network_main WHERE account_holder = ? LIMIT 1");
    $stmt->bind_param("s", $accountHolder);
    $stmt->execute();
    $stmt->bind_result($existingId);
    if ($stmt->fetch()) {
        $stmt->close();
        return $existingId;
    }
    $stmt->close();
    // none found → generate
    return generateCustomerId($conn);
}

// 6. Helper: update aggregates in accounts_network
function updateCustomerAggregates($conn, $customerID) {
    $sql = "
      UPDATE acc_network_main
         SET net_amount_used_from_customer  = (SELECT SUM(amount_used) FROM acc_network_main WHERE customer_id = ?),
             net_amount_paid_to_customer  = (SELECT SUM(amount_paid) FROM acc_network_main WHERE customer_id = ?),
             total_debt_due_to_customer   = (SELECT SUM(amount_used - amount_paid) FROM acc_network_main WHERE customer_id = ?)
       WHERE customer_id = ?";
    $u = $conn->prepare($sql);
    $u->bind_param("ssss", $customerID, $customerID, $customerID, $customerID);
    $u->execute();
    $u->close();
}

// 7. Main logic: only react to POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve & sanitize inputs
    $accountHolder    = trim($_POST['accountHolder'] ?? '');
    $productCategory  = trim($_POST['productCategory'] ?? '');
    $otherCategory    = trim($_POST['otherCategory'] ?? '');
    $amountUsed       = floatval($_POST['amountUsed'] ?? 0);
    $amountPaid       = floatval($_POST['amountPaid'] ?? 0);
    $transactionID    = trim($_POST['transactionID'] ?? '');
    $makeInvoice      = isset($_POST['makeInvoice']);  // checkbox

    // If category = Others, override
    if ($productCategory === 'Others' && $otherCategory !== '') {
        $productCategory = $otherCategory;
    }

    // 8. Get or create the customer ID
    $customerID = getOrCreateCustomerId($accConn, $accountHolder);

    // 9. Insert into acc_network_main
    $ins = $accConn->prepare(
      "INSERT INTO acc_network_main
         (customer_id, account_holder, product_category, amount_used, amount_paid, transaction_id)
       VALUES (?, ?, ?, ?, ?, ?)"
    );
    $ins->bind_param(
      "sssdss",
      $customerID,
      $accountHolder,
      $productCategory,
      $amountUsed,
      $amountPaid,
      $transactionID
    );
    if (! $ins->execute()) {
        die("Error inserting acc_network_main: " . $ins->error);
    }
    $ins->close();

    // 10. Update aggregates in acc_network_main
    updateCustomerAggregates($accConn, $customerID);

    // 11. If account holder is "yes_bank" AND user checked the box → insert into Taxes.Yes_Bank_Records
    if (strcasecmp($accountHolder, 'yes_bank') === 0 && $makeInvoice) {
        // Prepare invoice fields using only the remaining columns; Product_category is removed.
        $inv = $taxConn->prepare(
          "INSERT INTO Yes_Bank_Records
             (Transaction_ID, Customer_Name, Customer_ID, Fare)
           VALUES (?, ?, ?, ?)"
        );
        $inv->bind_param(
          "sssi",
          $transactionID,
          $accountHolder,
          $customerID,
          $amountUsed
        );
        if (! $inv->execute()) {
            // Log failure but don’t kill the whole script
            error_log("Failed to insert invoice record: " . $inv->error);
        }
        $inv->close();
    }

    echo "Acc‑network entry created successfully.";
    if (strcasecmp($accountHolder, 'yes_bank') === 0 && $makeInvoice) {
        echo " Invoice record has been generated in the Taxes DB.";
    }

    // close connections
    $accConn->close();
    $taxConn->close();
    exit;
}

// 12. If not POST
http_response_code(405);
echo "Method Not Allowed";
