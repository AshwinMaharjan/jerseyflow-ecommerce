-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 11, 2026 at 10:44 AM
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
-- Database: `jerseyflow_new`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`` PROCEDURE `safe_stock_movement` (IN `p_variant_id` INT UNSIGNED, IN `p_admin_id` INT UNSIGNED, IN `p_type` VARCHAR(20), IN `p_qty` INT, IN `p_reference` VARCHAR(60), IN `p_reason` TEXT, IN `p_note` TEXT, OUT `p_result` VARCHAR(100))   BEGIN
  DECLARE v_current_stock INT DEFAULT 0;
  DECLARE v_new_stock     INT DEFAULT 0;
  DECLARE v_product_id    INT UNSIGNED DEFAULT 0;
  DECLARE v_reorder_lvl   INT DEFAULT 5;

  -- Lock the row
  SELECT stock, product_id, reorder_level
  INTO   v_current_stock, v_product_id, v_reorder_lvl
  FROM   product_variants
  WHERE  variant_id = p_variant_id
  FOR UPDATE;

  -- Calculate new stock
  IF p_type = 'IN' OR p_type = 'RETURN' THEN
    SET v_new_stock = v_current_stock + p_qty;
  ELSEIF p_type = 'OUT' OR p_type = 'DAMAGE' THEN
    SET v_new_stock = v_current_stock - p_qty;
  ELSEIF p_type = 'ADJUST' THEN
    SET v_new_stock = p_qty;
  ELSE
    SET v_new_stock = v_current_stock + p_qty;
  END IF;

  -- Prevent negative stock
  IF v_new_stock < 0 THEN
    SET p_result = 'ERROR: Stock would go negative';
    ROLLBACK;
  ELSE
    -- Update variant stock
    UPDATE product_variants SET stock = v_new_stock, updated_at = NOW() WHERE variant_id = p_variant_id;

    -- Sync products.stock (sum of all variants)
    UPDATE products p
    SET    p.stock = (SELECT COALESCE(SUM(pv.stock),0) FROM product_variants pv WHERE pv.product_id = p.product_id)
    WHERE  p.product_id = v_product_id;

    -- Record movement
    INSERT INTO stock_movements
      (variant_id, product_id, admin_id, movement_type, quantity, stock_before, stock_after, reference_no, reason, note)
    VALUES
      (p_variant_id, v_product_id, p_admin_id, p_type, ABS(p_qty), v_current_stock, v_new_stock, p_reference, p_reason, p_note);

    -- Low stock / out of stock notification
    IF v_new_stock = 0 THEN
      INSERT INTO inventory_notifications (variant_id, product_id, type, message)
      SELECT p_variant_id, v_product_id, 'OUT_OF_STOCK',
             CONCAT('Variant #', p_variant_id, ' is now OUT OF STOCK.')
      WHERE NOT EXISTS (
        SELECT 1 FROM inventory_notifications
        WHERE variant_id = p_variant_id AND type = 'OUT_OF_STOCK' AND is_dismissed = 0
      );
    ELSEIF v_new_stock <= v_reorder_lvl THEN
      INSERT INTO inventory_notifications (variant_id, product_id, type, message)
      SELECT p_variant_id, v_product_id, 'LOW_STOCK',
             CONCAT('Variant #', p_variant_id, ' is LOW — only ', v_new_stock, ' units left.')
      WHERE NOT EXISTS (
        SELECT 1 FROM inventory_notifications
        WHERE variant_id = p_variant_id AND type = 'LOW_STOCK' AND is_dismissed = 0
      );
    END IF;

    SET p_result = CONCAT('OK:', v_new_stock);
  END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `size` varchar(10) NOT NULL,
  `quantity` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clubs`
--

CREATE TABLE `clubs` (
  `club_id` int(11) NOT NULL,
  `club_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `country_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clubs`
--

INSERT INTO `clubs` (`club_id`, `club_name`, `created_at`, `country_id`) VALUES
(1, 'FC Barcelona', '2026-03-29 14:53:56', 1),
(2, 'Real Madrid', '2026-03-29 14:53:56', 1),
(3, 'Manchester United', '2026-03-29 14:53:56', 2),
(4, 'Manchester City', '2026-03-29 14:53:56', 2),
(5, 'Liverpool FC', '2026-03-29 14:53:56', 2),
(6, 'Chelsea FC', '2026-03-29 14:53:56', 2),
(7, 'Arsenal FC', '2026-03-29 14:53:56', 2),
(8, 'Paris Saint-Germain', '2026-03-29 14:53:56', 5),
(9, 'Bayern Munich', '2026-03-29 14:53:56', 3),
(10, 'Borussia Dortmund', '2026-03-29 14:53:56', 3),
(11, 'Juventus', '2026-03-29 14:53:56', 4),
(12, 'AC Milan', '2026-03-29 14:53:56', 4),
(13, 'Inter Milan', '2026-03-29 14:53:56', 4),
(14, 'Atletico Madrid', '2026-03-29 14:53:56', 1),
(15, 'Tottenham Hotspur', '2026-03-29 14:53:56', 2),
(17, 'Al Nasr', '2026-03-29 16:03:12', 8),
(21, 'Aston Villa', '2026-04-04 10:43:11', 2),
(22, 'Newcastle United', '2026-04-04 10:43:22', 2),
(23, 'Fenerbahce', '2026-04-04 10:43:43', NULL),
(24, 'Flamengo', '2026-04-04 10:43:49', NULL),
(25, 'Porto', '2026-04-04 10:43:54', NULL),
(26, 'Galatasaray', '2026-04-04 10:44:05', NULL),
(27, 'Benfica', '2026-04-04 11:21:54', NULL),
(28, 'Celtic', '2026-04-04 11:22:39', NULL),
(29, 'Inter Miami', '2026-04-04 11:24:15', NULL),
(30, 'Napoli', '2026-04-04 11:25:06', NULL),
(31, 'AS Roma', '2026-04-04 11:25:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `country_id` int(11) NOT NULL,
  `country_name` varchar(100) NOT NULL,
  `sort_order` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`country_id`, `country_name`, `sort_order`, `created_at`) VALUES
(1, 'Spain', 1, '2026-03-29 14:50:24'),
(2, 'England', 2, '2026-03-29 14:50:24'),
(3, 'Germany', 3, '2026-03-29 14:50:24'),
(4, 'Italy', 4, '2026-03-29 14:50:24'),
(5, 'France', 5, '2026-03-29 14:50:24'),
(6, 'Portugal', 6, '2026-03-29 14:50:24'),
(7, 'Brazil', 7, '2026-03-29 14:50:24'),
(8, 'Argentina', 8, '2026-03-29 14:50:24'),
(9, 'Netherlands', 9, '2026-03-29 14:50:24'),
(10, 'Belgium', 10, '2026-03-29 14:50:24'),
(11, 'USA', 11, '2026-03-29 14:50:24'),
(12, 'Mexico', 12, '2026-03-29 14:50:24'),
(13, 'Japan', 13, '2026-03-29 14:50:24'),
(14, 'South Korea', 14, '2026-03-29 14:50:24'),
(15, 'Nigeria', 15, '2026-03-29 14:50:24'),
(16, 'Croatia', 16, '2026-03-29 14:50:24'),
(17, 'Morocco', 17, '2026-03-29 14:50:24'),
(18, 'Switzerland', 18, '2026-03-29 14:50:24'),
(19, 'Turkey', 19, '2026-03-29 14:50:24'),
(20, 'Saudi Arabia', 20, '2026-03-29 14:50:24'),
(24, 'Nepal', NULL, '2026-03-29 16:01:56'),
(25, 'Algeria', NULL, '2026-04-03 08:44:54'),
(26, 'Australia', NULL, '2026-04-03 08:55:44'),
(27, 'Canada', NULL, '2026-04-03 09:02:36');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_notifications`
--

CREATE TABLE `inventory_notifications` (
  `notif_id` int(10) UNSIGNED NOT NULL,
  `variant_id` int(10) UNSIGNED DEFAULT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('LOW_STOCK','OUT_OF_STOCK','REORDER','SYSTEM') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_dismissed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_notifications`
--

INSERT INTO `inventory_notifications` (`notif_id`, `variant_id`, `product_id`, `type`, `message`, `is_read`, `is_dismissed`, `created_at`) VALUES
(1, 31, 31, 'LOW_STOCK', 'Variant #31 is LOW — only 5 units left.', 1, 0, '2026-04-04 16:11:19'),
(2, 33, 33, 'LOW_STOCK', 'Variant #33 is LOW — only 4 units left.', 1, 0, '2026-04-04 16:33:02'),
(3, 34, 34, 'LOW_STOCK', 'Variant #34 is LOW — only 3 units left.', 1, 0, '2026-04-04 16:34:14'),
(4, 35, 35, 'LOW_STOCK', 'Variant #35 is LOW — only 4 units left.', 1, 0, '2026-04-04 16:35:45'),
(5, 36, 36, 'LOW_STOCK', 'Variant #36 is LOW — only 2 units left.', 1, 0, '2026-04-04 16:39:57'),
(6, 37, 37, 'LOW_STOCK', 'Variant #37 is LOW — only 2 units left.', 1, 0, '2026-04-04 16:41:44'),
(7, 38, 38, 'LOW_STOCK', 'Variant #38 is LOW — only 2 units left.', 1, 0, '2026-04-04 16:42:30'),
(8, 39, 39, 'LOW_STOCK', 'Variant #39 is LOW — only 3 units left.', 1, 0, '2026-04-04 16:43:14');

-- --------------------------------------------------------

--
-- Table structure for table `kits`
--

CREATE TABLE `kits` (
  `kit_id` int(11) NOT NULL,
  `kit_name` varchar(50) NOT NULL,
  `sort_order` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kits`
--

INSERT INTO `kits` (`kit_id`, `kit_name`, `sort_order`) VALUES
(1, 'Home', 1),
(2, 'Away', 2),
(3, 'Third', 3),
(4, 'Goalkeeper', 4);

-- --------------------------------------------------------

--
-- Table structure for table `new_orders`
--

CREATE TABLE `new_orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_status` enum('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(50) DEFAULT NULL COMMENT 'e.g. khalti, cod, esewa',
  `shipping_address` text DEFAULT NULL COMMENT 'JSON snapshot of address at time of order',
  `khalti_pidx` varchar(100) DEFAULT NULL COMMENT 'Khalti payment identifier returned on initiation',
  `khalti_txn_id` varchar(100) DEFAULT NULL COMMENT 'Khalti transaction ID confirmed after verification',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master order records for JerseyFlow';

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `payment_status` enum('unpaid','paid','failed','refunded') DEFAULT 'unpaid',
  `method_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `esewa_transaction_uuid` varchar(255) DEFAULT NULL,
  `esewa_ref_id` varchar(255) DEFAULT NULL,
  `address_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `total_amount`, `order_status`, `payment_status`, `method_id`, `created_at`, `updated_at`, `esewa_transaction_uuid`, `esewa_ref_id`, `address_id`) VALUES
(1, 2, 2000.00, 'delivered', 'paid', 2, '2026-04-10 15:22:02', '2026-04-10 16:20:19', NULL, NULL, 1),
(2, 2, 1250.00, 'delivered', 'paid', 2, '2026-04-10 16:22:11', '2026-04-10 16:23:31', NULL, NULL, 1),
(3, 3, 1750.00, 'processing', 'paid', 1, '2026-04-11 06:26:48', '2026-04-11 06:26:48', '20260411-082248-3', '000EU5I', 3),
(4, 3, 1350.00, 'pending', 'paid', 2, '2026-04-11 06:27:45', '2026-04-11 08:33:17', NULL, NULL, 3);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'price × quantity at time of purchase'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Line items belonging to each order';

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `variant_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 15, NULL, 1, 2000.00, 2000.00),
(2, 2, 38, 38, 1, 1250.00, 1250.00),
(3, 3, 47, 47, 1, 1750.00, 1750.00),
(4, 4, 27, 27, 1, 1350.00, 1350.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_tracking`
--

CREATE TABLE `order_tracking` (
  `tracking_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL,
  `note` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `gateway` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `order_id`, `amount`, `payment_status`, `gateway`, `transaction_id`, `failure_reason`, `paid_at`, `created_at`) VALUES
(1, 1, 3350.00, 'pending', 'cod', NULL, NULL, NULL, '2026-04-09 15:57:49'),
(2, 2, 2000.00, 'paid', 'esewa', '20260410-083339-2', NULL, '2026-04-10 12:20:04', '2026-04-10 06:35:04'),
(3, 3, 1350.00, 'pending', 'cod', NULL, NULL, NULL, '2026-04-10 14:49:57'),
(4, 4, 3550.00, 'pending', 'cod', NULL, NULL, NULL, '2026-04-10 14:51:03'),
(5, 5, 2900.00, 'pending', 'cod', NULL, NULL, NULL, '2026-04-10 15:00:46'),
(6, 6, 3250.00, 'pending', 'cod', NULL, NULL, NULL, '2026-04-10 15:09:14'),
(7, 7, 3000.00, 'pending', 'cod', NULL, NULL, NULL, '2026-04-10 15:12:00'),
(8, 1, 2000.00, 'pending', 'cod', NULL, NULL, NULL, '2026-04-10 15:22:02'),
(9, 2, 1250.00, 'pending', 'cod', NULL, NULL, NULL, '2026-04-10 16:22:11'),
(10, 3, 1750.00, 'paid', 'esewa', '20260411-082248-3', NULL, '2026-04-11 12:11:48', '2026-04-11 06:26:48'),
(11, 4, 1350.00, 'pending', 'cod', NULL, NULL, NULL, '2026-04-11 06:27:45');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `method_id` int(11) NOT NULL,
  `method_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`method_id`, `method_name`, `is_active`, `created_at`) VALUES
(1, 'Esewa', 1, '2026-04-08 14:29:14'),
(2, 'COD', 1, '2026-04-08 14:29:23');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL,
  `club_id` int(11) DEFAULT NULL,
  `size_id` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `kit_id` int(11) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `special_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `price`, `stock`, `club_id`, `size_id`, `image`, `description`, `created_at`, `kit_id`, `country_id`, `special_type`) VALUES
(1, 'Algeria World Cup 2026', 1200.00, 10, NULL, 2, NULL, 'The Algeria 2026 Home Jersey is a replica edition designed for the World Cup 2026 tournament featuring a white base with green accents that reflect the nation\'s traditional colors while maintaining a clean and balanced modern design with simple yet effective contrast detailing; it is associated with Algeria\'s participation in the 2026 World Cup campaign and as a replica version it mirrors the official on-field match kit design while being intended for supporter and retail use.', '2026-04-04 06:25:36', 1, 25, 'worldcup_2026'),
(2, 'Argentina World Cup 2026', 1400.00, 25, NULL, 3, NULL, 'The Argentina 2026 Home Jersey is a replica edition designed for the World Cup 2026 tournament featuring a white base with blue accents that reflect the nation\'s traditional football identity while maintaining a clean and balanced design with simple, recognizable contrast elements; it is associated with Argentina\'s participation in the 2026 World Cup campaign and as a replica version it mirrors the official on-field match kit design while being intended for supporter and retail use.', '2026-04-04 06:26:44', 1, 8, 'worldcup_2026'),
(3, 'Australia World Cup 2026', 1000.00, 10, NULL, 2, NULL, 'The Australia 2026 Away Jersey is a replica edition designed for the World Cup 2026 tournament featuring a black base with green accents that offer a bold and modern contrast while still reflecting the national team\'s color identity; the design maintains a clean and structured layout suitable for official match representation and is associated with Australia\'s participation in the 2026 World Cup campaign, and as a replica version it mirrors the on-field kit design while being intended for supporter and retail use.', '2026-04-04 06:27:51', 2, 26, 'worldcup_2026'),
(4, 'Belgium World Cup 2026', 1300.00, 12, NULL, 2, NULL, 'The Belgium 2026 Home Jersey is a replica edition created for the World Cup 2026 season. It features a bold red base complemented by yellow accents, reflecting the national flag colors. The design maintains a balanced and clean layout suitable for official match representation. It is associated with Belgium\'s participation in the 2026 World Cup campaign. As a replica version, it replicates the on-field design while being intended for supporter use.', '2026-04-04 06:28:43', 1, 10, 'worldcup_2026'),
(5, 'Brazil World Cup 2026', 1400.00, 20, NULL, 3, NULL, 'The Brazil 2026 Home Jersey is a replica edition designed for the World Cup 2026 tournament. It features a deep dark blue base with subtle yellow accents, reflecting a modern variation of Brazil\'s traditional color identity. The design maintains a clean and structured layout with minimal but noticeable contrast detailing. It is associated with Brazil\'s national squad participation in the 2026 World Cup campaign. As a replica version, it mirrors the official match kit design while being intended for supporter and retail use.', '2026-04-04 06:29:27', 2, 7, 'worldcup_2026'),
(6, 'Canada World Cup 2026', 1300.00, 16, NULL, 2, NULL, 'The Canada 2026 Home Jersey is a replica edition designed for the World Cup 2026 tournament featuring a clean red and white color scheme that reflects Canada\'s national identity while maintaining a balanced and modern design approach with simple yet strong contrast detailing; it is associated with Canada\'s participation in the 2026 World Cup campaign and as a replica version it mirrors the official match kit design used on-field while being intended for supporter and retail use.', '2026-04-04 06:30:20', 2, 27, 'worldcup_2026'),
(7, 'France World Cup 2026', 1650.00, 16, NULL, 3, NULL, 'The France 2026 Home Jersey is a replica edition designed for the World Cup 2026 tournament featuring a white and blue color scheme that reflects the team\'s traditional national identity while maintaining a clean and balanced modern design with subtle contrast detailing; it is associated with France\'s participation in the 2026 World Cup campaign and as a replica version it mirrors the official on-field match kit design while being intended for supporter and retail use.', '2026-04-04 06:31:04', 2, 5, 'worldcup_2026'),
(8, 'Germany World Cup 2026', 1250.00, 10, NULL, 2, NULL, 'The Germany 2026 Home Jersey is a replica edition designed for the World Cup 2026 tournament featuring a white base with black detailing and subtle yellow accents that reflect the nation\'s traditional football identity while maintaining a clean, structured, and modern design approach; it is associated with Germany\'s participation in the 2026 World Cup campaign and as a replica version it mirrors the official on-field match kit design while being intended for supporter and retail use.', '2026-04-04 06:32:01', 1, 3, 'worldcup_2026'),
(9, 'Japan World Cup 2026', 1350.00, 17, NULL, 3, NULL, 'The Japan 2026 Home Jersey is a replica edition designed for the World Cup 2026 tournament featuring a blue base with white accents that reflect the nation\'s traditional football identity while maintaining a clean and modern design with balanced contrast detailing; it is associated with Japan\'s participation in the 2026 World Cup campaign and as a replica version it mirrors the official on-field match kit design while being intended for supporter and retail use.', '2026-04-04 06:32:59', 2, 13, 'worldcup_2026'),
(10, 'South Korea World Cup 2026', 1300.00, 10, NULL, 2, NULL, 'The South Korea 2026 Away Jersey is a replica edition designed for the World Cup 2026 tournament featuring a red and white color scheme that reflects the team\'s national identity while maintaining a clean, modern, and balanced design with simple contrast detailing; it is associated with South Korea\'s participation in the 2026 World Cup campaign and as a replica version it mirrors the official on-field match kit design while being intended for supporter and retail use.', '2026-04-04 06:33:52', 2, 14, 'worldcup_2026'),
(11, 'Mexico World Cup  2026', 1450.00, 10, NULL, 3, NULL, 'The Mexico 2026 Away Jersey is a replica edition designed for the World Cup 2026 tournament featuring a green and white color scheme that reflects the nation\'s traditional football identity while maintaining a clean and structured modern design with balanced contrast detailing; it is associated with Mexico\'s participation in the 2026 World Cup campaign and as a replica version it mirrors the official on-field match kit design while being intended for supporter and retail use.', '2026-04-04 06:34:49', 2, 12, 'worldcup_2026'),
(12, 'Portugal World Cup 2026', 1500.00, 19, NULL, 3, NULL, 'The Portugal 2026 Third Jersey is a replica edition designed for the World Cup 2026 tournament featuring a black base with gold accents that create a bold, premium contrast while maintaining a clean and modern design approach with refined detailing; it is associated with Portugal\'s participation in the 2026 World Cup campaign and as a replica version it mirrors the official on-field match kit design while being intended for supporter and retail use.', '2026-04-04 06:35:45', 3, 6, 'worldcup_2026'),
(13, 'Spain World Cup 2026', 1400.00, 11, NULL, 3, NULL, 'The Spain 2026 Home Jersey is a replica edition designed for the World Cup 2026 tournament featuring a red base with yellow accents that reflect the nation\'s traditional football identity while maintaining a clean, classic, and balanced modern design with simple contrast detailing; it is associated with Spain\'s participation in the 2026 World Cup campaign and as a replica version it mirrors the official on-field match kit design while being intended for supporter and retail use.', '2026-04-04 06:36:31', 2, 1, 'worldcup_2026'),
(14, 'USA World Cup 2026', 1200.00, 10, NULL, 3, NULL, 'The USA 2026 Away Jersey is a replica edition designed for the World Cup 2026 tournament featuring a blue base with white accents that reflect the nation\'s football identity while maintaining a clean, modern, and structured design with subtle contrast detailing; it is associated with the United States participation in the 2026 World Cup campaign and as a replica version it mirrors the official on-field match kit design while being intended for supporter and retail use.', '2026-04-04 06:37:12', 2, 11, 'worldcup_2026'),
(15, 'AC Milan Retro Jersey', 2000.00, 23, 12, 2, NULL, 'The AC Milan 1994-95 retro Home Jersey is a classic replica edition inspired by one of the club\'s historic eras, featuring the traditional red and black vertical stripes that represent Milan\'s iconic identity; designed as a retro reissue, it reflects the football aesthetic of the mid-1990s while maintaining a simple, bold, and timeless look without modern performance enhancements; the jersey is associated with AC Milan\'s competitive legacy during that period in European football and domestic competitions; as a retro-style kit, it is intended for collectors and fans rather than current match use; it preserves authentic color blocking and vintage styling cues that highlight the club\'s traditional visual identity.', '2026-04-04 06:40:17', 1, NULL, 'retro'),
(16, 'Arsenal Retro Jersey', 2100.00, 18, 7, 3, NULL, 'The Arsenal FC 1995-96 retro Away Jersey is a classic replica edition inspired by a vintage period of the club, featuring a yellow base with blue accents that reflect Arsenal\'s traditional away color identity used in earlier football eras; the design follows a simple, bold aesthetic with minimal detailing, focusing on strong color contrast and a clean retro look typical of mid-1990s kits; it is associated with Arsenal\'s historical season context rather than any modern competition, and as a retro jersey it is intended for collectors and supporters who value classic football kit heritage rather than current match wear.', '2026-04-04 06:42:36', 2, NULL, 'retro'),
(17, 'Barcelona Retro Jersey', 2100.00, 16, 1, 2, NULL, 'The FC Barcelona 1998-99 retro Home Jersey is a classic replica edition inspired by a historic era of the club, featuring the traditional maroon and blue vertical stripes that represent Barcelona\'s iconic identity; the design reflects late-1990s football styling with a simple, bold layout and minimal modern detailing, focusing on strong color contrast and a traditional striped pattern; it is associated with Barcelona\'s historical season context rather than any current competition, and as a retro jersey it is intended for collectors and fans who appreciate vintage football kits and the club\'s heritage rather than modern match use.', '2026-04-04 06:44:03', 1, NULL, 'retro'),
(18, 'Bayern Munich Retro Jersey', 2100.00, 20, 9, 3, NULL, 'The Bayern Munich 2000-01 retro Home Jersey is a classic replica edition inspired by one of the club\'s historic eras, featuring a red base with blue accents that reflect Bayern\'s traditional identity in a simplified retro interpretation; the design follows an early-2000s football aesthetic with clean lines, minimal detailing, and a strong focus on bold color blocking rather than modern performance styling; it is associated with Bayern Munich\'s competitive legacy during that season in domestic and European competitions, and as a retro jersey it is intended for collectors and supporters who value vintage football heritage rather than current match use.', '2026-04-04 06:45:34', 1, NULL, 'retro'),
(19, 'Inter Milan Retro Jersey', 1900.00, 12, 13, 4, NULL, 'The Inter Milan 1997-98 retro Third Jersey is a classic replica edition inspired by a historic era of the club, featuring a blue and black color scheme that reflects Inter\'s traditional identity in a bold alternative design used during that period; the jersey follows a late-1990s football aesthetic with a clean, minimal layout and strong emphasis on color contrast rather than modern detailing or performance materials; it is associated with Inter Milan\'s competitive context of that season in domestic and European competitions, and as a retro jersey it is intended for collectors and fans who appreciate vintage football kits and the club\'s heritage rather than current match use.', '2026-04-04 06:47:16', 3, NULL, 'retro'),
(20, 'Manchester United Retro Jersey', 2000.00, 8, 3, 3, NULL, 'The Manchester United 1993-94 retro Home Jersey is a classic replica edition inspired by a historic era of the club, featuring a red base with black accents that reflect the traditional Manchester United identity in a simplified vintage interpretation; the design follows early Premier League-era styling with bold color blocking, minimal detailing, and a strong, clean layout typical of 1990s kits; it is associated with Manchester United\'s competitive legacy during that season in domestic and European football, and as a retro jersey it is intended for collectors and supporters who value classic football heritage rather than modern match use.', '2026-04-04 06:48:45', 1, NULL, 'retro'),
(21, 'Real Madrid Retro Jersey', 2200.00, 18, 2, 4, NULL, 'The Real Madrid CF 2006-07 retro Third Jersey is a classic replica edition inspired by a notable era of the club, featuring a black base with white accents that reflect a bold alternative color scheme used in historic third-kit designs; the layout follows mid-2000s football styling with a clean, minimal aesthetic and strong contrast detailing that emphasizes simplicity and elegance; it is associated with Real Madrid\'s competitive legacy during that season in domestic and European competitions, and as a retro jersey it is intended for collectors and supporters who appreciate vintage football kits rather than current match use.', '2026-04-04 06:49:56', 3, NULL, 'retro'),
(22, 'AC Milan 2025/26', 1350.00, 11, 12, 3, NULL, 'The AC Milan 2025-26 Third Jersey is a replica edition featuring a yellow base with black accents that create a high-contrast, modern look while still aligning with the club\'s alternative kit identity; the design focuses on a clean and contemporary aesthetic with sharp detailing and structured color blocking that differentiates it from the traditional home and away kits; it is associated with AC Milan\'s participation in the 2025-26 season across domestic and European competitions, and as a standard replica jersey it is intended for supporters and retail use while reflecting the official on-field match design.', '2026-04-04 06:52:50', 2, NULL, 'standard'),
(23, 'FC Barcelona 2025/26', 1450.00, 10, 1, 3, NULL, 'The FC Barcelona 2025-26 Home Jersey is a replica edition featuring the traditional blue and red vertical stripes that represent the club\'s iconic identity, updated with a modern, streamlined layout for the current season; the design maintains a balanced blend of classic heritage and contemporary styling with clean striping, subtle detailing, and refined color transitions; it is associated with Barcelona\'s participation in the 2025-26 season across domestic and European competitions, and as a standard replica jersey it is intended for supporters and retail use while reflecting the official on-field match design.', '2026-04-04 07:17:20', 1, NULL, 'standard'),
(24, 'Juventues 2025/26', 1200.00, 17, 11, 4, NULL, 'The Juventus FC 2025-26 Home Jersey is a replica edition featuring the traditional black and white striped design that represents the club\'s historic identity, updated with a modern interpretation for the current season; the layout maintains a clean and structured aesthetic with refined striping and subtle detailing that balances heritage with contemporary style; it is associated with Juventus participation in the 2025-26 season across domestic and European competitions, and as a standard replica jersey it is intended for supporters and retail use while reflecting the official on-field match design.', '2026-04-04 07:21:50', 1, NULL, 'standard'),
(25, 'Liverpool 2025/26', 1350.00, 11, 5, 2, NULL, 'The Liverpool FC 2025-26 Home Jersey is a replica edition featuring a red base with white accents that reflect the club\'s traditional identity, presented with a modern, streamlined design approach for the current season; the layout emphasizes a clean and balanced aesthetic with subtle detailing and refined color contrast that maintains Liverpool\'s classic home kit heritage; it is associated with Liverpool\'s participation in the 2025-26 season across domestic and European competitions, and as a standard replica jersey it is intended for supporters and retail use while reflecting the official on-field match design.', '2026-04-04 07:23:30', 1, NULL, 'standard'),
(26, 'Manchester United 2025/26', 1450.00, 14, 3, 3, NULL, 'The Manchester United 2025-26 Third Jersey is a replica edition featuring a black base with yellow accents that create a bold, high-contrast modern look while maintaining the club\'s alternative kit identity for the season; the design follows a clean, contemporary aesthetic with sharp detailing and structured color blocking that distinguishes it from the home and away kits; it is associated with Manchester United\'s participation in the 2025-26 season across domestic and European competitions, and as a standard replica jersey it is intended for supporters and retail use while reflecting the official on-field match design.', '2026-04-04 07:24:47', 3, NULL, 'standard'),
(27, 'Real Madrid 2025/26', 1350.00, 7, 2, 4, NULL, 'The Real Madrid CF 2025-26 Home Jersey is a replica edition featuring the club\'s traditional white base with black accents that reflect its classic identity while adding a modern, refined contrast for the current season; the design maintains a clean and elegant aesthetic with minimal detailing and structured color highlights that emphasize simplicity and heritage; it is associated with Real Madrid\'s participation in the 2025-26 season across domestic and European competitions, and as a standard replica jersey it is intended for supporters and retail use while reflecting the official on-field match design.', '2026-04-04 07:25:51', 1, NULL, 'standard'),
(28, 'England World Cup 2026', 1300.00, 7, NULL, 3, NULL, 'The England national football team 2025-26 Home Jersey is a replica edition designed for the World Cup 2026 campaign featuring a white base with blue accents that reflect the nation\'s traditional football identity while maintaining a clean and modern design with balanced contrast detailing; it is associated with England\'s participation in the 2026 World Cup and as a replica version it mirrors the official on-field match kit design while being intended for supporter and retail use.', '2026-04-04 09:44:19', 1, 2, 'worldcup_2026'),
(29, 'Chelsea Retro Jersey', 2500.00, 15, 6, 4, NULL, 'The Chelsea FC 2007-08 retro Home Jersey is a classic replica edition inspired by the club\'s late-2000s era, featuring a blue base with white accents that reflect Chelsea\'s traditional identity, designed with a clean and structured look typical of that period with minimal detailing and strong color contrast; it is associated with Chelsea\'s competitive performances during the 2007-08 season and as a retro jersey it is intended for collectors and fans who appreciate vintage football heritage rather than modern match use.', '2026-04-04 10:00:53', 1, NULL, 'retro'),
(30, 'Liverpool Retro Jersey', 2300.00, 19, 5, 3, NULL, 'The Liverpool FC 2009-10 retro Home Jersey is a classic replica edition inspired by the club\'s late-2000s era, featuring a cream-toned base with red accents that provide a distinctive variation from the traditional home colors while maintaining a simple and balanced retro design with minimal detailing; it is associated with Liverpool\'s participation during the 2009-10 season and as a retro jersey it is intended for collectors and supporters who value classic football kits rather than current match wear.', '2026-04-04 10:01:30', 1, NULL, 'retro'),
(31, 'Arsenal Limited Edition', 1450.00, 5, 7, 2, NULL, 'The Arsenal FC 2025-26 Home Jersey is a limited edition replica featuring a white base with red accents that reinterpret the club\'s traditional color identity in a clean and modern design; the layout emphasizes a balanced aesthetic with subtle detailing and refined contrast that distinguishes it from standard releases; it is associated with Arsenal\'s 2025-26 season across domestic and European competitions, and as a limited edition jersey it is intended for collectors and supporters while still reflecting the official on-field design elements.', '2026-04-04 10:26:19', 1, NULL, 'limited'),
(32, 'PSG Limited Edition', 1300.00, 6, 8, 2, NULL, 'The Paris Saint-Germain 2025-26 Home Jersey is a limited edition replica featuring a black base with gold accents that create a bold and premium look while maintaining a clean and modern design with refined detailing and strong contrast; it is associated with PSG\'s participation in the 2025-26 season across domestic and European competitions, and as a limited edition jersey it is intended for collectors and supporters while reflecting elements of the official on-field design.', '2026-04-04 10:46:53', 3, NULL, 'limited'),
(33, 'Chelsea Limited Edition', 1350.00, 4, 6, 4, NULL, 'The Chelsea FC 2025-26 Home Jersey is a limited edition replica featuring a blue base with white accents that reflect the club\'s traditional identity, designed with a modern and structured layout that emphasizes clarity, balance, and subtle detailing; it is associated with Chelsea\'s participation in the 2025-26 season across domestic and European competitions, and as a limited edition jersey it is intended for collectors and supporters while reflecting the official on-field match design.', '2026-04-04 10:48:02', 3, NULL, 'limited'),
(34, 'Aston Villa Limited Edition', 1250.00, 3, 21, 2, NULL, 'The Aston Villa FC 2025-26 Home Jersey is a limited edition replica featuring a brown base with white accents that present a unique variation of the club\'s traditional identity, designed with a clean and modern aesthetic that emphasizes balanced color contrast and minimal detailing; it is associated with Aston Villa\'s participation in the 2025-26 season across domestic and European competitions, and as a limited edition jersey it is intended for collectors and supporters while reflecting elements of the official on-field design.', '2026-04-04 10:49:14', 1, NULL, 'limited'),
(35, 'Newcastle United Limited Edition', 1100.00, 4, 22, 1, NULL, 'The Newcastle United FC 2025-26 Home Jersey is a limited edition replica featuring the classic black and white striped design that represents the club\'s historic identity, updated with a modern and refined layout that maintains strong visual contrast and a clean structure; it is associated with Newcastle United\'s participation in the 2025-26 season across domestic and European competitions, and as a limited edition jersey it is intended for collectors and supporters while reflecting the official on-field match design.', '2026-04-04 10:50:45', 1, NULL, 'limited'),
(36, 'Fenerbahce Limited Edition', 1350.00, 2, 23, 4, NULL, 'The Fenerbahce SK 2025-26 Away Jersey is a limited edition replica featuring a blue base with yellow accents that reflect the club\'s traditional colors while maintaining a clean and modern design with balanced contrast detailing; it is associated with Fenerbahce\'s participation in the 2025-26 season across domestic and European competitions, and as a limited edition jersey it is intended for collectors and supporters while reflecting elements of the official on-field design.', '2026-04-04 10:54:57', 2, NULL, 'limited'),
(37, 'Flamengo Limited Edition', 1350.00, 1, 24, 1, NULL, 'The CR Flamengo 2025-26 Home Jersey is a limited edition replica featuring the iconic red and black color combination that represents the club\'s identity, designed with a modern and structured layout that emphasizes bold contrast and clean detailing; it is associated with Flamengo\'s participation in the 2025-26 season across domestic and continental competitions, and as a limited edition jersey it is intended for collectors and supporters while reflecting the official on-field match design.', '2026-04-04 10:56:43', 1, NULL, 'limited'),
(38, 'FC Porto Limited Edition', 1250.00, 1, 25, 4, NULL, 'The FC Porto 2025-26 Home Jersey is a limited edition replica featuring the traditional blue and white design that defines the club\'s identity, presented with a clean and modern aesthetic that balances classic striping with refined detailing; it is associated with Porto\'s participation in the 2025-26 season across domestic and European competitions, and as a limited edition jersey it is intended for collectors and supporters while reflecting the official on-field match design.', '2026-04-04 10:57:29', 1, NULL, 'limited'),
(39, 'Galatasaray SK Limited Edition', 1450.00, 3, 26, 3, NULL, 'The Galatasaray SK 2025-26 Home Jersey is a limited edition replica featuring a maroon and orange color scheme that reflects the club\'s iconic identity, designed with a bold yet balanced layout that highlights strong color contrast and minimal detailing; it is associated with Galatasaray\'s participation in the 2025-26 season across domestic and European competitions, and as a limited edition jersey it is intended for collectors and supporters while reflecting the official on-field match design.', '2026-04-04 10:58:14', 1, NULL, 'limited'),
(40, 'Al Nassr Player Edition', 1350.00, 13, 17, 2, NULL, 'The Al Nassr FC 2025-26 Home Jersey Player Edition is designed in a blue base with yellow accents, representing the club\'s identity while featuring a performance-focused construction tailored to match on-field standards; associated with Cristiano Ronaldo, this version mirrors the professional kit worn during the season and is intended for high-performance wear and collectors seeking authentic player-level detailing.', '2026-04-04 11:20:28', 1, NULL, 'player_edition'),
(41, 'Atletico Madrid Player Edition', 1450.00, 10, 14, 1, NULL, 'The Atletico Madrid 2025-26 Home Jersey Player Edition features a blue and red color combination in a modernized layout that maintains the club\'s traditional identity while incorporating advanced fabric and fit; linked with Diego Costa, it reflects the match-ready design used during the season and is intended for those seeking an authentic on-field experience.', '2026-04-04 11:21:03', 1, NULL, 'player_edition'),
(42, 'SL Benfica Player Edition', 1350.00, 10, 27, 1, NULL, 'The SL Benfica 2025-26 Home Jersey Player Edition showcases a red base with white accents in a clean and structured design that aligns with the club\'s historic look while offering performance-focused construction; associated with Angel Di Maria, it mirrors the professional kit used during matches and is intended for high-performance use and collectors.', '2026-04-04 11:22:24', 1, NULL, 'player_edition'),
(43, 'Celtic FC Player Edition', 1250.00, 18, 28, 3, NULL, 'The Celtic FC 2025-26 Away Jersey Player Edition features a green and white color scheme with a modern layout that maintains the club\'s recognizable identity while integrating lightweight, match-ready materials; associated with Cameron Carter-Vickers, it reflects the on-field kit design and is intended for performance-focused wear.', '2026-04-04 11:23:15', 2, NULL, 'player_edition'),
(44, 'Manchester City Player Edition', 1350.00, 10, 4, 4, NULL, 'The Manchester City FC 2025-26 Away Jersey Player Edition presents a blue base with white accents in a sleek and contemporary design that emphasizes clarity and movement; linked with Phil Foden, it mirrors the exact kit worn during matches and is tailored for high-performance use and authenticity.', '2026-04-04 11:23:57', 2, NULL, 'player_edition'),
(45, 'Inter Miami Player Edition', 1550.00, 16, 29, 4, NULL, 'The Inter Miami CF 2025-26 Home Jersey Player Edition presents a pink and black color scheme in a sleek and contemporary design that emphasizes clarity and movement; linked with Lionel Messi, it mirrors the exact kit worn during matches and is tailored for high-performance use and authenticity.', '2026-04-04 11:24:57', 2, NULL, 'player_edition'),
(46, 'SSC Napoli Player Edition', 1250.00, 9, 30, 4, NULL, 'The SSC Napoli 2025-26 Home Jersey Player Edition features a blue base with yellow accents in a clean and refined layout that aligns with the club\'s identity while incorporating advanced performance materials; associated with Khvicha Kvaratskhelia, it reflects the match-ready design used during the season and is intended for professional-level use.', '2026-04-04 11:25:34', 2, NULL, 'player_edition'),
(47, 'AS Roma Player Edition', 1750.00, 7, 31, 2, NULL, 'The AS Roma 2025-26 Home Jersey Player Edition presents a red base with orange accents in a bold and structured design that reflects the club\'s traditional colors while maintaining a modern aesthetic; linked with Paulo Dybala, it mirrors the official on-field kit and is intended for performance-focused wear and collectors.', '2026-04-04 11:26:28', 2, NULL, 'player_edition'),
(48, 'Tottenham Hotspur FC Player Edition', 1250.00, 6, 15, 4, NULL, 'The Tottenham Hotspur FC 2025-26 Home Jersey Player Edition features a blue and white color scheme in a clean and contemporary layout that reflects the club\'s identity while incorporating match-ready construction; associated with Son Heung-min, it mirrors the professional kit worn during the season and is intended for high-performance use and authenticity.', '2026-04-04 11:27:00', 2, NULL, 'player_edition');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `image_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`image_id`, `product_id`, `image_path`, `is_primary`, `created_at`) VALUES
(1, 1, 'product_69d0aee04cd813.52677138.png', 1, '2026-04-04 06:25:36'),
(2, 1, 'product_69d0aee04fff02.52525905.jpeg', 0, '2026-04-04 06:25:36'),
(3, 1, 'product_69d0aee05044a6.85017513.jpeg', 0, '2026-04-04 06:25:36'),
(4, 1, 'product_69d0aee050a9c4.03151131.jpeg', 0, '2026-04-04 06:25:36'),
(5, 2, 'product_69d0af2446c7b0.35068781.png', 1, '2026-04-04 06:26:44'),
(6, 2, 'product_69d0af244883e0.29074496.png', 0, '2026-04-04 06:26:44'),
(7, 2, 'product_69d0af24498d07.47658025.png', 0, '2026-04-04 06:26:44'),
(8, 2, 'product_69d0af244a6221.81641700.jpeg', 0, '2026-04-04 06:26:44'),
(9, 3, 'product_69d0af67924623.74484893.png', 1, '2026-04-04 06:27:51'),
(10, 3, 'product_69d0af679332b9.42267250.jpeg', 0, '2026-04-04 06:27:51'),
(11, 3, 'product_69d0af6794a604.81546257.jpeg', 0, '2026-04-04 06:27:51'),
(12, 3, 'product_69d0af67951a73.16478456.jpeg', 0, '2026-04-04 06:27:51'),
(13, 4, 'product_69d0af9b0c7551.24998196.png', 1, '2026-04-04 06:28:43'),
(14, 4, 'product_69d0af9b0d4ea1.84065311.jpeg', 0, '2026-04-04 06:28:43'),
(15, 4, 'product_69d0af9b0de003.87035957.jpeg', 0, '2026-04-04 06:28:43'),
(16, 4, 'product_69d0af9b0f2ec9.84366416.jpeg', 0, '2026-04-04 06:28:43'),
(17, 5, 'product_69d0afc7398197.40683791.png', 1, '2026-04-04 06:29:27'),
(18, 5, 'product_69d0afc73a0232.86815768.jpeg', 0, '2026-04-04 06:29:27'),
(19, 5, 'product_69d0afc73a8849.15082603.jpeg', 0, '2026-04-04 06:29:27'),
(20, 5, 'product_69d0afc73b6fc0.43680720.jpeg', 0, '2026-04-04 06:29:27'),
(21, 6, 'product_69d0affca57471.57382970.png', 1, '2026-04-04 06:30:20'),
(22, 6, 'product_69d0affca608b3.34945027.jpeg', 0, '2026-04-04 06:30:20'),
(23, 6, 'product_69d0affca66277.15052930.jpeg', 0, '2026-04-04 06:30:20'),
(24, 6, 'product_69d0affca6cc73.20359175.jpeg', 0, '2026-04-04 06:30:20'),
(25, 7, 'product_69d0b02833e558.18900759.png', 1, '2026-04-04 06:31:04'),
(26, 7, 'product_69d0b028348498.93050252.jpeg', 0, '2026-04-04 06:31:04'),
(27, 7, 'product_69d0b028350b82.94438149.jpeg', 0, '2026-04-04 06:31:04'),
(28, 7, 'product_69d0b028357df6.81453949.jpeg', 0, '2026-04-04 06:31:04'),
(29, 8, 'product_69d0b061ea6c28.71163246.png', 1, '2026-04-04 06:32:01'),
(30, 8, 'product_69d0b061eb6a01.73797538.jpeg', 0, '2026-04-04 06:32:01'),
(31, 8, 'product_69d0b061ebbc19.03685779.jpeg', 0, '2026-04-04 06:32:02'),
(32, 8, 'product_69d0b061ec07f9.01835161.jpeg', 0, '2026-04-04 06:32:02'),
(33, 9, 'product_69d0b09bd353f1.93325504.png', 1, '2026-04-04 06:32:59'),
(34, 9, 'product_69d0b09bd40ca8.14984505.jpeg', 0, '2026-04-04 06:32:59'),
(35, 9, 'product_69d0b09bd4b8d9.79305803.jpeg', 0, '2026-04-04 06:32:59'),
(36, 9, 'product_69d0b09bd52b48.57212790.jpeg', 0, '2026-04-04 06:32:59'),
(37, 10, 'product_69d0b0d0533230.58304861.png', 1, '2026-04-04 06:33:52'),
(38, 10, 'product_69d0b0d05438b7.60252198.png', 0, '2026-04-04 06:33:52'),
(39, 10, 'product_69d0b0d05628b7.15452082.png', 0, '2026-04-04 06:33:52'),
(40, 10, 'product_69d0b0d056a898.71232896.jpeg', 0, '2026-04-04 06:33:52'),
(41, 11, 'product_69d0b109d4d564.45952464.png', 1, '2026-04-04 06:34:49'),
(42, 11, 'product_69d0b109d57d28.27135168.jpeg', 0, '2026-04-04 06:34:49'),
(43, 11, 'product_69d0b109d5efd4.59844581.jpeg', 0, '2026-04-04 06:34:49'),
(44, 11, 'product_69d0b109dac513.38924873.jpeg', 0, '2026-04-04 06:34:49'),
(45, 12, 'product_69d0b14140e308.52933484.png', 1, '2026-04-04 06:35:45'),
(46, 12, 'product_69d0b141428955.41214423.jpeg', 0, '2026-04-04 06:35:45'),
(47, 12, 'product_69d0b1414328d0.30330362.jpeg', 0, '2026-04-04 06:35:45'),
(48, 12, 'product_69d0b1414387d8.60411939.jpeg', 0, '2026-04-04 06:35:45'),
(49, 13, 'product_69d0b16fc72276.52814863.png', 1, '2026-04-04 06:36:31'),
(50, 13, 'product_69d0b16fc7da74.69009494.jpeg', 0, '2026-04-04 06:36:31'),
(51, 13, 'product_69d0b16fc89a24.59371659.jpeg', 0, '2026-04-04 06:36:31'),
(52, 13, 'product_69d0b16fc94a75.54683307.jpeg', 0, '2026-04-04 06:36:31'),
(53, 14, 'product_69d0b198ba0052.81361294.png', 1, '2026-04-04 06:37:12'),
(54, 14, 'product_69d0b198baeff0.28523972.jpeg', 0, '2026-04-04 06:37:12'),
(55, 14, 'product_69d0b198bbc3d2.86283156.jpeg', 0, '2026-04-04 06:37:12'),
(56, 14, 'product_69d0b198bcb3e3.00929373.jpeg', 0, '2026-04-04 06:37:12'),
(57, 15, 'product_69d0b251d1cbd2.01068344.png', 1, '2026-04-04 06:40:17'),
(58, 15, 'product_69d0b251d54fa6.58550602.jpeg', 0, '2026-04-04 06:40:17'),
(59, 15, 'product_69d0b251d65b81.43949613.jpeg', 0, '2026-04-04 06:40:17'),
(60, 15, 'product_69d0b251d6d872.06724987.jpeg', 0, '2026-04-04 06:40:17'),
(61, 16, 'product_69d0b2dced0ae3.71812868.png', 1, '2026-04-04 06:42:37'),
(62, 16, 'product_69d0b2dcef83e2.73223248.jpeg', 0, '2026-04-04 06:42:37'),
(63, 16, 'product_69d0b2dcf042d6.12128189.jpeg', 0, '2026-04-04 06:42:37'),
(64, 16, 'product_69d0b2dcf0d109.50412201.jpeg', 0, '2026-04-04 06:42:37'),
(65, 17, 'product_69d0b333896746.68225368.png', 1, '2026-04-04 06:44:03'),
(66, 17, 'product_69d0b3338bbca0.40778722.jpg', 0, '2026-04-04 06:44:03'),
(67, 17, 'product_69d0b3338c2ca3.04538175.jpg', 0, '2026-04-04 06:44:03'),
(68, 17, 'product_69d0b3338c9a34.02783483.jpg', 0, '2026-04-04 06:44:03'),
(69, 18, 'product_69d0b38e372271.62526045.png', 1, '2026-04-04 06:45:34'),
(70, 18, 'product_69d0b38e3b26d5.83389604.jpeg', 0, '2026-04-04 06:45:34'),
(71, 18, 'product_69d0b38e3b97d0.10815785.jpg', 0, '2026-04-04 06:45:34'),
(72, 18, 'product_69d0b38e3bf8a3.09174936.jpg', 0, '2026-04-04 06:45:34'),
(73, 19, 'product_69d0b3f4a11ef7.48924778.png', 1, '2026-04-04 06:47:16'),
(74, 19, 'product_69d0b3f4a50015.64934150.jpeg', 0, '2026-04-04 06:47:16'),
(75, 19, 'product_69d0b3f4a579c9.26428638.jpeg', 0, '2026-04-04 06:47:16'),
(76, 19, 'product_69d0b3f4a600f2.33691829.jpeg', 0, '2026-04-04 06:47:16'),
(77, 20, 'product_69d0b44d879423.77521155.png', 1, '2026-04-04 06:48:45'),
(78, 20, 'product_69d0b44d89ee36.53011588.jpeg', 0, '2026-04-04 06:48:45'),
(79, 20, 'product_69d0b44d8a4bd3.85531804.jpeg', 0, '2026-04-04 06:48:45'),
(80, 20, 'product_69d0b44d8bb788.30940978.jpeg', 0, '2026-04-04 06:48:45'),
(81, 21, 'product_69d0b494d378e1.80678157.webp', 1, '2026-04-04 06:49:56'),
(82, 21, 'product_69d0b494d46498.33097144.jpeg', 0, '2026-04-04 06:49:57'),
(83, 21, 'product_69d0b494d4d048.13586719.jpeg', 0, '2026-04-04 06:49:57'),
(84, 21, 'product_69d0b494d55103.62617822.jpeg', 0, '2026-04-04 06:49:57'),
(85, 22, 'product_69d0b542b62448.66418701.png', 1, '2026-04-04 06:52:50'),
(86, 22, 'product_69d0b542b7f551.31279279.png', 0, '2026-04-04 06:52:50'),
(87, 22, 'product_69d0b542b85712.09097748.png', 0, '2026-04-04 06:52:50'),
(88, 23, 'product_69d0bb00396059.52901770.png', 1, '2026-04-04 07:17:20'),
(89, 23, 'product_69d0bb003b3393.39149976.png', 0, '2026-04-04 07:17:20'),
(90, 23, 'product_69d0bb003bc104.72098040.png', 0, '2026-04-04 07:17:20'),
(91, 23, 'product_69d0bb003c2973.94857799.png', 0, '2026-04-04 07:17:20'),
(92, 24, 'product_69d0bc0e9647e2.62707629.png', 1, '2026-04-04 07:21:50'),
(93, 24, 'product_69d0bc0e981474.89376928.png', 0, '2026-04-04 07:21:50'),
(94, 24, 'product_69d0bc0e988b77.70871772.png', 0, '2026-04-04 07:21:50'),
(95, 24, 'product_69d0bc0e98e7b0.04216695.png', 0, '2026-04-04 07:21:50'),
(96, 25, 'product_69d0bc725b1573.66106320.png', 1, '2026-04-04 07:23:30'),
(97, 25, 'product_69d0bc725c6450.40442709.png', 0, '2026-04-04 07:23:30'),
(98, 25, 'product_69d0bc725ce699.45671347.png', 0, '2026-04-04 07:23:30'),
(99, 25, 'product_69d0bc725d6123.32718618.png', 0, '2026-04-04 07:23:30'),
(100, 26, 'product_69d0bcbf540829.85271769.png', 1, '2026-04-04 07:24:47'),
(101, 26, 'product_69d0bcbf5555d5.04233427.png', 0, '2026-04-04 07:24:47'),
(102, 26, 'product_69d0bcbf570a21.06097035.jpeg', 0, '2026-04-04 07:24:47'),
(103, 26, 'product_69d0bcbf577ee5.82300049.jpeg', 0, '2026-04-04 07:24:47'),
(104, 27, 'product_69d0bcff035ea7.09359781.png', 1, '2026-04-04 07:25:51'),
(105, 27, 'product_69d0bcff05e921.95283087.png', 0, '2026-04-04 07:25:51'),
(106, 27, 'product_69d0bcff06b940.62458204.png', 0, '2026-04-04 07:25:51'),
(107, 27, 'product_69d0bcff073b47.21912026.png', 0, '2026-04-04 07:25:51'),
(108, 28, 'product_69d0dd734ecb57.84560654.png', 1, '2026-04-04 09:44:19'),
(109, 28, 'product_69d0dd73533bd2.67712665.png', 0, '2026-04-04 09:44:19'),
(110, 28, 'product_69d0dd73540ee5.77452122.png', 0, '2026-04-04 09:44:19'),
(111, 28, 'product_69d0dd7355e9f3.48738609.jpeg', 0, '2026-04-04 09:44:19'),
(112, 29, 'product_69d0e155d40726.61899193.png', 1, '2026-04-04 10:00:53'),
(113, 29, 'product_69d0e155d6dee3.01035110.jpg', 0, '2026-04-04 10:00:53'),
(114, 29, 'product_69d0e155d74a01.13678431.jpg', 0, '2026-04-04 10:00:54'),
(115, 29, 'product_69d0e155d9c001.89911771.png', 0, '2026-04-04 10:00:54'),
(116, 30, 'product_69d0e17ac04500.40344704.png', 1, '2026-04-04 10:01:30'),
(117, 30, 'product_69d0e17ac11307.47853930.jpg', 0, '2026-04-04 10:01:30'),
(118, 30, 'product_69d0e17ac19569.57544487.jpg', 0, '2026-04-04 10:01:30'),
(119, 30, 'product_69d0e17ac2d3a7.69035854.png', 0, '2026-04-04 10:01:30'),
(120, 31, 'product_69d0e74b3e9f62.63183524.png', 1, '2026-04-04 10:26:19'),
(121, 31, 'product_69d0e74b418ac8.43860344.jpg', 0, '2026-04-04 10:26:19'),
(122, 31, 'product_69d0e74b421304.05095152.png', 0, '2026-04-04 10:26:19'),
(123, 31, 'product_69d0e74b542e52.65968531.webp', 0, '2026-04-04 10:26:19'),
(124, 32, 'product_69d0ec1d0721b8.96172077.png', 1, '2026-04-04 10:46:53'),
(125, 32, 'product_69d0ec1d2d21d2.18382831.webp', 0, '2026-04-04 10:46:53'),
(126, 32, 'product_69d0ec1d2f5f61.39687445.png', 0, '2026-04-04 10:46:53'),
(127, 32, 'product_69d0ec1d300bd4.78554716.jpeg', 0, '2026-04-04 10:46:53'),
(128, 33, 'product_69d0ec61df8f70.74139107.png', 1, '2026-04-04 10:48:02'),
(129, 33, 'product_69d0ec61e23b65.98649877.png', 0, '2026-04-04 10:48:02'),
(130, 33, 'product_69d0ec62205511.54150523.webp', 0, '2026-04-04 10:48:02'),
(131, 33, 'product_69d0ec6223cd05.28297043.jpeg', 0, '2026-04-04 10:48:02'),
(132, 34, 'product_69d0ecaaa70a45.43379318.png', 1, '2026-04-04 10:49:14'),
(133, 34, 'product_69d0ecaabd64c3.89885309.webp', 0, '2026-04-04 10:49:14'),
(134, 34, 'product_69d0ecaabe1364.50868067.webp', 0, '2026-04-04 10:49:14'),
(135, 34, 'product_69d0ecaac09683.99236526.jpeg', 0, '2026-04-04 10:49:14'),
(136, 35, 'product_69d0ed051f4183.18341662.png', 1, '2026-04-04 10:50:45'),
(137, 35, 'product_69d0ed053493d3.33760028.webp', 0, '2026-04-04 10:50:45'),
(138, 35, 'product_69d0ed05365629.98186772.jpeg', 0, '2026-04-04 10:50:45'),
(139, 35, 'product_69d0ed0536d073.62591221.png', 0, '2026-04-04 10:50:45'),
(140, 36, 'product_69d0ee0133f3c4.03132625.png', 1, '2026-04-04 10:54:57'),
(141, 36, 'product_69d0ee0137db96.95435673.jpeg', 0, '2026-04-04 10:54:57'),
(142, 36, 'product_69d0ee014bcd78.36008934.webp', 0, '2026-04-04 10:54:57'),
(143, 36, 'product_69d0ee014c7c42.33278006.png', 0, '2026-04-04 10:54:57'),
(144, 37, 'product_69d0ee6bb47540.93626590.png', 1, '2026-04-04 10:56:43'),
(145, 37, 'product_69d0ee6bb5f669.49448170.png', 0, '2026-04-04 10:56:43'),
(146, 37, 'product_69d0ee6bb84507.35016922.jpeg', 0, '2026-04-04 10:56:43'),
(147, 37, 'product_69d0ee6bc88aa6.01471946.webp', 0, '2026-04-04 10:56:43'),
(148, 38, 'product_69d0ee99eb3c73.09496014.webp', 1, '2026-04-04 10:57:29'),
(149, 38, 'product_69d0ee99eddba6.42591273.jpeg', 0, '2026-04-04 10:57:30'),
(150, 38, 'product_69d0ee99eea451.18034905.png', 0, '2026-04-04 10:57:30'),
(151, 38, 'product_69d0ee99f10852.53018388.png', 0, '2026-04-04 10:57:30'),
(152, 39, 'product_69d0eec6509a24.01651468.png', 1, '2026-04-04 10:58:14'),
(153, 39, 'product_69d0eec6520ea0.08482640.jpeg', 0, '2026-04-04 10:58:14'),
(154, 39, 'product_69d0eec652d124.84098211.png', 0, '2026-04-04 10:58:14'),
(155, 39, 'product_69d0eec6539eb2.80348209.webp', 0, '2026-04-04 10:58:14'),
(156, 40, 'product_69d0f3fc7c34b3.76108662.png', 1, '2026-04-04 11:20:28'),
(157, 40, 'product_69d0f3fc7e1027.18036959.png', 0, '2026-04-04 11:20:28'),
(158, 40, 'product_69d0f3fc92c656.55576496.jpg', 0, '2026-04-04 11:20:28'),
(159, 40, 'product_69d0f3fc938a85.83097044.png', 0, '2026-04-04 11:20:28'),
(160, 41, 'product_69d0f41f22f912.01859710.png', 1, '2026-04-04 11:21:03'),
(161, 41, 'product_69d0f41f283939.42691567.png', 0, '2026-04-04 11:21:03'),
(162, 41, 'product_69d0f41f2a84f5.92394772.png', 0, '2026-04-04 11:21:03'),
(163, 41, 'product_69d0f41f2c5d87.75409449.webp', 0, '2026-04-04 11:21:03'),
(164, 42, 'product_69d0f470597c06.15379308.png', 1, '2026-04-04 11:22:24'),
(165, 42, 'product_69d0f4705c68b0.84789501.webp', 0, '2026-04-04 11:22:24'),
(166, 42, 'product_69d0f4705dc447.78846018.jpeg', 0, '2026-04-04 11:22:24'),
(167, 42, 'product_69d0f4705e6f02.47360761.png', 0, '2026-04-04 11:22:24'),
(168, 43, 'product_69d0f4a30ac6d9.08816996.png', 1, '2026-04-04 11:23:15'),
(169, 43, 'product_69d0f4a30b9fb5.32303954.png', 0, '2026-04-04 11:23:15'),
(170, 43, 'product_69d0f4a30bf303.48390477.jpg', 0, '2026-04-04 11:23:15'),
(171, 43, 'product_69d0f4a30c88a6.88938009.png', 0, '2026-04-04 11:23:15'),
(172, 44, 'product_69d0f4cd58d687.54011042.png', 1, '2026-04-04 11:23:57'),
(173, 44, 'product_69d0f4cd65fa83.48649078.webp', 0, '2026-04-04 11:23:57'),
(174, 44, 'product_69d0f4cd66f841.88189767.png', 0, '2026-04-04 11:23:57'),
(175, 44, 'product_69d0f4cd677b71.83924619.jpeg', 0, '2026-04-04 11:23:57'),
(176, 45, 'product_69d0f509cafd16.49064999.png', 1, '2026-04-04 11:24:57'),
(177, 45, 'product_69d0f509cc1a09.22041107.png', 0, '2026-04-04 11:24:57'),
(178, 45, 'product_69d0f509cff109.03126486.webp', 0, '2026-04-04 11:24:57'),
(179, 45, 'product_69d0f509d17877.13270681.png', 0, '2026-04-04 11:24:57'),
(180, 46, 'product_69d0f52e1546f2.67467903.png', 1, '2026-04-04 11:25:34'),
(181, 46, 'product_69d0f52e164d85.67821426.webp', 0, '2026-04-04 11:25:34'),
(182, 46, 'product_69d0f52e181514.21419494.jpeg', 0, '2026-04-04 11:25:34'),
(183, 46, 'product_69d0f52e18e1c4.12941678.png', 0, '2026-04-04 11:25:34'),
(184, 47, 'product_69d0f5646ef488.25832908.png', 1, '2026-04-04 11:26:28'),
(185, 47, 'product_69d0f56471ad01.77307460.webp', 0, '2026-04-04 11:26:28'),
(186, 47, 'product_69d0f56472eca9.73104671.png', 0, '2026-04-04 11:26:28'),
(187, 47, 'product_69d0f56473ee00.86487443.png', 0, '2026-04-04 11:26:28'),
(188, 48, 'product_69d0f584356a37.30597614.png', 1, '2026-04-04 11:27:00'),
(189, 48, 'product_69d0f584375026.42898651.jpg', 0, '2026-04-04 11:27:00'),
(190, 48, 'product_69d0f5843bfab4.64976495.webp', 0, '2026-04-04 11:27:00'),
(191, 48, 'product_69d0f5843df2c3.37694356.jpeg', 0, '2026-04-04 11:27:00');

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `variant_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `size` enum('XS','S','M','L','XL','XXL','XXXL') NOT NULL,
  `color` varchar(50) NOT NULL DEFAULT 'Default',
  `sku` varchar(80) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 5,
  `reorder_qty` int(11) NOT NULL DEFAULT 20,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`variant_id`, `product_id`, `size`, `color`, `sku`, `stock`, `reorder_level`, `reorder_qty`, `cost_price`, `is_active`, `created_at`, `updated_at`, `price`) VALUES
(1, 1, 'M', 'Default', 'JF-00001-M-DEF', 10, 5, 20, NULL, 1, '2026-04-04 12:10:36', '2026-04-10 20:53:55', 1200.00),
(2, 2, 'L', 'Default', 'JF-00002-L-DEF', 25, 5, 20, NULL, 1, '2026-04-04 12:11:44', '2026-04-10 20:53:55', 1400.00),
(3, 3, 'M', 'Default', 'JF-00003-M-DEF', 10, 5, 20, NULL, 1, '2026-04-04 12:12:51', '2026-04-10 20:53:55', 1000.00),
(4, 4, 'M', 'Default', 'JF-00004-M-DEF', 12, 5, 20, NULL, 1, '2026-04-04 12:13:43', '2026-04-10 20:53:55', 1300.00),
(5, 5, 'L', 'Default', 'JF-00005-L-DEF', 20, 5, 20, NULL, 1, '2026-04-04 12:14:27', '2026-04-10 20:53:55', 1400.00),
(6, 6, 'M', 'Default', 'JF-00006-M-DEF', 16, 5, 20, NULL, 1, '2026-04-04 12:15:20', '2026-04-10 20:53:55', 1300.00),
(7, 7, 'L', 'Default', 'JF-00007-L-DEF', 16, 5, 20, NULL, 1, '2026-04-04 12:16:04', '2026-04-10 20:53:55', 1650.00),
(8, 8, 'M', 'Default', 'JF-00008-M-DEF', 10, 5, 20, NULL, 1, '2026-04-04 12:17:02', '2026-04-10 20:53:55', 1250.00),
(9, 9, 'L', 'Default', 'JF-00009-L-DEF', 17, 5, 20, NULL, 1, '2026-04-04 12:18:00', '2026-04-10 20:53:55', 1350.00),
(10, 10, 'M', 'Default', 'JF-00010-M-DEF', 10, 5, 20, NULL, 1, '2026-04-04 12:18:52', '2026-04-10 20:53:55', 1300.00),
(11, 11, 'L', 'Default', 'JF-00011-L-DEF', 10, 5, 20, NULL, 1, '2026-04-04 12:19:49', '2026-04-10 20:53:55', 1450.00),
(12, 12, 'L', 'Default', 'JF-00012-L-DEF', 20, 5, 20, NULL, 1, '2026-04-04 12:20:45', '2026-04-10 20:53:55', 1500.00),
(13, 13, 'L', 'Default', 'JF-00013-L-DEF', 12, 5, 20, NULL, 1, '2026-04-04 12:21:31', '2026-04-10 20:53:55', 1400.00),
(14, 14, 'L', 'Default', 'JF-00014-L-DEF', 10, 5, 20, NULL, 1, '2026-04-04 12:22:12', '2026-04-10 20:53:55', 1200.00),
(15, 15, 'M', 'Default', 'JF-00015-M-DEF', 25, 5, 20, NULL, 1, '2026-04-04 12:25:17', '2026-04-10 20:53:55', 2000.00),
(16, 16, 'L', 'Default', 'JF-00016-L-DEF', 19, 5, 20, NULL, 1, '2026-04-04 12:27:37', '2026-04-10 20:53:55', 2100.00),
(17, 17, 'M', 'Default', 'JF-00017-M-DEF', 17, 5, 20, NULL, 1, '2026-04-04 12:29:03', '2026-04-10 20:53:55', 2100.00),
(18, 18, 'L', 'Default', 'JF-00018-L-DEF', 20, 5, 20, NULL, 1, '2026-04-04 12:30:34', '2026-04-10 20:53:55', 2100.00),
(19, 19, 'XL', 'Default', 'JF-00019-XL-DEF', 12, 5, 20, NULL, 1, '2026-04-04 12:32:16', '2026-04-10 20:53:55', 1900.00),
(20, 20, 'L', 'Default', 'JF-00020-L-DEF', 10, 5, 20, NULL, 1, '2026-04-04 12:33:45', '2026-04-10 20:53:55', 2000.00),
(21, 21, 'XL', 'Default', 'JF-00021-XL-DEF', 19, 5, 20, NULL, 1, '2026-04-04 12:34:57', '2026-04-10 20:53:55', 2200.00),
(22, 22, 'L', 'Default', 'JF-00022-L-DEF', 11, 5, 20, NULL, 1, '2026-04-04 12:37:50', '2026-04-10 20:53:55', 1350.00),
(23, 23, 'L', 'Default', 'JF-00023-L-DEF', 10, 5, 20, NULL, 1, '2026-04-04 13:02:20', '2026-04-10 20:53:55', 1450.00),
(24, 24, 'XL', 'Default', 'JF-00024-XL-DEF', 18, 5, 20, NULL, 1, '2026-04-04 13:06:50', '2026-04-10 20:53:55', 1200.00),
(25, 25, 'M', 'Default', 'JF-00025-M-DEF', 12, 5, 20, NULL, 1, '2026-04-04 13:08:30', '2026-04-10 20:53:55', 1350.00),
(26, 26, 'L', 'Default', 'JF-00026-L-DEF', 14, 5, 20, NULL, 1, '2026-04-04 13:09:47', '2026-04-10 20:53:55', 1450.00),
(27, 27, 'XL', 'Default', 'JF-00027-XL-DEF', 11, 5, 20, NULL, 1, '2026-04-04 13:10:51', '2026-04-10 20:53:55', 1350.00),
(28, 28, 'L', 'Default', 'JF-00028-L-DEF', 7, 5, 20, NULL, 1, '2026-04-04 15:29:19', '2026-04-10 20:53:55', 1300.00),
(29, 29, 'XL', 'Default', 'JF-00029-XL-DEF', 15, 5, 20, NULL, 1, '2026-04-04 15:45:54', '2026-04-10 20:53:55', 2500.00),
(30, 30, 'L', 'Default', 'JF-00030-L-DEF', 19, 5, 20, NULL, 1, '2026-04-04 15:46:31', '2026-04-10 20:53:55', 2300.00),
(31, 31, 'M', 'Default', 'JF-00031-M-DEF', 5, 5, 20, NULL, 1, '2026-04-04 16:11:19', '2026-04-10 20:53:55', 1450.00),
(32, 32, 'M', 'Default', 'JF-00032-M-DEF', 6, 5, 20, NULL, 1, '2026-04-04 16:31:53', '2026-04-10 20:53:55', 1300.00),
(33, 33, 'XL', 'Default', 'JF-00033-XL-DEF', 4, 5, 20, NULL, 1, '2026-04-04 16:33:02', '2026-04-10 20:53:55', 1350.00),
(34, 34, 'M', 'Default', 'JF-00034-M-DEF', 3, 5, 20, NULL, 1, '2026-04-04 16:34:14', '2026-04-10 20:53:55', 1250.00),
(35, 35, 'S', 'Default', 'JF-00035-S-DEF', 4, 5, 20, NULL, 1, '2026-04-04 16:35:45', '2026-04-10 20:53:55', 1100.00),
(36, 36, 'XL', 'Default', 'JF-00036-XL-DEF', 2, 5, 20, NULL, 1, '2026-04-04 16:39:57', '2026-04-10 20:53:55', 1350.00),
(37, 37, 'S', 'Default', 'JF-00037-S-DEF', 2, 5, 20, NULL, 1, '2026-04-04 16:41:43', '2026-04-10 20:53:55', 1350.00),
(38, 38, 'XL', 'Default', 'JF-00038-XL-DEF', 2, 5, 20, NULL, 1, '2026-04-04 16:42:30', '2026-04-10 20:53:55', 1250.00),
(39, 39, 'L', 'Default', 'JF-00039-L-DEF', 3, 5, 20, NULL, 1, '2026-04-04 16:43:14', '2026-04-10 20:53:55', 1450.00),
(40, 40, 'M', 'Default', 'JF-00040-M-DEF', 13, 5, 20, NULL, 1, '2026-04-04 17:05:28', '2026-04-10 20:53:55', 1350.00),
(41, 41, 'S', 'Default', 'JF-00041-S-DEF', 10, 5, 20, NULL, 1, '2026-04-04 17:06:03', '2026-04-10 20:53:55', 1450.00),
(42, 42, 'S', 'Default', 'JF-00042-S-DEF', 10, 5, 20, NULL, 1, '2026-04-04 17:07:24', '2026-04-10 20:53:55', 1350.00),
(43, 43, 'L', 'Default', 'JF-00043-L-DEF', 18, 5, 20, NULL, 1, '2026-04-04 17:08:15', '2026-04-10 20:53:55', 1250.00),
(44, 44, 'XL', 'Default', 'JF-00044-XL-DEF', 10, 5, 20, NULL, 1, '2026-04-04 17:08:57', '2026-04-10 20:53:55', 1350.00),
(45, 45, 'XL', 'Default', 'JF-00045-XL-DEF', 16, 5, 20, NULL, 1, '2026-04-04 17:09:57', '2026-04-10 20:53:55', 1550.00),
(46, 46, 'XL', 'Default', 'JF-00046-XL-DEF', 10, 5, 20, NULL, 1, '2026-04-04 17:10:34', '2026-04-10 20:53:55', 1250.00),
(47, 47, 'M', 'Default', 'JF-00047-M-DEF', 10, 5, 20, NULL, 1, '2026-04-04 17:11:28', '2026-04-10 20:53:55', 1750.00),
(48, 48, 'XL', 'Default', 'JF-00048-XL-DEF', 8, 5, 20, NULL, 1, '2026-04-04 17:12:00', '2026-04-10 20:48:01', 1250.00);

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `refund_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `refund_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reorder_requests`
--

CREATE TABLE `reorder_requests` (
  `reorder_id` int(10) UNSIGNED NOT NULL,
  `variant_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `qty_requested` int(11) NOT NULL,
  `status` enum('PENDING','ORDERED','RECEIVED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `supplier_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `review_text` text DEFAULT NULL,
  `status` enum('visible','hidden') DEFAULT 'visible',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sizes`
--

CREATE TABLE `sizes` (
  `size_id` int(11) NOT NULL,
  `size_name` varchar(10) NOT NULL,
  `sort_order` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sizes`
--

INSERT INTO `sizes` (`size_id`, `size_name`, `sort_order`) VALUES
(1, 'S', 1),
(2, 'M', 2),
(3, 'L', 3),
(4, 'XL', 4);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `movement_id` int(10) UNSIGNED NOT NULL,
  `variant_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `movement_type` enum('IN','OUT','ADJUST','RETURN','DAMAGE','TRANSFER') NOT NULL,
  `quantity` int(11) NOT NULL,
  `stock_before` int(11) NOT NULL,
  `stock_after` int(11) NOT NULL,
  `reference_no` varchar(60) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`movement_id`, `variant_id`, `product_id`, `admin_id`, `movement_type`, `quantity`, `stock_before`, `stock_after`, `reference_no`, `reason`, `note`, `created_at`) VALUES
(1, 1, 1, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-03 14:33:56'),
(2, 2, 2, 0, 'IN', 19, 0, 19, '0', 'Initial stock on product creation', '', '2026-04-03 14:40:11'),
(3, 3, 3, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-03 14:41:50'),
(4, 4, 4, 0, 'IN', 7, 0, 7, '0', 'Initial stock on product creation', '', '2026-04-03 14:43:56'),
(5, 5, 5, 0, 'IN', 15, 0, 15, '0', 'Initial stock on product creation', '', '2026-04-03 14:46:00'),
(6, 6, 6, 0, 'IN', 15, 0, 15, '0', 'Initial stock on product creation', '', '2026-04-03 14:47:40'),
(7, 7, 7, 0, 'IN', 16, 0, 16, '0', 'Initial stock on product creation', '', '2026-04-03 14:48:24'),
(8, 8, 8, 0, 'IN', 8, 0, 8, '0', 'Initial stock on product creation', '', '2026-04-03 14:49:29'),
(9, 9, 9, 0, 'IN', 19, 0, 19, '0', 'Initial stock on product creation', '', '2026-04-03 14:55:45'),
(10, 10, 10, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 09:46:34'),
(11, 11, 11, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 09:57:28'),
(12, 11, 11, 0, 'ADJUST', 100, 100, 100, '0', 'Stock updated via edit product', '', '2026-04-04 10:33:55'),
(13, 1, 1, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 12:10:36'),
(14, 2, 2, 0, 'IN', 25, 0, 25, '0', 'Initial stock on product creation', '', '2026-04-04 12:11:44'),
(15, 3, 3, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 12:12:51'),
(16, 4, 4, 0, 'IN', 12, 0, 12, '0', 'Initial stock on product creation', '', '2026-04-04 12:13:43'),
(17, 5, 5, 0, 'IN', 20, 0, 20, '0', 'Initial stock on product creation', '', '2026-04-04 12:14:27'),
(18, 6, 6, 0, 'IN', 16, 0, 16, '0', 'Initial stock on product creation', '', '2026-04-04 12:15:20'),
(19, 7, 7, 0, 'IN', 16, 0, 16, '0', 'Initial stock on product creation', '', '2026-04-04 12:16:04'),
(20, 8, 8, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 12:17:02'),
(21, 9, 9, 0, 'IN', 17, 0, 17, '0', 'Initial stock on product creation', '', '2026-04-04 12:18:00'),
(22, 10, 10, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 12:18:52'),
(23, 11, 11, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 12:19:49'),
(24, 12, 12, 0, 'IN', 20, 0, 20, '0', 'Initial stock on product creation', '', '2026-04-04 12:20:45'),
(25, 13, 13, 0, 'IN', 12, 0, 12, '0', 'Initial stock on product creation', '', '2026-04-04 12:21:31'),
(26, 14, 14, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 12:22:12'),
(27, 15, 15, 0, 'IN', 25, 0, 25, '0', 'Initial stock on product creation', '', '2026-04-04 12:25:18'),
(28, 16, 16, 0, 'IN', 19, 0, 19, '0', 'Initial stock on product creation', '', '2026-04-04 12:27:37'),
(29, 17, 17, 0, 'IN', 17, 0, 17, '0', 'Initial stock on product creation', '', '2026-04-04 12:29:03'),
(30, 18, 18, 0, 'IN', 20, 0, 20, '0', 'Initial stock on product creation', '', '2026-04-04 12:30:34'),
(31, 19, 19, 0, 'IN', 12, 0, 12, '0', 'Initial stock on product creation', '', '2026-04-04 12:32:16'),
(32, 20, 20, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 12:33:45'),
(33, 21, 21, 0, 'IN', 19, 0, 19, '0', 'Initial stock on product creation', '', '2026-04-04 12:34:57'),
(34, 22, 22, 0, 'IN', 11, 0, 11, '0', 'Initial stock on product creation', '', '2026-04-04 12:37:50'),
(35, 23, 23, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 13:02:20'),
(36, 24, 24, 0, 'IN', 18, 0, 18, '0', 'Initial stock on product creation', '', '2026-04-04 13:06:50'),
(37, 25, 25, 0, 'IN', 12, 0, 12, '0', 'Initial stock on product creation', '', '2026-04-04 13:08:30'),
(38, 26, 26, 0, 'IN', 14, 0, 14, '0', 'Initial stock on product creation', '', '2026-04-04 13:09:47'),
(39, 27, 27, 0, 'IN', 11, 0, 11, '0', 'Initial stock on product creation', '', '2026-04-04 13:10:51'),
(40, 28, 28, 0, 'IN', 7, 0, 7, '0', 'Initial stock on product creation', '', '2026-04-04 15:29:19'),
(41, 29, 29, 0, 'IN', 15, 0, 15, '0', 'Initial stock on product creation', '', '2026-04-04 15:45:54'),
(42, 30, 30, 0, 'IN', 19, 0, 19, '0', 'Initial stock on product creation', '', '2026-04-04 15:46:31'),
(43, 31, 31, 0, 'IN', 5, 0, 5, '0', 'Initial stock on product creation', '', '2026-04-04 16:11:19'),
(44, 32, 32, 0, 'IN', 6, 0, 6, '0', 'Initial stock on product creation', '', '2026-04-04 16:31:53'),
(45, 33, 33, 0, 'IN', 4, 0, 4, '0', 'Initial stock on product creation', '', '2026-04-04 16:33:02'),
(46, 34, 34, 0, 'IN', 3, 0, 3, '0', 'Initial stock on product creation', '', '2026-04-04 16:34:14'),
(47, 35, 35, 0, 'IN', 4, 0, 4, '0', 'Initial stock on product creation', '', '2026-04-04 16:35:45'),
(48, 36, 36, 0, 'IN', 2, 0, 2, '0', 'Initial stock on product creation', '', '2026-04-04 16:39:57'),
(49, 37, 37, 0, 'IN', 2, 0, 2, '0', 'Initial stock on product creation', '', '2026-04-04 16:41:44'),
(50, 38, 38, 0, 'IN', 2, 0, 2, '0', 'Initial stock on product creation', '', '2026-04-04 16:42:30'),
(51, 39, 39, 0, 'IN', 3, 0, 3, '0', 'Initial stock on product creation', '', '2026-04-04 16:43:14'),
(52, 40, 40, 0, 'IN', 13, 0, 13, '0', 'Initial stock on product creation', '', '2026-04-04 17:05:28'),
(53, 41, 41, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 17:06:03'),
(54, 42, 42, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 17:07:24'),
(55, 43, 43, 0, 'IN', 18, 0, 18, '0', 'Initial stock on product creation', '', '2026-04-04 17:08:15'),
(56, 44, 44, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 17:08:57'),
(57, 45, 45, 0, 'IN', 16, 0, 16, '0', 'Initial stock on product creation', '', '2026-04-04 17:09:57'),
(58, 46, 46, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 17:10:34'),
(59, 47, 47, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-04 17:11:28'),
(60, 48, 48, 0, 'IN', 8, 0, 8, '0', 'Initial stock on product creation', '', '2026-04-04 17:12:00'),
(61, 49, 53, 0, 'IN', 10, 0, 10, '0', 'Initial stock on product creation', '', '2026-04-09 14:00:02'),
(62, 50, 54, 0, 'IN', 10, 10, 20, '0', 'Initial stock on product creation', '', '2026-04-09 14:52:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `status` enum('active','blocked') DEFAULT 'active',
  `profile_image` varchar(255) DEFAULT 'default.png',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `phone`, `address`, `role`, `status`, `profile_image`, `created_at`, `updated_at`, `is_deleted`) VALUES
(1, 'Main Admin', 'main_admin@gmail.com', '$2y$10$QagRxb4FGdCHtzMEwAiWquGQp3INwjTuTElJhkUHlAn0zR91bns5q', '9860214171', 'Aasha Galli', 'admin', 'active', '1775716264_2378.png', '2026-04-09 06:31:04', '2026-04-09 06:49:44', 0),
(2, 'Ashwin Maharjan', 'maharjan.ashwin098@gmail.com', '$2y$10$R7bUlA0sKN0iUzlW5jO1nuhpYdgk2jPOzv4vY5jfAbR6tgY1FnWtK', '9860214171', 'Aasha Galli', 'user', 'active', '1775717423_9199.jpg', '2026-04-09 06:50:23', '2026-04-09 06:50:23', 0),
(3, 'Anjali Maharjan', 'anjali@test.com', '$2y$10$8y3nIaYWnDNQW28GPZ4ocuJHuPXk.EacnV526ZYnrB4Qkbj4.pzMO', '9860214171', 'Aasha Galli', 'user', 'active', '1775888265_3025.png', '2026-04-11 06:17:45', '2026-04-11 06:17:45', 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `label` varchar(60) DEFAULT NULL COMMENT 'e.g. Home, Office',
  `full_name` varchar(120) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address_1` varchar(255) NOT NULL,
  `address_2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal` varchar(20) DEFAULT NULL,
  `country` varchar(100) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_addresses`
--

INSERT INTO `user_addresses` (`id`, `user_id`, `label`, `full_name`, `phone`, `address_1`, `address_2`, `city`, `state`, `postal`, `country`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 2, 'Nayabazar', 'Ashwin Maharjan', '+9779860214171', 'Aasha Galli', 'Nayabazar Multi Venue', 'Kathmandu', 'Bagmati Province', '1122', 'Nepal', 1, '2026-04-09 12:50:31', NULL),
(2, 1, 'Nayabazar', 'Mhrjn Ashwin', '+9779860214171', 'Derby Rd', 'Nayabazar Multi Venue', 'Southport', 'Bagmati Province', 'PR9 0TQ', 'United Kingdom', 1, '2026-04-09 14:14:10', NULL),
(3, 3, 'Teku', 'Anjali Maharjan', '9812345678', 'Lagan Tole', 'Nayabazar Multi Venue', 'Kathmandu', 'Bagmati Province', '1122', 'Nepal', 1, '2026-04-11 12:06:36', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_logs`
--

CREATE TABLE `user_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `clubs`
--
ALTER TABLE `clubs`
  ADD PRIMARY KEY (`club_id`),
  ADD UNIQUE KEY `club_name` (`club_name`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`country_id`),
  ADD UNIQUE KEY `uq_country_name` (`country_name`);

--
-- Indexes for table `inventory_notifications`
--
ALTER TABLE `inventory_notifications`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_variant` (`variant_id`);

--
-- Indexes for table `kits`
--
ALTER TABLE `kits`
  ADD PRIMARY KEY (`kit_id`),
  ADD UNIQUE KEY `kit_name` (`kit_name`);

--
-- Indexes for table `new_orders`
--
ALTER TABLE `new_orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `idx_orders_user` (`user_id`),
  ADD KEY `idx_orders_status` (`order_status`),
  ADD KEY `idx_orders_payment_status` (`payment_status`),
  ADD KEY `idx_orders_khalti_pidx` (`khalti_pidx`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `orders_ibfk_1` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_product` (`product_id`);

--
-- Indexes for table `order_tracking`
--
ALTER TABLE `order_tracking`
  ADD PRIMARY KEY (`tracking_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`method_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `idx_special_type` (`special_type`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`variant_id`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`refund_id`);

--
-- Indexes for table `reorder_requests`
--
ALTER TABLE `reorder_requests`
  ADD PRIMARY KEY (`reorder_id`),
  ADD KEY `idx_variant` (`variant_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`);

--
-- Indexes for table `sizes`
--
ALTER TABLE `sizes`
  ADD PRIMARY KEY (`size_id`),
  ADD UNIQUE KEY `size_name` (`size_name`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `idx_variant` (`variant_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_type` (`movement_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `clubs`
--
ALTER TABLE `clubs`
  MODIFY `club_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `country_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `inventory_notifications`
--
ALTER TABLE `inventory_notifications`
  MODIFY `notif_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `kits`
--
ALTER TABLE `kits`
  MODIFY `kit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `new_orders`
--
ALTER TABLE `new_orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_tracking`
--
ALTER TABLE `order_tracking`
  MODIFY `tracking_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `method_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=203;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `variant_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `refund_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reorder_requests`
--
ALTER TABLE `reorder_requests`
  MODIFY `reorder_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sizes`
--
ALTER TABLE `sizes`
  MODIFY `size_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `movement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_tracking`
--
ALTER TABLE `order_tracking`
  ADD CONSTRAINT `order_tracking_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
