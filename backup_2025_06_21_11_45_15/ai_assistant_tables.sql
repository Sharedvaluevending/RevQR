-- AI Assistant Tables for RevenueQR System

-- Table for logging AI chat interactions
CREATE TABLE IF NOT EXISTS ai_chat_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    user_message TEXT NOT NULL,
    ai_response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_business_created (business_id, created_at),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- Table for logging AI insights and recommendations
CREATE TABLE IF NOT EXISTS ai_insights_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    insights_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_business_created (business_id, created_at),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- Table for tracking AI assistant usage and performance
CREATE TABLE IF NOT EXISTS ai_usage_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    feature_used VARCHAR(50) NOT NULL, -- 'chat', 'insights', 'recommendations'
    usage_count INT DEFAULT 1,
    last_used TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_business_feature (business_id, feature_used),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- Insert initial usage stats for existing businesses
INSERT IGNORE INTO ai_usage_stats (business_id, feature_used, usage_count)
SELECT DISTINCT id, 'insights', 0 FROM businesses;

INSERT IGNORE INTO ai_usage_stats (business_id, feature_used, usage_count)
SELECT DISTINCT id, 'chat', 0 FROM businesses;

INSERT IGNORE INTO ai_usage_stats (business_id, feature_used, usage_count)
SELECT DISTINCT id, 'recommendations', 0 FROM businesses; 