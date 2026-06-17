<?php

// Dashboard Main Overview Panel
$router->add('GET', 'admin', 'AdminController@index');

// View Detailed Statistics Metrics Profile
$router->add('GET', 'admin/users', 'AdminController@users', 'admin.users');

$router->add('POST', 'admin/users/{id}/toggle-status', 'AdminController@toggleUserStatus', 'admin.users.toggle_status');

$router->add('GET', 'admin/audit', 'AdminController@auditLogs', 'admin.tools.audit');


// NEW: User status manipulation routes
$router->add('POST', 'admin/users/{id}/archive', 'AdminController@archiveUser', 'admin.users.archive');
$router->add('POST', 'admin/users/{id}/restore', 'AdminController@restoreUser', 'admin.users.restore');

// ИНСТРУМЕНТЫ АДМИНИСТРАТОРА
$router->add('GET', 'admin/tools', 'AdminController@tools', 'admin.tools');
$router->add('POST', 'admin/tools/compile-assets', 'AdminController@compileAssets', 'admin.tools.compile_assets');

// ОЧИСТКА СИСТЕМНЫХ ДАННЫХ И ЛОГОВ
$router->add('POST', 'admin/tools/clear-file-logs', 'AdminController@clearFileLogs', 'admin.tools.clear_file_logs');
$router->add('POST', 'admin/tools/clear-db-audit', 'AdminController@clearDbAudit', 'admin.tools.clear_db_audit');


// Admin Tag CRUD Management Routes
$router->add('GET', 'admin/tags', 'AdminController@tagsIndex', 'admin.tags');
$router->add('GET', 'admin/tags/create', 'AdminController@showTagCreateForm', 'admin.tags.create');
$router->add('POST', 'admin/tags/create', 'AdminController@createTag', 'admin.tags.create.submit');
$router->add('GET', 'admin/tags/{id}/edit', 'AdminController@showTagEditForm', 'admin.tags.edit');
$router->add('POST', 'admin/tags/{id}/edit', 'AdminController@updateTag', 'admin.tags.edit.submit');

// Administrative Profile Overrides & Avatar Deletion endpoints
$router->add('GET', 'admin/users/{id}/edit', 'AdminController@editUser', 'admin.users.edit');
$router->add('POST', 'admin/users/{id}/edit', 'AdminController@updateUser', 'admin.users.edit.submit');
$router->add('POST', 'admin/users/{id}/avatar/delete', 'AdminController@deleteUserAvatar', 'admin.users.avatar.delete');

$router->add('POST', 'admin/tools/cache-routes', 'AdminController@cacheRoutes', 'admin.tools.cache_routes');
$router->add('POST', 'admin/tools/clear-cache-routes', 'AdminController@clearCacheRoutes', 'admin.tools.clear_cache_routes');

$router->add('POST', 'admin/tools/send-test-email', 'AdminController@sendTestEmail', 'admin.tools.send_test_email');


// Real-time security alert telemetry payload lookup endpoint
$router->add('GET', 'api/admin/security-alerts', 'AdminController@getSecurityAlertsApi', 'api.admin.security_alerts');


$router->add('GET', 'admin/firewall', 'AdminController@firewallIndex', 'admin.firewall');
$router->add('POST', 'admin/firewall/ban', 'AdminController@banIp', 'admin.firewall.ban');
$router->add('POST', 'admin/firewall/{id}/unban', 'AdminController@unbanIp', 'admin.firewall.unban');


// ==================== INVITATION REQUESTS MANAGEMENT ====================

$router->add('GET', 'admin/invitations', 'AdminController@invitationsIndex', 'admin.invitations');
$router->add('POST', 'admin/invitations/{id}/approve', 'AdminController@approveInvitation', 'admin.invitations.approve');
$router->add('POST', 'admin/invitations/{id}/reject', 'AdminController@rejectInvitation', 'admin.invitations.reject');


// Admin Category CRUD Management Routes
$router->add('GET', 'admin/categories', 'AdminController@categoriesIndex', 'admin.categories');
$router->add('GET', 'admin/categories/create', 'AdminController@showCategoryCreateForm', 'admin.categories.create');
$router->add('POST', 'admin/categories/create', 'AdminController@createCategory', 'admin.categories.create.submit');
$router->add('GET', 'admin/categories/{id}/edit', 'AdminController@showCategoryEditForm', 'admin.categories.edit');
$router->add('POST', 'admin/categories/{id}/edit', 'AdminController@updateCategory', 'admin.categories.edit.submit');
$router->add('POST', 'admin/categories/{id}/delete', 'AdminController@deleteCategory', 'admin.categories.delete');