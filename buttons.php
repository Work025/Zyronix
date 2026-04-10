<?php

//asosiy menyu tugmalari 
function getMainMenu($chatId = null) {
    $keyboard = [
        [['text' => '⌛️ Vaqtli mukofot', 'callback_data' =>'time_mocus']],
        
        [['text' => '⭐ Yulduz ishlash', 'callback_data' => 'stars_earn']],
        
        [['text' => '💵 Yulduz yechish', 'callback_data' =>'stars_exit'],
         ['text' => '👤 Profilim', 'callback_data' => 'profile']],

        [['text' => '📝 Vazifalar', 'callback_data' => 'tasks'],
         ['text' => "🎟️ Promo-kod", 'callback_data' => 'promokod']],

        [['text' => "📚 Qo'llanma", 'callback_data' => 'manual'],
         ['text' => '🏆 Reyting','callback_data' => 'rating']],

        [['text' => '💬 Sharhlar', 'url' => 'https://t.me/starsuzbot_yangiliklar/155'],

        // 👉 YANGI: yulduz sotib olish tugmasi (lichkaga yo‘naltiradi)
        ['text' => '💸 Yulduz sotib olish', 'url' => 'https://t.me/stars_savdooo?text=Assalomu+alaykum,%0A%0AMenga+yulduz(telegram+stars+%E2%AD%90)+kerak+edi.+Sizdan+qanday+sotib+olishim+mumkin%3F']],
        
        
    ];

    if ($chatId == ADMIN_ID) {
        $keyboard[] = [
            ['text' => '👨‍💻 Admin panel', 'callback_data' => 'admin_panel']
        ];
    }

    return ['inline_keyboard' => $keyboard];
}

//admin panel
function getAdminPanelMenu(): array {
    return [
        'inline_keyboard' => [
            
            [['text' => '📊 Statistika', 'callback_data' => 'stats'],
            ['text' => '🤖 Bot holati', 'callback_data' => 'bot_status']],
            
            
            [['text' => '📤 Xabar yuborish', 'callback_data' => 'broadcast'],
            ['text' => '🛠 Kanallar','callback_data' => 'channel_settings']],

           
            [['text' => '🧑‍💻 Foydalanuvchilarni boshqarish', 'callback_data' => 'user_manage']],
            [[
            'text' => '🎁 Reklama ko‘rib ⭐️ yulduz ishlang',
            'web_app' => ['url' => 'https://6850585f6aad8.myxvest1.ru/reklama.html']] ],
            
            [['text' => '🎁 Promokodlar boshqaruvi', 'callback_data' => 'promocode_manage']],
            
            [['text' => '⚙️ Vazifalar boshqaruvi', 'callback_data' => 'tasks_manage']],
           
    

            
            [
    ['text' => '🧪 Test rejimini yoqish', 'callback_data' => 'enable_test'],
    ['text' => '❌ Test rejimini o‘chirish', 'callback_data' => 'disable_test']
],

            [['text' => '🏠 Asosiy menyuga qaytish', 'callback_data' => 'back_to_menu']]
            
        ]
    ];
}