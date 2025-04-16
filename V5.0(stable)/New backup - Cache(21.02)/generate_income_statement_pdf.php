<?php
require('fpdf/fpdf.php');
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
            SUM(bill_amount) AS net_sales,
            SUM(our_cost) AS cost_of_sales,
            SUM(profit) AS gross_profit,
            SUM(amount_received) AS amount_received
        FROM Main_table
    ";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    // Extract data
    $net_sales = $row['net_sales'] ?? 0;
    $cost_of_sales = $row['cost_of_sales'] ?? 0;
    $gross_profit = $net_sales - $cost_of_sales;
    $amount_received = $row['amount_received'] ?? 0;
    $net_income = $row['gross_profit'] ?? 0;

    // Create a PDF instance
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);

    // Header
    $pdf->Cell(190, 10, 'Income Statement', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(190, 10, 'For the Year Ended ' . date('F d, Y'), 0, 1, 'C');
    $pdf->Ln(10);

    // Income Statement Table
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(100, 10, 'Net Sales', 1);
    $pdf->Cell(90, 10, '₹ ' . number_format($net_sales, 2), 1, 1, 'R');
    $pdf->Cell(100, 10, 'Cost of Sales', 1);
    $pdf->Cell(90, 10, '₹ ' . number_format($cost_of_sales, 2), 1, 1, 'R');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(100, 10, 'Gross Profit', 1);
    $pdf->Cell(90, 10, '₹ ' . number_format($gross_profit, 2), 1, 1, 'R');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(100, 10, 'Amount Received', 1);
    $pdf->Cell(90, 10, '₹ ' . number_format($amount_received, 2), 1, 1, 'R');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(100, 10, 'Net Income', 1);
    $pdf->Cell(90, 10, '₹ ' . number_format($net_income, 2), 1, 1, 'R');

    // Output PDF
    $pdf->Output('D', 'Income_Statement_' . date('Ymd') . '.pdf');
} catch (Exception $e) {
    echo "Error generating PDF: " . $e->getMessage();
} finally {
    $conn->close();
}
?>
