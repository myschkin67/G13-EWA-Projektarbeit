<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';

$input = json_decode(file_get_contents("php://input"), true);

// checken ob daten da sind
if (!isset($input['id']) || !isset($input['stock'])) {
    http_response_code(400);
    exit;
}

// einfaches update query
$stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
$stmt->bind_param("ii", $input['stock'], $input['id']);
$stmt->execute();

echo json_encode(["success" => true]);
$conn->close();
?>