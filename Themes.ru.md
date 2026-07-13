# 🎨 Система тем (Themes)

Система тем позволяет полностью изменять внешний вид приложения **без правки исходного кода модулей**. Вы можете переопределить любой шаблон, стиль или скрипт, просто создав соответствующий файл в папке темы.

Если файл не найден в теме — система автоматически использует оригинальный файл из модуля. Это называется **Fallback Chain (цепочка отката)**.

---

## 📦 Быстрый старт: создание темы за 5 минут

### 1. Создайте папку темы

```bash
mkdir -p themes/my_theme/Modules/Stories/Views
mkdir -p themes/my_theme/assets/css
mkdir -p themes/my_theme/assets/js
```

### 2. Создайте файл `theme.json`

```json
{
    "name": "My Custom Theme",
    "version": "1.0.0",
    "author": "Your Name",
    "description": "Моя кастомная тема"
}
```

### 3. Активируйте тему в конфиге

Откройте `app/Config/config.php` и измените:

```php
return [
    'app' => [
        'name' => 'My App',
        'env' => 'production',
        'theme' => 'my_theme', // ← имя папки в /themes
    ],
];
```

### 4. Переопределите любой шаблон

Например, создайте `themes/my_theme/Modules/Stories/Views/show.php` — и он автоматически заменит оригинальный шаблон просмотра истории.

**Готово!** 🎉

---

## 📂 Структура папок темы

```text
/themes/
└── my_theme/
    ├── theme.json                          # Метаданные темы (обязательно)
    ├── layout.php                          # Глобальный layout (каркас страницы)
    │
    ├── Modules/                            # Переопределения шаблонов модулей
    │   ├── Stories/Views/
    │   │   ├── index.php                   # Лента историй
    │   │   ├── show.php                    # Просмотр истории
    │   │   ├── create.php                  # Создание истории
    │   │   └── partials/
    │   │       ├── _story_meta.php         # Частичный шаблон (partial)
    │   │       └── _story_card.php
    │   │
    │   ├── Users/Views/
    │   │   └── profile.php
    │   │
    │   └── Common/Views/
    │       └── _header.php
    │
    └── assets/                             # Стили и скрипты темы
        ├── css/
        │   ├── style.css                   # Основные стили темы
        │   └── admin.css                   # Стили для админки (опционально)
        └── js/
            └── theme.js                    # Кастомные скрипты
```

---

## 🔄 Как работает Fallback Chain

Когда система ищет шаблон, она проверяет пути в следующем порядке:

```
1. themes/{theme}/Modules/{Module}/Views/{view}.php   ← Приоритет 1 (тема)
2. themes/{theme}/Views/{view}.php                    ← Приоритет 2 (глобальные)
3. app/Modules/{Module}/Views/{view}.php              ← Приоритет 3 (модуль)
4. app/Modules/Common/Views/{view}.php                ← Приоритет 4 (fallback)
```

**Используется первый найденный файл.** Остальные игнорируются.

### Пример

Запрос: отрендерить `show.php` модуля `Stories` в теме `dark_mode`.

1. ✅ Проверяем: `themes/dark_mode/Modules/Stories/Views/show.php` — **найден!** Используем его.
2. ❌ Если бы не нашли — проверили бы `themes/dark_mode/Views/show.php`.
3. ❌ Если бы и там не было — использовали бы оригинал `app/Modules/Stories/Views/show.php`.

---

## 🖼 Переопределение шаблонов (Views)

### Полный шаблон

Чтобы переопределить шаблон, просто скопируйте его из модуля в тему:

```bash
# Копируем оригинал
cp app/Modules/Stories/Views/show.php themes/my_theme/Modules/Stories/Views/show.php

# Редактируем копию
nano themes/my_theme/Modules/Stories/Views/show.php
```

Теперь все изменения применяются только к вашей теме. Оригинальный файл модуля остаётся нетронутым.

### Частичные шаблоны (Partials)

Partials подключаются через хелпер `partial()`:

```php
<?php partial('Stories::_story_meta', ['story' => $story]); ?>
```

Чтобы переопределить partial, создайте файл по тому же пути в теме:

```text
themes/my_theme/Modules/Stories/Views/_story_meta.php
```

Хелпер автоматически найдёт его в теме и использует вместо оригинала.

---

## 🎨 Переопределение CSS и JavaScript

### Как это работает

Компилятор ассетов (`App\Core\Asset`) сканирует:
1. Все `.css` и `.js` файлы в `app/Modules/`
2. Все `.css` и `.js` файлы в `themes/{active_theme}/assets/`

Затем объединяет их в два бандла:
- `/public/css/app.min.css` — публичные стили
- `/public/css/admin.min.css` — стили админки
- `/public/js/app.min.js` — публичные скрипты

**Важно:** файлы темы добавляются **в конец** бандла. Это значит, что их CSS-правила имеют более высокий приоритет (благодаря каскадности CSS) и могут переопределять стили модулей без `!important`.

### Пример: переопределение цветов

Создайте `themes/my_theme/assets/css/style.css`:

```css
/* Переопределяем базовые цвета */
body {
    background-color: #1a1a1a;
    color: #e0e0e0;
}

a {
    color: #4a9eff;
}

.story-card {
    border: 1px solid #333;
    border-radius: 8px;
}
```

### Пример: стили для админки

Создайте `themes/my_theme/assets/css/admin.css`:

```css
.admin-sidebar {
    background: #2c3e50;
}
```

> **Совет:** чтобы админский CSS попал в `admin.min.css`, путь должен содержать подпапку `admin/` или имя модуля `Admin`. Например:
> - `themes/my_theme/assets/css/admin.css` ✅
> - `themes/my_theme/assets/admin/style.css` ✅

### Подключение ассетов в layout

В вашем `layout.php` используйте статический класс `Asset`:

```php
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= e($title ?? 'Site') ?></title>
    
    <!-- Автоматически подключит app.min.css с кэш-бастингом -->
    <link rel="stylesheet" href="<?= \App\Core\Asset::css() ?>">
</head>
<body>
    <?= $content ?>
    
    <script src="<?= \App\Core\Asset::js() ?>"></script>
</body>
</html>
```

---

## 🏗 Переопределение Layout

Layout — это каркас всей страницы (`<html>`, `<head>`, шапка, футер). Чтобы переопределить его:

```bash
cp app/Modules/Common/Views/layout.php themes/my_theme/layout.php
```

Теперь редактируйте `themes/my_theme/layout.php` — он будет использоваться вместо оригинала.

---

## ⚙️ Активация темы

### Способ 1: Через конфиг (рекомендуется)

В `app/Config/config.php`:

```php
return [
    'app' => [
        'theme' => 'my_theme',
    ],
];
```

### Способ 2: Через админку (если реализовано)

Если у вас есть модуль управления темами, просто выберите тему в списке и нажмите «Активировать».

---

## 🔨 Пересборка ассетов

### Автоматическая пересборка (development)

В режиме `development` система автоматически проверяет `mtime` исходных файлов при каждом запросе. Если какой-то `.css` или `.js` файл изменился — бандл пересобирается.

### Ручная пересборка (production)

В режиме `production` автопересборка отключена для производительности. Чтобы пересобрать ассеты:

**Через админку:**
Нажмите кнопку **«Перестроить CSS/JS»** в панели администратора.

**Через код:**
```php
\App\Core\Asset::forceRebuild();
```

**Через CLI (если есть команда):**
```bash
php cli.php assets:rebuild
```

---

## 🧩 Примеры использования

### Пример 1: Тёмная тема

```text
themes/dark_mode/
├── theme.json
├── layout.php
├── Modules/
│   └── Stories/Views/
│       └── show.php            # Изменённый шаблон истории
└── assets/
    └── css/
        ├── style.css           # Тёмные цвета
        └── admin.css           # Тёмная админка
```

`themes/dark_mode/assets/css/style.css`:
```css
:root {
    --bg-primary: #121212;
    --text-primary: #e0e0e0;
    --accent: #bb86fc;
}

body {
    background: var(--bg-primary);
    color: var(--text-primary);
}
```

### Пример 2: Минимальная тема (только CSS)

Если вам нужно изменить только стили, не трогайте шаблоны:

```text
themes/minimal/
├── theme.json
└── assets/
    └── css/
        └── style.css           # Только стили
```

Система будет использовать оригинальные шаблоны модулей, но применит ваши стили.

### Пример 3: Переопределение одного partial

Хотите изменить только блок с аватаром пользователя?

```bash
# Создайте только этот файл
mkdir -p themes/my_theme/Modules/Users/Views/
touch themes/my_theme/Modules/Users/Views/_avatar.php
```

В шаблоне:
```php
<?php partial('Users::_avatar', ['user' => $user]); ?>
```

Система найдёт `_avatar.php` в теме и использует его. Остальные шаблоны модуля `Users` останутся оригинальными.

---

## ❓ FAQ

### Q: Могу ли я переопределить только один partial, не трогая весь шаблон?
**A:** Да! Просто создайте файл partial в теме по тому же пути. Остальные файлы модуля будут использоваться как есть.

### Q: Что если я удалю файл из темы — сайт упадёт?
**A:** Нет. Система автоматически откатится к оригинальному файлу модуля. Это безопасно.

### Q: Как сбросить кэш браузера после изменения CSS?
**A:** Ничего делать не нужно. Система автоматически добавляет `?v=timestamp` к URL ассетов. Браузер загрузит новую версию.

### Q: Почему мои CSS-правила не применяются?
**A:** Убедитесь, что:
1. Файл лежит в `themes/{active_theme}/assets/css/`.
2. Активная тема правильно указана в конфиге.
3. Вы нажали «Перестроить CSS/JS» в админке (в production).
4. Проверьте `app.min.css` — ваши стили должны быть в конце файла.

### Q: Как добавить JS-скрипт только для определённой страницы?
**A:** Добавьте его в общий `app.min.js` через `themes/{theme}/assets/js/`, а внутри скрипта проверяйте наличие нужных DOM-элементов:

```js
if (document.querySelector('.story-page')) {
    // Код только для страницы истории
}
```

### Q: Могу ли я использовать SCSS/Less?
**A:** Да, но вам нужно предварительно компилировать их в CSS вручную (или через build-скрипт) и класть готовые `.css` файлы в `themes/{theme}/assets/css/`.

---

## 🐛 Отладка

### Проверка активной темы

```php
$theme = \App\Core\Config::get('config.app.theme');
echo "Активная тема: {$theme}";
```

### Просмотр путей, где система искала шаблон

Если шаблон не найден, исключение `RuntimeException` покажет все проверенные пути:

```
View 'show' not found for module 'Stories' (theme: 'dark_mode'). 
Searched in: 
- /var/www/themes/dark_mode/Modules/Stories/Views/show.php
- /var/www/themes/dark_mode/Views/show.php
- /var/www/app/Modules/Stories/Views/show.php
- /var/www/app/Modules/Common/Views/show.php
```

### Проверка содержимого бандла

Откройте `/public/css/app.min.css` — в начале каждого исходного файла будет комментарий:

```css
/* Source: /themes/dark_mode/assets/css/style.css */
body{background:#121212}
/* Source: /app/Modules/Stories/Views/css/story.css */
.story-card{border:1px solid #ccc}
```

Это поможет понять, какие файлы попали в бандл и в каком порядке.

---

## 📋 Чек-лист создания новой темы

- [ ] Создана папка `themes/{theme_name}/`
- [ ] Создан `theme.json` с метаданными
- [ ] (Опционально) Скопирован и изменён `layout.php`
- [ ] (Опционально) Скопированы и изменены нужные шаблоны из `app/Modules/*/Views/`
- [ ] (Опционально) Созданы CSS/JS файлы в `themes/{theme}/assets/`
- [ ] Тема активирована в `config.php`
- [ ] Нажата кнопка «Перестроить CSS/JS» в админке
- [ ] Проверено отображение сайта

---

## 📚 Дополнительные материалы

- **Исходный код компилятора ассетов:** `app/Core/Asset.php`
- **Поиск путей шаблонов:** `app/Core/ViewFinder.php`
- **Рендеринг шаблонов:** `app/Core/View.php`
- **Хелпер partials:** `app/Helpers/functions.php` (функция `partial()`)

---

**Создали крутую тему?** Поделитесь ей с сообществом! 🚀