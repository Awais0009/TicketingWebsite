</main>

    <footer class="bg-dark text-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-ticket-perforated me-2"></i>EventTickets</h5>
                    <p>Your premier destination for event tickets and experiences.</p>
                </div>
                <div class="col-md-6">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-light text-decoration-none">Home</a></li>
                        <li><a href="events.php" class="text-light text-decoration-none">Events</a></li>
                        
                    </ul>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; <?= date('Y') ?> EventTickets. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <?php 
    // Determine correct path to assets based on current directory
    $currentDir = basename(dirname($_SERVER['SCRIPT_NAME']));
    $assetsPath = ($currentDir === 'payment' || $currentDir === 'user' || $currentDir === 'auth' || $currentDir === 'admin' || $currentDir === 'organizer') ? '../assets' : 'assets';
    ?>
    <script src="<?= $assetsPath ?>/js/validation.js"></script>
</body>
</html>
