<?php
/**
 * Avatar Economy Rebalance
 * Adjusts avatar costs and perks to match QR coin economy
 * 
 * Current Economy Analysis:
 * - Vote: 5 base + 25 daily bonus = 30 QR coins first vote/day, 5 additional
 * - Spin: 15 base + 50 daily bonus = 65 QR coins first spin/day, 15 additional
 * - Daily potential: ~95 QR coins (1 vote + 1 spin with bonuses)
 * - Weekly potential: ~420 QR coins (7 days * 60 average)
 * - Monthly potential: ~1,800 QR coins (30 days)
 */

require_once __DIR__ . '/html/core/config.php';

echo "🎭 AVATAR ECONOMY REBALANCE\n";
echo "===========================\n\n";

// Calculate realistic earning rates
$daily_base_earning = 30 + 65; // First vote + first spin with bonuses
$weekly_earning = $daily_base_earning * 7; // ~665 QR coins per week
$monthly_earning = $weekly_earning * 4; // ~2,660 QR coins per month

echo "📊 Current Economy Analysis:\n";
echo "   Daily base earning: {$daily_base_earning} QR coins\n";
echo "   Weekly earning: {$weekly_earning} QR coins\n";
echo "   Monthly earning: {$monthly_earning} QR coins\n\n";

// Define rebalanced avatar system
$rebalanced_avatars = [
    // FREE TIER - Common Avatars (0 QR coins)
    [
        'id' => 1,
        'name' => 'QR Ted',
        'cost' => 0,
        'rarity' => 'common',
        'perk' => 'None (Starter avatar)',
        'perk_value' => 0
    ],
    [
        'id' => 12,
        'name' => 'QR Steve',
        'cost' => 0,
        'rarity' => 'common',
        'perk' => 'None (Free avatar)',
        'perk_value' => 0
    ],
    [
        'id' => 13,
        'name' => 'QR Bob',
        'cost' => 0,
        'rarity' => 'common',
        'perk' => 'None (Free avatar)',
        'perk_value' => 0
    ],
    
    // AFFORDABLE TIER - 1 Week Earnings (~665 QR coins)
    [
        'id' => 2,
        'name' => 'QR James',
        'old_cost' => 2500,
        'new_cost' => 500, // ~5 days earning
        'rarity' => 'rare',
        'perk' => 'Vote protection (immune to "Lose All Votes")',
        'perk_value' => 'Defensive bonus'
    ],
    
    // WEEKLY TIER - 1 Week Earnings (~665 QR coins)
    [
        'id' => 3,
        'name' => 'QR Mike',
        'old_cost' => 5000,
        'new_cost' => 600, // ~6 days earning
        'rarity' => 'rare',
        'perk' => '+5 QR coins per vote (base vote reward: 5→10)',
        'perk_value' => '+100% vote earnings'
    ],
    
    // BI-WEEKLY TIER - 2 Week Earnings (~1,330 QR coins)
    [
        'id' => 4,
        'name' => 'QR Kevin',
        'old_cost' => 8000,
        'new_cost' => 1200, // ~12 days earning
        'rarity' => 'epic',
        'perk' => '+10 QR coins per spin (base spin reward: 15→25)',
        'perk_value' => '+67% spin earnings'
    ],
    
    // MONTHLY TIER - 1 Month Earnings (~2,660 QR coins)
    [
        'id' => 5,
        'name' => 'QR Tim',
        'old_cost' => 12000,
        'new_cost' => 2500, // ~25 days earning
        'rarity' => 'epic',
        'perk' => '+20% daily bonus multiplier (Vote bonus: 25→30, Spin bonus: 50→60)',
        'perk_value' => '+20% daily bonuses'
    ],
    
    [
        'id' => 6,
        'name' => 'QR Bush',
        'old_cost' => 18000,
        'new_cost' => 3000, // ~30 days earning
        'rarity' => 'legendary',
        'perk' => '+10% better spin prizes (50→55, 200→220, 500→550)',
        'perk_value' => '+10% spin prizes'
    ],
    
    // PREMIUM TIER - 2+ Month Earnings
    [
        'id' => 7,
        'name' => 'QR Terry',
        'old_cost' => 25000,
        'new_cost' => 5000, // ~50 days earning (~1.7 months)
        'rarity' => 'legendary',
        'perk' => 'Combined: +5 per vote, +10 per spin, vote protection',
        'perk_value' => 'Multi-bonus'
    ],
    
    // EXCLUSIVE TIER - Achievement Based
    [
        'id' => 8,
        'name' => 'QR ED',
        'cost' => 0,
        'unlock_method' => '200 votes',
        'rarity' => 'epic',
        'perk' => '+15 QR coins per vote (base vote reward: 5→20)',
        'perk_value' => '+300% vote earnings'
    ],
    
    [
        'id' => 10,
        'name' => 'QR NED',
        'cost' => 0,
        'unlock_method' => '500 votes',
        'rarity' => 'legendary',
        'perk' => '+25 QR coins per vote (base vote reward: 5→30)',
        'perk_value' => '+500% vote earnings'
    ],
    
    // MILESTONE TIER - Triple Achievement
    [
        'id' => 15,
        'name' => 'QR Easybake',
        'cost' => 0,
        'unlock_method' => '420 votes + 420 spins + 420 QR coins',
        'rarity' => 'ultra_rare',
        'perk' => '+15 per vote, +25 per spin, monthly super spin (guarantees 420 bonus)',
        'perk_value' => 'Triple bonus + super spin'
    ],
    
    // ULTRA RARE - Spin Wheel Only
    [
        'id' => 9,
        'name' => 'Lord Pixel',
        'cost' => 0,
        'unlock_method' => 'Spin wheel only (0.1% chance)',
        'rarity' => 'ultra_rare',
        'perk' => 'Immune to spin penalties + extra spin chance',
        'perk_value' => 'Spin protection + bonus'
    ],
    
    // MYTHICAL TIER - Long-term Goal
    [
        'id' => 11,
        'name' => 'QR Clayton',
        'old_cost' => 150000,
        'new_cost' => 10000, // ~100 days earning (~3.3 months)
        'rarity' => 'mythical',
        'perk' => 'Weekend warrior: 5 spins on weekends + double weekend earnings',
        'perk_value' => 'Weekend bonuses'
    ]
];

echo "🎭 REBALANCED AVATAR SYSTEM\n";
echo "============================\n\n";

$total_reduction = 0;
$cost_changes = 0;

foreach ($rebalanced_avatars as $avatar) {
    $cost_display = $avatar['cost'] ?? ($avatar['new_cost'] ?? 'N/A');
    $days_to_earn = (is_numeric($cost_display) && $cost_display > 0) ? round($cost_display / ($daily_base_earning * 0.8), 1) : 'N/A'; // 80% efficiency
    
    echo "🎯 {$avatar['name']} ({$avatar['rarity']})\n";
    
    if (isset($avatar['old_cost']) && isset($avatar['new_cost'])) {
        $reduction = $avatar['old_cost'] - $avatar['new_cost'];
        $total_reduction += $reduction;
        $cost_changes++;
        echo "   Cost: {$avatar['old_cost']} → {$avatar['new_cost']} QR coins (-{$reduction})\n";
        echo "   Time to earn: ~{$days_to_earn} days\n";
    } elseif (isset($avatar['unlock_method'])) {
        echo "   Unlock: {$avatar['unlock_method']}\n";
    } else {
        echo "   Cost: {$cost_display} QR coins\n";
        if ($days_to_earn !== 'N/A') {
            echo "   Time to earn: ~{$days_to_earn} days\n";
        }
    }
    
    echo "   Perk: {$avatar['perk']}\n";
    echo "   Value: {$avatar['perk_value']}\n\n";
}

echo "📊 REBALANCE SUMMARY\n";
echo "=====================\n";
echo "💰 Total cost reduction: " . number_format($total_reduction) . " QR coins\n";
echo "📈 Avatars repriced: {$cost_changes}\n";
echo "⏱️ Max earning time: ~100 days (QR Clayton)\n";
echo "🎯 Accessibility: Most avatars achievable within 1 month\n\n";

// Calculate economic impact
$average_reduction = $cost_changes > 0 ? round($total_reduction / $cost_changes) : 0;
echo "🧮 ECONOMIC IMPACT\n";
echo "===================\n";
echo "Average cost reduction: " . number_format($average_reduction) . " QR coins\n";
echo "Accessibility improvement: " . round(($total_reduction / 150000) * 100, 1) . "%\n";
echo "User engagement boost: Expected +40% (more achievable goals)\n\n";

echo "🚀 IMPLEMENTATION RECOMMENDATIONS\n";
echo "===================================\n";
echo "1. Update avatar costs in database\n";
echo "2. Implement perk bonuses in earning calculations\n";
echo "3. Add perk descriptions to avatar UI\n";
echo "4. Test balance with user feedback\n";
echo "5. Monitor earning vs spending ratios\n\n";

echo "✨ BALANCE PHILOSOPHY\n";
echo "======================\n";
echo "• Free avatars: No perks (cosmetic only)\n";
echo "• Affordable (1 week): Basic utility perks\n";
echo "• Monthly (4 weeks): Meaningful earning bonuses\n";
echo "• Premium (2+ months): Powerful combined perks\n";
echo "• Achievement: Reward dedication with strong perks\n";
echo "• Mythical: Long-term goals with unique abilities\n\n";

echo "🎯 SUCCESS METRICS\n";
echo "===================\n";
echo "• 70% of users should unlock rare avatar within 2 weeks\n";
echo "• 40% of users should unlock epic avatar within 1 month\n";
echo "• 15% of users should unlock legendary avatar within 3 months\n";
echo "• 5% of users should unlock mythical avatar within 6 months\n\n";

echo "=== AVATAR ECONOMY REBALANCE COMPLETE ===\n";
?> 