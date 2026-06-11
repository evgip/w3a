/**
 * Скрипт управления выпадающим меню пользователя (CSP Compliant)
 */
document.addEventListener('DOMContentLoaded', function () {
    const trigger = document.getElementById('user-dropdown-trigger');
    const menu = document.getElementById('user-dropdown-menu');
    const wrapper = document.getElementById('user-dropdown-wrapper');

    if (!trigger || !menu) return; // Выходим, если пользователь — гость

    // 1. Открытие / Закрытие меню по клику на кнопку пользователя
    trigger.addEventListener('click', function (event) {
        event.stopPropagation(); // Предотвращаем всплытие события вверх к документу
        
        const isActive = menu.classList.contains('active');
        
        if (isActive) {
            menu.classList.remove('active');
            wrapper.classList.remove('active');
            trigger.setAttribute('aria-expanded', 'false');
        } else {
            menu.classList.add('active');
            wrapper.classList.add('active');
            trigger.setAttribute('aria-expanded', 'true');
        }
    });

    // 2. АВТОЗАКРЫТИЕ: Если меню открыто и пользователь кликнул в любое другое место страницы
    document.addEventListener('click', function (event) {
        // Если клик произошел вне блока выпадающего меню
        if (!wrapper.contains(event.target)) {
            if (menu.classList.contains('active')) {
                menu.classList.remove('active');
                wrapper.classList.remove('active');
                trigger.setAttribute('aria-expanded', 'false');
            }
        }
    });
});
