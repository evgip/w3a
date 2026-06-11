<?php

namespace App\Modules\Errors\Controllers;

use App\Core\Controller;

class ErrorsController extends Controller
{
    /**
     * Метод для ошибок 404 (Не найдено) и 403 (Запрещено)
     */
    public function notFound(string $message = "Страница не найдена")
    {
        // Рендерим шаблон layout.php из текущего модуля ошибок
        $this->render('layout', [
            'title' => 'Ошибка',
            'message' => $message
        ]);
    }
}
