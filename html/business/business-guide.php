<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

// Require business role
require_role('business');

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
/* Fix accordion styling for dark theme */
.accordion-item {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    margin-bottom: 1rem !important;
}
.accordion-button {
    background: rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 16px !important;
    font-weight: 600 !important;
}
.accordion-button:not(.collapsed) {
    background: rgba(255, 255, 255, 0.15) !important;
    color: #ffffff !important;
    box-shadow: none !important;
}
.accordion-button:focus {
    border-color: rgba(255, 255, 255, 0.25) !important;
    box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.1) !important;
}
.accordion-button::after {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e") !important;
}
.accordion-collapse {
    border: none !important;
}
.accordion-body {
    background: rgba(255, 255, 255, 0.05) !important;
    color: #ffffff !important;
    border-radius: 0 0 16px 16px !important;
    padding: 1.5rem !important;
}
/* Business-specific styling */
.feature-card {
    background: rgba(255,255,255,0.1) !important;
    backdrop-filter: blur(15px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border-left: 4px solid #28a745;
    color: #ffffff !important;
}
.quick-tip {
    background: rgba(40, 167, 69, 0.2) !important;
    border: 1px solid rgba(40, 167, 69, 0.3) !important;
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
    color: #ffffff !important;
}
.warning-tip {
    background: rgba(255, 193, 7, 0.2) !important;
    border: 1px solid rgba(255, 193, 7, 0.3) !important;
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
    color: #ffffff !important;
}
.revenue-highlight {
    background: rgba(40, 167, 69, 0.15) !important;
    border: 2px solid #28a745 !important;
    border-radius: 10px;
    padding: 1.5rem;
    margin: 1rem 0;
    color: #ffffff !important;
}
/* Fix all text colors */
h1, h2, h3, h4, h5, h6 {
    color: #ffffff !important;
}
p, li, span, div {
    color: rgba(255, 255, 255, 0.9) !important;
}
.text-primary {
    color: #64b5f6 !important;
}
.text-success {
    color: #4caf50 !important;
}
.text-warning {
    color: #ffc107 !important;
}
.text-info {
    color: #17a2b8 !important;
}
.btn {
    border-radius: 8px !important;
    font-weight: 500 !important;
}
/* Fix header section */
.guide-section {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    color: #ffffff !important;
}
/* Fix header section */
.guide-section {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    color: #ffffff !important;
}
</style>

<div class="container py-4">
    <!-- Header Section -->
    <div class="row mb-5">
        <div class="col-12 text-center">
            <div class="card">
                <div class="card-body text-white p-5">
                    <h1 class="display-4 mb-3">
                        <i class="bi bi-building text-warning me-3"></i>
                        RevenueQR Business Guide
                    </h1>
                    <p class="lead mb-4">Transform your vending business with AI-powered analytics, NAYAX integration, and advanced customer engagement!</p>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-warning">$50K+</h3>
                                <small class="opacity-75">Annual Revenue Potential</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-warning">40%+</h3>
                                <small class="opacity-75">Revenue Increase</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-warning">300%+</h3>
                                <small class="opacity-75">Engagement Increase</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-warning">24/7</h3>
                                <small class="opacity-75">AI Analytics</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- New Features Highlight -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="alert alert-warning">
                                <h5><i class="bi bi-star-fill me-2"></i>🆕 Latest Platform Updates</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>New Business Features:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>🎯 Promotional Ads Manager - Target users with customized ads</li>
                                            <li>🏇 Horse Racing Management - Custom jockey assignments</li>
                                            <li>🤖 AI Business Assistant - GPT-powered insights</li>
                                            <li>📊 Enhanced Analytics - Real-time performance tracking</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Customer Engagement:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>⚡ Quick Races - 6 daily 1-minute horse races</li>
                                            <li>🏆 Weekly Winners - Automated result tracking</li>
                                            <li>💰 Savings Dashboard - Real CAD savings display</li>
                                            <li>🎭 Posty Avatar - 5% loss cashback system</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Technical Improvements:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>📱 Progressive Web App (PWA)</li>
                                            <li>🔄 Real-time WebSocket integration</li>
                                            <li>📈 Advanced caching & optimization</li>
                                            <li>🔒 Enhanced security & monitoring</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion" id="businessGuideAccordion">
        <!-- Business Overview -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                    <i class="bi bi-briefcase me-2"></i>
                    RevenueQR Business Overview
                </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse show">
                <div class="accordion-body">
                    <div class="revenue-highlight">
                        <h5><i class="bi bi-graph-up-arrow text-success me-2"></i>What is RevenueQR for Business?</h5>
                        <p class="mb-3">RevenueQR transforms traditional vending machines into interactive, gamified experiences that increase customer engagement by 300%+ and drive significant revenue growth through AI-powered analytics, NAYAX integration, and comprehensive business intelligence.</p>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6><i class="bi bi-trophy text-warning me-2"></i>Key Benefits:</h6>
                                <ul class="mb-0">
                                    <li>40%+ revenue increase through gamification</li>
                                    <li>AI-powered business insights & optimization</li>
                                    <li>NAYAX vending machine integration</li>
                                    <li>Real-time analytics & predictive modeling</li>
                                    <li>Progressive Web App for mobile management</li>
                                    <li>Advanced customer engagement tools</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="bi bi-cash-coin text-success me-2"></i>Revenue Streams:</h6>
                                <ul class="mb-0">
                                    <li>Enhanced vending machine sales</li>
                                    <li>Casino revenue sharing (10% automatic)</li>
                                    <li>QR coin economy participation</li>
                                    <li>Premium analytics & AI insights</li>
                                    <li>Customer retention & loyalty programs</li>
                                    <li>Data monetization opportunities</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="quick-tip">
                        <i class="bi bi-lightbulb text-warning me-2"></i>
                        <strong>Business Success Tip:</strong> Businesses using RevenueQR see average revenue increases of 40-60% within the first 6 months of implementation.
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Coin Economy -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingTwo">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                    <i class="bi bi-coin me-2"></i>
                    QR Coin Economy & Business Wallet
                </button>
            </h2>
            <div id="collapseTwo" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6><i class="bi bi-wallet2 text-warning me-2"></i>Business QR Wallet</h6>
                                <ul>
                                    <li><strong>Earn QR Coins:</strong> Revenue from customer activities</li>
                                    <li><strong>Track Spending:</strong> Investment in customer rewards</li>
                                    <li><strong>Real-time Balance:</strong> Monitor coin flow and profits</li>
                                    <li><strong>Transaction History:</strong> Complete financial tracking</li>
                                </ul>
                                <a href="wallet.php" class="btn btn-warning btn-sm mt-2">
                                    <i class="bi bi-wallet me-1"></i>View Your Wallet
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6><i class="bi bi-arrow-repeat text-success me-2"></i>Coin Flow Mechanics</h6>
                                <ul>
                                    <li><strong>Customer Votes:</strong> You receive coins per engagement</li>
                                    <li><strong>Spin Rewards:</strong> Share in the prize economy</li>
                                    <li><strong>Store Purchases:</strong> Customers spend coins for discounts</li>
                                    <li><strong>Revenue Sharing:</strong> Percentage-based earnings model</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="revenue-highlight">
                        <h6><i class="bi bi-calculator text-success me-2"></i>Revenue Example Calculation:</h6>
                        <p><strong>Daily Activity:</strong> 100 customer votes × 2 QR coins/vote = 200 coins earned<br>
                        <strong>Conversion Rate:</strong> $0.10 per coin = $20/day<br>
                        <strong>Monthly Revenue:</strong> $20 × 30 days = $600/month<br>
                        <strong>Annual Potential:</strong> $7,200+ (not including sales increases)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics & Insights -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingThree">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                    <i class="bi bi-graph-up me-2"></i>
                    Analytics & Business Intelligence
                </button>
            </h2>
            <div id="collapseThree" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6><i class="bi bi-speedometer2 text-info me-2"></i>Dashboard Analytics</h6>
                                <ul>
                                    <li><strong>Real-time Metrics:</strong> Live customer engagement data</li>
                                    <li><strong>Sales Trends:</strong> Track performance over time</li>
                                    <li><strong>Customer Insights:</strong> Understand user behavior</li>
                                    <li><strong>Inventory Analytics:</strong> Optimize stock management</li>
                                    <li><strong>Revenue Tracking:</strong> Monitor all income streams</li>
                                </ul>
                                <a href="dashboard_enhanced.php" class="btn btn-info btn-sm mt-2">
                                    <i class="bi bi-speedometer2 me-1"></i>View Dashboard
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6><i class="bi bi-robot text-primary me-2"></i>AI-Powered Insights</h6>
                                <ul>
                                    <li><strong>Predictive Analytics:</strong> Forecast trends and demand</li>
                                    <li><strong>Smart Recommendations:</strong> AI suggests inventory changes</li>
                                    <li><strong>Performance Optimization:</strong> Automated improvement suggestions</li>
                                    <li><strong>Market Analysis:</strong> Compare with industry benchmarks</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="quick-tip">
                        <i class="bi bi-bar-chart text-info me-2"></i>
                        <strong>Analytics Pro Tip:</strong> Use the voting insights to predict which items will be popular before stocking them, reducing waste by up to 40%.
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Discount System -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingFour">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour">
                    <i class="bi bi-percent me-2"></i>
                    Customer Discount & Store System
                </button>
            </h2>
            <div id="collapseFour" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6><i class="bi bi-shop text-warning me-2"></i>Business Store Setup</h6>
                                <ol>
                                    <li><strong>Create Store Items:</strong> Add your products/services</li>
                                    <li><strong>Set Discount Levels:</strong> Choose percentage savings</li>
                                    <li><strong>Define QR Coin Costs:</strong> Price your discounts</li>
                                    <li><strong>Track Redemptions:</strong> Monitor customer usage</li>
                                </ol>
                                <a href="store-management.php" class="btn btn-warning btn-sm mt-2">
                                    <i class="bi bi-gear me-1"></i>Manage Store
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6><i class="bi bi-qr-code text-success me-2"></i>Purchase Code System</h6>
                                <ul>
                                    <li><strong>Automatic Generation:</strong> Unique codes for each purchase</li>
                                    <li><strong>Easy Redemption:</strong> Customers show codes at checkout</li>
                                    <li><strong>Verification System:</strong> Validate codes instantly</li>
                                    <li><strong>Usage Tracking:</strong> Monitor redemption rates</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="revenue-highlight">
                        <h6><i class="bi bi-cash-stack text-success me-2"></i>Discount Strategy Example:</h6>
                        <p><strong>Coffee Shop Discount:</strong> 20% off any drink for 50 QR coins<br>
                        <strong>Customer Value:</strong> $1.00 savings on $5.00 purchase<br>
                        <strong>Your Cost:</strong> Discounted margin + increased customer loyalty<br>
                        <strong>Result:</strong> Higher customer retention and repeat visits</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Casino & Gaming Integration -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingFive">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive">
                    <i class="bi bi-dice-6 me-2"></i>
                    Gaming & Casino Features
                </button>
            </h2>
            <div id="collapseFive" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6><i class="bi bi-controller text-primary me-2"></i>Spin Wheel System</h6>
                                <ul>
                                    <li><strong>Customer Engagement:</strong> Daily spin rewards</li>
                                    <li><strong>Prize Management:</strong> Control reward distribution</li>
                                    <li><strong>Spin Pack Sales:</strong> Premium spin opportunities</li>
                                    <li><strong>Revenue Generation:</strong> Coins from spin activities</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6><i class="bi bi-dice-5 text-warning me-2"></i>Casino Management</h6>
                                <ul>
                                    <li><strong>Slot Machine Integration:</strong> Virtual gaming experiences</li>
                                    <li><strong>Prize Pool Management:</strong> Control reward economics</li>
                                    <li><strong>Player Statistics:</strong> Track gaming engagement</li>
                                    <li><strong>Revenue Sharing:</strong> Participate in gaming profits</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="warning-tip">
                        <i class="bi bi-shield-check text-warning me-2"></i>
                        <strong>Compliance Note:</strong> All gaming features comply with local regulations and are designed for engagement, not gambling.
                    </div>
                </div>
            </div>
        </div>

        <!-- Implementation & Setup -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingSix">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix">
                    <i class="bi bi-gear me-2"></i>
                    Implementation & Setup Guide
                </button>
            </h2>
            <div id="collapseSix" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6><i class="bi bi-list-ol text-success me-2"></i>Quick Setup Steps</h6>
                                <ol>
                                    <li><strong>Business Registration:</strong> Complete your business profile</li>
                                    <li><strong>QR Code Integration:</strong> Install codes on vending machines</li>
                                    <li><strong>Inventory Setup:</strong> Add your products to the system</li>
                                    <li><strong>Store Configuration:</strong> Create discount offerings</li>
                                    <li><strong>Analytics Review:</strong> Monitor initial performance</li>
                                </ol>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6><i class="bi bi-tools text-info me-2"></i>Technical Requirements</h6>
                                <ul>
                                    <li><strong>QR Code Printing:</strong> Standard 4×4 inch codes</li>
                                    <li><strong>Internet Access:</strong> For real-time data sync</li>
                                    <li><strong>Mobile Compatibility:</strong> Customer smartphone scanning</li>
                                    <li><strong>POS Integration:</strong> Optional for advanced features</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="revenue-highlight">
                        <h6><i class="bi bi-clock text-success me-2"></i>Implementation Timeline:</h6>
                        <p><strong>Day 1:</strong> QR codes installed and system activated<br>
                        <strong>Week 1:</strong> Customer engagement begins, initial data collection<br>
                        <strong>Month 1:</strong> First analytics insights and optimization recommendations<br>
                        <strong>Month 3:</strong> Full system optimization and maximum revenue potential</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROI & Performance Metrics -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingSeven">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeven">
                    <i class="bi bi-calculator me-2"></i>
                    ROI & Performance Metrics
                </button>
            </h2>
            <div id="collapseSeven" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-success bg-opacity-10 rounded">
                                <h3 class="text-success">40-60%</h3>
                                <h6>Revenue Increase</h6>
                                <small class="text-muted">Average within 6 months</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-info bg-opacity-10 rounded">
                                <h3 class="text-info">300%+</h3>
                                <h6>Engagement Boost</h6>
                                <small class="text-muted">Customer interaction increase</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 bg-warning bg-opacity-10 rounded">
                                <h3 class="text-warning">ROI 3:1</h3>
                                <h6>Return on Investment</h6>
                                <small class="text-muted">Typical first-year performance</small>
                            </div>
                        </div>
                    </div>

                    <div class="feature-card mt-4">
                        <h6><i class="bi bi-graph-up-arrow text-success me-2"></i>Key Performance Indicators (KPIs)</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <ul>
                                    <li><strong>Daily Active Users:</strong> Unique customers per day</li>
                                    <li><strong>Engagement Rate:</strong> Votes and spins per visit</li>
                                    <li><strong>Revenue Per User:</strong> Average customer value</li>
                                    <li><strong>Retention Rate:</strong> Repeat customer percentage</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul>
                                    <li><strong>QR Coin Velocity:</strong> Coin circulation speed</li>
                                    <li><strong>Discount Redemption:</strong> Store usage rates</li>
                                    <li><strong>Inventory Turnover:</strong> Stock movement optimization</li>
                                    <li><strong>Customer Satisfaction:</strong> Engagement quality metrics</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Latest Features & Updates -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingNew">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseNew">
                    <i class="bi bi-star-fill me-2"></i>
                    🆕 Latest Features & Advanced Tools
                </button>
            </h2>
            <div id="collapseNew" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="alert alert-warning">
                        <h5><i class="bi bi-rocket text-warning me-2"></i>Revolutionary New Features</h5>
                        <p>Discover the cutting-edge capabilities that set RevenueQR apart from traditional vending solutions!</p>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                                                         <div class="feature-card border border-warning">
                                 <h6><i class="bi bi-pizza text-danger me-2"></i>🍕 Pizza Tracker - Workplace Morale System</h6>
                                 <ul>
                                     <li><strong>Team Reward Program:</strong> Collective engagement earns workplace pizza dinners</li>
                                     <li><strong>Workplace Focus:</strong> Perfect for factories, offices, schools, and break rooms</li>
                                     <li><strong>Progress Tracking:</strong> Visual meter shows team progress toward pizza goal</li>
                                     <li><strong>Automatic Fulfillment:</strong> System handles pizza ordering and delivery</li>
                                     <li><strong>Morale Boost:</strong> Nothing builds team spirit like earned pizza celebrations</li>
                                 </ul>
                                 <small class="text-warning"><strong>Business Impact:</strong> 35% improvement in workplace morale and employee retention</small>
                             </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card border border-primary">
                                <h6><i class="bi bi-phone text-primary me-2"></i>📱 NAYAX Integration</h6>
                                <ul>
                                    <li><strong>Vending Machine Integration:</strong> Direct machine connectivity</li>
                                    <li><strong>QR Code Discounts:</strong> Instant discount application</li>
                                    <li><strong>Mobile Optimization:</strong> Seamless checkout experience</li>
                                    <li><strong>Real-time Validation:</strong> Fraud prevention and security</li>
                                    <li><strong>Usage Analytics:</strong> Complete transaction tracking</li>
                                </ul>
                                <small class="text-primary"><strong>Business Impact:</strong> 30% increase in vending machine revenue</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-4 mt-3">
                        <div class="col-md-6">
                            <div class="feature-card border border-success">
                                <h6><i class="bi bi-gift text-success me-2"></i>🎁 Fortnite-Style Loot Boxes</h6>
                                <ul>
                                    <li><strong>Three-Tier System:</strong> Common, Rare, Legendary boxes</li>
                                    <li><strong>Animated Opening:</strong> Engaging user experience</li>
                                    <li><strong>Weighted Rewards:</strong> Balanced economic system</li>
                                    <li><strong>Exclusive Items:</strong> Rare avatars and boosts</li>
                                    <li><strong>Revenue Generation:</strong> Premium purchase options</li>
                                </ul>
                                <small class="text-success"><strong>Business Impact:</strong> 20% increase in user engagement and retention</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card border border-info">
                                <h6><i class="bi bi-robot text-info me-2"></i>🤖 AI Business Assistant</h6>
                                <ul>
                                    <li><strong>GPT-Powered Insights:</strong> Smart business recommendations</li>
                                    <li><strong>Predictive Analytics:</strong> Revenue forecasting</li>
                                    <li><strong>24/7 Chat Support:</strong> Instant assistance</li>
                                    <li><strong>Performance Optimization:</strong> Automated suggestions</li>
                                    <li><strong>Custom Reports:</strong> AI-generated business intelligence</li>
                                </ul>
                                <small class="text-info"><strong>Business Impact:</strong> 35% improvement in operational efficiency</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-4 mt-3">
                        <div class="col-md-6">
                            <div class="feature-card border border-danger">
                                <h6><i class="bi bi-dice-6 text-danger me-2"></i>🎰 Enhanced Casino System</h6>
                                <ul>
                                    <li><strong>Casino Spin Packs:</strong> Purchasable additional spins</li>
                                    <li><strong>5-Tier Pack System:</strong> From daily boosts to VIP monthly</li>
                                    <li><strong>Revenue Sharing:</strong> 10% automatic business revenue</li>
                                    <li><strong>Smart Tracking:</strong> Usage and expiration monitoring</li>
                                    <li><strong>Zero Management:</strong> Fully automated operation</li>
                                </ul>
                                <small class="text-danger"><strong>Business Impact:</strong> Additional $200-500/month passive income per location</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card border border-warning">
                                <h6><i class="bi bi-qr-code text-warning me-2"></i>📊 Advanced QR Manager</h6>
                                <ul>
                                    <li><strong>Unified Management:</strong> All QR codes in one interface</li>
                                    <li><strong>Analytics Dashboard:</strong> Detailed usage statistics</li>
                                    <li><strong>Bulk Operations:</strong> Print, export, and manage at scale</li>
                                    <li><strong>Real-time Tracking:</strong> Live scan monitoring</li>
                                    <li><strong>Export Capabilities:</strong> CSV and bulk downloads</li>
                                </ul>
                                <small class="text-warning"><strong>Business Impact:</strong> 50% reduction in QR code management time</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="revenue-highlight mt-4">
                        <h6><i class="bi bi-graph-up-arrow text-success me-2"></i>Progressive Web App (PWA) Benefits</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul>
                                    <li><strong>Mobile-First Design:</strong> Optimized for smartphones and tablets</li>
                                    <li><strong>Offline Capabilities:</strong> Works without internet connection</li>
                                    <li><strong>Push Notifications:</strong> Real-time alerts and updates</li>
                                    <li><strong>Native App Experience:</strong> Install directly to home screen</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul>
                                    <li><strong>Fast Loading:</strong> Instant access to business tools</li>
                                    <li><strong>Secure:</strong> HTTPS and modern security protocols</li>
                                    <li><strong>Cross-Platform:</strong> Works on iOS, Android, and desktop</li>
                                    <li><strong>Auto-Updates:</strong> Always access the latest features</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="dashboard.php" class="btn btn-warning btn-lg">
                            <i class="bi bi-rocket me-2"></i>Explore New Features Now
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Support & Resources -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingEight">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEight">
                    <i class="bi bi-headset me-2"></i>
                    Support & Resources
                </button>
            </h2>
            <div id="collapseEight" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6><i class="bi bi-people text-primary me-2"></i>Business Support</h6>
                                <ul>
                                    <li><strong>Dedicated Account Manager:</strong> Personal business consultant</li>
                                    <li><strong>24/7 Technical Support:</strong> System monitoring and assistance</li>
                                    <li><strong>Training Programs:</strong> Staff education on the platform</li>
                                    <li><strong>Best Practices Guide:</strong> Optimization strategies</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6><i class="bi bi-book text-info me-2"></i>Resources & Documentation</h6>
                                <ul>
                                    <li><strong>Setup Guides:</strong> Step-by-step implementation</li>
                                    <li><strong>Video Tutorials:</strong> Visual learning resources</li>
                                    <li><strong>API Documentation:</strong> Advanced integration options</li>
                                    <li><strong>Case Studies:</strong> Success stories from other businesses</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <div class="feature-card">
                            <h5><i class="bi bi-telephone text-success me-2"></i>Get Started Today!</h5>
                            <p class="mb-3">Ready to transform your vending business? Our team is here to help you maximize your revenue potential.</p>
                            <div class="d-flex justify-content-center gap-3">
                                <button class="btn btn-success btn-sm">
                                    <i class="bi bi-phone me-1"></i>Schedule Consultation
                                </button>
                                <button class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-envelope me-1"></i>Email Support
                                </button>
                                <a href="dashboard_enhanced.php" class="btn btn-primary btn-sm">
                                    <i class="bi bi-speedometer2 me-1"></i>Go to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Promotional Features -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingPromotional">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePromotional">
                    <i class="bi bi-megaphone me-2"></i>
                    🎯 Promotional Ads Manager - NEW!
                </button>
            </h2>
            <div id="collapsePromotional" class="accordion-collapse collapse show">
                <div class="accordion-body">
                    <div class="revenue-highlight">
                        <h5>📈 Boost Revenue with Targeted Advertising</h5>
                        <p>Create and manage promotional ads that appear on user voting pages and dashboards. Drive traffic to your casino, spin wheels, pizza tracker, and other features!</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-bullseye text-warning me-2"></i>Promotional Ad Features</h6>
                            <ul>
                                <li><strong>Page Targeting:</strong> Show ads on vote pages, user dashboards</li>
                                <li><strong>Feature Integration:</strong> Casino, spin wheels, pizza tracker</li>
                                <li><strong>Daily View Limits:</strong> Control ad frequency and budget</li>
                                <li><strong>Priority Ranking:</strong> Higher priority = more visibility</li>
                                <li><strong>Analytics Tracking:</strong> View counts, click-through rates</li>
                            </ul>
                            
                            <div class="quick-tip">
                                <strong>💡 Pro Tip:</strong> Ads with engaging visuals and clear CTAs get 40% higher engagement rates!
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6><i class="bi bi-gear text-info me-2"></i>How to Set Up Promotional Ads</h6>
                            <ol>
                                <li><strong>Access Manager:</strong> Go to Business Panel → Promotions</li>
                                <li><strong>Create Ad:</strong> Choose feature type (casino, spin wheel, etc.)</li>
                                <li><strong>Set Content:</strong> Title, description, call-to-action</li>
                                <li><strong>Configure Display:</strong> Pages to show, daily limits</li>
                                <li><strong>Set Priority:</strong> Higher priority = more visibility</li>
                                <li><strong>Activate:</strong> Enable and start reaching customers!</li>
                            </ol>
                            
                            <h6>📊 Ad Performance Tracking</h6>
                            <ul>
                                <li>Daily view counts and remaining budget</li>
                                <li>User engagement metrics</li>
                                <li>Click-through rates by page</li>
                                <li>ROI analysis and optimization tips</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="feature-card">
                        <h6>🎯 Supported Promotional Types</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>🎰 Casino Promotions</strong>
                                <ul class="mt-2">
                                    <li>Free spin offers</li>
                                    <li>Bonus multipliers</li>
                                    <li>Progressive jackpots</li>
                                </ul>
                            </div>
                            <div class="col-md-3">
                                <strong>🎪 Spin Wheel Ads</strong>
                                <ul class="mt-2">
                                    <li>Special reward wheels</li>
                                    <li>Bonus spin events</li>
                                    <li>Limited-time offers</li>
                                </ul>
                            </div>
                            <div class="col-md-3">
                                <strong>🍕 Pizza Tracker</strong>
                                <ul class="mt-2">
                                    <li>Order tracking demos</li>
                                    <li>Real-time updates</li>
                                    <li>Customer engagement</li>
                                </ul>
                            </div>
                            <div class="col-md-3">
                                <strong>🛍️ General Promos</strong>
                                <ul class="mt-2">
                                    <li>Store discounts</li>
                                    <li>Loyalty programs</li>
                                    <li>Event announcements</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Horse Racing Management -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingHorseRacing">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHorseRacing">
                    <i class="bi bi-award me-2"></i>
                    🏇 Horse Racing Management System
                </button>
            </h2>
            <div id="collapseHorseRacing" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <h5>Transform Your Inventory into Racing Entertainment!</h5>
                    <p>The horse racing system turns your vending machine items into virtual horses, creating an engaging betting experience that drives customer interaction and revenue.</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6>⚡ Quick Races System</h6>
                                <p><strong>6 Races Daily - Automated Entertainment</strong></p>
                                <ul>
                                    <li><strong>Schedule:</strong> Every 4 hours (8:00, 12:00, 16:00, 20:00, 00:00, 04:00)</li>
                                    <li><strong>Duration:</strong> 1 minute per race with live animations</li>
                                    <li><strong>Betting:</strong> Users bet 10-100 QR coins per race</li>
                                    <li><strong>Returns:</strong> 2x-4x payouts based on odds</li>
                                    <li><strong>Revenue:</strong> Commission on all bets placed</li>
                                </ul>
                            </div>
                            
                            <div class="quick-tip">
                                <strong>💰 Revenue Potential:</strong> Businesses see 25-40% revenue increase from racing engagement!
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6>🎨 Custom Jockey Assignments</h6>
                                <p><strong>Personalize Your Racing Experience</strong></p>
                                <ul>
                                    <li><strong>Custom Names:</strong> Brand your jockeys with business themes</li>
                                    <li><strong>Avatar Upload:</strong> Use custom jockey images</li>
                                    <li><strong>Color Schemes:</strong> Match your brand colors</li>
                                    <li><strong>Item Mapping:</strong> Assign jockeys to specific product categories</li>
                                    <li><strong>Performance Tuning:</strong> Adjust horse performance based on sales data</li>
                                </ul>
                            </div>
                            
                            <div class="warning-tip">
                                <strong>⚠️ Important:</strong> Custom jockey assignments only affect races at your specific business location.
                            </div>
                        </div>
                    </div>
                    
                    <h6><i class="bi bi-tools me-2"></i>Horse Racing Management Tools</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <strong>🐎 Race Creation</strong>
                            <ul>
                                <li>Create custom race events</li>
                                <li>Set prize pools and betting limits</li>
                                <li>Schedule special racing days</li>
                                <li>Configure race duration and frequency</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <strong>📊 Performance Analytics</strong>
                            <ul>
                                <li>Track betting volume and revenue</li>
                                <li>Monitor popular horses/items</li>
                                <li>Analyze customer engagement patterns</li>
                                <li>Optimize racing schedules for peak times</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <strong>🏆 Results Management</strong>
                            <ul>
                                <li>Drag-and-drop results entry interface</li>
                                <li>Automated payout processing</li>
                                <li>Winner announcement system</li>
                                <li>Historical race archives</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Analytics & AI Assistant -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingAnalytics">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAnalytics">
                    <i class="bi bi-graph-up me-2"></i>
                    🤖 AI Business Assistant & Enhanced Analytics
                </button>
            </h2>
            <div id="collapseAnalytics" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="revenue-highlight">
                        <h5>🧠 AI-Powered Business Intelligence</h5>
                        <p>Get intelligent insights, automated recommendations, and real-time performance analysis powered by GPT integration and advanced analytics.</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-robot text-primary me-2"></i>AI Business Assistant Features</h6>
                            <ul>
                                <li><strong>Intelligent Insights:</strong> AI-generated business recommendations</li>
                                <li><strong>Performance Analysis:</strong> Automated trend identification</li>
                                <li><strong>Predictive Analytics:</strong> Forecast sales and customer behavior</li>
                                <li><strong>Optimization Tips:</strong> Real-time suggestions for improvement</li>
                                <li><strong>Custom Reports:</strong> Generate detailed business reports</li>
                                <li><strong>Chat Interface:</strong> Ask questions, get instant answers</li>
                            </ul>
                            
                            <div class="quick-tip">
                                <strong>🎯 AI Advantage:</strong> Businesses using AI insights see 35% better decision-making efficiency!
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6><i class="bi bi-graph-up text-success me-2"></i>Enhanced Analytics Dashboard</h6>
                            <ul>
                                <li><strong>Real-Time Metrics:</strong> Live sales, engagement, revenue data</li>
                                <li><strong>Customer Behavior:</strong> Voting patterns, purchase trends</li>
                                <li><strong>Revenue Tracking:</strong> QR coin economy performance</li>
                                <li><strong>Engagement Analytics:</strong> User interaction heatmaps</li>
                                <li><strong>Predictive Models:</strong> Future performance forecasting</li>
                                <li><strong>Comparative Analysis:</strong> Benchmark against industry standards</li>
                            </ul>
                            
                            <div class="feature-card">
                                <strong>📊 Key Performance Indicators (KPIs)</strong>
                                <ul class="mt-2">
                                    <li>Daily/weekly/monthly revenue trends</li>
                                    <li>Customer acquisition and retention rates</li>
                                    <li>QR code scan frequency and conversion</li>
                                    <li>Product popularity and voting impact</li>
                                    <li>Gambling activity and profitability</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <h6><i class="bi bi-lightbulb me-2"></i>AI-Generated Recommendations</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <strong>📈 Revenue Optimization</strong>
                            <ul>
                                <li>Optimal pricing strategies</li>
                                <li>Product mix recommendations</li>
                                <li>Promotional timing suggestions</li>
                                <li>Inventory turnover improvements</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <strong>🎯 Customer Engagement</strong>
                            <ul>
                                <li>Personalized user experiences</li>
                                <li>Targeted promotional campaigns</li>
                                <li>Gamification strategy optimization</li>
                                <li>Loyalty program enhancements</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <strong>⚡ Operational Efficiency</strong>
                            <ul>
                                <li>Machine restocking schedules</li>
                                <li>Performance bottleneck identification</li>
                                <li>Cost reduction opportunities</li>
                                <li>Technology upgrade recommendations</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Engagement Features -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingEngagement">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEngagement">
                    <i class="bi bi-people me-2"></i>
                    💰 Customer Savings & Engagement Systems
                </button>
            </h2>
            <div id="collapseEngagement" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <h5>Drive Customer Loyalty with Visible Savings</h5>
                    <p>Help customers track their real-world savings while increasing engagement and repeat business through gamification and rewards.</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6>💰 Enhanced Savings Dashboard</h6>
                                <p><strong>Show Real CAD Value to Customers</strong></p>
                                <ul>
                                    <li><strong>Total Savings Display:</strong> Complete savings in Canadian dollars</li>
                                    <li><strong>Redeemed vs Pending:</strong> Clear breakdown of savings status</li>
                                    <li><strong>QR Coins Investment:</strong> Show coins used for discounts</li>
                                    <li><strong>Purchase History:</strong> Track all discount transactions</li>
                                    <li><strong>Savings Analytics:</strong> Help customers optimize spending</li>
                                </ul>
                            </div>
                            
                            <div class="quick-tip">
                                <strong>💡 Customer Psychology:</strong> Visible savings increase customer loyalty by 45% and repeat purchases by 60%!
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="feature-card">
                                <h6>🏆 Weekly Winners System</h6>
                                <p><strong>Automated Community Engagement</strong></p>
                                <ul>
                                    <li><strong>Voting Impact:</strong> Show customers their voting influence</li>
                                    <li><strong>Winner Archives:</strong> Historical results for all campaigns</li>
                                    <li><strong>Community Building:</strong> Foster customer participation</li>
                                    <li><strong>Automated Processing:</strong> Weekly calculation and display</li>
                                    <li><strong>Engagement Metrics:</strong> Track community participation rates</li>
                                </ul>
                            </div>
                            
                            <div class="warning-tip">
                                <strong>📅 Important:</strong> Weekly winners are calculated every Monday morning automatically.
                            </div>
                        </div>
                    </div>
                    
                    <div class="feature-card">
                        <h6>🎭 Posty Avatar Cashback System</h6>
                        <p><strong>Reduce Customer Gambling Losses</strong></p>
                        <div class="row">
                            <div class="col-md-6">
                                <ul>
                                    <li><strong>Automatic Unlock:</strong> After customer spends 50,000 QR coins</li>
                                    <li><strong>5% Cashback:</strong> On all spin wheel and casino losses</li>
                                    <li><strong>Instant Credit:</strong> Immediate return to customer balance</li>
                                    <li><strong>Loss Mitigation:</strong> Encourages continued gambling activity</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <strong>Business Benefits:</strong>
                                <ul>
                                    <li>Increased customer retention in gambling features</li>
                                    <li>Higher lifetime value per customer</li>
                                    <li>Reduced customer frustration from losses</li>
                                    <li>Enhanced perceived value of platform</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-open first section
document.addEventListener('DOMContentLoaded', function() {
    // First section is already open by default
});

// Add smooth scrolling for better UX
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 