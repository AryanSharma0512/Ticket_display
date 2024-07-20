<?php

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "acc_network";

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
              <th>Amount Used</th>
              <th>Total Amount Used</th>
              <th>Amount Paid</th>
              <th>Net Amount Paid</th>
              <th>Total Amount Remaining</th>
              <th>Channel</th>
            </tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . $row["product"] . "</td>
                <td>" . $row["amount_used"] . "</td>
                <td>" . $row["net_total_used"] . "</td>
                <td>" . $row["amount_paid"] . "</td>
                <td>" . $row["net_amount_paid"] . "</td>
                <td>" . $row["total_debt_due"] . "</td>
                <td>" . $row["channel"] . "</td>
              </tr>";
    }
    echo "</table>";
} else {
    echo "No transactions found for $customer";
}

// Close database connection
$conn->close();

?>