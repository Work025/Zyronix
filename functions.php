<?php

function getBackButton() {
    return [
        'inline_keyboard' => [
            [['text' => '🏠 Bosh menyuga qaytish', 'callback_data' => 'back_to_menu']]
        ]
    ];
}

function sendMessage($chatId, $text, $keyboard = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    return sendRequest('sendMessage', $data);
}

function deleteMessage($chatId, $messageId) {
    return sendRequest('deleteMessage', [
        'chat_id' => $chatId,
        'message_id' => $messageId
    ]);
}


function sendRewardMessage($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    global $pdo;

    // Bugungi kun uchun bu user necha referal qilganini tekshiramiz
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ref_logs WHERE referrer_chat_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$chat_id]);
    $count = $stmt->fetchColumn();

    //  Kamida 1 dona bo‘lsa yuboramiz
    if ($count < 1) return;

    $url = "https://api.telegram.org/bot" . API_KEY . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;

    return file_get_contents($url . "?" . http_build_query($data));
}

function sendPhoto($chatId, $photoUrl, $caption = '', $keyboard = null) {
    $data = [
        'chat_id' => $chatId,
        'photo' => $photoUrl,
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
    }

    // ❗ Bu yerda JSON emas, multipart orqali yuboriladi
    return sendRequest('sendPhoto', $data, false);
}

function editMessage($chatId, $messageId, $text, $keyboard = null) {
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    return sendRequest('editMessageText', $data);
}

function answerCallback($callbackId, $text = '') {
    return sendRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text' => $text
    ]);
}

function getBackToAdminPanelButton(): array {
    return [
        'inline_keyboard' => [
            [['text' => '◀️ Orqaga', 'callback_data' => 'admin_panel']]
        ]
    ];
}

function editMessageText($chat_id, $message_id, $text, $keyboard = null) {
    sendRequest('editMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'reply_markup' => $keyboard ? json_encode($keyboard) : null
    ]);
}

function setBotStatus($status, $minutes = 15) {
    file_put_contents('bot_status.json', json_encode([
        'active' => $status,
        'until' => $status ? null : time() + ($minutes * 60)
    ]));
}

function getBotStatus() {
    if (!file_exists('bot_status.json')) return true;

    $data = json_decode(file_get_contents('bot_status.json'), true);
    if ($data['active']) return true;

    if (isset($data['until']) && time() > $data['until']) {
        setBotStatus(true); // Avtomatik tiklanadi
        return true;
    }

    return false;
}

function getMaintenanceRemainingTime() {
    if (!file_exists('bot_status.json')) return null;

    $data = json_decode(file_get_contents('bot_status.json'), true);
    if (isset($data['until'])) {
        $qoldi = $data['until'] - time();
        if ($qoldi < 0) return null;

        $min = floor($qoldi / 60);
        $sec = $qoldi % 60;
        return "$min daqiqa $sec soniya";
    }

    return null;
}

function getProfileButtons(): array {
    return [
        'inline_keyboard' => [
            [['text' => '🎁 Kunlik bonusni olish', 'callback_data' => 'daily_bonus']],
            [['text' => '⬅️ Orqaga', 'callback_data' => 'back_to_menu']]
        ]
    ];
}

function getStarsEarnButtons($refLink) {
    $text = urlencode("📣 Ey do‘stim, super bot topdim!\n\nBu orqali har bir taklif uchun 3⭐ olaman.\nSenga ham tavsiya qilaman — albatta sinab ko‘r! 👇\n\n$refLink");

    
    $shareUrl = "https://t.me/share/url?url=&text=$text";

    return [
        'inline_keyboard' => [
            [
                ['text' => '📤 Do‘stlarga ulashish', 'url' => $shareUrl]
            ],
            [
                ['text' => '🏠 Bosh menyuga qaytish', 'callback_data' => 'back_to_menu']
            ]
        ]
    ];
}

function setUserStep($chat_id, $step) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET step = ? WHERE chat_id = ?");
    $stmt->execute([$step, $chat_id]);
}

function getUserStep($chat_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT step FROM users WHERE chat_id = ?");
    $stmt->execute([$chat_id]);
    $result = $stmt->fetch();
    return $result ? $result['step'] : null;
}

function checkAllMandatorySubscriptions($chat_id) {
    global $pdo;

    $notSubscribed = [];

    
    $stmt1 = $pdo->prepare("SELECT username, link FROM channels WHERE type = 'mandatory'");
    $stmt1->execute();
    $channels1 = $stmt1->fetchAll();

    foreach ($channels1 as $ch) {
        $username = $ch['username'];
        $invite_link = $ch['link'];

        
        $url = "https://api.telegram.org/bot" . API_KEY . "/getChatMember?chat_id=@$username&user_id=$chat_id";
        $res = json_decode(file_get_contents($url), true);

        if (!isset($res['ok']) || !$res['ok'] || !in_array($res['result']['status'], ['member', 'administrator', 'creator'])) {
            $notSubscribed[] = $invite_link;
        }
    }

   
    $stmt2 = $pdo->prepare("SELECT channel_id, invite_link FROM colobar_channels");
    $stmt2->execute();
    $channels2 = $stmt2->fetchAll();

    foreach ($channels2 as $ch) {
        $channel_id = $ch['channel_id'];
        $invite_link = $ch['invite_link'];

       
        $url = "https://api.telegram.org/bot" . API_KEY . "/getChatMember?chat_id=$channel_id&user_id=$chat_id";
        $res = json_decode(file_get_contents($url), true);

        if (!isset($res['ok']) || !$res['ok'] || !in_array($res['result']['status'], ['member', 'administrator', 'creator'])) {
            $notSubscribed[] = $invite_link;
        }
    }

    return $notSubscribed;
}

function check_subscription($chat_id) {
    if ((string)$chat_id === (string)ADMIN_ID) {
        return true;
    }

    $notSubscribed = checkAllMandatorySubscriptions($chat_id);

    if (!empty($notSubscribed)) {
        $text = "🤖 <b>Ey do‘stim, sizni eng zo‘r sovg‘alar kutmoqda!</b> 🎁\n\n"
              . "Lekin avval quyidagi kanallarga obuna bo‘lishingiz kerak! 🫣\n\n"
              . "👇 <b>Obuna bo‘ling</b> va → <b>“✅ Tekshirish”</b> tugmasini bosing!";

        $buttons = ['inline_keyboard' => []];

        foreach ($notSubscribed as $link) {
            $buttons['inline_keyboard'][] = [
                ['text' => "📣 Kanalga o‘tish", 'url' => $link]
            ];
        }

        $buttons['inline_keyboard'][] = [
            ['text' => '✅ Tekshirish', 'callback_data' => 'check_subscription']
        ];

        sendMessage($chat_id, $text, $buttons, 'HTML');
        return false;
    }

    return true;
}

function isUserSubscribed($chat_id, $channel_username) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/getChatMember";
    $data = [
        'chat_id' => "@$channel_username",
        'user_id' => $chat_id
    ];

    $response = file_get_contents($url . '?' . http_build_query($data));
    $result = json_decode($response, true);

    if (!isset($result['ok']) || !$result['ok']) return false;

    $status = $result['result']['status'];
    return in_array($status, ['member', 'creator', 'administrator']);
}

function tasksisUserSubscribed($chat_id, $channel_username) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/getChatMember";
    
    
    $data = [
        'chat_id' => $channel_username,
        'user_id' => $chat_id
    ];

    $response = file_get_contents($url . '?' . http_build_query($data));
    $result = json_decode($response, true);

    if (!isset($result['ok']) || !$result['ok']) return false;

    $status = $result['result']['status'];
    return in_array($status, ['member', 'creator', 'administrator']);
}


function isTestModeOn($pdo) {
    $stmt = $pdo->query("SELECT test_mode FROM settings LIMIT 1");
    return $stmt->fetchColumn() == 1;
}


function getRandomQuizQuestion() {
$quizzes = [

// 🔢 1. Matematika
["5 + 3 = ?", "8"],
["9 - 4 = ?", "5"],
["6 * 2 = ?", "12"],
["15 / 3 = ?", "5"],
["10 + 10 = ?", "20"],
["7 * 1 = ?", "7"],
["11 - 6 = ?", "5"],
["9 + 2 = ?", "11"],
["16 / 4 = ?", "4"],
["2 * 4 = ?", "8"],
["8 + 7 = ?", "15"],
["12 - 5 = ?", "7"],
["3 * 3 = ?", "9"],
["18 / 6 = ?", "3"],
["20 - 8 = ?", "12"],
["4 + 6 = ?", "10"],
["14 / 2 = ?", "7"],
["9 * 1 = ?", "9"],
["25 - 15 = ?", "10"],
["30 / 5 = ?", "6"],
["5 * 5 = ?", "25"],
["13 + 6 = ?", "19"],
["21 - 9 = ?", "12"],
["8 * 3 = ?", "24"],
["36 / 6 = ?", "6"],
["7 + 8 = ?", "15"],
["10 - 7 = ?", "3"],
["6 * 6 = ?", "36"],
["45 / 5 = ?", "9"],
["18 - 3 = ?", "15"],
["11 + 11 = ?", "22"],
["8 * 2 = ?", "16"],
["27 / 3 = ?", "9"],
["19 - 14 = ?", "5"],
["3 + 4 = ?", "7"],
["10 * 2 = ?", "20"],
["24 / 4 = ?", "6"],
["32 - 12 = ?", "20"],
["6 + 9 = ?", "15"],
["7 * 2 = ?", "14"],
["12 / 3 = ?", "4"],
["9 + 5 = ?", "14"],
["13 - 6 = ?", "7"],
["8 * 1 = ?", "8"],
["40 / 8 = ?", "5"],
["14 + 7 = ?", "21"],
["15 - 9 = ?", "6"],
["9 * 3 = ?", "27"],
["50 / 5 = ?", "10"],
["6 + 6 = ?", "12"],

// 🍏 2. Emoji savollar
["Ushbu mevani yozing: 🍎", "Olma"],
["Bu meva nima: 🍌", "Banan"],
["Bu hayvon: 🐶", "It"],
["Bu meva: 🍇", "Uzum"],
["Bu ob-havo belgisi: ☀️", "Quyosh"],
["Bu qanday ichimlik: ☕", "Choy"],
["Bu transport vositasi: 🚗", "Mashina"],
["Bu qaysi sport turi: ⚽", "Futbol"],
["Bu davlat bayrog‘i: 🇺🇿", "O‘zbekiston,Uzbekistan"],
["Bu meva: 🍓", "Qulupnay"],
["Bu meva: 🍍", "Ananas"],
["Bu hayvon: 🐱", "Mushuk"],
["Bu hayvon: 🐰", "Quyon"],
["Bu ob-havo: 🌧️", "Yomg‘ir"],
["Bu transport: 🚲", "Velosiped"],
["Bu sport turi: 🏀", "Basketbol"],
["Bu meva: 🍒", "Gilos"],
["Bu bayroq: 🇺🇸", "Amerika,USA"],
["Bu hayvon: 🐮", "Sigir"],
["Bu meva: 🍑", "Shaftoli"],
["Bu hayvon: 🐸", "Qurbaqa"],
["Bu ob-havo: ❄️", "Qor"],
["Bu ichimlik: 🥛", "Sut"],
["Bu transport: 🚕", "Taksi"],
["Bu hayvon: 🦁", "Sher"],
["Bu meva: 🍋", "Limon"],
["Bu hayvon: 🐔", "Tovuq"],
["Bu sport turi: 🏸", "Badminton"],
["Bu transport: ✈️", "Samolyot"],
["Bu meva: 🥝", "Kivi"],
["Bu hayvon: 🐷", "Cho‘chqa"],
["Bu ob-havo: 🌥️", "Bulutli"],
["Bu ichimlik: 🍹", "Kokteyl"],
["Bu sport: 🥊", "Boks"],
["Bu meva: 🍊", "Apelsin"],
["Bu bayroq: 🇷🇺", "Rossiya"],
["Bu hayvon: 🐵", "Maymun"],
["Bu meva: 🍈", "Qovun"],
["Bu hayvon: 🐢", "Toshbaqa"],
["Bu transport: 🚤", "Qayiq"],
["Bu sport turi: 🏐", "Voleybol"],
["Bu ob-havo: 🌈", "Kamalak"],
["Bu ichimlik: 🧃", "Sok"],
["Bu meva: 🥭", "Mango"],
["Bu bayroq: 🇹🇷", "Turkiya"],

];

return $quizzes[array_rand($quizzes)];
}

$bot_token="8276913331:AAGURqbJZ2tCgk3_Z53qWpeEQzjdkWnHPlg";
function sendMessageToChannel($channel_link, $text, $keyboard = null) {
    global $bot_token;

    $channel_id = str_replace(['https://t.me/', '@'], '', $channel_link);
    if (!str_starts_with($channel_id, '-')) {
        $channel_id = '@' . $channel_id;
    }

    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

    $data = [
        'chat_id' => $channel_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    error_log("Telegram javobi (channel): " . $response);
}










function getUserInfo($chatId) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAdFromAdexiumMultiLang($telegramId, $firstName = 'User') {
    $url = 'https://bid.tgads.live/bot-request';
    $languages = ['ru', 'en', 'id', 'tr', 'de', 'es', 'fr', 'ar', 'it', 'uz'];

    foreach ($languages as $lang) {
        $data = [
            'wid' => '9366eef0-d0ec-430e-ab2f-8c4c87b9fb87',
            'language' => $lang,
            'isPremium' => false,
            'firstName' => $firstName ?: 'User',
            'telegramId' => (string)$telegramId,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if (is_array($decoded) && !empty($decoded)) {
            // Log uchun yozamiz
            file_put_contents("adexium_log_$lang.json", json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if (isset($decoded[0]) && is_array($decoded[0])) {
                return $decoded[0]; // Birinchi reklamani qaytaramiz
            } else {
                return $decoded; // Yagona reklama
            }
        }
    }

    return null; // Hech bir til bo‘yicha reklama topilmadi
}



function sendAdToUser($chatId, $ad) {
    if (!isset($ad['text'], $ad['clickUrl'], $ad['buttonText'])) {
        sendMessage($chatId, "🚫 Reklama maʼlumotlari to‘liq emas");
        return;
    }

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => $ad['buttonText'], 'url' => $ad['clickUrl']]
            ]
        ]
    ];

   
    if (isset($ad['image']) && filter_var($ad['image'], FILTER_VALIDATE_URL)) {
        sendPhoto($chatId, $ad['image'], $ad['text'], $keyboard);
    } else {
        sendMessage($chatId, $ad['text'], $keyboard);
    }
}



function sendAd($chatId, $telegramId, $firstName) {
    $ad = getAdFromAdexiumMultiLang($telegramId, $firstName);

    if ($ad && isset($ad['text'], $ad['clickUrl'], $ad['buttonText'])) {
        sendAdToUser($chatId, $ad);
    } else {
        sendMessage($chatId, "⭐️ Yulduz ishlashda davom eting!");
    }
}