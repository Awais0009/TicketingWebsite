<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default page title if not set
if (!isset($pageTitle)) {
    $pageTitle = "EventTickets";
}

// Get cart count for logged-in users
$cart_count = 0;
if (isLoggedIn()) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_bookings WHERE user_id = ? AND status IN ('cart', 'booked')");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        $cart_count = $result ? $result['count'] : 0;
    } catch (PDOException $e) {
        error_log("Error getting cart count: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizeOutput($pageTitle); ?> - EventTickets</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS - check if file exists -->
    <?php 
    // Determine correct path to assets based on current directory
    $currentDir = basename(dirname($_SERVER['SCRIPT_NAME']));
    $assetsPath = ($currentDir === 'payment' || $currentDir === 'user' || $currentDir === 'auth' || $currentDir === 'admin' || $currentDir === 'organizer') ? '../assets' : 'assets';
    ?>
    <?php if (file_exists(__DIR__ . '/../assets/css/style.css')): ?>
        <link href="<?= $assetsPath ?>/css/style.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/index.php">
                <i class="bi bi-calendar-event me-2"></i>EventTickets
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if (isLoggedIn() && (hasRole('organizer') || hasRole('admin'))): ?>
                    <!-- Simplified header for organizers and admins -->
                    <ul class="navbar-nav me-auto">
                        <!-- Empty - no navigation items for organizers/admins -->
                    </ul>
                    
                    <div class="navbar-nav">
                        <span class="navbar-text me-3">
                            <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'User'); ?>
                            <small class="text-light-emphasis">(<?php echo ucfirst($_SESSION['role'] ?? 'user'); ?>)</small>
                        </span>
                        <a class="nav-link" href="/auth/logout.php">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Full navigation for regular users and guests -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="/index.php">
                                <i class="bi bi-house me-1"></i>Events
                            </a>
                        </li>
                        
                        <?php if (isLoggedIn() && hasRole('user')): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/user/my_bookings.php">
                                    <i class="bi bi-ticket-perforated me-1"></i>My Bookings
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <div class="navbar-nav">
                        <?php if (isLoggedIn() && hasRole('user')): ?>
                            <!-- Cart Icon for regular users -->
                            <a class="nav-link position-relative" href="/user/my_cart.php">
                                <i class="bi bi-cart me-1"></i>Cart
                                <?php if ($cart_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $cart_count; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            
                            <span class="navbar-text me-3">
                                <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'User'); ?>
                                <small class="text-light-emphasis">(<?php echo ucfirst($_SESSION['role'] ?? 'user'); ?>)</small>
                            </span>
                            <a class="nav-link" href="/auth/logout.php">
                                <i class="bi bi-box-arrow-right me-1"></i>Logout
                            </a>
                        <?php else: ?>
                            <!-- Login/Register for guests -->
                            <a class="nav-link" href="/auth/login.php">Login</a>
                            <a class="nav-link" href="/auth/register.php">Register</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container mt-4">
