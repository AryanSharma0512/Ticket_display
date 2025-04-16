<?php
/**
 * fetch_goal_seeker.php
 *
 * Advanced Projection Algorithm:
 * - Retrieves monthly data from main_table (bill_amount as revenue, profit as profit),
 *   filtering out rows where bill_amount < 3000.
 * - Aggregates data by month (all available months).
 * - Computes cumulative totals for revenue and profit.
 * - Calculates month-to-month increments (difference between consecutive cumulative totals).
 * - Computes:
 *      • Average monthly increment,
 *      • Median monthly increment,
 *      • Weighted increment = 0.7*average + 0.3*median.
 * - Also performs a linear regression on the cumulative totals (x = month index) to get the regression slope.
 * - Estimates months needed in two ways:
 *      Estimate1 = (goal - current_total) / weighted_increment
 *      Estimate2 = (goal - current_total) / regression_slope
 * - Final estimate = weighted blend: 60% of Estimate1 + 40% of Estimate2.
 * - If both profit and revenue goals are provided, the maximum estimated months is used.
 * - Computes the target date as current date + predicted months.
 * - Returns all intermediate values for further analysis.
 */

header('Content-Type: application/json');

// Retrieve POST inputs (default to 0 if blank)
$goal_profit  = isset($_POST['net_profit'])  ? floatval($_POST['net_profit'])  : 0;
$goal_revenue = isset($_POST['net_revenue']) ? floatval($_POST['net_revenue']) : 0;

if ($goal_profit <= 0 && $goal_revenue <= 0) {
    echo json_encode(["success" => false, "error" => "Please enter at least one non-zero goal."]);
    exit;
}

// Database connection parameters
$host = "localhost";
$user = "root";
$pass = "";
$db   = "accounting";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query: get monthly aggregated data (all available months) from main_table.
    // We use bill_amount as revenue and profit as profit.
    $sql = "
        SELECT 
            YEAR(entry_date) AS y,
            MONTH(entry_date) AS m,
            SUM(bill_amount) AS monthly_revenue,
            SUM(profit) AS monthly_profit
        FROM main_table
        WHERE bill_amount >= 3000
        GROUP BY YEAR(entry_date), MONTH(entry_date)
        ORDER BY YEAR(entry_date), MONTH(entry_date)
    ";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rows)) {
        echo json_encode(["success" => false, "error" => "No data available."]);
        exit;
    }
    
    // Build arrays for monthly totals
    $monthlyRevenue = [];
    $monthlyProfit  = [];
    foreach ($rows as $row) {
        $monthlyRevenue[] = floatval($row['monthly_revenue']);
        $monthlyProfit[]  = floatval($row['monthly_profit']);
    }
    
    // Compute current cumulative totals (using all months)
    $currentRevenueTotal = array_sum($monthlyRevenue);
    $currentProfitTotal  = array_sum($monthlyProfit);
    
    // Build cumulative arrays (trendline)
    $cumRevenue = [];
    $cumProfit  = [];
    $runningRev = 0;
    $runningProf = 0;
    foreach ($monthlyRevenue as $rev) {
        $runningRev += $rev;
        $cumRevenue[] = $runningRev;
    }
    foreach ($monthlyProfit as $prof) {
        $runningProf += $prof;
        $cumProfit[] = $runningProf;
    }
    
    // Number of data points (months)
    $n = count($cumRevenue);
    
    // Calculate month-to-month increments (difference between consecutive cumulative totals)
    $revenueIncrements = [];
    $profitIncrements = [];
    for ($i = 1; $i < $n; $i++) {
        $revenueIncrements[] = $cumRevenue[$i] - $cumRevenue[$i - 1];
        $profitIncrements[]  = $cumProfit[$i] - $cumProfit[$i - 1];
    }
    
    // Helper: Compute median of an array
    function median(array $arr) {
        if (empty($arr)) return 0;
        sort($arr);
        $count = count($arr);
        $mid = floor($count / 2);
        return ($count % 2) ? $arr[$mid] : (($arr[$mid - 1] + $arr[$mid]) / 2);
    }
    
    // Calculate average and median increments
    $avgRevenueIncrement = (count($revenueIncrements) > 0) ? array_sum($revenueIncrements) / count($revenueIncrements) : 0;
    $medianRevenueIncrement = median($revenueIncrements);
    $weightedRevenueIncrement = 0.7 * $avgRevenueIncrement + 0.3 * $medianRevenueIncrement;
    
    $avgProfitIncrement = (count($profitIncrements) > 0) ? array_sum($profitIncrements) / count($profitIncrements) : 0;
    $medianProfitIncrement = median($profitIncrements);
    $weightedProfitIncrement = 0.7 * $avgProfitIncrement + 0.3 * $medianProfitIncrement;
    
    // Ensure weighted increments are positive; if not, set to a small default value to avoid division by zero.
    if ($weightedRevenueIncrement <= 0) $weightedRevenueIncrement = 1;
    if ($weightedProfitIncrement <= 0) $weightedProfitIncrement = 1;
    
    // --- Linear Regression on cumulative totals ---
    // We'll define a function to compute linear regression coefficients (slope and intercept).
    function linearRegression($x, $y) {
        $n = count($x);
        if ($n === 0) return ["a" => 0, "b" => 0];
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumXX = 0;
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumXX += $x[$i] * $x[$i];
        }
        $meanX = $sumX / $n;
        $meanY = $sumY / $n;
        $b = ($sumXY - $n * $meanX * $meanY) / ($sumXX - $n * $meanX * $meanX);
        $a = $meanY - $b * $meanX;
        return ["a" => $a, "b" => $b];
    }
    
    // Create x values as month indices 1,2,...,n.
    $x = range(1, $n);
    $regRevenue = linearRegression($x, $cumRevenue);
    $regProfit  = linearRegression($x, $cumProfit);
    
    // For our projection, compute two estimates:
    // Estimate 1: Using weighted increment from recent data.
    //    monthsNeeded1 = (goal - current_total) / weighted_increment
    $estimate1_revenue = ($goal_revenue > $currentRevenueTotal) ? ($goal_revenue - $currentRevenueTotal) / $weightedRevenueIncrement : 0;
    $estimate1_profit  = ($goal_profit > $currentProfitTotal) ? ($goal_profit - $currentProfitTotal) / $weightedProfitIncrement : 0;
    
    // Estimate 2: Using regression slope (which gives average monthly cumulative increase)
    //    monthsNeeded2 = (goal - current_total) / regression_slope
    $estimate2_revenue = ($goal_revenue > $currentRevenueTotal && $regRevenue['b'] > 0) ? ($goal_revenue - $currentRevenueTotal) / $regRevenue['b'] : 0;
    $estimate2_profit  = ($goal_profit > $currentProfitTotal && $regProfit['b'] > 0) ? ($goal_profit - $currentProfitTotal) / $regProfit['b'] : 0;
    
    // Blend the two estimates for each metric.
    // We'll weight Estimate 1 at 60% and Estimate 2 at 40%.
    $final_estimate_revenue = ($goal_revenue > $currentRevenueTotal) 
        ? ceil(0.6 * $estimate1_revenue + 0.4 * $estimate2_revenue) 
        : 0;
    $final_estimate_profit = ($goal_profit > $currentProfitTotal) 
        ? ceil(0.6 * $estimate1_profit + 0.4 * $estimate2_profit) 
        : 0;
    
    // Use the higher of the two estimates (if both goals are provided)
    $monthsNeeded = max($final_estimate_revenue, $final_estimate_profit);
    
    // If the computed monthsNeeded is huge (or NaN), set a fallback.
    if (!is_finite($monthsNeeded) || $monthsNeeded < 0) {
        $monthsNeeded = 9999;
    }
    
    // Compute target date from current date + monthsNeeded.
    $targetDate = date("Y-m-d", strtotime("+$monthsNeeded month"));
    
    // Return all computed values for analysis.
    echo json_encode([
        "success" => true,
        "target_date" => $targetDate,
        "months_needed_profit"  => $final_estimate_profit,
        "months_needed_revenue" => $final_estimate_revenue,
        "weighted_profit_increment" => $weightedProfitIncrement,
        "weighted_revenue_increment" => $weightedRevenueIncrement,
        "avg_profit_increment" => $avgProfitIncrement,
        "median_profit_increment" => $medianProfitIncrement,
        "avg_revenue_increment" => $avgRevenueIncrement,
        "median_revenue_increment" => $medianRevenueIncrement,
        "regProfit" => $regProfit,
        "regRevenue" => $regRevenue,
        "current_profit_total" => $currentProfitTotal,
        "current_revenue_total" => $currentRevenueTotal,
        "estimate1_profit" => $estimate1_profit,
        "estimate2_profit" => $estimate2_profit,
        "estimate1_revenue" => $estimate1_revenue,
        "estimate2_revenue" => $estimate2_revenue,
        "final_estimate_profit" => $final_estimate_profit,
        "final_estimate_revenue" => $final_estimate_revenue,
        "monthsNeeded" => $monthsNeeded
    ]);
    
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
