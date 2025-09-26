
<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/security.php';

// Check if user is organizer or admin
if (!hasRole('organizer') && !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$pageTitle = 'Organizer Dashboard';

// Get organizer's events and statistics
try {
    $organizer_id = $_SESSION['user_id'];
    
    // Total events by this organizer
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM events WHERE organizer_id = ?");
    $stmt->execute([$organizer_id]);
    $total_events = $stmt->fetch()['total'];
    
    // Total tickets sold
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(b.tickets_booked), 0) as total 
        FROM bookings b 
        JOIN events e ON b.event_id = e.id 
        WHERE e.organizer_id = ?
    ");
    $stmt->execute([$organizer_id]);
    $total_tickets_sold = $stmt->fetch()['total'];
    
    // Total revenue
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(b.total_amount), 0) as total 
        FROM bookings b 
        JOIN events e ON b.event_id = e.id 
        WHERE e.organizer_id = ?
    ");
    $stmt->execute([$organizer_id]);
    $total_revenue = $stmt->fetch()['total'];
    
    // Recent events
    $stmt = $pdo->prepare("
        SELECT id, title, event_date, venue, price, total_tickets, available_tickets 
        FROM events 
        WHERE organizer_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$organizer_id]);
    $recent_events = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error in organizer dashboard: " . $e->getMessage());
    $total_events = $total_tickets_sold = $total_revenue = 0;
    $recent_events = [];
}

include __DIR__ . '/../inc/header.php';
?>

<div class="row">
    <div class="col-12">
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
                                <h5>Revenue</h5>
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
        
        <!-- Recent Events -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-clock-history me-2"></i>My Recent Events</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_events)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Event Title</th>
                                    <th>Date</th>
                                    <th>Venue</th>
                                    <th>Price</th>
                                    <th>Tickets</th>
                                    <th>Available</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_events as $event): ?>
                                <tr>
                                    <td><?php echo sanitizeOutput($event['title']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo sanitizeOutput($event['venue']); ?></td>
                                    <td>$<?php echo number_format($event['price'], 2); ?></td>
                                    <td><?php echo $event['total_tickets']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $event['available_tickets'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $event['available_tickets']; ?>
                                        </span>
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

<?php include __DIR__ . '/../inc/footer.php'; ?>