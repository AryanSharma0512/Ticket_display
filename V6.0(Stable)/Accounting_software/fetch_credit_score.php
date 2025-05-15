<?php
header('Content-Type: application/json');
require_once 'db_config.php';

if (!isset($_POST['customerID']) || empty($_POST['customerID'])) {
    echo json_encode(['error' => 'Customer ID is required']);
    exit;
}

$customerID = $_POST['customerID'];
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// 1️⃣ Fetch first transaction date (Customer Since)
$sqlFirstTransaction = "SELECT MIN(entry_date) AS first_transaction FROM main_table WHERE customer_id = ?";
$stmt = $conn->prepare($sqlFirstTransaction);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$customerSince = isset($result['first_transaction']) ? (date('Y') - date('Y', strtotime($result['first_transaction']))) : 0;

// 2️⃣ Fetch total business volume
$sqlBusiness = "SELECT SUM(bill_amount) AS total_business FROM main_table WHERE customer_id = ?";
$stmt = $conn->prepare($sqlBusiness);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$totalBusiness = $stmt->get_result()->fetch_assoc()['total_business'] ?? 0;

// 3️⃣ Count total number of transactions
$sqlTransactionCount = "SELECT COUNT(*) AS total_transactions FROM main_table WHERE customer_id = ?";
$stmt = $conn->prepare($sqlTransactionCount);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$totalTransactions = $stmt->get_result()->fetch_assoc()['total_transactions'] ?? 0;

// 4️⃣ Compute Running Balance & Max Credit Used
$sqlCreditFlow = "SELECT bill_amount, amount_received FROM main_table WHERE customer_id = ? ORDER BY entry_date ASC";
$stmt = $conn->prepare($sqlCreditFlow);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$result = $stmt->get_result();

$runningBalance = 0;
$maxCreditUsed = 0;

while ($row = $result->fetch_assoc()) {
    $runningBalance += ($row['bill_amount'] - $row['amount_received']); // Add due amount, subtract payments
    if ($runningBalance > $maxCreditUsed) {
        $maxCreditUsed = $runningBalance; // Store the highest balance as max credit used
    }
}

// 5️⃣ Repayment Ratio
$sqlRepayment = "SELECT SUM(amount_received) AS total_received FROM main_table WHERE customer_id = ?";
$stmt = $conn->prepare($sqlRepayment);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$totalReceived = $stmt->get_result()->fetch_assoc()['total_received'] ?? 0;
$repaymentRatio = $totalBusiness > 0 ? ($totalReceived / $totalBusiness) * 100 : 0;

// 6️⃣ Profitability
$sqlProfit = "SELECT SUM(profit) AS total_profit FROM main_table WHERE customer_id = ?";
$stmt = $conn->prepare($sqlProfit);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$totalProfit = $stmt->get_result()->fetch_assoc()['total_profit'] ?? 0;

// 7️⃣ Average Margin
$sqlMargin = "SELECT (SUM(profit) / SUM(bill_amount) * 100) AS avg_margin FROM main_table WHERE customer_id = ?";
$stmt = $conn->prepare($sqlMargin);
$stmt->bind_param("s", $customerID);
$stmt->execute();
$averageMargin = $stmt->get_result()->fetch_assoc()['avg_margin'] ?? 0;

// ✅ Close statement & connection
$stmt->close();
$conn->close();

// 8️⃣ Calculate Credit Score (Weighted Scoring)
$creditScore = (
    ($customerSince * 10) +        // Max 100 - Older customers get more points
    (($totalBusiness / 100000) * 2) + // Max 200 - Based on total business volume
    ($maxCreditUsed / 10000) +      // Max 150 - New method for "Max Credit Used"
    ($repaymentRatio * 2.5) +       // Max 250 - More repayment, better score
    ($totalProfit / 5000) +         // Max 100 - Profit-based scoring
    ($averageMargin * 30) +         // Max 100 - Profit margin contribution
    ($totalTransactions * 2)        // Max 100 - More transactions = more trust
);

// 🔥 Ensure the credit score stays within 900
$creditScore = min(900, round($creditScore));

echo json_encode([
    'credit_score' => $creditScore,
    'customer_since' => $customerSince,
    'total_business' => round($totalBusiness, 2),
    'max_credit_used' => round($maxCreditUsed, 2),
    'repayment_ratio' => round($repaymentRatio, 2),
    'total_profit' => round($totalProfit, 2),
    'average_margin' => round($averageMargin, 2),
    'total_transactions' => $totalTransactions
]);
?>
