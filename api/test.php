<?php
// 1. FEHLER ANZEIGEN (Der "Lautsprecher")
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>System-Diagnose Start</h1>";

// 2. DATENBANK TESTEN
echo "Test 1: Suche db.php... ";
if (file_exists('db.php')) {
    echo "✅ Gefunden.<br>";
    require_once 'db.php';
    echo "Test 2: Prüfe Verbindung... ";
    if ($conn->connect_error) {
        die("❌ FEHLER: " . $conn->connect_error);
    }
    echo "✅ Verbindung erfolgreich (Host: $host, User: $user).<br>";
    echo "Test 3: Charset setzen... ";
    if ($conn->set_charset("utf8mb4")) {
        echo "✅ OK.<br>";
    } else {
        echo "❌ Warnung: " . $conn->error . "<br>";
    }
} else {
    die("❌ FEHLER: db.php nicht gefunden! (Bist du im richtigen Ordner?)");
}

// 3. STRIPE TESTEN
echo "<hr>Test 4: Suche Stripe Bibliothek... ";
if (file_exists('stripe-php/init.php')) {
    echo "✅ Gefunden.<br>";
    require_once 'stripe-php/init.php';
    
    echo "Test 5: Lade Stripe Klasse... ";
    if (class_exists('\Stripe\Stripe')) {
        echo "✅ Stripe Klasse geladen.<br>";
    } else {
        echo "❌ FEHLER: Datei da, aber Klasse nicht gefunden.<br>";
    }
} else {
    echo "❌ FEHLER: Ordner 'stripe-php' oder Datei 'init.php' fehlt!<br>";
    echo "Pfad geprüft: " . realpath('stripe-php/init.php') . "<br>";
}

echo "<h1>Diagnose Ende</h1>";
?>