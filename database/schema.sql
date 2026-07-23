-- =====================================================
-- Google SecOps Calculator — Schema (MySQL 8)
-- =====================================================
CREATE DATABASE IF NOT EXISTS secops_calculator
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE secops_calculator;

-- ---------- Users (SSO Microsoft) ----------
CREATE TABLE IF NOT EXISTS users (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    azure_oid     VARCHAR(64)  NOT NULL UNIQUE,
    email         VARCHAR(255) NOT NULL UNIQUE,
    name          VARCHAR(255) NOT NULL,
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migração para bases existentes:
-- ALTER TABLE users ADD COLUMN role ENUM('user','admin') NOT NULL DEFAULT 'user';

-- ---------- Auditoria de privilégios (quem promoveu/removeu admin) ----------
CREATE TABLE IF NOT EXISTS admin_role_audit (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id  BIGINT UNSIGNED NULL,
    actor_email    VARCHAR(255) NOT NULL,
    target_user_id BIGINT UNSIGNED NOT NULL,
    target_email   VARCHAR(255) NOT NULL,
    action         ENUM('grant','revoke') NOT NULL,
    ip_address     VARCHAR(64) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role_audit_created (created_at),
    INDEX idx_role_audit_target (target_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- SKUs / Preços ----------
CREATE TABLE IF NOT EXISTS skus (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku_code            VARCHAR(100) NOT NULL UNIQUE,
    name                VARCHAR(150) NOT NULL,
    plan_description    VARCHAR(150) NOT NULL,
    price_usd_tb_year   DECIMAL(12,2) NOT NULL,
    active              TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Propostas ----------
CREATE TABLE IF NOT EXISTS proposals (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             BIGINT UNSIGNED NOT NULL,
    reseller_name       VARCHAR(255) NOT NULL,
    end_customer_name   VARCHAR(255) NOT NULL,
    pricing_type        ENUM('STANDARD','NON_STANDARD') NOT NULL,
    deal_registration   TINYINT(1) NOT NULL DEFAULT 0,
    contract_years      TINYINT UNSIGNED NOT NULL,
    dollar_rate         DECIMAL(10,4) NOT NULL,
    discount_total_pct  DECIMAL(5,2) NOT NULL,
    discount_td_pct     DECIMAL(5,2) NOT NULL,
    discount_reseller_pct DECIMAL(5,2) NOT NULL,
    discount_additional_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    total_usd           DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_monthly_brl   DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_proposal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_reseller (reseller_name),
    INDEX idx_customer (end_customer_name),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Itens da Proposta ----------
CREATE TABLE IF NOT EXISTS proposal_items (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proposal_id             BIGINT UNSIGNED NOT NULL,
    sku_id                  BIGINT UNSIGNED NOT NULL,
    solution                VARCHAR(50) NOT NULL DEFAULT 'SecOps',
    version                 VARCHAR(100) NOT NULL,
    tb_per_year             DECIMAL(12,4) NOT NULL,
    gb_per_year             DECIMAL(14,2) NOT NULL,
    unit_price_usd          DECIMAL(12,2) NOT NULL,
    gross_total_usd         DECIMAL(14,2) NOT NULL,
    net_total_usd           DECIMAL(14,2) NOT NULL,
    monthly_brl             DECIMAL(14,2) NOT NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_item_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_sku      FOREIGN KEY (sku_id)      REFERENCES skus(id)      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
