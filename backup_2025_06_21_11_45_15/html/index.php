<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/functions.php';

include 'core/includes/header.php';
?>

<style>
/* Million Dollar Landing Page */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --accent-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --gold-gradient: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    --dark-gradient: linear-gradient(135deg, #232526 0%, #414345 100%);
    --glass-bg: rgba(255, 255, 255, 0.1);
    --glass-border: rgba(255, 255, 255, 0.2);
    --text-glow: 0 0 20px rgba(255, 255, 255, 0.3);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
    background-attachment: fixed;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: #ffffff;
    overflow-x: hidden;
    line-height: 1.6;
}

/* Floating Background Elements */
.floating-bg {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: -1;
    overflow: hidden;
}

.floating-element {
    position: absolute;
    opacity: 0.1;
    animation: float 20s infinite linear;
}

.floating-element:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; }
.floating-element:nth-child(2) { top: 20%; right: 15%; animation-delay: 7s; }
.floating-element:nth-child(3) { bottom: 30%; left: 20%; animation-delay: 14s; }
.floating-element:nth-child(4) { top: 60%; right: 25%; animation-delay: 3s; }
.floating-element:nth-child(5) { bottom: 15%; right: 10%; animation-delay: 10s; }

@keyframes float {
    0% { transform: translateY(0px) rotate(0deg); opacity: 0.1; }
    50% { transform: translateY(-20px) rotate(180deg); opacity: 0.3; }
    100% { transform: translateY(0px) rotate(360deg); opacity: 0.1; }
}

/* Hero Section - Million Dollar Design */
.hero-section {
    min-height: 100vh;
    display: flex;
    align-items: center;
    position: relative;
    background: radial-gradient(circle at 30% 70%, rgba(102, 126, 234, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 70% 30%, rgba(240, 147, 251, 0.2) 0%, transparent 50%);
    overflow: hidden;
}

.hero-content {
    position: relative;
    z-index: 2;
}

.hero-badge {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 50px;
    padding: 0.5rem 1.5rem;
    margin-bottom: 2rem;
    display: inline-block;
    font-size: 0.9rem;
    font-weight: 600;
    color: #00f2fe;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 20px rgba(0, 242, 254, 0.3); }
    50% { box-shadow: 0 0 30px rgba(0, 242, 254, 0.5); }
}

.hero-title {
    font-size: 4.5rem;
    font-weight: 900;
    line-height: 1.1;
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, #ffffff 0%, #00f2fe 50%, #ffd700 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: var(--text-glow);
    animation: titleGlow 3s ease-in-out infinite alternate;
}

@keyframes titleGlow {
    0% { filter: brightness(1); }
    100% { filter: brightness(1.2); }
}

.hero-subtitle {
    font-size: 1.4rem;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 2.5rem;
    line-height: 1.7;
    max-width: 600px;
    font-weight: 300;
}

.cta-container {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.cta-primary {
    background: var(--primary-gradient);
    border: none;
    padding: 1.2rem 2.5rem;
    border-radius: 50px;
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    position: relative;
    overflow: hidden;
}

.cta-primary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.cta-primary:hover::before {
    left: 100%;
}

.cta-primary:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 20px 60px rgba(102, 126, 234, 0.5);
    color: white;
    text-decoration: none;
}

.cta-secondary {
    border: 2px solid var(--glass-border);
    padding: 1.2rem 2.5rem;
    border-radius: 50px;
    color: #ffffff;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
}

.cta-secondary:hover {
    border-color: #00f2fe;
    color: #00f2fe;
    text-decoration: none;
    box-shadow: 0 10px 30px rgba(0, 242, 254, 0.3);
    transform: translateY(-2px);
}

.hero-proof {
    display: flex;
    align-items: center;
    gap: 1rem;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.avatar-stack {
    display: flex;
    margin-left: -10px;
}

.avatar-stack img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 3px solid #ffffff;
    margin-left: -10px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.avatar-stack img:hover {
    transform: scale(1.2) translateY(-5px);
    z-index: 10;
}

.hero-visual {
    position: relative;
    perspective: 1000px;
}

.dashboard-mockup {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 1rem;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    transform: rotateY(-5deg) rotateX(5deg);
    transition: transform 0.3s ease;
    animation: float-gentle 6s ease-in-out infinite;
}

.dashboard-mockup:hover {
    transform: rotateY(0deg) rotateX(0deg) scale(1.02);
}

@keyframes float-gentle {
    0%, 100% { transform: rotateY(-5deg) rotateX(5deg) translateY(0px); }
    50% { transform: rotateY(-5deg) rotateX(5deg) translateY(-10px); }
}

.dashboard-mockup img {
    width: 100%;
    border-radius: 15px;
    display: block;
}

/* Trust Indicators */
.trust-section {
    padding: 3rem 0;
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
}

.trust-logos {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 3rem;
    flex-wrap: wrap;
    opacity: 0.7;
    filter: grayscale(100%);
    transition: all 0.3s ease;
}

.trust-logos:hover {
    opacity: 1;
    filter: grayscale(0%);
}

.trust-logo {
    height: 40px;
    transition: transform 0.3s ease;
}

.trust-logo:hover {
    transform: scale(1.1);
}

/* Explosive Stats Section */
.stats-section {
    padding: 6rem 0;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(240, 147, 251, 0.1) 100%);
    position: relative;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-top: 3rem;
}

.stat-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2.5rem 2rem;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--success-gradient);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.stat-card:hover::before {
    transform: scaleX(1);
}

.stat-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 25px 80px rgba(0, 242, 254, 0.3);
}

.stat-number {
    font-size: 3.5rem;
    font-weight: 900;
    background: var(--gold-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.5rem;
    animation: countUp 2s ease-out;
}

@keyframes countUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.stat-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1.1rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.stat-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
}

/* Features Showcase */
.features-section {
    padding: 6rem 0;
    position: relative;
}

.feature-showcase {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 3rem;
    margin-top: 4rem;
}

.feature-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 25px;
    padding: 3rem;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
    text-align: center;
}

.feature-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(240, 147, 251, 0.1));
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.feature-card:hover::after {
    opacity: 1;
}

.feature-card:hover {
    transform: translateY(-15px) scale(1.02);
    box-shadow: 0 30px 100px rgba(0, 0, 0, 0.4);
    border-color: rgba(0, 242, 254, 0.5);
}

.feature-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    background: var(--success-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    position: relative;
    z-index: 1;
}

.feature-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #ffffff;
    position: relative;
    z-index: 1;
}

.feature-description {
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.7;
    font-size: 1rem;
    position: relative;
    z-index: 1;
}

/* Platform Showcase Section */
.social-proof-section {
    padding: 6rem 0;
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
}

.screenshot-showcase-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 3rem;
    margin-top: 3rem;
}

.screenshot-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.4s ease;
}

.screenshot-card:hover {
    transform: translateY(-10px) scale(1.02);
    border-color: rgba(0, 242, 254, 0.5);
    box-shadow: 0 25px 80px rgba(0, 242, 254, 0.3);
}

.screenshot-image {
    position: relative;
    overflow: hidden;
}

.screenshot-image img {
    width: 100%;
    height: 250px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.screenshot-card:hover .screenshot-image img {
    transform: scale(1.05);
}

.screenshot-info {
    padding: 2rem;
}

.screenshot-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 1rem;
}

.screenshot-description {
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.6;
    font-size: 1rem;
}

/* Avatar Gallery */
.avatar-gallery-section {
    margin-top: 4rem;
    text-align: center;
}

.avatar-gallery {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 2rem;
}

.avatar-item {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--glass-border);
    box-shadow: 0 10px 30px rgba(0, 242, 254, 0.3);
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.avatar-item:hover {
    transform: scale(1.2) translateY(-10px);
    border-color: #00f2fe;
    box-shadow: 0 15px 40px rgba(0, 242, 254, 0.5);
    z-index: 10;
}

.avatar-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-item[data-tooltip]:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: -40px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--glass-bg);
    color: #fff;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
    white-space: nowrap;
    z-index: 20;
}

/* Why Choose Section */
.why-choose-section {
    padding: 6rem 0;
    background: linear-gradient(135deg, rgba(0, 0, 0, 0.4) 0%, rgba(16, 21, 46, 0.6) 100%);
    position: relative;
}

.why-choose-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 2rem;
    margin-top: 3rem;
}

.reason-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2.5rem;
    text-align: center;
    transition: all 0.4s ease;
    position: relative;
    overflow: hidden;
}

.reason-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--success-gradient);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.reason-card:hover::before {
    transform: scaleX(1);
}

.reason-card:hover {
    transform: translateY(-10px);
    border-color: rgba(0, 242, 254, 0.5);
    box-shadow: 0 20px 60px rgba(0, 242, 254, 0.3);
}

.reason-icon {
    font-size: 3rem;
    margin-bottom: 1.5rem;
    display: block;
}

.reason-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 1rem;
}

.reason-description {
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.7;
    font-size: 1rem;
}

.urgency-banner {
    margin-top: 4rem;
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.15) 0%, rgba(255, 193, 7, 0.1) 100%);
    border: 2px solid rgba(255, 215, 0, 0.3);
    border-radius: 20px;
    padding: 3rem 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.urgency-banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 215, 0, 0.1), transparent);
    animation: urgencyShine 3s infinite;
}

@keyframes urgencyShine {
    0% { left: -100%; }
    100% { left: 100%; }
}

.urgency-title {
    font-size: 2rem;
    font-weight: 800;
    color: #ffd700;
    margin-bottom: 1rem;
}

.urgency-text {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.urgency-counter {
    font-size: 1.3rem;
    font-weight: 700;
    color: #ff6b6b;
}

.counter-number {
    font-size: 2rem;
    color: #ffd700;
    animation: counterPulse 2s infinite;
}

@keyframes counterPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* Final CTA Section */
.final-cta-section {
    padding: 6rem 0;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(240, 147, 251, 0.2) 100%);
    text-align: center;
    position: relative;
}

.cta-content {
    max-width: 600px;
    margin: 0 auto;
}

.cta-title {
    font-size: 3rem;
    font-weight: 900;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, #ffffff 0%, #00f2fe 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.cta-subtitle {
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 3rem;
    line-height: 1.6;
}

.mega-cta {
    background: var(--success-gradient);
    border: none;
    padding: 1.5rem 3rem;
    border-radius: 50px;
    color: white;
    font-weight: 800;
    font-size: 1.3rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 15px 50px rgba(0, 242, 254, 0.4);
    position: relative;
    overflow: hidden;
    animation: megaPulse 3s ease-in-out infinite;
}

@keyframes megaPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 15px 50px rgba(0, 242, 254, 0.4); }
    50% { transform: scale(1.05); box-shadow: 0 20px 70px rgba(0, 242, 254, 0.6); }
}

.mega-cta:hover {
    transform: translateY(-5px) scale(1.1);
    box-shadow: 0 25px 80px rgba(0, 242, 254, 0.6);
    color: white;
    text-decoration: none;
}

/* Section Headers */
.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-title {
    font-size: 3rem;
    font-weight: 900;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, #ffffff 0%, #00f2fe 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.section-subtitle {
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.8);
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-title {
        font-size: 3rem;
    }
    
    .hero-subtitle {
        font-size: 1.2rem;
    }
    
    .cta-container {
        flex-direction: column;
        align-items: stretch;
    }
    
    .feature-showcase {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .testimonials-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Scroll Animations */
.fade-in {
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.8s ease;
}

.fade-in.visible {
    opacity: 1;
    transform: translateY(0);
}

.slide-in-left {
    opacity: 0;
    transform: translateX(-50px);
    transition: all 0.8s ease;
}

.slide-in-left.visible {
    opacity: 1;
    transform: translateX(0);
}

.slide-in-right {
    opacity: 0;
    transform: translateX(50px);
    transition: all 0.8s ease;
}

.slide-in-right.visible {
    opacity: 1;
    transform: translateX(0);
}
</style>

<!-- Floating Background Elements -->
<div class="floating-bg">
    <div class="floating-element">💰</div>
    <div class="floating-element">🎯</div>
    <div class="floating-element">🚀</div>
    <div class="floating-element">💎</div>
    <div class="floating-element">⚡</div>
</div>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6">
                <div class="hero-content">
                    <div class="hero-badge">
                        🆕 Revolutionary New Platform - Early Access Available Now!
                    </div>
                    
                    <h1 class="hero-title">
                        Turn Every Vending Machine Into a
                        <span style="background: var(--gold-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            Revenue Goldmine
                        </span>
                    </h1>
                    
                    <p class="hero-subtitle">
                        The world's most advanced vending platform that transforms ordinary machines into engaging, 
                        profit-generating experiences with AI analytics, gamification, and workplace team rewards. 
                        <strong>Guaranteed 40%+ revenue increase or your money back.</strong>
                    </p>
                    
                    <div class="cta-container">
                        <a href="/business/register.php" class="cta-primary">
                            <i class="bi bi-rocket-takeoff"></i>
                            Start Making Money Today
                        </a>
                        <a href="/user/dashboard.php" class="cta-secondary">
                            <i class="bi bi-play-circle"></i>
                            Watch Live Demo
                        </a>
                    </div>
                    
                    <div class="hero-proof">
                        <div class="avatar-stack">
                            <img src="/assets/img/avatars/qrCoin.png" alt="QR Coin">
                            <img src="/assets/img/avatars/avatar1.png" alt="Beta Tester">
                            <img src="/assets/img/avatars/avatar2.png" alt="Beta Tester">
                            <img src="/assets/img/avatars/avatar3.png" alt="Beta Tester">
                            <img src="/assets/img/avatars/avatar4.png" alt="Beta Tester">
                            <img src="/assets/img/avatars/avatar5.png" alt="Beta Tester">
                        </div>
                        <div>
                            <strong>🔥 Limited Beta Access</strong> - Be among the first to transform your vending business
                            <br><span style="color: #00f2fe;">💎 Early adopters get lifetime discounts</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="hero-visual fade-in">
                    <div class="dashboard-mockup">
                        <img src="/assets/img/screenshots/user dashscreenshot.png" alt="RevenueQR Dashboard - Live Analytics">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trust Indicators -->
<section class="trust-section">
    <div class="container">
        <div class="text-center mb-3">
            <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem;">
                Trusted by industry leaders and powered by enterprise technology
            </p>
        </div>
        <div class="trust-logos">
            <img src="/assets/img/logos/nayax-logo.png" alt="NAYAX" class="trust-logo">
            <img src="/assets/img/logos/bootstrap-logo.png" alt="Bootstrap" class="trust-logo">
            <img src="/assets/img/logos/mysql-logo.png" alt="MySQL" class="trust-logo">
            <img src="/assets/img/logos/php-logo.png" alt="PHP" class="trust-logo">
        </div>
    </div>
</section>

<!-- Explosive Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="section-header fade-in">
            <h2 class="section-title">Revolutionary Platform Capabilities</h2>
            <p class="section-subtitle">
                Cutting-edge features that will transform your vending business from day one
            </p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card fade-in">
                <div class="stat-number">100%</div>
                <div class="stat-label">Engagement Boost</div>
                <div class="stat-subtitle">Interactive QR experiences</div>
            </div>
            
            <div class="stat-card fade-in">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Real-Time Analytics</div>
                <div class="stat-subtitle">Monitor your empire anywhere</div>
            </div>
            
            <div class="stat-card fade-in">
                <div class="stat-number">5min</div>
                <div class="stat-label">Setup Time</div>
                <div class="stat-subtitle">From zero to revenue-ready</div>
            </div>
            
            <div class="stat-card fade-in">
                <div class="stat-number">∞</div>
                <div class="stat-label">Scalability</div>
                <div class="stat-subtitle">Unlimited machines & locations</div>
            </div>
        </div>
    </div>
</section>

<!-- Features Showcase -->
<section class="features-section">
    <div class="container">
        <div class="section-header fade-in">
            <h2 class="section-title">The Complete Revenue Revolution</h2>
            <p class="section-subtitle">
                Six game-changing features that transform your vending business overnight
            </p>
        </div>
        
        <div class="feature-showcase">
            <div class="feature-card slide-in-left">
                <div class="feature-icon">🤖</div>
                <h3 class="feature-title">AI-Powered Analytics</h3>
                <p class="feature-description">
                    Get crystal-clear insights into customer behavior, inventory optimization, and revenue forecasting. 
                    Our AI learns your business patterns and automatically suggests profit-maximizing actions.
                </p>
            </div>
            
            <div class="feature-card slide-in-right">
                <div class="feature-icon">💰</div>
                <h3 class="feature-title">QR Coin Economy</h3>
                <p class="feature-description">
                    Create customer loyalty that keeps them coming back. Our digital currency system increases 
                    repeat purchases by 300% and builds a community around your machines.
                </p>
            </div>
            
            <div class="feature-card slide-in-left">
                <div class="feature-icon">🍕</div>
                <h3 class="feature-title">Team Pizza Rewards</h3>
                <p class="feature-description">
                    Transform workplaces with collective rewards that boost morale. When teams hit engagement goals, 
                    they earn pizza dinners - perfect for factories, offices, and schools.
                </p>
            </div>
            
            <div class="feature-card slide-in-right">
                <div class="feature-icon">🎮</div>
                <h3 class="feature-title">Gamification Engine</h3>
                <p class="feature-description">
                    Turn every purchase into an engaging experience with spin wheels, voting systems, loot boxes, 
                    and achievement rewards that customers absolutely love.
                </p>
            </div>
            
            <div class="feature-card slide-in-left">
                <div class="feature-icon">📱</div>
                <h3 class="feature-title">NAYAX Integration</h3>
                <p class="feature-description">
                    Seamlessly connect with industry-leading vending hardware. Real-time machine connectivity, 
                    mobile payments, and instant discount delivery.
                </p>
            </div>
            
            <div class="feature-card slide-in-right">
                <div class="feature-icon">📊</div>
                <h3 class="feature-title">Live Dashboard</h3>
                <p class="feature-description">
                    Monitor your entire empire from anywhere. Real-time sales, customer engagement, machine 
                    performance, and profit analytics at your fingertips.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Platform Showcase Section -->
<section class="social-proof-section">
    <div class="container">
        <div class="section-header fade-in">
            <h2 class="section-title">See The Platform In Action</h2>
            <p class="section-subtitle">
                Experience the intuitive interfaces and powerful features that make RevenueQR revolutionary
            </p>
        </div>
        
        <div class="screenshot-showcase-grid">
            <div class="screenshot-card fade-in">
                <div class="screenshot-image">
                    <img src="/assets/img/screenshots/user dashscreenshot.png" alt="User Dashboard Screenshot">
                </div>
                <div class="screenshot-info">
                    <h4 class="screenshot-title">Interactive User Dashboard</h4>
                    <p class="screenshot-description">
                        Gamified experience with QR coin balance, spinning wheels, achievements, and instant rewards that keep customers engaged.
                    </p>
                </div>
            </div>
            
            <div class="screenshot-card fade-in">
                <div class="screenshot-image">
                    <img src="/assets/img/screenshots/business-analytics.png" alt="Business Analytics Screenshot">
                </div>
                <div class="screenshot-info">
                    <h4 class="screenshot-title">Real-Time Business Analytics</h4>
                    <p class="screenshot-description">
                        AI-powered insights showing customer behavior, inventory optimization, and revenue forecasting with beautiful visualizations.
                    </p>
                </div>
            </div>
            
            <div class="screenshot-card fade-in">
                <div class="screenshot-image">
                    <img src="/assets/img/screenshots/qr-generator.png" alt="QR Generator Screenshot">
                </div>
                <div class="screenshot-info">
                    <h4 class="screenshot-title">Smart QR Code Generator</h4>
                    <p class="screenshot-description">
                        Create customized QR codes for any machine or campaign in seconds with advanced tracking and analytics built-in.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Avatar Gallery -->
        <div class="avatar-gallery-section fade-in">
            <h4 class="text-center mb-4" style="color: #00f2fe;">👥 Built for Everyone</h4>
            <div class="avatar-gallery">
                <div class="avatar-item" data-tooltip="Factory Workers">
                    <img src="/assets/img/avatars/avatar1.png" alt="Factory Worker">
                </div>
                <div class="avatar-item" data-tooltip="Office Teams">
                    <img src="/assets/img/avatars/avatar2.png" alt="Office Worker">
                </div>
                <div class="avatar-item" data-tooltip="Students">
                    <img src="/assets/img/avatars/avatar3.png" alt="Student">
                </div>
                <div class="avatar-item" data-tooltip="Business Owners">
                    <img src="/assets/img/avatars/avatar4.png" alt="Business Owner">
                </div>
                <div class="avatar-item" data-tooltip="Vending Operators">
                    <img src="/assets/img/avatars/avatar5.png" alt="Vending Operator">
                </div>
                <div class="avatar-item" data-tooltip="QR Coins">
                    <img src="/img/qrCoin.png" alt="QR Coin">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose RevenueQR Section -->
<section class="why-choose-section">
    <div class="container">
        <div class="section-header fade-in">
            <h2 class="section-title">Why Smart Business Owners Choose RevenueQR</h2>
            <p class="section-subtitle">
                Don't just take our word for it - here's why this platform is revolutionizing the vending industry
            </p>
        </div>
        
        <div class="why-choose-grid">
            <div class="reason-card fade-in">
                <div class="reason-icon">🚀</div>
                <h4 class="reason-title">First-Mover Advantage</h4>
                <p class="reason-description">
                    Be among the first to leverage this revolutionary technology. Early adopters dominate their markets 
                    while competitors are still using outdated systems.
                </p>
            </div>
            
            <div class="reason-card fade-in">
                <div class="reason-icon">💎</div>
                <h4 class="reason-title">Built by Industry Experts</h4>
                <p class="reason-description">
                    Created by former blue-collar workers who understand real workplace needs. Every feature is designed 
                    from actual experience, not boardroom theories.
                </p>
            </div>
            
            <div class="reason-card fade-in">
                <div class="reason-icon">🛡️</div>
                <h4 class="reason-title">Zero Risk Investment</h4>
                <p class="reason-description">
                    30-day money-back guarantee, no setup fees, no long-term contracts. If you don't see results, 
                    you don't pay. Period.
                </p>
            </div>
            
            <div class="reason-card fade-in">
                <div class="reason-icon">⚡</div>
                <h4 class="reason-title">Instant ROI</h4>
                <p class="reason-description">
                    Most customers see increased engagement within 24 hours and revenue growth within the first week. 
                    This isn't a long-term investment - it's immediate profit.
                </p>
            </div>
            
            <div class="reason-card fade-in">
                <div class="reason-icon">🎯</div>
                <h4 class="reason-title">Proven Psychology</h4>
                <p class="reason-description">
                    Our gamification and reward systems tap into fundamental human psychology. People can't resist 
                    the dopamine hit of earning rewards and competing with friends.
                </p>
            </div>
            
            <div class="reason-card fade-in">
                <div class="reason-icon">🔮</div>
                <h4 class="reason-title">Future-Proof Technology</h4>
                <p class="reason-description">
                    Built on modern web standards with AI integration, mobile-first design, and scalable architecture. 
                    This platform grows with your business for years to come.
                </p>
            </div>
        </div>
        
        <div class="urgency-banner fade-in">
            <div class="urgency-content">
                <h3 class="urgency-title">⏰ Limited Time: Early Access Pricing</h3>
                <p class="urgency-text">
                    Lock in your lifetime discount as a founding member. This introductory pricing won't last long - 
                    once we hit 100 businesses, prices go up permanently.
                </p>
                <div class="urgency-counter">
                    <span class="counter-number">23</span> / 100 spots remaining
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Final CTA Section -->
<section class="final-cta-section">
    <div class="container">
        <div class="cta-content fade-in">
            <h2 class="cta-title">Claim Your Founding Member Spot</h2>
            <p class="cta-subtitle">
                Join the revolution as an early adopter and lock in lifetime discounts. Only 77 spots remaining 
                before we launch publicly and prices increase forever. 
                <strong>Don't let your competitors get ahead.</strong>
            </p>
            
            <a href="/business/register.php" class="mega-cta">
                <i class="bi bi-lightning-charge-fill"></i>
                Secure My Early Access Now
                <i class="bi bi-arrow-right"></i>
            </a>
            
            <div style="margin-top: 2rem; color: rgba(255, 255, 255, 0.8);">
                🚀 Instant setup • 💎 Lifetime discount • 🛡️ 30-day guarantee • 🎯 Be first to market
            </div>
            
            <div style="margin-top: 1rem; color: #ff6b6b; font-weight: 600;">
                ⚠️ Warning: Once public launch happens, early access pricing ends forever
            </div>
        </div>
    </div>
</section>

<script>
// Intersection Observer for scroll animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, observerOptions);

// Observe all animated elements
document.querySelectorAll('.fade-in, .slide-in-left, .slide-in-right').forEach(el => {
    observer.observe(el);
});

// Dynamic counter animation for stats
function animateCounter(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        element.innerHTML = Math.floor(progress * (end - start) + start);
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// Trigger counter animations when stats section is visible
const statsSection = document.querySelector('.stats-section');
let statsAnimated = false;

const statsObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && !statsAnimated) {
            statsAnimated = true;
            // Animate each stat number
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach((stat, index) => {
                const targetText = stat.textContent;
                let targetNumber = parseInt(targetText.replace(/[^0-9]/g, ''));
                if (targetText.includes('$')) {
                    animateCounter(stat, 0, targetNumber, 2000);
                    setTimeout(() => {
                        stat.innerHTML = targetText; // Restore original formatting
                    }, 2000);
                } else if (targetText.includes('%')) {
                    animateCounter(stat, 0, targetNumber, 2000);
                    setTimeout(() => {
                        stat.innerHTML = targetText; // Restore original formatting
                    }, 2000);
                } else {
                    animateCounter(stat, 0, targetNumber, 2000);
                    setTimeout(() => {
                        stat.innerHTML = targetText; // Restore original formatting
                    }, 2000);
                }
            });
        }
    });
});

if (statsSection) {
    statsObserver.observe(statsSection);
}

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Add parallax effect to floating elements
window.addEventListener('scroll', () => {
    const scrolled = window.pageYOffset;
    const parallaxElements = document.querySelectorAll('.floating-element');
    
    parallaxElements.forEach((element, index) => {
        const speed = 0.5 + (index * 0.1);
        element.style.transform = `translateY(${scrolled * speed}px)`;
    });
});
</script>

<?php require_once __DIR__ . '/core/includes/footer.php'; ?> 