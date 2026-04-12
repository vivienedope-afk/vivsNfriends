-- Migration: Add Notification Log Table
-- Purpose: Track all sent notifications for auditing and troubleshooting

CREATE TABLE IF NOT EXISTS notification_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    notification_type ENUM('email', 'sms', 'push') DEFAULT 'email',
    subject VARCHAR(255) NOT NULL,
    status ENUM('queued', 'sent', 'failed', 'skipped') DEFAULT 'queued',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status)
);

-- Migration: Add SMS Config Status Table (optional, for tracking SMS gateway configuration)
CREATE TABLE IF NOT EXISTS sms_gateway_config (
    config_id INT PRIMARY KEY AUTO_INCREMENT,
    gateway_type ENUM('twilio', 'aws_sns', 'mock') DEFAULT 'mock',
    is_active BOOLEAN DEFAULT FALSE,
    config_data JSON,
    last_tested TIMESTAMP NULL,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Insert default SMS gateway config (mock)
INSERT INTO sms_gateway_config (gateway_type, is_active, config_data) 
VALUES ('mock', TRUE, '{}')
ON DUPLICATE KEY UPDATE is_active = TRUE;
