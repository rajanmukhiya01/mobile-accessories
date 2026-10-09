# Bazario Mobile Accessories Management System

PHP/MySQLi application for managing mobile-accessory products, customer orders, delivery OTPs, announcements, notifications, and user/admin profiles. It is designed to run locally with XAMPP.

## Technologies

- PHP 8 or later, MySQL/MariaDB, and MySQLi
- HTML and CSS (some pages load Bootstrap from a CDN)
- Composer with PHPMailer for email delivery

## Repository Layout

```text
admin/                 Organized admin pages by feature
api/                   Product and order actions
assets/css/            Shared Bazario and responsive stylesheets
auth/                  Login, registration, logout, and delivery OTP pages
components/            Shared user layout
database/migrations/   SQL migration files
database/scripts/      Database setup and migration scripts
includes/              Guards, configuration compatibility file, and services
public/                Public front controller/redirect
uploads/               Runtime product/profile/announcement images
user/                  User feature directories (some are currently empty)
vendor/                Composer dependencies (PHPMailer)
*.php                  Existing root-level routes retained for compatibility
```

Root-level PHP pages are still referenced by links and form handlers. The organized directories contain newer feature-based entry points, so do not remove either route generation until callers have been migrated and verified. `assets/styles.css` currently has no references; it is retained for review. No standalone JavaScript files were found in the project inventory.

Apache denies direct access to the local `.env` file in the root `.htaccess`. Upload protection is configured in `uploads/.htaccess`: directory listings and common executable script extensions are disabled while existing image URLs remain unchanged.

## Local Setup (XAMPP)

1. Place the repository at `C:\xampp\htdocs\mobile-accessories`.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. This repository does not include a verified blank-install schema. The SQL files under `database/migrations/` are upgrades that expect existing application tables, and `setup_db.php` also expects base tables and may create demo accounts. Inspect the scripts and back up your database before applying anything; use only a disposable local database for setup testing. Do not expose setup scripts on a public server.
4. Copy `.env.example` to `.env` and set local database and SMTP values. `.env` is ignored by Git. If database variables are omitted, the existing defaults are used: `127.0.0.1:3306`, database `Mproject`, user `root`, and an empty password.
5. Install dependencies if needed with `composer install`. The checked-in `vendor/` directory currently contains PHPMailer; `composer.json` and `composer.lock` remain the dependency source of truth.
6. Open `http://localhost/mobile-accessories/public/`. Existing root routes, including `minor.php`, `user_dashboard.php`, and legacy admin pages, remain available for compatibility.

## Email and Delivery OTP

For Gmail SMTP, enable 2-Step Verification and create a Gmail app password. Set `SMTP_HOST=smtp.gmail.com`, `SMTP_PORT=587`, `SMTP_SECURE=tls`, `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM`, and `MAIL_FROM_NAME` in the local `.env`. Never commit an app password. Set `OTP_ENC_KEY` to a long random value; the built-in fallback in `config.php` is not suitable for production.

Delivery OTP handling is implemented in the OTP service and related root/auth pages. SMS and WhatsApp are not configured as real providers in this repository. No forgot-password or password-reset pages were found in the current inventory.

## Main Features

- User registration, login, dashboard, product browsing, checkout, order history, and order tracking
- Admin dashboard, product management, order status management, announcements, and delivery OTP management/logs
- User/admin profiles, notifications, announcements, and email-based delivery OTP verification

## Development Checks

The GitHub Actions workflow installs Composer dependencies and runs `php -l` on project PHP files. It does not run database-backed or browser-level functional tests. To lint locally with XAMPP PHP:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```

## Troubleshooting

- **Database connection fails:** confirm MySQL is running, the database exists, and `DB_HOST`, `DB_PORT`, `DB_USER`, and `DB_PASS` match the local server.
- **Email is not sent:** verify the Gmail app password, sender address, SMTP port/security, and outbound SMTP access.
- **Styles or uploads are missing:** open the application under its XAMPP project URL and check browser network requests; many legacy root routes use paths relative to the project root.
- **A nested organized page fails:** check its includes and asset paths as well as the root compatibility page; both route generations remain in the repository.
- **Composer is unavailable:** install Composer for Windows or configure the Composer executable to use XAMPP PHP, then run `composer install` from the project directory.
