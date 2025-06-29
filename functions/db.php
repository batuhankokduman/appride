<?php
// Hata ayıklama açık kalsın, projede ilerleyince kapatabiliriz.
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$db   = 'u620785866_ride_app';
$user = 'u620785866_ride_app';
$pass = 'SjS4Z3W6$pV';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Hataları yakala
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch türü: dizi
    PDO::ATTR_EMULATE_PREPARES   => false,                  // SQL enjeksiyonu engeli
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die('Veritabanı bağlantı hatası: ' . $e->getMessage());
}
