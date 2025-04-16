<?php
require('fpdf/fpdf.php');
include_once(__DIR__ . '/db_config.php'); // Include database configuration

try {
    // Connect to the database
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Fetch outstanding payments data
    $sql = "
        SELECT 
            customer_id, 
            customer_name, 
            SUM(bill_amount) AS total_bill_amount,
            SUM(amount_received) AS total_amount_received,
            SUM(bill_amount) - SUM(amount_received) AS outstanding_amount
        FROM Main_table 
        GROUP BY customer_id, customer_name 
        HAVING outstanding_amount > 0
    ";
    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        throw new Exception("No outstanding payments found.");
    }

    // Create a PDF instance
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);

    // Header
    $pdf->Cell(190, 10, 'Outstanding Payments Report', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(190, 10, 'Generated on ' . date('F d, Y'), 0, 1, 'C');
    $pdf->Ln(10);

    // Table Header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 10, 'Customer ID', 1, 0, 'C');
    $pdf->Cell(55, 10, 'Customer Name', 1, 0, 'C');
    $pdf->Cell(35, 10, 'Total Bill (INR)', 1, 0, 'C');
    $pdf->Cell(35, 10, 'Paid (INR)', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Outstanding (INR)', 1, 1, 'C');

    $pdf->SetFont('Arial', '', 10);

    // Populate table with data
    $totalOutstanding = 0;
    while ($row = $result->fetch_assoc()) {
        $customerID = $row['customer_id'];
        $customerName = $row['customer_name'];
        $totalBill = number_format($row['total_bill_amount'], 2);
        $totalPaid = number_format($row['total_amount_received'], 2);
        $outstandingAmount = number_format($row['outstanding_amount'], 2);

        // Adjust column widths to fit within the page
        $pdf->Cell(35, 10, $customerID, 1, 0, 'C');
        $pdf->Cell(55, 10, $customerName, 1, 0, 'C');
        $pdf->Cell(35, 10, $totalBill, 1, 0, 'R');
        $pdf->Cell(35, 10, $totalPaid, 1, 0, 'R');
        $pdf->Cell(30, 10, $outstandingAmount, 1, 1, 'R');

        $totalOutstanding += (float) $row['outstanding_amount'];
    }

    // Total Outstanding
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(160, 10, 'Total Outstanding Amount', 1, 0, 'R');
    $pdf->Cell(30, 10, 'INR ' . number_format($totalOutstanding, 2), 1, 1, 'R');

    // Output PDF
    $pdf->Output('D', 'Outstanding_Payments_' . date('Ymd') . '.pdf');
} catch (Exception $e) {
    echo "Error generating PDF: " . $e->getMessage();
} finally {
    $conn->close();
}
?>
