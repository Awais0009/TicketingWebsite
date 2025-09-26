<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/security.php';

// Check if user is admin
if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$pageTitle = 'Admin Dashboard';

// Get statistics
try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $total_users = $stmt->fetch()['total'];
    
    // Total organizers
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'organizer'");
    $total_organizers = $stmt->fetch()['total'];
    
    // Total events
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events");
    $total_events = $stmt->fetch()['total'];
    
    // Total bookings
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings");
    $total_bookings = $stmt->fetch()['total'];
    
    // Recent users
    $stmt = $pdo->query("SELECT name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
    $recent_users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error in admin dashboard: " . $e->getMessage());
    $total_users = $total_organizers = $total_events = $total_bookings = 0;
    $recent_users = [];
}

include __DIR__ . '/../inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-shield-check me-2"></i>Admin Dashboard</h1>
            <div class="text-muted">
                Welcome, <?php echo sanitizeOutput($_SESSION['name']); ?>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5>Users</h5>
                                <h2><?php echo $total_users; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5>Organizers</h5>
                                <h2><?php echo $total_organizers; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-person-badge fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5>Events</h5>
                                <h2><?php echo $total_events; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-calendar-event fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5>Bookings</h5>
                                <h2><?php echo $total_bookings; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-ticket fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Users -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-clock-history me-2"></i>Recent Users</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_users)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td><?php echo sanitizeOutput($user['name']); ?></td>
                                    <td><?php echo sanitizeOutput($user['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'organizer' ? 'success' : 'primary'); ?>">
                                            <?php echo ucfirst(sanitizeOutput($user['role'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No recent users found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>