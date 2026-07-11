# Bazario — Mobile Accessories (Local)

This repository contains a lightweight PHP/MySQLi e-commerce demo (Bazario) with a Delivery OTP verification system implemented.

Quick start (Windows + XAMPP):

1. Start XAMPP (Apache + MySQL).
2. Import the database schema in `database_migrations/` or run the migration script.
3. Install PHP dependencies (uses XAMPP PHP):
```
cd C:\xampp\htdocs\mobile-accessories
C:\xampp\php\php.exe composer.phar install
```
4. Configure environment and SMTP by editing `includes/config.php` or set environment variables:
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE`, `MAIL_FROM`, `MAIL_FROM_NAME`

5. Open the app in your browser: `http://localhost/mobile-accessories/public/` (adjust if using different DocumentRoot).

Notes:
- Delivery OTPs are generated immediately after checkout and are sent via Email/SMS/WhatsApp (SMS/WhatsApp simulated locally).
- OTPs are stored encrypted and verified via secure hashes. Admin pages allow OTP management and logs.

Contributing:
- Use feature branches and open pull requests. Consider enabling branch protection rules on `master`.

License: MIT (adapt as needed)
