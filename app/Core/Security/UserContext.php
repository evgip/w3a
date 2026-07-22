<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Value Object (DTO): содержит данные о текущем авторизованном пользователе
 * для текущего HTTP-запроса. 
 * 
 * Не содержит бизнес-логики, не умеет сохранять данные в БД.
 * Используется только для проверки прав и получения ID в сервисах.
 */
class UserContext 
{
    public function __construct(
        public readonly int $id,
        public readonly bool $isAdmin,
        public readonly bool $isModerator
    ) {}

    /**
     * Удобный метод для проверки прав модерации/администрирования
     */
    public function canModerate(): bool 
    { 
        return $this->isAdmin || $this->isModerator; 
    }
    
    /**
     * Проверка, авторизован ли пользователь вообще
     * (ID > 0 обычно означает гостя или неавторизованного, 
     *  но зависит от вашей логики. Можно добавить public readonly bool $isGuest)
     */
    public function isGuest(): bool
    {
        return $this->id === 0;
        // Или return $this->id <= 0; в зависимости от того, как у вас хранится guest
    }
}