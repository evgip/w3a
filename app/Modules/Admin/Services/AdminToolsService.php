<?php
declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Mailer;

/**
 * Сервис для инструментов разработчика.
 */
class AdminToolsService
{
    /**
     * Принудительная сборка CSS-ассетов.
     */
    public function compileAssets(): void
    {
        \App\Core\Asset::forceRebuild();
        Audit::log('admin.assets_compile', 'Администратор запустил ручную сборку CSS ассетов через панель инструментов');
    }
    
    /**
     * Очистка текстовых файлов логов.
     *
     * @return int Количество очищенных файлов
     */
    public function clearFileLogs(): int
    {
        $logDir = dirname(__DIR__, 4) . '/storage/logs/';
        $files = ['app.log', 'audit.log'];
        $clearedCount = 0;
        
        foreach ($files as $file) {
            $filePath = $logDir . $file;
            if (file_exists($filePath)) {
                file_put_contents($filePath, '');
                $clearedCount++;
            }
        }
        
        Audit::log('admin.tools_clear_files', 'Администратор очистил текстовые файлы системных логов на диске');
        
        return $clearedCount;
    }
    
    /**
     * Скомпилировать кэш маршрутов.
     */
    public function cacheRoutes(object $router): void
    {
        $router->compileCache();
        Audit::log('admin.cache_routes_compiled', 'Администратор скомпилировал кэш маршрутов фреймворка');
    }
    
    /**
     * Очистить кэш маршрутов.
     */
    public function clearCacheRoutes(object $router): void
    {
        $router->clearCache();
        Audit::log('admin.cache_routes_cleared', 'Администратор полностью удалил кэш маршрутов');
    }
    
    /**
     * Отправить тестовое письмо.
     *
     * @return string|null Сообщение об ошибке, или null при успехе
     */
    public function sendTestEmail(string $email): ?string
    {
        $subject = 'Тестовое письмо — проверка настроек почты';
        $message = 'Это тестовое письмо для проверки работоспособности настроек почты в системе.';
        
        try {
            $success = Mailer::send($email, $subject, $message);
            
            if ($success) {
                Audit::log('admin.test_email_sent', "Администратор отправил тестовое письмо на {$email}");
                return null;
            }
            
            return 'Не удалось отправить тестовое письмо. Проверьте настройки PHP mail() или SMTP.';
        } catch (\Exception $e) {
            return 'Ошибка при отправке письма: ' . $e->getMessage();
        }
    }
}