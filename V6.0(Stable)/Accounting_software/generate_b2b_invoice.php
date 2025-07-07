<?php
require 'fpdf/fpdf.php';
include 'db_config_taxes.php';

// Connect to taxes database
$conn = new mysqli($tax_db_servername, $tax_db_username, $tax_db_password, $tax_db_name);
if ($conn->connect_error) {
    die("Taxes DB connection failed: " . $conn->connect_error);
}

// Accept txn via ?txn= or ?transaction_id=
$txn = $_GET['txn'] ?? $_GET['transaction_id'] ?? '';
if (!$txn) {
    die("No invoice specified");
}

$stmt = $conn->prepare("SELECT * FROM B2B WHERE invoice_number = ?");
$stmt->bind_param('s', $txn);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if(!$data){
    die("Invoice not found");
}

$fare = floatval($data['fare_charges']);
$serviceFee = floatval($data['service_fee']);
$discount = floatval($data['effective_discount']);
$igst = floatval($data['igst']);
$grandTotal = $fare + $serviceFee - $discount + $igst;

class PDF extends FPDF {
    function Footer() {
        $this->SetY(-20);
        $this->SetFont('Arial','B',7);
        $this->SetTextColor(0,0,0);
        $this->Cell(100,4,'Registered Office:',0,0,'L');
        $this->SetFont('Arial','I',7);
        $this->Cell(0,4,'Page '.$this->PageNo().' of {nb}',0,1,'R');
        $this->SetFont('Arial','',7);
        $this->SetTextColor(128,128,128);
        $this->Cell(100,4,'Shree Dhanlaxmi Travels, A1-151, Darshanam Greens, Waghodia Dabhoi Ring Road, Vadodara, Gujarat - 390025',0,0,'L');
        $this->Cell(0,4,'',0,1,'R');
    }
    function SetDash($black=null,$white=null){
        if($black!==null && $white!==null){
            $s=sprintf('[%.3F %.3F] 0 d',$black*$this->k,$white*$this->k);
        }else{
            $s='[] 0 d';
        }
        $this->_out($s);
    }
    function RoundRect($x,$y,$w,$h,$r,$style='D'){
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
    function _Arc($x1,$y1,$x2,$y2,$x3,$y3){
        $h=$this->h; $k=$this->k;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',$x1*$k,($h-$y1)*$k,$x2*$k,($h-$y2)*$k,$x3*$k,($h-$y3)*$k));
    }
}

$pdf = new PDF('P','mm','A4');
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true,15);
$pdf->AddPage();

$pdf->Image("C:/Users/aryan/OneDrive/Desktop/Tickets frontend/elements/header logo.png",10.3,5.5,30);
$pdf->SetFont('Arial','B',20);
$pdf->Cell(0,12,'TAX INVOICE',0,1,'C');
$pdf->SetFont('Arial','',10);
$pdf->SetXY(150,15);
$pdf->MultiCell(50,5,"Shree Dhanlaxmi Travels\nA1-151, Darshanam Greens,\nWaghodia Dabhoi Ring Road,\nVadodara, Gujarat - 390025");

$leftLabels=[
    'Invoice No:',
    'Pan:',
    'GSTIN:',
    'Booking ID:',
    'HSN/SAC:',
    'Service Description:',
    'Customer Name:'
];
$leftValues=[
    $data['invoice_number'],
    'DVWPS5495B',
    '24DVWPS5495B1ZW',
    $data['booking_id'],
    $data['hsn_sac_code'],
    $data['service_description'],
    $data['customer_name']
];
$rightLabels=['Date:','Place of Supply:','Type/Category:','Txn Details:','Tax Payable under RCM:','CIN:'];
$rightValues=[
    date('d-M-Y',strtotime($data['date'])),
    $data['place_of_supply'],
    $data['type_or_category'],
    $data['txn_details'],
    $data['tax_payable_under_rcm'],
    $data['cin']
];

$infoY=$pdf->GetY();
$h=6; $off=4+$h;
$pdf->SetXY(10,$infoY+4);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(40,$h,$leftLabels[0],0,0,'L');
$pdf->SetFont('Arial','',10);
$pdf->Cell(50,$h,$leftValues[0],0,0,'L');
$pdf->SetDrawColor(0,0,0); $pdf->SetLineWidth(0.6);
$pdf->Line(10,$infoY+4+$h,200,$infoY+4+$h);
for($i=1;$i<count($leftLabels);$i++){
    $y=$infoY+4+$i*$h;
    $pdf->SetXY(10,$y);
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(40,$h,$leftLabels[$i],0,0,'L');
    $pdf->SetFont('Arial','',10);
    if($leftLabels[$i]=='Service Description:'){
        $x=10+40;
        $pdf->SetXY($x,$y);
        $pdf->MultiCell(50,$h/2,$leftValues[$i],0,'L');
    }else{
        $pdf->Cell(50,$h,$leftValues[$i],0,0,'L');
    }
}
for($i=0;$i<count($rightLabels);$i++){
    $pdf->SetXY(110,$infoY+$i*$h+$off);
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(40,$h,$rightLabels[$i],0,0,'L');
    $pdf->SetFont('Arial','',10);
    $pdf->SetX(110+40+5);
    $pdf->Cell(40,$h,$rightValues[$i],0,0,'L');
}
$pdf->SetY($infoY+max(count($leftLabels)-1,count($rightLabels))*$h+$off+4);
$pdf->SetDrawColor(128,128,128);
$pdf->SetLineWidth(0.2);
$pdf->SetDash(1,1);
$lineY=$pdf->GetY();
$pdf->Line(10,$lineY,200,$lineY);
$pdf->SetDash();
$pdf->Ln(4);

$sy=$pdf->GetY();
$flightDate=date('d-M-Y',strtotime($data['flight_date']));
$flightNumber=$data['flight_number'];
$product=$data['from_location'].' - '.$data['to_location'];
$pdf->SetFont('Arial','I',8);
$pdf->SetTextColor(128,128,128);
$pdf->SetXY(10,$sy-4);
$pdf->Cell(130,4,$product.' (Date of Flight: '.$flightDate.')',0,0,'L');
$pdf->Cell(60,4,'Flight No: '.$flightNumber,0,1,'R');
$pdf->SetTextColor(0,0,0);

$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(240,240,240);
$cw=[128,35,27];
$pdf->SetXY(10,$sy);
$headers=['Passenger Name(s)','E-ticket No.','PNR'];
foreach($headers as $i=>$t){
    $pdf->Cell($cw[$i],8,$t,1,($i==count($headers)-1?1:0),'C',true);
}
$pdf->SetFont('Arial','',10);
$y=$sy+8;
$names=array_map('trim',explode(',',$data['passenger_name']));
$eticks=array_map('trim',explode(',',$data['e_ticket']));
$pnrs=array_map('trim',explode(',',$data['pnr']));
$cnt=max(count($names),count($eticks),count($pnrs));
for($i=0;$i<$cnt;$i++){
    $name=$names[$i]??'';
    $et=$eticks[$i]??'';
    $pn=$pnrs[$i]??'';
    $pdf->SetXY(10,$y);
    $pdf->Cell($cw[0],6,$name,1,0,'C');
    $pdf->Cell($cw[1],6,$et,1,0,'C');
    $pdf->Cell($cw[2],6,$pn,1,1,'C');
    $y+=6;
}
$pdf->Ln(4);
$pdf->SetDrawColor(128,128,128);
$pdf->SetLineWidth(0.2);
$pdf->SetDash(1,1);
$cy=$pdf->GetY();
$pdf->Line(10,$cy,200,$cy);
$pdf->SetDash();
$pdf->Ln(4);

$py=$pdf->GetY();
$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(240,240,240);
$pdf->SetXY(10,$py);
$pdf->Cell(150,8,'PAYMENT BREAKUP',1,0,'C',true);
$pdf->Cell(40,8,'',1,1,'C',true);
$pdf->SetFont('Arial','',10);
$pdf->Cell(150,6,'Fare Charges (inclusive of applicable taxes)',1,0,'L');
$pdf->Cell(40,6,'INR '.number_format($fare,2),1,1,'R');
$pdf->Cell(150,6,'Service Fees',1,0,'L');
$pdf->Cell(40,6,'INR '.number_format($serviceFee,2),1,1,'R');
$pdf->Cell(150,6,'Effective Discount',1,0,'L');
$pdf->Cell(40,6,'INR '.number_format($discount,2),1,1,'R');
$pdf->Cell(150,6,'IGST',1,0,'L');
$pdf->Cell(40,6,'INR '.number_format($igst,2),1,1,'R');
$pdf->SetFont('Arial','B',11);
$pdf->Cell(150,8,'Grand Total',1,0,'L');
$pdf->Cell(40,8,'INR '.number_format($grandTotal,2),1,1,'R');

$pdf->Ln(4);
$pdf->SetFont('Arial','',9);
$pdf->SetXY(10,$pdf->GetY());
$pdf->MultiCell(90,4,"Business Name: {$data['business_name']}\n{$data['business_address']}\nGSTIN: {$data['business_gstin']}");
$pdf->Ln(2);

$pdf->SetFont('Arial','I',8);
$pdf->MultiCell(0,4,"Input tax credit of GST charged by the original service provider is available only against the invoice issued by the respective service provider. Shree Dhanlaxmi Travels acts only as a facilitator for these services.\n(This is not a valid travel document)",0,'L');
$pdf->Ln(4);
$pdf->SetFont('Arial','',7);
$pdf->Cell(0,4,'Terms & Conditions:',0,1);
$pdf->MultiCell(0,4,"1. Any dispute with respect to the invoice to be reported to Shree Dhanlaxmi Travels within 48 hours of receipt of invoice.\n2. This is system generated invoice and does not require signatures.",0,'L');
$pdf->Ln(4);
$stampX=150; $stampY=$pdf->GetY();
$pdf->SetXY($stampX,$stampY);
$path="C:/Users/aryan/OneDrive/Desktop/Tickets frontend/elements/InvoiceStamp.png";
if(file_exists($path)) $pdf->Image($path,$stampX,$stampY,35,35);
$pdf->SetLineWidth(0.2);
$pdf->Line($stampX,$stampY,$stampX+35,$stampY);
$pdf->Line($stampX,$stampY,$stampX,$stampY+35);
$pdf->Line($stampX+35,$stampY,$stampX+35,$stampY+35);
$bottomY=$stampY+35; $label='Stamp';
$pdf->SetFont('Arial','',8);
$lw=$pdf->GetStringWidth($label); $gp=2; $gw=$lw+2*$gp; $gs=$stampX+(35-$gw)/2; $ge=$gs+$gw;
$pdf->Line($stampX,$bottomY,$gs,$bottomY);
$pdf->Line($ge,$bottomY,$stampX+35,$bottomY);
$pdf->SetXY($gs,$bottomY-4);
$pdf->Cell($gw,8,$label,0,0,'C');

$pdf->Output('I',"b2b_invoice_{$data['invoice_number']}.pdf");
exit;
?>
