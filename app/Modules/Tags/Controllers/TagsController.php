<?php

namespace App\Modules\Tags\Controllers;

use App\Core\Controller;
use App\Modules\Tags\Models\Tag;

class TagsController extends Controller
{
    /**
     * Renders the Lobsters-style Master Tags Matrix Catalog Index (GET /tags)
     */
    public function index(): void
    {
        $tagModel = new Tag();
        $allTags = $tagModel->getAllTags();

        $this->render('index', [
            'title' => 'Каталог тем и тегов сообщества',
            'tags'  => $allTags
        ]);
    }
}
