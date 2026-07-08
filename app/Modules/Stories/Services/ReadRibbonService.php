<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use App\Modules\Stories\Models\ReadRibbon;
use App\Modules\Auth\Services\Auth;
use App\Core\Session;

/**
 * Сервис для работы с отметками прочитанного.
 */
class ReadRibbonService
{
    private ReadRibbon $readRibbon;
    private Session $session;

    public function __construct(ReadRibbon $readRibbon, Session $session)
    {
        $this->readRibbon = $readRibbon;
        $this->session = $session;
    }

    /**
     * Обрабатывает отметку прочитанного при просмотре истории.
     *
     * @param int $storyId ID истории
     * @return int Количество новых комментариев
     */
    public function handleStoryView(int $storyId): int
    {
        if (!Auth::check()) {
            return 0;
        }

        $userId = Auth::id();

        // 1. Получаем количество новых
        $newCount = $this->readRibbon->getNewCommentsCount($userId, $storyId);

        // 2. Синхронизация (если счётчик показывает новые, но их нет)
        if ($newCount > 0) {
            $realNewCount = $this->readRibbon->countRealNewComments($storyId, $userId);

            if ($realNewCount === 0) {
                $this->readRibbon->syncForUserAndStory($userId, $storyId);
                $newCount = 0;
            }
        }

        // 3. Показываем flash-сообщение
        if ($newCount > 0) {
            $word = $this->pluralizeComment($newCount);
            // ✅ Используем внедрённый Session
            $this->session->flash('info', "Вы пропустили {$newCount} {$word}.");
        }

        // 4. Отмечаем как прочитанное
        $this->readRibbon->syncForUserAndStory($userId, $storyId);

        return $newCount;
    }

    /**
     * Отмечает историю как прочитанную.
     */
    public function markAsRead(int $storyId): void
    {
        if (!Auth::check()) {
            return;
        }

        $this->readRibbon->syncForUserAndStory(Auth::id(), $storyId);
        // ✅ Используем внедрённый Session
        $this->session->flash('success', 'История отмечена как прочитанная.');
    }

    /**
     * Склонение слова "комментарий".
     */
    private function pluralizeComment(int $count): string
    {
        $abs = abs($count) % 100;
        $lastDigit = $abs % 10;

        if ($abs > 10 && $abs < 20) {
            return 'комментариев';
        }
        if ($lastDigit > 1 && $lastDigit < 5) {
            return 'комментария';
        }
        if ($lastDigit === 1) {
            return 'комментарий';
        }
        return 'комментариев';
    }
	
	/**
	 * Отмечает историю как прочитанную (обновляет read_ribbon до последнего комментария)
	 */
	public function markStoryAsRead(int $storyId): void
	{
		if (!Auth::check()) {
			return;
		}

		$this->readRibbon->syncForUserAndStory(Auth::id(), $storyId);
	}
	
	/**
	 * Массовая отметка историй как прочитанных (batch-операция)
	 * 
	 * @param array $storyIds Массив ID историй
	 * @return void
	 */
	public function markStoriesAsRead(array $storyIds): void
	{
		if (empty($storyIds) || !Auth::check()) {
			return;
		}

		$this->readRibbon->syncForStories(Auth::id(), $storyIds);
	}
}