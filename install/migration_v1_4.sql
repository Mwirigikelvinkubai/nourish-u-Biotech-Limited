-- =======================================================================
--  Migration v1.4 – per-line discount on sale_items
--  Run this once in phpMyAdmin on the nourish_u database.
-- =======================================================================
ALTER TABLE sale_items
  ADD COLUMN disc_pct DECIMAL(5,2) NOT NULL DEFAULT 0
    COMMENT 'Per-line discount percentage e.g. 10.00 = 10%'
    AFTER unit_price;
