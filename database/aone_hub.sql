-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 28, 2026 at 04:58 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aone_hub`
--

-- --------------------------------------------------------

--
-- Table structure for table `about_us`
--

CREATE TABLE `about_us` (
  `about_us_id` int NOT NULL,
  `about_us_title` varchar(100) NOT NULL,
  `about_us_title_text` varchar(100) NOT NULL,
  `about_us_images` varchar(255) NOT NULL,
  `about_us_who_we_are` varchar(255) NOT NULL,
  `about_us_experience` int NOT NULL,
  `about_us_team_members` int NOT NULL,
  `about_us_team_member_1` varchar(100) NOT NULL,
  `about_us_team_member_1_role` varchar(100) NOT NULL,
  `about_us_team_member_2` varchar(100) DEFAULT NULL,
  `about_us_team_member_2_role` varchar(100) DEFAULT NULL,
  `about_us_team_member_3` varchar(100) DEFAULT NULL,
  `about_us_team_member_3_role` varchar(100) DEFAULT NULL,
  `about_us_team_member_4` varchar(100) DEFAULT NULL,
  `about_us_team_member_4_role` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `about_us`
--

INSERT INTO `about_us` (`about_us_id`, `about_us_title`, `about_us_title_text`, `about_us_images`, `about_us_who_we_are`, `about_us_experience`, `about_us_team_members`, `about_us_team_member_1`, `about_us_team_member_1_role`, `about_us_team_member_2`, `about_us_team_member_2_role`, `about_us_team_member_3`, `about_us_team_member_3_role`, `about_us_team_member_4`, `about_us_team_member_4_role`) VALUES
(1, 'A-One Event Management', 'Designing unforgettable celebrations with creativity, elegance & perfection', 'event_1.jpg,event_2.jpg,event_3.jpg', 'Elite Event Management is a premium event planning company specializing in luxury weddings, corporate events, private parties and grand celebrations. We combine creativity, technology and flawless execution to deliver unforgettable experiences.', 10, 4, 'Vadhavana Ishit', 'Full Stack Developer', 'Kateshiya Priyanshu', 'Fron-end Developer', 'Makvana Hiren', 'Back-end Developer', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `book_id` int NOT NULL,
  `user_id` int NOT NULL,
  `event_id` int NOT NULL,
  `persons` int NOT NULL,
  `booking_status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'pending',
  `booked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`book_id`, `user_id`, `event_id`, `persons`, `booking_status`, `booked_at`, `updated_at`) VALUES
(23, 8, 64, 11, 'approved', '2026-02-03 07:26:15', '2026-02-16 13:37:05'),
(24, 8, 51, 11, 'pending', '2026-02-04 04:46:52', '2026-02-08 16:02:35'),
(25, 8, 8, 100, 'approved', '2026-02-04 05:07:15', '2026-02-08 16:02:35'),
(26, 8, 9, 175, 'approved', '2026-02-04 05:09:21', '2026-02-14 10:42:48'),
(29, 9, 86, 1, 'pending', '2026-02-06 04:06:49', '2026-02-08 16:02:35'),
(31, 9, 8, 50, 'pending', '2026-02-06 13:05:37', '2026-02-08 16:02:35'),
(32, 9, 9, 20, 'approved', '2026-02-06 14:07:03', '2026-02-08 16:02:35'),
(40, 9, 95, 20, 'approved', '2026-02-08 09:05:23', '2026-02-08 16:26:12'),
(41, 8, 10, 150, 'approved', '2026-02-08 09:51:55', '2026-02-08 16:32:33'),
(46, 9, 64, 1, 'pending', '2026-02-09 05:57:31', '2026-02-16 15:37:52'),
(48, 8, 64, 1, 'approved', '2026-02-14 10:34:35', '2026-02-16 15:37:49'),
(57, 8, 136, 100, 'approved', '2026-02-16 15:40:41', '2026-02-16 15:40:41'),
(61, 9, 143, 20, 'approved', '2026-02-17 15:39:15', '2026-02-17 15:39:15'),
(62, 8, 144, 5, 'approved', '2026-02-18 09:06:12', '2026-02-18 09:06:12'),
(64, 9, 51, 1, 'pending', '2026-03-03 05:45:21', '2026-03-03 05:45:21'),
(66, 8, 147, 10, 'approved', '2026-03-22 06:39:30', '2026-03-22 07:51:01'),
(69, 9, 150, 20, 'approved', '2026-03-24 09:15:19', '2026-03-24 09:15:19'),
(70, 9, 151, 5, 'approved', '2026-03-25 09:40:29', '2026-03-25 09:40:29'),
(71, 8, 151, 10, 'rejected', '2026-03-25 09:47:42', '2026-03-25 09:47:42');

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `category_id` int NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `category_seats` int NOT NULL,
  `category_price_per_hour` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`category_id`, `category_name`, `category_seats`, `category_price_per_hour`) VALUES
(1, 'Birthday', 500, 2000),
(2, 'Anniversary', 500, 2000),
(3, 'Engagement', 1000, 4000),
(4, 'Marriage', 1000, 4000);

-- --------------------------------------------------------

--
-- Table structure for table `contact`
--

CREATE TABLE `contact` (
  `contact_id` int NOT NULL,
  `contact_address` varchar(255) NOT NULL,
  `contact_phone` varchar(20) NOT NULL,
  `contact_email` varchar(100) NOT NULL,
  `working_hours` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `contact`
--

INSERT INTO `contact` (`contact_id`, `contact_address`, `contact_phone`, `contact_email`, `working_hours`) VALUES
(1, 'Trikon Baug, Rajkot, 360001', '1234567892', 'ishitvadhavana@gmail.com', 'Monday - Friday: 9:00 AM - 6:00 PM\nSaturday: 10:00 AM - 4:00 PM \nSunday: Closed');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `contact_message_id` int NOT NULL,
  `contact_message_full_name` varchar(100) NOT NULL,
  `contact_message_email` varchar(150) NOT NULL,
  `contact_message_subject` varchar(200) NOT NULL,
  `contact_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `is_read` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`contact_message_id`, `contact_message_full_name`, `contact_message_email`, `contact_message_subject`, `contact_message`, `is_read`, `created_at`) VALUES
(27, 'ISHITKUMAR VADHAVANA', 'ivandhava419@rku.ac.in', 'hii', 'help me', '0', '2026-02-17 21:17:56'),
(28, 'ISHITKUMAR VADHAVANA', 'ivandhava419@rku.ac.in', 'ertyuio', 'dfghjkl;', '1', '2026-02-17 22:13:20'),
(29, 'ISHITKUMAR VADHAVANA', 'ivandhava419@rku.ac.in', 'fghj', 'ertyuidfgh', '0', '2026-02-17 23:52:20'),
(30, 'ISHITKUMAR VADHAVANA', 'ivandhava419@rku.ac.in', '1qwsa', 'ewdsqwwqd', '1', '2026-02-17 23:52:26'),
(31, 'ISHITKUMAR VADHAVANA', 'ivandhava419@rku.ac.in', 'eq2w', '3wreds', '1', '2026-02-17 23:52:30'),
(32, 'ISHITKUMAR VADHAVANA', 'ivandhava419@rku.ac.in', 'wfg', '9o7kujyt', '0', '2026-02-17 23:52:38'),
(33, 'a1', 'q@mail.com', 'asdxs', 'aasdasdx', '0', '2026-02-18 14:31:30');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `coupon_id` int NOT NULL,
  `coupon_code` varchar(255) NOT NULL,
  `coupon_from_event_id` int NOT NULL,
  `coupon_applied_to_event_id` int DEFAULT NULL,
  `coupon_user_id` int NOT NULL,
  `coupon_discount` int NOT NULL,
  `coupon_valid_till` timestamp NOT NULL,
  `coupon_is_used` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  `coupon_created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`coupon_id`, `coupon_code`, `coupon_from_event_id`, `coupon_applied_to_event_id`, `coupon_user_id`, `coupon_discount`, `coupon_valid_till`, `coupon_is_used`, `coupon_created_at`) VALUES
(1, 'ABCD1234', 8, 9, 8, 10, '2026-03-31 05:42:19', '1', '2026-02-17 13:58:10'),
(3, 'ABCD1234', 9, NULL, 8, 10, '2028-01-31 05:42:19', '0', '2026-02-17 14:06:05');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int NOT NULL,
  `owner_id` int NOT NULL,
  `event_title` varchar(255) NOT NULL,
  `event_description` varchar(500) NOT NULL,
  `event_category` int NOT NULL,
  `event_date` date NOT NULL,
  `event_start_time` datetime NOT NULL,
  `event_end_time` datetime NOT NULL,
  `event_seats` int NOT NULL,
  `event_available_seats` int NOT NULL,
  `event_banner_image` varchar(255) NOT NULL,
  `event_gallery_images` varchar(255) DEFAULT NULL,
  `event_is_featured` enum('1','0') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '0',
  `event_registration_deadline` datetime NOT NULL,
  `event_price` int NOT NULL,
  `event_approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `event_status` enum('draft','published','ongoing','cancelled','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'draft',
  `event_paymeny_status` enum('pending','completed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `owner_id`, `event_title`, `event_description`, `event_category`, `event_date`, `event_start_time`, `event_end_time`, `event_seats`, `event_available_seats`, `event_banner_image`, `event_gallery_images`, `event_is_featured`, `event_registration_deadline`, `event_price`, `event_approval_status`, `event_status`, `event_paymeny_status`, `created_at`, `updated_at`) VALUES
(8, 8, 'Birthday', 'This is my son\'s birthday. You are invited to celebrate it.', 1, '2026-03-05', '2026-03-05 05:00:00', '2026-03-05 07:00:00', 200, 120, 'event_1.jpg', 'event_1.jpg', '1', '2026-02-28 00:00:00', 5000, 'approved', 'published', 'completed', '2026-02-01 12:06:43', '2026-03-23 09:17:43'),
(9, 8, 'this is title', 'This is event description', 1, '2025-03-22', '2025-03-22 02:00:00', '2025-03-22 21:00:00', 200, 2, 'event_2.jpg', 'event_2.jpg', '1', '2025-02-28 23:59:00', 5000, 'approved', 'completed', 'completed', '2026-02-01 12:06:55', '2026-03-23 09:09:43'),
(10, 8, 'Birthday', 'This is my son\'s birthday. You are invited to celebrate it.', 1, '2026-02-04', '2026-02-04 05:00:00', '2026-02-04 23:59:59', 200, 50, 'event_3.jpg', 'event_3.jpg', '1', '2026-01-31 00:00:00', 5000, 'approved', 'completed', 'completed', '2026-02-01 12:17:05', '2026-02-01 12:17:05'),
(51, 8, 'qweqwn', 'kjhnkm', 1, '2026-03-22', '2026-03-22 15:00:00', '2026-03-22 20:00:00', 200, 180, 'user.png', 'user.png', '1', '2026-03-05 00:00:00', 5000, 'approved', 'published', 'completed', '2026-02-03 03:57:16', '2026-02-08 06:55:42'),
(64, 8, 'this', 'ths is testing', 1, '2026-03-22', '2026-03-22 21:00:00', '2026-03-23 23:59:00', 500, 429, 'event_3.jpg', 'event_3.jpg', '1', '2026-03-20 23:59:59', 5000, 'approved', 'published', 'pending', '2026-02-03 05:37:13', '2026-02-03 05:37:13'),
(86, 8, 'anniversary event', 'this is anniversary event', 2, '2026-11-28', '2026-11-28 05:00:00', '2026-11-28 23:59:00', 100, 78, 'event_2.jpg', '', '1', '2026-02-27 23:59:59', 5000, 'rejected', 'cancelled', 'completed', '2026-02-03 07:26:15', '2026-02-16 12:54:56'),
(95, 9, 'hello', 'this is testing event', 2, '2028-02-22', '2028-02-22 00:00:00', '2028-02-22 23:59:00', 500, 480, 'logo.jpg', 'event_1.jpg,event_2.jpg,event_3.jpg', '0', '2027-02-22 23:59:00', 5000, 'pending', 'draft', 'completed', '2026-02-08 09:05:23', '2026-02-08 09:31:40'),
(136, 8, 'ishti', 'aksjxndwjkasmhzn', 2, '2027-03-22', '2027-03-22 22:22:00', '2027-03-22 23:00:00', 500, 400, 'banner_6992f1fcecc2a.jpg', '', '0', '2026-05-14 23:59:59', 5000, 'pending', 'draft', 'pending', '2026-02-16 10:31:24', '2026-02-16 10:31:24'),
(143, 9, 'dfgh', 'jdfgvhbn', 1, '2027-01-01', '2027-01-01 00:00:00', '2027-01-02 00:00:00', 500, 480, 'banner_69948ba3ac3c6.jpg', '', '0', '2026-02-19 23:59:59', 48000, 'approved', 'published', 'pending', '2026-02-17 15:39:15', '2026-03-25 09:42:53'),
(144, 8, 'asz', '123qwasd', 2, '2026-03-22', '2026-03-22 10:00:00', '2026-03-22 14:00:00', 100, 95, 'banner_6995810418c0e.jpg', '', '0', '2026-03-15 23:59:59', 8000, 'pending', 'draft', 'pending', '2026-02-18 09:06:12', '2026-02-18 09:06:12'),
(147, 8, 'ishit', 'jghgjgkjhg', 3, '2026-03-22', '2026-03-22 00:00:00', '2026-03-22 01:00:00', 1000, 990, 'banner_69bf8ea2eca3a.jpg', '', '0', '2026-03-21 23:59:59', 4000, 'approved', 'completed', 'completed', '2026-03-22 06:39:30', '2026-03-23 09:10:04'),
(150, 9, 'kdhsk', 'dfgyuhijokl', 1, '2026-05-31', '2026-05-31 00:00:00', '2026-05-31 23:59:00', 500, 480, 'banner_69c2562739298.jpg', '', '0', '2026-05-30 23:59:59', 48000, 'pending', 'draft', 'pending', '2026-03-24 09:15:19', '2026-03-24 10:14:42'),
(151, 9, '3445rtyuio', 'tgyiujokpl[;\']', 2, '2026-04-02', '2026-04-02 07:00:00', '2026-04-02 10:00:00', 200, 195, 'banner_69c3ad8d1b765.jpg', 'gallery_151_1774431846_9019.jpg,gallery_151_1774431846_1647.jpg', '0', '2026-04-01 23:59:59', 6000, 'approved', 'published', 'pending', '2026-03-25 09:40:29', '2026-03-25 09:46:10');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `token` varchar(64) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `otp_expires_at` datetime NOT NULL,
  `last_sent_at` datetime NOT NULL,
  `last_attempt` datetime DEFAULT NULL,
  `attempt_count` int UNSIGNED NOT NULL DEFAULT '0',
  `verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int NOT NULL,
  `user_id` int NOT NULL,
  `event_id` int NOT NULL,
  `review_rating` enum('1','2','3','4','5') NOT NULL,
  `review_review` varchar(255) NOT NULL,
  `reviewed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `user_id`, `event_id`, `review_rating`, `review_review`, `reviewed_at`) VALUES
(4, 9, 9, '4', 'I enjoyed a lot thank you very much', '2026-02-06 14:10:03'),
(5, 8, 9, '3', 'good', '2026-02-06 14:10:33'),
(6, 8, 10, '5', 'My event is awsome!!!', '2026-02-08 09:48:41');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int NOT NULL,
  `service_title` varchar(100) NOT NULL,
  `service_description` varchar(500) NOT NULL,
  `service_image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_title`, `service_description`, `service_image`) VALUES
(1, 'Event Planning', 'From concept to execution, our expert team helps you plan every detail for birthdays, weddings, conferences, & more.', 'service_20260217_131105_c04060dfcee9ade3.jpg'),
(2, 'Event Management', 'We handle logistics, registrations, and on-site coordination, so you can focus on enjoying your event.', 'event_management.png'),
(3, '24/7 Support', 'Our dedicated support team is always available to address queries and ensure your event success.', '24_by_7_Support.png'),
(7, 'abcd', 'this is required qwertyuio dfc tgfgf h g ', 'service_20260217_094040_75358bd64bb0e69c.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `user_phone_number` varchar(20) NOT NULL,
  `user_address` text NOT NULL,
  `profile_picture` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'user.png',
  `user_password` varchar(255) NOT NULL,
  `user_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `user_status` enum('active','inactive') NOT NULL DEFAULT 'inactive',
  `user_type` enum('user','owner','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'user',
  `registered_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `user_name`, `user_email`, `user_phone_number`, `user_address`, `profile_picture`, `user_password`, `user_token`, `user_status`, `user_type`, `registered_at`, `last_login`) VALUES
(8, 'Vadhavana Ishit', 'ivandhava419@rku.ac.in', '8460065647', 'Rajkot', 'user.png', '$2y$10$zCN7VZkT2l00Um8vdX9HJeQPuFBf.ty5eMsxAFZBthUMZ7MNJNKZS', NULL, 'active', 'owner', '2026-01-31 05:42:19', '2026-03-28 04:51:35'),
(9, 'Vadhavana Ishit', 'ishitvadhavana@gmail.com', '1234567890', 'Rajkot', 'user_9_1774432449.jpg', '$2y$10$onhLor6yEpEPqlhfWdpOPOZ9yB2fS1po8/oVqF1XlyKl46EnxkScu', NULL, 'active', 'admin', '2026-01-31 16:50:32', '2026-03-28 04:00:20'),
(10, 'Vadhavana Smit', 'vadhavanasmit@gmail.com', '8460065647', 'Veraval', 'user.png', '$2y$10$ZjRhp.ZmMiilex3UOosW9uYm5CdlqHsmcP359x9XznI6aZjWnAaRu', NULL, 'active', 'user', '2026-02-06 12:40:00', '2026-03-28 04:00:47'),
(30, 'Hiren Makvana', 'hmakvana358@rku.ac.in', '7891234560', 'Sardhar', 'user.png', '$2y$10$It7ty0FOdmTEBrWMSOf7HeIM4JY4YmsONLEsvHE3mQ4S70TwKX7PC', NULL, 'active', 'user', '2026-02-18 10:21:02', '2026-02-18 10:24:00');

-- --------------------------------------------------------

--
-- Table structure for table `why_aone_hub`
--

CREATE TABLE `why_aone_hub` (
  `why_id` int NOT NULL,
  `why_title` varchar(100) NOT NULL,
  `why_description` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `why_aone_hub`
--

INSERT INTO `why_aone_hub` (`why_id`, `why_title`, `why_description`) VALUES
(2, 'Innovative Approach', 'We blend tradition with new trends for truly memorable events.'),
(3, 'Transparent Pricing', 'No hidden costs—just honest, clear, and competitive rates.'),
(4, 'Custom Solutions', 'We listen to your needs and deliver personalized services, every time.'),
(5, 'Experienced Team', 'Our event professionals bring creativity and precision to every project.');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `about_us`
--
ALTER TABLE `about_us`
  ADD PRIMARY KEY (`about_us_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`book_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `contact`
--
ALTER TABLE `contact`
  ADD PRIMARY KEY (`contact_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`contact_message_id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`coupon_id`),
  ADD UNIQUE KEY `coupon_from_event_id` (`coupon_from_event_id`),
  ADD UNIQUE KEY `coupon_applied_to_event_id` (`coupon_applied_to_event_id`),
  ADD KEY `coupon_user_id` (`coupon_user_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `events_ibfk_1` (`owner_id`),
  ADD KEY `events_ibfk_2` (`event_category`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pr_user_id` (`user_id`),
  ADD UNIQUE KEY `uk_pr_token` (`token`),
  ADD KEY `idx_pr_expires` (`otp_expires_at`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `user_email` (`user_email`(100));

--
-- Indexes for table `why_aone_hub`
--
ALTER TABLE `why_aone_hub`
  ADD PRIMARY KEY (`why_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `about_us`
--
ALTER TABLE `about_us`
  MODIFY `about_us_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `book_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `category_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `contact`
--
ALTER TABLE `contact`
  MODIFY `contact_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `contact_message_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `coupon_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=152;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `why_aone_hub`
--
ALTER TABLE `why_aone_hub`
  MODIFY `why_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`);

--
-- Constraints for table `coupons`
--
ALTER TABLE `coupons`
  ADD CONSTRAINT `coupons_ibfk_1` FOREIGN KEY (`coupon_applied_to_event_id`) REFERENCES `events` (`event_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `coupons_ibfk_2` FOREIGN KEY (`coupon_from_event_id`) REFERENCES `events` (`event_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `coupons_ibfk_3` FOREIGN KEY (`coupon_user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `events_ibfk_2` FOREIGN KEY (`event_category`) REFERENCES `category` (`category_id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
