/**
 * Frontend Form Validator for the Users Module
 */
document.addEventListener('DOMContentLoaded', function () {
    // 1. Locate registration or login forms safely
    const registerForm = document.querySelector('form[action="/register"]');
    const loginForm = document.querySelector('form[action="/login"]');

    /**
     * Common handler to create and display a clean error notification alert
     */
    function showError(form, message) {
        // Clear out any old javascript validation alerts first
        let oldAlert = form.querySelector('.js-error-alert');
        if (oldAlert) oldAlert.remove();

        // Construct a new warning node layout matching your original PHP alert style
        const alertDiv = document.createElement('div');
        alertDiv.className = 'js-error-alert';
        alertDiv.style.color = '#e74c3c';
        alertDiv.style.background = '#fdf2f2';
        alertDiv.style.padding = '10px';
        alertDiv.style.border = '1px solid #f8b4b4';
        alertDiv.style.marginBottom = '15px';
        alertDiv.style.borderRadius = '4px';
        alertDiv.style.fontSize = '14px';
        alertDiv.innerText = message;

        // Insert the error alert at the very top of the form element container
        form.insertBefore(alertDiv, form.firstChild);
    }

    // 2. APPLY REGISTRATION FORM CONSTRAINTS
    if (registerForm) {
        registerForm.addEventListener('submit', function (event) {
            const nameInput = registerForm.querySelector('input[name="name"]');
            const emailInput = registerForm.querySelector('form input[name="email"]');
            const passwordInput = registerForm.querySelector('input[name="password"]');

            // Constraint Check A: Name min-length verification
            if (nameInput && nameInput.value.trim().length < 2) {
                event.preventDefault(); // Halt form submission cycle
                showError(registerForm, 'Имя пользователя должно быть не менее 2 символов.');
                nameInput.focus();
                return;
            }

            // Constraint Check B: HTML5 Email structural verification fallback
            const emailRegEx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailInput && !emailRegEx.test(emailInput.value.trim())) {
                event.preventDefault();
                showError(registerForm, 'Пожалуйста, введите корректный email адрес.');
                emailInput.focus();
                return;
            }

            // Constraint Check C: Password min-length verification (Matches Core/Validator)
            if (passwordInput && passwordInput.value.length < 6) {
                event.preventDefault(); 
                showError(registerForm, 'Пароль должен состоять минимум из 6 символов.');
                passwordInput.focus();
                return;
            }
        });
    }

    // 3. APPLY LOGIN FORM CONSTRAINTS
    if (loginForm) {
        loginForm.addEventListener('submit', function (event) {
            const passwordInput = loginForm.querySelector('input[name="password"]');

            if (passwordInput && passwordInput.value.length < 6) {
                event.preventDefault();
                showError(loginForm, 'Введен слишком короткий пароль. Минимум — 6 символов.');
                passwordInput.focus();
                return;
            }
        });
    }
});
