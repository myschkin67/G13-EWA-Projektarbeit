<?php
// CORS Header
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';

// --- Stripe Bibliothek manuell laden ---
require_once 'stripe-php/init.php';

// --- DEIN SECRET KEY ---
\Stripe\Stripe::setApiKey('sk_test_51Sf50LPfKVgT4yvvCI5xo9GviVESNd1BlTuMgDcWd7NRkxuSu8h0K9D8TgIlNPUwVEBSzgvgephBQ3wUQYVYcniB00DVPSBBer'); 

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['cart']) || empty($input['cart'])) {
    http_response_code(400);
    echo json_encode(["error" => "Warenkorb leer"]);
    exit;
}

$line_items = [];

foreach ($input['cart'] as $item) {
    // 1. Preis & Bestand aus DB holen
    $stmt = $conn->prepare("SELECT price, title, stock FROM products WHERE id = ?");
    $stmt->bind_param("i", $item['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $product_db = $res->fetch_assoc();
    $stmt->close(); 
    
    if ($product_db) {
        $qty = (int)$item['qty'];
        
        // Prüfen ob genug Lagerbestand da ist
        if ($product_db['stock'] < $qty) {
            http_response_code(400);
            echo json_encode(["error" => "Nicht genug Bestand für: " . $product_db['title']]);
            exit;
        }

        // 2. Lagerbestand reduzieren (SQL zieht ab)
        $updateStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $updateStmt->bind_param("ii", $qty, $item['id']);
        $updateStmt->execute();
        $updateStmt->close();

        // 3. Item für Stripe vorbereiten
        $line_items[] = [
            'price_data' => [
                'currency' => 'eur',
                'product_data' => ['name' => $product_db['title']],
                'unit_amount' => (int)((float)$product_db['price'] * 100),
            ],
            'quantity' => $qty,
        ];
    }
}

try {
    // --- NEU: Dynamische URL-Erkennung ---
    // Wir prüfen, wo wir gerade sind
    if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
        // LOKAL: Wir leiten zurück zum Vite Dev-Server
        $domain_url = 'http://localhost:5173';
    } else {
        // LIVE: Wir leiten auf den Uni-Server
        $domain_url = 'https://ivm108.informatik.htw-dresden.de/ewa/g13/aplbeleg';
    }

    // --- ECHTE STRIPE SESSION ERSTELLEN ---
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $line_items,
        'mode' => 'payment',
        // Hier nutzen wir jetzt die variable $domain_url
        'success_url' => $domain_url . '/?status=success',
        'cancel_url' => $domain_url . '/?status=cancel',
    ]);

    echo json_encode(['id' => $session->id]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>