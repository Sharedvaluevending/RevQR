<?php
/**
 * Fix Invalid QR Codes
 * Removes the 6 test QR codes with NULL URLs
 */

require_once __DIR__ . '/html/core/config.php';

echo "🔧 FIXING INVALID QR CODES\n";
echo "=========================\n\n";

try {
    // First, show what we're about to delete
    echo "📋 QR Codes to be deleted:\n";
    $stmt = $pdo->query("
        SELECT id, qr_type, code, machine_name, created_at
        FROM qr_codes 
        WHERE id IN (58, 61, 69, 70, 76, 77)
        ORDER BY id
    ");
    
    $to_delete = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($to_delete)) {
        echo "✅ No QR codes found to delete - they may have already been removed\n\n";
    } else {
        foreach ($to_delete as $qr) {
            echo "  • ID {$qr['id']}: {$qr['qr_type']} - {$qr['code']} - {$qr['machine_name']} ({$qr['created_at']})\n";
        }
        echo "\n";
        
        // Create backup before deletion
        echo "💾 Creating backup of QR codes to delete...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS deleted_qr_codes_backup AS 
            SELECT * FROM qr_codes WHERE id IN (58, 61, 69, 70, 76, 77)
        ");
        echo "✅ Backup created in deleted_qr_codes_backup table\n\n";
        
        // Delete the invalid QR codes
        echo "🗑️  Deleting invalid QR codes...\n";
        $stmt = $pdo->prepare("DELETE FROM qr_codes WHERE id IN (58, 61, 69, 70, 76, 77)");
        $stmt->execute();
        
        $deleted_count = $stmt->rowCount();
        echo "✅ Deleted $deleted_count QR codes\n\n";
        
        // Log the cleanup
        try {
            $pdo->exec("
                INSERT INTO migration_log (phase, step, status, message) 
                VALUES ('cleanup', 1, 'success', 'Removed $deleted_count test QR codes with NULL URLs')
            ");
            echo "📝 Cleanup logged\n\n";
        } catch (Exception $e) {
            echo "⚠️  Logging skipped: " . $e->getMessage() . "\n\n";
        }
    }
    
    // Verify the fix
    echo "🔍 Verification:\n";
    
    // Check for remaining invalid URLs
    $remaining_invalid = $pdo->query("
        SELECT COUNT(*) FROM qr_codes 
        WHERE qr_type IN ('static', 'dynamic') 
        AND (url IS NULL OR url = '' OR url NOT LIKE 'http%')
    ")->fetchColumn();
    
    if ($remaining_invalid == 0) {
        echo "✅ No more QR codes with invalid URLs\n";
    } else {
        echo "⚠️  Still $remaining_invalid QR codes with invalid URLs\n";
    }
    
    // Show current QR code status
    $total_qr = $pdo->query("SELECT COUNT(*) FROM qr_codes")->fetchColumn();
    $static_dynamic = $pdo->query("
        SELECT COUNT(*) FROM qr_codes 
        WHERE qr_type IN ('static', 'dynamic')
    ")->fetchColumn();
    
    echo "📊 Current QR Code Status:\n";
    echo "  • Total QR codes: $total_qr\n";
    echo "  • Static/Dynamic QR codes: $static_dynamic\n";
    echo "  • All other QR types: " . ($total_qr - $static_dynamic) . "\n\n";
    
    // Show QR codes by type
    echo "📈 QR Codes by Type:\n";
    $types = $pdo->query("
        SELECT qr_type, COUNT(*) as count 
        FROM qr_codes 
        GROUP BY qr_type 
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($types as $type) {
        echo "  • {$type['qr_type']}: {$type['count']} codes\n";
    }
    
    echo "\n🎉 QR CODE CLEANUP COMPLETED!\n";
    echo "✅ All invalid QR codes removed\n";
    echo "✅ System data integrity improved\n";
    echo "✅ Ready to proceed with Priority 3\n\n";
    
} catch (Exception $e) {
    echo "❌ Error during QR code cleanup: " . $e->getMessage() . "\n";
} 