-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 17, 2026 at 08:56 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `school_supply_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `GetInventoryByCategory` ()   BEGIN
    SELECT 
        c.name as category,
        COUNT(s.id) as total_items,
        SUM(s.quantity) as total_units,
        SUM(s.quantity * s.unit_price) as total_value
    FROM categories c
    LEFT JOIN school_supplies s ON c.id = s.category_id
    GROUP BY c.id
    ORDER BY total_value DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetLowStockSupplies` ()   BEGIN
    SELECT s.*, c.name as category_name 
    FROM school_supplies s
    LEFT JOIN categories c ON s.category_id = c.id
    WHERE s.quantity <= s.low_stock_threshold 
    ORDER BY (s.quantity / s.low_stock_threshold) ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetMostIssuedSupplies` (IN `p_limit` INT)   BEGIN
    SELECT 
        s.supply_name,
        s.supply_code,
        SUM(so.quantity) as total_issued,
        COUNT(DISTINCT so.issued_to) as unique_recipients
    FROM school_supplies s
    JOIN stock_out so ON s.id = so.supply_id
    GROUP BY s.id
    ORDER BY total_issued DESC
    LIMIT p_limit;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RecordStockIn` (IN `p_transaction_no` VARCHAR(20), IN `p_supply_id` INT, IN `p_supplier_id` INT, IN `p_quantity` INT, IN `p_po_no` VARCHAR(100), IN `p_invoice_no` VARCHAR(100), IN `p_remarks` TEXT, IN `p_received_by` INT)   BEGIN
    INSERT INTO stock_in (transaction_no, supply_id, supplier_id, quantity, purchase_order_no, invoice_no, remarks, received_by, date_received)
    VALUES (p_transaction_no, p_supply_id, p_supplier_id, p_quantity, p_po_no, p_invoice_no, p_remarks, p_received_by, CURDATE());
    
    UPDATE school_supplies SET quantity = quantity + p_quantity WHERE id = p_supply_id;
    CALL UpdateSupplyStatus(p_supply_id);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RecordStockOut` (IN `p_transaction_no` VARCHAR(20), IN `p_supply_id` INT, IN `p_quantity` INT, IN `p_issued_to` VARCHAR(100), IN `p_id_number` VARCHAR(50), IN `p_grade_section` VARCHAR(50), IN `p_purpose` VARCHAR(200), IN `p_remarks` TEXT, IN `p_issued_by` INT)   BEGIN
    INSERT INTO stock_out (transaction_no, supply_id, quantity, issued_to, id_number, grade_section, purpose, remarks, issued_by, date_issued)
    VALUES (p_transaction_no, p_supply_id, p_quantity, p_issued_to, p_id_number, p_grade_section, p_purpose, p_remarks, p_issued_by, CURDATE());
    
    UPDATE school_supplies SET quantity = quantity - p_quantity WHERE id = p_supply_id;
    CALL UpdateSupplyStatus(p_supply_id);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateSupplyStatus` (IN `supply_id` INT)   BEGIN
    UPDATE school_supplies 
    SET status = CASE 
        WHEN quantity <= 0 THEN 'Out of Stock'
        WHEN quantity <= low_stock_threshold THEN 'Low Stock'
        ELSE 'In Stock'
    END
    WHERE id = supply_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `log_id` varchar(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `log_id`, `user_id`, `action`, `module`, `details`, `ip_address`, `created_at`) VALUES
(3, 'LOG-20260517-2393', NULL, 'UPDATE', 'School Supplies', 'Supply \"#2 Pencil (Box of 12)\" changed: Qty 45→47, Price 60.00→60.00', NULL, '2026-05-17 06:30:51'),
(4, 'LOG-20260517-712', NULL, 'UPDATE', 'School Supplies', 'Supply \"A4 Bond Paper (500 sheets)\" changed: Qty 80→82, Price 220.00→220.00', NULL, '2026-05-17 06:31:10'),
(5, 'LOG-20260517-8284', NULL, 'UPDATE', 'School Supplies', 'Supply \"#2 Pencil (Box of 12)\" changed: Qty 47→49, Price 60.00→60.00', NULL, '2026-05-17 06:31:12'),
(6, 'LOG-20260517-1889', NULL, 'UPDATE', 'School Supplies', 'Supply \"A4 Bond Paper (500 sheets)\" changed: Qty 82→84, Price 220.00→220.00', NULL, '2026-05-17 06:31:15'),
(7, 'LOG-20260517-8997', NULL, 'UPDATE', 'School Supplies', 'Supply \"#2 Pencil (Box of 12)\" changed: Qty 49→51, Price 60.00→60.00', NULL, '2026-05-17 06:31:16'),
(8, 'LOG-20260517-9379', NULL, 'UPDATE', 'School Supplies', 'Supply \"A4 Bond Paper (500 sheets)\" changed: Qty 84→86, Price 220.00→220.00', NULL, '2026-05-17 06:31:19'),
(9, 'LOG-20260517-3219', NULL, 'UPDATE', 'School Supplies', 'Supply \"A4 Bond Paper (500 sheets)\" changed: Qty 86→88, Price 220.00→220.00', NULL, '2026-05-17 06:31:42'),
(10, 'LOG-20260517-2497', NULL, 'UPDATE', 'School Supplies', 'Supply \"A4 Bond Paper (500 sheets)\" changed: Qty 88→90, Price 220.00→220.00', NULL, '2026-05-17 06:32:04'),
(11, 'LOG-20260517-9607', NULL, 'UPDATE', 'School Supplies', 'Supply \"#2 Pencil (Box of 12)\" changed: Qty 51→49, Price 60.00→60.00', NULL, '2026-05-17 06:55:59');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 'Notebooks & Pads', 'Spiral notebooks, composition notebooks, pad paper', 1, '2026-05-17 06:08:44'),
(2, 'Writing Instruments', 'Pens, pencils, markers, highlighters, crayons', 1, '2026-05-17 06:08:44'),
(3, 'Art Supplies', 'Drawing materials, coloring materials, craft supplies', 1, '2026-05-17 06:08:44'),
(4, 'Paper Products', 'Bond paper, colored paper, construction paper', 1, '2026-05-17 06:08:44'),
(5, 'Folders & Binders', 'Clear books, expandable folders, ring binders', 1, '2026-05-17 06:08:44'),
(6, 'Mathematics Tools', 'Rulers, protractors, compass, calculators', 1, '2026-05-17 06:08:44'),
(7, 'Classroom Supplies', 'Whiteboard markers, erasers, chalk, tape', 1, '2026-05-17 06:08:44'),
(8, 'Technology', 'USB drives, headphones, mouse, keyboard', 1, '2026-05-17 06:08:44');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, '📦 Stock Received', NULL, 'success', 0, '2026-05-17 06:08:44'),
(2, '📦 Stock Received', NULL, 'success', 0, '2026-05-17 06:08:44'),
(3, '📦 Stock Received', NULL, 'success', 0, '2026-05-17 06:08:44'),
(4, '📦 Stock Received', NULL, 'success', 0, '2026-05-17 06:08:44'),
(5, '📦 Stock Received', NULL, 'success', 0, '2026-05-17 06:30:51'),
(6, '📦 Stock Received', '2 units of #2 Pencil (Box of 12) added to inventory', 'success', 0, '2026-05-17 06:30:51'),
(7, '📦 Stock Received', NULL, 'success', 0, '2026-05-17 06:31:10'),
(8, '📦 Stock Received', '2 units of A4 Bond Paper (500 sheets) added to inventory', 'success', 0, '2026-05-17 06:31:10'),
(9, '📦 Stock Received', NULL, 'success', 0, '2026-05-17 06:31:12'),
(10, '📦 Stock Received', '2 units of #2 Pencil (Box of 12) added to inventory', 'success', 0, '2026-05-17 06:31:12'),
(11, '📦 Stock Received', NULL, 'success', 0, '2026-05-17 06:31:15'),
(12, '📦 Stock Received', '2 units of A4 Bond Paper (500 sheets) added to inventory', 'success', 0, '2026-05-17 06:31:15'),
(13, '📦 Stock Received', NULL, 'success', 0, '2026-05-17 06:31:16'),
(14, '📦 Stock Received', '2 units of #2 Pencil (Box of 12) added to inventory', 'success', 0, '2026-05-17 06:31:16'),
(15, '📦 Stock Received', NULL, 'success', 0, '2026-05-17 06:31:19'),
(16, '📦 Stock Received', '2 units of A4 Bond Paper (500 sheets) added to inventory', 'success', 0, '2026-05-17 06:31:19'),
(17, '📦 Stock Received', NULL, 'success', 0, '2026-05-17 06:31:42'),
(18, '📦 Stock Received', '2 units of A4 Bond Paper (500 sheets) added to inventory', 'success', 0, '2026-05-17 06:31:42'),
(19, '📦 Stock Received', NULL, 'success', 0, '2026-05-17 06:32:04'),
(20, '📦 Stock Received', '2 units of A4 Bond Paper (500 sheets) added to inventory', 'success', 0, '2026-05-17 06:32:04');

-- --------------------------------------------------------

--
-- Table structure for table `school_supplies`
--

CREATE TABLE `school_supplies` (
  `id` int(11) NOT NULL,
  `supply_code` varchar(50) NOT NULL,
  `supply_name` varchar(200) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('In Stock','Low Stock','Out of Stock') DEFAULT 'In Stock',
  `low_stock_threshold` int(11) DEFAULT 10,
  `location` varchar(100) DEFAULT NULL COMMENT 'Storage location (e.g., Cabinet A1, Shelf 2)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_supplies`
--

INSERT INTO `school_supplies` (`id`, `supply_code`, `supply_name`, `category_id`, `supplier_id`, `quantity`, `unit_price`, `description`, `status`, `low_stock_threshold`, `location`, `created_at`, `updated_at`) VALUES
(1, 'NB-001', 'A4 Spiral Notebook (80 leaves)', 1, 1, 250, 45.00, '80 leaves, ruled, A4 size', 'In Stock', 30, 'Cabinet A1', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(2, 'NB-002', 'Composition Notebook (100 leaves)', 1, 1, 180, 55.00, '100 leaves, ruled, with cover', 'In Stock', 25, 'Cabinet A2', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(3, 'NB-003', 'Grade 1 Writing Pad', 1, 3, 500, 25.00, 'For elementary students', 'In Stock', 50, 'Cabinet A3', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(4, 'PEN-001', 'Black Ballpoint Pen (0.5mm)', 2, 2, 850, 8.00, 'Smooth writing, long lasting', 'In Stock', 100, 'Cabinet B1', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(5, 'PEN-002', 'Blue Ballpoint Pen (0.5mm)', 2, 2, 720, 8.00, 'Smooth writing, long lasting', 'In Stock', 100, 'Cabinet B1', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(6, 'PEN-003', 'Red Ballpoint Pen', 2, 2, 350, 8.00, 'For checking and corrections', 'In Stock', 50, 'Cabinet B1', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(7, 'PEN-004', '#2 Pencil (Box of 12)', 2, 1, 49, 60.00, '12 pieces per box', 'In Stock', 10, 'Cabinet B2', '2026-05-17 06:08:44', '2026-05-17 06:55:59'),
(8, 'PEN-005', 'Mechanical Pencil (0.7mm)', 2, 3, 120, 35.00, 'With eraser and refills', 'In Stock', 20, 'Cabinet B2', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(9, 'PEN-006', 'Highlighter Set (4 colors)', 2, 1, 80, 120.00, 'Neon colors, chisel tip', 'In Stock', 15, 'Cabinet B3', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(10, 'ART-001', 'Crayon Set (24 colors)', 3, 4, 60, 95.00, 'Non-toxic, vibrant colors', 'In Stock', 10, 'Cabinet C1', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(11, 'ART-002', 'Watercolor Set (12 colors)', 3, 4, 40, 180.00, 'With brush, washable', 'In Stock', 8, 'Cabinet C2', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(12, 'ART-003', 'Colored Paper (Assorted, 50 pcs)', 3, 1, 200, 25.00, 'A4 size, 10 colors', 'In Stock', 30, 'Cabinet C3', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(13, 'ART-004', 'Construction Paper (10 colors)', 3, 3, 150, 35.00, 'For arts and crafts', 'In Stock', 25, 'Cabinet C3', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(14, 'PAP-001', 'A4 Bond Paper (500 sheets)', 4, 2, 90, 220.00, '70gsm, box of 5 reams', 'In Stock', 15, 'Cabinet D1', '2026-05-17 06:08:44', '2026-05-17 06:32:04'),
(15, 'PAP-002', 'Short Bond Paper (500 sheets)', 4, 2, 120, 200.00, '70gsm, box of 5 reams', 'In Stock', 20, 'Cabinet D1', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(16, 'FLD-001', 'Clear Book (A4, 40 pockets)', 5, 2, 95, 120.00, 'For documents and projects', 'In Stock', 15, 'Cabinet E1', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(17, 'FLD-002', 'Expandable Folder (7 pockets)', 5, 1, 110, 85.00, 'With tabs and label holder', 'In Stock', 15, 'Cabinet E2', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(18, 'MATH-001', 'Plastic Ruler (12 inch)', 6, 3, 300, 15.00, 'Clear plastic, metric and inches', 'In Stock', 50, 'Cabinet F1', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(19, 'MATH-002', 'Protractor (180 degrees)', 6, 1, 180, 20.00, 'Clear plastic, half circle', 'In Stock', 30, 'Cabinet F1', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(20, 'MATH-003', 'Scientific Calculator', 6, 2, 45, 450.00, 'For high school and college', 'In Stock', 10, 'Cabinet F2', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(21, 'CLASS-001', 'Whiteboard Marker (Set of 4)', 7, 1, 85, 120.00, 'Black, blue, red, green', 'In Stock', 15, 'Cabinet G1', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(22, 'CLASS-002', 'Whiteboard Eraser', 7, 3, 50, 45.00, 'Felt pad, magnetic', 'In Stock', 10, 'Cabinet G1', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(23, 'CLASS-003', 'Masking Tape (1 inch)', 7, 2, 200, 30.00, 'For arts and labeling', 'In Stock', 40, 'Cabinet G2', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(24, 'TECH-001', 'USB Flash Drive (32GB)', 8, 4, 35, 350.00, 'For teacher use', 'In Stock', 8, 'Cabinet H1', '2026-05-17 06:08:44', '2026-05-17 06:08:44'),
(25, 'TECH-002', 'Over-ear Headphones', 8, 4, 25, 550.00, 'For computer lab', 'In Stock', 5, 'Cabinet H2', '2026-05-17 06:08:44', '2026-05-17 06:08:44');

--
-- Triggers `school_supplies`
--
DELIMITER $$
CREATE TRIGGER `after_supply_update` AFTER UPDATE ON `school_supplies` FOR EACH ROW BEGIN
    IF OLD.quantity != NEW.quantity OR OLD.unit_price != NEW.unit_price THEN
        INSERT INTO audit_logs (log_id, user_id, action, module, details, ip_address)
        VALUES (
            CONCAT('LOG-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', FLOOR(RAND() * 10000)),
            @current_user_id,
            'UPDATE',
            'School Supplies',
            CONCAT('Supply "', NEW.supply_name, '" changed: Qty ', OLD.quantity, '→', NEW.quantity, ', Price ', OLD.unit_price, '→', NEW.unit_price),
            @current_ip
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `stock_in`
--

CREATE TABLE `stock_in` (
  `id` int(11) NOT NULL,
  `transaction_no` varchar(20) NOT NULL,
  `supply_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `purchase_order_no` varchar(100) DEFAULT NULL,
  `invoice_no` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `date_received` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_in`
--

INSERT INTO `stock_in` (`id`, `transaction_no`, `supply_id`, `supplier_id`, `quantity`, `purchase_order_no`, `invoice_no`, `remarks`, `received_by`, `date_received`, `created_at`) VALUES
(1, 'SI-2024-001', 1, 1, 100, 'PO-001', 'INV-001', NULL, 1, '2024-01-15', '2026-05-17 06:08:44'),
(2, 'SI-2024-002', 7, 2, 200, 'PO-002', 'INV-002', NULL, 1, '2024-01-20', '2026-05-17 06:08:44'),
(3, 'SI-2024-003', 15, 2, 50, 'PO-003', 'INV-003', NULL, 1, '2024-02-10', '2026-05-17 06:08:44'),
(4, 'SI-2024-004', 21, 1, 40, 'PO-004', 'INV-004', NULL, 1, '2024-02-15', '2026-05-17 06:08:44'),
(5, 'SI-20260517-1027', 7, 4, 2, NULL, NULL, '', 1, '2026-05-17', '2026-05-17 06:30:51'),
(6, 'SI-20260517-6198', 14, 4, 2, NULL, NULL, '', 1, '2026-05-17', '2026-05-17 06:31:10'),
(7, 'SI-20260517-1379', 7, 4, 2, NULL, NULL, '', 1, '2026-05-17', '2026-05-17 06:31:12'),
(8, 'SI-20260517-4409', 14, 4, 2, NULL, NULL, '', 1, '2026-05-17', '2026-05-17 06:31:15'),
(9, 'SI-20260517-8800', 7, 4, 2, NULL, NULL, '', 1, '2026-05-17', '2026-05-17 06:31:16'),
(10, 'SI-20260517-7349', 14, 4, 2, NULL, NULL, '', 1, '2026-05-17', '2026-05-17 06:31:19'),
(11, 'SI-20260517-4851', 14, 4, 2, NULL, NULL, '', 1, '2026-05-17', '2026-05-17 06:31:42'),
(12, 'SI-20260517-3779', 14, 4, 2, NULL, NULL, '', 1, '2026-05-17', '2026-05-17 06:32:04');

--
-- Triggers `stock_in`
--
DELIMITER $$
CREATE TRIGGER `after_stock_in_insert` AFTER INSERT ON `stock_in` FOR EACH ROW BEGIN
    DECLARE supply_qty INT;
    DECLARE supply_threshold INT;
    DECLARE supply_name VARCHAR(200);
    
    SELECT quantity, low_stock_threshold, supply_name INTO supply_qty, supply_threshold, supply_name
    FROM school_supplies WHERE id = NEW.supply_id;
    
    -- Create notification for low stock
    IF supply_qty <= supply_threshold THEN
        INSERT INTO notifications (title, message, type)
        VALUES ('⚠️ Low Stock Alert', CONCAT(supply_name, ' is running low! Only ', supply_qty, ' units left.'), 'warning');
    END IF;
    
    -- Create notification for successful stock-in
    INSERT INTO notifications (title, message, type)
    VALUES ('? Stock Received', CONCAT(NEW.quantity, ' units of ', supply_name, ' added to inventory.'), 'success');
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `stock_out`
--

CREATE TABLE `stock_out` (
  `id` int(11) NOT NULL,
  `transaction_no` varchar(20) NOT NULL,
  `supply_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `issued_to` varchar(100) NOT NULL COMMENT 'Student name, Teacher name, or Department',
  `id_number` varchar(50) DEFAULT NULL COMMENT 'Student/Teacher ID number',
  `grade_section` varchar(50) DEFAULT NULL COMMENT 'e.g., Grade 7 - Section A',
  `purpose` varchar(200) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `date_issued` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_out`
--

INSERT INTO `stock_out` (`id`, `transaction_no`, `supply_id`, `quantity`, `issued_to`, `id_number`, `grade_section`, `purpose`, `remarks`, `issued_by`, `date_issued`, `created_at`) VALUES
(1, 'SO-2024-001', 3, 100, 'Grade 7 Students', 'G7-001', 'Grade 7 - All Sections', 'First Quarter Writing Pads', NULL, 2, '2024-06-10', '2026-05-17 06:08:44'),
(2, 'SO-2024-002', 4, 200, 'Grade 8 Students', 'G8-001', 'Grade 8 - All Sections', 'First Quarter Pens', NULL, 2, '2024-06-10', '2026-05-17 06:08:44'),
(3, 'SO-2024-003', 19, 50, 'Math Department', 'MATH-001', 'Faculty', 'Geometry Class', NULL, 3, '2024-06-12', '2026-05-17 06:08:44'),
(4, 'SO-2024-004', 1, 80, 'Grade 9 Students', 'G9-001', 'Grade 9 - All Sections', 'Research Notebooks', NULL, 2, '2024-06-15', '2026-05-17 06:08:44'),
(7, 'SO-20260517-8179', 7, 2, 'karl', 'wdw', '4', 'Classroom Use', '', 1, '2026-05-17', '2026-05-17 06:55:59');

--
-- Triggers `stock_out`
--
DELIMITER $$
CREATE TRIGGER `after_stock_out_insert` AFTER INSERT ON `stock_out` FOR EACH ROW BEGIN
    DECLARE supply_qty INT;
    DECLARE supply_threshold INT;
    DECLARE supply_name VARCHAR(200);
    
    SELECT quantity, low_stock_threshold, supply_name INTO supply_qty, supply_threshold, supply_name
    FROM school_supplies WHERE id = NEW.supply_id;
    
    IF supply_qty <= supply_threshold THEN
        INSERT INTO notifications (title, message, type)
        VALUES ('⚠️ Low Stock Alert', CONCAT(supply_name, ' is running low! Only ', supply_qty, ' units left.'), 'warning');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `email`, `phone`, `address`, `is_active`, `created_at`) VALUES
(1, 'National Book Store', 'Ramon Dela Cruz', 'ramon@nationalbookstore.com', '02-8888-1234', 'Quezon Citye', 1, '2026-05-17 06:08:44'),
(2, 'Office Warehouse', 'Lisa Santos', 'lisa@officewarehouse.com', '02-8888-5678', 'Pasig City', 1, '2026-05-17 06:08:44'),
(3, 'School Supply Depot', 'Mike Reyes', 'mike@ssdepot.com', '02-8888-9012', 'Manila', 1, '2026-05-17 06:08:44'),
(4, 'Art Central', 'Ana Garcia', 'ana@artcentral.com', '02-8888-3456', 'Makati City', 1, '2026-05-17 06:08:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Administrator','Staff','Teacher') DEFAULT 'Staff',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `fullname`, `email`, `password`, `role`, `is_active`, `last_login`, `created_at`) VALUES
(1, 'admin', 'School Administrator', 'admin@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 1, '2026-05-17 14:54:04', '2026-05-17 06:08:44'),
(2, 'staff2', 'Maria Santos', 'maria.santos@school.edu', '$2y$10$H9E4bfaTOynVlP0/nrsF5e2oOChqrfiIihvz5lhVbNM5juvHKX4.6', 'Staff', 1, NULL, '2026-05-17 06:08:44'),
(3, 'staff1', 'John Cruz', 'john.cruz@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff', 1, '2026-05-17 14:48:48', '2026-05-17 06:08:44'),
(4, 'karl.nico.soniga', 'karl nico soniga', 'karlnicosoniga@gmail.com', '$2y$10$dXJ8jWFbeP9Y3qZSb5wU0e8uX4tRm3rccl18kR7rcz.nEu4Ph.ww.', 'Administrator', 1, NULL, '2026-05-17 06:48:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `log_id` (`log_id`),
  ADD KEY `audit_logs_ibfk_1` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `school_supplies`
--
ALTER TABLE `school_supplies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supply_code` (`supply_code`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `stock_in`
--
ALTER TABLE `stock_in`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_no` (`transaction_no`),
  ADD KEY `supply_id` (`supply_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `stock_in_ibfk_3` (`received_by`);

--
-- Indexes for table `stock_out`
--
ALTER TABLE `stock_out`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_no` (`transaction_no`),
  ADD KEY `supply_id` (`supply_id`),
  ADD KEY `stock_out_ibfk_2` (`issued_by`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `school_supplies`
--
ALTER TABLE `school_supplies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `stock_in`
--
ALTER TABLE `stock_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `stock_out`
--
ALTER TABLE `stock_out`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `school_supplies`
--
ALTER TABLE `school_supplies`
  ADD CONSTRAINT `school_supplies_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `school_supplies_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_in`
--
ALTER TABLE `stock_in`
  ADD CONSTRAINT `stock_in_ibfk_1` FOREIGN KEY (`supply_id`) REFERENCES `school_supplies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_in_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `stock_in_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_out`
--
ALTER TABLE `stock_out`
  ADD CONSTRAINT `stock_out_ibfk_1` FOREIGN KEY (`supply_id`) REFERENCES `school_supplies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_out_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
