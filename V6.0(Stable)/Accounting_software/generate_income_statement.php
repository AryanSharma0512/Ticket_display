<?php
include_once(__DIR__ . '/db_config.php'); // Include database configuration

try {
    // Connect to the database
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Fetch data for the Income Statement
    $sql = "
        SELECT 
            SUM(bill_amount) AS net_sales,         -- Total sales
            SUM(our_cost) AS cost_of_sales,        -- Total cost incurred
            SUM(profit) AS gross_profit,           -- Total profit
            SUM(amount_received) AS amount_received -- Total payments collected
        FROM Main_table
    ";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Calculations for Income Statement
        $net_sales = $row['net_sales'] ?? 0;
        $cost_of_sales = $row['cost_of_sales'] ?? 0;
        $gross_profit = $net_sales - $cost_of_sales;
        $amount_received = $row['amount_received'] ?? 0;
        $net_income = $row['gross_profit'] ?? 0;

        // Generate Income Statement
        echo "
        <h2>Income Statement</h2>
        <p>For the Year Ended " . date('F d, Y') . "</p>
        <table border='1' style='width: 100%; text-align: right;'>
            <tr><td style='text-align: left;'>Net Sales</td><td>₹ " . number_format($net_sales, 2) . "</td></tr>
            <tr><td style='text-align: left;'>Cost of Sales</td><td>₹ " . number_format($cost_of_sales, 2) . "</td></tr>
            <tr><td style='text-align: left;'><strong>Gross Profit</strong></td><td><strong>₹ " . number_format($gross_profit, 2) . "</strong></td></tr>
            <tr><td style='text-align: left;'>Amount Received</td><td>₹ " . number_format($amount_received, 2) . "</td></tr>
            <tr><td style='text-align: left;'><strong>Net Income</strong></td><td><strong>₹ " . number_format($net_income, 2) . "</strong></td></tr>
        </table>";
    } else {
        echo "<p>No data available for the Income Statement.</p>";
    }
} catch (Exception $e) {
    echo "Error generating Income Statement: " . $e->getMessage();
} finally {
    $conn->close();
}
?>
