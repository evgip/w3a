<?php

declare(strict_types=1);

namespace App\Modules\Suggestions\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Modules\Suggestions\Services\SuggestionService;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Auth\Services\Auth;

class SuggestionController extends Controller
{
    /**
     * ✅ Хелпер: получить Session из контейнера
     */
    private function session(): Session
    {
        return $this->container->get(Session::class);
    }

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

            // ✅ Используем Auth::id() вместо $_SESSION
            $suggestionId = $this->service(SuggestionService::class)->addSuggestion(
                $targetType,
                $targetId,
                (int) Auth::id(),
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

    public function support(string $id): void
    {
        try {
            $suggestionModel = $this->container->get(Suggestion::class);
            $suggestion = $suggestionModel->find((int) $id);

            if (!$suggestion) {
                $this->json(['error' => 'Suggestion not found'], 404);
                return;
            }

            $this->service(SuggestionService::class)->addSuggestion(
                $suggestion['target_type'],
                $suggestion['target_id'],
                (int) Auth::id(),
                json_decode($suggestion['proposed_data'], true)
            );

            $this->json(['success' => true]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function approve(string $id): void
    {
        try {
            $this->service(SuggestionService::class)->approveSuggestion(
                (int) $id,
                (int) Auth::id()
            );

            $this->session()->flash('success', 'Предложение одобрено и применено.');
            $this->redirectBack();
        } catch (\Throwable $e) {
            $this->session()->flash('error', $e->getMessage());
            $this->redirectBack();
        }
    }

    public function reject(string $id): void
    {
        try {
            $reason = $this->request->post('reason', '');

            $this->service(SuggestionService::class)->rejectSuggestion(
                (int) $id,
                (int) Auth::id(),
                $reason
            );

            $this->session()->flash('success', 'Предложение отклонено.');
            $this->redirectBack();
        } catch (\Exception $e) {
            $this->session()->flash('error', $e->getMessage());
            $this->redirectBack();
        }
    }
}