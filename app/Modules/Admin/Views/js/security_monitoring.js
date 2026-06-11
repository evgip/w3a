/**
 * Asynchronous Real-Time Administrative Security Monitoring System Polling Engine
 */
document.addEventListener('DOMContentLoaded', function () {
    // Only run if we are looking at the administrative master management control panel layout workspace
    if (window.location.pathname.indexOf('/admin') !== 0) return;

    // Build the master toast appending container layout node straight via JavaScript DOM
    const toastContainer = document.createElement('div');
    toastContainer.className = 'admin-alert-toast-container';
    document.body.appendChild(toastContainer);

    // Keep track of evaluated event alert row IDs to prevent duplicate layout flashes
    const processedAlertIds = new Set();

    function scanSecurityIncidentsTelemetry() {
        fetch('/api/admin/security-alerts', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) throw new Error('API Access unauthorized or dropped');
            return response.json();
        })
        .then(data => {
            if (data.status === 'success' && Array.isArray(data.alerts)) {
                data.alerts.forEach(alert => {
                    const alertId = parseInt(alert.id, 10);
                    
                    // Skip if this incident context footprint was already flashed onto the dashboard layout
                    if (processedAlertIds.has(alertId)) return;
                    processedAlertIds.add(alertId);

                    // Drop alert card nodes only after initial loading handshake loops to prevent dashboard historical flooding
                    if (processedAlertIds.size > 10 && alertId) {
                        spawnSecurityToastAlert(alert);
                    }
                });
            }
        })
        .catch(err => console.warn('Security monitor channel disconnect:', err));
    }

    function spawnSecurityToastAlert(alert) {
        const toast = document.createElement('div');
        toast.className = 'security-alert-toast-item';
        
        toast.innerHTML = `
            <div class="security-alert-toast-header">
                <span>⚠️ КРИТИЧЕСКАЯ АКТИВНОСТЬ</span>
                <span class="security-alert-toast-time">${alert.created_at.split(' ')[1]}</span>
            </div>
            <div class="security-alert-toast-body">
                <strong>Действие:</strong> ${alert.action}<br>
                ${alert.description}<br>
                <strong>Атакующий IP:</strong> <span class="security-alert-toast-ip">${alert.ip_address}</span>
            </div>
        `;

        toastContainer.appendChild(toast);

        // Automatically fade out and destroy the layout tracking flash card after 8 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            toast.style.transition = 'all 0.4s ease';
            setTimeout(() => toast.remove(), 400);
        }, 8000);
    }

    // Set polling frequency tracking query cycles to run every 10 seconds safely
    scanSecurityIncidentsTelemetry();
    setInterval(scanSecurityIncidentsTelemetry, 10000);
});
