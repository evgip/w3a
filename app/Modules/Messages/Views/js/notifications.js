/**
 * Asynchronous Header Unread Message Counter Polling Helper
 */
document.addEventListener('DOMContentLoaded', function () {
    const badge = document.getElementById('unread-messages-badge');
    if (!badge) return; // Exit immediately if the user is a guest (no badge present)

    /**
     * Pull updates asynchronously via our new clean JSON API endpoint
     */
    function pollUnreadMessageCount() {
        fetch('/api/messages/unread-count', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network notification tracking failure');
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                const count = parseInt(data.count, 10);
                
                if (count > 0) {
                    badge.innerText = count;
                    badge.classList.remove('badge-hidden');
                } else {
                    badge.innerText = '0';
                    badge.classList.add('badge-hidden');
                }
            }
        })
        .catch(error => {
            console.error('Polling notifications tracker issue:', error);
        });
    }

    // Run lookups every 15 seconds to stay close to real-time without slamming database loops
    setInterval(pollUnreadMessageCount, 15000);
});
