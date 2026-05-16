-- File: sample_data.sql
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
('Low stock alert', 'USB-C Hub 7-in-1 below threshold', 'warning', DATE_SUB(NOW(), INTERVAL 2 MINUTE)),
('New stock arrival', '24 units of Dell Latitude 5430', 'info', DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
('Stock release approved', 'SO-3087 by R. Tan', 'success', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('User signed in', 'Maria Cruz from new device', 'info', DATE_SUB(NOW(), INTERVAL 2 HOUR));