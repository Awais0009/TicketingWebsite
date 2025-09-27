<?php
require_once __DIR__ . '/../inc/db_secure.php';
require_once __DIR__ . '/../inc/security.php';

// Check if user is admin
if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$pageTitle = 'Admin Dashboard';

// Handle admin actions
if ($_POST && isset($_POST['action'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        try {
            switch ($_POST['action']) {
                case 'delete_user':
                    $user_id = (int)$_POST['user_id'];
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                    $stmt->execute([$user_id]);
                    $message = "User deleted successfully!";
                    $message_type = "success";
                    break;
                    
                case 'delete_event':
                    $event_id = (int)$_POST['event_id'];
                    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                    $stmt->execute([$event_id]);
                    $message = "Event deleted successfully!";
                    $message_type = "success";
                    break;
                    
                case 'change_role':
                    $user_id = (int)$_POST['user_id'];
                    $new_role = $_POST['new_role'];
                    if (in_array($new_role, ['user', 'organizer'])) {
                        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ? AND role != 'admin'");
                        $stmt->execute([$new_role, $user_id]);
                        $message = "User role updated successfully!";
                        $message_type = "success";
                    }
                    break;
            }
        } catch (PDOException $e) {
            error_log("Admin action error: " . $e->getMessage());
            $message = "Error processing request. Please try again.";
            $message_type = "danger";
        }
    }
}

// Get comprehensive statistics
try {
    // User statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $total_users = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'organizer'");
    $total_organizers = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
    $total_admins = $stmt->fetch()['total'];
    
    // Event statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events");
    $total_events = $stmt->fetch()['total'];
    
    // Booking statistics (from user_bookings with 'paid' status)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_bookings WHERE status = 'paid'");
    $total_paid_bookings = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(tickets_requested), 0) as total FROM user_bookings WHERE status = 'paid'");
    $total_tickets_sold = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM user_bookings WHERE status = 'paid'");
    $total_revenue = $stmt->fetch()['total'];
    
    // Get all users with detailed info
    $stmt = $pdo->query("
        SELECT 
            u.id, u.name, u.email, u.role, u.created_at,
            COUNT(CASE WHEN e.id IS NOT NULL THEN 1 END) as events_created,
            COALESCE(SUM(CASE WHEN ub.status = 'paid' THEN ub.total_amount ELSE 0 END), 0) as revenue_generated
        FROM users u 
        LEFT JOIN events e ON u.id = e.organizer_id 
        LEFT JOIN user_bookings ub ON e.id = ub.event_id AND ub.status = 'paid'
        GROUP BY u.id, u.name, u.email, u.role, u.created_at
        ORDER BY u.created_at DESC
    ");
    $all_users = $stmt->fetchAll();
    
    // Get all events with stats
    $stmt = $pdo->query("
        SELECT 
            e.id, e.title, e.event_date, e.venue, e.price, e.total_tickets, e.available_tickets, e.created_at,
            u.name as organizer_name,
            COALESCE(SUM(CASE WHEN ub.status = 'paid' THEN ub.tickets_requested ELSE 0 END), 0) as tickets_sold,
            COALESCE(SUM(CASE WHEN ub.status = 'paid' THEN ub.total_amount ELSE 0 END), 0) as revenue,
            COALESCE(COUNT(CASE WHEN ub.status = 'paid' THEN 1 END), 0) as paid_bookings
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.id 
        LEFT JOIN user_bookings ub ON e.id = ub.event_id 
        GROUP BY e.id, e.title, e.event_date, e.venue, e.price, e.total_tickets, e.available_tickets, e.created_at, u.name
        ORDER BY e.created_at DESC
    ");
    $all_events = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error in admin dashboard: " . $e->getMessage());
    $total_users = $total_organizers = $total_admins = $total_events = $total_paid_bookings = $total_tickets_sold = $total_revenue = 0;
    $all_users = $all_events = [];
}

include __DIR__ . '/../inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-shield-check me-2"></i>Admin Dashboard</h2>
            
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-people fs-1"></i>
                        <h3><?php echo $total_users; ?></h3>
                        <small>Users</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-person-badge fs-1"></i>
                        <h3><?php echo $total_organizers; ?></h3>
                        <small>Organizers</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-event fs-1"></i>
                        <h3><?php echo $total_events; ?></h3>
                        <small>Events</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-ticket fs-1"></i>
                        <h3><?php echo $total_paid_bookings; ?></h3>
                        <small>Paid Bookings</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-ticket-detailed fs-1"></i>
                        <h3><?php echo $total_tickets_sold; ?></h3>
                        <small>Tickets Sold</small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card bg-dark text-white">
                    <div class="card-body text-center">
                        <i class="bi bi-currency-dollar fs-1"></i>
                        <h3>$<?php echo number_format($total_revenue, 0); ?></h3>
                        <small>Total Revenue</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
                    <i class="bi bi-people me-2"></i>Users Management
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button" role="tab">
                    <i class="bi bi-calendar-event me-2"></i>Events Management
                </button>
            </li>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content" id="adminTabsContent">
            <!-- Users Management Tab -->
            <div class="tab-pane fade show active" id="users" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-people me-2"></i>All Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Events Created</th>
                                        <th>Revenue Generated</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'organizer' ? 'success' : 'primary'); ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $user['events_created']; ?></td>
                                        <td>$<?php echo number_format($user['revenue_generated'], 2); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-warning" 
                                                        onclick="changeRole(<?php echo $user['id']; ?>, '<?php echo addslashes($user['name']); ?>', '<?php echo $user['role']; ?>')">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['name']); ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Events Management Tab -->
            <div class="tab-pane fade" id="events" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-calendar-event me-2"></i>All Events</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Event Title</th>
                                        <th>Organizer</th>
                                        <th>Date</th>
                                        <th>Venue</th>
                                        <th>Price</th>
                                        <th>Total Tickets</th>
                                        <th>Available</th>
                                        <th>Sold</th>
                                        <th>Revenue</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_events as $event): ?>
                                    <tr>
                                        <td><?php echo $event['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($event['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($event['organizer_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                        <td>$<?php echo number_format($event['price'], 2); ?></td>
                                        <td><?php echo $event['total_tickets']; ?></td>
                                        <td>
                                            <span class="badge <?php echo $event['available_tickets'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $event['available_tickets']; ?>
                                            </span>
                                        </td>
                                        <td><span class="badge bg-primary"><?php echo $event['tickets_sold']; ?></span></td>
                                        <td><strong class="text-success">$<?php echo number_format($event['revenue'], 2); ?></strong></td>
                                        <td>
                                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                                    onclick="deleteEvent(<?php echo $event['id']; ?>, '<?php echo addslashes($event['title']); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete user "<span id="deleteUserName"></span>"?</p>
                <p class="text-warning"><i class="bi bi-exclamation-triangle"></i> This will also delete all their events and bookings.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="deleteUserId" value="">
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Change Role Modal -->
<div class="modal fade" id="changeRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change User Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Change role for user "<span id="changeRoleUserName"></span>"</p>
                <p>Current role: <span id="currentRole" class="badge"></span></p>
                <form method="POST" id="changeRoleForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="change_role">
                    <input type="hidden" name="user_id" id="changeRoleUserId" value="">
                    <div class="mb-3">
                        <label class="form-label">New Role</label>
                        <select name="new_role" class="form-select" required>
                            <option value="user">User</option>
                            <option value="organizer">Organizer</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="changeRoleForm" class="btn btn-warning">Change Role</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Event Modal -->
<div class="modal fade" id="deleteEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the event "<span id="deleteEventTitle"></span>"?</p>
                <p class="text-warning"><i class="bi bi-exclamation-triangle"></i> This will also delete all bookings for this event.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete_event">
                    <input type="hidden" name="event_id" id="deleteEventId" value="">
                    <button type="submit" class="btn btn-danger">Delete Event</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteUser(userId, userName) {
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('deleteUserId').value = userId;
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}

function changeRole(userId, userName, currentRole) {
    document.getElementById('changeRoleUserName').textContent = userName;
    document.getElementById('changeRoleUserId').value = userId;
    document.getElementById('currentRole').textContent = currentRole.charAt(0).toUpperCase() + currentRole.slice(1);
    document.getElementById('currentRole').className = 'badge bg-' + (currentRole === 'organizer' ? 'success' : 'primary');
    new bootstrap.Modal(document.getElementById('changeRoleModal')).show();
}

function deleteEvent(eventId, eventTitle) {
    document.getElementById('deleteEventTitle').textContent = eventTitle;
    document.getElementById('deleteEventId').value = eventId;
    new bootstrap.Modal(document.getElementById('deleteEventModal')).show();
}
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>