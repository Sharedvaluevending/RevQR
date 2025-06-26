<?php

class PromotionalAdsManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get promotional ads for a specific page
     */
    public function getAdsForPage($page = 'vote', $limit = 3) {
        $stmt = $this->pdo->prepare("
            SELECT 
                pa.*,
                b.name as business_name,
                b.logo_path as business_logo,
                bcp.casino_enabled,
                bcp.location_bonus_multiplier,
                bps.spin_wheel_promo_enabled,
                bps.pizza_tracker_promo_enabled
            FROM business_promotional_ads pa
            JOIN businesses b ON pa.business_id = b.id
            LEFT JOIN business_casino_participation bcp ON pa.business_id = bcp.business_id AND pa.feature_type = 'casino'
            LEFT JOIN business_promotional_settings bps ON pa.business_id = bps.business_id
            WHERE pa.is_active = TRUE 
                AND (
                    (? = 'vote' AND pa.show_on_vote_page = TRUE) OR
                    (? = 'dashboard' AND pa.show_on_dashboard = TRUE)
                )
                AND pa.daily_views_count < pa.max_daily_views
                AND (
                    (pa.feature_type = 'casino' AND bcp.casino_enabled = TRUE) OR
                    (pa.feature_type = 'spin_wheel' AND bps.spin_wheel_promo_enabled = TRUE) OR
                    (pa.feature_type = 'pizza_tracker' AND bps.pizza_tracker_promo_enabled = TRUE) OR
                    pa.feature_type = 'general'
                )
            ORDER BY pa.priority DESC, RAND()
            LIMIT ?
        ");
        $stmt->execute([$page, $page, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Track ad view
     */
    public function trackView($ad_id, $user_id = null, $page = 'vote') {
        $viewer_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $today = date('Y-m-d');
        
        // Insert view record
        $stmt = $this->pdo->prepare("
            INSERT INTO business_ad_views (ad_id, user_id, viewer_ip, page_viewed, view_date)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$ad_id, $user_id, $viewer_ip, $page, $today]);
        
        // Update daily view count
        $stmt = $this->pdo->prepare("
            UPDATE business_promotional_ads 
            SET daily_views_count = daily_views_count + 1,
                total_views = total_views + 1
            WHERE id = ?
        ");
        $stmt->execute([$ad_id]);
    }
    
    /**
     * Track ad click
     */
    public function trackClick($ad_id, $user_id = null) {
        $viewer_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $today = date('Y-m-d');
        
        // Update the most recent view record to mark as clicked
        $stmt = $this->pdo->prepare("
            UPDATE business_ad_views 
            SET clicked = TRUE 
            WHERE ad_id = ? AND viewer_ip = ? AND view_date = ?
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$ad_id, $viewer_ip, $today]);
        
        // Update total click count
        $stmt = $this->pdo->prepare("
            UPDATE business_promotional_ads 
            SET total_clicks = total_clicks + 1
            WHERE id = ?
        ");
        $stmt->execute([$ad_id]);
    }
    
    /**
     * Reset daily view counts (run via cron)
     */
    public function resetDailyViews() {
        $stmt = $this->pdo->prepare("
            UPDATE business_promotional_ads 
            SET daily_views_count = 0
        ");
        $stmt->execute();
    }
    
    /**
     * Get ad performance stats for business
     */
    public function getBusinessAdStats($business_id, $days = 30) {
        $stmt = $this->pdo->prepare("
            SELECT 
                pa.feature_type,
                pa.ad_title,
                COUNT(bav.id) as total_views,
                COUNT(CASE WHEN bav.clicked = TRUE THEN 1 END) as total_clicks,
                ROUND(
                    COUNT(CASE WHEN bav.clicked = TRUE THEN 1 END) * 100.0 / NULLIF(COUNT(bav.id), 0), 
                    2
                ) as click_rate
            FROM business_promotional_ads pa
            LEFT JOIN business_ad_views bav ON pa.id = bav.ad_id AND bav.view_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            WHERE pa.business_id = ?
            GROUP BY pa.id
            ORDER BY total_views DESC
        ");
        $stmt->execute([$days, $business_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Create or update promotional ad
     */
    public function createAd($business_id, $feature_type, $title, $description, $cta_text = 'Learn More', $cta_url = '', $options = []) {
        $background_color = $options['background_color'] ?? '#007bff';
        $text_color = $options['text_color'] ?? '#ffffff';
        $show_on_vote_page = ($options['show_on_vote_page'] ?? true) ? 1 : 0;
        $show_on_dashboard = ($options['show_on_dashboard'] ?? false) ? 1 : 0;
        $priority = $options['priority'] ?? 1;
        $max_daily_views = $options['max_daily_views'] ?? 1000;
        
        $stmt = $this->pdo->prepare("
            INSERT INTO business_promotional_ads 
            (business_id, feature_type, ad_title, ad_description, ad_cta_text, ad_cta_url, 
             background_color, text_color, show_on_vote_page, show_on_dashboard, priority, max_daily_views)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ad_title = VALUES(ad_title),
                ad_description = VALUES(ad_description),
                ad_cta_text = VALUES(ad_cta_text),
                ad_cta_url = VALUES(ad_cta_url),
                background_color = VALUES(background_color),
                text_color = VALUES(text_color),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([
            $business_id, $feature_type, $title, $description, $cta_text, $cta_url,
            $background_color, $text_color, $show_on_vote_page, $show_on_dashboard, $priority, $max_daily_views
        ]);
    }
}
?> 