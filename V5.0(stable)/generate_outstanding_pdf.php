<?php
require('fpdf/fpdf.php');
include_once(__DIR__ . '/db_config.php'); // Include database configuration

class PDF extends FPDF
{
    function Header()
    {
        // Header with a custom background color
        $this->SetFillColor(50, 130, 184); // Dark Blue
        $this->SetTextColor(255); // White text
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(190, 12, 'Outstanding Payments Report', 0, 1, 'C', true);
        
        // Subtitle
        $this->SetTextColor(0); // Reset text color
        $this->SetFont('Arial', '', 12);
        $this->Cell(190, 8, 'Generated on ' . date('F d, Y'), 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }
}

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
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 10);

    // Table Header
    $pdf->SetFillColor(220, 220, 220); // Light Gray
    $pdf->SetTextColor(0);
    $pdf->Cell(60, 10, 'Customer ID', 1, 0, 'C', true);
    $pdf->Cell(80, 10, 'Customer Name', 1, 0, 'C', true);
    $pdf->Cell(50, 10, 'Outstanding (INR)', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 10);

    // Populate table with data
    $totalOutstanding = 0;
    $rowColorToggle = false;
    
    while ($row = $result->fetch_assoc()) {
        $customerID = $row['customer_id'];
        $customerName = $row['customer_name'];
        $outstandingAmount = number_format($row['outstanding_amount'], 2);

        // Alternating row colors
        if ($rowColorToggle) {
            $pdf->SetFillColor(245, 245, 245); // Lighter gray
        } else {
            $pdf->SetFillColor(255, 255, 255); // White
        }
        
        $pdf->Cell(60, 10, $customerID, 1, 0, 'C', true);
        $pdf->Cell(80, 10, $customerName, 1, 0, 'C', true);
        $pdf->Cell(50, 10, $outstandingAmount, 1, 1, 'R', true);

        $totalOutstanding += (float) $row['outstanding_amount'];
        $rowColorToggle = !$rowColorToggle; // Toggle row color
    }

    // Summary section
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(50, 130, 184); // Dark Blue
    $pdf->SetTextColor(255); // White text
    $pdf->Cell(140, 12, 'Total Outstanding Amount', 1, 0, 'R', true);
    $pdf->Cell(50, 12, 'INR ' . number_format($totalOutstanding, 2), 1, 1, 'R', true);

    // Output PDF
    $pdf->Output('D', 'Outstanding_Payments_' . date('Ymd') . '.pdf');

} catch (Exception $e) {
    echo "Error generating PDF: " . $e->getMessage();
} finally {
    $conn->close();
}
?>
