<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';

// Ensure user is logged in and is a business user
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'business') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$business_id = $_SESSION['business_id'];
$action = $_POST['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'add_promotional_ad':
            $ad_title = trim($_POST['ad_title'] ?? '');
            $ad_description = trim($_POST['ad_description'] ?? '');
            $background_color = $_POST['background_color'] ?? '#007bff';
            $text_color = '#ffffff'; // Default white text
            $cta_text = trim($_POST['cta_text'] ?? '');
            $cta_url = trim($_POST['cta_url'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($ad_title) || empty($ad_description)) {
                echo json_encode(['success' => false, 'message' => 'Ad title and description are required']);
                exit;
            }
            
            // Create promotional ads table if it doesn't exist
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS business_promotional_ads (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    business_id INT NOT NULL,
                    ad_title VARCHAR(255) NOT NULL,
                    ad_description TEXT NOT NULL,
                    background_color VARCHAR(7) DEFAULT '#007bff',
                    text_color VARCHAR(7) DEFAULT '#ffffff',
                    cta_text VARCHAR(100),
                    cta_url VARCHAR(500),
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
                )
            ");
            
            $stmt = $pdo->prepare("
                INSERT INTO business_promotional_ads 
                (business_id, ad_title, ad_description, background_color, text_color, cta_text, cta_url, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$business_id, $ad_title, $ad_description, $background_color, $text_color, $cta_text, $cta_url, $is_active]);
            
            echo json_encode(['success' => true, 'message' => 'Promotional ad added successfully']);
            break;
            
        case 'toggle_promotional_ads':
            // Create business settings table if it doesn't exist
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS business_feature_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    business_id INT NOT NULL,
                    feature_name VARCHAR(100) NOT NULL,
                    is_enabled TINYINT(1) DEFAULT 1,
                    settings JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_business_feature (business_id, feature_name),
                    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
                )
            ");
            
            // Toggle promotional ads visibility
            $stmt = $pdo->prepare("
                INSERT INTO business_feature_settings (business_id, feature_name, is_enabled)
                VALUES (?, 'promotional_ads', 0)
                ON DUPLICATE KEY UPDATE 
                is_enabled = NOT is_enabled,
                updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$business_id]);
            
            echo json_encode(['success' => true, 'message' => 'Promotional ads visibility toggled']);
            break;
            
        case 'enable_spin_wheel':
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS business_feature_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    business_id INT NOT NULL,
                    feature_name VARCHAR(100) NOT NULL,
                    is_enabled TINYINT(1) DEFAULT 1,
                    settings JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_business_feature (business_id, feature_name),
                    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
                )
            ");
            
            $stmt = $pdo->prepare("
                INSERT INTO business_feature_settings (business_id, feature_name, is_enabled, settings)
                VALUES (?, 'spin_wheel', 1, JSON_OBJECT('rewards', JSON_ARRAY('QR Coins', 'Free Vote', 'Bonus Spin')))
                ON DUPLICATE KEY UPDATE 
                is_enabled = 1,
                updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$business_id]);
            
            echo json_encode(['success' => true, 'message' => 'Spin wheel enabled successfully']);
            break;
            
        case 'disable_spin_wheel':
            $stmt = $pdo->prepare("
                INSERT INTO business_feature_settings (business_id, feature_name, is_enabled)
                VALUES (?, 'spin_wheel', 0)
                ON DUPLICATE KEY UPDATE 
                is_enabled = 0,
                updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$business_id]);
            
            echo json_encode(['success' => true, 'message' => 'Spin wheel disabled successfully']);
            break;
            
        case 'enable_pizza_tracker':
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS business_feature_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    business_id INT NOT NULL,
                    feature_name VARCHAR(100) NOT NULL,
                    is_enabled TINYINT(1) DEFAULT 1,
                    settings JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_business_feature (business_id, feature_name),
                    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
                )
            ");
            
            $stmt = $pdo->prepare("
                INSERT INTO business_feature_settings (business_id, feature_name, is_enabled, settings)
                VALUES (?, 'pizza_tracker', 1, JSON_OBJECT('tracking_url', '', 'display_name', 'Pizza Tracker'))
                ON DUPLICATE KEY UPDATE 
                is_enabled = 1,
                updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$business_id]);
            
            echo json_encode(['success' => true, 'message' => 'Pizza tracker enabled successfully']);
            break;
            
        case 'disable_pizza_tracker':
            $stmt = $pdo->prepare("
                INSERT INTO business_feature_settings (business_id, feature_name, is_enabled)
                VALUES (?, 'pizza_tracker', 0)
                ON DUPLICATE KEY UPDATE 
                is_enabled = 0,
                updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$business_id]);
            
            echo json_encode(['success' => true, 'message' => 'Pizza tracker disabled successfully']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Promotional features management error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?> 