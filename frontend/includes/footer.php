<?php

?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    
    <script>
    // Search functionality
    function searchTable(inputId, tableId) {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('keyup', function() {
                const filter = this.value.toLowerCase();
                const table = document.getElementById(tableId);
                if (table) {
                    const rows = table.getElementsByTagName('tr');
                    for (let i = 1; i < rows.length; i++) {
                        const cells = rows[i].getElementsByTagName('td');
                        let found = false;
                        for (let j = 0; j < cells.length; j++) {
                            if (cells[j] && cells[j].innerText.toLowerCase().indexOf(filter) > -1) {
                                found = true;
                                break;
                            }
                        }
                        rows[i].style.display = found ? '' : 'none';
                    }
                }
            });
        }
    }

    // Export to CSV
    function exportToCSV(tableId, filename) {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let row of rows) {
            const rowData = [];
            const cols = row.querySelectorAll('td, th');
            for (let col of cols) {
                rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
            }
            csv.push(rowData.join(','));
        }
        
        const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename + '.csv';
        a.click();
        window.URL.revokeObjectURL(url);
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert) alert.style.display = 'none';
        });
    }, 5000);

    // Pusher Configuration
    const PUSHER_KEY = 'e5ac4e30f057cddc45c9';
    const PUSHER_CLUSTER = 'ap1';
    
    Pusher.logToConsole = true;
    
    const pusher = new Pusher(PUSHER_KEY, {
        cluster: PUSHER_CLUSTER,
        useTLS: true
    });
    
    // Stock Channel
    const stockChannel = pusher.subscribe('stock-channel');
    stockChannel.bind('stock-update', function(data) {
        console.log('Stock update received:', data);
        showToast(data.message, data.type === 'stock_in' ? 'success' : 'warning');
        setTimeout(() => { location.reload(); }, 1500);
    });
    
    // Inventory Channel
    const inventoryChannel = pusher.subscribe('inventory-channel');
    inventoryChannel.bind('inventory-update', function(data) {
        console.log('Inventory update received:', data);
        showToast(data.message, 'info');
        if (window.location.href.indexOf('inventory') !== -1 || window.location.href.indexOf('dashboard') !== -1) {
            setTimeout(() => { location.reload(); }, 1500);
        }
    });
    
    // Notification Channel
    const notificationChannel = pusher.subscribe('notification-channel');
    notificationChannel.bind('new-notification', function(data) {
        console.log('New notification:', data);
        let toastType = data.type === 'success' ? 'success' : (data.type === 'warning' ? 'warning' : 'info');
        showToast(data.title + ': ' + data.message, toastType);
        updateNotificationBadge();
    });
    
    function showToast(message, type) {
        const existingToasts = document.querySelectorAll('.pusher-toast');
        existingToasts.forEach(toast => toast.remove());
        
        const toast = document.createElement('div');
        toast.className = `pusher-toast alert alert-${type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'info')} shadow-lg`;
        toast.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 320px; animation: slideIn 0.3s ease; border-radius: 12px;';
        
        let icon = 'bi-info-circle-fill';
        if (type === 'success') icon = 'bi-check-circle-fill';
        if (type === 'warning') icon = 'bi-exclamation-triangle-fill';
        
        toast.innerHTML = `
            <div class="d-flex align-items-center p-3">
                <i class="bi ${icon} fs-4 me-3 ${type === 'success' ? 'text-success' : (type === 'warning' ? 'text-warning' : 'text-info')}"></i>
                <div class="flex-grow-1">
                    <strong class="text-uppercase small">${type}</strong>
                    <div class="small">${message}</div>
                </div>
                <button type="button" class="btn-close" onclick="this.closest('.pusher-toast').remove()"></button>
            </div>
        `;
        
        document.body.appendChild(toast);
        setTimeout(() => { if (toast && toast.remove) toast.remove(); }, 5000);
    }
    
    function updateNotificationBadge() {
        fetch('../backend/ajax_notification_count.php')
            .then(response => response.json())
            .then(data => {
                const bellIcon = document.querySelector('.bi-bell');
                if (bellIcon && data.count > 0) {
                    let badge = document.querySelector('.notification-badge');
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'notification-badge';
                        badge.style.cssText = 'position: absolute; top: -8px; right: -12px; background: #dc3545; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px;';
                        bellIcon.parentElement.style.position = 'relative';
                        bellIcon.parentElement.appendChild(badge);
                    }
                    badge.textContent = data.count > 9 ? '9+' : data.count;
                    badge.style.display = 'inline-block';
                }
            })
            .catch(error => console.log('Error:', error));
    }
    
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .pusher-toast { animation: slideIn 0.3s ease; }
        .fade-in { animation: fadeIn 0.4s ease; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
    `;
    document.head.appendChild(style);
    
    document.addEventListener('DOMContentLoaded', function() {
        updateNotificationBadge();
    });
    </script>
</body>
</html>