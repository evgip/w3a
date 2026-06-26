<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Modules\Users\Services\UserService;
use App\Modules\Users\Services\AvatarService;
use App\Modules\Auth\Services\Auth;

/**
 * Контроллер для управления профилями пользователей и настройками аккаунта.
 * 
 * Отвечает за:
 * - Отображение публичных профилей пользователей
 * - Управление настройками профиля (email, bio, аватар, уведомления)
 * - Смену пароля авторизованным пользователем
 * 
 * Все действия (кроме profile) требуют авторизации через сессию.
 * Зависимости (UserService, AvatarService) получаются из контейнера через методы-геттеры.
 */
class UsersController extends Controller
{
    /**
     * Получить сервис для работы с пользователями из контейнера.
     * 
     * Сервис зарегистрирован как singleton, поэтому создаётся один раз
     * и переиспользуется в течение всего запроса.
     * 
     * @return UserService Сервис для операций с пользователями и профилями
     */
    private function getUserService(): UserService
    {
        return $this->service(UserService::class);
    }

    /**
     * Получить сервис для работы с аватарами из контейнера.
     * 
     * Отвечает за загрузку, валидацию и обработку файлов аватаров.
     * 
     * @return AvatarService Сервис для управления аватарами
     */
    private function getAvatarService(): AvatarService
    {
        return $this->service(AvatarService::class);
    }

    /**
     * Отображение всех участников (GET /users}).
     */
	public function index() {
		return true;
	}

    /**
     * Отображение публичного профиля пользователя (GET /user/{username}).
     * 
     * Загружает и отображает:
     * - Основную информацию о пользователе (username, email и т.д.)
     * - Данные профиля (bio, аватар)
     * - Информацию о бане (если есть)
     * - Статистику (количество историй и комментариев)
     * - Карму пользователя
     * 
     * @param string $username Имя пользователя (username) — обязательный параметр маршрута
     * @return void
     */
    public function profile(string $username): void
    {
        // Создаём модель напрямую (пока не внедрена через DI)
        $userModel = new \App\Modules\Users\Models\User();
        
        // Ищем пользователя по username (с обрезкой пробелов)
        $user = $userModel->findBy('username', trim($username));

        if (!$user) {
            // Пользователь не найден — показываем 404
            $errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
            if (class_exists($errorController)) {
                (new $errorController())->notFound("Пользователь <i>{$username}</i> не найден.");
                exit;
            }
            die("<h1>404 Errors</h1>");
        }

        // Загружаем дополнительные данные профиля (bio, аватар)
        $profile = $userModel->getProfile((int)$user['id']);
        $user['bio'] = $profile['bio'] ?? null;
        $user['avatar'] = $profile['avatar'] ?? null;

        // Проверяем, забанен ли пользователь
        $banInfo = $userModel->getBanInfo((int)$user['id']);
        $user['is_banned'] = $banInfo !== null;
        $user['ban_reason'] = $banInfo['reason'] ?? null;
        $user['banned_at'] = $banInfo['created_at'] ?? null;
        $user['expires_at'] = $banInfo['expires_at'] ?? null;

        // Загружаем статистику (количество историй и комментариев)
        $stats = $userModel->getProfileStats((int)$user['id']);
        
        // Загружаем карму пользователя
        $userKarma = $userModel->getUserKarma((int)$user['id']);

        // Рендерим шаблон профиля с данными
        $this->render('profile', [
            'title' => 'Профиль пользователя ' . e($user['username']),
            'profileUser' => $user,
            'storiesCount' => $stats['stories_count'] ?? 0,
            'commentsCount' => $stats['comments_count'] ?? 0,
            'userKarma' => $userKarma ?? 0
        ]);
    }

    /**
     * Отображение страницы настроек профиля (GET /account/settings).
     * 
     * Загружает данные текущего авторизованного пользователя:
     * - Основная информация (email, username)
     * - Данные профиля (bio, аватар)
     * - Настройки оповещений
     * - Активные уведомления для отображения
     * 
     * Требует авторизации (проверяется через наличие `$_SESSION['user_id']`).
     * Если пользователь не найден — редирект на главную.
     * 
     * @return void
     */
	public function settings(): void
	{
		$userId = Auth::id();
		
		// Загружаем пользователя с данными профиля
		$user = $this->getUserService()->getUserWithProfile($userId);
		if (!$user) {
			redirect('/');
		}
		
		// === Загружаем настройки через сервис ===
		$settings = $this->getUserService()->getUserSettings($userId);
		
		// Рендерим страницу
		$this->render('settings', [
			'title' => 'Настройки профиля',
			'user' => $user,
			'settings' => $settings,
			'request' => $this->request
		]);
	}

    /**
     * Обработка обновления настроек профиля (POST /account/settings).
     * 
     * Выполняет:
     * 1. Обновление email (с проверкой уникальности)
     * 2. Загрузку нового аватара (если файл передан)
     * 3. Обновление данных профиля (bio, avatar)
     * 4. Обновление настроек уведомлений
     * 
     * При ошибке email (неуникальный) — редирект на форму с сохранением текущих данных.
     * При загрузке аватара — старый файл удаляется через AvatarService.
     * 
     * @return void
     */
    public function updateSettings(): void
    {
        // Получаем ID текущего пользователя
        $userId = Auth::id();
        
        // Загружаем текущие данные пользователя
        $user = $this->getUserService()->getUserWithProfile($userId);

        if (!$user) {
            // Пользователь не авторизован — редирект на главную
            redirect('/');
        }

        // Получаем данные из формы
        $email = trim($this->request->getParams('email'));
        $bio = trim($this->request->getParams('bio'));
        $oldAvatarFilename = $user['avatar']; // Текущий аватар (для удаления)
        $newAvatarFilename = $oldAvatarFilename; // По умолчанию не меняется

        // === Обновление email ===
        // Проверяем, изменился ли email
        if ($email !== $user['email']) {
            // Пытаемся обновить email (сервис проверяет уникальность)
            if (!$this->getUserService()->updateEmail($userId, $email)) {
                // Email уже занят — редирект обратно на форму
				redirect(route('account.settings'));
            }
        }

        // === Загрузка нового аватара ===
        // Проверяем, был ли загружен файл без ошибок
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            // Обрабатываем загрузку (валидация, сохранение, удаление старого)
            $uploadedAvatar = $this->getAvatarService()->handleUpload(
                $_FILES['avatar_file'], 
                $oldAvatarFilename
            );
            
            if ($uploadedAvatar) {
                // Если загрузка успешна — обновляем имя файла
                $newAvatarFilename = $uploadedAvatar;
            }
        }

        // === Обновление профиля ===
        // Сохраняем bio и имя файла аватара
        $this->getUserService()->updateProfile($userId, [
            'bio' => $bio,
            'avatar' => $newAvatarFilename
        ]);

        // === Обновление настроек уведомлений ===
        // Конвертируем checkbox'ы в 0/1
        $this->getUserService()->updateSettings($userId, [
            'notify_on_reply' => $this->request->getParams('notify_on_reply') ? 1 : 0,
            'notify_on_story_comment' => $this->request->getParams('notify_on_story_comment') ? 1 : 0,
            'email_notifications' => $this->request->getParams('email_notifications') ? 1 : 0,
        ]);

        // Обновляем аватар в сессии (для отображения в шапке сайта)
        $_SESSION['user_avatar'] = $newAvatarFilename;

        // Показываем flash-сообщение об успехе
        Session::setFlash('success', 'Настройки сохранены.');
        
        // Редирект обратно на страницу настроек
		redirect(route('account.settings'));
    }

    /**
     * Обработка смены пароля (POST /account/password).
     * 
     * Выполняет:
     * 1. Валидацию длины нового пароля (минимум 6 символов)
     * 2. Проверку текущего пароля
     * 3. Обновление хэша пароля в БД
     * 
     * Возвращает flash-сообщение (успех/ошибка) и редиректит обратно.
     * 
     * @return void
     */
    public function updatePassword(): void
    {
        // Получаем ID текущего пользователя
        $userId = Auth::id();
        
        // Получаем пароли из формы
        $currentPassword = $this->request->getParams('current_password');
        $newPassword = $this->request->getParams('new_password');

        // === Валидация длины пароля ===
        if (strlen($newPassword) < 6) {
            Session::setFlash('error', 'Пароль должен быть не менее 6 символов.');
            redirect(route('account.settings'));
        }

        // === Попытка смены пароля ===
        // Сервис проверяет текущий пароль и обновляет хэш
        $this->getUserService()->changePassword($userId, $currentPassword, $newPassword);

        // Редирект обратно на страницу настроек
        redirect(route('account.settings'));
    }
}