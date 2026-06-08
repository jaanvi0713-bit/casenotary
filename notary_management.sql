-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 03, 2026 at 12:49 PM
-- Server version: 8.4.7
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `notary_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_foreign` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `subject_type`, `subject_id`, `ip_address`, `created_at`) VALUES
(1, 1, 'case_created', 'Created new case CASE-2026-0001 for Sarah Mitchell.', 'case', 1, '127.0.0.1', '2026-05-27 06:21:51');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_id` bigint UNSIGNED DEFAULT NULL,
  `client_id` bigint UNSIGNED NOT NULL,
  `admin_id` bigint UNSIGNED DEFAULT NULL,
  `title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meeting_link` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `reminder_sent` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('requested','scheduled','confirmed','completed','cancelled','no_show') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `google_event_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outlook_event_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointments_case_id_foreign` (`case_id`),
  KEY `appointments_client_id_foreign` (`client_id`),
  KEY `appointments_admin_id_foreign` (`admin_id`),
  KEY `appointments_created_by_foreign` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `case_id`, `client_id`, `admin_id`, `title`, `description`, `location`, `meeting_link`, `starts_at`, `ends_at`, `reminder_sent`, `status`, `google_event_id`, `outlook_event_id`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 5, 7, 1, 'Doc Signing', 'Test Case', 'Zoom', 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=Doc+Signing&dates=20260531T205700%2F20260602T205800&details=Client%3A+Louis+Macwell%0AEmail%3A+Louismac%40gmail.com%0ATest+Case&location=Zoom&add=Louismac%40gmail.com', '2026-05-31 20:57:00', '2026-06-02 20:58:00', 0, 'scheduled', NULL, NULL, NULL, '2026-05-31 16:58:22', '2026-05-31 18:55:20'),
(5, 6, 8, 1, 'doc', NULL, 'Zoom', 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=doc&dates=20260610T120000%2F20260610T150000&details=Client%3A+Louis+Macwell%0AEmail%3A+Louismac%40gmail.com&location=Zoom&add=Louismac%40gmail.com', '2026-06-10 12:00:00', '2026-06-10 15:00:00', 0, 'scheduled', NULL, NULL, NULL, '2026-06-01 12:56:00', '2026-06-02 07:10:19');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `event` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_id` bigint UNSIGNED NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `event`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'login', 'user', 1, NULL, NULL, NULL, '', NULL),
(2, 1, 'login', 'user', 1, NULL, NULL, NULL, '', NULL),
(3, 1, 'login', 'user', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL),
(4, 1, 'logout', 'user', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL),
(5, 1, 'login', 'user', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL),
(6, 1, 'login', 'user', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL),
(7, 1, 'logout', 'user', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL),
(8, 1, 'login', 'user', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL),
(9, 1, 'logout', 'user', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL),
(10, 1, 'login', 'user', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cases`
--

DROP TABLE IF EXISTS `cases`;
CREATE TABLE IF NOT EXISTS `cases` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int UNSIGNED NOT NULL,
  `case_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `client_instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `service_type` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `services` json DEFAULT NULL,
  `client_id` bigint UNSIGNED NOT NULL,
  `assigned_admin_id` bigint UNSIGNED DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `deadline` date DEFAULT NULL,
  `status` enum('pending','in_progress','waiting_for_client','completed','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `progress` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cases_company_number` (`company_id`,`case_number`),
  KEY `cases_client_id_foreign` (`client_id`),
  KEY `cases_assigned_admin_id_foreign` (`assigned_admin_id`),
  KEY `cases_created_by_foreign` (`created_by`),
  KEY `idx_cases_company` (`company_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cases`
--

INSERT INTO `cases` (`id`, `company_id`, `case_number`, `title`, `description`, `client_instructions`, `service_type`, `service_fee`, `services`, `client_id`, `assigned_admin_id`, `priority`, `deadline`, `status`, `progress`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'CASE-2026-0001', 'Document Notarization – Sarah Mitchell', 'Standard notary services and document authentication.', NULL, 'Notarization', 250.00, NULL, 1, 1, 'medium', '2026-06-10', 'pending', 10, 1, '2026-05-27 08:21:51', '2026-05-27 08:21:51'),
(2, 1, 'CASE-2026-0002', 'Document Notarization – James Rodriguez', 'Standard notary services and document authentication.', NULL, 'Notarization', 325.00, NULL, 2, 1, 'high', '2026-06-11', 'in_progress', 45, 1, '2026-05-27 08:21:51', '2026-05-27 08:21:51'),
(3, 1, 'CASE-2026-0003', 'Document Notarization – Emily Chen', 'Standard notary services and document authentication.', NULL, 'Notarization', 400.00, NULL, 3, 1, 'low', '2026-06-12', 'waiting_for_client', 70, 1, '2026-05-27 08:21:51', '2026-05-27 08:21:51'),
(4, 1, 'CASE-2026-0004', 'Smith Property Transfer', NULL, NULL, 'Property Transfer', 30000.00, NULL, 1, 1, 'high', '2026-05-31', 'in_progress', 0, NULL, '2026-05-28 09:34:59', '2026-05-28 09:34:59'),
(5, 1, 'CASE-2026-0005', 'Prop Transfer', 'Testing', 'Docs', 'Prop Transfer', 20000.00, NULL, 7, 1, 'medium', '2026-06-02', 'in_progress', 0, NULL, '2026-05-31 18:37:48', '2026-05-31 18:37:48'),
(6, 1, 'CASE-2026-0006', 'Prop Transfer', NULL, 'docs', 'signing', 1000.00, NULL, 8, 1, 'medium', '2026-06-07', 'waiting_for_client', 0, NULL, '2026-06-01 10:41:55', '2026-06-01 10:41:55'),
(7, 1, 'CASE-2026-0007', 'PROP', NULL, 'ID', 'Disbursement, Notarisation', 5700.00, '{\"vat\": [{\"net\": 3000, \"type\": \"Notarisation\"}], \"totals\": {\"vat_amount\": 600, \"grand_total\": 5700, \"non_vat_subtotal\": 2100, \"vat_net_subtotal\": 3000, \"vat_gross_subtotal\": 3600, \"non_vat_rate_amount\": 100, \"non_vat_net_subtotal\": 2000}, \"non_vat\": [{\"net\": 2000, \"type\": \"Disbursement\"}], \"version\": 2, \"vat_rate\": 20, \"non_vat_rate\": 5}', 8, 1, 'medium', '2026-06-05', 'pending', 0, NULL, '2026-06-02 18:44:28', '2026-06-02 18:44:33'),
(9, 2, 'CASE-2026-0001', 'Tranfer', NULL, NULL, 'Disbursement, Notarisation', 7050.00, '{\"vat\": [{\"net\": 5000, \"type\": \"Notarisation\"}], \"totals\": {\"vat_amount\": 1000, \"grand_total\": 7050, \"non_vat_subtotal\": 1050, \"vat_net_subtotal\": 5000, \"vat_gross_subtotal\": 6000, \"non_vat_rate_amount\": 50, \"non_vat_net_subtotal\": 1000}, \"non_vat\": [{\"net\": 1000, \"type\": \"Disbursement\"}], \"version\": 2, \"vat_rate\": 20, \"non_vat_rate\": 5}', 9, 1, 'medium', '2026-06-27', 'in_progress', 0, NULL, '2026-06-03 10:34:38', '2026-06-03 10:34:38');

-- --------------------------------------------------------

--
-- Table structure for table `case_notes`
--

DROP TABLE IF EXISTS `case_notes`;
CREATE TABLE IF NOT EXISTS `case_notes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `note` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_case_notes_case` (`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_conversations`
--

DROP TABLE IF EXISTS `chatbot_conversations`;
CREATE TABLE IF NOT EXISTS `chatbot_conversations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `company_id` int UNSIGNED DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'New chat',
  `messages` json NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chatbot_conversations_user` (`user_id`),
  KEY `idx_chatbot_conversations_updated` (`updated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chatbot_conversations`
--

INSERT INTO `chatbot_conversations` (`id`, `user_id`, `company_id`, `title`, `messages`, `created_at`, `updated_at`) VALUES
(4, 1, 1, 'Morning briefing', '[{\"text\": \"Morning briefing\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Good morning! Here\'s your briefing for Notary Management Pro:**\\n\\n• **Clients:** 4\\n• **Active cases:** 6\\n• **Pending invoices:** 1\\n• **Upcoming appointments:** 1\\n• **Total revenue:** £ 1,595.00\\n• **Next appointment:** doc — Jun 10, 2026 12:00 PM\\n• **Overdue invoices:** 1 — ask “list overdue invoices” for details.\\n\\n**Recent case activity:**\\n• **CASE-2026-0006** — Prop Transfer (*Waiting For Client*)\\n• **CASE-2026-0005** — Prop Transfer (*In Progress*)\\n• **CASE-2026-0004** — Smith Property Transfer (*In Progress*)\\n\\n_Ask about a client name, active cases, payments, or appointments anytime._\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"Give me a dashboard summary\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Dashboard overview:**\\n\\n• Clients: 4\\n• Active cases: 6\\n• Pending invoices: 1\\n• Upcoming appointments: 1\\n• Total revenue: £ 1,595.00\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"How many clients do we have?\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"You currently have **4 registered clients** in the system.\\n\\n[Open clients](http://localhost/casenotary/admin/pages/clients.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"List active cases\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Active cases:** (5)\\n\\n• **CASE-2026-0006** — Prop Transfer (*Waiting For Client*) — Louis Macwell\\n  [Open case](http://localhost/casenotary/admin/pages/case-view.php?id=6)\\n• **CASE-2026-0004** — Smith Property Transfer (*In Progress*) — Sarah Mitchell\\n  [Open case](http://localhost/casenotary/admin/pages/case-view.php?id=4)\\n• **CASE-2026-0001** — Document Notarization – Sarah Mitchell (*Pending*) — Sarah Mitchell\\n  [Open case](http://localhost/casenotary/admin/pages/case-view.php?id=1)\\n• **CASE-2026-0002** — Document Notarization – James Rodriguez (*In Progress*) — James Rodriguez\\n  [Open case](http://localhost/casenotary/admin/pages/case-view.php?id=2)\\n• **CASE-2026-0003** — Document Notarization – Emily Chen (*Waiting For Client*) — Emily Chen\\n  [Open case](http://localhost/casenotary/admin/pages/case-view.php?id=3)\\n\\n[Open cases](http://localhost/casenotary/admin/pages/cases.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"What is our total revenue?\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Revenue summary:**\\n\\n• **Total revenue:** £ 1,595.00\\n• **This month:** £ 1,270.00\\n• **Paid invoices:** 3\\n\\n[Open payments](http://localhost/casenotary/admin/pages/payments.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"List upcoming appointments\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Upcoming appointments:**\\n\\n• **doc** — Jun 10, 2026 12:00 PM (*Scheduled*)\\n  Client: **Louis Macwell** · **1** case(s) · Louismac@gmail.com · +23052512345\\n  [Open in calendar](http://localhost/casenotary/admin/pages/appointments.php)\\n\\n[View all appointments](http://localhost/casenotary/admin/pages/appointments.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"List recent payments\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Payments for that period:**\\n\\n• £ 1,020.00 from **Louis Macwell** — INV-2026-0004 — Jun 01, 2026 7:18 AM\\n• £ 250.00 from **Sarah Mitchell** — INV-2026-0001 — Jun 01, 2026 6:51 AM\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"List overdue invoices\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Overdue invoices:**\\n\\n• **INV-2026-0003** — £ 400.00 — Emily Chen — due Jun 06, 2026\\n\\n[Open payments](http://localhost/casenotary/admin/pages/payments.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"Show unread notifications\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Unread notifications:** (18 unread)\\n\\n• **Appointment cancelled** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment cancelled** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment scheduled** *(unread)* — [Open](../pages/appointments.php)\\n• **Payment received** *(unread)* — [Open](../pages/case-view.php?id=6#invoice-payments)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n• **Payment received** *(unread)* — [Open](../pages/case-view.php?id=1#invoice-payments)\\n• **Invoice generated** *(unread)* — [Open](../pages/case-view.php?id=6#invoice-payments)\\n\\n[View all notifications](http://localhost/casenotary/admin/pages/notifications.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"how to create an appointment\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"12 % of revenue\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Alternative (concise):**\\n\\n**12% of total revenue** (£ 1,595.00) = **£ 191.40**\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"Morning briefing\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Good morning! Here\'s your briefing for Your Company:**\\n\\n• **Clients:** 4\\n• **Active cases:** 7\\n• **Pending invoices:** 4\\n• **Upcoming appointments:** 1\\n• **Total revenue:** £ 1,595.00\\n• **Next appointment:** doc — Jun 10, 2026 12:00 PM\\n• **Overdue invoices:** 1 — ask “list overdue invoices” for details.\\n\\n**Recent case activity:**\\n• **CASE-2026-0007** — PROP (*Pending*)\\n• **CASE-2026-0006** — Prop Transfer (*Waiting For Client*)\\n• **CASE-2026-0005** — Prop Transfer (*In Progress*)\\n\\n_Ask about a client name, active cases, payments, or appointments anytime._\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"Give me a dashboard summary\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Dashboard overview:**\\n\\n• Clients: 4\\n• Active cases: 7\\n• Pending invoices: 4\\n• Upcoming appointments: 1\\n• Total revenue: £ 1,595.00\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"How many clients do we have?\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"You currently have **4 registered clients** in the system.\\n\\n[Open clients](http://localhost/casenotary/admin/pages/clients.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"List active cases\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Active cases:** (6)\\n\\n• **CASE-2026-0007** — PROP (*Pending*) — Louis Macwell\\n  [Open case](http://localhost/casenotary/admin/pages/case-view.php?id=7)\\n• **CASE-2026-0006** — Prop Transfer (*Waiting For Client*) — Louis Macwell\\n  [Open case](http://localhost/casenotary/admin/pages/case-view.php?id=6)\\n• **CASE-2026-0004** — Smith Property Transfer (*In Progress*) — Sarah Mitchell\\n  [Open case](http://localhost/casenotary/admin/pages/case-view.php?id=4)\\n• **CASE-2026-0001** — Document Notarization – Sarah Mitchell (*Pending*) — Sarah Mitchell\\n  [Open case](http://localhost/casenotary/admin/pages/case-view.php?id=1)\\n• **CASE-2026-0002** — Document Notarization – James Rodriguez (*In Progress*) — James Rodriguez\\n  [Open case](http://localhost/casenotary/admin/pages/case-view.php?id=2)\\n• **CASE-2026-0003** — Document Notarization – Emily Chen (*Waiting For Client*) — Emily Chen\\n  [Open case](http://localhost/casenotary/admin/pages/case-view.php?id=3)\\n\\n[Open cases](http://localhost/casenotary/admin/pages/cases.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"What is our total revenue?\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Revenue summary:**\\n\\n• **Total revenue:** £ 1,595.00\\n• **This month:** £ 1,270.00\\n• **Paid invoices:** 3\\n\\n[Open payments](http://localhost/casenotary/admin/pages/payments.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"List upcoming appointments\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Upcoming appointments:**\\n\\n• **doc** — Jun 10, 2026 12:00 PM (*Scheduled*)\\n  Client: **Louis Macwell** · **2** case(s) · Louismac@gmail.com · +23052512345\\n  [Open in calendar](http://localhost/casenotary/admin/pages/appointments.php)\\n\\n[View all appointments](http://localhost/casenotary/admin/pages/appointments.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"List recent payments\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Payments for that period:**\\n\\n• £ 1,020.00 from **Louis Macwell** — INV-2026-0004 — Jun 01, 2026 7:18 AM\\n• £ 250.00 from **Sarah Mitchell** — INV-2026-0001 — Jun 01, 2026 6:51 AM\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"List overdue invoices\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Overdue invoices:**\\n\\n• **INV-2026-0003** — £ 400.00 — Emily Chen — due Jun 06, 2026\\n\\n[Open payments](http://localhost/casenotary/admin/pages/payments.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"Show unread notifications\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Unread notifications:** (20 unread)\\n\\n• **Quotation created** *(unread)* — [Open](../pages/case-view.php?id=7#quotations)\\n• **New case created** *(unread)* — [Open](../pages/case-view.php?id=7)\\n• **Appointment cancelled** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment cancelled** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment scheduled** *(unread)* — [Open](../pages/appointments.php)\\n• **Payment received** *(unread)* — [Open](../pages/case-view.php?id=6#invoice-payments)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n\\n[View all notifications](http://localhost/casenotary/admin/pages/notifications.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"Show unread notifications\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Unread notifications:** (20 unread)\\n\\n• **Quotation created** *(unread)* — [Open](../pages/case-view.php?id=7#quotations)\\n• **New case created** *(unread)* — [Open](../pages/case-view.php?id=7)\\n• **Appointment cancelled** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment cancelled** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment scheduled** *(unread)* — [Open](../pages/appointments.php)\\n• **Payment received** *(unread)* — [Open](../pages/case-view.php?id=6#invoice-payments)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n\\n[View all notifications](http://localhost/casenotary/admin/pages/notifications.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"Show unread notifications\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"**Unread notifications:** (20 unread)\\n\\n• **Quotation created** *(unread)* — [Open](../pages/case-view.php?id=7#quotations)\\n• **New case created** *(unread)* — [Open](../pages/case-view.php?id=7)\\n• **Appointment cancelled** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment cancelled** *(unread)* — [Open](../pages/appointments.php)\\n• **Appointment scheduled** *(unread)* — [Open](../pages/appointments.php)\\n• **Payment received** *(unread)* — [Open](../pages/case-view.php?id=6#invoice-payments)\\n• **Appointment updated** *(unread)* — [Open](../pages/appointments.php)\\n\\n[View all notifications](http://localhost/casenotary/admin/pages/notifications.php)\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"Draft a client letter about an upcoming appointment\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"You have **1 upcoming appointments**.\\n\\nWould you like me to **list them all** with client details and case counts, or look up a **specific client** (e.g. *“for Emily Chen”* or just *“Emily”*)?\", \"type\": \"bot\", \"attachments\": \"\"}, {\"text\": \"What is an apostille?\", \"type\": \"user\", \"attachments\": \"\"}, {\"text\": \"An **apostille** certifies a document for use in countries that are party to the Hague Apostille Convention. Notaries often notarize first; apostilles are usually issued by a government office (e.g. FCDO in the UK). Ask clients where the document will be used and allow extra time for legalization.\", \"type\": \"bot\", \"attachments\": \"\"}]', '2026-06-02 22:17:04', '2026-06-03 13:04:34');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
CREATE TABLE IF NOT EXISTS `clients` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USA',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `login_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','inactive','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clients_email_unique` (`email`),
  UNIQUE KEY `uq_clients_company_email` (`company_id`,`email`),
  KEY `clients_user_id_foreign` (`user_id`),
  KEY `clients_created_by_foreign` (`created_by`),
  KEY `idx_clients_company` (`company_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `company_id`, `user_id`, `first_name`, `last_name`, `email`, `phone`, `company_name`, `address`, `city`, `state`, `zip`, `country`, `notes`, `login_enabled`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Sarah', 'Mitchell', 'sarah.mitchell@email.com', '+1 (555) 010-0000', 'Mitchell Legal LLC', NULL, NULL, NULL, NULL, 'USA', NULL, 1, 'active', 1, '2026-05-27 08:21:51', '2026-05-27 08:21:51'),
(2, 1, NULL, 'James', 'Rodriguez', 'james.r@email.com', '+1 (555) 010-0000', NULL, NULL, NULL, NULL, NULL, 'USA', NULL, 1, 'active', 1, '2026-05-27 08:21:51', '2026-05-27 08:21:51'),
(3, 1, NULL, 'Emily', 'Chen', 'emily.chen@email.com', '+1 (555) 010-0000', 'Chen & Associates', NULL, NULL, NULL, NULL, 'USA', NULL, 1, 'active', 1, '2026-05-27 08:21:51', '2026-05-27 08:21:51'),
(8, 1, 6, 'Louis', 'Macwell', 'Louismac@gmail.com', '+23052512345', 'TEST', 'royal rd', 'moka', NULL, '58692', 'mauritius', NULL, 1, 'active', NULL, '2026-06-01 10:28:35', '2026-06-01 10:49:20'),
(9, 2, 7, 'Sammy', 'well', 'wellmy@gmail.com', '+4925456958152', 'TEST', 'Louis Street', 'Test', 'Test', '2566125', 'Germany', NULL, 1, 'active', NULL, '2026-06-03 08:57:05', '2026-06-03 08:57:05');

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
CREATE TABLE IF NOT EXISTS `companies` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(63) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_companies_slug` (`slug`),
  KEY `idx_companies_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `slug`, `name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'my-notary-firm', 'FunTurtle Company', 'active', '2026-06-03 10:57:30', '2026-06-03 12:48:05'),
(2, 'nexa-digital', 'Nexa Digital', 'active', '2026-06-03 12:49:02', '2026-06-03 15:01:32');

-- --------------------------------------------------------

--
-- Table structure for table `company_roles`
--

DROP TABLE IF EXISTS `company_roles`;
CREATE TABLE IF NOT EXISTS `company_roles` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int UNSIGNED NOT NULL,
  `slug` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_builtin` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_role_slug` (`company_id`,`slug`),
  KEY `idx_company_roles_company` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company_roles`
--

INSERT INTO `company_roles` (`id`, `company_id`, `slug`, `label`, `description`, `is_builtin`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin', 'Administrator', 'Full access to company settings, users, payments, and all modules.', 1, 10, 'active', '2026-06-03 16:06:18', '2026-06-03 16:06:18'),
(2, 1, 'manager', 'Manager', 'Clients, cases, payments, appointments, notifications, and AI — tune access on Role access.', 1, 20, 'active', '2026-06-03 16:06:18', '2026-06-03 16:06:18'),
(3, 1, 'staff', 'Staff', 'Usually assigned cases only — tune access on Role access.', 1, 30, 'active', '2026-06-03 16:06:18', '2026-06-03 16:06:18'),
(4, 1, 'viewer', 'Viewer', 'View-focused — enable read-only on Role access to block edits.', 1, 40, 'active', '2026-06-03 16:06:18', '2026-06-03 16:06:18'),
(5, 2, 'admin', 'Administrator', 'Full access to company settings, users, payments, and all modules.', 1, 10, 'active', '2026-06-03 16:06:18', '2026-06-03 16:06:18'),
(6, 2, 'manager', 'Manager', 'Clients, cases, payments, appointments, notifications, and AI — tune access on Role access.', 1, 20, 'active', '2026-06-03 16:06:18', '2026-06-03 16:06:18'),
(7, 2, 'staff', 'Staff', 'Usually assigned cases only — tune access on Role access.', 1, 30, 'active', '2026-06-03 16:06:18', '2026-06-03 16:06:18'),
(8, 2, 'viewer', 'Viewer', 'View-focused — enable read-only on Role access to block edits.', 1, 40, 'active', '2026-06-03 16:06:18', '2026-06-03 16:06:18');

-- --------------------------------------------------------

--
-- Table structure for table `company_role_permissions`
--

DROP TABLE IF EXISTS `company_role_permissions`;
CREATE TABLE IF NOT EXISTS `company_role_permissions` (
  `company_id` int UNSIGNED NOT NULL,
  `role` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permissions` json NOT NULL,
  `assigned_cases_only` tinyint(1) NOT NULL DEFAULT '0',
  `read_only` tinyint(1) NOT NULL DEFAULT '0',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`,`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company_role_permissions`
--

INSERT INTO `company_role_permissions` (`company_id`, `role`, `permissions`, `assigned_cases_only`, `read_only`, `updated_at`) VALUES
(1, 'admin', '[\"dashboard\", \"users\", \"clients\", \"cases\", \"payments\", \"appointments\", \"notifications\", \"chatbot\", \"profile\"]', 0, 0, '2026-06-03 16:31:42'),
(1, 'manager', '[\"dashboard\", \"clients\", \"cases\", \"payments\", \"appointments\", \"notifications\", \"chatbot\"]', 0, 0, '2026-06-03 16:31:42'),
(1, 'staff', '[\"dashboard\", \"clients\", \"cases\", \"appointments\", \"notifications\", \"chatbot\", \"profile\"]', 1, 0, '2026-06-03 16:31:42'),
(1, 'viewer', '[\"dashboard\", \"clients\", \"cases\", \"payments\", \"appointments\", \"profile\"]', 0, 1, '2026-06-03 16:31:42'),
(2, 'admin', '[\"dashboard\", \"users\", \"clients\", \"cases\", \"payments\", \"appointments\", \"notifications\", \"chatbot\", \"settings\", \"profile\"]', 0, 0, '2026-06-03 15:46:21'),
(2, 'manager', '[\"dashboard\", \"clients\", \"cases\", \"payments\", \"appointments\", \"notifications\", \"chatbot\", \"profile\"]', 0, 0, '2026-06-03 15:46:21'),
(2, 'staff', '[\"dashboard\", \"clients\", \"cases\", \"appointments\", \"notifications\", \"chatbot\", \"profile\"]', 1, 0, '2026-06-03 15:46:21'),
(2, 'viewer', '[\"dashboard\", \"clients\", \"cases\", \"payments\", \"appointments\", \"notifications\", \"profile\"]', 0, 1, '2026-06-03 15:46:21');

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

DROP TABLE IF EXISTS `company_settings`;
CREATE TABLE IF NOT EXISTS `company_settings` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int UNSIGNED NOT NULL,
  `company_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Notary Management',
  `logo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `favicon` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_path` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#3aafa9',
  `secondary_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#00182c',
  `dark_accent` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#000000',
  `accent_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#000000',
  `font_family` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Montserrat',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `office_email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_hours` text COLLATE utf8mb4_unicode_ci,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_website` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registration_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_vat_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_payable_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_number` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_sort_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_iban` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_bic` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_invoice_payment_terms` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facebook_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instagram_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linkedin_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_host` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_port` int UNSIGNED NOT NULL DEFAULT '587',
  `smtp_username` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_password` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_encryption` enum('tls','ssl','none') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tls',
  `stripe_public_key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_secret_key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_calendar_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `outlook_calendar_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `google_access_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `google_refresh_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `google_token_expires` int UNSIGNED DEFAULT NULL,
  `openai_api_key` text COLLATE utf8mb4_unicode_ci,
  `openai_model` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT 'gpt-4o-mini',
  `ollama_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `ollama_base_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'http://127.0.0.1:11434',
  `ollama_model` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT 'llama3.2',
  `google_calendar_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outlook_calendar_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_settings_company` (`company_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company_settings`
--

INSERT INTO `company_settings` (`id`, `company_id`, `company_name`, `logo`, `favicon`, `logo_path`, `primary_color`, `secondary_color`, `dark_accent`, `accent_color`, `font_family`, `description`, `office_email`, `office_phone`, `business_hours`, `address`, `city`, `state`, `zip_code`, `country`, `company_website`, `registration_number`, `tax_vat_number`, `invoice_payable_name`, `bank_account_number`, `bank_sort_code`, `bank_iban`, `bank_bic`, `default_invoice_payment_terms`, `facebook_url`, `instagram_url`, `linkedin_url`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_encryption`, `stripe_public_key`, `stripe_secret_key`, `google_calendar_enabled`, `outlook_calendar_enabled`, `created_at`, `updated_at`, `google_access_token`, `google_refresh_token`, `google_token_expires`, `openai_api_key`, `openai_model`, `ollama_enabled`, `ollama_base_url`, `ollama_model`, `google_calendar_id`, `outlook_calendar_id`) VALUES
(1, 1, 'FunTurtle Company', 'company_1/branding/logo.png', NULL, NULL, '#3aafa9', '#00182c', '#000000', '#000000', 'Montserrat', NULL, 'office@notary.local', '+23052512345', NULL, 'Labourdonnais Street', 'Port LOuis', NULL, '321560', 'Mauritius', NULL, NULL, NULL, 'FunTurtle Company', '52261440', '50-15-01', 'NGCHYTKDYMVG144855', 'NHKUYG9K', NULL, NULL, NULL, NULL, NULL, 587, NULL, NULL, '', 'admin@admin.com', 'admin123', 0, 0, '2026-05-27 08:21:51', '2026-06-03 08:45:14', NULL, NULL, NULL, 'https://platform.openai.com/api-keys', 'gpt-4o-mini', 1, 'http://127.0.0.1:11434', 'llama3.2', NULL, NULL),
(2, 2, 'Nexa Digital', 'company_2/branding/logo.png', NULL, NULL, '#1907a2', '#00182c', '#000000', '#000000', 'Montserrat', NULL, NULL, NULL, 'Monday - Friday: 10 00 am - 16 00 pm\r\nSaturday - Sunday: Closed', 'Palma Road', 'Quatre Bornes', NULL, '364456', 'Mauritius', NULL, NULL, NULL, 'Nexa Digital', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 587, NULL, NULL, 'tls', 'admin@admin.com', 'admin123', 0, 0, NULL, '2026-06-03 11:01:32', NULL, NULL, NULL, NULL, 'gpt-4o-mini', 1, 'http://127.0.0.1:11434', 'llama3.2', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
CREATE TABLE IF NOT EXISTS `documents` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_id` bigint UNSIGNED DEFAULT NULL,
  `client_id` bigint UNSIGNED DEFAULT NULL,
  `uploaded_by` bigint UNSIGNED NOT NULL,
  `upload_source` enum('admin','client') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `original_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint UNSIGNED NOT NULL DEFAULT '0',
  `file_extension` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_client_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `documents_case_id_foreign` (`case_id`),
  KEY `documents_client_id_foreign` (`client_id`),
  KEY `documents_uploaded_by_foreign` (`uploaded_by`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int UNSIGNED NOT NULL,
  `case_id` bigint UNSIGNED DEFAULT NULL,
  `client_id` bigint UNSIGNED NOT NULL,
  `invoice_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `amount_paid` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('pending','paid','partially_paid','overdue') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pdf_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_payment_intent_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoices_company_number` (`company_id`,`invoice_number`),
  KEY `invoices_case_id_foreign` (`case_id`),
  KEY `invoices_client_id_foreign` (`client_id`),
  KEY `invoices_created_by_foreign` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `company_id`, `case_id`, `client_id`, `invoice_number`, `issue_date`, `due_date`, `subtotal`, `tax_rate`, `tax_amount`, `discount`, `total`, `amount_paid`, `payment_status`, `notes`, `pdf_path`, `stripe_payment_intent_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'INV-2026-0001', '2026-05-22', '2026-06-06', 250.00, 0.00, 0.00, 0.00, 250.00, 250.00, 'paid', NULL, NULL, NULL, 1, '2026-05-27 08:21:51', '2026-06-01 10:51:43'),
(2, 1, 2, 2, 'INV-2026-0002', '2026-05-22', '2026-06-06', 325.00, 0.00, 0.00, 0.00, 325.00, 325.00, 'paid', NULL, NULL, NULL, 1, '2026-05-27 08:21:51', '2026-05-27 08:21:51'),
(3, 1, 3, 3, 'INV-2026-0003', '2026-05-22', '2026-06-06', 400.00, 0.00, 0.00, 0.00, 400.00, 0.00, 'overdue', NULL, NULL, NULL, 1, '2026-05-27 08:21:51', '2026-05-27 08:21:51'),
(4, 1, 6, 8, 'INV-2026-0004', '2026-06-01', '2026-06-15', 1000.00, 2.00, 20.00, 0.00, 1020.00, 1020.00, 'paid', '', 'cases/6/generated/invoice_4.html', NULL, NULL, '2026-06-01 10:47:18', '2026-06-01 11:18:54'),
(5, 1, 7, 8, 'INV-2026-0005', '2026-06-03', '2026-06-17', 5700.00, 20.00, 1140.00, 0.00, 6840.00, 0.00, 'pending', '', 'cases/7/generated/invoice_5.html', NULL, NULL, '2026-06-03 07:46:47', '2026-06-03 07:46:47'),
(6, 1, 7, 8, 'INV-2026-0006', '2026-06-03', '2026-06-17', 5000.00, 20.00, 700.00, 0.00, 5700.00, 0.00, 'pending', '', 'cases/7/generated/invoice_6.html', NULL, NULL, '2026-06-03 07:54:40', '2026-06-03 07:54:40'),
(7, 1, 7, 8, 'INV-2026-0007', '2026-06-03', '2026-06-17', 5000.00, 20.00, 700.00, 0.00, 5700.00, 0.00, 'pending', '', 'cases/7/generated/invoice_7.html', NULL, NULL, '2026-06-03 07:59:33', '2026-06-03 07:59:33'),
(8, 0, 9, 9, 'INV-2026-0001', '2026-06-03', '2026-06-17', 6000.00, 20.00, 1050.00, 0.00, 7050.00, 0.00, 'pending', '', 'cases/9/generated/invoice_8.html', NULL, NULL, '2026-06-03 10:34:54', '2026-06-03 10:34:54');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint UNSIGNED NOT NULL,
  `description` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_invoice_id_foreign` (`invoice_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_05_27_000001_create_notary_management_tables', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `company_id` int UNSIGNED DEFAULT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bi-bell',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_foreign` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `company_id`, `type`, `title`, `message`, `link`, `icon`, `is_read`, `read_at`, `metadata`, `created_at`, `updated_at`) VALUES
(3, 5, 1, 'appointment', 'Appointment scheduled', 'Doc Signing — May 31, 2026 8:57 PM', 'http://localhost/casenotary/client/pages/appointments.php', 'bi-bell', 1, NULL, NULL, '2026-05-31 16:58:24', NULL),
(21, 6, 1, 'appointment', 'Appointment scheduled', 'signing — Jun 02, 2026 2:44 PM', 'http://localhost/casenotary/client/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-01 10:44:25', NULL),
(5, 5, 1, 'appointment', 'Appointment updated', 'Doc Signing — May 31, 2026 8:57 PM', 'http://localhost/casenotary/client/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-05-31 18:30:53', NULL),
(7, 5, 1, 'case', 'New case created', 'Case CASE-2026-0005 was created.', 'http://localhost/casenotary/admin/pages/case-view.php?id=5', 'bi-bell', 0, NULL, NULL, '2026-05-31 18:37:48', NULL),
(9, 5, 1, 'appointment', 'Appointment updated', 'Doc Signing — May 31, 2026 8:57 PM', 'http://localhost/casenotary/client/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-05-31 18:55:22', NULL),
(27, 1, 1, 'appointment', 'Appointment updated', 'Louis Macwell — signing (Jun 02, 2026 2:44 PM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-01 11:02:20', NULL),
(26, 6, 1, 'appointment', 'Appointment updated', 'signing — Jun 02, 2026 2:44 PM', 'http://localhost/casenotary/client/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-01 11:02:20', NULL),
(20, 1, 1, 'case', 'New case created', 'Case CASE-2026-0006 was created.', 'http://localhost/casenotary/admin/pages/case-view.php?id=6', 'bi-bell', 0, NULL, NULL, '2026-06-01 10:41:55', NULL),
(17, 1, 1, 'appointment', 'Appointment scheduled', 'James Rodriguez — Doc (Jun 02, 2026 2:17 PM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-01 10:17:33', NULL),
(19, 6, 1, 'case', 'New case created', 'Case CASE-2026-0006 was created.', 'http://localhost/casenotary/admin/pages/case-view.php?id=6', 'bi-bell', 0, NULL, NULL, '2026-06-01 10:41:55', NULL),
(13, 1, 1, 'appointment', 'Appointment cancelled', 'Sarah Mitchell — Initial Consultation (May 29, 2026 10:00 AM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-01 08:47:21', NULL),
(14, 1, 1, 'appointment', 'Appointment updated', 'Sarah Mitchell — Initial Consultation (May 29, 2026 10:00 AM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-01 08:55:58', NULL),
(15, 1, 1, 'appointment', 'Appointment cancelled', 'Sarah Mitchell — Initial Consultation (May 29, 2026 10:00 AM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-01 08:56:10', NULL),
(16, 1, 1, 'appointment', 'Appointment cancelled', 'Sarah Mitchell — Initial Consultation (May 29, 2026 10:00 AM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-01 09:39:09', NULL),
(18, 1, 1, 'appointment', 'Appointment cancelled', 'James Rodriguez — Doc (Jun 02, 2026 2:17 PM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-01 10:17:48', NULL),
(22, 1, 1, 'appointment', 'Appointment scheduled', 'Louis Macwell — signing (Jun 02, 2026 2:44 PM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-01 10:44:25', NULL),
(23, 6, 1, 'invoice', 'Invoice generated', 'INV-2026-0004 — £ 1,020.00', 'http://localhost/casenotary/admin/pages/case-view.php?id=6#invoice-payments', 'bi-bell', 0, NULL, NULL, '2026-06-01 10:47:18', NULL),
(24, 1, 1, 'invoice', 'Invoice generated', 'INV-2026-0004 — £ 1,020.00', 'http://localhost/casenotary/admin/pages/case-view.php?id=6#invoice-payments', 'bi-bell', 0, NULL, NULL, '2026-06-01 10:47:18', NULL),
(25, 1, 1, 'payment', 'Payment received', '£ 250.00 for INV-2026-0001', 'http://localhost/casenotary/admin/pages/case-view.php?id=1#invoice-payments', 'bi-bell', 0, NULL, NULL, '2026-06-01 10:51:43', NULL),
(28, 6, 1, 'payment', 'Payment received', '£ 1,020.00 for INV-2026-0004', 'http://localhost/casenotary/admin/pages/case-view.php?id=6#invoice-payments', 'bi-bell', 0, NULL, NULL, '2026-06-01 11:18:54', NULL),
(29, 1, 1, 'payment', 'Payment received', '£ 1,020.00 for INV-2026-0004', 'http://localhost/casenotary/admin/pages/case-view.php?id=6#invoice-payments', 'bi-bell', 0, NULL, NULL, '2026-06-01 11:18:54', NULL),
(30, 6, 1, 'appointment', 'Appointment scheduled', 'doc — Jun 10, 2026 12:00 PM', 'http://localhost/casenotary/client/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-01 12:56:02', NULL),
(31, 1, 1, 'appointment', 'Appointment scheduled', 'Louis Macwell — doc (Jun 10, 2026 12:00 PM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-01 12:56:02', NULL),
(32, 6, 1, 'appointment', 'Appointment cancelled', 'signing on Jun 02, 2026 2:44 PM has been cancelled.', 'http://localhost/casenotary/client/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-02 06:53:34', NULL),
(33, 1, 1, 'appointment', 'Appointment cancelled', 'Louis Macwell — signing (Jun 02, 2026 2:44 PM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-02 06:53:34', NULL),
(34, 6, 1, 'appointment', 'Appointment updated', 'doc — Jun 10, 2026 12:00 PM', 'http://localhost/casenotary/client/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-02 07:01:26', NULL),
(35, 1, 1, 'appointment', 'Appointment updated', 'Louis Macwell — doc (Jun 10, 2026 12:00 PM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-02 07:01:26', NULL),
(36, 6, 1, 'appointment', 'Appointment updated', 'doc — Jun 10, 2026 12:00 PM', 'http://localhost/casenotary/client/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-02 07:01:45', NULL),
(37, 1, 1, 'appointment', 'Appointment updated', 'Louis Macwell — doc (Jun 10, 2026 12:00 PM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-02 07:01:45', NULL),
(38, 6, 1, 'appointment', 'Appointment updated', 'doc — Jun 10, 2026 12:00 PM', 'http://localhost/casenotary/client/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-02 07:10:21', NULL),
(39, 1, 1, 'appointment', 'Appointment updated', 'Louis Macwell — doc (Jun 10, 2026 12:00 PM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-02 07:10:21', NULL),
(40, 6, 1, 'appointment', 'Appointment request submitted', 'Meeting — pending approval. Preferred time: Jun 11, 2026 12:00 PM', 'http://localhost/casenotary/client/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-02 07:12:51', NULL),
(41, 1, 1, 'appointment', 'New appointment request', 'Louis Macwell requested \"Meeting\" for Jun 11, 2026 12:00 PM', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 1, NULL, NULL, '2026-06-02 07:12:51', NULL),
(42, 6, 1, 'appointment', 'Appointment cancelled', 'Meeting on Jun 11, 2026 12:00 PM has been cancelled.', 'http://localhost/casenotary/client/pages/appointments.php', 'bi-bell', 1, NULL, NULL, '2026-06-02 17:11:57', NULL),
(43, 1, 1, 'appointment', 'Appointment cancelled', 'Louis Macwell — Meeting (Jun 11, 2026 12:00 PM)', 'http://localhost/casenotary/admin/pages/appointments.php', 'bi-bell', 0, NULL, NULL, '2026-06-02 17:11:57', NULL),
(44, 6, 1, 'case', 'New case created', 'Case CASE-2026-0007 was created.', 'http://localhost/casenotary/admin/pages/case-view.php?id=7', 'bi-bell', 1, NULL, NULL, '2026-06-02 18:44:28', NULL),
(45, 1, 1, 'case', 'New case created', 'Case CASE-2026-0007 was created.', 'http://localhost/casenotary/admin/pages/case-view.php?id=7', 'bi-bell', 0, NULL, NULL, '2026-06-02 18:44:28', NULL),
(46, 6, 1, 'document', 'Quotation created', 'QUO-2026-0001', 'http://localhost/casenotary/admin/pages/case-view.php?id=7#quotations', 'bi-bell', 1, NULL, NULL, '2026-06-02 18:44:29', NULL),
(47, 1, 1, 'document', 'Quotation created', 'QUO-2026-0001', 'http://localhost/casenotary/admin/pages/case-view.php?id=7#quotations', 'bi-bell', 0, NULL, NULL, '2026-06-02 18:44:29', NULL),
(48, 6, 1, 'invoice', 'Invoice generated', 'INV-2026-0005 — £ 6,840.00', 'http://localhost/casenotary/admin/pages/case-view.php?id=7#invoice-payments', 'bi-bell', 0, NULL, NULL, '2026-06-03 07:46:47', NULL),
(49, 6, 1, 'invoice', 'Invoice generated', 'INV-2026-0006 — £ 5,700.00', 'http://localhost/casenotary/admin/pages/case-view.php?id=7#invoice-payments', 'bi-bell', 0, NULL, NULL, '2026-06-03 07:54:40', NULL),
(50, 6, 1, 'invoice', 'Invoice generated', 'INV-2026-0007 — £ 5,700.00', 'http://localhost/casenotary/admin/pages/case-view.php?id=7#invoice-payments', 'bi-bell', 0, NULL, NULL, '2026-06-03 07:59:33', NULL),
(51, 7, 2, 'case', 'New case created', 'Case CASE-2026-0001 was created.', 'http://localhost/casenotary/admin/pages/case-view.php?id=9', 'bi-bell', 0, NULL, NULL, '2026-06-03 10:34:38', NULL),
(52, 1, 2, 'case', 'New case created', 'Case CASE-2026-0001 was created.', 'http://localhost/casenotary/admin/pages/case-view.php?id=9', 'bi-bell', 0, NULL, NULL, '2026-06-03 10:34:38', NULL),
(53, 7, 2, 'document', 'Quotation created', 'QUO-2026-0002', 'http://localhost/casenotary/admin/pages/case-view.php?id=9#quotations', 'bi-bell', 0, NULL, NULL, '2026-06-03 10:34:38', NULL),
(54, 1, 2, 'document', 'Quotation created', 'QUO-2026-0002', 'http://localhost/casenotary/admin/pages/case-view.php?id=9#quotations', 'bi-bell', 0, NULL, NULL, '2026-06-03 10:34:38', NULL),
(55, 7, 2, 'invoice', 'Invoice generated', 'INV-2026-0001 — £ 7,050.00', 'http://localhost/casenotary/admin/pages/case-view.php?id=9#invoice-payments', 'bi-bell', 0, NULL, NULL, '2026-06-03 10:34:54', NULL),
(56, 1, 2, 'invoice', 'Invoice generated', 'INV-2026-0001 — £ 7,050.00', 'http://localhost/casenotary/admin/pages/case-view.php?id=9#invoice-payments', 'bi-bell', 1, NULL, NULL, '2026-06-03 10:34:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_password_resets_email` (`email`),
  KEY `idx_password_resets_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint UNSIGNED NOT NULL,
  `client_id` bigint UNSIGNED NOT NULL,
  `payment_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('stripe','cash','check','bank_transfer','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'stripe',
  `transaction_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_invoice_id_foreign` (`invoice_id`),
  KEY `payments_client_id_foreign` (`client_id`),
  KEY `payments_created_by_foreign` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `invoice_id`, `client_id`, `payment_number`, `amount`, `payment_method`, `transaction_id`, `payment_status`, `paid_at`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, 2, 'PAY-2026-0001', 325.00, 'bank_transfer', NULL, 'completed', '2026-05-25 08:21:51', NULL, 1, '2026-05-27 08:21:51', '2026-05-27 08:21:51'),
(2, 1, 1, 'PAY-2026-0002', 250.00, 'bank_transfer', NULL, 'completed', '2026-06-01 02:51:43', '', 1, '2026-06-01 10:51:43', '2026-06-01 10:51:43'),
(3, 4, 8, 'PAY-2026-0003', 1020.00, 'bank_transfer', NULL, 'completed', '2026-06-01 03:18:54', NULL, 1, '2026-06-01 11:18:54', '2026-06-01 11:18:54');

-- --------------------------------------------------------

--
-- Table structure for table `proposals`
--

DROP TABLE IF EXISTS `proposals`;
CREATE TABLE IF NOT EXISTS `proposals` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_id` bigint UNSIGNED NOT NULL,
  `proposal_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `pdf_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','sent','accepted','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proposals_case_id_foreign` (`case_id`),
  KEY `proposals_created_by_foreign` (`created_by`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

DROP TABLE IF EXISTS `quotations`;
CREATE TABLE IF NOT EXISTS `quotations` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_id` bigint UNSIGNED NOT NULL,
  `quotation_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pdf_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','sent','accepted','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `valid_until` date DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotations_case_id_foreign` (`case_id`),
  KEY `quotations_created_by_foreign` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quotations`
--

INSERT INTO `quotations` (`id`, `case_id`, `quotation_number`, `title`, `subtotal`, `tax_rate`, `tax_amount`, `total`, `notes`, `pdf_path`, `status`, `valid_until`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 7, 'QUO-2026-0001', 'Quotation — PROP', 5000.00, 12.00, 600.00, 5700.00, '[{\"description\":\"Disbursement (Non-VAT)\",\"amount\":2000},{\"description\":\"Notarisation (VAT net)\",\"amount\":3000}]', 'cases/7/generated/quotation_1.html', 'sent', '2026-07-02', NULL, '2026-06-02 18:44:28', '2026-06-02 18:44:28'),
(2, 9, 'QUO-2026-0002', 'Quotation — Tranfer', 6000.00, 16.67, 1000.00, 7050.00, '[{\"description\":\"Disbursement (Non-VAT)\",\"amount\":1000},{\"description\":\"Notarisation (VAT net)\",\"amount\":5000}]', 'cases/9/generated/quotation_2.html', 'sent', '2026-07-03', NULL, '2026-06-03 10:34:38', '2026-06-03 10:34:38');

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

DROP TABLE IF EXISTS `receipts`;
CREATE TABLE IF NOT EXISTS `receipts` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` bigint UNSIGNED NOT NULL,
  `receipt_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pdf_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `receipts_payment_id_foreign` (`payment_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `receipts`
--

INSERT INTO `receipts` (`id`, `payment_id`, `receipt_number`, `pdf_path`, `issued_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'RCP-2026-0001', NULL, '2026-06-01 02:51:43', '2026-06-01 10:51:43', '2026-06-01 10:51:43'),
(2, 3, 'RCP-2026-0002', NULL, '2026-06-01 03:18:54', '2026-06-01 11:18:54', '2026-06-01 11:18:54');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('qmYgjrOz6z9rvH9pTWse8flyDTOutOm2D6h8bjew', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'eyJfdG9rZW4iOiJUTXFjU0NXSHpPanltTHppOW5STWl0RmRoeEg2UWlRWG1rUnE4REN1IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdFwvbm90YXJ5LW1hbmFnZW1lbnRcL3B1YmxpYyIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1779963132),
('PJFRbAe33nnM9LDjAG5FsMEe503mXDDFt7d1BpsM', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'eyJfdG9rZW4iOiJCTDZkWXhRUFVmWHlyRmRTbkZRcmhhdERGWkNzblNrSjVhRGQyQzR3IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdFwvbm90YXJ5LW1hbmFnZW1lbnRcL3B1YmxpYyIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1779963132),
('0wei8ukLuWcH2nXEChjspqGYohPLsrclbyoFkWI8', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'eyJfdG9rZW4iOiJZZk1DTEY5UU1rN3E4TnViSmk4ZnlOR2xFNWNPZ25TaDhqWE8wWU9LIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdFwvbm90YXJ5LW1hbmFnZW1lbnRcL3B1YmxpYyIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1779963132),
('zjjdGJVIKyN0XdMTOliZwLJWEAzPHReHM0m6KuvK', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'eyJfdG9rZW4iOiIyQWE0aG1XSDViSldiWlQyQjJXT0JpMzBiZXBwNDBNd3I4cmZUcTMyIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdFwvbm90YXJ5LW1hbmFnZW1lbnRcL3B1YmxpYyIsInJvdXRlIjpudWxsfSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1779963132);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `password` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `client_id` bigint UNSIGNED DEFAULT NULL,
  `avatar` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','suspended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `uk_users_email_company` (`email`,`company_id`),
  UNIQUE KEY `uq_users_company_email` (`company_id`,`email`),
  KEY `users_client_id_foreign` (`client_id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `company_id`, `name`, `email`, `email_verified_at`, `last_login`, `password`, `role`, `first_name`, `last_name`, `client_id`, `avatar`, `status`, `is_active`, `last_login_at`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Admin User', 'admin@admin.com', NULL, '2026-06-03 16:42:31', '$2y$10$zxvAOOvv3W.oCt6WY5twWefbIqYIOznQxcTqIyu5Jfqe9AV9GsG.i', 'super_admin', 'Admin', 'User', NULL, NULL, 'active', 1, '2026-05-28 11:15:34', NULL, '2026-05-27 08:21:51', '2026-05-27 08:21:51'),
(6, 1, 'Louis Macwell', 'Louismac@gmail.com', NULL, '2026-06-03 14:01:29', '$2y$10$EXS6yhMhkqGLcNxCoDkn9u3ffMDGjhhaGg/QcFxQu9iBgDpCBBds6', 'client', '', '', 8, NULL, 'active', 1, NULL, NULL, '2026-06-01 10:28:35', '2026-06-01 10:49:20'),
(7, 2, 'Sammy well', 'wellmy@gmail.com', NULL, '2026-06-03 15:05:39', '$2y$10$1l4SqEW3Xl5aOwlqWm7XOeH3G7AZAyzLKT2LrfCmtl6lmvx.mzZ9S', 'client', '', '', 9, NULL, 'active', 1, NULL, NULL, '2026-06-03 08:57:05', '2026-06-03 08:57:05'),
(8, 1, '', 'fran@gmail.com', NULL, '2026-06-03 15:24:09', '$2y$10$RWBTuSjA4rfYrZ8r1WF8Z.Ky6nbxBantTghiirFfW0Fq2aB9S0Him', 'manager', 'Francis', 'Barber', NULL, NULL, 'active', 1, NULL, NULL, '2026-06-03 11:23:07', '2026-06-03 11:23:07'),
(9, 1, '', 'max@gmail.com', NULL, '2026-06-03 16:33:55', '$2y$10$OcpDAOvVDSPtbW5LmOINGOs4b1lGs8boIjihDoyJFlUOjwuas8ALa', 'staff', 'Max', 'Holly', NULL, NULL, 'active', 1, NULL, NULL, '2026-06-03 11:34:20', '2026-06-03 11:34:20');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
