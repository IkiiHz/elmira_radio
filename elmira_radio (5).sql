-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 09 Jun 2025 pada 11.22
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `elmira_radio`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `ad_bookings`
--

CREATE TABLE `ad_bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_penyiar_id` int(11) DEFAULT NULL,
  `ad_title` varchar(255) NOT NULL,
  `ad_content` text NOT NULL,
  `desired_schedule` text DEFAULT NULL,
  `ad_file_listener` varchar(255) DEFAULT NULL,
  `payment_proof_file` varchar(255) DEFAULT NULL,
  `user_payment_notes` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending_admin_confirmation',
  `admin_id` int(11) DEFAULT NULL,
  `broadcaster_id` int(11) DEFAULT NULL,
  `ad_file_broadcaster` varchar(255) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `broadcaster_notes` text DEFAULT NULL,
  `user_rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `ad_bookings`
--

INSERT INTO `ad_bookings` (`id`, `user_id`, `assigned_penyiar_id`, `ad_title`, `ad_content`, `desired_schedule`, `ad_file_listener`, `payment_proof_file`, `user_payment_notes`, `status`, `admin_id`, `broadcaster_id`, `ad_file_broadcaster`, `admin_notes`, `broadcaster_notes`, `user_rejection_reason`, `created_at`, `updated_at`) VALUES
(1, 4, 3, 'kehilangan stnk', 'hilang stnk sim dan dompet di jalan nangka', 'senin jam 12', NULL, NULL, NULL, 'pending_broadcaster_creation', NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-27 14:34:36', '2025-05-28 12:35:19'),
(2, 4, 3, 'kehilangan stnk', 'hgvhjvhj', 'senin jam 12', NULL, NULL, NULL, 'confirmed_by_user', NULL, NULL, 'ad_bcast_2_1748431647.mp3', NULL, NULL, NULL, '2025-05-27 15:04:53', '2025-05-28 11:33:06'),
(3, 4, 3, 'duit korupsi', 'jadi gini bng', 'selasa jam 8 malam', NULL, NULL, NULL, 'confirmed_by_user', NULL, NULL, 'ad_bcast_3_1748436178.mp3', NULL, 'harus gini ya', NULL, '2025-05-28 12:39:23', '2025-05-28 12:43:44'),
(4, 4, 3, 'anak hilang', 'ajdasnhjdas', 'rabu subuh', NULL, NULL, NULL, 'confirmed_by_user', NULL, NULL, 'ad_bcast_4_1748436361.mp3', NULL, 'gini ya?', NULL, '2025-05-28 12:44:10', '2025-05-28 12:46:54'),
(5, 4, 3, 'anak hilang', '.', 'rabu subuh', 'ad_listener_4_1748594664.mp3', NULL, NULL, 'confirmed_by_user', NULL, NULL, 'ad_bcast_5_1748595156.mp3', NULL, 'oke ini hasilnya', NULL, '2025-05-30 08:44:24', '2025-05-30 08:55:15'),
(6, 4, 19, 'duit korupsi', 'jgujkgujk', 'paket 1', 'ad_listener_4_1748606860.mp3', 'proof_booking_6_1748606984.jpeg', NULL, 'pending_broadcaster_creation', NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-30 12:07:40', '2025-06-09 07:26:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `analytics`
--

CREATE TABLE `analytics` (
  `id` int(11) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `listeners_count` int(11) DEFAULT 0,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `live_chat`
--

CREATE TABLE `live_chat` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `program_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `live_chat`
--

INSERT INTO `live_chat` (`id`, `user_id`, `message`, `sent_at`, `program_id`) VALUES
(1, 4, 'kak', '2025-05-30 10:23:42', NULL),
(2, 4, 'kk', '2025-05-30 10:24:23', NULL),
(4, 4, 'fsdsf', '2025-05-30 10:25:07', NULL),
(5, 4, 'gas kak', '2025-05-30 10:34:30', NULL),
(6, 3, 'oke', '2025-05-30 10:34:54', NULL),
(7, 19, 'ada apa ya', '2025-06-09 07:27:24', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `program_id` int(11) DEFAULT NULL,
  `notification_time` datetime NOT NULL,
  `status` enum('unread','read') DEFAULT 'unread'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `program_id`, `notification_time`, `status`) VALUES
(7, 4, 3, '2025-06-02 09:56:00', 'unread'),
(8, 4, 1, '2025-05-31 06:45:00', 'unread');

-- --------------------------------------------------------

--
-- Struktur dari tabel `now_playing_stream`
--

CREATE TABLE `now_playing_stream` (
  `id` int(11) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `penyiar_id` int(11) NOT NULL,
  `song_title` varchar(255) NOT NULL,
  `artist_name` varchar(255) DEFAULT NULL,
  `album_art_url` varchar(255) DEFAULT NULL,
  `song_file_path` varchar(512) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `now_playing_stream`
--

INSERT INTO `now_playing_stream` (`id`, `program_id`, `penyiar_id`, `song_title`, `artist_name`, `album_art_url`, `song_file_path`, `is_active`, `updated_at`) VALUES
(1, NULL, 3, 'Dendi Nata   Abadi (Indo Version) Lyric Video Yt.Savetube.Me', 'dendi', '', '/elmira_radio/assets/music/Dendi Nata - Abadi (Indo Version) Lyric Video-yt.savetube.me.mp3', 0, '2025-05-23 05:44:13'),
(2, NULL, 3, 'tanpa cinta', 'yovie', '', '/elmira_radio/assets/music/Yovie Widianto, Tiara Andini - Tanpa Cinta (Official Music Video)-yt.savetube.me.mp3', 0, '2025-05-26 13:19:36'),
(3, NULL, 3, 'tanpa cinta', 'yovie', '', '/elmira_radio/assets/music/Epic Gaming Music No Copyright _ Free Royalty Free Track for Streams  _  No Copyright & Free Ascend-yt.savetube.me (1).mp3', 0, '2025-05-26 13:59:51'),
(4, NULL, 3, 'tanpa cinta', 'yovie', '', '/elmira_radio/assets/music/Yovie Widianto, Tiara Andini - Tanpa Cinta (Official Music Video)-yt.savetube.me.mp3', 0, '2025-05-27 14:51:54'),
(5, NULL, 3, 'tanpa cinta', 'yovie', '', '/elmira_radio/assets/music/Yovie Widianto, Tiara Andini - Tanpa Cinta (Official Music Video)-yt.savetube.me.mp3', 0, '2025-05-27 14:52:22'),
(6, NULL, 3, 'tanpa cinta', 'yovie', '', '/elmira_radio/assets/music/Yovie Widianto, Tiara Andini - Tanpa Cinta (Official Music Video)-yt.savetube.me.mp3', 0, '2025-05-27 14:52:56'),
(7, NULL, 3, 'tanpa cinta', 'yovie', '', '/elmira_radio/assets/music/Dendi Nata - Abadi (Indo Version) Lyric Video-yt.savetube.me.mp3', 0, '2025-05-27 14:53:27'),
(8, NULL, 3, 'tanpa cinta', 'yovie', '', '/elmira_radio/assets/music/Dendi Nata - Abadi (Indo Version) Lyric Video-yt.savetube.me.mp3', 0, '2025-05-27 14:53:48'),
(9, NULL, 3, 'tanpa cinta', 'yovie', '', '/elmira_radio/assets/music/Dendi Nata - Abadi (Indo Version) Lyric Video-yt.savetube.me.mp3', 0, '2025-05-27 15:02:01'),
(10, NULL, 3, 'tanpa cinta', 'yovie', '', '/elmira_radio/assets/music/Dendi Nata - Abadi (Indo Version) Lyric Video-yt.savetube.me.mp3', 0, '2025-05-27 15:05:26'),
(11, NULL, 3, 'tanpa cinta', 'yovie', 'https://open.spotify.com/intl-id/album/5FZVotW2jvhK31tvGSCKHz?si=hT3APfgNRKio4zDhwyuMdQ', '/elmira_radio/assets/music/Dendi Nata - Abadi (Indo Version) Lyric Video-yt.savetube.me.mp3', 0, '2025-05-27 15:06:15'),
(12, NULL, 3, 'tanpa cinta', 'yovie', 'https://is1-ssl.mzstatic.com/image/thumb/Music/v4/7b/15/90/7b159039-d530-f767-01e8-506cda85fa8d/886444546676.jpg/592x592bb.webp', '/elmira_radio/assets/music/Dendi Nata - Abadi (Indo Version) Lyric Video-yt.savetube.me.mp3', 0, '2025-05-27 15:06:35'),
(13, NULL, 3, 'tanpa cinta', 'yovie', 'https://is1-ssl.mzstatic.com/image/thumb/Music/v4/7b/15/90/7b159039-d530-f767-01e8-506cda85fa8d/886444546676.jpg/592x592bb.webp', '/elmira_radio/assets/music/Yovie Widianto, Tiara Andini - Tanpa Cinta (Official Music Video)-yt.savetube.me.mp3', 0, '2025-05-28 06:04:22'),
(14, NULL, 3, 'tanpa cinta', 'yovie', 'https://is1-ssl.mzstatic.com/image/thumb/Music/v4/7b/15/90/7b159039-d530-f767-01e8-506cda85fa8d/886444546676.jpg/592x592bb.webp', '/elmira_radio/assets/music/Yovie Widianto, Tiara Andini - Tanpa Cinta (Official Music Video)-yt.savetube.me.mp3', 0, '2025-05-28 06:04:39'),
(15, NULL, 3, 'tanpa cinta', 'yovie', 'https://is1-ssl.mzstatic.com/image/thumb/Music/v4/7b/15/90/7b159039-d530-f767-01e8-506cda85fa8d/886444546676.jpg/592x592bb.webp', '/elmira_radio/assets/music/Yovie Widianto, Tiara Andini - Tanpa Cinta (Official Music Video)-yt.savetube.me.mp3', 0, '2025-05-28 06:04:53'),
(16, NULL, 3, 'tanpa cinta', 'yovie', 'https://is1-ssl.mzstatic.com/image/thumb/Music/v4/7b/15/90/7b159039-d530-f767-01e8-506cda85fa8d/886444546676.jpg/592x592bb.webp', '/elmira_radio/assets/music/Yovie Widianto, Tiara Andini - Tanpa Cinta (Official Music Video)-yt.savetube.me.mp3', 0, '2025-05-28 06:04:54'),
(17, NULL, 3, 'tanpa cinta', 'yovie', 'https://is1-ssl.mzstatic.com/image/thumb/Music/v4/7b/15/90/7b159039-d530-f767-01e8-506cda85fa8d/886444546676.jpg/592x592bb.webp', '/elmira_radio/assets/music/Yovie Widianto, Tiara Andini - Tanpa Cinta (Official Music Video)-yt.savetube.me.mp3', 0, '2025-05-28 06:04:54'),
(18, NULL, 3, 'tanpa cinta', 'yovie', 'https://is1-ssl.mzstatic.com/image/thumb/Music/v4/7b/15/90/7b159039-d530-f767-01e8-506cda85fa8d/886444546676.jpg/592x592bb.webp', '/elmira_radio/assets/music/Yovie Widianto, Tiara Andini - Tanpa Cinta (Official Music Video)-yt.savetube.me.mp3', 0, '2025-05-28 06:04:54'),
(19, NULL, 3, 'tanpa cinta', 'yovie', 'https://is1-ssl.mzstatic.com/image/thumb/Music/v4/7b/15/90/7b159039-d530-f767-01e8-506cda85fa8d/886444546676.jpg/592x592bb.webp', '/elmira_radio/assets/music/Yovie Widianto, Tiara Andini - Tanpa Cinta (Official Music Video)-yt.savetube.me.mp3', 0, '2025-05-30 09:56:12'),
(20, NULL, 3, 'tanpa cinta', 'yovie', 'https://is1-ssl.mzstatic.com/image/thumb/Music/v4/7b/15/90/7b159039-d530-f767-01e8-506cda85fa8d/886444546676.jpg/592x592bb.webp', '/elmira_radio/assets/music/Yovie Widianto, Tiara Andini - Tanpa Cinta (Official Music Video)-yt.savetube.me.mp3', 0, '2025-05-30 09:57:33'),
(21, NULL, 3, 'tanpa cinta', 'yovie', 'https://th.bing.com/th/id/OIP.wMe6v83lSYf8QpBFnQp31QAAAA?rs=1&pid=ImgDetMain', '/elmira_radio/assets/music/Yovie Widianto, Tiara Andini - Tanpa Cinta (Official Music Video)-yt.savetube.me.mp3', 1, '2025-05-30 09:57:33');

-- --------------------------------------------------------

--
-- Struktur dari tabel `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `penyiar_id` int(11) DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `programs`
--

INSERT INTO `programs` (`id`, `title`, `description`, `penyiar_id`, `start_time`, `end_time`, `day_of_week`) VALUES
(1, 'karaoke', '', 3, '07:00:00', '10:00:00', 'Saturday'),
(3, 'brownies', '', 3, '10:11:00', '11:50:00', 'Monday'),
(5, 'Info Terkini', 'Berita ringan dan informasi menarik di sela-sela lagu.', 19, '10:00:00', '12:00:00', 'Tuesday'),
(6, 'Request Siang', 'Acara permintaan lagu dari pendengar.', 20, '12:00:00', '14:00:00', 'Wednesday'),
(7, 'Top Hits Indonesia', 'Memutarkan tangga lagu Indonesia terpopuler minggu ini.', 3, '14:00:00', '16:00:00', 'Friday'),
(8, 'Sore-Sore Seru', 'Obrolan santai menemani sore hari.', 19, '16:00:00', '18:00:00', 'Monday'),
(9, 'Indie Corner', 'Menghadirkan lagu-lagu dari skena musik independen.', 20, '19:00:00', '21:00:00', 'Wednesday'),
(10, 'Elmira Night Show', 'Program malam dengan bincang-bincang inspiratif dan lagu syahdu.', 3, '20:00:00', '22:00:00', 'Friday'),
(11, 'Weekend Love Songs', 'Kumpulan lagu-lagu cinta untuk menemani malam minggu.', 19, '20:00:00', '22:00:00', 'Saturday'),
(12, 'Semangat Pagi', 'Memulai hari dengan musik & informasi positif.', 20, '07:00:00', '10:00:00', 'Monday'),
(13, 'Elmira Classic Rock', 'Parade lagu rock klasik dari era 70an-90an.', 3, '20:00:00', '22:00:00', 'Monday'),
(14, 'Zona 90an', 'Nostalgia dengan hits terbaik dari era 90-an.', 3, '08:00:00', '10:00:00', 'Tuesday'),
(15, 'Pop Up', 'Lagu-lagu Pop terbaru yang sedang viral.', 19, '14:00:00', '16:00:00', 'Tuesday'),
(16, 'Dialog Interaktif', 'Bincang-bincang dengan narasumber mengenai isu terkini.', 20, '19:00:00', '21:00:00', 'Tuesday'),
(17, 'Inspirasi Pagi', 'Obrolan ringan dan lagu penyemangat di pagi hari.', 19, '09:00:00', '11:00:00', 'Wednesday'),
(18, 'Afternoon Delight', 'Musik santai untuk menemani waktu istirahat sore.', 3, '15:00:00', '17:00:00', 'Wednesday'),
(19, 'Kamis Manis', 'Kumpulan lagu-lagu romantis lintas generasi.', 19, '09:00:00', '11:00:00', 'Thursday'),
(20, 'Tembang Kenangan', 'Menghadirkan kembali lagu-lagu lawas yang tak terlupakan.', 20, '15:00:00', '17:00:00', 'Thursday'),
(21, 'Malam Jumat Show', 'Cerita misteri dan lagu-lagu bertema horor/mistis.', 3, '20:00:00', '22:00:00', 'Thursday'),
(22, 'Jumat Berkah', 'Konten rohani dan lagu-lagu religi.', 20, '08:00:00', '10:00:00', 'Friday'),
(23, 'Sabtu Ceria', 'Musik upbeat dan konten hiburan untuk akhir pekan.', 20, '10:00:00', '13:00:00', 'Saturday'),
(24, 'Request Weekend', 'Memutarkan lagu-lagu pilihan pendengar di akhir pekan.', 19, '14:00:00', '17:00:00', 'Saturday'),
(25, 'Sunday Morning Slow', 'Lagu-lagu easy listening untuk menemani pagi di hari Minggu.', 3, '08:00:00', '11:00:00', 'Sunday'),
(26, 'Chart Attack Global', 'Tangga lagu internasional terpopuler.', 19, '12:00:00', '15:00:00', 'Sunday'),
(27, 'Ruang Komunitas', 'Wadah untuk komunitas lokal berbagi cerita dan info.', 20, '16:00:00', '18:00:00', 'Sunday'),
(28, 'Santai Malam', 'Musik chill dan akustik untuk menutup akhir pekan.', 3, '19:00:00', '21:00:00', 'Sunday');

-- --------------------------------------------------------

--
-- Struktur dari tabel `song_requests`
--

CREATE TABLE `song_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `song_title` varchar(100) NOT NULL,
  `artist` varchar(100) NOT NULL,
  `message` text DEFAULT NULL,
  `request_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','played','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `song_requests`
--

INSERT INTO `song_requests` (`id`, `user_id`, `song_title`, `artist`, `message`, `request_time`, `status`) VALUES
(1, 4, 'Dendi Nata   Abadi (Indo Version) Lyric Video Yt.Savetube.Me', 'ssa', '', '2025-05-28 11:53:33', 'played'),
(2, 4, 'Dendi Nata   Abadi (Indo Version) Lyric Video Yt.Savetube.Me', 'ssa', '', '2025-05-28 12:24:06', 'played'),
(3, 4, 'tarot', 'hindia', 'harus lagu ini bang', '2025-05-28 12:38:17', 'played'),
(4, 4, 'tarot', 'hindia', 'semangat', '2025-05-30 10:29:45', 'played');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','penyiar','user','owner') NOT NULL,
  `profile_image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `profile_image_url`, `created_at`) VALUES
(1, 'admin', '$2a$12$0PgNuXqvGhEXgUtsAW5JKutu2jEMQU5FrG17dWrVjWCcNE740Rix.', 'admin@elmiaradio.com', 'admin', NULL, '2025-05-23 01:58:49'),
(2, 'Kyp', '$2y$10$sA0PFGAIdoZtH.3nz6vYRuU6THoHBercz0T1dF5vuW/XTNvcokulm', 'LatascaOfc@gmail.com', 'user', NULL, '2025-05-23 02:44:58'),
(3, 'aril', '$2y$10$OGFNwWfaiEs4GBFiXTF3re9VgUwtMOE17YG2FjjWRkb0F7niSPlcu', 'rifkynaufalat471@gmail.com', 'penyiar', 'profile_68399c6a6b1064.45259456.jpg', '2025-05-23 04:24:51'),
(4, 'kipli', '$2y$10$d8Bgh1CGjtCOWNG2uiTW.uzSA7ZOlVe6VV5Pq43gpPob2gUlbdmRi', 'rifkynaufal571@gmail.com', 'user', NULL, '2025-05-27 11:56:53'),
(18, 'owner', '$2y$10$hc/ueK/soJcpviCI/LwVUuY6edkdCGQMl/8TKkARveiLo02ksDAkK', 'black@mallu.cloud', 'owner', NULL, '2025-05-30 10:59:38'),
(19, 'ipul', '$2y$10$nlvwc1JdlddIbIJXLNA5pet7qh8vUo8IPQTSm7PEvfFgfB0TrMdIa', 'erik032@yanwi.co', 'penyiar', NULL, '2025-05-30 11:38:34'),
(20, 'kypli', '$2y$10$vmu43ciLt8N8/9zJ4Ud3xujSbSKO9E8toZTtfMxx6kX27h4.Ln9vK', 'nrifky751@gmail.com', 'penyiar', NULL, '2025-05-30 11:39:30');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `ad_bookings`
--
ALTER TABLE `ad_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `broadcaster_id` (`broadcaster_id`),
  ADD KEY `fk_ad_booking_assigned_penyiar` (`assigned_penyiar_id`);

--
-- Indeks untuk tabel `analytics`
--
ALTER TABLE `analytics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indeks untuk tabel `live_chat`
--
ALTER TABLE `live_chat`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indeks untuk tabel `now_playing_stream`
--
ALTER TABLE `now_playing_stream`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `penyiar_id` (`penyiar_id`);

--
-- Indeks untuk tabel `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `penyiar_id` (`penyiar_id`);

--
-- Indeks untuk tabel `song_requests`
--
ALTER TABLE `song_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `ad_bookings`
--
ALTER TABLE `ad_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `analytics`
--
ALTER TABLE `analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `live_chat`
--
ALTER TABLE `live_chat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `now_playing_stream`
--
ALTER TABLE `now_playing_stream`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT untuk tabel `song_requests`
--
ALTER TABLE `song_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `ad_bookings`
--
ALTER TABLE `ad_bookings`
  ADD CONSTRAINT `ad_bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ad_bookings_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ad_bookings_ibfk_3` FOREIGN KEY (`broadcaster_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ad_booking_assigned_penyiar` FOREIGN KEY (`assigned_penyiar_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `analytics`
--
ALTER TABLE `analytics`
  ADD CONSTRAINT `analytics_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`);

--
-- Ketidakleluasaan untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`);

--
-- Ketidakleluasaan untuk tabel `now_playing_stream`
--
ALTER TABLE `now_playing_stream`
  ADD CONSTRAINT `now_playing_stream_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `now_playing_stream_ibfk_2` FOREIGN KEY (`penyiar_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `programs_ibfk_1` FOREIGN KEY (`penyiar_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
