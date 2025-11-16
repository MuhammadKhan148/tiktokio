-- ==================================================================
-- Proxy Rotation System for YTDLP Provider
-- ==================================================================
-- This schema enables thread-safe proxy rotation for yt-dlp downloads
-- ==================================================================

-- Table for storing rotating proxies per provider
CREATE TABLE IF NOT EXISTS api_proxies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_key VARCHAR(50) NOT NULL COMMENT 'Provider identifier (e.g., ytdlp, tikwm)',
    proxy_uri VARCHAR(255) NOT NULL COMMENT 'Proxy URL (e.g., http://proxy.example.com:8080)',
    auth_username VARCHAR(100) DEFAULT NULL COMMENT 'Proxy authentication username',
    auth_password VARCHAR(255) DEFAULT NULL COMMENT 'Proxy authentication password',
    is_active TINYINT(1) DEFAULT 1 COMMENT '1=active, 0=disabled',
    last_used_at DATETIME DEFAULT NULL COMMENT 'Last time this proxy was selected',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notes TEXT DEFAULT NULL COMMENT 'Admin notes about this proxy',
    
    INDEX idx_provider_active (provider_key, is_active),
    INDEX idx_last_used (last_used_at),
    INDEX idx_provider_last_used (provider_key, last_used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Table for tracking proxy usage statistics
CREATE TABLE IF NOT EXISTS api_proxy_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proxy_id INT NOT NULL,
    provider_key VARCHAR(50) NOT NULL,
    request_count INT DEFAULT 0,
    success_count INT DEFAULT 0,
    failure_count INT DEFAULT 0,
    last_success_at DATETIME DEFAULT NULL,
    last_failure_at DATETIME DEFAULT NULL,
    avg_response_time_ms INT DEFAULT NULL,
    date DATE NOT NULL,
    
    FOREIGN KEY (proxy_id) REFERENCES api_proxies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_proxy_date (proxy_id, date),
    INDEX idx_provider_date (provider_key, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
-- Sample Test Data (Replace with your actual proxies)
-- ==================================================================

-- Insert dummy proxies for testing
INSERT INTO api_proxies (provider_key, proxy_uri, auth_username, auth_password, is_active, notes) VALUES
('ytdlp', 'http://proxy1.example.com:8080', 'user1', 'pass1', 1, 'Primary proxy server - DUMMY FOR TESTING'),
('ytdlp', 'http://proxy2.example.com:8080', 'user2', 'pass2', 1, 'Secondary proxy server - DUMMY FOR TESTING'),
('ytdlp', 'http://proxy3.example.com:8080', NULL, NULL, 1, 'Public proxy without auth - DUMMY FOR TESTING'),
('ytdlp', 'http://192.168.1.100:3128', 'testuser', 'testpass', 0, 'Disabled for maintenance - DUMMY FOR TESTING')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- ==================================================================
-- Useful Queries for Admin & Debugging
-- ==================================================================

-- View proxy rotation status
-- SELECT 
--     id,
--     provider_key,
--     proxy_uri,
--     auth_username,
--     is_active,
--     last_used_at,
--     TIMESTAMPDIFF(SECOND, last_used_at, NOW()) as seconds_since_last_use,
--     notes
-- FROM api_proxies
-- WHERE provider_key = 'ytdlp'
-- ORDER BY last_used_at ASC;

-- Check which proxy will be used next
-- SELECT 
--     id,
--     proxy_uri,
--     COALESCE(last_used_at, '1970-01-01') as last_used
-- FROM api_proxies
-- WHERE provider_key = 'ytdlp' AND is_active = 1
-- ORDER BY COALESCE(last_used_at, '1970-01-01') ASC, id ASC
-- LIMIT 1;

-- Count active proxies per provider
-- SELECT 
--     provider_key,
--     COUNT(*) as total_proxies,
--     SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_proxies,
--     SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_proxies
-- FROM api_proxies
-- GROUP BY provider_key;

-- Disable a specific proxy
-- UPDATE api_proxies SET is_active = 0 WHERE id = ?;

-- Enable a specific proxy
-- UPDATE api_proxies SET is_active = 1 WHERE id = ?;

-- Reset all proxy usage times (useful for testing rotation)
-- UPDATE api_proxies SET last_used_at = NULL WHERE provider_key = 'ytdlp';

-- Delete a proxy
-- DELETE FROM api_proxies WHERE id = ?;

-- ==================================================================
-- Notes for Production Deployment
-- ==================================================================
-- 1. Replace dummy proxies with your actual proxy servers
-- 2. Store proxy passwords securely (consider encryption at rest)
-- 3. Monitor proxy health via api_proxy_stats table
-- 4. Set up alerts for when no active proxies are available
-- 5. Implement automatic proxy health checks and disable failing proxies
-- ==================================================================

