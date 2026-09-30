<?php
/**
 * Выбор тегов через Tagify (заменяет чекбоксы).
 *
 * @var array $availableTags  Все доступные теги: [['id'=>int,'name'=>string], ...]
 * @var array $selectedTagIds Предвыбранные id тегов (для редактирования)
 */
$selectedTagIds = array_map('intval', $selectedTagIds ?? []);

// Оборачиваем данные для JS (name+id), чтобы Tagify дополнительно хранил id.
$whitelist = array_map(fn($t) => [
    'value' => (string)($t['name'] ?? ''),
    'id'    => (int)($t['id'] ?? 0),
], $availableTags ?? []);

$preselected = [];
foreach (($availableTags ?? []) as $t) {
    if (in_array((int)$t['id'], $selectedTagIds, true)) {
        $preselected[] = ['value' => (string)($t['name'] ?? ''), 'id' => (int)$t['id']];
    }
}
?>

<link rel="stylesheet" href="/assets/js/tag/tagify.css" nonce="<?= csp_nonce() ?>">
<script src="/assets/js/tag/tagify.js" nonce="<?= csp_nonce() ?>"></script>

<div class="form-field-group">
    <label><strong>Теги</strong></label>
    <p class="hint">Введите или выберите один или несколько тегов (максимум 5):</p>

    <input type="text"
           id="tags-tagify"
           class="tagify"
           placeholder="Выберите теги..."
           autocomplete="off">

    <div id="tags-selected-container"></div>

    <script nonce="<?= csp_nonce() ?>">
    (function() {
        const input = document.getElementById('tags-tagify');
        if (!input || !window.Tagify) return;

        const whitelist = <?= json_encode($whitelist, JSON_UNESCAPED_UNICODE) ?>;
        const preselected = <?= json_encode($preselected, JSON_UNESCAPED_UNICODE) ?>;

        const tagify = new Tagify(input, {
            whitelist: whitelist,
            enforceWhitelist: true,
            keepInvalidTags: false,
            maxTags: 5,
            dropdown: {
                enabled: 1,
                maxItems: 20,
                closeOnSelect: false,
            }
        });

        // Предзаполняем выбранные теги
        if (preselected.length) {
            tagify.addTags(preselected);
        }

        const form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function() {
                const container = document.getElementById('tags-selected-container');
                if (!container) return;
                container.innerHTML = '';
                (tagify.value || []).forEach(function(tag) {
                    const id = tag.id ? parseInt(tag.id, 10) : 0;
                    if (id > 0) {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'tags[]';
                        hidden.value = id;
                        container.appendChild(hidden);
                    }
                });
            });
        }
    })();
    </script>
</div>