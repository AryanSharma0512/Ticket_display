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

$stmt = $conn->prepare("SELECT transaction_id, product_category, amount_used, amount_paid, entry_date, channel FROM acc_network_main WHERE account_holder = ? AND entry_date BETWEEN ? AND ? ORDER BY entry_date ASC");
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
$fromDateFormatted = date('d-m-Y', strtotime($fromDate));
$toDateFormatted = date('d-m-Y', strtotime($toDate));
$openingDateFormatted = $fromDateFormatted;
$widths = [10,26,15,30,28,28,24,116];

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

require 'fpdf/fpdf.php';

class PDF extends FPDF {
    function RoundRect($x,$y,$w,$h,$r,$style='D') {
        $k=$this->k; $hp=$this->h;
        if($style=='F') $op='f';
        elseif($style=='DF') $op='B';
        else $op='S';
        $MyArc=4/3*(sqrt(2)-1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k));
        $this->_out(sprintf('%.2F %.2F l',($x+$w-$r)*$k,($hp-$y)*$k));
        $xc=$x+$w-$r; $yc=$y+$r;
        $this->_Arc($xc+$r*$MyArc,$yc-$r,$xc+$r,$yc-$r*$MyArc,$xc+$r,$yc);
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$y-$h+$r)*$k));
        $xc=$x+$w-$r; $yc=$y+$h-$r;
        $this->_Arc($xc+$r,$yc+$r*$MyArc,$xc+$r*$MyArc,$yc+$r,$xc,$yc+$r);
        $this->_out(sprintf('%.2F %.2F l',($x+$r)*$k,($hp-$y-$h)*$k));
        $xc=$x+$r; $yc=$y+$h-$r;
        $this->_Arc($xc-$r*$MyArc,$yc+$r,$xc-$r,$yc+$r*$MyArc,$xc-$r,$yc);
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$y-$r)*$k));
        $xc=$x+$r; $yc=$y+$r;
        $this->_Arc($xc-$r,$yc-$r*$MyArc,$xc-$r*$MyArc,$yc-$r,$xc,$yc-$r);
        $this->_out($op);
    }
    function _Arc($x1,$y1,$x2,$y2,$x3,$y3) {
        $h=$this->h; $k=$this->k;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',$x1*$k,($h-$y1)*$k,$x2*$k,($h-$y2)*$k,$x3*$k,($h-$y3)*$k));
    }
    function Header() {
        global $accountHolder, $fromDateFormatted, $toDateFormatted, $openingBalance, $totalUsed, $totalPaid, $netBalance, $openingDateFormatted;
        if ($this->PageNo() == 1) {
            $this->SetFillColor(200,200,200);
            $this->SetTextColor(0);
            $this->SetFont('Arial','B',12);
            $this->Cell(0,10,'Account Records',0,1,'R',true);
            $this->Ln(6);

            $this->SetFont('Arial','B',14);
            $this->Cell(0,8,"Account Statement for $accountHolder",0,1,'L');
            $this->SetFont('Arial','',12);
            $this->Cell(0,8,"From: $fromDateFormatted To: $toDateFormatted",0,1,'L');

            $x = $this->GetX();
            $y = $this->GetY();
            $w = $this->w - 20;
            $h = 14;
            $cellW = $w / 4;
            $this->SetFillColor(245,245,245);
            $this->RoundRect($x, $y, $w, $h, 3, 'D');
            $this->SetFont('Arial','',10);
            for($i=0;$i<4;$i++) {
                $this->SetXY($x + $i*$cellW, $y+2);
                if($i==0){
                    $this->SetXY($x + $i*$cellW, $y+4);
                    $this->Cell($cellW,5,'Opening Balance: '.formatIndian($openingBalance),0,2,'C');
                    $this->SetFont('Arial','',7);
                    $this->SetTextColor(128,128,128);
                    $this->SetXY($x + $i*$cellW, $y+10);
                    $this->Cell($cellW,3,'(on '.$openingDateFormatted.')',0,0,'C');
                    $this->SetFont('Arial','',10);
                    $this->SetTextColor(0);
                }elseif($i==1){
                    $this->Cell($cellW,$h-4,'Total Debit: '.formatIndian($totalUsed),0,0,'C');
                }elseif($i==2){
                    $this->Cell($cellW,$h-4,'Total Credit: '.formatIndian($totalPaid),0,0,'C');
                }else{
                    $this->SetFont('Arial','B',12);
                    if($netBalance < 0){
                        $this->SetTextColor(0,128,0);
                    }else{
                        $this->SetTextColor(220,0,0);
                    }
                    // Display the correct relationship between Aryan and the customer
                    $remark = ($netBalance < 0)
                        ? 'ARYAN has money deposited with '.$accountHolder
                        : 'ARYAN owes money to '.$accountHolder;
                    $this->SetXY($x + $i*$cellW, $y+4);
                    $this->Cell($cellW,5,'Net Balance: '.formatIndian($netBalance),0,2,'C');
                    $this->SetFont('Arial','',7);
                    $this->SetTextColor(128,128,128);
                    $this->SetXY($x + $i*$cellW, $y+10);
                    $this->Cell($cellW,3,$remark,0,0,'C');
                    $this->SetTextColor(0);
                    $this->SetFont('Arial','',10);
                }
                if($i<3) {
                    $lineX = $x + ($i+1)*$cellW;
                    $this->SetDrawColor(211,211,211);
                    $this->Line($lineX, $y+2, $lineX, $y+$h-2);
                    $this->SetDrawColor(0);
                }
            }
            $this->Ln($h + 4);
        }

        global $widths;
        $this->SetFont('Arial','B',9);
        $this->SetFillColor(0,102,204);
        $this->SetTextColor(255);
        $headers = ['#','Entry Date','TXN ID','Category','Amount Used','Amount Paid','Balance','Remark'];
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

    // Calculate number of lines a text will occupy for a given width
    function calcLines($w, $txt) {
        $cw = $this->CurrentFont['cw'];
        if($w==0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2*$this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);
        if($nb>0 && $s[$nb-1]=="\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while($i<$nb){
            $c = $s[$i];
            if($c=="\n"){
                $i++; $sep=-1; $j=$i; $l=0; $nl++; continue;
            }
            if($c==' ') $sep=$i;
            $l += isset($cw[$c]) ? $cw[$c] : $this->GetStringWidth($c)*1000;
            if($l>$wmax){
                if($sep==-1){
                    if($i==$j) $i++;
                }else{
                    $i = $sep+1;
                }
                $sep = -1; $j=$i; $l=0; $nl++;
            }else{
                $i++;
            }
        }
        return $nl;
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage('L','A4');
$pdf->SetFont('Arial','',9);
$pdf->SetTextColor(0);
$balance = $openingBalance;
$index = 1;
$currentMonth = '';
$monthlyUsed = 0;
$monthlyPaid = 0;
$monthlyClosing = $balance;

foreach ($transactions as $tx) {
    $txMonth = date('F Y', strtotime($tx['entry_date']));
    if ($currentMonth !== '' && $txMonth !== $currentMonth) {
        $label = $currentMonth . ' Totals';
        $pdf->SetFont('Arial','B',9);
        $pdf->SetFillColor(245,245,245);
        // Use the default line width for monthly totals rows
        $pdf->SetLineWidth(0.2);
        $pdf->Cell($widths[0]+$widths[1]+$widths[2]+$widths[3],8,$label,1,0,'R',true);
        $pdf->Cell($widths[4],8,formatIndian($monthlyUsed),1,0,'R',true);
        $pdf->Cell($widths[5],8,formatIndian($monthlyPaid),1,0,'R',true);
        $pdf->Cell($widths[6],8,formatIndian($monthlyClosing),1,0,'R',true);
        $pdf->Cell($widths[7],8,'',1,1,'R',true);
        $pdf->SetLineWidth(0.2);
        $pdf->SetFont('Arial','',9);
        $pdf->SetFillColor(255);
        $monthlyUsed = 0;
        $monthlyPaid = 0;
    }
    if ($currentMonth === '' || $txMonth !== $currentMonth) {
        $currentMonth = $txMonth;
    }

    $used = (float)$tx['amount_used'];
    $paid = (float)$tx['amount_paid'];
    $balance += $used - $paid;
    $monthlyUsed += $used;
    $monthlyPaid += $paid;
    $monthlyClosing = $balance;
    $catLines = $pdf->calcLines($widths[3], $tx['product_category']);
    $remarkLines = $pdf->calcLines($widths[7], $tx['channel']);
    $rowH = max(8, $catLines * 8, $remarkLines * 8);

    $pdf->Cell($widths[0], $rowH, $index++, 1, 0, 'C');
    $pdf->Cell($widths[1], $rowH, date('d-m-Y', strtotime($tx['entry_date'])), 1, 0, 'C');
    $pdf->Cell($widths[2], $rowH, substr($tx['transaction_id'], -6), 1, 0, 'L');

    $catX = $pdf->GetX();
    $catY = $pdf->GetY();
    $pdf->MultiCell($widths[3], 8, $tx['product_category'], 0, 'L');
    $pdf->SetXY($catX + $widths[3], $catY);

    $pdf->SetFillColor(255,230,230);
    $pdf->Cell($widths[4], $rowH, formatIndian($used), 1, 0, 'R', true);
    $pdf->SetFillColor(230,255,230);
    $pdf->Cell($widths[5], $rowH, formatIndian($paid), 1, 0, 'R', true);
    $pdf->SetFillColor(255);
    $pdf->Cell($widths[6], $rowH, formatIndian($balance), 1, 0, 'R');

    $remarkX = $pdf->GetX();
    $remarkY = $pdf->GetY();
    $pdf->MultiCell($widths[7], 8, $tx['channel'], 0, 'L');
    $pdf->SetXY($remarkX + $widths[7], $remarkY);

    $pdf->SetY($catY + $rowH);
    $pdf->Rect($catX, $catY, $widths[3], $rowH);
    $pdf->Rect($remarkX, $remarkY, $widths[7], $rowH);
}

if ($currentMonth !== '') {
    $label = $currentMonth . ' Totals';
    $startX = $pdf->GetX();
    $startY = $pdf->GetY();
    $rowW = array_sum($widths);
    $pdf->SetFont('Arial','B',9);
    $pdf->SetFillColor(245,245,245);
    // Use the default line width for monthly totals rows
    $pdf->SetLineWidth(0.2);
    $pdf->Cell($widths[0]+$widths[1]+$widths[2]+$widths[3],8,$label,1,0,'R',true);
    $pdf->Cell($widths[4],8,formatIndian($monthlyUsed),1,0,'R',true);
    $pdf->Cell($widths[5],8,formatIndian($monthlyPaid),1,0,'R',true);
    $pdf->Cell($widths[6],8,formatIndian($monthlyClosing),1,0,'R',true);
    $pdf->Cell($widths[7],8,'',1,1,'R',true);
    $pdf->SetLineWidth(0.2);
    $pdf->SetFont('Arial','',9);
    $pdf->SetFillColor(255);
}

$pdf->Ln(2);
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(255,239,150);
$startX = $pdf->GetX();
$startY = $pdf->GetY();
$rowW = array_sum($widths);
$rowH = 10;
$pdf->RoundRect($startX, $startY, $rowW, $rowH, 3, 'DF');
$pdf->SetXY($startX, $startY);
$pdf->Cell($widths[0]+$widths[1]+$widths[2]+$widths[3],$rowH,'Totals',0,0,'R');
$pdf->Cell($widths[4],$rowH,formatIndian($totalUsed),0,0,'R');
$pdf->Cell($widths[5],$rowH,formatIndian($totalPaid),0,0,'R');
$pdf->Cell($widths[6],$rowH,formatIndian($netBalance),0,0,'R');
$pdf->Cell($widths[7],$rowH,'',0,1,'R');
$pdf->SetDrawColor(0);
$pdf->SetLineWidth(0.2);
$posX = $startX;
foreach ($widths as $w) {
    $posX += $w;
    $pdf->Line($posX, $startY, $posX, $startY+$rowH);
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Backend_Customer_Statement.pdf"');
$pdf->Output('D','Backend_Customer_Statement.pdf');
$conn->close();
