<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
require_once 'db.php';

// neueste bestellungen oben
$sql = "SELECT * FROM orders ORDER BY created_at DESC";
$result = $conn->query($sql);

$orders = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        // wir haben die kundendaten als json text gespeichert,
        // müssen sie jetzt zurückwandeln damit vue sie lesen kann
        $row['customer_data'] = json_decode($row['customer_data']);
        $orders[] = $row;
    }
}

echo json_encode($orders);
$conn->close();
?>