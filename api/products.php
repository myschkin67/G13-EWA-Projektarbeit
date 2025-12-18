<?php
// WICHTIG: Erlaubt Zugriff von Port 5173 auf Port 80
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once 'db.php';

// Simpler Abruf aller Produkte
$sql = "SELECT * FROM products";
$result = $conn->query($sql);

$products = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        // Zahlen auch wirklich als Zahlen senden
        $row['price'] = (float)$row['price'];
        $row['stock'] = (int)$row['stock'];
        $products[] = $row;
    }
}

echo json_encode($products, JSON_UNESCAPED_UNICODE);
$conn->close();
?>