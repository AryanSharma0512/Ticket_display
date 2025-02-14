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

// Retrieve selected customer from GET parameter
$customer = $conn->real_escape_string($_GET["customer"]);

// Fetch data from customer's table
$sql = "SELECT * FROM `$customer`";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Output data of each row
    echo "<table border='1'>
            <tr>
              <th>Product</th>
              <th>Price</th>
              <th>Quantity</th>
              <th>Bill Amount</th>
              <th>Total Bill Payable</th>
              <th>Amount Received</th>
              <th>Total Collection Due</th>
              <th>Our Cost</th>
              <th>Channel</th>
              <th>Profit</th>
              <th>Net Total Profit</th>
            </tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . $row["product"] . "</td>
                <td>" . $row["price"] . "</td>
                <td>" . $row["quantity"] . "</td>
                <td>" . $row["bill_amount"] . "</td>
                <td>" . $row["net_total_collectable"] . "</td>
                <td>" . $row["amount_received"] . "</td>
                <td>" . $row["total_collection_due"] . "</td>
                <td>" . $row["our_cost"] . "</td>
                <td>" . $row["channel"] . "</td>
                <td>" . $row["profit"] . "</td>
                <td>" . $row["net_total_profit"] . "</td>
              </tr>";
    }
    echo "</table>";
} else {
    echo "No transactions found for $customer";
}

// Close database connection
$conn->close();

?>