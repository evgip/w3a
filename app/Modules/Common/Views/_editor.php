<?php

declare(strict_types=1);

/**
 * Универсальный блочный редактор (Editor.js)
 */

$editor = array_merge([
    'name'        => 'description',
    'value'       => '', // Ожидаем здесь JSON-строку
    'placeholder' => 'Начните писать или введите / для выбора блока...',
    'label'       => 'Текст публикации',
    'hint'        => 'Блочный редактор. Поддерживает заголовки, списки, цитаты и код.',
], $editor ?? []);

$uid = substr(md5($editor['name'] . uniqid('', true)), 0, 8);
$containerId = 'editorjs-container-' . $uid;
$hiddenTextareaId = 'editorjs-hidden-' . $uid;
$nonce = csp_nonce();

// Безопасная инициализация данных: пытаемся декодировать JSON, иначе берем пустой блок
$initialData = [
    'time'    => time() * 1000,
    'blocks'  => [['type' => 'paragraph', 'data' => ['text' => '']]],
    'version' => '2.30.7'
];

if (!empty($editor['value']) && is_string($editor['value'])) {
    $decoded = json_decode($editor['value'], true);
    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['blocks'])) {
        $initialData = $decoded;
    }
}

$initialDataJson = json_encode($initialData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
?>

<!-- Подключаем локальные файлы Editor.js -->
<script src="/assets/editor/editorjs.umd.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/editor/header.umd.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/editor/editorjs-list.umd.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/editor/quote.umd.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/editor/inline-code.umd.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/editor/image.umd.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/editor/code.umd.js" nonce="<?= $nonce ?>"></script>
<script src="/assets/editor/embed.umd.js" nonce="<?= $nonce ?>"></script>

<div class="form-field-group">
    <label><strong><?= e($editor['label']) ?></strong></label>
    <?php if (!empty($editor['hint'])): ?>
        <p class="hint"><?= e($editor['hint']) ?></p>
    <?php endif; ?>

    <!-- Контейнер для визуального редактора -->
    <div id="<?= e($containerId) ?>" class="editorjs-custom-wrapper"></div>

    <!-- Скрытое поле, которое отправит JSON на сервер -->
    <textarea id="<?= e($hiddenTextareaId) ?>" name="<?= e($editor['name']) ?>" class="hidden"><?= e($editor['value']) ?></textarea>
</div>


<script nonce="<?= $nonce ?>">
/**
 * Paywall Block для Editor.js
 * ОПРЕДЕЛЯЕМ КЛАСС ДО ЕГО ИСПОЛЬЗОВАНИЯ!
 */
(function() {
    'use strict';

    class PaywallBlock {
        static get toolbox() {
            return {
                title: 'Замок (paywall)',
                icon: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
            };
        }

        static get isReadOnlySupported() {
            return true;
        }

        constructor({ data, api, readOnly }) {
            this.api = api;
            this.readOnly = readOnly;
            this.data = {
                title: (data && data.title) ? data.title : 'Продолжение доступно участникам',
            };
        }

        render() {
            const wrapper = document.createElement('div');
            wrapper.className = 'editorjs-paywall';

            if (!this.readOnly) {
                wrapper.innerHTML = `
                    <div class="editorjs-paywall__marker">
                        <span class="editorjs-paywall__icon">🔒</span>
                        <div class="editorjs-paywall__text">
                            <strong>Начало закрытой части</strong>
                            <p>Всё, что ниже этого маркера, будет видно только авторизованным читателям.</p>
                        </div>
                    </div>
                `;
            } else {
                wrapper.innerHTML = `<div class="paywall-divider"></div>`;
            }

            return wrapper;
        }

        save() {
            return { title: this.data.title };
        }

        static get sanitize() {
            return { title: {} };
        }
    }

    // 🔑 КЛЮЧЕВОЕ: регистрируем класс под ДВУМЯ именами
    window.PaywallBlock = PaywallBlock;
    window.PaywallTool = PaywallBlock;  // ← чтобы PaywallTool был определён
})();
</script>

<script nonce="<?= $nonce ?>">
/**
 * LinkCard Block для Editor.js
 * Карточка-ссылка на свою статью (аналог Medium).
 * Сервер перезапекает метаданные при сохранении — здесь только preview.
 */
(function() {
    'use strict';

    const API_URL = '/stories/link-preview?url=';

    class LinkCardBlock {
        static get toolbox() {
            return {
                title: 'Карточка статьи',
                icon: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
            };
        }

        static get isReadOnlySupported() {
            return true;
        }

        static get pasteConfig() {
            return {
                patterns: {
                    linkcard: /^(?:https?:\/\/[^\s\/]+)?\/story\/\d+(?:[\/\?\#][^\s]*)?$/i,
                },
            };
        }

        static onPaste(event) {
            const url = (event.detail && event.detail.data) || '';
            return { url: String(url || '').trim() };
        }

        constructor({ data, api, readOnly }) {
            this.api = api;
            this.readOnly = readOnly;
            this.data = LinkCardBlock._normalize(data || { url: '' });
        }

        static _normalize(d) {
            return {
                url: d.url || '',
                story_id: d.story_id || 0,
                title: d.title || '',
                author_name: d.author_name || '',
                author_avatar: d.author_avatar || '',
                cover_image: d.cover_image || '',
                excerpt: d.excerpt || '',
                reading_time: d.reading_time || 0,
            };
        }

        render() {
            const wrapper = document.createElement('div');
            wrapper.className = 'editorjs-linkcard-tool';
            this._wrapper = wrapper;

            if (this.readOnly) {
                wrapper.appendChild(LinkCardBlock._card(this.data));
                return wrapper;
            }

            // Вставлено по ссылке (onPaste) — метаданных ещё нет, подтягиваем
            if (this.data.url && !this.data.story_id) {
                this._fetch(() => this._refresh());
            }

            this._refresh();
            return wrapper;
        }

        _refresh() {
            const w = this._wrapper;
            if (!w) return;
            while (w.firstChild) w.removeChild(w.firstChild);

            if (this.data.url) {
                w.appendChild(LinkCardBlock._card(this.data));
                w.appendChild(this._actions());
            } else {
                w.appendChild(this._form());
            }
        }

        _actions() {
            const box = document.createElement('div');
            box.className = 'editorjs-linkcard__actions';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'editorjs-linkcard__btn';
            btn.textContent = '🔗 Сменить';
            btn.addEventListener('click', () => this._refreshForm());

            box.appendChild(btn);
            return box;
        }

        _refreshForm() {
            const w = this._wrapper;
            if (!w) return;
            while (w.firstChild) w.removeChild(w.firstChild);
            w.appendChild(this._form());
        }

        _form() {
            const form = document.createElement('div');
            form.className = 'editorjs-linkcard__form';

            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'editorjs-linkcard__input';
            input.value = this.data.url || '';
            input.placeholder = 'Вставьте ссылку на статью (/story/123)';

            const apply = document.createElement('button');
            apply.type = 'button';
            apply.className = 'editorjs-linkcard__btn editorjs-linkcard__btn--primary';
            apply.textContent = 'Прикрепить';

            apply.addEventListener('click', () => {
                const url = input.value.trim();
                if (!url) return;
                this.data = LinkCardBlock._normalize({ url: url });
                this._fetch(() => this._refresh());
            });

            form.appendChild(input);
            form.appendChild(apply);
            return form;
        }

        _fetch(cb) {
            const url = this.data.url;
            if (!url) return;
            fetch(API_URL + encodeURIComponent(url), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(r => r.json())
                .then(json => {
                    if (json && json.success) this.data = LinkCardBlock._normalize(json.data);
                })
                .catch(() => {})
                .finally(() => { if (cb) cb(); });
        }

        static _card(d) {
            const card = document.createElement('a');
            card.className = 'editorjs-linkcard';
            card.href = d.url || '/story/' + (d.story_id || 0);
            card.target = '_blank';
            card.rel = 'noopener noreferrer';

            if (d.cover_image) {
                const media = document.createElement('span');
                media.className = 'editorjs-linkcard__media';
                const img = document.createElement('img');
                img.src = d.cover_image;
                img.alt = d.title || '';
                img.loading = 'lazy';
                media.appendChild(img);
                card.appendChild(media);
            }

            const body = document.createElement('span');
            body.className = 'editorjs-linkcard__body';

            const title = document.createElement('span');
            title.className = 'editorjs-linkcard__title';
            title.textContent = d.title || 'Просмотреть статью';
            body.appendChild(title);

            if (d.excerpt) {
                const excerpt = document.createElement('span');
                excerpt.className = 'editorjs-linkcard__excerpt';
                excerpt.textContent = d.excerpt;
                body.appendChild(excerpt);
            }

            const meta = document.createElement('span');
            meta.className = 'editorjs-linkcard__meta';
            if (d.author_name) {
                const author = document.createElement('span');
                author.className = 'editorjs-linkcard__author';
                author.textContent = d.author_name;
                meta.appendChild(author);
            }
            if (d.reading_time) {
                const time = document.createElement('span');
                time.className = 'editorjs-linkcard__time';
                time.textContent = d.reading_time + ' мин чтения';
                meta.appendChild(time);
            }
            body.appendChild(meta);

            card.appendChild(body);
            return card;
        }

        save() {
            return this.data;
        }

        static get sanitize() {
            return {
                url: {},
                title: {},
                author_name: {},
                author_avatar: {},
                cover_image: {},
                excerpt: {},
                story_id: true,
                reading_time: true,
            };
        }
    }

    window.LinkCardBlock = LinkCardBlock;
})();
</script>


<script nonce="<?= $nonce ?>">
document.addEventListener('DOMContentLoaded', function() {
    const containerId = '<?= e($containerId) ?>';
    const hiddenTextareaId = '<?= e($hiddenTextareaId) ?>';
    const form = document.getElementById(containerId)?.closest('form');

    const HeaderTool = window.Header;
    const ListTool = window.EditorjsList;
    const QuoteTool = window.Quote;
    const InlineCodeTool = window.InlineCode;
    const ImageTool = window.ImageTool;
    const CodeTool = window.CodeTool;
    const PaywallTool = window.PaywallTool;
    const EmbedTool = window.Embed;

    if (!window.EditorJS || !ListTool || !InlineCodeTool) {
        console.error('❌ Не удалось загрузить плагины Editor.js. Проверьте пути и CSP.');
        return;
    }

    if (!ImageTool) {
        console.warn('⚠️ Плагин Image не загружен.');
    }
    
    if (!PaywallTool) {
        console.warn('⚠️ Плагин Paywall не загружен.');
    }
    
    if (!CodeTool) {
        console.warn('⚠️ Плагин Code не загружен.');
    }

    if (!EmbedTool) {
        console.warn('⚠️ Плагин Embed не загружен.');
    }

    const editorI18n = {
        messages: {
            ui: {
                blockTunes: { toggler: { "Click to tune": "Нажмите для настройки", "or drag to move": "или перетащите" } },
                inlineToolbar: { converter: { "Convert to": "Конвертировать в" } },
                toolbar: { toolbox: { "Add": "Добавить блок" } },
                popover: { "Filter": "Поиск", "Nothing found": "Ничего не найдено" }
            },
            toolNames: {
                "Text": "Текст", "Heading": "Заголовок", "List": "Список",
                "Quote": "Цитата", "Code": "Блок кода", "Link": "Ссылка",
                "Bold": "Жирный", "Italic": "Курсив", "InlineCode": "Встроенный код",
                "Замок (paywall)": "Замок (paywall)",
                "Image": "Изображение",
				"Ordered List": "Нумерованный",    
				"Unordered List": "Маркированный",  
				"Checklist": "Чеклист",
                "Embed": "Видео",
                "Карточка статьи": "Карточка статьи",
            },
            tools: {
                heading: { "Heading": "Заголовок" },
                list: { "Ordered": "Нумерованный", "Unordered": "Маркированный" },
                quote: { "Align Left": "По левому краю", "Align Center": "По центру" },
                inlineCode: { "Inline Code": "Встроенный код" },
                link: { "Add a link": "Добавить ссылку", "Enter a link": "Введите URL", "Enter a link text": "Введите текст" },
                image: {
                    "Image": "Изображение",
                    "Select an image": "Выберите изображение",
					 "Select an Image": "Выберите изображение",
                    "Paste an image URL": "Вставьте URL изображения",
                    "Uploading...": "Загрузка...",
                    "Loading...": "Загрузка...",
                    "With border": "С рамкой",
                    "Stretch image": "Растянуть изображение",
                    "With background": "С фоном",
                    "Caption": "Подпись",
                    "Enter a caption": "Введите подпись",
                    "Add Image": "Добавить изображение",
                    "Upload an image": "Загрузить изображение",
                    "Drop an image here": "Перетащите изображение сюда",
                    "URL": "URL"
                },
                code: {
                    "Code": "Код",
                    "Enter your code here...": "Введите код...",
                    "Language": "Язык",
                    "Placeholder": "Напишите код здесь..."
                }
            },
            blockTunes: {
                delete: { "Delete": "Удалить" },
                moveUp: { "Move up": "Переместить вверх" },
                moveDown: { "Move down": "Переместить вниз" }
            },
            errors: { tool: { "name": "Ошибка в инструменте \"%name%\"" } }
        }
    };

    // 🔑 ВАЖНО: строим tools динамически, без undefined значений
    const tools = {
        header: { class: HeaderTool, config: { levels: [2, 3, 4], defaultLevel: 2 } },
        list: { class: ListTool, config: { defaultStyle: 'unordered' } },
        quote: { class: QuoteTool },
        inlineCode: { class: InlineCodeTool, shortcut: 'CMD+SHIFT+C' },
    };

    // Добавляем опциональные инструменты только если они загружены
    if (CodeTool) {
        tools.code = {
            class: CodeTool,
            shortcut: 'CMD+SHIFT+K',
            config: { placeholder: 'Введите код...' }
        };
    }

    if (PaywallTool) {
        tools.paywall = { class: PaywallTool };
    }

    if (ImageTool) {
        tools.image = {
            class: ImageTool,
            config: {
                endpoints: { byFile: '/stories/upload-image' },
                field: 'image',
                types: 'image/jpeg, image/png, image/gif, image/webp'
            }
        };
    }

    // Embed: YouTube (встроен) + свои сервисы VK и Rutube
    if (EmbedTool) {
        // В оригинальном Embed-классе НЕТ статического toolbox,
        // поэтому он не появляется в меню «+» — только по вставке ссылки.
        // Оборачиваем в подкласс, чтобы добавить пункт «Видео» в палитру.
        class EmbedWithToolbox extends EmbedTool {
            static get toolbox() {
                return {
                    title: 'Видео',
                    icon: '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>',
                };
            }
        }

        tools.embed = {
            class: EmbedWithToolbox,
            config: {
                services: {
                    // Встроенные сервисы (кроме YouTube отключаем, чтобы не раздувать палитру)
                    youtube: true,
                    vimeo: true,
                    // ВКонтакте / VK Видео (vk.com и vkvideo.ru)
                    vk: {
                        regex: /^https?:\/\/(?:m\.|www\.)?(?:vk\.com|vkvideo\.ru)\/video(-?\d+)_(\d+)/,
                        // Плагин подставляет один плейсхолдер <%= remote_id %>,
                        // поэтому весь query (oid, id, hd) собираем в id()
                        embedUrl: 'https://vk.com/video_ext.php?<%= remote_id %>',
                        html: '<iframe width="560" height="315" frameborder="0" allowfullscreen allow="autoplay; encrypted-media; fullscreen; picture-in-picture"></iframe>',
                        height: 315,
                        width: 560,
                        id: (groups) => 'oid=' + groups[0] + '&id=' + groups[1] + '&hd=2',
                    },
                    // Rutube
                    rutube: {
                        regex: /^https?:\/\/(?:www\.)?rutube\.ru\/video\/([a-zA-Z0-9]+)/,
                        embedUrl: 'https://rutube.ru/play/embed/<%= remote_id %>',
                        html: '<iframe width="720" height="405" frameborder="0" allowfullscreen allow="autoplay; encrypted-media; fullscreen; picture-in-picture"></iframe>',
                        height: 405,
                        width: 720,
                    },
                }
            }
        };
    }

    // Карточка-ссылка на свою статью (linkCard)
    if (window.LinkCardBlock) {
        tools.linkCard = { class: window.LinkCardBlock };
    }

    const editor = new EditorJS({
		holder: containerId,
		placeholder: '<?= e($editor['placeholder']) ?>',
		i18n: editorI18n,
		tools: tools,
		data: <?= $initialDataJson ?>,
		onReady: () => {
			console.log('✅ Editor.js инициализирован');
			
			// Проверяем все изображения на битые ссылки
			checkBrokenImages();
			
			// Наблюдаем за изменениями DOM (новые блоки)
			const observer = new MutationObserver(checkBrokenImages);
			observer.observe(document.getElementById(containerId), {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['src']
			});
		},
		onChange: async () => {
			const outputData = await editor.save();
			document.getElementById(hiddenTextareaId).value = JSON.stringify(outputData);
		}
	});

	/**
	 * Проверка битых изображений в редакторе
	 */
	function checkBrokenImages() {
		const container = document.getElementById('<?= e($containerId) ?>');
		if (!container) return;
		
		const images = container.querySelectorAll('.image-tool__image img');
		
		images.forEach(img => {
			// Пропускаем уже обработанные
			if (img.dataset.checked) return;
			
			img.dataset.checked = 'true';
			
			// Обработчик ошибки загрузки
			img.onerror = function() {
				this.classList.add('broken');
				const imageContainer = this.closest('.image-tool__image');
				if (imageContainer) {
					imageContainer.classList.add('has-error');
					
					// Добавляем клик для удаления блока
					imageContainer.addEventListener('click', async function(e) {
						if (confirm('Изображение не найдено. Удалить этот блок?')) {
							const block = this.closest('.ce-block');
							if (block) {
								// Удаляем блок через Editor.js API
								const blockIndex = Array.from(block.parentNode.children).indexOf(block);
								await editor.blocks.delete(blockIndex);
							}
						}
					});
				}
			};
			
			// Если изображение уже битое (naturalWidth === 0)
			if (img.complete && img.naturalWidth === 0) {
				img.onerror();
			}
		});
	}

    if (form) {
        form.addEventListener('submit', async function(e) {
            try {
                const outputData = await editor.save();
                document.getElementById(hiddenTextareaId).value = JSON.stringify(outputData);
            } catch (error) {
                console.error('❌ Ошибка сохранения Editor.js:', error);
                e.preventDefault();
                alert('Ошибка в редакторе. Проверьте консоль (F12).');
            }
        });
        editor.isReady.then(() => {
            editor.save().then(outputData => {
                document.getElementById(hiddenTextareaId).value = JSON.stringify(outputData);
            });
        });
    }
});
</script>