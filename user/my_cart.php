<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/security.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

$pageTitle = 'My Cart & Bookings';

// Get user's cart items and bookings
try {
    $stmt = $pdo->prepare("
        SELECT ub.*, e.title, e.event_date, e.venue, e.price, e.available_tickets
        FROM user_bookings ub
        JOIN events e ON ub.event_id = e.id
        WHERE ub.user_id = ? AND ub.status IN ('cart', 'booked')
        ORDER BY 
            CASE WHEN ub.status = 'booked' THEN 1 ELSE 2 END,
            ub.updated_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $items = $stmt->fetchAll();
    
    // Separate items by status
    $cart_items = array_filter($items, function($item) { return $item['status'] === 'cart'; });
    $booked_items = array_filter($items, function($item) { return $item['status'] === 'booked'; });
    
    // Calculate totals
    $cart_total = 0;
    foreach ($cart_items as $item) {
        $cart_total += ($item['tickets_requested'] * $item['price']);
    }
    
    $booked_total = 0;
    foreach ($booked_items as $item) {
        $booked_total += ($item['total_amount'] > 0 ? $item['total_amount'] : ($item['tickets_requested'] * $item['price']));
    }
    
} catch (PDOException $e) {
    error_log("Database error in my cart: " . $e->getMessage());
    $cart_items = $booked_items = [];
    $cart_total = $booked_total = 0;
}

include __DIR__ . '/../inc/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-cart me-2"></i>My Cart & Bookings</h1>
            <a href="../index.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-2"></i>Continue Shopping
            </a>
        </div>
        
        <!-- Error Messages -->
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
        
        <!-- Confirmed Bookings (Awaiting Payment) -->
        <?php if (!empty($booked_items)): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="bi bi-clock me-2"></i>
                    Awaiting Payment (<?php echo count($booked_items); ?> bookings)
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    Complete payment within 15 minutes to secure your tickets.
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Reference</th>
                                <th>Tickets</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booked_items as $item): ?>
                            <?php 
                            $item_total = $item['total_amount'] > 0 ? $item['total_amount'] : ($item['tickets_requested'] * $item['price']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitizeOutput($item['title']); ?></strong>
                                    <br><small class="text-muted"><?php echo date('M j, Y @ g:i A', strtotime($item['event_date'])); ?></small>
                                </td>
                                <td>
                                    <code><?php echo sanitizeOutput($item['booking_reference'] ?? 'Pending'); ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $item['tickets_requested']; ?></span>
                                </td>
                                <td>
                                    <strong>$<?php echo number_format($item_total, 2); ?></strong>
                                </td>
                                <td>
                                    <a href="../payment/checkout.php?booking_id=<?php echo $item['id']; ?>" 
                                       class="btn btn-success btn-sm">
                                        <i class="bi bi-credit-card me-1"></i>Pay Now
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3"><strong>Total Awaiting Payment:</strong></td>
                                <td><strong>$<?php echo number_format($booked_total, 2); ?></strong></td>
                                <td>
                                    <?php if (count($booked_items) > 1): ?>
                                        <a href="../payment/checkout.php" class="btn btn-success btn-sm">
                                            <i class="bi bi-credit-card me-1"></i>Pay All
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Shopping Cart -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-cart me-2"></i>
                    Shopping Cart (<?php echo count($cart_items); ?> items)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($cart_items)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Date</th>
                                    <th>Tickets</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $item): ?>
                                <?php $item_total = $item['tickets_requested'] * $item['price']; ?>
                                <tr>
                                    <td>
                                        <strong><?php echo sanitizeOutput($item['title']); ?></strong>
                                        <br><small class="text-muted">Added <?php echo date('M j, g:i A', strtotime($item['created_at'])); ?></small>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($item['event_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $item['tickets_requested']; ?></span>
                                    </td>
                                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                                    <td><strong>$<?php echo number_format($item_total, 2); ?></strong></td>
                                    <td>
                                        <div class="btn-group-sm">
                                            <a href="../event.php?id=<?php echo $item['event_id']; ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-pencil me-1"></i>Edit
                                            </a>
                                            <a href="../confirm_booking.php?id=<?php echo $item['id']; ?>" 
                                               class="btn btn-primary btn-sm"
                                               onclick="return confirm('Confirm this booking for payment?')">
                                                <i class="bi bi-check-circle me-1"></i>Confirm
                                            </a>
                                            <a href="../cancel_booking.php?id=<?php echo $item['id']; ?>" 
                                               class="btn btn-outline-danger btn-sm"
                                               onclick="return confirm('Remove from cart?')">
                                                <i class="bi bi-trash me-1"></i>Remove
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="4"><strong>Cart Total:</strong></td>
                                    <td><strong>$<?php echo number_format($cart_total, 2); ?></strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-cart-x fs-1 text-muted"></i>
                        <h4 class="text-muted mt-3">Your cart is empty</h4>
                        <p class="text-muted">Browse events and add tickets to your cart!</p>
                        <a href="../index.php" class="btn btn-primary">
                            <i class="bi bi-calendar-event me-2"></i>Browse Events
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Empty State -->
        <?php if (empty($cart_items) && empty($booked_items)): ?>
        <div class="text-center py-5">
            <i class="bi bi-cart fs-1 text-muted"></i>
            <h3 class="text-muted mt-3">No items found</h3>
            <p class="text-muted">Start by browsing our amazing events!</p>
            <a href="../index.php" class="btn btn-primary btn-lg">
                <i class="bi bi-calendar-event me-2"></i>Browse Events
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>