<?php

if ($callback && $cid != ADMIN_ID) {
    if (!getBotStatus()) {
        $qoldi = getMaintenanceRemainingTime();
        $timeText = $qoldi ? "<b>$qoldi</b> kutishingiz kerak 😊" : "qisqa vaqt kutishingiz kerak 😊";

        sendMessage($cid, "🔧 <b>Texnik yangilanish!⚡</b>\n\n" .
            "👨‍💻 Hozirda botni yanada yaxshiroq qilish uchun ishlamoqdamiz...\n" .
            "⏰ Atigi $timeText\n\n" .
            "🎯 <b>Maqsad:</b> sizga tezroq va sifatliroq xizmat berish!\n\n" .
            "💬 <b>Savol bo‘lsa:</b> @Bunyod_0528\n\n" .
            "💪 <i>Sabr-toqatingiz uchun rahmat!</i>\n" .
            "🚀 <b>Tez orada qaytamiz!</b>", null);
        exit;
    }
}

// texnik ish matni
if ($message && $cid != ADMIN_ID) {
    if (!getBotStatus()) {
        $qoldi = getMaintenanceRemainingTime();
        $timeText = $qoldi ? "<b>$qoldi</b> kutishingiz kerak 😊" : "qisqa vaqt kutishingiz kerak 😊";

        sendMessage($cid, "🔧 <b>Texnik yangilanish!⚡</b>\n\n" .
            "👨‍💻 Hozirda botni yanada yaxshiroq qilish uchun ishlamoqdamiz...\n" .
            "⏰ Atigi $timeText\n\n" .
            "🎯 <b>Maqsad:</b> sizga tezroq va sifatliroq xizmat berish!\n\n" .
            "💬 <b>Savol bo‘lsa:</b> @Bunyod_0528\n\n" .
            "💪 <i>Sabr-toqatingiz uchun rahmat!</i>\n" .
            "🚀 <b>Tez orada qaytamiz!</b>", null);
        exit;
    }
}