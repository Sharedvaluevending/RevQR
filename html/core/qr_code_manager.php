<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;

/**
 * QR Code Manager for Nayax Integration
 * Handles QR code generation, storage, and validation for business discounts
 */
class QRCodeManager {
    
    /**
     * Generate QR code for business discount purchase
     * 
     * @param int $purchase_id Business purchase ID
     * @param array $purchase_data Purchase details
     * @return array QR code generation result
     */
    public static function generateDiscountQRCode($purchase_id, $purchase_data) {
        global $pdo;
        
        try {
            // Create QR code content payload for Nayax machines
            $qr_payload = [
                'type' => 'business_discount',
                'purchase_id' => $purchase_id,
                'purchase_code' => $purchase_data['purchase_code'],
                'business_id' => $purchase_data['business_id'],
                'discount_percentage' => $purchase_data['discount_percentage'],
                'expires_at' => $purchase_data['expires_at'],
                'user_id' => $purchase_data['user_id'],
                'timestamp' => time(),
                'security_hash' => self::generateSecurityHash($purchase_id, $purchase_data)
            ];
            
            // Add item selection if specific items are selected
            if (!empty($purchase_data['selected_items'])) {
                $qr_payload['selected_items'] = $purchase_data['selected_items'];
            }
            
            // Add machine-specific data if applicable
            if (!empty($purchase_data['nayax_machine_id'])) {
                $qr_payload['nayax_machine_id'] = $purchase_data['nayax_machine_id'];
            }
            
            $qr_content = json_encode($qr_payload);
            
            // Generate QR code image
            $result = Builder::create()
                ->writer(new PngWriter())
                ->writerOptions([])
                ->data($qr_content)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(new ErrorCorrectionLevelMedium())
                ->size(250)
                ->margin(10)
                ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->build();
                
            // Convert to base64 for storage
            $qr_base64 = base64_encode($result->getString());
            
            // Update business purchase with QR code data
            $stmt = $pdo->prepare("
                UPDATE business_purchases 
                SET qr_code_data = ?, qr_code_content = ?, nayax_machine_id = ?, item_selection = ?
                WHERE id = ?
            ");
            
            $item_selection_json = !empty($purchase_data['selected_items']) ? 
                json_encode($purchase_data['selected_items']) : null;
                
            $stmt->execute([
                $qr_base64,
                $qr_content,
                $purchase_data['nayax_machine_id'] ?? null,
                $item_selection_json,
                $purchase_id
            ]);
            
            return [
                'success' => true,
                'qr_code_data' => $qr_base64,
                'qr_code_content' => $qr_content,
                'message' => 'QR code generated successfully'
            ];
            
        } catch (Exception $e) {
            error_log("QR Code generation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate QR code: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate QR code when scanned
     * 
     * @param string $qr_content QR code content
     * @param string $scanner_ip IP address of scanner
     * @param string $user_agent User agent of scanning device
     * @return array Validation result
     */
    public static function validateQRCode($qr_content, $scanner_ip = null, $user_agent = null) {
        global $pdo;
        
        try {
            $qr_data = json_decode($qr_content, true);
            
            if (!$qr_data || !isset($qr_data['purchase_id'])) {
                return self::logScanResult(null, 'invalid', 'Invalid QR code format', $scanner_ip, $user_agent);
            }
            
            $purchase_id = $qr_data['purchase_id'];
            
            // Get purchase details
            $stmt = $pdo->prepare("
                SELECT bp.*, bsi.item_name, b.name as business_name
                FROM business_purchases bp
                JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
                JOIN businesses b ON bp.business_id = b.id
                WHERE bp.id = ?
            ");
            $stmt->execute([$purchase_id]);
            $purchase = $stmt->fetch();
            
            if (!$purchase) {
                return self::logScanResult($purchase_id, 'invalid', 'Purchase not found', $scanner_ip, $user_agent);
            }
            
            // Verify security hash
            $expected_hash = self::generateSecurityHash($purchase_id, $purchase);
            if ($qr_data['security_hash'] !== $expected_hash) {
                return self::logScanResult($purchase_id, 'invalid', 'Security validation failed', $scanner_ip, $user_agent);
            }
            
            // Check if already redeemed
            if ($purchase['status'] === 'redeemed') {
                return self::logScanResult($purchase_id, 'already_used', 'Discount already redeemed', $scanner_ip, $user_agent);
            }
            
            // Check if expired
            if (strtotime($purchase['expires_at']) < time()) {
                return self::logScanResult($purchase_id, 'expired', 'Discount has expired', $scanner_ip, $user_agent);
            }
            
            // Update scan tracking
            self::updateScanTracking($purchase_id, $scanner_ip, $user_agent);
            
            return [
                'success' => true,
                'scan_result' => 'success',
                'purchase' => $purchase,
                'discount_percentage' => $purchase['discount_percentage'],
                'business_name' => $purchase['business_name'],
                'expires_at' => $purchase['expires_at'],
                'selected_items' => json_decode($purchase['item_selection'] ?? '[]', true)
            ];
            
        } catch (Exception $e) {
            error_log("QR Code validation error: " . $e->getMessage());
            return self::logScanResult(null, 'error', $e->getMessage(), $scanner_ip, $user_agent);
        }
    }
    
    /**
     * Mark QR code as redeemed
     * 
     * @param int $purchase_id Purchase ID
     * @param string $nayax_machine_id Machine that processed redemption
     * @return bool Success status
     */
    public static function markAsRedeemed($purchase_id, $nayax_machine_id = null) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE business_purchases 
                SET status = 'redeemed', redeemed_at = NOW(), nayax_machine_id = COALESCE(?, nayax_machine_id)
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$nayax_machine_id, $purchase_id]);
            
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            error_log("QR Code redemption error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get QR code statistics for a user
     * 
     * @param int $user_id User ID
     * @return array QR code statistics
     */
    public static function getUserQRStats($user_id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_qr_codes,
                    COUNT(CASE WHEN status = 'pending' AND expires_at > NOW() THEN 1 END) as active_codes,
                    COUNT(CASE WHEN status = 'redeemed' THEN 1 END) as redeemed_codes,
                    COUNT(CASE WHEN expires_at <= NOW() AND status != 'redeemed' THEN 1 END) as expired_codes,
                    COALESCE(SUM(scan_count), 0) as total_scans
                FROM business_purchases 
                WHERE user_id = ? AND qr_code_data IS NOT NULL
            ");
            $stmt->execute([$user_id]);
            
            return $stmt->fetch() ?: [
                'total_qr_codes' => 0,
                'active_codes' => 0,
                'redeemed_codes' => 0,
                'expired_codes' => 0,
                'total_scans' => 0
            ];
            
        } catch (Exception $e) {
            error_log("QR Stats error: " . $e->getMessage());
            return [
                'total_qr_codes' => 0,
                'active_codes' => 0,
                'redeemed_codes' => 0,
                'expired_codes' => 0,
                'total_scans' => 0
            ];
        }
    }
    
    /**
     * Generate security hash for QR code validation
     * 
     * @param int $purchase_id Purchase ID
     * @param array $purchase_data Purchase data
     * @return string Security hash
     */
    private static function generateSecurityHash($purchase_id, $purchase_data) {
        $secret_key = 'revenueqr_nayax_security_2025'; // In production, store in config
        $data_string = $purchase_id . $purchase_data['purchase_code'] . $purchase_data['expires_at'] . $secret_key;
        return hash('sha256', $data_string);
    }
    
    /**
     * Log QR code scan result
     * 
     * @param int|null $purchase_id Purchase ID
     * @param string $result Scan result
     * @param string $error_message Error message if any
     * @param string|null $scanner_ip Scanner IP
     * @param string|null $user_agent User agent
     * @return array Result data
     */
    private static function logScanResult($purchase_id, $result, $error_message = null, $scanner_ip = null, $user_agent = null) {
        global $pdo;
        
        try {
            if ($purchase_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO business_purchase_qr_scans 
                    (business_purchase_id, scan_result, error_message, scanner_ip, user_agent)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$purchase_id, $result, $error_message, $scanner_ip, $user_agent]);
            }
        } catch (Exception $e) {
            error_log("Scan logging error: " . $e->getMessage());
        }
        
        return [
            'success' => $result === 'success',
            'scan_result' => $result,
            'message' => $error_message ?: 'QR code validated successfully'
        ];
    }
    
    /**
     * Update scan tracking for a purchase
     * 
     * @param int $purchase_id Purchase ID
     * @param string|null $scanner_ip Scanner IP
     * @param string|null $user_agent User agent
     */
    private static function updateScanTracking($purchase_id, $scanner_ip = null, $user_agent = null) {
        global $pdo;
        
        try {
            // Update scan count and last scanned time
            $stmt = $pdo->prepare("
                UPDATE business_purchases 
                SET scan_count = scan_count + 1, last_scanned_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$purchase_id]);
            
            // Log detailed scan
            $stmt = $pdo->prepare("
                INSERT INTO business_purchase_qr_scans 
                (business_purchase_id, scan_result, scanner_ip, user_agent)
                VALUES (?, 'success', ?, ?)
            ");
            $stmt->execute([$purchase_id, $scanner_ip, $user_agent]);
            
        } catch (Exception $e) {
            error_log("Scan tracking error: " . $e->getMessage());
        }
    }
}
?> 