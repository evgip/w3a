--
-- Структура таблицы `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Guest',
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'guest',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `role`, `ip_address`, `action`, `description`, `payload`, `created_at`) VALUES
(1, NULL, 'Guest', 'guest', '127.0.0.1', 'auth.login_failed', 'Неудачная попытка входа в систему', '{\"attempted_email\":\"admin@example.com\"}', '2026-06-08 23:30:23'),
(2, NULL, 'Guest', 'guest', '127.0.0.1', 'auth.login_failed', 'Неудачная попытка входа в систему', '{\"attempted_email\":\"admin@example.com\"}', '2026-06-08 23:30:44'),
(3, NULL, 'Guest', 'guest', '127.0.0.1', 'auth.login_failed', 'Неудачная попытка входа в систему', '{\"attempted_email\":\"admin@example.com\"}', '2026-06-08 23:31:21');

-- --------------------------------------------------------

--
-- Структура таблицы `banned_ips`
--

CREATE TABLE `banned_ips` (
  `id` int UNSIGNED NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `comments`
--

CREATE TABLE `comments` (
  `id` int UNSIGNED NOT NULL,
  `story_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `parent_id` int UNSIGNED DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `comments`
--

INSERT INTO `comments` (`id`, `story_id`, `user_id`, `parent_id`, `comment`, `score`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, NULL, 'Отличная архитектура у фреймворка!', 1, '2026-06-09 01:20:16', '2026-06-09 01:20:16', NULL),
(2, 1, 1, 1, 'Согласен, один SQL-запрос на дерево — это быстро.', 1, '2026-06-09 01:20:16', '2026-06-09 02:08:52', NULL),
(3, 1, 1, 2, 'И рекурсия на анонимных функциях PHP выглядит лаконично.', 1, '2026-06-09 01:20:16', '2026-06-09 01:20:16', NULL),
(4, 1, 1, NULL, 'А когда мы добавим форму отправки нового комментария?', 1, '2026-06-09 01:20:16', '2026-06-09 01:20:16', NULL),
(5, 2, 1, NULL, 'Добавим первые комментарий 333', 1, '2026-06-09 01:51:00', '2026-06-09 02:28:13', NULL),
(6, 2, 1, 5, 'Добавим ответ Админу', 2, '2026-06-09 01:51:12', '2026-06-09 02:28:01', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `conversations`
--

CREATE TABLE `conversations` (
  `id` int UNSIGNED NOT NULL,
  `user_one` int UNSIGNED NOT NULL,
  `user_two` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `email_activations`
--

CREATE TABLE `email_activations` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `messages`
--

CREATE TABLE `messages` (
  `id` int UNSIGNED NOT NULL,
  `conversation_id` int UNSIGNED NOT NULL,
  `sender_id` int UNSIGNED NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int UNSIGNED NOT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int UNSIGNED NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint_action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `rate_limits`
--

INSERT INTO `rate_limits` (`id`, `ip_address`, `endpoint_action`, `created_at`) VALUES
(158, '127.0.0.1', 'auth.submit', '2026-06-14 03:31:51'),
(154, '127.0.0.1', 'global.get', '2026-06-14 03:31:44'),
(155, '127.0.0.1', 'global.get', '2026-06-14 03:31:46'),
(156, '127.0.0.1', 'global.get', '2026-06-14 03:31:48'),
(157, '127.0.0.1', 'global.get', '2026-06-14 03:31:49'),
(159, '127.0.0.1', 'global.get', '2026-06-14 03:31:54'),
(160, '127.0.0.1', 'global.get', '2026-06-14 03:31:56'),
(161, '127.0.0.1', 'global.get', '2026-06-14 03:32:02'),
(162, '127.0.0.1', 'global.get', '2026-06-14 03:32:04'),
(163, '127.0.0.1', 'global.get', '2026-06-14 03:32:07'),
(164, '127.0.0.1', 'global.get', '2026-06-14 03:32:15'),
(165, '127.0.0.1', 'global.get', '2026-06-14 03:32:17'),
(166, '127.0.0.1', 'global.get', '2026-06-14 03:32:25'),
(167, '127.0.0.1', 'global.get', '2026-06-14 03:32:28'),
(168, '127.0.0.1', 'global.get', '2026-06-14 03:32:36'),
(169, '127.0.0.1', 'global.get', '2026-06-14 03:32:38'),
(170, '127.0.0.1', 'global.get', '2026-06-14 03:32:47'),
(171, '127.0.0.1', 'global.get', '2026-06-14 03:32:49'),
(172, '127.0.0.1', 'global.get', '2026-06-14 03:32:49'),
(173, '127.0.0.1', 'global.get', '2026-06-14 03:32:57'),
(174, '127.0.0.1', 'global.get', '2026-06-14 03:32:59'),
(175, '127.0.0.1', 'global.get', '2026-06-14 03:33:02'),
(176, '127.0.0.1', 'global.get', '2026-06-14 03:33:07'),
(177, '127.0.0.1', 'global.get', '2026-06-14 03:33:10'),
(178, '127.0.0.1', 'global.get', '2026-06-14 03:33:18'),
(179, '127.0.0.1', 'global.get', '2026-06-14 03:33:20'),
(180, '127.0.0.1', 'global.get', '2026-06-14 03:33:29'),
(181, '127.0.0.1', 'global.get', '2026-06-14 03:33:31'),
(182, '127.0.0.1', 'global.get', '2026-06-14 03:33:39'),
(183, '127.0.0.1', 'global.get', '2026-06-14 03:33:40'),
(184, '127.0.0.1', 'global.get', '2026-06-14 03:33:41'),
(185, '127.0.0.1', 'global.get', '2026-06-14 03:33:41'),
(186, '127.0.0.1', 'global.get', '2026-06-14 03:33:43'),
(187, '127.0.0.1', 'global.get', '2026-06-14 03:33:43'),
(188, '127.0.0.1', 'global.get', '2026-06-14 03:33:43'),
(190, '127.0.0.1', 'global.get', '2026-06-14 03:33:46'),
(191, '127.0.0.1', 'global.get', '2026-06-14 03:33:46'),
(192, '127.0.0.1', 'global.get', '2026-06-14 03:33:46'),
(193, '127.0.0.1', 'global.get', '2026-06-14 03:33:49'),
(194, '127.0.0.1', 'global.get', '2026-06-14 03:33:49'),
(195, '127.0.0.1', 'global.get', '2026-06-14 03:33:49'),
(196, '127.0.0.1', 'global.get', '2026-06-14 03:33:49'),
(197, '127.0.0.1', 'global.get', '2026-06-14 03:33:58'),
(198, '127.0.0.1', 'global.get', '2026-06-14 03:33:58'),
(199, '127.0.0.1', 'global.get', '2026-06-14 03:33:58'),
(200, '127.0.0.1', 'global.get', '2026-06-14 03:33:59'),
(201, '127.0.0.1', 'global.get', '2026-06-14 03:34:02'),
(202, '127.0.0.1', 'global.get', '2026-06-14 03:34:08'),
(203, '127.0.0.1', 'global.get', '2026-06-14 03:34:09'),
(189, '127.0.0.1', 'global.post', '2026-06-14 03:33:46');

-- --------------------------------------------------------

--
-- Структура таблицы `stories`
--

CREATE TABLE `stories` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
(1, 1, 'Кастомный PHP фреймворк успешно запущен!', 'https://github.com', NULL, 15, 1, '2026-06-09 00:53:33', '2026-06-09 16:11:07', NULL),
(2, 1, 'Это второй пост для теста', NULL, 'Заполните, если это чисто текстовый пост, либо как дополнение к ссылке. 333', 2, 2, '2026-06-09 01:14:37', '2026-06-14 03:27:15', NULL);

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
(4, 2, 1),
(3, 2, 4);

-- --------------------------------------------------------

--
-- Структура таблицы `tags`
--

CREATE TABLE `tags` (
  `id` int UNSIGNED NOT NULL,
  `tag` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_media` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `tags`
--

INSERT INTO `tags` (`id`, `tag`, `description`, `is_media`, `created_at`, `deleted_at`, `category`) VALUES
(1, 'php', 'Язык1 программирования PHP и фреймворки', 1, '2026-06-09 04:08:44', NULL, 'languages'),
(2, 'security', 'Уязвимости, безопасность и CSP политики', 0, '2026-06-09 04:08:44', NULL, 'practices'),
(3, 'show', 'Демонстрация личных проектов разработчиков', 0, '2026-06-09 04:08:44', NULL, 'format'),
(4, 'video', 'Материал содержит видеоролик', 1, '2026-06-09 04:08:44', NULL, 'format'),
(5, 'libarea', 'Обсуждение сайта...', 0, '2026-06-09 04:46:26', NULL, 'other');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('user','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `bio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`, `updated_at`, `deleted_at`, `bio`, `avatar`, `is_active`) VALUES
(1, 'Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-06-08 17:28:55', '2026-06-14 03:27:25', NULL, 'О себе я расскажуыва ыва ыва 3', 'a6fa1e10b80411629030d4079a5d2253.jpg', 1),
(2, 'test', 'test@test.ru', '$2y$10$xKEu8vvztJ/yoA2yaHHque9z4el8tdWKZDI4SY/AvX3HyyojdHFva', 'user', '2026-06-14 03:31:51', '2026-06-14 03:33:46', NULL, 'sdfsdfsdf', NULL, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Тип объекта (Comment, Message, Story)',
  `notifiable_id` int UNSIGNED DEFAULT NULL COMMENT 'ID связанного объекта',
  `actor_id` int UNSIGNED DEFAULT NULL COMMENT 'ID пользователя, вызвавшего событие',
  `message` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL COMMENT 'Время прочтения',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `votes`
--

CREATE TABLE `votes` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `votable_type` enum('story','comment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `votable_id` int UNSIGNED NOT NULL,
  `vote_type` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `votes`
--

INSERT INTO `votes` (`id`, `user_id`, `votable_type`, `votable_id`, `vote_type`, `created_at`) VALUES
(5, 1, 'comment', 6, 1, '2026-06-09 02:28:01'),
(6, 1, 'story', 2, 1, '2026-06-09 02:28:05');

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
-- Индексы таблицы `banned_ips`
--
ALTER TABLE `banned_ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_address` (`ip_address`),
  ADD KEY `idx_banned_ip_lookup` (`ip_address`);

--
-- Индексы таблицы `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comments_user_id` (`user_id`),
  ADD KEY `idx_comments_story_id` (`story_id`),
  ADD KEY `idx_comments_parent_id` (`parent_id`),
  ADD KEY `idx_comments_deleted_at` (`deleted_at`);
ALTER TABLE `comments` ADD FULLTEXT KEY `idx_comments_search` (`comment`);

--
-- Индексы таблицы `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_user_pair_unique` (`user_one`,`user_two`),
  ADD KEY `fk_conv_user_two` (`user_two`);

--
-- Индексы таблицы `email_activations`
--
ALTER TABLE `email_activations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `fk_email_activation_uid` (`user_id`),
  ADD KEY `idx_activation_token` (`token`);

--
-- Индексы таблицы `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_msg_sender_id` (`sender_id`),
  ADD KEY `idx_msg_lookup` (`conversation_id`,`created_at`);

--
-- Индексы таблицы `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_reset_email_token` (`email`,`token`);

--
-- Индексы таблицы `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_action_time` (`ip_address`,`endpoint_action`,`created_at`);

--
-- Индексы таблицы `stories`
--
ALTER TABLE `stories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stories_user_id` (`user_id`),
  ADD KEY `idx_stories_score` (`score` DESC),
  ADD KEY `idx_stories_deleted_at` (`deleted_at`),
  ADD KEY `idx_stories_created_at` (`created_at` DESC);
ALTER TABLE `stories` ADD FULLTEXT KEY `idx_stories_search` (`title`,`description`);

--
-- Индексы таблицы `taggings`
--
ALTER TABLE `taggings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `story_tag_unique` (`story_id`,`tag_id`),
  ADD KEY `fk_taggings_tag_id` (`tag_id`),
  ADD KEY `idx_taggings_story_id` (`story_id`),
  ADD KEY `idx_taggings_tag_id` (`tag_id`);

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
-- Индексы таблицы `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_notif_lookup` (`user_id`,`is_read`,`created_at` DESC),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`),
  ADD KEY `idx_notifiable` (`notifiable_type`,`notifiable_id`),
  ADD KEY `idx_actor` (`actor_id`);

--
-- Индексы таблицы `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid_type_id_unique` (`user_id`,`votable_type`,`votable_id`),
  ADD KEY `idx_votable_lookup` (`votable_type`,`votable_id`),
  ADD KEY `idx_votes_votable` (`votable_type`,`votable_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `banned_ips`
--
ALTER TABLE `banned_ips`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT для таблицы `email_activations`
--
ALTER TABLE `email_activations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=204;

--
-- AUTO_INCREMENT для таблицы `stories`
--
ALTER TABLE `stories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `taggings`
--
ALTER TABLE `taggings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- Ограничения внешнего ключа таблицы `email_activations`
--
ALTER TABLE `email_activations`
  ADD CONSTRAINT `fk_email_activation_uid` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Ограничения внешнего ключа таблицы `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `fk_notif_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notification_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `fk_poly_votes_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE stories ADD COLUMN user_is_following TINYINT(1) NOT NULL DEFAULT 0 
    COMMENT 'Подписан ли автор на уведомления о новых комментариях';
	
	
ALTER TABLE users 
    ADD COLUMN notify_on_reply TINYINT(1) NOT NULL DEFAULT 1 
        COMMENT 'Уведомлять об ответах на мои комментарии',
    ADD COLUMN notify_on_story_comment TINYINT(1) NOT NULL DEFAULT 1 
        COMMENT 'Уведомлять о комментариях в историях, на которые я подписан',
    ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 0 
        COMMENT 'Дублировать уведомления на email';	
	
CREATE TABLE IF NOT EXISTS tag_filters (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    tag_id      INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uq_user_tag (user_id, tag_id),
    KEY idx_user_id (user_id),
    KEY idx_tag_id (tag_id),
    
    CONSTRAINT fk_tag_filters_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tag_filters_tag FOREIGN KEY (tag_id) 
        REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;	
	
-- Таблица приглашений
CREATE TABLE IF NOT EXISTS `invitations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `inviter_id` INT UNSIGNED NOT NULL COMMENT 'ID пользователя, который пригласил',
    `invitee_email` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email получателя (опционально)',
    `invitee_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID зарегистрировавшегося пользователя',
    `status` ENUM('pending', 'accepted', 'expired', 'revoked') NOT NULL DEFAULT 'pending',
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_code` (`code`),
    KEY `idx_inviter` (`inviter_id`),
    KEY `idx_invitee` (`invitee_id`),
    KEY `idx_status` (`status`),
    KEY `idx_expires` (`expires_at`),
    CONSTRAINT `fk_invitations_inviter` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invitations_invitee` FOREIGN KEY (`invitee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица запросов приглашений
CREATE TABLE IF NOT EXISTS `invitation_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `reason` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Причина запроса',
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `ip_address` VARCHAR(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- ФАЙЛ: db/migrations/001_add_moderation.sql
-- ============================================

-- 1. Расширяем enum role в таблице users
ALTER TABLE `users` 
MODIFY COLUMN `role` ENUM('user','moderator','admin') 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user';

-- 2. Таблица модераторских заметок о пользователях
CREATE TABLE `mod_notes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL COMMENT 'Пользователь, к которому относится заметка',
    `moderator_id` INT UNSIGNED NOT NULL COMMENT 'Автор заметки',
    `note` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `is_private` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=видна только модераторам, 0=публичная',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_moderator_id` (`moderator_id`),
    INDEX `idx_is_private` (`is_private`),
    CONSTRAINT `fk_mod_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mod_notes_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Таблица лога модераторских действий
CREATE TABLE `moderations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `moderator_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL 
        COMMENT 'Тип: ban, unban, delete_story, warn, mute и т.д.',
    `target_type` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL 
        COMMENT 'Тип объекта: user, story, comment',
    `target_id` INT UNSIGNED NOT NULL COMMENT 'ID объекта воздействия',
    `reason` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_moderator_id` (`moderator_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_target` (`target_type`, `target_id`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_moderations_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Таблица статистики активности модераторов
CREATE TABLE `mod_activity` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `moderator_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `date` DATE NOT NULL,
    `count` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_mod_action_date` (`moderator_id`, `action`, `date`),
    INDEX `idx_date` (`date`),
    CONSTRAINT `fk_mod_activity_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;