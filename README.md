# Nourish U Biotech Limited – Med Distribution Software

A complete PHP + MySQL platform for **Nourish U Biotech Limited** to manage importation, distribution and sales of medicines, supplements and related products.

> *"Your Partner in Natural Wellness."*

---



## What's new in v1.2

* **Modern split-screen login** with the Nourish U gradient and brand logo.
* **Footer credit** &mdash; "Software by Kimiru Ventures" on every page.
* **Soft-delete** for users, products, clients, sales, feedback and sample drops &mdash; with reason + audit. Admin can view **Archived** and restore.
* **Sample-drop pickups** now record at the visit: cash/M-Pesa payment collected, returned units (auto-flow back to stock), and let you **reschedule** or mark **no-show** with a reason. Each drop carries a prominent *next pickup date*.
* **Reports** expanded: top clients with balance + KYC, monthly/weekly time-trend (Chart.js bars), sample-drop conversion %, feedback severity + per-rep resolution analytics.

### Upgrading from v1.0/v1.1 &mdash; run the migration

```
mysql -u root nourish_u < install/migration_v1_2.sql
```

Fresh installs already get everything via `schema.sql + seed.sql`.

## Modules

1. **User Management** – Admin / Director, Medical Reps, Accountant / Finance.
2. **Rep Profiles** – ID, PPB licence, region, target, hire date, bank, next-of-kin.
3. **Products & Inventory** – batch / expiry, stock levels, reorder alerts, per-product commission %.
4. **Clients & KYC** – Pharmacy / clinic / hospital / wholesaler clients with the full *Supplier KYC Profile* (directors, accountant, credit limit, payment terms, credit period, three trade references), document uploads and **GPS coordinates** with interactive map.
5. **Account Opening Form PDF** – One-click pre-filled, branded *Supplier KYC Profile* per client, ready for signature.
6. **Sales & Invoices** – Multi-line sale entry, auto invoice numbers, branded letterhead invoice with **NCBA bank** and **M-Pesa Pay Bill** payment details auto-rendered, payment tracking.
7. **Free Sample Drops & Pickups** – Schedule, mark dropped, confirm pickup; unused units flow back to stock.
8. **Feedback & Complaints** – Type / severity / follow-up workflow.
9. **Hybrid Commissions** – Per-product commission % **plus** monthly volume tiered bonus.
10. **Reports & Dashboards** – Sales by rep / product / region, expiry alerts, commission ledger, top clients.

## Tech stack

* **PHP 8.0+** (plain PHP, no framework)
* **MySQL / MariaDB**
* **Bootstrap 5** + **Bootstrap Icons** (CDN)
* **Leaflet + OpenStreetMap** for client maps – no API key needed.

---

## XAMPP installation (Windows)

This project lives at **`C:\xampp\htdocs\nourish_u`** so it's reachable at **http://localhost/nourish_u/**.

1. Install XAMPP (https://www.apachefriends.org), then start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open phpMyAdmin → http://localhost/phpmyadmin
3. **Create a new database** called `nourish_u` (collation `utf8mb4_general_ci`).
4. Click the new database → **Import** tab:
   * import `C:\xampp\htdocs\nourish_u\install\schema.sql`
   * then import `C:\xampp\htdocs\nourish_u\install\seed.sql`
5. Open **http://localhost/nourish_u/** and log in.

> If you previously installed v1.0, you can ALTER your database in place by importing `install/migration_v1_1.sql` instead of re-running `schema.sql`.

### Default login

| Role        | Email                         | Password   |
|-------------|-------------------------------|------------|
| Admin       | admin@nourishu.co.ke          | admin123   |
| Med Rep     | rep@nourishu.co.ke            | rep123     |
| Accountant  | finance@nourishu.co.ke        | finance123 |

> **Change these immediately after first login** (top-right menu → My Profile).

### DB credentials

If you ever change MySQL user/password, edit `config/db.php`. On a stock XAMPP install user is `root` and password is empty — no edit needed.

---

## Folder layout

```
htdocs/nourish_u/                 ← drop this into C:\xampp\htdocs\
├── README.md
├── index.php                     ← entry point
├── login.php / logout.php / dashboard.php / profile.php / download.php
├── admin/    {users,products,tiers,settings}.php
├── clients/  {index,add,edit,view,kyc_pdf,_form_fields}.php
├── sales/    {index,add,view,invoice}.php
├── samples/  {index,add,view}.php
├── feedback/ {index,add}.php
├── inventory/index.php
├── commissions/index.php
├── reports/index.php
├── assets/
│   ├── css/style.css
│   └── img/{logo_full,logo_nav,logo_login,logo_dark,logo_letterhead,icon,favicon}.png
├── config/   {db,config}.php          ← .htaccess deny
├── includes/ {auth,functions,commission,header,sidebar,footer}.php  ← .htaccess deny
├── install/  {schema,seed,migration_v1_1}.sql                       ← .htaccess deny
└── uploads/  ← KYC documents (PHP execution denied)
```

---

## Brand identity

The app is themed with the official **Nourish U Biotech Limited** palette pulled from the supplied logo:

* **Cyan / teal** `#3DC9D9` (primary)
* **Deep purple** `#594396` (secondary / gradient end)
* **Gradient** for navbar, login splash and printed letterhead bars

The official logo appears in the navbar, login splash and at the top of every printed invoice and KYC form. The official **NCBA bank** and **M-Pesa Pay Bill** payment details auto-render on every printed invoice's footer.

---

## Hybrid commission model

```
line_commission = line_total × product.base_commission_pct           (per sale line)
monthly_bonus   = monthly_subtotal × tier.bonus_pct                  (per rep, per month)
total_commission = SUM(line_commission)  +  monthly_bonus
```

Tiers and per-product percentages are fully editable from
*Admin → Commission Tiers* and *Admin → Products*.

---

## Security

* All DB queries use **PDO prepared statements**.
* All output is escaped with `htmlspecialchars()`.
* **CSRF tokens** required on every state-changing form.
* **Role-based access control** via `require_role()`.
* Passwords hashed with bcrypt (`password_hash()`).
* `config/`, `includes/`, `install/` blocked by `.htaccess`.
* `uploads/` denies any PHP execution and serves files only via the auth-checked `download.php` endpoint.
* Every state change is recorded in `audit_log`.
