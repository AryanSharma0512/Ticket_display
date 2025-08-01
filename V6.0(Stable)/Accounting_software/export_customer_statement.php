<?php
require_once 'db_config.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Validate GET parameters
if (!isset($_GET['customerID']) || empty($_GET['customerID'])) {
    echo "Customer ID is required";
    exit;
}

$customerID = $_GET['customerID'];
$fromDate = $_GET['fromDate'] ?? '';
$toDate = $_GET['toDate'] ?? '';
$unsettledOnly = isset($_GET['unsettledOnly']) && $_GET['unsettledOnly'] == "1";

if ($fromDate === '') {
    $stmt = $conn->prepare("SELECT entry_date FROM main_table WHERE customer_id = ? ORDER BY entry_date ASC LIMIT 1");
    $stmt->bind_param('s', $customerID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $fromDate = $row['entry_date'];
    }
    $stmt->close();
}

if ($toDate === '') {
    $stmt = $conn->prepare("SELECT entry_date FROM main_table WHERE customer_id = ? ORDER BY entry_date DESC LIMIT 1");
    $stmt->bind_param('s', $customerID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $toDate = $row['entry_date'];
    }
    $stmt->close();
}

if ($fromDate === '' || $toDate === '') {
    die('No transactions found for this customer');
}

$fromDateFormatted = date('d-m-Y', strtotime($fromDate));
$toDateFormatted = date('d-m-Y', strtotime($toDate));

// Fetch customer name
$sqlCustomer = "SELECT customer_name FROM main_table WHERE customer_id = ? LIMIT 1";
$stmtCustomer = $conn->prepare($sqlCustomer);
$stmtCustomer->bind_param("s", $customerID);
$stmtCustomer->execute();
$resultCustomer = $stmtCustomer->get_result();
$customerName = "Unknown Customer";

if ($row = $resultCustomer->fetch_assoc()) {
    $customerName = $row['customer_name'];
}
$stmtCustomer->close();

// Fetch transactions
$sql = "SELECT transaction_id, product, price, quantity, bill_amount, amount_received, entry_date
        FROM main_table WHERE customer_id = ? AND entry_date BETWEEN ? AND ? ORDER BY entry_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $customerID, $fromDate, $toDate);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

// 🚀 **Apply the Running Balance Logic (Unsettled Transactions Filtering)**
if ($unsettledOnly && !empty($transactions)) {
    $runningBalance = 0.0;
    $lastSettledIndex = -1;

    foreach ($transactions as $index => $transaction) {
        $bill = (float)$transaction['bill_amount'];
        $paid = (float)$transaction['amount_received'];

        $runningBalance += ($bill - $paid);

        if (abs($runningBalance) < 0.01) {
            $lastSettledIndex = $index;
        }
    }

    if ($lastSettledIndex === (count($transactions) - 1)) {
        $transactions = [];
    } else {
        $transactions = array_slice($transactions, $lastSettledIndex + 1);
    }
}

// Calculate Due Amount Dynamically
$sqlDue = "SELECT SUM(bill_amount) - SUM(amount_received) AS due_from_customer 
           FROM main_table WHERE customer_id = ?";
$stmtDue = $conn->prepare($sqlDue);
$stmtDue->bind_param("s", $customerID);
$stmtDue->execute();
$resultDue = $stmtDue->get_result();
$dueAmount = 0;

if ($row = $resultDue->fetch_assoc()) {
    $dueAmount = $row['due_from_customer'] ? floatval($row['due_from_customer']) : 0;
}
$stmtDue->close();

// Generate PDF
require('fpdf/fpdf.php');

class PDF extends FPDF {
    function Header() {
        global $customerName, $customerID, $fromDateFormatted, $toDateFormatted;
        $this->SetFont('Arial', 'B', 14);
        $this->SetFillColor(50, 50, 50);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(275, 12, "Customer Statement - $customerName (ID: $customerID)", 0, 1, 'C', true);
        $this->Ln(6);
        $this->SetFont('Arial','',11);
        $this->Cell(275, 8, "From: $fromDateFormatted To: $toDateFormatted", 0, 1, 'C', true);
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' | ' . utf8_decode('© Shree Dhanlaxmi Travels 2025. Travel with love and convenience'), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage('L', 'A4');
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(0, 102, 204);
$pdf->SetTextColor(255, 255, 255);

// Table headers
$headers = ["#", "Transaction ID", "Product", "Price (INR)", "Qty", "Bill Amount (INR)", "Amount Received (INR)", "Entry Date"];
$widths = [10, 40, 50, 30, 15, 40, 40, 50];

foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 8, $header, 1, 0, 'C', true);
}
$pdf->Ln();

// Table body with alternating row colors (Light Blue and White)
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 0);
$index = 1;
$fill = false; // Toggle for alternating row colors
$pdf->SetFillColor(230, 242, 255); // Light blue background color

foreach ($transactions as $row) {
    $pdf->Cell($widths[0], 8, $index++, 1, 0, 'C', $fill);
    $pdf->Cell($widths[1], 8, $row['transaction_id'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[2], 8, $row['product'], 1, 0, 'L', $fill);
    $pdf->Cell($widths[3], 8, number_format($row['price'], 2), 1, 0, 'R', $fill);
    $pdf->Cell($widths[4], 8, $row['quantity'], 1, 0, 'R', $fill);
    $pdf->Cell($widths[5], 8, number_format($row['bill_amount'], 2), 1, 0, 'R', $fill);
    $pdf->Cell($widths[6], 8, number_format($row['amount_received'], 2), 1, 0, 'R', $fill);
    $pdf->Cell($widths[7], 8, $row['entry_date'], 1, 0, 'C', $fill);
    $pdf->Ln();
    
    $fill = !$fill; // Toggle fill color for next row
}

// Final Due Amount Row (Yellow Highlight)
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(255, 204, 0);
$pdf->Cell(array_sum($widths) - $widths[7], 10, "Due from Customer:", 1, 0, 'R', true);
$pdf->Cell($widths[7], 10, number_format($dueAmount, 2) . " INR", 1, 1, 'C', true);

// Force download the PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Customer_Statement.pdf"');
$pdf->Output('D', 'Customer_Statement.pdf');
$conn->close();