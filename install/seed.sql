-- =======================================================================
--  Nourish U Biotech Limited – Seed data
--  Default admin / rep / accountant + sample products / clients / sales
-- =======================================================================

-- Default users -------------------------------------------------------
-- Passwords (bcrypt of):  admin123 / rep123 / finance123
-- These hashes were generated with PHP password_hash(..., PASSWORD_BCRYPT).
INSERT INTO users (name, email, phone, password_hash, role, status) VALUES
 ('System Administrator', 'admin@nourishu.co.ke',   '+254700000001',
  '$2y$10$WnID.2b4jExr3AhoPfapfeownbqTI3twNbtQ8nkn1o6lrTwuCjrHK', 'admin',     'active'),
 ('Daniel Kariuki (Rep)','rep@nourishu.co.ke',      '+254700000002',
  '$2y$10$pai6Ov5422kOUZlNXl64ROMS4n6b8p7gapEsZU81k3ospyaAq/nca', 'rep',       'active'),
 ('Grace Wanjiru (Finance)','finance@nourishu.co.ke','+254700000003',
  '$2y$10$.ckqBKg.o.Nt/q05FD8HvO8DR9fUg1.GS/blPYFkL2p7gS756COrK', 'accountant','active');

-- Hashes above were created at runtime; if your PHP version chokes,
-- log in once with these credentials AND a password reset will rehash:
--   admin@nourishu.co.ke  / admin123
--   rep@nourishu.co.ke    / rep123
--   finance@nourishu.co.ke/ finance123

INSERT INTO rep_profiles (user_id, id_number, license_no, region, monthly_target, hire_date, bio)
VALUES (2, '28765431', 'PPB-REP-2024-117', 'Nairobi Central', 800000.00, '2024-02-01',
        'Field rep covering CBD pharmacies and clinics.');

-- Commission tiers ----------------------------------------------------
INSERT INTO commission_tiers (label, min_amount, max_amount, bonus_pct) VALUES
 ('Tier 1 – Starter', 0,        499999.99, 0.00),
 ('Tier 2 – Bronze',  500000,   999999.99, 1.50),
 ('Tier 3 – Silver',  1000000,  1999999.99,3.00),
 ('Tier 4 – Gold',    2000000,  NULL,      5.00);

-- Products ------------------------------------------------------------
INSERT INTO products
 (sku, name, category, manufacturer, country_of_origin, unit, price, cost,
  base_commission_pct, stock_qty, reorder_level, batch_no, expiry_date, status, description)
VALUES
 ('NU-VITC-1000', 'Vitamin C 1000mg (60 tabs)',  'Supplement', 'Nutramed Labs',
  'India',   'bottle',  650.00,  320.00, 8.00, 240, 50, 'B24-VC-117', '2027-03-31', 'active',
  'High-strength immunity booster.'),
 ('NU-OMEG-500', 'Omega 3 Fish Oil 500mg (90 caps)','Supplement','HealthSeas',
  'Norway',  'bottle', 1450.00,  720.00, 7.00, 180, 40, 'B24-OM-019', '2026-12-31','active',
  'EPA + DHA softgel capsules.'),
 ('NU-PARA-500', 'Paracetamol 500mg (24 tabs)',   'OTC Medicine','Cosmos Ltd',
  'Kenya',   'pack',    45.00,   18.00, 4.00, 600, 120,'PP-2025-09', '2027-09-30','active',
  'Analgesic / antipyretic.'),
 ('NU-AMOX-250','Amoxicillin 250mg (caps x100)',  'Prescription','MediCore Pharma',
  'India',   'pack',   320.00,  155.00, 4.00, 150,  30,'AX-2025-11', '2026-11-30','active',
  'Broad-spectrum antibiotic. Rx only.'),
 ('NU-IRON-FE',  'Iron + Folate (Pregnacare) 30tab','Supplement','WellBaby Corp',
  'UK',      'pack',   780.00,  410.00, 9.00,  90,  25,'IF-25-04',   '2027-04-30','active',
  'Antenatal iron + folic acid.'),
 ('NU-ORS-PWD', 'ORS Sachets (6-pack)',            'OTC Medicine','Cosmos Ltd',
  'Kenya',   'pack',    90.00,   38.00, 3.00, 800, 150,'OR-2025-02', '2027-02-28','active',
  'Oral rehydration salts.'),
 ('NU-DERM-CR', 'Hydrocortisone Cream 1% 15g',     'OTC Medicine','DermaPlus',
  'India',   'tube',   180.00,   85.00, 5.00, 220,  40,'HC-2025-08', '2026-08-31','active',
  'Topical anti-inflammatory cream.'),
 ('NU-MULTI-K', 'Multivitamin Kids Syrup 200ml',   'Supplement','Kid-Health',
  'UK',      'bottle', 540.00,  270.00, 8.00, 110,  30,'MK-2024-12', '2026-06-30','active',
  'Sweet syrup, ages 2-12.');

-- Clients -------------------------------------------------------------
INSERT INTO clients
 (name, type, license_no, kra_pin, contact_person, contact_role, phone, email,
  address, region, city, lat, lng, kyc_status, rep_id, credit_limit)
VALUES
 ('Afya Bora Pharmacy',     'pharmacy', 'PPB-RP-7781','P051234567X',
  'James Otieno','Owner', '+254712345678','jamesotieno@afyabora.co.ke',
  'Tom Mboya St', 'Nairobi','Nairobi',-1.2848,36.8255,'verified', 2, 200000.00),
 ('Westlands Medi Clinic',  'clinic',   'KMPDC-CL-9921','P061122334X',
  'Dr. Mary Kihiu','Director','+254722111222','clinic@westmed.co.ke',
  'Waiyaki Way',  'Nairobi','Nairobi',-1.2630,36.8055,'verified', 2, 150000.00),
 ('Highlife Wholesalers',   'wholesaler','PPB-WH-4412','P012999000X',
  'Peter Mwangi','Procurement','+254733445566','procure@highlife.co.ke',
  'Industrial Area','Nairobi','Nairobi',-1.3105,36.8554,'verified', 2, 750000.00),
 ('Sunset Pharmacy Karen',  'pharmacy', 'PPB-RP-3357', NULL,
  'Faith Njeri','Owner','+254701020304','sunset.karen@gmail.com',
  'Karen Hardy',   'Nairobi','Nairobi',-1.3290,36.7090,'pending',  2, 50000.00);

-- Sample sale (single multi-line invoice) -----------------------------
INSERT INTO sales
 (invoice_no, client_id, rep_id, sale_date, subtotal, tax_pct, tax_amount,
  discount, total, payment_status, paid_amount, payment_method, notes)
VALUES
 ('INV-2026-0001', 1, 2, '2026-04-28',
  4400.00, 16.00, 704.00, 0.00, 5104.00, 'paid', 5104.00, 'M-Pesa',
  'First demo sale.');

INSERT INTO sale_items
 (sale_id, product_id, qty, unit_price, line_total, commission_pct, commission_amount)
VALUES
 (1, 1,  4,  650.00, 2600.00, 8.00, 208.00),
 (1, 3, 20,   45.00,  900.00, 4.00,  36.00),
 (1, 6, 10,   90.00,  900.00, 3.00,  27.00);

-- Sample sample-drop (scheduled) --------------------------------------
INSERT INTO sample_drops (client_id, rep_id, scheduled_date, status, notes)
VALUES (4, 2, '2026-05-10', 'scheduled', 'Drop trial pack of multivitamin kids syrup.');

INSERT INTO sample_drop_items (drop_id, product_id, qty_dropped) VALUES
 (1, 8, 5);

-- Sample feedback -----------------------------------------------------
INSERT INTO feedback (client_id, rep_id, sale_id, type, severity, message, status)
VALUES
 (1, 2, 1, 'praise',     'low',    'Customers love the new Vitamin C packaging.', 'closed'),
 (2, 2, NULL,'suggestion','medium','Please introduce paediatric paracetamol syrup.','open');

-- Settings ------------------------------------------------------------
-- Real Nourish U Biotech Limited details (from Account Opening Form & Bank Details)
INSERT INTO settings (`key`, `value`) VALUES
 ('company_name',     'Nourish U Biotech Limited'),
 ('company_tagline',  'Your Partner in Natural Wellness'),
 ('company_address',  'P.O. Box 761 – 00515, Nairobi, Kenya'),
 ('company_phone',    '+254 720 089 063 / +254 780 089 063'),
 ('company_email',    'Nourishupharma@gmail.com'),
 ('vat_default_pct',  '16.00'),
 ('invoice_prefix',   'INV-2026-'),
 -- NCBA bank account
 ('bank_name',         'NCBA Bank'),
 ('bank_branch',       'ABC Place'),
 ('bank_account_name', 'Nourish U Biotech Limited'),
 ('bank_account_kes',  '1005858439'),
 ('bank_account_usd',  '1006641133'),
 ('bank_swift',        'CBAFKENX'),
 -- M-Pesa Pay Bill
 ('mpesa_paybill',     '880100'),
 ('mpesa_account',     '606264');
