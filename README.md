# MyPortfolio

A PHP/MySQL portfolio management system for XAMPP.

## Features
- Google account login (OAuth 2.0)
- Admin and user roles
- Admin CRUD for user accounts
- User profile, projects, creative artwork, education, experience, skills and certifications
- Public/private portfolio
- Per-section visibility and featured content
- Resume/CV PDF generation
- Harvard-style resume layout
- Secure file uploads
- PDO prepared statements and CSRF protection

## Requirements
- XAMPP with PHP 8.1+
- MySQL/MariaDB
- Composer
- Google Cloud OAuth 2.0 Web Application credentials

## Installation
1. Extract `myportfolio` into `C:\xampp\htdocs\`.
2. Create a MySQL database and import `database/myportfolio.sql`.
3. Run `composer install` inside the MyPortfolio directory.
4. Edit `config/config.php` with your database credentials.
5. Edit `config/google.php` with your Google OAuth Client ID and Client Secret.
6. In Google Cloud Console, add this Authorized Redirect URI:
   `http://localhost/myportfolio/auth/google-callback.php`
7. Open `http://localhost/myportfolio/`.

## First admin
After importing SQL, the seed admin is:
- Email: admin@myportfolio.local
- Role: admin

For Google login, change the seed admin email to the Google account you want to use, or create another admin directly in the database.

## Composer
`composer.json` includes:
- google/apiclient
- dompdf/dompdf

For local development, the uploads directory is writable by Apache.
"# myportfolio" 
"# myportfolio" 
