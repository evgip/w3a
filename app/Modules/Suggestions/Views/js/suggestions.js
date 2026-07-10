class SuggestionsManager {
    constructor() {
        this.modal = document.getElementById('suggest-modal');
        this.tagsGroup = document.getElementById('suggest-tags-group');
        this.textGroup = document.getElementById('suggest-text-group');
        this.form = document.getElementById('suggest-form');
        
        this.init();
    }
    
    init() {
        // Открытие модалки
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('suggest-edit-btn')) {
                this.openSuggestModal(e.target);
            }
        });
        
        // Закрытие модалки
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('close-modal') || e.target.id === 'suggest-modal') {
                this.closeModal();
            }
        });
        
        // Отправка формы
        if (this.form) {
            this.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitSuggestion();
            });
        }
        
        // Закрытие по Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !this.modal.classList.contains('hidden')) {
                this.closeModal();
            }
        });
    }
    
    openSuggestModal(button) {
        const targetType = button.dataset.targetType;
        const targetId = button.dataset.targetId;
        const currentTitle = button.dataset.currentTitle || '';
        
        // Заполняем данные формы
        document.getElementById('suggest-target-type').value = targetType;
        document.getElementById('suggest-target-id').value = targetId;
        document.getElementById('suggest-title').value = currentTitle;
        
        // Показываем/скрываем группы полей через классы
        if (targetType === 'Story') {
            this.showElement(this.tagsGroup);
            this.hideElement(this.textGroup);
        } else if (targetType === 'Comment') {
            this.hideElement(this.tagsGroup);
            this.showElement(this.textGroup);
        }
        
        // Показываем модальное окно
        this.showModal();
    }
    
    showModal() {
        this.modal.classList.remove('hidden');
        this.modal.classList.add('modal-visible');
    }
    
    closeModal() {
        this.modal.classList.remove('modal-visible');
        this.modal.classList.add('hidden');
    }
    
    showElement(element) {
        if (element) {
            element.classList.remove('field-hidden');
            element.classList.add('field-visible');
        }
    }
    
    hideElement(element) {
        if (element) {
            element.classList.remove('field-visible');
            element.classList.add('field-hidden');
        }
    }
    
    async submitSuggestion() {
        const formData = new FormData(this.form);
        
        const targetType = formData.get('target_type');
        const csrfToken = getCsrfToken(); // Используем глобальную функцию из core_utils.js
        
        const proposedData = {};
        
        const newTitle = formData.get('title')?.trim();
        if (newTitle) {
            proposedData.title = newTitle;
        }
        
        if (targetType === 'Story') {
            const selectedTags = Array.from(document.querySelectorAll('input[name="tags[]"]:checked'))
                .map(cb => parseInt(cb.value));
            proposedData.tag_ids = selectedTags;
        } else if (targetType === 'Comment') {
            const text = formData.get('text')?.trim();
            if (text) {
                proposedData.text = text;
            }
        }
        
        if (Object.keys(proposedData).length === 0) {
            alert('Укажите хотя бы одно изменение');
            return;
        }
        
        formData.append('proposed_data', JSON.stringify(proposedData));
        
        try {
            const response = await fetch('/suggestions', {
                method: 'POST',
                body: formData
                // CSRF-токен добавляется автоматически перехватчиком из core_utils.js
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.closeModal();
                window.location.reload();
            } else {
                alert('Ошибка: ' + result.error);
            }
        } catch (error) {
            alert('Ошибка сети: ' + error.message);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new SuggestionsManager();
});