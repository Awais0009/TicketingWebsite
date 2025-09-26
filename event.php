<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/security.php';

// Get event ID
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$event_id) {
    header('Location: index.php');
    exit();
}

// Get event details with better error handling
try {
    // Clear any cached plans first
    $pdo->exec("DEALLOCATE ALL");
    
    // Get event details (separate query to avoid caching issues)
    $event_query = "
        SELECT e.id, e.title, e.description, e.event_date, e.venue, e.price, 
               e.total_tickets, e.available_tickets, e.organizer_id, e.created_at,
               u.name as organizer_name, u.email as organizer_email
        FROM events e
        LEFT JOIN users u ON e.organizer_id = u.id
        WHERE e.id = $1
    ";
    
    $stmt = $pdo->prepare($event_query);
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        $_SESSION['error'] = "Event not found.";
        header('Location: index.php');
        exit();
    }
    
    // Initialize images array
    $event['images'] = [];
    
    // Try to get event images (check if table exists first)
    try {
        $table_check = $pdo->query("SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = 'event_images'
        )");
        $table_exists = $table_check->fetch()['exists'];
        
        if ($table_exists) {
            $images_query = "
                SELECT image_url, display_order 
                FROM event_images 
                WHERE event_id = $1 
                ORDER BY display_order ASC
            ";
            $stmt = $pdo->prepare($images_query);
            $stmt->execute([$event_id]);
            $event['images'] = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // Images table issue - continue without images
        error_log("Event images error: " . $e->getMessage());
        $event['images'] = [];
    }
    
    // Check user's booking status
    $user_booking = null;
    if (isLoggedIn()) {
        try {
            $booking_query = "
                SELECT id, user_id, event_id, tickets_requested, status, 
                       booking_reference, total_amount, created_at, updated_at
                FROM user_bookings 
                WHERE user_id = $1 AND event_id = $2 AND status IN ('cart', 'booked')
                ORDER BY created_at DESC 
                LIMIT 1
            ";
            $stmt = $pdo->prepare($booking_query);
            $stmt->execute([$_SESSION['user_id'], $event_id]);
            $user_booking = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("User booking check error: " . $e->getMessage());
            $user_booking = null;
        }
    }
    
} catch (PDOException $e) {
    error_log("Database error in event detail: " . $e->getMessage());
    
    // Show user-friendly error page
    $pageTitle = "Database Error";
    include __DIR__ . '/inc/header.php';
    ?>
    
    <div class="container mt-5">
        <div class="alert alert-danger">
            <h4><i class="bi bi-exclamation-triangle me-2"></i>Database Error</h4>
            <p>We're having trouble loading this event. This might be due to:</p>
            <ul>
                <li>Database connection issues</li>
                <li>Cached query plan conflicts</li>
                <li>Table structure changes</li>
            </ul>
            <p><strong>Error:</strong> <?php echo sanitizeOutput($e->getMessage()); ?></p>
            
            <div class="mt-3">
                <a href="debug_reset.php" class="btn btn-warning me-2">
                    <i class="bi bi-arrow-clockwise me-2"></i>Reset Database Cache
                </a>
                <a href="index.php" class="btn btn-primary">
                    <i class="bi bi-house me-2"></i>Back to Events
                </a>
            </div>
        </div>
    </div>
    
    <?php
    include __DIR__ . '/inc/footer.php';
    exit();
}

$pageTitle = $event['title'];
include __DIR__ . '/inc/header.php';
?>

<!-- Debug Info (remove in production) -->
<div class="alert alert-info">
    <strong>Debug:</strong> 
    Event ID: <?php echo $event_id; ?> | 
    Event Found: <?php echo $event ? 'Yes' : 'No'; ?> | 
    Images: <?php echo count($event['images']); ?> | 
    User Booking: <?php echo $user_booking ? $user_booking['status'] : 'None'; ?> |
    Available Tickets: <?php echo $event['available_tickets']; ?>
</div>

<div class="row">
    <!-- Event Images -->
    <div class="col-md-6 mb-4">
        <?php if (!empty($event['images'])): ?>
            <div id="eventCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <?php foreach ($event['images'] as $index => $image): ?>
                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <img src="<?php echo sanitizeOutput($image['image_url']); ?>" 
                             class="d-block w-100" 
                             alt="<?php echo sanitizeOutput($event['title']); ?>"
                             style="height: 400px; object-fit: cover; border-radius: 10px;">
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($event['images']) > 1): ?>
                <!-- Carousel Controls -->
                <button class="carousel-control-prev" type="button" 
                        data-bs-target="#eventCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" 
                        data-bs-target="#eventCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                </button>
                
                <!-- Indicators -->
                <div class="carousel-indicators">
                    <?php foreach ($event['images'] as $index => $image): ?>
                    <button type="button" data-bs-target="#eventCarousel" 
                            data-bs-slide-to="<?php echo $index; ?>" 
                            <?php echo $index === 0 ? 'class="active"' : ''; ?>></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Default placeholder -->
            <div class="d-flex align-items-center justify-content-center bg-light text-center" 
                 style="height: 400px; border-radius: 10px; border: 2px dashed #dee2e6;">
                <div>
                    <i class="bi bi-calendar-event fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted"><?php echo sanitizeOutput($event['title']); ?></h5>
                    <p class="text-muted">No images available</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Event Details -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h1 class="card-title"><?php echo sanitizeOutput($event['title']); ?></h1>
                
                <div class="mb-3">
                    <p class="text-muted mb-2">
                        <i class="bi bi-calendar me-2"></i>
                        <strong><?php echo date('l, F j, Y @ g:i A', strtotime($event['event_date'])); ?></strong>
                    </p>
                    <p class="text-muted mb-2">
                        <i class="bi bi-geo-alt me-2"></i>
                        <?php echo sanitizeOutput($event['venue']); ?>
                    </p>
                    <?php if ($event['organizer_name']): ?>
                    <p class="text-muted mb-3">
                        <i class="bi bi-person me-2"></i>
                        Organized by <strong><?php echo sanitizeOutput($event['organizer_name']); ?></strong>
                    </p>
                    <?php endif; ?>
                </div>
                
                <!-- Messages -->
                <?php if (isset($_SESSION['booking_errors'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        <?php foreach ($_SESSION['booking_errors'] as $error): ?>
                            <div><?php echo sanitizeOutput($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php unset($_SESSION['booking_errors']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['booking_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        <?php echo sanitizeOutput($_SESSION['booking_success']); ?>
                    </div>
                    <?php unset($_SESSION['booking_success']); ?>
                <?php endif; ?>
                
                <!-- Pricing & Availability -->
                <div class="row mb-4">
                    <div class="col-6">
                        <div class="text-center p-3 bg-primary text-white rounded">
                            <h3 class="mb-0">$<?php echo number_format($event['price'], 2); ?></h3>
                            <small>per ticket</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-3 <?php echo $event['available_tickets'] > 0 ? 'bg-success' : 'bg-danger'; ?> text-white rounded">
                            <h3 class="mb-0"><?php echo $event['available_tickets']; ?></h3>
                            <small>tickets left</small>
                        </div>
                    </div>
                </div>
                
                <!-- Booking Section -->
                <?php if (isLoggedIn()): ?>
    
    <?php if ($user_booking && $user_booking['status'] === 'booked'): ?>
        <!-- User has confirmed booking awaiting payment -->
        <div class="alert alert-warning">
            <i class="bi bi-clock me-2"></i>
            <strong>Booking Confirmed!</strong>
            <br>You have <?php echo $user_booking['tickets_requested']; ?> tickets reserved.
            <br><small>Complete payment within 15 minutes to secure your booking.</small>
        </div>
        
        <div class="d-grid gap-2">
            <a href="payment/checkout.php?booking_id=<?php echo $user_booking['id']; ?>" class="btn btn-success btn-lg">
                <i class="bi bi-credit-card me-2"></i>Complete Payment
            </a>
            <a href="user/my_cart.php" class="btn btn-outline-primary">
                <i class="bi bi-cart me-2"></i>View Cart
            </a>
        </div>
        
    <?php elseif ($user_booking && $user_booking['status'] === 'cart'): ?>
        <!-- User has tickets in cart -->
        <div class="alert alert-info">
            <i class="bi bi-cart me-2"></i>
            <strong><?php echo $user_booking['tickets_requested']; ?> tickets in your cart</strong>
        </div>
        
        <div class="d-grid gap-2 mb-3">
            <a href="confirm_booking.php?id=<?php echo $user_booking['id']; ?>" class="btn btn-success btn-lg">
                <i class="bi bi-check-circle me-2"></i>Confirm Booking
            </a>
            <a href="user/my_cart.php" class="btn btn-outline-primary">
                <i class="bi bi-cart me-2"></i>View Cart
            </a>
        </div>
        
    <?php elseif ($event['available_tickets'] > 0): ?>
        <!-- Add to Cart Form -->
        <form method="POST" action="book_ticket.php" class="needs-validation" novalidate>
            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="mb-3">
                <label for="tickets" class="form-label">Number of Tickets</label>
                <select class="form-select" id="tickets" name="tickets" required>
                    <option value="">Select tickets...</option>
                    <?php for ($i = 1; $i <= min(10, $event['available_tickets']); $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?> ticket<?php echo $i > 1 ? 's' : ''; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <strong>Total: <span id="totalAmount" class="fw-bold text-muted">Select tickets first</span></strong>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-cart-plus me-2"></i>Add to Cart
            </button>
        </form>
        
    <?php else: ?>
        <!-- Sold Out -->
        <div class="text-center">
            <button class="btn btn-secondary btn-lg w-100" disabled>
                <i class="bi bi-x-circle me-2"></i>Sold Out
            </button>
        </div>
    <?php endif; ?>
    
<?php elseif ($event['available_tickets'] > 0): ?>
    <!-- Not logged in -->
    <div class="text-center">
        <a href="auth/login.php?redirect=event.php?id=<?php echo $event_id; ?>" class="btn btn-primary btn-lg w-100">
            <i class="bi bi-person me-2"></i>Login to Book Tickets
        </a>
        <p class="text-muted mt-2">
            <small>Don't have an account? <a href="auth/register.php">Register here</a></small>
        </p>
    </div>
<?php else: ?>
    <!-- Sold Out for guest -->
    <div class="text-center">
        <button class="btn btn-secondary btn-lg w-100" disabled>
            <i class="bi bi-x-circle me-2"></i>Event Sold Out
        </button>
    </div>
<?php endif; ?>

<!-- Navigation -->
<div class="mt-3 d-flex gap-2">
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back to Events
    </a>
    <?php if (isLoggedIn()): ?>
        <a href="user/my_cart.php" class="btn btn-outline-primary">
            <i class="bi bi-cart me-2"></i>My Cart
        </a>
    <?php endif; ?>
</div>
            </div>
        </div>
    </div>
</div>

<!-- Event Description -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-info-circle me-2"></i>About This Event</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($event['description'])): ?>
                    <p class="card-text" style="white-space: pre-line;"><?php echo sanitizeOutput($event['description']); ?></p>
                <?php else: ?>
                    <p class="text-muted">No description available for this event.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Calculate total amount when tickets change
document.addEventListener('DOMContentLoaded', function() {
    const ticketsSelect = document.getElementById('tickets');
    const totalAmountSpan = document.getElementById('totalAmount');
    const pricePerTicket = <?php echo $event['price']; ?>;
    
    if (ticketsSelect && totalAmountSpan) {
        ticketsSelect.addEventListener('change', function() {
            const tickets = parseInt(this.value) || 0;
            if (tickets > 0) {
                const total = tickets * pricePerTicket;
                totalAmountSpan.textContent = '$' + total.toFixed(2);
                totalAmountSpan.className = 'fw-bold text-success';
            } else {
                totalAmountSpan.textContent = 'Select tickets first';
                totalAmountSpan.className = 'fw-bold text-muted';
            }
        });
    }
});
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>