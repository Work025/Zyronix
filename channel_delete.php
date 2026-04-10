<?php

// === Majburiy obuna kanalini o‘chirish tugmasi bosilganda ===
if (!empty($data) && preg_match('/^del_mand_(\d+)$/', $data, $match)) {
    $channel_id = $match[1];

    $stmt = $pdo->prepare("SELECT * FROM channels WHERE id = ? AND type = 'mandatory'");
    $stmt->execute([$channel_id]);
    $channel = $stmt->fetch();

    if (!$channel) {
        sendMessage($cid, "❗ Kanal topilmadi yoki allaqachon o‘chirilgan.");
        return;
    }

    // Kanalni bazadan o‘chiramiz
    $stmt = $pdo->prepare("DELETE FROM channels WHERE id = ?");
    $stmt->execute([$channel_id]);

    sendMessage($cid, "✅ <b>@{$channel['username']}</b> majburiy obuna ro‘yxatidan o‘chirildi.", getBackToAdminPanelButton());
    return;
}