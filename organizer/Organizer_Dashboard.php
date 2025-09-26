
<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/security.php';

// Check if user is organizer or admin
if (!hasRole('organizer') && !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$pageTitle = 'Organizer Dashboard';

// Handle event deletion/cancellation
if ($_POST && isset($_POST['action']) && isset($_POST['event_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $event_id = (int)$_POST['event_id'];
        $organizer_id = $_SESSION['user_id'];
        
        try {
            if ($_POST['action'] === 'delete') {
                // Check if event belongs to this organizer
                $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ? AND organizer_id = ?");
                $stmt->execute([$event_id, $organizer_id]);
                
                if ($stmt->fetch()) {
                    // Delete the event (cascade will handle related records)
                    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND organizer_id = ?");
                    $stmt->execute([$event_id, $organizer_id]);
                    $message = "Event deleted successfully!";
                    $message_type = "success";
                }
            }
        } catch (PDOException $e) {
            error_log("Error deleting event: " . $e->getMessage());
            $message = "Error deleting event. Please try again.";
            $message_type = "danger";
        }
    }
}

// Get organizer's events and statistics
try {
    $organizer_id = $_SESSION['user_id'];
    
    // Total events by this organizer
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM events WHERE organizer_id = ?");
    $stmt->execute([$organizer_id]);
    $total_events = $stmt->fetch()['total'];
    
    // Total tickets sold from PAID bookings only
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ub.tickets_requested), 0) as total 
        FROM user_bookings ub 
        JOIN events e ON ub.event_id = e.id 
        WHERE e.organizer_id = ? AND ub.status = 'paid'
    ");
    $stmt->execute([$organizer_id]);
    $total_tickets_sold = $stmt->fetch()['total'];
    
    // Total revenue from PAID bookings only
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ub.total_amount), 0) as total 
        FROM user_bookings ub 
        JOIN events e ON ub.event_id = e.id 
        WHERE e.organizer_id = ? AND ub.status = 'paid'
    ");
    $stmt->execute([$organizer_id]);
    $total_revenue = $stmt->fetch()['total'];
    
    // Get all events with detailed statistics
    $stmt = $pdo->prepare("
        SELECT 
            e.id, 
            e.title, 
            e.event_date, 
            e.venue, 
            e.price, 
            e.total_tickets, 
            e.available_tickets,
            e.created_at,
            COALESCE(SUM(CASE WHEN ub.status = 'paid' THEN ub.tickets_requested ELSE 0 END), 0) as tickets_sold,
            COALESCE(SUM(CASE WHEN ub.status = 'paid' THEN ub.total_amount ELSE 0 END), 0) as revenue,
            COALESCE(COUNT(CASE WHEN ub.status = 'paid' THEN 1 END), 0) as total_bookings
        FROM events e 
        LEFT JOIN user_bookings ub ON e.id = ub.event_id 
        WHERE e.organizer_id = ? 
        GROUP BY e.id, e.title, e.event_date, e.venue, e.price, e.total_tickets, e.available_tickets, e.created_at
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$organizer_id]);
    $events = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error in organizer dashboard: " . $e->getMessage());
    $total_events = $total_tickets_sold = $total_revenue = 0;
    $events = [];
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
            <h1><i class="bi bi-calendar-plus me-2"></i>Organizer Dashboard</h1>
            <div>
                <a href="create_event.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Create Event
                </a>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5>My Events</h5>
                                <h2><?php echo $total_events; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-calendar-event fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5>Tickets Sold</h5>
                                <h2><?php echo $total_tickets_sold; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-ticket fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5>Total Revenue</h5>
                                <h2>$<?php echo number_format($total_revenue, 2); ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-currency-dollar fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- All Events Table -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-list-ul me-2"></i>My Events</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($events)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Event Title</th>
                                    <th>Date</th>
                                    <th>Venue</th>
                                    <th>Price</th>
                                    <th>Total Tickets</th>
                                    <th>Available</th>
                                    <th>Sold</th>
                                    <th>Revenue</th>
                                    <th>Bookings</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                    <td>$<?php echo number_format($event['price'], 2); ?></td>
                                    <td><?php echo $event['total_tickets']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $event['available_tickets'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $event['available_tickets']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo $event['tickets_sold']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-success">
                                            $<?php echo number_format($event['revenue'], 2); ?>
                                        </strong>
                                    </td>
                                    <td><?php echo $event['total_bookings']; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="confirmDelete(<?php echo $event['id']; ?>, '<?php echo addslashes($event['title']); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-calendar-x fs-1 text-muted"></i>
                        <p class="text-muted mt-2">No events created yet.</p>
                        <a href="create_event.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Create Your First Event
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the event "<span id="eventTitle"></span>"?</p>
                <p class="text-warning"><i class="bi bi-exclamation-triangle"></i> This action cannot be undone. All bookings for this event will also be deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="event_id" id="deleteEventId" value="">
                    <button type="submit" class="btn btn-danger">Delete Event</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(eventId, eventTitle) {
    document.getElementById('eventTitle').textContent = eventTitle;
    document.getElementById('deleteEventId').value = eventId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>