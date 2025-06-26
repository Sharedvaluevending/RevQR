<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/business_utils.php';

// Require business role
require_role('business');

// Get business ID
$business_id = getOrCreateBusinessId($pdo, $_SESSION['user_id']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'generate_all_qr_codes':
        generateAllQRCodes($pdo, $business_id);
        break;
        
    case 'batch_print_by_type':
        batchPrintByType($pdo, $business_id);
        break;
        
    case 'export_qr_inventory':
        exportQRInventory($pdo, $business_id);
        break;
        
    case 'generate_print_report':
        generatePrintReport($pdo, $business_id);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function generateAllQRCodes($pdo, $business_id) {
    try {
        // Generate QR codes for all business machines that don't have them
        $stmt = $pdo->prepare("
            SELECT 
                vl.id as machine_id,
                vl.name as machine_name,
                vl.location,
                COUNT(qr.id) as qr_count
            FROM voting_lists vl
            LEFT JOIN qr_codes qr ON qr.machine_id = vl.id AND qr.status = 'active'
            WHERE vl.business_id = ?
            GROUP BY vl.id, vl.name, vl.location
            HAVING qr_count = 0
        ");
        $stmt->execute([$business_id]);
        $machines_without_qr = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $generated_count = 0;
        $errors = [];
        
        foreach ($machines_without_qr as $machine) {
            try {
                // Generate QR code for this machine
                $qr_code = generateUniqueCode();
                $content_url = buildMachineURL($machine['machine_id'], $machine['machine_name']);
                
                $stmt = $pdo->prepare("
                    INSERT INTO qr_codes (
                        code, url, qr_type, business_id, machine_id, 
                        machine_name, machine_location, status, created_at
                    ) VALUES (?, ?, 'machine_voting', ?, ?, ?, ?, 'active', NOW())
                ");
                
                $stmt->execute([
                    $qr_code,
                    $content_url,
                    $business_id,
                    $machine['machine_id'],
                    $machine['machine_name'],
                    $machine['location']
                ]);
                
                $generated_count++;
                
            } catch (Exception $e) {
                $errors[] = "Failed to generate QR for machine '{$machine['machine_name']}': " . $e->getMessage();
            }
        }
        
        echo json_encode([
            'success' => true,
            'generated_count' => $generated_count,
            'total_machines' => count($machines_without_qr),
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function batchPrintByType($pdo, $business_id) {
    try {
        $qr_type = $_POST['qr_type'] ?? 'all';
        $template = $_POST['template'] ?? 'avery_5658';
        
        $where_clause = "WHERE qr.business_id = ? AND qr.status = 'active'";
        $params = [$business_id];
        
        if ($qr_type !== 'all') {
            $where_clause .= " AND qr.qr_type = ?";
            $params[] = $qr_type;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                qr.*,
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
                    CONCAT('/uploads/qr/', qr.code, '.png')
                ) as qr_url,
                COALESCE(qr.machine_name, 'QR Code') as display_name
            FROM qr_codes qr
            $where_clause
            ORDER BY qr.qr_type, qr.machine_name
        ");
        $stmt->execute($params);
        $qr_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($qr_codes)) {
            echo json_encode(['success' => false, 'error' => 'No QR codes found for specified type']);
            return;
        }
        
        // Group by type for organized printing
        $grouped_qr_codes = [];
        foreach ($qr_codes as $qr) {
            $grouped_qr_codes[$qr['qr_type']][] = $qr;
        }
        
        echo json_encode([
            'success' => true,
            'qr_codes' => $qr_codes,
            'grouped_codes' => $grouped_qr_codes,
            'total_count' => count($qr_codes),
            'template' => $template
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function exportQRInventory($pdo, $business_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                qr.code,
                qr.qr_type,
                qr.machine_name,
                qr.machine_location,
                qr.status,
                qr.created_at,
                qr.url,
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(qr.meta, '$.file_path')),
                    CONCAT('/uploads/qr/', qr.code, '.png')
                ) as qr_url,
                (SELECT COUNT(*) FROM qr_code_stats WHERE qr_code_id = qr.id) as scan_count
            FROM qr_codes qr
            WHERE qr.business_id = ?
            ORDER BY qr.qr_type, qr.machine_name, qr.created_at DESC
        ");
        $stmt->execute([$business_id]);
        $qr_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate CSV content
        $csv_content = "QR Code,Type,Machine Name,Location,Status,Created Date,Scan Count,URL\n";
        
        foreach ($qr_codes as $qr) {
            $csv_content .= sprintf(
                "%s,%s,%s,%s,%s,%s,%d,%s\n",
                $qr['code'],
                $qr['qr_type'],
                $qr['machine_name'] ?: 'N/A',
                $qr['machine_location'] ?: 'N/A',
                $qr['status'],
                date('Y-m-d', strtotime($qr['created_at'])),
                $qr['scan_count'],
                $qr['url']
            );
        }
        
        // Save CSV file
        $filename = 'qr_inventory_' . date('Y-m-d_H-i-s') . '.csv';
        $file_path = __DIR__ . '/../../uploads/temp/' . $filename;
        
        // Ensure temp directory exists
        $temp_dir = dirname($file_path);
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }
        
        file_put_contents($file_path, $csv_content);
        
        echo json_encode([
            'success' => true,
            'download_url' => '/uploads/temp/' . $filename,
            'filename' => $filename,
            'total_count' => count($qr_codes)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function generatePrintReport($pdo, $business_id) {
    try {
        // Get comprehensive statistics
        $stats = [];
        
        // Total QR codes by type
        $stmt = $pdo->prepare("
            SELECT 
                qr_type,
                COUNT(*) as count,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
            FROM qr_codes 
            WHERE business_id = ?
            GROUP BY qr_type
        ");
        $stmt->execute([$business_id]);
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Scan statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT qr.id) as qr_with_scans,
                SUM(scan_stats.scan_count) as total_scans,
                AVG(scan_stats.scan_count) as avg_scans_per_qr
            FROM qr_codes qr
            LEFT JOIN (
                SELECT qr_code_id, COUNT(*) as scan_count
                FROM qr_code_stats
                GROUP BY qr_code_id
            ) scan_stats ON qr.id = scan_stats.qr_code_id
            WHERE qr.business_id = ?
        ");
        $stmt->execute([$business_id]);
        $stats['scan_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Recent activity
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as qr_created
            FROM qr_codes 
            WHERE business_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 10
        ");
        $stmt->execute([$business_id]);
        $stats['recent_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Machines without QR codes
        $stmt = $pdo->prepare("
            SELECT vl.name, vl.location
            FROM voting_lists vl
            LEFT JOIN qr_codes qr ON qr.machine_id = vl.id AND qr.status = 'active'
            WHERE vl.business_id = ? AND qr.id IS NULL
        ");
        $stmt->execute([$business_id]);
        $stats['missing_qr'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function generateUniqueCode() {
    return 'QR' . strtoupper(bin2hex(random_bytes(8)));
}

function buildMachineURL($machine_id, $machine_name) {
    $base_url = defined('APP_URL') ? APP_URL : 'https://revenueqr.sharedvaluevending.com';
    return $base_url . '/html/user/vote.php?machine_id=' . $machine_id;
}
?> 