# MBELMS (Machine Booking & Equipment Loan Management System) - Simple PHP Starter

Simple secure PHP + MySQL project based on the SWAP proposal.

## Requirements
- XAMPP (Apache + MySQL)
- PHP 8.x

## Setup
1) Copy this folder into your XAMPP `htdocs` (e.g., `htdocs/mbelms`).
2) Create database + tables:
   - Open phpMyAdmin and run `database.sql`
3) Update DB credentials in `config/app.php` if needed.
4) Visit: `http://localhost/mbelms/`

## First run setup
After importing `database.sql`, open:
- `http://localhost/mbelms/setup.php`

This will create demo accounts:
- admin / Admin123!
- staff / Staff123!

(After setup, `setup.php` auto-disables itself if users already exist.)

## Folder structure
- `config/` app + db bootstrap
- `lib/` shared helpers (auth, csrf, flash)
- `includes/` header/footer
- `pages/` staff pages (login/dashboard)
- `admin/` admin-only pages
