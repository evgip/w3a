<?php $request = new \App\Core\Request(); ?>

<h3 class="dev-tools-title">🛠️ Инструменты разработчика фреймворка</h3>
<p class="dev-tools-desc">
    Управление состоянием, оптимизацией и логами ядра без необходимости использовать терминал CLI или сторонние утилиты.
</p>

<!-- БЛОК 1: ASSET PIPELINE (CSS + JS) -->
<div class="card dev-card">
    <div class="card-row">
        <div class="card-info">
            <strong class="card-title">⚡ Компиляция ресурсов (Asset Pipeline)</strong>
            <span class="card-text">
                Автоматический рекурсивный поиск файлов <code>.css</code> и <code>.js</code> внутри всех папок <code>app/Modules/</code>. 
                PHP склеит их, очистит от комментариев/пробелов и перезапишет финальные продакшен-бандлы в <code>public/css/app.min.css</code> и <code>public/js/app.min.js</code>.
            </span>
        </div>
        <form action="/admin/tools/compile-assets" method="POST" class="card-form">
            <?= $request->csrfField() ?>
            <button type="submit" class="btn btn-blue">
                Скомпилировать ассеты (CSS + JS)
            </button>
        </form>
    </div>
</div>

<!-- БЛОК 2: ОЧИСТКА ФАЙЛОВЫХ ЛОГОВ -->
<div class="card dev-card">
    <div class="card-row">
        <div class="card-info">
            <strong class="card-title">📂 Файловая система: Очистка storage/logs/</strong>
            <span class="card-text">
                Мгновенное обнуление содержимого файлов логов разработки <code>app.log</code> (ошибки PHP) и резервного лога безопасности <code>audit.log</code>, расположенных в изолированном верхнем разделе вашего проекта. Сами файлы и их права доступа сохраняются.
            </span>
        </div>
        <form action="/admin/tools/clear-file-logs" method="POST" class="card-form">
            <?= $request->csrfField() ?>
            <button type="submit" onclick="return confirm('Вы уверены, что хотите полностью очистить файлы логов на диске?');" class="btn btn-orange">
                🗑️ Очистить логи на диске
            </button>
        </form>
    </div>
</div>

<!-- БЛОК 3: ОЧИСТКА ЛОГОВ В БАЗЕ ДАННЫХ -->
<div class="card dev-card dev-card-last">
    <div class="card-row">
        <div class="card-info">
            <strong class="card-title card-title-danger">⚠️ База данных: Очистка таблицы audit_logs</strong>
            <span class="card-text">
                Выполняет SQL-команду <code>TRUNCATE TABLE</code>, полностью и безвозвратно удаляя все накопленные записи аудита действий пользователей из СУБД MySQL. Операция также сбрасывает счетчик автоинкремента ID на 1.
            </span>
        </div>
        <form action="/admin/tools/clear-db-audit" method="POST" class="card-form">
            <?= $request->csrfField() ?>
            <button type="submit" onclick="return confirm('ВНИМАНИЕ! Вы уверены, что хотите БЕЗВОЗВРАТНО удалить все логи аудита из Базы Данных?');" class="btn btn-red">
                🚨 Очистить аудит в БД
            </button>
        </form>
    </div>
	
    <!-- БЛОК 4: ОПТИМИЗАЦИЯ ЯДРА (Исправлена вложенность в HTML) -->
    <div class="card dev-card">
		<div class="card-row">
			<div class="card-info">
				<strong class="card-title">🔀 Оптимизация ядра: Кэширование маршрутизатора</strong>
				<span class="card-text">
					Склеивает и компилирует конфигурационные карты роутов всех изолированных модулей в единый плоский PHP-массив внутри <code>storage/cache/</code>. 
					Это убирает необходимость сканировать жесткий диск при каждом посещении сайта пользователями, кратно ускоряя генерацию страниц.
				</span>
			</div>
			
			<div class="btn-group">
				<form action="<?= route('admin.tools.cache_routes') ?>" method="POST" class="card-form">
					<?= $request->csrfField() ?>
					<button type="submit" class="btn btn-blue">
						🚀 Включить кэш роутов
					</button>
				</form>

				<form action="<?= route('admin.tools.clear_cache_routes') ?>" method="POST" class="card-form">
					<?= $request->csrfField() ?>
					<button type="submit" class="btn btn-red">
						🗑️ Сбросить кэш
					</button>
				</form>
			</div>
		</div>
	</div>
	
	
    <div class="card dev-card">
		<div class="card-row">
			<div class="card-info">
				<strong class="card-title">Тестовой письмо. Добавьте Email:</strong>
				<span class="card-text">
				  <form action="<?= route('admin.tools.send_test_email') ?>" method="POST" class="admin-form-group ">
					<?= $request->csrfField() ?>
				
					        
            <input type="email" name="email" required value="">
				</span>
			</div>
			
			<div class="btn-group">
					<button type="submit" class="btn btn-red">
						📩 Отправить тестовое письмо
					</button>
				</form>


			</div>
		</div>
	</div>
	
	 


 
	
</div>

