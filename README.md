# 🌐 W3A — Modular Forum

[English](#english) | [Русский](#русский)

---

## English

A highly secure, enterprise-grade, and lightweight custom Hierarchical Modular MVC (HMVC) framework engineered on modern PHP. This application mirrors optimization patterns inspired by high-utility link-aggregation communities like **Lobsters** and **Hacker News**, managing complex data workflows such as sharded asset tracking, single-query recursive tree discussions, sliding-window rate limiting, and secure polymorphic upvoting.

The platform functions under strict **Content Security Policy (CSP)** configurations. 100% of presentation design tokens and interaction scripts are completely scrubbed from the view templates and aggregated into compressed public production asset bundles.

![LibArea test design](https://raw.githubusercontent.com/evgip/soc/main/public/github-admin.png)


![LibArea test design](https://raw.githubusercontent.com/evgip/soc/main/public/github-setting.png)



### 🛠️ Core Architectural Milestones

*   **HMVC Domain Encapsulation:** Absolute separation of concerns. Every isolated feature (`Stories`, `Users`, `Admin`, `Messages`, `Tags`, `Votes`) maintains its own dedicated controller stack, routing maps, class models, and template layers.
*   **Decoupled `Common` Design Module:** Shared system tokens (buttons, form inputs, avatar rendering sizes grids, flash alerts) reside inside an independent global baseline layer (`app/Modules/Common`), ensuring components are fully reusable and DRY compliant.
*   **Asynchronous AJAX Polymorphic Voting:** Rating transactions are intercepted natively via JavaScript `fetch` loops, processing atomic score delta transitions (`+1`, `-1`, or `+2` direction flips) on the fly without refreshing the page or violating CSP.
*   **Lobsters-Style Protection Rules:** Access controllers query aggregated user profile metrics dynamically to enforce safety boundaries (e.g., blocking new low-karma accounts from performing downvotes).
*   **Decoupled Multi-Tier Security Stack:** Built-in cryptographic anti-CSRF form guards, deep binary byte-signature MIME evaluation (`finfo`), single-lookup IP Firewall lockouts, and automated security alert notifications pushed straight to admin dashboards.
*   **PHPMailer SMTP Integration:** Email templates (account activations and access tokens recovery links) are fully localized inside `Lang` dictionaries and dispatched using secure configuration-driven SMTP servers.


### 📂 Repository Directory Blueprint

```text
├── app/
│   ├── Config/           # Configuration environment maps (CSP whitelists, SMTP, Rate Limits)
│   ├── Core/             # Central Framework Engine (Router, Controller, Model, Mailer...)
│   └── Modules/          # Autonomous Feature Domain Repositories
│       ├── Admin/        # Administration telemetry decks, audit filters & mod models
│       ├── Common/       # Core shared design tokens, baseline reset CSS & global components
│       ├── Messages/     # Dialogue room rooms, real-time unread badges & private chats
│       ├── Stories/      # News feeds timeline, Markdown posts & recursive comments tree
│       ├── Tags/         # Categorized tag catalog matrices matching Lobsters grid layouts
│       └── Users/        # Profile workspaces, auth logic & secure shard storage uploads
├── db/
│   └── schema.sql        # Core baseline relational database architecture dump
├── public/               # Shared HTTP webroot entry point
│   ├── css/              # Minified production compiled asset sheets (app.min.css)
│   ├── js/               # Consolidated nonced JavaScript logic bundle (app.min.js)
│   └── index.php         # Central bootstrapper application execution checkpoint
└── storage/
    ├── cache/            # Serialized flat static routes compiled cache targets
    └── logs/             # Central system execution log files (app.log)
```

### 🚀 Fast Production Setup

1.  **Hydrate Autoloader Maps:**
    ```bash
    composer install
    composer dump-autoload
    ```
2.  **Initialize Database Blueprints:** Create a new MySQL/MariaDB database schema partition and import the core structures `db/schema.sql`.
3.  **Configure Environment Parameters:** Set up your database credentials, system base URLs (`app.url`), and target SMTP servers inside **`app/Config/config.php`** and **`app/Config/mail.php`**.
4.  **Compile Static Asset Pipelines:** Log in with an administrator card profile, navigate to `http://soc.local`, and click **«Скомпилировать ресурсы (CSS + JS)»**.

---

## Русский

Безопасный, высокопроизводительный и легковесный кастомный иерархически-модульный MVC (HMVC) форум на чистом PHP. Архитектура и функционал спроектированы по лекалам платформ **Lobsters** и **Hacker News**: здесь реализованы такие сложные инженерные решения, как шардирование дискового пространства, рекурсивные ветки обсуждений за один SQL-запрос, скользящее ограничение частоты запросов и полиморфная система оценки контента.

Платформа работает под эгидой жесткой политики **Content Security Policy (CSP)** — 100% стилей оформления, дизайн-токенов и скриптов интерактивности полностью вынесены из HTML-представлений в сжатые компилируемые статические бандлы.

### 🛠️ Ключевые архитектурные особенности

*   **Изолированная HMVC-архитектура:** Абсолютная инкапсуляция доменных зон. Каждый модуль (`Stories`, `Users`, `Admin`, `Messages`, `Tags`, `Votes`) содержит собственные контроллеры, локальные роуты, модели данных и шаблоны отображения.
*   **Выделенный модуль `Common`:** Общая дизайн-система (системные кнопки, элементы форм, адаптивная сетка размеров аватарок, всплывающие сессионные уведомления) вынесена в независимый базовый слой (`app/Modules/Common`), гарантируя соблюдение принципа DRY.
*   **Асинхронный AJAX-движок оценки (Лайки):** Переключение рейтинга полностью перехвачено нативным JavaScript-клиентом. Изменение рейтинга (дельты `+1`, `-1` или смена вектора на `+2`) выполняется на лету в фоновом режиме, без мигания экрана и перезагрузки страницы.
*   **Лобстер-механизмы защиты рейтинга:** Контроллеры динамически проверяют репутационный профиль и карму голосующего в СУБД, автоматически скрывая в UI и блокируя на сервере кнопки дизлайков (▼) для аккаунтов-новичков.
*   **Многоуровневый комплекс безопасности:** Надежная криптографическая anti-CSRF защита, проверка реальных байтовых MIME-типов файлов через `finfo`, ограничение частоты запросов (Rate Limiting), встроенный межсетевой экран (Firewall IP) и телеметрия атак в реальном времени.
*   **Интеграция PHPMailer SMTP:** Все почтовые шаблоны (активация учетных записей, ссылки восстановления доступа) вынесены в файлы локализации (`Lang`) и отправляются через защищенные SMTP-серверы, прописанные в изолированных конфигах.


### 📂 Архитектурная карта репозитория

```text
├── app/
│   ├── Config/           # Конфигурационные файлы среды (CSP-карты, лимиты, SMTP)
│   ├── Core/             # Системное ядро фреймворка (Router, Controller, Model, Mailer...)
│   └── Modules/          # Независимые инкапсулированные модули бизнес-логики
│       ├── Admin/        # Панель модератора, аудит-логи, административные модели пользователей
│       ├── Common/       # Базовые стили сброса, глобальные UI-компоненты и дизайн-система
│       ├── Messages/     # Личные сообщения, комнаты диалогов, счетчики и пагинация чатов
│       ├── Stories/      # Лента новостей, Markdown-публикации и дерево комментариев
│       ├── Tags/         # Каталог тегов сообщества с многоколоночной сеткой Lobsters
│       └── Votes/        # Транзакции полиморфного переключения лайков и дизлайков
├── db/
│   └── schema.sql        # Базовый SQL-дамп архитектуры базы данных
├── public/               # Публичная точка входа веб-сервера (Webroot)
│   ├── css/              # Сжатые и оптимизированные компилятором файлы стилей (app.min.css)
│   ├── js/               # Объединенный и очищенный JS-бандл логики интерфейса (app.min.js)
│   └── index.php         # Центральный бутстрап-файл инициализации приложения
└── storage/
    ├── cache/            # Скомпилированный кэш карт маршрутизатора
    └── logs/             # Журнал системных логов работы ядра (app.log)
```

### 🚀 Быстрый запуск на локальной машине

1.  **Восстановление автозагрузки классов Composer:**
    ```bash
    composer install
    composer dump-autoload
    ```
2.  **Инициализация СУБД:** Создайте чистую базу данных в MySQL/MariaDB и импортируйте базовый дамп `db/schema.sql`.
3.  **Настройка конфигурации:** Заполните параметры подключения к БД, абсолютный базовый URL сайта (`app.url`) и параметры почты в файлах **`app/Config/config.php`** и **`app/Config/mail.php`**.
4.  **Компиляция статических ресурсов:** Перейдите по адресу `http://soc.local` под учетной записью администратора и нажмите кнопку **«Скомпилировать ресурсы (CSS + JS)»**.


License: Open-source software under the [MIT License](LICENSE).
