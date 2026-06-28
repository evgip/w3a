<?php

declare(strict_types=1);

namespace App\Modules\Suggestions\Controllers;

use App\Core\Controller;
use App\Modules\Suggestions\Services\SuggestionService;
use App\Modules\Suggestions\Models\Suggestion;

class SuggestionController extends Controller
{
    /**
     * GET /suggestions/{targetType}/{targetId}
     * Получить активные предложения для контента
     */
    public function index(string $targetType, string $targetId): void
    {
        $suggestions = $this->service(SuggestionService::class)->getActiveSuggestions(
            $targetType,
            (int) $targetId
        );
        
        $this->json([
            'suggestions' => $suggestions,
            'count' => count($suggestions)
        ]);
    }
    
    /**
     * GET /suggestions/{targetType}/{targetId}/log
     * Получить лог изменений для контента
     */
    public function log(string $targetType, string $targetId): void
    {
        $limit = (int) ($_GET['limit'] ?? 50);
        
        $logs = $this->service(SuggestionService::class)->getChangeLog(
            $targetType,
            (int) $targetId,
            $limit
        );
        
        $this->json([
            'logs' => $logs,
            'count' => count($logs)
        ]);
    }
    
    /**
     * POST /suggestions
     * Добавить новое предложение
     */
    public function store(): void
    {
        try {
            $targetType = $_POST['target_type'] ?? '';
            $targetId = (int) ($_POST['target_id'] ?? 0);
            $proposedData = json_decode($_POST['proposed_data'] ?? '{}', true);
            
            if (!$targetType || !$targetId || empty($proposedData)) {
                $this->json(['error' => 'Invalid parameters'], 400);
                return;
            }
            
            $suggestionId = $this->service(SuggestionService::class)->addSuggestion(
                $targetType,
                $targetId,
                (int) $_SESSION['user_id'],
                $proposedData
            );
            
            $this->json([
                'success' => true,
                'suggestion_id' => $suggestionId,
                'message' => 'Suggestion added successfully'
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * POST /suggestions/{id}/support
     * Поддержать существующее предложение
     */
    public function support(string $id): void
    {
        try {
            $suggestion = Suggestion::find((int) $id);
            
            if (!$suggestion) {
                $this->json(['error' => 'Suggestion not found'], 404);
                return;
            }
            
            // Добавляем предложение от текущего пользователя с теми же данными
            $this->service(SuggestionService::class)->addSuggestion(
                $suggestion['target_type'],
                $suggestion['target_id'],
                (int) $_SESSION['user_id'],
                json_decode($suggestion['proposed_data'], true)
            );
            
            $this->json(['success' => true]);
            
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }
	
	/**
	 * POST /suggestions/{id}/approve
	 * Одобрить предложение (только для модераторов)
	 */
	public function approve(string $id): void
	{
		try {
			$this->service(SuggestionService::class)->approveSuggestion(
				(int) $id,
				(int) $_SESSION['user_id']
			);
			
			Session::setFlash('success', 'Предложение одобрено и применено.');
			$this->redirectBack();
			
		} catch (\Exception $e) {
			Session::setFlash('error', $e->getMessage());
			$this->redirectBack();
		}
	}

	/**
	 * POST /suggestions/{id}/reject
	 * Отклонить предложение (только для модераторов)
	 */
	public function reject(string $id): void
	{
		try {
			$reason = $this->request->post('reason', '');
			
			$this->service(SuggestionService::class)->rejectSuggestion(
				(int) $id,
				(int) $_SESSION['user_id'],
				$reason
			);
			
			Session::setFlash('success', 'Предложение отклонено.');
			$this->redirectBack();
			
		} catch (\Exception $e) {
			Session::setFlash('error', $e->getMessage());
			$this->redirectBack();
		}
	}
}