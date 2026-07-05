<?php

/**
 * Система приглашений
 */

return [
	'invitations_enabled' => env('INVITATIONS_ENABLED', 0),	// Включить/выключить систему инвайтов env('INVITATIONS_ENABLED', 1)
	'min_karma_for_invitation' => 10,            // Минимальная карма для создания приглашений
	'max_invitations_per_user' => 5,             // Максимум активных приглашений на пользователя
	'invitation_expires_days' => 7,              // Срок действия приглашения в днях
];