-- =======================================================================
--  Nourish U Biotech Limited – Med Distribution System
--  Schema definition
-- =======================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS commissions;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS sample_drop_items;
DROP TABLE IF EXISTS sample_drops;
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS client_documents;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS commission_tiers;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS rep_profiles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------
--  USERS  (admins, reps, accountants)
-- -----------------------------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120)   NOT NULL,
    email         VARCHAR(160)   NOT NULL UNIQUE,
    phone         VARCHAR(40)    NULL,
    password_hash VARCHAR(255)   NOT NULL,
    role          ENUM('admin','rep','accountant') NOT NULL,
    status        ENUM('active','suspended')        NOT NULL DEFAULT 'active',
    last_login_at DATETIME       NULL,
    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    deleted_at    DATETIME      NULL,
    deleted_by    INT UNSIGNED  NULL,
    delete_reason VARCHAR(255)  NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------
--  REP PROFILES  (extra fields just for reps)
-- -----------------------------------------------------------------------
CREATE TABLE rep_profiles (
    user_id       INT UNSIGNED   PRIMARY KEY,
    id_number     VARCHAR(40)    NULL,
    license_no    VARCHAR(60)    NULL,
    region        VARCHAR(80)    NULL,
    monthly_target DECIMAL(14,2) NOT NULL DEFAULT 0,
    hire_date     DATE           NULL,
    photo         VARCHAR(255)   NULL,
    bio           TEXT           NULL,
    bank_name     VARCHAR(80)    NULL,
    bank_account  VARCHAR(40)    NULL,
    next_of_kin   VARCHAR(120)   NULL,
    next_of_kin_phone VARCHAR(40) NULL,
    CONSTRAINT fk_rep_profiles_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------
--  PRODUCTS / INVENTORY
-- -----------------------------------------------------------------------
CREATE TABLE products (
    id                  INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    sku                 VARCHAR(60)   NOT NULL UNIQUE,
    name                VARCHAR(180)  NOT NULL,
    category            VARCHAR(80)   NULL,
    manufacturer        VARCHAR(160)  NULL,
    country_of_origin   VARCHAR(80)   NULL,
    unit                VARCHAR(40)   NOT NULL DEFAULT 'pack',
    price               DECIMAL(14,2) NOT NULL DEFAULT 0,
    cost                DECIMAL(14,2) NOT NULL DEFAULT 0,
    base_commission_pct DECIMAL(5,2)  NOT NULL DEFAULT 0
                        COMMENT 'Per-product commission percentage e.g. 5.00',
    stock_qty           INT           NOT NULL DEFAULT 0,
    reorder_level       INT           NOT NULL DEFAULT 0,
    batch_no            VARCHAR(80)   NULL,
    expiry_date         DATE          NULL,
    status              ENUM('active','inactive') NOT NULL DEFAULT 'active',
    description         TEXT          NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_status (status),
    INDEX idx_products_expiry (expiry_date),
    deleted_at    DATETIME      NULL,
    deleted_by    INT UNSIGNED  NULL,
    delete_reason VARCHAR(255)  NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------
--  COMMISSION TIERS  (monthly volume bonus)
-- -----------------------------------------------------------------------
CREATE TABLE commission_tiers (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    label       VARCHAR(80)   NOT NULL,
    min_amount  DECIMAL(14,2) NOT NULL DEFAULT 0,
    max_amount  DECIMAL(14,2) NULL COMMENT 'NULL = no upper cap',
    bonus_pct   DECIMAL(5,2)  NOT NULL DEFAULT 0,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------
--  CLIENTS (pharmacies / clinics / hospitals)
-- -----------------------------------------------------------------------
CREATE TABLE clients (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200)  NOT NULL,
    type            ENUM('pharmacy','clinic','hospital','wholesaler','other') NOT NULL DEFAULT 'pharmacy',
    license_no      VARCHAR(80)   NULL,
    kra_pin         VARCHAR(40)   NULL,
    contact_person  VARCHAR(160)  NULL,
    contact_role    VARCHAR(80)   NULL,
    phone           VARCHAR(40)   NULL,
    email           VARCHAR(160)  NULL,
    address          VARCHAR(255)  NULL,
    postal_address   VARCHAR(255)  NULL,
    region           VARCHAR(80)   NULL,
    city             VARCHAR(80)   NULL,
    lat              DECIMAL(10,7) NULL,
    lng              DECIMAL(10,7) NULL,
    kyc_status       ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    kyc_notes        TEXT          NULL,
    rep_id           INT UNSIGNED  NULL COMMENT 'Primary rep assigned',
    credit_limit     DECIMAL(14,2) NOT NULL DEFAULT 0,
    -- Account Opening Form fields
    directors          TEXT         NULL COMMENT 'One name per line',
    accountant_name    VARCHAR(160) NULL,
    accountant_phone   VARCHAR(40)  NULL,
    bank_name          VARCHAR(80)  NULL,
    bank_branch        VARCHAR(80)  NULL,
    payment_terms      VARCHAR(120) NULL,
    credit_period_days INT          NULL,
    trade_ref_1        TEXT         NULL,
    trade_ref_2        TEXT         NULL,
    trade_ref_3        TEXT         NULL,
    signed_name        VARCHAR(160) NULL,
    signed_position    VARCHAR(80)  NULL,
    signed_at          DATE         NULL,
    notes              TEXT         NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_clients_rep
        FOREIGN KEY (rep_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_clients_kyc (kyc_status),
    INDEX idx_clients_rep (rep_id),
    deleted_at    DATETIME      NULL,
    deleted_by    INT UNSIGNED  NULL,
    delete_reason VARCHAR(255)  NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE client_documents (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED  NOT NULL,
    doc_type    VARCHAR(80)   NOT NULL COMMENT 'Pharmacy License, KRA PIN, ID, etc.',
    file_path   VARCHAR(255)  NOT NULL,
    uploaded_by INT UNSIGNED  NULL,
    uploaded_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_client_docs_client
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_client_docs_user
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------
--  SALES + LINE ITEMS
-- -----------------------------------------------------------------------
CREATE TABLE sales (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    invoice_no      VARCHAR(40)   NOT NULL UNIQUE,
    client_id       INT UNSIGNED  NOT NULL,
    rep_id          INT UNSIGNED  NOT NULL,
    sale_date       DATE          NOT NULL,
    subtotal        DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_pct         DECIMAL(5,2)  NOT NULL DEFAULT 0,
    tax_amount      DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount        DECIMAL(14,2) NOT NULL DEFAULT 0,
    total           DECIMAL(14,2) NOT NULL DEFAULT 0,
    payment_status  ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
    paid_amount     DECIMAL(14,2) NOT NULL DEFAULT 0,
    payment_method  VARCHAR(40)   NULL,
    notes           TEXT          NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sales_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_sales_rep    FOREIGN KEY (rep_id)    REFERENCES users(id),
    INDEX idx_sales_date (sale_date),
    INDEX idx_sales_rep  (rep_id),
    INDEX idx_sales_pay  (payment_status),
    deleted_at    DATETIME      NULL,
    deleted_by    INT UNSIGNED  NULL,
    delete_reason VARCHAR(255)  NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sale_items (
    id                INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    sale_id           INT UNSIGNED  NOT NULL,
    product_id        INT UNSIGNED  NOT NULL,
    qty               INT           NOT NULL DEFAULT 1,
    unit_price        DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_total        DECIMAL(14,2) NOT NULL DEFAULT 0,
    commission_pct    DECIMAL(5,2)  NOT NULL DEFAULT 0,
    commission_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_sale_items_sale    FOREIGN KEY (sale_id)    REFERENCES sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_sale_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------
--  FREE SAMPLE DROPS + PICKUPS
-- -----------------------------------------------------------------------
CREATE TABLE sample_drops (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    client_id       INT UNSIGNED  NOT NULL,
    rep_id          INT UNSIGNED  NOT NULL,
    scheduled_date  DATE          NOT NULL,
    drop_date       DATE          NULL,
    pickup_date     DATE          NULL,
    next_pickup_date  DATE          NULL,
    payment_collected DECIMAL(14,2) NOT NULL DEFAULT 0,
    payment_method    VARCHAR(40)   NULL,
    reschedule_reason VARCHAR(255)  NULL,
    status          ENUM('scheduled','dropped','rescheduled','no_show','picked_up','cancelled')
                                  NOT NULL DEFAULT 'scheduled',
    notes           TEXT          NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_drops_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_drops_rep    FOREIGN KEY (rep_id)    REFERENCES users(id),
    INDEX idx_drops_status (status),
    INDEX idx_drops_sched  (scheduled_date),
    deleted_at    DATETIME      NULL,
    deleted_by    INT UNSIGNED  NULL,
    delete_reason VARCHAR(255)  NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sample_drop_items (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    drop_id     INT UNSIGNED  NOT NULL,
    product_id  INT UNSIGNED  NOT NULL,
    qty_dropped INT           NOT NULL DEFAULT 0,
    qty_used    INT           NOT NULL DEFAULT 0,
    qty_returned INT          NOT NULL DEFAULT 0,
    CONSTRAINT fk_drop_items_drop    FOREIGN KEY (drop_id)    REFERENCES sample_drops(id) ON DELETE CASCADE,
    CONSTRAINT fk_drop_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------
--  FEEDBACK / COMPLAINTS
-- -----------------------------------------------------------------------
CREATE TABLE feedback (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED  NOT NULL,
    rep_id      INT UNSIGNED  NOT NULL,
    sale_id     INT UNSIGNED  NULL,
    type        ENUM('praise','suggestion','complaint','adverse_event') NOT NULL DEFAULT 'suggestion',
    severity    ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
    message     TEXT          NOT NULL,
    status      ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    follow_up   TEXT          NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_feedback_rep    FOREIGN KEY (rep_id)    REFERENCES users(id),
    CONSTRAINT fk_feedback_sale   FOREIGN KEY (sale_id)   REFERENCES sales(id) ON DELETE SET NULL,
    INDEX idx_fb_status (status),
    INDEX idx_fb_sev    (severity),
    deleted_at    DATETIME      NULL,
    deleted_by    INT UNSIGNED  NULL,
    delete_reason VARCHAR(255)  NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------
--  COMMISSIONS LEDGER  (monthly summary per rep)
-- -----------------------------------------------------------------------
CREATE TABLE commissions (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    rep_id          INT UNSIGNED  NOT NULL,
    period_year     SMALLINT      NOT NULL,
    period_month    TINYINT       NOT NULL,
    sales_total     DECIMAL(14,2) NOT NULL DEFAULT 0,
    base_total      DECIMAL(14,2) NOT NULL DEFAULT 0,
    bonus_pct       DECIMAL(5,2)  NOT NULL DEFAULT 0,
    bonus_total     DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_commission DECIMAL(14,2) NOT NULL DEFAULT 0,
    status          ENUM('pending','approved','paid') NOT NULL DEFAULT 'pending',
    generated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at     DATETIME      NULL,
    approved_by     INT UNSIGNED  NULL,
    paid_at         DATETIME      NULL,
    notes           TEXT          NULL,
    UNIQUE KEY uk_rep_period (rep_id, period_year, period_month),
    CONSTRAINT fk_commissions_rep      FOREIGN KEY (rep_id)      REFERENCES users(id),
    CONSTRAINT fk_commissions_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------
--  AUDIT LOG
-- -----------------------------------------------------------------------
CREATE TABLE audit_log (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED  NULL,
    action     VARCHAR(80)   NOT NULL,
    entity     VARCHAR(80)   NULL,
    entity_id  INT UNSIGNED  NULL,
    details    TEXT          NULL,
    ip         VARCHAR(45)   NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------
--  SETTINGS  (key/value)
-- -----------------------------------------------------------------------
CREATE TABLE settings (
    `key`      VARCHAR(80)   PRIMARY KEY,
    `value`    TEXT          NULL,
    updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
