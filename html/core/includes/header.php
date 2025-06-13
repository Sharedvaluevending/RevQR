<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/security.php';  // Add security headers

// Set security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; img-src 'self' data: blob: https://api.qrserver.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; connect-src 'self'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Navigation should be dynamic - no aggressive caching
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("Vary: Cookie, Authorization"); // Vary by user session

// Prevent browser back/forward cache (bfcache) issues
header("X-Robots-Tag: noindex, nofollow, noarchive, nosnippet");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d6efd">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <title><?php echo APP_NAME; ?></title>
    
    <!-- Cache Control Meta Tags -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Favicon -->
    <link rel="ihttps://revenueqr.sharedvaluevending.com/qr-generator.phpcon" type="image/png" href="<?php echo APP_URL; ?>/img/logoRQ.png">
    
    <link rel="shortcut icon" type="image/png" href="<?php echo APP_URL; ?>/img/logoRQ.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts for QR Generator Labels -->
    <!-- TEMPORARILY DISABLED TO TEST FOR BLUE LINE ISSUE -->
    <!-- <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Caveat&family=Dancing+Script&family=Fjalla+One&family=Indie+Flower&family=Lobster&family=Montserrat&family=Orbitron&family=Oswald&family=Pacifico&family=Permanent+Marker&family=Raleway&family=Roboto&display=swap" rel="stylesheet"> -->
    
    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js for Dashboard Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- bfcache-fix.js temporarily disabled - was causing reload loops -->
    
    <!-- Initialize Bootstrap Components -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all dropdowns
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });
        
        // Initialize all tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Initialize all modals to prevent errors
        try {
            var modalElementList = [].slice.call(document.querySelectorAll('.modal'));
            modalElementList.forEach(function(modalEl) {
                if (modalEl && !modalEl._modal) {
                    modalEl._modal = new bootstrap.Modal(modalEl);
                }
            });
        } catch (error) {
            console.warn('Modal initialization warning:', error);
        }
    });
    </script>

    <!-- Custom CSS -->
    <style type="text/css">
        /* Critical CSS - Masculine Dark Blue/Steel Theme */
        html, body {
            background: linear-gradient(135deg, #00a000 0%, #00b000 25%, #00c000 75%, #00d000 100%) !important;
            background-attachment: fixed !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            min-height: 100vh !important;
        }
        
        /* Glass morphism cards */
        .card {
            background: rgba(255, 255, 255, 0.12) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 16px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
            transition: all 0.3s ease !important;
        }
        
        .card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.25) !important;
        }
        
        /* Navigation */
        .navbar {
            background: rgba(30, 60, 114, 0.95) !important;
            backdrop-filter: blur(20px) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.95) !important;
        }
        
        .navbar-dark .navbar-nav .nav-link:hover,
        .navbar-dark .navbar-nav .nav-link:focus {
            color: #64b5f6 !important;
        }
        
        .navbar-dark .navbar-brand {
            color: #ffffff !important;
        }
        
        .navbar-dark .navbar-brand:hover,
        .navbar-dark .navbar-brand:focus {
            color: #64b5f6 !important;
        }
        
        /* Dropdown menu items */
        .dropdown-menu {
            background: rgba(30, 60, 114, 0.95) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        .dropdown-item {
            color: rgba(255, 255, 255, 0.9) !important;
        }
        
        .dropdown-item:hover,
        .dropdown-item:focus {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
        }
        
        .dropdown-header {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        .dropdown-divider {
            border-color: rgba(255, 255, 255, 0.15) !important;
        }
        
        /* Dashboard cards */
        .dashboard-card {
            cursor: pointer;
            background: rgba(255, 255, 255, 0.12) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 16px !important;
            transition: all 0.3s ease !important;
        }
        
        .card-metric {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            color: #ffffff !important;
        }
        
        .card-title {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 600;
        }
        
        /* Text colors */
        .text-primary { color: #64b5f6 !important; }
        .text-success { color: #4caf50 !important; }
        .text-warning { color: #ff9800 !important; }
        .text-info { color: #00bcd4 !important; }
        .text-muted { color: rgba(255, 255, 255, 0.75) !important; }
        
        /* Better text visibility */
        .text-light { color: rgba(255, 255, 255, 0.9) !important; }
        .small, small { color: rgba(255, 255, 255, 0.8) !important; }
        
        /* Breadcrumb styling */
        .breadcrumb {
            background: rgba(255, 255, 255, 0.1) !important;
            border-radius: 8px !important;
            padding: 0.75rem 1rem !important;
        }
        
        .breadcrumb-item a {
            color: #64b5f6 !important;
            text-decoration: none !important;
        }
        
        .breadcrumb-item a:hover {
            color: #ffffff !important;
        }
        
        .breadcrumb-item.active {
            color: rgba(255, 255, 255, 0.85) !important;
        }
        
        /* General paragraph and text styling */
        p, div, span, li {
            color: rgba(255, 255, 255, 0.9) !important;
        }
        
        /* Override for specific muted text that needs to be more visible */
        .card-subtitle, .card-text {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        /* Form labels */
        .form-label, label {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500 !important;
        }
        
        /* Form help text */
        .form-text {
            color: rgba(255, 255, 255, 0.7) !important;
        }
        
        /* UNIVERSAL DROPDOWN STYLING - CLEAN AND SIMPLE */
        .form-select,
        select.form-select,
        select {
            background: #ffffff !important;
            border: 1px solid #ced4da !important;
            color: #333333 !important;
            border-radius: 8px !important;
            text-decoration: none !important;
            spellcheck: false !important;
        }
        
        .form-select:focus,
        select.form-select:focus,
        select:focus {
            background: #ffffff !important;
            border-color: #007bff !important;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25) !important;
            color: #333333 !important;
            outline: none !important;
            text-decoration: none !important;
        }
        
        .form-select option,
        select.form-select option,
        select option {
            background: #ffffff !important;
            color: #333333 !important;
            text-decoration: none !important;
        }
        
        /* Alert styling */
        .alert {
            border: none !important;
            border-radius: 12px !important;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.15) !important;
            border: 1px solid rgba(76, 175, 80, 0.3) !important;
            color: #4caf50 !important;
        }
        
        .alert-danger {
            background: rgba(244, 67, 54, 0.15) !important;
            border: 1px solid rgba(244, 67, 54, 0.3) !important;
            color: #f44336 !important;
        }
        
        .alert-warning {
            background: rgba(255, 152, 0, 0.15) !important;
            border: 1px solid rgba(255, 152, 0, 0.3) !important;
            color: #ff9800 !important;
        }
        
        .alert-info {
            background: rgba(0, 188, 212, 0.15) !important;
            border: 1px solid rgba(0, 188, 212, 0.3) !important;
            color: #00bcd4 !important;
        }
        
        /* Progress bar styling */
        .progress {
            background: rgba(255, 255, 255, 0.1) !important;
            border-radius: 8px !important;
        }
        
        .progress-bar {
            background: linear-gradient(135deg, #64b5f6 0%, #1976d2 100%) !important;
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%) !important;
            border: none !important;
            box-shadow: 0 4px 15px rgba(25, 118, 210, 0.3) !important;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%) !important;
            border: none !important;
        }
        
        .btn-outline-primary {
            border: 2px solid #64b5f6 !important;
            color: #64b5f6 !important;
        }
        
        .btn-outline-primary:hover {
            background: #64b5f6 !important;
            color: #1e3c72 !important;
        }
        
        /* Tables */
        .table {
            background: rgba(255, 255, 255, 0.08) !important;
            backdrop-filter: blur(10px) !important;
            color: rgba(255, 255, 255, 0.9) !important;
        }
        
        .table thead th {
            background: rgba(30, 60, 114, 0.3) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: #ffffff !important;
        }
        
        .table tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
        
        .table tbody tr:hover {
            background: rgba(255, 255, 255, 0.05) !important;
        }
        
        /* Modal */
        .modal-content {
            background: rgba(30, 60, 114, 0.95) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 16px !important;
            color: #ffffff !important;
        }
        
        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        /* Footer */
        .footer {
            background: rgba(30, 60, 114, 0.15) !important;
            backdrop-filter: blur(20px) !important;
            border-top: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        /* Footer specific text styling */
        .footer .text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        .footer a {
            color: rgba(255, 255, 255, 0.85) !important;
            text-decoration: none !important;
        }
        
        .footer a:hover {
            color: #64b5f6 !important;
        }
        
        /* Form controls */
        .form-control {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: #ffffff !important;
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15) !important;
            border-color: #64b5f6 !important;
            box-shadow: 0 0 0 0.25rem rgba(100, 181, 246, 0.25) !important;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5) !important;
        }
        
        /* Welcome section */
        .welcome-section {
            background: rgba(255, 255, 255, 0.1) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        .welcome-section h1 {
            color: #ffffff !important;
        }
        
        /* Badges */
        .badge.bg-success { background: #2e7d32 !important; }
        .badge.bg-warning { background: #f57c00 !important; }
        .badge.bg-danger { background: #d32f2f !important; }
        .badge.bg-info { background: #0288d1 !important; }
        .badge.bg-primary { background: #1976d2 !important; }
        
        /* Fix white and light backgrounds for dark theme */
        .bg-white {
            background: rgba(255, 255, 255, 0.12) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        .bg-light {
            background: rgba(255, 255, 255, 0.08) !important;
            backdrop-filter: blur(15px) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
        
        /* Card headers with white backgrounds */
        .card-header.bg-white {
            background: rgba(30, 60, 114, 0.2) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        .card-header.bg-white h5, 
        .card-header.bg-white .h5 {
            color: rgba(255, 255, 255, 0.95) !important;
        }
        
        /* Table heads with light backgrounds */
        .table thead.bg-light th {
            background: rgba(30, 60, 114, 0.25) !important;
            color: #ffffff !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
        }
        
        /* Input group styling for light backgrounds */
        .input-group-text.bg-light {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        /* Secondary badges need better contrast */
        .badge.bg-secondary {
            background: rgba(52, 73, 94, 0.8) !important;
            color: #ffffff !important;
        }
        
        /* Light badges with dark text need fixing */
        .badge.bg-light {
            background: rgba(255, 255, 255, 0.2) !important;
            color: rgba(255, 255, 255, 0.9) !important;
        }
        
        .badge.bg-light.text-dark {
            background: rgba(255, 255, 255, 0.25) !important;
            color: rgba(255, 255, 255, 0.95) !important;
        }
        
        /* Border styling for better definition */
        .border-bottom {
            border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        .border-top {
            border-top: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        
        /* Sticky headers need proper styling */
        .sticky-top {
            background: rgba(30, 60, 114, 0.95) !important;
            backdrop-filter: blur(20px) !important;
        }
        
        /* Fix text-dark classes for dark theme visibility */
        .text-dark {
            color: rgba(255, 255, 255, 0.9) !important;
        }
        
        /* Warning badges with text-dark need special handling */
        .badge.bg-warning.text-dark {
            background: #f57c00 !important;
            color: #ffffff !important;
        }
        
        /* Info badges with text-dark */
        .badge.bg-info.text-dark {
            background: #0288d1 !important;
            color: #ffffff !important;
        }
        
        /* Card headers with warning background and dark text */
        .card-header.bg-warning.text-dark {
            background: rgba(245, 124, 0, 0.2) !important;
            color: #ffffff !important;
            border-bottom: 1px solid rgba(245, 124, 0, 0.3) !important;
        }
        
        /* Gradient card styling for level card */
        .gradient-card-primary {
            background: linear-gradient(135deg, #00a000 0%, #00b000 25%, #00c000 75%, #00d000 100%) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.25) !important;
            border-radius: 16px !important;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4) !important;
        }
        
        .gradient-card-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5) !important;
            border: 1px solid rgba(255, 255, 255, 0.35) !important;
        }
        
        /* Responsive Navigation Fixes */
        @media (max-width: 768px) {
            /* Adjust main content padding for mobile navbar */
            main {
                padding-top: 70px !important;
            }
        }
        
        @media (max-width: 992px) {
            /* Tablet navbar adjustments */
            main {
                padding-top: 75px !important;
            }
        }
        
        /* Ensure navbar doesn't cause horizontal scroll */
        .navbar {
            /* overflow-x: hidden; - REMOVED: This was causing dropdown scroll issues */
        }
        
        .navbar .container {
            max-width: 100%;
            /* overflow-x: hidden; - REMOVED: This was causing dropdown scroll issues */
        }
        
        /* Fix dropdown menu positioning on mobile */
        @media (max-width: 991px) {
            .dropdown-menu {
                border: none;
                box-shadow: none;
                background: rgba(52, 58, 64, 0.95) !important;
                backdrop-filter: blur(10px);
            }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include __DIR__ . '/navbar.php'; ?>

    <!-- Main Content -->
    <main class="flex-grow-1" style="padding-top: 80px;">
        <!-- Main Content Container -->
        <div class="container-fluid px-3">
