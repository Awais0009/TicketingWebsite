<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/security.php';

// Validate event ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php?error=invalid_event');
    exit;
}

$event_id = (int)$_GET['id'];

try {
    // Get event details
    $stmt = $pdo->prepare("SELECT e.*, u.name as organizer_name 
                          FROM events e 
                          LEFT JOIN users u ON e.organizer_id = u.id 
                          WHERE e.id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        header('Location: index.php?error=event_not_found');
        exit;
    }
    
    // Generate CSRF token
    generateCSRFToken();
    
    // Get event images
    $images = [];
    try {
        $stmt = $pdo->prepare("SELECT image_url FROM event_images WHERE event_id = ? ORDER BY display_order");
        $stmt->execute([$event_id]);
        $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Try JSON images field if event_images table fails
        if (!empty($event['images'])) {
            $decoded = json_decode($event['images'], true);
            if (is_array($decoded)) {
                $images = $decoded;
            }
        }
    }
    
    // Check user's cart for this event
    $user_booking = null;
    if (isLoggedIn()) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM user_bookings 
                                  WHERE user_id = ? AND event_id = ? AND status = 'cart'
                                  ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$_SESSION['user_id'], $event_id]);
            $user_booking = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Continue without booking info
        }
    }
    
} catch (Exception $e) {
    error_log("Event page error: " . $e->getMessage());
    header('Location: index.php?error=database_error');
    exit;
}

$pageTitle = $event['title'];
include __DIR__ . '/inc/header.php';
?>

<div class="container mt-4">
    <!-- Display any messages -->
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php 
            $errors = [
                'csrf' => 'Security error. Please try again.',
                'invalid_data' => 'Invalid booking information.',
                'insufficient_tickets' => 'Not enough tickets available.',
                'database_error' => 'A database error occurred.'
            ];
            echo $errors[$_GET['error']] ?? 'An error occurred.';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= e($_GET['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Event Images -->
            <?php if (!empty($images)): ?>
            <div id="eventCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <?php foreach($images as $index => $image): ?>
                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                        <img src="<?= e($image) ?>" class="d-block w-100" alt="Event Image" 
                             style="height: 400px; object-fit: cover; border-radius: 8px;">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($images) > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#eventCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#eventCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                </button>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="mb-4">
                <div class="w-100 d-flex align-items-center justify-content-center" 
                     style="height: 400px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                            color: white; font-size: 1.5rem; border-radius: 8px;">
                    <i class="bi bi-calendar-event me-3"></i><?= e($event['title']) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Event Details -->
            <h1 class="mb-3"><?= e($event['title']) ?></h1>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <p class="mb-2">
                        <i class="bi bi-calendar-event me-2 text-primary"></i>
                        <strong>Date & Time:</strong><br>
                        <span class="ms-4"><?= formatDate($event['event_date']) ?></span>
                    </p>
                </div>
                <div class="col-md-6">
                    <p class="mb-2">
                        <i class="bi bi-geo-alt me-2 text-primary"></i>
                        <strong>Venue:</strong><br>
                        <span class="ms-4"><?= e($event['venue']) ?></span>
                    </p>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <p class="mb-2">
                        <i class="bi bi-currency-dollar me-2 text-success"></i>
                        <strong>Price per Ticket:</strong><br>
                        <span class="ms-4 h4 text-success"><?= formatPrice($event['price']) ?></span>
                    </p>
                </div>
                <div class="col-md-6">
                    <p class="mb-2">
                        <i class="bi bi-ticket me-2 text-info"></i>
                        <strong>Availability:</strong><br>
                        <span class="ms-4">
                            <span class="badge bg-<?= $event['available_tickets'] > 0 ? 'success' : 'danger' ?>">
                                <?= $event['available_tickets'] ?> of <?= $event['total_tickets'] ?> available
                            </span>
                        </span>
                    </p>
                </div>
            </div>
            
            <?php if (!empty($event['organizer_name'])): ?>
            <p class="mb-4">
                <i class="bi bi-person me-2 text-secondary"></i>
                <strong>Organized by:</strong> <?= e($event['organizer_name']) ?>
            </p>
            <?php endif; ?>
            
            <div class="mt-4">
                <h3><i class="bi bi-info-circle me-2"></i>About This Event</h3>
                <div class="bg-light p-4 rounded">
                    <?php if (!empty($event['description'])): ?>
                        <p class="mb-0"><?= nl2br(e($event['description'])) ?></p>
                    <?php else: ?>
                        <p class="text-muted mb-0"><em>No description available for this event.</em></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Booking Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow sticky-top" style="top: 20px;">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-ticket-perforated me-2"></i>Book Your Tickets
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!isLoggedIn()): ?>
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle me-2"></i>Please login to book tickets
                        </div>
                        <div class="d-grid gap-2">
                            <a href="auth/Login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                               class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login to Book
                            </a>
                            <a href="auth/register.php" class="btn btn-outline-primary">
                                <i class="bi bi-person-plus me-2"></i>Create Account
                            </a>
                        </div>
                        
                    <?php elseif ($event['available_tickets'] <= 0): ?>
                        <div class="alert alert-warning mb-3">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Sold Out!</strong><br>
                            This event has no more tickets available.
                        </div>
                        <button class="btn btn-secondary w-100" disabled>
                            <i class="bi bi-x-circle me-2"></i>Sold Out
                        </button>
                        
                    <?php else: ?>
                        <form method="POST" action="book_ticket.php" class="needs-validation" novalidate>
                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="mb-3">
                                <label for="tickets" class="form-label">
                                    <i class="bi bi-ticket me-1"></i>Number of Tickets:
                                </label>
                                <select class="form-select" id="tickets" name="tickets" required>
                                    <option value="">Select tickets...</option>
                                    <?php for($i = 1; $i <= min(10, $event['available_tickets']); $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?> Ticket<?= $i > 1 ? 's' : '' ?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="invalid-feedback">Please select number of tickets</div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                    <span><strong>Total Price:</strong></span>
                                    <span class="h4 text-success mb-0">
                                        $<span id="total-price">0.00</span>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($user_booking): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-cart me-2"></i>
                                    <small>You currently have <strong><?= $user_booking['quantity'] ?></strong> 
                                    ticket(s) for this event in your cart.</small>
                                    <hr class="my-2">
                                    <a href="user/my_cart.php" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-cart me-1"></i>View Cart
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-cart-plus me-2"></i>Add to Cart
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Event Statistics -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-graph-up me-2"></i>Booking Statistics
                    </h6>
                </div>
                <div class="card-body">
                    <?php 
                    $sold = $event['total_tickets'] - $event['available_tickets'];
                    $soldPercentage = $event['total_tickets'] > 0 ? ($sold / $event['total_tickets']) * 100 : 0;
                    ?>
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <div class="border-end">
                                <h3 class="text-danger mb-1"><?= $sold ?></h3>
                                <small class="text-muted">Tickets Sold</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success mb-1"><?= $event['available_tickets'] ?></h3>
                            <small class="text-muted">Available</small>
                        </div>
                    </div>
                    
                    <div class="progress mb-2" style="height: 10px;">
                        <div class="progress-bar bg-danger" style="width: <?= $soldPercentage ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted"><?= number_format($soldPercentage, 1) ?>% sold</small>
                        <small class="text-muted"><?= $event['total_tickets'] ?> total</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update total price calculation
document.getElementById('tickets')?.addEventListener('change', function() {
    const tickets = parseInt(this.value) || 0;
    const price = <?= $event['price'] ?>;
    const total = tickets * price;
    document.getElementById('total-price').textContent = total.toFixed(2);
});

// Bootstrap form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        const forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include __DIR__ . '/inc/footer.php'; ?>