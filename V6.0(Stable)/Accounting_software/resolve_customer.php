<?php
header('Content-Type: application/json');
require_once 'db_config.php';

if (!isset($_GET['input']) || trim($_GET['input']) === '') {
    echo json_encode(['error' => 'Input is required']);
    exit;
}

$input = trim($_GET['input']);
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$idMatch = null;
$nameMatch = null;

$stmt = $conn->prepare('SELECT DISTINCT customer_id FROM main_table WHERE customer_id = ? LIMIT 1');
$stmt->bind_param('s', $input);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $idMatch = $row['customer_id'];
}
$stmt->close();

$stmt = $conn->prepare('SELECT DISTINCT customer_id FROM main_table WHERE customer_name = ? LIMIT 1');
$stmt->bind_param('s', $input);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $nameMatch = $row['customer_id'];
}
$stmt->close();
$conn->close();

if ($idMatch && $nameMatch && $idMatch !== $nameMatch) {
    echo json_encode(['status' => 'ambiguous', 'idMatch' => $idMatch, 'nameMatch' => $nameMatch]);
} elseif ($idMatch) {
    echo json_encode(['status' => 'id', 'customerID' => $idMatch]);
} elseif ($nameMatch) {
    echo json_encode(['status' => 'name', 'customerID' => $nameMatch]);
} else {
    echo json_encode(['status' => 'none']);
}
?>
