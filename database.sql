-- blnot SQL Dökümü
-- Veritabanı: `blnot`
-- Yalnızca tabel strukturu ve admin istifadeci

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `categories`
--

CREATE TABLE `categories` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `id` varchar(50) NOT NULL,
  `label` varchar(50) NOT NULL,
  `icon` varchar(50) NOT NULL,
  `color` char(7) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `notes`
--

CREATE TABLE `notes` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `id` varchar(64) NOT NULL,
  `content` mediumtext NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `completed` tinyint(1) NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `note_attachments`
--

CREATE TABLE `note_attachments` (
  `id` char(32) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `note_id` varchar(64) NOT NULL,
  `storage_key` varchar(80) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size_bytes` int(10) UNSIGNED NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$12$5sXw3PolBITvw8b6fmTcl.HTThqivnvMGzac/TNa0VlhVeFPXMSby', 'admin', 1, NULL, '2026-07-04 10:17:48', '2026-07-04 10:17:48');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`user_id`,`id`),
  ADD KEY `idx_categories_user` (`user_id`),
  ADD KEY `idx_categories_user_position` (`user_id`,`position`);

--
-- Tablo için indeksler `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`user_id`,`id`),
  ADD KEY `idx_notes_user_position` (`user_id`,`position`),
  ADD KEY `idx_notes_user_category` (`user_id`,`category`);

--
-- Tablo için indeksler `note_attachments`
--
ALTER TABLE `note_attachments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `storage_key` (`storage_key`),
  ADD KEY `idx_attachments_user_note` (`user_id`,`note_id`),
  ADD KEY `idx_attachments_user_note_position` (`user_id`,`note_id`,`position`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `note_attachments`
--
ALTER TABLE `note_attachments`
  ADD CONSTRAINT `fk_attachments_note` FOREIGN KEY (`user_id`,`note_id`) REFERENCES `notes` (`user_id`, `id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
