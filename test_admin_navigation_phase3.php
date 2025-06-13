<?php
/**
 * 🎯 PHASE 3 STEP 2: ADMIN NAVIGATION DIAGNOSTIC
 * 
 * Comprehensive testing of admin panel navigation links and functionality
 * Part of RevenueQR Platform Critical Fixes Phase 3
 */

require_once __DIR__ . '/html/core/config.php';

// Test Results Tracking
$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;
$warnings = 0;

echo "🔍 PHASE 3 STEP 2: ADMIN NAVIGATION ANALYSIS\n";
echo "=============================================\n\n";

echo "🚀 Starting Admin Navigation Diagnostics...\n\n";

// Test function
function test_result($test_name, $success, $message = '') {
    global $total_tests, $passed_tests, $failed_tests;
    $total_tests++;
    
    if ($success) {
        $passed_tests++;
        echo "✅ $test_name: PASSED";
        if ($message) echo " - $message";
        echo "\n";
    } else {
        $failed_tests++;
        echo "❌ $test_name: FAILED";
        if ($message) echo " - $message";
        echo "\n";
    }
}

function warning_result($test_name, $message) {
    global $warnings;
    $warnings++;
    echo "⚠️  $test_name: WARNING - $message\n";
}

// =====================================
// TEST 1: CORE ADMIN FILE STRUCTURE
// =====================================

echo "📁 Testing Core Admin File Structure...\n";

$admin_files = [
    'Admin Dashboard' => '/html/admin/dashboard_modular.php',
    'User Management' => '/html/admin/manage-users.php',
    'Business Management' => '/html/admin/manage-businesses.php',
    'Reports' => '/html/admin/reports.php',
    'System Monitor' => '/html/admin/system-monitor.php',
    'Casino Management' => '/html/admin/casino-management.php',
    'Settings' => '/html/admin/settings.php',
    'Main Navbar' => '/html/core/includes/navbar.php',
    'Admin Header' => '/html/admin/includes/header.php',
    'Admin Footer' => '/html/admin/includes/footer.php'
];

foreach ($admin_files as $name => $path) {
    $full_path = __DIR__ . $path;
    test_result(
        "File: $name",
        file_exists($full_path),
        file_exists($full_path) ? "Found at $path" : "Missing: $path"
    );
}

// =====================================
// TEST 2: ADMIN NAVIGATION MENU LINKS
// =====================================

echo "\n🔗 Testing Admin Navigation Menu Links...\n";

$admin_nav_links = [
    'Admin Dashboard' => '/admin/dashboard_modular.php',
    'Manage Users' => '/admin/manage-users.php',
    'Manage Businesses' => '/admin/manage-businesses.php',
    'Reports' => '/admin/reports.php',
    'System Monitor' => '/admin/system-monitor.php',
    'Settings' => '/admin/settings.php',
    'Economy Dashboard' => '/admin/dashboard_modular.php',
    'Business Management' => '/admin/manage-businesses.php',
    'System Reports' => '/admin/reports.php'
];

foreach ($admin_nav_links as $name => $url) {
    $file_path = __DIR__ . '/html' . $url;
    $url_accessible = file_exists($file_path);
    
    test_result(
        "Nav Link: $name",
        $url_accessible,
        $url_accessible ? "Accessible at $url" : "Broken link: $url"
    );
}

// =====================================
// TEST 3: ADMIN DASHBOARD FUNCTIONALITY
// =====================================

echo "\n📊 Testing Admin Dashboard Functionality...\n";

// Check if dashboard_modular.php includes required components
$dashboard_file = __DIR__ . '/html/admin/dashboard_modular.php';
if (file_exists($dashboard_file)) {
    $dashboard_content = file_get_contents($dashboard_file);
    
    $dashboard_features = [
        'QR Coin Economy Section' => 'QR Coin Economy',
        'User Management Access' => 'manage-users.php',
        'Business Management Access' => 'manage-businesses.php',
        'Casino Management Link' => 'casino-management.php',
        'Reports Access' => 'reports.php',
        'System Monitor Link' => 'system-monitor.php',
        'Settings Access' => 'settings.php',
        'Bootstrap Icons' => 'bi bi-',
        'Responsive Design' => 'container',
        'Navigation Menu' => 'nav-link'
    ];
    
    foreach ($dashboard_features as $feature => $search_term) {
        $has_feature = strpos($dashboard_content, $search_term) !== false;
        test_result(
            "Dashboard Feature: $feature",
            $has_feature,
            $has_feature ? "Present" : "Missing search term: $search_term"
        );
    }
} else {
    test_result("Admin Dashboard File", false, "dashboard_modular.php not found");
}

// =====================================
// TEST 4: ADMIN PERMISSION SYSTEM
// =====================================

echo "\n🔐 Testing Admin Permission System...\n";

// Check navbar.php for admin role checks
$navbar_file = __DIR__ . '/html/core/includes/navbar.php';
if (file_exists($navbar_file)) {
    $navbar_content = file_get_contents($navbar_file);
    
    $permission_checks = [
        'Admin Role Check' => "has_role('admin')",
        'Business Role Check' => "has_role('business')",
        'Login Check' => 'is_logged_in()',
        'Admin Navigation Section' => 'if (has_role(\'admin\'))',
        'Business Navigation Section' => 'elseif (has_role(\'business\'))',
        'User Navigation Section' => 'else:'
    ];
    
    foreach ($permission_checks as $check => $search_term) {
        $has_check = strpos($navbar_content, $search_term) !== false;
        test_result(
            "Permission: $check",
            $has_check,
            $has_check ? "Implemented" : "Missing: $search_term"
        );
    }
} else {
    test_result("Navigation Permission System", false, "navbar.php not found");
}

// =====================================
// TEST 5: ADMIN QUICK ACTIONS
// =====================================

echo "\n⚡ Testing Admin Quick Actions...\n";

// Check for admin quick action buttons in dashboard
if (file_exists($dashboard_file)) {
    $admin_actions = [
        'Quick Dashboard Access' => 'dashboard_modular.php',
        'Quick User Management' => 'manage-users.php',
        'Quick Business Management' => 'manage-businesses.php',
        'Quick Casino Management' => 'casino-management.php',
        'Quick Reports Access' => 'reports.php',
        'Quick System Monitor' => 'system-monitor.php',
        'Quick Settings Access' => 'settings.php'
    ];
    
    foreach ($admin_actions as $action => $search_term) {
        $has_action = strpos($dashboard_content, $search_term) !== false;
        test_result(
            "Quick Action: $action",
            $has_action,
            $has_action ? "Available" : "Missing: $search_term"
        );
    }
}

// =====================================
// TEST 6: ADMIN PANEL INTEGRATION
// =====================================

echo "\n🔧 Testing Admin Panel Integration...\n";

// Check for proper includes and dependencies
$integration_files = [
    'Core Config' => '/html/core/config.php',
    'Session Management' => '/html/core/session.php',
    'Database Connection' => '/html/core/database.php',
    'Authentication' => '/html/core/auth.php',
    'Admin Functions' => '/html/core/admin_functions.php'
];

foreach ($integration_files as $name => $path) {
    $full_path = __DIR__ . $path;
    $exists = file_exists($full_path);
    
    test_result(
        "Integration: $name",
        $exists,
        $exists ? "Available" : "Missing: $path"
    );
    
    // Check if file is included in dashboard
    if ($exists && file_exists($dashboard_file)) {
        $include_check = strpos($dashboard_content, basename($path)) !== false;
        if (!$include_check) {
            warning_result(
                "Dashboard Include: $name",
                "File exists but may not be included in dashboard"
            );
        }
    }
}

// =====================================
// TEST 7: ADMIN DROPDOWN MENUS
// =====================================

echo "\n📋 Testing Admin Dropdown Menus...\n";

if (file_exists($navbar_file)) {
    $dropdown_features = [
        'QR Coin Economy Dropdown' => 'economyDropdown',
        'Economy Dashboard Link' => 'Economy Dashboard',
        'Business Management Link' => 'Business Management',
        'System Reports Link' => 'System Reports',
        'Dropdown Dividers' => 'dropdown-divider',
        'Coming Soon Features' => 'Coming Soon',
        'Bootstrap Dropdowns' => 'dropdown-toggle'
    ];
    
    foreach ($dropdown_features as $feature => $search_term) {
        $has_feature = strpos($navbar_content, $search_term) !== false;
        test_result(
            "Dropdown: $feature",
            $has_feature,
            $has_feature ? "Present" : "Missing: $search_term"
        );
    }
}

// =====================================
// TEST 8: ADMIN RESPONSIVE DESIGN
// =====================================

echo "\n📱 Testing Admin Responsive Design...\n";

if (file_exists($dashboard_file)) {
    $responsive_features = [
        'Bootstrap Container' => 'container',
        'Responsive Grid' => 'col-',
        'Mobile Navigation' => 'navbar-toggler',
        'Responsive Cards' => 'card',
        'Flex Layout' => 'd-flex',
        'Mobile Display Classes' => 'd-none d-sm-',
        'Responsive Buttons' => 'btn-sm'
    ];
    
    foreach ($responsive_features as $feature => $search_term) {
        $has_feature = strpos($dashboard_content, $search_term) !== false;
        test_result(
            "Responsive: $feature",
            $has_feature,
            $has_feature ? "Implemented" : "Missing: $search_term"
        );
    }
}

// =====================================
// TEST 9: ERROR HANDLING AND REDIRECTS
// =====================================

echo "\n🚨 Testing Error Handling and Redirects...\n";

// Check for proper redirects
$redirect_files = [
    'Dashboard Redirect' => '/html/admin/dashboard.php'
];

foreach ($redirect_files as $name => $path) {
    $full_path = __DIR__ . $path;
    if (file_exists($full_path)) {
        $content = file_get_contents($full_path);
        $has_redirect = strpos($content, 'Location:') !== false || strpos($content, 'header(') !== false;
        test_result(
            "Redirect: $name",
            $has_redirect,
            $has_redirect ? "Properly redirects" : "No redirect found"
        );
    } else {
        test_result("Redirect File: $name", false, "File not found: $path");
    }
}

// =====================================
// FINAL REPORT & RECOMMENDATIONS
// =====================================

echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 PHASE 3 STEP 2 - ADMIN NAVIGATION DIAGNOSTIC RESULTS\n";
echo str_repeat("=", 50) . "\n\n";

$success_rate = round(($passed_tests / $total_tests) * 100, 1);

echo "📈 OVERALL RESULTS:\n";
echo "  ✅ Passed: $passed_tests\n";
echo "  ❌ Failed: $failed_tests\n";
echo "  ⚠️  Warnings: $warnings\n";
echo "  📊 Total Tests: $total_tests\n";
echo "  🎯 Success Rate: $success_rate%\n\n";

// Determine status and recommendations
if ($success_rate >= 90) {
    echo "🎉 STATUS: EXCELLENT - Admin navigation is in great shape!\n";
    echo "🎯 RECOMMENDATION: Minor optimizations only\n\n";
} elseif ($success_rate >= 75) {
    echo "✅ STATUS: GOOD - Admin navigation mostly functional\n";
    echo "🎯 RECOMMENDATION: Address failed tests for full functionality\n\n";
} elseif ($success_rate >= 60) {
    echo "⚠️  STATUS: NEEDS ATTENTION - Several admin navigation issues\n";
    echo "🎯 RECOMMENDATION: Priority fixes required\n\n";
} else {
    echo "🚨 STATUS: CRITICAL - Major admin navigation problems\n";
    echo "🎯 RECOMMENDATION: Immediate comprehensive repair needed\n\n";
}

echo "🚀 NEXT STEPS:\n";
echo "1. Review failed tests above\n";
echo "2. Fix broken navigation links\n";
echo "3. Restore missing admin functionality\n";
echo "4. Test admin user access and permissions\n";
echo "5. Verify responsive design on mobile devices\n\n";

echo "🔧 PHASE 3 STEP 2 READY FOR FIXES IMPLEMENTATION\n";
echo "==============================================\n";
?> 