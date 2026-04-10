<?php

if ($callback) {
    $cid = $callback['from']['id']; 
    
    $is_admin = ((string)$cid === (string)ADMIN_ID);
    
    
    if (!$is_admin) {
        if (!check_subscription($cid)) {
            return;
        }
    }
    
    if (mb_stripos($data, "delete_colobar_") === 0) {
    $channelId = str_replace("delete_colobar_", "", $data);

    
    $stmt = $pdo->prepare("DELETE FROM colobar_channels WHERE id = ?");
    $stmt->execute([$channelId]);

    answerCallback($callbackId, "✅ Kanal o‘chirildi!");
    editMessageText($chat_id, $message_id, "✅ Kanal muvaffaqiyatli o‘chirildi.");

    return;
}

    switch ($data) {
    
        case 'time_mocus':
    deleteMessage($cid, $messageId);
    
    $stmt = $pdo->prepare("SELECT last_quiz_at FROM users WHERE chat_id = ?");
    $stmt->execute([$cid]);
    $last = $stmt->fetchColumn();

    if ($last && strtotime($last) > strtotime('-20 minutes')) {
    $secondsLeft = strtotime($last) + (20 * 60) - time();
    $minutes = floor($secondsLeft / 60);
    $seconds = $secondsLeft % 60;

    $leftTime = ($minutes > 0 ? "$minutes daqiqa " : "") . "$seconds soniya";

    sendMessage($cid, "⏳ Siz ushbu bo‘limdan faqat har 20 daqiqada bir marta foydalanishingiz mumkin!\n\nIltimos, $leftTime dan so‘ng qayta urinib ko‘ring", getBackButton());
    return;
}

    
    list($question, $answer) = getRandomQuizQuestion();

    
    $stmt = $pdo->prepare("UPDATE users SET quiz_question = ?, quiz_answer = ?, last_quiz_at = NOW() WHERE chat_id = ?");
    $stmt->execute([$question, $answer, $cid]);

    
    setUserStep($cid, 'awaiting_quiz_answer');

    sendMessage($cid, "🧠 <b>Vaqtli savol:</b>\n\n<b>$question</b>\n\nTo‘g‘ri javob bering va 0.1⭐ yutuqni qo‘lga kiriting!", getBackButton(), 'HTML');
    
    
    $userInfo = getUserInfo($cid);
    if ($userInfo) {
        $firstName = $userInfo['first_name'];
    } else {
        $firstName = "User";
    }

    
    sendAd($cid, $cid, $firstName);
    break;
        
        case 'stars_earn':
    deleteMessage($cid, $messageId);

    $link = "https://t.me/starsuz_bot?start=$cid"; // 

    $caption = "🎉 <b>Do'stlaringizni taklif qiling</b> va har bir do‘stingiz uchun <b>3⭐️</b> oling!\n\n";
    $caption .= "🔗 <b>Shaxsiy havolangiz</b> (nusxa olish uchun bosing):\n\n";
    $caption .= "<code>$link</code>\n\n";
    $caption .= "🚀 <b>Qayerda ulashish mumkin?</b>\n";
    $caption .= "• Do'stlaringizga shaxsiy xabarlar orqali yuboring 👥\n";
    $caption .= "• Telegram hikoya, kanal va guruhlarda ulashing 📣\n";
    $caption .= "• Instagram, TikTok, WhatsApp kabi tarmoqlarda tarqating 🌐\n\n";
    $caption .= "🎯 Qancha ko‘p ulashsangiz, shuncha ko‘p yulduz to‘playsiz!";

    $imageUrl = "https://t.me/Realdasturlash/150"; //rasm URL sini yozing

    sendPhoto($cid, $imageUrl, $caption, getStarsEarnButtons($link));
    
   
    $userInfo = getUserInfo($cid);
    if ($userInfo) {
        $firstName = $userInfo['first_name'];
    } else {
        $firstName = "User";
    }

    // Reklamani chiqarish
    sendAd($cid, $cid, $firstName);
    break;
        
        case 'stars_exit':
    deleteMessage($cid, $messageId);

    
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE chat_id = ?");
    $stmt->execute([$cid]);
    $user = $stmt->fetch();

    if (!$user) {
        sendMessage($cid, "❌ Sizning profilingiz topilmadi. Iltimos, /start tugmasini bosing.");
        return;
    }

    $balance = round((float)$user['balance'], 2);

    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM ref_logs 
        WHERE referrer_chat_id = ? 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$cid]);
    $today_verified_referrals = (int)$stmt->fetchColumn();

   
    if ($today_verified_referrals < 3) {
        sendMessage($cid, "💰Balans: {$balance}⭐️\n\n❗️Yulduzlarni chiqarib olishingiz uchun <b>bugun</b> kamida <b>3 ta do‘stingizni</b> botga taklif qilgan bo‘lishingiz kerak!\n\n📅 Bugungi do‘stlar soni: <b>{$today_verified_referrals}</b>", getBackButton(), 'HTML');
      
    $userInfo = getUserInfo($cid);
    if ($userInfo) {
        $firstName = $userInfo['first_name'];
    } else {
        $firstName = "User";
    }

    // Reklamani chiqarish
    sendAd($cid, $cid, $firstName);
        return;
    }

    $text = "💰Balans: {$balance}⭐️\n\n🎁 Yulduzlar sonini va ularni qabul qilmoqchi bo‘lgan sovg‘ani tanlang:";

    $keyboard = [
        [['text' => '15⭐(🧸)', 'callback_data' => 'withdraw_15_teddy'], ['text' => '15⭐(💝)', 'callback_data' => 'withdraw_15_gift']],
        [['text' => '25⭐(🌹)', 'callback_data' => 'withdraw_25_rose'], ['text' => '25⭐(🎁)', 'callback_data' => 'withdraw_25_box']],
        [['text' => '50⭐(🍾)', 'callback_data' => 'withdraw_50_bottle'], ['text' => '50⭐(💐)', 'callback_data' => 'withdraw_50_flowers']],
        [['text' => '50⭐(🚀)', 'callback_data' => 'withdraw_50_rocket'], ['text' => '50⭐(🎂)', 'callback_data' => 'withdraw_50_cake']],
        [['text' => '100⭐(🏆)', 'callback_data' => 'withdraw_100_cup'], ['text' => '100⭐(💍)', 'callback_data' => 'withdraw_100_ring']],
        [['text' => '100⭐(💎)', 'callback_data' => 'withdraw_100_diamond']],
        [['text' => '⬅️ Orqaga', 'callback_data' => 'back_to_menu']]
    ];

    sendMessage($cid, $text, ['inline_keyboard' => $keyboard], 'HTML'); 
    
    // Bazadan user ma'lumotini olish
    $userInfo = getUserInfo($cid);
    if ($userInfo) {
        $firstName = $userInfo['first_name'];
    } else {
        $firstName = "User";
    }

    // Reklamani chiqarish
    sendAd($cid, $cid, $firstName);
    break;
    

   case (strpos($data, 'withdraw_') === 0):
    deleteMessage($cid, $messageId);

    
    $parts = explode('_', $data);
    $amount = (int)$parts[1];
    $gift_type = $parts[2];

    $gift_emojis = [
        'teddy' => '🧸', 'gift' => '💝', 'rose' => '🌹',
        'box' => '🎁', 'bottle' => '🍾', 'flowers' => '💐',
        'rocket' => '🚀', 'cake' => '🎂', 'cup' => '🏆',
        'ring' => '💍', 'diamond' => '💎'
    ];
    $gift_emoji = $gift_emojis[$gift_type] ?? '🎁';

    // Foydalanuvchini bazadan olamiz
    $stmt = $pdo->prepare("SELECT balance, username, first_name FROM users WHERE chat_id = ?");
    $stmt->execute([$cid]);
    $user = $stmt->fetch();

    if (!$user) {
        sendMessage($cid, "❌ Sizning profilingiz topilmadi.");
        return;
    }

    $balance = round((float)$user['balance'], 2);

    if ($balance < $amount) {
        sendMessage($cid, "❌ Sizda yetarli yulduz yo'q!\n\n💰 Balansingiz: {$balance}⭐️\nKerakli: {$amount}⭐️", getBackButton());
        return;
    }

    
    $stmt = $pdo->prepare("SELECT channel_link FROM withdraw_channels LIMIT 1");
    $stmt->execute();
    $channel = $stmt->fetch();

    if (!$channel) {
        sendMessage($cid, "❌ Xatolik yuz berdi. Keyinroq urinib ko‘ring.");
        return;
    }

    
    $stmt = $pdo->prepare("INSERT INTO withdraw_requests (chat_id, username, amount, gift_emoji, status, channel_link) VALUES (?, ?, ?, ?, 'pending', ?)");
    $stmt->execute([$cid, $user['username'], $amount, $gift_emoji, $channel['channel_link']]);
    $request_id = $pdo->lastInsertId();

    
    $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE chat_id = ?");
    $stmt->execute([$balance - $amount, $cid]);

    $username = $user['username'] ? "@{$user['username']}" : $user['first_name'];

    $admin_text = "🆕 Yangi yulduz yechish arizasi!\n\n";
    $admin_text .= "👤 Foydalanuvchi: {$username}\n";
    $admin_text .= "💰 Miqdor: {$amount}⭐️\n";
    $admin_text .= "🎁 Sovg'a: {$gift_emoji}\n";
    $admin_text .= "🆔 Ariza ID: #{$request_id}\n\n";
    $admin_text .= "Arizani ko‘rib chiqish uchun tugmalardan foydalaning:";

    $admin_keyboard = [
        [
            ['text' => "✅ Tasdiqlash", 'callback_data' => "approve_withdraw_{$request_id}"],
            ['text' => "❌ Bekor qilish", 'callback_data' => "reject_withdraw_{$request_id}"]
        ]
    ];

    
    sendMessageToChannel($channel['channel_link'], $admin_text, ['inline_keyboard' => $admin_keyboard]);

    
    sendMessage($cid, "✅ Arizangiz muvaffaqiyatli yuborildi!\n\n💰 Miqdor: {$amount}⭐️\n🎁 Sovg'a: {$gift_emoji}\n🆔 Ariza ID: #{$request_id}\n\n⏳ Arizangiz ko‘rib chiqilmoqda...", getBackButton());
    break;

// Admin arizani tasdiqlash
case (strpos($data, 'approve_withdraw_') === 0):
    $request_id = (int)str_replace('approve_withdraw_', '', $data);
    
    
    $stmt = $pdo->prepare("SELECT * FROM withdraw_requests WHERE id = ? AND status = 'pending'");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        editMessage($cid, $messageId, "❌ Bu ariza topilmadi yoki allaqachon ko'rib chiqilgan.");
        return;
    }
    
    
    $stmt = $pdo->prepare("UPDATE withdraw_requests SET status = 'approved' WHERE id = ?");
    $stmt->execute([$request_id]);
    
    
$link_button = [
    'inline_keyboard' => [
        [
            ['text' => '🤖Botimizni baholang✍️', 'url' => 'https://t.me/starsuzbot_yangiliklar/155?comment=368']
        ]
    ]
];


sendMessage(
    $request['chat_id'],
    "🎉 Tabriklaymiz!\n\n✅ Sizning #{$request_id} raqamli arizangiz tasdiqlandi!\n💰 Miqdor: {$request['amount']}⭐️\n🎁 Sovg'a: {$request['gift_emoji']}\n\n🚀 Tez orada sizga sovg'a yuboriladi!",
    $link_button
);
    
   
    $username = $request['username'] ? "@{$request['username']}" : "ID: {$request['chat_id']}";
    $updated_text = "✅ TASDIQLANGAN\n\n";
    $updated_text .= "👤 Foydalanuvchi: {$username}\n";
    $updated_text .= "💰 Miqdor: {$request['amount']}⭐️\n";
    $updated_text .= "🎁 Sovg'a: {$request['gift_emoji']}\n";
    $updated_text .= "🆔 Ariza ID: #{$request_id}\n\n";
    $updated_text .= "📅 Tasdiqlangan: " . date('Y-m-d H:i:s');
    
    editMessage($cid, $messageId, $updated_text);

    
    $tolov_text = "🎉 <b>Sovg'a topshirildi!</b>\n\n";
    $tolov_text .= "👤 Foydalanuvchi: {$username}\n";
    $tolov_text .= "💰 Miqdor: {$request['amount']}⭐️\n";
    $tolov_text .= "🎁 Sovg'a: {$request['gift_emoji']}\n";
    $tolov_text .= "🆔 Ariza ID: #{$request_id}\n";
    $tolov_text .= "📅 Sana: " . date('Y-m-d H:i:s');

    sendMessageToChannel(TOLOVLAR_CHANNEL, $tolov_text);
    
    break;

case (strpos($data, 'reject_withdraw_') === 0):
    $request_id = (int)str_replace('reject_withdraw_', '', $data);
    
    
    $stmt = $pdo->prepare("SELECT * FROM withdraw_requests WHERE id = ? AND status = 'pending'");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        editMessage($cid, $messageId, "❌ Bu ariza topilmadi yoki allaqachon ko'rib chiqilgan.");
        return;
    }
    
    
    $stmt = $pdo->prepare("UPDATE withdraw_requests SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$request_id]);
    
    // Foydalanuvchiga yulduzlarni qaytarib beramiz
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE chat_id = ?");
    $stmt->execute([$request['amount'], $request['chat_id']]);
    
    // Foydalanuvchiga xabar yuboramiz
    sendMessage($request['chat_id'], "❌ Afsuski, #{$request_id} raqamli arizangiz bekor qilindi.\n\n💰 {$request['amount']}⭐️ balansingizga qaytarildi.\n\n📝 Boshqa sovg'alar uchun yana harakat qilib ko'ring!");
    
    // Admin xabarini yangilaymiz
    $username = $request['username'] ? "@{$request['username']}" : "ID: {$request['chat_id']}";
    $updated_text = "❌ BEKOR QILINDI\n\n";
    $updated_text .= "👤 Foydalanuvchi: {$username}\n";
    $updated_text .= "💰 Miqdor: {$request['amount']}⭐️\n";
    $updated_text .= "🎁 Sovg'a: {$request['gift_emoji']}\n";
    $updated_text .= "🆔 Ariza ID: #{$request_id}\n\n";
    $updated_text .= "📅 Bekor qilindi: " . date('Y-m-d H:i:s');
    
    editMessage($cid, $messageId, $updated_text);
    break;


        case 'profile':
    deleteMessage($cid, $messageId);

    $stmt = $pdo->prepare("SELECT first_name, last_name, created_at, balance, ref_count FROM users WHERE chat_id = ?");
    $stmt->execute([$cid]);
    $user = $stmt->fetch();

    if (!$user) {
        sendMessage($cid, "❗ Profil topilmadi!", getBackButton());
        break;
    }

    $ism = $user['first_name'] ?? '';
    $familiya = $user['last_name'] ?? '';
    $sana = date("Y-m-d H:i", strtotime($user['created_at']));
    $balans = number_format((float)$user['balance'], 2);
    $ref = (int)$user['ref_count'];

    $text = "✨ <b>Profil</b>\n";
    $text .= "──────────────\n";
    $text .= "👤 Ism: $ism $familiya\n";
    $text .= "🆔 ID: <code>$cid</code>\n";
    $text .= "📅 Ro‘yxatdan o‘tgan vaqt: $sana\n";
    $text .= "──────────────\n";
    $text .= "👥 Jami do‘stlar: $ref\n";
    $text .= "💰 Balans: $balans ⭐️\n\n";
    $text .= "⁉️ <b>Kunlik bonusni qanday olish mumkin?</b>\n";
    $text .= "Telegram profilingiz tavsifiga referal havolangizni qo‘shing va har kuni 1⭐️ oling.\n\n";
    $text .= "👇 Kunlik bonusni olish uchun tugmani bosing:";

    // Bu yerga o'zingizning rasm havolangizni yozing
    $photoUrl = "https://t.me/Realdasturlash/150";

    sendPhoto($cid, $photoUrl, $text, getProfileButtons());
    
    
    $userInfo = getUserInfo($cid);
    if ($userInfo) {
        $firstName = $userInfo['first_name'];
    } else {
        $firstName = "User";
    }

    // Reklamani chiqarish
    sendAd($cid, $cid, $firstName);

    break;

        case 'tasks':
    deleteMessage($cid, $messageId);

    
    $stmt = $pdo->query("SELECT * FROM tasks_channels");
    $channels = $stmt->fetchAll();

    
    if (empty($channels)) {
    $text = "⏳ <b>Vazifalar tayyorlanmoqda...</b>\n\nAdmin hozirda siz uchun maxsus yangi vazifalarni tayyorlamoqda. 😊\n\n🔔 Yangi imkoniyatlardan birinchi bo‘lib foydalanish uchun bizni kuzatib boring va sabrli bo‘ling.\n\n✨ Sizni foydali va yulduzlarga boy vazifalar kutmoqda!";
    sendMessage($cid, $text, getBackButton(), 'HTML');
    break;
}

   
    $text = "✨ <b>Yangi vazifa!</b> ✨\n\n";
    $text .= "• <b>Kanalga obuna bo'ling</b>\n\n";
    $text .= "🎁 <b>Mukofot:</b> 0.4 ⭐️\n\n";
    $text .= "‼️ <b>Mukofotni to‘liq olish uchun:</b>\n";
    $text .= "Obuna bo‘ling va 7 kun davomida obunani bekor qilmang.\n\n";
    $text .= "✅ Tugatgandan so‘ng, \"<b>Obunani tasdiqlang</b>\" tugmasini bosing.";

   
    $channelButtons = [];
    foreach ($channels as $ch) {
        $channelButtons[] = [['text' => "📢 {$ch['channel_name']}", 'url' => $ch['channel_link']]];
    }

    
    $channelButtons[] = [
        ['text' => "✅ Obunani tasdiqlang", 'callback_data' => 'tasks_check_subscription'],
        ['text' => "⬅️ Ortga", 'callback_data' => 'back_to_menu']
    ];

    sendMessage($cid, $text, ['inline_keyboard' => $channelButtons], 'HTML');
    
    
    $userInfo = getUserInfo($cid);
    if ($userInfo) {
        $firstName = $userInfo['first_name'];
    } else {
        $firstName = "User";
    }

    // Reklamani chiqarish
    sendAd($cid, $cid, $firstName);
    break;
        
        case 'promokod':
    deleteMessage($cid, $messageId);

    
    $stmt = $pdo->prepare("SELECT username FROM channels WHERE type = 'additional' LIMIT 1");
    $stmt->execute();
    $channel = $stmt->fetch();

    if (!$channel) {
        sendMessage($cid, "⚠️ Hozircha promo-kodga oid kanal qo‘shilmagan.", getBackButton());
        break;
    }

    $additionalChannel = $channel['username'];

   
    if (!isUserSubscribed($cid, $additionalChannel)) {
        $text = "🎁 Promo-kodlar bo‘limi faqat <b>@$additionalChannel</b> kanaliga a’zo foydalanuvchilar uchun ochiladi.\n\n" .
                "🟢 Kanalga obuna bo‘ling va maxsus sovg‘alarni oling!";
        $buttons = [
            'inline_keyboard' => [
                [['text' => "🔗 Kanalga o'tish", 'url' => "https://t.me/$additionalChannel"]],
                [['text' => "✅ Tekshirish", 'callback_data' => 'promokod_check']]
            ]
        ];
        sendMessage($cid, $text, $buttons);
        break;
    }

    // 3. A'zolik tasdiqlansa promo-kod kiritish
$text = "🎉 Siz promo-kod bo‘limiga kirdingiz!\n\n✍️ Kodni yozing:";
setUserStep($cid, 'awaiting_promocode_input'); // 💡 STEP belgilanmoqda
sendMessage($cid, $text, getBackButton());
    break;
    
        case 'promokod_check':
   
    $stmt = $pdo->prepare("SELECT username FROM channels WHERE type = 'additional' LIMIT 1");
    $stmt->execute();
    $channel = $stmt->fetch();

    if (!$channel) {
        sendMessage($cid, "⚠️ Promo-kodga oid kanal yo‘q.", getBackButton());
        break;
    }

    $additionalChannel = $channel['username'];

    if (!isUserSubscribed($cid, $additionalChannel)) {
        $text = "❗ Hali ham <b>@$additionalChannel</b> kanaliga a’zo emassiz.\n\n" .
                "⏳ Iltimos, obuna bo‘lib qayta tekshiring.";
        $buttons = [
            'inline_keyboard' => [
                [['text' => "🔗 Kanalga o'tish", 'url' => "https://t.me/$additionalChannel"]],
                [['text' => "✅ Tekshirish", 'callback_data' => 'promokod_check']]
            ]
        ];
        sendMessage($cid, $text, $buttons);
    } else {
        $text = "🎉 Ajoyib! Endi promo-kod bo'limidan foydalanishingiz mumkin:";
        sendMessage($cid, $text, getBackButton());
    }
    break;

        case 'manual':
    deleteMessage($cid, $messageId);

    // Manual matni
    $text = "🤖 <b>Salom do‘stim!</b>\n\n"
          . "Quyida botdan qanday foydalanish haqida qisqacha yo‘riqnoma bor. O‘qing va yulduz yig‘ishni boshlang! ✨\n\n"
          . "🗓 <b>Bot ishga tushgan sana:</b> <i>2025-yil 8-iyul</i>\n\n"
          . "⌛ <b>Vaqli mukofot:</b>\n"
          . "Har 6 daqiqada bir marta yulduz olish mumkin\n"
          . "Qiziqarli savollarga javob berish orqali har 6 daqiqada 0.1⭐ yulduz oling!\n\n"
          . "💵 <b>Yulduz yechish:</b>\n"
          . "Yig‘gan yulduzlaringizni ajoyib sovg‘alarga almashtiring. Shartlarni bajaring va o‘zingizga yoqqan sovg'ani tanlang\n\n"
          . "📢 <b>Obuna masalasi:</b>\n"
          . "Botdan to‘liq foydalanish uchun majburiy kanallarga obuna bo‘lishni unutmang. Bu oddiy va tez bajariladi.\n\n"
          . "🙋‍♂️ <b>Yordam kerakmi?</b>\n"
          . "Hech narsa tushunmasangiz, bizga bemalol yozing 👉 @Bunyod_0528\n\n"
          . "🚀 <i>Omad tilaymiz! Yulduzlar siz tomonda bo‘lsin!</i>";

   
    sendMessage($cid, $text, getBackButton(), 'HTML');

    
    $userInfo = getUserInfo($cid);
    if ($userInfo) {
        $firstName = $userInfo['first_name'];
    } else {
        $firstName = "User";
    }

    // Reklamani chiqarish
    sendAd($cid, $cid, $firstName);
    break;


        case 'rating':
    deleteMessage($cid, $messageId);

    
    $stmt = $pdo->query("
        SELECT referrer_chat_id, COUNT(*) AS refs
        FROM ref_logs
        WHERE DATE(created_at) = CURDATE()
        GROUP BY referrer_chat_id
        ORDER BY refs DESC
        LIMIT 3
    ");
    $topUsers = $stmt->fetchAll();

    
    $stmt = $pdo->query("
        SELECT referrer_chat_id, COUNT(*) AS refs
        FROM ref_logs
        WHERE DATE(created_at) = CURDATE()
        GROUP BY referrer_chat_id
        ORDER BY refs DESC
    ");
    $allUsers = $stmt->fetchAll();

    $yourRank = null;
    $yourRefs = 0;
    foreach ($allUsers as $index => $user) {
        if ($user['referrer_chat_id'] == $cid) {
            $yourRank = $index + 1;
            $yourRefs = $user['refs'];
            break;
        }
    }

   
    if (empty($topUsers)) {
        $text = "⚠️ Bugun hali hech kim do‘st taklif qilmagan\n\n✨ Birinchi bo‘lib siz boshlang!";
        sendMessage($cid, $text, getBackButton(), 'HTML');
        break;
    }

    
    $text = "🏆 <b>Bugungi TOP 3 foydalanuvchilar:</b>\n\n";
    $places = ['🥇', '🥈', '🥉'];

    foreach ($topUsers as $i => $user) {
        $chatId = $user['referrer_chat_id'];
        $refs = $user['refs'];

        // Foydalanuvchi haqida ma’lumot
        $stmt = $pdo->prepare("SELECT username, first_name FROM users WHERE chat_id = ?");
        $stmt->execute([$chatId]);
        $info = $stmt->fetch();

        $name = $info['username'] ? "@{$info['username']}" : $info['first_name'];
        $text .= "{$places[$i]} <b>{$name}</b> | Do‘stlar: <b>{$refs}</b>\n"; 
    }

    // Sovrinlar
    $text .= "\n🎁 <b>Sovrinlar:</b>\n";
    $text .= "1-o‘rin — 15 ⭐️\n";
    $text .= "2-o‘rin — 12 ⭐️\n";
    $text .= "3-o‘rin — 9 ⭐️\n";

    
    if ($yourRank && $yourRank <= 3) {
        $text .= "\n🎉 Siz <b>{$yourRank}-o‘rindasiz</b>! Davom eting!";
    } elseif ($yourRank) {
        $text .= "\n📌 Siz bugungi <b>{$yourRank}</b>-o‘rindasiz";
    } else {
        $text .= "\nℹ️ Siz hali bugun faol emassiz";
    }

    sendMessage($cid, $text, getBackButton(), 'HTML');
    
    // Bazadan user ma'lumotini olish
    $userInfo = getUserInfo($cid);
    if ($userInfo) {
        $firstName = $userInfo['first_name'];
    } else {
        $firstName = "User";
    }

    // Reklamani chiqarish
    sendAd($cid, $cid, $firstName);
    break;

        case 'admin_panel':
            if ($cid != ADMIN_ID) {
                answerCallback($callback['id'], "⛔ Sizda bu bo‘limga ruxsat yo‘q.");
                break;
            }
            deleteMessage($cid, $messageId);
            $text = "💻 <b>Admin Panel</b> ga xush kelibsiz 🎉";
            sendMessage($cid, $text, getAdminPanelMenu());
            // Bazadan user ma'lumotini olish
    $userInfo = getUserInfo($cid);
    if ($userInfo) {
        $firstName = $userInfo['first_name'];
    } else {
        $firstName = "User";
    }

    // Reklamani chiqarish
    sendAd($cid, $cid, $firstName);
            break;

        case 'back_to_menu':
    setUserStep($cid, null);
    deleteMessage($cid, $messageId);

    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE chat_id = ?");
    $stmt->execute([$cid]);
    $user = $stmt->fetch();

    $firstName = strip_tags($user['first_name'] ?? '');
    $lastName = strip_tags($user['last_name'] ?? '');

    $caption = "👋 <b>Assalomu alaykum hurmatli $firstName $lastName</b>\n\n";
    $caption .= "1️⃣ <b>“⭐️ Yulduz ishlash”</b> tugmasini bosing va shaxsiy referal havolangizni oling\n";
    $caption .= "2️⃣ <b>Do‘stlaringizni taklif qiling</b> — har bir do'stingiz uchun <b>3⭐️</b> beriladi!\n";

    $caption .= "<pre>🎁 Qo‘shimcha imkoniyatlar:</pre>\n";
    $caption .= "➤ <i>Kundalik mukofotlar va promo-kodlar</i>\n";
    $caption .= "➤ <i>Vazifalarni bajarib yulduz yig‘ing</i>\n";
    $caption .= "➤ <i>Top foydalanuvchilar ro‘yxatida 1-o‘ringa chiqing</i>\n\n";

    $caption .= "<u>Quyidagi menyudan kerakli bo‘limni tanlang</u> 👇";

    sendRequest('sendVideo', [
        'chat_id' => $cid,
        'video' => 'https://t.me/Realdasturlash/150',
        'caption' => $caption,
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode(getMainMenu($cid))
    ]);
    break;
            case 'stats':
    $start = microtime(true); 

    deleteMessage($cid, $messageId);

    // 📊 Jami foydalanuvchilar
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total = $stmt->fetchColumn();

    // 🟢 Faol foydalanuvchilar (so‘nggi 24 soat ichida ishlatgan)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND last_active_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $active = $stmt->fetchColumn();

    // 🟠 Nofaol, lekin hali botni tark etmaganlar
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND last_active_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $inactive = $stmt->fetchColumn();

    // 🔴 Botni tark etganlar
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 0");
    $leftTotal = $stmt->fetchColumn();

    // 👨‍👩‍👧‍👦 Referal orqali kelganlar
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE referral_id IS NOT NULL");
    $refJoined = $stmt->fetchColumn();

    // 📆 Oxirgi 24 soatda qo‘shilganlar
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $new24h = $stmt->fetchColumn();

    // ➖ 24 soatda chiqib ketganlar
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 0 AND left_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $left24h = $stmt->fetchColumn();

    // 💸 Kutayotgan yechishlar
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdraw_requests WHERE status = 'pending'");
    $pendingWithdraw = $stmt->fetchColumn();

    // ✅ Tasdiqlangan yechishlar
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdraw_requests WHERE status = 'approved'");
    $approvedWithdraw = $stmt->fetchColumn();

    // 🛠 Vazifa kanallari
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks_channels");
    $taskChannels = $stmt->fetchColumn();

    // 🎁 Faol promokodlar
    $stmt = $pdo->query("SELECT COUNT(*) FROM promocodes WHERE max_uses IS NULL OR used_count < max_uses");
    $activePromos = $stmt->fetchColumn();

    // ⏱ Ishlash tezligi (ms)
    $end = microtime(true);
    $executionTime = round(($end - $start) * 1000, 3);

    // ⚡ Bot holati — yuklanishga qarab baholaymiz
    if ($executionTime < 300) {
        $loadStatus = "🟢 Yengil";
    } elseif ($executionTime < 700) {
        $loadStatus = "🟡 O‘rtacha";
    } else {
        $loadStatus = "🔴 Og‘ir";
    }

    // 📥 Yakuniy matn
    $text = "📈 <b>BOT STATISTIKASI (real vaqt):</b>\n\n";
    $text .= "👥 <b>Jami foydalanuvchilar:</b> $total\n";
    $text .= "🟢 <b>Faol foydalanuvchilar:</b> $active\n";
    $text .= "🟠 <b>Nofaol (lekin botdan chiqmaganlar):</b> $inactive\n";
    $text .= "🔴 <b>Botni tark etganlar:</b> $leftTotal\n";
    $text .= "⭐ <b>Referal orqali qo‘shilganlar:</b> $refJoined\n\n";

    $text .= "📆 <b>Oxirgi 24 soatda:</b>\n";
    $text .= "➕ Yangi foydalanuvchilar: $new24h\n";
    $text .= "➖ Chiqib ketganlar: $left24h\n\n";

    $text .= "💸 Kutayotgan yechishlar: $pendingWithdraw\n";
    $text .= "✅ Tasdiqlangan yechishlar: $approvedWithdraw\n";
    $text .= "🛠 Vazifa kanallari: $taskChannels\n";
    $text .= "🎁 Faol promokodlar: $activePromos\n\n";

    $text .= "⚡ <b>Botning o‘rtacha yuklanishi:</b>\n";
    $text .= "– Tezligi: <b>{$executionTime} ms</b>\n";
    $text .= "– Holat: $loadStatus";

    sendMessage($cid, $text, getBackToAdminPanelButton(), 'HTML');
    // Bazadan user ma'lumotini olish
    $userInfo = getUserInfo($cid);
    if ($userInfo) {
        $firstName = $userInfo['first_name'];
    } else {
        $firstName = "User";
    }

    // Reklamani chiqarish
    sendAd($cid, $cid, $firstName);
    break;
    
        case 'broadcast':
    deleteMessage($cid, $messageId);
    setUserStep($cid, 'awaiting_broadcast');
    sendMessage($cid, "📨 Yubormoqchi bo‘lgan xabaringizni yuboring. Matn, rasm, video, audio yoki hujjat bo‘lishi mumkin.");
    break;
    
        case 'user_manage':
    deleteMessage($cid, $messageId);
    sendMessage($cid, "🔍 Foydalanuvchini qidirish uchun uning chat ID raqamini yuboring:");
    setUserStep($cid, 'awaiting_user_search');
    break;
    
        case 'bot_status':
    deleteMessage($cid, $messageId);
    $statusText = getBotStatus() 
        ? "✅ Bot hozirda <b>ishlayapti</b> rejimida" 
        : "🚧 Bot <b>texnik</b> rejimda";
    
    $text = "🤖 <b>Bot holati</b>\n\n" .
            "$statusText\n\n" .
            "Kerakli rejimni tanlang:\n\n" .
            "☑️ <b>Ishlayapti rejimi</b> — bot barcha foydalanuvchilar uchun ochiq bo‘ladi.\n" .
            "🔧 <b>Texnik rejim</b> — bot vaqtincha yopiladi va foydalanuvchilarga texnik ishlar haqida xabar beriladi.";

    $buttons = [
        'inline_keyboard' => [
            [['text' => '☑️ Ishlayapti rejimi', 'callback_data' => 'set_status_active']],
            [['text' => '🔧 Texnik rejim', 'callback_data' => 'set_status_maintenance']],
            [['text' => '⬅️ Orqaga', 'callback_data' => 'admin_panel']]
        ]
    ];

    sendMessage($cid, $text, $buttons);
    break;

case 'set_status_active':
    setBotStatus(true);
    editMessageText(
        $cid,
        $messageId,
        "✅ Bot <b>ishlayapti</b> rejimiga o‘tkazildi.\n\n" .
        "Endi bot barcha foydalanuvchilar uchun faol",
        getBackToAdminPanelButton()
    );
    break;

case 'set_status_maintenance':
    editMessageText(
        $cid,
        $messageId,
        "🕒 Texnik rejim vaqtini tanlang:",
        [
            'inline_keyboard' => [
                [['text' => '⏰ 15 daqiqa', 'callback_data' => 'maintenance_15']],
                [['text' => '⏰ 30 daqiqa', 'callback_data' => 'maintenance_30']],
                [['text' => '⏰ 60 daqiqa', 'callback_data' => 'maintenance_60']],
                [['text' => '⬅️ Orqaga', 'callback_data' => 'admin_panel']]
            ]
        ]
    );
    break;
    
        case 'maintenance_15':
        case 'maintenance_30':
        case 'maintenance_60':
    $minutes = (int) str_replace('maintenance_', '', $data);
    setBotStatus(false, $minutes);
    editMessageText(
        $cid,
        $messageId,
        "🚧 Bot <b>texnik rejimga</b> o‘tkazildi.\n\n" .
        "⏳ Rejalashtirilgan vaqt: <b>$minutes daqiqa</b>\n\n" .
        "Endi foydalanuvchilar texnik ishlar haqida xabarni ko‘radi va bot <b>$minutes daqiqadan</b> keyin <b>ishlayapti</b> rejimga o'tadi",
        getBackToAdminPanelButton()
    );
    break;
    
        case 'daily_bonus':
    $stmt = $pdo->prepare("SELECT username, last_bonus_date FROM users WHERE chat_id = ?");
    $stmt->execute([$cid]);
    $user = $stmt->fetch();

    if (!$user) {
        sendMessage($cid, "❗ Profil topilmadi!", getBackButton());
        break;
    }

    $username = $user['username'];
    $lastBonus = $user['last_bonus_date'];
    $today = date('Y-m-d');

    
    $refLink = "t.me/starsuz_bot?start=$cid";
    $userInfo = file_get_contents("https://api.telegram.org/bot" . API_KEY . "/getChat?chat_id=$cid");
    $userInfo = json_decode($userInfo, true);
    $bio = $userInfo['result']['bio'] ?? '';

    if (stripos($bio, $refLink) === false) {
        sendMessage($cid, "❗ Sizning Telegram profilingiz tavsifida(bio) referal havolangiz topilmadi.\n\n🔗 Iltimos, tavsifga quyidagini qo‘shing:\n\n<code>$refLink</code>\n\nSo‘ngra qayta urinib ko‘ring", getBackButton());
        break;
    }

    
    if ($lastBonus === $today) {
        sendMessage($cid, "✅ Siz bugun allaqachon kunlik bonusni olgansiz.\n\n🗓 Ertaga yana urining.", getBackButton());
        break;
    }

    
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + 1, last_bonus_date = ? WHERE chat_id = ?");
    $stmt->execute([$today, $cid]);

    sendMessage($cid, "🎉 Sizga 1⭐️ qo‘shildi!\n\nErtaga yana bonus olishni unutmang!", getBackButton());
    break;
    
        case 'channel_settings':
    deleteMessage($cid, $messageId);

    $text = "🛠 <b>Kanallar sozlamalari</b>\n\n" .
            "Quyidagi bo‘limlar orqali botdagi kanallaringizni boshqaring:";

    $buttons = [
        'inline_keyboard' => [
            [['text' => '🔐 Majburiy obuna', 'callback_data' => 'mandatory_channels']],
            [['text' => '➕ Promokod kanal', 'callback_data' => 'additional_channels']],
            [['text' => '🎗️ Homiylar uchun', 'callback_data' => 'colobar_channels']],
            [['text' => '◀️ Orqaga', 'callback_data' => 'admin_panel']]
        ]
    ];

    sendMessage($cid, $text, $buttons);
    break;
    
        case 'colobar_channels':
    deleteMessage($cid, $messageId);

    $text = "🎗️ <b>Homiy kanallarni boshqarish uchun quyidagilardan birini tanlang:</b>";

    $buttons = [
        'inline_keyboard' => [
            [
                ['text' => '➕ Kanal qoʻshish', 'callback_data' => 'add_colobar'],
                ['text' => '➖ Kanalni ayirish', 'callback_data' => 'remove_colobar']
            ],
            [
                ['text' => '📋 Roʻyxat', 'callback_data' => 'list_colobar']
            ],
            [
                ['text' => '◀️ Orqaga', 'callback_data' => 'channel_settings']
            ]
        ]
    ];

    sendMessage($cid, $text, $buttons);
    break;
    
        case 'add_colobar':
    setUserStep($cid, 'await_colobar_invite_link');
    sendMessage($cid, "🔗 Iltimos, homiy kanalning <b>taklif havolasini</b> yuboring:\n\nMasalan: https://t.me/+abcDEFgHIjk1");
    break;
    
        case 'remove_colobar':
    deleteMessage($cid, $messageId);

    
    $stmt = $pdo->query("SELECT id, invite_link FROM colobar_channels");
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$channels) {
        sendMessage($cid, "📭 Hozircha hech qanday homiy kanal qo‘shilmagan.");
        return;
    }

    $keyboard = ['inline_keyboard' => []];

    foreach ($channels as $channel) {
        $keyboard['inline_keyboard'][] = [
            ['text' => $channel['invite_link'], 'callback_data' => "delete_colobar_" . $channel['id']]
        ];
    }

    $keyboard['inline_keyboard'][] = [
        ['text' => '◀️ Orqaga', 'callback_data' => 'colobar_channels']
    ];

    sendMessage($cid, "🗑 O‘chirmoqchi bo‘lgan homiy kanalni tanlang:", $keyboard);
    break;
    
        case 'list_colobar':
    deleteMessage($cid, $messageId);

    
    $stmt = $pdo->query("SELECT channel_id, invite_link FROM colobar_channels");
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$channels) {
        sendMessage($cid, "📭 Hozircha hech qanday homiy kanal ulangan emas.");
        return;
    }

    $text = "📋 <b>Ulangan homiy kanallar ro‘yxati:</b>\n\n";
    $n = 1;

    foreach ($channels as $channel) {
        $channel_id = $channel['channel_id'];
        $invite_link = $channel['invite_link'];

        
        $url = "https://api.telegram.org/bot" . API_KEY . "/getChatMembersCount?chat_id=" . urlencode($channel_id);
        $res = json_decode(file_get_contents($url), true);

        if (isset($res['ok']) && $res['ok']) {
            $members = $res['result'];
        } else {
            $members = "noma’lum";
        }

        $text .= "$n. <a href=\"$invite_link\">$invite_link</a> — 👥 $members ta a’zo\n\n";
        $n++;
    }

    $text .= "✏️ Kanalni o‘chirish yoki yangisini qo‘shish uchun tugmalardan foydalaning.";

    $buttons = [
        'inline_keyboard' => [
            [
                ['text' => '◀️ Orqaga', 'callback_data' => 'colobar_channels']
            ]
        ]
    ];

    sendMessage($cid, $text, $buttons, 'HTML');
    break;
    
        
    
        
        case 'mandatory_channels':
case 'additional_channels':
    deleteMessage($cid, $messageId);

    $text = "📢 <b>Quyidagi tugmalar orqali kanallarni boshqarishingiz mumkin:</b>";

    $buttons = [
        'inline_keyboard' => [
            [
                ['text' => '➕ Qo‘shish', 'callback_data' => ($data === 'mandatory_channels' ? 'add_mandatory' : 'add_additional')],
                ['text' => '➖ Ayirish', 'callback_data' => ($data === 'mandatory_channels' ? 'remove_mandatory' : 'remove_additional')]
            ],
            [
                ['text' => '📋 Ro‘yxat', 'callback_data' => ($data === 'mandatory_channels' ? 'list_mandatory' : 'list_additional')],
                ['text' => '📊 Maʼlumot', 'callback_data' => ($data === 'mandatory_channels' ? 'stats_mandatory' : 'stats_additional')]
            ],
            [
                ['text' => '◀️ Orqaga', 'callback_data' => 'channel_settings']
            ]
        ]
    ];

    sendMessage($cid, $text, $buttons);
    break;
    
        case 'stats_mandatory':
    deleteMessage($cid, $messageId);

    // Majburiy obuna kanallarini olish
    $stmt = $pdo->prepare("SELECT * FROM channels WHERE type = 'mandatory'");
    $stmt->execute();
    $channels = $stmt->fetchAll();

    if (!$channels) {
        sendMessage($cid, "📭 Majburiy obuna kanallari yo‘q.", getBackToAdminPanelButton());
        break;
    }

    $text = "📊 <b>Majburiy kanallar ulangan vaqti:</b>\n\n";

    foreach ($channels as $i => $ch) {
        $num = $i + 1;
        $text .= "$num. <b>@{$ch['username']}</b>\n";
        $text .= "📆 Ulangan: <i>" . date('Y-m-d H:i', strtotime($ch['created_at'])) . "</i>\n\n";
    }

    sendMessage($cid, $text, getBackToAdminPanelButton(), 'HTML');
    break;
    
        case 'add_mandatory':
    deleteMessage($cid, $messageId);
    $text = "🔗 Yangi <b>majburiy obuna kanali</b> qo‘shmoqchisiz.\n\nIltimos, kanal <b>usernameni</b> quyidagi formatda yuboring:\n\n<code>username</code> (faqat `@` belgisiz)\n\nMasalan:\n<code>realdasturlash</code>";
    
   
    setUserStep($cid, 'awaiting_mandatory_channel');
    sendMessage($cid, $text, getBackToAdminPanelButton());
    break;
    
        case 'remove_mandatory':
    deleteMessage($cid, $messageId);

    $stmt = $pdo->prepare("SELECT * FROM channels WHERE type = 'mandatory'");
    $stmt->execute();
    $channels = $stmt->fetchAll();

    if (!$channels) {
        sendMessage($cid, "📭 Majburiy kanallar ro‘yxati bo‘sh!", getBackToAdminPanelButton());
        break;
    }

    $buttons = ['inline_keyboard' => []];

    foreach ($channels as $ch) {
        $buttons['inline_keyboard'][] = [
            ['text' => "@{$ch['username']}", 'callback_data' => "del_mand_{$ch['id']}"]
        ];
    }

    $buttons['inline_keyboard'][] = [['text' => '◀️ Orqaga', 'callback_data' => 'mandatory_channels']];
    sendMessage($cid, "🗑 O‘chirmoqchi bo‘lgan kanalni tanlang:", $buttons);
    break;
    
        case 'list_mandatory':
    deleteMessage($cid, $messageId);

    $stmt = $pdo->prepare("SELECT * FROM channels WHERE type = 'mandatory'");
    $stmt->execute();
    $channels = $stmt->fetchAll();

    if (!$channels) {
        sendMessage($cid, "📭 Majburiy obuna kanallari ro‘yxati hozircha yo‘q.", getBackToAdminPanelButton());
        break;
    }

    $text = "📋 <b>Majburiy obuna kanallari:</b>\n\n";
    foreach ($channels as $i => $ch) {
        $num = $i + 1;
        $text .= "$num. <b>@{$ch['username']}</b> — <a href=\"{$ch['link']}\">havola</a>\n";
    }

    sendMessage($cid, $text, getBackToAdminPanelButton());
    break;
    
        case 'add_additional':
    deleteMessage($cid, $messageId);
    $text = "🔗 Yangi qo‘shimcha kanal qo‘shmoqchisiz.\n\nIltimos, kanal <b>usernameni</b> quyidagi formatda yuboring:\n\n<code>username</code> (faqat `@` belgisiz)\n\nMasalan:\n<code>realdasturlash</code>";
    setUserStep($cid, 'awaiting_additional_channel');
    sendMessage($cid, $text, getBackToAdminPanelButton(), 'HTML');
    break;
    
        case 'remove_additional':
    deleteMessage($cid, $messageId);

    $stmt = $pdo->prepare("SELECT * FROM channels WHERE type = 'additional'");
    $stmt->execute();
    $channels = $stmt->fetchAll();

    if (!$channels) {
        sendMessage($cid, "📭 Qo‘shimcha kanallar ro‘yxati bo‘sh!", getBackToAdminPanelButton());
        break;
    }

    $buttons = ['inline_keyboard' => []];
    foreach ($channels as $ch) {
        $buttons['inline_keyboard'][] = [
            ['text' => "@{$ch['username']}", 'callback_data' => "del_add_{$ch['id']}"]
        ];
    }

    $buttons['inline_keyboard'][] = [['text' => '◀️ Orqaga', 'callback_data' => 'additional_channels']];
    sendMessage($cid, "🗑 O‘chirmoqchi bo‘lgan kanalni tanlang:", $buttons);
    break;
     
         case 'list_additional':
    deleteMessage($cid, $messageId);

    $stmt = $pdo->prepare("SELECT * FROM channels WHERE type = 'additional'");
    $stmt->execute();
    $channels = $stmt->fetchAll();

    if (!$channels) {
        sendMessage($cid, "📭 Qo‘shimcha kanallar ro‘yxati hozircha yo‘q.", getBackToAdminPanelButton());
        break;
    }

    $text = "📋 <b>Qo‘shimcha (promo-kod) kanallar:</b>\n\n";
    foreach ($channels as $i => $ch) {
        $num = $i + 1;
        $text .= "$num. <b>@{$ch['username']}</b> — <a href=\"{$ch['link']}\">havola</a>\n";
    }

    sendMessage($cid, $text, getBackToAdminPanelButton());
    break;
    
        case (preg_match('/^del_add_(\d+)$/', $data, $m) ? true : false):
    $id = $m[1];
    $stmt = $pdo->prepare("DELETE FROM channels WHERE id = ? AND type = 'additional'");
    $stmt->execute([$id]);
    sendMessage($cid, "✅ Kanal muvaffaqiyatli o‘chirildi.", getBackToAdminPanelButton());
    break;
    
        case 'check_subscription':
    $callback_id = $update['callback_query']['id'];
    $cid = $update['callback_query']['from']['id'];
    $messageId = $update['callback_query']['message']['message_id'];

    
    deleteMessage($cid, $messageId);

    
    answerCallbackQuery($callback_id, "Tekshirilmoqda...");

    $stmt = $pdo->prepare("SELECT channel_id, invite_link FROM channels WHERE type = 'mandatory'");
    $stmt->execute();
    $channels = $stmt->fetchAll();

    $notSubscribed = [];

    foreach ($channels as $ch) {
        $channel_id = $ch['channel_id'];
        $url = "https://api.telegram.org/bot" . API_KEY . "/getChatMember?chat_id=$channel_id&user_id=$cid";
        $res = json_decode(file_get_contents($url), true);

        if (!isset($res['ok']) || !$res['ok']) {
            $notSubscribed[] = $ch;
        } else {
            $status = $res['result']['status'];
            if (!in_array($status, ['member', 'creator', 'administrator'])) {
                $notSubscribed[] = $ch;
            }
        }
    }

    if (count($notSubscribed) > 0) {
        $text = "Do‘stim! Hali barcha kanallarga a’zo bo‘lmaganga o‘xshaysiz 😊\n\n👇 Quyidagilarga a’zo bo‘lib, \n“✅ Tekshirish” tugmasini bosing va davom eting:";
        $buttons = ['inline_keyboard' => []];

        foreach ($notSubscribed as $ch) {
            $buttons['inline_keyboard'][] = [
                ['text' => "📣 Kanalga o‘tish", 'url' => $ch['invite_link']]
            ];
        }

        $buttons['inline_keyboard'][] = [['text' => "✅ Tekshirish", 'callback_data' => 'check_subscription']];

        sendMessage($cid, $text, $buttons);
    } else {
        
        sendMessage($cid, "🎉 Ajoyib! Obuna muvaffaqiyatli tasdiqlandi. Endi asosiy menyudan bemalol foydalanishingiz mumkin 👇", getBackButton($cid));

        // Referral tekshiruvlar
        $stmt = $pdo->prepare("SELECT referral_id, referred, first_name, last_name, username FROM users WHERE chat_id = ?");
        $stmt->execute([$cid]);
        $userData = $stmt->fetch();

        if ($userData && $userData['referral_id'] && !$userData['referred']) {
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + 2, ref_count = ref_count + 1, ref_verified_count = ref_verified_count + 1 WHERE chat_id = ?");
            $stmt->execute([$userData['referral_id']]);

            $stmt = $pdo->prepare("UPDATE users SET referred = 1 WHERE chat_id = ?");
            $stmt->execute([$cid]);

            $stmt = $pdo->prepare("INSERT INTO ref_logs (referrer_chat_id, referred_chat_id) VALUES (?, ?)");
            $stmt->execute([$userData['referral_id'], $cid]);

            sendMessage($userData['referral_id'], "🎉 Sizning havolangiz orqali foydalanuvchi <b>{$userData['first_name']} {$userData['last_name']}</b> (@{$userData['username']}) ro‘yxatdan o‘tdi. Sizga <b>2⭐</b> taqdim etildi!", null, 'HTML');
        }
    }
    break;
    
        case 'promocode_manage':
    deleteMessage($cid, $messageId);
    $text = "🎁 <b>Promokodlar boshqaruvi</b>\n\nQuyidagi tugmalar orqali promokodlar bilan ishlashingiz mumkin:";
    $buttons = [
        'inline_keyboard' => [
            [['text' => '➕ Yangi promokod qo‘shish', 'callback_data' => 'add_promocode']],
            [['text' => '📋 Promokodlar ro‘yxati', 'callback_data' => 'list_promocodes']],
            [['text' => '🗑 Promokodni o‘chirish', 'callback_data' => 'delete_promocode']],
            [['text' => '◀️ Orqaga', 'callback_data' => 'admin_panel']]
        ]
    ];
    sendMessage($cid, $text, $buttons);
    break;
    
        case 'add_promocode':
    deleteMessage($cid, $messageId);
    $text = "➕ Yangi promokod yaratmoqchisiz.\n\nIltimos, quyidagi formatda yozing:\n\n<code>KOD|yulduz_soni|max_foydalanish</code>\n\nMasalan:\n<code>YANGI2025|5|100</code>";
    setUserStep($cid, 'awaiting_promocode_data');
    sendMessage($cid, $text, getBackToAdminPanelButton(), 'HTML');
    break;
    
        case 'list_promocodes':
    deleteMessage($cid, $messageId);

    $stmt = $pdo->query("SELECT code, stars, max_uses, used_count FROM promocodes ORDER BY id DESC LIMIT 20");
    $rows = $stmt->fetchAll();

    if (!$rows) {
        sendMessage($cid, "📭 Hozircha hech qanday promokod mavjud emas.", getBackToAdminPanelButton());
        break;
    }

    $text = "📋 <b>Promokodlar ro‘yxati:</b>\n\n";
    foreach ($rows as $i => $row) {
        $text .= ($i+1) . ". <b>{$row['code']}</b> – {$row['stars']}⭐ | {$row['used_count']}/{$row['max_uses']} ishlatilgan\n";
    }

    sendMessage($cid, $text, getBackToAdminPanelButton(), 'HTML');
    break;
    
        case 'delete_promocode':
    deleteMessage($cid, $messageId);

    // Promokodlar ro‘yxatini olib kelamiz
    $stmt = $pdo->query("SELECT * FROM promocodes ORDER BY created_at DESC");
    $promos = $stmt->fetchAll();

    if (!$promos) {
        sendMessage($cid, "📭 Hozircha promokodlar mavjud emas.", getBackToAdminPanelButton());
        break;
    }

    $buttons = ['inline_keyboard' => []];

    foreach ($promos as $promo) {
        $code = $promo['code'];
        $id = $promo['id'];
        $buttons['inline_keyboard'][] = [
            ['text' => "$code", 'callback_data' => "delpromo_$id"]
        ];
    }

    $buttons['inline_keyboard'][] = [['text' => '◀️ Orqaga', 'callback_data' => 'promocode_manage']];
    sendMessage($cid, "🗑 O‘chirmoqchi bo‘lgan promokodni tanlang:", $buttons);
    break;
    
        case 'enable_test':
    $pdo->query("UPDATE settings SET test_mode = 1");
    editMessageText($cid, $messageId, "✅ Test rejimi YOQILDI. Endi faqat 3 foydalanuvchi kira oladi.", getAdminPanelMenu());
    break;

case 'disable_test':
    $pdo->query("UPDATE settings SET test_mode = 0");
    editMessageText($cid, $messageId, "❌ Test rejimi O‘CHIRILDI. Endi hamma foydalanuvchi kirishi mumkin.", getAdminPanelMenu());
    break;
    
        case 'tasks_manage':
    deleteMessage($cid, $messageId);

    // Faqat admin kira oladi
    if ($cid != ADMIN_ID) {
        sendMessage($cid, "⛔ Sizda ruxsat yo‘q.");
        break;
    }

    $text = "⚙️ <b>Vazifalar boshqaruvi</b>\n\nQuyidagi bo‘limlar orqali vazifalarni qo‘shishingiz, o‘chirishingiz yoki ko‘rishingiz mumkin.";

    $buttons = [
        [['text' => '➕ Yangi vazifa qo‘shish', 'callback_data' => 'task_add']],
        [['text' => '📋 Vazifalar ro‘yxati', 'callback_data' => 'task_list']],
        [['text' => '❌ Vazifani o‘chirish', 'callback_data' => 'task_delete']],
        [['text' => '🔙 Orqaga', 'callback_data' => 'admin_panel']]
    ];

    sendMessage($cid, $text, ['inline_keyboard' => $buttons], 'HTML');
    break;
    
        case 'task_add':
    deleteMessage($cid, $messageId);
    setUserStep($cid, 'tasks_awaiting_channel_data');

    $text = "➕ <b>Yangi kanal vazifasi qo‘shish</b>\n\nIltimos, kanal nomi va havolasini shu formatda yuboring:\n\n<code>KanalNomi | https://t.me/kanal_link</code>";
    sendMessage($cid, $text, getBackButton(), 'HTML');
    break;
    
        case 'task_list':
    deleteMessage($cid, $messageId);

    $stmt = $pdo->query("SELECT * FROM tasks_channels");
    $rows = $stmt->fetchAll();

    if (!$rows) {
        sendMessage($cid, "📋 Hech qanday vazifa yo‘q!", getBackButton());
        break;
    }

    $text = "📋 <b>Ulangan kanallar ro‘yxati:</b>\n\n";
    foreach ($rows as $i => $row) {
        $text .= ($i+1) . ". <b>" . $row['channel_name'] . "</b>\n🔗 " . $row['channel_link'] . "\n\n";
    }

    sendMessage($cid, $text, getBackButton(), 'HTML');
    break;
    
        case 'task_delete':
    deleteMessage($cid, $messageId);
    setUserStep($cid, 'awaiting_channel_delete');

    $stmt = $pdo->query("SELECT * FROM tasks_channels");
    $rows = $stmt->fetchAll();

    if (!$rows) {
        sendMessage($cid, "❌ O‘chirish uchun hech qanday kanal yo‘q!", getBackButton());
        setUserStep($cid, null);
        break;
    }

    $text = "🗑 <b>O‘chirish uchun kanal nomini yozing:</b>\n\n";
    foreach ($rows as $row) {
        $text .= "• " . $row['channel_name'] . "\n";
    }

    sendMessage($cid, $text, getBackButton(), 'HTML');
    break;
    
       case 'tasks_check_subscription':
    deleteMessage($cid, $messageId);

   
    $stmt = $pdo->query("SELECT version FROM tasks_channels ORDER BY id DESC LIMIT 1");
    $currentVersion = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT last_task_version FROM users WHERE chat_id = ?");
    $stmt->execute([$cid]);
    $userVersion = (int)$stmt->fetchColumn();

    if ($userVersion >= $currentVersion) {
        sendMessage($cid, "✅ Siz ushbu vazifani allaqachon bajargansiz. Yangi vazifani kuting!", getBackButton());
        return;
    }

    
    $stmt = $pdo->query("SELECT channel_link FROM tasks_channels");
    $channels = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $allSubscribed = true;

    foreach ($channels as $link) {
        
        $channelUsername = '@' . str_replace("https://t.me/", "", $link);

        if (!tasksisUserSubscribed($cid, $channelUsername)) {
            $allSubscribed = false;
            break;
        }
    }

   
    if ($allSubscribed) {
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + 0.4, last_task_version = ? WHERE chat_id = ?");
        $stmt->execute([$currentVersion, $cid]);

        sendMessage($cid, "🎉 Obunangiz tasdiqlandi va sizga\n0.4⭐ mukofot berildi!", getBackButton());
    } else {
        sendMessage($cid, "❌ Obunangiz aniqlanmadi. Iltimos, barcha kanallarga obuna bo‘ling va qayta urinib ko‘ring!", getBackButton());
    }

    break;
        
    }
}

require_once __DIR__ . '/channel_delete.php';