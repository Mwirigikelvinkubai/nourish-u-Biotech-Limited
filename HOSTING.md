# Nourish U Biotech &mdash; Production Hosting Guide

Deploy `nourish_u/` to a real domain in three flavours, then run through the pre-flight checklist.

The app needs:
- **PHP 8.0+** (8.1 or 8.2 recommended) with `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (optional, for image previews).
- **MySQL 5.7+** or **MariaDB 10.3+**.
- **Apache** with `mod_rewrite`, `mod_headers`, or **Nginx** with PHP-FPM.
- An SSL/TLS certificate (Let's Encrypt is free).

---

## Option A &mdash; Shared hosting (cPanel)

1. **Upload via File Manager or FTP** to `public_html/` (or a subfolder like `public_html/app/`). The entire contents of `nourish_u/` go in.
2. **Create the database** in cPanel &rarr; *MySQL Databases*: e.g. `youracct_nourishu`. Create a user with a strong password and add them with **All Privileges**.
3. **Import the schema**: phpMyAdmin &rarr; select the new DB &rarr; *Import* &rarr; pick `install/schema.sql`, then again with `install/seed.sql`.
4. **Edit credentials** in `config/db.php`:
   ```php
   $DB_HOST = 'localhost';
   $DB_NAME = 'youracct_nourishu';
   $DB_USER = 'youracct_nu';
   $DB_PASS = 'YourStrongPassword';
   ```
5. Point your domain (or sub-domain) to the folder; in cPanel that is usually *Domains &rarr; Document Root*.
6. Force HTTPS via cPanel's *SSL/TLS Status &rarr; Run AutoSSL*, then enable *Force HTTPS Redirect*.

---

## Option B &mdash; VPS / dedicated (Ubuntu + Apache)

```bash
# 1. Install PHP + MariaDB + Apache
sudo apt update
sudo apt install -y apache2 mariadb-server \
                    php php-cli php-mysql php-mbstring php-xml php-gd php-curl libapache2-mod-php

# 2. Create the database
sudo mysql -e "
  CREATE DATABASE nourishu CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
  CREATE USER 'nourishu'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG';
  GRANT ALL PRIVILEGES ON nourishu.* TO 'nourishu'@'localhost';
  FLUSH PRIVILEGES;"

# 3. Upload the app
sudo rsync -a --delete ./nourish_u/ /var/www/nourishu/
sudo chown -R www-data:www-data /var/www/nourishu
sudo chmod -R 750 /var/www/nourishu
sudo chmod -R 770 /var/www/nourishu/uploads

# 4. Edit config/db.php with the new user/password.

# 5. Apache vhost
sudo tee /etc/apache2/sites-available/nourishu.conf > /dev/null <<'CONF'
<VirtualHost *:80>
    ServerName  nourishu.example.com
    DocumentRoot /var/www/nourishu
    <Directory /var/www/nourishu>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog  ${APACHE_LOG_DIR}/nourishu-error.log
    CustomLog ${APACHE_LOG_DIR}/nourishu-access.log combined
</VirtualHost>
CONF
sudo a2ensite nourishu
sudo a2enmod rewrite headers
sudo systemctl reload apache2

# 6. Import schema
sudo mysql nourishu < /var/www/nourishu/install/schema.sql
sudo mysql nourishu < /var/www/nourishu/install/seed.sql

# 7. Free HTTPS with Let's Encrypt
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d nourishu.example.com
```

---

## Option C &mdash; VPS with Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name nourishu.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name nourishu.example.com;
    root /var/www/nourishu;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/nourishu.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/nourishu.example.com/privkey.pem;

    # Block the private folders even if reached directly
    location ~ ^/(config|includes|install)/ { deny all; return 404; }
    # Block PHP execution inside uploads
    location ~ ^/uploads/.*\.(php|phtml|phar)$ { deny all; return 404; }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    client_max_body_size 10M;
}
```

---

## Pre-flight checklist (run this on production)

1. **Change every default password.** Sign in as `admin@nourishu.co.ke / admin123`, change it via *Top-right menu &rarr; My Profile &rarr; New password*. Repeat for the rep and accountant seeded accounts. Then create your real team and **soft-delete or rename the seed accounts**.
2. **Update company details** under *Admin &rarr; Settings* &mdash; especially the bank account numbers and M-Pesa PayBill if they are different from the demo values.
3. **Set `display_errors = Off`** in your production `php.ini` (it should already be off on cPanel/most hosts). Keep `log_errors = On` and check `/var/log/apache2/error.log` (or cPanel's *Errors* page).
4. **`config/db.php` is in `.gitignore`** but verify it isn't world-readable: `chmod 640 config/db.php`.
5. **`uploads/` is writeable** by the web user (`www-data` on Ubuntu, the cPanel user on shared). Receipts and KYC documents land there.
6. **`uploads/.htaccess`** blocks PHP execution; verify with `curl https://your-domain/uploads/test.php` &mdash; it should refuse.
7. **`config/`, `includes/`, `install/`** are blocked by their `.htaccess`. Verify with `curl https://your-domain/config/db.php` &mdash; 403/404.
8. **HTTPS is forced**: `http://` should 301 to `https://`. Most browsers will flag insecure forms with passwords otherwise.
9. **Database backups**: schedule a nightly mysqldump:
   ```
   0 2 * * * mysqldump nourishu | gzip > /var/backups/nourishu-$(date +\%F).sql.gz
   ```
10. **Auto-migrator stays on**: `includes/migrate.php` is idempotent (column-add-if-missing); leave it in place so future updates run on first request.
11. **Timezone**: `config/config.php` is set to `Africa/Nairobi`. Change if you're not in EAT.
12. **Logo & brand**: Edit *Admin &rarr; Settings* to update the company name, tagline, email, phone. The logo files live in `assets/img/`.
13. **Mail (future)**: There's no SMTP wiring yet. If you later want password-reset emails or sample-drop reminders, plug in PHPMailer with Gmail/SendGrid/Mailgun.

---

## Update workflow

Each time you push a code update:

```bash
cd /var/www/nourishu
git pull          # (if you set up git)
# or rsync the new files over

# Database upgrades happen automatically the next time
# any user loads any page (auto-migrator adds missing columns
# and creates new tables). No phpMyAdmin step needed.

sudo systemctl reload apache2     # optional, clears PHP opcache
```

If you bypass git and just FTP changes, **clear PHP's OPcache** by restarting Apache/PHP-FPM &mdash; otherwise the server may keep serving the old `.php` for up to a minute.

---

## Smoke-test after going live

1. Sign in as admin &rarr; *Admin &rarr; Settings* &rarr; verify bank details.
2. *Admin &rarr; Users* &rarr; create a real rep + a real accountant. Soft-delete the seed `rep@nourishu.co.ke` etc.
3. *Clients* &rarr; create a real client, capture KYC, drop a GPS pin.
4. *Sales* &rarr; log a sale, print the invoice (Save as PDF) &mdash; verify NCBA + M-Pesa details render.
5. *Commissions* &rarr; current month should show the rep's live figures from that sale.
6. *Expenses* &rarr; rep logs an expense with receipt; accountant approves and marks paid.
7. *Sample drops* &rarr; schedule one, mark it dropped, then come back later to confirm pickup or reschedule.
8. *Reports* &rarr; click *Download PDF* &mdash; should print the company letterhead.

Once steps 1-8 pass, you are live.
