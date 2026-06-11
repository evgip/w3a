--
-- Структура таблицы `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Guest',
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'guest',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `role`, `ip`, `action`, `description`, `payload`, `created_at`) VALUES
(1, NULL, 'Guest', 'guest', '127.0.0.1', 'auth.login_failed', 'Неудачная попытка входа в систему', '{\"attempted_email\":\"admin@example.com\"}', '2026-06-09 08:30:23'),
(2, NULL, 'Guest', 'guest', '127.0.0.1', 'auth.login_failed', 'Неудачная попытка входа в систему', '{\"attempted_email\":\"admin@example.com\"}', '2026-06-09 08:30:44'),
(3, NULL, 'Guest', 'guest', '127.0.0.1', 'auth.login_failed', 'Неудачная попытка входа в систему', '{\"attempted_email\":\"admin@example.com\"}', '2026-06-09 08:31:21');

-- --------------------------------------------------------

--
-- Структура таблицы `comments`
--

CREATE TABLE `comments` (
  `id` int UNSIGNED NOT NULL,
  `story_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `parent_id` int UNSIGNED DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `comments`
--

INSERT INTO `comments` (`id`, `story_id`, `user_id`, `parent_id`, `comment`, `score`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, NULL, 'Отличная архитектура у фреймворка!', 1, '2026-06-09 10:20:16', '2026-06-09 10:20:16', NULL),
(2, 1, 1, 1, 'Согласен, один SQL-запрос на дерево — это быстро.', 1, '2026-06-09 10:20:16', '2026-06-09 11:08:52', NULL),
(3, 1, 1, 2, 'И рекурсия на анонимных функциях PHP выглядит лаконично.', 1, '2026-06-09 10:20:16', '2026-06-09 10:20:16', NULL),
(4, 1, 1, NULL, 'А когда мы добавим форму отправки нового комментария?', 1, '2026-06-09 10:20:16', '2026-06-09 10:20:16', NULL),
(5, 2, 1, NULL, 'Добавим первые комментарий 333', 1, '2026-06-09 10:51:00', '2026-06-09 11:28:13', NULL),
(6, 2, 1, 5, 'Добавим ответ Админу', 2, '2026-06-09 10:51:12', '2026-06-09 11:28:01', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `conversations`
--

CREATE TABLE `conversations` (
  `id` int UNSIGNED NOT NULL,
  `user_one` int UNSIGNED NOT NULL,
  `user_two` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `messages`
--

CREATE TABLE `messages` (
  `id` int UNSIGNED NOT NULL,
  `conversation_id` int UNSIGNED NOT NULL,
  `sender_id` int UNSIGNED NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `stories`
--

CREATE TABLE `stories` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `score` int NOT NULL DEFAULT '1',
  `comments_count` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `stories`
--

INSERT INTO `stories` (`id`, `user_id`, `title`, `url`, `description`, `score`, `comments_count`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Кастомный PHP фреймворк успешно запущен!', 'https://github.com', NULL, 15, 1, '2026-06-09 09:53:33', '2026-06-10 01:11:07', NULL),
(2, 1, 'Это второй пост для теста', NULL, 'Заполните, если это чисто текстовый пост, либо как дополнение к ссылке.', 2, 2, '2026-06-09 10:14:37', '2026-06-10 01:16:39', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `taggings`
--

CREATE TABLE `taggings` (
  `id` int UNSIGNED NOT NULL,
  `story_id` int UNSIGNED NOT NULL,
  `tag_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `taggings`
--

INSERT INTO `taggings` (`id`, `story_id`, `tag_id`) VALUES
(1, 2, 1),
(2, 2, 4);

-- --------------------------------------------------------

--
-- Структура таблицы `tags`
--

CREATE TABLE `tags` (
  `id` int UNSIGNED NOT NULL,
  `tag` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_media` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `tags`
--

INSERT INTO `tags` (`id`, `tag`, `description`, `is_media`, `created_at`, `deleted_at`, `category`) VALUES
(1, 'php', 'Язык1 программирования PHP и фреймворки', 1, '2026-06-09 13:08:44', NULL, 'languages'),
(2, 'security', 'Уязвимости, безопасность и CSP политики', 0, '2026-06-09 13:08:44', NULL, 'practices'),
(3, 'show', 'Демонстрация личных проектов разработчиков', 0, '2026-06-09 13:08:44', NULL, 'format'),
(4, 'video', 'Материал содержит видеоролик', 1, '2026-06-09 13:08:44', NULL, 'format'),
(5, 'libarea', 'Обсуждение сайта...', 0, '2026-06-09 13:46:26', NULL, 'other');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('user','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `updated_at`, `deleted_at`, `bio`, `avatar`) VALUES
(1, 'Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-06-09 02:28:55', '2026-06-09 15:09:48', NULL, 'О себе я расскажуыва ыва ыва', 'a6fa1e10b80411629030d4079a5d2253.jpg');

-- --------------------------------------------------------

--
-- Структура таблицы `votes`
--

CREATE TABLE `votes` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `votable_type` enum('story','comment') COLLATE utf8mb4_unicode_ci NOT NULL,
  `votable_id` int UNSIGNED NOT NULL,
  `vote_type` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `votes`
--

INSERT INTO `votes` (`id`, `user_id`, `votable_type`, `votable_id`, `vote_type`, `created_at`) VALUES
(5, 1, 'comment', 6, 1, '2026-06-09 11:28:01'),
(6, 1, 'story', 2, 1, '2026-06-09 11:28:05');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Индексы таблицы `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comments_user_id` (`user_id`),
  ADD KEY `idx_comments_story_id` (`story_id`),
  ADD KEY `idx_comments_parent_id` (`parent_id`),
  ADD KEY `idx_comments_deleted_at` (`deleted_at`);

--
-- Индексы таблицы `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_user_pair_unique` (`user_one`,`user_two`),
  ADD KEY `fk_conv_user_two` (`user_two`);

--
-- Индексы таблицы `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_msg_sender_id` (`sender_id`),
  ADD KEY `idx_msg_lookup` (`conversation_id`,`created_at`);

--
-- Индексы таблицы `stories`
--
ALTER TABLE `stories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stories_user_id` (`user_id`),
  ADD KEY `idx_stories_score` (`score` DESC),
  ADD KEY `idx_stories_deleted_at` (`deleted_at`);

--
-- Индексы таблицы `taggings`
--
ALTER TABLE `taggings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `story_tag_unique` (`story_id`,`tag_id`),
  ADD KEY `fk_taggings_tag_id` (`tag_id`);

--
-- Индексы таблицы `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tag` (`tag`),
  ADD KEY `idx_tags_category` (`category`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Индексы таблицы `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid_type_id_unique` (`user_id`,`votable_type`,`votable_id`),
  ADD KEY `idx_votable_lookup` (`votable_type`,`votable_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `stories`
--
ALTER TABLE `stories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `taggings`
--
ALTER TABLE `taggings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_story_id` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conv_user_one` FOREIGN KEY (`user_one`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_conv_user_two` FOREIGN KEY (`user_two`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_conversation_id` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msg_sender_id` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `stories`
--
ALTER TABLE `stories`
  ADD CONSTRAINT `fk_stories_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `taggings`
--
ALTER TABLE `taggings`
  ADD CONSTRAINT `fk_taggings_story_id` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_taggings_tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `fk_poly_votes_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,          -- Поддержка IPv4 и IPv6
  `endpoint_action` VARCHAR(100) NOT NULL,     -- Тип действия (напр., 'global.get' или 'auth.post')
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  -- КРИТИЧЕСКИЙ ИНДЕКС: Обеспечивает мгновенный подсчет скользящего окна за секунды
  INDEX `idx_ip_action_time` (`ip_address`, `endpoint_action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `user_notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,                  -- Target user receiving the alert notification
  `type` VARCHAR(50) NOT NULL,                       -- Severity class type indicator ('info', 'success', 'warning')
  `message` VARCHAR(255) NOT NULL,                   -- The actual readable localized text string payload
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,           -- Reading tracking state flag marker
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `fk_notif_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_user_notif_lookup` (`user_id`, `is_read`, `created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(191) NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_reset_email_token` (`email`, `token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `banned_ips` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL UNIQUE,
  `reason` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_banned_ip_lookup` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 1. Add active status flag tracking down to the core users matrix
ALTER TABLE `users` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 0;

-- 2. Create the specialized email verification tokens matrix partition
CREATE TABLE IF NOT EXISTS `email_activations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  CONSTRAINT `fk_email_activation_uid` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_activation_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Добавляем составной полнотекстовый индекс для поиска по заголовкам и описаниям постов
ALTER TABLE `stories` ADD FULLTEXT INDEX `idx_stories_search` (`title`, `description`);


-- Add a full-text search index to the comments table matrix
ALTER TABLE `comments` ADD FULLTEXT INDEX `idx_comments_search` (`comment`);