<?php
/**
 * Configuration Manager for QR Coin Economy 2.0
 * Provides dynamic configuration management for economic settings
 * 
 * @author QR Coin Economy Team
 * @version 1.0
 * @date 2025-01-17
 */

class ConfigManager {
    private static $cache = [];
    private static $cache_lifetime = 3600; // 1 hour cache
    private static $cache_loaded = false;
    
    /**
     * Get a configuration value with type casting
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value with proper type casting
     */
    public static function get($key, $default = null) {
        global $pdo;
        
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        try {
            $stmt = $pdo->prepare("SELECT setting_value, setting_type FROM config_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            if (!$result) {
                self::$cache[$key] = $default;
                return $default;
            }
            
            $value = self::castValue($result['setting_value'], $result['setting_type']);
            self::$cache[$key] = $value;
            return $value;
            
        } catch (PDOException $e) {
            error_log("ConfigManager::get() error for key '$key': " . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Set a configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @param string $type Value type (string, int, float, boolean, json)
     * @param string $description Optional description
     * @return bool Success status
     */
    public static function set($key, $value, $type = 'string', $description = null) {
        global $pdo;
        
        try {
            // Validate and prepare value based on type
            $prepared_value = self::prepareValue($value, $type);
            
            $stmt = $pdo->prepare("
                INSERT INTO config_settings (setting_key, setting_value, setting_type, description) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    setting_type = VALUES(setting_type),
                    description = COALESCE(VALUES(description), description),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $result = $stmt->execute([$key, $prepared_value, $type, $description]);
            
            // Clear cache for this key
            unset(self::$cache[$key]);
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("ConfigManager::set() error for key '$key': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get multiple configuration values at once
     * 
     * @param array $keys Array of configuration keys
     * @return array Associative array of key => value pairs
     */
    public static function getMultiple($keys) {
        global $pdo;
        
        if (empty($keys)) {
            return [];
        }
        
        $results = [];
        $uncached_keys = [];
        
        // Check cache first
        foreach ($keys as $key) {
            if (isset(self::$cache[$key])) {
                $results[$key] = self::$cache[$key];
            } else {
                $uncached_keys[] = $key;
            }
        }
        
        // Fetch uncached keys from database
        if (!empty($uncached_keys)) {
            try {
                $placeholders = str_repeat('?,', count($uncached_keys) - 1) . '?';
                $stmt = $pdo->prepare("
                    SELECT setting_key, setting_value, setting_type 
                    FROM config_settings 
                    WHERE setting_key IN ($placeholders)
                ");
                $stmt->execute($uncached_keys);
                
                while ($row = $stmt->fetch()) {
                    $value = self::castValue($row['setting_value'], $row['setting_type']);
                    $results[$row['setting_key']] = $value;
                    self::$cache[$row['setting_key']] = $value;
                }
                
            } catch (PDOException $e) {
                error_log("ConfigManager::getMultiple() error: " . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Get all economic settings
     * 
     * @return array Economic configuration values
     */
    public static function getEconomicSettings() {
        return self::getMultiple([
            'qr_coin_vote_base',
            'qr_coin_spin_base', 
            'qr_coin_vote_bonus',
            'qr_coin_spin_bonus',
            'qr_coin_decay_rate',
            'qr_coin_decay_threshold',
            'economy_mode'
        ]);
    }
    
    /**
     * Get subscription pricing for all tiers
     * 
     * @return array Subscription pricing information
     */
    public static function getSubscriptionPricing() {
        return [
            'starter' => [
                'monthly_cents' => self::get('subscription_starter_monthly', 4900),
                'qr_coins' => self::get('subscription_starter_coins', 1000),
                'machines' => self::get('subscription_starter_machines', 3)
            ],
            'professional' => [
                'monthly_cents' => self::get('subscription_professional_monthly', 14900),
                'qr_coins' => self::get('subscription_professional_coins', 3000),
                'machines' => self::get('subscription_professional_machines', 10)
            ],
            'enterprise' => [
                'monthly_cents' => self::get('subscription_enterprise_monthly', 39900),
                'qr_coins' => self::get('subscription_enterprise_coins', 8000),
                'machines' => self::get('subscription_enterprise_machines', 999)
            ]
        ];
    }
    
    /**
     * Check if a feature is enabled
     * 
     * @param string $feature Feature name
     * @return bool Whether feature is enabled
     */
    public static function isEnabled($feature) {
        $value = self::get($feature . '_enabled', false);
        return (bool) $value;
    }
    
    /**
     * Clear configuration cache
     * 
     * @param string|null $key Specific key to clear, or null for all
     */
    public static function clearCache($key = null) {
        if ($key === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$key]);
        }
    }
    
    /**
     * Cast string value to appropriate type
     * 
     * @param string $value Raw value from database
     * @param string $type Target type
     * @return mixed Properly typed value
     */
    private static function castValue($value, $type) {
        switch ($type) {
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true);
            case 'string':
            default:
                return $value;
        }
    }
    
    /**
     * Prepare value for database storage
     * 
     * @param mixed $value Value to prepare
     * @param string $type Target type
     * @return string Prepared value for database
     */
    private static function prepareValue($value, $type) {
        switch ($type) {
            case 'int':
                return (string) (int) $value;
            case 'float':
                return (string) (float) $value;
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'json':
                return json_encode($value);
            case 'string':
            default:
                return (string) $value;
        }
    }
    
    /**
     * Get configuration settings by prefix
     * 
     * @param string $prefix Setting key prefix
     * @return array Settings matching the prefix
     */
    public static function getByPrefix($prefix) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT setting_key, setting_value, setting_type 
                FROM config_settings 
                WHERE setting_key LIKE ?
                ORDER BY setting_key
            ");
            $stmt->execute([$prefix . '%']);
            
            $results = [];
            while ($row = $stmt->fetch()) {
                $value = self::castValue($row['setting_value'], $row['setting_type']);
                $results[$row['setting_key']] = $value;
                self::$cache[$row['setting_key']] = $value; // Cache it
            }
            
            return $results;
            
        } catch (PDOException $e) {
            error_log("ConfigManager::getByPrefix() error for prefix '$prefix': " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update economic settings safely
     * 
     * @param array $settings Associative array of setting_key => value
     * @return bool Success status
     */
    public static function updateEconomicSettings($settings) {
        global $pdo;
        
        $allowed_keys = [
            'qr_coin_vote_base', 'qr_coin_spin_base', 
            'qr_coin_vote_bonus', 'qr_coin_spin_bonus',
            'qr_coin_decay_rate', 'qr_coin_decay_threshold'
        ];
        
        try {
            $pdo->beginTransaction();
            
            foreach ($settings as $key => $value) {
                if (!in_array($key, $allowed_keys)) {
                    continue; // Skip invalid keys
                }
                
                $type = (strpos($key, 'rate') !== false) ? 'float' : 'int';
                self::set($key, $value, $type);
            }
            
            $pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $pdo->rollback();
            error_log("ConfigManager::updateEconomicSettings() error: " . $e->getMessage());
            return false;
        }
    }
}

// Helper functions for backwards compatibility
function get_config($key, $default = null) {
    return ConfigManager::get($key, $default);
}

function set_config($key, $value, $type = 'string') {
    return ConfigManager::set($key, $value, $type);
}
?> 