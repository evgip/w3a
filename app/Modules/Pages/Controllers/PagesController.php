<?php

namespace App\Modules\Pages\Controllers;

use App\Core\Controller;

class PagesController extends Controller
{
    public function about(): void
    {
        $this->render('about', [
            'title' => 'О проекте',
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