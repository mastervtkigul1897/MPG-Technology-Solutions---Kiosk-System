-- MySQL dump 10.13  Distrib 9.6.0, for macos26.3 (arm64)
--
-- Host: 127.0.0.1    Database: laundry_system
-- ------------------------------------------------------
-- Server version	9.6.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `user_role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `activity_logs_tenant_id_index` (`tenant_id`),
  KEY `activity_logs_user_id_index` (`user_id`),
  KEY `activity_logs_module_index` (`module`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_logs_chk_1` CHECK (json_valid(`meta`))
) ENGINE=InnoDB AUTO_INCREMENT=277 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

INSERT IGNORE INTO `activity_logs` VALUES (32,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (owner@example.com)','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"role\": \"super_admin\", \"email\": \"owner@example.com\"}','2026-03-28 18:08:34','2026-03-28 18:08:34'),(35,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','103.129.125.132','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-03-28 20:09:39','2026-03-28 20:09:39'),(36,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-28 20:12:18','2026-03-28 20:12:18'),(37,2,4,'tenant_admin','staff','create_cashier','POST','/tenant/staff','Created cashier: Yasuhiro Suda (yasuhirosuda@gmail.com)','103.129.125.132','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"new_user_id\":5,\"email\":\"yasuhirosuda@gmail.com\",\"modules\":[\"pos\",\"transactions\",\"activity_logs\",\"notifications\"]}','2026-03-28 20:13:31','2026-03-28 20:13:31'),(38,2,4,'tenant_admin','staff','remove_cashier','DELETE','/tenant/staff/5','Removed cashier: Yasuhiro Suda (yasuhirosuda@gmail.com)','103.129.125.132','Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36','{\"removed_user_id\":5}','2026-03-28 20:16:54','2026-03-28 20:16:54'),(39,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-28 20:18:47','2026-03-28 20:18:47'),(43,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; CDY-NX9B; HMSCore 6.15.4.342) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.5735.196 HuaweiBrowser/16.0.9.302 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-28 23:56:48','2026-03-28 23:56:48'),(44,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; CDY-NX9B; HMSCore 6.15.4.342) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.5735.196 HuaweiBrowser/16.0.9.302 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-28 23:58:08','2026-03-28 23:58:08'),(45,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','175.158.236.26','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-29 02:09:34','2026-03-29 02:09:34'),(46,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-29 03:47:47','2026-03-29 03:47:47'),(47,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','175.158.236.26','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-29 04:39:58','2026-03-29 04:39:58'),(48,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','175.158.236.26','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-29 06:11:02','2026-03-29 06:11:02'),(49,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','175.158.236.26','Mozilla/5.0 (Linux; Android 10; CDY-NX9B Build/HUAWEICDY-N29B; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/548.0.0.37.65;]','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-29 06:43:42','2026-03-29 06:43:42'),(50,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-29 07:07:45','2026-03-29 07:07:45'),(51,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-29 07:38:53','2026-03-29 07:38:53'),(52,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-29 12:24:52','2026-03-29 12:24:52'),(53,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-30 02:57:44','2026-03-30 02:57:44'),(54,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-30 07:37:04','2026-03-30 07:37:04'),(55,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-30 08:17:40','2026-03-30 08:17:40'),(56,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-30 09:13:16','2026-03-30 09:13:16'),(57,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','158.62.80.48','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-30 12:17:33','2026-03-30 12:17:33'),(58,2,4,'tenant_admin','inventory','restock','PUT','/tenant/ingredients/14','Restock (notifications) [ID: 14, Spicy Beef Noddles] stock change: +3.00 (was 2.00 → 5.00)','158.62.80.48','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','{\"ingredient_id\":14,\"ingredient_name\":\"Spicy Beef Noddles\",\"previous_stock\":2,\"stock_change\":3,\"source\":\"notifications\"}','2026-03-30 12:24:52','2026-03-30 12:24:52'),(59,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','158.62.80.48','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-30 14:06:25','2026-03-30 14:06:25'),(60,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-30 17:07:40','2026-03-30 17:07:40'),(61,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Linux; Android 15; 23078PND5G) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.5993.90 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-30 18:49:25','2026-03-30 18:49:25'),(62,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Linux; Android 15; 23078PND5G Build/AP3A.240617.008; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/146.0.7680.120 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-30 19:18:50','2026-03-30 19:18:50'),(63,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Linux; Android 15; 23078PND5G Build/AP3A.240617.008; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/146.0.7680.120 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-30 19:21:05','2026-03-30 19:21:05'),(64,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','158.62.80.48','Mozilla/5.0 (Linux; Android 8.1.0; CPH1909 Build/O11019; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/138.0.7204.180 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-30 22:43:44','2026-03-30 22:43:44'),(65,2,4,'tenant_admin','transactions','void','DELETE','/tenant/transactions/25','Transaction #25 marked as void','158.62.80.48','Mozilla/5.0 (Linux; Android 8.1.0; CPH1909 Build/O11019; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/138.0.7204.180 Mobile Safari/537.36','{\"transaction_id\":25,\"status\":\"void\"}','2026-03-30 22:44:21','2026-03-30 22:44:21'),(66,2,4,'tenant_admin','transactions','unvoid','DELETE','/tenant/transactions/25','Transaction #25 marked as completed','158.62.80.48','Mozilla/5.0 (Linux; Android 8.1.0; CPH1909 Build/O11019; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/138.0.7204.180 Mobile Safari/537.36','{\"transaction_id\":25,\"status\":\"completed\"}','2026-03-30 22:44:32','2026-03-30 22:44:32'),(67,2,4,'tenant_admin','transactions','void','DELETE','/tenant/transactions/25','Transaction #25 marked as void','158.62.80.48','Mozilla/5.0 (Linux; Android 8.1.0; CPH1909 Build/O11019; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/138.0.7204.180 Mobile Safari/537.36','{\"transaction_id\":25,\"status\":\"void\"}','2026-03-30 22:44:39','2026-03-30 22:44:39'),(68,2,4,'tenant_admin','transactions','void','DELETE','/tenant/transactions/26','Transaction #26 marked as void','158.62.80.48','Mozilla/5.0 (Linux; Android 8.1.0; CPH1909 Build/O11019; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/138.0.7204.180 Mobile Safari/537.36','{\"transaction_id\":26,\"status\":\"void\"}','2026-03-30 22:46:18','2026-03-30 22:46:18'),(69,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','175.158.236.26','Mozilla/5.0 (Linux; Android 8.1.0; CPH1909 Build/O11019; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/138.0.7204.180 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-31 01:31:25','2026-03-31 01:31:25'),(70,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','175.158.236.26','Mozilla/5.0 (Linux; Android 8.1.0; CPH1909 Build/O11019; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/138.0.7204.180 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-31 02:18:11','2026-03-31 02:18:11'),(71,2,4,'tenant_admin','transactions','void','DELETE','/tenant/transactions/27','Transaction #27 marked as void','175.158.236.26','Mozilla/5.0 (Linux; Android 8.1.0; CPH1909 Build/O11019; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/138.0.7204.180 Mobile Safari/537.36','{\"transaction_id\":27,\"status\":\"void\"}','2026-03-31 02:22:47','2026-03-31 02:22:47'),(72,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-31 05:20:16','2026-03-31 05:20:16'),(73,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Linux; Android 15; 23078PND5G Build/AP3A.240617.008; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/146.0.7680.120 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-31 06:26:38','2026-03-31 06:26:38'),(74,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','175.158.236.26','Mozilla/5.0 (Linux; Android 8.1.0; CPH1909 Build/O11019; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/138.0.7204.180 Mobile Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-31 06:32:47','2026-03-31 06:32:47'),(75,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-31 12:25:05','2026-03-31 12:25:05'),(76,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-31 13:27:36','2026-03-31 13:27:36'),(77,2,4,'tenant_admin','transactions','void','DELETE','/tenant/transactions/29','Transaction #29 marked as void','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"transaction_id\":29,\"status\":\"void\"}','2026-03-31 13:30:51','2026-03-31 13:30:51'),(78,2,4,'tenant_admin','transactions','unvoid','DELETE','/tenant/transactions/29','Transaction #29 marked as completed','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"transaction_id\":29,\"status\":\"completed\"}','2026-03-31 13:31:02','2026-03-31 13:31:02'),(79,2,4,'tenant_admin','inventory','restock','PUT','/tenant/ingredients/8','Restock (notifications) [ID: 8, Pancit Canton] stock change: +2.00 (was 4.00 → 6.00)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"ingredient_id\":8,\"ingredient_name\":\"Pancit Canton\",\"previous_stock\":4,\"stock_change\":2,\"source\":\"notifications\"}','2026-03-31 13:33:16','2026-03-31 13:33:16'),(80,2,4,'tenant_admin','inventory','restock','PUT','/tenant/ingredients/8','Restock (notifications) [ID: 8, Pancit Canton] stock change: +0.00 (was 6.00 → 6.00)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"ingredient_id\":8,\"ingredient_name\":\"Pancit Canton\",\"previous_stock\":6,\"stock_change\":0,\"source\":\"notifications\"}','2026-03-31 13:33:16','2026-03-31 13:33:16'),(81,2,4,'tenant_admin','inventory','restock','PUT','/tenant/ingredients/8','Restock (notifications) [ID: 8, Pancit Canton] stock change: +0.00 (was 6.00 → 6.00)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"ingredient_id\":8,\"ingredient_name\":\"Pancit Canton\",\"previous_stock\":6,\"stock_change\":0,\"source\":\"notifications\"}','2026-03-31 13:33:16','2026-03-31 13:33:16'),(82,2,4,'tenant_admin','inventory','restock','PUT','/tenant/ingredients/8','Restock (notifications) [ID: 8, Pancit Canton] stock change: +0.00 (was 6.00 → 6.00)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"ingredient_id\":8,\"ingredient_name\":\"Pancit Canton\",\"previous_stock\":6,\"stock_change\":0,\"source\":\"notifications\"}','2026-03-31 13:33:16','2026-03-31 13:33:16'),(83,2,4,'tenant_admin','inventory','restock','PUT','/tenant/ingredients/8','Restock (notifications) [ID: 8, Pancit Canton] stock change: +0.00 (was 6.00 → 6.00)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"ingredient_id\":8,\"ingredient_name\":\"Pancit Canton\",\"previous_stock\":6,\"stock_change\":0,\"source\":\"notifications\"}','2026-03-31 13:33:16','2026-03-31 13:33:16'),(84,2,4,'tenant_admin','inventory','restock','PUT','/tenant/ingredients/8','Restock (notifications) [ID: 8, Pancit Canton] stock change: +0.00 (was 6.00 → 6.00)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"ingredient_id\":8,\"ingredient_name\":\"Pancit Canton\",\"previous_stock\":6,\"stock_change\":0,\"source\":\"notifications\"}','2026-03-31 13:33:16','2026-03-31 13:33:16'),(85,2,4,'tenant_admin','inventory','restock','PUT','/tenant/ingredients/8','Restock (notifications) [ID: 8, Pancit Canton] stock change: +0.00 (was 6.00 → 6.00)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"ingredient_id\":8,\"ingredient_name\":\"Pancit Canton\",\"previous_stock\":6,\"stock_change\":0,\"source\":\"notifications\"}','2026-03-31 13:33:16','2026-03-31 13:33:16'),(86,2,4,'tenant_admin','inventory','restock','PUT','/tenant/ingredients/8','Restock (notifications) [ID: 8, Pancit Canton] stock change: +0.00 (was 6.00 → 6.00)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"ingredient_id\":8,\"ingredient_name\":\"Pancit Canton\",\"previous_stock\":6,\"stock_change\":0,\"source\":\"notifications\"}','2026-03-31 13:33:16','2026-03-31 13:33:16'),(87,2,4,'tenant_admin','inventory','restock','PUT','/tenant/ingredients/8','Restock (notifications) [ID: 8, Pancit Canton] stock change: +0.00 (was 6.00 → 6.00)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"ingredient_id\":8,\"ingredient_name\":\"Pancit Canton\",\"previous_stock\":6,\"stock_change\":0,\"source\":\"notifications\"}','2026-03-31 13:33:18','2026-03-31 13:33:18'),(88,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','158.62.80.48','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-03-31 23:03:21','2026-03-31 23:03:21'),(89,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','175.158.236.26','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-01 01:55:31','2026-04-01 01:55:31'),(90,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','175.158.236.26','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-01 04:15:56','2026-04-01 04:15:56'),(91,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','175.158.236.26','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-01 04:15:57','2026-04-01 04:15:57'),(92,2,4,'tenant_admin','transactions','void','DELETE','/tenant/transactions/49','Transaction #49 marked as void','175.158.236.26','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"transaction_id\":49,\"status\":\"void\"}','2026-04-01 04:48:15','2026-04-01 04:48:15'),(93,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','175.158.236.26','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-01 06:17:26','2026-04-01 06:17:26'),(94,2,4,'tenant_admin','transactions','void','DELETE','/tenant/transactions/64','Transaction #64 marked as void','175.158.236.26','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"transaction_id\":64,\"status\":\"void\"}','2026-04-01 07:42:36','2026-04-01 07:42:36'),(95,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','175.158.236.26','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-01 08:19:32','2026-04-01 08:19:32'),(96,2,4,'tenant_admin','transactions','void','DELETE','/tenant/transactions/65','Transaction #65 marked as void','175.158.236.26','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"transaction_id\":65,\"status\":\"void\"}','2026-04-01 08:35:19','2026-04-01 08:35:19'),(97,2,4,'tenant_admin','transactions','void','DELETE','/tenant/transactions/68','Transaction #68 marked as void','175.158.236.26','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"transaction_id\":68,\"status\":\"void\"}','2026-04-01 08:38:05','2026-04-01 08:38:05'),(98,2,4,'tenant_admin','transactions','void','DELETE','/tenant/transactions/69','Transaction #69 marked as void','175.158.236.26','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"transaction_id\":69,\"status\":\"void\"}','2026-04-01 08:38:15','2026-04-01 08:38:15'),(99,2,4,'tenant_admin','inventory','restock','PUT','/tenant/ingredients/32','Restock (notifications) [ID: 32, Mangoes] stock change: +4905.00 (was 95.00 → 5000.00)','175.158.236.26','Mozilla/5.0 (Linux; Android 10; AGS6-W09 Build/HUAWEIAGS6-W09; ) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Safari/537.36','{\"ingredient_id\":32,\"ingredient_name\":\"Mangoes\",\"previous_stock\":95,\"stock_change\":4905,\"source\":\"notifications\"}','2026-04-01 09:45:16','2026-04-01 09:45:16'),(100,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','103.129.125.132','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-01 15:36:10','2026-04-01 15:36:10'),(102,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-01 16:05:17','2026-04-01 16:05:17'),(104,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-01 16:07:58','2026-04-01 16:07:58'),(105,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-01 18:07:47','2026-04-01 18:07:47'),(106,NULL,1,'super_admin','backups','run_forced_backups','POST','/super-admin/backups/runner/force','Force-triggered backups for all active stores.','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"created\":0,\"failed\":0}','2026-04-01 18:10:10','2026-04-01 18:10:10'),(107,NULL,1,'super_admin','backups','run_forced_backups','POST','/super-admin/backups/runner/force','Force-triggered backups for all active stores.','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"created\":0,\"failed\":0}','2026-04-01 18:23:38','2026-04-01 18:23:38'),(108,NULL,1,'super_admin','backups','run_forced_backups','POST','/super-admin/backups/runner/force','Force-triggered backups for all active stores.','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"created\":0,\"failed\":0}','2026-04-01 18:23:47','2026-04-01 18:23:47'),(109,NULL,1,'super_admin','backups','run_forced_backups','POST','/super-admin/backups/runner/force','Force-triggered backups for all active stores.','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"created\":2,\"failed\":0}','2026-04-01 18:25:23','2026-04-01 18:25:23'),(110,NULL,1,'super_admin','backups','create_snapshot','POST','/super-admin/tenants/1/backups','Created tenant snapshot for store #1','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":1,\"tenant_slug\":\"demo-store\",\"backup_id\":5,\"backup_key\":\"tenant-1-manual-20260402-022606-72e48735\"}','2026-04-01 18:26:06','2026-04-01 18:26:06'),(111,2,4,'tenant_admin','branches','branch_toggle_self_service','POST','/tenant/branches/2/toggle-active','Store owner toggled branch status','::1','Mozilla/5.0 (iPad; CPU OS 11_0 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) Version/11.0 Mobile/15A5341f Safari/604.1','{\"tenant_id\":2,\"branch_id\":2,\"active\":false}','2026-04-01 18:50:45','2026-04-01 18:50:45'),(112,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','::1','Mozilla/5.0 (iPad; CPU OS 11_0 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) Version/11.0 Mobile/15A5341f Safari/604.1','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-01 18:51:37','2026-04-01 18:51:37'),(113,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','::1','Mozilla/5.0 (iPad; CPU OS 11_0 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) Version/11.0 Mobile/15A5341f Safari/604.1','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-01 18:52:19','2026-04-01 18:52:19'),(114,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-01 18:58:29','2026-04-01 18:58:29'),(115,NULL,1,'super_admin','branches','branch_limit_update','POST','/super-admin/tenants/2/branches/limit','Updated branch limit','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"max_branches\":2}','2026-04-01 18:58:38','2026-04-01 18:58:38'),(116,2,4,'tenant_admin','branches','branch_create_self_service','POST','/tenant/branches','Store owner created branch','::1','Mozilla/5.0 (iPad; CPU OS 11_0 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) Version/11.0 Mobile/15A5341f Safari/604.1','{\"tenant_id\":2,\"new_tenant_id\":3}','2026-04-01 18:58:48','2026-04-01 18:58:48'),(117,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (iPad; CPU OS 11_0 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) Version/11.0 Mobile/15A5341f Safari/604.1','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 18:58:57','2026-04-01 18:58:57'),(118,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (iPad; CPU OS 11_0 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) Version/11.0 Mobile/15A5341f Safari/604.1','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 18:59:39','2026-04-01 18:59:39'),(119,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (iPad; CPU OS 11_0 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) Version/11.0 Mobile/15A5341f Safari/604.1','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 19:01:22','2026-04-01 19:01:22'),(120,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 19:07:23','2026-04-01 19:07:23'),(121,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 19:07:44','2026-04-01 19:07:44'),(122,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 19:07:56','2026-04-01 19:07:56'),(123,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 19:11:21','2026-04-01 19:11:21'),(124,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 19:11:39','2026-04-01 19:11:39'),(125,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 19:16:32','2026-04-01 19:16:32'),(126,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 19:16:43','2026-04-01 19:16:43'),(127,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 19:23:56','2026-04-01 19:23:56'),(128,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 19:24:10','2026-04-01 19:24:10'),(129,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 19:25:23','2026-04-01 19:25:23'),(130,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 19:25:26','2026-04-01 19:25:26'),(131,2,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/3/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"branch_id\":3}','2026-04-01 19:25:31','2026-04-01 19:25:31'),(132,2,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/3/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"branch_id\":3}','2026-04-01 19:25:47','2026-04-01 19:25:47'),(133,2,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/3/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"branch_id\":3}','2026-04-01 19:28:02','2026-04-01 19:28:02'),(134,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 19:28:21','2026-04-01 19:28:21'),(135,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 19:28:26','2026-04-01 19:28:26'),(136,NULL,1,'super_admin','branches','branch_set_main','POST','/super-admin/tenants/2/branches/3/set-main','Super admin set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"branch_id\":3}','2026-04-01 19:28:52','2026-04-01 19:28:52'),(137,2,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/3/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"branch_id\":3}','2026-04-01 19:30:45','2026-04-01 19:30:45'),(138,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 19:31:01','2026-04-01 19:31:01'),(139,3,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/2/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"branch_id\":2}','2026-04-01 19:33:11','2026-04-01 19:33:11'),(140,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 19:35:04','2026-04-01 19:35:04'),(141,3,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/3/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"branch_id\":3}','2026-04-01 19:35:10','2026-04-01 19:35:10'),(142,2,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/2/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"branch_id\":2}','2026-04-01 19:35:19','2026-04-01 19:35:19'),(143,3,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/3/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"branch_id\":3}','2026-04-01 19:35:25','2026-04-01 19:35:25'),(144,2,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/2/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"branch_id\":2}','2026-04-01 19:35:32','2026-04-01 19:35:32'),(145,3,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/3/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"branch_id\":3}','2026-04-01 19:37:17','2026-04-01 19:37:17'),(146,2,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/2/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"branch_id\":2}','2026-04-01 19:37:22','2026-04-01 19:37:22'),(147,3,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/3/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"branch_id\":3}','2026-04-01 19:37:27','2026-04-01 19:37:27'),(148,2,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/2/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"branch_id\":2}','2026-04-01 19:37:52','2026-04-01 19:37:52'),(149,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 19:37:58','2026-04-01 19:37:58'),(150,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 19:38:04','2026-04-01 19:38:04'),(151,3,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/3/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"branch_id\":3}','2026-04-01 19:38:15','2026-04-01 19:38:15'),(152,2,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/2/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"branch_id\":2}','2026-04-01 19:38:24','2026-04-01 19:38:24'),(153,3,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/3/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"branch_id\":3}','2026-04-01 19:38:34','2026-04-01 19:38:34'),(154,3,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/3/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"branch_id\":3}','2026-04-01 19:38:34','2026-04-01 19:38:34'),(155,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-01 19:38:52','2026-04-01 19:38:52'),(156,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 19:38:55','2026-04-01 19:38:55'),(157,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-01 19:39:00','2026-04-01 19:39:00'),(158,2,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/2/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"branch_id\":2}','2026-04-01 19:39:06','2026-04-01 19:39:06'),(159,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 19:39:09','2026-04-01 19:39:09'),(160,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-01 19:39:14','2026-04-01 19:39:14'),(161,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 19:40:12','2026-04-01 19:40:12'),(162,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 19:40:20','2026-04-01 19:40:20'),(163,3,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/3/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"branch_id\":3}','2026-04-01 19:40:25','2026-04-01 19:40:25'),(164,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 19:46:52','2026-04-01 19:46:52'),(165,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 19:47:00','2026-04-01 19:47:00'),(166,2,4,'tenant_admin','branches','branch_set_main_self_service','POST','/tenant/branches/2/set-main','Store owner set main branch','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"branch_id\":2}','2026-04-01 19:47:05','2026-04-01 19:47:05'),(167,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 19:47:18','2026-04-01 19:47:18'),(168,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-01 19:49:06','2026-04-01 19:49:06'),(169,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 20:05:55','2026-04-01 20:05:55'),(170,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-01 20:06:01','2026-04-01 20:06:01'),(171,2,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":3,\"target_branch_id\":2}','2026-04-01 20:22:37','2026-04-01 20:22:37'),(220,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-12 16:44:52','2026-04-12 16:44:52'),(221,NULL,1,'super_admin','backups','create_snapshot','POST','/super-admin/tenants/1/backups','Created tenant snapshot for store #1','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','{\"tenant_id\":1,\"tenant_slug\":\"demo-store\",\"backup_id\":6,\"backup_key\":\"tenant-1-manual-20260413-004515-87400594\"}','2026-04-12 16:45:15','2026-04-12 16:45:15'),(226,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-15 19:11:08','2026-04-15 19:11:08'),(227,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-15 21:39:30','2026-04-15 21:39:30'),(228,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-15 22:37:25','2026-04-15 22:37:25'),(229,NULL,1,'super_admin','backups','run_forced_backups','POST','/super-admin/backups/runner/force','Force-triggered backups for all active stores.','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"created\":4,\"failed\":0}','2026-04-15 22:38:05','2026-04-15 22:38:05'),(233,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-16 05:11:58','2026-04-16 05:11:58'),(234,3,4,'tenant_admin','branches','branch_switch_self_service','POST','/tenant/branches/switch','Store owner switched active branch context','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"tenant_id\":2,\"target_branch_id\":3}','2026-04-16 05:46:43','2026-04-16 05:46:43'),(235,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-16 05:46:55','2026-04-16 05:46:55'),(236,2,4,'tenant_admin','auth','login','POST','/login','Successful login: Maricris Gulle (maricrisgulle@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"maricrisgulle@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-16 06:52:37','2026-04-16 06:52:37'),(237,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-19 17:20:42','2026-04-19 17:20:42'),(238,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-19 17:24:32','2026-04-19 17:24:32'),(240,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-19 17:43:26','2026-04-19 17:43:26'),(241,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-20 07:29:04','2026-04-20 07:29:04'),(242,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-20 07:30:27','2026-04-20 07:30:27'),(243,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-20 07:41:54','2026-04-20 07:41:54'),(244,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-20 08:21:27','2026-04-20 08:21:27'),(245,1,3,'cashier','auth','login','POST','/login','Successful login: Cashier One (cashier@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"cashier@demo.store\",\"role\":\"cashier\"}','2026-04-20 09:05:21','2026-04-20 09:05:21'),(246,1,3,'cashier','auth','login','POST','/login','Successful login: Cashier One (cashier@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"cashier@demo.store\",\"role\":\"cashier\"}','2026-04-20 09:09:49','2026-04-20 09:09:49'),(247,1,3,'cashier','inventory','restock','PUT','/tenant/ingredients/4','Restock (notifications) [ID: 4, Cup] stock change: 0 (was 63 → 63)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"ingredient_id\":4,\"ingredient_name\":\"Cup\",\"previous_stock\":63,\"stock_change\":0,\"source\":\"notifications\"}','2026-04-20 09:59:07','2026-04-20 09:59:07'),(248,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-20 10:06:26','2026-04-20 10:06:26'),(249,1,2,'tenant_admin','branches','branch_laundry_config_update','POST','/tenant/branches/laundry-config','Store owner updated branch laundry config','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"tenant_id\":1,\"machine_assignment_enabled\":false}','2026-04-20 10:06:40','2026-04-20 10:06:40'),(250,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','::1','Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-20 14:56:48','2026-04-20 14:56:48'),(251,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-20 16:01:14','2026-04-20 16:01:14'),(252,1,2,'tenant_admin','branches','branch_laundry_config_update','POST','/tenant/branches/laundry-config','Store owner updated branch laundry config','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"tenant_id\":1,\"machine_assignment_enabled\":false,\"fold_service_amount\":10,\"fold_commission_target\":\"branch\"}','2026-04-20 16:35:14','2026-04-20 16:35:14'),(253,1,2,'tenant_admin','branches','branch_laundry_config_update','POST','/tenant/branches/laundry-config','Store owner updated branch laundry config','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"tenant_id\":1,\"machine_assignment_enabled\":false,\"fold_service_amount\":0,\"fold_commission_target\":\"branch\"}','2026-04-20 16:46:18','2026-04-20 16:46:18'),(254,1,2,'tenant_admin','staff','create_cashier','POST','/tenant/staff','Created cashier: Driver (driver@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"new_user_id\":7,\"email\":\"driver@demo.store\",\"modules\":[\"pos\",\"transactions\",\"activity_logs\"]}','2026-04-20 17:06:30','2026-04-20 17:06:30'),(255,1,7,'cashier','auth','login','POST','/login','Successful login: Driver (driver@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"driver@demo.store\",\"role\":\"cashier\"}','2026-04-20 17:06:50','2026-04-20 17:06:50'),(256,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-20 17:09:24','2026-04-20 17:09:24'),(257,1,7,'cashier','auth','login','POST','/login','Successful login: Driver (driver@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"driver@demo.store\",\"role\":\"cashier\"}','2026-04-20 17:12:51','2026-04-20 17:12:51'),(258,1,7,'cashier','auth','login','POST','/login','Successful login: Driver (driver@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"driver@demo.store\",\"role\":\"cashier\"}','2026-04-20 17:15:12','2026-04-20 17:15:12'),(259,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-20 17:15:33','2026-04-20 17:15:33'),(260,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-21 07:24:06','2026-04-21 07:24:06'),(261,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-21 07:52:57','2026-04-21 07:52:57'),(262,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-21 07:53:15','2026-04-21 07:53:15'),(263,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-21 08:23:17','2026-04-21 08:23:17'),(264,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-21 08:23:26','2026-04-21 08:23:26'),(265,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-21 09:15:30','2026-04-21 09:15:30'),(266,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','::1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-21 09:16:54','2026-04-21 09:16:54'),(267,6,9,'tenant_admin','auth','login','POST','/login','Successful login: test1@gmail.com (test1@gmail.com)','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"test1@gmail.com\",\"role\":\"tenant_admin\"}','2026-04-21 09:45:46','2026-04-21 09:45:46'),(268,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-21 09:46:03','2026-04-21 09:46:03'),(269,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-21 09:48:47','2026-04-21 09:48:47'),(270,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-21 09:48:58','2026-04-21 09:48:58'),(271,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-21 09:49:41','2026-04-21 09:49:41'),(272,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-21 10:16:09','2026-04-21 10:16:09'),(273,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-21 10:16:17','2026-04-21 10:16:17'),(274,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-21 10:16:38','2026-04-21 10:16:38'),(275,NULL,1,'super_admin','auth','login','POST','/login','Successful login: Platform Owner (mtech1897@gmail.com)','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"mtech1897@gmail.com\",\"role\":\"super_admin\"}','2026-04-21 10:38:38','2026-04-21 10:38:38'),(276,1,2,'tenant_admin','auth','login','POST','/login','Successful login: Store Admin (admin@demo.store)','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','{\"email\":\"admin@demo.store\",\"role\":\"tenant_admin\"}','2026-04-21 10:38:55','2026-04-21 10:38:55');

--
-- Table structure for table `app_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `app_settings` (
  `key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_settings`
--

INSERT IGNORE INTO `app_settings` VALUES ('app_name','Kiosk + Inventory System'),('backup_retention_days','30'),('maintenance_message','test'),('maintenance_mode','0'),('subscription_warning_days','7');

--
-- Table structure for table `cache`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

INSERT IGNORE INTO `cache` VALUES ('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba','i:1;',1774594558),('laravel-cache-5c785c036466adea360111aa28563bfd556b5fba:timer','i:1774594558;',1774594558),('laravel-cache-da4b9237bacccdf19c0760cab7aec4a8359010b0','i:7;',1774617186),('laravel-cache-da4b9237bacccdf19c0760cab7aec4a8359010b0:timer','i:1774617186;',1774617186);

--
-- Table structure for table `cache_locks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--


--
-- Table structure for table `categories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_tenant_id_name_unique` (`tenant_id`,`name`),
  KEY `categories_tenant_id_index` (`tenant_id`),
  CONSTRAINT `categories_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--


--
-- Table structure for table `damaged_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `damaged_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ingredient_id` bigint unsigned NOT NULL,
  `quantity` decimal(38,16) NOT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `damaged_items_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `damaged_items_ingredient_id_foreign` (`ingredient_id`),
  CONSTRAINT `damaged_items_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `damaged_items_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `damaged_items`
--


--
-- Table structure for table `expenses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ingredient_id` bigint unsigned DEFAULT NULL,
  `transaction_id` bigint unsigned DEFAULT NULL,
  `type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(38,16) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expenses_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `expenses_user_id_foreign` (`user_id`),
  KEY `expenses_ingredient_id_foreign` (`ingredient_id`),
  KEY `expenses_transaction_id_foreign` (`transaction_id`),
  CONSTRAINT `expenses_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

INSERT IGNORE INTO `expenses` VALUES (27,2,4,NULL,NULL,'manual','ice',75.0000000000000000,'2026-03-31 03:12:51','2026-03-31 03:12:51'),(28,1,2,NULL,NULL,'manual','dawasdawdwa',1000.0000000000000000,'2026-04-20 15:57:47','2026-04-20 15:57:57');

--
-- Table structure for table `failed_jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--


--
-- Table structure for table `ingredients`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `ingredients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `unit_cost` decimal(38,16) NOT NULL DEFAULT '0.0000000000000000',
  `stock_quantity` decimal(38,16) NOT NULL DEFAULT '0.0000000000000000',
  `low_stock_threshold` decimal(38,16) NOT NULL DEFAULT '0.0000000000000000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ingredients_tenant_id_name_unique` (`tenant_id`,`name`),
  KEY `ingredients_tenant_id_index` (`tenant_id`),
  CONSTRAINT `ingredients_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ingredients`
--

INSERT IGNORE INTO `ingredients` VALUES (1,1,'Mango','g','general',1.0000000000000000,2128.0000000000000000,500.0000000000000000,'2026-03-26 05:52:33','2026-03-27 05:08:12'),(2,1,'Milk','ml','general',1.0000000000000000,5794.0000000000000000,1000.0000000000000000,'2026-03-26 05:52:33','2026-03-27 05:08:12'),(3,1,'Sugar','g','general',1.0000000000000000,2616.0000000000000000,400.0000000000000000,'2026-03-26 05:52:33','2026-03-27 05:08:12'),(4,1,'Cup','pc','general',1.0000000000000000,63.0000000000000000,100.0000000000000000,'2026-03-26 06:00:48','2026-04-20 09:59:07'),(5,1,'Takoyaki Cup - Small','pc','general',1.0000000000000000,180.1266666333500000,5.0000000000000000,'2026-03-26 06:06:43','2026-04-06 17:47:37'),(6,2,'6.5oz cup','pc','general',0.0000000000000000,742.0000000000000000,50.0000000000000000,'2026-03-28 20:38:29','2026-03-29 02:25:57'),(7,2,'Spicy Pancit Canton','pc','general',0.0000000000000000,12.0000000000000000,5.0000000000000000,'2026-03-29 02:10:15','2026-03-29 05:41:40'),(8,2,'Pancit Canton','pc','general',0.0000000000000000,6.0000000000000000,5.0000000000000000,'2026-03-29 02:23:02','2026-03-31 13:33:18'),(9,2,'Bearbrand','pc','general',0.0000000000000000,1.0000000000000000,3.0000000000000000,'2026-03-29 02:23:19','2026-03-29 02:25:35'),(10,2,'Nescafe 3in1','pc','general',0.0000000000000000,20.0000000000000000,5.0000000000000000,'2026-03-29 02:24:24','2026-03-29 02:24:24'),(11,2,'Kopiko 3in1','pc','general',0.0000000000000000,15.0000000000000000,5.0000000000000000,'2026-03-29 02:24:39','2026-03-29 02:24:39'),(12,2,'Milo','pc','general',0.0000000000000000,4.0000000000000000,2.0000000000000000,'2026-03-29 02:24:47','2026-03-29 02:24:47'),(13,2,'Nescafe Stick','pc','general',0.0000000000000000,6.0000000000000000,3.0000000000000000,'2026-03-29 02:25:11','2026-03-29 02:25:35'),(14,2,'Spicy Beef Noddles','pc','general',0.0000000000000000,5.0000000000000000,2.0000000000000000,'2026-03-29 02:26:32','2026-03-30 12:24:52'),(15,2,'8oz cup','pc','general',0.0000000000000000,43.0000000000000000,5.0000000000000000,'2026-03-29 02:28:11','2026-03-29 02:28:11'),(16,2,'8oz Y cup','pc','general',0.0000000000000000,894.0000000000000000,100.0000000000000000,'2026-03-29 02:32:45','2026-03-29 02:32:59'),(17,2,'12oz Y cup','pc','general',0.0000000000000000,304.0000000000000000,100.0000000000000000,'2026-03-29 02:34:50','2026-03-29 02:34:50'),(18,2,'8\'s box','pc','general',0.0000000000000000,29.0000000000000000,15.0000000000000000,'2026-03-29 02:36:09','2026-03-29 02:36:09'),(19,2,'12\'s box','pc','general',0.0000000000000000,31.0000000000000000,15.0000000000000000,'2026-03-29 02:37:01','2026-03-29 02:40:26'),(20,2,'16\'s box','pc','general',0.0000000000000000,17.0000000000000000,10.0000000000000000,'2026-03-29 02:37:30','2026-03-29 02:37:30'),(21,2,'Siomai tray','pc','general',0.0000000000000000,386.0000000000000000,40.0000000000000000,'2026-03-29 02:39:19','2026-03-29 02:39:19'),(22,2,'Bobba Straw','pc','general',0.0000000000000000,936.0000000000000000,100.0000000000000000,'2026-03-29 02:47:44','2026-03-29 02:47:44'),(23,2,'Coke Swakto','pc','general',0.0000000000000000,16.0000000000000000,5.0000000000000000,'2026-03-29 02:53:46','2026-03-29 02:53:46'),(24,2,'Royal Swakto','pc','general',0.0000000000000000,6.0000000000000000,5.0000000000000000,'2026-03-29 02:54:06','2026-03-29 02:54:06'),(25,2,'Egg Peewee','pc','general',0.0000000000000000,0.0000000000000000,5.0000000000000000,'2026-03-29 02:54:32','2026-03-29 02:54:32'),(26,2,'Egg small','pc','general',0.0000000000000000,32.0000000000000000,6.0000000000000000,'2026-03-29 02:54:50','2026-03-29 02:54:50'),(28,2,'Milk Syrup','ml','general',0.0000000000000000,70.0000000000000000,500.0000000000000000,'2026-03-29 02:56:14','2026-03-29 02:59:58'),(31,2,'Crushed Graham','g','general',0.0000000000000000,1501.0000000000000000,500.0000000000000000,'2026-03-29 03:01:19','2026-03-29 03:01:19'),(32,2,'Mangoes','g','general',0.0000000000000000,4015.0000000000000000,500.0000000000000000,'2026-03-29 03:01:47','2026-04-01 09:45:16'),(33,2,'Condensed Milk','g','general',0.0000000000000000,309.0000000000000000,50.0000000000000000,'2026-03-29 03:02:27','2026-03-29 03:02:27'),(34,2,'16oz Y cup','pc','general',0.0000000000000000,144.0000000000000000,25.0000000000000000,'2026-03-29 03:11:52','2026-03-29 03:11:52'),(35,2,'22oz Y cup','pc','general',0.0000000000000000,79.0000000000000000,15.0000000000000000,'2026-03-29 03:12:24','2026-03-29 03:12:24'),(36,2,'220cc cup','pc','general',0.0000000000000000,161.0000000000000000,30.0000000000000000,'2026-03-29 03:13:03','2026-03-29 03:13:03'),(37,2,'320cc cup','pc','general',0.0000000000000000,157.0000000000000000,25.0000000000000000,'2026-03-29 03:13:23','2026-03-29 03:13:23'),(38,2,'390cc cup','pc','general',0.0000000000000000,126.0000000000000000,25.0000000000000000,'2026-03-29 03:13:59','2026-03-29 03:13:59'),(39,2,'Mineral water 500','pc','general',0.0000000000000000,22.0000000000000000,5.0000000000000000,'2026-03-29 03:14:16','2026-03-29 03:14:16'),(40,2,'Mineral Water','pc','general',0.0000000000000000,3.0000000000000000,3.0000000000000000,'2026-03-29 03:14:43','2026-03-29 03:14:43'),(41,2,'Dome lids','pc','general',0.0000000000000000,1473.0000000000000000,100.0000000000000000,'2026-03-29 03:25:20','2026-03-29 03:25:20'),(42,2,'Hotdog','pc','general',0.0000000000000000,98.0000000000000000,5.0000000000000000,'2026-03-29 05:07:39','2026-03-29 05:07:39'),(43,2,'Meatloaf','pc','general',0.0000000000000000,46.0000000000000000,6.0000000000000000,'2026-03-29 05:07:49','2026-03-29 05:07:49'),(44,2,'Siomai Beef','pc','general',0.0000000000000000,64.0000000000000000,20.0000000000000000,'2026-03-29 05:08:15','2026-03-29 05:08:15'),(45,2,'Siomai Chicken','pc','general',0.0000000000000000,80.0000000000000000,20.0000000000000000,'2026-03-29 05:08:34','2026-03-29 05:08:34'),(46,2,'Siomai Pork','pc','general',0.0000000000000000,88.0000000000000000,20.0000000000000000,'2026-03-29 05:08:44','2026-03-29 05:08:44'),(47,2,'Skinless','pc','general',0.0000000000000000,48.0000000000000000,6.0000000000000000,'2026-03-29 05:09:04','2026-03-29 05:09:04'),(48,2,'Sisig','pc','general',0.0000000000000000,42.0000000000000000,10.0000000000000000,'2026-03-29 05:09:16','2026-03-29 05:09:16'),(49,2,'Shawarma','pc','general',0.0000000000000000,48.0000000000000000,10.0000000000000000,'2026-03-29 05:09:28','2026-03-29 05:09:28'),(50,2,'Frenchfries','g','general',0.0000000000000000,1520.0000000000000000,500.0000000000000000,'2026-03-29 05:09:50','2026-03-29 05:09:50'),(51,2,'Cheese Powder','g','general',0.0000000000000000,500.0000000000000000,50.0000000000000000,'2026-03-29 05:10:41','2026-03-29 05:10:41'),(52,2,'Barbeque Powder','g','general',0.0000000000000000,500.0000000000000000,50.0000000000000000,'2026-03-29 05:10:55','2026-03-29 05:10:55'),(53,2,'Sour Cream Powder','g','general',0.0000000000000000,500.0000000000000000,50.0000000000000000,'2026-03-29 05:11:09','2026-03-29 05:11:09'),(54,2,'Tuna Flakes','g','general',0.0000000000000000,500.0000000000000000,40.0000000000000000,'2026-03-29 05:11:44','2026-03-29 05:11:44'),(55,2,'Aonori','g','general',0.0000000000000000,500.0000000000000000,10.0000000000000000,'2026-03-29 05:11:57','2026-03-29 05:11:57'),(56,2,'Mini Fork','pack','general',0.0000000000000000,30.0000000000000000,5.0000000000000000,'2026-03-29 05:12:20','2026-03-29 05:12:20'),(57,2,'Takoyaki Powder','pc','general',0.0000000000000000,100.0000000000000000,20.0000000000000000,'2026-03-29 05:13:31','2026-03-29 05:13:31'),(58,2,'Rice','g','general',0.0000000000000000,980.0000000000000000,1000.0000000000000000,'2026-03-29 05:14:55','2026-03-29 05:14:55'),(59,2,'Spoon','pc','general',0.0000000000000000,82.0000000000000000,20.0000000000000000,'2026-03-29 05:37:05','2026-03-29 05:37:05'),(60,2,'Fork','pc','general',0.0000000000000000,47.0000000000000000,10.0000000000000000,'2026-03-29 05:37:28','2026-03-29 05:37:28'),(61,2,'Takoyaki Sauce','g','general',0.0000000000000000,5000.0000000000000000,1.0000000000000000,'2026-03-29 05:38:09','2026-03-29 05:38:09'),(62,2,'Spicy Sauce','bottle','general',0.0000000000000000,10.0000000000000000,2.0000000000000000,'2026-03-29 05:38:50','2026-03-29 05:38:50'),(63,2,'Chili Powder','tsp','general',0.0000000000000000,25.0000000000000000,4.0000000000000000,'2026-03-29 05:39:15','2026-03-29 05:39:15'),(64,2,'Food Coloring','pc','general',0.0000000000000000,25.0000000000000000,5.0000000000000000,'2026-03-29 05:39:29','2026-03-29 05:39:29'),(65,2,'Cooking Oil','g','general',0.0000000000000000,5000.0000000000000000,500.0000000000000000,'2026-03-29 05:40:12','2026-03-29 05:40:12'),(66,2,'Pancit Canton tray','pc','general',0.0000000000000000,72.0000000000000000,10.0000000000000000,'2026-03-29 06:16:06','2026-03-29 06:16:06'),(67,2,'Mango small','pc','general',0.0000000000000000,50.0000000000000000,20.0000000000000000,'2026-03-29 07:20:16','2026-03-29 07:20:16'),(68,2,'Mango big','pc','general',0.0000000000000000,50.0000000000000000,20.0000000000000000,'2026-03-29 07:20:51','2026-03-29 07:20:51'),(69,2,'Mangoes Small','pc','general',0.0000000000000000,500.0000000000000000,20.0000000000000000,'2026-03-30 12:42:53','2026-03-30 12:42:53'),(70,2,'Mangoes Big','pc','general',0.0000000000000000,500.0000000000000000,20.0000000000000000,'2026-03-30 12:43:06','2026-03-30 12:43:06'),(71,2,'Mineral','pc','general',0.0000000000000000,10.0000000000000000,5.0000000000000000,'2026-04-01 06:17:50','2026-04-01 06:17:50'),(72,2,'Mineral 1L','pc','general',0.0000000000000000,4.0000000000000000,2.0000000000000000,'2026-04-01 06:18:05','2026-04-01 06:18:05'),(73,2,'Japanese','pc','general',0.0000000000000000,32.0000000000000000,10.0000000000000000,'2026-04-01 07:43:40','2026-04-01 07:43:40'),(74,2,'Takoyaki solo','pc','general',0.0000000000000000,50.0000000000000000,10.0000000000000000,'2026-04-01 11:10:15','2026-04-01 11:10:15'),(75,3,'6.5oz cup','pc','general',0.0000000000000000,742.0000000000000000,50.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(76,3,'Spicy Pancit Canton','pc','general',0.0000000000000000,12.0000000000000000,5.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(77,3,'Pancit Canton','pc','general',0.0000000000000000,6.0000000000000000,5.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(78,3,'Bearbrand','pc','general',0.0000000000000000,1.0000000000000000,3.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(79,3,'Nescafe 3in1','pc','general',0.0000000000000000,20.0000000000000000,5.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(80,3,'Kopiko 3in1','pc','general',0.0000000000000000,15.0000000000000000,5.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(81,3,'Milo','pc','general',0.0000000000000000,4.0000000000000000,2.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(82,3,'Nescafe Stick','pc','general',0.0000000000000000,6.0000000000000000,3.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(83,3,'Spicy Beef Noddles','pc','general',0.0000000000000000,5.0000000000000000,2.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(84,3,'8oz cup','pc','general',0.0000000000000000,43.0000000000000000,5.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(85,3,'8oz Y cup','pc','general',0.0000000000000000,894.0000000000000000,100.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(86,3,'12oz Y cup','pc','general',0.0000000000000000,303.0000000000000000,100.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(87,3,'8\'s box','pc','general',0.0000000000000000,29.0000000000000000,15.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(88,3,'12\'s box','pc','general',0.0000000000000000,26.0000000000000000,15.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(89,3,'16\'s box','pc','general',0.0000000000000000,17.0000000000000000,10.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(90,3,'Siomai tray','pc','general',0.0000000000000000,371.0000000000000000,40.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(91,3,'Bobba Straw','pc','general',0.0000000000000000,936.0000000000000000,100.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(92,3,'Coke Swakto','pc','general',0.0000000000000000,17.0000000000000000,5.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(93,3,'Royal Swakto','pc','general',0.0000000000000000,7.0000000000000000,5.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(94,3,'Egg Peewee','pc','general',0.0000000000000000,0.0000000000000000,5.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(95,3,'Egg small','pc','general',0.0000000000000000,32.0000000000000000,6.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(96,3,'Milk Syrup','ml','general',0.0000000000000000,70.0000000000000000,500.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(97,3,'Crushed Graham','g','general',0.0000000000000000,1501.0000000000000000,500.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(98,3,'Mangoes','g','general',0.0000000000000000,4015.0000000000000000,500.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(99,3,'Condensed Milk','g','general',0.0000000000000000,309.0000000000000000,50.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(100,3,'16oz Y cup','pc','general',0.0000000000000000,144.0000000000000000,25.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(101,3,'22oz Y cup','pc','general',0.0000000000000000,79.0000000000000000,15.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(102,3,'220cc cup','pc','general',0.0000000000000000,163.0000000000000000,30.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(103,3,'320cc cup','pc','general',0.0000000000000000,159.0000000000000000,25.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(104,3,'390cc cup','pc','general',0.0000000000000000,128.0000000000000000,25.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(105,3,'Mineral water 500','pc','general',0.0000000000000000,22.0000000000000000,5.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(106,3,'Mineral Water','pc','general',0.0000000000000000,3.0000000000000000,3.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(107,3,'Dome lids','pc','general',0.0000000000000000,1473.0000000000000000,100.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(108,3,'Hotdog','pc','general',0.0000000000000000,98.0000000000000000,5.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(109,3,'Meatloaf','pc','general',0.0000000000000000,46.0000000000000000,6.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(110,3,'Siomai Beef','pc','general',0.0000000000000000,54.0000000000000000,20.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(111,3,'Siomai Chicken','pc','general',0.0000000000000000,70.0000000000000000,20.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(112,3,'Siomai Pork','pc','general',0.0000000000000000,42.0000000000000000,20.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(113,3,'Skinless','pc','general',0.0000000000000000,48.0000000000000000,6.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(114,3,'Sisig','pc','general',0.0000000000000000,42.0000000000000000,10.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(115,3,'Shawarma','pc','general',0.0000000000000000,48.0000000000000000,10.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(116,3,'Frenchfries','g','general',0.0000000000000000,2240.0000000000000000,500.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(117,3,'Cheese Powder','g','general',0.0000000000000000,500.0000000000000000,50.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(118,3,'Barbeque Powder','g','general',0.0000000000000000,500.0000000000000000,50.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(119,3,'Sour Cream Powder','g','general',0.0000000000000000,500.0000000000000000,50.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(120,3,'Tuna Flakes','g','general',0.0000000000000000,500.0000000000000000,40.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(121,3,'Aonori','g','general',0.0000000000000000,500.0000000000000000,10.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(122,3,'Mini Fork','pack','general',0.0000000000000000,30.0000000000000000,5.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(123,3,'Takoyaki Powder','pc','general',0.0000000000000000,100.0000000000000000,20.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(124,3,'Rice','g','general',0.0000000000000000,980.0000000000000000,1000.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(125,3,'Spoon','pc','general',0.0000000000000000,82.0000000000000000,20.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(126,3,'Fork','pc','general',0.0000000000000000,47.0000000000000000,10.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(127,3,'Takoyaki Sauce','g','general',0.0000000000000000,5000.0000000000000000,1.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(128,3,'Spicy Sauce','bottle','general',0.0000000000000000,10.0000000000000000,2.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(129,3,'Chili Powder','tsp','general',0.0000000000000000,25.0000000000000000,4.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(130,3,'Food Coloring','pc','general',0.0000000000000000,25.0000000000000000,5.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(131,3,'Cooking Oil','g','general',0.0000000000000000,5000.0000000000000000,500.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(132,3,'Pancit Canton tray','pc','general',0.0000000000000000,72.0000000000000000,10.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(133,3,'Mango small','pc','general',0.0000000000000000,50.0000000000000000,20.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(134,3,'Mango big','pc','general',0.0000000000000000,50.0000000000000000,20.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(135,3,'Mangoes Small','pc','general',0.0000000000000000,500.0000000000000000,20.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(136,3,'Mangoes Big','pc','general',0.0000000000000000,500.0000000000000000,20.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(137,3,'Mineral','pc','general',0.0000000000000000,10.0000000000000000,5.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(138,3,'Mineral 1L','pc','general',0.0000000000000000,4.0000000000000000,2.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(139,3,'Japanese','pc','general',0.0000000000000000,29.0000000000000000,10.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(140,3,'Takoyaki solo','pc','general',0.0000000000000000,50.0000000000000000,10.0000000000000000,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(141,1,'Cheese Flavor','g','flavor',0.0000000000000000,1996.0000000000000000,2.0000000000000000,'2026-04-13 09:11:33','2026-04-13 09:11:33'),(142,1,'BBQ','g','flavor',0.0000000000000000,2219.0000000000000000,2.0000000000000000,'2026-04-13 09:14:54','2026-04-13 09:14:54'),(143,4,'tew','pc','general',0.0000000000000000,1.0000000000000000,1.0000000000000000,'2026-04-15 22:18:35','2026-04-15 22:18:35');

--
-- Table structure for table `inventory_movements`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `inventory_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `ingredient_id` bigint unsigned NOT NULL,
  `transaction_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(38,16) NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_movements_tenant_id_ingredient_id_created_at_index` (`tenant_id`,`ingredient_id`,`created_at`),
  KEY `inventory_movements_ingredient_id_foreign` (`ingredient_id`),
  KEY `inventory_movements_transaction_id_foreign` (`transaction_id`),
  KEY `inventory_movements_user_id_foreign` (`user_id`),
  CONSTRAINT `inventory_movements_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_movements_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_movements_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=181 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_movements`
--

INSERT IGNORE INTO `inventory_movements` VALUES (1,1,1,1,2,'OUT',12.0000000000000000,'sale','2026-04-06 19:01:41','2026-04-06 19:01:41'),(2,1,2,1,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:01:41','2026-04-06 19:01:41'),(3,1,3,1,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:01:41','2026-04-06 19:01:41'),(4,1,4,1,2,'OUT',1.0000000000000000,'sale','2026-04-06 19:01:41','2026-04-06 19:01:41'),(5,1,1,1,2,'IN',12.0000000000000000,'void_item','2026-04-06 19:04:46','2026-04-06 19:04:46'),(6,1,2,1,2,'IN',2.0000000000000000,'void_item','2026-04-06 19:04:46','2026-04-06 19:04:46'),(7,1,3,1,2,'IN',2.0000000000000000,'void_item','2026-04-06 19:04:46','2026-04-06 19:04:46'),(8,1,4,1,2,'IN',1.0000000000000000,'void_item','2026-04-06 19:04:46','2026-04-06 19:04:46'),(9,1,1,2,2,'OUT',100.0000000000000000,'sale','2026-04-06 19:07:30','2026-04-06 19:07:30'),(10,1,2,2,2,'OUT',150.0000000000000000,'sale','2026-04-06 19:07:30','2026-04-06 19:07:30'),(11,1,3,2,2,'OUT',20.0000000000000000,'sale','2026-04-06 19:07:30','2026-04-06 19:07:30'),(12,1,4,2,2,'OUT',1.0000000000000000,'sale','2026-04-06 19:07:30','2026-04-06 19:07:30'),(13,1,1,2,2,'IN',100.0000000000000000,'void_item','2026-04-06 19:07:41','2026-04-06 19:07:41'),(14,1,2,2,2,'IN',150.0000000000000000,'void_item','2026-04-06 19:07:41','2026-04-06 19:07:41'),(15,1,3,2,2,'IN',20.0000000000000000,'void_item','2026-04-06 19:07:41','2026-04-06 19:07:41'),(16,1,4,2,2,'IN',1.0000000000000000,'void_item','2026-04-06 19:07:41','2026-04-06 19:07:41'),(29,1,1,2,2,'OUT',12.0000000000000000,'edit_item','2026-04-06 19:11:37','2026-04-06 19:11:37'),(30,1,2,2,2,'OUT',2.0000000000000000,'edit_item','2026-04-06 19:11:37','2026-04-06 19:11:37'),(31,1,3,2,2,'OUT',2.0000000000000000,'edit_item','2026-04-06 19:11:37','2026-04-06 19:11:37'),(32,1,4,2,2,'OUT',1.0000000000000000,'edit_item','2026-04-06 19:11:37','2026-04-06 19:11:37'),(33,1,1,2,2,'IN',12.0000000000000000,'void_item','2026-04-06 19:12:55','2026-04-06 19:12:55'),(34,1,2,2,2,'IN',2.0000000000000000,'void_item','2026-04-06 19:12:55','2026-04-06 19:12:55'),(35,1,3,2,2,'IN',2.0000000000000000,'void_item','2026-04-06 19:12:55','2026-04-06 19:12:55'),(36,1,4,2,2,'IN',1.0000000000000000,'void_item','2026-04-06 19:12:55','2026-04-06 19:12:55'),(37,1,1,1,2,'OUT',100.0000000000000000,'edit_item','2026-04-06 19:17:25','2026-04-06 19:17:25'),(38,1,2,1,2,'OUT',150.0000000000000000,'edit_item','2026-04-06 19:17:25','2026-04-06 19:17:25'),(39,1,3,1,2,'OUT',20.0000000000000000,'edit_item','2026-04-06 19:17:25','2026-04-06 19:17:25'),(40,1,4,1,2,'OUT',1.0000000000000000,'edit_item','2026-04-06 19:17:25','2026-04-06 19:17:25'),(41,1,1,3,2,'OUT',12.0000000000000000,'sale','2026-04-06 19:22:28','2026-04-06 19:22:28'),(42,1,2,3,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:22:28','2026-04-06 19:22:28'),(43,1,3,3,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:22:28','2026-04-06 19:22:28'),(44,1,4,3,2,'OUT',1.0000000000000000,'sale','2026-04-06 19:22:28','2026-04-06 19:22:28'),(45,1,1,3,2,'IN',12.0000000000000000,'void_item','2026-04-06 19:22:40','2026-04-06 19:22:40'),(46,1,2,3,2,'IN',2.0000000000000000,'void_item','2026-04-06 19:22:40','2026-04-06 19:22:40'),(47,1,3,3,2,'IN',2.0000000000000000,'void_item','2026-04-06 19:22:40','2026-04-06 19:22:40'),(48,1,4,3,2,'IN',1.0000000000000000,'void_item','2026-04-06 19:22:40','2026-04-06 19:22:40'),(49,1,1,3,2,'OUT',100.0000000000000000,'edit_item','2026-04-06 19:22:56','2026-04-06 19:22:56'),(50,1,2,3,2,'OUT',150.0000000000000000,'edit_item','2026-04-06 19:22:56','2026-04-06 19:22:56'),(51,1,3,3,2,'OUT',20.0000000000000000,'edit_item','2026-04-06 19:22:56','2026-04-06 19:22:56'),(52,1,4,3,2,'OUT',1.0000000000000000,'edit_item','2026-04-06 19:22:56','2026-04-06 19:22:56'),(53,1,1,4,2,'OUT',12.0000000000000000,'sale','2026-04-06 19:23:23','2026-04-06 19:23:23'),(54,1,2,4,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:23:23','2026-04-06 19:23:23'),(55,1,3,4,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:23:23','2026-04-06 19:23:23'),(56,1,4,4,2,'OUT',1.0000000000000000,'sale','2026-04-06 19:23:23','2026-04-06 19:23:23'),(57,1,5,4,2,'OUT',1.0000000000000000,'edit_item','2026-04-06 19:23:54','2026-04-06 19:23:54'),(58,1,5,4,2,'OUT',1.0000000000000000,'edit_item','2026-04-06 19:23:54','2026-04-06 19:23:54'),(59,1,1,4,2,'IN',12.0000000000000000,'void_item','2026-04-06 19:24:45','2026-04-06 19:24:45'),(60,1,2,4,2,'IN',2.0000000000000000,'void_item','2026-04-06 19:24:45','2026-04-06 19:24:45'),(61,1,3,4,2,'IN',2.0000000000000000,'void_item','2026-04-06 19:24:45','2026-04-06 19:24:45'),(62,1,4,4,2,'IN',1.0000000000000000,'void_item','2026-04-06 19:24:45','2026-04-06 19:24:45'),(63,1,1,5,2,'OUT',112.0000000000000000,'sale','2026-04-06 19:25:18','2026-04-06 19:25:18'),(64,1,2,5,2,'OUT',152.0000000000000000,'sale','2026-04-06 19:25:18','2026-04-06 19:25:18'),(65,1,3,5,2,'OUT',22.0000000000000000,'sale','2026-04-06 19:25:18','2026-04-06 19:25:18'),(66,1,4,5,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:25:18','2026-04-06 19:25:18'),(67,1,5,5,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:25:18','2026-04-06 19:25:18'),(68,1,1,5,2,'IN',100.0000000000000000,'void_item','2026-04-06 19:25:52','2026-04-06 19:25:52'),(69,1,2,5,2,'IN',150.0000000000000000,'void_item','2026-04-06 19:25:52','2026-04-06 19:25:52'),(70,1,3,5,2,'IN',20.0000000000000000,'void_item','2026-04-06 19:25:52','2026-04-06 19:25:52'),(71,1,4,5,2,'IN',1.0000000000000000,'void_item','2026-04-06 19:25:52','2026-04-06 19:25:52'),(72,1,5,5,2,'OUT',1.0000000000000000,'edit_item','2026-04-06 19:25:52','2026-04-06 19:25:52'),(73,1,1,5,2,'IN',112.0000000000000000,'void_transaction','2026-04-06 19:30:06','2026-04-06 19:30:06'),(74,1,2,5,2,'IN',152.0000000000000000,'void_transaction','2026-04-06 19:30:06','2026-04-06 19:30:06'),(75,1,3,5,2,'IN',22.0000000000000000,'void_transaction','2026-04-06 19:30:06','2026-04-06 19:30:06'),(76,1,4,5,2,'IN',2.0000000000000000,'void_transaction','2026-04-06 19:30:06','2026-04-06 19:30:06'),(77,1,5,5,2,'IN',3.0000000000000000,'void_transaction','2026-04-06 19:30:06','2026-04-06 19:30:06'),(78,1,1,5,2,'OUT',112.0000000000000000,'unvoid_transaction','2026-04-06 19:30:09','2026-04-06 19:30:09'),(79,1,2,5,2,'OUT',152.0000000000000000,'unvoid_transaction','2026-04-06 19:30:09','2026-04-06 19:30:09'),(80,1,3,5,2,'OUT',22.0000000000000000,'unvoid_transaction','2026-04-06 19:30:09','2026-04-06 19:30:09'),(81,1,4,5,2,'OUT',2.0000000000000000,'unvoid_transaction','2026-04-06 19:30:09','2026-04-06 19:30:09'),(82,1,5,5,2,'OUT',3.0000000000000000,'unvoid_transaction','2026-04-06 19:30:09','2026-04-06 19:30:09'),(83,1,1,6,2,'OUT',112.0000000000000000,'sale','2026-04-06 19:30:31','2026-04-06 19:30:31'),(84,1,2,6,2,'OUT',152.0000000000000000,'sale','2026-04-06 19:30:31','2026-04-06 19:30:31'),(85,1,3,6,2,'OUT',22.0000000000000000,'sale','2026-04-06 19:30:31','2026-04-06 19:30:31'),(86,1,4,6,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:30:31','2026-04-06 19:30:31'),(87,1,5,6,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:30:31','2026-04-06 19:30:31'),(92,1,1,6,2,'IN',100.0000000000000000,'void_item','2026-04-06 19:37:19','2026-04-06 19:37:19'),(93,1,2,6,2,'IN',150.0000000000000000,'void_item','2026-04-06 19:37:19','2026-04-06 19:37:19'),(94,1,3,6,2,'IN',20.0000000000000000,'void_item','2026-04-06 19:37:19','2026-04-06 19:37:19'),(95,1,4,6,2,'IN',1.0000000000000000,'void_item','2026-04-06 19:37:19','2026-04-06 19:37:19'),(96,1,5,6,2,'OUT',1.0000000000000000,'edit_item','2026-04-06 19:37:19','2026-04-06 19:37:19'),(97,1,1,7,2,'OUT',112.0000000000000000,'sale','2026-04-06 19:39:18','2026-04-06 19:39:18'),(98,1,2,7,2,'OUT',152.0000000000000000,'sale','2026-04-06 19:39:18','2026-04-06 19:39:18'),(99,1,3,7,2,'OUT',22.0000000000000000,'sale','2026-04-06 19:39:18','2026-04-06 19:39:18'),(100,1,4,7,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:39:18','2026-04-06 19:39:18'),(101,1,5,7,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:39:18','2026-04-06 19:39:18'),(102,1,1,7,2,'IN',100.0000000000000000,'void_item','2026-04-06 19:39:43','2026-04-06 19:39:43'),(103,1,2,7,2,'IN',150.0000000000000000,'void_item','2026-04-06 19:39:43','2026-04-06 19:39:43'),(104,1,3,7,2,'IN',20.0000000000000000,'void_item','2026-04-06 19:39:43','2026-04-06 19:39:43'),(105,1,4,7,2,'IN',1.0000000000000000,'void_item','2026-04-06 19:39:43','2026-04-06 19:39:43'),(106,1,5,7,2,'OUT',1.0000000000000000,'edit_item','2026-04-06 19:39:43','2026-04-06 19:39:43'),(107,1,1,8,2,'OUT',112.0000000000000000,'sale','2026-04-06 19:44:31','2026-04-06 19:44:31'),(108,1,2,8,2,'OUT',152.0000000000000000,'sale','2026-04-06 19:44:31','2026-04-06 19:44:31'),(109,1,3,8,2,'OUT',22.0000000000000000,'sale','2026-04-06 19:44:31','2026-04-06 19:44:31'),(110,1,4,8,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:44:31','2026-04-06 19:44:31'),(111,1,5,8,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:44:31','2026-04-06 19:44:31'),(112,1,1,8,2,'IN',100.0000000000000000,'void_item','2026-04-06 19:44:54','2026-04-06 19:44:54'),(113,1,2,8,2,'IN',150.0000000000000000,'void_item','2026-04-06 19:44:54','2026-04-06 19:44:54'),(114,1,3,8,2,'IN',20.0000000000000000,'void_item','2026-04-06 19:44:54','2026-04-06 19:44:54'),(115,1,4,8,2,'IN',1.0000000000000000,'void_item','2026-04-06 19:44:54','2026-04-06 19:44:54'),(116,1,5,8,2,'OUT',1.0000000000000000,'edit_item','2026-04-06 19:44:54','2026-04-06 19:44:54'),(117,1,1,9,2,'OUT',112.0000000000000000,'sale','2026-04-06 19:53:20','2026-04-06 19:53:20'),(118,1,2,9,2,'OUT',152.0000000000000000,'sale','2026-04-06 19:53:20','2026-04-06 19:53:20'),(119,1,3,9,2,'OUT',22.0000000000000000,'sale','2026-04-06 19:53:20','2026-04-06 19:53:20'),(120,1,4,9,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:53:20','2026-04-06 19:53:20'),(121,1,5,9,2,'OUT',2.0000000000000000,'sale','2026-04-06 19:53:20','2026-04-06 19:53:20'),(122,1,1,9,2,'IN',100.0000000000000000,'void_item','2026-04-06 20:01:16','2026-04-06 20:01:16'),(123,1,2,9,2,'IN',150.0000000000000000,'void_item','2026-04-06 20:01:16','2026-04-06 20:01:16'),(124,1,3,9,2,'IN',20.0000000000000000,'void_item','2026-04-06 20:01:16','2026-04-06 20:01:16'),(125,1,4,9,2,'IN',1.0000000000000000,'void_item','2026-04-06 20:01:16','2026-04-06 20:01:16'),(126,1,5,9,2,'OUT',1.0000000000000000,'edit_item','2026-04-06 20:01:16','2026-04-06 20:01:16'),(127,1,5,9,2,'IN',1.0000000000000000,'void_item','2026-04-06 20:02:02','2026-04-06 20:02:02'),(128,1,1,10,2,'IN',100.0000000000000000,'void_item','2026-04-06 20:05:50','2026-04-06 20:05:50'),(129,1,2,10,2,'IN',150.0000000000000000,'void_item','2026-04-06 20:05:50','2026-04-06 20:05:50'),(130,1,3,10,2,'IN',20.0000000000000000,'void_item','2026-04-06 20:05:50','2026-04-06 20:05:50'),(131,1,4,10,2,'IN',1.0000000000000000,'void_item','2026-04-06 20:05:50','2026-04-06 20:05:50'),(132,1,1,10,2,'OUT',112.0000000000000000,'sale','2026-04-06 20:10:01','2026-04-06 20:10:01'),(133,1,2,10,2,'OUT',152.0000000000000000,'sale','2026-04-06 20:10:01','2026-04-06 20:10:01'),(134,1,3,10,2,'OUT',22.0000000000000000,'sale','2026-04-06 20:10:01','2026-04-06 20:10:01'),(135,1,4,10,2,'OUT',2.0000000000000000,'sale','2026-04-06 20:10:01','2026-04-06 20:10:01'),(136,1,5,10,2,'OUT',2.0000000000000000,'sale','2026-04-06 20:10:01','2026-04-06 20:10:01'),(137,1,1,10,2,'IN',112.0000000000000000,'void_transaction','2026-04-06 20:11:49','2026-04-06 20:11:49'),(138,1,2,10,2,'IN',152.0000000000000000,'void_transaction','2026-04-06 20:11:49','2026-04-06 20:11:49'),(139,1,3,10,2,'IN',22.0000000000000000,'void_transaction','2026-04-06 20:11:49','2026-04-06 20:11:49'),(140,1,4,10,2,'IN',2.0000000000000000,'void_transaction','2026-04-06 20:11:49','2026-04-06 20:11:49'),(141,1,5,10,2,'IN',2.0000000000000000,'void_transaction','2026-04-06 20:11:49','2026-04-06 20:11:49'),(142,1,1,9,2,'IN',112.0000000000000000,'void_transaction','2026-04-06 20:11:57','2026-04-06 20:11:57'),(143,1,2,9,2,'IN',152.0000000000000000,'void_transaction','2026-04-06 20:11:57','2026-04-06 20:11:57'),(144,1,3,9,2,'IN',22.0000000000000000,'void_transaction','2026-04-06 20:11:57','2026-04-06 20:11:57'),(145,1,4,9,2,'IN',2.0000000000000000,'void_transaction','2026-04-06 20:11:57','2026-04-06 20:11:57'),(146,1,5,9,2,'IN',3.0000000000000000,'void_transaction','2026-04-06 20:11:57','2026-04-06 20:11:57'),(147,1,5,11,2,'OUT',2.0000000000000000,'sale','2026-04-07 15:36:07','2026-04-07 15:36:07'),(148,1,5,12,2,'OUT',2.0000000000000000,'sale','2026-04-07 15:37:10','2026-04-07 15:37:10'),(149,1,1,13,2,'OUT',112.0000000000000000,'sale','2026-04-12 17:07:47','2026-04-12 17:07:47'),(150,1,2,13,2,'OUT',152.0000000000000000,'sale','2026-04-12 17:07:47','2026-04-12 17:07:47'),(151,1,3,13,2,'OUT',22.0000000000000000,'sale','2026-04-12 17:07:47','2026-04-12 17:07:47'),(152,1,4,13,2,'OUT',2.0000000000000000,'sale','2026-04-12 17:07:47','2026-04-12 17:07:47'),(153,1,5,14,2,'OUT',0.6244444555500000,'sale','2026-04-13 09:19:27','2026-04-13 09:19:27'),(154,1,142,14,2,'OUT',1.0000000000000000,'sale','2026-04-13 09:19:27','2026-04-13 09:19:27'),(155,1,5,15,2,'OUT',0.6244444555500000,'sale','2026-04-13 09:20:09','2026-04-13 09:20:09'),(156,1,141,15,2,'OUT',1.0000000000000000,'sale','2026-04-13 09:20:09','2026-04-13 09:20:09'),(157,1,1,16,2,'OUT',150.0000000000000000,'sale','2026-04-13 09:22:43','2026-04-13 09:22:43'),(158,1,2,16,2,'OUT',24.0000000000000000,'sale','2026-04-13 09:22:43','2026-04-13 09:22:43'),(159,1,3,16,2,'OUT',2.0000000000000000,'sale','2026-04-13 09:22:43','2026-04-13 09:22:43'),(160,1,4,16,2,'OUT',2.0000000000000000,'sale','2026-04-13 09:22:43','2026-04-13 09:22:43'),(161,1,141,16,2,'OUT',2.0000000000000000,'sale','2026-04-13 09:22:43','2026-04-13 09:22:43'),(162,1,1,17,2,'OUT',75.0000000000000000,'sale','2026-04-13 09:23:02','2026-04-13 09:23:02'),(163,1,2,17,2,'OUT',12.0000000000000000,'sale','2026-04-13 09:23:02','2026-04-13 09:23:02'),(164,1,3,17,2,'OUT',1.0000000000000000,'sale','2026-04-13 09:23:02','2026-04-13 09:23:02'),(165,1,4,17,2,'OUT',1.0000000000000000,'sale','2026-04-13 09:23:02','2026-04-13 09:23:02'),(166,1,141,17,2,'OUT',1.0000000000000000,'sale','2026-04-13 09:23:02','2026-04-13 09:23:02'),(167,1,5,17,2,'OUT',0.6244444555500000,'sale','2026-04-13 09:23:02','2026-04-13 09:23:02'),(168,1,142,17,2,'OUT',1.0000000000000000,'sale','2026-04-13 09:23:02','2026-04-13 09:23:02'),(169,1,1,18,2,'OUT',175.0000000000000000,'sale','2026-04-13 18:29:25','2026-04-13 18:29:25'),(170,1,2,18,2,'OUT',162.0000000000000000,'sale','2026-04-13 18:29:25','2026-04-13 18:29:25'),(171,1,3,18,2,'OUT',21.0000000000000000,'sale','2026-04-13 18:29:25','2026-04-13 18:29:25'),(172,1,4,18,2,'OUT',2.0000000000000000,'sale','2026-04-13 18:29:25','2026-04-13 18:29:25'),(173,1,142,18,2,'OUT',1.0000000000000000,'sale','2026-04-13 18:29:25','2026-04-13 18:29:25'),(174,2,38,21,4,'OUT',1.0000000000000000,'sale','2026-04-16 05:18:28','2026-04-16 05:18:28'),(175,2,50,21,4,'OUT',280.0000000000000000,'sale','2026-04-16 05:18:28','2026-04-16 05:18:28'),(176,2,37,21,4,'OUT',1.0000000000000000,'sale','2026-04-16 05:18:28','2026-04-16 05:18:28'),(177,2,37,22,4,'OUT',1.0000000000000000,'sale','2026-04-16 07:11:11','2026-04-16 07:11:11'),(178,2,50,22,4,'OUT',120.0000000000000000,'sale','2026-04-16 07:11:11','2026-04-16 07:11:11'),(179,2,36,23,4,'OUT',1.0000000000000000,'sale','2026-04-16 08:13:23','2026-04-16 08:13:23'),(180,2,50,23,4,'OUT',80.0000000000000000,'sale','2026-04-16 08:13:23','2026-04-16 08:13:23');

--
-- Table structure for table `job_batches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--


--
-- Table structure for table `jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--


--
-- Table structure for table `laundry_attendance`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_attendance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `staff_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attendance_date` date NOT NULL,
  `days_worked` decimal(6,2) NOT NULL DEFAULT '1.00',
  `loads_folded` int NOT NULL DEFAULT '0',
  `day_rate` decimal(16,4) NOT NULL DEFAULT '350.0000',
  `folding_fee_per_load` decimal(16,4) NOT NULL DEFAULT '10.0000',
  `deductions` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_attendance_tenant_idx` (`tenant_id`,`attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_attendance`
--


--
-- Table structure for table `laundry_branch_configs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_branch_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `machine_assignment_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `fold_service_amount` decimal(16,4) NOT NULL DEFAULT '10.0000',
  `fold_commission_target` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `laundry_branch_configs_tenant_unique` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_branch_configs`
--

INSERT IGNORE INTO `laundry_branch_configs` VALUES (1,1,0,0.0000,'branch','2026-04-20 10:06:40','2026-04-20 16:46:18');

--
-- Table structure for table `laundry_customer_points`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_customer_points` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `points_balance` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `lifetime_earned` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `lifetime_redeemed` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `laundry_customer_points_unique` (`tenant_id`,`customer_id`),
  KEY `laundry_customer_points_tenant_idx` (`tenant_id`),
  KEY `laundry_customer_points_customer_fk` (`customer_id`),
  CONSTRAINT `laundry_customer_points_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `laundry_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_customer_points`
--

INSERT IGNORE INTO `laundry_customer_points` VALUES (1,1,3,2.0000,2.0000,0.0000,'2026-04-19 20:48:59','2026-04-20 15:25:47'),(3,1,4,1.0000,1.0000,0.0000,'2026-04-20 15:36:10','2026-04-20 15:36:10');

--
-- Table structure for table `laundry_customers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_customers_tenant_idx` (`tenant_id`),
  KEY `laundry_customers_birthday_idx` (`tenant_id`,`birthday`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_customers`
--

INSERT IGNORE INTO `laundry_customers` VALUES (1,1,'Juan Dela Cruz','09171234567','juan.delacruz@example.com','1992-03-14','2026-04-19 18:40:54','2026-04-19 18:40:54'),(2,1,'Maria Santos','09182345678','maria.santos@example.com','1995-07-22','2026-04-19 18:40:54','2026-04-19 18:40:54'),(3,1,'Carlo Reyes','09193456789','carlo.reyes@example.com','1990-11-05','2026-04-19 18:40:54','2026-04-19 18:40:54'),(4,1,'Angelica Lim','09214567890','angelica.lim@example.com','1998-01-30','2026-04-19 18:40:54','2026-04-19 18:40:54'),(5,1,'Paolo Navarro','09225678901','paolo.navarro@example.com','1993-09-17','2026-04-19 18:40:54','2026-04-19 18:40:54');

--
-- Table structure for table `laundry_inventory_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_inventory_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pcs',
  `stock_quantity` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `low_stock_threshold` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `unit_cost` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `laundry_inventory_unique` (`tenant_id`,`name`),
  KEY `laundry_inventory_tenant_idx` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_inventory_items`
--

INSERT IGNORE INTO `laundry_inventory_items` VALUES (41,4,'Premium Powder Detergent','detergent','kg',25.0000,6.0000,185.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(42,3,'Premium Powder Detergent','detergent','kg',25.0000,6.0000,185.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(43,2,'Premium Powder Detergent','detergent','kg',25.0000,6.0000,185.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(44,1,'Tide','detergent','pc',25.0000,6.0000,185.0000,NULL,'2026-04-19 18:12:45','2026-04-20 07:49:33'),(45,4,'Liquid Detergent (Commercial)','detergent','liter',40.0000,10.0000,95.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(46,3,'Liquid Detergent (Commercial)','detergent','liter',40.0000,10.0000,95.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(47,2,'Liquid Detergent (Commercial)','detergent','liter',40.0000,10.0000,95.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(48,1,'Ariel','detergent','pc',30.0000,10.0000,95.0000,'uploads/laundry-inventory/tenant-1-item-15707a364d134407.webp','2026-04-19 18:12:45','2026-04-20 16:12:45'),(49,4,'Fabric Conditioner','fabcon','liter',35.0000,8.0000,120.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(50,3,'Fabric Conditioner','fabcon','liter',35.0000,8.0000,120.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(51,2,'Fabric Conditioner','fabcon','liter',35.0000,8.0000,120.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(52,1,'Fabric Conditioner','fabcon','liter',25.0000,8.0000,120.0000,NULL,'2026-04-19 18:12:45','2026-04-20 15:36:10'),(53,4,'Color-Safe Bleach','bleach','liter',18.0000,5.0000,140.0000,NULL,'2026-04-19 18:12:45','2026-04-19 19:14:40'),(54,3,'Color-Safe Bleach','bleach','liter',18.0000,5.0000,140.0000,NULL,'2026-04-19 18:12:45','2026-04-19 19:14:40'),(55,2,'Color-Safe Bleach','bleach','liter',18.0000,5.0000,140.0000,NULL,'2026-04-19 18:12:45','2026-04-19 19:14:40'),(56,1,'Color-Safe Bleach','bleach','liter',13.0000,5.0000,140.0000,NULL,'2026-04-19 18:12:45','2026-04-20 15:36:10'),(57,4,'Laundry Disinfectant','other','liter',15.0000,4.0000,165.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(58,3,'Laundry Disinfectant','other','liter',15.0000,4.0000,165.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(59,2,'Laundry Disinfectant','other','liter',15.0000,4.0000,165.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(61,4,'Stain Remover','other','liter',12.0000,3.0000,210.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(62,3,'Stain Remover','other','liter',12.0000,3.0000,210.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(63,2,'Stain Remover','other','liter',12.0000,3.0000,210.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(65,4,'Laundry Bags (Medium)','other','pcs',300.0000,80.0000,4.5000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(66,3,'Laundry Bags (Medium)','other','pcs',300.0000,80.0000,4.5000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(67,2,'Laundry Bags (Medium)','other','pcs',300.0000,80.0000,4.5000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(69,4,'Laundry Bags (Large)','other','pcs',220.0000,60.0000,6.5000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(70,3,'Laundry Bags (Large)','other','pcs',220.0000,60.0000,6.5000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(71,2,'Laundry Bags (Large)','other','pcs',220.0000,60.0000,6.5000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(73,4,'Receipt Paper 57mm','other','roll',90.0000,20.0000,18.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(74,3,'Receipt Paper 57mm','other','roll',90.0000,20.0000,18.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(75,2,'Receipt Paper 57mm','other','roll',90.0000,20.0000,18.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(77,4,'Mesh Laundry Net','other','pcs',80.0000,20.0000,28.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(78,3,'Mesh Laundry Net','other','pcs',80.0000,20.0000,28.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(79,2,'Mesh Laundry Net','other','pcs',80.0000,20.0000,28.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(81,4,'Disposable Gloves','other','box',30.0000,8.0000,165.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(82,3,'Disposable Gloves','other','box',30.0000,8.0000,165.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(83,2,'Disposable Gloves','other','box',30.0000,8.0000,165.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(85,4,'Machine Cleaner','machine_cleaner','liter',10.0000,3.0000,230.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(86,3,'Machine Cleaner','machine_cleaner','liter',10.0000,3.0000,230.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(87,2,'Machine Cleaner','machine_cleaner','liter',10.0000,3.0000,230.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(88,1,'Machine Cleaner','machine_cleaner','liter',10.0000,3.0000,230.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(89,4,'Rust and Scale Remover','machine_cleaner','liter',8.0000,2.0000,275.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(90,3,'Rust and Scale Remover','machine_cleaner','liter',8.0000,2.0000,275.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(91,2,'Rust and Scale Remover','machine_cleaner','liter',8.0000,2.0000,275.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(92,1,'Rust and Scale Remover','machine_cleaner','liter',8.0000,2.0000,275.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:50:52'),(93,4,'Downy-Type Fragrance Booster','other','liter',14.0000,4.0000,190.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(94,3,'Downy-Type Fragrance Booster','other','liter',14.0000,4.0000,190.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(95,2,'Downy-Type Fragrance Booster','other','liter',14.0000,4.0000,190.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(97,4,'Spot Cleaning Brush','other','pcs',45.0000,10.0000,35.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(98,3,'Spot Cleaning Brush','other','pcs',45.0000,10.0000,35.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45'),(99,2,'Spot Cleaning Brush','other','pcs',45.0000,10.0000,35.0000,NULL,'2026-04-19 18:12:45','2026-04-19 18:12:45');

--
-- Table structure for table `laundry_inventory_purchases`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_inventory_purchases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `quantity` decimal(16,4) NOT NULL,
  `unit_cost` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchased_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_inventory_purchases_tenant_idx` (`tenant_id`,`purchased_at`),
  KEY `laundry_inventory_purchases_item_fk` (`item_id`),
  CONSTRAINT `laundry_inventory_purchases_item_fk` FOREIGN KEY (`item_id`) REFERENCES `laundry_inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_inventory_purchases`
--


--
-- Table structure for table `laundry_load_cards`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_load_cards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `machine_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `laundry_load_cards_unique` (`tenant_id`,`machine_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_load_cards`
--


--
-- Table structure for table `laundry_load_definitions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_load_definitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detergent_item_id` bigint unsigned DEFAULT NULL,
  `fabcon_item_id` bigint unsigned DEFAULT NULL,
  `bleach_item_id` bigint unsigned DEFAULT NULL,
  `detergent_included_qty` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `fabcon_included_qty` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `bleach_included_qty` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_load_definitions_tenant_idx` (`tenant_id`,`sort_order`,`name`),
  KEY `laundry_load_definitions_detergent_item_fk` (`detergent_item_id`),
  KEY `laundry_load_definitions_fabcon_item_fk` (`fabcon_item_id`),
  KEY `laundry_load_definitions_bleach_item_fk` (`bleach_item_id`),
  CONSTRAINT `laundry_load_definitions_bleach_item_fk` FOREIGN KEY (`bleach_item_id`) REFERENCES `laundry_inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `laundry_load_definitions_detergent_item_fk` FOREIGN KEY (`detergent_item_id`) REFERENCES `laundry_inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `laundry_load_definitions_fabcon_item_fk` FOREIGN KEY (`fabcon_item_id`) REFERENCES `laundry_inventory_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_load_definitions`
--

INSERT IGNORE INTO `laundry_load_definitions` VALUES (1,1,'Full Service - Surf / Downy / Zonrox',44,52,56,1.0000,1.0000,1.0000,0,'2026-04-19 19:25:56','2026-04-19 19:25:56'),(2,2,'Full Service - Surf / Downy / Zonrox',43,51,55,1.0000,1.0000,1.0000,0,'2026-04-19 19:25:56','2026-04-19 19:25:56'),(3,3,'Full Service - Surf / Downy / Zonrox',42,50,54,1.0000,1.0000,1.0000,0,'2026-04-19 19:25:56','2026-04-19 19:25:56'),(4,4,'Full Service - Surf / Downy / Zonrox',41,49,53,1.0000,1.0000,1.0000,0,'2026-04-19 19:25:56','2026-04-19 19:25:56');

--
-- Table structure for table `laundry_machines`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_machines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `machine_kind` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'washer',
  `machine_code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `machine_label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `current_order_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `laundry_machines_tenant_code_unique` (`tenant_id`,`machine_code`),
  KEY `laundry_machines_tenant_status_idx` (`tenant_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_machines`
--

INSERT IGNORE INTO `laundry_machines` VALUES (1,1,'washer','W-01','Washer #1','available',NULL,'2026-04-19 19:01:14','2026-04-20 07:56:23'),(2,1,'washer','W-02','Washer #2','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(3,1,'washer','W-03','Washer #3','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(4,1,'dryer','D-01','Dryer #1','available',NULL,'2026-04-19 19:01:14','2026-04-20 07:56:23'),(5,1,'dryer','D-02','Dryer #2','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(6,1,'dryer','D-03','Dryer #3','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(7,2,'washer','W-01','Washer #1','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(8,2,'washer','W-02','Washer #2','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(9,2,'washer','W-03','Washer #3','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(10,2,'dryer','D-01','Dryer #1','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(11,2,'dryer','D-02','Dryer #2','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(12,2,'dryer','D-03','Dryer #3','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(13,3,'washer','W-01','Washer #1','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(14,3,'washer','W-02','Washer #2','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(15,3,'washer','W-03','Washer #3','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(16,3,'dryer','D-01','Dryer #1','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(17,3,'dryer','D-02','Dryer #2','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(18,3,'dryer','D-03','Dryer #3','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(19,4,'washer','W-01','Washer #1','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(20,4,'washer','W-02','Washer #2','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(21,4,'washer','W-03','Washer #3','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(22,4,'dryer','D-01','Dryer #1','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(23,4,'dryer','D-02','Dryer #2','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14'),(24,4,'dryer','D-03','Dryer #3','available',NULL,'2026-04-19 19:01:14','2026-04-19 19:01:14');

--
-- Table structure for table `laundry_order_add_ons`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_order_add_ons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `item_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(16,4) NOT NULL DEFAULT '1.0000',
  `unit_price` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `total_price` decimal(16,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`id`),
  KEY `laundry_order_addons_tenant_idx` (`tenant_id`),
  KEY `laundry_order_addons_order_fk` (`order_id`),
  CONSTRAINT `laundry_order_addons_order_fk` FOREIGN KEY (`order_id`) REFERENCES `laundry_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_order_add_ons`
--

INSERT IGNORE INTO `laundry_order_add_ons` VALUES (1,1,1,'Liquid Detergent (Commercial)',1.0000,95.0000,95.0000),(2,1,1,'Fabric Conditioner',1.0000,120.0000,120.0000),(3,1,1,'Color-Safe Bleach',1.0000,140.0000,140.0000),(4,1,2,'Liquid Detergent (Commercial)',1.0000,95.0000,95.0000),(5,1,2,'Fabric Conditioner',1.0000,120.0000,120.0000),(6,1,4,'Ariel',1.0000,95.0000,95.0000),(7,1,5,'Fabric Conditioner',1.0000,120.0000,120.0000),(8,1,7,'Ariel',1.0000,95.0000,95.0000),(9,1,7,'Fabric Conditioner',1.0000,120.0000,120.0000),(10,1,7,'Color-Safe Bleach',1.0000,140.0000,140.0000);

--
-- Table structure for table `laundry_order_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_order_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_kind` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supply_block` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none' COMMENT 'none|full_service|wash_supplies',
  `show_addon_supplies` tinyint(1) NOT NULL DEFAULT '1',
  `price_per_load` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `laundry_order_types_tenant_code` (`tenant_id`,`code`),
  KEY `laundry_order_types_tenant_sort` (`tenant_id`,`sort_order`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_order_types`
--

INSERT IGNORE INTO `laundry_order_types` VALUES (1,1,'drop_off','Drop-off (Full Service)','full_service','full_service',1,80.0000,1,1,'2026-04-19 19:47:05','2026-04-19 19:56:59'),(2,1,'wash_only','Wash only','wash_only','wash_supplies',1,60.0000,2,1,'2026-04-19 19:47:05','2026-04-19 19:56:59'),(3,1,'dry_only','Dry only','dry_only','none',0,40.0000,3,1,'2026-04-19 19:47:05','2026-04-19 19:56:59'),(4,2,'drop_off','Drop-off (Full Service)','full_service','full_service',1,80.0000,1,1,'2026-04-19 19:47:05','2026-04-19 19:56:59'),(5,2,'wash_only','Wash only','wash_only','wash_supplies',1,60.0000,2,1,'2026-04-19 19:47:05','2026-04-19 19:56:59'),(6,2,'dry_only','Dry only','dry_only','none',0,40.0000,3,1,'2026-04-19 19:47:05','2026-04-19 19:56:59'),(7,3,'drop_off','Drop-off (Full Service)','full_service','full_service',1,80.0000,1,1,'2026-04-19 19:47:05','2026-04-19 19:56:59'),(8,3,'wash_only','Wash only','wash_only','wash_supplies',1,60.0000,2,1,'2026-04-19 19:47:05','2026-04-19 19:56:59'),(9,3,'dry_only','Dry only','dry_only','none',0,40.0000,3,1,'2026-04-19 19:47:05','2026-04-19 19:56:59'),(10,4,'drop_off','Drop-off (Full Service)','full_service','full_service',1,80.0000,1,1,'2026-04-19 19:47:05','2026-04-19 19:56:59'),(11,4,'wash_only','Wash only','wash_only','wash_supplies',1,60.0000,2,1,'2026-04-19 19:47:05','2026-04-19 19:56:59'),(12,4,'dry_only','Dry only','dry_only','none',0,40.0000,3,1,'2026-04-19 19:47:05','2026-04-19 19:56:59'),(13,1,'rinse_only','Rinse only','rinse_only','rinse_supplies',0,60.0000,4,1,'2026-04-19 19:56:59','2026-04-21 07:32:35'),(14,2,'rinse_only','Rinse only','rinse_only','rinse_supplies',0,60.0000,4,1,'2026-04-19 19:56:59','2026-04-21 07:32:35'),(15,3,'rinse_only','Rinse only','rinse_only','rinse_supplies',0,60.0000,4,1,'2026-04-19 19:56:59','2026-04-21 07:32:35'),(16,4,'rinse_only','Rinse only','rinse_only','rinse_supplies',0,60.0000,4,1,'2026-04-19 19:56:59','2026-04-21 07:32:35'),(20,5,'drop_off','Drop-off (Full Service)','full_service','full_service',1,80.0000,1,1,'2026-04-21 08:24:44','2026-04-21 08:24:44'),(21,5,'wash_only','Wash only','wash_only','wash_supplies',1,60.0000,2,1,'2026-04-21 08:24:44','2026-04-21 08:24:44'),(22,5,'dry_only','Dry only','dry_only','none',0,40.0000,3,1,'2026-04-21 08:24:44','2026-04-21 08:24:44'),(23,5,'rinse_only','Rinse only','rinse_only','rinse_supplies',0,60.0000,4,1,'2026-04-21 08:24:44','2026-04-21 08:24:44'),(24,6,'drop_off','Drop-off (Full Service)','full_service','full_service',1,80.0000,1,1,'2026-04-21 09:16:41','2026-04-21 09:16:41'),(25,6,'wash_only','Wash only','wash_only','wash_supplies',1,60.0000,2,1,'2026-04-21 09:16:41','2026-04-21 09:16:41'),(26,6,'dry_only','Dry only','dry_only','none',0,40.0000,3,1,'2026-04-21 09:16:41','2026-04-21 09:16:41'),(27,6,'rinse_only','Rinse only','rinse_only','rinse_supplies',0,60.0000,4,1,'2026-04-21 09:16:41','2026-04-21 09:16:41');

--
-- Table structure for table `laundry_orders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_code` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `machine_id` bigint unsigned DEFAULT NULL,
  `washer_machine_id` bigint unsigned DEFAULT NULL,
  `dryer_machine_id` bigint unsigned DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `include_fold_service` tinyint(1) NOT NULL DEFAULT '0',
  `inclusion_detergent_item_id` bigint unsigned DEFAULT NULL,
  `inclusion_fabcon_item_id` bigint unsigned DEFAULT NULL,
  `inclusion_bleach_item_id` bigint unsigned DEFAULT NULL,
  `load_definition_id` bigint unsigned DEFAULT NULL,
  `load_label_snapshot` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `machine_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wash_qty` int NOT NULL DEFAULT '0',
  `dry_minutes` int NOT NULL DEFAULT '0',
  `subtotal` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `add_on_total` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `total_amount` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `payment_method` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `payment_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paid',
  `amount_tendered` decimal(16,4) DEFAULT NULL,
  `change_amount` decimal(16,4) DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `laundry_orders_tenant_reference_unique` (`tenant_id`,`reference_code`),
  KEY `laundry_orders_tenant_idx` (`tenant_id`,`created_at`),
  KEY `laundry_orders_customer_idx` (`customer_id`),
  CONSTRAINT `laundry_orders_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `laundry_customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_orders`
--

INSERT IGNORE INTO `laundry_orders` VALUES (1,NULL,1,2,1,1,4,NULL,0,NULL,NULL,NULL,NULL,NULL,'drop_off','W-01',1,1,80.0000,355.0000,435.0000,'cash','paid',500.0000,65.0000,'completed','2026-04-19 19:15:22','2026-04-19 20:33:30'),(2,NULL,1,2,1,1,4,3,1,48,52,56,NULL,NULL,'drop_off','W-01',1,1,80.0000,215.0000,295.0000,'gcash','paid',295.0000,0.0000,'completed','2026-04-19 20:48:59','2026-04-19 20:49:25'),(3,NULL,1,2,1,1,4,NULL,0,48,52,56,NULL,NULL,'drop_off','W-01',1,1,80.0000,0.0000,80.0000,'qr_payment','paid',80.0000,0.0000,'paid','2026-04-20 07:55:58','2026-04-20 10:34:49'),(4,NULL,1,3,NULL,NULL,NULL,NULL,1,48,52,56,NULL,NULL,'drop_off','manual',1,1,80.0000,95.0000,175.0000,'gcash','paid',175.0000,0.0000,'paid','2026-04-20 14:28:46','2026-04-20 14:29:46'),(5,NULL,1,2,NULL,NULL,NULL,3,1,48,52,NULL,NULL,NULL,'drop_off','manual',1,1,80.0000,120.0000,200.0000,'paymaya','paid',200.0000,0.0000,'paid','2026-04-20 15:25:47','2026-04-20 15:42:35'),(6,NULL,1,2,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,'dry_only','manual',0,1,40.0000,0.0000,40.0000,'paymaya','paid',40.0000,0.0000,'paid','2026-04-20 15:32:55','2026-04-20 15:44:47'),(7,'#BC@AQ3',1,2,NULL,NULL,NULL,4,1,48,52,NULL,NULL,NULL,'drop_off','manual',1,1,80.0000,355.0000,435.0000,'gcash','paid',435.0000,0.0000,'paid','2026-04-20 15:36:10','2026-04-20 15:42:50'),(8,'tx84k3g',1,2,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,'dry_only','manual',0,1,40.0000,0.0000,40.0000,'paymaya','paid',40.0000,0.0000,'paid','2026-04-20 15:40:15','2026-04-20 15:43:05');

--
-- Table structure for table `laundry_reward_configs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_reward_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `points_per_amount_spent` decimal(16,4) NOT NULL DEFAULT '0.0100',
  `points_per_dropoff_load` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `reward_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Gift Reward',
  `reward_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reward_points_cost` int NOT NULL DEFAULT '100',
  `minimum_points_to_redeem` int NOT NULL DEFAULT '100',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `laundry_reward_configs_tenant_unique` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_reward_configs`
--


--
-- Table structure for table `laundry_reward_redemptions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_reward_redemptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `reward_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `points_used` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `redeemed_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_reward_redemptions_tenant_idx` (`tenant_id`,`created_at`),
  KEY `laundry_reward_redemptions_customer_fk` (`customer_id`),
  CONSTRAINT `laundry_reward_redemptions_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `laundry_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_reward_redemptions`
--


--
-- Table structure for table `laundry_service_pricing`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_service_pricing` (
  `tenant_id` bigint unsigned NOT NULL,
  `price_drop_off` decimal(16,4) NOT NULL DEFAULT '80.0000',
  `price_wash_only` decimal(16,4) NOT NULL DEFAULT '60.0000',
  `price_dry_only` decimal(16,4) NOT NULL DEFAULT '40.0000',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_service_pricing`
--


--
-- Table structure for table `laundry_time_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `laundry_time_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `clock_in_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `clock_in_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clock_out_at` timestamp NULL DEFAULT NULL,
  `clock_out_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_time_logs_tenant_idx` (`tenant_id`,`clock_in_at`),
  KEY `laundry_time_logs_user_idx` (`user_id`,`clock_in_at`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laundry_time_logs`
--

INSERT IGNORE INTO `laundry_time_logs` VALUES (1,1,2,'2026-04-19 17:43:13',NULL,'2026-04-19 17:43:33',NULL,'Incomplete shift: 0h 0m (below 8 hours).','2026-04-19 17:43:13','2026-04-19 17:43:33'),(2,1,2,'2026-04-19 18:11:28',NULL,'2026-04-19 18:11:32',NULL,'Incomplete shift: 0h 0m (below 8 hours).','2026-04-19 18:11:28','2026-04-19 18:11:32'),(3,1,2,'2026-04-19 18:25:08',NULL,'2026-04-19 18:25:12',NULL,'Incomplete shift: 0h 0m (below 8 hours).','2026-04-19 18:25:08','2026-04-19 18:25:12'),(4,1,2,'2026-04-19 18:25:46',NULL,'2026-04-19 18:25:49',NULL,'Incomplete shift: 0h 0m (below 8 hours).','2026-04-19 18:25:46','2026-04-19 18:25:49'),(5,1,2,'2026-04-19 19:35:33',NULL,'2026-04-20 07:30:45',NULL,NULL,'2026-04-19 19:35:33','2026-04-20 07:30:45'),(6,1,2,'2026-04-20 07:32:17',NULL,'2026-04-20 14:56:58','uploads/attendance/tenant1-user2-out-20260420225658.jpg','Incomplete shift: 7h 24m (below 8 hours).','2026-04-20 07:32:17','2026-04-20 14:56:58'),(7,1,3,'2026-04-20 09:24:02','storage/attendance/tenant1-user3-in-20260420172402.jpg','2026-04-20 09:29:25','storage/attendance/tenant1-user3-out-20260420172925.jpg','Incomplete shift: 0h 5m (below 8 hours).','2026-04-20 09:24:02','2026-04-20 09:29:25'),(8,1,3,'2026-04-20 09:32:09','uploads/attendance/tenant1-user3-in-20260420173209.jpg','2026-04-20 09:32:44','uploads/attendance/tenant1-user3-out-20260420173244.jpg','Incomplete shift: 0h 0m (below 8 hours).','2026-04-20 09:32:09','2026-04-20 09:32:44'),(9,1,3,'2026-04-20 09:32:55','uploads/attendance/tenant1-user3-in-20260420173255.jpg','2026-04-20 14:56:15','uploads/attendance/tenant1-user3-out-20260420225615.jpg','Incomplete shift: 5h 23m (below 8 hours).','2026-04-20 09:32:55','2026-04-20 14:56:15'),(10,1,2,'2026-04-20 15:00:51','uploads/attendance/tenant1-user2-in-20260420230051.jpg','2026-04-20 15:18:28','uploads/attendance/tenant1-user2-out-20260420231828.jpg','Incomplete shift: 0h 17m (below 8 hours).','2026-04-20 15:00:51','2026-04-20 15:18:28'),(11,1,7,'2026-04-20 17:15:19','uploads/attendance/tenant1-user7-in-20260421011519.jpg','2026-04-20 17:15:25','uploads/attendance/tenant1-user7-out-20260421011525.jpg','Incomplete shift: 0h 0m (below 8 hours).','2026-04-20 17:15:19','2026-04-20 17:15:25');

--
-- Table structure for table `migrations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

INSERT IGNORE INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_03_26_134539_create_categories_table',1),(5,'2026_03_26_134539_create_ingredients_table',1),(6,'2026_03_26_134539_create_inventory_movements_table',1),(7,'2026_03_26_134539_create_product_ingredients_table',1),(8,'2026_03_26_134539_create_products_table',1),(9,'2026_03_26_134539_create_tenants_table',1),(10,'2026_03_26_134539_create_transaction_items_table',1),(11,'2026_03_26_134539_create_transactions_table',1),(12,'2026_03_26_134603_add_tenant_and_role_to_users_table',1),(13,'2026_03_26_135203_add_foreign_keys_to_domain_tables',1),(14,'2026_03_26_172800_create_expenses_table',2),(15,'2026_03_26_172813_add_cost_columns_to_ingredients_and_transactions',2),(16,'2026_03_27_000001_create_activity_logs_table',3);

--
-- Table structure for table `password_reset_tokens`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--


--
-- Table structure for table `product_flavor_ingredients`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `product_flavor_ingredients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `ingredient_id` bigint unsigned NOT NULL,
  `quantity_required` decimal(38,16) NOT NULL DEFAULT '1.0000000000000000',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pfi_tenant_product_ingredient_unique` (`tenant_id`,`product_id`,`ingredient_id`),
  KEY `pfi_tenant_product_idx` (`tenant_id`,`product_id`),
  KEY `pfi_tenant_ingredient_idx` (`tenant_id`,`ingredient_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_flavor_ingredients`
--

INSERT IGNORE INTO `product_flavor_ingredients` VALUES (2,1,5,141,1.0000000000000000,'2026-04-13 17:15:20','2026-04-13 17:15:20'),(3,1,5,142,1.0000000000000000,'2026-04-13 17:15:20','2026-04-13 17:15:20'),(4,1,3,141,1.0000000000000000,'2026-04-13 17:21:43','2026-04-13 17:21:43'),(5,1,3,142,1.0000000000000000,'2026-04-13 17:21:43','2026-04-13 17:21:43');

--
-- Table structure for table `product_ingredients`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `product_ingredients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `ingredient_id` bigint unsigned NOT NULL,
  `quantity_required` decimal(38,16) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_ingredients_product_id_ingredient_id_unique` (`product_id`,`ingredient_id`),
  KEY `product_ingredients_tenant_id_product_id_ingredient_id_index` (`tenant_id`,`product_id`,`ingredient_id`),
  KEY `product_ingredients_ingredient_id_foreign` (`ingredient_id`),
  CONSTRAINT `product_ingredients_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_ingredients_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_ingredients_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_ingredients`
--

INSERT IGNORE INTO `product_ingredients` VALUES (12,1,1,1,100.0000000000000000,'2026-03-26 10:40:32','2026-03-26 10:40:32'),(13,1,1,2,150.0000000000000000,'2026-03-26 10:40:32','2026-03-26 10:40:32'),(14,1,1,3,20.0000000000000000,'2026-03-26 10:40:32','2026-03-26 10:40:32'),(15,1,1,4,1.0000000000000000,'2026-03-26 10:40:32','2026-03-26 10:40:32'),(16,1,2,1,12.0000000000000000,'2026-03-26 10:42:25','2026-03-26 10:42:25'),(17,1,2,2,2.0000000000000000,'2026-03-26 10:42:25','2026-03-26 10:42:25'),(18,1,2,3,2.0000000000000000,'2026-03-26 10:42:25','2026-03-26 10:42:25'),(19,1,2,4,1.0000000000000000,'2026-03-26 10:42:25','2026-03-26 10:42:25'),(28,2,8,16,1.0000000000000000,'2026-03-29 04:45:11','2026-03-29 04:45:11'),(29,2,8,32,75.0000000000000000,'2026-03-29 04:45:11','2026-03-29 04:45:11'),(30,2,8,28,60.0000000000000000,'2026-03-29 04:45:11','2026-03-29 04:45:11'),(31,2,8,22,1.0000000000000000,'2026-03-29 04:45:11','2026-03-29 04:45:11'),(32,2,8,41,1.0000000000000000,'2026-03-29 04:45:11','2026-03-29 04:45:11'),(33,2,9,32,95.0000000000000000,'2026-03-29 04:50:26','2026-03-29 04:50:26'),(34,2,9,28,80.0000000000000000,'2026-03-29 04:50:26','2026-03-29 04:50:26'),(35,2,9,17,1.0000000000000000,'2026-03-29 04:50:26','2026-03-29 04:50:26'),(36,2,9,41,1.0000000000000000,'2026-03-29 04:50:26','2026-03-29 04:50:26'),(37,2,9,22,1.0000000000000000,'2026-03-29 04:50:26','2026-03-29 04:50:26'),(41,2,11,34,1.0000000000000000,'2026-03-29 04:52:23','2026-03-29 04:52:23'),(42,2,11,41,1.0000000000000000,'2026-03-29 04:52:23','2026-03-29 04:52:23'),(43,2,11,22,1.0000000000000000,'2026-03-29 04:52:23','2026-03-29 04:52:23'),(44,2,11,28,120.0000000000000000,'2026-03-29 04:52:23','2026-03-29 04:52:23'),(45,2,11,32,135.0000000000000000,'2026-03-29 04:52:23','2026-03-29 04:52:23'),(46,2,12,16,1.0000000000000000,'2026-03-29 04:54:30','2026-03-29 04:54:30'),(47,2,12,41,1.0000000000000000,'2026-03-29 04:54:30','2026-03-29 04:54:30'),(48,2,12,22,1.0000000000000000,'2026-03-29 04:54:30','2026-03-29 04:54:30'),(49,2,12,32,75.0000000000000000,'2026-03-29 04:54:30','2026-03-29 04:54:30'),(50,2,12,28,60.0000000000000000,'2026-03-29 04:54:30','2026-03-29 04:54:30'),(51,2,12,31,6.0000000000000000,'2026-03-29 04:54:30','2026-03-29 04:54:30'),(52,2,12,33,3.0000000000000000,'2026-03-29 04:54:30','2026-03-29 04:54:30'),(53,2,13,17,1.0000000000000000,'2026-03-29 04:59:20','2026-03-29 04:59:20'),(54,2,13,41,1.0000000000000000,'2026-03-29 04:59:20','2026-03-29 04:59:20'),(55,2,13,22,1.0000000000000000,'2026-03-29 04:59:20','2026-03-29 04:59:20'),(56,2,13,32,95.0000000000000000,'2026-03-29 04:59:20','2026-03-29 04:59:20'),(57,2,13,28,80.0000000000000000,'2026-03-29 04:59:20','2026-03-29 04:59:20'),(58,2,13,31,9.0000000000000000,'2026-03-29 04:59:20','2026-03-29 04:59:20'),(59,2,13,33,3.0000000000000000,'2026-03-29 04:59:20','2026-03-29 04:59:20'),(60,2,14,34,1.0000000000000000,'2026-03-29 05:01:23','2026-03-29 05:01:23'),(61,2,14,41,1.0000000000000000,'2026-03-29 05:01:23','2026-03-29 05:01:23'),(62,2,14,22,1.0000000000000000,'2026-03-29 05:01:23','2026-03-29 05:01:23'),(63,2,14,32,135.0000000000000000,'2026-03-29 05:01:23','2026-03-29 05:01:23'),(64,2,14,28,120.0000000000000000,'2026-03-29 05:01:23','2026-03-29 05:01:23'),(65,2,14,31,12.0000000000000000,'2026-03-29 05:01:23','2026-03-29 05:01:23'),(66,2,14,33,3.0000000000000000,'2026-03-29 05:01:23','2026-03-29 05:01:23'),(67,2,15,35,1.0000000000000000,'2026-03-29 05:04:33','2026-03-29 05:04:33'),(68,2,15,41,1.0000000000000000,'2026-03-29 05:04:33','2026-03-29 05:04:33'),(69,2,15,22,1.0000000000000000,'2026-03-29 05:04:33','2026-03-29 05:04:33'),(70,2,15,32,150.0000000000000000,'2026-03-29 05:04:33','2026-03-29 05:04:33'),(71,2,15,28,135.0000000000000000,'2026-03-29 05:04:33','2026-03-29 05:04:33'),(72,2,16,35,1.0000000000000000,'2026-03-29 05:05:55','2026-03-29 05:05:55'),(73,2,16,41,1.0000000000000000,'2026-03-29 05:05:55','2026-03-29 05:05:55'),(74,2,16,22,1.0000000000000000,'2026-03-29 05:05:55','2026-03-29 05:05:55'),(75,2,16,32,150.0000000000000000,'2026-03-29 05:05:55','2026-03-29 05:05:55'),(76,2,16,28,135.0000000000000000,'2026-03-29 05:05:55','2026-03-29 05:05:55'),(77,2,16,31,15.0000000000000000,'2026-03-29 05:05:55','2026-03-29 05:05:55'),(78,2,16,33,3.0000000000000000,'2026-03-29 05:05:55','2026-03-29 05:05:55'),(118,2,44,50,120.0000000000000000,'2026-03-29 06:39:04','2026-03-29 06:39:04'),(119,2,44,37,1.0000000000000000,'2026-03-29 06:39:04','2026-03-29 06:39:04'),(120,2,45,50,80.0000000000000000,'2026-03-29 06:39:43','2026-03-29 06:39:43'),(121,2,45,36,1.0000000000000000,'2026-03-29 06:39:43','2026-03-29 06:39:43'),(124,2,47,58,200.0000000000000000,'2026-03-29 06:41:09','2026-03-29 06:41:09'),(125,2,47,66,1.0000000000000000,'2026-03-29 06:41:09','2026-03-29 06:41:09'),(126,2,48,7,1.0000000000000000,'2026-03-29 06:48:10','2026-03-29 06:48:10'),(127,2,48,66,1.0000000000000000,'2026-03-29 06:48:10','2026-03-29 06:48:10'),(128,2,48,60,1.0000000000000000,'2026-03-29 06:48:10','2026-03-29 06:48:10'),(129,2,49,8,1.0000000000000000,'2026-03-29 06:48:44','2026-03-29 06:48:44'),(130,2,49,66,1.0000000000000000,'2026-03-29 06:48:44','2026-03-29 06:48:44'),(131,2,49,60,1.0000000000000000,'2026-03-29 06:48:44','2026-03-29 06:48:44'),(132,2,50,25,1.0000000000000000,'2026-03-29 06:49:11','2026-03-29 06:49:11'),(133,2,50,66,1.0000000000000000,'2026-03-29 06:49:11','2026-03-29 06:49:11'),(134,2,51,25,1.0000000000000000,'2026-03-29 06:49:35','2026-03-29 06:49:35'),(135,2,51,66,1.0000000000000000,'2026-03-29 06:49:35','2026-03-29 06:49:35'),(136,2,52,10,1.0000000000000000,'2026-03-29 06:50:15','2026-03-29 06:50:15'),(137,2,52,15,1.0000000000000000,'2026-03-29 06:50:15','2026-03-29 06:50:15'),(138,2,53,11,1.0000000000000000,'2026-03-29 06:50:36','2026-03-29 06:50:36'),(139,2,53,15,1.0000000000000000,'2026-03-29 06:50:36','2026-03-29 06:50:36'),(140,2,54,9,1.0000000000000000,'2026-03-29 06:50:56','2026-03-29 06:50:56'),(141,2,54,15,1.0000000000000000,'2026-03-29 06:50:56','2026-03-29 06:50:56'),(142,2,55,12,1.0000000000000000,'2026-03-29 06:51:13','2026-03-29 06:51:13'),(143,2,55,15,1.0000000000000000,'2026-03-29 06:51:13','2026-03-29 06:51:13'),(144,2,56,13,1.0000000000000000,'2026-03-29 06:51:36','2026-03-29 06:51:36'),(145,2,56,15,1.0000000000000000,'2026-03-29 06:51:36','2026-03-29 06:51:36'),(146,2,57,44,4.0000000000000000,'2026-03-29 06:52:30','2026-03-29 06:52:30'),(147,2,57,21,1.0000000000000000,'2026-03-29 06:52:30','2026-03-29 06:52:30'),(148,2,58,44,6.0000000000000000,'2026-03-29 06:52:47','2026-03-29 06:52:47'),(149,2,58,21,1.0000000000000000,'2026-03-29 06:52:47','2026-03-29 06:52:47'),(150,2,59,45,4.0000000000000000,'2026-03-29 06:53:16','2026-03-29 06:53:16'),(151,2,59,21,1.0000000000000000,'2026-03-29 06:53:16','2026-03-29 06:53:16'),(152,2,60,45,6.0000000000000000,'2026-03-29 06:53:39','2026-03-29 06:53:39'),(153,2,60,21,1.0000000000000000,'2026-03-29 06:53:39','2026-03-29 06:53:39'),(154,2,61,46,4.0000000000000000,'2026-03-29 06:54:09','2026-03-29 06:54:09'),(155,2,61,21,1.0000000000000000,'2026-03-29 06:54:09','2026-03-29 06:54:09'),(156,2,62,46,6.0000000000000000,'2026-03-29 06:54:28','2026-03-29 06:54:28'),(157,2,62,21,1.0000000000000000,'2026-03-29 06:54:28','2026-03-29 06:54:28'),(158,2,63,23,1.0000000000000000,'2026-03-29 06:54:47','2026-03-29 06:54:47'),(159,2,64,24,1.0000000000000000,'2026-03-29 06:55:03','2026-03-29 06:55:03'),(160,2,65,49,1.0000000000000000,'2026-03-29 06:57:30','2026-03-29 06:57:30'),(161,2,65,58,190.0000000000000000,'2026-03-29 06:57:30','2026-03-29 06:57:30'),(162,2,65,25,1.0000000000000000,'2026-03-29 06:57:30','2026-03-29 06:57:30'),(163,2,65,38,1.0000000000000000,'2026-03-29 06:57:30','2026-03-29 06:57:30'),(164,2,65,59,1.0000000000000000,'2026-03-29 06:57:30','2026-03-29 06:57:30'),(165,2,66,48,1.0000000000000000,'2026-03-29 06:58:07','2026-03-29 06:58:07'),(166,2,66,58,190.0000000000000000,'2026-03-29 06:58:07','2026-03-29 06:58:07'),(167,2,66,25,1.0000000000000000,'2026-03-29 06:58:07','2026-03-29 06:58:07'),(168,2,66,38,1.0000000000000000,'2026-03-29 06:58:07','2026-03-29 06:58:07'),(169,2,66,59,1.0000000000000000,'2026-03-29 06:58:07','2026-03-29 06:58:07'),(170,2,67,42,1.0000000000000000,'2026-03-29 06:58:48','2026-03-29 06:58:48'),(171,2,67,58,190.0000000000000000,'2026-03-29 06:58:48','2026-03-29 06:58:48'),(172,2,67,25,1.0000000000000000,'2026-03-29 06:58:48','2026-03-29 06:58:48'),(173,2,67,38,1.0000000000000000,'2026-03-29 06:58:48','2026-03-29 06:58:48'),(174,2,67,59,1.0000000000000000,'2026-03-29 06:58:48','2026-03-29 06:58:48'),(175,2,68,43,2.0000000000000000,'2026-03-29 06:59:30','2026-03-29 06:59:30'),(176,2,68,58,190.0000000000000000,'2026-03-29 06:59:30','2026-03-29 06:59:30'),(177,2,68,25,1.0000000000000000,'2026-03-29 06:59:30','2026-03-29 06:59:30'),(178,2,68,38,1.0000000000000000,'2026-03-29 06:59:30','2026-03-29 06:59:30'),(179,2,68,59,1.0000000000000000,'2026-03-29 06:59:30','2026-03-29 06:59:30'),(180,2,69,47,2.0000000000000000,'2026-03-29 07:00:11','2026-03-29 07:00:11'),(181,2,69,58,190.0000000000000000,'2026-03-29 07:00:11','2026-03-29 07:00:11'),(182,2,69,25,1.0000000000000000,'2026-03-29 07:00:11','2026-03-29 07:00:11'),(183,2,69,38,1.0000000000000000,'2026-03-29 07:00:11','2026-03-29 07:00:11'),(184,2,69,59,1.0000000000000000,'2026-03-29 07:00:11','2026-03-29 07:00:11'),(185,2,70,44,2.0000000000000000,'2026-03-29 07:02:25','2026-03-29 07:02:25'),(186,2,70,58,190.0000000000000000,'2026-03-29 07:02:25','2026-03-29 07:02:25'),(187,2,70,25,1.0000000000000000,'2026-03-29 07:02:25','2026-03-29 07:02:25'),(188,2,70,38,1.0000000000000000,'2026-03-29 07:02:25','2026-03-29 07:02:25'),(189,2,70,59,1.0000000000000000,'2026-03-29 07:02:25','2026-03-29 07:02:25'),(190,2,71,45,2.0000000000000000,'2026-03-29 07:03:07','2026-03-29 07:03:07'),(191,2,71,58,190.0000000000000000,'2026-03-29 07:03:07','2026-03-29 07:03:07'),(192,2,71,25,1.0000000000000000,'2026-03-29 07:03:07','2026-03-29 07:03:07'),(193,2,71,38,1.0000000000000000,'2026-03-29 07:03:07','2026-03-29 07:03:07'),(194,2,71,59,1.0000000000000000,'2026-03-29 07:03:07','2026-03-29 07:03:07'),(195,2,72,46,2.0000000000000000,'2026-03-29 07:03:53','2026-03-29 07:03:53'),(196,2,72,58,190.0000000000000000,'2026-03-29 07:03:53','2026-03-29 07:03:53'),(197,2,72,25,1.0000000000000000,'2026-03-29 07:03:53','2026-03-29 07:03:53'),(198,2,72,38,1.0000000000000000,'2026-03-29 07:03:54','2026-03-29 07:03:54'),(199,2,72,59,1.0000000000000000,'2026-03-29 07:03:54','2026-03-29 07:03:54'),(200,2,73,50,160.0000000000000000,'2026-03-29 07:04:50','2026-03-29 07:04:50'),(201,2,73,38,1.0000000000000000,'2026-03-29 07:04:50','2026-03-29 07:04:50'),(203,2,17,6,1.0000000000000000,'2026-03-31 06:34:54','2026-03-31 06:34:54'),(204,2,20,20,1.0000000000000000,'2026-03-31 14:04:04','2026-03-31 14:04:04'),(205,2,19,19,1.0000000000000000,'2026-03-31 14:04:17','2026-03-31 14:04:17'),(207,2,18,18,1.0000000000000000,'2026-04-01 04:29:13','2026-04-01 04:29:13'),(208,2,74,71,1.0000000000000000,'2026-04-01 06:18:43','2026-04-01 06:18:43'),(209,2,75,72,1.0000000000000000,'2026-04-01 06:19:09','2026-04-01 06:19:09'),(210,2,76,73,3.0000000000000000,'2026-04-01 08:25:49','2026-04-01 08:25:49'),(212,2,77,74,1.0000000000000000,'2026-04-01 11:11:17','2026-04-01 11:11:17'),(213,2,78,17,1.0000000000000000,'2026-04-01 17:04:27','2026-04-01 17:04:27'),(215,2,80,19,1.0000000000000000,'2026-04-01 17:14:50','2026-04-01 17:14:50'),(216,2,79,19,1.0000000000000000,'2026-04-01 17:38:36','2026-04-01 17:38:36'),(217,3,81,85,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(218,3,81,98,75.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(219,3,81,96,60.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(220,3,81,91,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(221,3,81,107,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(222,3,82,98,95.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(223,3,82,96,80.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(224,3,82,86,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(225,3,82,107,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(226,3,82,91,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(227,3,83,100,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(228,3,83,107,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(229,3,83,91,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(230,3,83,96,120.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(231,3,83,98,135.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(232,3,84,85,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(233,3,84,107,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(234,3,84,91,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(235,3,84,98,75.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(236,3,84,96,60.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(237,3,84,97,6.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(238,3,84,99,3.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(239,3,85,86,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(240,3,85,107,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(241,3,85,91,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(242,3,85,98,95.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(243,3,85,96,80.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(244,3,85,97,9.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(245,3,85,99,3.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(246,3,86,100,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(247,3,86,107,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(248,3,86,91,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(249,3,86,98,135.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(250,3,86,96,120.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(251,3,86,97,12.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(252,3,86,99,3.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(253,3,87,101,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(254,3,87,107,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(255,3,87,91,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(256,3,87,98,150.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(257,3,87,96,135.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(258,3,88,101,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(259,3,88,107,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(260,3,88,91,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(261,3,88,98,150.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(262,3,88,96,135.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(263,3,88,97,15.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(264,3,88,99,3.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(265,3,93,116,120.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(266,3,93,103,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(267,3,94,116,80.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(268,3,94,102,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(269,3,95,124,200.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(270,3,95,132,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(271,3,96,76,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(272,3,96,132,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(273,3,96,126,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(274,3,97,77,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(275,3,97,132,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(276,3,97,126,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(277,3,98,94,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(278,3,98,132,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(279,3,99,94,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(280,3,99,132,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(281,3,100,79,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(282,3,100,84,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(283,3,101,80,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(284,3,101,84,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(285,3,102,78,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(286,3,102,84,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(287,3,103,81,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(288,3,103,84,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(289,3,104,82,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(290,3,104,84,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(291,3,105,110,4.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(292,3,105,90,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(293,3,106,110,6.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(294,3,106,90,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(295,3,107,111,4.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(296,3,107,90,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(297,3,108,111,6.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(298,3,108,90,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(299,3,109,112,4.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(300,3,109,90,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(301,3,110,112,6.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(302,3,110,90,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(303,3,111,92,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(304,3,112,93,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(305,3,113,115,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(306,3,113,124,190.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(307,3,113,94,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(308,3,113,104,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(309,3,113,125,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(310,3,114,114,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(311,3,114,124,190.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(312,3,114,94,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(313,3,114,104,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(314,3,114,125,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(315,3,115,108,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(316,3,115,124,190.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(317,3,115,94,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(318,3,115,104,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(319,3,115,125,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(320,3,116,109,2.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(321,3,116,124,190.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(322,3,116,94,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(323,3,116,104,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(324,3,116,125,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(325,3,117,113,2.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(326,3,117,124,190.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(327,3,117,94,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(328,3,117,104,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(329,3,117,125,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(330,3,118,110,2.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(331,3,118,124,190.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(332,3,118,94,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(333,3,118,104,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(334,3,118,125,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(335,3,119,111,2.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(336,3,119,124,190.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(337,3,119,94,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(338,3,119,104,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(339,3,119,125,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(340,3,120,112,2.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(341,3,120,124,190.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(342,3,120,94,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(343,3,120,104,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(344,3,120,125,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(345,3,121,116,160.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(346,3,121,104,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(347,3,89,75,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(348,3,92,89,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(349,3,91,88,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(350,3,90,87,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(351,3,122,137,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(352,3,123,138,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(353,3,124,139,3.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(354,3,125,140,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(355,3,126,86,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(356,3,128,88,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(357,3,127,88,1.0000000000000000,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(362,1,4,5,1.0000000000000000,'2026-04-09 03:09:03','2026-04-09 03:09:03'),(366,1,5,5,0.6244444555500000,'2026-04-13 09:15:20','2026-04-13 09:15:20'),(367,1,3,1,75.0000000000000000,'2026-04-13 09:21:43','2026-04-13 09:21:43'),(368,1,3,2,12.0000000000000000,'2026-04-13 09:21:43','2026-04-13 09:21:43'),(369,1,3,3,1.0000000000000000,'2026-04-13 09:21:43','2026-04-13 09:21:43'),(370,1,3,4,1.0000000000000000,'2026-04-13 09:21:43','2026-04-13 09:21:43');

--
-- Table structure for table `products`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(38,16) NOT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `has_flavor_options` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_tenant_id_name_unique` (`tenant_id`,`name`),
  KEY `products_tenant_id_category_id_index` (`tenant_id`,`category_id`),
  KEY `products_category_id_foreign` (`category_id`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

INSERT IGNORE INTO `products` VALUES (1,1,NULL,'Mango Shake 8oz',79.0000000000000000,NULL,1,0,'2026-03-26 05:52:33','2026-03-26 05:52:33'),(2,1,NULL,'Mango Shake 12 oz',99.0000000000000000,NULL,1,0,'2026-03-26 05:55:53','2026-03-26 10:46:21'),(3,1,NULL,'Mango Shake 8 oz Special',105.0000000000000000,NULL,1,1,'2026-03-26 06:03:01','2026-04-13 09:21:43'),(4,1,NULL,'Takoyaki 3pcs.',36.0000000000000000,NULL,1,0,'2026-03-26 06:08:21','2026-04-09 03:09:03'),(5,1,NULL,'Takoyaki 6 pcs.',150.0000000000000000,NULL,1,1,'2026-03-26 21:24:01','2026-04-13 09:15:20'),(8,2,NULL,'Mango Shake 8oz',35.0000000000000000,NULL,1,0,'2026-03-29 04:45:11','2026-03-29 04:45:11'),(9,2,NULL,'Mango Shake 12oz',45.0000000000000000,NULL,1,0,'2026-03-29 04:50:26','2026-03-29 04:50:26'),(11,2,NULL,'Mango Shake 16oz',55.0000000000000000,NULL,1,0,'2026-03-29 04:52:23','2026-03-29 04:52:23'),(12,2,NULL,'Mango Graham Shake 8oz',45.0000000000000000,NULL,1,0,'2026-03-29 04:54:30','2026-03-29 04:54:30'),(13,2,NULL,'Mango Graham Shake 12oz',55.0000000000000000,NULL,1,0,'2026-03-29 04:59:20','2026-03-29 04:59:20'),(14,2,NULL,'Mango Graham Shake 16oz',65.0000000000000000,NULL,1,0,'2026-03-29 05:01:23','2026-03-29 05:01:23'),(15,2,NULL,'Mango Shake 22oz',70.0000000000000000,NULL,1,0,'2026-03-29 05:04:33','2026-03-29 05:04:33'),(16,2,NULL,'Mango Graham Shake 22oz',80.0000000000000000,NULL,1,0,'2026-03-29 05:05:55','2026-03-29 05:05:55'),(17,2,NULL,'Takoyaki 3pcs.',30.0000000000000000,NULL,1,0,'2026-03-29 06:12:28','2026-03-31 06:34:54'),(18,2,NULL,'Takoyaki 8pcs.',80.0000000000000000,NULL,1,0,'2026-03-29 06:12:46','2026-04-01 04:29:13'),(19,2,NULL,'Takoyaki 12pcs',120.0000000000000000,NULL,1,0,'2026-03-29 06:13:05','2026-03-31 14:04:17'),(20,2,NULL,'Takoyaki 16pcs',150.0000000000000000,NULL,1,0,'2026-03-29 06:13:23','2026-03-31 14:04:04'),(44,2,NULL,'French Fries Medium',35.0000000000000000,NULL,1,0,'2026-03-29 06:39:04','2026-03-29 06:39:04'),(45,2,NULL,'French Fries Small',25.0000000000000000,NULL,1,0,'2026-03-29 06:39:43','2026-03-29 06:39:43'),(47,2,NULL,'Rice',15.0000000000000000,NULL,1,0,'2026-03-29 06:41:09','2026-03-29 06:41:09'),(48,2,NULL,'Paluto Pancit Canton Spicy',20.0000000000000000,NULL,1,0,'2026-03-29 06:48:10','2026-03-29 06:48:10'),(49,2,NULL,'Paluto Pancit Canton',20.0000000000000000,NULL,1,0,'2026-03-29 06:48:44','2026-03-29 06:48:44'),(50,2,NULL,'Paluto Fried Egg',15.0000000000000000,NULL,1,0,'2026-03-29 06:49:11','2026-03-29 06:49:11'),(51,2,NULL,'Paluto Boiled Egg',15.0000000000000000,NULL,1,0,'2026-03-29 06:49:35','2026-03-29 06:49:35'),(52,2,NULL,'Patimpla Nescafe Classic',15.0000000000000000,NULL,1,0,'2026-03-29 06:50:15','2026-03-29 06:50:15'),(53,2,NULL,'Patimpla Kopiko Brown',15.0000000000000000,NULL,1,0,'2026-03-29 06:50:36','2026-03-29 06:50:36'),(54,2,NULL,'Patimpla Bearbrand',20.0000000000000000,NULL,1,0,'2026-03-29 06:50:56','2026-03-29 06:50:56'),(55,2,NULL,'Patimpla Milo',15.0000000000000000,NULL,1,0,'2026-03-29 06:51:13','2026-03-29 06:51:13'),(56,2,NULL,'Patimpla Nescafe Stick',10.0000000000000000,NULL,1,0,'2026-03-29 06:51:36','2026-03-29 06:51:36'),(57,2,NULL,'Siomai Beef 4s',20.0000000000000000,NULL,1,0,'2026-03-29 06:52:30','2026-03-29 06:52:30'),(58,2,NULL,'Siomai Beef 6s',30.0000000000000000,NULL,1,0,'2026-03-29 06:52:47','2026-03-29 06:52:47'),(59,2,NULL,'Siomai Chicken 4s',20.0000000000000000,NULL,1,0,'2026-03-29 06:53:16','2026-03-29 06:53:16'),(60,2,NULL,'Siomai Chicken 6s',30.0000000000000000,NULL,1,0,'2026-03-29 06:53:39','2026-03-29 06:53:39'),(61,2,NULL,'Siomai Pork 4s',20.0000000000000000,NULL,1,0,'2026-03-29 06:54:09','2026-03-29 06:54:09'),(62,2,NULL,'Siomai Pork 6s',30.0000000000000000,NULL,1,0,'2026-03-29 06:54:28','2026-03-29 06:54:28'),(63,2,NULL,'Swakto Coke',20.0000000000000000,NULL,1,0,'2026-03-29 06:54:47','2026-03-29 06:54:47'),(64,2,NULL,'Swakto Royal',20.0000000000000000,NULL,1,0,'2026-03-29 06:55:03','2026-03-29 06:55:03'),(65,2,NULL,'Ricemeal Shawarma',60.0000000000000000,NULL,1,0,'2026-03-29 06:57:30','2026-03-29 06:57:30'),(66,2,NULL,'Ricemeal Sisig',50.0000000000000000,NULL,1,0,'2026-03-29 06:58:07','2026-03-29 06:58:07'),(67,2,NULL,'Ricemeal Hotdog',40.0000000000000000,NULL,1,0,'2026-03-29 06:58:48','2026-03-29 06:58:48'),(68,2,NULL,'Ricemeal Meatloaf',40.0000000000000000,NULL,1,0,'2026-03-29 06:59:30','2026-03-29 06:59:30'),(69,2,NULL,'Ricemeal Skinless',40.0000000000000000,NULL,1,0,'2026-03-29 07:00:11','2026-03-29 07:00:11'),(70,2,NULL,'Ricemeal Siomai Beef',40.0000000000000000,NULL,1,0,'2026-03-29 07:02:25','2026-03-29 07:02:25'),(71,2,NULL,'Ricemeal Siomai Chicken',40.0000000000000000,NULL,1,0,'2026-03-29 07:03:07','2026-03-29 07:03:07'),(72,2,NULL,'Ricemeal Siomai Pork',40.0000000000000000,NULL,1,0,'2026-03-29 07:03:53','2026-03-29 07:03:53'),(73,2,NULL,'French Fries Large',45.0000000000000000,NULL,1,0,'2026-03-29 07:04:50','2026-03-29 07:04:50'),(74,2,NULL,'Mineral 500ml',15.0000000000000000,NULL,1,0,'2026-04-01 06:18:43','2026-04-01 06:18:43'),(75,2,NULL,'Mineral 1000ml',25.0000000000000000,NULL,1,0,'2026-04-01 06:19:09','2026-04-01 06:19:09'),(76,2,NULL,'Siomai Japanese',25.0000000000000000,NULL,1,0,'2026-04-01 08:25:49','2026-04-01 08:25:49'),(77,2,NULL,'Takoyaki solo',12.0000000000000000,NULL,1,0,'2026-04-01 11:10:33','2026-04-01 11:11:17'),(78,2,NULL,'Test Product',122.0000000000000000,'uploads/products/714efe40b9ec892ce9c6d61ecbded70f82aa37d9c11c15d564680f761a100621.jpg',1,0,'2026-04-01 17:04:27','2026-04-01 17:04:27'),(79,2,NULL,'Test Product 2',122.0000000000000000,'uploads/products/test-product-2_c6a086cc1beb7648cc9c3906636a68b3fa0a6957deb951fafc076655a98cedf7.jpg',1,0,'2026-04-01 17:13:56','2026-04-01 17:38:35'),(80,2,NULL,'Test Product 3',234.0000000000000000,'uploads/products/test-product-3_38e38a070d6c03cbfdfbe7fd61246c34e7acae075dd512395aa3ea8c1a03f3fc.jpg',1,0,'2026-04-01 17:14:50','2026-04-01 17:14:50'),(81,3,NULL,'Mango Shake 8oz',35.0000000000000000,'',1,0,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(82,3,NULL,'Mango Shake 12oz',45.0000000000000000,'',1,0,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(83,3,NULL,'Mango Shake 16oz',55.0000000000000000,'',1,0,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(84,3,NULL,'Mango Graham Shake 8oz',45.0000000000000000,'',1,0,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(85,3,NULL,'Mango Graham Shake 12oz',55.0000000000000000,'',1,0,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(86,3,NULL,'Mango Graham Shake 16oz',65.0000000000000000,'',1,0,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(87,3,NULL,'Mango Shake 22oz',70.0000000000000000,'',1,0,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(88,3,NULL,'Mango Graham Shake 22oz',80.0000000000000000,'',1,0,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(89,3,NULL,'Takoyaki 3pcs.',30.0000000000000000,'',1,0,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(90,3,NULL,'Takoyaki 8pcs.',80.0000000000000000,'',1,0,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(91,3,NULL,'Takoyaki 12pcs',120.0000000000000000,'',1,0,'2026-04-01 18:58:47','2026-04-01 18:58:47'),(92,3,NULL,'Takoyaki 16pcs',150.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(93,3,NULL,'French Fries Medium',35.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(94,3,NULL,'French Fries Small',25.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(95,3,NULL,'Rice',15.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(96,3,NULL,'Paluto Pancit Canton Spicy',20.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(97,3,NULL,'Paluto Pancit Canton',20.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(98,3,NULL,'Paluto Fried Egg',15.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(99,3,NULL,'Paluto Boiled Egg',15.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(100,3,NULL,'Patimpla Nescafe Classic',15.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(101,3,NULL,'Patimpla Kopiko Brown',15.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(102,3,NULL,'Patimpla Bearbrand',20.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(103,3,NULL,'Patimpla Milo',15.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(104,3,NULL,'Patimpla Nescafe Stick',10.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(105,3,NULL,'Siomai Beef 4s',20.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(106,3,NULL,'Siomai Beef 6s',30.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(107,3,NULL,'Siomai Chicken 4s',20.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(108,3,NULL,'Siomai Chicken 6s',30.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(109,3,NULL,'Siomai Pork 4s',20.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(110,3,NULL,'Siomai Pork 6s',30.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(111,3,NULL,'Swakto Coke',20.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(112,3,NULL,'Swakto Royal',20.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(113,3,NULL,'Ricemeal Shawarma',60.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(114,3,NULL,'Ricemeal Sisig',50.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(115,3,NULL,'Ricemeal Hotdog',40.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(116,3,NULL,'Ricemeal Meatloaf',40.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(117,3,NULL,'Ricemeal Skinless',40.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(118,3,NULL,'Ricemeal Siomai Beef',40.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(119,3,NULL,'Ricemeal Siomai Chicken',40.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(120,3,NULL,'Ricemeal Siomai Pork',40.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(121,3,NULL,'French Fries Large',45.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(122,3,NULL,'Mineral 500ml',15.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(123,3,NULL,'Mineral 1000ml',25.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(124,3,NULL,'Siomai Japanese',25.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(125,3,NULL,'Takoyaki solo',12.0000000000000000,'',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(126,3,NULL,'Test Product',122.0000000000000000,'uploads/products/714efe40b9ec892ce9c6d61ecbded70f82aa37d9c11c15d564680f761a100621.jpg',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(127,3,NULL,'Test Product 2',122.0000000000000000,'uploads/products/test-product-2_c6a086cc1beb7648cc9c3906636a68b3fa0a6957deb951fafc076655a98cedf7.jpg',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(128,3,NULL,'Test Product 3',234.0000000000000000,'uploads/products/test-product-3_38e38a070d6c03cbfdfbe7fd61246c34e7acae075dd512395aa3ea8c1a03f3fc.jpg',1,0,'2026-04-01 18:58:48','2026-04-01 18:58:48'),(129,4,NULL,'dw',2.0000000000000000,'',1,0,'2026-04-15 22:18:53','2026-04-15 22:18:53');

--
-- Table structure for table `sessions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

INSERT IGNORE INTO `sessions` VALUES ('20FSGpSNnxly0GOVQFHczxGIZqX3YwXzsyrLjuIO',2,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiQVRiemxKTFNhUmRtSnBMODI0WGdsOEF0YzBCWDlXYUJkeXQweEt4MSI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MjtzOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czozMjoiaHR0cDovLzEyNy4wLjAuMTo4MDAwL3RlbmFudC9wb3MiO3M6NToicm91dGUiO3M6MTY6InRlbmFudC5wb3MuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1774617155);

--
-- Table structure for table `tenant_backup_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `tenant_backup_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `backup_id` bigint unsigned NOT NULL,
  `table_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `row_count` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_backup_items_backup_table_unique` (`backup_id`,`table_name`),
  KEY `tenant_backup_items_table_name_index` (`table_name`),
  CONSTRAINT `tenant_backup_items_backup_id_fk` FOREIGN KEY (`backup_id`) REFERENCES `tenant_backups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_backup_items`
--

INSERT IGNORE INTO `tenant_backup_items` VALUES (61,6,'tenants',1,'2026-04-12 16:45:15','2026-04-12 16:45:15'),(62,6,'users',2,'2026-04-12 16:45:15','2026-04-12 16:45:15'),(63,6,'categories',0,'2026-04-12 16:45:15','2026-04-12 16:45:15'),(64,6,'ingredients',5,'2026-04-12 16:45:15','2026-04-12 16:45:15'),(65,6,'products',5,'2026-04-12 16:45:15','2026-04-12 16:45:15'),(66,6,'product_ingredients',14,'2026-04-12 16:45:15','2026-04-12 16:45:15'),(67,6,'expenses',1,'2026-04-12 16:45:15','2026-04-12 16:45:15'),(68,6,'transactions',12,'2026-04-12 16:45:15','2026-04-12 16:45:15'),(69,6,'transaction_items',42,'2026-04-12 16:45:15','2026-04-12 16:45:15'),(70,6,'inventory_movements',132,'2026-04-12 16:45:15','2026-04-12 16:45:15'),(71,6,'activity_logs',7,'2026-04-12 16:45:15','2026-04-12 16:45:15'),(72,6,'damaged_items',0,'2026-04-12 16:45:15','2026-04-12 16:45:15'),(73,7,'tenants',1,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(74,7,'users',2,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(75,7,'categories',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(76,7,'ingredients',7,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(77,7,'products',5,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(78,7,'product_ingredients',14,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(79,7,'expenses',1,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(80,7,'transactions',19,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(81,7,'transaction_items',53,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(82,7,'inventory_movements',157,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(83,7,'activity_logs',11,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(84,7,'damaged_items',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(85,8,'tenants',1,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(86,8,'users',1,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(87,8,'categories',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(88,8,'ingredients',66,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(89,8,'products',48,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(90,8,'product_ingredients',141,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(91,8,'expenses',1,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(92,8,'transactions',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(93,8,'transaction_items',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(94,8,'inventory_movements',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(95,8,'activity_logs',98,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(96,8,'damaged_items',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(97,9,'tenants',1,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(98,9,'users',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(99,9,'categories',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(100,9,'ingredients',66,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(101,9,'products',48,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(102,9,'product_ingredients',141,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(103,9,'expenses',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(104,9,'transactions',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(105,9,'transaction_items',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(106,9,'inventory_movements',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(107,9,'activity_logs',24,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(108,9,'damaged_items',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(109,10,'tenants',1,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(110,10,'users',1,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(111,10,'categories',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(112,10,'ingredients',1,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(113,10,'products',1,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(114,10,'product_ingredients',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(115,10,'expenses',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(116,10,'transactions',1,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(117,10,'transaction_items',1,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(118,10,'inventory_movements',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(119,10,'activity_logs',0,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(120,10,'damaged_items',0,'2026-04-15 22:38:05','2026-04-15 22:38:05');

--
-- Table structure for table `tenant_backups`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `tenant_backups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `backup_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `backup_key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned NOT NULL DEFAULT '0',
  `checksum_sha256` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `table_count` int unsigned NOT NULL DEFAULT '0',
  `row_count` bigint unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ready',
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_backups_backup_key_unique` (`backup_key`),
  KEY `tenant_backups_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `tenant_backups_status_index` (`status`),
  KEY `tenant_backups_created_by_user_id_fk` (`created_by_user_id`),
  CONSTRAINT `tenant_backups_created_by_user_id_fk` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tenant_backups_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_backups`
--

INSERT IGNORE INTO `tenant_backups` VALUES (6,1,'manual','tenant-1-manual-20260413-004515-87400594','storage/backups/tenants/1/tenant-1-manual-20260413-004515-87400594.sql.gz',4400,'14af389dcd1544d10b82ca61f2b41a3cc521273d0b62faebd2f7b8591f3eef41',12,221,'ready',1,NULL,'2026-04-12 16:45:15','2026-04-12 16:45:15'),(7,1,'manual_forced','tenant-1-manual_forced-20260416-063805-830a2012','storage/backups/tenants/1/tenant-1-manual_forced-20260416-063805-830a2012.sql.gz',5391,'47051b684c6cf75b484c52215f65643463bc68f974c752a2f7d163a6b26d6cfd',12,270,'ready',1,NULL,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(8,2,'manual_forced','tenant-2-manual_forced-20260416-063805-9cb02664','storage/backups/tenants/2/tenant-2-manual_forced-20260416-063805-9cb02664.sql.gz',8211,'1814c4a1c50f170d57ace2199ad3a957ebb96fe784b97a0925e731c2c580eda5',12,356,'ready',1,NULL,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(9,3,'manual_forced','tenant-3-manual_forced-20260416-063805-46e3574c','storage/backups/tenants/3/tenant-3-manual_forced-20260416-063805-46e3574c.sql.gz',4436,'c769e096a798719aebd10963e8920eb077a6c841e9fc0371779863ebcf170f8c',12,280,'ready',1,NULL,'2026-04-15 22:38:05','2026-04-15 22:38:05'),(10,4,'manual_forced','tenant-4-manual_forced-20260416-063805-84d18bcf','storage/backups/tenants/4/tenant-4-manual_forced-20260416-063805-84d18bcf.sql.gz',1231,'a6798d6e85f83f7190afc785c034579284def71cf6fb8699476adb26d6abb248',12,6,'ready',1,NULL,'2026-04-15 22:38:05','2026-04-15 22:38:05');

--
-- Table structure for table `tenant_trial_devices`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `tenant_trial_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `device_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_token` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_label` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_trial_devices_device_hash_unique` (`device_hash`),
  KEY `tenant_trial_devices_tenant_id_index` (`tenant_id`),
  KEY `tenant_trial_devices_user_id_index` (`user_id`),
  CONSTRAINT `tenant_trial_devices_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_trial_devices_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_trial_devices`
--

INSERT IGNORE INTO `tenant_trial_devices` VALUES (1,6,9,'d754e422f567fdd0ad9134aeb6f6fdc2c1deb2219d4018c29e6ccaad5517bd9d','mpg_yfopi3xr9m9mo8cy9nv','Google Inc. on MacIntel','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','::1','2026-04-21 09:16:41','2026-04-21 08:24:44','2026-04-21 09:16:41');

--
-- Table structure for table `tenants`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `tenants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_tenant_id` bigint unsigned DEFAULT NULL,
  `branch_group_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'basic',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_main_branch` tinyint(1) NOT NULL DEFAULT '0',
  `license_starts_at` timestamp NULL DEFAULT NULL,
  `license_expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `paid_amount` decimal(38,16) DEFAULT NULL,
  `max_branches` int unsigned DEFAULT NULL,
  `receipt_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `receipt_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_business_style` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_tax_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_footer_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `receipt_lan_print_copies` tinyint unsigned NOT NULL DEFAULT '1',
  `receipt_escpos_line_width` tinyint unsigned NOT NULL DEFAULT '32',
  `receipt_escpos_right_col_width` tinyint unsigned NOT NULL DEFAULT '10',
  `receipt_escpos_extra_feeds` tinyint unsigned NOT NULL DEFAULT '8',
  `receipt_escpos_cut_mode` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `receipt_serial_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_vat_applicable` tinyint(1) NOT NULL DEFAULT '1',
  `receipt_dti_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_tax_type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'non_vat',
  `receipt_is_bir_registered` tinyint(1) NOT NULL DEFAULT '0',
  `receipt_bir_accreditation_no` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_min` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_permit_no` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_ble_printer_match_rules` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`),
  KEY `tenants_parent_tenant_id_index` (`parent_tenant_id`),
  KEY `tenants_branch_group_id_index` (`branch_group_id`),
  KEY `tenants_branch_group_active_index` (`branch_group_id`,`is_active`),
  KEY `tenants_branch_group_main_index` (`branch_group_id`,`is_main_branch`),
  CONSTRAINT `tenants_branch_group_id_fk` FOREIGN KEY (`branch_group_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tenants_parent_tenant_id_fk` FOREIGN KEY (`parent_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

INSERT IGNORE INTO `tenants` VALUES (1,1,1,'Demo Store','demo-store','subscription_1m',1,1,'2026-04-21 10:38:48','2026-04-20 15:59:59','2026-03-26 05:52:32','2026-04-21 10:38:48',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,32,10,8,'none',NULL,1,NULL,'non_vat',0,NULL,NULL,NULL,NULL),(2,2,2,'Y3M\'s Snacks','y3ms-snacks','subscription',1,1,'2026-03-28 20:11:20','2026-05-06 16:00:00','2026-03-28 20:11:20','2026-04-16 07:46:24',0.0000000000000000,2,'+639777212946','Block 2, Lot 8 Phase 3, Westdale Villas, Punta II, Tanza, Cavite','mtech1897@gmail.com','Y3M\'s Snacks - Mintal Branch','Food Kiosk','2312312','Thank you for your purchase!',1,32,10,8,'none','2312',0,'1234','non_vat',0,NULL,NULL,NULL,NULL),(3,2,2,'Marvin Pardillo Gulle','22','subscription',1,0,'2026-03-28 20:11:20','2026-05-06 00:00:00','2026-04-01 18:58:47','2026-04-01 19:47:05',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,32,10,8,'none',NULL,1,NULL,'non_vat',0,NULL,NULL,NULL,NULL),(4,4,4,'MPG Technology Solutions','mpg-technology-solutions','subscription_3m',1,1,'2026-04-21 08:24:05','2026-07-21 08:24:05','2026-04-15 22:09:01','2026-04-21 08:24:05',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,32,10,8,'none',NULL,1,NULL,'non_vat',0,NULL,NULL,NULL,NULL),(5,5,5,'MPG Technology Solutions 2','mpg-technology-solutions-2','free_access',1,1,'2026-04-21 08:24:44','2026-04-28 08:24:44','2026-04-21 08:24:44','2026-04-21 09:15:46',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,32,10,8,'none',NULL,1,NULL,'non_vat',0,NULL,NULL,NULL,NULL),(6,6,6,'Test1','test1','free_access',1,1,'2026-04-21 09:46:23','2026-04-28 09:46:23','2026-04-21 09:16:41','2026-04-21 09:46:23',NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,32,10,8,'none',NULL,1,NULL,'non_vat',0,NULL,NULL,NULL,NULL);

--
-- Table structure for table `transaction_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `transaction_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `transaction_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `flavor_ingredient_id` bigint unsigned DEFAULT NULL,
  `flavor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flavor_quantity_required` decimal(38,16) DEFAULT NULL,
  `quantity` int unsigned NOT NULL,
  `unit_price` decimal(38,16) NOT NULL,
  `unit_expense` decimal(38,16) NOT NULL DEFAULT '0.0000000000000000',
  `line_total` decimal(38,16) NOT NULL,
  `line_expense` decimal(38,16) NOT NULL DEFAULT '0.0000000000000000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_items_tenant_id_transaction_id_product_id_index` (`tenant_id`,`transaction_id`,`product_id`),
  KEY `transaction_items_transaction_id_foreign` (`transaction_id`),
  KEY `transaction_items_product_id_foreign` (`product_id`),
  KEY `ti_tenant_tx_qty_idx` (`tenant_id`,`transaction_id`,`quantity`),
  CONSTRAINT `transaction_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `transaction_items_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaction_items_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction_items`
--

INSERT IGNORE INTO `transaction_items` VALUES (1,1,1,2,NULL,NULL,NULL,0,99.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-06 19:01:41','2026-04-06 19:04:46'),(2,1,2,1,NULL,NULL,NULL,0,79.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-06 19:07:30','2026-04-06 19:07:41'),(6,1,2,2,NULL,NULL,NULL,0,99.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-06 19:11:37','2026-04-06 19:12:55'),(7,1,1,1,NULL,NULL,NULL,1,79.0000000000000000,0.0000000000000000,79.0000000000000000,0.0000000000000000,'2026-04-06 19:17:25','2026-04-06 19:17:25'),(8,1,3,2,NULL,NULL,NULL,0,99.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-06 19:22:28','2026-04-06 19:22:40'),(9,1,3,1,NULL,NULL,NULL,1,79.0000000000000000,0.0000000000000000,79.0000000000000000,0.0000000000000000,'2026-04-06 19:22:56','2026-04-06 19:22:56'),(10,1,4,2,NULL,NULL,NULL,0,99.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-06 19:23:23','2026-04-06 19:24:45'),(11,1,4,4,NULL,NULL,NULL,1,36.0000000000000000,0.0000000000000000,36.0000000000000000,0.0000000000000000,'2026-04-06 19:23:54','2026-04-06 19:23:54'),(12,1,4,5,NULL,NULL,NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-06 19:23:54','2026-04-06 19:23:54'),(13,1,5,1,NULL,NULL,NULL,0,79.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-06 19:25:18','2026-04-06 19:25:52'),(14,1,5,2,NULL,NULL,NULL,1,99.0000000000000000,0.0000000000000000,99.0000000000000000,0.0000000000000000,'2026-04-06 19:25:18','2026-04-06 19:25:18'),(15,1,5,4,NULL,NULL,NULL,1,36.0000000000000000,0.0000000000000000,36.0000000000000000,0.0000000000000000,'2026-04-06 19:25:18','2026-04-06 19:25:18'),(16,1,5,5,NULL,NULL,NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-06 19:25:18','2026-04-06 19:25:18'),(17,1,5,5,NULL,NULL,NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-06 19:25:52','2026-04-06 19:25:52'),(18,1,6,1,NULL,NULL,NULL,0,79.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-06 19:30:31','2026-04-06 19:37:19'),(19,1,6,2,NULL,NULL,NULL,1,99.0000000000000000,0.0000000000000000,99.0000000000000000,0.0000000000000000,'2026-04-06 19:30:31','2026-04-06 19:30:31'),(20,1,6,4,NULL,NULL,NULL,1,36.0000000000000000,0.0000000000000000,36.0000000000000000,0.0000000000000000,'2026-04-06 19:30:31','2026-04-06 19:30:31'),(21,1,6,5,NULL,NULL,NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-06 19:30:31','2026-04-06 19:30:31'),(26,1,6,5,NULL,NULL,NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-06 19:37:19','2026-04-06 19:37:19'),(27,1,7,1,NULL,NULL,NULL,0,79.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-06 19:39:18','2026-04-06 19:39:43'),(28,1,7,2,NULL,NULL,NULL,1,99.0000000000000000,0.0000000000000000,99.0000000000000000,0.0000000000000000,'2026-04-06 19:39:18','2026-04-06 19:39:18'),(29,1,7,4,NULL,NULL,NULL,1,36.0000000000000000,0.0000000000000000,36.0000000000000000,0.0000000000000000,'2026-04-06 19:39:18','2026-04-06 19:39:18'),(30,1,7,5,NULL,NULL,NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-06 19:39:18','2026-04-06 19:39:18'),(31,1,7,5,NULL,NULL,NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-06 19:39:43','2026-04-06 19:39:43'),(32,1,8,1,NULL,NULL,NULL,0,79.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-06 19:44:31','2026-04-06 19:44:54'),(33,1,8,2,NULL,NULL,NULL,1,99.0000000000000000,0.0000000000000000,99.0000000000000000,0.0000000000000000,'2026-04-06 19:44:31','2026-04-06 19:44:31'),(34,1,8,4,NULL,NULL,NULL,1,36.0000000000000000,0.0000000000000000,36.0000000000000000,0.0000000000000000,'2026-04-06 19:44:31','2026-04-06 19:44:31'),(35,1,8,5,NULL,NULL,NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-06 19:44:31','2026-04-06 19:44:31'),(36,1,8,5,NULL,NULL,NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-06 19:44:54','2026-04-06 19:44:54'),(37,1,9,1,NULL,NULL,NULL,0,79.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-06 19:53:20','2026-04-06 20:01:16'),(38,1,9,2,NULL,NULL,NULL,1,99.0000000000000000,0.0000000000000000,99.0000000000000000,0.0000000000000000,'2026-04-06 19:53:20','2026-04-06 19:53:20'),(39,1,9,4,NULL,NULL,NULL,1,36.0000000000000000,0.0000000000000000,36.0000000000000000,0.0000000000000000,'2026-04-06 19:53:20','2026-04-06 19:53:20'),(40,1,9,5,NULL,NULL,NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-06 19:53:20','2026-04-06 19:53:20'),(41,1,9,5,NULL,NULL,NULL,0,150.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-06 20:01:16','2026-04-06 20:02:02'),(42,1,10,1,NULL,NULL,NULL,0,79.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-06 20:02:28','2026-04-06 20:05:50'),(43,1,10,2,NULL,NULL,NULL,1,99.0000000000000000,0.0000000000000000,99.0000000000000000,0.0000000000000000,'2026-04-06 20:02:28','2026-04-06 20:02:28'),(44,1,10,4,NULL,NULL,NULL,1,36.0000000000000000,0.0000000000000000,36.0000000000000000,0.0000000000000000,'2026-04-06 20:02:28','2026-04-06 20:02:28'),(45,1,10,5,NULL,NULL,NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-06 20:02:28','2026-04-06 20:02:28'),(46,1,11,4,NULL,NULL,NULL,1,36.0000000000000000,0.0000000000000000,36.0000000000000000,0.0000000000000000,'2026-04-07 15:36:07','2026-04-07 15:36:07'),(47,1,11,5,NULL,NULL,NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-07 15:36:07','2026-04-07 15:36:07'),(48,1,12,4,NULL,NULL,NULL,1,0.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-07 15:37:10','2026-04-07 15:37:10'),(49,1,12,5,NULL,NULL,NULL,1,0.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-07 15:37:10','2026-04-07 15:37:10'),(50,1,13,1,NULL,NULL,NULL,1,0.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-12 17:07:47','2026-04-12 17:07:47'),(51,1,13,2,NULL,NULL,NULL,1,0.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'2026-04-12 17:07:47','2026-04-12 17:07:47'),(52,1,14,5,142,'BBQ',NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-13 09:19:27','2026-04-13 09:19:27'),(53,1,15,5,141,'Cheese Flavor',NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-13 09:20:09','2026-04-13 09:20:09'),(54,1,16,3,141,'Cheese Flavor',NULL,2,105.0000000000000000,0.0000000000000000,210.0000000000000000,0.0000000000000000,'2026-04-13 09:22:43','2026-04-13 09:22:43'),(55,1,17,3,141,'Cheese Flavor',NULL,1,105.0000000000000000,0.0000000000000000,105.0000000000000000,0.0000000000000000,'2026-04-13 09:23:02','2026-04-13 09:23:02'),(56,1,17,5,142,'BBQ',NULL,1,150.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,'2026-04-13 09:23:02','2026-04-13 09:23:02'),(57,1,18,1,NULL,NULL,NULL,1,79.0000000000000000,0.0000000000000000,79.0000000000000000,0.0000000000000000,'2026-04-13 18:24:35','2026-04-13 18:24:35'),(58,1,18,3,142,'BBQ',1.0000000000000000,1,105.0000000000000000,0.0000000000000000,105.0000000000000000,0.0000000000000000,'2026-04-13 18:24:35','2026-04-13 18:24:35'),(59,1,19,1,NULL,NULL,NULL,1,79.0000000000000000,0.0000000000000000,79.0000000000000000,0.0000000000000000,'2026-04-13 18:30:01','2026-04-13 18:30:01'),(60,1,19,2,NULL,NULL,NULL,1,99.0000000000000000,0.0000000000000000,99.0000000000000000,0.0000000000000000,'2026-04-13 18:30:01','2026-04-13 18:30:01'),(61,4,20,129,NULL,NULL,NULL,1,2.0000000000000000,0.0000000000000000,2.0000000000000000,0.0000000000000000,'2026-04-15 22:19:05','2026-04-15 22:19:05'),(62,2,21,73,NULL,NULL,NULL,1,45.0000000000000000,0.0000000000000000,45.0000000000000000,0.0000000000000000,'2026-04-16 05:18:28','2026-04-16 05:18:28'),(63,2,21,44,NULL,NULL,NULL,1,35.0000000000000000,0.0000000000000000,35.0000000000000000,0.0000000000000000,'2026-04-16 05:18:28','2026-04-16 05:18:28'),(64,2,22,44,NULL,NULL,NULL,1,35.0000000000000000,0.0000000000000000,35.0000000000000000,0.0000000000000000,'2026-04-16 07:11:11','2026-04-16 07:11:11'),(65,2,23,45,NULL,NULL,NULL,1,25.0000000000000000,0.0000000000000000,25.0000000000000000,0.0000000000000000,'2026-04-16 08:13:23','2026-04-16 08:13:23');

--
-- Table structure for table `transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `total_amount` decimal(38,16) NOT NULL,
  `amount_tendered` decimal(38,16) DEFAULT NULL,
  `change_amount` decimal(38,16) DEFAULT NULL,
  `payment_method` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `amount_paid` decimal(38,16) NOT NULL DEFAULT '0.0000000000000000',
  `payment_breakdown_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `refunded_amount` decimal(38,16) NOT NULL DEFAULT '0.0000000000000000',
  `added_paid_amount` decimal(38,16) NOT NULL DEFAULT '0.0000000000000000',
  `original_total_amount` decimal(38,16) DEFAULT NULL,
  `expense_total` decimal(38,16) NOT NULL DEFAULT '0.0000000000000000',
  `profit_total` decimal(38,16) NOT NULL DEFAULT '0.0000000000000000',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `was_updated` tinyint(1) NOT NULL DEFAULT '0',
  `pending_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pending_contact` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transactions_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `transactions_user_id_foreign` (`user_id`),
  KEY `transactions_tenant_status_created_at_index` (`tenant_id`,`status`,`created_at`),
  CONSTRAINT `transactions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

INSERT IGNORE INTO `transactions` VALUES (1,1,2,79.0000000000000000,99.0000000000000000,0.0000000000000000,'gcash',99.0000000000000000,NULL,0.0000000000000000,79.0000000000000000,99.0000000000000000,0.0000000000000000,79.0000000000000000,'completed',1,NULL,NULL,'2026-04-06 19:01:41','2026-04-06 19:17:25'),(2,1,2,0.0000000000000000,79.0000000000000000,0.0000000000000000,'cash',79.0000000000000000,NULL,79.0000000000000000,0.0000000000000000,79.0000000000000000,0.0000000000000000,0.0000000000000000,'completed',1,NULL,NULL,'2026-04-06 19:07:30','2026-04-06 19:12:55'),(3,1,2,79.0000000000000000,99.0000000000000000,0.0000000000000000,'cash',99.0000000000000000,NULL,99.0000000000000000,79.0000000000000000,99.0000000000000000,0.0000000000000000,79.0000000000000000,'completed',1,NULL,NULL,'2026-04-06 19:22:28','2026-04-06 19:22:56'),(4,1,2,186.0000000000000000,99.0000000000000000,0.0000000000000000,'cash',99.0000000000000000,NULL,99.0000000000000000,186.0000000000000000,99.0000000000000000,0.0000000000000000,186.0000000000000000,'completed',1,NULL,NULL,'2026-04-06 19:23:23','2026-04-06 19:24:45'),(5,1,2,285.0000000000000000,500.0000000000000000,136.0000000000000000,'cash',364.0000000000000000,NULL,79.0000000000000000,0.0000000000000000,364.0000000000000000,0.0000000000000000,285.0000000000000000,'completed',1,NULL,NULL,'2026-04-06 19:25:18','2026-04-06 19:30:09'),(6,1,2,435.0000000000000000,500.0000000000000000,136.0000000000000000,'cash',364.0000000000000000,NULL,0.0000000000000000,71.0000000000000000,364.0000000000000000,0.0000000000000000,435.0000000000000000,'completed',1,NULL,NULL,'2026-04-06 19:30:31','2026-04-06 19:37:19'),(7,1,2,435.0000000000000000,500.0000000000000000,0.0000000000000000,'cash',364.0000000000000000,NULL,0.0000000000000000,71.0000000000000000,364.0000000000000000,0.0000000000000000,435.0000000000000000,'completed',1,NULL,NULL,'2026-04-06 19:39:18','2026-04-06 19:39:43'),(8,1,2,435.0000000000000000,500.0000000000000000,0.0000000000000000,'cash',364.0000000000000000,NULL,0.0000000000000000,71.0000000000000000,364.0000000000000000,0.0000000000000000,435.0000000000000000,'completed',1,NULL,NULL,'2026-04-06 19:44:31','2026-04-06 19:44:54'),(9,1,2,285.0000000000000000,500.0000000000000000,0.0000000000000000,'cash',364.0000000000000000,NULL,150.0000000000000000,71.0000000000000000,364.0000000000000000,0.0000000000000000,285.0000000000000000,'void',1,NULL,NULL,'2026-04-06 19:53:20','2026-04-06 20:11:57'),(10,1,2,285.0000000000000000,300.0000000000000000,15.0000000000000000,'cash',285.0000000000000000,NULL,0.0000000000000000,0.0000000000000000,285.0000000000000000,0.0000000000000000,285.0000000000000000,'void',1,'test',NULL,'2026-04-06 20:02:28','2026-04-06 20:11:49'),(11,1,2,186.0000000000000000,1000.0000000000000000,814.0000000000000000,'cash',186.0000000000000000,NULL,0.0000000000000000,0.0000000000000000,186.0000000000000000,0.0000000000000000,186.0000000000000000,'completed',0,NULL,NULL,'2026-04-07 15:36:07','2026-04-07 15:36:07'),(12,1,2,0.0000000000000000,0.0000000000000000,0.0000000000000000,'free',0.0000000000000000,NULL,0.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'completed',0,NULL,NULL,'2026-04-07 15:37:10','2026-04-07 15:37:10'),(13,1,2,0.0000000000000000,0.0000000000000000,0.0000000000000000,'free',0.0000000000000000,NULL,0.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,0.0000000000000000,'completed',0,NULL,NULL,'2026-04-12 17:07:47','2026-04-12 17:07:47'),(14,1,2,150.0000000000000000,200.0000000000000000,50.0000000000000000,'cash',150.0000000000000000,NULL,0.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,150.0000000000000000,'completed',0,NULL,NULL,'2026-04-13 09:19:27','2026-04-13 09:19:27'),(15,1,2,150.0000000000000000,150.0000000000000000,0.0000000000000000,'paymaya',150.0000000000000000,NULL,0.0000000000000000,0.0000000000000000,150.0000000000000000,0.0000000000000000,150.0000000000000000,'completed',0,NULL,NULL,'2026-04-13 09:20:09','2026-04-13 09:20:09'),(16,1,2,210.0000000000000000,1000.0000000000000000,790.0000000000000000,'cash',210.0000000000000000,NULL,0.0000000000000000,0.0000000000000000,210.0000000000000000,0.0000000000000000,210.0000000000000000,'completed',0,NULL,NULL,'2026-04-13 09:22:43','2026-04-13 09:22:43'),(17,1,2,255.0000000000000000,1000.0000000000000000,745.0000000000000000,'cash',255.0000000000000000,NULL,0.0000000000000000,0.0000000000000000,255.0000000000000000,0.0000000000000000,255.0000000000000000,'completed',0,NULL,NULL,'2026-04-13 09:23:02','2026-04-13 09:23:02'),(18,1,2,184.0000000000000000,184.0000000000000000,0.0000000000000000,'cash',184.0000000000000000,NULL,0.0000000000000000,0.0000000000000000,184.0000000000000000,0.0000000000000000,184.0000000000000000,'completed',0,'aa','w','2026-04-13 18:24:35','2026-04-13 18:29:25'),(19,1,2,178.0000000000000000,NULL,NULL,'cash',0.0000000000000000,NULL,0.0000000000000000,0.0000000000000000,NULL,0.0000000000000000,178.0000000000000000,'pending',0,'test','w','2026-04-13 18:30:01','2026-04-13 18:30:01'),(20,4,6,2.0000000000000000,2.0000000000000000,0.0000000000000000,'gcash',2.0000000000000000,NULL,0.0000000000000000,0.0000000000000000,2.0000000000000000,0.0000000000000000,2.0000000000000000,'completed',0,NULL,NULL,'2026-04-15 22:19:05','2026-04-15 22:19:05'),(21,2,4,80.0000000000000000,100.0000000000000000,20.0000000000000000,'split',80.0000000000000000,'[{\"method\":\"cash\",\"amount\":50},{\"method\":\"gcash\",\"amount\":50}]',0.0000000000000000,0.0000000000000000,80.0000000000000000,0.0000000000000000,80.0000000000000000,'completed',0,NULL,NULL,'2026-04-16 05:18:28','2026-04-16 05:18:28'),(22,2,4,35.0000000000000000,35.0000000000000000,0.0000000000000000,'cash',35.0000000000000000,NULL,0.0000000000000000,0.0000000000000000,35.0000000000000000,0.0000000000000000,35.0000000000000000,'completed',0,NULL,NULL,'2026-04-16 07:11:11','2026-04-16 07:11:11'),(23,2,4,25.0000000000000000,25.0000000000000000,0.0000000000000000,'online_banking',25.0000000000000000,NULL,0.0000000000000000,0.0000000000000000,25.0000000000000000,0.0000000000000000,25.0000000000000000,'completed',0,NULL,NULL,'2026-04-16 08:13:23','2026-04-16 08:13:23');

--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned DEFAULT NULL,
  `module_permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cashier',
  `staff_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full_time',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `day_rate` decimal(16,4) NOT NULL DEFAULT '350.0000',
  `folding_fee_per_load` decimal(16,4) NOT NULL DEFAULT '10.0000',
  `overtime_rate_per_hour` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `work_days_csv` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1,2,3,4,5,6,7',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_tenant_id_role_index` (`tenant_id`,`role`),
  CONSTRAINT `users_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_chk_1` CHECK (json_valid(`module_permissions`))
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

INSERT IGNORE INTO `users` VALUES (1,NULL,NULL,'Platform Owner','mtech1897@gmail.com','super_admin','full_time',NULL,'$2a$12$1LIVblCbb9dxeKjKiZPP0eMa5xqpf6awLlCI2Wdg3Zxa5R4nGhZB.',NULL,'2026-03-26 05:52:32','2026-03-26 05:52:32',350.0000,10.0000,0.0000,'1,2,3,4,5,6,7'),(2,1,NULL,'Store Admin','admin@demo.store','tenant_admin','full_time',NULL,'$2y$12$Zj/vlfMT699BxACLoxzNfOJFiYFxEpznOKRqb3G1Q18g83rVHNjea','fOdp6TCZzR9bhZFr5j9bW1GOVUI7cYSXXurzpKPllhzVtOCR3m73hy3bUeNq','2026-03-26 05:52:33','2026-03-26 05:52:33',350.0000,10.0000,0.0000,'1,2,3,4,5,6,7'),(3,1,'[\"pos\", \"transactions\", \"activity_logs\", \"notifications\"]','Cashier One','cashier@demo.store','cashier','full_time',NULL,'$2y$12$DaJ9oRLF2d414MCssm3Cy.UG2iY6ziRP3X9h6nZCIDKmhgVEH17Wm',NULL,'2026-03-26 05:52:33','2026-04-20 17:55:23',350.0000,10.0000,50.0000,'1,2,3,4,5,6'),(4,2,NULL,'Maricris Gulle','maricrisgulle@gmail.com','tenant_admin','full_time','2026-03-28 20:11:20','$2y$10$FHP.Hj2X5RysoXPRA0nsTOQe11g71Tdu87ePoOxsGAUNdXw.qWU46',NULL,'2026-03-28 20:11:20','2026-03-28 20:11:20',350.0000,10.0000,0.0000,'1,2,3,4,5,6,7'),(6,4,NULL,'Marvin Pardillo Gulle','gullemarvin@gmail.com','tenant_admin','full_time','2026-04-15 22:09:01','$2y$10$ZCQpxnabNHavMLcDSvcpEelU2p/Wr7nhUDOkJSQ1GmvVe74oJcfTe',NULL,'2026-04-15 22:09:01','2026-04-15 22:09:01',350.0000,10.0000,0.0000,'1,2,3,4,5,6,7'),(7,1,'[]','Driver','driver@demo.store','cashier','driver','2026-04-20 17:06:30','$2y$10$XjXeFSKfgdx.3xmIhZ9mYuZUqRZj4ZfxE6q9q6Rb1n/ilxroxJqJO',NULL,'2026-04-20 17:06:30','2026-04-20 17:47:41',350.0000,0.0000,0.0000,'1,2,3,4,5,6,7'),(8,5,NULL,'Marvin Pardillo Gulle','test@gmail.com','tenant_admin','full_time','2026-04-21 08:24:44','$2y$10$Pt.Nro959VHjhiuY4bE7ferBjKkbrxuQZvO7PnaXjZdsfSToHV5Mi',NULL,'2026-04-21 08:24:44','2026-04-21 08:24:44',350.0000,10.0000,0.0000,'1,2,3,4,5,6,7'),(9,6,NULL,'test1@gmail.com','test1@gmail.com','tenant_admin','full_time','2026-04-21 09:16:41','$2y$10$zePc4curh7qyOBLoC0RZBe.N5wA59kPLVngcd8nThAdvhBwiEkMYK',NULL,'2026-04-21 09:16:41','2026-04-21 09:16:41',350.0000,10.0000,0.0000,'1,2,3,4,5,6,7');

--
-- Dumping events for database 'laundry_system'
--

--
-- Dumping routines for database 'laundry_system'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-21 20:50:58
