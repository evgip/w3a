<?php
declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Core\Audit;
use App\Core\Mailer;
use App\Modules\Stories\Models\Comment;

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
	
	/**
	 * Пересчитать confidence_score для пакета комментариев
	 * 
	 * @param int $offset Смещение
	 * @param int $batchSize Размер пакета
	 * @return array ['processed' => int, 'total' => int, 'hasMore' => bool]
	 */
	public function recalculateConfidenceScoreBatch(int $offset, int $batchSize = 1000): array
	{
		try {
			$commentModel = new \App\Modules\Stories\Models\Comment();
			
			// Получаем общее количество
			$total = $commentModel->getCommentsCount();
			
			// Получаем пакет комментариев
			$comments = $commentModel->getCommentsBatch($offset, $batchSize);
			
			$processed = 0;
			
			foreach ($comments as $comment) {
				try {
					// Проверяем существование функции
					if (!function_exists('wilson_score')) {
						throw new \Exception('Функция wilson_score() не найдена. Проверьте app/helpers.php');
					}
					
					$confidenceScore = wilson_score(
						(int)$comment['score'],
						(int)$comment['flag_count']
					);
					
					$commentModel->updateConfidenceScore(
						(int)$comment['id'],
						$confidenceScore
					);
					
					$processed++;
				} catch (\Exception $e) {
					// Логируем ошибку, но продолжаем обработку
					\App\Core\Logger::error('Failed to update confidence score', [
						'comment_id' => $comment['id'],
						'error' => $e->getMessage(),
					]);
				}
			}
			
			$hasMore = ($offset + $processed) < $total;
			
			return [
				'processed' => $processed,
				'total' => $total,
				'hasMore' => $hasMore,
				'nextOffset' => $offset + $processed,
			];
			
		} catch (\Exception $e) {
			\App\Core\Logger::error('Recalculate batch error', [
				'offset' => $offset,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);
			throw $e;
		}
	}
}