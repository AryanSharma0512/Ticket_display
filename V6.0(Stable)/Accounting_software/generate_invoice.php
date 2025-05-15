<?php
// generate_invoice.php
// ———————————————————————————————————————————————————————————————————————
// FPDF‑based invoice styled after your example.
// Pulls from accounting.main_table and taxes.Yes_Bank_Records,
// computes a 5% service fee on Fare, leaves placeholders
// for logo, QR, stamp, etc. Automatically numbers pages.
// Inserts a full‑width passenger‑details box
// between the Customer Name and Payment Breakup sections.
// ———————————————————————————————————————————————————————————————————————

require 'fpdf/fpdf.php';

// 1) Load your accounting DB credentials
include 'db_config.php';           // defines $servername, $username, $password, $dbname
// 2) Load your passenger DB credentials
include 'db_config_pdetails.php';  // defines $pd_servername, $pd_username, $pd_password, $pd_dbname

// 3) Connect to accounting database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Accounting DB connection failed: " . $conn->connect_error);
}
// 4) Connect to passenger_details database
$pd_conn = new mysqli($pd_servername, $pd_username, $pd_password, $pd_dbname);
if ($pd_conn->connect_error) {
    die("Passenger_details DB connection failed: " . $pd_conn->connect_error);
}

// 5) Get the txn from query string; accept either 'txn' or 'transaction_id'
$txn = $_GET['txn'] ?? $_GET['transaction_id'] ?? '';
if (!$txn) {
    die("No txn specified");
}

// Fetch the customer_id (CIN) from the accounting main_table using the transaction id
$cin_sql = "SELECT customer_id FROM accounting.main_table WHERE transaction_id = ?";
$cin_stmt = $conn->prepare($cin_sql);
$cin_stmt->bind_param('s', $txn);
$cin_stmt->execute();
$cin_result = $cin_stmt->get_result()->fetch_assoc();
$cin = $cin_result['customer_id'] ?? '';
$cin_stmt->close();

// Connect to the accounts_network database for fetching product_category
$an_conn = new mysqli($servername, $username, $password, "accounts_network");
if ($an_conn->connect_error) {
    die("accounts_network DB connection failed: " . $an_conn->connect_error);
}

$an_sql = "SELECT product_category FROM acc_network_main WHERE transaction_id = ?";
$an_stmt = $an_conn->prepare($an_sql);
$an_stmt->bind_param('s', $txn);
$an_stmt->execute();
$an_result = $an_stmt->get_result()->fetch_assoc();
$product_category = $an_result['product_category'] ?? '';
$an_stmt->close();
$an_conn->close();

// 6) Pull master record + fare+discount from taxes.Yes_Bank_Records
$sql = "
  SELECT
    m.transaction_id,
    m.entry_date,
    m.customer_name,
    m.bill_amount,
    y.Fare,
    y.Effective_Discount,
    m.product
  FROM accounting.main_table AS m
  LEFT JOIN taxes.Yes_Bank_Records AS y
    ON y.Transaction_ID = m.transaction_id
  WHERE m.transaction_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $txn);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$data) {
    die("Transaction not found");
}

// 7) Pull the requested passenger fields for this txn including Passenger_names
$pd_sql = "
  SELECT
    Departure_date,
    Flight_number,
    Passenger_names,
    Eticket_numbers,
    PNR,
    Trip_id
  FROM passenger_details
  WHERE transaction_id = ?
";
$pd_stmt = $pd_conn->prepare($pd_sql);
$pd_stmt->bind_param('s', $txn);
$pd_stmt->execute();
$passengers = $pd_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pd_stmt->close();

// 8) Compute Service Fees with goal‑seeking for a rounded grand total & ~5% margin
$fare            = floatval($data['Fare'] ?? 0);
$discount        = floatval($data['Effective_Discount'] ?? 0);

// 1. Compute the initial service fee (ideal value)
$serviceFees0    = $fare * 5 / 95;

// 2. Compute the gross total ignoring GST (fare + service fee – discount)
$gross0          = $fare + $serviceFees0 - $discount;

// 3. Determine adjustment (x) to get a 5% margin (i.e. service fee / gross)
$x      = (0.05 * $gross0 - $serviceFees0) / 0.95;
$S_temp = $serviceFees0 + $x;

// 4. Compute a temporary grand total (including GST at 18% on service fee)
$G_temp = $fare - $discount + 1.18 * $S_temp;

// 5. Determine the desired grand total as an exact multiple of 10, and compute error
$desiredGrandTotal = round($G_temp, -1);      // exact multiple of ten
$error             = $desiredGrandTotal - $G_temp;

// 6. Adjust discount by subtracting the error (if error is positive, discount is reduced)
$discount_adj      = round($discount - $error, 2);

// 7. Recalculate the final service fee so that:
//    grandTotal = fare - adjusted discount + 1.18 * serviceFees_final equals desiredGrandTotal
$serviceFees_final = round(($desiredGrandTotal - ($fare - $discount_adj)) / 1.18, 2);
$gst               = round(0.18 * $serviceFees_final, 2);
$grandTotal        = $fare - $discount_adj + 1.18 * $serviceFees_final;

// (Optional) You can also calculate the effective margin as:
$gross_total      = $fare + $serviceFees_final - $discount_adj;
$margin           = round($serviceFees_final / $gross_total, 4);  // ideally near 0.05

// Now use $serviceFees_final as your service fee and $gst and $grandTotal as computed.

// 10) Extend FPDF to add automatic footer + RoundRect
class PDF extends FPDF {
    function Footer() {
        // Position 20mm from the bottom to allow two lines on the left.
        $this->SetY(-20);

        // Left block: Registered Office text (first line in bold)
        $this->SetFont('Arial','B',7);
        $this->SetTextColor(0,0,0);
        // Use a fixed width cell for left block (e.g., 100mm) then a cell for page numbering on the same line
        $this->Cell(100,4,'Registered Office:',0,0,'L');

        // Right block: Page numbering on the same line, right aligned.
        $this->SetFont('Arial','I',7);
        $this->SetTextColor(0,0,0);
        $this->Cell(0,4,'Page '.$this->PageNo().' of {nb}',0,1,'R');

        // Second line on the left: Display the address in small grey text.
        $this->SetFont('Arial','',7);
        $this->SetTextColor(128,128,128);
        $this->Cell(100,4,'Shree Dhanlaxmi Travels, A1-151, Darshanam Greens, Waghodia Dabhoi Ring Road, Vadodara, Gujarat - 390025',0,0,'L');

        // Ensure the right side remains clear.
        $this->Cell(0,4,'',0,1,'R');
    }
    function SetDash($black = null, $white = null) {
        if($black!==null && $white!==null) {
            $s = sprintf('[%.3F %.3F] 0 d',$black*$this->k,$white*$this->k);
        } else {
            $s = '[] 0 d';
        }
        $this->_out($s);
    }
    function RoundRect($x,$y,$w,$h,$r,$style='D') {
        $k=$this->k; $hp=$this->h;
        if($style=='F') $op='f';
        elseif($style=='DF') $op='B';
        else $op='S';
        $MyArc=4/3*(sqrt(2)-1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k));
        $this->_out(sprintf('%.2F %.2F l',($x+$w-$r)*$k,($hp-$y)*$k));
        // top-right corner
        $xc=$x+$w-$r; $yc=$y+$r;
        $this->_Arc($xc+$r*$MyArc,$yc-$r,$xc+$r,$yc-$r*$MyArc,$xc+$r,$yc);
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$y-$h+$r)*$k));
        // bottom-right
        $xc=$x+$w-$r; $yc=$y+$h-$r;
        $this->_Arc($xc+$r,$yc+$r*$MyArc,$xc+$r*$MyArc,$yc+$r,$xc,$yc+$r);
        $this->_out(sprintf('%.2F %.2F l',($x+$r)*$k,($hp-$y-$h)*$k));
        // bottom-left
        $xc=$x+$r; $yc=$y+$h-$r;
        $this->_Arc($xc-$r*$MyArc,$yc+$r,$xc-$r,$yc+$r*$MyArc,$xc-$r,$yc);
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$y-$r)*$k));
        // top-left
        $xc=$x+$r; $yc=$y+$r;
        $this->_Arc($xc-$r,$yc-$r*$MyArc,$xc-$r*$MyArc,$yc-$r,$xc,$yc-$r);
        $this->_out($op);
    }
    function _Arc($x1,$y1,$x2,$y2,$x3,$y3) {
        $h=$this->h; $k=$this->k;
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1*$k,($h-$y1)*$k,
            $x2*$k,($h-$y2)*$k,
            $x3*$k,($h-$y3)*$k
        ));
    }
}

// 11) Create PDF
$pdf=new PDF('P','mm','A4');
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true,15);
$pdf->AddPage();

// Company logo
$pdf->Image(
  "C:/Users/aryan/OneDrive/Desktop/Tickets frontend/elements/header logo.png",
  10.3,5.5,30
);

// Title & address
$pdf->SetFont('Arial','B',20);
$pdf->Cell(0,12,'TAX INVOICE',0,1,'C');
$pdf->SetFont('Arial','',10);
$pdf->SetXY(150,15);
$pdf->MultiCell(50,5,
  "Shree Dhanlaxmi Travels\n"
 ."A1-151, Darshanam Greens,\n"
 ."Waghodia Dabhoi Ring Road,\n"
 ."Vadodara, Gujarat - 390025"
);

// Info box
$leftLabels  = [
  'Invoice No:',
  'Pan:',
  'GSTIN:',
  'Booking ID:',
  'HSN/SAC:',
  'Service Description:',
  'Customer Name:'
];

$leftValues  = [
  $data['transaction_id'],
  'DVWPS5495B',
  '24DVWPS5495B1ZW',
  (!empty($passengers) && isset($passengers[0]['Trip_id'])) ? $passengers[0]['Trip_id'] : $data['transaction_id'],
  '998551',
  ($product_category == "Ticket" ? "Reservation Services For Air Transportation" : ""),
  $data['customer_name']
];

$rightLabels = ['Date:','Place of Supply:','Type/Category:','Txn Details:','Tax Payable under RCM:','CIN:'];
$rightValues = [
  date('d-M-Y', strtotime($data['entry_date'])),
  'Gujarat',
  'B2C/REG',
  'RG',
  'No',
  $cin
];

$infoY=$pdf->GetY();
$h=6; $off=4+$h;

// Invoice No above line
$pdf->SetXY(10,$infoY+4);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(40,$h,$leftLabels[0],0,0,'L');
$pdf->SetFont('Arial','',10);
$pdf->Cell(50,$h,$leftValues[0],0,0,'L');
// thick separator
$pdf->SetDrawColor(0,0,0); $pdf->SetLineWidth(0.6);
$pdf->Line(10,$infoY+4+$h,200,$infoY+4+$h);
// left col below
for($i=1;$i<count($leftLabels);$i++){
    $y = $infoY+4+$i*$h;
    $pdf->SetXY(10, $y);
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(40, $h, $leftLabels[$i], 0, 0, 'L');
    $pdf->SetFont('Arial','',10);
    // For Service Description, use MultiCell to wrap text (assuming a cell width of 50mm)
    if($leftLabels[$i] == "Service Description:"){
        // Save current X position after label (should be 10+40)
        $x = 10 + 40;
        // Set Y position to current row start
        $pdf->SetXY($x, $y);
        // Use MultiCell with line height set to half the row height so it spans about 2 lines
        $pdf->MultiCell(50, $h/2, $leftValues[$i], 0, 'L');
    } else {
        $pdf->Cell(50, $h, $leftValues[$i], 0, 0, 'L');
    }
}
// right col
for($i=0;$i<count($rightLabels);$i++){
    $pdf->SetXY(110, $infoY + $i*$h + $off);
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(40, $h, $rightLabels[$i], 0, 0, 'L');
    $pdf->SetFont('Arial','',10);
    // Shift the right value 5mm to the right by setting X before printing the value
    $pdf->SetX(110 + 40 + 5);
    $pdf->Cell(40, $h, $rightValues[$i], 0, 0, 'L');
}
$pdf->SetY($infoY+max(count($leftLabels)-1,count($rightLabels))*$h+$off+4);

// Insert grey dotted separator between (Info Box) and Product/Date of Flight line
$pdf->SetDrawColor(128,128,128);
$pdf->SetLineWidth(0.2);
$pdf->SetDash(1,1); // enable dotted line
$lineY = $pdf->GetY();
$pdf->Line(10, $lineY, 200, $lineY);
$pdf->SetDash(); // reset to solid line
$pdf->Ln(4);

// Passenger‑Details -- modified to remove the rectangular overlap
$sy = $pdf->GetY();

// Optionally, if at least one passenger exists, get flight date and flight number from the first record
if (!empty($passengers)) {
    $flightDate   = date('d-M-Y', strtotime($passengers[0]['Departure_date']));
    $flightNumber = $passengers[0]['Flight_number'];  // retrieve Flight No from passenger_details
    $product      = $data['product'] ?? '';
    
    // Set grey font for these details
    $pdf->SetFont('Arial','I',8);
    $pdf->SetTextColor(128,128,128);
    
    // Position the product and date in a fixed-width cell (left side)
    $pdf->SetXY(10, $sy - 4);
    $pdf->Cell(130,4, $product . " (Date of Flight: " . $flightDate . ")", 0, 0, 'L');
    
    // Then, in the same line, position the Flight Number in a fixed-width cell on the right
    $pdf->Cell(60,4, "Flight No: " . $flightNumber, 0, 1, 'R');
    
    $pdf->SetTextColor(0,0,0);
}

// Now set up the header row for passenger details without the date column
$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(240,240,240);
// New column widths (total = 190): Passenger Name = 128, E-ticket No. = 35, PNR = 27
$cw = [128,35,27];
$pdf->SetXY(10, $sy);
$headers = ['Passenger Name(s)', 'E-ticket No.', 'PNR'];
foreach($headers as $i=>$t){
    $pdf->Cell($cw[$i],8,$t,1, ($i==count($headers)-1 ? 1 : 0),'C',true);
}

// Print data rows without Flight No.
$pdf->SetFont('Arial','',10);
$y = $sy + 8;
foreach($passengers as $p){
    // Decode JSON-encoded passenger names and e-ticket numbers
    $names = json_decode($p['Passenger_names'], true);
    $etickets = json_decode($p['Eticket_numbers'], true);
    $pnr = $p['PNR'];
    // Ensure we have arrays. If not, make them single-element arrays.
    if(!is_array($names)) {
        $names = [$p['Passenger_names']];
    }
    if(!is_array($etickets)) {
        $etickets = [$p['Eticket_numbers']];
    }
    $count = count($names);
    for($i = 0; $i < $count; $i++){
        $name = $names[$i];
        $eticket = isset($etickets[$i]) ? $etickets[$i] : '';
        $pdf->SetXY(10, $y);
        $pdf->Cell($cw[0],6, $name,1,0,'C');
        $pdf->Cell($cw[1],6, $eticket,1,0,'C');
        $pdf->Cell($cw[2],6, $pnr,1,1,'C');
        $y += 6;
    }
}

$pdf->Ln(4);
// dashed separator
$pdf->SetDrawColor(128,128,128);
$pdf->SetLineWidth(0.2);
$pdf->SetDash(1,1);
$cy=$pdf->GetY();
$pdf->Line(10,$cy,200,$cy);
$pdf->SetDash();
$pdf->Ln(4);

// Payment‑Breakup -- modified to remove the rectangular overlap
$py=$pdf->GetY();
// header + rows
$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(240,240,240);
$pdf->SetXY(10,$py);
$pdf->Cell(150,8,'PAYMENT BREAKUP',1,0,'C',true);
$pdf->Cell(40,8,'',1,1,'C',true);
$pdf->SetFont('Arial','',10);
$pdf->Cell(150,6,'Fare Charges (inclusive of applicable taxes)',1,0,'L');
$pdf->Cell(40,6,'INR '.number_format($fare,2),1,1,'R');
$pdf->Cell(150,6,'Service Fees (5%)',1,0,'L');
$pdf->Cell(40,6,'INR '.number_format($serviceFees_final,2),1,1,'R');
$pdf->Cell(150,6,'Effective Discount',1,0,'L');
$pdf->Cell(40,6,'INR '.number_format($discount_adj,2),1,1,'R');
$pdf->Cell(150,6,'IGST @18%',1,0,'L');
$pdf->Cell(40,6,'INR '.number_format($gst,2),1,1,'R');
$pdf->SetFont('Arial','B',11);
$pdf->Cell(150,8,'Grand Total',1,0,'L');
$pdf->Cell(40,8,'INR '.number_format($grandTotal,2),1,1,'R');

$pdf->Ln(6);
// Footer notes & stamp (unchanged)
$pdf->SetFont('Arial','I',8);
$pdf->MultiCell(0,4,
  "Input tax credit of GST charged by the original service provider is available only against the invoice issued "
 .'by the respective service provider. Shree Dhanlaxmi Travels acts only as a facilitator for these services.'."\n"
 ."(This is not a valid travel document)",0,'L');
$pdf->Ln(4);
$pdf->SetFont('Arial','',7);
$pdf->Cell(0,4,'Terms & Conditions:',0,1);
$pdf->MultiCell(0,4,
  "1. Any dispute with respect to the invoice to be reported to Shree Dhanlaxmi Travels within 48 hours of receipt of invoice.\n"
  ."2. This is system generated invoice and does not require signatures.",
0,'L');
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
$lw=$pdf->GetStringWidth($label);
$gp=2; $gw=$lw+2*$gp;
$gs=$stampX+ (35-$gw)/2; $ge=$gs+$gw;
$pdf->Line($stampX,$bottomY,$gs,$bottomY);
$pdf->Line($ge,$bottomY,$stampX+35,$bottomY);
$pdf->SetXY($gs,$bottomY-4);
$pdf->Cell($gw,8,$label,0,0,'C');

$pdf->Output('I',"invoice_{$data['transaction_id']}.pdf");
exit;
?>