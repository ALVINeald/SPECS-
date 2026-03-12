-- ============================================================
--  SPECS – Supermarket Pricing Estimation & Comparison System
--  DATABASE STRUCTURE FILE
--  Version: 1.0  |  Date: November 2025
--  Author: Mbabazi Alvin (24/BSU/DIT/3253)
--  Bishop Stuart University – Mbarara City, Uganda
-- ============================================================
-- HOW TO USE:
--  1. Open phpMyAdmin (Laragon → phpMyAdmin)
--  2. Create a new database called 'specs_db'
--  3. Click 'Import' and upload this file (specs_db.sql)
--  4. Then import specs_seed.sql for all the data
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+03:00";

-- Drop and recreate database
DROP DATABASE IF EXISTS `specs_db`;
CREATE DATABASE `specs_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `specs_db`;

-- ─────────────────────────────────────────────────────────
-- TABLE: categories
-- ─────────────────────────────────────────────────────────
CREATE TABLE `categories` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `icon`        VARCHAR(10)  DEFAULT '📦',
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
-- TABLE: stores
-- ─────────────────────────────────────────────────────────
CREATE TABLE `stores` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150)  NOT NULL,
  `short_name`  VARCHAR(50)   NOT NULL,
  `address`     VARCHAR(250)  DEFAULT NULL,
  `tier`        ENUM('premium','mid','budget','market') DEFAULT 'mid',
  `phone`       VARCHAR(20)   DEFAULT NULL,
  `image`       VARCHAR(255)  DEFAULT NULL,
  `active`      TINYINT(1)    DEFAULT 1,
  `created_at`  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
-- TABLE: users
-- Supports both password login AND Google OAuth login
-- ─────────────────────────────────────────────────────────
CREATE TABLE `users` (
  `id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `fullname`        VARCHAR(150)  NOT NULL,
  `email`           VARCHAR(200)  NOT NULL,
  `password_hash`   VARCHAR(255)  DEFAULT NULL,
  -- OAuth fields (for Google / device login)
  `oauth_provider`  ENUM('local','google','facebook','apple') DEFAULT 'local',
  `oauth_id`        VARCHAR(255)  DEFAULT NULL,
  -- Profile
  `profile_picture` VARCHAR(500)  DEFAULT NULL,
  `phone`           VARCHAR(20)   DEFAULT NULL,
  `role`            ENUM('user','admin','manager') DEFAULT 'user',
  -- User preferences
  `monthly_budget`  INT(11)       DEFAULT 0,
  `preferred_store` INT(11)       DEFAULT NULL,
  `notifications`   TINYINT(1)    DEFAULT 1,
  -- Account status
  `is_active`       TINYINT(1)    DEFAULT 1,
  `email_verified`  TINYINT(1)    DEFAULT 0,
  `last_login`      TIMESTAMP     NULL DEFAULT NULL,
  `created_at`      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_oauth` (`oauth_provider`, `oauth_id`),
  FOREIGN KEY (`preferred_store`) REFERENCES `stores`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
-- TABLE: products
-- ─────────────────────────────────────────────────────────
CREATE TABLE `products` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(200) NOT NULL,
  `unit`         VARCHAR(60)  NOT NULL,
  `category_id`  INT(11)      NOT NULL,
  `description`  TEXT         DEFAULT NULL,
  `image`        VARCHAR(255) DEFAULT NULL,
  `base_price`   INT(11)      NOT NULL DEFAULT 0 COMMENT 'Reference base price in UGX',
  `active`       TINYINT(1)   DEFAULT 1,
  `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_active` (`active`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
-- TABLE: prices
-- Current price of a product at a specific store
-- ─────────────────────────────────────────────────────────
CREATE TABLE `prices` (
  `id`           INT(11)   NOT NULL AUTO_INCREMENT,
  `product_id`   INT(11)   NOT NULL,
  `store_id`     INT(11)   NOT NULL,
  `price`        INT(11)   NOT NULL COMMENT 'Price in UGX',
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by`   INT(11)   DEFAULT NULL COMMENT 'Admin user who updated it',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_store` (`product_id`, `store_id`),
  KEY `idx_store` (`store_id`),
  KEY `idx_product` (`product_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`store_id`)   REFERENCES `stores`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
-- TABLE: price_history
-- Records every price change for trend charts
-- ─────────────────────────────────────────────────────────
CREATE TABLE `price_history` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `product_id`   INT(11)      NOT NULL,
  `store_id`     INT(11)      NOT NULL,
  `old_price`    INT(11)      NOT NULL,
  `new_price`    INT(11)      NOT NULL,
  `change_pct`   DECIMAL(5,2) DEFAULT NULL COMMENT 'Percentage change',
  `reason`       VARCHAR(255) DEFAULT NULL COMMENT 'Why price changed',
  `changed_by`   INT(11)      DEFAULT NULL COMMENT 'Admin who made the change',
  `changed_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ph_product` (`product_id`),
  KEY `idx_ph_store`   (`store_id`),
  KEY `idx_ph_date`    (`changed_at`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`store_id`)   REFERENCES `stores`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
-- TABLE: alerts
-- User price drop notifications
-- ─────────────────────────────────────────────────────────
CREATE TABLE `alerts` (
  `id`             INT(11)     NOT NULL AUTO_INCREMENT,
  `user_id`        INT(11)     NOT NULL,
  `product_id`     INT(11)     NOT NULL,
  `store_id`       INT(11)     DEFAULT NULL COMMENT 'NULL means any store',
  `target_price`   INT(11)     NOT NULL COMMENT 'UGX threshold',
  `is_triggered`   TINYINT(1)  DEFAULT 0,
  `is_active`      TINYINT(1)  DEFAULT 1,
  `notify_email`   TINYINT(1)  DEFAULT 1,
  `triggered_at`   TIMESTAMP   NULL DEFAULT NULL,
  `created_at`     TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alert_user`    (`user_id`),
  KEY `idx_alert_product` (`product_id`),
  KEY `idx_alert_active`  (`is_active`),
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`store_id`)   REFERENCES `stores`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
-- TABLE: basket
-- Saved user shopping baskets (persists across sessions)
-- ─────────────────────────────────────────────────────────
CREATE TABLE `basket` (
  `id`          INT(11)    NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)    NOT NULL,
  `product_id`  INT(11)    NOT NULL,
  `quantity`    INT(11)    NOT NULL DEFAULT 1,
  `added_at`    TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_basket_item` (`user_id`, `product_id`),
  KEY `idx_basket_user` (`user_id`),
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
-- TABLE: store_plans
-- Downloadable shopping plan receipts (like MTN MoMo receipts)
-- ─────────────────────────────────────────────────────────
CREATE TABLE `store_plans` (
  `id`            INT(11)       NOT NULL AUTO_INCREMENT,
  `user_id`       INT(11)       NOT NULL,
  `store_id`      INT(11)       NOT NULL,
  `plan_ref`      VARCHAR(20)   NOT NULL COMMENT 'Unique reference e.g. SPECS-2025-00142',
  `items_json`    LONGTEXT      NOT NULL COMMENT 'JSON snapshot of basket items',
  `total_amount`  INT(11)       NOT NULL,
  `savings`       INT(11)       DEFAULT 0 COMMENT 'Compared to most expensive store',
  `created_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_ref` (`plan_ref`),
  KEY `idx_plan_user`  (`user_id`),
  KEY `idx_plan_store` (`store_id`),
  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
-- TABLE: admin_logs
-- Full audit trail of admin actions
-- ─────────────────────────────────────────────────────────
CREATE TABLE `admin_logs` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `admin_id`    INT(11)      NOT NULL,
  `action`      VARCHAR(100) NOT NULL COMMENT 'e.g. ADD_PRODUCT, UPDATE_PRICE, DELETE_USER',
  `target_type` VARCHAR(50)  DEFAULT NULL COMMENT 'e.g. product, store, user',
  `target_id`   INT(11)      DEFAULT NULL,
  `details`     TEXT         DEFAULT NULL COMMENT 'Human-readable description',
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_admin`  (`admin_id`),
  KEY `idx_log_action` (`action`),
  KEY `idx_log_date`   (`created_at`),
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
-- TABLE: password_resets
-- For forgot-password email flow
-- ─────────────────────────────────────────────────────────
CREATE TABLE `password_resets` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(200) NOT NULL,
  `token`      VARCHAR(100) NOT NULL,
  `expires_at` TIMESTAMP    NOT NULL,
  `used`       TINYINT(1)   DEFAULT 0,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pr_email` (`email`),
  KEY `idx_pr_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────
-- VIEWS (for easy reporting)
-- ─────────────────────────────────────────────────────────

-- View: current prices with product and store names
CREATE OR REPLACE VIEW `v_prices_full` AS
  SELECT
    p.id          AS product_id,
    p.name        AS product_name,
    p.unit,
    c.name        AS category,
    s.id          AS store_id,
    s.name        AS store_name,
    s.short_name  AS store_short,
    s.tier        AS store_tier,
    pr.price,
    pr.updated_at
  FROM `prices` pr
  JOIN `products` p ON pr.product_id = p.id
  JOIN `categories` c ON p.category_id = c.id
  JOIN `stores` s ON pr.store_id = s.id
  WHERE p.active = 1 AND s.active = 1;

-- View: best price per product (cheapest store)
CREATE OR REPLACE VIEW `v_best_prices` AS
  SELECT
    pr.product_id,
    p.name        AS product_name,
    p.unit,
    c.name        AS category,
    MIN(pr.price) AS best_price,
    s.name        AS best_store
  FROM `prices` pr
  JOIN `products` p  ON pr.product_id = p.id
  JOIN `categories` c ON p.category_id = c.id
  JOIN `stores` s    ON pr.store_id = s.id
  WHERE p.active = 1
  GROUP BY pr.product_id, p.name, p.unit, c.name, s.name
  HAVING pr.price = MIN(pr.price);

-- View: active alerts with details
CREATE OR REPLACE VIEW `v_active_alerts` AS
  SELECT
    a.id,
    u.fullname    AS user_name,
    u.email       AS user_email,
    p.name        AS product_name,
    p.unit,
    s.name        AS store_name,
    a.target_price,
    a.is_triggered,
    a.created_at
  FROM `alerts` a
  JOIN `users` u    ON a.user_id    = u.id
  JOIN `products` p ON a.product_id = p.id
  LEFT JOIN `stores` s ON a.store_id = s.id
  WHERE a.is_active = 1;

-- ─────────────────────────────────────────────────────────
-- STORED PROCEDURE: Check and trigger price alerts
-- Called after any price update
-- ─────────────────────────────────────────────────────────
DELIMITER $$
CREATE PROCEDURE `sp_check_alerts`(IN p_product_id INT, IN p_store_id INT, IN p_new_price INT)
BEGIN
  UPDATE `alerts`
  SET
    `is_triggered` = 1,
    `triggered_at` = CURRENT_TIMESTAMP
  WHERE
    `product_id`  = p_product_id
    AND (`store_id` = p_store_id OR `store_id` IS NULL)
    AND `target_price` >= p_new_price
    AND `is_active`    = 1
    AND `is_triggered` = 0;
END$$
DELIMITER ;

-- ─────────────────────────────────────────────────────────
-- STORED PROCEDURE: Log a price change to history
-- ─────────────────────────────────────────────────────────
DELIMITER $$
CREATE PROCEDURE `sp_log_price_change`(
  IN p_product_id INT,
  IN p_store_id   INT,
  IN p_old_price  INT,
  IN p_new_price  INT,
  IN p_reason     VARCHAR(255),
  IN p_changed_by INT
)
BEGIN
  DECLARE v_pct DECIMAL(5,2);
  SET v_pct = ROUND(((p_new_price - p_old_price) / p_old_price) * 100, 2);
  INSERT INTO `price_history`
    (`product_id`,`store_id`,`old_price`,`new_price`,`change_pct`,`reason`,`changed_by`)
  VALUES
    (p_product_id, p_store_id, p_old_price, p_new_price, v_pct, p_reason, p_changed_by);
  -- Also trigger alert check
  CALL sp_check_alerts(p_product_id, p_store_id, p_new_price);
END$$
DELIMITER ;

-- ─────────────────────────────────────────────────────────
-- TRIGGER: Auto-log price changes
-- ─────────────────────────────────────────────────────────
DELIMITER $$
CREATE TRIGGER `tr_price_update`
AFTER UPDATE ON `prices`
FOR EACH ROW
BEGIN
  IF OLD.price != NEW.price THEN
    CALL sp_log_price_change(
      NEW.product_id,
      NEW.store_id,
      OLD.price,
      NEW.price,
      'Manual/AI update',
      NEW.updated_by
    );
  END IF;
END$$
DELIMITER ;

-- ============================================================
-- DATABASE STRUCTURE COMPLETE
-- Next: Import specs_seed.sql to load all data
-- ============================================================
