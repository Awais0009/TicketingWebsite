<?php

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/security.php';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

$pageTitle = 'Test Payment';
include __DIR__ . '/../inc/header.php';
?>

<div class="container">
    <h2>Payment Test Page</h2>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5>Simple Form Test</h5>
                    <form method="POST" action="process_payment.php">
                        <input type="hidden" name="payment_method" value="test">
                        <input type="hidden" name="booking_ids" value="1">
                        <input type="hidden" name="total_amount" value="100.00">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <button type="submit" class="btn btn-primary">Test Form Submit</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5>JavaScript Test</h5>
                    <button onclick="testJS()" class="btn btn-success">Test JavaScript</button>
                    <button onclick="submitWithJS()" class="btn btn-warning">Submit with JS</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testJS() {
    alert('JavaScript is working!');
    console.log('JS test successful');
}

function submitWithJS() {
    console.log('Creating form with JavaScript...');
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'process_payment.php';
    
    const fields = {
        'payment_method': 'js_test',
        'booking_ids': '1',
        'total_amount': '150.00',
        'csrf_token': '<?php echo generateCSRFToken(); ?>'
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
}
</script>

<?php include __DIR__ . '/../inc/footer.php'; ?>