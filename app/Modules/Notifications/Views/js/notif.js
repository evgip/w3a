// Автоматическое обновление счётчика уведомлений каждые 60 секунд
function updateNotificationBadge() {
    fetch('/api/notifications/count')
        .then(response => response.json())
        .then(data => {
            const badge = document.getElementById('notification-badge');
            if (data.count > 0) {
                if (badge) {
                    badge.textContent = data.count;
                    badge.style.display = 'inline-block';
                } else {
                    // Создаём бейдж, если его нет
                    const bellIcon = document.querySelector('a[href="/notifications"] i.bi-bell');
                    if (bellIcon) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                        newBadge.id = 'notification-badge';
                        newBadge.textContent = data.count;
                        bellIcon.parentElement.appendChild(newBadge);
                    }
                }
            } else {
                if (badge) {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(error => console.error('Error fetching notifications:', error));
}

// Запускаем обновление при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    updateNotificationBadge();
    
    // Обновляем каждые 60 секунд
    setInterval(updateNotificationBadge, 60000);
});