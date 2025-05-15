<?php
include_once(__DIR__ . '/db_config.php'); // Ensure the correct path to the database configuration file

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    // Initialize variables
    $query = "";
    $params = [];

    // Determine the search criteria
    if (isset($_GET['customer_name']) && !empty(trim($_GET['customer_name']))) {
        $query = "SELECT 
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
        $params[] = trim($_GET['customer_name']);
    } elseif (isset($_GET['customer_id']) && !empty(trim($_GET['customer_id']))) {
        $query = "SELECT 
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
                WHERE customer_id = ? 
                ORDER BY customer_id ASC";
        $params[] = trim($_GET['customer_id']);
    } elseif (isset($_GET['transaction_id']) && !empty(trim($_GET['transaction_id']))) {
        $query = "SELECT 
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
                WHERE transaction_id = ? 
                ORDER BY transaction_id ASC";
        $params[] = trim($_GET['transaction_id']);
    } else {
        echo "Please provide a valid search criteria.";
        exit;
    }

    try {
        // Create a database connection
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        // Prepare and execute the SQL query
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error preparing query: " . $conn->error);
        }

        // Dynamically bind parameters
        $stmt->bind_param(str_repeat("s", count($params)), ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if rows are returned
        if ($result->num_rows > 0) {
            // Start building the HTML table
            echo "<table border='1' style='border-collapse: collapse; width: 100%; text-align: left;'>";
            echo "<tr>
                    <th>Customer ID</th>
                    <th>Customer Name</th>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Bill Amount</th>
                    <th>Amount Received</th>
                    <th>Net Total Collectable Due</th>
                    <th>Entry Date</th>
                    <th>Our Cost</th>
                    <th>Channel</th>
                    <th>Profit</th>
                    <th>Net Total Profit</th>
                  </tr>";

            // Loop through each row and display it in the table
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                        <td>{$row['customer_id']}</td>
                        <td>{$row['customer_name']}</td>
                        <td>{$row['product']}</td>
                        <td>{$row['price']}</td>
                        <td>{$row['quantity']}</td>
                        <td>{$row['bill_amount']}</td>
                        <td>{$row['amount_received']}</td>
                        <td>{$row['net_total_collectable_due_from_customer']}</td>
                        <td>{$row['entry_date']}</td>
                        <td>{$row['our_cost']}</td>
                        <td>{$row['channel']}</td>
                        <td>{$row['profit']}</td>
                        <td>{$row['net_total_profit_from_customer']}</td>
                      </tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No records found.</p>";
        }

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Invalid request method.";
}
?>
