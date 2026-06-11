<?php $request = new \App\Core\Request(); ?>

<h3 style="margin-bottom: 5px;">🛠️ Инструменты разработчика фреймворка</h3>
<p style="color: #7f8c8d; margin-bottom: 30px;">
    Управление состоянием, оптимизацией и логами ядра без необходимости использовать терминал CLI или сторонние утилиты.
</p>

<!-- БЛОК 1: ASSET PIPELINE (CSS + JS) -->
<div class="card" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
        <div style="flex: 1; min-width: 300px;">
            <strong style="font-size: 16px; display: block; margin-bottom: 5px; color: #2c3e50;">⚡ Компиляция ресурсов (Asset Pipeline)</strong>
            <span style="color: #7f8c8d; font-size: 14px; display: block; line-height: 1.5;">
                Автоматический рекурсивный поиск файлов <code>.css</code> и <code>.js</code> внутри всех папок <code>app/Modules/</code>. 
                PHP склеит их, очистит от комментариев/пробелов и перезапишет финальные продакшен-бандлы в <code>public/css/app.min.css</code> и <code>public/js/app.min.js</code>.
            </span>
        </div>
        <form action="/admin/tools/compile-assets" method="POST" style="margin: 0;">
            <?= $request->csrfField() ?>
            <button type="submit" style="padding: 12px 20px; background: #2980b9; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; white-space: nowrap;">
                Скомпилировать ассеты (CSS + JS)
            </button>
        </form>
    </div>
</div>

<!-- БЛОК 2: ОЧИСТКА ФАЙЛОВЫХ ЛОГОВ -->
<div class="card" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
        <div style="flex: 1; min-width: 300px;">
            <strong style="font-size: 16px; display: block; margin-bottom: 5px; color: #2c3e50;">📂 Файловая система: Очистка storage/logs/</strong>
            <span style="color: #7f8c8d; font-size: 14px; display: block; line-height: 1.5;">
                Мгновенное обнуление содержимого файлов логов разработки <code>app.log</code> (ошибки PHP) и резервного лога безопасности <code>audit.log</code>, расположенных в изолированном верхнем разделе вашего проекта. Сами файлы и их права доступа сохраняются.
            </span>
        </div>
        <form action="/admin/tools/clear-file-logs" method="POST" style="margin: 0;">
            <?= $request->csrfField() ?>
            <button type="submit" onclick="return confirm('Вы уверены, что хотите полностью очистить файлы логов на диске?');" 
                    style="padding: 12px 20px; background: #e67e22; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; white-space: nowrap;">
                🗑️ Очистить логи на диске
            </button>
        </form>
    </div>
</div>

<!-- БЛОК 3: ОЧИСТКА ЛОГОВ В БАЗЕ ДАННЫХ -->
<div class="card" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.04);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
        <div style="flex: 1; min-width: 300px;">
            <strong style="font-size: 16px; display: block; margin-bottom: 5px; color: #e74c3c;">⚠️ База данных: Очистка таблицы audit_logs</strong>
            <span style="color: #7f8c8d; font-size: 14px; display: block; line-height: 1.5;">
                Выполняет SQL-команду <code>TRUNCATE TABLE</code>, полностью и безвозвратно удаляя все накопленные записи аудита действий пользователей из СУБД MySQL. Операция также сбрасывает счетчик автоинкремента ID на 1.
            </span>
        </div>
        <form action="/admin/tools/clear-db-audit" method="POST" style="margin: 0;">
            <?= $request->csrfField() ?>
            <button type="submit" onclick="return confirm('ВНИМАНИЕ! Вы уверены, что хотите БЕЗВОЗВРАТНО удалить все логи аудита из Базы Данных?');" 
                    style="padding: 12px 20px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; white-space: nowrap;">
                🚨 Очистить аудит в БД
            </button>
        </form>
    </div>
	
	<div class="card" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 25px;">
		<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
			<div style="flex: 1; min-width: 300px;">
				<strong style="font-size: 16px; display: block; margin-bottom: 5px; color: #2c3e50;">🔀 Оптимизация ядра: Кэширование маршрутизатора</strong>
				<span style="color: #7f8c8d; font-size: 14px; display: block; line-height: 1.5;">
					Склеивает и компилирует конфигурационные карты роутов всех изолированных модулей в единый плоский PHP-массив внутри <code>storage/cache/</code>. 
					Это убирает необходимость сканировать жесткий диск при каждом посещении сайта пользователями, кратно ускоряя генерацию страниц.
				</span>
			</div>
			
			<!-- Две кнопки: Создать кэш и Сбросить кэш -->
			<div style="display: flex; gap: 10px; flex-wrap: wrap;">
				<form action="<?= route('admin.tools.cache_routes') ?>" method="POST" style="margin: 0;">
					<?= $request->csrfField() ?>
					<button type="submit" class="btn-action btn-action-success" style="padding: 12px 20px; font-weight: bold; font-size: 14px; color: white; border: none; border-radius: 4px; cursor: pointer;">
						🚀 Включить кэш роутов
					</button>
				</form>

				<form action="<?= route('admin.tools.clear_cache_routes') ?>" method="POST" style="margin: 0;">
					<?= $request->csrfField() ?>
					<button type="submit" class="btn-action btn-action-danger" style="padding: 12px 20px; font-weight: bold; font-size: 14px; color: white; border: none; border-radius: 4px; cursor: pointer;">
						🗑️ Сбросить кэш
					</button>
				</form>
			</div>
		</div>
	</div>
	
</div>

