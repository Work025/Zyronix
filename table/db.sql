CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `chat_id` BIGINT(20) NOT NULL,
  `first_name` VARCHAR(255) NOT NULL,
  `last_name` VARCHAR(255) DEFAULT '',
  `username` VARCHAR(255) DEFAULT '',
  `last_task_version` INT DEFAULT 0,
  `temp_data` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `left_at` DATETIME DEFAULT NULL,
  `balance` DECIMAL(10,2) DEFAULT 0,  --  Float summalar uchun o‘zgartirildi
  `ref_count` INT(11) DEFAULT 0,
  `ref_verified_count` INT(11) DEFAULT 0,
  `referred` TINYINT(1) DEFAULT 0, --  Tasdiqlangan foydalanuvchimi (0 yoki 1)
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_active_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_bonus_date` DATE DEFAULT NULL,
  `referral_id` BIGINT(20) DEFAULT NULL,
  `step` VARCHAR(64) DEFAULT NULL,

  --  Vaqtli mukofot (quiz) bo‘limi uchun qo‘shilgan ustunlar
  `last_quiz_at` DATETIME DEFAULT NULL,         -- So‘nggi test ishtiroki vaqti
  `quiz_question` TEXT DEFAULT NULL,            -- Yuborilgan savol
  `quiz_answer` TEXT DEFAULT NULL,              -- To‘g‘ri javob

  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_id` (`chat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `channels` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(255) NOT NULL,
  `link` VARCHAR(255) NOT NULL,
  `type` ENUM('mandatory', 'additional') NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `promocodes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(255) NOT NULL UNIQUE,
  `stars` INT NOT NULL DEFAULT 0,
  `max_uses` INT DEFAULT NULL, -- nechta foydalanuvchi ishlatishi mumkin
  `used_count` INT DEFAULT 0,  -- hozircha nechta ishlatilgan
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_promocodes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT NOT NULL,
  `promo_id` INT NOT NULL,
  `used_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (`user_id`, `promo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `test_mode` TINYINT(1) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS tasks_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_name VARCHAR(255) NOT NULL,
    version INT DEFAULT 1,
    channel_link VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS withdraw_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id BIGINT NOT NULL,
    username VARCHAR(64),
    amount FLOAT NOT NULL,
    gift_emoji VARCHAR(10),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    message_id INT, -- kanalga yuborilgan xabar ID
    channel_link VARCHAR(255) -- kanal manzili
);


CREATE TABLE IF NOT EXISTS withdraw_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_link VARCHAR(255) NOT NULL
);
CREATE TABLE IF NOT EXISTS ref_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_chat_id BIGINT NOT NULL,
    referred_chat_id BIGINT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS colobar_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invite_link VARCHAR(255) DEFAULT NULL,
    channel_id BIGINT UNIQUE,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP
);