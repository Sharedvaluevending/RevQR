<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auto_login.php';
require_once __DIR__ . '/../core/pizza_tracker_utils.php';

$tracker = null;
$message = '';
$message_type = '';

// Get tracker ID from query params
$tracker_id = isset($_GET['tracker_id']) ? (int)$_GET['tracker_id'] : null;

if (!$tracker_id) {
    $message = "You are not tracking any pizza. To track a pizza, scan a QR code with pizza tracker link on page or use a direct URL with tracker ID.";
    $message_type = "info";
} else {
    // Initialize pizza tracker utility
    $pizzaTracker = new PizzaTracker($pdo);
    
    // Track click if source is provided
    if (isset($_GET['source'])) {
        $source_page = 'qr_direct';
        if ($_GET['source'] === 'voting') {
            $source_page = 'voting_page';
        } elseif ($_GET['source'] === 'campaign') {
            $source_page = 'campaign_page';
        }
        
        $pizzaTracker->trackClick($tracker_id, $source_page, null, $_SERVER['HTTP_REFERER'] ?? null);
    }
    
    // Get tracker details
    $tracker = $pizzaTracker->getTrackerDetails($tracker_id);
    
    if (!$tracker || !$tracker['is_active']) {
        $message = "Pizza tracker not found or is inactive.";
        $message_type = "danger";
        $tracker = null;
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* MODERN DARK THEME MATCHING USER DASHBOARD - Pizza Tracker Public Page */
/* Override global header styles for pizza tracker page */
html.pizza-tracker-page, 
html.pizza-tracker-page body,
body.pizza-tracker-page {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #34495e 75%, #2c3e50 100%) !important;
    background-attachment: fixed !important;
    color: #ffffff !important;
    min-height: 100vh !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    overflow-x: hidden !important;
    /* Prevent overscroll bounce that can cause white flash */
    overscroll-behavior: none !important;
    -webkit-overflow-scrolling: touch !important;
}

/* Fix mobile background issues - remove fixed attachment on mobile */
@media (max-width: 768px) {
    html.pizza-tracker-page, 
    html.pizza-tracker-page body,
    body.pizza-tracker-page {
        background-attachment: scroll !important;
        /* Ensure background covers overscroll areas */
        background-size: 100% 120vh !important;
        background-repeat: no-repeat !important;
        /* Extended background for overscroll */
        background-position: center top !important;
    }
    
    /* Prevent mobile browser white flash on overscroll */
    html.pizza-tracker-page {
        overscroll-behavior-y: none !important;
        -webkit-overflow-scrolling: touch !important;
        /* Ensure full coverage */
        min-height: 120vh !important;
    }
    
    body.pizza-tracker-page {
        /* Additional mobile background coverage */
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #34495e 75%, #2c3e50 100%) !important;
        min-height: 120vh !important;
        position: relative !important;
    }
    
    /* Create a pseudo-element for extended background on very small screens */
    body.pizza-tracker-page::before {
        content: '';
        position: fixed;
        top: -20vh;
        left: 0;
        right: 0;
        bottom: -20vh;
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #34495e 75%, #2c3e50 100%);
        z-index: -1;
        pointer-events: none;
    }
}

/* Additional fix for very small screens and potential overscroll */
@media (max-width: 576px) {
    html.pizza-tracker-page, 
    html.pizza-tracker-page body,
    body.pizza-tracker-page {
        /* Ensure no white shows during overscroll */
        background-size: 100% 150vh !important;
        min-height: 150vh !important;
    }
    
    body.pizza-tracker-page::before {
        top: -30vh;
        bottom: -30vh;
    }
}

/* Glass morphism cards matching user dashboard */
.pizza-tracker-page .card,
.pizza-tracker-page .card.h-100 {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    transition: all 0.3s ease !important;
}

.pizza-tracker-page .card:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
}

.pizza-tracker-page .container,
.pizza-tracker-page .container-fluid {
    background: unset !important;
    backdrop-filter: unset !important;
    border: unset !important;
    border-radius: unset !important;
    box-shadow: unset !important;
}

/* Dark Masculine Color Scheme */
:root {
    --bg-primary: #0f0f0f;
    --bg-secondary: #1a1a1a;
    --bg-card: #252525;
    --bg-accent: #2d2d2d;
    --text-primary: #ffffff;
    --text-secondary: #b8b8b8;
    --text-muted: #8a8a8a;
    --accent-green: #00ff88;
    --accent-red: #ff4757;
    --accent-blue: #3742fa;
    --accent-orange: #ff6348;
    --accent-purple: #7d5fff;
    --border-color: #3a3a3a;
    --shadow-dark: 0 8px 32px rgba(0, 0, 0, 0.6);
    --shadow-light: 0 4px 16px rgba(0, 0, 0, 0.3);
    
    /* Pizza-specific gradients */
    --pizza-gradient: linear-gradient(135deg, var(--accent-orange) 0%, #ff9f43 100%);
    --success-gradient: linear-gradient(135deg, var(--accent-green) 0%, #00d084 100%);
    --warning-gradient: linear-gradient(135deg, var(--accent-orange) 0%, #ff7675 100%);
    --danger-gradient: linear-gradient(135deg, var(--accent-red) 0%, #ff3838 100%);
}

/* Override navbar and footer */
.pizza-tracker-page .navbar,
.pizza-tracker-page .navbar-nav,
.pizza-tracker-page .nav-link,
.pizza-tracker-page .footer {
    display: none !important;
}

.pizza-tracker-page main {
    padding: 0 !important;
    margin: 0 !important;
    width: 100% !important;
    min-height: 100vh !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

/* Hero Section with Animated Pizza */
.pizza-hero {
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-accent) 100%);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-dark);
    color: var(--text-primary);
    padding: 4rem 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    margin: 2rem auto;
    max-width: 1200px;
}

.pizza-hero::before {
    content: '🍕';
    position: absolute;
    font-size: 15rem;
    opacity: 0.1;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    animation: float 6s ease-in-out infinite;
    z-index: 0;
}

.pizza-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: conic-gradient(from 0deg, transparent, rgba(255, 99, 72, 0.05), transparent);
    animation: rotate 15s linear infinite;
    pointer-events: none;
    z-index: 0;
}

@keyframes float {
    0%, 100% { transform: translate(-50%, -50%) rotate(0deg); }
    50% { transform: translate(-50%, -50%) rotate(10deg) scale(1.1); }
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.pizza-hero h1 {
    color: var(--text-primary) !important;
    font-weight: 700;
    font-size: 3rem;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
    background: var(--pizza-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.pizza-hero .lead {
    color: var(--text-secondary) !important;
    font-size: 1.3rem;
    position: relative;
    z-index: 1;
    margin-bottom: 1rem;
}

.pizza-hero p {
    color: var(--text-muted) !important;
    position: relative;
    z-index: 1;
}

/* Progress Container */
.progress-container {
    background: linear-gradient(135deg, var(--bg-card) 0%, rgba(37, 37, 37, 0.9) 100%);
    backdrop-filter: blur(20px);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    box-shadow: var(--shadow-light);
    padding: 2rem;
    margin: 2rem 0;
    position: relative;
    overflow: hidden;
}

.progress-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--pizza-gradient);
}

.progress-container h3 {
    color: var(--text-primary) !important;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.progress-container .text-muted {
    color: var(--text-secondary) !important;
}

/* Pizza Progress Bar */
.pizza-progress {
    height: 40px;
    border-radius: 20px;
    background: var(--bg-accent);
    border: 1px solid var(--border-color);
    overflow: hidden;
    position: relative;
    margin: 1.5rem 0;
}

.pizza-progress-bar {
    height: 100%;
    background: var(--pizza-gradient);
    border-radius: 20px;
    transition: width 1s ease-in-out;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #000;
    font-weight: 700;
    font-size: 1.1rem;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.pizza-progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

/* Pizza Count Display */
.pizza-count {
    font-size: 3rem;
    color: var(--accent-orange);
    font-weight: 700;
    margin: 1rem 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}

.stat-card {
    background: linear-gradient(135deg, var(--bg-accent) 0%, rgba(45, 45, 45, 0.8) 100%);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-light);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--pizza-gradient);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-dark);
    border-color: var(--accent-orange);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--accent-orange);
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    font-weight: 500;
}

/* Completion Badge */
.completion-badge {
    background: var(--success-gradient);
    color: #000;
    padding: 1rem 2rem;
    border-radius: 50px;
    font-size: 1.2rem;
    font-weight: 700;
    display: inline-block;
    margin: 1rem 0;
    animation: pulse 2s infinite;
    box-shadow: 0 4px 15px rgba(0, 255, 136, 0.3);
    border: 1px solid rgba(0, 255, 136, 0.2);
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* Recent Activity */
.recent-activity {
    background: linear-gradient(135deg, rgba(0, 255, 136, 0.05) 0%, var(--bg-card) 100%);
    border: 1px solid rgba(0, 255, 136, 0.2);
    border-radius: 15px;
    padding: 1.5rem;
    margin: 2rem 0;
    box-shadow: var(--shadow-light);
}

.recent-activity h5 {
    color: var(--accent-green) !important;
    font-weight: 700;
    margin-bottom: 1rem;
    text-align: center;
}

.activity-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: rgba(0, 255, 136, 0.1);
    border: 1px solid rgba(0, 255, 136, 0.2);
    border-radius: 10px;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
}

.activity-item:hover {
    background: rgba(0, 255, 136, 0.15);
    transform: translateX(4px);
}

.activity-item:last-child {
    margin-bottom: 0;
}

.activity-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--success-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #000;
    margin-right: 1rem;
    flex-shrink: 0;
    font-size: 1.5rem;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(0, 255, 136, 0.3);
}

/* Alert Styling */
.alert {
    background: linear-gradient(135deg, rgba(55, 66, 250, 0.1) 0%, var(--bg-card) 100%);
    border: 1px solid rgba(55, 66, 250, 0.3);
    border-radius: 12px;
    color: var(--accent-blue);
    padding: 1rem 1.25rem;
    margin: 1rem 0;
}

/* Call to Action */
.lead {
    color: var(--text-primary) !important;
    font-weight: 600;
    font-size: 1.2rem;
}

.text-muted {
    color: var(--text-muted) !important;
}

/* Error Container */
.error-container {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-primary);
}

.error-container h1 {
    font-size: 5rem;
    margin-bottom: 1rem;
    opacity: 0.8;
}

.error-container h2 {
    color: var(--accent-red) !important;
    font-weight: 700;
    margin-bottom: 1rem;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .pizza-hero {
        padding: 2rem 1rem;
        margin: 1rem 0;
        border-radius: 0;
    }
    
    .pizza-hero::before {
        font-size: 8rem;
    }
    
    .pizza-hero h1 {
        font-size: 2rem;
    }
    
    .promo-banner {
        margin: 1rem 0;
        padding: 1.25rem 1.5rem;
        min-height: 100px;
        border-radius: 0;
    }
    
    .promo-content {
        gap: 1.5rem;
    }
    
    .promo-icon {
        font-size: 2rem;
    }
    
    .promo-message {
        font-size: 1.3rem;
    }
    
    .progress-container {
        padding: 1.5rem;
        margin: 1rem 0;
        border-radius: 0;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin: 1rem 0;
    }
    
    .stat-card {
        border-radius: 0;
        margin-bottom: 0;
    }
    
    .stat-value {
        font-size: 2rem;
    }
    
    .pizza-count {
        font-size: 2.5rem;
    }
    
    .container {
        padding: 0 !important;
    }
    
    .recent-activity {
        border-radius: 0;
        margin: 1rem 0;
    }
    
    .alert {
        border-radius: 0;
        margin: 1rem 0;
    }
    
    .cta-section {
        padding: 2rem 1.5rem;
        margin: 1rem 0;
        border-radius: 0;
    }
    
    .cta-title {
        font-size: 2rem;
    }
    
    .cta-lead {
        font-size: 1.1rem;
        margin-bottom: 2rem;
    }
    
    .cta-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
        margin: 2rem 0;
    }
    
    .cta-item {
        padding: 1.5rem 1rem;
        border-radius: 8px;
    }
    
    .cta-icon {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
    }
    
    .cta-highlight {
        padding: 1.5rem;
        margin-top: 2rem;
        border-radius: 8px;
    }
    
    .cta-highlight p {
        font-size: 1rem;
    }
}

@media (max-width: 576px) {
    .pizza-hero {
        margin: 1rem 0;
        border-radius: 0;
        padding: 1.5rem 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .pizza-hero h1 {
        font-size: 1.8rem;
    }
    
    .activity-item {
        flex-direction: column;
        text-align: center;
        padding: 1.5rem;
    }
    
    .activity-icon {
        margin-right: 0;
        margin-bottom: 1rem;
    }
    
    .promo-banner {
        margin: 1rem 0;
        padding: 1rem 1.25rem;
        min-height: 80px;
        border-radius: 0;
    }
    
    .promo-content {
        gap: 1rem;
    }
    
    .promo-icon {
        font-size: 1.75rem;
    }
    
    .promo-message {
        font-size: 1.1rem;
    }
    
    .progress-container {
        margin: 1rem 0;
        border-radius: 0;
        padding: 1.25rem 1rem;
    }
    
    .cta-section {
        padding: 1.5rem 1rem;
        margin: 1rem 0;
        border-radius: 0;
    }
    
    .cta-item {
        border-radius: 6px;
    }
    
    .cta-highlight {
        border-radius: 6px;
    }
}

/* Container Override for Full Dark Theme */
.pizza-tracker-page .container-fluid {
    background: var(--bg-primary) !important;
    padding: 0 2rem !important;
    min-height: 100vh !important;
    max-width: 100% !important;
}

/* FIX: Desktop mode on mobile - ensure natural Bootstrap behavior */
@media (min-width: 768px) and (max-width: 1024px) {
    .pizza-tracker-page .container-fluid {
        max-width: 1200px !important;
        width: 100% !important;
        margin: 0 auto !important;
        padding-left: 15px !important;
        padding-right: 15px !important;
    }
    
    /* Ensure Bootstrap grid behaves naturally */
    .row {
        width: 100% !important;
        margin-left: -15px !important;
        margin-right: -15px !important;
        display: flex !important;
        flex-wrap: wrap !important;
    }
    
    [class*="col-"] {
        position: relative !important;
        width: 100% !important;
        padding-left: 15px !important;
        padding-right: 15px !important;
    }
    
    .col-md-6 { 
        flex: 0 0 50% !important; 
        max-width: 50% !important;
    }
    
    .card {
        width: 100% !important;
        margin-bottom: 1rem !important;
    }
}

.pizza-tracker-page .container {
    background: rgba(26, 26, 26, 0.95) !important;
    border-radius: 16px !important;
    box-shadow: var(--shadow-dark) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid var(--border-color) !important;
    margin: 1rem auto !important;
    padding: 2rem !important;
    max-width: 1400px !important;
}

/* Promotional Banner Styling */
.promo-banner {
    background: linear-gradient(135deg, var(--accent-orange) 0%, #ff7675 100%);
    box-shadow: var(--shadow-light);
    border-radius: 16px;
    margin: 1rem auto;
    padding: 1.5rem 2rem;
    position: relative;
    overflow: hidden;
    animation: promoGlow 3s ease-in-out infinite alternate;
    max-width: 1200px;
    min-height: 120px;
    display: flex;
    align-items: center;
}

.promo-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: conic-gradient(from 0deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    animation: shimmer 4s linear infinite;
    pointer-events: none;
}

@keyframes promoGlow {
    0% { box-shadow: 0 4px 20px rgba(255, 99, 72, 0.3); }
    100% { box-shadow: 0 8px 40px rgba(255, 99, 72, 0.6); }
}

@keyframes shimmer {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.promo-content {
    display: flex;
    align-items: center;
    gap: 2rem;
    position: relative;
    z-index: 1;
    cursor: pointer;
    transition: transform 0.3s ease;
    width: 100%;
}

.promo-content:hover {
    transform: scale(1.02);
}

.promo-icon {
    font-size: 2.5rem;
    animation: bounce 2s infinite;
    flex-shrink: 0;
}

.promo-icon-right {
    transform: scaleX(-1);
    animation: bounceRight 2s infinite;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-8px); }
    60% { transform: translateY(-4px); }
}

@keyframes bounceRight {
    0%, 20%, 50%, 80%, 100% { transform: scaleX(-1) translateY(0); }
    40% { transform: scaleX(-1) translateY(-8px); }
    60% { transform: scaleX(-1) translateY(-4px); }
}

.promo-text {
    flex: 1;
    color: white;
    text-align: center;
}

.promo-message {
    font-size: 1.6rem;
    font-weight: 700;
    margin-bottom: 0;
    line-height: 1.3;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

/* Call to Action Section */
.cta-section {
    background: linear-gradient(135deg, var(--bg-card) 0%, rgba(37, 37, 37, 0.9) 100%);
    backdrop-filter: blur(20px);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    box-shadow: var(--shadow-light);
    padding: 3rem 2rem;
    margin: 2rem 0;
    position: relative;
    overflow: hidden;
}

.cta-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--pizza-gradient);
}

.cta-title {
    color: var(--text-primary) !important;
    font-weight: 700;
    font-size: 2.5rem;
    margin-bottom: 1rem;
    background: var(--pizza-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.cta-lead {
    color: var(--text-secondary) !important;
    font-size: 1.3rem;
    margin-bottom: 2.5rem;
    font-weight: 500;
}

.cta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
    margin: 2.5rem 0;
}

.cta-item {
    background: linear-gradient(135deg, rgba(255, 99, 72, 0.1) 0%, var(--bg-accent) 100%);
    border: 1px solid rgba(255, 99, 72, 0.2);
    border-radius: 16px;
    padding: 2rem 1.5rem;
    text-align: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.cta-item::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: conic-gradient(from 0deg, transparent, rgba(255, 99, 72, 0.03), transparent);
    animation: rotate 15s linear infinite;
    pointer-events: none;
}

.cta-item:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 40px rgba(255, 99, 72, 0.3);
    border-color: rgba(255, 99, 72, 0.4);
    background: linear-gradient(135deg, rgba(255, 99, 72, 0.15) 0%, var(--bg-accent) 100%);
}

.cta-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: block;
    position: relative;
    z-index: 1;
}

.cta-item h5 {
    color: var(--accent-orange) !important;
    font-weight: 700;
    margin-bottom: 1rem;
    font-size: 1.2rem;
    position: relative;
    z-index: 1;
}

.cta-item p {
    color: var(--text-secondary) !important;
    line-height: 1.5;
    margin-bottom: 0;
    position: relative;
    z-index: 1;
}

.cta-highlight {
    background: linear-gradient(135deg, rgba(0, 255, 136, 0.1) 0%, var(--bg-card) 100%);
    border: 1px solid rgba(0, 255, 136, 0.2);
    border-radius: 15px;
    padding: 2rem;
    margin-top: 2.5rem;
    position: relative;
}

.cta-highlight::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--success-gradient);
}

.cta-highlight strong {
    color: var(--accent-green) !important;
    font-weight: 700;
}

.cta-highlight p {
    color: var(--text-primary) !important;
    font-size: 1.1rem;
    line-height: 1.6;
}

.cta-highlight .text-muted {
    color: var(--text-secondary) !important;
    font-size: 1rem;
}

/* Full width responsive adjustments */
@media (max-width: 768px) {
    .pizza-tracker-page .container-fluid {
        padding: 0 1rem !important;
    }
}

@media (max-width: 576px) {
    .pizza-tracker-page .container-fluid {
        padding: 0 0.5rem !important;
    }
}

/* Progress container with max width */
.container-fluid.my-5 {
    max-width: 1400px !important;
    margin: 2rem auto !important;
    padding: 0 1rem !important;
}

@media (max-width: 768px) {
    .container-fluid.my-5 {
        padding: 0 0.5rem !important;
        margin: 1rem auto !important;
    }
}
</style>

<body class="pizza-tracker-page">
    <div class="container-fluid px-0">
        <!-- Header Section with QR Logo and Avatars -->
        <div class="header-section" style="text-align: center; padding: 2rem 0; margin-bottom: 2rem;">
            <img src="../img/logoRQ.png" alt="Revenue QR Logo" style="width: 120px; height: 120px; margin-bottom: 1rem; border-radius: 50%; box-shadow: 0 8px 32px rgba(100, 181, 246, 0.3); border: 3px solid rgba(255, 255, 255, 0.2);">
            <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.2rem; margin-bottom: 2rem;">Pizza Progress Tracker</p>
            
            <!-- Sample Avatars -->
            <div style="display: flex; justify-content: center; gap: 1rem; margin: 1rem 0;">
                <img src="../img/qrCoin.png" alt="QR Coin" style="width: 60px; height: 60px; border-radius: 50%; border: 2px solid rgba(255, 255, 255, 0.3); box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3); transition: all 0.3s ease;">
                <img src="../img/qractivity.png" alt="QR Activity" style="width: 60px; height: 60px; border-radius: 50%; border: 2px solid rgba(255, 255, 255, 0.3); box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3); transition: all 0.3s ease;">
                <img src="../img/SHAREDLOGOblank.png" alt="Business Logo" style="width: 60px; height: 60px; border-radius: 50%; border: 2px solid rgba(255, 255, 255, 0.3); box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3); transition: all 0.3s ease; background-color: white; padding: 4px;">
            </div>
        </div>

        <?php if (!$tracker): ?>
            <!-- No Tracker State - Helpful Guide -->
            <div class="pizza-hero">
                <div class="container-fluid">
                    <div class="error-container">
                        <h1>🍕</h1>
                        <h2>You're Not Tracking Any Pizza</h2>
                        <p class="lead">To start tracking pizza progress, you'll need to scan a QR code with a pizza tracker link.</p>
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> mt-3">
                                <strong>How to track pizza:</strong><br>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Call to Action Grid -->
                        <div class="cta-grid mt-4">
                            <div class="cta-item">
                                <div class="cta-icon">📱</div>
                                <h5>Scan QR Code</h5>
                                <p>Look for QR codes on campaigns, voting pages, or business materials that link to pizza trackers.</p>
                            </div>
                            <div class="cta-item">
                                <div class="cta-icon">🗳️</div>
                                <h5>Check Voting Pages</h5>
                                <p>Pizza tracker links are often embedded in voting campaigns - participate to track progress!</p>
                            </div>
                            <div class="cta-item">
                                <div class="cta-icon">🔗</div>
                                <h5>Direct Links</h5>
                                <p>Businesses may provide direct URLs with tracker IDs. These links will automatically start tracking.</p>
                            </div>
                        </div>
                        
                        <div class="cta-highlight mt-4">
                            <p class="mb-2">
                                <strong>🍕 What is Pizza Tracking?</strong> Businesses set up pizza goals where a percentage of vending machine sales 
                                goes toward earning free pizza for everyone. Track the progress and celebrate when goals are reached!
                            </p>
                            <p class="text-muted">
                                <a href="<?php echo APP_URL; ?>/user/dashboard.php" style="color: var(--accent-green);">← Return to Dashboard</a> or 
                                <a href="<?php echo APP_URL; ?>/qr-display-public.php" style="color: var(--accent-blue);">View QR Gallery</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Hero Section -->
            <div class="pizza-hero">
                <div class="container-fluid">
                    <h1 class="display-4 mb-3">
                        🍕 <?php echo htmlspecialchars($tracker['name']); ?>
                    </h1>
                    <p class="lead mb-4">
                        <?php echo htmlspecialchars($tracker['business_name']); ?>
                    </p>
                    <?php if ($tracker['description']): ?>
                        <p class="mb-4">
                            <?php echo htmlspecialchars($tracker['description']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <!-- Completion Status -->
                    <?php if ($tracker['is_complete']): ?>
                        <div class="completion-badge">
                            🎉 Goal Reached! Pizza Time! 🎉
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Promotional Message -->
            <?php if ($tracker['promo_active'] && !empty($tracker['promo_message'])): ?>
                <?php 
                // Track promotional message view
                $stmt = $pdo->prepare("UPDATE pizza_trackers SET promo_views = promo_views + 1 WHERE id = ?");
                $stmt->execute([$tracker_id]);
                ?>
                <div class="promo-banner">
                    <div class="container-fluid">
                        <div class="promo-content" onclick="trackPromoClick(<?php echo $tracker_id; ?>)">
                            <div class="promo-icon">📢</div>
                            <div class="promo-text">
                                <div class="promo-message">
                                    <?php echo nl2br(htmlspecialchars($tracker['promo_message'])); ?>
                                </div>
                            </div>
                            <div class="promo-icon promo-icon-right">📢</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Progress Section -->
            <div class="container-fluid my-5">
                <div class="progress-container">
                    <div class="row align-items-center mb-4">
                        <div class="col-md-8">
                            <h3 class="mb-0">Progress to Next Pizza</h3>
                            <p class="text-muted mb-0">
                                <?php echo $tracker['progress_percent']; ?>% complete
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="pizza-count">
                                <?php echo $tracker['completion_count']; ?> 🍕
                            </div>
                            <small class="text-muted">Pizzas Earned</small>
                        </div>
                    </div>
                    
                    <div class="pizza-progress">
                        <div class="pizza-progress-bar" 
                             style="width: <?php echo $tracker['progress_percent']; ?>%">
                            <?php if ($tracker['progress_percent'] > 20): ?>
                                <?php echo $tracker['progress_percent']; ?>%
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($tracker['total_clicks'] ?? 0); ?></div>
                        <div class="stat-label">Total Views</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">$<?php echo number_format($tracker['pizza_cost'], 0); ?></div>
                        <div class="stat-label">Pizza Cost</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $tracker['completion_count']; ?></div>
                        <div class="stat-label">Pizzas Earned</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $tracker['progress_percent']; ?>%</div>
                        <div class="stat-label">Progress</div>
                    </div>
                </div>

                <!-- Last Completion -->
                <?php if ($tracker['last_completion_date']): ?>
                    <div class="text-center mt-4">
                        <div class="recent-activity">
                            <h5 class="mb-3">🎉 Last Pizza Earned</h5>
                            <div class="activity-item justify-content-center">
                                <div class="activity-icon">
                                    🍕
                                </div>
                                <div>
                                    <strong>Pizza Goal Achieved!</strong><br>
                                    <small class="text-muted">
                                        <?php echo date('F j, Y \a\t g:i A', strtotime($tracker['last_completion_date'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Campaign Link -->
                <?php if ($tracker['campaign_name']): ?>
                    <div class="text-center mt-4">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            This pizza tracker is linked to the 
                            <strong><?php echo htmlspecialchars($tracker['campaign_name']); ?></strong> campaign.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Call to Action -->
                <div class="text-center mt-5">
                    <div class="cta-section">
                        <h3 class="cta-title">🎯 Help Us Reach Our Pizza Goal!</h3>
                        <p class="cta-lead">Every purchase counts toward our delicious victory!</p>
                        
                        <div class="cta-grid">
                            <div class="cta-item">
                                <div class="cta-icon">🗳️</div>
                                <h5>Vote in Campaigns</h5>
                                <p>Participate in our fun voting campaigns and help shape what goes in our vending machines!</p>
                            </div>
                            <div class="cta-item">
                                <div class="cta-icon">🥤</div>
                                <h5>Buy from Vending Machines</h5>
                                <p>Every snack, drink, and treat you purchase contributes a percentage directly to our pizza fund!</p>
                            </div>
                            <div class="cta-item">
                                <div class="cta-icon">🤝</div>
                                <h5>Everyone Participates</h5>
                                <p>Whether you vote, buy, or just cheer us on - every action brings us closer to pizza time!</p>
                            </div>
                        </div>
                        
                        <div class="cta-highlight">
                            <p class="mb-2">
                                <strong>🍕 How it works:</strong> A percentage of every vending machine purchase automatically goes toward our pizza goal. 
                                The more everyone participates, the faster we earn our free pizza celebration!
                            </p>
                            <p class="text-muted">
                                Keep checking back to watch our progress grow with every purchase and vote. 
                                Together, we're building something delicious! 🎉
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Auto-refresh script -->
    <script>
        // Track promotional message clicks
        function trackPromoClick(trackerId) {
            // Send AJAX request to track click
            fetch('/html/api/track-promo-click.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    tracker_id: trackerId,
                    timestamp: new Date().toISOString()
                })
            }).catch(error => {
                console.log('Analytics tracking failed:', error);
            });
            
            // Add visual feedback
            const promoContent = event.target.closest('.promo-content');
            if (promoContent) {
                promoContent.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    promoContent.style.transform = 'scale(1.02)';
                }, 150);
            }
        }
        
        // Auto-refresh page every 60 seconds to show updated progress
        setTimeout(function() {
            location.reload();
        }, 60000);
        
        // Add loading animation to progress bar
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.querySelector('.pizza-progress-bar');
            if (progressBar) {
                const currentWidth = progressBar.style.width;
                progressBar.style.width = '0%';
                setTimeout(() => {
                    progressBar.style.width = currentWidth;
                }, 500);
            }
        });
    </script>
</body>
</html> 