<?php
require_once __DIR__ . '/../inc/db_secure.php';
require_once __DIR__ . '/../inc/security.php';

// Check if user is organizer or admin
if (!hasRole('organizer') && !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$pageTitle = 'Create Event';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = cleanInput($_POST['title'] ?? '');
    $description = cleanInput($_POST['description'] ?? '');
    $event_date = cleanInput($_POST['event_date'] ?? '');
    $venue = cleanInput($_POST['venue'] ?? '');
    $price = cleanInput($_POST['price'] ?? '');
    $total_tickets = cleanInput($_POST['total_tickets'] ?? '');
    $image_urls = array_filter(array_map('trim', explode("\n", $_POST['image_urls'] ?? '')));
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    $errors = [];
    
    // Debug: Show what image URLs we received
    if (!empty($image_urls)) {
        error_log("Received image URLs: " . print_r($image_urls, true));
    }
    
    // Validate CSRF token
    if (!verifyCSRFToken($csrf_token)) {
        $errors[] = "Invalid security token.";
    }
    
    // Validate input
    if (empty($title)) {
        $errors[] = "Event title is required.";
    }
    
    if (empty($description)) {
        $errors[] = "Event description is required.";
    }
    
    if (empty($event_date)) {
        $errors[] = "Event date is required.";
    } elseif (strtotime($event_date) <= time()) {
        $errors[] = "Event date must be in the future.";
    }
    
    if (empty($venue)) {
        $errors[] = "Venue is required.";
    }
    
    if (empty($price) || !is_numeric($price) || $price < 0) {
        $errors[] = "Valid price is required.";
    }
    
    if (empty($total_tickets) || !is_numeric($total_tickets) || $total_tickets < 1) {
        $errors[] = "Valid number of tickets is required.";
    }
    
    // Validate image URLs
    if (!empty($image_urls)) {
        foreach ($image_urls as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = "Invalid image URL: " . htmlspecialchars($url);
            }
        }
    }
    
    // Create event
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert event
            $stmt = $pdo->prepare("
                INSERT INTO events (title, description, event_date, venue, price, total_tickets, available_tickets, organizer_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id
            ");
            
            $available_tickets = $total_tickets;
            $organizer_id = $_SESSION['user_id'];
            
            $stmt->execute([$title, $description, $event_date, $venue, $price, $total_tickets, $available_tickets, $organizer_id]);
            $event_id = $stmt->fetch()['id'];
            
            error_log("Created event with ID: " . $event_id);
            
            // Insert images if provided
            if (!empty($image_urls)) {
                $image_stmt = $pdo->prepare("INSERT INTO event_images (event_id, image_url, display_order) VALUES (?, ?, ?)");
                foreach ($image_urls as $index => $image_url) {
                    $image_stmt->execute([$event_id, $image_url, $index + 1]);
                    error_log("Inserted image: $image_url for event $event_id");
                }
            }
            
            $pdo->commit();
            $success = "Event created successfully with " . count($image_urls) . " images!";
            
            // Clear form data
            $title = $description = $event_date = $venue = $price = $total_tickets = '';
            $image_urls = [];
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Database error occurred: " . $e->getMessage();
            error_log("Database error in create event: " . $e->getMessage());
        }
    }
}

include __DIR__ . '/../inc/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-calendar-plus me-2"></i>Create New Event</h4>
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
                        <br><a href="organizer_dashboard.php" class="alert-link">View Dashboard</a> | 
                        <a href="../index.php" class="alert-link">View Events</a>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="title" class="form-label">Event Title</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo sanitizeOutput($title ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Event Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required><?php echo sanitizeOutput($description ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="event_date" class="form-label">Event Date & Time</label>
                            <input type="datetime-local" class="form-control" id="event_date" name="event_date" 
                                   value="<?php echo sanitizeOutput($event_date ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="venue" class="form-label">Venue</label>
                            <input type="text" class="form-control" id="venue" name="venue" 
                                   value="<?php echo sanitizeOutput($venue ?? ''); ?>" 
                                   placeholder="e.g. Madison Square Garden, New York" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label">Ticket Price ($)</label>
                            <input type="number" class="form-control" id="price" name="price" 
                                   value="<?php echo sanitizeOutput($price ?? ''); ?>" 
                                   step="0.01" min="0" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="total_tickets" class="form-label">Total Tickets</label>
                            <input type="number" class="form-control" id="total_tickets" name="total_tickets" 
                                   value="<?php echo sanitizeOutput($total_tickets ?? ''); ?>" 
                                   min="1" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="image_urls" class="form-label">Event Images (URLs)</label>
                        <textarea class="form-control" id="image_urls" name="image_urls" rows="4" 
                                  placeholder="Enter image URLs, one per line. For example:&#10;https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=800&#10;https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=800"><?php echo sanitizeOutput(implode("\n", $image_urls ?? [])); ?></textarea>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            Add image URLs one per line. Use high-quality images (800px+ width recommended).
                            <br><strong>Free image sources:</strong> 
                            <a href="https://unsplash.com" target="_blank">Unsplash</a>, 
                            <a href="https://pexels.com" target="_blank">Pexels</a>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="organizer_dashboard.php" class="btn btn-secondary me-md-2">
                            <i class="bi bi-arrow-left me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-calendar-plus me-2"></i>Create Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Sample Images Preview 
<div class="row justify-content-center mt-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h6><i class="bi bi-images me-2"></i>Sample Event Images for Testing</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <img src="https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=400" class="img-fluid rounded" alt="Concert">
                        <small class="text-muted d-block">Music Concert</small>
                    </div>
                    <div class="col-md-4 mb-2">
                        <img src="https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400" class="img-fluid rounded" alt="Conference">
                        <small class="text-muted d-block">Tech Conference</small>
                    </div>
                    <div class="col-md-4 mb-2">
                        <img src="https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400" class="img-fluid rounded" alt="Sports">
                        <small class="text-muted d-block">Sports Event</small>
                    </div>
                </div>
            </div>
        </div>
    </div>  
</div> -->

<?php include __DIR__ . '/../inc/footer.php'; ?>