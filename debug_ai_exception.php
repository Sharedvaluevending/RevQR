<?php
require_once 'html/core/config.php';
require_once 'html/core/ai_assistant.php';

echo "=== AI ASSISTANT EXCEPTION DEBUG ===\n\n";

$aiAssistant = new AIAssistant();
$business_id = 1;

try {
    echo "Calling getBusinessAnalytics...\n";
    $analytics = $aiAssistant->getBusinessAnalytics($business_id, $pdo);
    
    echo "✅ Analytics method completed successfully\n";
    echo "Analytics keys: " . implode(', ', array_keys($analytics)) . "\n\n";
    
    // Check each key
    echo "Data check:\n";
    echo "- casino_participation: " . (empty($analytics['casino_participation']) ? "EMPTY" : "HAS DATA - enabled: " . $analytics['casino_participation']['casino_enabled']) . "\n";
    echo "- promotional_ads: " . count($analytics['promotional_ads'] ?? []) . " records\n";
    echo "- spin_wheels: " . count($analytics['spin_wheels'] ?? []) . " records\n";
    echo "- pizza_trackers: " . count($analytics['pizza_trackers'] ?? []) . " records\n";
    echo "- qr_performance: " . count($analytics['qr_performance'] ?? []) . " records\n";
    
    echo "\nTesting insights generation...\n";
    $insights = $aiAssistant->generateInsights($analytics);
    echo "✅ Insights generated: " . count($insights['recommendations']) . " recommendations\n";
    
    if (count($insights['recommendations']) > 0) {
        echo "\nRecommendations:\n";
        foreach ($insights['recommendations'] as $i => $rec) {
            echo "  " . ($i + 1) . ". {$rec['title']}\n";
        }
    } else {
        echo "\n❌ NO RECOMMENDATIONS GENERATED\n";
        
        // Debug insights generation step by step
        echo "\nDebugging insights generation:\n";
        
        // Check casino specifically
        if (!empty($analytics['casino_participation'])) {
            $casino = $analytics['casino_participation'];
            echo "Casino: enabled=" . $casino['casino_enabled'] . "\n";
            if (!$casino['casino_enabled']) {
                echo "Should generate casino insight for disabled casino\n";
            } else {
                echo "Casino enabled, checking revenue...\n";
                if (isset($analytics['casino_revenue'])) {
                    echo "Casino revenue: $" . $analytics['casino_revenue']['total_casino_revenue'] . "\n";
                } else {
                    echo "No casino revenue data\n";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ EXCEPTION CAUGHT: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?> 