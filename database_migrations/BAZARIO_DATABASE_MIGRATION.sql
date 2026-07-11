-- ============================================
-- BAZARIO - Database Migration Script
-- Converts existing mobile-accessories database to Bazario with new features
-- Version: 1.0
-- WARNING: Backup your database before running this script!
-- ============================================

-- ============================================
-- PHASE 1: REMOVE PAYMENT SYSTEM & UPDATE ORDERS TABLE
-- ============================================

-- Step 1: Update orders table - Remove house_number, change payment method to COD only
ALTER TABLE orders 
  DROP COLUMN house_number,
  ADD COLUMN address_line1 VARCHAR(255) AFTER street,
  ADD COLUMN address_line2 VARCHAR(255) AFTER address_line1,
  MODIFY street VARCHAR(100),
  MODIFY payment_method ENUM('COD') DEFAULT 'COD',
  ADD COLUMN tracking_number VARCHAR(100),
  ADD COLUMN estimated_delivery DATE;

-- Step 2: Populate address_line1 from existing street data
UPDATE orders SET address_line1 = street WHERE address_line1 IS NULL;

-- Step 3: Update existing payment methods to COD only
UPDATE orders SET payment_method = 'COD' WHERE payment_method != 'COD';

-- ============================================
-- PHASE 2: ADD ORDER TRACKING TIMESTAMPS
-- ============================================

-- Step 4: Add timestamp fields for tracking each status
ALTER TABLE orders 
  ADD COLUMN placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER created_at,
  ADD COLUMN confirmed_at TIMESTAMP NULL AFTER placed_at,
  ADD COLUMN processing_at TIMESTAMP NULL AFTER confirmed_at,
  ADD COLUMN packing_at TIMESTAMP NULL AFTER processing_at,
  ADD COLUMN shipped_at TIMESTAMP NULL AFTER packing_at,
  ADD COLUMN delivered_at TIMESTAMP NULL AFTER shipped_at,
  ADD COLUMN cancelled_at TIMESTAMP NULL AFTER delivered_at,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER cancelled_at;

-- Step 5: Populate placed_at from existing created_at
UPDATE orders SET placed_at = created_at WHERE placed_at IS NULL;

-- Step 6: Update order status enum values to match new flow
ALTER TABLE orders 
  MODIFY status ENUM('Order Placed', 'Confirmed', 'Processing', 'Packing', 'Out for Delivery', 'Delivered', 'Cancelled') DEFAULT 'Order Placed';

-- Step 7: Migrate existing status values
UPDATE orders SET status = 'Order Placed' WHERE status = 'Pending';
UPDATE orders SET status = 'Confirmed' WHERE status IN ('Confirmed', 'Processing', 'Shipped');
UPDATE orders SET status = 'Delivered' WHERE status = 'Delivered';

-- ============================================
-- PHASE 3: CREATE NOTIFICATION SYSTEM TABLES
-- ============================================

-- Step 8: Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'order_placed, order_confirmed, processing, packing, shipped, delivered, cancelled',
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    icon_class VARCHAR(50) DEFAULT 'fa-info-circle',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_order (order_id),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Step 9: Create order_status_history table (for timeline tracking)
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    changed_by INT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    INDEX idx_order (order_id),
    INDEX idx_timestamp (timestamp),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Step 10: Create notification_preferences table
CREATE TABLE IF NOT EXISTS notification_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    email_on_order_placed BOOLEAN DEFAULT TRUE,
    email_on_order_confirmed BOOLEAN DEFAULT TRUE,
    email_on_processing BOOLEAN DEFAULT TRUE,
    email_on_packing BOOLEAN DEFAULT TRUE,
    email_on_shipped BOOLEAN DEFAULT TRUE,
    email_on_delivered BOOLEAN DEFAULT TRUE,
    sms_on_order_placed BOOLEAN DEFAULT FALSE,
    sms_on_order_confirmed BOOLEAN DEFAULT FALSE,
    sms_on_processing BOOLEAN DEFAULT FALSE,
    sms_on_packing BOOLEAN DEFAULT FALSE,
    sms_on_shipped BOOLEAN DEFAULT TRUE,
    sms_on_delivered BOOLEAN DEFAULT TRUE,
    phone_number VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- PHASE 4: DATA MIGRATION & INITIALIZATION
-- ============================================

-- Step 11: Create default notification preferences for existing users
INSERT INTO notification_preferences (user_id) 
SELECT id FROM users 
WHERE id NOT IN (SELECT user_id FROM notification_preferences);

-- Step 12: Backfill status history from existing orders
INSERT INTO order_status_history (order_id, status, timestamp)
SELECT id, status, placed_at FROM orders
WHERE id NOT IN (SELECT order_id FROM order_status_history);

-- ============================================
-- PHASE 5: ADD INDEXES FOR PERFORMANCE
-- ============================================

-- Step 13: Add indexes to orders table
ALTER TABLE orders 
  ADD INDEX idx_user_id (user_id),
  ADD INDEX idx_status (status),
  ADD INDEX idx_placed_at (placed_at),
  ADD INDEX idx_updated_at (updated_at),
  ADD INDEX idx_order_number (order_number);

-- Step 14: Add indexes to users table
ALTER TABLE users 
  ADD INDEX idx_email (email),
  ADD INDEX idx_username (username),
  ADD INDEX idx_role (role);

-- Step 15: Add indexes to product table
ALTER TABLE product 
  ADD INDEX idx_category (category),
  ADD INDEX idx_created_at (created_at);

-- ============================================
-- PHASE 6: VIEW FOR DASHBOARD STATISTICS
-- ============================================

-- Step 16: Create view for quick statistics
CREATE OR REPLACE VIEW order_statistics AS
SELECT 
  o.user_id,
  COUNT(*) as total_orders,
  SUM(CASE WHEN o.status = 'Delivered' THEN 1 ELSE 0 END) as delivered_count,
  SUM(CASE WHEN o.status IN ('Processing', 'Packing', 'Out for Delivery') THEN 1 ELSE 0 END) as in_transit_count,
  SUM(CASE WHEN o.status = 'Order Placed' THEN 1 ELSE 0 END) as pending_count,
  SUM(CASE WHEN o.status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_count,
  SUM(o.total_amount) as total_spent,
  AVG(DATEDIFF(o.delivered_at, o.placed_at)) as avg_delivery_days
FROM orders o
WHERE o.status != 'Cancelled'
GROUP BY o.user_id;

-- Step 17: Create view for admin dashboard
CREATE OR REPLACE VIEW admin_order_summary AS
SELECT 
  DATE(o.placed_at) as date,
  o.status,
  COUNT(*) as order_count,
  SUM(o.total_amount) as revenue,
  AVG(DATEDIFF(o.delivered_at, o.placed_at)) as avg_days_to_delivery
FROM orders o
GROUP BY DATE(o.placed_at), o.status;

-- ============================================
-- PHASE 7: STORED PROCEDURES FOR COMMON OPERATIONS
-- ============================================

-- Step 18: Procedure to update order status automatically
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS update_order_status_with_history(
  IN p_order_id INT,
  IN p_new_status VARCHAR(50),
  IN p_admin_id INT,
  IN p_notes TEXT
)
BEGIN
  DECLARE v_old_status VARCHAR(50);
  DECLARE v_user_id INT;
  DECLARE v_timestamp_field VARCHAR(50);
  
  START TRANSACTION;
  
  BEGIN
    -- Get current order status and user_id
    SELECT status, user_id INTO v_old_status, v_user_id 
    FROM orders 
    WHERE id = p_order_id 
    FOR UPDATE;
    
    IF v_old_status IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order not found';
    END IF;
    
    -- Validate status transition (simple check)
    IF (v_old_status = 'Delivered' OR v_old_status = 'Cancelled') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot update delivered or cancelled orders';
    END IF;
    
    -- Update order status
    UPDATE orders 
    SET status = p_new_status, updated_at = NOW()
    WHERE id = p_order_id;
    
    -- Add to status history
    INSERT INTO order_status_history (order_id, status, changed_by, notes)
    VALUES (p_order_id, p_new_status, p_admin_id, p_notes);
    
    COMMIT;
  END;
END$$

DELIMITER ;

-- ============================================
-- PHASE 8: ADD BRANDING UPDATES
-- ============================================

-- Step 19: Add new settings table for system configuration
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Step 20: Insert Bazario branding settings
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('app_name', 'Bazario'),
('app_tagline', 'Your Mobile Accessories Store'),
('brand_primary_color', '#001a33'),
('brand_secondary_color', '#f5f5f5'),
('support_email', 'support@bazario.com'),
('support_phone', '+91-XXXXXXXXXX'),
('enable_sms_notifications', '0'),
('timezone', 'Asia/Kolkata');

-- ============================================
-- PHASE 9: VERIFICATION QUERIES
-- ============================================

-- Step 21: Verify migration completion
SELECT 
  'Tables Created' as verification_step,
  COUNT(*) as count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'Mproject'
AND TABLE_NAME IN ('notifications', 'order_status_history', 'notification_preferences', 'system_settings');

-- Step 22: Check for any orders still with old payment methods
SELECT COUNT(*) as legacy_payment_methods
FROM orders
WHERE payment_method NOT IN ('COD');

-- Step 23: Check orders with missing address data
SELECT COUNT(*) as incomplete_addresses
FROM orders
WHERE address_line1 IS NULL OR street IS NULL OR city IS NULL;

-- ============================================
-- ADDITIONAL QUERIES FOR REFERENCE
-- ============================================

-- Query to get recent orders with status timeline
/* 
SELECT 
  o.id,
  o.order_number,
  o.customer_name,
  o.status,
  o.total_amount,
  o.placed_at,
  o.delivered_at,
  DATEDIFF(o.delivered_at, o.placed_at) as delivery_days
FROM orders o
WHERE o.placed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY o.placed_at DESC;
*/

-- Query to get undelivered orders
/*
SELECT 
  o.order_number,
  o.customer_name,
  o.status,
  DATEDIFF(NOW(), o.placed_at) as days_since_order
FROM orders o
WHERE o.status NOT IN ('Delivered', 'Cancelled')
ORDER BY o.placed_at;
*/

-- Query to get notification statistics
/*
SELECT 
  type,
  COUNT(*) as total_sent,
  SUM(CASE WHEN is_read = TRUE THEN 1 ELSE 0 END) as opened
FROM notifications
GROUP BY type;
*/

-- ============================================
-- ROLLBACK PLAN (if needed)
-- ============================================

-- To rollback changes, use the backup taken before this migration:
-- mysql -u root -p database_name < backup_before_bazario.sql

-- Or manually:
/*
ALTER TABLE orders 
  ADD COLUMN house_number VARCHAR(50),
  DROP COLUMN address_line1,
  DROP COLUMN address_line2,
  MODIFY payment_method ENUM('COD', 'Online') DEFAULT 'COD';

DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS notification_preferences;
DROP TABLE IF EXISTS system_settings;
DROP VIEW IF EXISTS order_statistics;
DROP VIEW IF EXISTS admin_order_summary;
DROP PROCEDURE IF EXISTS update_order_status_with_history;
*/

-- ============================================
-- END OF MIGRATION SCRIPT
-- ============================================
