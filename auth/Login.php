<?php
// Start session first
session_start();

// Include required files
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/security.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isset($_GET['redirect'])) {
        $redirect_to = $_GET['redirect'];
    } else {
        // Redirect to appropriate dashboard based on role
        switch ($_SESSION['role'] ?? 'user') {
            case 'admin':
                $redirect_to = '../admin/admin_dashboard.php';
                break;
            case 'organizer':
                $redirect_to = '../organizer/Organizer_Dashboard.php';
                break;
            default:
                $redirect_to = '../index.php';
                break;
        }
    }
    header("Location: $redirect_to");
    exit;
}

$error = '';
$redirect_to = $_GET['redirect'] ?? '../index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use the correct function name: verifyCSRFToken (not validateCSRFToken)
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security error. Please refresh the page and try again.';
    } else {
        $email = cleanInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields';
        } elseif (!isValidEmail($email)) {
            $error = 'Please enter a valid email address';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && verifyPassword($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name']; // Fix: use 'name' instead of 'user_name'
                    $_SESSION['user_name'] = $user['name']; // Keep backward compatibility
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['role'] = $user['role']; // Add this for hasRole() function
                    
                    // Redirect to appropriate dashboard based on role
                    if (isset($_GET['redirect'])) {
                        $redirect_to = $_GET['redirect'];
                    } else {
                        // Default redirects based on role
                        switch ($user['role']) {
                            case 'admin':
                                $redirect_to = '../admin/admin_dashboard.php';
                                break;
                            case 'organizer':
                                $redirect_to = '../organizer/Organizer_Dashboard.php';
                                break;
                            default:
                                $redirect_to = '../index.php';
                                break;
                        }
                    }
                    
                    header("Location: $redirect_to");
                    exit;
                } else {
                    $error = 'Invalid email or password';
                    error_log("Login failed for email: $email");
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'Login failed. Please try again.';
            }
        }
    }
}

$pageTitle = 'Login';
include __DIR__ . '/../inc/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login to Your Account
                    </h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="bi bi-envelope me-1"></i>Email Address
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?= e($_POST['email'] ?? '') ?>" 
                                   required 
                                   autocomplete="email"
                                   placeholder="Enter your email">
                            <div class="invalid-feedback">Please provide a valid email address.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="bi bi-lock me-1"></i>Password
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   autocomplete="current-password"
                                   placeholder="Enter your password">
                            <div class="invalid-feedback">Please provide your password.</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                            </button>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-2">Don't have an account?</p>
                        <a href="register.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" 
                           class="btn btn-outline-success">
                            <i class="bi bi-person-plus me-2"></i>Create Account
                        </a>
                    </div>
                </div>
            </div>

            
       </div>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>