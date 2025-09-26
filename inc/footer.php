</main>

    <!-- Footer -->
    <footer class="footer mt-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="text-muted mb-0">
                        &copy; <?php echo date('Y'); ?> EventTickets. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS - check if file exists -->
    <?php if (file_exists(__DIR__ . '/../assets/js/validation.js')): ?>
        <script src="/assets/js/validation.js"></script>
    <?php endif; ?>
</body>
</html>
