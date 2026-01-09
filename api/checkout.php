<?php
// wir starten einen puffer, damit keine warnungen das json kaputt machen
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

// fehler verstecken (für den user), wir wollen sauberes json
error_reporting(0);
ini_set('display_errors', 0);

require_once 'db.php';
require_once 'stripe-php/init.php';

// dein key
\Stripe\Stripe::setApiKey('sk_test_51Sf50LPfKVgT4yvvCI5xo9GviVESNd1BlTuMgDcWd7NRkxuSu8h0K9D8TgIlNPUwVEBSzgvgephBQ3wUQYVYcniB00DVPSBBer'); 

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['cart']) || empty($input['cart'])) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(["error" => "warenkorb leer"]);
    exit;
}

// kundendaten verpacken
if (isset($input['customer'])) {
    $customer_json = json_encode($input['customer'], JSON_UNESCAPED_UNICODE);
} else {
    $customer_json = json_encode(["name" => "gast"]);
}

$line_items = [];
$total_amount = 0;

foreach ($input['cart'] as $item) {
    $stmt = $conn->prepare("SELECT price, title, stock FROM products WHERE id = ?");
    $stmt->bind_param("i", $item['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $product_db = $res->fetch_assoc();
    $stmt->close();
    
    if ($product_db) {
        $qty = (int)$item['qty'];
        $stock = (int)$product_db['stock']; // sicherstellen dass es int ist
        
        // bestand checken
        if ($stock < $qty) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(["error" => "Zu wenig Bestand bei: " . $product_db['title'] . " (Nur noch $stock da)"]);
            exit;
        }

        // abziehen
        $update = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $update->bind_param("ii", $qty, $item['id']);
        $update->execute();
        $update->close();

        // preis
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

// bestellung speichern (jetzt funktioniert es, weil die spalte da ist!)
$insertOrder = $conn->prepare("INSERT INTO orders (customer_data, total_price) VALUES (?, ?)");
$insertOrder->bind_param("sd", $customer_json, $total_amount);
$insertOrder->execute();
$insertOrder->close();

try {
    // redirect url bestimmen
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

    // puffer leeren und json rausgeben
    ob_end_clean();
    echo json_encode(['id' => $session->id]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
$conn->close();
?>