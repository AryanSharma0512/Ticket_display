<?php
require_once __DIR__ . '/db_config_acc.php';

if (!isset($_GET['accountHolder']) || trim($_GET['accountHolder']) === '') {
    echo 'Account holder name is required';
    exit;
}
$accountHolder = trim($_GET['accountHolder']);
$fromDate = $_GET['fromDate'] ?? '';
$toDate = $_GET['toDate'] ?? '';
$unsettledOnly = isset($_GET['unsettledOnly']) && $_GET['unsettledOnly'] == '1';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if ($fromDate === '') {
    $stmt = $conn->prepare("SELECT entry_date FROM acc_network_main WHERE account_holder = ? ORDER BY entry_date ASC LIMIT 1");
    $stmt->bind_param('s', $accountHolder);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $fromDate = $row['entry_date'];
    }
    $stmt->close();
}

if ($toDate === '') {
    $stmt = $conn->prepare("SELECT entry_date FROM acc_network_main WHERE account_holder = ? ORDER BY entry_date DESC LIMIT 1");
    $stmt->bind_param('s', $accountHolder);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $toDate = $row['entry_date'];
    }
    $stmt->close();
}

if ($fromDate === '' || $toDate === '') {
    die('No transactions found for this account holder');
}

$stmt = $conn->prepare("SELECT transaction_id, product_category, amount_used, amount_paid, entry_date FROM acc_network_main WHERE account_holder = ? AND entry_date BETWEEN ? AND ? ORDER BY entry_date ASC");
$stmt->bind_param('sss', $accountHolder, $fromDate, $toDate);
$stmt->execute();
$result = $stmt->get_result();
$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

$stmt = $conn->prepare("SELECT SUM(amount_used) AS total_used, SUM(amount_paid) AS total_paid FROM acc_network_main WHERE account_holder = ? AND entry_date BETWEEN ? AND ?");
$stmt->bind_param('sss', $accountHolder, $fromDate, $toDate);
$stmt->execute();
$totRes = $stmt->get_result();
$totalUsed = 0;
$totalPaid = 0;
if ($row = $totRes->fetch_assoc()) {
    $totalUsed = $row['total_used'] ?? 0;
    $totalPaid = $row['total_paid'] ?? 0;
}
$stmt->close();

$stmt = $conn->prepare("SELECT SUM(amount_used) AS used_before, SUM(amount_paid) AS paid_before FROM acc_network_main WHERE account_holder = ? AND entry_date < ?");
$stmt->bind_param('ss', $accountHolder, $fromDate);
$stmt->execute();
$balRes = $stmt->get_result();
$openingBalance = 0;
if ($row = $balRes->fetch_assoc()) {
    $openingBalance = ($row['used_before'] ?? 0) - ($row['paid_before'] ?? 0);
}
$stmt->close();
$netBalance = $openingBalance + $totalUsed - $totalPaid;

if ($unsettledOnly && !empty($transactions)) {
    $running = $openingBalance;
    $lastSettled = -1;
    foreach ($transactions as $idx => $tx) {
        $running += (float)$tx['amount_used'] - (float)$tx['amount_paid'];
        if (abs($running) < 0.01) {
            $lastSettled = $idx;
        }
    }
    if ($lastSettled === count($transactions) - 1) {
        $transactions = [];
        $totalUsed = 0;
        $totalPaid = 0;
        $openingBalance = 0;
        $netBalance = 0;
    } else {
        $transactions = array_slice($transactions, $lastSettled + 1);
        $totalUsed = 0;
        $totalPaid = 0;
        foreach ($transactions as $row) {
            $totalUsed += (float)$row['amount_used'];
            $totalPaid += (float)$row['amount_paid'];
        }
        $openingBalance = 0;
        $netBalance = $totalUsed - $totalPaid;
    }
}
$widths = [10,30,30,60,50,50,47];

require 'fpdf/fpdf.php';

class PDF extends FPDF {
    function Header() {
        global $accountHolder, $fromDate, $toDate, $openingBalance, $totalUsed, $totalPaid, $netBalance;
        if ($this->PageNo() == 1) {
            $this->SetFillColor(200,200,200);
            $this->SetTextColor(0);
            $this->SetFont('Arial','B',12);
            $this->Cell(0,10,'Account Records',0,1,'R',true);
            $this->Ln(6);

            $this->SetFont('Arial','B',14);
            $this->Cell(0,8,"Account Statement for $accountHolder",0,1,'L');
            $this->SetFont('Arial','',12);
            $this->Cell(0,8,"From: $fromDate To: $toDate",0,1,'L');

            $x = $this->GetX();
            $y = $this->GetY();
            $w = $this->w - 20;
            $h = 28;
            $this->SetFillColor(245,245,245);
            $this->Rect($x, $y, $w, $h, 'D');
            $this->SetXY($x+2, $y+2);
            $this->SetFont('Arial','',10);
            $this->Cell(0,6,"Opening Balance on $fromDate: ".number_format($openingBalance,2),0,1);
            $this->Cell(0,6,"Total Debit: ".number_format($totalUsed,2),0,1);
            $this->Cell(0,6,"Total Credit: ".number_format($totalPaid,2),0,1);
            $msg = $netBalance >= 0 ? "You owe $accountHolder" : "$accountHolder has deposited balance";
            $this->Cell(0,6,"Net Balance: ".number_format($netBalance,2)." - $msg",0,1);
            $this->Ln(2);
        }

        global $widths;
        $this->SetFont('Arial','B',9);
        $this->SetFillColor(0,102,204);
        $this->SetTextColor(255);
        $headers = ['#','Entry Date','TXN ID','Category','Amount Used (INR)','Amount Paid (INR)','Balance (INR)'];
        foreach ($headers as $i => $h) {
            $this->Cell($widths[$i],8,$h,1,0,'C',true);
        }
        $this->Ln();
        $this->SetFont('Arial','',9);
        $this->SetTextColor(0);
    }
    function Footer() {
        $this->SetY(-18);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,5,'Page '.$this->PageNo(),0,1,'C');
        $this->Cell(0,5,'Note: TXN IDs shown above are the last six digits of the full transaction ID.',0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage('L','A4');
$pdf->SetFont('Arial','',9);
$pdf->SetTextColor(0);
$balance = $openingBalance;
$index = 1;
foreach ($transactions as $tx) {
    $used = (float)$tx['amount_used'];
    $paid = (float)$tx['amount_paid'];
    $balance += $used - $paid;
    $pdf->Cell($widths[0],8,$index++,1,0,'C');
    $pdf->Cell($widths[1],8,date('d-m-Y', strtotime($tx['entry_date'])),1,0,'C');
    $pdf->Cell($widths[2],8,substr($tx['transaction_id'], -6),1,0,'L');
    $pdf->Cell($widths[3],8,$tx['product_category'],1,0,'L');
    $pdf->Cell($widths[4],8,number_format($used,2),1,0,'R');
    $pdf->Cell($widths[5],8,number_format($paid,2),1,0,'R');
    $pdf->Cell($widths[6],8,number_format($balance,2),1,0,'R');
    $pdf->Ln();
}

$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(255,204,0);
$pdf->Cell($widths[0]+$widths[1]+$widths[2]+$widths[3],10,'Totals',1,0,'R',true);
$pdf->Cell($widths[4],10,number_format($totalUsed,2),1,0,'R',true);
$pdf->Cell($widths[5],10,number_format($totalPaid,2),1,0,'R',true);
$pdf->Cell($widths[6],10,number_format($netBalance,2),1,1,'R',true);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Backend_Customer_Statement.pdf"');
$pdf->Output('D','Backend_Customer_Statement.pdf');
$conn->close();
