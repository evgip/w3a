class SuggestionsManager {
    constructor() {
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
        const form = document.getElementById('suggest-form');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitSuggestion();
            });
        }
    }
    
    openSuggestModal(button) {
        const targetType = button.dataset.targetType;
        const targetId = button.dataset.targetId;
        const currentTitle = button.dataset.currentTitle || '';
        
        document.getElementById('suggest-target-type').value = targetType;
        document.getElementById('suggest-target-id').value = targetId;
        document.getElementById('suggest-title').value = currentTitle;
        
        if (targetType === 'Story') {
            document.getElementById('suggest-tags-group').style.display = 'block';
            document.getElementById('suggest-text-group').style.display = 'none';
        } else if (targetType === 'Comment') {
            document.getElementById('suggest-tags-group').style.display = 'none';
            document.getElementById('suggest-text-group').style.display = 'block';
        }
        
        document.getElementById('suggest-modal').style.display = 'block';
    }
    
    closeModal() {
        document.getElementById('suggest-modal').style.display = 'none';
    }
    
    async submitSuggestion() {
        const form = document.getElementById('suggest-form');
        const formData = new FormData(form);
        
        const targetType = formData.get('target_type');
        const csrfToken = formData.get('csrf_token');
        
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
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                // ПЕРЕЗАГРУЖАЕМ страницу, чтобы увидеть обновления
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