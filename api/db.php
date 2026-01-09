<?php
// zugangsdaten für die uni datenbank
$host = "localhost";
$user = "g13";        // dein nutzer
$pass = "dm38tan";    // dein passwort
$db   = "g13";        // deine datenbank

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(["error" => "datenbank verbindung fehlgeschlagen"]));
}

// wichtig: utf8 sonst gehen umlaute kaputt
$conn->set_charset("utf8mb4");
?>