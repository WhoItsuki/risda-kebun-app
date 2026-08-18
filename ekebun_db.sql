-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for risda_kebun
CREATE DATABASE IF NOT EXISTS `risda_kebun` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `risda_kebun`;

-- Dumping structure for table risda_kebun.admins
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table risda_kebun.kebun
CREATE TABLE IF NOT EXISTS `kebun` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pekebun_id` int NOT NULL,
  `qr_code_hash` varchar(64) DEFAULT NULL,
  `no_lot` varchar(50) NOT NULL,
  `lokasi_kebun` varchar(255) NOT NULL,
  `mukim` varchar(100) NOT NULL,
  `daerah` varchar(100) NOT NULL,
  `keluasan_kebun` decimal(10,2) DEFAULT NULL,
  `tahun_tanam` int DEFAULT NULL,
  `tahun_sulaman` int DEFAULT NULL,
  `klon_getah` varchar(100) DEFAULT NULL,
  `jarak_tanaman` varchar(50) DEFAULT NULL,
  `jumlah_pokok` int DEFAULT NULL,
  `koordinat` varchar(50) DEFAULT NULL,
  `pelan_lot` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qr_code_hash` (`qr_code_hash`),
  KEY `pekebun_id` (`pekebun_id`),
  CONSTRAINT `kebun_ibfk_1` FOREIGN KEY (`pekebun_id`) REFERENCES `pekebun` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table risda_kebun.pekebun
CREATE TABLE IF NOT EXISTS `pekebun` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(150) NOT NULL,
  `no_telefon` varchar(20) NOT NULL,
  `alamat` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table risda_kebun.tanam_semula
CREATE TABLE IF NOT EXISTS `tanam_semula` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kebun_id` int NOT NULL,
  `no_tanam_semula` varchar(50) NOT NULL,
  `tahun_tanam_semula` int NOT NULL,
  `keluasan_diluluskan` decimal(10,2) DEFAULT NULL,
  `bantuan_ansuran` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kebun_id` (`kebun_id`),
  CONSTRAINT `tanam_semula_ibfk_1` FOREIGN KEY (`kebun_id`) REFERENCES `kebun` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
