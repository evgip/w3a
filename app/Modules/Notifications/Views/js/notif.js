/**
 * Обновляет счетчик уведомлений в шапке
 */
function updateHeaderNotificationCount() {
    fetch('/api/notifications/count') // Убедитесь, что маршрут совпадает с getCountAction в контроллере
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
    
    // Опционально: обновлять каждые 60 секунд (вместо 15, так как теперь это единый центр)
    setInterval(updateHeaderNotificationCount, 60000);
});


// Помечаем уведомление как прочитанное при клике (без перезагрузки, для UX)
function markAsRead(notificationId) {
    fetch('/notifications/mark-as-read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            // Добавьте ваш CSRF-токен здесь, если он используется
            // 'X-CSRF-Token': 'your_token'
        },
        body: 'id=' + notificationId
    }).then(() => {
        // Обновляем счетчик в шапке
        updateHeaderNotificationCount();
    });
}

// Кнопка "Отметить все как прочитанные"
document.getElementById('mark-all-read-btn')?.addEventListener('click', function() {
    fetch('/notifications/mark-all-as-read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    }).then(() => {
        location.reload();
    });
});