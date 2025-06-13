    </div> <!-- End Admin Content Container -->
    
    <!-- Admin Footer -->
    <footer class="mt-5 py-4 bg-white border-top">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">
                        <i class="bi bi-shield-check text-primary me-1"></i>
                        <strong>RevenueQR Admin Panel</strong> - Platform Management System
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 text-muted">
                        Version 2.0 | 
                        <a href="<?php echo APP_URL; ?>/admin/system-monitor.php" class="text-decoration-none">
                            <i class="bi bi-activity me-1"></i>System Status
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Admin Panel JavaScript -->
    <script>
        // Auto-refresh system status indicators
        function updateSystemStatus() {
            const statusElements = document.querySelectorAll('.system-status');
            statusElements.forEach(element => {
                // Add pulse animation to active status indicators
                if (element.classList.contains('text-success')) {
                    element.style.animation = 'pulse 2s infinite';
                }
            });
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Update system status
            updateSystemStatus();
            
            // Auto-refresh every 30 seconds
            setInterval(updateSystemStatus, 30000);
        });
        
        // Confirm dangerous actions
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('confirm-action')) {
                const action = e.target.getAttribute('data-action') || 'this action';
                if (!confirm(`Are you sure you want to ${action}? This cannot be undone.`)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Add loading states to form submissions
        document.addEventListener('submit', function(e) {
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Processing...';
                submitBtn.disabled = true;
            }
        });
    </script>
    
    <!-- Custom CSS animations -->
    <style>
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
    </style>
    
    <?php if (isset($additional_footer_content)) echo $additional_footer_content; ?>
</body>
</html> 