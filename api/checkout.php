<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// preflight anfrage abfangen
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';
require_once 'stripe-php/init.php'; // stripe laden

// dein stripe key
\Stripe\Stripe::setApiKey('sk_test_51Sf50LPfKVgT4yvvCI5xo9GviVESNd1BlTuMgDcWd7NRkxuSu8h0K9D8TgIlNPUwVEBSzgvgephBQ3wUQYVYcniB00DVPSBBer'); 

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['cart']) || empty($input['cart'])) {
    http_response_code(400);
    echo json_encode(["error" => "korb leer"]);
    exit;
}

// kundendaten vorbereiten
if (isset($input['customer'])) {
    $customer_json = json_encode($input['customer'], JSON_UNESCAPED_UNICODE);
} else {
    $customer_json = json_encode(["name" => "gast"]);
}

$line_items = [];
$total_amount = 0;

// wir vertrauen nicht dem preis vom frontend,
// sondern holen die echten preise aus der db
foreach ($input['cart'] as $item) {
    $stmt = $conn->prepare("SELECT price, title, stock FROM products WHERE id = ?");
    $stmt->bind_param("i", $item['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $product_db = $res->fetch_assoc();
    $stmt->close();
    
    if ($product_db) {
        $qty = (int)$item['qty'];
        
        // checken ob genug da ist
        if ($product_db['stock'] < $qty) {
            http_response_code(400);
            echo json_encode(["error" => "nicht genug bestand bei: " . $product_db['title']]);
            exit;
        }

        // bestand abziehen (reservieren)
        $update = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $update->bind_param("ii", $qty, $item['id']);
        $update->execute();
        $update->close();

        // item für stripe zusammenbauen
        $price_cent = (int)((float)$product_db['price'] * 100);
        $total_amount += ((float)$product_db['price'] * $qty);

        $line_items[] = [
            'price_data' => [
                'currency' => 'eur',
                'product_data' => ['name' => $product_db['title']],
                'unit_amount' => $price_cent,
            ],
            'quantity' => $qty,
        ];
    }
}

// bestellung in die orders tabelle speichern
$insertOrder = $conn->prepare("INSERT INTO orders (customer_data, total_price) VALUES (?, ?)");
$insertOrder->bind_param("sd", $customer_json, $total_amount);
$insertOrder->execute();
$insertOrder->close();

try {
    // wir müssen gucken wo das script läuft. lokal ist es localhost, auf dem server die uni adresse.
    // sonst leitet stripe falsch zurück.
    if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
        $domain = 'http://localhost:5173';
    } else {
        $domain = 'https://ivm108.informatik.htw-dresden.de/ewa/g13/aplbeleg';
    }

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $line_items,
        'mode' => 'payment',
        'success_url' => $domain . '/?status=success',
        'cancel_url' => $domain . '/?status=cancel',
    ]);

    echo json_encode(['id' => $session->id]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
$conn->close();
?>