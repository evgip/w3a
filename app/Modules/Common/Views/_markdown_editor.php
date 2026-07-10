<?php

/**
 * Универсальный Markdown-редактор
 * 
 * Использование:
 * partial('Shared::_markdown_editor', [
 *     'editor' => [
 *         'name' => 'description',
 *         'value' => $story['description'] ?? '',
 *         'placeholder' => 'Введите текст...',
 *         'rows' => 10,
 *         'textarea_id' => 'story-description',
 *         'preview_url' => '/stories/preview',
 *         'allow_images' => true,
 *         'label' => 'Текст обсуждения',
 *         'hint' => 'Поддерживается Markdown-разметка...',
 *     ]
 * ]);
 * 
 * @var array $editor Конфигурация редактора
 */

// Значения по умолчанию
$editor = array_merge([
    'name' => 'description',
    'value' => '',
    'placeholder' => 'Введите текст...',
    'rows' => 10,
    'textarea_id' => 'markdown-textarea',
    'preview_url' => '/stories/preview',
    'allow_images' => true,
    'required' => false,
    'label' => 'Текст',
    'hint' => 'Поддерживается Markdown-разметка: **жирный**, *курсив*, [ссылки](url), `код`',
], $editor ?? []);

// Уникальный суффикс для ID (если на странице несколько редакторов)
$uid = substr(md5($editor['textarea_id'] . uniqid('', true)), 0, 8);
$textareaId = $editor['textarea_id'] . '-' . $uid;
$previewAreaId = 'preview-area-' . $uid;
$previewContentId = 'preview-content-' . $uid;
$previewBtnId = 'preview-btn-' . $uid;
$closePreviewId = 'close-preview-' . $uid;
$toolbarClass = 'markdown-toolbar-' . $uid;
?>

<div class="form-field-group">
    <label for="<?= e($textareaId) ?>"><strong><?= e($editor['label']) ?></strong></label>
    <?php if (!empty($editor['hint'])): ?>
        <p class="hint"><?= e($editor['hint']) ?></p>
    <?php endif; ?>

    <!-- Панель инструментов Markdown -->
    <div class="markdown-toolbar <?= e($toolbarClass) ?>">
        <button type="button" class="btn-markdown" data-action="bold" title="Жирный (Ctrl+B)">
            <strong>B</strong>
        </button>
        <button type="button" class="btn-markdown" data-action="italic" title="Курсив (Ctrl+I)">
            <em>I</em>
        </button>
        <button type="button" class="btn-markdown" data-action="code" title="Код">
            &lt;/&gt;
        </button>
        <span class="toolbar-separator"></span>
        <button type="button" class="btn-markdown" data-action="link" title="Ссылка">
            🔗
        </button>
        <button type="button" class="btn-markdown" data-action="quote" title="Цитата">
            ❝
        </button>
        <button type="button" class="btn-markdown" data-action="list" title="Список">
            ☰
        </button>

        <span class="toolbar-spacer"></span>

        <button type="button" id="<?= e($previewBtnId) ?>" class="btn-markdown" title="Предпросмотр (Ctrl+Enter)">
            👁 Предпросмотр
        </button>
    </div>

    <textarea id="<?= e($textareaId) ?>" name="<?= e($editor['name']) ?>"
        rows="<?= (int)$editor['rows'] ?>"
        placeholder="<?= e($editor['placeholder']) ?>"
        <?= !empty($editor['required']) ? 'required' : '' ?>><?= e($editor['value']) ?></textarea>

    <!-- Область предпросмотра -->
    <div id="<?= e($previewAreaId) ?>" class="preview-area hidden">
        <div class="preview-header">
            <strong>Предпросмотр</strong>
            <button type="button" id="<?= e($closePreviewId) ?>" class="btn-close-preview" title="Закрыть">×</button>
        </div>
        <div id="<?= e($previewContentId) ?>" class="preview-content"></div>
    </div>
</div>

<script nonce="<?= csp_nonce(); ?>">
    document.addEventListener('DOMContentLoaded', function() {
        const textareaId = '<?= e($textareaId) ?>';
        const previewAreaId = '<?= e($previewAreaId) ?>';
        const previewContentId = '<?= e($previewContentId) ?>';
        const previewBtnId = '<?= e($previewBtnId) ?>';
        const closePreviewId = '<?= e($closePreviewId) ?>';
        const toolbarClass = '<?= e($toolbarClass) ?>';
        const previewUrl = '<?= e($editor['preview_url']) ?>';
        const allowImages = <?= $editor['allow_images'] ? 'true' : 'false' ?>;

        const textarea = document.getElementById(textareaId);
        const previewArea = document.getElementById(previewAreaId);
        const previewContent = document.getElementById(previewContentId);
        const previewBtn = document.getElementById(previewBtnId);
        const closePreview = document.getElementById(closePreviewId);

        if (!textarea) return;

        // ==================== УТИЛИТЫ ====================

        // ✅ Функции для управления видимостью через классы
        function showPreviewArea() {
            previewArea.classList.remove('hidden');
            previewArea.classList.add('preview-visible');
        }

        function hidePreviewArea() {
            previewArea.classList.remove('preview-visible');
            previewArea.classList.add('hidden');
        }

        // ==================== ПРЕДПРОСМОТР MARKDOWN ====================

        function showPreview() {
            const text = textarea.value.trim();

            if (!text) {
                hidePreviewArea();
                return;
            }

            previewBtn.disabled = true;
            previewBtn.textContent = '⏳ Загрузка...';

            // ✅ Используем FormData вместо строки
            const formData = new FormData();
            formData.append('text', text);
            formData.append('allow_images', allowImages ? '1' : '0');

            // ✅ CSRF-токен добавляется автоматически перехватчиком из core_utils.js
            fetch(previewUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (response.status === 419) {
                        throw new Error('Сессия истекла. Обновите страницу.');
                    }
                    if (!response.ok) {
                        return response.json().then(data => {
                            throw new Error(data.error || 'HTTP error! status: ' + response.status);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        alert('Ошибка: ' + data.error);
                        return;
                    }

                    if (data.html) {
                        previewContent.innerHTML = data.html;
                        showPreviewArea();
                        previewArea.scrollIntoView({
                            behavior: 'smooth',
                            block: 'nearest'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ошибка при предпросмотре: ' + error.message);
                })
                .finally(() => {
                    previewBtn.disabled = false;
                    previewBtn.textContent = '👁 Предпросмотр';
                });
        }

        previewBtn.addEventListener('click', showPreview);

        closePreview.addEventListener('click', function() {
            hidePreviewArea();
        });

        textarea.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                showPreview();
            }
        });

        // ==================== ПАНЕЛЬ ИНСТРУМЕНТОВ MARKDOWN ====================

        function insertMarkdown(action) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            let replacement = '';
            let cursorOffset = 0;

            switch (action) {
                case 'bold':
                    replacement = `**${selectedText || 'жирный текст'}**`;
                    cursorOffset = selectedText ? replacement.length : 2;
                    break;
                case 'italic':
                    replacement = `_${selectedText || 'курсив'}_`;
                    cursorOffset = selectedText ? replacement.length : 1;
                    break;
                case 'code':
                    if (selectedText.includes('\n')) {
                        replacement = `\n\`\`\`\n${selectedText}\n\`\`\`\n`;
                    } else {
                        replacement = `\`${selectedText || 'код'}\``;
                    }
                    cursorOffset = selectedText ? replacement.length : 1;
                    break;
                case 'link':
                    const url = prompt('Введите URL:', 'https://');
                    if (url) {
                        const linkText = selectedText || 'ссылка';
                        replacement = `[${linkText}](${url})`;
                        cursorOffset = replacement.length;
                    }
                    break;
                case 'quote':
                    if (selectedText) {
                        replacement = selectedText.split('\n').map(line => `> ${line}`).join('\n');
                    } else {
                        replacement = `\n> цитата\n`;
                    }
                    cursorOffset = replacement.length;
                    break;
                case 'list':
                    if (selectedText) {
                        const lines = selectedText.split('\n');
                        replacement = lines.map(line => `- ${line}`).join('\n');
                    } else {
                        replacement = `\n- элемент списка\n`;
                    }
                    cursorOffset = replacement.length;
                    break;
            }

            if (replacement) {
                textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
                textarea.focus();
                const newPos = start + cursorOffset;
                textarea.selectionStart = newPos;
                textarea.selectionEnd = newPos;
            }
        }

        // Привязываем обработчики к кнопкам этой панели
        const toolbar = document.querySelector('.' + toolbarClass);
        if (toolbar) {
            toolbar.querySelectorAll('.btn-markdown[data-action]').forEach(btn => {
                btn.addEventListener('click', function() {
                    insertMarkdown(this.dataset.action);
                });
            });
        }

        // Горячие клавиши
        textarea.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === 'b') {
                    e.preventDefault();
                    insertMarkdown('bold');
                } else if (e.key === 'i') {
                    e.preventDefault();
                    insertMarkdown('italic');
                }
            }
        });
    });
</script>