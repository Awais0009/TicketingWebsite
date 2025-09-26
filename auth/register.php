<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/security.php';

$pageTitle = 'Register';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = cleanInput($_POST['name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = cleanInput($_POST['role'] ?? 'user');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    $errors = [];
    
    // Validate CSRF token
    if (!validateCSRFToken($csrf_token)) {
        $errors[] = "Invalid security token.";
    }
    
    // Validate input
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    // Validate role
    if (!in_array($role, ['user', 'organizer'])) {
        $role = 'user'; // Default to user if invalid role
    }
    
    // Check if email exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Email already registered.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error occurred.";
            error_log("Database error in registration: " . $e->getMessage());
        }
    }
    
    // Register user
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$name, $email, $hashed_password, $role])) {
                $success = "Registration successful! You can now login.";
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        } catch (PDOException $e) {
            $errors[] = "Registration failed. Please try again.";
            error_log("Database error in user registration: " . $e->getMessage());
        }
    }
}

include __DIR__ . '/../inc/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-person-plus me-2"></i>Create User Account</h4>
            </div>
            <div class="card-body">
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo sanitizeOutput($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <?php echo sanitizeOutput($success); ?>
                        <br><a href="login.php" class="alert-link">Login now</a>
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> This form is for regular users only. 
                    Contact admin for organizer accounts.
                </div>
                
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo sanitizeOutput($_POST['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo sanitizeOutput($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">Minimum 8 characters</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus me-2"></i>Create Account
                    </button>
                </form>
                
                <div class="text-center mt-3">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>