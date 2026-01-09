<?php
// header setzen damit vue auch vom anderen server zugreifen kann (cors)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once 'db.php';

$sql = "SELECT * FROM products";
$result = $conn->query($sql);

$data = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        // zahlen müssen auch wirklich zahlen sein
        $row['price'] = (float)$row['price'];
        $row['stock'] = (int)$row['stock'];
        $data[] = $row;
    }
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
$conn->close();
?>