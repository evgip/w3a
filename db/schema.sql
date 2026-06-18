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
(1, NULL, 'Guest', 'guest', '127.0.0.1', 'auth.login_failed', 'Неудачная попытка входа в систему', '{\"attempted_email\":\"admin@example.com\"}', '2026-06-08 11:30:23'),
(2, NULL, 'Guest', 'guest', '127.0.0.1', 'auth.login_failed', 'Неудачная попытка входа в систему', '{\"attempted_email\":\"admin@example.com\"}', '2026-06-08 11:30:44'),
(3, NULL, 'Guest', 'guest', '127.0.0.1', 'auth.login_failed', 'Неудачная попытка входа в систему', '{\"attempted_email\":\"admin@example.com\"}', '2026-06-08 11:31:21');

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
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Языки программирования', 'languages', 'PHP, Python, JavaScript и другие', 10, '2026-06-17 01:10:39', '2026-06-17 01:10:39'),
(2, 'Практики', 'practices', 'Безопасность, архитектура, методологии', 20, '2026-06-17 01:10:39', '2026-06-17 01:10:39'),
(3, 'Формат', 'format', 'Видео, демонстрации проектов, подкасты', 30, '2026-06-17 01:10:39', '2026-06-17 01:10:39'),
(4, 'Разное', 'other', 'Общие темы и обсуждения', 99, '2026-06-17 01:10:39', '2026-06-17 01:10:39');

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
  `flag_count` int UNSIGNED NOT NULL DEFAULT '0',
  `is_hidden_by_flags` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `comments`
--

INSERT INTO `comments` (`id`, `story_id`, `user_id`, `parent_id`, `comment`, `score`, `flag_count`, `is_hidden_by_flags`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, NULL, 'Отличная архитектура у фреймворка!', 1, 0, 0, '2026-06-08 13:20:16', '2026-06-08 13:20:16', NULL),
(2, 1, 1, 1, 'Согласен, один SQL-запрос на дерево — это быстро.', 1, 0, 0, '2026-06-08 13:20:16', '2026-06-08 14:08:52', NULL),
(3, 1, 1, 2, 'И рекурсия на анонимных функциях PHP выглядит лаконично.', 1, 0, 0, '2026-06-08 13:20:16', '2026-06-08 13:20:16', NULL),
(4, 1, 1, NULL, 'А когда мы добавим форму отправки нового комментария?', 1, 0, 0, '2026-06-08 13:20:16', '2026-06-08 13:20:16', NULL),
(5, 2, 1, NULL, 'Добавим первые комментарий 333', 1, 0, 0, '2026-06-08 13:51:00', '2026-06-08 14:28:13', NULL),
(6, 2, 1, 5, 'Добавим ответ Админу', 2, 0, 0, '2026-06-08 13:51:12', '2026-06-08 14:28:01', NULL);

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
-- Структура таблицы `domains`
--

CREATE TABLE `domains` (
  `id` int UNSIGNED NOT NULL,
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('banned','allowed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'banned',
  `ban_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banned_by` int UNSIGNED DEFAULT NULL COMMENT 'ID модератора/админа, забанившего домен',
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
-- Структура таблицы `flags`
--

CREATE TABLE `flags` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Автор жалобы',
  `flaggable_type` enum('story','comment') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Тип контента',
  `flaggable_id` int UNSIGNED NOT NULL COMMENT 'ID story или comment',
  `reason` enum('spam','offensive','duplicate','broken_link','misleading','off_topic','illegal','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `comment` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Пояснение от пользователя',
  `status` enum('pending','resolved','dismissed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `resolved_by` int UNSIGNED DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `invitations`
--

CREATE TABLE `invitations` (
  `id` int UNSIGNED NOT NULL,
  `code` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `inviter_id` int UNSIGNED NOT NULL COMMENT 'ID пользователя, который пригласил',
  `invitee_email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email получателя (опционально)',
  `invitee_id` int UNSIGNED DEFAULT NULL COMMENT 'ID зарегистрировавшегося пользователя',
  `status` enum('pending','accepted','expired','revoked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `invitation_requests`
--

CREATE TABLE `invitation_requests` (
  `id` int UNSIGNED NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Причина запроса',
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `moderations`
--

CREATE TABLE `moderations` (
  `id` int UNSIGNED NOT NULL,
  `moderator_id` int UNSIGNED NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Тип: ban, unban, delete_story, warn, mute и т.д.',
  `target_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Тип объекта: user, story, comment',
  `target_id` int UNSIGNED NOT NULL COMMENT 'ID объекта воздействия',
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `mod_activity`
--

CREATE TABLE `mod_activity` (
  `id` int UNSIGNED NOT NULL,
  `moderator_id` int UNSIGNED NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `count` int UNSIGNED NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `mod_notes`
--

CREATE TABLE `mod_notes` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Пользователь, к которому относится заметка',
  `moderator_id` int UNSIGNED NOT NULL COMMENT 'Автор заметки',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=видна только модераторам, 0=публичная',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
(622, '127.0.0.1', 'global.get', '2026-06-18 15:12:26'),
(623, '127.0.0.1', 'global.get', '2026-06-18 15:12:37'),
(624, '127.0.0.1', 'global.get', '2026-06-18 15:12:39'),
(625, '127.0.0.1', 'global.get', '2026-06-18 15:12:46'),
(626, '127.0.0.1', 'global.get', '2026-06-18 15:12:47'),
(627, '127.0.0.1', 'global.get', '2026-06-18 15:12:57'),
(628, '127.0.0.1', 'global.get', '2026-06-18 15:13:08'),
(629, '127.0.0.1', 'global.get', '2026-06-18 15:13:19'),
(630, '127.0.0.1', 'global.get', '2026-06-18 15:13:29');

-- --------------------------------------------------------

--
-- Структура таблицы `read_ribbons`
--

CREATE TABLE `read_ribbons` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `story_id` int UNSIGNED NOT NULL,
  `last_read_comment_id` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'ID последнего прочитанного комментария (0 = история открыта, но комментариев не было)',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Отметки прочитанных историй для индикации новых комментариев';

--
-- Дамп данных таблицы `read_ribbons`
--

INSERT INTO `read_ribbons` (`id`, `user_id`, `story_id`, `last_read_comment_id`, `updated_at`) VALUES
(1, 1, 2, 6, '2026-06-17 07:32:51'),
(2, 1, 1, 4, '2026-06-17 07:32:57');

-- --------------------------------------------------------

--
-- Структура таблицы `stories`
--

CREATE TABLE `stories` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `score` int NOT NULL DEFAULT '1',
  `flag_count` int UNSIGNED NOT NULL DEFAULT '0',
  `is_hidden_by_flags` tinyint(1) NOT NULL DEFAULT '0',
  `comments_count` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `user_is_following` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Подписан ли автор на уведомления о новых комментариях'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `stories`
--

INSERT INTO `stories` (`id`, `user_id`, `title`, `url`, `domain`, `description`, `score`, `flag_count`, `is_hidden_by_flags`, `comments_count`, `created_at`, `updated_at`, `deleted_at`, `user_is_following`) VALUES
(1, 1, 'Первый пост, написал статью про этот движок.', 'https://libarea.ru/articles/2222/reshil-napisat-lobsters-na-php', 'libarea.ru', 'Редирект будет на LibArea, а если нажать на домен, то сортировка публикаций тут по этому домену.', 15, 0, 0, 4, '2026-06-08 12:53:33', '2026-06-17 04:29:13', NULL, 0),
(2, 1, 'Это второй пост для теста', NULL, NULL, 'Заполните, если это чисто текстовый пост, либо как дополнение к ссылке. 333', 2, 0, 0, 2, '2026-06-08 13:14:37', '2026-06-17 04:29:09', NULL, 0);

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
  `category_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `tags`
--

INSERT INTO `tags` (`id`, `tag`, `description`, `is_media`, `created_at`, `deleted_at`, `category_id`) VALUES
(1, 'php', 'Язык1 программирования PHP и фреймворки', 1, '2026-06-08 16:08:44', NULL, 1),
(2, 'security', 'Уязвимости, безопасность и CSP политики', 0, '2026-06-08 16:08:44', NULL, 2),
(3, 'show', 'Демонстрация личных проектов разработчиков', 0, '2026-06-08 16:08:44', NULL, 3),
(4, 'video', 'Материал содержит видеоролик', 1, '2026-06-08 16:08:44', NULL, 3),
(5, 'libarea', 'Обсуждение сайта...', 0, '2026-06-08 16:46:26', NULL, 4);

-- --------------------------------------------------------

--
-- Структура таблицы `tag_filters`
--

CREATE TABLE `tag_filters` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `tag_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('user','moderator','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`, `updated_at`, `deleted_at`, `is_active`) VALUES
(1, 'Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-06-08 05:28:55', '2026-06-17 01:15:11', NULL, 1),
(2, 'test', 'test@test.ru', '$2y$10$xKEu8vvztJ/yoA2yaHHque9z4el8tdWKZDI4SY/AvX3HyyojdHFva', 'user', '2026-06-13 15:31:51', '2026-06-17 01:15:14', NULL, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `user_bans`
--

CREATE TABLE `user_bans` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `banned_by` int UNSIGNED DEFAULT NULL COMMENT 'ID модератора/админа',
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата начала бана',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Дата окончания (NULL = перманентный бан)',
  `unbanned_at` timestamp NULL DEFAULT NULL COMMENT 'Дата досрочного снятия бана',
  `unbanned_by` int UNSIGNED DEFAULT NULL COMMENT 'Кто досрочно разбанил'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Структура таблицы `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` int UNSIGNED NOT NULL COMMENT 'Ссылка на пользователя',
  `bio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'О себе',
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Имя файла аватара',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_profiles`
--

INSERT INTO `user_profiles` (`user_id`, `bio`, `avatar`, `updated_at`) VALUES
(1, 'О себе я расскажуыва ыва ыва 3', 'a6fa1e10b80411629030d4079a5d2253.jpg', '2026-06-17 01:15:11'),
(2, 'sdfsdfsdf', NULL, '2026-06-17 01:15:14');

-- --------------------------------------------------------

--
-- Структура таблицы `user_settings`
--

CREATE TABLE `user_settings` (
  `user_id` int UNSIGNED NOT NULL COMMENT 'Ссылка на пользователя',
  `notify_on_reply` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Уведомлять об ответах на мои комментарии',
  `notify_on_story_comment` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Уведомлять о комментариях в историях, на которые я подписан',
  `email_notifications` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Дублировать уведомления на email',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_settings`
--

INSERT INTO `user_settings` (`user_id`, `notify_on_reply`, `notify_on_story_comment`, `email_notifications`, `updated_at`) VALUES
(1, 1, 1, 1, '2026-06-17 01:15:11'),
(2, 1, 1, 1, '2026-06-17 01:15:14');

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
(5, 1, 'comment', 6, 1, '2026-06-08 14:28:01'),
(6, 1, 'story', 2, 1, '2026-06-08 14:28:05');

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
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_categories_slug` (`slug`),
  ADD KEY `idx_categories_sort` (`sort_order`);

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
-- Индексы таблицы `domains`
--
ALTER TABLE `domains`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_domains_domain` (`domain`),
  ADD KEY `idx_domains_status` (`status`),
  ADD KEY `idx_domains_deleted_at` (`deleted_at`),
  ADD KEY `fk_domains_banned_by` (`banned_by`);

--
-- Индексы таблицы `email_activations`
--
ALTER TABLE `email_activations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `fk_email_activation_uid` (`user_id`),
  ADD KEY `idx_activation_token` (`token`);

--
-- Индексы таблицы `flags`
--
ALTER TABLE `flags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_flags_unique` (`user_id`,`flaggable_type`,`flaggable_id`),
  ADD KEY `idx_flags_status` (`status`),
  ADD KEY `idx_flags_target` (`flaggable_type`,`flaggable_id`),
  ADD KEY `fk_flags_user` (`user_id`),
  ADD KEY `fk_flags_resolved_by` (`resolved_by`),
  ADD KEY `idx_flags_deleted_at` (`deleted_at`);

--
-- Индексы таблицы `invitations`
--
ALTER TABLE `invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_code` (`code`),
  ADD KEY `idx_inviter` (`inviter_id`),
  ADD KEY `idx_invitee` (`invitee_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Индексы таблицы `invitation_requests`
--
ALTER TABLE `invitation_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Индексы таблицы `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_msg_sender_id` (`sender_id`),
  ADD KEY `idx_msg_lookup` (`conversation_id`,`created_at`);

--
-- Индексы таблицы `moderations`
--
ALTER TABLE `moderations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_moderator_id` (`moderator_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_target` (`target_type`,`target_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Индексы таблицы `mod_activity`
--
ALTER TABLE `mod_activity`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_mod_action_date` (`moderator_id`,`action`,`date`),
  ADD KEY `idx_date` (`date`);

--
-- Индексы таблицы `mod_notes`
--
ALTER TABLE `mod_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_moderator_id` (`moderator_id`),
  ADD KEY `idx_is_private` (`is_private`);

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
-- Индексы таблицы `read_ribbons`
--
ALTER TABLE `read_ribbons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_story` (`user_id`,`story_id`),
  ADD KEY `idx_user_updated` (`user_id`,`updated_at`),
  ADD KEY `fk_rr_story` (`story_id`);

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
  ADD KEY `idx_tags_category_id` (`category_id`);

--
-- Индексы таблицы `tag_filters`
--
ALTER TABLE `tag_filters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_tag` (`user_id`,`tag_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_tag_id` (`tag_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Индексы таблицы `user_bans`
--
ALTER TABLE `user_bans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

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
-- Индексы таблицы `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Индексы таблицы `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`user_id`);

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
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- AUTO_INCREMENT для таблицы `domains`
--
ALTER TABLE `domains`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `email_activations`
--
ALTER TABLE `email_activations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `flags`
--
ALTER TABLE `flags`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `invitations`
--
ALTER TABLE `invitations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `invitation_requests`
--
ALTER TABLE `invitation_requests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `moderations`
--
ALTER TABLE `moderations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `mod_activity`
--
ALTER TABLE `mod_activity`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `mod_notes`
--
ALTER TABLE `mod_notes`
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
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=631;

--
-- AUTO_INCREMENT для таблицы `read_ribbons`
--
ALTER TABLE `read_ribbons`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
-- AUTO_INCREMENT для таблицы `tag_filters`
--
ALTER TABLE `tag_filters`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `user_bans`
--
ALTER TABLE `user_bans`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- Ограничения внешнего ключа таблицы `domains`
--
ALTER TABLE `domains`
  ADD CONSTRAINT `fk_domains_banned_by` FOREIGN KEY (`banned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `email_activations`
--
ALTER TABLE `email_activations`
  ADD CONSTRAINT `fk_email_activation_uid` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `flags`
--
ALTER TABLE `flags`
  ADD CONSTRAINT `fk_flags_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_flags_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `invitations`
--
ALTER TABLE `invitations`
  ADD CONSTRAINT `fk_invitations_invitee` FOREIGN KEY (`invitee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_invitations_inviter` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_conversation_id` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msg_sender_id` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `moderations`
--
ALTER TABLE `moderations`
  ADD CONSTRAINT `fk_moderations_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `mod_activity`
--
ALTER TABLE `mod_activity`
  ADD CONSTRAINT `fk_mod_activity_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `mod_notes`
--
ALTER TABLE `mod_notes`
  ADD CONSTRAINT `fk_mod_notes_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mod_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `read_ribbons`
--
ALTER TABLE `read_ribbons`
  ADD CONSTRAINT `fk_rr_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Ограничения внешнего ключа таблицы `tags`
--
ALTER TABLE `tags`
  ADD CONSTRAINT `fk_tags_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `tag_filters`
--
ALTER TABLE `tag_filters`
  ADD CONSTRAINT `fk_tag_filters_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tag_filters_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `fk_notif_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notification_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `fk_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `fk_poly_votes_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

