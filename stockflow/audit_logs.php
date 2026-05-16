<?php
// File: audit_logs.php
if (!isLoggedIn() || $_SESSION['role'] !== 'Administrator') {
    redirect('index.php?page=dashboard');
}

// Fetch audit logs
$stmt = $pdo->query("SELECT al.*, u.name as user_name 
                     FROM audit_logs al 
                     LEFT JOIN users u ON al.user_id = u.id 
                     ORDER BY al.created_at DESC 
                     LIMIT 50");
$logs = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Audit Logs</h4>
        <p class="text-muted mb-0">Track every user action across the system</p>
    </div>
    
    <div class="data-table">
        <div class="p-3">
            <input type="text" id="searchLog" class="form-control" placeholder="Search logs...">
        </div>
        <div class="table-responsive">
            <table class="table" id="logTable">
                <thead>
                    <tr><th>LOG ID</th><th>USER</th><th>ACTION</th><th>MODULE</th><th>DETAILS</th><th>DATE/TIME</th><th>IP ADDRESS</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo escape($log['log_id']); ?></td>
                        <td><?php echo escape($log['user_name'] ?? 'System'); ?></td>
                        <td>
                            <span class="badge <?php echo $log['action'] === 'LOGIN' ? 'bg-success' : 'bg-info'; ?>">
                                <?php echo $log['action']; ?>
                            </span>
                        </td>
                        <td><?php echo escape($log['module']); ?></td>
                        <td><?php echo escape($log['details']); ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                        <td><?php echo escape($log['ip_address']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
searchTable('searchLog', 'logTable');
</script>