<?php
session_start();
require_once 'inc/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$event_id = (int)$_GET['id'];

try {
    // Get event details
    $stmt = $pdo->prepare("SELECT e.*, u.full_name as organizer_name 
                          FROM events e 
                          LEFT JOIN users u ON e.organizer_id = u.id 
                          WHERE e.id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        header('Location: index.php?error=event_not_found');
        exit;
    }
    
    // Get event images (handle gracefully if table issues)
    $images = [];
    try {
        $stmt = $pdo->prepare("SELECT image_url FROM event_images WHERE event_id = ? ORDER BY id");
        $stmt->execute([$event_id]);
        $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // If event_images query fails, try to use images from events table
        if (!empty($event['images'])) {
            $decoded = json_decode($event['images'], true);
            if (is_array($decoded)) {
                $images = $decoded;
            }
        }
    }
    
    // Check if user has items in cart for this event
    $user_booking = null;
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM user_bookings 
                                  WHERE user_id = ? AND event_id = ? AND status IN ('cart', 'booked')
                                  ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$_SESSION['user_id'], $event_id]);
            $user_booking = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Continue without booking info if error
        }
    }
    
} catch (Exception $e) {
    error_log("Event page error: " . $e->getMessage());
    header('Location: index.php?error=database_error');
    exit;
}

$pageTitle = htmlspecialchars($event['title']);
include 'inc/header.php';
?>

<div class="container mt-4">
    <!-- Event Header -->
    <div class="row">
        <div class="col-lg-8">
            <!-- Image Carousel -->
            <?php if (!empty($images)): ?>
            <div id="eventCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <?php for($i = 0; $i < count($images); $i++): ?>
                    <button type="button" data-bs-target="#eventCarousel" data-bs-slide-to="<?= $i ?>" 
                            <?= $i === 0 ? 'class="active" aria-current="true"' : '' ?> 
                            aria-label="Slide <?= $i + 1 ?>"></button>
                    <?php endfor; ?>
                </div>
                <div class="carousel-inner">
                    <?php foreach($images as $index => $image): ?>
                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                        <img src="<?= htmlspecialchars($image) ?>" class="d-block w-100" alt="Event Image" 
                             style="height: 400px; object-fit: cover;">
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#eventCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#eventCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
            <?php else: ?>
            <!-- Default image if no images available -->
            <div class="mb-4">
                <img src="assets/images/default-event.jpg" class="img-fluid w-100" alt="Event" 
                     style="height: 400px; object-fit: cover; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center;">
            </div>
            <?php endif; ?>

            <!-- Event Details -->
            <h1 class="mb-3"><?= htmlspecialchars($event['title']) ?></h1>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <p><i class="bi bi-calendar-event me-2"></i><strong>Date:</strong> 
                       <?= date('F j, Y \a\t g:i A', strtotime($event['event_date'])) ?></p>
                </div>
                <div class="col-md-6">
                    <p><i class="bi bi-geo-alt me-2"></i><strong>Venue:</strong> 
                       <?= htmlspecialchars($event['venue']) ?></p>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <p><i class="bi bi-currency-dollar me-2"></i><strong>Price:</strong> 
                       $<?= number_format($event['price'], 2) ?></p>
                </div>
                <div class="col-md-6">
                    <p><i class="bi bi-ticket me-2"></i><strong>Available Tickets:</strong> 
                       <?= $event['available_tickets'] ?> of <?= $event['total_tickets'] ?></p>
                </div>
            </div>
            
            <?php if (!empty($event['organizer_name'])): ?>
            <p><i class="bi bi-person me-2"></i><strong>Organizer:</strong> 
               <?= htmlspecialchars($event['organizer_name']) ?></p>
            <?php endif; ?>
            
            <div class="mt-4">
                <h3>About This Event</h3>
                <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Book Tickets</h5>
                    
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="alert alert-info">
                            <p class="mb-2">Please <a href="auth/login.php">login</a> to book tickets.</p>
                            <p class="mb-0">Don't have an account? <a href="auth/register.php">Register here</a></p>
                        </div>
                    <?php elseif ($event['available_tickets'] <= 0): ?>
                        <div class="alert alert-warning">
                            <p class="mb-0">Sorry, this event is sold out!</p>
                        </div>
                    <?php else: ?>
                        <!-- Booking Form -->
                        <?php if ($user_booking): ?>
                            <div class="alert alert-success">
                                <p class="mb-2"><strong>You have <?= $user_booking['tickets_requested'] ?> tickets in your cart</strong></p>
                                <p class="mb-2">Status: <?= ucfirst($user_booking['status']) ?></p>
                                <a href="user/my_cart.php" class="btn btn-primary btn-sm">View Cart</a>
                            </div>
                        <?php endif; ?>
                        
                        <form action="book_ticket.php" method="POST">
                            <?php
                            // Generate CSRF token
                            if (!isset($_SESSION['csrf_token'])) {
                                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            }
                            ?>
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                            
                            <div class="mb-3">
                                <label for="tickets" class="form-label">Number of Tickets:</label>
                                <select name="tickets" id="tickets" class="form-select" required>
                                    <?php for($i = 1; $i <= min(10, $event['available_tickets']); $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <p><strong>Total: $<span id="total-price"><?= number_format($event['price'], 2) ?></span></strong></p>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <?= $user_booking ? 'Update Cart' : 'Add to Cart' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Event Stats -->
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="card-title">Event Statistics</h6>
                    <div class="row text-center">
                        <div class="col-6">
                            <h4 class="text-primary"><?= $event['total_tickets'] - $event['available_tickets'] ?></h4>
                            <small class="text-muted">Tickets Sold</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success"><?= $event['available_tickets'] ?></h4>
                            <small class="text-muted">Available</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update total price when tickets change
document.getElementById('tickets').addEventListener('change', function() {
    const tickets = parseInt(this.value);
    const price = <?= $event['price'] ?>;
    const total = tickets * price;
    document.getElementById('total-price').textContent = total.toFixed(2);
});
</script>

<?php include 'inc/footer.php'; ?>