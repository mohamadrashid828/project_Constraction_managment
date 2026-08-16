-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: 127.0.0.1    Database: construction_management
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `apartments`
--

LOCK TABLES `apartments` WRITE;
/*!40000 ALTER TABLE `apartments` DISABLE KEYS */;
INSERT INTO `apartments` VALUES (15,9,14,'101','2BR',123.00,'active','2026-03-27 20:01:47'),(18,9,14,'102','3BR',123.00,'active','2026-03-28 11:17:12'),(19,10,21,'101','1BR',142.00,'active','2026-03-29 18:18:19'),(20,9,14,'103','2BR',3456.00,'active','2026-06-30 15:20:29'),(26,9,23,'as','Shop',12.00,'active','2026-07-24 11:13:12');
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `buildings`
--

LOCK TABLES `buildings` WRITE;
/*!40000 ALTER TABLE `buildings` DISABLE KEYS */;
INSERT INTO `buildings` VALUES (3,'Building C',12.00,'active','2026-03-26 17:32:20',NULL),(9,'Building B',2500.00,'active','2026-03-27 19:35:59',''),(10,'Tower 1',2354.00,'active','2026-03-29 18:16:34',''),(17,'Tower A',1233.00,'active','2026-07-03 12:20:02','');
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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `floors`
--

LOCK TABLES `floors` WRITE;
/*!40000 ALTER TABLE `floors` DISABLE KEYS */;
INSERT INTO `floors` VALUES (7,3,1,'Ground Floor','active','2026-03-26 18:57:11',NULL),(8,3,2,'First Floor','active','2026-03-26 18:57:11',NULL),(9,3,3,'Second Floor','active','2026-03-26 18:57:11',NULL),(14,9,1,'Floor one','active','2026-03-27 19:44:46',123.00),(21,10,1,'first floor','active','2026-03-29 18:17:39',232.00),(23,9,-1,'Theird Floor','active','2026-07-03 12:32:08',32434.00);
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_item_types`
--

LOCK TABLES `inventory_item_types` WRITE;
/*!40000 ALTER TABLE `inventory_item_types` DISABLE KEYS */;
INSERT INTO `inventory_item_types` VALUES (1,'Block',1,NULL,'2026-07-03 17:27:30','2026-07-03 18:15:24');
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_items`
--

LOCK TABLES `inventory_items` WRITE;
/*!40000 ALTER TABLE `inventory_items` DISABLE KEYS */;
INSERT INTO `inventory_items` VALUES (4,'Block','',1,'M2',1,2,'2026-07-03 17:13:10','2026-07-03 22:52:12');
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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_movements`
--

LOCK TABLES `inventory_movements` WRITE;
/*!40000 ALTER TABLE `inventory_movements` DISABLE KEYS */;
INSERT INTO `inventory_movements` VALUES (5,4,'in',1400.000,12.00,'purchase',3,NULL,NULL,NULL,0,'',2,NULL,'2026-07-03','2026-07-03 17:14:05'),(6,4,'out',1200.000,NULL,'usage',NULL,9,14,15,0,'',2,NULL,'2026-07-03','2026-07-03 17:15:04'),(8,4,'out',120.000,NULL,'usage',NULL,NULL,NULL,NULL,1,'',2,NULL,'2026-07-03','2026-07-03 21:30:53'),(17,4,'out',30.000,NULL,'usage',NULL,NULL,NULL,NULL,1,'',2,'tryghj','2026-07-07','2026-07-07 13:03:05');
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_purchase_requests`
--

LOCK TABLES `inventory_purchase_requests` WRITE;
/*!40000 ALTER TABLE `inventory_purchase_requests` DISABLE KEYS */;
INSERT INTO `inventory_purchase_requests` VALUES (2,'Block',4,14010.000,'medium',NULL,'M2','','fulfilled',2,2,'2026-07-04 02:09:51','2026-07-03 17:15:41'),(8,'Block',4,312.000,'medium',NULL,'M2','','fulfilled',2,2,'2026-07-04 02:09:48','2026-07-03 23:09:18'),(11,'Block',4,344.000,'urgent',NULL,'M2','','fulfilled',2,2,'2026-07-07 16:09:07','2026-07-07 13:08:36');
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_purchases`
--

LOCK TABLES `inventory_purchases` WRITE;
/*!40000 ALTER TABLE `inventory_purchases` DISABLE KEYS */;
INSERT INTO `inventory_purchases` VALUES (3,4,1400.000,12.00,16800.00,'',NULL,'',NULL,'2026-07-03',NULL,'',2,'2026-07-03 17:14:05');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `measurements`
--

LOCK TABLES `measurements` WRITE;
/*!40000 ALTER TABLE `measurements` DISABLE KEYS */;
INSERT INTO `measurements` VALUES (1,9,14,15,1,123.000,13.00,1599.00,'2026-03-25',4,'saqf',0,NULL,'specific','draft','2026-03-28 12:50:25','2026-07-11 09:48:28');
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
-- Table structure for table `project_stakeholder_documents`
--

DROP TABLE IF EXISTS `project_stakeholder_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_stakeholder_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_id` int(11) NOT NULL,
  `doc_name` varchar(160) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stakeholder` (`stakeholder_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_stakeholder_documents`
--

LOCK TABLES `project_stakeholder_documents` WRITE;
/*!40000 ALTER TABLE `project_stakeholder_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_stakeholder_documents` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_stakeholder_subparts`
--

LOCK TABLES `project_stakeholder_subparts` WRITE;
/*!40000 ALTER TABLE `project_stakeholder_subparts` DISABLE KEYS */;
INSERT INTO `project_stakeholder_subparts` VALUES (3,2,'kafmall',1.50,'m','USD',1,'2026-03-29 13:00:18','2026-03-29 13:00:18'),(4,2,'saqf',1500.00,'m²','IQD',1,'2026-03-29 13:00:37','2026-03-29 13:00:37'),(5,2,'diwar',1000.00,'m²','IQD',1,'2026-03-29 13:01:06','2026-03-29 13:01:06'),(6,4,'saqf',123.00,'m²','IQD',1,'2026-03-29 18:29:03','2026-03-29 18:29:03'),(7,4,'kafmar',1500.00,'m²','IQD',1,'2026-03-29 18:29:31','2026-03-29 18:29:31'),(11,9,'test',1500.00,'per_hour','IQD',1,'2026-07-28 20:06:22','2026-07-28 20:06:22'),(12,9,'test3',150000.00,'per_day','IQD',1,'2026-07-28 20:07:57','2026-07-28 20:07:57');
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
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company_name` varchar(160) DEFAULT NULL,
  `stakeholder_date` date DEFAULT NULL,
  `work_type_key` varchar(80) NOT NULL,
  `cash_percentage` decimal(5,2) NOT NULL DEFAULT 100.00,
  `apartment_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `apartment_meter_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `contract_file` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_stakeholders`
--

LOCK TABLES `project_stakeholders` WRITE;
/*!40000 ALTER TABLE `project_stakeholders` DISABLE KEYS */;
INSERT INTO `project_stakeholders` VALUES (2,'ahmad','','','','2026-03-29','kech_kary',0.75,0.25,1.31,NULL,'photo_0771042aff4245f4926a12285cd7e5c8.png','e34324d7edf47d1e315c373b0015655d0b067545e852f7c6b52086915535db61',1,4,'2026-03-29 12:52:31','2026-07-25 08:20:24'),(4,'Karwan',NULL,NULL,NULL,'2026-03-29','kech_kary',100.00,0.00,0.00,NULL,NULL,'cc5ad8039f7403bfcaed06aee4b3072f9c328e6c5a364513e5cfbcb1edb64a21',1,4,'2026-03-29 18:25:13','2026-06-23 13:46:32'),(5,'Karwan',NULL,NULL,NULL,'2026-07-08','other',100.00,0.00,0.00,NULL,NULL,'c99a23f2f5bfa2624ffb234beec5428de552e90f43a619ab2f6ce73a81a02ecd',1,2,'2026-07-07 13:16:22','2026-07-07 13:16:22'),(9,'az','mohamadrashid828@gmail.com','07508285828','za','2026-07-28','shofel',100.00,0.00,0.00,'contract_1785258664_8bdc36b4.pdf',NULL,'f40bf4cd654697d5e29f477bd9bcfc5c5e84cb8d9896802256c778843c6c3241',1,6,'2026-07-28 17:11:04','2026-07-28 17:11:04');
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_work_entries`
--

LOCK TABLES `project_work_entries` WRITE;
/*!40000 ALTER TABLE `project_work_entries` DISABLE KEYS */;
INSERT INTO `project_work_entries` VALUES (1,'2026-03-29','Kamaran Ahmed','aint',2,3,400.00,1.50,600.00,'m','USD',9,14,15,'','draft',NULL,4,'2026-03-29 13:29:52','2026-03-29 13:29:52',NULL,NULL,NULL),(2,'2026-03-29','Kamaran Ahmed','aint',2,5,12.00,1000.00,12000.00,'m²','IQD',9,14,15,'','draft',NULL,4,'2026-03-29 15:33:49','2026-03-29 15:33:49',NULL,NULL,NULL),(3,'2026-03-29','Kamaran Ahmed','kech_kary',4,7,100.00,1500.00,150000.00,'m²','IQD',10,21,19,'kufkdjhsfakjdf','draft',NULL,4,'2026-03-29 18:33:07','2026-03-29 18:33:07',NULL,NULL,NULL),(4,'2026-03-30','Kamaran Ahmed','kech_kary',2,5,1200.00,1000.00,1200000.00,'m²','IQD',9,14,15,'good','accepted',1,4,'2026-03-30 18:55:13','2026-03-30 18:57:34',NULL,NULL,NULL),(5,'2026-03-30','azad','kech_kary',2,5,1399.00,1000.00,1399000.00,'m²','IQD',9,14,15,'','medium',1,4,'2026-03-30 18:55:31','2026-03-30 18:57:34',NULL,NULL,NULL),(6,'2026-03-30','amanj','kech_kary',2,5,300.00,1000.00,300000.00,'m²','IQD',9,14,15,'not good','rejected',2,4,'2026-03-30 18:55:50','2026-03-30 19:39:04',NULL,NULL,NULL),(7,'2026-03-30','Kamaran Ahmed','kech_kary',2,4,1200.00,1500.00,1800000.00,'m²','IQD',9,14,15,'not good','accepted',3,4,'2026-03-30 19:47:09','2026-03-30 20:10:43',4,'2026-03-30 22:10:19','rejected'),(8,'2026-03-30','Kamaran Ahmed','kech_kary',4,7,3000.00,1500.00,4500000.00,'m²','IQD',9,14,18,'','rejected',4,4,'2026-03-30 20:13:39','2026-03-30 20:14:16',4,'2026-03-30 22:13:58','rejected'),(11,'2026-07-24','Field Engineer','kech_kary',2,5,156.00,1000.00,156000.00,'m²','IQD',10,21,19,'','medium',NULL,3,'2026-07-24 21:40:52','2026-07-24 21:40:52',NULL,NULL,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `image_file` varchar(255) DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_work_types`
--

LOCK TABLES `project_work_types` WRITE;
/*!40000 ALTER TABLE `project_work_types` DISABLE KEYS */;
INSERT INTO `project_work_types` VALUES (16,'aint',NULL,NULL,'aint','m²','apartment','per_unit',0,NULL,'2026-03-29 16:01:27','2026-03-29 16:11:18'),(20,'Soean',NULL,NULL,'oean','m²','apartment','per_unit',0,4,'2026-03-29 16:11:10','2026-03-29 16:11:14'),(21,'Electric','','cat_39d4a1abe969c065ecd111024badc9b6.png','lectric','m²','apartment','per_unit',1,4,'2026-03-29 16:12:49','2026-07-24 11:24:34'),(22,'Keck kary',NULL,NULL,'eck_kary','m²','apartment','per_unit',0,4,'2026-03-29 16:12:56','2026-03-29 16:13:38'),(23,'test',NULL,NULL,'test','m²','apartment','per_unit',1,4,'2026-03-29 16:13:34','2026-03-29 16:13:34'),(24,'kech kary',NULL,NULL,'kech_kary','m²','apartment','per_unit',1,4,'2026-03-29 18:20:06','2026-03-29 18:20:06'),(27,'Saqf','Saqf',NULL,'saqf','m²','apartment','per_unit',1,2,'2026-07-04 12:47:58','2026-07-04 12:47:58'),(28,'other','kary tr',NULL,'other','m²','apartment','per_unit',1,2,'2026-07-07 13:15:57','2026-07-07 13:15:57'),(30,'Electric fd','dsf','cat_e68dbda21644d4ce09ff97bbcc6024cd.png','electric_fd','m²','apartment','per_unit',1,6,'2026-07-24 11:13:38','2026-07-24 11:13:38'),(32,'shofel','','cat_b8b7810a39082decb4e437e739e3f151.jpg','shofel','m²','apartment','per_unit',1,6,'2026-07-28 17:10:37','2026-07-28 17:10:37');
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
INSERT INTO `role_permissions` VALUES (1,4),(1,5),(1,6),(1,7),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,21),(2,4),(2,5),(2,6),(2,7),(2,9),(2,10),(2,11),(2,12),(2,13),(2,14),(2,15),(2,16),(2,17),(2,18),(2,19),(2,20),(2,21),(3,4),(3,7),(5,4),(5,7),(8,4),(8,5),(8,6),(8,7),(8,19),(8,20),(8,21),(9,9),(9,15),(9,16),(9,17);
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `slfa_payments`
--

LOCK TABLES `slfa_payments` WRITE;
/*!40000 ALTER TABLE `slfa_payments` DISABLE KEYS */;
INSERT INTO `slfa_payments` VALUES (1,2,'2026-03-30',2599000.00,0.75,19492.50,0.25,6497.50,4959.9237,1.31,2,'',4,'2026-03-30 18:57:34'),(2,2,'2026-03-30',300000.00,0.75,2250.00,0.25,750.00,572.5191,1.31,1,'',4,'2026-03-30 19:39:04'),(3,2,'2026-03-30',1800000.00,0.75,13500.00,0.25,4500.00,3435.1145,1.31,1,'i have accpeted becoeuse',4,'2026-03-30 20:10:43'),(4,4,'2026-03-30',4500000.00,100.00,4500000.00,0.00,0.00,0.0000,0.00,1,'',4,'2026-03-30 20:14:16');
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stakeholder_work_prices`
--

LOCK TABLES `stakeholder_work_prices` WRITE;
/*!40000 ALTER TABLE `stakeholder_work_prices` DISABLE KEYS */;
INSERT INTO `stakeholder_work_prices` VALUES (1,'gechkari','Karwan',2.00,1,4,'2026-03-28 15:22:44','2026-03-28 15:22:44');
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
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin@example.com','Super Admin',1,1,'2026-03-26 15:43:51'),(2,'manager','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','manager@example.com','Project Manager',2,1,'2026-03-26 15:43:51'),(3,'engineer','$2y$10$ztJA0uBeuuKsZsNsXqJhuuK7FAjazM057t1qQZ4e.nZbQ4XNMYs36','engineer@example.com','Field Engineer',3,1,'2026-03-26 15:43:51'),(4,'Mohammed','$2y$10$AgsBE7svADaxaS3XbguT9O2OavuDPzx8JEpFCUl0lEvX0VEwHh6Hm','mohamadrashid828@gmail.com','Mohammad Rashid Ahmed',2,1,'2026-03-26 16:12:10'),(5,'karzan','$2y$10$NVT4qn7Fu8Gmmoq2kk4bs.ZclYNnhQ5H1SgstShCt97Wzr64jC/.K','mr5121@cs.soran.edu.iq','Karzan az',1,0,'2026-03-28 11:17:46'),(6,'zrar','$2y$10$QKOVcgNLdhXHC14FvMVEkuqsepR9tHZA.NLPxHgC9LYT1JDUz7fVC','zrar@gmail.com','Zrar Safary Mahmood',2,1,'2026-04-04 14:39:57'),(7,'himdad','$2y$10$kZMGF1dGzRAX6CEG6j.J1uufNFAVh17vXjmzgzNvYEkq677OFva52','himdad@gmail.com','Himdad',1,1,'2026-04-04 14:40:29');
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
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-15 12:43:43
