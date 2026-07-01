-- =========================================================================
-- WIKI PAGES - Wiki страницы
-- =========================================================================

CREATE TABLE `wiki_pages` (
    `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
    `tag_id` int UNSIGNED DEFAULT NULL COMMENT 'Связь с тегом (опционально)',
    `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Markdown контент',
    `rendered_content` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Кэшированный HTML',
    `author_id` int UNSIGNED NOT NULL COMMENT 'Автор страницы',
    `is_primary` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Основная страница тега',
    `status` enum('draft','published','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
    `view_count` int UNSIGNED NOT NULL DEFAULT '0',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_tag_slug` (`tag_id`, `slug`),
    KEY `wiki_pages_tag_id` (`tag_id`),
    KEY `wiki_pages_author_id` (`author_id`),
    KEY `wiki_pages_status` (`status`),
    CONSTRAINT `fk_wiki_pages_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_wiki_pages_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- WIKI REVISIONS - История изменений wiki страниц
-- =========================================================================

CREATE TABLE `wiki_revisions` (
    `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
    `wiki_page_id` int UNSIGNED NOT NULL,
    `revision_number` int UNSIGNED NOT NULL,
    `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `edit_summary` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_id` int UNSIGNED NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `wiki_revisions_unique` (`wiki_page_id`, `revision_number`),
    KEY `wiki_revisions_user_id` (`user_id`),
    CONSTRAINT `fk_wiki_revisions_page` FOREIGN KEY (`wiki_page_id`) REFERENCES `wiki_pages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wiki_revisions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- WIKI PERMISSIONS - Права доступа к wiki для тегов
-- =========================================================================

CREATE TABLE `wiki_permissions` (
    `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
    `tag_id` int UNSIGNED NOT NULL,
    `user_id` int UNSIGNED NOT NULL,
    `can_edit` tinyint(1) NOT NULL DEFAULT '1',
    `can_delete` tinyint(1) NOT NULL DEFAULT '0',
    `granted_by` int UNSIGNED NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `wiki_permissions_unique` (`tag_id`, `user_id`),
    KEY `wiki_permissions_user_id` (`user_id`),
    CONSTRAINT `fk_wiki_permissions_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wiki_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wiki_permissions_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;