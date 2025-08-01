<?php
require_once 'db_config.php';

// Validate GET parameters first to avoid notices that corrupt PDF output
if (!isset($_GET['customerID']) || trim($_GET['customerID']) === '') {
    echo 'Customer ID is required';
    exit;
}

$customerID    = trim($_GET['customerID']);
$fromDate       = $_GET['fromDate'] ?? '';
$toDate         = $_GET['toDate'] ?? '';
$unsettledOnly  = isset($_GET['unsettledOnly']) && $_GET['unsettledOnly'] == '1';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Determine default date range if not supplied
if ($fromDate === '') {
    $stmt = $conn->prepare('SELECT entry_date FROM main_table WHERE customer_id = ? ORDER BY entry_date ASC LIMIT 1');
    $stmt->bind_param('s', $customerID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $fromDate = $row['entry_date'];
    }
    $stmt->close();
}

if ($toDate === '') {
    $stmt = $conn->prepare('SELECT entry_date FROM main_table WHERE customer_id = ? ORDER BY entry_date DESC LIMIT 1');
    $stmt->bind_param('s', $customerID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $toDate = $row['entry_date'];
    }
    $stmt->close();
}

if ($fromDate === '' || $toDate === '') {
    echo 'No transactions found for this customer';
    $conn->close();
    exit;
}

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
           FROM main_table WHERE customer_id = ? AND entry_date BETWEEN ? AND ?";
$stmtDue = $conn->prepare($sqlDue);
$stmtDue->bind_param("sss", $customerID, $fromDate, $toDate);
$stmtDue->execute();
$resultDue = $stmtDue->get_result();
$dueAmount = 0;

if ($row = $resultDue->fetch_assoc()) {
    $dueAmount = $row['due_from_customer'] ? floatval($row['due_from_customer']) : 0;
}
$stmtDue->close();

// Generate PDF
require('fpdf/fpdf.php');

// Format dates for the header similar to the backend version
$fromDateFormatted = date('d-m-Y', strtotime($fromDate));
$toDateFormatted   = date('d-m-Y', strtotime($toDate));

// Column widths used by the PDF table
$widths = [10, 40, 50, 30, 15, 40, 40, 50];

// Helper to format numbers in the Indian style
function formatIndian($num) {
    $negative = $num < 0 ? '-' : '';
    $num = abs($num);
    $parts = explode('.', number_format($num, 2, '.', ''));
    $int = $parts[0];
    $dec = $parts[1];
    $last3 = substr($int, -3);
    $rest = substr($int, 0, -3);
    if ($rest !== '') {
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $int = $rest . ',' . $last3;
    }
    return $negative . $int . '.' . $dec;
}

class PDF extends FPDF {
    function Header() {
        global $customerName, $customerID, $fromDateFormatted, $toDateFormatted, $widths;
        if ($this->PageNo() == 1) {
            $this->SetFillColor(200, 200, 200);
            $this->SetTextColor(0);
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 10, 'Customer Records', 0, 1, 'R', true);
            $this->Ln(6);

            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 8, "Customer Statement for $customerName (ID: $customerID)", 0, 1, 'L');
            $this->SetFont('Arial', '', 12);
            $this->Cell(0, 8, "From: $fromDateFormatted To: $toDateFormatted", 0, 1, 'L');
            $this->Ln(4);
        }

        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(0, 102, 204);
        $this->SetTextColor(255);
        $headers = ['#', 'Transaction ID', 'Product', 'Price (INR)', 'Qty', 'Bill Amount (INR)', 'Amount Received (INR)', 'Entry Date'];
        foreach ($headers as $i => $h) {
            $this->Cell($widths[$i], 8, $h, 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(0);
    }

    function Footer() {
        $this->SetY(-18);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, 'Page ' . $this->PageNo(), 0, 1, 'C');
        $this->Cell(0, 5, utf8_decode('© Shree Dhanlaxmi Travels 2025. Travel with love and convenience'), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage('L', 'A4');
$pdf->SetFillColor(230, 242, 255); // light blue for rows

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
    $pdf->Cell($widths[3], 8, formatIndian($row['price']), 1, 0, 'R', $fill);
    $pdf->Cell($widths[4], 8, $row['quantity'], 1, 0, 'R', $fill);
    $pdf->Cell($widths[5], 8, formatIndian($row['bill_amount']), 1, 0, 'R', $fill);
    $pdf->Cell($widths[6], 8, formatIndian($row['amount_received']), 1, 0, 'R', $fill);
    $pdf->Cell($widths[7], 8, $row['entry_date'], 1, 0, 'C', $fill);
    $pdf->Ln();
    
    $fill = !$fill; // Toggle fill color for next row
}

// Final Due Amount Row (Yellow Highlight)
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(255, 204, 0);
$pdf->Cell(array_sum($widths) - $widths[7], 10, 'Due from Customer:', 1, 0, 'R', true);
$pdf->Cell($widths[7], 10, formatIndian($dueAmount) . ' INR', 1, 1, 'C', true);

// Output PDF inline so it opens in the browser
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Customer_Statement.pdf"');
$pdf->Output('I', 'Customer_Statement.pdf');
$conn->close();
exit;
