<?php
// Erlaubt Zugriff von deinem Frontend
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Erlaubt POST und OPTIONS
header("Access-Control-Allow-Headers: Content-Type");  // Erlaubt JSON-Header
header("Content-Type: application/json; charset=UTF-8");

// Preflight-Anfrage von Chrome/Safari abfangen und sofort mit OK antworten
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';

// Wir empfangen JSON-Daten vom Frontend
$input = json_decode(file_get_contents("php://input"), true);

// Prüfen ob Daten da sind
if (!isset($input['id']) || !isset($input['stock'])) {
    http_response_code(400);
    echo json_encode(["error" => "Fehlende Daten"]);
    exit;
}

$id = (int)$input['id'];
$stock = (int)$input['stock'];

// SQL Update Befehl (Prepared Statement gegen Hacker-Angriffe)
$stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
$stmt->bind_param("ii", $stock, $id); // "ii" heißt: Zwei Integers

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Lager aktualisiert"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Datenbankfehler: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>