<?php
/**
 * Nayax QR Code Generator
 * Generates QR codes for vending machines that redirect users to the discount store
 * 
 * @author RevenueQR Team
 * @version 1.0
 * @date 2025-01-17
 */

require_once __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\Label\LabelAlignment\LabelAlignmentCenter;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;

class NayaxQRGenerator {
    
    private $base_url;
    private $storage_path;
    
    public function __construct($base_url = null) {
        $this->base_url = $base_url ?: 'https://' . $_SERVER['HTTP_HOST'];
        $this->storage_path = __DIR__ . '/../uploads/qr/nayax/';
        
        // Create storage directory if it doesn't exist
        if (!is_dir($this->storage_path)) {
            mkdir($this->storage_path, 0755, true);
        }
    }
    
    /**
     * Generate QR code for a specific Nayax machine
     */
    public function generateMachineQR($business_id, $nayax_machine_id, $machine_name = null, $custom_params = []) {
        try {
            // Build the URL that users will be redirected to
            $redirect_url = $this->buildRedirectURL($business_id, $nayax_machine_id, $custom_params);
            
            // Generate filename
            $filename = "nayax_machine_{$nayax_machine_id}_" . date('Y_m_d') . ".png";
            $file_path = $this->storage_path . $filename;
            
            // Build QR code with branding
            $result = Builder::create()
                ->writer(new PngWriter())
                ->writerOptions([])
                ->data($redirect_url)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(new ErrorCorrectionLevelLow())
                ->size(300)
                ->margin(10)
                ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->logoPath(__DIR__ . '/../assets/img/logos/revenueqr_logo_small.png') // If logo exists
                ->logoResizeToWidth(50)
                ->logoResizeToHeight(50)
                ->labelText($machine_name ?: "Scan for Discounts!")
                ->labelFont(new NotoSans(16))
                ->labelAlignment(new LabelAlignmentCenter())
                ->validateResult(false)
                ->build();
            
            // Save QR code to file
            $result->saveToFile($file_path);
            
            // Also save web-accessible version
            $web_path = "/uploads/qr/nayax/" . $filename;
            
            return [
                'success' => true,
                'qr_code_url' => $redirect_url,
                'file_path' => $file_path,
                'web_path' => $web_path,
                'filename' => $filename,
                'machine_id' => $nayax_machine_id,
                'business_id' => $business_id
            ];
            
        } catch (Exception $e) {
            error_log("NayaxQRGenerator::generateMachineQR() error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Build the redirect URL for the QR code
     */
    private function buildRedirectURL($business_id, $nayax_machine_id, $custom_params = []) {
        $params = array_merge([
            'source' => 'nayax_qr',
            'business_id' => $business_id,
            'machine_id' => $nayax_machine_id,
            'timestamp' => time()
        ], $custom_params);
        
        $query_string = http_build_query($params);
        return $this->base_url . "/html/nayax/discount-store?" . $query_string;
    }
    
    /**
     * Generate QR code for general Nayax discount store
     */
    public function generateDiscountStoreQR($business_id = null, $custom_message = null) {
        try {
            $params = ['source' => 'nayax_general'];
            if ($business_id) {
                $params['business_id'] = $business_id;
            }
            
            $redirect_url = $this->base_url . "/html/nayax/discount-store?" . http_build_query($params);
            
            $filename = "nayax_discount_store_" . ($business_id ?: 'general') . "_" . date('Y_m_d') . ".png";
            $file_path = $this->storage_path . $filename;
            
            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($redirect_url)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(new ErrorCorrectionLevelLow())
                ->size(400)
                ->margin(15)
                ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->logoPath(__DIR__ . '/../assets/img/logos/revenueqr_logo_small.png')
                ->logoResizeToWidth(60)
                ->logoResizeToHeight(60)
                ->labelText($custom_message ?: "Scan for QR Coin Discounts!")
                ->labelFont(new NotoSans(18))
                ->labelAlignment(new LabelAlignmentCenter())
                ->validateResult(false)
                ->build();
            
            $result->saveToFile($file_path);
            
            return [
                'success' => true,
                'qr_code_url' => $redirect_url,
                'file_path' => $file_path,
                'web_path' => "/uploads/qr/nayax/" . $filename,
                'filename' => $filename
            ];
            
        } catch (Exception $e) {
            error_log("NayaxQRGenerator::generateDiscountStoreQR() error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get existing QR code for a machine
     */
    public function getMachineQR($nayax_machine_id) {
        $pattern = $this->storage_path . "nayax_machine_{$nayax_machine_id}_*.png";
        $files = glob($pattern);
        
        if (!empty($files)) {
            // Get the most recent file
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            $file_path = $files[0];
            $filename = basename($file_path);
            
            return [
                'exists' => true,
                'file_path' => $file_path,
                'web_path' => "/uploads/qr/nayax/" . $filename,
                'filename' => $filename,
                'created_at' => date('Y-m-d H:i:s', filemtime($file_path))
            ];
        }
        
        return ['exists' => false];
    }
    
    /**
     * Generate batch QR codes for all machines
     */
    public function generateBatchQRCodes($business_id = null) {
        try {
            global $pdo;
            
            $where_clause = "WHERE status = 'active'";
            $params = [];
            
            if ($business_id) {
                $where_clause .= " AND business_id = ?";
                $params[] = $business_id;
            }
            
            $stmt = $pdo->prepare("
                SELECT nm.*, b.name as business_name 
                FROM nayax_machines nm
                JOIN businesses b ON nm.business_id = b.id
                {$where_clause}
                ORDER BY b.name, nm.machine_name
            ");
            $stmt->execute($params);
            $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [];
            $success_count = 0;
            $error_count = 0;
            
            foreach ($machines as $machine) {
                $result = $this->generateMachineQR(
                    $machine['business_id'],
                    $machine['nayax_machine_id'],
                    $machine['machine_name'] . " - " . $machine['business_name']
                );
                
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                
                $results[] = [
                    'machine_name' => $machine['machine_name'],
                    'business_name' => $machine['business_name'],
                    'nayax_machine_id' => $machine['nayax_machine_id'],
                    'result' => $result
                ];
            }
            
            return [
                'success' => true,
                'total_machines' => count($machines),
                'success_count' => $success_count,
                'error_count' => $error_count,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            error_log("NayaxQRGenerator::generateBatchQRCodes() error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up old QR codes
     */
    public function cleanupOldQRCodes($days_old = 30) {
        try {
            $cutoff_time = time() - ($days_old * 24 * 60 * 60);
            $files = glob($this->storage_path . "*.png");
            $deleted_count = 0;
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff_time) {
                    if (unlink($file)) {
                        $deleted_count++;
                    }
                }
            }
            
            return [
                'success' => true,
                'deleted_count' => $deleted_count,
                'message' => "Cleaned up {$deleted_count} old QR codes"
            ];
            
        } catch (Exception $e) {
            error_log("NayaxQRGenerator::cleanupOldQRCodes() error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate QR code analytics report
     */
    public function getQRAnalytics() {
        try {
            $files = glob($this->storage_path . "*.png");
            $analytics = [
                'total_qr_codes' => count($files),
                'by_type' => [
                    'machine_specific' => 0,
                    'general_store' => 0
                ],
                'by_date' => [],
                'file_sizes' => []
            ];
            
            foreach ($files as $file) {
                $filename = basename($file);
                $created_date = date('Y-m-d', filemtime($file));
                
                // Count by type
                if (strpos($filename, 'machine_') !== false) {
                    $analytics['by_type']['machine_specific']++;
                } elseif (strpos($filename, 'discount_store') !== false) {
                    $analytics['by_type']['general_store']++;
                }
                
                // Count by date
                if (!isset($analytics['by_date'][$created_date])) {
                    $analytics['by_date'][$created_date] = 0;
                }
                $analytics['by_date'][$created_date]++;
                
                // File size
                $analytics['file_sizes'][] = filesize($file);
            }
            
            // Calculate average file size
            if (!empty($analytics['file_sizes'])) {
                $analytics['avg_file_size_kb'] = round(array_sum($analytics['file_sizes']) / count($analytics['file_sizes']) / 1024, 2);
            }
            
            return $analytics;
            
        } catch (Exception $e) {
            error_log("NayaxQRGenerator::getQRAnalytics() error: " . $e->getMessage());
            return [];
        }
    }
}
?> 