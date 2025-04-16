<?php
include_once(__DIR__ . '/db_config.php'); // Include database configuration

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="Income_Statement_' . date('Ymd') . '.csv"');

$output = fopen('php://output', 'w');

// Write the header row
fputcsv($output, ['Field', 'Amount (INR)']);

try {
    // Connect to the database
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Fetch data for the Income Statement
    $sql = "
        SELECT 
            SUM(bill_amount) AS net_sales,
            SUM(our_cost) AS cost_of_sales,
            SUM(profit) AS gross_profit,
            SUM(amount_received) AS amount_received
        FROM Main_table
    ";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    // Write the data rows
    fputcsv($output, ['Net Sales', $row['net_sales'] ?? 0]);
    fputcsv($output, ['Cost of Sales', $row['cost_of_sales'] ?? 0]);
    fputcsv($output, ['Gross Profit', ($row['net_sales'] - $row['cost_of_sales']) ?? 0]);
    fputcsv($output, ['Amount Received', $row['amount_received'] ?? 0]);
    fputcsv($output, ['Net Income', $row['gross_profit'] ?? 0]);
} catch (Exception $e) {
    fputcsv($output, ['Error', $e->getMessage()]);
} finally {
    fclose($output);
    $conn->close();
}
?>
