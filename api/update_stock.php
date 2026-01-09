<?php
// cors header setzen (standard prozedur)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// preflight anfragen direkt durchwinken
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';

$input = json_decode(file_get_contents("php://input"), true);

// kurz checken ob wir alles haben was wir brauchen
if (!isset($input['id']) || !isset($input['stock'])) {
    http_response_code(400);
    echo json_encode(["error" => "daten fehlen"]);
    exit;
}

// sicherstellen dass es zahlen sind (ganz wichtig!)
$id = (int)$input['id'];
$stock = (int)$input['stock'];

// datenbank update fahren
$stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
$stmt->bind_param("ii", $stock, $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "bestand gespeichert"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "db fehler: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>