-- File: database.sql
-- Create Database
CREATE DATABASE IF NOT EXISTS stockflow_db;
USE stockflow_db;

-- Users Table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Administrator', 'Inventory Staff', 'Viewer') DEFAULT 'Viewer',
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories Table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    item_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Suppliers Table
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inventory Table
CREATE TABLE inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    asset_code VARCHAR(50) UNIQUE NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    category_id INT,
    supplier_id INT,
    quantity INT DEFAULT 0,
    unit_price DECIMAL(10,2),
    description TEXT,
    image VARCHAR(255),
    status ENUM('In Stock', 'Low Stock', 'Out of Stock') DEFAULT 'In Stock',
    low_stock_threshold INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

-- Stock-In Transactions
CREATE TABLE stock_in (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id VARCHAR(20) UNIQUE NOT NULL,
    product_id INT,
    supplier_id INT,
    quantity_added INT NOT NULL,
    notes TEXT,
    staff_id INT,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (staff_id) REFERENCES users(id)
);

-- Stock-Out Transactions
CREATE TABLE stock_out (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id VARCHAR(20) UNIQUE NOT NULL,
    product_id INT,
    quantity_released INT NOT NULL,
    released_to VARCHAR(100),
    purpose VARCHAR(200),
    staff_id INT,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id)
);

-- Audit Logs
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    log_id VARCHAR(20) UNIQUE NOT NULL,
    user_id INT,
    action VARCHAR(100),
    module VARCHAR(50),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200),
    message TEXT,
    type VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Stored Procedure: Update Inventory Status
DELIMITER //
CREATE PROCEDURE UpdateInventoryStatus()
BEGIN
    UPDATE inventory 
    SET status = CASE 
        WHEN quantity <= 0 THEN 'Out of Stock'
        WHEN quantity <= low_stock_threshold THEN 'Low Stock'
        ELSE 'In Stock'
    END;
END//
DELIMITER ;

-- Trigger: After Stock-In
DELIMITER //
CREATE TRIGGER after_stock_in
AFTER INSERT ON stock_in
FOR EACH ROW
BEGIN
    UPDATE inventory 
    SET quantity = quantity + NEW.quantity_added
    WHERE id = NEW.product_id;
    CALL UpdateInventoryStatus();
    
    -- Update category item count
    UPDATE categories c
    SET item_count = (
        SELECT COALESCE(SUM(i.quantity), 0)
        FROM inventory i
        WHERE i.category_id = c.id
    );
    
    -- Check for low stock alert
    IF EXISTS (
        SELECT 1 FROM inventory 
        WHERE id = NEW.product_id AND quantity <= low_stock_threshold
    ) THEN
        INSERT INTO notifications (title, message, type, created_at)
        VALUES ('Low Stock Alert', 
            CONCAT('Product is running low on stock'), 
            'warning', NOW());
    END IF;
END//
DELIMITER ;

-- Trigger: After Stock-Out
DELIMITER //
CREATE TRIGGER after_stock_out
AFTER INSERT ON stock_out
FOR EACH ROW
BEGIN
    UPDATE inventory 
    SET quantity = quantity - NEW.quantity_released
    WHERE id = NEW.product_id;
    CALL UpdateInventoryStatus();
    
    -- Update category item count
    UPDATE categories c
    SET item_count = (
        SELECT COALESCE(SUM(i.quantity), 0)
        FROM inventory i
        WHERE i.category_id = c.id
    );
END//
DELIMITER ;

-- Trigger: Audit Log
DELIMITER //
CREATE TRIGGER after_inventory_update
AFTER UPDATE ON inventory
FOR EACH ROW
BEGIN
    IF OLD.quantity != NEW.quantity THEN
        INSERT INTO audit_logs (log_id, user_id, action, module, details, created_at)
        VALUES (
            CONCAT('LOG-', FLOOR(RAND() * 10000)),
            @current_user_id,
            'UPDATE',
            'Inventory',
            CONCAT('Product ', NEW.product_name, ' quantity changed from ', OLD.quantity, ' to ', NEW.quantity),
            NOW()
        );
    END IF;
END//
DELIMITER ;

-- Insert Default Data with proper password hashing (password = 'password' hashed with bcrypt)
-- The hash below is for 'password'
INSERT INTO users (user_id, name, email, password, role, is_active) VALUES
('USR-01', 'Maria Cruz', 'maria.cruz@corp.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', TRUE),
('USR-02', 'Andrew Lim', 'andrew.lim@corp.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Inventory Staff', TRUE),
('USR-03', 'Ramon Tan', 'ramon.tan@corp.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Inventory Staff', TRUE),
('USR-04', 'Liza Reyes', 'liza.reyes@corp.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Viewer', FALSE);

INSERT INTO categories (name, description, is_active) VALUES
('Electronics', 'Consumer & business electronics', TRUE),
('Furniture', 'Office and workspace furniture', TRUE),
('Office Supplies', 'Daily consumables and stationery', TRUE),
('IT Equipment', 'Networking, computing & peripherals', TRUE),
('Tools', 'Maintenance and repair tools', TRUE);

INSERT INTO suppliers (supplier_id, name, contact_person, email, phone, is_active) VALUES
('SUP-01', 'TechSource Inc.', 'Maria Cruz', 'maria@techsource.ph', '+63 917 200 1100', TRUE),
('SUP-02', 'HP Philippines', 'Andrew Lim', 'andrew@hp.ph', '+63 917 332 8801', TRUE),
('SUP-03', 'OfficePro', 'Ramon Tan', 'ramon@officepro.ph', '+63 917 411 6620', TRUE),
('SUP-04', 'Digital Walker', 'Liza Reyes', 'liza@digitalwalker.ph', '+63 917 822 5511', TRUE),
('SUP-05', 'Cisco PH', 'Mark Villanueva', 'mark@cisco.ph', '+63 917 660 9920', FALSE),
('SUP-06', 'PaperWorks', 'Joanne Sy', 'joanne@paperworks.ph', '+63 917 555 1212', TRUE);

INSERT INTO inventory (asset_code, product_name, category_id, supplier_id, quantity, unit_price, status, low_stock_threshold) VALUES
('AST-1001', 'Dell Latitude 5430', 1, 1, 45, 85000, 'In Stock', 10),
('AST-1002', 'HP LaserJet Pro M404', 4, 2, 12, 25000, 'In Stock', 5),
('AST-1003', 'Steelcase Series 1 Chair', 2, 3, 28, 15000, 'In Stock', 5),
('AST-1004', 'Logitech MX Master 3S', 1, 4, 5, 4500, 'Low Stock', 10),
('AST-1005', 'USB-C Hub 7-in-1', 4, 1, 8, 1200, 'In Stock', 10),
('AST-1006', 'Cisco Catalyst 9200', 4, 5, 3, 45000, 'In Stock', 3),
('AST-1007', 'A4 Bond Paper Ream', 3, 6, 0, 250, 'Out of Stock', 20),
('AST-1008', 'DeWalt Cordless Drill', 5, 1, 15, 8500, 'In Stock', 5),
('AST-1009', 'Samsung 27" Monitor', 1, 2, 20, 12000, 'In Stock', 5),
('AST-1010', 'Conference Table 8-Seater', 2, 3, 4, 35000, 'In Stock', 2);

-- Insert sample stock-in transactions
INSERT INTO stock_in (transaction_id, product_id, supplier_id, quantity_added, notes, staff_id, date) VALUES
('SI-2037', 7, 6, 100, 'Bulk order', 2, '2026-05-11'),
('SI-2038', 4, 4, 30, 'Regular restock', 4, '2026-05-11'),
('SI-2039', 6, 5, 6, 'Network equipment', 1, '2026-05-12'),
('SI-2040', 3, 3, 12, 'Office chairs', 2, '2026-05-12'),
('SI-2041', 1, 1, 24, 'Laptop shipment', 1, '2026-05-12');

-- Insert sample stock-out transactions
INSERT INTO stock_out (transaction_id, product_id, quantity_released, released_to, purpose, staff_id, date) VALUES
('SO-3085', 7, 12, 'HR Department', 'Training', 2, '2026-05-11'),
('SO-3086', 5, 5, 'Marketing Team', 'Field work', 1, '2026-05-11'),
('SO-3087', 4, 8, 'Engineering Dept', 'New hires', 3, '2026-05-12'),
('SO-3088', 2, 4, 'Finance Dept', 'Replacement', 4, '2026-05-12');

-- Insert sample audit logs
INSERT INTO audit_logs (log_id, user_id, action, module, details, ip_address) VALUES
('LOG-9976', 2, 'DELETE', 'Categories', 'Deleted category draft', '192.168.1.100'),
('LOG-9977', 4, 'UPDATE', 'Inventory', 'Edited inventory AST-1007', '192.168.1.101'),
('LOG-9978', 1, 'LOGIN', 'Auth', 'Logged in', '192.168.1.102'),
('LOG-9979', 3, 'APPROVE', 'Stock-Out', 'Approved Stock-Out SO-3087', '192.168.1.103'),
('LOG-9980', 2, 'UPDATE', 'Suppliers', 'Updated supplier OfficePro', '192.168.1.104'),
('LOG-9981', 1, 'CREATE', 'Stock-In', 'Created Stock-In SI-2041', '192.168.1.105');

-- Insert sample notifications
INSERT INTO notifications (title, message, type, created_at) VALUES
('Low stock alert', 'USB-C Hub 7-in-1 is below threshold', 'warning', DATE_SUB(NOW(), INTERVAL 2 MINUTE)),
('New stock arrival', '24 units of Dell Latitude 5430 added', 'info', DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
('Stock release approved', 'SO-3087 approved by R. Tan', 'success', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('User signed in', 'Maria Cruz logged in from new device', 'info', DATE_SUB(NOW(), INTERVAL 2 HOUR));