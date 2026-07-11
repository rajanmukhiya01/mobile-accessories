<?php
/**
 * BAZARIO Database Migration Runner
 * Purpose: Add order tracking and notification system to database
 * Safety: Checks for existing columns before adding
 */

require_once 'config.php';

$migrations_applied = [];
$errors = [];

echo "========================================\n";
echo "BAZARIO DATABASE MIGRATION RUNNER\n";
echo "========================================\n\n";

// PHASE 1: Check and add status column to orders table
echo "[PHASE 1] Checking orders table for status column...\n";
$check_status = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='orders' AND COLUMN_NAME='status' AND TABLE_SCHEMA='Mproject'");
if (mysqli_num_rows($check_status) == 0) {
    // Column doesn't exist, add it
    if (mysqli_query($conn, "ALTER TABLE orders ADD COLUMN status VARCHAR(30) NOT NULL DEFAULT 'Order Placed'")) {
        echo "✓ Added 'status' column to orders table\n";
        $migrations_applied[] = "orders.status column";
    } else {
        echo "✗ Failed to add status column: " . mysqli_error($conn) . "\n";
        $errors[] = "Failed to add status column";
    }
} else {
    echo "✓ Column 'status' already exists\n";
}

// Add other tracking timestamps
echo "\n[PHASE 2] Adding timestamp columns to orders table...\n";
$timestamp_columns = [
    'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'last_status_changed_at TIMESTAMP NULL'
];

foreach ($timestamp_columns as $col_def) {
    $col_name = explode(' ', $col_def)[0];
    $check = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='orders' AND COLUMN_NAME='$col_name' AND TABLE_SCHEMA='Mproject'");
    if (mysqli_num_rows($check) == 0) {
        if (mysqli_query($conn, "ALTER TABLE orders ADD COLUMN $col_def")) {
            echo "✓ Added '$col_name' column\n";
            $migrations_applied[] = "orders.$col_name column";
        } else {
            echo "✗ Failed to add $col_name: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "✓ Column '$col_name' already exists\n";
    }
}

// PHASE 3: Create order_status_history table
echo "\n[PHASE 3] Creating order_status_history table...\n";
$check_table = mysqli_query($conn, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='order_status_history' AND TABLE_SCHEMA='Mproject'");
if (mysqli_num_rows($check_table) == 0) {
    $sql = "CREATE TABLE order_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        status VARCHAR(50) NOT NULL,
        changed_by INT NULL,
        note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        INDEX idx_order_id (order_id),
        INDEX idx_created_at (created_at)
    )";
    if (mysqli_query($conn, $sql)) {
        echo "✓ Created order_status_history table\n";
        $migrations_applied[] = "order_status_history table";
    } else {
        echo "✗ Failed to create order_status_history: " . mysqli_error($conn) . "\n";
        $errors[] = "Failed to create order_status_history table";
    }
} else {
    echo "✓ Table order_status_history already exists\n";
}

// PHASE 4: Create notifications table
echo "\n[PHASE 4] Creating notifications table...\n";
$check_table = mysqli_query($conn, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='notifications' AND TABLE_SCHEMA='Mproject'");
if (mysqli_num_rows($check_table) == 0) {
    $sql = "CREATE TABLE notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        order_id INT NULL,
        title VARCHAR(255) NOT NULL,
        body TEXT,
        notification_type VARCHAR(50) DEFAULT 'order_status',
        link VARCHAR(255) NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at)
    )";
    if (mysqli_query($conn, $sql)) {
        echo "✓ Created notifications table\n";
        $migrations_applied[] = "notifications table";
    } else {
        echo "✗ Failed to create notifications: " . mysqli_error($conn) . "\n";
        $errors[] = "Failed to create notifications table";
    }
} else {
    echo "✓ Table notifications already exists\n";
}

// PHASE 5: Create notification_preferences table
echo "\n[PHASE 5] Creating notification_preferences table...\n";
$check_table = mysqli_query($conn, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='notification_preferences' AND TABLE_SCHEMA='Mproject'");
if (mysqli_num_rows($check_table) == 0) {
    $sql = "CREATE TABLE notification_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        email_on_order_placed TINYINT(1) DEFAULT 1,
        email_on_processing TINYINT(1) DEFAULT 1,
        email_on_packing TINYINT(1) DEFAULT 1,
        email_on_out_for_delivery TINYINT(1) DEFAULT 1,
        email_on_delivered TINYINT(1) DEFAULT 1,
        sms_on_order_placed TINYINT(1) DEFAULT 0,
        sms_on_processing TINYINT(1) DEFAULT 1,
        sms_on_packing TINYINT(1) DEFAULT 0,
        sms_on_out_for_delivery TINYINT(1) DEFAULT 1,
        sms_on_delivered TINYINT(1) DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id)
    )";
    if (mysqli_query($conn, $sql)) {
        echo "✓ Created notification_preferences table\n";
        $migrations_applied[] = "notification_preferences table";
    } else {
        echo "✗ Failed to create notification_preferences: " . mysqli_error($conn) . "\n";
        $errors[] = "Failed to create notification_preferences table";
    }
} else {
    echo "✓ Table notification_preferences already exists\n";
}

// PHASE 6: Create notification_queue table
echo "\n[PHASE 6] Creating notification_queue table...\n";
$check_table = mysqli_query($conn, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='notification_queue' AND TABLE_SCHEMA='Mproject'");
if (mysqli_num_rows($check_table) == 0) {
    $sql = "CREATE TABLE notification_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        notification_type VARCHAR(50) NOT NULL,
        recipient VARCHAR(255) NOT NULL,
        subject VARCHAR(255),
        body TEXT,
        status VARCHAR(20) DEFAULT 'pending',
        retry_count INT DEFAULT 0,
        error_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    )";
    if (mysqli_query($conn, $sql)) {
        echo "✓ Created notification_queue table\n";
        $migrations_applied[] = "notification_queue table";
    } else {
        echo "✗ Failed to create notification_queue: " . mysqli_error($conn) . "\n";
        $errors[] = "Failed to create notification_queue table";
    }
} else {
    echo "✓ Table notification_queue already exists\n";
}

// PHASE 7: Create notification_templates table
echo "\n[PHASE 7] Creating notification_templates table...\n";
$check_table = mysqli_query($conn, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='notification_templates' AND TABLE_SCHEMA='Mproject'");
if (mysqli_num_rows($check_table) == 0) {
    $sql = "CREATE TABLE notification_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        status VARCHAR(50) UNIQUE NOT NULL,
        email_subject VARCHAR(255),
        email_body_html LONGTEXT,
        sms_body TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    if (mysqli_query($conn, $sql)) {
        echo "✓ Created notification_templates table\n";
        $migrations_applied[] = "notification_templates table";
        
        // Insert default templates
        $templates = [
            ['Order Placed', 'Your Order Has Been Placed Successfully!', 'Your order {ORDER_NUMBER} has been placed successfully!'],
            ['Processing', 'Your Order is Being Processed', 'Your order {ORDER_NUMBER} is being processed.'],
            ['Packing', 'Your Order is Being Packed', 'Your order {ORDER_NUMBER} is being packed.'],
            ['Out for Delivery', 'Your Order is Out for Delivery', 'Your order {ORDER_NUMBER} is out for delivery!'],
            ['Delivered', 'Your Order Has Been Delivered', 'Your order {ORDER_NUMBER} has been delivered successfully!']
        ];
        
        foreach ($templates as $tpl) {
            mysqli_query($conn, "INSERT IGNORE INTO notification_templates (status, email_subject, sms_body) VALUES ('$tpl[0]', '$tpl[1]', '$tpl[2]')");
        }
        echo "  ✓ Inserted default notification templates\n";
    } else {
        echo "✗ Failed to create notification_templates: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "✓ Table notification_templates already exists\n";
}

// PHASE 8: Create system_settings table
echo "\n[PHASE 8] Creating system_settings table...\n";
$check_table = mysqli_query($conn, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='system_settings' AND TABLE_SCHEMA='Mproject'");
if (mysqli_num_rows($check_table) == 0) {
    $sql = "CREATE TABLE system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value LONGTEXT,
        description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    if (mysqli_query($conn, $sql)) {
        echo "✓ Created system_settings table\n";
        
        // Insert default settings
        $settings = [
            ['app_name', 'Bazario'],
            ['brand_color_primary', '#001a33'],
            ['brand_color_accent', '#3498db'],
            ['estimated_delivery_days', '5'],
            ['timezone', 'Asia/Kolkata']
        ];
        
        foreach ($settings as $setting) {
            mysqli_query($conn, "INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('{$setting[0]}', '{$setting[1]}')");
        }
        echo "  ✓ Inserted default system settings\n";
        $migrations_applied[] = "system_settings table";
    } else {
        echo "✗ Failed to create system_settings: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "✓ Table system_settings already exists\n";
}

// Final summary
echo "\n========================================\n";
echo "MIGRATION SUMMARY\n";
echo "========================================\n";
echo "✓ Successfully applied: " . count($migrations_applied) . " migrations\n";
if (count($errors) > 0) {
    echo "✗ Errors encountered: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
} else {
    echo "✓ No errors!\n";
}

echo "\nMigrations Applied:\n";
foreach ($migrations_applied as $migration) {
    echo "  ✓ $migration\n";
}

echo "\n========================================\n";
echo "NEXT STEPS:\n";
echo "========================================\n";
echo "1. Update profiles to add notification preferences\n";
echo "2. Implement notification_service.php functions\n";
echo "3. Create order tracking UI page\n";
echo "4. Add admin status update functionality\n";
echo "5. Test complete notification flow\n";
echo "\n";

mysqli_close($conn);
?>
