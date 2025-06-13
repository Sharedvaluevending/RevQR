<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require user role
require_role('user');

// Get user's current stats for personalized examples
$user_balance = QRCoinManager::getBalance($_SESSION['user_id']);
$stats = getUserStats($_SESSION['user_id'], get_client_ip());

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
/* New features highlighting */
.new-feature {
    background: rgba(255, 193, 7, 0.2) !important;
    border: 1px solid rgba(255, 193, 7, 0.3) !important;
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
    position: relative;
}
.new-feature::after {
    content: 'NEW';
    position: absolute;
    top: -8px;
    right: 10px;
    background: #ffc107;
    color: #000;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: bold;
}
.update-feature {
    background: rgba(0, 123, 255, 0.2) !important;
    border: 1px solid rgba(0, 123, 255, 0.3) !important;
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
    position: relative;
}
.update-feature::after {
    content: 'UPDATED';
    position: absolute;
    top: -8px;
    right: 10px;
    background: #007bff;
    color: #fff;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: bold;
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
.text-danger {
    color: #dc3545 !important;
}
.btn {
    border-radius: 8px !important;
    font-weight: 500 !important;
}
</style>

<div class="container py-4">
    <h1 class="text-center mb-5">
        <i class="bi bi-book-half text-primary me-3"></i>
        RevenueQR User Guide
        <small class="text-muted d-block mt-2">Your complete guide to earning rewards and maximizing benefits</small>
    </h1>

    <!-- User Stats Overview -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-success bg-opacity-20 border-success">
                <div class="card-body text-center">
                    <h3 class="text-success"><?php echo number_format($user_balance); ?></h3>
                    <small>Your QR Coin Balance</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary bg-opacity-20 border-primary">
                <div class="card-body text-center">
                    <h3 class="text-primary"><?php echo $stats['level'] ?? 1; ?></h3>
                    <small>Your Current Level</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning bg-opacity-20 border-warning">
                <div class="card-body text-center">
                    <h3 class="text-warning"><?php echo $stats['rank'] ?? 'Unranked'; ?></h3>
                    <small>Leaderboard Rank</small>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion" id="userGuideAccordion">
        <!-- Getting Started -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingOne">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                    <i class="bi bi-play-circle me-2"></i>
                    Getting Started with RevenueQR
                </button>
            </h2>
            <div id="collapseOne" class="accordion-collapse collapse show">
                <div class="accordion-body">
                    <h5>Welcome to the Future of Vending!</h5>
                    <p>RevenueQR transforms ordinary vending machine interactions into engaging, rewarding experiences through QR codes, gamification, and digital rewards.</p>
                    
                    <h6>Quick Start Guide:</h6>
                    <ol>
                        <li><strong>Find a RevenueQR Machine:</strong> Look for QR codes on vending machines</li>
                        <li><strong>Scan & Register:</strong> Use your phone to scan and create your account</li>
                        <li><strong>Start Earning:</strong> Vote on products, spin wheels, and earn QR coins</li>
                        <li><strong>Spend Rewards:</strong> Use coins in the QR Store or for business discounts</li>
                        <li><strong>Track Progress:</strong> Monitor your earnings and rank on leaderboards</li>
                    </ol>
                    
                    <div class="new-feature">
                        <h6><i class="bi bi-phone text-warning me-2"></i>Progressive Web App (PWA)</h6>
                        <p>RevenueQR now works as a Progressive Web App! Add it to your home screen for a native app experience with offline support and push notifications.</p>
                    </div>
                    
                    <div class="new-feature">
                        <h6><i class="bi bi-piggy-bank text-success me-2"></i>💰 Savings Dashboard</h6>
                        <p>Track all your discount savings in CAD! The new dashboard shows total savings, redeemed amounts, and pending discounts from QR store purchases.</p>
                    </div>
                    
                    <div class="new-feature">
                        <h6><i class="bi bi-person-badge text-primary me-2"></i>🎭 Posty Avatar</h6>
                        <p>Unlock the legendary Posty avatar after spending 50,000 QR coins! Get 5% cashback on all spin wheel and casino losses.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Features Section -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingNewFeatures">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseNewFeatures">
                    <i class="bi bi-stars me-2"></i>
                    🆕 Latest Features & Updates
                </button>
            </h2>
            <div id="collapseNewFeatures" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <h5>🎉 Recent Platform Updates</h5>
                    <p>Discover all the exciting new features and improvements we've added to enhance your RevenueQR experience!</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="new-feature">
                                <h6>🏇 Quick Horse Racing</h6>
                                <p><strong>6 Daily 1-Minute Races!</strong> Bet on simulated horse races with real vending data. Quick entertainment with instant results!</p>
                                <ul>
                                    <li>Race every 4 hours (6 races daily)</li>
                                    <li>Bet 10-100 QR coins per race</li>
                                    <li>Odds range from 2:1 to 4:1</li>
                                    <li>Live race animations</li>
                                </ul>
                            </div>
                            
                            <div class="new-feature">
                                <h6>🏆 Weekly Winners System</h6>
                                <p><strong>See Your Voting Impact!</strong> Track which items won or lost based on community votes.</p>
                                <ul>
                                    <li>Automated weekly result calculation</li>
                                    <li>Historical winner archives</li>
                                    <li>Vote-in and vote-out tracking</li>
                                    <li>Community impact visualization</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="new-feature">
                                <h6>💰 Enhanced Savings Tracking</h6>
                                <p><strong>Real CAD Savings!</strong> Your dashboard now shows exact Canadian dollar savings from QR store discounts.</p>
                                <ul>
                                    <li>Total savings in CAD currency</li>
                                    <li>Redeemed vs pending tracking</li>
                                    <li>QR coins investment counter</li>
                                    <li>Purchase history integration</li>
                                </ul>
                            </div>
                            
                            <div class="new-feature">
                                <h6>🎭 Posty Avatar & Cashback</h6>
                                <p><strong>5% Loss Protection!</strong> The legendary Posty avatar gives you cashback on gambling losses.</p>
                                <ul>
                                    <li>Unlock at 50,000 QR coins spent</li>
                                    <li>5% cashback on spin/casino losses</li>
                                    <li>Automatic credit to your balance</li>
                                    <li>Works with all gambling activities</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="update-feature">
                        <h6>📱 Enhanced Mobile Experience</h6>
                        <p>Progressive Web App capabilities, better mobile interfaces, and offline support for seamless usage anywhere!</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Earning QR Coins -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingTwo">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                    <i class="bi bi-coin me-2"></i>
                    Earning QR Coins - Complete Guide
                </button>
            </h2>
            <div id="collapseTwo" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-hand-thumbs-up text-success me-2"></i>Voting System</h6>
                            <ul>
                                <li><strong>Base Reward:</strong> 5 QR coins per vote</li>
                                <li><strong>Daily Bonus:</strong> +25 coins for first vote</li>
                                <li><strong>Premium Voting:</strong> Special high-reward votes</li>
                                <li><strong>Vote Types:</strong> IN (add items) or OUT (remove items)</li>
                                <li><strong>Weekly Limits:</strong> Balanced economy with weekly caps</li>
                            </ul>
                            
                            <div class="update-feature">
                                <strong>Weekly Winners Integration:</strong> See which items won based on your votes!
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-arrow-repeat text-info me-2"></i>Spin Wheel System</h6>
                            <ul>
                                <li><strong>Base Reward:</strong> 15 QR coins per spin</li>
                                <li><strong>Daily Bonus:</strong> +50 coins for first spin</li>
                                <li><strong>Prize Multipliers:</strong> 2x-10x bonus rewards</li>
                                <li><strong>Special Spins:</strong> Super spins with enhanced prizes</li>
                                <li><strong>Business Wheels:</strong> Location-specific rewards</li>
                            </ul>
                            
                            <div class="new-feature">
                                <strong>Posty Cashback:</strong> 5% back on losses with Posty avatar!
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6><i class="bi bi-star-fill text-warning me-2"></i>Daily Bonuses</h6>
                            <ul>
                                <li>First vote of the day: +25 coins</li>
                                <li>First spin of the day: +50 coins</li>
                                <li>Login streak bonuses</li>
                                <li>Weekend bonus multipliers</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-trophy text-success me-2"></i>Achievement Rewards</h6>
                            <ul>
                                <li>Level progression bonuses</li>
                                <li>Milestone achievement rewards</li>
                                <li>Leaderboard ranking rewards</li>
                                <li>Special event participation</li>
                            </ul>
                        </div>
                    </div>

                    <div class="new-feature">
                        <h6><i class="bi bi-activity me-2"></i>🏇 Horse Racing Quick Earnings</h6>
                        <p>New way to earn with 6 daily quick races! Each race takes just 1 minute with potential 2x-4x returns on your QR coin bets.</p>
                        <ul>
                            <li><strong>Racing Schedule:</strong> Every 4 hours (6 races daily)</li>
                            <li><strong>Bet Range:</strong> 10-100 QR coins per race</li>
                            <li><strong>Win Odds:</strong> 2:1 to 4:1 depending on horse selection</li>
                            <li><strong>Live Action:</strong> Watch races unfold in real-time</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Horse Racing System -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingHorseRacing">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHorseRacing">
                    <i class="bi bi-badge-wc me-2"></i>
                    🏇 Horse Racing Arena - Complete Guide
                </button>
            </h2>
            <div id="collapseHorseRacing" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <h5>Welcome to the Horse Racing Arena!</h5>
                    <p>Experience the thrill of horse racing powered by real vending machine data. Bet on horses, watch live races, and climb the leaderboards!</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="new-feature">
                                <h6>⚡ Quick Races</h6>
                                <p><strong>6 Races Daily - 1 Minute Each!</strong></p>
                                <ul>
                                    <li><strong>Schedule:</strong> Every 4 hours starting at 8:00 AM</li>
                                    <li><strong>Race Times:</strong> 8:00, 12:00, 16:00, 20:00, 00:00, 04:00</li>
                                    <li><strong>Duration:</strong> Exactly 1 minute per race</li>
                                    <li><strong>Betting Window:</strong> 10 minutes before each race</li>
                                </ul>
                                
                                <h6>How to Bet:</h6>
                                <ol>
                                    <li>Choose your bet amount (10-100 QR coins)</li>
                                    <li>Select up to 4 horses</li>
                                    <li>Pick betting type (Single, Exacta, Trifecta, Superfecta)</li>
                                    <li>Confirm your bet before race starts</li>
                                    <li>Watch the live race animation!</li>
                                </ol>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="update-feature">
                                <h6>🏆 Enhanced Leaderboards</h6>
                                <p><strong>Multiple Ways to Compete!</strong></p>
                                <ul>
                                    <li><strong>Top Winners:</strong> Biggest QR coin earners</li>
                                    <li><strong>Win Rate Champions:</strong> Best success percentages</li>
                                    <li><strong>Race Participation:</strong> Most active racers</li>
                                    <li><strong>Top Bettors:</strong> Highest total wagered</li>
                                    <li><strong>Current Streaks:</strong> Longest winning streaks</li>
                                    <li><strong>High Rollers:</strong> Biggest single bets</li>
                                </ul>
                                
                                <p class="mt-3"><strong>Leaderboard Features:</strong></p>
                                <ul>
                                    <li>Real-time ranking updates</li>
                                    <li>Activity status indicators</li>
                                    <li>Weekly and all-time rankings</li>
                                    <li>Avatar display integration</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <h6><i class="bi bi-graph-up me-2"></i>Betting Types & Payouts</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul>
                                <li><strong>Single Bet:</strong> Pick 1 horse to win (2:1 - 4:1 odds)</li>
                                <li><strong>Exacta:</strong> Pick 1st and 2nd place in order (6:1 - 12:1)</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul>
                                <li><strong>Trifecta:</strong> Pick 1st, 2nd, 3rd in order (20:1 - 50:1)</li>
                                <li><strong>Superfecta:</strong> Pick all 4 places in order (100:1 - 500:1)</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="new-feature">
                        <h6>🎭 Posty Avatar Bonus</h6>
                        <p>If you have the Posty avatar equipped, you'll receive 5% cashback on any losing horse racing bets! This helps reduce your losses and gives you more chances to win.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Savings & Spending -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingSavings">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSavings">
                    <i class="bi bi-piggy-bank me-2"></i>
                    💰 Savings Dashboard & Spending Guide
                </button>
            </h2>
            <div id="collapseSavings" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="new-feature">
                        <h5>🆕 Enhanced Savings Tracking</h5>
                        <p>Your dashboard now displays comprehensive savings information in Canadian dollars, giving you a clear picture of your real-world savings!</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-cash-stack text-success me-2"></i>Savings Dashboard Features</h6>
                            <ul>
                                <li><strong>Total Savings CAD:</strong> Complete savings in Canadian dollars</li>
                                <li><strong>Redeemed Savings:</strong> Amount you've already saved</li>
                                <li><strong>Pending Savings:</strong> Future savings from current purchases</li>
                                <li><strong>QR Coins Invested:</strong> Total coins used for discounts</li>
                                <li><strong>Purchase Count:</strong> Number of discount purchases made</li>
                            </ul>
                            
                            <h6>Understanding Your Savings</h6>
                            <p>The savings dashboard aggregates data from:</p>
                            <ul>
                                <li>Business vending machine discounts</li>
                                <li>QR Store purchase discounts</li>
                                <li>Special promotional offers</li>
                                <li>Bulk purchase savings</li>
                            </ul>
                        </div>
                        
                        <div class="col-md-6">
                            <h6><i class="bi bi-shop text-info me-2"></i>QR Store & Spending</h6>
                            <ul>
                                <li><strong>Discount Purchases:</strong> Use QR coins for real discounts</li>
                                <li><strong>Business Rewards:</strong> Location-specific offers</li>
                                <li><strong>Bulk Savings:</strong> Better deals for larger purchases</li>
                                <li><strong>Member Pricing:</strong> Exclusive prices for active users</li>
                            </ul>
                            
                            <h6>Maximizing Your Savings</h6>
                            <ol>
                                <li>Use QR coins for high-value item discounts</li>
                                <li>Watch for special promotional periods</li>
                                <li>Combine multiple offers when possible</li>
                                <li>Track your savings trends over time</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="update-feature">
                        <h6>📊 Savings Analytics</h6>
                        <p>Track your savings patterns, identify the best deals, and optimize your QR coin spending for maximum real-world value.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Avatar System -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingAvatars">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAvatars">
                    <i class="bi bi-person-circle me-2"></i>
                    🎭 Avatar System & Special Perks
                </button>
            </h2>
            <div id="collapseAvatars" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <h5>Avatar Collection & Perks</h5>
                    <p>Collect unique avatars that not only customize your appearance but also provide special gameplay benefits and perks!</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-collection me-2"></i>Avatar Types & Unlocks</h6>
                            <ul>
                                <li><strong>Common:</strong> Basic avatars available for purchase</li>
                                <li><strong>Rare:</strong> Unlock through achievements or milestones</li>
                                <li><strong>Epic:</strong> High-tier achievements required</li>
                                <li><strong>Legendary:</strong> Exclusive unlocks with special perks</li>
                            </ul>
                            
                            <h6>Unlock Methods</h6>
                            <ul>
                                <li>Vote milestones (200, 500+ votes)</li>
                                <li>Spin wheel achievements</li>
                                <li>Points accumulation (150,000+)</li>
                                <li>Spending milestones (50,000+ coins)</li>
                                <li>Triple achievements (420/420/420)</li>
                            </ul>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="new-feature">
                                <h6>🎭 Featured: Posty Avatar</h6>
                                <p><strong>Legendary Tier - 5% Cashback Perk</strong></p>
                                <ul>
                                    <li><strong>Unlock:</strong> Spend 50,000 QR coins total</li>
                                    <li><strong>Perk:</strong> 5% cashback on spin/casino losses</li>
                                    <li><strong>How it Works:</strong> Automatic credit after any loss</li>
                                    <li><strong>Coverage:</strong> All spin wheels, casino games, horse racing</li>
                                </ul>
                                
                                <p><strong>Cashback Examples:</strong></p>
                                <ul>
                                    <li>Lose 100 coins → Get 5 coins back</li>
                                    <li>Lose 500 coins → Get 25 coins back</li>
                                    <li>Lose 1000 coins → Get 50 coins back</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <h6><i class="bi bi-star me-2"></i>Other Special Avatar Perks</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul>
                                <li><strong>QR ED:</strong> +10 vote bonus (200 votes unlock)</li>
                                <li><strong>Lord Pixel:</strong> +20 spin bonus (spin achievements)</li>
                                <li><strong>QR NED:</strong> +15 vote bonus (500 votes unlock)</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul>
                                <li><strong>QR Clayton:</strong> +25 point bonus (150K points)</li>
                                <li><strong>QR Easybake:</strong> Triple bonus perks (420³ unlock)</li>
                                <li><strong>QR Ryan:</strong> Special exclusive avatar</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- New: Loot Boxes -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingLoot">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLoot">
                    <i class="bi bi-gift me-2"></i>
                    Loot Boxes & Premium Rewards 🆕
                </button>
            </h2>
            <div id="collapseLoot" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="new-feature">
                        <h5><i class="bi bi-box-seam text-warning me-2"></i>Fortnite-Style Loot Box System</h5>
                        <p>Experience the thrill of opening loot boxes with guaranteed rewards and exciting animations!</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-success">🟢 Common Loot Box</h6>
                            <ul>
                                <li><strong>Cost:</strong> 300 QR coins</li>
                                <li><strong>Contains:</strong> 150-400 coins worth of rewards</li>
                                <li><strong>Rewards:</strong> QR coins, spins, votes</li>
                                <li><strong>Perfect for:</strong> Daily opening</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-primary">🔵 Rare Loot Box</h6>
                            <ul>
                                <li><strong>Cost:</strong> 750 QR coins</li>
                                <li><strong>Contains:</strong> 400-800 coins worth of rewards</li>
                                <li><strong>Rewards:</strong> Premium spins, vote bonuses, boosts</li>
                                <li><strong>Perfect for:</strong> Weekly treats</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-warning">🟡 Legendary Loot Box</h6>
                            <ul>
                                <li><strong>Cost:</strong> 2,000 QR coins</li>
                                <li><strong>Contains:</strong> 1,500-4,000 coins worth of rewards</li>
                                <li><strong>Rewards:</strong> Rare avatars, massive bonuses, exclusive items</li>
                                <li><strong>Perfect for:</strong> Special occasions</li>
                            </ul>
                        </div>
                    </div>
                    
                    <h6><i class="bi bi-magic text-info me-2"></i>Special Boosts Available</h6>
                    <ul>
                        <li><strong>Spin Multiplier:</strong> 2x spin rewards for 24-72 hours</li>
                        <li><strong>Vote Bonus:</strong> +50% vote earnings for 48 hours</li>
                        <li><strong>Lucky Charm:</strong> +10% better spin results for 72 hours</li>
                        <li><strong>Exclusive Avatars:</strong> Legendary-tier avatars only from loot boxes</li>
                    </ul>
                    
                    <a href="qr-store.php#loot-boxes" class="btn btn-primary">Open Loot Boxes Now</a>
                </div>
            </div>
        </div>

        <!-- Enhanced Spending Guide -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingThree">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                    <i class="bi bi-shop me-2"></i>
                    Spending Your QR Coins - Enhanced Store
                </button>
            </h2>
            <div id="collapseThree" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-bag text-primary me-2"></i>QR Store</h6>
                            <ul>
                                <li><strong>Avatars:</strong> 16 unique collectible avatars (1,000-75,000 coins)</li>
                                <li><strong>Avatar Accessories:</strong> Hats, glasses, and special effects</li>
                                <li><strong>Spin Packs:</strong> Extra daily spins for spin wheels</li>
                                <li><strong>Spin Insurance:</strong> Protect your streaks and bonuses</li>
                                <li><strong>Vote Multipliers:</strong> Increase voting rewards temporarily</li>
                            </ul>
                            
                            <div class="new-feature">
                                <h6>🎰 Casino Spin Packs</h6>
                                <ul>
                                    <li><strong>Daily Casino Boost:</strong> 2 spins/day × 3 days (300 coins)</li>
                                    <li><strong>Extra Casino Spins:</strong> 3-5 spins/day × 7 days (800-1,200 coins)</li>
                                    <li><strong>Premium Packs:</strong> 10 spins/day × 14 days (2,500 coins)</li>
                                    <li><strong>VIP Casino Spins:</strong> 20 spins/day × 30 days (5,000 coins)</li>
                                </ul>
                            </div>
                            
                            <a href="qr-store.php" class="btn btn-primary btn-sm">Visit QR Store</a>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-building text-warning me-2"></i>Business Stores & NAYAX</h6>
                            <ul>
                                <li><strong>Real Discounts:</strong> 5%-15% off at local businesses</li>
                                <li><strong>Restaurant Savings:</strong> Food and beverage discounts</li>
                                <li><strong>Retail Discounts:</strong> Shopping and service savings</li>
                                <li><strong>Instant Codes:</strong> 8-character redemption codes</li>
                            </ul>
                            
                            <div class="new-feature">
                                <h6>📱 NAYAX Machine Integration</h6>
                                <ul>
                                    <li><strong>QR Code Discounts:</strong> Scan at NAYAX vending machines</li>
                                    <li><strong>Mobile Optimized:</strong> Seamless mobile checkout experience</li>
                                    <li><strong>Real-time Validation:</strong> Instant discount application</li>
                                    <li><strong>Usage Tracking:</strong> Monitor your savings and usage</li>
                                </ul>
                            </div>
                            
                            <a href="business-stores.php" class="btn btn-warning btn-sm">Browse Discounts</a>
                        </div>
                    </div>
                    
                    <h6><i class="bi bi-star text-info me-2"></i>Smart Spending Tips</h6>
                    <ul>
                        <li><strong>Plan Purchases:</strong> Save for rare avatars and premium items</li>
                        <li><strong>Use Discounts:</strong> Business discounts provide real-world value</li>
                        <li><strong>Stack Bonuses:</strong> Combine multipliers for maximum earnings</li>
                        <li><strong>Track Spending:</strong> Monitor purchase history and ROI</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Pizza Tracker System -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingPizza">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePizza">
                    <i class="bi bi-pizza me-2"></i>
                    Pizza Tracker - Workplace Morale Rewards 🆕
                </button>
            </h2>
            <div id="collapsePizza" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="new-feature">
                        <h5><i class="bi bi-people-fill text-danger me-2"></i>Team Pizza Rewards System</h5>
                        <p>Watch your workplace earn a free pizza dinner through collective engagement! Perfect for factory workers, office teams, and school staff who deserve recognition for their participation.</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-graph-up text-primary me-2"></i>How It Works</h6>
                            <ol>
                                <li><strong>Team Engagement:</strong> Everyone at your workplace participates in voting and spinning</li>
                                <li><strong>Progress Tracking:</strong> Watch the pizza meter fill up as your team engages</li>
                                <li><strong>Milestone Celebrations:</strong> Visual progress shows when you're getting closer to pizza time</li>
                                <li><strong>Pizza Delivery:</strong> When the goal is reached, your workplace gets a free pizza dinner!</li>
                            </ol>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-heart text-success me-2"></i>Perfect For</h6>
                            <ul>
                                <li><strong>Factory Workers:</strong> Hard-working teams deserve appreciation</li>
                                <li><strong>Office Teams:</strong> Build camaraderie and team spirit</li>
                                <li><strong>School Staff:</strong> Teachers and support staff recognition</li>
                                <li><strong>Break Rooms:</strong> Turn lunch breaks into team celebrations</li>
                                <li><strong>Shift Workers:</strong> Reward different shifts fairly</li>
                            </ul>
                        </div>
                    </div>
                    
                    <h6><i class="bi bi-trophy text-warning me-2"></i>The Blue Collar Spirit</h6>
                    <ul>
                        <li><strong>Real Appreciation:</strong> Nothing beats a free pizza after a hard day's work</li>
                        <li><strong>Team Building:</strong> Everyone contributes, everyone celebrates together</li>
                        <li><strong>Fair Rewards:</strong> Based on collective effort, not individual competition</li>
                        <li><strong>Workplace Morale:</strong> Boost team spirit and job satisfaction</li>
                        <li><strong>Simple Recognition:</strong> No complicated rewards - just good food and good times</li>
                    </ul>
                    
                    <div class="alert alert-success">
                        <i class="bi bi-pizza me-2"></i>
                        <strong>The Power of Pizza:</strong> As any blue collar worker knows, a free pizza dinner brings the team together like nothing else. It's simple, it's appreciated, and it shows that hard work pays off!
                    </div>
                </div>
            </div>
        </div>

        <!-- Casino & Gaming -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingCasino">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCasino">
                    <i class="bi bi-dice-6 me-2"></i>
                    Casino & Gaming Features 🆕
                </button>
            </h2>
            <div id="collapseCasino" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-slot-machine text-primary me-2"></i>Slot Machine Casino</h6>
                            <ul>
                                <li><strong>Daily Free Spins:</strong> 3 free spins per day per business</li>
                                <li><strong>7-Tier Prize System:</strong> From small wins to massive jackpots</li>
                                <li><strong>Location-Based Gaming:</strong> Different machines at different businesses</li>
                                <li><strong>Revenue Sharing:</strong> 10% of winnings go to the business</li>
                                <li><strong>Bonus Features:</strong> Wild symbols, scatter pays, bonus rounds</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-trophy text-warning me-2"></i>Horse Racing Platform</h6>
                            <ul>
                                <li><strong>Virtual Races:</strong> Automated horse racing events</li>
                                <li><strong>Betting System:</strong> Win/Place/Show betting options</li>
                                <li><strong>Daily Races:</strong> Multiple races throughout the day</li>
                                <li><strong>Jockey System:</strong> Unique jockeys with different stats</li>
                                <li><strong>Live Commentary:</strong> Real-time race narration</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="new-feature">
                        <h6>🎰 Enhanced Casino Spin Packs</h6>
                        <p>Purchase additional casino spins beyond your daily free spins:</p>
                        <div class="row">
                            <div class="col-md-6">
                                <ul>
                                    <li><strong>Daily Boost:</strong> +2 spins/day for 3 days (300 coins)</li>
                                    <li><strong>Weekly Pack:</strong> +3 spins/day for 7 days (800 coins)</li>
                                    <li><strong>Premium Pack:</strong> +5 spins/day for 7 days (1,200 coins)</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul>
                                    <li><strong>VIP Weekly:</strong> +10 spins/day for 14 days (2,500 coins)</li>
                                    <li><strong>VIP Monthly:</strong> +20 spins/day for 30 days (5,000 coins)</li>
                                    <li><strong>Smart Tracking:</strong> Monitor pack usage and expiration</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <a href="../casino/index.php" class="btn btn-danger btn-sm w-100">Play Casino</a>
                        </div>
                        <div class="col-6">
                            <a href="../horse-racing/index.php" class="btn btn-warning btn-sm w-100">Horse Racing</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Guide -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingFour">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour">
                    <i class="bi bi-speedometer2 me-2"></i>
                    Understanding Your Enhanced Dashboard
                </button>
            </h2>
            <div id="collapseFour" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-graph-up text-success me-2"></i>Key Metrics</h6>
                            <ul>
                                <li><strong>QR Coin Balance:</strong> Your current spendable coins</li>
                                <li><strong>Level & XP Progress:</strong> User advancement tracking</li>
                                <li><strong>Activity Streak:</strong> Consecutive engagement days</li>
                                <li><strong>Leaderboard Rank:</strong> Your position vs other users</li>
                                <li><strong>Total Earnings:</strong> Lifetime QR coin earnings</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-activity text-info me-2"></i>Activity Tracking</h6>
                            <ul>
                                <li><strong>Recent Activity:</strong> Latest votes, spins, and purchases</li>
                                <li><strong>Daily Summary:</strong> Today's earnings and activities</li>
                                <li><strong>Achievement Progress:</strong> Current milestone tracking</li>
                                <li><strong>Bonus Timers:</strong> Time until next daily bonuses</li>
                                <li><strong>Pack Status:</strong> Active spin packs and expiration</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="new-feature">
                        <h6><i class="bi bi-phone text-primary me-2"></i>Enhanced Mobile Experience</h6>
                        <ul>
                            <li><strong>Progressive Web App:</strong> Install as a native app</li>
                            <li><strong>Offline Support:</strong> View cached data without internet</li>
                            <li><strong>Push Notifications:</strong> Get alerts for bonuses and updates</li>
                            <li><strong>Touch Optimized:</strong> Smooth mobile interactions</li>
                            <li><strong>Dark Mode:</strong> Eye-friendly interface</li>
                        </ul>
                    </div>
                    
                    <h6><i class="bi bi-award text-warning me-2"></i>Achievement System</h6>
                    <ul>
                        <li><strong>Voting Achievements:</strong> Vote milestone rewards</li>
                        <li><strong>Spinning Achievements:</strong> Spin streak and total rewards</li>
                        <li><strong>Earning Achievements:</strong> QR coin accumulation milestones</li>
                        <li><strong>Social Achievements:</strong> Community interaction rewards</li>
                        <li><strong>Special Achievements:</strong> Limited-time and seasonal rewards</li>
                    </ul>
                    
                    <a href="dashboard.php" class="btn btn-primary">View Your Dashboard</a>
                </div>
            </div>
        </div>

        <!-- QR Code Features -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingQR">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseQR">
                    <i class="bi bi-qr-code me-2"></i>
                    Advanced QR Code Features 🆕
                </button>
            </h2>
            <div id="collapseQR" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="new-feature">
                        <h5><i class="bi bi-magic text-primary me-2"></i>Enhanced QR Code System</h5>
                        <p>Experience next-generation QR code functionality with advanced features and seamless integration!</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-list-ul text-success me-2"></i>QR Code Types</h6>
                            <ul>
                                <li><strong>Voting QR:</strong> Direct access to voting pages</li>
                                <li><strong>Spin Wheel QR:</strong> Immediate spin access</li>
                                <li><strong>Casino QR:</strong> Direct casino game access</li>
                                <li><strong>Business Discount QR:</strong> NAYAX machine discounts</li>
                                <li><strong>Pizza Tracker QR:</strong> Order tracking access</li>
                                <li><strong>Campaign QR:</strong> Multi-feature campaign access</li>
                                <li><strong>Custom QR:</strong> Business-specific experiences</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-phone text-info me-2"></i>Scanning Features</h6>
                            <ul>
                                <li><strong>Fast Recognition:</strong> Instant QR code detection</li>
                                <li><strong>Error Correction:</strong> Works even with damaged codes</li>
                                <li><strong>Bulk Scanning:</strong> Scan multiple codes quickly</li>
                                <li><strong>History Tracking:</strong> See previously scanned codes</li>
                                <li><strong>Analytics:</strong> Track your QR interactions</li>
                            </ul>
                        </div>
                    </div>
                    
                    <h6><i class="bi bi-gear text-warning me-2"></i>Advanced Features</h6>
                    <ul>
                        <li><strong>Smart Redirects:</strong> Automatic routing to appropriate features</li>
                        <li><strong>Session Management:</strong> Seamless login and session handling</li>
                        <li><strong>Location Awareness:</strong> Location-specific QR functionality</li>
                        <li><strong>Real-time Validation:</strong> Instant QR code verification</li>
                        <li><strong>Fraud Protection:</strong> Security measures against fake codes</li>
                    </ul>
                    
                    <div class="alert alert-success">
                        <i class="bi bi-lightbulb me-2"></i>
                        <strong>Pro Tip:</strong> Save frequently used QR codes to your phone's favorites for quick access!
                    </div>
                </div>
            </div>
        </div>

        <!-- Leaderboards & Competition -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingFive">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive">
                    <i class="bi bi-trophy me-2"></i>
                    Leaderboards & Community Competition
                </button>
            </h2>
            <div id="collapseFive" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-list-ol text-primary me-2"></i>Ranking Categories</h6>
                            <ul>
                                <li><strong>QR Coin Balance:</strong> Total accumulated coins</li>
                                <li><strong>Total Votes:</strong> Lifetime voting participation</li>
                                <li><strong>Total Spins:</strong> Spin wheel engagement</li>
                                <li><strong>Casino Winnings:</strong> Total casino earnings</li>
                                <li><strong>Horse Racing:</strong> Betting success rate</li>
                                <li><strong>Activity Level:</strong> Overall platform engagement</li>
                                <li><strong>Weekly Activity:</strong> Recent engagement scores</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-award text-warning me-2"></i>Leaderboard Features</h6>
                            <ul>
                                <li><strong>Top 100 Display:</strong> See the best performers</li>
                                <li><strong>Your Position:</strong> Track your ranking progress</li>
                                <li><strong>Trophy Levels:</strong> Gold, Silver, Bronze badges</li>
                                <li><strong>Weekly Reset:</strong> Fresh competition cycles</li>
                                <li><strong>Achievement Tracking:</strong> Special milestone recognition</li>
                                <li><strong>Community Stats:</strong> Platform-wide statistics</li>
                            </ul>
                        </div>
                    </div>
                    
                    <h6><i class="bi bi-people text-success me-2"></i>Community Features</h6>
                    <ul>
                        <li><strong>Avatar Display:</strong> Show off your unique avatar</li>
                        <li><strong>Level Indicators:</strong> Display your user level</li>
                        <li><strong>Streak Tracking:</strong> Community streak competitions</li>
                        <li><strong>Social Recognition:</strong> Top performer highlighting</li>
                    </ul>
                    
                    <a href="leaderboards.php" class="btn btn-warning">View Leaderboards</a>
                </div>
            </div>
        </div>

        <!-- Support & Help -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingSix">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix">
                    <i class="bi bi-question-circle me-2"></i>
                    Support & Troubleshooting
                </button>
            </h2>
            <div id="collapseSix" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-bug text-danger me-2"></i>Common Issues</h6>
                            <ul>
                                <li><strong>QR Code Won't Scan:</strong> Clean camera lens, ensure good lighting</li>
                                <li><strong>Coins Not Adding:</strong> Check internet connection, refresh page</li>
                                <li><strong>Spins Not Working:</strong> Clear browser cache, try different browser</li>
                                <li><strong>Login Problems:</strong> Reset password, check email verification</li>
                                <li><strong>Mobile Issues:</strong> Update browser, reinstall PWA</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-life-preserver text-info me-2"></i>Getting Help</h6>
                            <ul>
                                <li><strong>Contact Support:</strong> Email support@revenueqr.com</li>
                                <li><strong>Live Chat:</strong> Available during business hours</li>
                                <li><strong>User Manual:</strong> Comprehensive documentation</li>
                                <li><strong>Video Tutorials:</strong> Step-by-step guides</li>
                                <li><strong>FAQ Section:</strong> Quick answers to common questions</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="new-feature">
                        <h6><i class="bi bi-robot text-primary me-2"></i>AI Assistant</h6>
                        <p>Get instant help with our AI-powered assistant available 24/7 for troubleshooting and guidance!</p>
                    </div>
                    
                    <h6><i class="bi bi-shield-check text-success me-2"></i>Account Security</h6>
                    <ul>
                        <li><strong>Secure Login:</strong> Use strong passwords and 2FA if available</li>
                        <li><strong>Privacy Settings:</strong> Control what information is visible</li>
                        <li><strong>Transaction History:</strong> Monitor all QR coin transactions</li>
                        <li><strong>Report Issues:</strong> Flag suspicious activity immediately</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-5">
        <div class="col-md-3 mb-3">
            <a href="dashboard.php" class="btn btn-primary w-100">
                <i class="bi bi-speedometer2 me-2"></i>Your Dashboard
            </a>
        </div>
        <div class="col-md-3 mb-3">
            <a href="qr-store.php" class="btn btn-success w-100">
                <i class="bi bi-shop me-2"></i>QR Store
            </a>
        </div>
        <div class="col-md-3 mb-3">
            <a href="leaderboards.php" class="btn btn-warning w-100">
                <i class="bi bi-trophy me-2"></i>Leaderboards
            </a>
        </div>
        <div class="col-md-3 mb-3">
            <a href="vote.php" class="btn btn-info w-100">
                <i class="bi bi-hand-thumbs-up me-2"></i>Start Voting
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 