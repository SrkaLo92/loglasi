-- WordPress + Listivo, primer za testiranje uvoza (php artisan uvoz:wordpress).
-- Oblik tabela je kao u WordPress-u 6.x; podaci su izmišljeni.
SET NAMES utf8mb4;
DROP TABLE IF EXISTS `wp_options`, `wp_users`, `wp_usermeta`, `wp_posts`, `wp_postmeta`, `wp_terms`, `wp_term_taxonomy`, `wp_term_relationships`, `wp_termmeta`, `wp_listivo_messages`;

CREATE TABLE `wp_options` (
  `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `option_name` varchar(191) NOT NULL DEFAULT '',
  `option_value` longtext NOT NULL,
  `autoload` varchar(20) NOT NULL DEFAULT 'yes',
  PRIMARY KEY (`option_id`),
  UNIQUE KEY `option_name` (`option_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `wp_users` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_login` varchar(60) NOT NULL DEFAULT '',
  `user_pass` varchar(255) NOT NULL DEFAULT '',
  `user_nicename` varchar(50) NOT NULL DEFAULT '',
  `user_email` varchar(100) NOT NULL DEFAULT '',
  `user_url` varchar(100) NOT NULL DEFAULT '',
  `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_activation_key` varchar(255) NOT NULL DEFAULT '',
  `user_status` int(11) NOT NULL DEFAULT 0,
  `display_name` varchar(250) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  KEY `user_login_key` (`user_login`),
  KEY `user_nicename` (`user_nicename`),
  KEY `user_email` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `wp_usermeta` (
  `umeta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `meta_key` varchar(255) DEFAULT NULL,
  `meta_value` longtext DEFAULT NULL,
  PRIMARY KEY (`umeta_id`),
  KEY `user_id` (`user_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `wp_posts` (
  `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_author` bigint(20) unsigned NOT NULL DEFAULT 0,
  `post_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content` longtext NOT NULL,
  `post_title` text NOT NULL,
  `post_excerpt` text NOT NULL,
  `post_status` varchar(20) NOT NULL DEFAULT 'publish',
  `comment_status` varchar(20) NOT NULL DEFAULT 'open',
  `ping_status` varchar(20) NOT NULL DEFAULT 'open',
  `post_password` varchar(255) NOT NULL DEFAULT '',
  `post_name` varchar(200) NOT NULL DEFAULT '',
  `to_ping` text NOT NULL,
  `pinged` text NOT NULL,
  `post_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_modified_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content_filtered` longtext NOT NULL,
  `post_parent` bigint(20) unsigned NOT NULL DEFAULT 0,
  `guid` varchar(255) NOT NULL DEFAULT '',
  `menu_order` int(11) NOT NULL DEFAULT 0,
  `post_type` varchar(20) NOT NULL DEFAULT 'post',
  `post_mime_type` varchar(100) NOT NULL DEFAULT '',
  `comment_count` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `post_name` (`post_name`(191)),
  KEY `type_status_date` (`post_type`,`post_status`,`post_date`,`ID`),
  KEY `post_parent` (`post_parent`),
  KEY `post_author` (`post_author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `wp_postmeta` (
  `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `meta_key` varchar(255) DEFAULT NULL,
  `meta_value` longtext DEFAULT NULL,
  PRIMARY KEY (`meta_id`),
  KEY `post_id` (`post_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `wp_terms` (
  `term_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL DEFAULT '',
  `slug` varchar(200) NOT NULL DEFAULT '',
  `term_group` bigint(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`term_id`),
  KEY `slug` (`slug`(191)),
  KEY `name` (`name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `wp_term_taxonomy` (
  `term_taxonomy_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `taxonomy` varchar(32) NOT NULL DEFAULT '',
  `description` longtext NOT NULL,
  `parent` bigint(20) unsigned NOT NULL DEFAULT 0,
  `count` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`term_taxonomy_id`),
  UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`),
  KEY `taxonomy` (`taxonomy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `wp_term_relationships` (
  `object_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `term_taxonomy_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `term_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`object_id`,`term_taxonomy_id`),
  KEY `term_taxonomy_id` (`term_taxonomy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `wp_termmeta` (
  `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `meta_key` varchar(255) DEFAULT NULL,
  `meta_value` longtext DEFAULT NULL,
  PRIMARY KEY (`meta_id`),
  KEY `term_id` (`term_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Pretpostavljen oblik Listivo tabele poruka (kolone se podešavaju u config/uvoz.php).
CREATE TABLE `wp_listivo_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_from` bigint(20) unsigned NOT NULL,
  `user_to` bigint(20) unsigned NOT NULL,
  `listing_id` bigint(20) unsigned DEFAULT NULL,
  `message` text NOT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

INSERT INTO `wp_options` (`option_name`, `option_value`, `autoload`) VALUES
('siteurl', 'http://loglasirs.local', 'yes'),
('home', 'http://loglasirs.local', 'yes'),
('loglasi_kurs_eur', '117.5', 'yes'),
('loglasi_cenovnik', 'a:4:{i:0;a:6:{s:2:"id";s:9:"podizanje";s:5:"naziv";s:16:"Podizanje oglasa";s:4:"cena";i:60;s:8:"trajanje";s:11:"jednokratno";s:4:"opis";s:29:"Oglas se vraća na vrh liste.";s:7:"aktivno";i:1;}i:1;a:6:{s:2:"id";s:11:"istaknuto-7";s:5:"naziv";s:21:"Istaknut oglas 7 dana";s:4:"cena";i:300;s:8:"trajanje";s:6:"7 dana";s:4:"opis";s:22:"Izdvojen na početnoj.";s:7:"aktivno";i:1;}i:2;a:6:{s:2:"id";s:12:"istaknuto-30";s:5:"naziv";s:22:"Istaknut oglas 30 dana";s:4:"cena";i:800;s:8:"trajanje";s:7:"30 dana";s:4:"opis";s:10:"Ceo mesec.";s:7:"aktivno";i:0;}i:3;a:6:{s:2:"id";s:6:"biznis";s:5:"naziv";s:12:"Biznis paket";s:4:"cena";i:6000;s:8:"trajanje";s:9:"godišnje";s:4:"opis";s:13:"Profil firme.";s:7:"aktivno";i:1;}}', 'yes'),
('loglasi_pocetna_besplatni_oglasi', '10', 'yes'),
('loglasi_pocetna_biznis_besplatno', '30', 'yes');

INSERT INTO `wp_users` (`ID`, `user_login`, `user_pass`, `user_nicename`, `user_email`, `user_url`, `user_registered`, `user_activation_key`, `user_status`, `display_name`) VALUES
(1, 'srdjan', '$P$BZlPX7NIx8MYpXokBW2AGsN7i.aUOt0', 'srdjan', 'srdjan@example.com', '', '2024-03-01 10:00:00', '', 0, 'Srđan'),
(2, 'marko', '$wp$2y$10$abcdefghijklmnopqrstuuJ5Zb6fV1QnZ0b9cQ7sVd8m3E2r1yXcO', 'marko-petrovic', 'marko@example.com', '', '2024-05-10 12:30:00', '', 0, 'Marko Petrović'),
(3, 'jelena', '$P$BDn3QJ8i9W2Yx7VtN5u4QeRbK1pLmSo/', 'jelena', 'jelena@example.com', '', '2024-06-02 08:15:00', '', 0, 'Jelena Đorđević'),
(5, 'pera', '$P$B12345678901234567890123456789.', 'pera', 'pera@example.com', '', '2024-07-01 09:00:00', '', 0, 'Pera Lošbroj'),
(7, 'novak', '$wp$2y$10$zyxwvutsrqponmlkjihgfeQ1w2e3r4t5y6u7i8o9p0a1s2d3f4g5h6', 'novak-novi', 'novak@example.com', '', '2026-10-20 18:00:00', '', 0, 'Novak Nović'),
(80, 'kredit', '$P$Bspamspamspamspamspamspamspam1', 'brzi-krediti', 'spam1@example.com', '', '2025-01-01 00:00:00', '', 0, 'Brzi Krediti'),
(106, 'casino', '$P$Bspamspamspamspamspamspamspam2', 'casino-bonus', 'spam2@example.com', '', '2025-02-01 00:00:00', '', 0, 'Casino'),
(109, 'pills', '$P$Bspamspamspamspamspamspamspam3', 'pills-shop', 'spam3@example.com', '', '2025-03-01 00:00:00', '', 0, 'Pills'),
(117, 'drina', '$P$Bdrinadrinadrinadrinadrinadrin1', 'auto-centar-drina', 'drina@example.com', '', '2024-04-15 11:00:00', '', 0, 'Auto centar Drina');

INSERT INTO `wp_usermeta` (`user_id`, `meta_key`, `meta_value`) VALUES
(1, 'wp_capabilities', 'a:1:{s:13:"administrator";b:1;}'),
(1, 'nickname', 'srdjan'),
(2, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}'),
(2, 'nickname', 'marko'),
(3, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}'),
(3, 'nickname', 'jelena'),
(5, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}'),
(5, 'nickname', 'pera'),
(7, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}'),
(7, 'nickname', 'novak'),
(80, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}'),
(80, 'nickname', 'kredit'),
(106, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}'),
(106, 'nickname', 'casino'),
(109, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}'),
(109, 'nickname', 'pills'),
(117, 'wp_capabilities', 'a:1:{s:10:"subscriber";b:1;}'),
(117, 'nickname', 'drina'),
(1, 'phone', '0655415128'),
(1, 'phone_country_code', '&#x1F1F7&#x1F1F8 +381'),
(2, 'phone', '065/123-4567'),
(2, 'account_type', 'regular'),
(3, 'phone', '+3810641112222'),
(3, 'loglasi_telefon_proveren', '1'),
(3, 'listivo_favorites', 'a:3:{i:0;i:5001;i:1;i:5007;i:2;i:5002;}'),
(3, 'image', '6006'),
(5, 'phone', '12345'),
(7, 'phone', '064 999 8877'),
(80, 'phone', '0601234567'),
(117, 'account_type', 'business'),
(117, 'phone', '15 123456'),
(117, 'phone_country_code', '&#x1F1F7&#x1F1F8 +381'),
(117, 'loglasi_firma_logo', '6004'),
(117, 'loglasi_firma_proverena', '1'),
(117, 'loglasi_firma_delatnost', 'Prodaja polovnih automobila'),
(117, 'loglasi_firma_opis', 'Polovni automobili sa garancijom, otkup i zamena.'),
(117, 'loglasi_firma_adresa', 'Kneza Miloša 12'),
(117, 'loglasi_firma_mesto', 'Loznica'),
(117, 'loglasi_firma_radno_vreme', 'a:7:{i:1;s:7:"08–16";i:2;s:7:"08–16";i:3;s:7:"08–16";i:4;s:7:"08–16";i:5;s:7:"08–16";i:6;s:7:"09–13";i:7;s:9:"Zatvoreno";}'),
(117, 'loglasi_firma_sajt', 'https://autocentardrina.example.com'),
(117, 'loglasi_firma_facebook', 'https://www.facebook.com/autocentardrina'),
(117, 'loglasi_firma_instagram', 'https://www.instagram.com/autocentardrina'),
(117, 'loglasi_firma_telefon', '+381641234567');

INSERT INTO `wp_terms` (`term_id`, `name`, `slug`, `term_group`) VALUES
(101, 'Vozila', 'vozila', 0),
(102, 'Automobili', 'automobili', 0),
(103, 'Motocikli', 'motocikli', 0),
(104, 'Nekretnine', 'nekretnine', 0),
(105, 'Stanovi', 'stanovi', 0),
(106, 'Elektronika', 'elektronika', 0),
(107, 'Mobilni telefoni', 'mobilni-telefoni', 0),
(201, 'Loznica', 'loznica', 0),
(202, 'Banja Koviljača', 'banja-koviljaca', 0),
(203, 'Lešnica', 'lesnica', 0),
(204, 'Klupci', 'klupci', 0),
(435, 'Euro', 'euro', 0),
(436, 'Dinar', 'dinar', 0),
(301, 'Audi', 'audi', 0),
(302, 'Volkswagen', 'volkswagen', 0),
(311, 'Dizel', 'dizel', 0),
(312, 'Benzin', 'benzin', 0),
(321, 'Klima', 'klima', 0),
(322, 'Parking senzori', 'parking-senzori', 0),
(323, 'Navigacija', 'navigacija', 0),
(331, 'Prazna opcija', 'prazna-opcija', 0);

INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`, `term_id`, `taxonomy`, `description`, `parent`, `count`) VALUES
(1101, 101, 'listivo_14', 'Automobili, motori i delovi', 0, 0),
(1102, 102, 'listivo_14', '', 101, 0),
(1103, 103, 'listivo_14', '', 101, 0),
(1104, 104, 'listivo_14', '', 0, 0),
(1105, 105, 'listivo_14', '', 104, 0),
(1106, 106, 'listivo_14', '', 0, 0),
(1107, 107, 'listivo_14', '', 106, 0),
(1201, 201, 'listivo_14144', '', 0, 0),
(1202, 202, 'listivo_14144', '', 0, 0),
(1203, 203, 'listivo_14144', '', 0, 0),
(1204, 204, 'listivo_14144', '', 201, 0),
(1435, 435, 'listivo_434', '', 0, 0),
(1436, 436, 'listivo_434', '', 0, 0),
(1301, 301, 'listivo_1910', '', 0, 0),
(1302, 302, 'listivo_1910', '', 0, 0),
(1311, 311, 'listivo_1911', '', 0, 0),
(1312, 312, 'listivo_1911', '', 0, 0),
(1321, 321, 'listivo_945', '', 0, 0),
(1322, 322, 'listivo_945', '', 0, 0),
(1323, 323, 'listivo_945', '', 0, 0),
(1331, 331, 'listivo_946', '', 0, 0);

INSERT INTO `wp_termmeta` (`term_id`, `meta_key`, `meta_value`) VALUES
(101, 'order', '1'),
(104, 'order', '2'),
(106, 'order', '3'),
(102, 'order', '1'),
(103, 'order', '2');

INSERT INTO `wp_posts` (`post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`, `ID`) VALUES
(1, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Cena', '', 'publish', 'closed', 'closed', '', 'polje-130', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_custom_field', '', 0, 130),
(1, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Galerija', '', 'publish', 'closed', 'closed', '', 'polje-145', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_custom_field', '', 0, 145),
(1, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Marka automobila', '', 'publish', 'closed', 'closed', '', 'polje-1910', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_custom_field', '', 0, 1910),
(1, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Gorivo', '', 'publish', 'closed', 'closed', '', 'polje-1911', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_custom_field', '', 0, 1911),
(1, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Kilometraža', '', 'publish', 'closed', 'closed', '', 'polje-1912', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_custom_field', '', 0, 1912),
(1, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Oprema', '', 'publish', 'closed', 'closed', '', 'polje-945', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_custom_field', '', 0, 945),
(1, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Prazno polje', '', 'publish', 'closed', 'closed', '', 'polje-946', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_custom_field', '', 0, 946),
(1, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Kvadratura', '', 'publish', 'closed', 'closed', '', 'polje-4657', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_custom_field', '', 0, 4657),
(1, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Napomena', '', 'publish', 'closed', 'closed', '', 'polje-4661', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_custom_field', '', 0, 4661),
(2, '2026-09-05 14:00:00', '2026-09-05 12:00:00', '<p>Odlično stanje, redovno servisiran.</p>
<p>Prvi vlasnik &ndash; Loznica.<br>Moguća zamena.</p>', 'Audi A4 2.0 TDI &amp; navigacija', '', 'publish', 'closed', 'closed', '', 'audi-a4-2-0-tdi', '', '', '2026-09-06 09:00:00', '2026-09-06 07:00:00', '', 0, '', 0, 'listivo_listing', '', 0, 5001),
(117, '2026-09-10 10:00:00', '2026-09-10 08:00:00', 'Stan 54,5 m², grejanje na drva.', 'Dvosoban stan u Klupcima', '', 'publish', 'closed', 'closed', '', 'dvosoban-stan-klupci', '', '', '2026-09-10 10:00:00', '2026-09-10 08:00:00', '', 0, '', 0, 'listivo_listing', '', 0, 5002),
(3, '2026-09-01 10:00:00', '2026-09-01 08:00:00', 'Baterija 88%.', 'iPhone 13, 128 GB', '', 'pending', 'closed', 'closed', '', 'iphone-13-128-gb', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_listing', '', 0, 5003),
(2, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Nacrt bez sluga', '', 'draft', 'closed', 'closed', '', '', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_listing', '', 0, 5004),
(3, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Stari bicikl', '', 'expired', 'closed', 'closed', '', 'stari-bicikl', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_listing', '', 0, 5005),
(2, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Obrisan oglas', '', 'trash', 'closed', 'closed', '', 'obrisan-oglas__trashed', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_listing', '', 0, 5006),
(80, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Brzi krediti bez papira', '', 'publish', 'closed', 'closed', '', 'brzi-krediti-bez-papira', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_listing', '', 0, 5007),
(106, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Casino bonus', '', 'publish', 'closed', 'closed', '', 'casino-bonus-500', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_listing', '', 0, 5008),
(7, '2026-10-21 09:00:00', '2026-10-21 07:00:00', '', 'Volkswagen Golf 7', '', 'publish', 'closed', 'closed', '', 'volkswagen-golf-7', '', '', '2026-10-21 09:00:00', '2026-10-21 07:00:00', '', 0, '', 0, 'listivo_listing', '', 0, 5009),
(2, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Privatan oglas', '', 'private', 'closed', 'closed', '', 'privatan-oglas', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_listing', '', 0, 5010),
(2, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Automatski nacrt', '', 'auto-draft', 'closed', 'closed', '', '', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_listing', '', 0, 5011),
(999, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Oglas obrisanog korisnika', '', 'publish', 'closed', 'closed', '', 'oglas-obrisanog-korisnika', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_listing', '', 0, 5012),
(3, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Oglas bez kategorije', '', 'publish', 'closed', 'closed', '', 'oglas-bez-kategorije', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'listivo_listing', '', 0, 5013),
(2, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Audi A4 (revizija)', '', 'inherit', 'closed', 'closed', '', '5001-revision-v1', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 5001, '', 0, 'revision', '', 0, 5100),
(2, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'audi-1.jpg', '', 'inherit', 'closed', 'closed', '', 'slika-6001', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 5001, 'http://loglasirs.local/wp-content/uploads/2026/09/audi-1.jpg', 0, 'attachment', 'image/jpeg', 0, 6001),
(2, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'audi-2.jpg', '', 'inherit', 'closed', 'closed', '', 'slika-6002', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 5001, 'http://loglasirs.local/wp-content/uploads/2026/09/audi-2.jpg', 0, 'attachment', 'image/jpeg', 0, 6002),
(2, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'stan-scaled.jpg', '', 'inherit', 'closed', 'closed', '', 'slika-6003', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 5002, 'http://loglasirs.local/wp-content/uploads/2026/09/stan-scaled.jpg', 0, 'attachment', 'image/jpeg', 0, 6003),
(2, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'drina-logo.png', '', 'inherit', 'closed', 'closed', '', 'slika-6004', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, 'http://loglasirs.local/wp-content/uploads/2026/04/drina-logo.png', 0, 'attachment', 'image/jpeg', 0, 6004),
(2, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'kredit.jpg', '', 'inherit', 'closed', 'closed', '', 'slika-6005', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 5007, 'http://loglasirs.local/wp-content/uploads/2026/09/kredit.jpg', 0, 'attachment', 'image/jpeg', 0, 6005),
(2, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'jelena.jpg', '', 'inherit', 'closed', 'closed', '', 'slika-6006', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, 'http://loglasirs.local/wp-content/uploads/2026/06/jelena.jpg', 0, 'attachment', 'image/jpeg', 0, 6006),
(117, '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 'Order &ndash; 1. 10. 2026', '', 'wc-completed', 'closed', 'closed', '', '', '', '', '2026-09-01 10:00:00', '2026-09-01 08:00:00', '', 0, '', 0, 'shop_order', '', 0, 7001);

INSERT INTO `wp_postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES
(5001, 'listivo_130_listivo_435', '8.900'),
(5001, 'listivo_1912', '185000'),
(5001, 'featured', '1'),
(5001, 'featured_expire', '2030-01-15 12:00:00'),
(5001, 'listivo_145', 'a:2:{i:0;i:6001;i:1;i:6002;}'),
(5001, '_thumbnail_id', '6001'),
(5001, 'views', '120'),
(5001, 'loglasi_cena_eur', '8900.00'),
(5001, 'loglasi_moguca_zamena', 'Da'),
(5002, 'listivo_130_listivo_436', '12500'),
(5002, 'listivo_4657', '54,5'),
(5002, 'listivo_4661', 'Uknjižen, prvi sprat'),
(5002, 'listivo_145', '6003'),
(5002, 'views', '40'),
(5003, 'listivo_130_listivo_435', '0'),
(5003, 'loglasi_vrsta_oglasa', 'Prodajem'),
(5003, 'loglasi_stanje', 'kao-novo'),
(5003, 'loglasi_preuzimanje', 'a:2:{i:0;s:18:"Lično preuzimanje";i:1;s:7:"Dostava";}'),
(5005, 'listivo_130_listivo_436', '3000'),
(5006, '_wp_desired_post_slug', 'obrisan-oglas'),
(5007, 'listivo_145', 'a:1:{i:0;i:6005;}'),
(5009, 'listivo_130_listivo_435', '11 500'),
(5009, 'listivo_1912', '210000'),
(5009, 'featured', '0'),
(6001, '_wp_attached_file', '2026/09/audi-1.jpg'),
(6001, '_wp_attachment_metadata', 'a:4:{s:5:"width";i:1600;s:6:"height";i:1200;s:4:"file";s:18:"2026/09/audi-1.jpg";s:5:"sizes";a:1:{s:6:"medium";a:3:{s:4:"file";s:18:"audi-1-300x225.jpg";s:5:"width";i:300;s:6:"height";i:225;}}}'),
(6001, '_wp_attachment_image_alt', 'Audi A4 spreda'),
(6002, '_wp_attached_file', '2026/09/audi-2.jpg'),
(6002, '_wp_attachment_metadata', 'a:4:{s:5:"width";i:1600;s:6:"height";i:1200;s:4:"file";s:18:"2026/09/audi-2.jpg";s:5:"sizes";a:1:{s:6:"medium";a:3:{s:4:"file";s:18:"audi-2-300x225.jpg";s:5:"width";i:300;s:6:"height";i:225;}}}'),
(6003, '_wp_attached_file', '2026/09/stan-scaled.jpg'),
(6003, '_wp_attachment_metadata', 'a:5:{s:5:"width";i:2560;s:6:"height";i:1920;s:4:"file";s:23:"2026/09/stan-scaled.jpg";s:5:"sizes";a:1:{s:6:"medium";a:3:{s:4:"file";s:23:"stan-scaled-300x225.jpg";s:5:"width";i:300;s:6:"height";i:225;}}s:14:"original_image";s:8:"stan.jpg";}'),
(6004, '_wp_attached_file', '2026/04/drina-logo.png'),
(6004, '_wp_attachment_metadata', 'a:4:{s:5:"width";i:400;s:6:"height";i:400;s:4:"file";s:22:"2026/04/drina-logo.png";s:5:"sizes";a:1:{s:6:"medium";a:3:{s:4:"file";s:22:"drina-logo-300x225.jpg";s:5:"width";i:300;s:6:"height";i:225;}}}'),
(6005, '_wp_attached_file', '2026/09/kredit.jpg'),
(6005, '_wp_attachment_metadata', 'a:4:{s:5:"width";i:800;s:6:"height";i:600;s:4:"file";s:18:"2026/09/kredit.jpg";s:5:"sizes";a:1:{s:6:"medium";a:3:{s:4:"file";s:18:"kredit-300x225.jpg";s:5:"width";i:300;s:6:"height";i:225;}}}'),
(6006, '_wp_attached_file', '2026/06/jelena.jpg'),
(6006, '_wp_attachment_metadata', 'a:4:{s:5:"width";i:300;s:6:"height";i:300;s:4:"file";s:18:"2026/06/jelena.jpg";s:5:"sizes";a:1:{s:6:"medium";a:3:{s:4:"file";s:18:"jelena-300x225.jpg";s:5:"width";i:300;s:6:"height";i:225;}}}');

INSERT INTO `wp_term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) VALUES
(5001, 1101, 0),
(5001, 1102, 0),
(5001, 1201, 0),
(5001, 1301, 0),
(5001, 1311, 0),
(5001, 1321, 0),
(5001, 1323, 0),
(5002, 1104, 0),
(5002, 1105, 0),
(5002, 1201, 0),
(5002, 1204, 0),
(5003, 1107, 0),
(5003, 1202, 0),
(5004, 1103, 0),
(5005, 1101, 0),
(5005, 1203, 0),
(5006, 1102, 0),
(5007, 1106, 0),
(5007, 1201, 0),
(5008, 1106, 0),
(5009, 1102, 0),
(5009, 1201, 0),
(5009, 1302, 0),
(5009, 1312, 0),
(5010, 1106, 0),
(5012, 1106, 0);

INSERT INTO `wp_listivo_messages` (`id`, `user_from`, `user_to`, `listing_id`, `message`, `seen`, `created_at`) VALUES
(1, 3, 2, 5001, 'Dobar dan, da li je auto još dostupan?', 1, '2026-09-07 10:00:00'),
(2, 2, 3, 5001, 'Jeste, možete da dođete u subotu.', 1, '2026-09-07 10:30:00'),
(3, 3, 117, 5002, 'Da li je cena fiksna?', 0, '2026-09-12 18:00:00'),
(4, 109, 2, NULL, 'Kupite jeftine lekove!', 0, '2026-09-13 01:00:00'),
(5, 7, 117, NULL, 'Pozdrav, radite li subotom?', 0, '2026-10-21 12:00:00');
