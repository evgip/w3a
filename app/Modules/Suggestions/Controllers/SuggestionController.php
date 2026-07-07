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

	/**
	 * Получить историю изменений (ленту предложений)
	 */
	public function log(string $targetType, string $targetId): void
	{
		try {
			// Базовая валидация
			$targetType = trim($targetType);
			$targetIdInt = (int) $targetId;

			if ($targetType === '' || $targetIdInt <= 0) {
				$this->json(['error' => 'Invalid parameters: target_type and target_id are required'], 400);
				return;
			}

			// Получаем limit из query-параметров через Request
			$limit = (int) $this->request->input('limit', 50);

			// Ограничиваем диапазон (защита от злоупотреблений)
			$limit = max(1, min($limit, 200));

			$logs = $this->service(SuggestionService::class)->getChangeLog(
				$targetType,
				$targetIdInt,
				$limit
			);

			$this->json([
				'success' => true,
				'logs' => $logs,
				'count' => count($logs),
				'limit' => $limit
			]);
		} catch (\Exception $e) {
			error_log('Suggestion log error: ' . $e->getMessage());
			$this->json(['error' => 'Failed to retrieve change log'], 500);
		}
	}

	/**
	 * Создать новое предложение
	 */
	public function store(): void
	{
		try {
			// Проверка авторизации
			$userId = Auth::id();
			if (!$userId) {
				$this->json(['error' => 'Authentication required'], 401);
				return;
			}

			// Получаем данные через Request (поддерживает GET/POST/JSON)
			$targetType = trim((string) $this->request->input('target_type', ''));
			$targetId = (int) $this->request->input('target_id', 0);
			$proposedDataRaw = $this->request->input('proposed_data');

			// Базовая валидация обязательных параметров
			if ($targetType === '' || $targetId <= 0) {
				$this->json([
					'error' => 'Missing or invalid required parameters: target_type, target_id'
				], 400);
				return;
			}

			// Универсальный парсинг proposed_data (строка или массив)
			$proposedData = $this->parseProposedData($proposedDataRaw);
			if (empty($proposedData)) {
				$this->json(['error' => 'Invalid or empty proposed_data'], 400);
				return;
			}

			// Создаём предложение
			$suggestionId = $this->service(SuggestionService::class)->addSuggestion(
				$targetType,
				$targetId,
				(int) $userId,
				$proposedData
			);

			$this->json([
				'success' => true,
				'suggestion_id' => $suggestionId,
				'message' => 'Suggestion added successfully'
			], 201);
		} catch (\Exception $e) {
			error_log('Suggestion store error: ' . $e->getMessage());
			$this->json(['error' => 'Failed to create suggestion'], 500);
		}
	}

	/**
	 * Универсальный парсер proposed_data
	 * Поддерживает: JSON-строку, массив, null
	 */
	private function parseProposedData(mixed $data): array
	{
		if (is_array($data)) {
			return $data;
		}

		if (is_string($data) && $data !== '') {
			$decoded = json_decode($data, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				return $decoded;
			}
		}

		return [];
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
