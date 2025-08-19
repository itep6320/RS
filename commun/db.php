<?php
// --- Configuration de la base ---
$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbPort = getenv('PORT');
$charset = getenv('CHARSET');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset;port=$dbPort", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$mysqli = new mysqli($host, $username, $password, $dbname, $dbPort);
if ($mysqli->connect_errno) {
    die("Erreur MySQL: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");
