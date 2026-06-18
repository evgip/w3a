document.addEventListener('DOMContentLoaded', function() {
    // Массив классов, требующих подтверждения
    const confirmClasses = ['.flag-link', '.delete-link', '.restore-link'];
    
    confirmClasses.forEach(function(selector) {
        document.querySelectorAll(selector).forEach(function(link) {
            link.addEventListener('click', function(e) {
                const message = this.getAttribute('data-confirm');
                if (message && !confirm(message)) {
                    e.preventDefault();
                }
            });
        });
    });
});