<?php

if (!isLoggedIn()) {
    redirect('index.php?page=dashboard');
}

// Mark notifications as read
if (isset($_GET['mark_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$_GET['mark_read']]);
    redirect('index.php?page=notifications');
}

// Fetch notifications
$stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC");
$notifications = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Notifications</h4>
        <p class="text-muted mb-0">Real-time alerts on stock, transactions, and activity</p>
    </div>
    
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <?php foreach ($notifications as $notification): ?>
            <div class="stat-card mb-3 <?php echo !$notification['is_read'] ? 'border-start border-primary border-4' : ''; ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1"><?php echo escape($notification['title']); ?></h6>
                        <p class="text-muted small mb-0"><?php echo escape($notification['message']); ?></p>
                        <small class="text-muted"><?php echo date('M d, H:i', strtotime($notification['created_at'])); ?></small>
                    </div>
                    <?php if (!$notification['is_read']): ?>
                    <a href="?page=notifications&mark_read=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-primary">
                        Mark as read
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($notifications)): ?>
            <div class="text-center py-5">
                <i class="bi bi-bell-slash fs-1 text-muted"></i>
                <p class="text-muted mt-3">No notifications yet</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>