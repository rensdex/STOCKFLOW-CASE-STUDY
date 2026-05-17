<?php
if (!isLoggedIn() || $_SESSION['role'] !== 'Administrator') {
    die('<div style="text-align: center; padding: 50px;"><h1 style="color: #ef4444;">🚫 Access Denied</h1><p>You do not have permission to access this page.</p><a href="index.php?page=dashboard">← Back to Dashboard</a></div>');
}

// Fetch audit logs with correct column names
$stmt = $pdo->query("SELECT al.*, u.fullname as user_name 
                     FROM audit_logs al 
                     LEFT JOIN users u ON al.user_id = u.id 
                     ORDER BY al.created_at DESC 
                     LIMIT 100");
$logs = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>📋 Audit Logs</h4>
        <p class="text-muted mb-0">Track every user action across the system</p>
    </div>
    
    <div class="data-table">
        <div class="p-3">
            <input type="text" id="searchLog" class="form-control" placeholder="Search logs by user, action, module, or details...">
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="logTable">
                <thead class="table-light">
                    <tr>
                        <th>LOG ID</th>
                        <th>USER</th>
                        <th>ACTION</th>
                        <th>MODULE</th>
                        <th>DETAILS</th>
                        <th>DATE/TIME</th>
                        <th>IP ADDRESS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr class="text-center">
                        <td colspan="7" class="py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-2">No audit logs found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><code><?php echo escape($log['log_id']); ?></code></td>
                            <td><?php echo escape($log['user_name'] ?? 'System'); ?></td>
                            <td>
                                <?php
                                $badgeClass = 'bg-info';
                                if ($log['action'] === 'LOGIN') $badgeClass = 'bg-success';
                                if ($log['action'] === 'DELETE') $badgeClass = 'bg-danger';
                                if ($log['action'] === 'UPDATE') $badgeClass = 'bg-warning';
                                if ($log['action'] === 'INSERT' || $log['action'] === 'ADD') $badgeClass = 'bg-primary';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>">
                                    <?php echo escape($log['action']); ?>
                                </span>
                            </td>
                            <td><?php echo escape($log['module']); ?></td>
                            <td style="max-width: 300px; word-wrap: break-word;"><?php echo escape($log['details']); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                            <td><code><?php echo escape($log['ip_address']); ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('searchLog')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#logTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Auto-refresh every 30 seconds (optional)
setInterval(function() {
    location.reload();
}, 30000);
</script>

<style>
.data-table {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.table th {
    background: #f8fafc;
    padding: 1rem;
    font-weight: 600;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
    font-size: 0.85rem;
}
.table td {
    padding: 0.85rem 1rem;
    vertical-align: middle;
}
.badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
}
.bg-success { background: #10b981 !important; }
.bg-danger { background: #ef4444 !important; }
.bg-warning { background: #f59e0b !important; color: #1f2937 !important; }
.bg-primary { background: #3b82f6 !important; }
.bg-info { background: #06b6d4 !important; }
code {
    background: #f1f5f9;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.8rem;
}
</style>