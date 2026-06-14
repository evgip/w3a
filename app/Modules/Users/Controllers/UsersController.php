<?php

namespace App\Modules\Users\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Validator;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Audit;
use App\Modules\Users\Models\User;

class UsersController extends Controller
{
	
	public function index()
    {
        // 1. Создаем экземпляр модели User
        $userModel = new User();
        
        // 2. Вызываем базовый метод all() вместо статического getAll()
        $users = $userModel->all();
        
        // 3. Рендерим страницу списка пользователей
        $this->render('index', [
            'users' => $users, 
            'title' => 'Список пользователей'
        ]);
    }
	
    /**
     * Отображение публичного профиля пользователя (GET /user/{username})
     */
    public function profile(string $username): void
    {
        $userModel = new \App\Modules\Users\Models\User();

        // 1. Находим пользователя по его имени через базовый метод findBy ядра
        $user = $userModel->findBy('username', trim($username));

        // Если пользователь не найден в БД — отдаем 404 страницу
        if (!$user) {
            $errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
            if (class_exists($errorController)) {
                (new $errorController())->notFound("Пользователь '{$username}' не найден.");
                exit;
            }
            http_response_code(404);
            die("404 Not Found");
        }

        // 2. Запрашиваем статистику активности через метод модели (БЕЗ SQL В КОНТРОЛЛЕРЕ)
        $stats = $userModel->getProfileStats((int)$user['id']);

        // 3. Вычисляем суммарную репутацию кармы пользователя
        $userKarma = $userModel->getUserKarma((int)$user['id']);

        // 4. Рендерим шаблон, передавая туда чистые данные
        $this->render('profile', [
            'title'         => 'Профиль пользователя ' . e($user['username']),
            'profileUser'   => $user,
            'storiesCount'  => $stats['stories_count'],
            'commentsCount' => $stats['comments_count'],
			'userKarma'     => $userKarma 
        ]);
    }

	
    /**
     * Отображение формы логина (GET /login)
     */
    public function showLoginForm()
    {
        // Если уже авторизован — отправляем на главную
        if (Auth::check()) {
            header('Location: /');
            exit;
        }

        $request = new Request();
        
        // Рендерим шаблон login.php из папки Views модуля Users
        $this->render('login', [
            'title' => 'Авторизация',
            'request' => $request // Передаем объект запроса для вывода CSRF-поля
        ]);
    }

    /**
     * Обработка отправки формы (POST /login)
     * Handles authentication credential pipelines validation (POST /login)
     */
    public function login(): void
    {
        $request = new Request();
        $request->validateCsrf();

        $email    = trim($request->getParams('email'));
        $password = $request->getParams('password');

        $userModel = new User();
        $user = $userModel->findBy('email', $email);

        if ($user && password_verify($password, $user['password'])) {
			
            // CRITICAL STATUS ENFORCEMENT CHECK: Block inactive user logins
            $activeStatus = config_int('user.status_active', 1);
			if ((int)$user['is_active'] !== $activeStatus) {
                \App\Core\Session::setFlash('error', 'Ваш аккаунт еще не активирован. Пожалуйста, перейдите по ссылке из приветственного письма на Email.');
                header('Location: /login');
                exit;
            }
            
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_name']   = $user['username'];
            $_SESSION['user_avatar'] = $user['avatar']; 
            $_SESSION['user_role']   = $user['role'] ?? 'user';
            $_SESSION['last_activity_time'] = time();

            session_regenerate_id(true);

            Audit::log('auth.login_success', 'Пользователь успешно авторизовался в системе', ['user_id' => $user['id']]);
            Session::setFlash('success', 'Добро пожаловать обратно, ' . e($user['username']) . '!');
            
            header('Location: ' . route('home'));
            exit;
        }

        Audit::log('auth.login_failed', 'Неудачная попытка входа в систему', ['attempted_email' => $email]);
        Session::setFlash('error', 'Неверный Email адрес или пароль.');
        header('Location: ' . route('auth.login'));
        exit;
    }


    /**
     * Выход из системы (GET /logout)
     */
    public function logout()
    {
        Auth::logout();
        header('Location: /login');
        exit;
    }
	
    /**
     * Display the registration form (GET /register)
     */
	public function showRegisterForm(): void
	{
		$request = new Request();
		
		// Получаем старые значения из сессии (если есть)
		$old = \App\Core\Session::get('old_input', []);
		\App\Core\Session::delete('old_input'); // Очищаем после использования
		
		$this->render('register', [
			'title' => 'Регистрация нового пользователя',
			'request' => $request,
			'old' => $old
		]);
	}

    /**
     * Process registration submission (POST /register)
     */
	public function register(): void
	{
		$request = new Request();
		
		// 1. Обязательная проверка CSRF-токена
		$request->validateCsrf();
		
		// --- КРИТИЧЕСКИЙ РУБЕЖ: ПРОВЕРКА КАПЧИ ---
		if (!\App\Core\Captcha::verify()) {
			\App\Core\Session::setFlash('error', 'Пожалуйста, подтвердите, что вы не робот (капча не пройдена).');
			header('Location: /register');
			exit;
		}
		// --- КОНЕЦ БЛОКА ЗАЩИТЫ ---
		
		// 2. Валидация входных данных
		$validator = new Validator();
		$minNameLength = config_int('validation.name_min_length', 2);
		$minPasswordLength = config_int('validation.password_min_length', 6);

		$isValid = $validator->validate($_POST, [
			'username' => "required|min:{$minNameLength}",
			'email' => 'required|email|unique:users,email',
			'password' => "required|min:{$minPasswordLength}"
		]);
		
		if (!$isValid) {
			$errors = $validator->getErrors();
			$firstField = array_key_first($errors);
			$errorMessage = $errors[$firstField][0];
			
			// Используем flash-сообщения вместо переменной $error
			\App\Core\Session::setFlash('error', $errorMessage);
			
			// Сохраняем старые значения полей в сессии
			\App\Core\Session::set('old_input', [
				'username' => $_POST['username'] ?? '',
				'email' => $_POST['email'] ?? ''
			]);
			
			header('Location: /register');
			exit;
		}
		
		// 3. Извлечение проверенных данных
		$username = $request->getParams('username');
		$email = $request->getParams('email');
		$rawPassword = $request->getParams('password');
		
		// 4. Хеширование пароля
		$hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);
		
		$userModel = new User();
		
		// Дополнительная проверка уникальности имени (на случай гонки данных)
		if ($userModel->findBy('username', $username)) {
			\App\Core\Session::setFlash('error', 'Это имя пользователя уже занято.');
			header('Location: /register');
			exit;
		}
		
		// 5. Создание пользователя
		$newUserId = $userModel->create([
			'username' => $username,
			'email' => $email,
			'password' => $hashedPassword,
			'role' => 'user'
		]);
		
		if ($newUserId > 0) {
			// Создание токена активации
			$activationModel = new \App\Modules\Users\Models\EmailActivation();
			$token = $activationModel->createActivationToken($newUserId);
			
			$activationLink = config('config.app.url') . "/register/activate/" . $token;
			
			// Формирование письма
			$subject = \App\Core\Lang::format('email_activation_subject', [e(app_name())]);
			$htmlBody = \App\Core\Lang::format('email_activation_body', [
				e($username),
				$activationLink
			]);
			
			// Отправка письма
			\App\Core\Mailer::send($email, $subject, $htmlBody);
			
			// Логирование
			\App\Core\Audit::log('user.registered', "Зарегистрирован новый аккаунт", [
				'new_user_name' => $username,
				'new_user_email' => $email
			]);
			
			\App\Core\Session::setFlash('success', 'Регистрация успешна! На ваш Email отправлена ссылка для активации аккаунта. Пожалуйста, проверьте почтовый ящик.');
			header('Location: /login');
			exit;
		}
		
		// Если создание не удалось
		\App\Core\Session::setFlash('error', 'Не удалось создать аккаунт. Попробуйте позже.');
		header('Location: /register');
		exit;
	}
	
	
	
	
	
	public function apiUsers()
	{
		$users = \App\Modules\Users\Models\User::all();
		// Отдаст красивый JSON-ответ с заголовками application/json
		$this->json(['success' => true, 'data' => $users]);
	}
	
    /**
     * Display authenticated settings and system notifications layer (GET /account/settings)
     */
    public function settings(): void
    {
        if (!\App\Core\Auth::check()) {
            header('Location: ' . route('auth.login'));
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $userModel = new \App\Modules\Users\Models\User();
        $user = $userModel->find($userId);

        // NEW: Fetch all logged notifications via the notification model layer
        $notifModel = new \App\Modules\Users\Models\Notification();
        $notifications = $notifModel->getActiveNotifications($userId);

        $this->render('settings', [
            'title' => 'Настройки профиля и уведомления',
            'user' => $user,
            'notifications' => $notifications,
            'request' => new \App\Core\Request()
        ]);
    }

    /**
     * Clear active unread message notifications flags (POST /account/notifications/read)
     */
    public function clearNotifications(): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf();

        $userId = (int)$_SESSION['user_id'];
        $notifModel = new \App\Modules\Users\Models\Notification();
        $notifModel->markAllAsRead($userId);

        \App\Core\Session::setFlash('success', 'Все уведомления отмечены как прочитанные.');
        header('Location: ' . route('account.settings'));
        exit;
    }


    /**
     * Process avatar upload with automatic smart resize to 150x150 (POST /account/settings)
     */
    public function updateSettings(): void
    {
        if (!\App\Core\Auth::check()) { 
            header('Location: /login'); 
            exit; 
        }

        $request = new \App\Core\Request();
        $request->validateCsrf();

        $userId = (int)$_SESSION['user_id'];
        $userModel = new \App\Modules\Users\Models\User();
        $user = $userModel->find($userId);

        if (!$user) { header('Location: /'); exit; }

        $email = trim($request->getParams('email'));
        $bio = trim($request->getParams('bio'));
        $avatarFilename = $user['avatar'];

        if ($email !== $user['email'] && $userModel->findBy('email', $email)) {
            \App\Core\Session::setFlash('error', 'Этот Email адрес уже занят другим аккаунтом.');
            header('Location: ' . route('account.settings'));
            exit;
        }

        // SMART RESIZE AVATAR UPLOAD HANDLING
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['avatar_file']['tmp_name'] ?? '';
            
            if (empty($fileTmpPath) || !file_exists($fileTmpPath)) {
                \App\Core\Session::setFlash('error', 'Временный файл загрузки недоступен.');
                header('Location: ' . route('account.settings'));
                exit;
            }

			$maxAvatarSize = config_int('uploads.avatar_max_size', 5242880);
			$maxAvatarSizeMb = config_int('uploads.avatar_max_size_mb', 5);

			if ($_FILES['avatar_file']['size'] > $maxAvatarSize) {
				\App\CoreSession::setFlash('error', "Размер загружаемого файла не должен превышать {$maxAvatarSizeMb} МБ.");
				header('Location: ' . route('account.settings'));
				exit;
			}

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileTmpPath);
            finfo_close($finfo);

            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($mimeType, $allowedMimeTypes)) {
                \App\Core\Session::setFlash('error', 'Разрешены только графические форматы файлов (JPG, PNG, GIF).');
                header('Location: ' . route('account.settings'));
                exit;
            }

            // Нам больше не нужно сохранять оригинальное расширение, мы принудительно переведем всё в универсальный JPG для оптимизации места
            $avatarFilename = bin2hex(random_bytes(16)) . '.jpg';
            $subFolder = substr($avatarFilename, 0, 2);

            $projectRoot = dirname(__FILE__, 5); 
            $baseUploadDir = $projectRoot . '/public/uploads/avatars';
            $uploadTargetDir = $baseUploadDir . '/' . $subFolder;

            if (!is_dir($baseUploadDir)) mkdir($baseUploadDir, 0777, true);
            if (!is_dir($uploadTargetDir)) mkdir($uploadTargetDir, 0777, true);

            // --- АЛГОРИТМ УМНОГО РЕСАЙЗА И КРОПА СИЛАМИ PHP GD ---
            list($srcWidth, $srcHeight) = getimagesize($fileTmpPath);
            
            // Создаем временный ресурс изображения в зависимости от исходного типа
            switch ($mimeType) {
                case 'image/png':  $srcImage = imagecreatefrompng($fileTmpPath); break;
                case 'image/gif':  $srcImage = imagecreatefromgif($fileTmpPath); break;
                default:           $srcImage = imagecreatefromjpeg($fileTmpPath); break;
            }

            if (!$srcImage) {
                \App\Core\Session::setFlash('error', 'Не удалось обработать структуру изображения.');
                header('Location: ' . route('account.settings'));
                exit;
            }

            // Вычисляем параметры для обрезки (центрируем квадрат)
            $targetSize = 150;
            $dstImage = imagecreatetruecolor($targetSize, $targetSize);

            // Сохраняем прозрачность для PNG/GIF, переводя её в белый фон для результирующего JPG
            $whiteBackground = imagecolorallocate($dstImage, 255, 255, 255);
            imagefill($dstImage, 0, 0, $whiteBackground);

            if ($srcWidth > $srcHeight) {
                // Исходник альбомный (широкий)
                $srcX = (int)(($srcWidth - $srcHeight) / 2);
                $srcY = 0;
                $srcSquareSize = $srcHeight;
            } else {
                // Исходник портретный (высокий)
                $srcX = 0;
                $srcY = (int)(($srcHeight - $srcWidth) / 2);
                $srcSquareSize = $srcWidth;
            }

            // Высококачественное сжатие и обрезка в целевой квадрат 150x150
            imagecopyresampled(
                $dstImage, $srcImage, 
                0, 0, $srcX, $srcY, 
                $targetSize, $targetSize, $srcSquareSize, $srcSquareSize
            );

            // Сохраняем сжатый JPG в папку шардирования с качеством 85% (идеальный баланс веса и четкости)
            $finalDestination = $uploadTargetDir . '/' . $avatarFilename;
            imagejpeg($dstImage, $finalDestination, 85);

            // Очищаем память сервера от тяжелых графических ресурсов
            imagedestroy($srcImage);
            imagedestroy($dstImage);

            // Удаление старого аватара с диска и папки, где она лежит
            if (!empty($user['avatar'])) {
                $oldSub = substr($user['avatar'], 0, 2);
                $oldFolderDir = $baseUploadDir . '/' . $oldSub;
                $oldAvatarPath = $oldFolderDir . '/' . $user['avatar'];
                
                // 1. Удаляем сам файл картинки
                if (file_exists($oldAvatarPath)) {
                    unlink($oldAvatarPath);
                }

                // 2. УМНАЯ ОЧИСТКА: Если папка шардирования опустела — удаляем её
                if (is_dir($oldFolderDir)) {
                    // Сканируем папку, исключая системные указатели "." и ".."
                    $remainingFiles = array_diff(scandir($oldFolderDir), ['.', '..']);
                    if (empty($remainingFiles)) {
                        rmdir($oldFolderDir); // Папка пуста, удаляем её с диска
                    }
                }
            }
			
        }

        // Обновляем настройки в БД
        $userModel->update($userId, [
            'email'  => $email,
            'bio'    => $bio,
            'avatar' => $avatarFilename
        ]);

        \App\Core\Session::setFlash('success', 'Изменения вашего личного кабинета успешно сохранены. Аватар оптимизирован.');
        header('Location: ' . route('account.settings'));
        exit;
    }


    /**
     * Process secure password changes (POST /account/settings/password)
     */
    public function updatePassword(): void
    {
        if (!\App\Core\Auth::check()) {
            header('Location: /login');
            exit;
        }

        $request = new \App\Core\Request();
        $request->validateCsrf(); // Critical anti-CSRF exploit check

        $userId = (int)$_SESSION['user_id'];
        $userModel = new \App\Modules\Users\Models\User();
        $user = $userModel->find($userId);

        if (!$user) { header('Location: /'); exit; }

        $currentPassword = $request->getParams('current_password');
        $newPassword     = $request->getParams('new_password');
        $confirmPassword = $request->getParams('confirm_password');

        // 1. Authenticate that the user knows their current password
        if (!password_verify($currentPassword, $user['password'])) {
            \App\Core\Session::setFlash('error', 'Текущий пароль указан неверно.');
            header('Location: ' . route('account.settings'));
            exit;
        }

        // 2. Enforce strict match boundaries on the new password confirmation
        if ($newPassword !== $confirmPassword) {
            \App\Core\Session::setFlash('error', 'Новый пароль и его подтверждение не совпадают.');
            header('Location: ' . route('account.settings'));
            exit;
        }

        // 3. Parse length requirements using your core Validator layer
        $validator = new \App\Core\Validator();
        $isValid = $validator->validate(['new_password' => $newPassword], ['new_password' => 'required|min:6']);

        if (!$isValid) {
            \App\Core\Session::setFlash('error', 'Новый пароль должен содержать как минимум 6 символов.');
            header('Location: ' . route('account.settings'));
            exit;
        }

        // Prevent updating to the exact same password string
        if (password_verify($newPassword, $user['password'])) {
            \App\Core\Session::setFlash('error', 'Новый пароль не должен совпадать с текущим.');
            header('Location: ' . route('account.settings'));
            exit;
        }

        // 4. Encrypt using secure native BCRYPT algorithms and save to database
        $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $userModel->update($userId, [
            'password' => $newHashedPassword
        ]);

        // 5. SECURITY EXTRA: Force session regeneration to mitigate fixation vectors
        session_regenerate_id(true);
        $_SESSION['last_activity_time'] = time();

        \App\Core\Audit::log('user.password_changed', 'Пользователь успешно изменил свой пароль из личного кабинета');
        \App\Core\Session::setFlash('success', 'Ваш системный пароль успешно изменен.');
        
		
		// Inside updatePassword() right after updating the user table row record:
		(new \App\Modules\Users\Models\Notification())->create([
			'user_id' => $userId,
			'type' => 'warning',
			'message' => 'Системный пароль вашего аккаунта был успешно изменен.'
		]);
		
		
        header('Location: ' . route('account.settings'));
        exit;
    }

    public function showRequestResetForm(): void
    {
        $this->render('password_request', ['title' => 'Восстановление пароля', 'request' => new \App\Core\Request()]);
    }

    public function sendResetLink(): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf();

        // Enforce Captcha verification check to prevent bot flooding
        if (!\App\Core\Captcha::verify()) {
            \App\Core\Session::setFlash('error', 'Капча не пройдена.');
            header('Location: /password/reset');
            exit;
        }

        $email = trim($request->getParams('email'));
        $userModel = new \App\Modules\Users\Models\User();
        $user = $userModel->findBy('email', $email);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            (new \App\Modules\Users\Models\PasswordReset())->createToken($email, $token);

            $resetLink = config('config.app.url') . "/password/reset/" . $token;

            // --- CLEAN ARCHITECTURE REFACTOR: FETCH LOCALIZED RECOVERY CONTENT ---
            $subject  = \App\Core\Lang::get('email_recovery_subject', [e(app_name())]);
            $htmlBody = \App\Core\Lang::format('email_recovery_body', [
                e($user['username']), 
                $resetLink
            ]);

            \App\Core\Mailer::send($email, $subject, $htmlBody);
            // ---------------------------------------------------------

            \App\Core\Session::setFlash('success', 'Инструкции по восстановлению отправлены. Проверьте ваш Email (или логи storage/logs/app.log).');
			
        } else {
            \App\Core\Session::setFlash('error', 'Пользователь с таким Email адресом не зарегистрирован.');
        }

        header('Location: /password/reset');
        exit;
    }

    public function showResetPasswordForm(string $token): void
    {
        $resetModel = new \App\Modules\Users\Models\PasswordReset();
        $email = $resetModel->validateToken($token);

        if (!$email) {
            \App\Core\Session::setFlash('error', 'Ссылка восстановления недействительна или срок её действия (1 час) истёк.');
            header('Location: /password/reset');
            exit;
        }

        $this->render('password_reset_form', [
            'title' => 'Придумайте новый пароль',
            'token' => $token,
            'request' => new \App\Core\Request()
        ]);
    }

    public function executePasswordReset(): void
    {
        $request = new \App\Core\Request();
        $request->validateCsrf();

        $token = $request->getParams('token');
        $password = $request->getParams('password');
        $confirmPassword = $request->getParams('confirm_password');

        $resetModel = new \App\Modules\Users\Models\PasswordReset();
        $email = $resetModel->validateToken($token);

        if (!$email) {
            \App\Core\Session::setFlash('error', 'Ошибка валидации токена.');
            header('Location: /password/reset');
            exit;
        }

        if ($password !== $confirmPassword || strlen($password) < 6) {
            \App\Core\Session::setFlash('error', 'Пароли не совпадают или длина пароля менее 6 символов.');
            header('Location: /password/reset/' . $token);
            exit;
        }

        $userModel = new \App\Modules\Users\Models\User();
        $user = $userModel->findBy('email', $email);

        if ($user) {
            $userModel->update((int)$user['id'], [
                'password' => password_hash($password, PASSWORD_BCRYPT)
            ]);
            $resetModel->clearToken($token);

            \App\Core\Audit::log('user.password_recovered', "Пользователь ID: {$user['id']} успешно восстановил доступ через Email");
            \App\Core\Session::setFlash('success', 'Ваш пароль успешно изменен. Теперь вы можете войти в аккаунт.');
            header('Location: /login');
            exit;
        }

        header('Location: /');
        exit;
    }

   /**
     * Verify token signatures and activate user records (GET /register/activate/{token})
     */
    public function activateAccount(string $token): void
    {
        $activationModel = new \App\Modules\Users\Models\EmailActivation();
        $userId = $activationModel->verifyToken($token);

        if (!$userId) {
            \App\Core\Session::setFlash('error', 'Код активации недействителен или устарел.');
            header('Location: /login');
            exit;
        }

        $userModel = new \App\Modules\Users\Models\User();
        $userModel->update($userId, [
            'is_active' => 1 // Set active status flag to true
        ]);

        // Clean up verification token record off disk storage
        $activationModel->clearToken($token);

        \App\Core\Audit::log('user.activated', "Учетная запись пользователя ID: {$userId} успешно подтверждена и активирована");
        \App\Core\Session::setFlash('success', 'Ваш аккаунт успешно активирован! Теперь вы можете войти в систему.');
        header('Location: /login');
        exit;
    }

}
