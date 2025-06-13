-- Pizza Tracker Phase 3 & 4 Database Schema
-- Advanced features including notifications, webhooks, API integration, and enhanced analytics

-- API Keys for third-party integration
CREATE TABLE IF NOT EXISTS api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    api_key VARCHAR(64) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    scopes JSON DEFAULT NULL,
    rate_limit_per_hour INT DEFAULT 1000,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_api_key (api_key),
    INDEX idx_business_active (business_id, is_active),
    INDEX idx_expires_at (expires_at)
);

-- Notification preferences for businesses
CREATE TABLE IF NOT EXISTS pizza_tracker_notification_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL UNIQUE,
    email_enabled BOOLEAN DEFAULT FALSE,
    sms_enabled BOOLEAN DEFAULT FALSE,
    push_enabled BOOLEAN DEFAULT FALSE,
    email_addresses TEXT,
    phone_numbers TEXT,
    milestones JSON DEFAULT '[25, 50, 75, 90, 100]',
    notification_hours JSON DEFAULT '{"start": "09:00", "end": "21:00"}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- Notification history and logging
CREATE TABLE IF NOT EXISTS pizza_tracker_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tracker_id INT NOT NULL,
    milestone INT NOT NULL,
    notification_type ENUM('email', 'sms', 'push', 'webhook') NOT NULL,
    recipient VARCHAR(255),
    message_subject VARCHAR(255),
    message_body TEXT,
    sent_successfully BOOLEAN DEFAULT FALSE,
    error_message TEXT,
    external_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tracker_id) REFERENCES pizza_trackers(id) ON DELETE CASCADE,
    INDEX idx_tracker_milestone (tracker_id, milestone),
    INDEX idx_created_at (created_at),
    INDEX idx_notification_type (notification_type)
);

-- Webhook configurations
CREATE TABLE IF NOT EXISTS pizza_tracker_webhooks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    events JSON NOT NULL,
    secret VARCHAR(255) NOT NULL,
    headers JSON DEFAULT NULL,
    timeout_seconds INT DEFAULT 30,
    retry_attempts INT DEFAULT 3,
    is_active BOOLEAN DEFAULT TRUE,
    last_triggered_at TIMESTAMP NULL,
    total_triggers INT DEFAULT 0,
    success_count INT DEFAULT 0,
    failure_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_active (business_id, is_active)
);

-- Webhook delivery logs
CREATE TABLE IF NOT EXISTS pizza_tracker_webhook_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    webhook_id INT NOT NULL,
    tracker_id INT,
    event_type VARCHAR(50) NOT NULL,
    payload JSON NOT NULL,
    response_status INT,
    response_body TEXT,
    response_time_ms INT,
    attempt_number INT DEFAULT 1,
    delivered_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES pizza_tracker_webhooks(id) ON DELETE CASCADE,
    FOREIGN KEY (tracker_id) REFERENCES pizza_trackers(id) ON DELETE SET NULL,
    INDEX idx_webhook_event (webhook_id, event_type),
    INDEX idx_created_at (created_at),
    INDEX idx_status (response_status)
);

-- Enhanced analytics tracking
CREATE TABLE IF NOT EXISTS pizza_tracker_analytics_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tracker_id INT NOT NULL,
    event_type ENUM('view', 'click', 'scan', 'milestone', 'completion', 'share') NOT NULL,
    source VARCHAR(50),
    user_agent TEXT,
    ip_address VARCHAR(45),
    referrer TEXT,
    session_id VARCHAR(100),
    user_id INT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tracker_id) REFERENCES pizza_trackers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tracker_type_date (tracker_id, event_type, created_at),
    INDEX idx_session (session_id),
    INDEX idx_created_at (created_at)
);

-- User sessions for analytics
CREATE TABLE IF NOT EXISTS pizza_tracker_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(100) UNIQUE NOT NULL,
    tracker_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    page_views INT DEFAULT 1,
    duration_seconds INT DEFAULT 0,
    is_bounce BOOLEAN DEFAULT TRUE,
    referrer TEXT,
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100),
    utm_campaign VARCHAR(100),
    FOREIGN KEY (tracker_id) REFERENCES pizza_trackers(id) ON DELETE CASCADE,
    INDEX idx_tracker_date (tracker_id, started_at),
    INDEX idx_session_id (session_id)
);

-- Revenue tracking with detailed metadata
CREATE TABLE IF NOT EXISTS pizza_tracker_revenue_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tracker_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    source ENUM('manual', 'api', 'pos_sync', 'order_sync', 'webhook') DEFAULT 'manual',
    external_id VARCHAR(100),
    order_id VARCHAR(100),
    customer_id VARCHAR(100),
    payment_method VARCHAR(50),
    currency VARCHAR(3) DEFAULT 'USD',
    description TEXT,
    metadata JSON DEFAULT NULL,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tracker_id) REFERENCES pizza_trackers(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tracker_date (tracker_id, created_at),
    INDEX idx_external_id (external_id),
    INDEX idx_source (source)
);

-- Multi-language support
CREATE TABLE IF NOT EXISTS pizza_tracker_translations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tracker_id INT NOT NULL,
    language_code VARCHAR(5) NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    translated_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tracker_id) REFERENCES pizza_trackers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_translation (tracker_id, language_code, field_name),
    INDEX idx_tracker_lang (tracker_id, language_code)
);

-- Push notification tokens
CREATE TABLE IF NOT EXISTS user_push_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    platform ENUM('web', 'android', 'ios') NOT NULL,
    device_info JSON DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_token (user_id, token),
    INDEX idx_user_active (user_id, is_active)
);

-- Predictive analytics data
CREATE TABLE IF NOT EXISTS pizza_tracker_predictions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tracker_id INT NOT NULL,
    prediction_type ENUM('completion_date', 'revenue_forecast', 'milestone_eta') NOT NULL,
    predicted_value JSON NOT NULL,
    confidence_score DECIMAL(3,2),
    input_data JSON NOT NULL,
    algorithm_version VARCHAR(20),
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valid_until TIMESTAMP,
    FOREIGN KEY (tracker_id) REFERENCES pizza_trackers(id) ON DELETE CASCADE,
    INDEX idx_tracker_type (tracker_id, prediction_type),
    INDEX idx_generated_at (generated_at)
);

-- A/B testing for tracker displays
CREATE TABLE IF NOT EXISTS pizza_tracker_ab_tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tracker_id INT NOT NULL,
    test_name VARCHAR(100) NOT NULL,
    variant_a JSON NOT NULL,
    variant_b JSON NOT NULL,
    traffic_split DECIMAL(3,2) DEFAULT 0.50,
    start_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    winner_variant CHAR(1),
    statistical_significance DECIMAL(5,4),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tracker_id) REFERENCES pizza_trackers(id) ON DELETE CASCADE,
    INDEX idx_tracker_active (tracker_id, is_active)
);

-- A/B test participation tracking
CREATE TABLE IF NOT EXISTS pizza_tracker_ab_participants (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    test_id INT NOT NULL,
    session_id VARCHAR(100) NOT NULL,
    variant CHAR(1) NOT NULL,
    converted BOOLEAN DEFAULT FALSE,
    conversion_value DECIMAL(10,2),
    participated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES pizza_tracker_ab_tests(id) ON DELETE CASCADE,
    INDEX idx_test_variant (test_id, variant),
    INDEX idx_session (session_id)
);

-- External integrations (POS, ordering systems, etc.)
CREATE TABLE IF NOT EXISTS pizza_tracker_integrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    integration_type ENUM('pos', 'ordering', 'inventory', 'crm', 'marketing') NOT NULL,
    provider_name VARCHAR(100) NOT NULL,
    api_endpoint VARCHAR(500),
    api_credentials JSON,
    sync_frequency ENUM('realtime', 'hourly', 'daily', 'weekly') DEFAULT 'daily',
    last_sync_at TIMESTAMP NULL,
    sync_status ENUM('success', 'error', 'pending') DEFAULT 'pending',
    error_message TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    settings JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_type (business_id, integration_type),
    INDEX idx_sync_status (sync_status)
);

-- Sync logs for external integrations
CREATE TABLE IF NOT EXISTS pizza_tracker_sync_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    integration_id INT NOT NULL,
    sync_type ENUM('full', 'incremental', 'manual') NOT NULL,
    records_processed INT DEFAULT 0,
    records_success INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    status ENUM('running', 'completed', 'failed') DEFAULT 'running',
    error_details JSON DEFAULT NULL,
    FOREIGN KEY (integration_id) REFERENCES pizza_tracker_integrations(id) ON DELETE CASCADE,
    INDEX idx_integration_date (integration_id, started_at),
    INDEX idx_status (status)
);

-- Advanced reporting and business intelligence
CREATE TABLE IF NOT EXISTS pizza_tracker_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    report_name VARCHAR(100) NOT NULL,
    report_type ENUM('performance', 'engagement', 'revenue', 'predictions', 'custom') NOT NULL,
    parameters JSON NOT NULL,
    schedule_cron VARCHAR(100),
    email_recipients TEXT,
    last_generated_at TIMESTAMP NULL,
    next_scheduled_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_business_type (business_id, report_type),
    INDEX idx_schedule (next_scheduled_at, is_active)
);

-- Generated report instances
CREATE TABLE IF NOT EXISTS pizza_tracker_report_instances (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    report_id INT NOT NULL,
    file_path VARCHAR(500),
    file_size_bytes BIGINT,
    generation_time_ms INT,
    parameters_used JSON,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    download_count INT DEFAULT 0,
    FOREIGN KEY (report_id) REFERENCES pizza_tracker_reports(id) ON DELETE CASCADE,
    INDEX idx_report_date (report_id, generated_at),
    INDEX idx_expires_at (expires_at)
);

-- Rate limiting for API endpoints
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    api_key_id INT NOT NULL,
    endpoint VARCHAR(200) NOT NULL,
    request_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    window_end TIMESTAMP,
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE,
    INDEX idx_key_endpoint_window (api_key_id, endpoint, window_start),
    INDEX idx_window_end (window_end)
);

-- Audit log for all pizza tracker activities
CREATE TABLE IF NOT EXISTS pizza_tracker_audit_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tracker_id INT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tracker_id) REFERENCES pizza_trackers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tracker_action_date (tracker_id, action, created_at),
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_entity (entity_type, entity_id)
);

-- Add some sample data for testing
INSERT IGNORE INTO pizza_tracker_notification_preferences (business_id, email_enabled, email_addresses) 
SELECT id, TRUE, 'test@example.com' FROM businesses LIMIT 1;

-- Create indexes for performance optimization
CREATE INDEX IF NOT EXISTS idx_pizza_trackers_business_active ON pizza_trackers(business_id, is_active);
CREATE INDEX IF NOT EXISTS idx_pizza_tracker_analytics_tracker_date ON pizza_tracker_analytics(tracker_id, created_at);
CREATE INDEX IF NOT EXISTS idx_pizza_tracker_stages_tracker_order ON pizza_tracker_stages(tracker_id, stage_order);

-- Update existing pizza_trackers table for enhanced features
ALTER TABLE pizza_trackers 
ADD COLUMN IF NOT EXISTS auto_reset BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS reset_frequency ENUM('daily', 'weekly', 'monthly', 'quarterly') DEFAULT 'monthly',
ADD COLUMN IF NOT EXISTS last_reset_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS timezone VARCHAR(50) DEFAULT 'UTC',
ADD COLUMN IF NOT EXISTS currency VARCHAR(3) DEFAULT 'USD',
ADD COLUMN IF NOT EXISTS external_id VARCHAR(100),
ADD COLUMN IF NOT EXISTS integration_source VARCHAR(50),
ADD COLUMN IF NOT EXISTS ab_test_variant CHAR(1),
ADD INDEX IF NOT EXISTS idx_external_id (external_id),
ADD INDEX IF NOT EXISTS idx_integration_source (integration_source);

-- Update pizza_tracker_stages for enhanced tracking
ALTER TABLE pizza_tracker_stages
ADD COLUMN IF NOT EXISTS estimated_duration_hours INT,
ADD COLUMN IF NOT EXISTS actual_duration_hours INT,
ADD COLUMN IF NOT EXISTS assigned_to INT,
ADD COLUMN IF NOT EXISTS priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
ADD COLUMN IF NOT EXISTS external_id VARCHAR(100),
ADD FOREIGN KEY IF NOT EXISTS fk_assigned_to (assigned_to) REFERENCES users(id) ON DELETE SET NULL;

-- Views for common analytics queries
CREATE OR REPLACE VIEW pizza_tracker_analytics_summary AS
SELECT 
    pt.id,
    pt.name,
    pt.business_id,
    pt.current_revenue,
    pt.revenue_goal,
    pt.progress_percent,
    pt.completion_count,
    COUNT(DISTINCT ptas.session_id) as unique_visitors,
    COUNT(ptae.id) as total_events,
    AVG(ptas.duration_seconds) as avg_session_duration,
    SUM(CASE WHEN ptas.is_bounce THEN 1 ELSE 0 END) / COUNT(DISTINCT ptas.session_id) * 100 as bounce_rate
FROM pizza_trackers pt
LEFT JOIN pizza_tracker_analytics_events ptae ON pt.id = ptae.tracker_id
LEFT JOIN pizza_tracker_sessions ptas ON pt.id = ptas.tracker_id
WHERE pt.is_active = 1
GROUP BY pt.id;

-- Performance monitoring view
CREATE OR REPLACE VIEW pizza_tracker_performance_metrics AS
SELECT 
    pt.id,
    pt.name,
    pt.business_id,
    pt.created_at,
    DATEDIFF(NOW(), pt.created_at) as days_active,
    pt.current_revenue / GREATEST(DATEDIFF(NOW(), pt.created_at), 1) as revenue_per_day,
    pt.progress_percent / GREATEST(DATEDIFF(NOW(), pt.created_at), 1) as progress_per_day,
    (pt.revenue_goal - pt.current_revenue) / GREATEST(pt.current_revenue / GREATEST(DATEDIFF(NOW(), pt.created_at), 1), 1) as estimated_days_to_completion
FROM pizza_trackers pt
WHERE pt.is_active = 1 AND pt.current_revenue > 0;

-- Triggers for audit logging
DELIMITER //

CREATE TRIGGER IF NOT EXISTS pizza_tracker_audit_insert
AFTER INSERT ON pizza_trackers
FOR EACH ROW
BEGIN
    INSERT INTO pizza_tracker_audit_log 
    (tracker_id, action, entity_type, entity_id, new_values, created_at)
    VALUES 
    (NEW.id, 'CREATE', 'pizza_tracker', NEW.id, JSON_OBJECT(
        'name', NEW.name,
        'revenue_goal', NEW.revenue_goal,
        'business_id', NEW.business_id
    ), NOW());
END//

CREATE TRIGGER IF NOT EXISTS pizza_tracker_audit_update
AFTER UPDATE ON pizza_trackers
FOR EACH ROW
BEGIN
    INSERT INTO pizza_tracker_audit_log 
    (tracker_id, action, entity_type, entity_id, old_values, new_values, created_at)
    VALUES 
    (NEW.id, 'UPDATE', 'pizza_tracker', NEW.id, 
    JSON_OBJECT(
        'current_revenue', OLD.current_revenue,
        'progress_percent', OLD.progress_percent
    ),
    JSON_OBJECT(
        'current_revenue', NEW.current_revenue,
        'progress_percent', NEW.progress_percent
    ), NOW());
END//

DELIMITER ;

-- Clean up old data periodically (30 days for logs, 90 days for analytics events)
CREATE EVENT IF NOT EXISTS cleanup_old_pizza_tracker_data
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE + INTERVAL 1 DAY
DO
BEGIN
    DELETE FROM pizza_tracker_webhook_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    DELETE FROM pizza_tracker_analytics_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    DELETE FROM pizza_tracker_sessions WHERE started_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    DELETE FROM api_rate_limits WHERE window_end < DATE_SUB(NOW(), INTERVAL 1 DAY);
    DELETE FROM pizza_tracker_report_instances WHERE expires_at < NOW();
END;

COMMIT; 