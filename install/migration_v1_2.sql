-- =======================================================================
--  Nourish U Biotech - Migration to v1.2
--  Adds soft-delete columns to mutable tables.
--  Adds payment-collection + reschedule fields to sample_drops.
--  Idempotent: safe to re-run.
-- =======================================================================

DELIMITER //

DROP PROCEDURE IF EXISTS nu_add_col//
CREATE PROCEDURE nu_add_col(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = tbl
       AND COLUMN_NAME  = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
END//

DROP PROCEDURE IF EXISTS nu_add_idx//
CREATE PROCEDURE nu_add_idx(IN tbl VARCHAR(64), IN idx VARCHAR(64), IN cols VARCHAR(255))
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = tbl
       AND INDEX_NAME   = idx
  ) THEN
    SET @s = CONCAT('CREATE INDEX `', idx, '` ON `', tbl, '` (', cols, ')');
    PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
END//

DELIMITER ;

-- ---------------- soft-delete columns -------------------------------
CALL nu_add_col('users',         'deleted_at',     '`deleted_at` DATETIME NULL');
CALL nu_add_col('users',         'deleted_by',     '`deleted_by` INT UNSIGNED NULL');
CALL nu_add_col('users',         'delete_reason',  '`delete_reason` VARCHAR(255) NULL');

CALL nu_add_col('products',      'deleted_at',     '`deleted_at` DATETIME NULL');
CALL nu_add_col('products',      'deleted_by',     '`deleted_by` INT UNSIGNED NULL');
CALL nu_add_col('products',      'delete_reason',  '`delete_reason` VARCHAR(255) NULL');

CALL nu_add_col('clients',       'deleted_at',     '`deleted_at` DATETIME NULL');
CALL nu_add_col('clients',       'deleted_by',     '`deleted_by` INT UNSIGNED NULL');
CALL nu_add_col('clients',       'delete_reason',  '`delete_reason` VARCHAR(255) NULL');

CALL nu_add_col('sales',         'deleted_at',     '`deleted_at` DATETIME NULL');
CALL nu_add_col('sales',         'deleted_by',     '`deleted_by` INT UNSIGNED NULL');
CALL nu_add_col('sales',         'delete_reason',  '`delete_reason` VARCHAR(255) NULL');

CALL nu_add_col('feedback',      'deleted_at',     '`deleted_at` DATETIME NULL');
CALL nu_add_col('feedback',      'deleted_by',     '`deleted_by` INT UNSIGNED NULL');
CALL nu_add_col('feedback',      'delete_reason',  '`delete_reason` VARCHAR(255) NULL');

CALL nu_add_col('sample_drops',  'deleted_at',     '`deleted_at` DATETIME NULL');
CALL nu_add_col('sample_drops',  'deleted_by',     '`deleted_by` INT UNSIGNED NULL');
CALL nu_add_col('sample_drops',  'delete_reason',  '`delete_reason` VARCHAR(255) NULL');

-- ---------------- sample-drop pickup workflow -----------------------
CALL nu_add_col('sample_drops',  'payment_collected', '`payment_collected` DECIMAL(14,2) NOT NULL DEFAULT 0');
CALL nu_add_col('sample_drops',  'payment_method',    '`payment_method` VARCHAR(40) NULL');
CALL nu_add_col('sample_drops',  'next_pickup_date',  '`next_pickup_date` DATE NULL');
CALL nu_add_col('sample_drops',  'reschedule_reason', '`reschedule_reason` VARCHAR(255) NULL');

ALTER TABLE sample_drops
  MODIFY COLUMN status
    ENUM('scheduled','dropped','rescheduled','no_show','picked_up','cancelled')
    NOT NULL DEFAULT 'scheduled';

-- ---------------- indexes -------------------------------------------
CALL nu_add_idx('users',        'idx_users_deleted',    'deleted_at');
CALL nu_add_idx('products',     'idx_products_deleted', 'deleted_at');
CALL nu_add_idx('clients',      'idx_clients_deleted',  'deleted_at');
CALL nu_add_idx('sales',        'idx_sales_deleted',    'deleted_at');
CALL nu_add_idx('feedback',     'idx_feedback_deleted', 'deleted_at');
CALL nu_add_idx('sample_drops', 'idx_drops_deleted',    'deleted_at');

DROP PROCEDURE nu_add_col;
DROP PROCEDURE nu_add_idx;
