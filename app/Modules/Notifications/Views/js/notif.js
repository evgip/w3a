/**
 * Обновляет счетчик уведомлений в шапке
 */
function updateHeaderNotificationCount() {
    fetch('/api/notifications/count')
        .then(response => response.json())
        .then(data => {
            const badge = document.getElementById('header-notification-badge');
            if (!badge) return;
            
            if (data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(error => console.error('Ошибка получения счетчика уведомлений:', error));
}

// Запускаем при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    updateHeaderNotificationCount();
    
    // Обновляем счетчик каждые 60 секунд
    setInterval(updateHeaderNotificationCount, 60000);
    
    // Делегирование событий для кликов по уведомлениям
    document.addEventListener('click', function(e) {
        const link = e.target.closest('.notification-link');
        if (!link) return;
        
        const notificationId = link.dataset.notificationId;
        if (!notificationId) return;
        
        // Останавливаем немедленный переход по ссылке
        e.preventDefault();
        
        const destinationUrl = link.href;
        
        // Получаем CSRF-токен
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content 
            || document.querySelector('input[name="csrf_token"]')?.value 
            || '';
        
        // Отправляем запрос на отметку как прочитанного
        fetch(`/notifications/${notificationId}/read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            // Обновляем счетчик в шапке
            updateHeaderNotificationCount();
            
            // Визуально помечаем уведомление как прочитанное
            const notificationItem = link.closest('.notification-item');
            if (notificationItem) {
                notificationItem.classList.remove('notification-unread');
                notificationItem.classList.add('notification-read');
            }
            
            // ТОЛЬКО ПОСЛЕ ответа сервера переходим по ссылке
            window.location.href = destinationUrl;
        })
        .catch(error => {
            console.error('Ошибка при отметке уведомления:', error);
            // Даже при ошибке переходим, чтобы пользователь не остался на месте
            window.location.href = destinationUrl;
        });
    });
});

// Кнопка "Отметить все как прочитанные"
document.getElementById('mark-all-read-btn')?.addEventListener('click', function(e) {
    e.preventDefault();
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content 
        || document.querySelector('input[name="csrf_token"]')?.value 
        || '';
    
    fetch('/notifications/mark-all-as-read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (response.ok) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Ошибка при отметке всех уведомлений:', error);
    });
});

