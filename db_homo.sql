-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 07, 2026 at 09:27 PM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_homo`
--

-- --------------------------------------------------------

--
-- Table structure for table `aoat_records`
--

DROP TABLE IF EXISTS `aoat_records`;
CREATE TABLE IF NOT EXISTS `aoat_records` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `professional_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `professional_last_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `professional_email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `professional_role` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `profession` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subregion` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipality` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` enum('Asignada','Realizado','Devuelta','Aprobada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asignada',
  `audit_observation` text COLLATE utf8mb4_unicode_ci,
  `audit_motive` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_aoat_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asistencia_actividades`
--

DROP TABLE IF EXISTS `asistencia_actividades`;
CREATE TABLE IF NOT EXISTS `asistencia_actividades` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Código para QR y URL de registro (ej. 871812)',
  `subregion` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipality` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lugar` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `advisor_user_id` bigint UNSIGNED NOT NULL,
  `advisor_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activity_date` date NOT NULL,
  `actividad_tipos` json NOT NULL COMMENT 'Array de tipos de listado seleccionados (multi-select)',
  `status` enum('Pendiente','Activo') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pendiente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `fk_asistencia_advisor` (`advisor_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asistencia_asistentes`
--

DROP TABLE IF EXISTS `asistencia_asistentes`;
CREATE TABLE IF NOT EXISTS `asistencia_asistentes` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `actividad_id` bigint UNSIGNED NOT NULL,
  `document_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Urbana, Rural, etc.',
  `sex` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Masculino, Femenino, No binario, Transgénero/transexual/travesti',
  `age` tinyint UNSIGNED DEFAULT NULL,
  `etnia` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Afrodescendiente, Indígena, Otro',
  `etnia_otro` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Especificación si etnia = Otro',
  `grupo_poblacional` json DEFAULT NULL COMMENT 'Array de opciones grupo poblacional',
  `registered_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_asistente_actividad` (`actividad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entrenamiento_plans`
--

DROP TABLE IF EXISTS `entrenamiento_plans`;
CREATE TABLE IF NOT EXISTS `entrenamiento_plans` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `professional_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `professional_email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subregion` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipality` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `editable` tinyint(1) NOT NULL DEFAULT '1',
  `payload` json NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_entrenamiento_plans_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pic_records`
--

DROP TABLE IF EXISTS `pic_records`;
CREATE TABLE IF NOT EXISTS `pic_records` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `professional_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `professional_email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subregion` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipality` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `editable` tinyint(1) NOT NULL DEFAULT '1',
  `payload` json NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pic_records_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'admin', 'Administrador del sistema'),
(2, 'medico', 'Médico'),
(3, 'abogado', 'Abogado'),
(4, 'psicologo', 'Psicólogo'),
(5, 'profesional social', 'Profesional Social'),
(6, 'asesor', 'Asesor');

-- --------------------------------------------------------

--
-- Table structure for table `test_responses`
--

DROP TABLE IF EXISTS `test_responses`;
CREATE TABLE IF NOT EXISTS `test_responses` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phase` enum('pre','post') COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subregion` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipality` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `profession` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_questions` tinyint UNSIGNED DEFAULT NULL,
  `correct_answers` tinyint UNSIGNED DEFAULT NULL,
  `score_percent` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_test_person_phase` (`test_key`,`phase`,`document_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_response_answers`
--

DROP TABLE IF EXISTS `test_response_answers`;
CREATE TABLE IF NOT EXISTS `test_response_answers` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `response_id` bigint UNSIGNED NOT NULL,
  `question_number` tinyint UNSIGNED NOT NULL,
  `selected_option` char(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_response_question` (`response_id`,`question_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_plans`
--

DROP TABLE IF EXISTS `training_plans`;
CREATE TABLE IF NOT EXISTS `training_plans` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `professional_name` varchar(150) NOT NULL,
  `professional_email` varchar(150) NOT NULL,
  `professional_role` varchar(80) NOT NULL,
  `subregion` varchar(120) NOT NULL,
  `municipality` varchar(120) NOT NULL,
  `plan_year` int NOT NULL,
  `editable` tinyint(1) NOT NULL DEFAULT '1',
  `payload` json NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_training_plans_user` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Administrador', 'juandipa2112@gmail.com', '$2y$10$e0KxkzGiLBcGmkMlzYGXj.SLRhjNTg4M/L9/HQTNaXlXX8LoRN3UG', 1, '2026-03-06 13:58:23', '2026-03-06 13:58:23');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `fk_user_roles_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `aoat_records`
--
ALTER TABLE `aoat_records`
  ADD CONSTRAINT `fk_aoat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `asistencia_actividades`
--
ALTER TABLE `asistencia_actividades`
  ADD CONSTRAINT `fk_asistencia_advisor` FOREIGN KEY (`advisor_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `asistencia_asistentes`
--
ALTER TABLE `asistencia_asistentes`
  ADD CONSTRAINT `fk_asistente_actividad` FOREIGN KEY (`actividad_id`) REFERENCES `asistencia_actividades` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entrenamiento_plans`
--
ALTER TABLE `entrenamiento_plans`
  ADD CONSTRAINT `fk_entrenamiento_plans_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pic_records`
--
ALTER TABLE `pic_records`
  ADD CONSTRAINT `fk_pic_records_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `test_response_answers`
--
ALTER TABLE `test_response_answers`
  ADD CONSTRAINT `fk_tra_response` FOREIGN KEY (`response_id`) REFERENCES `test_responses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
