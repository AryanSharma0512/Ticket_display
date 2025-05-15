<?php
include_once(__DIR__ . '/db_config.php'); // Include database configuration

if (isset($_GET['customer']) && !empty(trim($_GET['customer']))) {
    $customerName = trim($_GET['customer']);

    // Create a database connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // SQL query to fetch the required data for the given customer name
    $sql = "SELECT 
                customer_id, 
                customer_name, 
                product, 
                price, 
                quantity, 
                bill_amount, 
                amount_received, 
                net_total_collectable_due_from_customer, 
                entry_date, 
                our_cost, 
                channel, 
                profit, 
                net_total_profit_from_customer 
            FROM Main_table 
            WHERE customer_name = ? 
            ORDER BY customer_name ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $customerName);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Set headers for the CSV download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=account_details.csv');

            // Open the output stream
            $output = fopen('php://output', 'w');

            // Write the column headers
            fputcsv($output, [
                'Customer ID', 'Customer Name', 'Product', 'Price', 'Quantity', 
                'Bill Amount', 'Amount Received', 'Net Total Collectable Due', 
                'Entry Date', 'Our Cost', 'Channel', 'Profit', 'Net Total Profit'
            ]);

            // Write rows to the CSV
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['customer_id'], $row['customer_name'], $row['product'], $row['price'], 
                    $row['quantity'], $row['bill_amount'], $row['amount_received'], 
                    $row['net_total_collectable_due_from_customer'], $row['entry_date'], 
                    $row['our_cost'], $row['channel'], $row['profit'], $row['net_total_profit_from_customer']
                ]);
            }

            fclose($output);
        } else {
            echo "No records found for customer: $customerName.";
        }

        $stmt->close();
    } else {
        echo "Error preparing query: " . $conn->error;
    }

    $conn->close();
} else {
    echo "Please provide a valid customer name.";
}
?>
