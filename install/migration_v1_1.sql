-- =======================================================================
--  Nourish U Biotech Limited – Migration to v1.1
--  Adds full Account Opening Form KYC fields + bank-payment settings.
--  Safe to run on an existing v1.0 database (uses IF NOT EXISTS / ALTER).
-- =======================================================================

ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS postal_address     VARCHAR(255) NULL AFTER address,
  ADD COLUMN IF NOT EXISTS directors          TEXT         NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS accountant_name    VARCHAR(160) NULL,
  ADD COLUMN IF NOT EXISTS accountant_phone   VARCHAR(40)  NULL,
  ADD COLUMN IF NOT EXISTS bank_name          VARCHAR(80)  NULL,
  ADD COLUMN IF NOT EXISTS bank_branch        VARCHAR(80)  NULL,
  ADD COLUMN IF NOT EXISTS payment_terms      VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS credit_period_days INT          NULL,
  ADD COLUMN IF NOT EXISTS trade_ref_1        TEXT         NULL,
  ADD COLUMN IF NOT EXISTS trade_ref_2        TEXT         NULL,
  ADD COLUMN IF NOT EXISTS trade_ref_3        TEXT         NULL,
  ADD COLUMN IF NOT EXISTS signed_name        VARCHAR(160) NULL,
  ADD COLUMN IF NOT EXISTS signed_position    VARCHAR(80)  NULL,
  ADD COLUMN IF NOT EXISTS signed_at          DATE         NULL;

-- Seed the Nourish U company + bank-payment settings (upsert)
INSERT INTO settings (`key`,`value`) VALUES
  ('company_name',    'Nourish U Biotech Limited'),
  ('company_address', 'P.O. Box 761 – 00515, Nairobi, Kenya'),
  ('company_phone',   '+254 720 089 063 / +254 780 089 063'),
  ('company_email',   'Nourishupharma@gmail.com'),
  ('company_tagline', 'Your Partner in Natural Wellness'),
  ('bank_name',         'NCBA Bank'),
  ('bank_branch',       'ABC Place'),
  ('bank_account_name', 'Nourish U Biotech Limited'),
  ('bank_account_kes',  '1005858439'),
  ('bank_account_usd',  '1006641133'),
  ('bank_swift',        'CBAFKENX'),
  ('mpesa_paybill',     '880100'),
  ('mpesa_account',     '606264')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
