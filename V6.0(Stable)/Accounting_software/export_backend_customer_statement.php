<?php
require_once __DIR__ . '/db_config_acc.php';

if (!isset($_GET['accountHolder']) || trim($_GET['accountHolder']) === '') {
    echo 'Account holder name is required';
    exit;
}
$accountHolder = trim($_GET['accountHolder']);

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT transaction_id, product_category, amount_used, amount_paid, entry_date FROM acc_network_main WHERE account_holder = ? ORDER BY entry_date ASC");
$stmt->bind_param('s', $accountHolder);
$stmt->execute();
$result = $stmt->get_result();
$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

$stmt = $conn->prepare("SELECT SUM(amount_used) AS total_used, SUM(amount_paid) AS total_paid FROM acc_network_main WHERE account_holder = ?");
$stmt->bind_param('s', $accountHolder);
$stmt->execute();
$totRes = $stmt->get_result();
$totalUsed = 0;
$totalPaid = 0;
if ($row = $totRes->fetch_assoc()) {
    $totalUsed = $row['total_used'] ?? 0;
    $totalPaid = $row['total_paid'] ?? 0;
}
$stmt->close();

require 'fpdf/fpdf.php';

class PDF extends FPDF {
    function Header() {
        global $accountHolder;
        $this->SetFont('Arial','B',14);
        $this->SetFillColor(50,50,50);
        $this->SetTextColor(255,255,255);
        $this->Cell(275,12,"Account Statement - $accountHolder",0,1,'C',true);
        $this->Ln(8);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo(),0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage('L','A4');
$pdf->SetFont('Arial','B',9);
$pdf->SetFillColor(0,102,204);
$pdf->SetTextColor(255);

$headers = ['#','Transaction ID','Category','Amount Used (INR)','Amount Paid (INR)','Entry Date','Balance (INR)'];
$widths = [10,40,45,40,40,50,40];
foreach ($headers as $i => $h) {
    $pdf->Cell($widths[$i],8,$h,1,0,'C',true);
}
$pdf->Ln();

$pdf->SetFont('Arial','',9);
$pdf->SetTextColor(0);
$balance = 0;
$index = 1;
foreach ($transactions as $tx) {
    $used = (float)$tx['amount_used'];
    $paid = (float)$tx['amount_paid'];
    $balance += $used - $paid;
    $pdf->Cell($widths[0],8,$index++,1,0,'C');
    $pdf->Cell($widths[1],8,$tx['transaction_id'],1,0,'L');
    $pdf->Cell($widths[2],8,$tx['product_category'],1,0,'L');
    $pdf->Cell($widths[3],8,number_format($used,2),1,0,'R');
    $pdf->Cell($widths[4],8,number_format($paid,2),1,0,'R');
    $pdf->Cell($widths[5],8,$tx['entry_date'],1,0,'C');
    $pdf->Cell($widths[6],8,number_format($balance,2),1,0,'R');
    $pdf->Ln();
}

$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(255,204,0);
$pdf->Cell($widths[0]+$widths[1]+$widths[2],10,'Totals',1,0,'R',true);
$pdf->Cell($widths[3],10,number_format($totalUsed,2),1,0,'R',true);
$pdf->Cell($widths[4],10,number_format($totalPaid,2),1,0,'R',true);
$pdf->Cell($widths[5],10,'',1,0,'R',true);
$pdf->Cell($widths[6],10,number_format($totalUsed-$totalPaid,2),1,1,'R',true);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Backend_Customer_Statement.pdf"');
$pdf->Output('D','Backend_Customer_Statement.pdf');
$conn->close();
