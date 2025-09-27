<?php
require_once __DIR__ . '/../inc/db_secure.php';
require_once __DIR__ . '/../inc/security.php';

if (!isLoggedIn()) {
    header('Location: ../auth/Login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get all paid bookings
    $stmt = $pdo->prepare("
        SELECT 
            ub.id,
            ub.tickets_requested,
            ub.total_amount,
            ub.booking_reference,
            ub.created_at as booking_date,
            e.id as event_id,
            e.title,
            e.event_date,
            e.venue,
            e.price as unit_price,
            CASE 
                WHEN e.event_date > NOW() THEN 'upcoming'
                ELSE 'past'
            END as event_status
        FROM user_bookings ub
        JOIN events e ON ub.event_id = e.id
        WHERE ub.user_id = ? AND ub.status = 'paid'
        ORDER BY e.event_date DESC
    ");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate upcoming and past events
    $upcoming = array_filter($bookings, fn($b) => $b['event_status'] === 'upcoming');
    $past = array_filter($bookings, fn($b) => $b['event_status'] === 'past');
    
} catch (Exception $e) {
    error_log("My bookings error: " . $e->getMessage());
    $bookings = [];
    $upcoming = [];
    $past = [];
}

$pageTitle = 'My Bookings';
include __DIR__ . '/../inc/header.php';
?>

<div class="container mt-4">
    <h1><i class="bi bi-ticket me-2"></i>My Bookings</h1>
    
    <?php if (empty($bookings)): ?>
        <div class="text-center py-5">
            <i class="bi bi-ticket-perforated display-1 text-muted"></i>
            <h3 class="text-muted mt-3">No bookings found</h3>
            <p class="text-muted">You haven't booked any events yet.</p>
            <a href="../index.php" class="btn btn-primary">Browse Events</a>
        </div>
    <?php else: ?>
        
        <!-- Upcoming Events -->
        <?php if (!empty($upcoming)): ?>
        <div class="mb-5">
            <h3 class="text-success"><i class="bi bi-calendar-event me-2"></i>Upcoming Events</h3>
            <div class="row">
                <?php foreach($upcoming as $booking): ?>
                <div class="col-md-6 mb-3">
                    <div class="card border-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5><?= e($booking['title']) ?></h5>
                                    <p class="text-muted mb-1">
                                        <i class="bi bi-calendar me-1"></i><?= formatDate($booking['event_date']) ?>
                                    </p>
                                    <p class="text-muted mb-1">
                                        <i class="bi bi-geo-alt me-1"></i><?= e($booking['venue']) ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong><?= $booking['tickets_requested'] ?> ticket(s)</strong>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <h5 class="text-success"><?= formatPrice($booking['total_amount']) ?></h5>
                                    <small class="text-muted">Ref: <?= e($booking['booking_reference']) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Past Events -->
        <?php if (!empty($past)): ?>
        <div class="mb-5">
            <h3 class="text-secondary"><i class="bi bi-clock-history me-2"></i>Past Events</h3>
            <div class="row">
                <?php foreach($past as $booking): ?>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5><?= e($booking['title']) ?></h5>
                                    <p class="text-muted mb-1">
                                        <i class="bi bi-calendar me-1"></i><?= formatDate($booking['event_date']) ?>
                                    </p>
                                    <p class="text-muted mb-1">
                                        <i class="bi bi-geo-alt me-1"></i><?= e($booking['venue']) ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong><?= $booking['tickets_requested'] ?> ticket(s)</strong>
                                    </p>
                                    <span class="badge bg-secondary">Attended</span>
                                </div>
                                <div class="text-end">
                                    <h5 class="text-secondary"><?= formatPrice($booking['total_amount']) ?></h5>
                                    <small class="text-muted">Ref: <?= e($booking['booking_reference']) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>