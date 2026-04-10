<?php

// === Buyruqlarni qayta ishlash ===
if ($message) {

    
    if (isTestModeOn($pdo)) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $userCount = $stmt->fetchColumn();

        if ($userCount >= 1) {
            sendMessage($cid, "🚫 Bot hozirda test rejimida va testchilarning soni to‘ldi. Keyinroq urinib ko‘ring.");
            return;
        }
    }

    switch (true) {
        case (strpos($tx, '/start') === 0):

            $args = explode(' ', $tx);
            $referrer_id = $args[1] ?? null;

            // Foydalanuvchini tekshiramiz
            $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = ?");
            $stmt->execute([$cid]);
            $user = $stmt->fetch();

            if (!$user) {
                $stmt = $pdo->prepare("INSERT INTO users (chat_id, first_name, last_name, username, referral_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$cid, $firstName, $lastName, $username, ($referrer_id != $cid) ? $referrer_id : null]);
            } else {
                if ($referrer_id && $referrer_id != $cid) {
                    sendMessage($cid, "ℹ️ Siz ilgari ro‘yxatdan o‘tgansiz. Bu referal havola endi hisobga olinmaydi.");
                }

                if ($referrer_id == $cid) {
                    sendMessage($cid, "❗ O‘zingizning referal havolangizdan foydalana olmaysiz!");
                }
            }

            // Obuna tekshiruvi
            if (!check_subscription($cid)) return;

            // Referal tasdiqlash
            $stmt = $pdo->prepare("SELECT referral_id, referred FROM users WHERE chat_id = ?");
            $stmt->execute([$cid]);
            $u = $stmt->fetch();

            if ($u && $u['referral_id'] && !$u['referred']) {
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + 2, ref_count = ref_count + 1, ref_verified_count = ref_verified_count + 1 WHERE chat_id = ?");
    $stmt->execute([$u['referral_id']]);

    $stmt = $pdo->prepare("UPDATE users SET referred = 1 WHERE chat_id = ?");
    $stmt->execute([$cid]);

    
    $stmt = $pdo->prepare("INSERT INTO ref_logs (referrer_chat_id, referred_chat_id) VALUES (?, ?)");
    $stmt->execute([$u['referral_id'], $cid]);

    sendMessage($u['referral_id'], "🎉 Sizning havolangiz orqali foydalanuvchi <b>$firstName $lastName</b> (@$username) to‘liq ro‘yxatdan o‘tdi. Sizga <b>2⭐</b> taqdim etildi!", null, 'HTML');
}

            // Faollikni yangilash
            $stmt = $pdo->prepare("UPDATE users SET last_active_at = NOW() WHERE chat_id = ?");
            $stmt->execute([$cid]);

            // Menyu chiqarish
            $caption = "👋 <b>Assalomu alaykum hurmatli\n $firstName $lastName</b>\n\n";
            $caption .= "1️⃣ “⭐️ <b>Yulduz ishlash”</b> tugmasini bosing va shaxsiy referal havolangizni oling\n";
            $caption .= "2️⃣ <b>Do‘stlaringizni taklif qiling</b> — har bir do‘stingiz uchun <b>3⭐️</b> beriladi!\n";
            $caption .= "<pre>🎁 Qo‘shimcha imkoniyatlar:</pre>\n";
            $caption .= "<i>➤ Kundalik mukofotlar va promo-kodlar</i>\n";
            $caption .= "<i>➤ Vazifalarni bajarib yulduz yig‘ing</i>\n";
            $caption .= "<i>➤ Top foydalanuvchilar ro‘yxatida 1- o‘ringa chiqing</i>\n\n";
            $caption .= "<u>Quyidagi menyudan kerakli bo‘limni tanlang</u>👇";

            sendRequest('sendVideo', [
                'chat_id' => $cid,
                'video' => 'https://t.me/YulduzlarSayyorasi/8?single',
                'caption' => $caption,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(getMainMenu($cid))
            ]);

            break;
    }
}