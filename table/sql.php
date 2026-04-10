<?php
declare(strict_types=1);

// === PDO bilan ulanish ===
$host = 'localhost';
$dbname = '688b3fdc310aa_stars';
$username = '688b3fdc310aa_stars';
$password = 'firdavs';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    file_put_contents("pdo_errors.log", date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    die("❌ Ma'lumotlar bazasiga ulanishda xatolik!");
}

?>