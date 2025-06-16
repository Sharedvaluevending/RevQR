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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#1e3c72">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="RevenueQR">
    <title><?php echo APP_NAME; ?></title>
    
    <!-- Cache Control Meta Tags -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- AGGRESSIVE CACHE BUSTING -->
    <style id="cache-buster">
        /* FORCE RELOAD: <?php echo time(); ?> */
        /* Green theme disabled - pure blue only */
        body { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #3d72b4 75%, #5a95d1 100%) !important; }
        .navbar { background: rgba(30, 60, 114, 0.95) !important; }
        /* Kill any green */
        * { --green: blue !important; --success: blue !important; }
    </style>
    
    <!-- MOBILE NAVIGATION FIX -->
    <style id="mobile-nav-fix">
        /* CRITICAL: Ensure content is visible on mobile */
        @media (max-width: 991.98px) {
            body, html {
                overflow: visible !important;
                height: auto !important;
                position: static !important;
            }
            
            main {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: relative !important;
                z-index: 1 !important;
            }
            
            .container-fluid, .container {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            
            .card, .row, .col-md-6, .col-lg-4, .col-12 {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
        }
        /* Mobile Navigation Fixes */
        @media (max-width: 991.98px) {
            /* Fix navbar toggler visibility */
            .navbar-toggler {
                border: 1px solid rgba(255, 255, 255, 0.3) !important;
                padding: 0.25rem 0.5rem !important;
                font-size: 1.1rem !important;
                border-radius: 0.375rem !important;
                position: relative !important;
                z-index: 1051 !important;
            }
            
            .navbar-toggler:focus {
                text-decoration: none !important;
                outline: 0 !important;
                box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.25) !important;
            }
            
            .navbar-toggler-icon {
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
                width: 1.2em !important;
                height: 1.2em !important;
            }
            
            /* Fix mobile navbar collapse */
            .navbar-collapse {
                position: absolute !important;
                top: 100% !important;
                left: 0 !important;
                right: 0 !important;
                background: rgba(30, 60, 114, 0.98) !important;
                backdrop-filter: blur(20px) !important;
                border: 1px solid rgba(255, 255, 255, 0.15) !important;
                border-top: none !important;
                border-radius: 0 0 15px 15px !important;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
                z-index: 1050 !important;
                max-height: calc(100vh - 80px) !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }
            
            /* Mobile dropdown menu fixes */
            .navbar-nav .dropdown-menu {
                position: static !important;
                float: none !important;
                width: auto !important;
                margin-top: 0 !important;
                background-color: rgba(52, 58, 64, 0.95) !important;
                border: 1px solid rgba(255, 255, 255, 0.1) !important;
                border-radius: 0.5rem !important;
                margin: 0.5rem 1rem !important;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2) !important;
            }
            
            /* Mobile nav links */
            .navbar-nav .nav-link {
                padding: 0.75rem 1.5rem !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
                color: rgba(255, 255, 255, 0.9) !important;
                font-weight: 500 !important;
            }
            
            .navbar-nav .nav-link:hover,
            .navbar-nav .nav-link:focus {
                background-color: rgba(255, 255, 255, 0.1) !important;
                color: #ffffff !important;
                border-radius: 0.375rem !important;
                margin: 0 0.5rem !important;
            }
            
            /* Mobile dropdown items */
            .dropdown-item {
                padding: 0.75rem 1.5rem !important;
                color: rgba(255, 255, 255, 0.9) !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
            }
            
            .dropdown-item:hover,
            .dropdown-item:focus {
                background-color: rgba(255, 255, 255, 0.1) !important;
                color: #ffffff !important;
            }
            
            /* Mobile badges */
            .navbar .badge {
                font-size: 0.7rem !important;
                padding: 0.25rem 0.5rem !important;
            }
            
                         /* Fix main content padding for mobile */
             main {
                 padding-top: 70px !important;
                 display: block !important;
                 visibility: visible !important;
                 min-height: calc(100vh - 70px) !important;
                 position: relative !important;
                 z-index: 1 !important;
             }
             
             .container-fluid {
                 display: block !important;
                 visibility: visible !important;
                 position: relative !important;
                 z-index: 1 !important;
             }
         }
         
         /* Small mobile devices */
         @media (max-width: 575.98px) {
             .navbar-brand span {
                 display: none !important;
             }
             
             .navbar-brand img {
                 height: 28px !important;
             }
             
             main {
                 padding-top: 65px !important;
                 display: block !important;
                 visibility: visible !important;
                 min-height: calc(100vh - 65px) !important;
             }
         }
        
        /* Fix touch issues on iOS */
        @supports (-webkit-touch-callout: none) {
            .navbar-toggler {
                -webkit-touch-callout: none !important;
                -webkit-user-select: none !important;
                -webkit-tap-highlight-color: transparent !important;
            }
            
            .nav-link,
            .dropdown-item {
                -webkit-touch-callout: none !important;
                -webkit-tap-highlight-color: rgba(255, 255, 255, 0.1) !important;
            }
        }
    </style>
    
    <!-- MOBILE WHITE FLASH PREVENTION -->
    <style id="mobile-flash-fix">
        /* Prevent white flash on mobile devices */
        html {
            background: #1e3c72 !important;
            overscroll-behavior: none !important;
            -webkit-overflow-scrolling: touch !important;
            height: 100% !important;
        }
        
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #3d72b4 75%, #5a95d1 100%) !important;
            overscroll-behavior: none !important;
            -webkit-overflow-scrolling: touch !important;
            min-height: 100vh !important;
            min-height: -webkit-fill-available !important;
        }
        
        /* Mobile-specific fixes */
        @media (max-width: 768px) {
            html, body {
                background-attachment: scroll !important;
                background-size: 100% 120vh !important;
                background-repeat: no-repeat !important;
                background-position: center top !important;
                overscroll-behavior-y: none !important;
                overscroll-behavior-x: none !important;
                -webkit-overflow-scrolling: touch !important;
            }
            
            /* Extended background for overscroll areas */
            body::before {
                content: '';
                position: fixed;
                top: -20vh;
                left: 0;
                right: 0;
                bottom: -20vh;
                background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #3d72b4 75%, #5a95d1 100%);
                z-index: -1;
                pointer-events: none;
            }
            
            /* REMOVED iOS Safari bounce prevention - was hiding content */
            main, .container-fluid, .card, .content {
                display: block !important;
                visibility: visible !important;
                position: relative !important;
                z-index: 1 !important;
            }
        }
        
        /* iOS specific fixes */
        @supports (-webkit-touch-callout: none) {
            html {
                height: -webkit-fill-available !important;
            }
            
            body {
                min-height: -webkit-fill-available !important;
            }
        }
    </style>
    
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
        // Initialize all dropdowns with mobile-friendly options
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl, {
                autoClose: true,
                boundary: 'viewport'
            });
        });
        
        // Fix mobile navbar toggler
        var navbarToggler = document.querySelector('.navbar-toggler');
        var navbarCollapse = document.querySelector('.navbar-collapse');
        
        if (navbarToggler && navbarCollapse) {
            // Ensure proper Bootstrap collapse initialization
            var bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                toggle: false
            });
            
            // Add click handler for mobile toggler
            navbarToggler.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                bsCollapse.toggle();
            });
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 991.98) {
                    var isClickInsideNav = navbarCollapse.contains(e.target) || navbarToggler.contains(e.target);
                    if (!isClickInsideNav && navbarCollapse.classList.contains('show')) {
                        bsCollapse.hide();
                    }
                }
            });
            
            // Close mobile menu when clicking nav links (except dropdowns)
            var navLinks = document.querySelectorAll('.navbar-nav .nav-link:not(.dropdown-toggle)');
            navLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 991.98 && navbarCollapse.classList.contains('show')) {
                        bsCollapse.hide();
                    }
                });
            });
        }
        
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
        
        // Add touch-friendly enhancements for mobile
        if ('ontouchstart' in window) {
            // Add touch class to body for CSS targeting
            document.body.classList.add('touch-device');
            
            // Improve touch targets
            var touchTargets = document.querySelectorAll('.nav-link, .dropdown-item, .btn');
            touchTargets.forEach(function(target) {
                target.style.minHeight = '44px';
                target.style.display = 'flex';
                target.style.alignItems = 'center';
            });
        }
        
        // Prevent iOS double-tap zoom on buttons
        var clickableElements = document.querySelectorAll('button, .btn, .nav-link, .dropdown-item');
        clickableElements.forEach(function(element) {
            element.addEventListener('touchend', function(e) {
                e.preventDefault();
                element.click();
            });
        });
    });
    </script>

    <!-- Custom CSS -->
    <style type="text/css">
        /* Critical CSS - Masculine Dark Blue/Steel Theme */
        html, body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #3d72b4 75%, #5a95d1 100%) !important;
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
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #3d72b4 75%, #5a95d1 100%) !important;
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
        
        /* Ensure navbar container responsiveness */
        .navbar {
            position: fixed !important;
            top: 0 !important;
            width: 100% !important;
            z-index: 1030 !important;
        }
        
        .navbar .container {
            max-width: 100%;
            padding: 0 1rem;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include __DIR__ . '/navbar.php'; ?>

    <!-- Main Content -->
    <main class="flex-grow-1" style="padding-top: 80px;">
        <!-- Main Content Container -->
        <div class="container-fluid px-3">
