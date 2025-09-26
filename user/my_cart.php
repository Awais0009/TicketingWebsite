<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/security.php';

// Require login
if (!isLoggedIn()) {
    header('Location: ../auth/Login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get user's cart items with proper column names
    $stmt = $pdo->prepare("
        SELECT 
            ub.id,
            ub.tickets_requested,
            ub.total_amount,
            ub.booking_reference,
            ub.created_at,
            e.id as event_id,
            e.title,
            e.event_date,
            e.venue,
            e.price as unit_price,
            e.available_tickets
        FROM user_bookings ub 
        JOIN events e ON ub.event_id = e.id 
        WHERE ub.user_id = ? AND ub.status = 'cart'
        ORDER BY ub.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_amount = array_sum(array_column($cart_items, 'total_amount'));
    $total_tickets = array_sum(array_column($cart_items, 'tickets_requested'));
    
} catch (Exception $e) {
    error_log("Cart error: " . $e->getMessage());
    $cart_items = [];
    $total_amount = 0;
    $total_tickets = 0;
}

$pageTitle = 'My Cart';
include __DIR__ . '/../inc/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1><i class="bi bi-cart me-2"></i>My Cart</h1>
            <?php if (!empty($cart_items)): ?>
                <p class="text-muted"><?= count($cart_items) ?> event(s), <?= $total_tickets ?> ticket(s) total</p>
            <?php endif; ?>
        </div>
        <a href="../index.php" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-2"></i>Continue Shopping
        </a>
    </div>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= e($_GET['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?= e($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (empty($cart_items)): ?>
        <div class="text-center py-5">
            <i class="bi bi-cart-x display-1 text-muted"></i>
            <h3 class="text-muted mt-3">Your cart is empty</h3>
            <p class="text-muted">Browse events and add tickets to your cart.</p>
            <a href="../index.php" class="btn btn-primary btn-lg">
                <i class="bi bi-calendar-event me-2"></i>Browse Events
            </a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Cart Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach($cart_items as $index => $item): ?>
                        <div class="p-4 <?= $index > 0 ? 'border-top' : '' ?>">
                            <div class="row align-items-center">
                                <div class="col-lg-6">
                                    <h5 class="mb-2">
                                        <a href="../event.php?id=<?= $item['event_id'] ?>" 
                                           class="text-decoration-none">
                                            <?= e($item['title']) ?>
                                        </a>
                                    </h5>
                                    <div class="text-muted small">
                                        <div class="mb-1">
                                            <i class="bi bi-calendar me-1"></i>
                                            <?= formatDate($item['event_date']) ?>
                                        </div>
                                        <div class="mb-1">
                                            <i class="bi bi-geo-alt me-1"></i>
                                            <?= e($item['venue']) ?>
                                        </div>
                                        <div class="mb-1">
                                            <i class="bi bi-hash me-1"></i>
                                            <?= e($item['booking_reference']) ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-2 text-center">
                                    <div class="fw-bold text-primary">
                                        <?= $item['tickets_requested'] ?> ticket<?= $item['tickets_requested'] > 1 ? 's' : '' ?>
                                    </div>
                                    <small class="text-muted"><?= formatPrice($item['unit_price']) ?> each</small>
                                </div>
                                
                                <div class="col-lg-2 text-center">
                                    <h5 class="text-success mb-0"><?= formatPrice($item['total_amount']) ?></h5>
                                </div>
                                
                                <div class="col-lg-2 text-end">
                                    <div class="btn-group-vertical gap-2">
                                        <button class="btn btn-outline-danger btn-sm" 
                                                onclick="removeFromCart(<?= $item['id'] ?>, '<?= e($item['title']) ?>')"
                                                data-loading-text="Removing...">
                                            <i class="bi bi-trash me-1"></i>Remove
                                        </button>
                                        <a href="../event.php?id=<?= $item['event_id'] ?>" 
                                           class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Show availability warning if low -->
                            <?php if ($item['available_tickets'] < 10): ?>
                                <div class="alert alert-warning mt-3 mb-0 small">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Only <?= $item['available_tickets'] ?> tickets left for this event!
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Events:</span>
                            <span><?= count($cart_items) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Tickets:</span>
                            <span><?= $total_tickets ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total Amount:</strong>
                            <strong class="text-success h4"><?= formatPrice($total_amount) ?></strong>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="../payment/checkout.php" class="btn btn-success btn-lg"
                               data-loading-text="<i class='bi bi-arrow-repeat me-2'></i>Loading...">
                                <i class="bi bi-credit-card me-2"></i>Proceed to Checkout
                            </a>
                            <button class="btn btn-outline-danger" 
                                    onclick="clearCart()"
                                    data-confirm="Are you sure you want to remove all items from your cart?">
                                <i class="bi bi-trash me-2"></i>Clear Cart
                            </button>
                        </div>
                        
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="bi bi-shield-check me-1"></i>
                                Secure checkout with SSL encryption
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Remove Item Form (hidden) -->
<form id="removeForm" method="POST" action="remove_from_cart.php" style="display: none;">
    <input type="hidden" name="booking_id" id="removeBookingId">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
</form>

<script>
function removeFromCart(bookingId, eventTitle) {
    if (confirm(`Are you sure you want to remove "${eventTitle}" from your cart?`)) {
        const form = document.getElementById('removeForm');
        const bookingIdInput = document.getElementById('removeBookingId');
        
        bookingIdInput.value = bookingId;
        form.submit();
    }
}

function clearCart() {
    if (confirm('Are you sure you want to remove all items from your cart? This cannot be undone.')) {
        window.location.href = 'clear_cart.php?csrf_token=<?= generateCSRFToken() ?>';
    }
}
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>