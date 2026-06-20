<?php
// app/Modules/Errors/Controllers/ErrorsController.php

namespace App\Modules\Errors\Controllers;

use App\Core\Controller;

class ErrorsController extends Controller
{
    /**
     * Ошибка 404 —  Страница не найдена
     */
    public function notFound(string $message = "Страница не найдена"): void
    {
		 http_response_code(404);
		
		$this->render('errors/404', [
            'title' => 'Ошибка 404 — cтраница не найдена',
            'message' => $message,
			'statusCode' => 404,
        ]);
    }

    /**
     * Ошибка 419 — CSRF-токен недействителен (срок действия формы истёк)
     */
    public function csrf(string $message = "Срок действия формы истёк"): void
    {
        http_response_code(419);
        
		$this->render('errors/419', [
            'title' => 'Ошибка 419 — срок действия формы истёк',
            'message' => $message,
			'statusCode' => 419,
        ]);
    }

    /**
     * Ошибка 403 — Доступ запрещён
     */
    public function forbidden(string $message = "Доступ запрещён"): void
    {
        http_response_code(403);
        
        $this->render('errors/403', [
            'title' => 'Ошибка 403 — доступ запрещён',
            'message' => $message,
            'statusCode' => 403,
        ]);
    }
}

 