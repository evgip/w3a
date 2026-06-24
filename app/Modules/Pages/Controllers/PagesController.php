<?php

namespace App\Modules\Pages\Controllers;

use App\Core\{Controller, Markdown};

class PagesController extends Controller
{
    public function about(): void
    {
        // Путь к Markdown-файлу
        $mdFile = dirname(__DIR__) . '/Views/md/about.md';
        
        if (!file_exists($mdFile)) {
            http_response_code(404);
            $errorController = "App\Modules\Errors\Controllers\ErrorsController";
            if (class_exists($errorController)) {
                (new $errorController())->notFound("Страница не найдена");
                exit;
            }
            die("<h1>404 Not Found</h1>");
        }
    
		$html = markdown(file_get_contents($mdFile));

        $this->render('about', [
            'title'   => 'О проекте',
            'content' => $html,
        ]);
    }

    public function privacy(): void
    {
        $this->render('privacy', [
            'title' => 'Политика конфиденциальности',
        ]);
    }

    public function rules(): void
    {
        $this->render('rules', [
            'title' => 'Правила сообщества',
        ]);
    }

    public function chat(): void
    {
        $this->render('chat', [
            'title' => 'Чат',
        ]);
    }
}