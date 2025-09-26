<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/security.php';

$pageTitle = 'Login';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    $errors = [];
    
    // Validate CSRF token
    if (!validateCSRFToken($csrf_token)) {
        $errors[] = "Invalid security token.";
    }
    
    // Validate input
    if (empty($email)) {
        $errors[] = "Email is required.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    
    // Authenticate user
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful - create session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header('Location: ../admin/admin_dashboard.php');
                        break;
                    case 'organizer':
                        header('Location: ../organizer/organizer_dashboard.php');
                        break;
                    default:
                        header('Location: ../index.php');
                        break;
                }
                exit();
            } else {
                $errors[] = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $errors[] = "Login failed. Please try again.";
            error_log("Database error in login: " . $e->getMessage());
        }
    }
}

include __DIR__ . '/../inc/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-box-arrow-in-right me-2"></i>Login</h4>
            </div>
            <div class="card-body">
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo sanitizeOutput($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Test Accounts Info -->
               
                
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo sanitizeOutput($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>
                </form>
                
                <div class="text-center mt-3">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>