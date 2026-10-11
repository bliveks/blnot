# Notes App (MySQL)

Sessiya əsaslı autentifikasiya, CSRF qoruması və MySQL yaddaşı olan sadə qeyd tətbiqi.

## Tələblər
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Apache/Nginx

## Quraşdırma
1. `.env.example` faylını `.env` olaraq kopyalayın
2. `.env` içindəki MySQL məlumatlarını doldurun
3. `database.sql` dökümünü veritabanına import edin (yalnız `admin` istifadəçisi ilə)
4. Tətbiqi brauzerdə açın

İlk işlətmədə backend avtomatik olaraq tabelləri yaradır:
- `users`
- `categories`
- `notes`

İstifadəçilər yalnız admin paneli üzerinden yaradıla bilər.

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

## Təhlükəsizlik
- Sessiya möhkəmləndirməsi (`HttpOnly`, `SameSite`, opsiyonel `Secure`)
- CSRF token yoxlamaları
- Sadə qeyd input doğrulaması
- Sessiya faylları `sessions/.htaccess` ilə bağlanır
- `.env` və yüklənən fayllar gitə daxil edilmir (`.gitignore`ya baxın)
