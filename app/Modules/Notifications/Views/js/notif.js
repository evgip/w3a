/**
 * Обновляет счетчик уведомлений в шапке
 */
function updateHeaderNotificationCount() {
	
    if (!document.getElementById('header-notification-badge')) {
        return; 
    }
	
    fetch('/api/notifications/count')
        .then(response => {
            if (response.status === 401 || response.status === 403) {
                const badge = document.getElementById('header-notification-badge');
                if (badge) badge.style.display = 'none';
                return null;
            }
            
            if (response.status === 419) {
                console.warn('CSRF истёк');
                return null;
            }
            
            if (!response.ok) {
				console.warn('Счетчик уведомлений: HTTP ' + response.status);
				return null; 
            }
            
			// Защита от парсинга HTML (если сервер всё же редиректнул на страницу логина)
			const contentType = response.headers.get("content-type");
			if (!contentType || !contentType.includes("application/json")) {
				const badge = document.getElementById('header-notification-badge');
				if (badge) badge.style.display = 'none';
				return null;
			}
			
            return response.json();
        })
        .then(data => {
            if (!data) return;
            
            const badge = document.getElementById('header-notification-badge');
            if (!badge) return;
            
            if (data.count > 0) {
                badge.textContent = data.count > 99 ? '99+' : data.count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Ошибка получения счетчика уведомлений:', error);
        });
}

// Запускаем при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    updateHeaderNotificationCount();
    setInterval(updateHeaderNotificationCount, 60000);
    
    // Делегирование событий для кликов по уведомлениям
    document.addEventListener('click', function(e) {
        const link = e.target.closest('.notification-link');
        if (!link) return;
        
        const notificationId = link.dataset.notificationId;
        if (!notificationId) return;
        
        e.preventDefault();
        
        const destinationUrl = link.href;
        const csrfToken = getCsrfToken();
        
        // ✅ ИСПРАВЛЕНО: передаём токен в теле запроса
        fetch(`/notifications/${notificationId}/read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}`,  // ← ДОБАВЛЕНО
            credentials: 'same-origin'
        })
        .then(response => {
            if (response.status === 419) {
                alert('Сессия истекла. Обновите страницу.');
                location.reload();
                return;
            }
            
            updateHeaderNotificationCount();
            
            const notificationItem = link.closest('.notification-item');
            if (notificationItem) {
                notificationItem.classList.remove('notification-unread');
                notificationItem.classList.add('notification-read');
            }
            
            window.location.href = destinationUrl;
        })
        .catch(error => {
            console.error('Ошибка при отметке уведомления:', error);
            window.location.href = destinationUrl;
        });
    });
});

// Кнопка "Отметить все как прочитанные"
document.getElementById('mark-all-read-btn')?.addEventListener('click', function(e) {
    e.preventDefault();
    
    const csrfToken = getCsrfToken();
    
    // ✅ ИСПРАВЛЕНО: передаём токен в теле запроса
    fetch('/notifications/mark-all-read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `csrf_token=${encodeURIComponent(csrfToken)}`,  // ← ДОБАВЛЕНО
        credentials: 'same-origin'
    })
    .then(response => {
        if (response.status === 419) {
            alert('Сессия истекла. Обновите страницу.');
            location.reload();
            return;
        }
        
        if (response.ok) {
            location.reload();
        } else {
            alert('Ошибка при отметке уведомлений.');
        }
    })
    .catch(error => {
        console.error('Ошибка при отметке всех уведомлений:', error);
        alert('Ошибка соединения с сервером.');
    });
});

/**
 * ✅ НОВАЯ ФУНКЦИЯ: Получить CSRF-токен из разных источников
 */
function getCsrfToken() {
    // Приоритет 1: meta-тег (самый надёжный)
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.content;
    
    // Приоритет 2: скрытое поле в любой форме на странице
    const input = document.querySelector('input[name="csrf_token"]');
    if (input) return input.value;
    
    return '';
}