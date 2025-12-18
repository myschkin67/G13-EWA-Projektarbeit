<?php
$host = "localhost";
$user = "root";      // Oder dein XAMPP User (z.B. "g13")
$pass = "";          // Oder dein XAMPP Passwort
$db   = "shop_db";   // Name deiner Datenbank

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(["error" => "Datenbankfehler"]));
}
$conn->set_charset("utf8mb4"); // Wichtig für Umlaute/Emojis
?>