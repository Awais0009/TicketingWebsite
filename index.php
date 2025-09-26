<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/security.php';

$pageTitle = 'Events';

// Get all events with their images
try {
    // First get events
    $events_query = "
        SELECT e.*, u.name as organizer_name
        FROM events e
        LEFT JOIN users u ON e.organizer_id = u.id
        WHERE e.event_date > NOW()
        ORDER BY e.event_date ASC
    ";
    
    $stmt = $pdo->query($events_query);
    $events = $stmt->fetchAll();
    
    // Then get images for each event
    foreach ($events as &$event) {
        $image_stmt = $pdo->prepare("
            SELECT image_url, display_order 
            FROM event_images 
            WHERE event_id = ? 
            ORDER BY display_order ASC
        ");
        $image_stmt->execute([$event['id']]);
        $event['images'] = $image_stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log("Database error in homepage: " . $e->getMessage());
    $events = [];
}

include __DIR__ . '/inc/header.php';
?>

<div class="row">
    <div class="col-12">
        
        <?php if (isset($_GET['logged_out'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                You have been logged out successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Upcoming Events</h1>
                <p class="lead">Discover amazing events and book your tickets</p>
            </div>
            
            <?php if (hasRole('organizer')): ?>
                <div>
                    <a href="organizer/create_event.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Create Event
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (isLoggedIn()): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                Welcome back, <strong><?php echo sanitizeOutput($_SESSION['name']); ?></strong>!
                <small class="text-muted">(<?php echo ucfirst(sanitizeOutput($_SESSION['role'])); ?>)</small>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Please <a href="auth/login.php" class="alert-link">login</a> or 
                <a href="auth/register.php" class="alert-link">register</a> to book tickets.
            </div>
        <?php endif; ?>
        
        <!-- Events Grid -->
        <?php if (!empty($events)): ?>
            <div class="row">
                <?php foreach ($events as $event): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 event-card">
                        
                        <!-- Image Carousel -->
                        <?php if (!empty($event['images'])): ?>
                            <div id="carousel-<?php echo $event['id']; ?>" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-inner">
                                    <?php foreach ($event['images'] as $index => $image): ?>
                                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                        <img src="<?php echo sanitizeOutput($image['image_url']); ?>" 
                                             class="d-block w-100 card-img-top" 
                                             alt="<?php echo sanitizeOutput($event['title']); ?>"
                                             style="height: 200px; object-fit: cover;">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (count($event['images']) > 1): ?>
                                <!-- Carousel Controls -->
                                <button class="carousel-control-prev" type="button" 
                                        data-bs-target="#carousel-<?php echo $event['id']; ?>" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon"></span>
                                </button>
                                <button class="carousel-control-next" type="button" 
                                        data-bs-target="#carousel-<?php echo $event['id']; ?>" data-bs-slide="next">
                                    <span class="carousel-control-next-icon"></span>
                                </button>
                                
                                <!-- Indicators -->
                                <div class="carousel-indicators">
                                    <?php foreach ($event['images'] as $index => $image): ?>
                                    <button type="button" data-bs-target="#carousel-<?php echo $event['id']; ?>" 
                                            data-bs-slide-to="<?php echo $index; ?>" 
                                            <?php echo $index === 0 ? 'class="active"' : ''; ?>></button>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Default placeholder image -->
                            <div class="card-img-top d-flex align-items-center justify-content-center bg-light" 
                                 style="height: 200px;">
                                <i class="bi bi-calendar-event fs-1 text-muted"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo sanitizeOutput($event['title']); ?></h5>
                            <p class="card-text text-muted small mb-2">
                                <i class="bi bi-calendar me-1"></i>
                                <?php echo date('M j, Y @ g:i A', strtotime($event['event_date'])); ?>
                            </p>
                            <p class="card-text text-muted small mb-2">
                                <i class="bi bi-geo-alt me-1"></i>
                                <?php echo sanitizeOutput($event['venue']); ?>
                            </p>
                            <p class="card-text text-muted small mb-3">
                                <i class="bi bi-person me-1"></i>
                                by <?php echo sanitizeOutput($event['organizer_name']); ?>
                            </p>
                            
                            <p class="card-text flex-grow-1">
                                <?php echo sanitizeOutput(substr($event['description'], 0, 100)); ?>
                                <?php if (strlen($event['description']) > 100): ?>...<?php endif; ?>
                            </p>
                            
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <span class="h5 text-primary mb-0">$<?php echo number_format($event['price'], 2); ?></span>
                                        <small class="text-muted d-block">per ticket</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?php echo $event['available_tickets'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $event['available_tickets']; ?> available
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (isLoggedIn() && $event['available_tickets'] > 0): ?>
                                    <a href="event.php?id=<?php echo $event['id']; ?>" class="btn btn-primary w-100">
                                        <i class="bi bi-ticket me-2"></i>Book Tickets
                                    </a>
                                <?php elseif ($event['available_tickets'] > 0): ?>
                                    <a href="auth/login.php" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>Login to Book
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100" disabled>
                                        <i class="bi bi-x-circle me-2"></i>Sold Out
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x fs-1 text-muted"></i>
                <h3 class="text-muted mt-3">No Upcoming Events</h3>
                <p class="text-muted">Check back later for new events!</p>
                
                <?php if (hasRole('organizer')): ?>
                    <a href="organizer/create_event.php" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-circle me-2"></i>Create First Event
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>