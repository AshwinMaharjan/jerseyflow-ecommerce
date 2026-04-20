-- phpMyAdmin SQL Dump
-- JerseyFlow - Database Schema (Structure Only)
-- All personal/sensitive data has been removed for public distribution.
-- --------------------------------------------------------

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Database: `jerseyflow_new`
-- --------------------------------------------------------

DELIMITER $$

--
-- Stored Procedure: safe_stock_movement
--

CREATE DEFINER=`` PROCEDURE `safe_stock_movement` (
  IN  `p_variant_id` INT UNSIGNED,
  IN  `p_admin_id`   INT UNSIGNED,
  IN  `p_type`       VARCHAR(20),
  IN  `p_qty`        INT,
  IN  `p_reference`  VARCHAR(60),
  IN  `p_reason`     TEXT,
  IN  `p_note`       TEXT,
  OUT `p_result`     VARCHAR(100)
)
BEGIN
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
    UPDATE product_variants
    SET    stock = v_new_stock, updated_at = NOW()
    WHERE  variant_id = p_variant_id;

    -- Sync products.stock (sum of all variants)
    UPDATE products p
    SET    p.stock = (
             SELECT COALESCE(SUM(pv.stock), 0)
             FROM   product_variants pv
             WHERE  pv.product_id = p.product_id
           )
    WHERE  p.product_id = v_product_id;

    -- Record movement
    INSERT INTO stock_movements
      (variant_id, product_id, admin_id, movement_type, quantity,
       stock_before, stock_after, reference_no, reason, note)
    VALUES
      (p_variant_id, v_product_id, p_admin_id, p_type, ABS(p_qty),
       v_current_stock, v_new_stock, p_reference, p_reason, p_note);

    -- Low stock / out of stock notification
    IF v_new_stock = 0 THEN
      INSERT INTO inventory_notifications (variant_id, product_id, type, message)
      SELECT p_variant_id, v_product_id, 'OUT_OF_STOCK',
             CONCAT('Variant #', p_variant_id, ' is now OUT OF STOCK.')
      WHERE NOT EXISTS (
        SELECT 1 FROM inventory_notifications
        WHERE variant_id = p_variant_id
          AND type = 'OUT_OF_STOCK'
          AND is_dismissed = 0
      );
    ELSEIF v_new_stock <= v_reorder_lvl THEN
      INSERT INTO inventory_notifications (variant_id, product_id, type, message)
      SELECT p_variant_id, v_product_id, 'LOW_STOCK',
             CONCAT('Variant #', p_variant_id, ' is LOW — only ', v_new_stock, ' units left.')
      WHERE NOT EXISTS (
        SELECT 1 FROM inventory_notifications
        WHERE variant_id = p_variant_id
          AND type = 'LOW_STOCK'
          AND is_dismissed = 0
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
  `cart_id`    int(10) UNSIGNED NOT NULL,
  `user_id`    int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `size`       varchar(10) NOT NULL,
  `quantity`   tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clubs`
--

CREATE TABLE `clubs` (
  `club_id`    int(11) NOT NULL,
  `club_name`  varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `country_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Reference data for table `clubs` (non-sensitive)
--

INSERT INTO `clubs` (`club_id`, `club_name`, `created_at`, `country_id`) VALUES
(1,  'FC Barcelona',       '2026-03-29 14:53:56', 1),
(2,  'Real Madrid',        '2026-03-29 14:53:56', 1),
(3,  'Manchester United',  '2026-03-29 14:53:56', 2),
(4,  'Manchester City',    '2026-03-29 14:53:56', 2),
(5,  'Liverpool FC',       '2026-03-29 14:53:56', 2),
(6,  'Chelsea FC',         '2026-03-29 14:53:56', 2),
(7,  'Arsenal FC',         '2026-03-29 14:53:56', 2),
(8,  'Paris Saint-Germain','2026-03-29 14:53:56', 5),
(9,  'Bayern Munich',      '2026-03-29 14:53:56', 3),
(10, 'Borussia Dortmund',  '2026-03-29 14:53:56', 3),
(11, 'Juventus',           '2026-03-29 14:53:56', 4),
(12, 'AC Milan',           '2026-03-29 14:53:56', 4),
(13, 'Inter Milan',        '2026-03-29 14:53:56', 4),
(14, 'Atletico Madrid',    '2026-03-29 14:53:56', 1),
(15, 'Tottenham Hotspur',  '2026-03-29 14:53:56', 2),
(17, 'Al Nasr',            '2026-03-29 16:03:12', 8),
(21, 'Aston Villa',        '2026-04-04 10:43:11', 2),
(22, 'Newcastle United',   '2026-04-04 10:43:22', 2),
(23, 'Fenerbahce',         '2026-04-04 10:43:43', NULL),
(24, 'Flamengo',           '2026-04-04 10:43:49', NULL),
(25, 'Porto',              '2026-04-04 10:43:54', NULL),
(26, 'Galatasaray',        '2026-04-04 10:44:05', NULL),
(27, 'Benfica',            '2026-04-04 11:21:54', NULL),
(28, 'Celtic',             '2026-04-04 11:22:39', NULL),
(29, 'Inter Miami',        '2026-04-04 11:24:15', NULL),
(30, 'Napoli',             '2026-04-04 11:25:06', NULL),
(31, 'AS Roma',            '2026-04-04 11:25:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `country_id`   int(11) NOT NULL,
  `country_name` varchar(100) NOT NULL,
  `sort_order`   int(11) DEFAULT NULL,
  `created_at`   timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Reference data for table `countries` (non-sensitive)
--

INSERT INTO `countries` (`country_id`, `country_name`, `sort_order`, `created_at`) VALUES
(1,  'Spain',       1,    '2026-03-29 14:50:24'),
(2,  'England',     2,    '2026-03-29 14:50:24'),
(3,  'Germany',     3,    '2026-03-29 14:50:24'),
(4,  'Italy',       4,    '2026-03-29 14:50:24'),
(5,  'France',      5,    '2026-03-29 14:50:24'),
(6,  'Portugal',    6,    '2026-03-29 14:50:24'),
(7,  'Brazil',      7,    '2026-03-29 14:50:24'),
(8,  'Argentina',   8,    '2026-03-29 14:50:24'),
(9,  'Netherlands', 9,    '2026-03-29 14:50:24'),
(10, 'Belgium',     10,   '2026-03-29 14:50:24'),
(11, 'USA',         11,   '2026-03-29 14:50:24'),
(12, 'Mexico',      12,   '2026-03-29 14:50:24'),
(13, 'Japan',       13,   '2026-03-29 14:50:24'),
(14, 'South Korea', 14,   '2026-03-29 14:50:24'),
(15, 'Nigeria',     15,   '2026-03-29 14:50:24'),
(16, 'Croatia',     16,   '2026-03-29 14:50:24'),
(17, 'Morocco',     17,   '2026-03-29 14:50:24'),
(18, 'Switzerland', 18,   '2026-03-29 14:50:24'),
(19, 'Turkey',      19,   '2026-03-29 14:50:24'),
(20, 'Saudi Arabia',20,   '2026-03-29 14:50:24'),
(24, 'Nepal',       NULL, '2026-03-29 16:01:56'),
(25, 'Algeria',     NULL, '2026-04-03 08:44:54'),
(26, 'Australia',   NULL, '2026-04-03 08:55:44'),
(27, 'Canada',      NULL, '2026-04-03 09:02:36');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_notifications`
--

CREATE TABLE `inventory_notifications` (
  `notif_id`     int(10) UNSIGNED NOT NULL,
  `variant_id`   int(10) UNSIGNED DEFAULT NULL,
  `product_id`   int(10) UNSIGNED DEFAULT NULL,
  `type`         enum('LOW_STOCK','OUT_OF_STOCK','REORDER','SYSTEM') NOT NULL,
  `message`      text NOT NULL,
  `is_read`      tinyint(1) NOT NULL DEFAULT 0,
  `is_dismissed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at`   datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kits`
--

CREATE TABLE `kits` (
  `kit_id`     int(11) NOT NULL,
  `kit_name`   varchar(50) NOT NULL,
  `sort_order` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Reference data for table `kits` (non-sensitive)
--

INSERT INTO `kits` (`kit_id`, `kit_name`, `sort_order`) VALUES
(1, 'Home',       1),
(2, 'Away',       2),
(3, 'Third',      3),
(4, 'Goalkeeper', 4);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id`                int(11) NOT NULL,
  `user_id`                 int(11) NOT NULL,
  `total_amount`            decimal(10,2) NOT NULL,
  `order_status`            enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `payment_status`          enum('unpaid','paid','failed','refunded') DEFAULT 'unpaid',
  `method_id`               int(11) DEFAULT NULL,
  `created_at`              timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`              timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `esewa_transaction_uuid`  varchar(255) DEFAULT NULL,
  `esewa_ref_id`            varchar(255) DEFAULT NULL,
  `address_id`              int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `order_id`      int(10) UNSIGNED NOT NULL,
  `product_id`    int(10) UNSIGNED NOT NULL,
  `variant_id`    int(11) DEFAULT NULL,
  `quantity`      smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price`    decimal(10,2) DEFAULT NULL,
  `subtotal`      decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'price × quantity at time of purchase'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Line items belonging to each order';

-- --------------------------------------------------------

--
-- Table structure for table `order_tracking`
--

CREATE TABLE `order_tracking` (
  `tracking_id` int(11) NOT NULL,
  `order_id`    int(11) NOT NULL,
  `status`      enum('pending','processing','shipped','delivered','cancelled') NOT NULL,
  `note`        text DEFAULT NULL,
  `location`    varchar(255) DEFAULT NULL,
  `updated_at`  timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id`     int(11) NOT NULL,
  `order_id`       int(11) NOT NULL,
  `amount`         decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `gateway`        varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `paid_at`        datetime DEFAULT NULL,
  `created_at`     timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `method_id`   int(11) NOT NULL,
  `method_name` varchar(50) NOT NULL,
  `is_active`   tinyint(1) DEFAULT 1,
  `created_at`  timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Reference data for table `payment_methods` (non-sensitive)
--

INSERT INTO `payment_methods` (`method_id`, `method_name`, `is_active`, `created_at`) VALUES
(1, 'Esewa', 1, '2026-04-08 14:29:14'),
(2, 'COD',   1, '2026-04-08 14:29:23');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id`   int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `price`        decimal(10,2) NOT NULL,
  `stock`        int(11) NOT NULL,
  `club_id`      int(11) DEFAULT NULL,
  `size_id`      int(11) NOT NULL,
  `image`        varchar(255) DEFAULT NULL,
  `description`  text DEFAULT NULL,
  `created_at`   timestamp NOT NULL DEFAULT current_timestamp(),
  `kit_id`       int(11) DEFAULT NULL,
  `country_id`   int(11) DEFAULT NULL,
  `special_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `image_id`   int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `variant_id`    int(10) UNSIGNED NOT NULL,
  `product_id`    int(10) UNSIGNED NOT NULL,
  `size`          enum('XS','S','M','L','XL','XXL','XXXL') NOT NULL,
  `color`         varchar(50) NOT NULL DEFAULT 'Default',
  `sku`           varchar(80) NOT NULL,
  `stock`         int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 5,
  `reorder_qty`   int(11) NOT NULL DEFAULT 20,
  `cost_price`    decimal(10,2) DEFAULT NULL,
  `is_active`     tinyint(1) NOT NULL DEFAULT 1,
  `created_at`    datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`    datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `price`         decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `refund_id`     int(11) NOT NULL,
  `payment_id`    int(11) NOT NULL,
  `order_id`      int(11) NOT NULL,
  `amount`        decimal(10,2) NOT NULL,
  `refund_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reason`        text DEFAULT NULL,
  `processed_at`  datetime DEFAULT NULL,
  `created_at`    timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reorder_requests`
--

CREATE TABLE `reorder_requests` (
  `reorder_id`    int(10) UNSIGNED NOT NULL,
  `variant_id`    int(10) UNSIGNED NOT NULL,
  `product_id`    int(10) UNSIGNED NOT NULL,
  `admin_id`      int(10) UNSIGNED NOT NULL,
  `qty_requested` int(11) NOT NULL,
  `status`        enum('PENDING','ORDERED','RECEIVED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `supplier_note` text DEFAULT NULL,
  `created_at`    datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`    datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id`   int(11) NOT NULL,
  `product_id`  int(11) NOT NULL,
  `user_id`     int(11) NOT NULL,
  `rating`      tinyint(4) NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `review_text` text DEFAULT NULL,
  `status`      enum('visible','hidden') DEFAULT 'visible',
  `created_at`  timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sizes`
--

CREATE TABLE `sizes` (
  `size_id`    int(11) NOT NULL,
  `size_name`  varchar(10) NOT NULL,
  `sort_order` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Reference data for table `sizes` (non-sensitive)
--

INSERT INTO `sizes` (`size_id`, `size_name`, `sort_order`) VALUES
(1, 'S',  1),
(2, 'M',  2),
(3, 'L',  3),
(4, 'XL', 4);

-- --------------------------------------------------------

--
-- Table structure for table `stock_log`
--

CREATE TABLE `stock_log` (
  `log_id`       int(11) NOT NULL,
  `product_id`   int(11) NOT NULL,
  `move_type`    enum('IN','OUT') NOT NULL,
  `quantity`     int(11) NOT NULL,
  `stock_before` int(11) NOT NULL,
  `stock_after`  int(11) NOT NULL,
  `note`         text DEFAULT NULL,
  `created_at`   datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `movement_id`   int(10) UNSIGNED NOT NULL,
  `variant_id`    int(10) UNSIGNED NOT NULL,
  `product_id`    int(10) UNSIGNED NOT NULL,
  `admin_id`      int(10) UNSIGNED NOT NULL,
  `movement_type` enum('IN','OUT','ADJUST','RETURN','DAMAGE','TRANSFER') NOT NULL,
  `quantity`      int(11) NOT NULL,
  `stock_before`  int(11) NOT NULL,
  `stock_after`   int(11) NOT NULL,
  `reference_no`  varchar(60) DEFAULT NULL,
  `reason`        text DEFAULT NULL,
  `note`          text DEFAULT NULL,
  `created_at`    datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id`       int(11) NOT NULL,
  `full_name`     varchar(100) NOT NULL,
  `email`         varchar(100) NOT NULL,
  `password`      varchar(255) NOT NULL,
  `phone`         varchar(20) DEFAULT NULL,
  `address`       text DEFAULT NULL,
  `role`          enum('admin','user') DEFAULT 'user',
  `status`        enum('active','blocked') DEFAULT 'active',
  `profile_image` varchar(255) DEFAULT 'default.png',
  `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted`    tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id`         int(10) UNSIGNED NOT NULL,
  `user_id`    int(10) UNSIGNED NOT NULL,
  `label`      varchar(60) DEFAULT NULL COMMENT 'e.g. Home, Office',
  `full_name`  varchar(120) NOT NULL,
  `phone`      varchar(30) DEFAULT NULL,
  `address_1`  varchar(255) NOT NULL,
  `address_2`  varchar(255) DEFAULT NULL,
  `city`       varchar(100) NOT NULL,
  `state`      varchar(100) DEFAULT NULL,
  `postal`     varchar(20) DEFAULT NULL,
  `country`    varchar(100) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_logs`
--

CREATE TABLE `user_logs` (
  `log_id`     int(11) NOT NULL,
  `user_id`    int(11) NOT NULL,
  `action`     varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================================
-- PRIMARY KEYS & INDEXES
-- ========================================================

ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `idx_user` (`user_id`);

ALTER TABLE `clubs`
  ADD PRIMARY KEY (`club_id`),
  ADD UNIQUE KEY `club_name` (`club_name`);

ALTER TABLE `countries`
  ADD PRIMARY KEY (`country_id`),
  ADD UNIQUE KEY `uq_country_name` (`country_name`);

ALTER TABLE `inventory_notifications`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_variant` (`variant_id`);

ALTER TABLE `kits`
  ADD PRIMARY KEY (`kit_id`),
  ADD UNIQUE KEY `kit_name` (`kit_name`);

ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `orders_ibfk_1` (`user_id`);

ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_product` (`product_id`);

ALTER TABLE `order_tracking`
  ADD PRIMARY KEY (`tracking_id`),
  ADD KEY `order_id` (`order_id`);

ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`);

ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`method_id`);

ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `idx_special_type` (`special_type`);

ALTER TABLE `product_images`
  ADD PRIMARY KEY (`image_id`);

ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`variant_id`);

ALTER TABLE `refunds`
  ADD PRIMARY KEY (`refund_id`);

ALTER TABLE `reorder_requests`
  ADD PRIMARY KEY (`reorder_id`),
  ADD KEY `idx_variant` (`variant_id`),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`);

ALTER TABLE `sizes`
  ADD PRIMARY KEY (`size_id`),
  ADD UNIQUE KEY `size_name` (`size_name`);

ALTER TABLE `stock_log`
  ADD PRIMARY KEY (`log_id`);

ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `idx_variant` (`variant_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_type` (`movement_type`),
  ADD KEY `idx_created` (`created_at`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`log_id`);

-- ========================================================
-- AUTO_INCREMENT VALUES
-- ========================================================

ALTER TABLE `cart`
  MODIFY `cart_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `clubs`
  MODIFY `club_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

ALTER TABLE `countries`
  MODIFY `country_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

ALTER TABLE `inventory_notifications`
  MODIFY `notif_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `kits`
  MODIFY `kit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `order_items`
  MODIFY `order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `order_tracking`
  MODIFY `tracking_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `payment_methods`
  MODIFY `method_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `product_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `product_variants`
  MODIFY `variant_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `refunds`
  MODIFY `refund_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `reorder_requests`
  MODIFY `reorder_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sizes`
  MODIFY `size_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `stock_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `stock_movements`
  MODIFY `movement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_addresses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

-- ========================================================
-- FOREIGN KEY CONSTRAINTS
-- ========================================================

ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

ALTER TABLE `order_tracking`
  ADD CONSTRAINT `order_tracking_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;