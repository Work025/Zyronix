<?php
//Loglar va xatoliklarni sozlash
ini_set('display_errors', '1'); 
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL);

$log_dir = __DIR__ . "/logs";
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}

function writeLog(string $message, string $file = 'errors.log'): void {
    global $log_dir;
    $date = date("Y-m-d H:i:s");
    $logMessage = "[$date] $message\n"; // Log yozuvi
    file_put_contents("$log_dir/$file", $logMessage, FILE_APPEND);
}

// 12 soatda bir marta input.log faylini tozalassh
$cleanup_file = "$log_dir/cleanup_time.log"; 
$now = time();

$last_cleanup = 0;
if (file_exists($cleanup_file)) {
    $last_cleanup = (int) file_get_contents($cleanup_file);
}

// Agar 12 soatdan (43200 soniya) ko‘p vaqt o‘tgan bo‘lsa, logni tozalaymiz
if ($now - $last_cleanup > 11180) {
    file_put_contents("$log_dir/input.log", "");
    file_put_contents($cleanup_file, (string) $now);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents("php://input");
    file_put_contents("$log_dir/input.log", $input . "\n", FILE_APPEND);
}
ob_start();

//Sozlamalar
define('API_KEY', '8276913331:AAGURqbJZ2tCgk3_Z53qWpeEQzjdkWnHPlg');//bot tokeni
const ADMIN_ID = 7281428723; // admin id
define('TOLOVLAR_CHANNEL', '@vip_stars_tulov');

// === Ma'lumotlar bazasi ulanishi ===
require_once __DIR__ . '/table/sql.php';

$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['my_chat_member'])) {
    $status = $update['my_chat_member']['new_chat_member']['status'];
    $chat_id = $update['my_chat_member']['chat']['id'];

    if ($status === 'left' || $status === 'kicked') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0, left_at = NOW() WHERE chat_id = ?");
        $stmt->execute([$chat_id]);
    }

    if ($status === 'member') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE chat_id = ?");
        $stmt->execute([$chat_id]);
    }

    exit; 
}

// === Telegram API funksiyalari ===
function sendRequest($method, $data = [], $asJson = true) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);

    if ($asJson) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        writeLog("HTTP xato: $httpCode, Javob: $response");
        return false;
    }

    $decoded = json_decode($response, true);
    if (!$decoded['ok']) {
        writeLog("Telegram API xato: " . $response);
        return false;
    }

    return $decoded;
}

require("functions.php");

function getValidatedUpdate() {
    $input = file_get_contents('php://input');
    if (empty($input)) {
        writeLog("Bo'sh so'rov keldi");
        exit;
    }
    
    $update = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        writeLog("JSON dekodlashda xato: " . json_last_error_msg());
        exit;
    }
    return $update;
}

// === Foydalanuvchi ma'lumotlarini saqlash ===
function saveUser($chatId, $firstName, $lastName = '', $username = '') {
    global $pdo;

    $stmt = $pdo->prepare("INSERT INTO users 
        (chat_id, first_name, last_name, username, balance, ref_count, ref_verified_count, created_at)
        VALUES (?, ?, ?, ?, 0, 0, 0, NOW())
        ON DUPLICATE KEY UPDATE 
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            username = VALUES(username),
            last_active_at = NOW()");
    
    return $stmt->execute([$chatId, $firstName, $lastName, $username]);
}

// === Asosiy menyu tugmalari ===
require("buttons.php");

// === Telegram so'rovini olish ===
$update = getValidatedUpdate();

$message = $update['message'] ?? null;
$callback = $update['callback_query'] ?? null;

$cid = null;
$tx = null;
$data = null;
$messageId = null;

if ($callback) {
    $cid = $callback['message']['chat']['id'];
    $data = $callback['data'];
    $messageId = $callback['message']['message_id'];
    
    // Callback javobini berish
    answerCallback($callback['id']);
    
} elseif ($message) {
    $cid = $message['chat']['id'];
    $tx = trim($message['text'] ?? '');
    $messageId = $message['message_id'];
    
    // Foydalanuvchi ma'lumotlarini saqlash
    $firstName = htmlspecialchars($message['from']['first_name'] ?? '');
$lastName = htmlspecialchars($message['from']['last_name'] ?? '');
$username = htmlspecialchars($message['from']['username'] ?? '');
}

// === Foydalanuvchi faolligini yangilash ===
if ($cid) {
    $stmt = $pdo->prepare("UPDATE users SET last_active_at = NOW() WHERE chat_id = ?");
    $stmt->execute([$cid]);
}

require_once __DIR__ . '/technical.php';

require_once __DIR__ . '/steps_handler.php';


if (strpos($data, 'delpromo_') === 0) {
    $promoId = str_replace('delpromo_', '', $data);

    
    $stmt = $pdo->prepare("DELETE FROM promocodes WHERE id = ?");
    $stmt->execute([$promoId]);

    
    $stmt = $pdo->prepare("DELETE FROM user_promocodes WHERE promo_id = ?");
    $stmt->execute([$promoId]);

    sendMessage($cid, "✅ Promokod muvaffaqiyatli o‘chirildi!", getBackToAdminPanelButton());
}

require_once __DIR__ . '/start_commond.php';

require_once __DIR__ . '/callback_handler.php';


ob_end_flush();

?>