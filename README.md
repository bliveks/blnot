# Notes App (MySQL)

Simple notes app with session-based auth, CSRF protection, and MySQL storage.

## Requirements
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Apache/Nginx

## Setup
1. Copy `.env.example` to `.env`
2. Fill MySQL credentials in `.env`
3. Open the app in the browser

On first run, the backend creates tables automatically:
- `users`
- `categories`
- `notes`

Users can only be created from the admin panel.

## API
- `GET functions.php?action=checkSession`
- `GET functions.php?action=getNotes`
- `GET functions.php?action=getCategories`
- `POST functions.php?action=login`
- `POST functions.php?action=logout`
- `POST functions.php?action=saveNotes`
- `POST functions.php?action=saveCategories`
- `GET functions.php?action=getUsers` (admin)
- `POST functions.php?action=createUser` (admin)

## Security
- Session hardening (`HttpOnly`, `SameSite`, optional `Secure`)
- CSRF token checks
- Basic note input validation
- Session files are denied by `sessions/.htaccess`
