# 🌐 W3A - Новостной агрегатор в стиле Hacker News и Lobster

[🇷🇺 Русский](README.ru.md) | [🇬🇧 English](README.md)

Современный, легковесный новостной агрегатор, созданный на PHP 8.1+ и MySQL, вдохновлённый Hacker News и Lobster. Модульная HMVC-архитектура, обновления в реальном времени и комплексные инструменты модерации.

![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-orange)
![License](https://img.shields.io/badge/License-MIT-green)


![W3A home](https://raw.githubusercontent.com/evgip/soc/main/public/github-home.png)


![W3A admin](https://raw.githubusercontent.com/evgip/soc/main/public/github-admin.png)


![W3A setting](https://raw.githubusercontent.com/evgip/soc/main/public/github-setting.png)


## 🚀 Возможности

### Основной функционал
- **Новости** - Публикация и просмотр новостей с URL или текстовым содержимым
- **Комментарии** - Древовидная система комментариев с вложенными ответами
- **Система голосований** - Голосование за/против новостей и комментариев в реальном времени
- **Аутентификация** - Безопасная регистрация, вход и управление сессиями
- **Профили пользователей** - Отслеживание активности, опубликованные новости и история комментариев

### Расширенные возможности
- **Обновления в реальном времени** - AJAX-обновления голосов и комментариев
- **Инструменты модерации** - Блокировка пользователей, доменов и управление контентом
- **Журнал аудита** - Комплексное отслеживание всех административных действий
- **Ограничение частоты запросов** - Защита от злоупотреблений с настраиваемыми лимитами
- **CSRF-защита** - Токены безопасности для всех форм
- **Мягкое удаление** - Восстанавливаемое удаление контента
- **Поиск** - Полнотекстовый поиск по новостям и комментариям

### Пользовательский опыт
- **Адаптивный дизайн** - Мобильная версия интерфейса
- **Тёмная тема** - Автоматическое переключение по системным настройкам
- **Горячие клавиши** - Навигация для опытных пользователей
- **Поддержка Markdown** - Форматирование текста в комментариях

## 🛠️ Технологический стек

| Компонент | Технология |
|-----------|-----------|
| **Backend** | PHP 8.1+ (без фреймворка, собственная HMVC) |
| **База данных** | MySQL 8.0+ с PDO |
| **Frontend** | Vanilla JavaScript, CSS3 |
| **Архитектура** | Модульный паттерн HMVC |
| **Безопасность** | CSRF-токены, rate limiting, хеширование паролей (bcrypt) |
| **Сервер** | Apache/Nginx с mod_rewrite |

## 📦 Установка

Установите пакет через [Composer](http://getcomposer.org/). 

### Требования
- PHP 8.1 или выше
- MySQL 8.0 или выше
- Apache/Nginx с включённой переадресацией URL
- Composer (опционально, для разработки)

### Шаг 1: Клонирование репозитория

```bash
git clone https://github.com/evgip/w3a.git
cd w3a
```

### Шаг 2: Настройка базы данных

1. Создайте базу данных MySQL:
```sql
CREATE DATABASE w3a CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Импортируйте схему:
```bash
mysql -u your_username -p w3a < db/schema.sql
```

3. Обновите учётные данные в `app/Config/config.php`:
```php
'database' => [
	'host' => 'MySQL-8.2',
	'port' => '3306',
	'dbname' => 'soc',
	'username' => 'root',
	'password' => '',
	'charset' => 'utf8mb4',
]
```

### Шаг 3: Настройка приложения

Отредактируйте `app/Config/config.php`:
```php
'app' => [
	'name' => 'w3a', // Короткое
	'url' => 'http://soc.local',
	'lang' => 'ru', // Язык по умолчанию
```

### Шаг 4: Установка прав доступа

```bash
chmod -R 755 storage/
chmod -R 755 public/
```

### Шаг 5: Настройка веб-сервера

**Apache** (`.htaccess` уже включён):
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

**Nginx**:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Шаг 6: Данные для входа

- Администратор: admin@example.com / password
- Пользователь: test@test.ru / password

## 📁 Структура проекта

```
w3a/
├── app/
│	├── Сonfig/                 # Конфигурация
│   │ 	└── config.php          # Настройки приложения
│   ├── Core/                   # Основные классы фреймворка
│   │   ├── Router.php          # Маршрутизация URL
│   │   ├── Controller.php      # Базовый контроллер
│   │   ├── Model.php           # Базовая модель
│   │   ├── Database.php        # Обёртка PDO
│   │   ├── Auth.php            # Аутентификация
│   │   ├── Session.php         # Управление сессиями
│   │   ├── Request.php         # Обработка HTTP-запросов
│   │   ├── Response.php        # HTTP-ответы
│   │   ├── View.php            # Рендеринг шаблонов
│   │   ├── Audit.php           # Журнал аудита
│   │   └── Validator.php       # Валидация ввода
│   │
│   └── Modules/                 # Модули функционала (HMVC)
│       ├── Auth/               # Модуль аутентификации
│       │   ├── Controllers/
│       │   ├── Models/
│       │   ├── Views/
│       │   └── routes.php
│       ├── Stories/            # Модуль новостей
│       ├── Comments/           # Модуль комментариев
│       ├── Votes/              # Система голосований
│       ├── Users/              # Профили пользователей
│       ├── Moderation/         # Инструменты модерации
│       ├── Origins/            # Управление доменами
│       └── Api/                # REST API
├── public/                     # Публичные ресурсы
│   ├── index.php              # Точка входа
│   ├── css/                   # Стили
│   ├── js/                    # JavaScript файлы
│   └── images/                # Изображения
│
├── storage/                   # Записываемое хранилище
│   ├── logs/                  # Логи приложения
│   └── cache/                 # Файлы кэша
│
├── db/                        # База данных
│   └── schema.sql             # Схема базы данных
│
└── README.ru.md               # Этот файл
```

## 🔒 Безопасность

- **Хеширование паролей** - bcrypt с автоматической солью
- **CSRF-защита** - Проверка токенов во всех формах
- **Защита от SQL-инъекций** - Подготовленные выражения (PDO)
- **Защита от XSS** - Экранирование вывода через `htmlspecialchars()`
- **Rate Limiting** - Настраиваемые лимиты для каждого действия
- **Безопасность сессий** - HTTP-only cookies, регенерация
- **Валидация ввода** - Серверная проверка всех входных данных

## 🧪 Разработка

### Режим отладки

Включите в `app/Config/config.php`:
```php
'env' => 'development', // development или production
```

## 📊 Схема базы данных

### Основные таблицы
- `users` - Учётные записи и профили пользователей
- `stories` - Новости (URL или текст)
- `comments` - Древовидные комментарии
- `votes` - Голоса за/против
- `sessions` - Активные сессии пользователей
- `audit_logs` - Журнал административной активности
- `domains` - Список заблокированных доменов
- `flags` - Журнал жалоб

Полная схема в файле `db/schema.sql`.

## 🤝 Участие в разработке

Вклад приветствуется! Пожалуйста, следуйте этим шагам:

1. Форкните репозиторий
2. Создайте ветку для новой функции (`git checkout -b feature/amazing-feature`)
3. Зафиксируйте изменения (`git commit -m 'Add amazing feature'`)
4. Отправьте в ветку (`git push origin feature/amazing-feature`)
5. Откройте Pull Request

## 📝 Лицензия

Этот проект лицензирован под MIT License - подробности в файле [LICENSE](LICENSE).

## 🙏 Благодарности

- Вдохновлено функционалом [Hacker News](https://news.ycombinator.com/) и [Lobster](https://lobste.rs/)
- Скорость, простотой фреймворка [HLEB](https://hleb2framework.ru/)
- Создано на чистом PHP (без фреймворков)
- Модульная HMVC-архитектура

## 📧 Контакты

- **Автор**: Evg
- **Репозиторий**: https://github.com/evgip/w3a
- **Баги и предложения**: https://github.com/evgip/w3a/issues

---

**⭐ Если вам полезен этот проект, поставьте звёздочку на GitHub!**