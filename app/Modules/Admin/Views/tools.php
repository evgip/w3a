<h1>🛠️ Инструменты разработчика фреймворка</h1>

<p class="hint">
    Управление состоянием, оптимизацией и логами ядра без необходимости использовать терминал CLI или сторонние утилиты.
</p>

<hr>

<!-- БЛОК 1: ASSET PIPELINE (CSS + JS) -->
<div class="form-field-group">
    <h3>⚡ Компиляция ресурсов (Asset Pipeline)</h3>
    <p class="hint">
        Автоматический рекурсивный поиск файлов <code>.css</code> и <code>.js</code> внутри всех папок <code>app/Modules/</code>. 
        PHP склеит их, очистит от комментариев/пробелов и перезапишет финальные продакшен-бандлы в <code>public/css/app.min.css</code> и <code>public/js/app.min.js</code>.
    </p>
    
    <form action="<?= route('admin.tools.compile_assets') ?>" method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="btn-primary">Скомпилировать ассеты (CSS + JS)</button>
    </form>
</div>

<hr>

<!-- БЛОК 2: ОЧИСТКА ФАЙЛОВЫХ ЛОГОВ -->
<div class="form-field-group">
    <h3>📂 Файловая система: Очистка storage/logs/</h3>
    <p class="hint">
        Мгновенное обнуление содержимого файлов логов разработки <code>app.log</code> (ошибки PHP) и резервного лога безопасности <code>audit.log</code>, расположенных в изолированном верхнем разделе вашего проекта. Сами файлы и их права доступа сохраняются.
    </p>
    
    <form action="<?= route('admin.tools.clear_file_logs') ?>" method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="button restore-link" style="color: #ac130d;" data-confirm="Вы уверены, что хотите полностью очистить файлы логов на диске?">
            🗑️ Очистить логи на диске
        </button>
    </form>
</div>

<hr>

<!-- БЛОК 3: ОЧИСТКА ЛОГОВ В БАЗЕ ДАННЫХ -->
<div class="form-field-group">
    <h3>⚠️ База данных: Очистка таблицы audit_logs</h3>
    <p class="hint">
        Выполняет SQL-команду <code>TRUNCATE TABLE</code>, полностью и безвозвратно удаляя все накопленные записи аудита действий пользователей из СУБД MySQL. Операция также сбрасывает счетчик автоинкремента ID на 1.
    </p>
    
    <form action="<?= route('admin.tools.clear_db_audit') ?>" method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="button restore-link" style="color: #ac130d;" data-confirm="ВНИМАНИЕ! Вы уверены, что хотите БЕЗВОЗВРАТНО удалить все логи аудита из Базы Данных?">
            🚨 Очистить аудит в БД
        </button>
    </form>
</div>

<hr>

<!-- БЛОК 4: ОПТИМИЗАЦИЯ ЯДРА -->
<div class="form-field-group">
    <h3>🔀 Оптимизация ядра: Кэширование маршрутизатора</h3>
    <p class="hint">
        Склеивает и компилирует конфигурационные карты роутов всех изолированных модулей в единый плоский PHP-массив внутри <code>storage/cache/</code>. 
        Это убирает необходимость сканировать жесткий диск при каждом посещении сайта пользователями, кратно ускоряя генерацию страниц.
    </p>
    
    <div class="form-actions">
        <form action="<?= route('admin.tools.cache_routes') ?>" method="POST" style="display:inline;">
            <?= csrf_field() ?>
            <button type="submit" class="btn-primary">🚀 Включить кэш роутов</button>
        </form>
        
        <form action="<?= route('admin.tools.clear_cache_routes') ?>" method="POST" style="display:inline;">
            <?= csrf_field() ?>
            <button type="submit" class="button restore-link" style="color: #ac130d;" data-confirm="Вы уверены, что хотите сбросить кэш маршрутов?">
                🗑️ Сбросить кэш
            </button>
        </form>
    </div>
</div>

<hr>

<!-- БЛОК 5: ТЕСТОВОЕ ПИСЬМО -->
<div class="form-field-group">
    <h3>📩 Отправка тестового письма</h3>
    <p class="hint">
        Отправляет тестовое сообщение на указанный email-адрес для проверки корректности настройки почтового сервера.
    </p>
    
    <form action="<?= route('admin.tools.send_test_email') ?>" method="POST">
        <?= csrf_field() ?>
        
        <div class="form-field-group">
            <label for="test_email">Email адрес получателя:</label>
            <input type="email" id="test_email" name="email" required class="form-input-wide" placeholder="example@domain.com">
        </div>
        
        <button type="submit" class="btn-primary">📩 Отправить тестовое письмо</button>
    </form>
</div>