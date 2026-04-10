<?php

$step = getUserStep($cid);

if ($step == 'await_colobar_invite_link') {
  
    if (!preg_match('/^https:\/\/t\.me\/\+[a-zA-Z0-9_-]{10,}$/', $tx)) {
        sendMessage($cid, "❌ Noto‘g‘ri havola.\nMasalan: https://t.me/+abcDEFgHIjk1");
        return;
    }

    
    $stmt = $pdo->prepare("INSERT INTO colobar_channels (channel_id, invite_link) VALUES (?, ?)");
    $stmt->execute([null, $tx]); 
    sendMessage($cid, "✅ Homiy kanal linki muvaffaqiyatli qo‘shildi:\n\nLink: $tx");
//homiy kanal id sini bazadan qo'shasz
    
    setUserStep($cid, null);
    $stmt = $pdo->prepare("UPDATE users SET temp_data = NULL WHERE chat_id = ?");
    $stmt->execute([$cid]);

    return;
}



if ($step === 'awaiting_mandatory_channel') {
    setUserStep($cid, null);

    $username = trim((string) $tx);

   
    if (!preg_match('/^[a-zA-Z0-9_]{5,}$/', $username)) {
        sendMessage($cid, "❗ Noto‘g‘ri format. Faqat @ belgisiz kanal usernameni yuboring.\n\nMasalan:\n<code>realdasturlash</code>", getBackToAdminPanelButton());
        return;
    }

    
    $link = "https://t.me/$username";

    
    $stmt = $pdo->prepare("SELECT * FROM channels WHERE username = ? AND type = 'mandatory'");
    $stmt->execute([$username]);
    $exists = $stmt->fetch();

    if ($exists) {
        sendMessage($cid, "⚠️ @$username allaqachon majburiy obuna ro‘yxatida mavjud.", getBackToAdminPanelButton());
        return;
    }

    
    $stmt = $pdo->prepare("INSERT INTO channels (username, link, type) VALUES (?, ?, 'mandatory')");
    $stmt->execute([$username, $link]);

    sendMessage($cid, "✅ @$username kanali majburiy obuna ro‘yxatiga qo‘shildi!", getBackToAdminPanelButton());
    return;
}

if ($message && $step == 'awaiting_additional_channel') {
    $username = trim($message['text']);

    if (!preg_match('/^[a-zA-Z0-9_]{5,}$/', $username)) {
        sendMessage($cid, "❗ Iltimos, kanal username'ni to‘g‘ri formatda yuboring (masalan: <code>realdasturlash</code>)", getBackToAdminPanelButton(), 'HTML');
        return;
    }

    $link = "https://t.me/$username";

    
    $stmt = $pdo->prepare("INSERT INTO channels (username, link, type) VALUES (?, ?, 'additional')");
    $stmt->execute([$username, $link]);

    setUserStep($cid, null);
    sendMessage($cid, "✅ <b>@$username</b> qo‘shimcha kanal sifatida qo‘shildi!", getBackToAdminPanelButton(), 'HTML');
    return;
}

if ($message && $step === 'awaiting_promocode_data') {
    setUserStep($cid, null); 
    $parts = explode('|', $tx);

    if (count($parts) < 3) {
        sendMessage($cid, "❗ Noto‘g‘ri format! Iltimos, ushbu shaklda yozing:\n\n<code>KOD|yulduz_soni|max_foydalanish</code>", getBackToAdminPanelButton(), 'HTML');
        return;
    }

    $code = strtoupper(trim($parts[0]));
    $stars = (int) trim($parts[1]);
    $maxUses = (int) trim($parts[2]);

   
    $stmt = $pdo->prepare("SELECT id FROM promocodes WHERE code = ?");
    $stmt->execute([$code]);

    if ($stmt->fetch()) {
        sendMessage($cid, "⚠️ <b>$code</b> nomli promokod allaqachon mavjud!", getBackToAdminPanelButton(), 'HTML');
        return;
    }

    // qo‘shish
    $stmt = $pdo->prepare("INSERT INTO promocodes (code, stars, max_uses) VALUES (?, ?, ?)");
    $stmt->execute([$code, $stars, $maxUses]);

    sendMessage($cid, "✅ <b>$code</b> promokodi muvaffaqiyatli yaratildi!", getBackToAdminPanelButton(), 'HTML');
    return;
}


if ($message && $step === 'awaiting_promocode_input') {
   
    $stmt = $pdo->prepare("SELECT username FROM channels WHERE type = 'additional' LIMIT 1");
    $stmt->execute();
    $channel = $stmt->fetch();

    if ($channel) {
        $additionalChannel = $channel['username'];
        if (!isUserSubscribed($cid, $additionalChannel)) {
            $text = "❗ Promo-koddan foydalanish uchun <b>@$additionalChannel</b> kanaliga obuna bo‘lishingiz kerak!\n\n🎟️Promo-kod ushbu kanalga tashlanadi ✅";
            $buttons = [
                'inline_keyboard' => [
                    [['text' => "🔗 Kanalga o'tish", 'url' => "https://t.me/$additionalChannel"]],
                    [['text' => "✅ Tekshirish", 'callback_data' => 'promokod_check']]
                ]
            ];
            sendMessage($cid, $text, $buttons);
            return;
        }
    }

    $code = strtoupper(trim($tx));

    $stmt = $pdo->prepare("SELECT * FROM promocodes WHERE code = ?");
    $stmt->execute([$code]);
    $promo = $stmt->fetch();

    if (!$promo) {
      
        sendMessage($cid, "❌ Bunday promokod topilmadi\n\nIltimos, to‘g‘ri kod kiriting\n", getBackButton());
        return;
    }

   
    setUserStep($cid, null);

    $promoId = $promo['id'];
    $stars = $promo['stars'];
    $maxUses = $promo['max_uses'];
    $usedCount = $promo['used_count'];

    $stmt = $pdo->prepare("SELECT id FROM user_promocodes WHERE user_id = ? AND promo_id = ?");
    $stmt->execute([$cid, $promoId]);
    $usedBefore = $stmt->fetch();

    if ($usedBefore) {
        sendMessage($cid, "⚠️ Ushbu promokoddan allaqachon foydalangansiz", getBackButton());
        return;
    }

    if ($maxUses !== null && $usedCount >= $maxUses) {
        sendMessage($cid, "🚫 Bu promokoddan foydalanish muddati tugagan.", getBackButton());
        return;
    }

    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE chat_id = ?");
    $stmt->execute([$stars, $cid]);

    $stmt = $pdo->prepare("INSERT INTO user_promocodes (user_id, promo_id) VALUES (?, ?)");
    $stmt->execute([$cid, $promoId]);

    $stmt = $pdo->prepare("UPDATE promocodes SET used_count = used_count + 1 WHERE id = ?");
    $stmt->execute([$promoId]);

    sendMessage($cid, "🎉 Tabriklaymiz!\n\n💫 Siz <b>$stars</b>⭐ yulduzga ega bo‘ldingiz!", getBackButton(), 'HTML');
    return;
}

if ($message && $step === 'awaiting_quiz_answer') {
    $userAnswer = mb_strtolower(preg_replace('/\s+/', '', trim($tx))); 

   
    $stmt = $pdo->prepare("SELECT quiz_answer, quiz_question FROM users WHERE chat_id = ?");
    $stmt->execute([$cid]);
    $row = $stmt->fetch();

    $question = trim($row['quiz_question']);
    $answersRaw = $row['quiz_answer'] ?? '';

   
    $correctAnswers = array_map(function ($ans) {
        return mb_strtolower(preg_replace('/\s+/', '', trim($ans)));
    }, explode(',', $answersRaw));

  
    if (in_array($userAnswer, $correctAnswers)) {
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + 0.1, step = NULL, quiz_question = NULL, quiz_answer = NULL WHERE chat_id = ?");
        $stmt->execute([$cid]);

        sendMessage($cid, "🎉 Tabriklaymiz! Siz to‘g‘ri javob berdingiz va 0.1⭐ yutuqqa ega bo‘ldingiz!", getBackButton());
    } else {
        sendMessage($cid, "❌ Afsus, noto‘g‘ri javob. Yana urinib ko‘ring!\n\nSavol: <b>$question</b>", getBackButton(), 'HTML');
    }

    return;
}

if ($step === 'tasks_awaiting_channel_data') {
    $parts = explode('|', $tx);
    if (count($parts) == 2) {
        $name = trim($parts[0]);
        $link = trim($parts[1]);

      
        $stmt = $pdo->query("SELECT MAX(version) FROM tasks_channels");
        $maxVersion = (int)$stmt->fetchColumn();
        $newVersion = $maxVersion + 1;

        $stmt = $pdo->prepare("INSERT INTO tasks_channels (channel_name, channel_link, version) VALUES (?, ?, ?)");
        $stmt->execute([$name, $link, $newVersion]);

        sendMessage($cid, "✅ Yangi <b>vazifa</b> (kanal) muvaffaqiyatli ulandi!\n\n📌 Nomi: <b>$name</b>\n🔗 Havola: $link\n📎 Versiya: $newVersion", getBackButton(), 'HTML');
        setUserStep($cid, null);
    } else {
        sendMessage($cid, "❌ Noto‘g‘ri format. Iltimos quyidagicha yozing:\n\n<code>KanalNomi | https://t.me/link</code>", getBackButton(), 'HTML');
    }
    return;
}

if ($step === 'awaiting_channel_delete') {
    $stmt = $pdo->prepare("DELETE FROM tasks_channels WHERE channel_name = ?");
    $stmt->execute([trim($tx)]);

    if ($stmt->rowCount()) {
        sendMessage($cid, "✅ Kanal '$tx' muvaffaqiyatli o‘chirildi!", getBackButton());
    } else {
        sendMessage($cid, "❌ Bunday kanal topilmadi!", getBackButton());
    }

    setUserStep($cid, null);
    return;
}




if ($step === 'tasks_awaiting_channel_data') {
    $parts = explode('|', $tx);
    if (count($parts) !== 2) {
        sendMessage($cid, "❌ Noto‘g‘ri format!\nIltimos, quyidagi formatda yuboring:\n\n<code>KanalNomi | https://t.me/kanal_link</code>", getBackButton(), 'HTML');
        return;
    }

    $name = trim($parts[0]);
    $kanal = trim($parts[1]);

    if (!filter_var($kanal, FILTER_VALIDATE_URL) || strpos($kanal, 'https://t.me/') !== 0) {
        sendMessage($cid, "❗ Havola noto‘g‘ri ko‘rinmoqda. Faqat Telegram kanallari uchun havolalarni yuboring.", getBackButton());
        return;
    }

    
    $stmt = $pdo->query("SELECT MAX(version) FROM tasks_channels");
    $maxVersion = (int)$stmt->fetchColumn();
    $newVersion = $maxVersion + 1;

    // Bazaga yozamiz
    $stmt = $pdo->prepare("INSERT INTO tasks_channels (channel_name, channel_link, version) VALUES (?, ?, ?)");
    $stmt->execute([$name, $kanal, $newVersion]);

    
    setUserStep($cid);

    sendMessage($cid, "✅ <b>Yangi kanal vazifasi ulandi!</b>\n\n📌 Nomi: <b>$name</b>\n🔗 Havola: $kanal\n📎 Version: $newVersion", getBackButton(), 'HTML');
    return;
}

if ($message && $step === 'awaiting_broadcast') {
    deleteMessage($cid, $messageId);
    setUserStep($cid, null); 

    $stmt = $pdo->query("SELECT chat_id FROM users WHERE is_active = 1");
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $sent = 0;

    foreach ($users as $userId) {
        if (isset($message['text'])) {
            sendMessage($userId, $message['text']);
        } elseif (isset($message['photo'])) {
            $photo = end($message['photo']);
            sendRequest('sendPhoto', [
                'chat_id' => $userId,
                'photo' => $photo['file_id'],
                'caption' => $message['caption'] ?? '',
                'parse_mode' => 'HTML'
            ]);
        } elseif (isset($message['video'])) {
            sendRequest('sendVideo', [
                'chat_id' => $userId,
                'video' => $message['video']['file_id'],
                'caption' => $message['caption'] ?? '',
                'parse_mode' => 'HTML'
            ]);
        } elseif (isset($message['document'])) {
            sendRequest('sendDocument', [
                'chat_id' => $userId,
                'document' => $message['document']['file_id'],
                'caption' => $message['caption'] ?? '',
                'parse_mode' => 'HTML'
            ]);
        }

        $sent++;
        usleep(300000); // 0.3 soniya delay (spamdan saqlanish uchun)
    }

    sendMessage($cid, "✅ Xabar <b>$sent</b> ta foydalanuvchiga yuborildi.", getAdminPanelMenu());
    return;
}



if ($message && getUserStep($cid) === 'awaiting_user_search') {
    setUserStep($cid, null);
    $searchId = trim($tx);

   
    $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = ?");
    $stmt->execute([$searchId]);
    $user = $stmt->fetch();

    if (!$user) {
        sendMessage($cid, "❌ Foydalanuvchi topilmadi.");
        return;
    }

    // Promokod ishlatgan soni
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_promocodes WHERE user_id = ?");
    $stmt->execute([$searchId]);
    $promoCount = $stmt->fetchColumn();

  
    $stmt = $pdo->prepare("
        SELECT SUM(p.stars) 
        FROM user_promocodes up 
        JOIN promocodes p ON up.promo_id = p.id 
        WHERE up.user_id = ?
    ");
    $stmt->execute([$searchId]);
    $promoStars = $stmt->fetchColumn() ?? 0;

    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM withdraw_requests WHERE chat_id = ?");
    $stmt->execute([$searchId]);
    $totalWithdraws = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*), SUM(amount) 
        FROM withdraw_requests 
        WHERE chat_id = ? AND status = 'approved'
    ");
    $stmt->execute([$searchId]);
    list($approvedWithdraws, $totalStarsWithdrawn) = $stmt->fetch(PDO::FETCH_NUM);
    $totalStarsWithdrawn = $totalStarsWithdrawn ?? 0;

    
    $referralDisplay = '—';
    if (!empty($user['referral_id'])) {
        $stmt = $pdo->prepare("
            SELECT username, first_name, last_name, chat_id 
            FROM users 
            WHERE chat_id = ?
        ");
        $stmt->execute([$user['referral_id']]);
        $refUser = $stmt->fetch();

        if ($refUser) {
            if (!empty($refUser['username'])) {
                $referralDisplay = '@' . $refUser['username'] . " ({$refUser['chat_id']})";
            } else {
                $fullname = trim($refUser['first_name'] . ' ' . $refUser['last_name']);
                $referralDisplay = $fullname . " ({$refUser['chat_id']})";
            }
        }
    }

   
    $status = $user['is_active'] ? "🟢 Faol" : "🔴 Bloklagan yoki chiqib ketgan";
    $username = $user['username'] ? '@' . $user['username'] : '—';
    $firstname = $user['first_name'] ?? '—';
    $lastname = $user['last_name'] ?? '—';

    $text = "👤 <b>Foydalanuvchi ma’lumotlari</b>\n\n";
    $text .= "🆔 Chat ID: <code>{$user['chat_id']}</code>\n\n";
    $text .= "👤 Username: $username\n\n";
    $text .= "✍️ Ism: $firstname\n\n";
    $text .= "📝 Familiya: $lastname\n\n";
    $text .= "⭐ Balansi: <b>{$user['balance']}</b>\n\n";
    $text .= "🎟️ Promokod ishlatgan: <b>$promoCount</b>\n\n";
    $text .= "🌟 Promokod orqali olgan yulduzlar: <b>$promoStars</b>\n\n";
    $text .= "👥 Referal orqali kelgan: $referralDisplay\n\n";
    $text .= "📅 Qo‘shilgan: {$user['created_at']}\n\n";
    $text .= "🕓 So‘nggi aktivlik: {$user['last_active_at']}\n";
    $text .= "💸 Yechib olish arizalari: <b>$totalWithdraws</b>\n";
    $text .= "✅ Tasdiqlangan arizalar: <b>$approvedWithdraws</b>\n";
    $text .= "🌟 Yechilgan yulduzlar: <b>$totalStarsWithdrawn</b>\n";
    $text .= "📌 Holati: $status";

    sendMessage($cid, $text, getBackToAdminPanelButton(), 'HTML');
    return;
}