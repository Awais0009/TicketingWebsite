<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/security.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

// Get booking details
try {
    if ($booking_id) {
        $stmt = $pdo->prepare("
            SELECT ub.*, e.title, e.event_date, e.venue, e.price
            FROM user_bookings ub
            JOIN events e ON ub.event_id = e.id
            WHERE ub.id = ? AND ub.user_id = ? AND ub.status = 'booked'
        ");
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            $_SESSION['payment_error'] = "Booking not found or not ready for payment.";
            header('Location: ../user/my_cart.php');
            exit();
        }
        
        $total_amount = $booking['total_amount'] > 0 ? $booking['total_amount'] : ($booking['tickets_requested'] * $booking['price']);
        $booking_reference = $booking['booking_reference'];
        
    } else {
        $_SESSION['payment_error'] = "No booking specified for payment.";
        header('Location: ../user/my_cart.php');
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Checkout error: " . $e->getMessage());
    $_SESSION['payment_error'] = "Database error occurred.";
    header('Location: ../user/my_cart.php');
    exit();
}

$pageTitle = 'Payment Checkout';
include __DIR__ . '/../inc/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-credit-card me-2"></i>Complete Payment</h4>
            </div>
            <div class="card-body">
                
                <!-- Booking Details -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Booking Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5><?php echo sanitizeOutput($booking['title']); ?></h5>
                                <p class="mb-1">
                                    <i class="bi bi-calendar me-2"></i>
                                    <?php echo date('M j, Y @ g:i A', strtotime($booking['event_date'])); ?>
                                </p>
                                <p class="mb-1">
                                    <i class="bi bi-geo-alt me-2"></i>
                                    <?php echo sanitizeOutput($booking['venue']); ?>
                                </p>
                                <p class="mb-0">
                                    <i class="bi bi-ticket me-2"></i>
                                    <?php echo $booking['tickets_requested']; ?> tickets
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <h4 class="text-success">$<?php echo number_format($total_amount, 2); ?></h4>
                                <small class="text-muted">Reference: <?php echo sanitizeOutput($booking_reference); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Methods -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border-success h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-cash fs-1 text-success mb-3"></i>
                                <h5>Demo Payment</h5>
                                <p class="text-muted">Complete payment instantly</p>
                                <form method="POST" action="process_payment.php" onsubmit="return confirmPayment()">
                                    <input type="hidden" name="payment_method" value="demo">
                                    <input type="hidden" name="booking_ids" value="<?php echo $booking_id; ?>">
                                    <input type="hidden" name="total_amount" value="<?php echo $total_amount; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <button type="submit" class="btn btn-success btn-lg w-100">
                                        <i class="bi bi-check-circle me-2"></i>Pay $<?php echo number_format($total_amount, 2); ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-primary h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-credit-card fs-1 text-primary mb-3"></i>
                                <h5>Stripe Payment</h5>
                                <p class="text-muted">Secure card payment</p>
                                <button type="button" class="btn btn-primary btn-lg w-100" onclick="processStripePayment()">
                                    <i class="bi bi-lock me-2"></i>Pay with Card
                                </button>
                                <small class="text-muted d-block mt-2">Test Mode Active</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="../user/my_cart.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Cart
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmPayment() {
    return confirm('Confirm payment of $<?php echo number_format($total_amount, 2); ?>?');
}

function processStripePayment() {
    if (!confirm('Process payment of $<?php echo number_format($total_amount, 2); ?> via Stripe?')) {
        return;
    }
    
    // Show loading
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    btn.disabled = true;
    
    // Create form and submit
    setTimeout(function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process_payment.php';
        
        const fields = {
            'payment_method': 'stripe',
            'booking_ids': '<?php echo $booking_id; ?>',
            'total_amount': '<?php echo $total_amount; ?>',
            'csrf_token': '<?php echo generateCSRFToken(); ?>',
            'stripe_token': 'tok_demo_' + Date.now()
        };
        
        for (const [key, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
    }, 1000);
}
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>