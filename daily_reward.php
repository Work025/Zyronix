<?php
require 'bootstrap.php'; 

$rewards = [15, 12, 9];
$places = ['🥇', '🥈', '🥉'];
$channel = '@vip_stars_tulov'; // ← o'zingizning kanal username 

// Bugungi eng faol 3 foydalanuvchini olish
$stmt = $pdo->query("
    SELECT referrer_chat_id, COUNT(*) AS refs
    FROM ref_logs
    WHERE DATE(created_at) = CURDATE()
    GROUP BY referrer_chat_id
    ORDER BY refs DESC
    LIMIT 3
");
$topUsers = $stmt->fetchAll();

if (empty($topUsers)) {
    // Hech kim do‘st taklif qilmagan bo‘lsa
    sendMessageToChannel($channel, "📭 Bugun hech kim do‘st taklif qilmagan\n\n✨ Ertangi reytingda siz ham bo‘lishingiz mumkin!", null, 'HTML');
    exit;
}

$now = date("Y-m-d  H:i");

$text = "🏆 <b>Bugungi g‘oliblar e’lon qilindi!</b>\n🕒 <i>$now</i>\n\n";

foreach ($topUsers as $i => $user) {
    $chatId = $user['referrer_chat_id'];
    $refs = $user['refs'];

    // Foydalanuvchi haqida ma’lumot
    $stmt = $pdo->prepare("SELECT username, first_name FROM users WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $info = $stmt->fetch();

    $name = $info['username'] ? "@{$info['username']}" : $info['first_name'];
    $text .= "{$places[$i]} <b>{$name}</b> | Do‘stlar: <b>{$refs}</b>\n";

    // Mukofot berish
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE chat_id = ?");
    $stmt->execute([$rewards[$i], $chatId]);

    // Ularga xabar yuborish
    sendRewardMessage($chatId, "🎉 Tabriklaymiz! Siz bugungi reytingda <b>{$places[$i]}</b> o‘rinni egalladingiz!\n\nSizga <b>{$rewards[$i]}⭐</b> mukofot taqdim etildi. Yana davom eting!", null, 'HTML');
}

$text .= "\n🎁 Mukofotlar hisobingizga qo‘shildi\n<i>StarsUZ jamoasi</i>";

sendMessageToChannel($channel, $text, null, 'HTML');