<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/security.php';

// Require login
if (!isLoggedIn()) {
    header('Location: ../auth/Login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get cart items
    $stmt = $pdo->prepare("
        SELECT 
            ub.id,
            ub.tickets_requested,
            ub.total_amount,
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
    
    if (empty($cart_items)) {
        header('Location: ../user/my_cart.php?error=' . urlencode('Your cart is empty'));
        exit;
    }
    
    $total_amount = array_sum(array_column($cart_items, 'total_amount'));
    $total_tickets = array_sum(array_column($cart_items, 'tickets_requested'));
    
    // Get user details
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Checkout error: " . $e->getMessage());
    header('Location: ../user/my_cart.php?error=' . urlencode('Error loading checkout'));
    exit;
}

$pageTitle = 'Checkout';
include __DIR__ . '/../inc/header.php';
?>

<div class="container mt-4">
    <h1><i class="bi bi-credit-card me-2"></i>Checkout</h1>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <?php foreach($cart_items as $item): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 <?= $item !== end($cart_items) ? 'border-bottom' : '' ?>">
                        <div>
                            <h6 class="mb-0"><?= e($item['title']) ?></h6>
                            <small class="text-muted"><?= formatDate($item['event_date']) ?> • <?= e($item['venue']) ?></small>
                        </div>
                        <div class="text-end">
                            <div><?= $item['tickets_requested'] ?> × <?= formatPrice($item['unit_price']) ?></div>
                            <strong><?= formatPrice($item['total_amount']) ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-between align-items-center pt-3 mt-3 border-top">
                        <h5 class="mb-0">Total</h5>
                        <h4 class="text-success mb-0"><?= formatPrice($total_amount) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Payment Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="process_payment.php">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= e($user['name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="">Select payment method</option>
                                <option value="card">Credit/Debit Card</option>
                                <option value="cash">Cash on Delivery</option>
                            </select>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input type="checkbox" name="terms" class="form-check-input" required>
                            <label class="form-check-label">I agree to terms and conditions</label>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100">
                            Confirm Payment - <?= formatPrice($total_amount) ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>