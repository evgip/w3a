<?php
declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Invitations\Models\InvitationRequest;

/**
 * Сервис для управления запросами на приглашение.
 */
class AdminInvitationService
{
    private InvitationRequest $requestModel;
    
    public function __construct()
    {
        $this->requestModel = new InvitationRequest();
    }
    
    /**
     * Получить список запросов по статусу.
     */
    public function getRequests(string $status = 'pending'): array
    {
        $allowedStatuses = ['pending', 'approved', 'rejected'];
        
        if (!in_array($status, $allowedStatuses)) {
            $status = 'pending';
        }
        
        return $this->requestModel->getAllRequests($status);
    }
    
    /**
     * Одобрить запрос.
     */
    public function approveRequest(int $id): bool
    {
        return $this->requestModel->approveRequest($id);
    }
    
    /**
     * Отклонить запрос.
     */
    public function rejectRequest(int $id): bool
    {
        return $this->requestModel->rejectRequest($id);
    }
}