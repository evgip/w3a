<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Container;
use App\Modules\Admin\Models\AdminUser;
use App\Modules\Admin\Models\AuditLog;
use App\Modules\Admin\Services\AdminUserService;
use App\Modules\Admin\Services\AdminTagService;
use App\Modules\Admin\Services\AdminCategoryService;
use App\Modules\Admin\Services\AdminAuditService;
use App\Modules\Admin\Services\AdminToolsService;
use App\Modules\Admin\Services\AdminFirewallService;
use App\Modules\Admin\Services\AdminInvitationService;
use App\Modules\Users\Models\User;
use App\Modules\Users\Models\Notification;
use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Models\Category;
use App\Modules\Invitations\Models\InvitationRequest;

/**
 * Провайдер сервисов модуля Admin.
 * 
 * Регистрирует все административные сервисы и их зависимости.
 */
class ModuleServiceProvider
{
    public function register(Container $container): void
    {
        // === МОДЕЛИ ===

        $container->singleton(AdminUser::class, fn() => new AdminUser());
        $container->singleton(AuditLog::class, fn() => new AuditLog());

        // Cross-module модели
        // TODO: перенести в соответствующие модули когда появятся их провайдеры
        $container->singleton(Tag::class, fn() => new Tag());
        $container->singleton(Category::class, fn() => new Category());
        $container->singleton(InvitationRequest::class, fn() => new InvitationRequest());

        // === СЕРВИСЫ ===

        $container->singleton(AdminUserService::class, function (Container $c) {
            return new AdminUserService(
                $c->get(User::class),
                $c->get(AdminUser::class),
                $c->get(Notification::class)
            );
        });

        $container->singleton(AdminTagService::class, function (Container $c) {
            return new AdminTagService(
                $c->get(Tag::class),
                $c->get(Category::class)
            );
        });

        $container->singleton(AdminCategoryService::class, function (Container $c) {
            return new AdminCategoryService(
                $c->get(Category::class)
            );
        });

        $container->singleton(AdminAuditService::class, function (Container $c) {
            return new AdminAuditService(
                $c->get(AuditLog::class)
            );
        });

        $container->singleton(AdminToolsService::class, fn() => new AdminToolsService());

        $container->singleton(AdminFirewallService::class, fn() => new AdminFirewallService());

        $container->singleton(AdminInvitationService::class, function (Container $c) {
            return new AdminInvitationService(
                $c->get(InvitationRequest::class)
            );
        });
    }
}
