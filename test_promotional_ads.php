<?php
require_once 'html/core/config.php';
require_once 'html/core/promotional_ads_manager.php';

$adsManager = new PromotionalAdsManager($pdo);

// Create a test promotional ad
$result = $adsManager->createAd(
    1, // business_id
    'casino',
    'Test Business Casino Bonus!',
    'Play our slots and win big! Extra bonuses for location players.',
    'Play Now',
    '/casino/index.php',
    [
        'background_color' => '#dc3545',
        'text_color' => '#ffffff',
        'show_on_vote_page' => true,
        'priority' => 2
    ]
);

echo 'Test promotional ad created: ' . ($result ? 'Success' : 'Failed') . PHP_EOL;

// Get ads for vote page
$ads = $adsManager->getAdsForPage('vote', 2);
echo 'Found ' . count($ads) . ' promotional ads for vote page' . PHP_EOL;

foreach ($ads as $ad) {
    echo "- {$ad['ad_title']} by {$ad['business_name']} ({$ad['feature_type']})" . PHP_EOL;
}
?> 