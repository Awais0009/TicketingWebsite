<?php
require_once __DIR__ . '/../inc/db_secure.php';
require_once __DIR__ . '/../inc/security.php';

if (!isLoggedIn()) {
    header('Location: ../auth/Login.php');
    exit;
}

$payment_id = $_GET['payment_id'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$payment_id) {
    header('Location: ../index.php?error=' . urlencode('Invalid payment reference'));
    exit;
}

try {
    // Get booked events for this payment
    $stmt = $pdo->prepare("
        SELECT 
            ub.id,
            ub.tickets_requested,
            ub.total_amount,
            ub.created_at as booking_date,
            e.title,
            e.event_date,
            e.venue,
            e.price as unit_price
        FROM user_bookings ub
        JOIN events e ON ub.event_id = e.id
        WHERE ub.user_id = ? AND ub.booking_reference = ? AND ub.status = 'paid'
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$user_id, $payment_id]);
    $booked_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($booked_events)) {
        header('Location: ../index.php?error=' . urlencode('Payment not found'));
        exit;
    }
    
    $total_amount = array_sum(array_column($booked_events, 'total_amount'));
    $total_tickets = array_sum(array_column($booked_events, 'tickets_requested'));
    
    // Get user details
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Payment success page error: " . $e->getMessage());
    header('Location: ../index.php?error=' . urlencode('Error loading payment details'));
    exit;
}

$pageTitle = 'Payment Successful';
include __DIR__ . '/../inc/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-success text-white text-center">
                    <h3 class="mb-0"><i class="bi bi-check-circle me-2"></i>Payment Successful!</h3>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h4>Thank you for your purchase, <?= e($user['name']) ?>!</h4>
                        <p class="text-muted">Your tickets have been confirmed.</p>
                        <p><strong>Payment ID:</strong> <?= e($payment_id) ?></p>
                    </div>
                    
                    <h5>Booking Summary</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Date & Venue</th>
                                    <th>Tickets</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($booked_events as $event): ?>
                                <tr>
                                    <td><?= e($event['title']) ?></td>
                                    <td>
                                        <?= formatDate($event['event_date']) ?><br>
                                        <small class="text-muted"><?= e($event['venue']) ?></small>
                                    </td>
                                    <td><?= $event['tickets_requested'] ?></td>
                                    <td><?= formatPrice($event['total_amount']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-success">
                                    <th colspan="2">Total</th>
                                    <th><?= $total_tickets ?> tickets</th>
                                    <th><?= formatPrice($total_amount) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="../user/my_bookings.php" class="btn btn-primary me-2">
                            <i class="bi bi-ticket me-2"></i>View My Bookings
                        </a>
                        <a href="../index.php" class="btn btn-secondary">
                            <i class="bi bi-house me-2"></i>Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, nav, footer { display: none !important; }
    .container { max-width: none !important; }
}
</style>

<script>
// Auto redirect to bookings after 5 seconds
setTimeout(function() {
    if (confirm('Would you like to view your bookings now?')) {
        window.location.href = '../user/my_bookings.php';
    }
}, 3000);
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>