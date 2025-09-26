<?php

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/security.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Check if payment success data exists
if (!isset($_SESSION['payment_success'])) {
    header('Location: ../user/my_cart.php');
    exit();
}

$payment_data = $_SESSION['payment_success'];
unset($_SESSION['payment_success']); // Clear the session data

$pageTitle = 'Payment Successful';
include __DIR__ . '/../inc/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <!-- Success Message -->
        <div class="card border-success">
            <div class="card-header bg-success text-white text-center">
                <h4><i class="bi bi-check-circle me-2"></i>Payment Successful!</h4>
            </div>
            <div class="card-body text-center">
                <div class="mb-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                </div>
                
                <h5 class="text-success mb-3">Thank you for your payment!</h5>
                <p class="lead">Your booking has been confirmed and you will receive a confirmation email shortly.</p>
                
                <!-- Payment Details -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Payment Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Payment Method:</strong><br>
                                <span class="text-muted">
                                    <?php 
                                    switch($payment_data['method']) {
                                        case 'stripe': echo 'Stripe Payment'; break;
                                        case 'demo': echo 'Demo Payment'; break;
                                        case 'test': echo 'Test Payment'; break;
                                        case 'js_test': echo 'JavaScript Test'; break;
                                        default: echo ucfirst($payment_data['method']);
                                    }
                                    ?>
                                    <?php if (isset($payment_data['test_mode'])): ?>
                                        <span class="badge bg-warning text-dark ms-2">Test Mode</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="col-md-6">
                                <strong>Total Amount:</strong><br>
                                <span class="text-success fs-5">$<?php echo number_format($payment_data['total_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Booking References -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Booking References</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($payment_data['booking_references'] as $ref): ?>
                            <div class="mb-2">
                                <span class="badge bg-primary fs-6"><?php echo sanitizeOutput($ref); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <small class="text-muted">Keep these reference numbers for your records</small>
                    </div>
                </div>
                
                <!-- Next Steps -->
                <div class="alert alert-info">
                    <h6><i class="bi bi-info-circle me-2"></i>What's Next?</h6>
                    <ul class="text-start mb-0">
                        <li>You will receive a confirmation email with your e-tickets</li>
                        <li>Please arrive 30 minutes before the event</li>
                        <li>Bring a valid ID for entry</li>
                        <li>Contact support if you have any questions</li>
                    </ul>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <a href="../index.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-calendar-event me-2"></i>Browse More Events
                    </a>
                    <a href="../user/my_bookings.php" class="btn btn-outline-primary btn-lg">
                        <i class="bi bi-ticket me-2"></i>View My Bookings
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Additional Information -->
        <div class="card mt-4">
            <div class="card-body">
                <h6><i class="bi bi-question-circle me-2"></i>Need Help?</h6>
                <p class="mb-0">
                    If you have any questions about your booking, please contact our support team at:
                    <br>
                    <i class="bi bi-envelope me-1"></i>support@eventtickets.com
                    <br>
                    <i class="bi bi-telephone me-1"></i>+1 (555) 123-4567
                </p>
            </div>
        </div>
    </div>
</div>

<script>
// Print functionality
function printReceipt() {
    window.print();
}

// Confetti effect (optional)
document.addEventListener('DOMContentLoaded', function() {
    // Simple celebration animation
    setTimeout(function() {
        const successIcon = document.querySelector('.bi-check-circle-fill');
        if (successIcon) {
            successIcon.style.animation = 'bounce 1s ease-in-out';
        }
    }, 500);
});
</script>

<style>
@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-20px);
    }
    60% {
        transform: translateY(-10px);
    }
}
</style>

<?php include __DIR__ . '/../inc/footer.php'; ?>