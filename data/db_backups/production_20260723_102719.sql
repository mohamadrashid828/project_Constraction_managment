-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: srv1733.hstgr.io    Database: u670910047_kaver
-- ------------------------------------------------------
-- Server version	11.8.8-MariaDB-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `apartments`
--

DROP TABLE IF EXISTS `apartments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `apartments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `building_id` int(11) NOT NULL,
  `floor_id` int(11) NOT NULL,
  `apartment_number` varchar(50) NOT NULL,
  `apartment_type` varchar(100) DEFAULT NULL,
  `area_sqm` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `building_id` (`building_id`),
  KEY `floor_id` (`floor_id`),
  CONSTRAINT `apartments_ibfk_1` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `apartments_ibfk_2` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `apartments`
--

LOCK TABLES `apartments` WRITE;
/*!40000 ALTER TABLE `apartments` DISABLE KEYS */;
INSERT INTO `apartments` VALUES (1,6,120,'1','Penthouse',45.00,'active','2026-07-23 08:57:55');
/*!40000 ALTER TABLE `apartments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `buildings`
--

DROP TABLE IF EXISTS `buildings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `buildings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `building_name` varchar(100) DEFAULT NULL,
  `total_area` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `comments` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `buildings`
--

LOCK TABLES `buildings` WRITE;
/*!40000 ALTER TABLE `buildings` DISABLE KEYS */;
INSERT INTO `buildings` VALUES (1,'A1',19578.55,'active','2026-07-13 08:27:29',''),(2,'A2',19844.31,'active','2026-07-13 08:48:46',''),(3,'A3',20039.06,'active','2026-07-13 08:52:11',''),(4,'B1',24535.17,'active','2026-07-13 09:22:48',''),(5,'B2',25144.16,'active','2026-07-13 10:04:42',''),(6,'Commercial',1.00,'active','2026-07-13 13:48:18',''),(7,'Parking (Garage)',1.00,'active','2026-07-13 13:48:45','');
/*!40000 ALTER TABLE `buildings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `floors`
--

DROP TABLE IF EXISTS `floors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `floors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `building_id` int(11) NOT NULL,
  `floor_number` int(11) NOT NULL,
  `floor_name` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `area` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `building_id` (`building_id`),
  CONSTRAINT `floors_ibfk_1` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `floors`
--

LOCK TABLES `floors` WRITE;
/*!40000 ALTER TABLE `floors` DISABLE KEYS */;
INSERT INTO `floors` VALUES (1,1,2,'FLOOR 2','active','2026-07-13 08:28:57',905.38),(2,1,3,'FLOOR 3','active','2026-07-13 08:29:22',874.88),(3,1,4,'FLOOR 4','active','2026-07-13 08:29:38',891.33),(4,1,5,'FLOOR 5','active','2026-07-13 08:30:55',874.88),(5,1,6,'FLOOR 6','active','2026-07-13 08:31:14',891.00),(6,1,7,'FLOOR 7','active','2026-07-13 08:31:19',874.90),(7,1,8,'FLOOR 8','active','2026-07-13 08:31:24',891.03),(8,1,9,'FLOOR 9','active','2026-07-13 08:31:28',874.87),(9,1,10,'FLOOR 10','active','2026-07-13 08:31:34',874.87),(10,1,11,'FLOOR 11','active','2026-07-13 08:31:39',896.19),(11,1,12,'FLOOR 12','active','2026-07-13 08:31:43',884.04),(12,1,13,'FLOOR 13','active','2026-07-13 08:32:01',884.04),(13,1,14,'FLOOR 14','active','2026-07-13 08:32:06',884.04),(14,1,15,'FLOOR 15','active','2026-07-13 08:32:11',940.77),(15,1,16,'FLOOR 16','active','2026-07-13 08:32:15',905.98),(16,1,17,'FLOOR 17','active','2026-07-13 08:32:19',895.21),(17,1,18,'FLOOR 18','active','2026-07-13 08:32:22',895.21),(18,1,19,'FLOOR 19','active','2026-07-13 08:32:27',895.21),(19,1,20,'FLOOR 20','active','2026-07-13 08:32:34',895.21),(20,1,21,'FLOOR 21','active','2026-07-13 08:32:38',895.21),(21,1,22,'FLOOR 22','active','2026-07-13 08:32:42',895.21),(22,1,23,'FLOOR 23','active','2026-07-13 08:32:49',895.21),(23,1,24,'ROOF','active','2026-07-13 08:33:01',1.00),(24,2,2,'FLOOR 2','active','2026-07-13 08:53:15',962.52),(25,2,3,'FLOOR 3','active','2026-07-13 08:53:28',896.16),(26,2,4,'FLOOR 4','active','2026-07-13 08:53:34',899.91),(27,2,5,'FLOOR 5','active','2026-07-13 08:53:39',896.67),(28,2,6,'FLOOR 6','active','2026-07-13 08:53:43',879.18),(29,2,7,'FLOOR 7','active','2026-07-13 08:53:47',917.09),(30,2,8,'FLOOR 8','active','2026-07-13 08:53:51',882.42),(31,2,9,'FLOOR 9','active','2026-07-13 08:53:54',900.27),(32,2,10,'FLOOR 10','active','2026-07-13 08:53:59',910.41),(33,2,11,'FLOOR 11','active','2026-07-13 08:54:04',942.07),(34,2,12,'FLOOR 12','active','2026-07-13 08:54:08',925.31),(35,2,13,'FLOOR 13','active','2026-07-13 08:54:12',924.28),(36,2,14,'FLOOR 14','active','2026-07-13 08:54:16',924.28),(37,2,15,'FLOOR 15','active','2026-07-13 08:54:21',902.38),(38,2,16,'FLOOR 16','active','2026-07-13 08:54:29',902.36),(39,2,17,'FLOOR 17','active','2026-07-13 08:54:38',882.72),(40,2,18,'FLOOR 18','active','2026-07-13 08:54:47',882.72),(41,2,19,'FLOOR 19','active','2026-07-13 08:54:53',882.72),(42,2,20,'FLOOR 20','active','2026-07-13 08:54:59',882.72),(43,2,21,'FLOOR 21','active','2026-07-13 08:55:08',882.72),(44,2,22,'FLOOR 22','active','2026-07-13 08:55:13',882.72),(45,2,23,'FLOOR 23','active','2026-07-13 08:55:18',882.72),(46,2,24,'ROOF','active','2026-07-13 08:56:35',1.00),(47,3,2,'FLOOR 2','active','2026-07-13 09:07:47',905.45),(48,3,3,'FLOOR 3','active','2026-07-13 09:07:52',901.90),(49,3,4,'FLOOR 4','active','2026-07-13 09:07:56',902.03),(50,3,5,'FLOOR 5','active','2026-07-13 09:08:01',918.93),(51,3,6,'FLOOR 6','active','2026-07-13 09:08:05',881.09),(52,3,7,'FLOOR 7','active','2026-07-13 09:08:11',922.44),(53,3,8,'FLOOR 8','active','2026-07-13 09:08:18',920.82),(54,3,9,'FLOOR 9','active','2026-07-13 09:08:23',903.43),(55,3,10,'FLOOR 10','active','2026-07-13 09:08:28',924.75),(56,3,11,'FLOOR 11','active','2026-07-13 09:08:34',914.51),(57,3,12,'FLOOR 12','active','2026-07-13 09:08:40',914.72),(58,3,13,'FLOOR 13','active','2026-07-13 09:08:44',899.25),(59,3,14,'FLOOR 14','active','2026-07-13 09:08:49',899.18),(60,3,15,'FLOOR 15','active','2026-07-13 09:08:54',900.82),(61,3,16,'FLOOR 16','active','2026-07-13 09:09:11',885.27),(62,3,17,'FLOOR 17','active','2026-07-13 09:09:15',920.66),(63,3,18,'FLOOR 18','active','2026-07-13 09:09:19',920.66),(64,3,19,'FLOOR 19','active','2026-07-13 09:09:23',920.66),(65,3,20,'FLOOR 20','active','2026-07-13 09:09:30',920.66),(66,3,21,'FLOOR 21','active','2026-07-13 09:09:33',920.66),(67,3,22,'FLOOR 22','active','2026-07-13 09:09:37',920.66),(68,3,23,'FLOOR 23','active','2026-07-13 09:09:41',920.66),(69,3,24,'ROOF','active','2026-07-13 09:09:51',1.00),(70,4,2,'FLOOR 2','active','2026-07-13 10:05:14',1257.41),(71,4,3,'FLOOR 3','active','2026-07-13 10:05:19',1257.41),(72,4,4,'FLOOR 4','active','2026-07-13 10:05:22',1257.41),(73,4,5,'FLOOR 5','active','2026-07-13 10:05:26',1257.41),(74,4,6,'FLOOR 6','active','2026-07-13 10:05:31',1226.51),(75,4,7,'FLOOR 7','active','2026-07-13 10:05:35',1091.52),(76,4,8,'FLOOR 8','active','2026-07-13 10:05:39',1090.95),(77,4,9,'FLOOR 9','active','2026-07-13 10:05:43',1099.22),(78,4,10,'FLOOR 10','active','2026-07-13 10:05:49',1092.71),(79,4,11,'FLOOR 11','active','2026-07-13 10:05:53',1092.70),(80,4,12,'FLOOR 12','active','2026-07-13 10:05:57',1075.88),(81,4,13,'FLOOR 13','active','2026-07-13 10:06:01',1067.61),(82,4,14,'FLOOR 14','active','2026-07-13 10:06:06',1066.88),(83,4,15,'FLOOR 15','active','2026-07-13 10:06:10',1066.88),(84,4,16,'FLOOR 16','active','2026-07-13 10:06:16',1066.88),(86,4,17,'FLOOR 17','active','2026-07-13 10:07:10',1066.88),(87,4,18,'FLOOR 18','active','2026-07-13 10:07:14',1066.88),(88,4,19,'FLOOR 19','active','2026-07-13 10:07:18',1066.88),(89,4,20,'FLOOR 20','active','2026-07-13 10:07:24',1066.88),(90,4,21,'FLOOR 21','active','2026-07-13 10:07:27',1066.88),(91,4,22,'FLOOR 22','active','2026-07-13 10:07:38',1066.88),(92,4,23,'FLOOR 23','active','2026-07-13 10:08:32',1066.88),(93,4,24,'ROOF','active','2026-07-13 10:08:38',1.00),(94,5,2,'FLOOR 2','active','2026-07-13 10:24:55',1378.05),(95,5,3,'FLOOR 3','active','2026-07-13 10:25:00',1378.05),(96,5,4,'FLOOR 4','active','2026-07-13 10:25:04',1378.05),(97,5,5,'FLOOR 5','active','2026-07-13 10:25:09',1378.05),(98,5,6,'FLOOR 6','active','2026-07-13 10:25:21',1347.02),(99,5,7,'FLOOR 7','active','2026-07-13 10:25:27',1091.47),(100,5,8,'FLOOR 8','active','2026-07-13 10:25:32',1091.56),(101,5,9,'FLOOR 9','active','2026-07-13 10:25:37',1099.82),(102,5,10,'FLOOR 10','active','2026-07-13 10:25:42',1093.38),(103,5,11,'FLOOR 11','active','2026-07-13 10:25:46',1093.38),(104,5,12,'FLOOR 12','active','2026-07-13 10:25:50',1074.83),(105,5,13,'FLOOR 13','active','2026-07-13 10:25:54',1066.50),(106,5,14,'FLOOR 14','active','2026-07-13 10:25:58',1067.40),(107,5,15,'FLOOR 15','active','2026-07-13 10:26:04',1067.40),(108,5,16,'FLOOR 16','active','2026-07-13 10:26:08',1067.40),(109,5,17,'FLOOR 17','active','2026-07-13 10:26:12',1067.40),(110,5,18,'FLOOR 18','active','2026-07-13 10:26:17',1067.40),(111,5,19,'FLOOR 19','active','2026-07-13 10:26:22',1067.40),(112,5,20,'FLOOR 20','active','2026-07-13 10:26:29',1067.40),(113,5,21,'FLOOR 21','active','2026-07-13 10:26:33',1067.40),(114,5,22,'FLOOR 22','active','2026-07-13 10:26:37',1067.40),(115,5,23,'FLOOR 23','active','2026-07-13 10:26:40',1067.40),(116,5,24,'ROOF','active','2026-07-13 10:26:50',1.00),(117,1,0,'All Floors','active','2026-07-13 10:41:41',1.00),(118,2,1,'All Floors','active','2026-07-16 11:24:07',1.00),(119,3,1,'All Floors','active','2026-07-16 11:26:24',1.00),(120,6,1,'Ground','active','2026-07-23 08:57:21',1.00);
/*!40000 ALTER TABLE `floors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_attendance`
--

DROP TABLE IF EXISTS `hr_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `att_date` date NOT NULL,
  `status` enum('present','late','half_day','absent','leave') NOT NULL DEFAULT 'present',
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `marked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_att_emp_date` (`employee_id`,`att_date`),
  KEY `idx_att_date` (`att_date`),
  CONSTRAINT `hr_attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_attendance`
--

LOCK TABLES `hr_attendance` WRITE;
/*!40000 ALTER TABLE `hr_attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_contracts`
--

DROP TABLE IF EXISTS `hr_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `contract_title` varchar(160) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `salary_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `salary_period` enum('monthly','daily','hourly') NOT NULL DEFAULT 'monthly',
  `currency` varchar(10) NOT NULL DEFAULT 'IQD',
  `status` enum('active','expired','terminated','renewed') NOT NULL DEFAULT 'active',
  `file_name` varchar(255) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_con_employee` (`employee_id`),
  KEY `idx_con_status` (`status`),
  KEY `idx_con_end` (`end_date`),
  CONSTRAINT `hr_contracts_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_contracts`
--

LOCK TABLES `hr_contracts` WRITE;
/*!40000 ALTER TABLE `hr_contracts` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_contracts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_documents`
--

DROP TABLE IF EXISTS `hr_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `doc_name` varchar(160) NOT NULL,
  `doc_type` enum('id','contract','certificate','other') NOT NULL DEFAULT 'other',
  `file_name` varchar(255) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_doc_employee` (`employee_id`),
  CONSTRAINT `hr_documents_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_documents`
--

LOCK TABLES `hr_documents` WRITE;
/*!40000 ALTER TABLE `hr_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_employee_assignments`
--

DROP TABLE IF EXISTS `hr_employee_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employee_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `building_id` int(11) DEFAULT NULL,
  `assigned_from` date NOT NULL,
  `assigned_to` date DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asg_employee` (`employee_id`),
  KEY `idx_asg_building` (`building_id`),
  CONSTRAINT `hr_employee_assignments_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_employee_assignments`
--

LOCK TABLES `hr_employee_assignments` WRITE;
/*!40000 ALTER TABLE `hr_employee_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_employee_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_employees`
--

DROP TABLE IF EXISTS `hr_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(20) NOT NULL,
  `full_name` varchar(160) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `emergency_phone` varchar(40) DEFAULT NULL,
  `national_id` varchar(60) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `job_title` varchar(120) DEFAULT NULL,
  `department` varchar(120) DEFAULT NULL,
  `employment_type` enum('permanent','contract','temporary','daily','hourly','intern','site_worker') NOT NULL DEFAULT 'permanent',
  `building_id` int(11) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `salary_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `salary_period` enum('monthly','daily','hourly') NOT NULL DEFAULT 'monthly',
  `currency` varchar(10) NOT NULL DEFAULT 'IQD',
  `status` enum('active','on_leave','resigned') NOT NULL DEFAULT 'active',
  `safety_training_done` tinyint(1) NOT NULL DEFAULT 0,
  `safety_training_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_code` (`employee_code`),
  KEY `idx_emp_status` (`status`),
  KEY `idx_emp_type` (`employment_type`),
  KEY `idx_emp_building` (`building_id`),
  KEY `idx_emp_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_employees`
--

LOCK TABLES `hr_employees` WRITE;
/*!40000 ALTER TABLE `hr_employees` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_leaves`
--

DROP TABLE IF EXISTS `hr_leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_leaves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type` enum('annual','sick','unpaid','other') NOT NULL DEFAULT 'annual',
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `days` decimal(5,1) NOT NULL DEFAULT 1.0,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leave_employee` (`employee_id`),
  KEY `idx_leave_status` (`status`),
  KEY `idx_leave_from` (`date_from`),
  CONSTRAINT `hr_leaves_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_leaves`
--

LOCK TABLES `hr_leaves` WRITE;
/*!40000 ALTER TABLE `hr_leaves` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_leaves` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hr_payroll`
--

DROP TABLE IF EXISTS `hr_payroll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hr_payroll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `building_id` int(11) DEFAULT NULL,
  `work_basis` varchar(80) DEFAULT NULL,
  `base_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `overtime_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `bonus_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `deduction_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'IQD',
  `payment_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `paid_date` date DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pay_employee` (`employee_id`),
  KEY `idx_pay_status` (`payment_status`),
  KEY `idx_pay_start` (`period_start`),
  KEY `idx_pay_building` (`building_id`),
  CONSTRAINT `hr_payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hr_payroll`
--

LOCK TABLES `hr_payroll` WRITE;
/*!40000 ALTER TABLE `hr_payroll` DISABLE KEYS */;
/*!40000 ALTER TABLE `hr_payroll` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_item_types`
--

DROP TABLE IF EXISTS `inventory_item_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_item_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_item_types`
--

LOCK TABLES `inventory_item_types` WRITE;
/*!40000 ALTER TABLE `inventory_item_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_item_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_items`
--

DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(160) NOT NULL,
  `item_code` varchar(60) DEFAULT NULL,
  `item_type_id` int(11) DEFAULT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item_name` (`item_name`),
  KEY `idx_item_active` (`is_active`),
  KEY `fk_inv_item_type` (`item_type_id`),
  CONSTRAINT `fk_inv_item_type` FOREIGN KEY (`item_type_id`) REFERENCES `inventory_item_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_items`
--

LOCK TABLES `inventory_items` WRITE;
/*!40000 ALTER TABLE `inventory_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_movements`
--

DROP TABLE IF EXISTS `inventory_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `movement_type` enum('in','out') NOT NULL,
  `quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `unit_price` decimal(14,2) DEFAULT NULL,
  `reference_type` varchar(30) NOT NULL DEFAULT 'manual',
  `reference_id` int(11) DEFAULT NULL,
  `building_id` int(11) DEFAULT NULL,
  `floor_id` int(11) DEFAULT NULL,
  `apartment_id` int(11) DEFAULT NULL,
  `is_project_wide` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `moved_by` int(11) DEFAULT NULL,
  `person_name` varchar(160) DEFAULT NULL,
  `movement_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mv_item` (`item_id`),
  KEY `idx_mv_type` (`movement_type`),
  KEY `idx_mv_date` (`movement_date`),
  CONSTRAINT `inventory_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_movements`
--

LOCK TABLES `inventory_movements` WRITE;
/*!40000 ALTER TABLE `inventory_movements` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_purchase_requests`
--

DROP TABLE IF EXISTS `inventory_purchase_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_purchase_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(160) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `needed_by_date` date DEFAULT NULL,
  `unit` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','fulfilled') NOT NULL DEFAULT 'pending',
  `requested_by` int(11) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_req_status` (`status`),
  KEY `fk_inv_req_item` (`item_id`),
  CONSTRAINT `fk_inv_req_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_purchase_requests`
--

LOCK TABLES `inventory_purchase_requests` WRITE;
/*!40000 ALTER TABLE `inventory_purchase_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_purchase_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_purchases`
--

DROP TABLE IF EXISTS `inventory_purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(16,2) NOT NULL DEFAULT 0.00,
  `vendor` varchar(160) DEFAULT NULL,
  `ordered_by_name` varchar(160) DEFAULT NULL,
  `invoice_no` varchar(80) DEFAULT NULL,
  `invoice_file` varchar(255) DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_purch_item` (`item_id`),
  KEY `idx_purch_date` (`purchase_date`),
  CONSTRAINT `inventory_purchases_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_purchases`
--

LOCK TABLES `inventory_purchases` WRITE;
/*!40000 ALTER TABLE `inventory_purchases` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_purchases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `measurements`
--

DROP TABLE IF EXISTS `measurements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `measurements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `building_id` int(11) NOT NULL,
  `floor_id` int(11) NOT NULL,
  `apartment_id` int(11) DEFAULT NULL,
  `work_type_id` int(11) NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(12,2) DEFAULT NULL,
  `measurement_date` date NOT NULL,
  `measured_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `is_general_measurement` tinyint(1) DEFAULT 0,
  `floor_area` decimal(10,2) DEFAULT NULL,
  `measurement_type` enum('general','specific') DEFAULT 'specific',
  `status` enum('draft','medium','accepted','approved','rejected') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `building_id` (`building_id`),
  KEY `floor_id` (`floor_id`),
  KEY `apartment_id` (`apartment_id`),
  KEY `work_type_id` (`work_type_id`),
  KEY `measured_by` (`measured_by`),
  CONSTRAINT `measurements_ibfk_1` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`),
  CONSTRAINT `measurements_ibfk_2` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`id`),
  CONSTRAINT `measurements_ibfk_3` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`id`),
  CONSTRAINT `measurements_ibfk_4` FOREIGN KEY (`work_type_id`) REFERENCES `work_types` (`id`),
  CONSTRAINT `measurements_ibfk_5` FOREIGN KEY (`measured_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `measurements`
--

LOCK TABLES `measurements` WRITE;
/*!40000 ALTER TABLE `measurements` DISABLE KEYS */;
/*!40000 ALTER TABLE `measurements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `action_label` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (4,'data_entry','Data Entry','Access','Can enter measurement data','2026-03-26 19:08:51'),(5,'user_management','User Management','Access','Can manage users','2026-03-26 19:08:51'),(6,'project_settings','Project Settings','Access','Can configure project settings','2026-03-26 19:08:51'),(7,'slfa','Slfa','Access',NULL,'2026-04-04 14:35:52'),(9,'inventory.view','Inventory','View','View storage/inventory pages and data','2026-07-04 07:13:06'),(10,'inventory.create','Inventory','Create','Add catalogue items/item types and submit purchase requests','2026-07-04 07:13:06'),(11,'inventory.edit','Inventory','Edit','Edit catalogue items/item types','2026-07-04 07:13:06'),(12,'inventory.delete','Inventory','Delete','Remove catalogue items/item types','2026-07-04 07:13:06'),(13,'inventory.approve','Inventory','Approve','Approve a pending purchase request','2026-07-04 07:13:06'),(14,'inventory.reject','Inventory','Reject','Reject a pending purchase request','2026-07-04 07:13:06'),(15,'inventory.receive_stock','Inventory','Receive Stock','Record a purchase / stock in','2026-07-04 07:13:06'),(16,'inventory.issue_stock','Inventory','Issue Stock','Issue stock out to a location','2026-07-04 07:13:06'),(17,'inventory.mark_delivered','Inventory','Mark as Delivered','Mark an approved request as delivered to storage','2026-07-04 07:13:06'),(18,'inventory.export','Inventory','Export','Export inventory data (CSV)','2026-07-04 07:13:06'),(19,'stakeholders','Stakeholders','Access','Can manage project stakeholders','2026-07-04 09:05:59'),(20,'analytics','Analytics','Access','Can view the analytics/analysis page','2026-07-04 09:05:59'),(21,'hr','HR','Access','Can access the HR management module','2026-07-11 10:09:28');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_settings`
--

DROP TABLE IF EXISTS `project_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_settings`
--

LOCK TABLES `project_settings` WRITE;
/*!40000 ALTER TABLE `project_settings` DISABLE KEYS */;
INSERT INTO `project_settings` VALUES (1,'project_name','Green World Towers','2026-03-26 16:29:34','2026-03-26 16:29:34'),(2,'project_description','Construction project managed by Dahenkar Company','2026-03-26 16:29:34','2026-03-26 16:29:34'),(3,'num_buildings','3','2026-03-26 16:29:34','2026-03-26 16:29:34');
/*!40000 ALTER TABLE `project_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_stakeholder_subparts`
--

DROP TABLE IF EXISTS `project_stakeholder_subparts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_stakeholder_subparts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_id` int(11) NOT NULL,
  `subpart_name` varchar(160) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `metric_type` varchar(30) NOT NULL DEFAULT 'm²',
  `currency_type` varchar(20) NOT NULL DEFAULT 'USD',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stakeholder_subpart` (`stakeholder_id`,`subpart_name`),
  KEY `idx_stakeholder` (`stakeholder_id`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_stakeholder_subparts`
--

LOCK TABLES `project_stakeholder_subparts` WRITE;
/*!40000 ALTER TABLE `project_stakeholder_subparts` DISABLE KEYS */;
INSERT INTO `project_stakeholder_subparts` VALUES (1,1,'ئەرزی مطبخ',8.00,'m²','USD',1,'2026-07-13 07:56:01','2026-07-14 13:43:43'),(2,1,'ئەرزی کۆریدۆر',8.00,'m²','USD',1,'2026-07-13 07:57:17','2026-07-14 13:43:32'),(3,1,'ئەرزی حمام',8.00,'m²','USD',1,'2026-07-13 07:57:38','2026-07-14 13:43:22'),(4,1,'ئەرزی بەلەکۆن',8.00,'m²','USD',1,'2026-07-13 07:58:05','2026-07-14 13:42:51'),(5,1,'ئیزارەی مطبخ',4.00,'m','USD',1,'2026-07-13 08:00:21','2026-07-14 13:44:19'),(6,1,'ئیزارەی کۆریدۆر',4.00,'m','USD',1,'2026-07-13 08:00:44','2026-07-14 13:44:09'),(7,1,'ئیزارەی بەلەکۆن',4.00,'m','USD',1,'2026-07-13 08:01:05','2026-07-14 13:43:55'),(8,2,'دیوار حمام',17.00,'m²','USD',1,'2026-07-13 08:04:14','2026-07-13 08:04:14'),(9,2,'ئەرزی حمام',17.00,'m²','USD',1,'2026-07-13 08:04:33','2026-07-13 08:04:33'),(10,2,'ئەرزی مطبخ',17.00,'m²','USD',1,'2026-07-13 08:04:51','2026-07-13 08:04:51'),(11,2,'ئەرزی بەلەکۆن',17.00,'m²','USD',1,'2026-07-13 08:05:06','2026-07-13 08:05:06'),(12,2,'ئەرزی کۆریدۆر',17.00,'m²','USD',1,'2026-07-13 08:05:24','2026-07-13 08:05:24'),(13,2,'ژووری کارەبا و میکانیک',17.00,'m²','USD',1,'2026-07-13 08:09:37','2026-07-13 08:09:37'),(14,2,'دیواری واجهەی مسعدی نهۆمەکان',26.00,'m²','USD',1,'2026-07-13 08:12:34','2026-07-13 08:12:34'),(15,2,'ژێر محەجەرە',13.00,'m²','USD',1,'2026-07-13 08:13:04','2026-07-13 08:13:04'),(16,2,'ئیزارەی مطبخ',6.00,'m','USD',1,'2026-07-13 08:13:24','2026-07-13 08:13:24'),(17,2,'ئیزارەی کۆریدۆر',6.00,'m','USD',1,'2026-07-13 08:13:40','2026-07-13 08:13:40'),(18,2,'ئیزارەی بەلەکۆن',6.00,'m','USD',1,'2026-07-13 08:14:11','2026-07-13 08:14:11'),(19,2,'ئەرزی لۆبییەکان',25.00,'m²','USD',1,'2026-07-13 08:14:55','2026-07-13 08:14:55'),(20,2,'واجهەی مسعدی لۆبی',25.00,'m²','USD',1,'2026-07-13 08:15:35','2026-07-13 08:15:35'),(21,2,'کاشی ئەرزی پێش تاوەرەکان',8.00,'m²','USD',1,'2026-07-13 08:16:39','2026-07-13 08:16:39'),(22,2,'کاشی ئەرزی دووکانەکان',17.00,'m²','USD',1,'2026-07-13 08:19:15','2026-07-13 08:19:15'),(23,2,'کاشی ئەرزی ژوورە خەدەمییەکان',17.00,'m²','USD',1,'2026-07-13 08:19:53','2026-07-13 08:19:53'),(26,3,'مانتۆسڤا - سەقف - قەلەکیم',6.00,'m²','USD',1,'2026-07-13 08:32:51','2026-07-13 08:35:12'),(27,3,'مانتۆسڤا - سەقف - دیکۆرەتیڤ',5.00,'m²','USD',1,'2026-07-13 08:33:17','2026-07-13 08:33:17'),(28,3,'مانتۆسڤا - دیوار - کەفمال',16.00,'m²','USD',1,'2026-07-13 08:34:01','2026-07-13 08:34:01'),(29,3,'مانتۆسڤا - دیوار - دیکۆرەتیڤ',6.00,'m²','USD',1,'2026-07-13 08:35:59','2026-07-13 08:35:59'),(30,3,'مانتۆسڤا - کارە ئیزافییەکان',45000.00,'per_apartment','USD',1,'2026-07-13 08:39:51','2026-07-13 08:39:51'),(31,4,'کەوەنتەر - بەرزی تا سەقف',200.00,'m','USD',1,'2026-07-13 08:43:19','2026-07-13 08:43:19'),(32,4,'کەوەنتەر - بەرزی ستاندارد',185.00,'m','USD',1,'2026-07-13 08:43:47','2026-07-13 08:44:11'),(39,9,'گێچکاری - سەقف',5000.00,'m²','IQD',1,'2026-07-13 09:47:19','2026-07-13 09:57:11'),(40,9,'گێچکاری - دیوار - کەفمال',3500.00,'m²','IQD',1,'2026-07-13 09:47:51','2026-07-13 09:56:56'),(41,9,'گێچکاری - دیوار - مخمر',1500.00,'m²','IQD',1,'2026-07-13 09:57:55','2026-07-13 09:57:55'),(42,9,'گێچکاری - سەقف - تەئمینات',1500.00,'m²','IQD',1,'2026-07-13 09:58:33','2026-07-13 09:58:33'),(43,9,'گێچکاری - دیوار - تەئمینات',1500.00,'m²','IQD',1,'2026-07-13 09:59:01','2026-07-13 09:59:01'),(44,9,'گێچکاری - فەرقی کەفمال و مخمر',500.00,'m²','IQD',1,'2026-07-13 10:00:43','2026-07-13 10:00:43'),(45,9,'A1گێچکاری - تەسلیحات',25000.00,'per_apartment','IQD',1,'2026-07-13 10:05:58','2026-07-13 10:20:57'),(46,9,'A2گێچکاری - تەسلیحات',45000.00,'per_apartment','IQD',1,'2026-07-13 10:10:04','2026-07-13 10:21:12'),(47,10,'گێچکاری - سەقف',3500.00,'m²','IQD',1,'2026-07-13 10:13:42','2026-07-13 10:13:42'),(48,10,'گێچکاری - دیوار - کەفمال',2500.00,'m²','IQD',1,'2026-07-13 10:14:22','2026-07-13 10:14:22'),(49,10,'گێچکاری - دیوار - مخمر',1000.00,'m²','IQD',1,'2026-07-13 10:14:50','2026-07-13 10:14:50'),(50,10,'A3گێچکاری تەسلیحات',45000.00,'per_apartment','IQD',1,'2026-07-13 10:16:06','2026-07-13 10:22:27'),(51,11,'چوارچێوەی پەنجەرە',3.75,'m','USD',1,'2026-07-13 10:37:06','2026-07-13 10:37:06'),(52,11,'بەرمیلی زبڵ',35.00,'m','USD',1,'2026-07-13 10:38:52','2026-07-13 10:38:52'),(53,12,'لەبخی دیوار - حمام',8000.00,'m²','IQD',1,'2026-07-14 09:33:20','2026-07-14 09:34:11'),(54,12,'لەبخی  ژووری کارەبا و میکانیک',8000.00,'m²','IQD',1,'2026-07-14 09:34:43','2026-07-14 09:34:43'),(55,12,'لەبخی کارە ئیزافییەکان',8000.00,'m²','IQD',1,'2026-07-14 09:35:09','2026-07-14 09:35:09'),(56,13,'جپسم بۆرد + معجون و سمپارە',14.00,'m²','USD',1,'2026-07-15 14:21:39','2026-07-15 14:21:39'),(57,13,'بۆردێکس + معجون و سمپارە',20.00,'m²','USD',1,'2026-07-15 14:32:06','2026-07-15 14:32:06'),(58,13,'بۆردێکسی قەپات کردنی تەنیشت جبهە جام',20.00,'m²','USD',1,'2026-07-16 11:18:24','2026-07-16 11:18:24');
/*!40000 ALTER TABLE `project_stakeholder_subparts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_stakeholders`
--

DROP TABLE IF EXISTS `project_stakeholders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_stakeholders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_name` varchar(160) NOT NULL,
  `stakeholder_date` date DEFAULT NULL,
  `work_type_key` varchar(80) NOT NULL,
  `cash_percentage` decimal(5,2) NOT NULL DEFAULT 100.00,
  `apartment_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `apartment_meter_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `contract_file` varchar(255) DEFAULT NULL,
  `access_token` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stakeholder_work` (`stakeholder_name`,`work_type_key`),
  UNIQUE KEY `access_token` (`access_token`),
  KEY `idx_work_type` (`work_type_key`),
  KEY `idx_access_token` (`access_token`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_stakeholders`
--

LOCK TABLES `project_stakeholders` WRITE;
/*!40000 ALTER TABLE `project_stakeholders` DISABLE KEYS */;
INSERT INTO `project_stakeholders` VALUES (1,'Evan Jalal Hamza','2026-07-13','ceramic_work',100.00,0.00,0.00,NULL,'38d2046c011c0fa176c89f7cea122392d2386f45dbbe83365bae91e85c5d608f',1,7,'2026-07-13 07:52:57','2026-07-13 07:52:57'),(2,'Muhamad Xala','2026-07-13','ceramic_work',50.00,50.00,700.00,NULL,'49a737d10e72cffaa1615e742c298b7379bea6d878401578eda100e0100d03c8',1,7,'2026-07-13 08:03:38','2026-07-13 08:03:38'),(3,'Sarkar Sabah Haqi','2026-07-13','exterior_plastering_work_mantosva',50.00,50.00,700.00,NULL,'46446f2fc9c7bd5e471c55957d5efb23b5c6d72b81940c56f17fcb3f40b22a94',1,7,'2026-07-13 08:28:43','2026-07-13 08:28:43'),(4,'Sarkar Sabah Haqi','2026-07-13','wooden_cabinet_work',50.00,50.00,700.00,NULL,'c0a8df7404bca5528564e7b44f469c83240af5d6cf1602a5d8585bc7dadd489a',1,7,'2026-07-13 08:42:01','2026-07-13 08:42:01'),(9,'Mevan Bwrhan Hakim(A1-A2)','2026-07-13','gypsum_plastering_work',100.00,0.00,0.00,NULL,'53455cc5a069bb05fe0c4443cddba6022cbfdf9dc53b702192f04c63594c90bf',1,7,'2026-07-13 09:45:54','2026-07-13 09:45:54'),(10,'Mevan Bwrhan Hakim(A3-B1-B2)','2026-07-13','gypsum_plastering_work',50.00,50.00,700.00,NULL,'372c799ba660bca23c0c6dca005b1d0aa172fb509b588b2b60081e391063e267',1,7,'2026-07-13 10:11:43','2026-07-13 10:12:23'),(11,'Akar Bestun Abdulqadr','2026-07-13','window_frame_work',100.00,0.00,0.00,NULL,'ea0cd154f3c18218758ae7e432e8105e433b3b63b50f2f130597f3ede1182598',1,7,'2026-07-13 10:36:19','2026-07-13 10:36:19'),(12,'Abdulrahman Hussein Abdulrahman','2026-07-14','cement_plastering',50.00,50.00,700.00,NULL,'6038f6bcbe0e1438152bcddd5034439d8358725df719bb72a16695aa81af3f52',1,7,'2026-07-14 09:32:32','2026-07-14 09:32:32'),(13,'Khalid Muslim Musa (A1-A2-A3)','2026-07-15','gypsum_board_work',50.00,50.00,700.00,NULL,'c9836f9e2d68c14e1c71c1f093e7eb12d0df167dc23cb0f25c290db2b862bd76',1,7,'2026-07-15 14:17:16','2026-07-15 14:24:47');
/*!40000 ALTER TABLE `project_stakeholders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_work_entries`
--

DROP TABLE IF EXISTS `project_work_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_work_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_date` date NOT NULL,
  `engineer_name` varchar(180) NOT NULL,
  `work_type_key` varchar(80) NOT NULL,
  `stakeholder_id` int(11) DEFAULT NULL,
  `subpart_id` int(11) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `metric_type` varchar(30) NOT NULL DEFAULT 'unit',
  `currency_type` varchar(20) NOT NULL DEFAULT 'USD',
  `building_id` int(11) NOT NULL,
  `floor_id` int(11) NOT NULL,
  `apartment_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `slfa_payment_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status_changed_by` int(11) DEFAULT NULL,
  `status_changed_at` datetime DEFAULT NULL,
  `previous_status` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_work_type_key` (`work_type_key`),
  KEY `idx_apartment` (`apartment_id`),
  KEY `idx_work_date` (`work_date`),
  KEY `idx_slfa_payment` (`slfa_payment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=430 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_work_entries`
--

LOCK TABLES `project_work_entries` WRITE;
/*!40000 ALTER TABLE `project_work_entries` DISABLE KEYS */;
INSERT INTO `project_work_entries` VALUES (48,'2026-07-14','Himdad','cement_plastering',12,53,330.00,8000.00,2640000.00,'m²','IQD',1,21,0,'','accepted',3,7,'2026-07-14 09:40:45','2026-07-14 10:08:08',NULL,NULL,NULL),(49,'2026-07-14','Himdad','cement_plastering',12,53,332.23,8000.00,2657840.00,'m²','IQD',2,44,0,'','accepted',3,7,'2026-07-14 09:41:46','2026-07-14 10:08:08',NULL,NULL,NULL),(50,'2026-07-14','Himdad','cement_plastering',12,53,318.00,8000.00,2544000.00,'m²','IQD',3,47,0,'','accepted',3,7,'2026-07-14 09:42:49','2026-07-14 10:08:08',NULL,NULL,NULL),(51,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,48,0,'','accepted',3,7,'2026-07-14 09:43:38','2026-07-14 10:08:08',NULL,NULL,NULL),(52,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,49,0,'','accepted',3,7,'2026-07-14 09:44:14','2026-07-14 10:08:08',NULL,NULL,NULL),(53,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,50,0,'','accepted',3,7,'2026-07-14 09:44:34','2026-07-14 10:08:08',NULL,NULL,NULL),(54,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,51,0,'','accepted',3,7,'2026-07-14 09:44:53','2026-07-14 10:08:08',NULL,NULL,NULL),(55,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,52,0,'','accepted',3,7,'2026-07-14 09:45:13','2026-07-14 10:08:08',NULL,NULL,NULL),(56,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,53,0,'','accepted',3,7,'2026-07-14 09:45:36','2026-07-14 10:08:08',NULL,NULL,NULL),(57,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,54,0,'','accepted',3,7,'2026-07-14 09:45:55','2026-07-14 10:08:08',NULL,NULL,NULL),(58,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,55,0,'','accepted',3,7,'2026-07-14 09:46:18','2026-07-14 10:08:08',NULL,NULL,NULL),(59,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,56,0,'','accepted',3,7,'2026-07-14 09:46:37','2026-07-14 10:08:08',NULL,NULL,NULL),(60,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,57,0,'','accepted',3,7,'2026-07-14 09:46:53','2026-07-14 10:08:08',NULL,NULL,NULL),(61,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,58,0,'','accepted',3,7,'2026-07-14 09:47:13','2026-07-14 10:08:08',NULL,NULL,NULL),(62,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,59,0,'','accepted',3,7,'2026-07-14 09:47:28','2026-07-14 10:08:08',NULL,NULL,NULL),(63,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,60,0,'','accepted',3,7,'2026-07-14 09:47:44','2026-07-14 10:08:08',NULL,NULL,NULL),(64,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,61,0,'','accepted',3,7,'2026-07-14 09:48:03','2026-07-14 10:08:08',NULL,NULL,NULL),(65,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,62,0,'','accepted',3,7,'2026-07-14 09:48:18','2026-07-14 10:08:08',NULL,NULL,NULL),(66,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,63,0,'','accepted',3,7,'2026-07-14 09:48:43','2026-07-14 10:08:08',NULL,NULL,NULL),(67,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,64,0,'','accepted',3,7,'2026-07-14 09:49:03','2026-07-14 10:08:08',NULL,NULL,NULL),(68,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,65,0,'','accepted',3,7,'2026-07-14 09:49:20','2026-07-14 10:08:08',NULL,NULL,NULL),(69,'2026-07-14','Himdad','cement_plastering',12,53,318.60,8000.00,2548800.00,'m²','IQD',3,66,0,'','accepted',3,7,'2026-07-14 09:49:39','2026-07-14 10:08:08',NULL,NULL,NULL),(70,'2026-07-14','Himdad','cement_plastering',12,53,331.43,8000.00,2651440.00,'m²','IQD',3,67,0,'','accepted',3,7,'2026-07-14 09:50:13','2026-07-14 10:08:08',NULL,NULL,NULL),(71,'2026-07-14','Himdad','cement_plastering',12,53,376.90,8000.00,3015200.00,'m²','IQD',4,70,0,'','accepted',4,7,'2026-07-14 10:13:01','2026-07-14 11:52:19',NULL,NULL,NULL),(72,'2026-07-14','Himdad','cement_plastering',12,53,376.90,8000.00,3015200.00,'m²','IQD',4,71,0,'','accepted',4,7,'2026-07-14 10:13:32','2026-07-14 11:52:19',NULL,NULL,NULL),(73,'2026-07-14','Himdad','cement_plastering',12,53,376.90,8000.00,3015200.00,'m²','IQD',4,72,0,'','accepted',4,7,'2026-07-14 10:13:50','2026-07-14 11:52:19',NULL,NULL,NULL),(74,'2026-07-14','Himdad','cement_plastering',12,53,376.90,8000.00,3015200.00,'m²','IQD',4,73,0,'','accepted',4,7,'2026-07-14 10:14:48','2026-07-14 11:52:19',NULL,NULL,NULL),(75,'2026-07-14','Himdad','cement_plastering',12,53,376.80,8000.00,3014400.00,'m²','IQD',4,74,0,'','accepted',4,7,'2026-07-14 10:15:17','2026-07-14 11:52:19',NULL,NULL,NULL),(76,'2026-07-14','Himdad','cement_plastering',12,53,376.80,8000.00,3014400.00,'m²','IQD',4,75,0,'','accepted',4,7,'2026-07-14 10:15:39','2026-07-14 11:52:19',NULL,NULL,NULL),(77,'2026-07-14','Himdad','cement_plastering',12,53,376.80,8000.00,3014400.00,'m²','IQD',4,76,0,'','accepted',4,7,'2026-07-14 10:16:02','2026-07-14 11:52:19',NULL,NULL,NULL),(78,'2026-07-14','Himdad','cement_plastering',12,53,376.80,8000.00,3014400.00,'m²','IQD',4,77,0,'','accepted',4,7,'2026-07-14 10:16:17','2026-07-14 11:52:19',NULL,NULL,NULL),(79,'2026-07-14','Himdad','cement_plastering',12,53,376.80,8000.00,3014400.00,'m²','IQD',4,78,0,'','accepted',4,7,'2026-07-14 10:16:42','2026-07-14 11:52:19',NULL,NULL,NULL),(80,'2026-07-14','Himdad','cement_plastering',12,53,376.80,8000.00,3014400.00,'m²','IQD',4,79,0,'','accepted',4,7,'2026-07-14 10:16:55','2026-07-14 11:52:19',NULL,NULL,NULL),(81,'2026-07-14','Himdad','cement_plastering',12,53,376.80,8000.00,3014400.00,'m²','IQD',4,80,0,'','accepted',4,7,'2026-07-14 10:17:16','2026-07-14 11:52:19',NULL,NULL,NULL),(82,'2026-07-14','Himdad','cement_plastering',12,53,376.80,8000.00,3014400.00,'m²','IQD',4,81,0,'','accepted',4,7,'2026-07-14 10:17:40','2026-07-14 11:52:19',NULL,NULL,NULL),(83,'2026-07-14','Himdad','cement_plastering',12,53,397.65,8000.00,3181200.00,'m²','IQD',4,82,0,'','accepted',4,7,'2026-07-14 10:18:07','2026-07-14 11:52:19',NULL,NULL,NULL),(84,'2026-07-14','Himdad','cement_plastering',12,53,397.65,8000.00,3181200.00,'m²','IQD',4,83,0,'','accepted',4,7,'2026-07-14 10:18:32','2026-07-14 11:52:19',NULL,NULL,NULL),(85,'2026-07-14','Himdad','cement_plastering',12,53,397.65,8000.00,3181200.00,'m²','IQD',4,84,0,'','accepted',4,7,'2026-07-14 10:19:00','2026-07-14 11:52:19',NULL,NULL,NULL),(86,'2026-07-14','Himdad','cement_plastering',12,53,397.65,8000.00,3181200.00,'m²','IQD',4,86,0,'','accepted',4,7,'2026-07-14 10:19:17','2026-07-14 11:52:19',NULL,NULL,NULL),(87,'2026-07-14','Himdad','cement_plastering',12,53,397.65,8000.00,3181200.00,'m²','IQD',4,87,0,'','accepted',4,7,'2026-07-14 10:19:35','2026-07-14 11:52:19',NULL,NULL,NULL),(88,'2026-07-14','Himdad','cement_plastering',12,53,397.65,8000.00,3181200.00,'m²','IQD',4,88,0,'','accepted',4,7,'2026-07-14 10:19:55','2026-07-14 11:52:19',NULL,NULL,NULL),(89,'2026-07-14','Himdad','cement_plastering',12,53,397.65,8000.00,3181200.00,'m²','IQD',4,89,0,'','accepted',4,7,'2026-07-14 10:20:32','2026-07-14 11:52:19',NULL,NULL,NULL),(90,'2026-07-14','Himdad','cement_plastering',12,53,397.65,8000.00,3181200.00,'m²','IQD',4,90,0,'','accepted',4,7,'2026-07-14 10:20:54','2026-07-14 11:52:19',NULL,NULL,NULL),(91,'2026-07-14','Himdad','cement_plastering',12,53,397.65,8000.00,3181200.00,'m²','IQD',4,91,0,'','accepted',4,7,'2026-07-14 10:21:27','2026-07-14 11:52:19',NULL,NULL,NULL),(92,'2026-07-14','Himdad','cement_plastering',12,53,338.28,8000.00,2706240.00,'m²','IQD',5,94,0,'','accepted',4,7,'2026-07-14 10:22:22','2026-07-14 11:52:19',NULL,NULL,NULL),(93,'2026-07-14','Himdad','cement_plastering',12,53,338.28,8000.00,2706240.00,'m²','IQD',5,95,0,'','accepted',4,7,'2026-07-14 10:22:43','2026-07-14 11:52:19',NULL,NULL,NULL),(94,'2026-07-14','Himdad','cement_plastering',12,53,337.67,8000.00,2701360.00,'m²','IQD',5,96,0,'','accepted',4,7,'2026-07-14 10:23:09','2026-07-14 11:52:19',NULL,NULL,NULL),(95,'2026-07-14','Himdad','cement_plastering',12,53,337.67,8000.00,2701360.00,'m²','IQD',5,97,0,'','accepted',4,7,'2026-07-14 10:23:26','2026-07-14 11:52:19',NULL,NULL,NULL),(96,'2026-07-14','Himdad','cement_plastering',12,53,396.82,8000.00,3174560.00,'m²','IQD',5,98,0,'','accepted',4,7,'2026-07-14 10:24:30','2026-07-14 11:52:19',NULL,NULL,NULL),(97,'2026-07-14','Himdad','cement_plastering',12,53,396.82,8000.00,3174560.00,'m²','IQD',5,99,0,'','accepted',4,7,'2026-07-14 10:25:44','2026-07-14 11:52:19',NULL,NULL,NULL),(98,'2026-07-14','Himdad','cement_plastering',12,53,359.52,8000.00,2876160.00,'m²','IQD',5,100,0,'','accepted',4,7,'2026-07-14 10:27:58','2026-07-14 11:52:19',NULL,NULL,NULL),(100,'2026-07-14','Himdad','cement_plastering',12,53,394.13,8000.00,3153040.00,'m²','IQD',5,101,0,'','accepted',4,7,'2026-07-14 10:33:03','2026-07-14 11:52:19',NULL,NULL,NULL),(101,'2026-07-14','Himdad','cement_plastering',12,53,394.13,8000.00,3153040.00,'m²','IQD',5,102,0,'','accepted',4,7,'2026-07-14 10:34:33','2026-07-14 11:52:19',NULL,NULL,NULL),(102,'2026-07-14','Himdad','cement_plastering',12,53,394.13,8000.00,3153040.00,'m²','IQD',5,103,0,'','accepted',4,7,'2026-07-14 10:34:53','2026-07-14 11:52:19',NULL,NULL,NULL),(103,'2026-07-14','Himdad','cement_plastering',12,53,398.64,8000.00,3189120.00,'m²','IQD',5,104,0,'','accepted',4,7,'2026-07-14 10:35:56','2026-07-14 11:52:19',NULL,NULL,NULL),(104,'2026-07-14','Himdad','cement_plastering',12,53,398.64,8000.00,3189120.00,'m²','IQD',5,105,0,'','accepted',4,7,'2026-07-14 10:36:11','2026-07-14 11:52:19',NULL,NULL,NULL),(105,'2026-07-14','Himdad','cement_plastering',12,53,398.64,8000.00,3189120.00,'m²','IQD',5,106,0,'','accepted',4,7,'2026-07-14 10:36:28','2026-07-14 11:52:19',NULL,NULL,NULL),(106,'2026-07-14','Himdad','cement_plastering',12,53,398.64,8000.00,3189120.00,'m²','IQD',5,107,0,'','accepted',4,7,'2026-07-14 10:36:47','2026-07-14 11:52:19',NULL,NULL,NULL),(107,'2026-07-14','Himdad','cement_plastering',12,53,398.64,8000.00,3189120.00,'m²','IQD',5,108,0,'','accepted',4,7,'2026-07-14 10:41:45','2026-07-14 11:52:19',NULL,NULL,NULL),(108,'2026-07-14','Himdad','cement_plastering',12,53,397.17,8000.00,3177360.00,'m²','IQD',5,109,0,'','accepted',4,7,'2026-07-14 10:42:12','2026-07-14 11:52:19',NULL,NULL,NULL),(109,'2026-07-14','Himdad','cement_plastering',12,53,397.17,8000.00,3177360.00,'m²','IQD',5,110,0,'','accepted',4,7,'2026-07-14 10:43:29','2026-07-14 11:52:19',NULL,NULL,NULL),(110,'2026-07-14','Himdad','cement_plastering',12,53,397.17,8000.00,3177360.00,'m²','IQD',5,111,0,'','accepted',4,7,'2026-07-14 10:43:47','2026-07-14 11:52:19',NULL,NULL,NULL),(111,'2026-07-14','Himdad','cement_plastering',12,53,397.17,8000.00,3177360.00,'m²','IQD',5,112,0,'','accepted',4,7,'2026-07-14 10:44:57','2026-07-14 11:52:19',NULL,NULL,NULL),(112,'2026-07-14','Himdad','cement_plastering',12,53,397.17,8000.00,3177360.00,'m²','IQD',5,113,0,'','accepted',4,7,'2026-07-14 10:45:23','2026-07-14 11:52:19',NULL,NULL,NULL),(113,'2026-07-14','Himdad','cement_plastering',12,53,397.17,8000.00,3177360.00,'m²','IQD',5,114,0,'','accepted',4,7,'2026-07-14 10:45:41','2026-07-14 11:52:19',NULL,NULL,NULL),(114,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,1,0,'','accepted',4,7,'2026-07-14 10:50:24','2026-07-14 11:52:19',NULL,NULL,NULL),(115,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,2,0,'','accepted',4,7,'2026-07-14 10:50:52','2026-07-14 11:52:19',NULL,NULL,NULL),(116,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,3,0,'','accepted',4,7,'2026-07-14 10:51:08','2026-07-14 11:52:19',NULL,NULL,NULL),(117,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,4,0,'','accepted',4,7,'2026-07-14 10:51:29','2026-07-14 11:52:19',NULL,NULL,NULL),(118,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,5,0,'','accepted',4,7,'2026-07-14 10:51:52','2026-07-14 11:52:19',NULL,NULL,NULL),(119,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,6,0,'','accepted',4,7,'2026-07-14 10:52:11','2026-07-14 11:52:19',NULL,NULL,NULL),(120,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,7,0,'','accepted',4,7,'2026-07-14 10:52:30','2026-07-14 11:52:19',NULL,NULL,NULL),(121,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,8,0,'','accepted',4,7,'2026-07-14 10:52:44','2026-07-14 11:52:19',NULL,NULL,NULL),(122,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,9,0,'','accepted',4,7,'2026-07-14 10:53:13','2026-07-14 11:52:19',NULL,NULL,NULL),(123,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,10,0,'','accepted',4,7,'2026-07-14 10:53:27','2026-07-14 11:52:19',NULL,NULL,NULL),(124,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,11,0,'','accepted',4,7,'2026-07-14 10:53:43','2026-07-14 11:52:19',NULL,NULL,NULL),(125,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,12,0,'','accepted',4,7,'2026-07-14 10:53:56','2026-07-14 11:52:19',NULL,NULL,NULL),(126,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,13,0,'','accepted',4,7,'2026-07-14 10:54:13','2026-07-14 11:52:19',NULL,NULL,NULL),(127,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,14,0,'','accepted',4,7,'2026-07-14 10:54:27','2026-07-14 11:52:19',NULL,NULL,NULL),(128,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,15,0,'','accepted',4,7,'2026-07-14 10:54:42','2026-07-14 11:52:19',NULL,NULL,NULL),(129,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,16,0,'','accepted',4,7,'2026-07-14 10:54:57','2026-07-14 11:52:19',NULL,NULL,NULL),(130,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,17,0,'','accepted',4,7,'2026-07-14 10:55:15','2026-07-14 11:52:19',NULL,NULL,NULL),(131,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,18,0,'','accepted',4,7,'2026-07-14 10:55:33','2026-07-14 11:52:19',NULL,NULL,NULL),(132,'2026-07-14','Himdad','cement_plastering',12,53,38.98,8000.00,311840.00,'m²','IQD',1,19,0,'','accepted',4,7,'2026-07-14 10:55:48','2026-07-14 11:52:19',NULL,NULL,NULL),(133,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,20,0,'','accepted',4,7,'2026-07-14 10:57:19','2026-07-14 11:52:19',NULL,NULL,NULL),(134,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',1,21,0,'','accepted',4,7,'2026-07-14 10:57:42','2026-07-14 11:52:19',NULL,NULL,NULL),(135,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,24,0,'','accepted',4,7,'2026-07-14 10:58:04','2026-07-14 11:52:19',NULL,NULL,NULL),(136,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,25,0,'','accepted',4,7,'2026-07-14 10:58:21','2026-07-14 11:52:19',NULL,NULL,NULL),(137,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,26,0,'','accepted',4,7,'2026-07-14 10:58:34','2026-07-14 11:52:19',NULL,NULL,NULL),(138,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,27,0,'','accepted',4,7,'2026-07-14 10:59:17','2026-07-14 11:52:19',NULL,NULL,NULL),(139,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,28,0,'','accepted',4,7,'2026-07-14 10:59:52','2026-07-14 11:52:19',NULL,NULL,NULL),(140,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,29,0,'','accepted',4,7,'2026-07-14 11:00:08','2026-07-14 11:52:19',NULL,NULL,NULL),(141,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,30,0,'','accepted',4,7,'2026-07-14 11:00:22','2026-07-14 11:52:19',NULL,NULL,NULL),(142,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,31,0,'','accepted',4,7,'2026-07-14 11:00:36','2026-07-14 11:52:19',NULL,NULL,NULL),(143,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,32,0,'','accepted',4,7,'2026-07-14 11:00:50','2026-07-14 11:52:19',NULL,NULL,NULL),(144,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,33,0,'','accepted',4,7,'2026-07-14 11:01:03','2026-07-14 11:52:19',NULL,NULL,NULL),(145,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,34,0,'','accepted',4,7,'2026-07-14 11:01:20','2026-07-14 11:52:19',NULL,NULL,NULL),(146,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,35,0,'','accepted',4,7,'2026-07-14 11:01:48','2026-07-14 11:52:19',NULL,NULL,NULL),(147,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,36,0,'','accepted',4,7,'2026-07-14 11:02:06','2026-07-14 11:52:19',NULL,NULL,NULL),(148,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,37,0,'','accepted',4,7,'2026-07-14 11:02:20','2026-07-14 11:52:19',NULL,NULL,NULL),(149,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,38,0,'','accepted',4,7,'2026-07-14 11:02:34','2026-07-14 11:52:19',NULL,NULL,NULL),(150,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,39,0,'','accepted',4,7,'2026-07-14 11:02:49','2026-07-14 11:52:19',NULL,NULL,NULL),(151,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,40,0,'','accepted',4,7,'2026-07-14 11:03:02','2026-07-14 11:52:19',NULL,NULL,NULL),(152,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,41,0,'','accepted',4,7,'2026-07-14 11:03:15','2026-07-14 11:52:19',NULL,NULL,NULL),(153,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,42,0,'','accepted',4,7,'2026-07-14 11:03:29','2026-07-14 11:52:19',NULL,NULL,NULL),(154,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,43,0,'','accepted',4,7,'2026-07-14 11:04:00','2026-07-14 11:52:19',NULL,NULL,NULL),(155,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',2,44,0,'','accepted',4,7,'2026-07-14 11:04:14','2026-07-14 11:52:19',NULL,NULL,NULL),(156,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,47,0,'','accepted',4,7,'2026-07-14 11:05:32','2026-07-14 11:52:19',NULL,NULL,NULL),(157,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,48,0,'','accepted',4,7,'2026-07-14 11:05:52','2026-07-14 11:52:19',NULL,NULL,NULL),(158,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,49,0,'','accepted',4,7,'2026-07-14 11:10:25','2026-07-14 11:52:19',NULL,NULL,NULL),(159,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,60,0,'','accepted',4,7,'2026-07-14 11:11:04','2026-07-14 11:52:19',NULL,NULL,NULL),(160,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,59,0,'','accepted',4,7,'2026-07-14 11:13:24','2026-07-14 11:52:19',NULL,NULL,NULL),(161,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,58,0,'','accepted',4,7,'2026-07-14 11:13:44','2026-07-14 11:52:19',NULL,NULL,NULL),(162,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,57,0,'','accepted',4,7,'2026-07-14 11:14:09','2026-07-14 11:52:19',NULL,NULL,NULL),(163,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,56,0,'','accepted',4,7,'2026-07-14 11:15:37','2026-07-14 11:52:19',NULL,NULL,NULL),(164,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,55,0,'','accepted',4,7,'2026-07-14 11:16:33','2026-07-14 11:52:19',NULL,NULL,NULL),(165,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,54,0,'','accepted',4,7,'2026-07-14 11:17:10','2026-07-14 11:52:19',NULL,NULL,NULL),(166,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,53,0,'','accepted',4,7,'2026-07-14 11:17:33','2026-07-14 11:52:19',NULL,NULL,NULL),(167,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,52,0,'','accepted',4,7,'2026-07-14 11:17:52','2026-07-14 11:52:19',NULL,NULL,NULL),(168,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,63,0,'','accepted',4,7,'2026-07-14 11:18:23','2026-07-14 11:52:19',NULL,NULL,NULL),(169,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,62,0,'','accepted',4,7,'2026-07-14 11:18:44','2026-07-14 11:52:19',NULL,NULL,NULL),(170,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,61,0,'','accepted',4,7,'2026-07-14 11:19:07','2026-07-14 11:52:19',NULL,NULL,NULL),(171,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,64,0,'','accepted',4,7,'2026-07-14 11:19:45','2026-07-14 11:52:19',NULL,NULL,NULL),(172,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,65,0,'','accepted',4,7,'2026-07-14 11:20:03','2026-07-14 11:52:19',NULL,NULL,NULL),(173,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,66,0,'','accepted',4,7,'2026-07-14 11:20:18','2026-07-14 11:52:19',NULL,NULL,NULL),(174,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,67,0,'','accepted',4,7,'2026-07-14 11:20:35','2026-07-14 11:52:19',NULL,NULL,NULL),(175,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,51,0,'','accepted',5,7,'2026-07-14 12:01:09','2026-07-14 12:01:43',NULL,NULL,NULL),(176,'2026-07-14','Himdad','cement_plastering',12,54,38.98,8000.00,311840.00,'m²','IQD',3,50,0,'','accepted',5,7,'2026-07-14 12:01:30','2026-07-14 12:01:43',NULL,NULL,NULL),(177,'2026-07-14','Himdad','ceramic_work',1,3,56.40,8.00,451.20,'m²','USD',1,1,0,'','accepted',6,7,'2026-07-14 13:45:31','2026-07-14 13:56:19',NULL,NULL,NULL),(178,'2026-07-14','Himdad','ceramic_work',1,3,57.33,8.00,458.64,'m²','USD',1,2,0,'','accepted',6,7,'2026-07-14 13:45:52','2026-07-14 13:56:19',NULL,NULL,NULL),(179,'2026-07-14','Himdad','ceramic_work',1,3,57.33,8.00,458.64,'m²','USD',1,3,0,'','accepted',6,7,'2026-07-14 13:46:21','2026-07-14 13:56:19',NULL,NULL,NULL),(180,'2026-07-14','Himdad','ceramic_work',1,3,57.33,8.00,458.64,'m²','USD',1,4,0,'','accepted',6,7,'2026-07-14 13:46:47','2026-07-14 13:56:19',NULL,NULL,NULL),(181,'2026-07-14','Himdad','ceramic_work',1,3,53.60,8.00,428.80,'m²','USD',1,5,0,'','accepted',6,7,'2026-07-14 13:47:23','2026-07-14 13:56:19',NULL,NULL,NULL),(182,'2026-07-14','Himdad','ceramic_work',1,3,57.33,8.00,458.64,'m²','USD',1,6,0,'','accepted',6,7,'2026-07-14 13:47:47','2026-07-14 13:56:19',NULL,NULL,NULL),(183,'2026-07-14','Himdad','ceramic_work',1,3,57.33,8.00,458.64,'m²','USD',1,7,0,'','accepted',6,7,'2026-07-14 13:48:14','2026-07-14 13:56:19',NULL,NULL,NULL),(184,'2026-07-14','Himdad','ceramic_work',1,3,61.08,8.00,488.64,'m²','USD',1,8,0,'','accepted',6,7,'2026-07-14 13:48:40','2026-07-14 13:56:19',NULL,NULL,NULL),(185,'2026-07-14','Himdad','ceramic_work',1,3,57.37,8.00,458.96,'m²','USD',1,9,0,'','accepted',6,7,'2026-07-14 13:49:01','2026-07-14 13:56:19',NULL,NULL,NULL),(186,'2026-07-14','Himdad','ceramic_work',1,3,57.37,8.00,458.96,'m²','USD',1,10,0,'','accepted',6,7,'2026-07-14 13:49:19','2026-07-14 13:56:19',NULL,NULL,NULL),(187,'2026-07-14','Himdad','ceramic_work',1,3,57.95,8.00,463.60,'m²','USD',1,11,0,'','accepted',6,7,'2026-07-14 13:49:43','2026-07-14 13:56:19',NULL,NULL,NULL),(188,'2026-07-14','Himdad','ceramic_work',1,3,57.95,8.00,463.60,'m²','USD',1,12,0,'','accepted',6,7,'2026-07-14 13:50:04','2026-07-14 13:56:19',NULL,NULL,NULL),(189,'2026-07-14','Himdad','ceramic_work',1,3,57.95,8.00,463.60,'m²','USD',1,13,0,'','accepted',6,7,'2026-07-14 13:50:27','2026-07-14 13:56:19',NULL,NULL,NULL),(190,'2026-07-14','Himdad','ceramic_work',1,3,57.95,8.00,463.60,'m²','USD',1,14,0,'','accepted',6,7,'2026-07-14 13:50:52','2026-07-14 13:56:19',NULL,NULL,NULL),(191,'2026-07-14','Himdad','ceramic_work',1,3,57.95,8.00,463.60,'m²','USD',1,15,0,'','accepted',6,7,'2026-07-14 13:51:24','2026-07-14 13:56:19',NULL,NULL,NULL),(192,'2026-07-14','Himdad','ceramic_work',1,3,57.95,8.00,463.60,'m²','USD',1,16,0,'','accepted',6,7,'2026-07-14 13:52:09','2026-07-14 13:56:19',NULL,NULL,NULL),(193,'2026-07-14','Himdad','ceramic_work',1,3,57.95,8.00,463.60,'m²','USD',1,17,0,'','accepted',6,7,'2026-07-14 13:52:57','2026-07-14 13:56:19',NULL,NULL,NULL),(194,'2026-07-14','Himdad','ceramic_work',1,3,57.95,8.00,463.60,'m²','USD',1,18,0,'','accepted',6,7,'2026-07-14 13:53:24','2026-07-14 13:56:19',NULL,NULL,NULL),(195,'2026-07-14','Himdad','ceramic_work',1,1,54.53,8.00,436.24,'m²','USD',1,1,0,'','accepted',7,7,'2026-07-14 14:01:20','2026-07-14 14:12:11',NULL,NULL,NULL),(196,'2026-07-14','Himdad','ceramic_work',1,1,54.69,8.00,437.52,'m²','USD',1,2,0,'','accepted',7,7,'2026-07-14 14:01:38','2026-07-14 14:12:11',NULL,NULL,NULL),(197,'2026-07-14','Himdad','ceramic_work',1,1,54.69,8.00,437.52,'m²','USD',1,3,0,'','accepted',7,7,'2026-07-14 14:01:56','2026-07-14 14:12:11',NULL,NULL,NULL),(198,'2026-07-14','Himdad','ceramic_work',1,1,54.69,8.00,437.52,'m²','USD',1,4,0,'','accepted',7,7,'2026-07-14 14:02:14','2026-07-14 14:12:11',NULL,NULL,NULL),(199,'2026-07-14','Himdad','ceramic_work',1,1,54.69,8.00,437.52,'m²','USD',1,5,0,'','accepted',7,7,'2026-07-14 14:02:33','2026-07-14 14:12:11',NULL,NULL,NULL),(201,'2026-07-14','Himdad','ceramic_work',1,1,54.62,8.00,436.96,'m²','USD',1,7,0,'','accepted',7,7,'2026-07-14 14:03:14','2026-07-14 14:12:11',NULL,NULL,NULL),(202,'2026-07-14','Himdad','ceramic_work',1,1,50.41,8.00,403.28,'m²','USD',1,8,0,'','accepted',7,7,'2026-07-14 14:03:33','2026-07-14 14:12:11',NULL,NULL,NULL),(203,'2026-07-14','Himdad','ceramic_work',1,1,54.62,8.00,436.96,'m²','USD',1,9,0,'','accepted',7,7,'2026-07-14 14:03:59','2026-07-14 14:12:11',NULL,NULL,NULL),(204,'2026-07-14','Himdad','ceramic_work',1,1,54.62,8.00,436.96,'m²','USD',1,10,0,'','accepted',7,7,'2026-07-14 14:04:19','2026-07-14 14:12:11',NULL,NULL,NULL),(205,'2026-07-14','Himdad','ceramic_work',1,1,54.76,8.00,438.08,'m²','USD',1,11,0,'','accepted',7,7,'2026-07-14 14:04:34','2026-07-14 14:12:11',NULL,NULL,NULL),(206,'2026-07-14','Himdad','ceramic_work',1,1,54.76,8.00,438.08,'m²','USD',1,12,0,'','accepted',7,7,'2026-07-14 14:04:51','2026-07-14 14:12:11',NULL,NULL,NULL),(207,'2026-07-14','Himdad','ceramic_work',1,1,54.76,8.00,438.08,'m²','USD',1,13,0,'','accepted',7,7,'2026-07-14 14:05:12','2026-07-14 14:12:11',NULL,NULL,NULL),(208,'2026-07-14','Himdad','ceramic_work',1,1,54.76,8.00,438.08,'m²','USD',1,14,0,'','accepted',7,7,'2026-07-14 14:05:38','2026-07-14 14:12:11',NULL,NULL,NULL),(209,'2026-07-14','Himdad','ceramic_work',1,1,54.76,8.00,438.08,'m²','USD',1,15,0,'','accepted',7,7,'2026-07-14 14:05:58','2026-07-14 14:12:11',NULL,NULL,NULL),(210,'2026-07-14','Himdad','ceramic_work',1,1,54.76,8.00,438.08,'m²','USD',1,16,0,'','accepted',7,7,'2026-07-14 14:06:20','2026-07-14 14:12:11',NULL,NULL,NULL),(211,'2026-07-14','Himdad','ceramic_work',1,1,54.76,8.00,438.08,'m²','USD',1,17,0,'','accepted',7,7,'2026-07-14 14:06:41','2026-07-14 14:12:11',NULL,NULL,NULL),(212,'2026-07-14','Himdad','ceramic_work',1,1,54.76,8.00,438.08,'m²','USD',1,18,0,'','accepted',7,7,'2026-07-14 14:07:01','2026-07-14 14:12:11',NULL,NULL,NULL),(213,'2026-07-14','Himdad','ceramic_work',1,1,54.62,8.00,436.96,'m²','USD',1,6,0,'','accepted',7,7,'2026-07-14 14:11:49','2026-07-14 14:12:11',NULL,NULL,NULL),(214,'2026-07-14','Himdad','ceramic_work',1,2,54.86,8.00,438.88,'m²','USD',1,1,0,'','accepted',8,7,'2026-07-14 14:13:33','2026-07-14 14:28:44',NULL,NULL,NULL),(215,'2026-07-14','Himdad','ceramic_work',1,2,54.73,8.00,437.84,'m²','USD',1,2,0,'','accepted',8,7,'2026-07-14 14:13:52','2026-07-14 14:28:44',NULL,NULL,NULL),(216,'2026-07-14','Himdad','ceramic_work',1,2,54.73,8.00,437.84,'m²','USD',1,3,0,'','accepted',8,7,'2026-07-14 14:14:12','2026-07-14 14:28:44',NULL,NULL,NULL),(217,'2026-07-14','Himdad','ceramic_work',1,2,54.73,8.00,437.84,'m²','USD',1,4,0,'','accepted',8,7,'2026-07-14 14:14:38','2026-07-14 14:28:44',NULL,NULL,NULL),(218,'2026-07-14','Himdad','ceramic_work',1,2,54.73,8.00,437.84,'m²','USD',1,5,0,'','accepted',8,7,'2026-07-14 14:14:57','2026-07-14 14:28:44',NULL,NULL,NULL),(219,'2026-07-14','Himdad','ceramic_work',1,2,54.67,8.00,437.36,'m²','USD',1,6,0,'','accepted',8,7,'2026-07-14 14:15:34','2026-07-14 14:28:44',NULL,NULL,NULL),(220,'2026-07-14','Himdad','ceramic_work',1,2,54.67,8.00,437.36,'m²','USD',1,7,0,'','accepted',8,7,'2026-07-14 14:15:51','2026-07-14 14:28:44',NULL,NULL,NULL),(221,'2026-07-14','Himdad','ceramic_work',1,2,54.67,8.00,437.36,'m²','USD',1,8,0,'','accepted',8,7,'2026-07-14 14:16:18','2026-07-14 14:28:44',NULL,NULL,NULL),(222,'2026-07-14','Himdad','ceramic_work',1,2,54.67,8.00,437.36,'m²','USD',1,9,0,'','accepted',8,7,'2026-07-14 14:16:41','2026-07-14 14:28:44',NULL,NULL,NULL),(223,'2026-07-14','Himdad','ceramic_work',1,2,54.67,8.00,437.36,'m²','USD',1,10,0,'','accepted',8,7,'2026-07-14 14:16:58','2026-07-14 14:28:44',NULL,NULL,NULL),(224,'2026-07-14','Himdad','ceramic_work',1,2,54.54,8.00,436.32,'m²','USD',1,11,0,'','accepted',8,7,'2026-07-14 14:17:34','2026-07-14 14:28:44',NULL,NULL,NULL),(225,'2026-07-14','Himdad','ceramic_work',1,2,54.54,8.00,436.32,'m²','USD',1,12,0,'','accepted',8,7,'2026-07-14 14:17:57','2026-07-14 14:28:44',NULL,NULL,NULL),(226,'2026-07-14','Himdad','ceramic_work',1,2,54.54,8.00,436.32,'m²','USD',1,13,0,'','accepted',8,7,'2026-07-14 14:25:32','2026-07-14 14:28:44',NULL,NULL,NULL),(227,'2026-07-14','Himdad','ceramic_work',1,2,54.54,8.00,436.32,'m²','USD',1,14,0,'','accepted',8,7,'2026-07-14 14:26:16','2026-07-14 14:28:44',NULL,NULL,NULL),(228,'2026-07-14','Himdad','ceramic_work',1,2,54.54,8.00,436.32,'m²','USD',1,15,0,'','accepted',8,7,'2026-07-14 14:26:33','2026-07-14 14:28:44',NULL,NULL,NULL),(229,'2026-07-14','Himdad','ceramic_work',1,2,54.54,8.00,436.32,'m²','USD',1,16,0,'','accepted',8,7,'2026-07-14 14:26:49','2026-07-14 14:28:44',NULL,NULL,NULL),(230,'2026-07-14','Himdad','ceramic_work',1,2,54.54,8.00,436.32,'m²','USD',1,17,0,'','accepted',8,7,'2026-07-14 14:27:09','2026-07-14 14:28:44',NULL,NULL,NULL),(231,'2026-07-14','Himdad','ceramic_work',1,2,54.54,8.00,436.32,'m²','USD',1,18,0,'','accepted',8,7,'2026-07-14 14:27:28','2026-07-14 14:28:44',NULL,NULL,NULL),(232,'2026-07-14','Himdad','ceramic_work',1,4,145.04,8.00,1160.32,'m²','USD',1,1,0,'','accepted',9,7,'2026-07-14 14:31:05','2026-07-15 07:54:54',NULL,NULL,NULL),(233,'2026-07-14','Himdad','ceramic_work',1,4,109.72,8.00,877.76,'m²','USD',1,2,0,'','accepted',9,7,'2026-07-14 14:31:23','2026-07-15 07:54:54',NULL,NULL,NULL),(234,'2026-07-14','Himdad','ceramic_work',1,4,125.64,8.00,1005.12,'m²','USD',1,3,0,'','accepted',9,7,'2026-07-14 14:31:44','2026-07-15 07:54:54',NULL,NULL,NULL),(235,'2026-07-14','Himdad','ceramic_work',1,4,109.72,8.00,877.76,'m²','USD',1,4,0,'','accepted',9,7,'2026-07-14 14:32:57','2026-07-15 07:54:54',NULL,NULL,NULL),(236,'2026-07-14','Himdad','ceramic_work',1,4,125.64,8.00,1005.12,'m²','USD',1,5,0,'','accepted',9,7,'2026-07-14 14:33:18','2026-07-15 07:54:54',NULL,NULL,NULL),(237,'2026-07-15','Himdad','ceramic_work',1,4,109.72,8.00,877.76,'m²','USD',1,6,0,'','accepted',9,7,'2026-07-15 07:49:30','2026-07-15 07:54:54',NULL,NULL,NULL),(238,'2026-07-15','Himdad','ceramic_work',1,4,125.64,8.00,1005.12,'m²','USD',1,7,0,'','accepted',9,7,'2026-07-15 07:49:53','2026-07-15 07:54:54',NULL,NULL,NULL),(239,'2026-07-15','Himdad','ceramic_work',1,4,109.72,8.00,877.76,'m²','USD',1,8,0,'','accepted',9,7,'2026-07-15 07:50:19','2026-07-15 07:54:54',NULL,NULL,NULL),(240,'2026-07-15','Himdad','ceramic_work',1,4,109.72,8.00,877.76,'m²','USD',1,9,0,'','accepted',9,7,'2026-07-15 07:50:43','2026-07-15 07:54:54',NULL,NULL,NULL),(241,'2026-07-15','Himdad','ceramic_work',1,4,130.81,8.00,1046.48,'m²','USD',1,10,0,'','accepted',9,7,'2026-07-15 07:51:02','2026-07-15 07:54:54',NULL,NULL,NULL),(242,'2026-07-15','Himdad','ceramic_work',1,4,116.54,8.00,932.32,'m²','USD',1,11,0,'','accepted',9,7,'2026-07-15 07:51:31','2026-07-15 07:54:54',NULL,NULL,NULL),(243,'2026-07-15','Himdad','ceramic_work',1,4,116.54,8.00,932.32,'m²','USD',1,12,0,'','accepted',9,7,'2026-07-15 07:51:49','2026-07-15 07:54:54',NULL,NULL,NULL),(244,'2026-07-15','Himdad','ceramic_work',1,4,116.54,8.00,932.32,'m²','USD',1,13,0,'','accepted',9,7,'2026-07-15 07:52:08','2026-07-15 07:54:54',NULL,NULL,NULL),(245,'2026-07-15','Himdad','ceramic_work',1,4,172.33,8.00,1378.64,'m²','USD',1,14,0,'','accepted',9,7,'2026-07-15 07:52:29','2026-07-15 07:54:54',NULL,NULL,NULL),(246,'2026-07-15','Himdad','ceramic_work',1,4,140.47,8.00,1123.76,'m²','USD',1,15,0,'','accepted',9,7,'2026-07-15 07:52:50','2026-07-15 07:54:54',NULL,NULL,NULL),(247,'2026-07-15','Himdad','ceramic_work',1,4,129.46,8.00,1035.68,'m²','USD',1,16,0,'','accepted',9,7,'2026-07-15 07:53:13','2026-07-15 07:54:54',NULL,NULL,NULL),(248,'2026-07-15','Himdad','ceramic_work',1,4,129.46,8.00,1035.68,'m²','USD',1,17,0,'','accepted',9,7,'2026-07-15 07:53:31','2026-07-15 07:54:54',NULL,NULL,NULL),(249,'2026-07-15','Himdad','ceramic_work',1,4,129.46,8.00,1035.68,'m²','USD',1,18,0,'','accepted',9,7,'2026-07-15 07:53:47','2026-07-15 07:54:54',NULL,NULL,NULL),(250,'2026-07-15','Himdad','ceramic_work',1,5,57.73,4.00,230.92,'m','USD',1,1,0,'','accepted',10,7,'2026-07-15 07:56:40','2026-07-15 08:43:59',NULL,NULL,NULL),(251,'2026-07-15','Himdad','ceramic_work',1,5,57.91,4.00,231.64,'m','USD',1,2,0,'','accepted',10,7,'2026-07-15 07:57:01','2026-07-15 08:43:59',NULL,NULL,NULL),(252,'2026-07-15','Himdad','ceramic_work',1,5,57.91,4.00,231.64,'m','USD',1,3,0,'','accepted',10,7,'2026-07-15 07:57:24','2026-07-15 08:43:59',NULL,NULL,NULL),(253,'2026-07-15','Himdad','ceramic_work',1,5,57.91,4.00,231.64,'m','USD',1,4,0,'','accepted',10,7,'2026-07-15 07:57:50','2026-07-15 08:43:59',NULL,NULL,NULL),(254,'2026-07-15','Himdad','ceramic_work',1,5,57.91,4.00,231.64,'m','USD',1,5,0,'','accepted',10,7,'2026-07-15 07:58:10','2026-07-15 08:43:59',NULL,NULL,NULL),(255,'2026-07-15','Himdad','ceramic_work',1,5,57.88,4.00,231.52,'m','USD',1,6,0,'','accepted',10,7,'2026-07-15 07:58:33','2026-07-15 08:43:59',NULL,NULL,NULL),(256,'2026-07-15','Himdad','ceramic_work',1,5,57.88,4.00,231.52,'m','USD',1,7,0,'','accepted',10,7,'2026-07-15 07:59:16','2026-07-15 08:43:59',NULL,NULL,NULL),(257,'2026-07-15','Himdad','ceramic_work',1,5,56.31,4.00,225.24,'m','USD',1,8,0,'','accepted',10,7,'2026-07-15 07:59:38','2026-07-15 08:43:59',NULL,NULL,NULL),(258,'2026-07-15','Himdad','ceramic_work',1,5,57.88,4.00,231.52,'m','USD',1,9,0,'','accepted',10,7,'2026-07-15 08:00:07','2026-07-15 08:43:59',NULL,NULL,NULL),(259,'2026-07-15','Himdad','ceramic_work',1,5,57.88,4.00,231.52,'m','USD',1,10,0,'','accepted',10,7,'2026-07-15 08:09:44','2026-07-15 08:43:59',NULL,NULL,NULL),(260,'2026-07-15','Himdad','ceramic_work',1,5,58.00,4.00,232.00,'m','USD',1,11,0,'','accepted',10,7,'2026-07-15 08:10:09','2026-07-15 08:43:59',NULL,NULL,NULL),(261,'2026-07-15','Himdad','ceramic_work',1,5,58.00,4.00,232.00,'m','USD',1,12,0,'','accepted',10,7,'2026-07-15 08:10:39','2026-07-15 08:43:59',NULL,NULL,NULL),(262,'2026-07-15','Himdad','ceramic_work',1,5,58.00,4.00,232.00,'m','USD',1,13,0,'','accepted',10,7,'2026-07-15 08:10:57','2026-07-15 08:43:59',NULL,NULL,NULL),(263,'2026-07-15','Himdad','ceramic_work',1,5,58.00,4.00,232.00,'m','USD',1,14,0,'','accepted',10,7,'2026-07-15 08:11:11','2026-07-15 08:43:59',NULL,NULL,NULL),(264,'2026-07-15','Himdad','ceramic_work',1,5,58.00,4.00,232.00,'m','USD',1,15,0,'','accepted',10,7,'2026-07-15 08:11:24','2026-07-15 08:43:59',NULL,NULL,NULL),(265,'2026-07-15','Himdad','ceramic_work',1,5,58.00,4.00,232.00,'m','USD',1,16,0,'','accepted',10,7,'2026-07-15 08:11:38','2026-07-15 08:43:59',NULL,NULL,NULL),(266,'2026-07-15','Himdad','ceramic_work',1,5,58.00,4.00,232.00,'m','USD',1,17,0,'','accepted',10,7,'2026-07-15 08:11:58','2026-07-15 08:43:59',NULL,NULL,NULL),(268,'2026-07-15','Himdad','ceramic_work',1,6,49.15,4.00,196.60,'m','USD',1,1,0,'','accepted',10,7,'2026-07-15 08:13:21','2026-07-15 08:43:59',NULL,NULL,NULL),(269,'2026-07-15','Himdad','ceramic_work',1,6,48.97,4.00,195.88,'m','USD',1,2,0,'','accepted',10,7,'2026-07-15 08:13:43','2026-07-15 08:43:59',NULL,NULL,NULL),(270,'2026-07-15','Himdad','ceramic_work',1,6,48.97,4.00,195.88,'m','USD',1,3,0,'','accepted',10,7,'2026-07-15 08:14:48','2026-07-15 08:43:59',NULL,NULL,NULL),(271,'2026-07-15','Himdad','ceramic_work',1,6,48.97,4.00,195.88,'m','USD',1,4,0,'','accepted',10,7,'2026-07-15 08:15:15','2026-07-15 08:43:59',NULL,NULL,NULL),(272,'2026-07-15','Himdad','ceramic_work',1,6,48.97,4.00,195.88,'m','USD',1,5,0,'','accepted',10,7,'2026-07-15 08:16:07','2026-07-15 08:43:59',NULL,NULL,NULL),(273,'2026-07-15','Himdad','ceramic_work',1,6,48.72,4.00,194.88,'m','USD',1,6,0,'','accepted',10,7,'2026-07-15 08:16:43','2026-07-15 08:43:59',NULL,NULL,NULL),(274,'2026-07-15','Himdad','ceramic_work',1,6,48.72,4.00,194.88,'m','USD',1,7,0,'','accepted',10,7,'2026-07-15 08:17:11','2026-07-15 08:43:59',NULL,NULL,NULL),(275,'2026-07-15','Himdad','ceramic_work',1,6,48.72,4.00,194.88,'m','USD',1,8,0,'','accepted',10,7,'2026-07-15 08:17:31','2026-07-15 08:43:59',NULL,NULL,NULL),(276,'2026-07-15','Himdad','ceramic_work',1,6,48.72,4.00,194.88,'m','USD',1,9,0,'','accepted',10,7,'2026-07-15 08:17:53','2026-07-15 08:43:59',NULL,NULL,NULL),(277,'2026-07-15','Himdad','ceramic_work',1,6,48.72,4.00,194.88,'m','USD',1,10,0,'','accepted',10,7,'2026-07-15 08:18:18','2026-07-15 08:43:59',NULL,NULL,NULL),(278,'2026-07-15','Himdad','ceramic_work',1,6,48.55,4.00,194.20,'m','USD',1,11,0,'','accepted',10,7,'2026-07-15 08:18:38','2026-07-15 08:43:59',NULL,NULL,NULL),(279,'2026-07-15','Himdad','ceramic_work',1,6,48.55,4.00,194.20,'m','USD',1,12,0,'','accepted',10,7,'2026-07-15 08:19:06','2026-07-15 08:43:59',NULL,NULL,NULL),(280,'2026-07-15','Himdad','ceramic_work',1,6,48.55,4.00,194.20,'m','USD',1,13,0,'','accepted',10,7,'2026-07-15 08:19:22','2026-07-15 08:43:59',NULL,NULL,NULL),(281,'2026-07-15','Himdad','ceramic_work',1,6,48.55,4.00,194.20,'m','USD',1,14,0,'','accepted',10,7,'2026-07-15 08:19:37','2026-07-15 08:43:59',NULL,NULL,NULL),(282,'2026-07-15','Himdad','ceramic_work',1,6,48.55,4.00,194.20,'m','USD',1,15,0,'','accepted',10,7,'2026-07-15 08:20:29','2026-07-15 08:43:59',NULL,NULL,NULL),(283,'2026-07-15','Himdad','ceramic_work',1,6,48.55,4.00,194.20,'m','USD',1,16,0,'','accepted',10,7,'2026-07-15 08:20:44','2026-07-15 08:43:59',NULL,NULL,NULL),(284,'2026-07-15','Himdad','ceramic_work',1,6,48.55,4.00,194.20,'m','USD',1,17,0,'','accepted',10,7,'2026-07-15 08:20:58','2026-07-15 08:43:59',NULL,NULL,NULL),(285,'2026-07-15','Himdad','ceramic_work',1,6,48.55,4.00,194.20,'m','USD',1,18,0,'','accepted',10,7,'2026-07-15 08:21:11','2026-07-15 08:43:59',NULL,NULL,NULL),(286,'2026-07-15','Himdad','ceramic_work',1,5,58.00,4.00,232.00,'m','USD',1,18,0,'','accepted',10,7,'2026-07-15 08:30:35','2026-07-15 08:43:59',NULL,NULL,NULL),(287,'2026-07-15','Himdad','ceramic_work',1,7,160.70,4.00,642.80,'m','USD',1,1,0,'','accepted',10,7,'2026-07-15 08:31:29','2026-07-15 08:43:59',NULL,NULL,NULL),(288,'2026-07-15','Himdad','ceramic_work',1,7,141.08,4.00,564.32,'m','USD',1,2,0,'','accepted',10,7,'2026-07-15 08:31:52','2026-07-15 08:43:59',NULL,NULL,NULL),(289,'2026-07-15','Himdad','ceramic_work',1,7,153.70,4.00,614.80,'m','USD',1,3,0,'','accepted',10,7,'2026-07-15 08:35:00','2026-07-15 08:43:59',NULL,NULL,NULL),(291,'2026-07-15','Himdad','ceramic_work',1,7,141.08,4.00,564.32,'m','USD',1,4,0,'','accepted',10,7,'2026-07-15 08:35:23','2026-07-15 08:43:59',NULL,NULL,NULL),(292,'2026-07-15','Himdad','ceramic_work',1,7,153.70,4.00,614.80,'m','USD',1,5,0,'','accepted',10,7,'2026-07-15 08:36:14','2026-07-15 08:43:59',NULL,NULL,NULL),(293,'2026-07-15','Himdad','ceramic_work',1,7,141.08,4.00,564.32,'m','USD',1,6,0,'','accepted',10,7,'2026-07-15 08:36:38','2026-07-15 08:43:59',NULL,NULL,NULL),(294,'2026-07-15','Himdad','ceramic_work',1,7,153.70,4.00,614.80,'m','USD',1,7,0,'','accepted',10,7,'2026-07-15 08:37:00','2026-07-15 08:43:59',NULL,NULL,NULL),(295,'2026-07-15','Himdad','ceramic_work',1,7,141.08,4.00,564.32,'m','USD',1,8,0,'','accepted',10,7,'2026-07-15 08:37:21','2026-07-15 08:43:59',NULL,NULL,NULL),(296,'2026-07-15','Himdad','ceramic_work',1,7,141.08,4.00,564.32,'m','USD',1,9,0,'','accepted',10,7,'2026-07-15 08:37:42','2026-07-15 08:43:59',NULL,NULL,NULL),(297,'2026-07-15','Himdad','ceramic_work',1,7,158.67,4.00,634.68,'m','USD',1,10,0,'','accepted',10,7,'2026-07-15 08:38:01','2026-07-15 08:43:59',NULL,NULL,NULL),(298,'2026-07-15','Himdad','ceramic_work',1,7,137.16,4.00,548.64,'m','USD',1,11,0,'','accepted',10,7,'2026-07-15 08:38:19','2026-07-15 08:43:59',NULL,NULL,NULL),(299,'2026-07-15','Himdad','ceramic_work',1,7,137.16,4.00,548.64,'m','USD',1,12,0,'','accepted',10,7,'2026-07-15 08:38:51','2026-07-15 08:43:59',NULL,NULL,NULL),(300,'2026-07-15','Himdad','ceramic_work',1,7,137.16,4.00,548.64,'m','USD',1,13,0,'','accepted',10,7,'2026-07-15 08:39:10','2026-07-15 08:43:59',NULL,NULL,NULL),(301,'2026-07-15','Himdad','ceramic_work',1,7,185.93,4.00,743.72,'m','USD',1,14,0,'','accepted',10,7,'2026-07-15 08:39:30','2026-07-15 08:43:59',NULL,NULL,NULL),(302,'2026-07-15','Himdad','ceramic_work',1,7,166.02,4.00,664.08,'m','USD',1,15,0,'','accepted',10,7,'2026-07-15 08:40:57','2026-07-15 08:43:59',NULL,NULL,NULL),(303,'2026-07-15','Himdad','ceramic_work',1,7,157.94,4.00,631.76,'m','USD',1,16,0,'','accepted',10,7,'2026-07-15 08:41:16','2026-07-15 08:43:59',NULL,NULL,NULL),(304,'2026-07-15','Himdad','ceramic_work',1,7,157.94,4.00,631.76,'m','USD',1,17,0,'','accepted',10,7,'2026-07-15 08:41:33','2026-07-15 08:43:59',NULL,NULL,NULL),(305,'2026-07-15','Himdad','ceramic_work',1,7,157.94,4.00,631.76,'m','USD',1,18,0,'','accepted',10,7,'2026-07-15 08:41:49','2026-07-15 08:43:59',NULL,NULL,NULL),(306,'2026-07-16','Himdad','gypsum_board_work',13,56,424.87,14.00,5948.18,'m²','USD',1,1,0,'','accepted',11,7,'2026-07-16 10:02:51','2026-07-16 10:14:05',NULL,NULL,NULL),(307,'2026-07-16','Himdad','gypsum_board_work',13,56,425.30,14.00,5954.20,'m²','USD',1,2,0,'','accepted',11,7,'2026-07-16 10:03:16','2026-07-16 10:14:05',NULL,NULL,NULL),(308,'2026-07-16','Himdad','gypsum_board_work',13,56,425.81,14.00,5961.34,'m²','USD',1,3,0,'','accepted',11,7,'2026-07-16 10:03:39','2026-07-16 10:14:05',NULL,NULL,NULL),(309,'2026-07-16','Himdad','gypsum_board_work',13,56,425.30,14.00,5954.20,'m²','USD',1,4,0,'','accepted',11,7,'2026-07-16 10:03:59','2026-07-16 10:14:05',NULL,NULL,NULL),(310,'2026-07-16','Himdad','gypsum_board_work',13,56,428.86,14.00,6004.04,'m²','USD',1,5,0,'','accepted',11,7,'2026-07-16 10:04:21','2026-07-16 10:14:05',NULL,NULL,NULL),(311,'2026-07-16','Himdad','gypsum_board_work',13,56,426.98,14.00,5977.72,'m²','USD',1,6,0,'','accepted',11,7,'2026-07-16 10:04:42','2026-07-16 10:14:05',NULL,NULL,NULL),(312,'2026-07-16','Himdad','gypsum_board_work',13,56,427.61,14.00,5986.54,'m²','USD',1,7,0,'','accepted',11,7,'2026-07-16 10:05:03','2026-07-16 10:14:05',NULL,NULL,NULL),(313,'2026-07-16','Himdad','gypsum_board_work',13,56,422.82,14.00,5919.48,'m²','USD',1,8,0,'','accepted',11,7,'2026-07-16 10:05:19','2026-07-16 10:14:05',NULL,NULL,NULL),(314,'2026-07-16','Himdad','gypsum_board_work',13,56,427.14,14.00,5979.96,'m²','USD',1,9,0,'','accepted',11,7,'2026-07-16 10:05:52','2026-07-16 10:14:05',NULL,NULL,NULL),(315,'2026-07-16','Himdad','gypsum_board_work',13,56,427.14,14.00,5979.96,'m²','USD',1,10,0,'','accepted',11,7,'2026-07-16 10:06:22','2026-07-16 10:14:05',NULL,NULL,NULL),(316,'2026-07-16','Himdad','gypsum_board_work',13,56,427.05,14.00,5978.70,'m²','USD',1,11,0,'','accepted',11,7,'2026-07-16 10:06:38','2026-07-16 10:14:05',NULL,NULL,NULL),(317,'2026-07-16','Himdad','gypsum_board_work',13,56,427.05,14.00,5978.70,'m²','USD',1,12,0,'','accepted',11,7,'2026-07-16 10:07:06','2026-07-16 10:14:05',NULL,NULL,NULL),(318,'2026-07-16','Himdad','gypsum_board_work',13,56,425.67,14.00,5959.38,'m²','USD',1,13,0,'','accepted',11,7,'2026-07-16 10:07:39','2026-07-16 10:14:05',NULL,NULL,NULL),(319,'2026-07-16','Himdad','gypsum_board_work',13,56,426.34,14.00,5968.76,'m²','USD',1,14,0,'','accepted',11,7,'2026-07-16 10:08:02','2026-07-16 10:14:05',NULL,NULL,NULL),(320,'2026-07-16','Himdad','gypsum_board_work',13,56,425.44,14.00,5956.16,'m²','USD',1,15,0,'','accepted',11,7,'2026-07-16 10:08:19','2026-07-16 10:14:05',NULL,NULL,NULL),(321,'2026-07-16','Himdad','gypsum_board_work',13,56,425.44,14.00,5956.16,'m²','USD',1,16,0,'','accepted',11,7,'2026-07-16 10:08:41','2026-07-16 10:14:05',NULL,NULL,NULL),(322,'2026-07-16','Himdad','gypsum_board_work',13,56,425.44,14.00,5956.16,'m²','USD',1,17,0,'','accepted',11,7,'2026-07-16 10:09:01','2026-07-16 10:14:05',NULL,NULL,NULL),(323,'2026-07-16','Himdad','gypsum_board_work',13,56,425.44,14.00,5956.16,'m²','USD',1,18,0,'','accepted',11,7,'2026-07-16 10:09:22','2026-07-16 10:14:05',NULL,NULL,NULL),(324,'2026-07-16','Himdad','gypsum_board_work',13,56,425.44,14.00,5956.16,'m²','USD',1,19,0,'','accepted',11,7,'2026-07-16 10:09:41','2026-07-16 10:14:05',NULL,NULL,NULL),(325,'2026-07-16','Himdad','gypsum_board_work',13,56,425.44,14.00,5956.16,'m²','USD',1,20,0,'','accepted',11,7,'2026-07-16 10:10:18','2026-07-16 10:14:05',NULL,NULL,NULL),(326,'2026-07-16','Himdad','gypsum_board_work',13,56,513.85,14.00,7193.90,'m²','USD',1,21,0,'','accepted',11,7,'2026-07-16 10:10:51','2026-07-16 10:14:05',NULL,NULL,NULL),(327,'2026-07-16','Himdad','gypsum_board_work',13,56,421.92,14.00,5906.88,'m²','USD',2,24,0,'','accepted',12,7,'2026-07-16 10:14:41','2026-07-16 10:24:26',NULL,NULL,NULL),(328,'2026-07-16','Himdad','gypsum_board_work',13,56,423.21,14.00,5924.94,'m²','USD',2,25,0,'','accepted',12,7,'2026-07-16 10:15:03','2026-07-16 10:24:26',NULL,NULL,NULL),(329,'2026-07-16','Himdad','gypsum_board_work',13,56,423.21,14.00,5924.94,'m²','USD',2,26,0,'','accepted',12,7,'2026-07-16 10:15:25','2026-07-16 10:24:26',NULL,NULL,NULL),(330,'2026-07-16','Himdad','gypsum_board_work',13,56,424.11,14.00,5937.54,'m²','USD',2,27,0,'','accepted',12,7,'2026-07-16 10:15:44','2026-07-16 10:24:26',NULL,NULL,NULL),(331,'2026-07-16','Himdad','gypsum_board_work',13,56,423.20,14.00,5924.80,'m²','USD',2,28,0,'','accepted',12,7,'2026-07-16 10:16:02','2026-07-16 10:24:26',NULL,NULL,NULL),(332,'2026-07-16','Himdad','gypsum_board_work',13,56,425.16,14.00,5952.24,'m²','USD',2,29,0,'','accepted',12,7,'2026-07-16 10:16:19','2026-07-16 10:24:26',NULL,NULL,NULL),(333,'2026-07-16','Himdad','gypsum_board_work',13,56,424.70,14.00,5945.80,'m²','USD',2,30,0,'','accepted',12,7,'2026-07-16 10:16:55','2026-07-16 10:24:26',NULL,NULL,NULL),(334,'2026-07-16','Himdad','gypsum_board_work',13,56,424.70,14.00,5945.80,'m²','USD',2,31,0,'','accepted',12,7,'2026-07-16 10:17:58','2026-07-16 10:24:26',NULL,NULL,NULL),(335,'2026-07-16','Himdad','gypsum_board_work',13,56,424.70,14.00,5945.80,'m²','USD',2,32,0,'','accepted',12,7,'2026-07-16 10:18:26','2026-07-16 10:24:26',NULL,NULL,NULL),(336,'2026-07-16','Himdad','gypsum_board_work',13,56,424.25,14.00,5939.50,'m²','USD',2,33,0,'','accepted',12,7,'2026-07-16 10:18:45','2026-07-16 10:24:26',NULL,NULL,NULL),(337,'2026-07-16','Himdad','gypsum_board_work',13,56,425.32,14.00,5954.48,'m²','USD',2,34,0,'','accepted',12,7,'2026-07-16 10:19:10','2026-07-16 10:24:26',NULL,NULL,NULL),(338,'2026-07-16','Himdad','gypsum_board_work',13,56,425.32,14.00,5954.48,'m²','USD',2,35,0,'','accepted',12,7,'2026-07-16 10:20:46','2026-07-16 10:24:26',NULL,NULL,NULL),(339,'2026-07-16','Himdad','gypsum_board_work',13,56,426.13,14.00,5965.82,'m²','USD',2,36,0,'','accepted',12,7,'2026-07-16 10:21:01','2026-07-16 10:24:26',NULL,NULL,NULL),(340,'2026-07-16','Himdad','gypsum_board_work',13,56,424.84,14.00,5947.76,'m²','USD',2,37,0,'','accepted',12,7,'2026-07-16 10:21:36','2026-07-16 10:24:26',NULL,NULL,NULL),(341,'2026-07-16','Himdad','gypsum_board_work',13,56,425.28,14.00,5953.92,'m²','USD',2,38,0,'','accepted',12,7,'2026-07-16 10:21:52','2026-07-16 10:24:26',NULL,NULL,NULL),(342,'2026-07-16','Himdad','gypsum_board_work',13,56,425.28,14.00,5953.92,'m²','USD',2,39,0,'','accepted',12,7,'2026-07-16 10:22:05','2026-07-16 10:24:26',NULL,NULL,NULL),(343,'2026-07-16','Himdad','gypsum_board_work',13,56,425.28,14.00,5953.92,'m²','USD',2,40,0,'','accepted',12,7,'2026-07-16 10:22:25','2026-07-16 10:24:26',NULL,NULL,NULL),(344,'2026-07-16','Himdad','gypsum_board_work',13,56,425.28,14.00,5953.92,'m²','USD',2,41,0,'','accepted',12,7,'2026-07-16 10:22:39','2026-07-16 10:24:26',NULL,NULL,NULL),(345,'2026-07-16','Himdad','gypsum_board_work',13,56,425.28,14.00,5953.92,'m²','USD',2,42,0,'','accepted',12,7,'2026-07-16 10:22:52','2026-07-16 10:24:26',NULL,NULL,NULL),(346,'2026-07-16','Himdad','gypsum_board_work',13,56,425.28,14.00,5953.92,'m²','USD',2,43,0,'','accepted',12,7,'2026-07-16 10:23:04','2026-07-16 10:24:26',NULL,NULL,NULL),(347,'2026-07-16','Himdad','gypsum_board_work',13,56,426.91,14.00,5976.74,'m²','USD',3,47,0,'','accepted',13,7,'2026-07-16 10:24:56','2026-07-16 10:45:20',NULL,NULL,NULL),(348,'2026-07-16','Himdad','gypsum_board_work',13,56,428.16,14.00,5994.24,'m²','USD',3,48,0,'','accepted',13,7,'2026-07-16 10:25:15','2026-07-16 10:45:20',NULL,NULL,NULL),(349,'2026-07-16','Himdad','gypsum_board_work',13,56,427.72,14.00,5988.08,'m²','USD',3,49,0,'','accepted',13,7,'2026-07-16 10:25:32','2026-07-16 10:45:20',NULL,NULL,NULL),(350,'2026-07-16','Himdad','gypsum_board_work',13,56,428.64,14.00,6000.96,'m²','USD',3,50,0,'','accepted',13,7,'2026-07-16 10:25:50','2026-07-16 10:45:20',NULL,NULL,NULL),(351,'2026-07-16','Himdad','gypsum_board_work',13,56,427.72,14.00,5988.08,'m²','USD',3,51,0,'','accepted',13,7,'2026-07-16 10:26:14','2026-07-16 10:45:20',NULL,NULL,NULL),(352,'2026-07-16','Himdad','gypsum_board_work',13,56,429.48,14.00,6012.72,'m²','USD',3,52,0,'','accepted',13,7,'2026-07-16 10:26:37','2026-07-16 10:45:20',NULL,NULL,NULL),(353,'2026-07-16','Himdad','gypsum_board_work',13,56,429.46,14.00,6012.44,'m²','USD',3,53,0,'','accepted',13,7,'2026-07-16 10:26:54','2026-07-16 10:45:20',NULL,NULL,NULL),(354,'2026-07-16','Himdad','gypsum_board_work',13,56,429.14,14.00,6007.96,'m²','USD',3,54,0,'','accepted',13,7,'2026-07-16 10:29:01','2026-07-16 10:45:20',NULL,NULL,NULL),(355,'2026-07-16','Himdad','gypsum_board_work',13,56,429.46,14.00,6012.44,'m²','USD',3,55,0,'','accepted',13,7,'2026-07-16 10:29:23','2026-07-16 10:45:20',NULL,NULL,NULL),(356,'2026-07-16','Himdad','gypsum_board_work',13,56,434.13,14.00,6077.82,'m²','USD',3,56,0,'','accepted',13,7,'2026-07-16 10:29:41','2026-07-16 10:45:20',NULL,NULL,NULL),(357,'2026-07-16','Himdad','gypsum_board_work',13,56,431.52,14.00,6041.28,'m²','USD',3,57,0,'','accepted',13,7,'2026-07-16 10:29:57','2026-07-16 10:45:20',NULL,NULL,NULL),(358,'2026-07-16','Himdad','gypsum_board_work',13,56,431.52,14.00,6041.28,'m²','USD',3,58,0,'','accepted',13,7,'2026-07-16 10:30:15','2026-07-16 10:45:20',NULL,NULL,NULL),(359,'2026-07-16','Himdad','gypsum_board_work',13,56,431.06,14.00,6034.84,'m²','USD',3,59,0,'','accepted',13,7,'2026-07-16 10:30:38','2026-07-16 10:45:20',NULL,NULL,NULL),(360,'2026-07-16','Himdad','gypsum_board_work',13,56,431.52,14.00,6041.28,'m²','USD',3,60,0,'','accepted',13,7,'2026-07-16 10:31:32','2026-07-16 10:45:20',NULL,NULL,NULL),(361,'2026-07-16','Himdad','gypsum_board_work',13,56,431.06,14.00,6034.84,'m²','USD',3,61,0,'','accepted',13,7,'2026-07-16 10:32:19','2026-07-16 10:45:20',NULL,NULL,NULL),(362,'2026-07-16','Himdad','gypsum_board_work',13,56,435.17,14.00,6092.38,'m²','USD',3,62,0,'','accepted',13,7,'2026-07-16 10:32:50','2026-07-16 10:45:20',NULL,NULL,NULL),(363,'2026-07-16','Himdad','gypsum_board_work',13,56,431.06,14.00,6034.84,'m²','USD',3,63,0,'','accepted',13,7,'2026-07-16 10:33:05','2026-07-16 10:45:20',NULL,NULL,NULL),(364,'2026-07-16','Himdad','gypsum_board_work',13,56,431.06,14.00,6034.84,'m²','USD',3,64,0,'','accepted',13,7,'2026-07-16 10:33:37','2026-07-16 10:45:20',NULL,NULL,NULL),(365,'2026-07-16','Himdad','gypsum_board_work',13,56,431.06,14.00,6034.84,'m²','USD',3,65,0,'','accepted',13,7,'2026-07-16 10:34:08','2026-07-16 10:45:20',NULL,NULL,NULL),(366,'2026-07-16','Himdad','gypsum_board_work',13,56,431.06,14.00,6034.84,'m²','USD',3,66,0,'','accepted',13,7,'2026-07-16 10:36:24','2026-07-16 10:45:20',NULL,NULL,NULL),(367,'2026-07-16','Himdad','gypsum_board_work',13,57,41.15,20.00,823.00,'m²','USD',1,1,0,'','accepted',14,7,'2026-07-16 10:48:34','2026-07-16 11:15:53',NULL,NULL,NULL),(368,'2026-07-16','Himdad','gypsum_board_work',13,57,41.15,20.00,823.00,'m²','USD',1,2,0,'','accepted',14,7,'2026-07-16 10:48:58','2026-07-16 11:15:53',NULL,NULL,NULL),(369,'2026-07-16','Himdad','gypsum_board_work',13,57,41.15,20.00,823.00,'m²','USD',1,3,0,'','accepted',14,7,'2026-07-16 10:49:23','2026-07-16 11:15:53',NULL,NULL,NULL),(370,'2026-07-16','Himdad','gypsum_board_work',13,57,41.15,20.00,823.00,'m²','USD',1,4,0,'','accepted',14,7,'2026-07-16 10:49:39','2026-07-16 11:15:53',NULL,NULL,NULL),(371,'2026-07-16','Himdad','gypsum_board_work',13,57,37.13,20.00,742.60,'m²','USD',1,5,0,'','accepted',14,7,'2026-07-16 10:50:19','2026-07-16 11:15:53',NULL,NULL,NULL),(372,'2026-07-16','Himdad','gypsum_board_work',13,57,41.54,20.00,830.80,'m²','USD',1,6,0,'','accepted',14,7,'2026-07-16 10:50:39','2026-07-16 11:15:53',NULL,NULL,NULL),(373,'2026-07-16','Himdad','gypsum_board_work',13,57,41.54,20.00,830.80,'m²','USD',1,7,0,'','accepted',14,7,'2026-07-16 10:50:56','2026-07-16 11:15:53',NULL,NULL,NULL),(374,'2026-07-16','Himdad','gypsum_board_work',13,57,42.92,20.00,858.40,'m²','USD',1,8,0,'','accepted',14,7,'2026-07-16 10:51:47','2026-07-16 11:15:53',NULL,NULL,NULL),(375,'2026-07-16','Himdad','gypsum_board_work',13,57,41.54,20.00,830.80,'m²','USD',1,9,0,'','accepted',14,7,'2026-07-16 10:52:08','2026-07-16 11:15:53',NULL,NULL,NULL),(376,'2026-07-16','Himdad','gypsum_board_work',13,57,41.54,20.00,830.80,'m²','USD',1,10,0,'','accepted',14,7,'2026-07-16 10:52:50','2026-07-16 11:15:53',NULL,NULL,NULL),(377,'2026-07-16','Himdad','gypsum_board_work',13,57,42.32,20.00,846.40,'m²','USD',1,11,0,'','accepted',14,7,'2026-07-16 10:53:06','2026-07-16 11:15:53',NULL,NULL,NULL),(378,'2026-07-16','Himdad','gypsum_board_work',13,57,42.32,20.00,846.40,'m²','USD',1,12,0,'','accepted',14,7,'2026-07-16 10:53:29','2026-07-16 11:15:53',NULL,NULL,NULL),(379,'2026-07-16','Himdad','gypsum_board_work',13,57,42.32,20.00,846.40,'m²','USD',1,13,0,'','accepted',14,7,'2026-07-16 10:53:43','2026-07-16 11:15:53',NULL,NULL,NULL),(380,'2026-07-16','Himdad','gypsum_board_work',13,57,42.32,20.00,846.40,'m²','USD',1,14,0,'','accepted',14,7,'2026-07-16 10:56:34','2026-07-16 11:15:53',NULL,NULL,NULL),(381,'2026-07-16','Himdad','gypsum_board_work',13,57,42.32,20.00,846.40,'m²','USD',1,15,0,'','accepted',14,7,'2026-07-16 10:56:50','2026-07-16 11:15:53',NULL,NULL,NULL),(382,'2026-07-16','Himdad','gypsum_board_work',13,57,42.32,20.00,846.40,'m²','USD',1,16,0,'','accepted',14,7,'2026-07-16 10:57:46','2026-07-16 11:15:53',NULL,NULL,NULL),(383,'2026-07-16','Himdad','gypsum_board_work',13,57,42.32,20.00,846.40,'m²','USD',1,17,0,'','accepted',14,7,'2026-07-16 10:58:35','2026-07-16 11:15:53',NULL,NULL,NULL),(384,'2026-07-16','Himdad','gypsum_board_work',13,57,42.32,20.00,846.40,'m²','USD',1,18,0,'','accepted',14,7,'2026-07-16 10:58:48','2026-07-16 11:15:53',NULL,NULL,NULL),(385,'2026-07-16','Himdad','gypsum_board_work',13,57,42.32,20.00,846.40,'m²','USD',1,19,0,'','accepted',14,7,'2026-07-16 10:59:10','2026-07-16 11:15:53',NULL,NULL,NULL),(386,'2026-07-16','Himdad','gypsum_board_work',13,57,42.32,20.00,846.40,'m²','USD',1,20,0,'','accepted',14,7,'2026-07-16 10:59:24','2026-07-16 11:15:53',NULL,NULL,NULL),(387,'2026-07-16','Himdad','gypsum_board_work',13,57,41.02,20.00,820.40,'m²','USD',2,24,0,'','accepted',14,7,'2026-07-16 10:59:59','2026-07-16 11:15:53',NULL,NULL,NULL),(388,'2026-07-16','Himdad','gypsum_board_work',13,57,41.12,20.00,822.40,'m²','USD',2,25,0,'','accepted',14,7,'2026-07-16 11:00:14','2026-07-16 11:15:53',NULL,NULL,NULL),(389,'2026-07-16','Himdad','gypsum_board_work',13,57,41.12,20.00,822.40,'m²','USD',2,26,0,'','accepted',14,7,'2026-07-16 11:00:33','2026-07-16 11:15:53',NULL,NULL,NULL),(390,'2026-07-16','Himdad','gypsum_board_work',13,57,41.12,20.00,822.40,'m²','USD',2,27,0,'','accepted',14,7,'2026-07-16 11:00:58','2026-07-16 11:15:53',NULL,NULL,NULL),(391,'2026-07-16','Himdad','gypsum_board_work',13,57,41.12,20.00,822.40,'m²','USD',2,28,0,'','accepted',14,7,'2026-07-16 11:01:41','2026-07-16 11:15:53',NULL,NULL,NULL),(392,'2026-07-16','Himdad','gypsum_board_work',13,57,41.23,20.00,824.60,'m²','USD',2,29,0,'','accepted',14,7,'2026-07-16 11:02:04','2026-07-16 11:15:53',NULL,NULL,NULL),(393,'2026-07-16','Himdad','gypsum_board_work',13,57,41.23,20.00,824.60,'m²','USD',2,30,0,'','accepted',14,7,'2026-07-16 11:02:23','2026-07-16 11:15:53',NULL,NULL,NULL),(394,'2026-07-16','Himdad','gypsum_board_work',13,57,41.23,20.00,824.60,'m²','USD',2,31,0,'','accepted',14,7,'2026-07-16 11:02:37','2026-07-16 11:15:53',NULL,NULL,NULL),(395,'2026-07-16','Himdad','gypsum_board_work',13,57,41.23,20.00,824.60,'m²','USD',2,32,0,'','accepted',14,7,'2026-07-16 11:02:50','2026-07-16 11:15:53',NULL,NULL,NULL),(396,'2026-07-16','Himdad','gypsum_board_work',13,57,41.23,20.00,824.60,'m²','USD',2,33,0,'','accepted',14,7,'2026-07-16 11:03:03','2026-07-16 11:15:53',NULL,NULL,NULL),(397,'2026-07-16','Himdad','gypsum_board_work',13,57,41.54,20.00,830.80,'m²','USD',2,34,0,'','accepted',14,7,'2026-07-16 11:03:25','2026-07-16 11:15:53',NULL,NULL,NULL),(398,'2026-07-16','Himdad','gypsum_board_work',13,57,41.54,20.00,830.80,'m²','USD',2,35,0,'','accepted',14,7,'2026-07-16 11:03:35','2026-07-16 11:15:53',NULL,NULL,NULL),(399,'2026-07-16','Himdad','gypsum_board_work',13,57,41.54,20.00,830.80,'m²','USD',2,36,0,'','accepted',14,7,'2026-07-16 11:05:19','2026-07-16 11:15:53',NULL,NULL,NULL),(400,'2026-07-16','Himdad','gypsum_board_work',13,57,38.36,20.00,767.20,'m²','USD',2,37,0,'','accepted',14,7,'2026-07-16 11:05:37','2026-07-16 11:15:53',NULL,NULL,NULL),(401,'2026-07-16','Himdad','gypsum_board_work',13,57,38.36,20.00,767.20,'m²','USD',2,38,0,'','accepted',14,7,'2026-07-16 11:05:49','2026-07-16 11:15:53',NULL,NULL,NULL),(402,'2026-07-16','Himdad','gypsum_board_work',13,57,38.36,20.00,767.20,'m²','USD',2,39,0,'','accepted',14,7,'2026-07-16 11:06:04','2026-07-16 11:15:53',NULL,NULL,NULL),(403,'2026-07-16','Himdad','gypsum_board_work',13,57,38.36,20.00,767.20,'m²','USD',2,40,0,'','accepted',14,7,'2026-07-16 11:06:18','2026-07-16 11:15:53',NULL,NULL,NULL),(404,'2026-07-16','Himdad','gypsum_board_work',13,57,38.36,20.00,767.20,'m²','USD',2,41,0,'','accepted',14,7,'2026-07-16 11:06:33','2026-07-16 11:15:53',NULL,NULL,NULL),(405,'2026-07-16','Himdad','gypsum_board_work',13,57,38.36,20.00,767.20,'m²','USD',2,42,0,'','accepted',14,7,'2026-07-16 11:06:57','2026-07-16 11:15:53',NULL,NULL,NULL),(406,'2026-07-16','Himdad','gypsum_board_work',13,57,38.36,20.00,767.20,'m²','USD',2,43,0,'','accepted',14,7,'2026-07-16 11:07:11','2026-07-16 11:15:53',NULL,NULL,NULL),(407,'2026-07-16','Himdad','gypsum_board_work',13,57,17.57,20.00,351.40,'m²','USD',3,47,0,'','accepted',14,7,'2026-07-16 11:07:56','2026-07-16 11:15:53',NULL,NULL,NULL),(408,'2026-07-16','Himdad','gypsum_board_work',13,57,18.49,20.00,369.80,'m²','USD',3,48,0,'','accepted',14,7,'2026-07-16 11:08:13','2026-07-16 11:15:53',NULL,NULL,NULL),(409,'2026-07-16','Himdad','gypsum_board_work',13,57,26.54,20.00,530.80,'m²','USD',3,49,0,'','accepted',14,7,'2026-07-16 11:08:42','2026-07-16 11:15:53',NULL,NULL,NULL),(410,'2026-07-16','Himdad','gypsum_board_work',13,57,26.54,20.00,530.80,'m²','USD',3,50,0,'','accepted',14,7,'2026-07-16 11:08:58','2026-07-16 11:15:53',NULL,NULL,NULL),(411,'2026-07-16','Himdad','gypsum_board_work',13,57,26.54,20.00,530.80,'m²','USD',3,51,0,'','accepted',14,7,'2026-07-16 11:09:13','2026-07-16 11:15:53',NULL,NULL,NULL),(412,'2026-07-16','Himdad','gypsum_board_work',13,57,25.14,20.00,502.80,'m²','USD',3,52,0,'','accepted',14,7,'2026-07-16 11:10:49','2026-07-16 11:15:53',NULL,NULL,NULL),(413,'2026-07-16','Himdad','gypsum_board_work',13,57,26.56,20.00,531.20,'m²','USD',3,53,0,'','accepted',14,7,'2026-07-16 11:11:05','2026-07-16 11:15:53',NULL,NULL,NULL),(414,'2026-07-16','Himdad','gypsum_board_work',13,57,23.82,20.00,476.40,'m²','USD',3,54,0,'','accepted',14,7,'2026-07-16 11:11:21','2026-07-16 11:15:53',NULL,NULL,NULL),(415,'2026-07-16','Himdad','gypsum_board_work',13,57,27.96,20.00,559.20,'m²','USD',3,55,0,'','accepted',14,7,'2026-07-16 11:11:40','2026-07-16 11:15:53',NULL,NULL,NULL),(416,'2026-07-16','Himdad','gypsum_board_work',13,57,22.57,20.00,451.40,'m²','USD',3,56,0,'','accepted',14,7,'2026-07-16 11:11:54','2026-07-16 11:15:53',NULL,NULL,NULL),(417,'2026-07-16','Himdad','gypsum_board_work',13,57,41.02,20.00,820.40,'m²','USD',3,57,0,'','accepted',14,7,'2026-07-16 11:12:10','2026-07-16 11:15:53',NULL,NULL,NULL),(418,'2026-07-16','Himdad','gypsum_board_work',13,57,27.61,20.00,552.20,'m²','USD',3,58,0,'','accepted',14,7,'2026-07-16 11:12:25','2026-07-16 11:15:53',NULL,NULL,NULL),(419,'2026-07-16','Himdad','gypsum_board_work',13,57,29.93,20.00,598.60,'m²','USD',3,59,0,'','accepted',14,7,'2026-07-16 11:12:42','2026-07-16 11:15:53',NULL,NULL,NULL),(420,'2026-07-16','Himdad','gypsum_board_work',13,57,27.42,20.00,548.40,'m²','USD',3,60,0,'','accepted',14,7,'2026-07-16 11:13:02','2026-07-16 11:15:53',NULL,NULL,NULL),(421,'2026-07-16','Himdad','gypsum_board_work',13,57,24.57,20.00,491.40,'m²','USD',3,61,0,'','accepted',14,7,'2026-07-16 11:13:21','2026-07-16 11:15:53',NULL,NULL,NULL),(422,'2026-07-16','Himdad','gypsum_board_work',13,57,28.23,20.00,564.60,'m²','USD',3,62,0,'','accepted',14,7,'2026-07-16 11:13:37','2026-07-16 11:15:53',NULL,NULL,NULL),(423,'2026-07-16','Himdad','gypsum_board_work',13,57,31.74,20.00,634.80,'m²','USD',3,63,0,'','accepted',14,7,'2026-07-16 11:13:57','2026-07-16 11:15:53',NULL,NULL,NULL),(424,'2026-07-16','Himdad','gypsum_board_work',13,57,30.15,20.00,603.00,'m²','USD',3,64,0,'','accepted',14,7,'2026-07-16 11:14:13','2026-07-16 11:15:53',NULL,NULL,NULL),(425,'2026-07-16','Himdad','gypsum_board_work',13,57,26.00,20.00,520.00,'m²','USD',3,65,0,'','accepted',14,7,'2026-07-16 11:15:21','2026-07-16 11:15:53',NULL,NULL,NULL),(426,'2026-07-16','Himdad','gypsum_board_work',13,57,27.06,20.00,541.20,'m²','USD',3,66,0,'','accepted',14,7,'2026-07-16 11:15:38','2026-07-16 11:15:53',NULL,NULL,NULL),(427,'2026-07-16','Himdad','gypsum_board_work',13,58,80.00,20.00,1600.00,'m²','USD',1,117,0,'لە نهۆمەکانی (2-21) دەگرێتەوە','accepted',15,7,'2026-07-16 11:20:43','2026-07-16 11:29:07',NULL,NULL,NULL),(428,'2026-07-16','Himdad','gypsum_board_work',13,58,135.00,20.00,2700.00,'m²','USD',2,118,0,'نهۆمەکانی (2-21) دەگرێتەوە','accepted',15,7,'2026-07-16 11:25:03','2026-07-16 11:29:07',NULL,NULL,NULL),(429,'2026-07-16','Himdad','gypsum_board_work',13,58,100.00,20.00,2000.00,'m²','USD',3,119,0,'نهۆمەکانی (2-21) دەگرێتەوە','accepted',15,7,'2026-07-16 11:28:16','2026-07-16 11:29:07',NULL,NULL,NULL);
/*!40000 ALTER TABLE `project_work_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_work_type_fields`
--

DROP TABLE IF EXISTS `project_work_type_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_work_type_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_type_id` int(11) NOT NULL,
  `field_key` varchar(80) NOT NULL,
  `field_label` varchar(120) NOT NULL,
  `input_type` varchar(30) NOT NULL DEFAULT 'number',
  `unit_label` varchar(30) DEFAULT NULL,
  `field_role` varchar(20) NOT NULL DEFAULT 'meta',
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `options_json` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_work_field` (`work_type_id`,`field_key`),
  KEY `idx_work_type_id` (`work_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_work_type_fields`
--

LOCK TABLES `project_work_type_fields` WRITE;
/*!40000 ALTER TABLE `project_work_type_fields` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_work_type_fields` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_work_types`
--

DROP TABLE IF EXISTS `project_work_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_work_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_type_name` varchar(120) NOT NULL,
  `work_type_name_ku` varchar(120) DEFAULT NULL,
  `work_type_key` varchar(80) NOT NULL,
  `quantity_unit` varchar(30) NOT NULL DEFAULT 'm²',
  `scope_level` varchar(30) NOT NULL DEFAULT 'apartment',
  `pricing_mode` varchar(30) NOT NULL DEFAULT 'per_unit',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_type_key` (`work_type_key`),
  KEY `idx_work_type_key` (`work_type_key`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_work_types`
--

LOCK TABLES `project_work_types` WRITE;
/*!40000 ALTER TABLE `project_work_types` DISABLE KEYS */;
INSERT INTO `project_work_types` VALUES (1,'Electrical Work','کاری کارەباییەکان','electrical_work','m²','apartment','per_unit',1,7,'2026-07-13 07:14:57','2026-07-13 07:14:57'),(2,'Ceramic Work','کاری کاشیکاری','ceramic_work','m²','apartment','per_unit',1,7,'2026-07-13 07:16:00','2026-07-13 07:16:00'),(3,'Mechanical Work','کاری میکانیک','mechanical_work','m²','apartment','per_unit',1,7,'2026-07-13 07:16:43','2026-07-13 07:16:43'),(4,'Exterior painting','کاری سبوغی دەرەوە','exterior_painting','m²','apartment','per_unit',0,7,'2026-07-13 07:17:46','2026-07-13 07:18:16'),(5,'Exterior Painting Work','کاری بۆیەی دەرەوە','exterior_painting_work','m²','apartment','per_unit',1,7,'2026-07-13 07:19:13','2026-07-13 07:19:13'),(6,'Interior Painting Work','کاری بۆیەی ناوەوە','interior_painting_work','m²','apartment','per_unit',1,7,'2026-07-13 07:19:59','2026-07-13 07:19:59'),(7,'Gypsum Board Work','کاری جپسم بۆرد','gypsum_board_work','m²','apartment','per_unit',1,7,'2026-07-13 07:20:45','2026-07-13 07:20:45'),(8,'Gypsum Plastering Work','کاری گەچکاری','gypsum_plastering_work','m²','apartment','per_unit',1,7,'2026-07-13 07:22:35','2026-07-13 07:22:35'),(9,'Formwork work','کاری نەجاری','formwork_work','m²','apartment','per_unit',1,7,'2026-07-13 07:23:50','2026-07-13 07:23:50'),(10,'Masonry Wall Work','کاری دیواری بلۆک','masonry_wall_work','m²','apartment','per_unit',1,7,'2026-07-13 07:25:27','2026-07-13 07:25:27'),(11,'Balcony Rails Work','کاری محەجەرە','balcony_rails_work','m²','apartment','per_unit',1,7,'2026-07-13 07:26:21','2026-07-13 07:26:21'),(12,'Exterior Plastering Work ( Mantosva )','کاری مانتۆسڤا','exterior_plastering_work_mantosva','m²','apartment','per_unit',1,7,'2026-07-13 07:30:22','2026-07-13 07:30:22'),(13,'Wooden Floor Tiles ( Parquet )','کاری  داری ئەرزی ( پارکێت )','wooden_floor_tiles_parquet','m²','apartment','per_unit',1,7,'2026-07-13 07:33:13','2026-07-13 07:33:13'),(14,'Cement Plastering','کاری لەبغی دیوار','cement_plastering','m²','apartment','per_unit',1,7,'2026-07-13 07:34:47','2026-07-13 07:34:47'),(15,'Screed Concrete Work','کاری شاپی ئەرزی','screed_concrete_work','m²','apartment','per_unit',1,7,'2026-07-13 07:35:37','2026-07-13 07:35:37'),(16,'Pipe Covering Work ( Fuga )','کاری فوگە','pipe_covering_work_fuga','m²','apartment','per_unit',1,7,'2026-07-13 07:36:34','2026-07-13 07:36:34'),(17,'Window Frame Work','کاری چوارچێوەی پەنجەرە','window_frame_work','m²','apartment','per_unit',1,7,'2026-07-13 07:37:55','2026-07-13 07:37:55'),(18,'Window Glass Work','کاری پەنجەرەی شوشە','window_glass_work','m²','apartment','per_unit',1,7,'2026-07-13 07:38:52','2026-07-13 07:38:52'),(19,'Door Work','کاری بەستانی دەرگا','door_work','m²','apartment','per_unit',1,7,'2026-07-13 07:39:28','2026-07-13 07:39:28'),(20,'Wooden Cabinet Work','کاری کەوەنتەر','wooden_cabinet_work','m²','apartment','per_unit',1,7,'2026-07-13 07:40:33','2026-07-13 07:40:33'),(21,'Marble Work','کاری مەرمەر','marble_work','m²','apartment','per_unit',1,7,'2026-07-13 07:41:20','2026-07-13 07:41:20'),(22,'Air Condition ( splite)','کاری بەستانی سپلیت','air_condition_splite','m²','apartment','per_unit',1,7,'2026-07-13 07:42:06','2026-07-13 07:42:06'),(23,'Elevator Work','کاری بەستانی مسعد','elevator_work','m²','apartment','per_unit',1,7,'2026-07-13 07:43:17','2026-07-13 07:43:17'),(24,'Wall Paper Work','کاری کاغەزی دیوار','wall_paper_work','m²','apartment','per_unit',1,7,'2026-07-13 07:43:51','2026-07-13 07:43:51'),(25,'Facade Percaline Work','کاری پۆرسەلین','facade_percaline_work','m²','apartment','per_unit',1,7,'2026-07-13 07:46:46','2026-07-13 07:46:46');
/*!40000 ALTER TABLE `project_work_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,4),(2,4),(3,4),(5,4),(8,4),(2,5),(8,5),(2,6),(8,6),(1,7),(2,7),(5,7),(8,7),(1,9),(2,9),(9,9),(1,10),(2,10),(1,11),(2,11),(1,12),(2,12),(1,13),(2,13),(1,14),(2,14),(1,15),(2,15),(9,15),(1,16),(2,16),(9,16),(1,17),(2,17),(9,17),(1,18),(2,18),(1,19),(2,19),(8,19),(1,20),(2,20),(8,20),(1,21),(2,21),(8,21);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','Full administration','2026-03-26 15:43:51'),(2,'manager','Project manager','2026-03-26 15:43:51'),(3,'engineer','Site engineer','2026-03-26 15:43:51'),(5,'Owner','','2026-06-29 17:16:49'),(8,'full admin','','2026-07-03 12:35:54'),(9,'Store Staff','Receives purchased stock and marks approved requests as delivered','2026-07-04 07:14:27');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `slfa_payments`
--

DROP TABLE IF EXISTS `slfa_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `slfa_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `total_work_value` decimal(14,2) NOT NULL DEFAULT 0.00,
  `cash_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cash_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `apartment_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `apartment_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `apartment_sqm` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `apartment_meter_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `entry_count` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stakeholder` (`stakeholder_id`),
  KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `slfa_payments`
--

LOCK TABLES `slfa_payments` WRITE;
/*!40000 ALTER TABLE `slfa_payments` DISABLE KEYS */;
INSERT INTO `slfa_payments` VALUES (1,9,'2026-07-13',4500000.00,100.00,4500000.00,0.00,0.00,0.0000,0.00,1,'',1,'2026-07-13 10:23:20'),(2,9,'2026-07-13',450000.00,100.00,450000.00,0.00,0.00,0.0000,0.00,1,'',7,'2026-07-13 10:30:28'),(3,12,'2026-07-14',58920480.00,50.00,29460240.00,50.00,29460240.00,42086.0571,700.00,23,'',7,'2026-07-14 10:08:08'),(4,12,'2026-07-14',148338400.00,50.00,74169200.00,50.00,74169200.00,105956.0000,700.00,103,'',7,'2026-07-14 11:52:19'),(5,12,'2026-07-14',623680.00,50.00,311840.00,50.00,311840.00,445.4857,700.00,2,'',7,'2026-07-14 12:01:43'),(6,1,'2026-07-14',8288.56,100.00,8288.56,0.00,0.00,0.0000,0.00,18,'',7,'2026-07-14 13:56:19'),(7,1,'2026-07-14',7842.08,100.00,7842.08,0.00,0.00,0.0000,0.00,18,'',7,'2026-07-14 14:12:11'),(8,1,'2026-07-14',7867.60,100.00,7867.60,0.00,0.00,0.0000,0.00,18,'',7,'2026-07-14 14:28:44'),(9,1,'2026-07-15',18017.36,100.00,18017.36,0.00,0.00,0.0000,0.00,18,'',7,'2026-07-15 07:54:54'),(10,1,'2026-07-15',18565.40,100.00,18565.40,0.00,0.00,0.0000,0.00,54,'',7,'2026-07-15 08:43:59'),(11,13,'2026-07-16',126482.02,50.00,63241.01,50.00,63241.01,90.3443,700.00,21,'',7,'2026-07-16 10:14:05'),(12,13,'2026-07-16',118894.30,50.00,59447.15,50.00,59447.15,84.9245,700.00,20,'',7,'2026-07-16 10:24:26'),(13,13,'2026-07-16',120496.74,50.00,60248.37,50.00,60248.37,86.0691,700.00,20,'',7,'2026-07-16 10:45:20'),(14,13,'2026-07-16',43485.20,50.00,21742.60,50.00,21742.60,31.0609,700.00,60,'',7,'2026-07-16 11:15:53'),(15,13,'2026-07-16',6300.00,50.00,3150.00,50.00,3150.00,4.5000,700.00,3,'',7,'2026-07-16 11:29:07');
/*!40000 ALTER TABLE `slfa_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stakeholder_work_prices`
--

DROP TABLE IF EXISTS `stakeholder_work_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stakeholder_work_prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_type_key` varchar(80) NOT NULL,
  `stakeholder_name` varchar(150) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_work_type_key` (`work_type_key`),
  KEY `idx_stakeholder_name` (`stakeholder_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stakeholder_work_prices`
--

LOCK TABLES `stakeholder_work_prices` WRITE;
/*!40000 ALTER TABLE `stakeholder_work_prices` DISABLE KEYS */;
/*!40000 ALTER TABLE `stakeholder_work_prices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$h3X/6LRkXKrX7lV7/n2/m.xy5Hj9xEi73uk/vo1VgQrAdEJzkUST.','admin@example.com','Super Admin',8,1,'2026-03-26 15:43:51'),(3,'Rebin','$2y$10$oWm0KV4RKVtX217b2hm2zO1FG0pj0TJtZufFmA9VxF1QYgHecZzhC','engineer@example.com','Rebin Salah',3,1,'2026-03-26 15:43:51'),(4,'Mohammed','$2y$10$AgsBE7svADaxaS3XbguT9O2OavuDPzx8JEpFCUl0lEvX0VEwHh6Hm','mohamadrashid828@gmail.com','Mohammad Rashid Ahmed',2,1,'2026-03-26 16:12:10'),(5,'karzan','$2y$10$NVT4qn7Fu8Gmmoq2kk4bs.ZclYNnhQ5H1SgstShCt97Wzr64jC/.K','mr5121@cs.soran.edu.iq','Karzan az',1,0,'2026-03-28 11:17:46'),(6,'zrar','$2y$10$QKOVcgNLdhXHC14FvMVEkuqsepR9tHZA.NLPxHgC9LYT1JDUz7fVC','zrar@gmail.com','Zrar Safary Mahmood',8,1,'2026-04-04 14:39:57'),(7,'himdad','$2y$10$tM8OuybmtWH6Zuo8nhBMsu12ZVYo/eHYJxQyuct7fqqxfGJ9hhlaS','himdad@gmail.com','Himdad',8,1,'2026-04-04 14:40:29');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_types`
--

DROP TABLE IF EXISTS `work_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_type_code` varchar(50) NOT NULL,
  `work_type_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'm²',
  `category` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_type_code` (`work_type_code`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_types`
--

LOCK TABLES `work_types` WRITE;
/*!40000 ALTER TABLE `work_types` DISABLE KEYS */;
INSERT INTO `work_types` VALUES (1,'GECHKARI','Gechkari Work','Gechkari construction measurements','m²','Foundation','active','2026-03-26 17:32:20'),(2,'CONCRETE','Concrete Work','Concrete pouring and finishing','m³','Structure','active','2026-03-26 17:32:20'),(3,'BRICKWORK','Brick Work','Brick laying and masonry','m²','Structure','active','2026-03-26 17:32:20'),(4,'PLASTERING','Plastering','Internal and external plastering','m²','Finishing','active','2026-03-26 17:32:20'),(5,'ELECTRICAL','Electrical Work','Electrical installations','point','Services','active','2026-03-26 17:32:20'),(6,'PLUMBING','Plumbing Work','Plumbing installations','point','Services','active','2026-03-26 17:32:20'),(7,'PAINTING','Painting Work','Interior and exterior painting','m²','Finishing','active','2026-03-26 17:32:20'),(8,'TILING','Tiling Work','Floor and wall tiling','m²','Finishing','active','2026-03-26 17:32:20');
/*!40000 ALTER TABLE `work_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'u670910047_kaver'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-23 13:28:05
