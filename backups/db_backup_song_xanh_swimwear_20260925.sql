-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: localhost    Database: song_xanh_swimwear
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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_admin_user_id_foreign` (`admin_user_id`),
  KEY `activity_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `activity_logs_action_created_at_index` (`action`,`created_at`),
  CONSTRAINT `activity_logs_admin_user_id_foreign` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,30,'site_settings.updated','App\\Models\\SiteSetting',1,'Cập nhật thông tin hiển thị của website.',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-09-04 18:47:20','2026-09-04 18:47:20'),(2,30,'site_settings.updated','App\\Models\\SiteSetting',1,'Cập nhật thông tin hiển thị của website.',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-09-04 18:47:36','2026-09-04 18:47:36'),(3,30,'order.status_updated','App\\Models\\Order',7,'Cập nhật trạng thái đơn VB-MA2Q4H2 từ pending sang confirmed.','{\"old_status\":\"pending\",\"new_status\":\"confirmed\",\"ghn_order_code\":\"L8K3XE\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-09-04 19:24:36','2026-09-04 19:24:36'),(4,30,'order.ghn_synced','App\\Models\\Order',7,'Đồng bộ vận đơn GHN cho đơn VB-MA2Q4H2.','{\"ghn_status\":\"ready_to_pick\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-09-04 19:24:39','2026-09-04 19:24:39'),(5,30,'order.status_updated','App\\Models\\Order',7,'Cập nhật trạng thái đơn VB-MA2Q4H2 từ confirmed sang completed.','{\"old_status\":\"confirmed\",\"new_status\":\"completed\",\"ghn_order_code\":null}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-09-04 19:28:13','2026-09-04 19:28:13'),(6,30,'order.ghn_synced','App\\Models\\Order',7,'Đồng bộ vận đơn GHN cho đơn VB-MA2Q4H2.','{\"ghn_status\":\"ready_to_pick\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-09-04 19:28:14','2026-09-04 19:28:14'),(7,30,'order.ghn_synced','App\\Models\\Order',7,'Đồng bộ vận đơn GHN cho đơn VB-MA2Q4H2.','{\"ghn_status\":\"ready_to_pick\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-09-04 19:28:27','2026-09-04 19:28:27'),(8,30,'order.ghn_synced','App\\Models\\Order',7,'Đồng bộ vận đơn GHN cho đơn VB-MA2Q4H2.','{\"ghn_status\":\"ready_to_pick\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-09-04 19:28:33','2026-09-04 19:28:33'),(9,30,'order.status_updated','App\\Models\\Order',8,'Cập nhật trạng thái đơn VB-NYZC6LE từ pending sang confirmed.','{\"old_status\":\"pending\",\"new_status\":\"confirmed\",\"ghn_order_code\":\"L8K3YL\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Safari/605.1.15','2026-09-05 06:50:36','2026-09-05 06:50:36'),(10,30,'order.ghn_synced','App\\Models\\Order',8,'Đồng bộ vận đơn GHN cho đơn VB-NYZC6LE.','{\"ghn_status\":\"ready_to_pick\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Safari/605.1.15','2026-09-05 06:50:38','2026-09-05 06:50:38'),(11,30,'order.ghn_synced','App\\Models\\Order',7,'Đồng bộ vận đơn GHN cho đơn VB-MA2Q4H2.','{\"ghn_status\":\"ready_to_pick\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Safari/605.1.15','2026-09-05 06:51:03','2026-09-05 06:51:03'),(12,30,'return_request.updated','App\\Models\\ReturnRequest',1,'Cập nhật yêu cầu đổi trả #1 sang trạng thái approved.','{\"action\":\"approve\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Safari/605.1.15','2026-09-05 06:55:28','2026-09-05 06:55:28'),(13,30,'product.created','App\\Models\\Product',28,'Tạo sản phẩm: Test',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-06 09:49:43','2026-09-06 09:49:43'),(14,30,'product.updated','App\\Models\\Product',28,'Cập nhật sản phẩm: Test',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-06 09:49:55','2026-09-06 09:49:55'),(15,30,'coupon.created','App\\Models\\Coupon',1,'Tạo mã ưu đãi TEST01.',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-06 09:52:06','2026-09-06 09:52:06'),(16,30,'coupon.updated','App\\Models\\Coupon',1,'Cập nhật mã ưu đãi TEST01.',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-06 09:52:34','2026-09-06 09:52:34'),(17,30,'coupon.updated','App\\Models\\Coupon',1,'Cập nhật mã ưu đãi TEST01.',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-06 09:56:46','2026-09-06 09:56:46'),(18,30,'coupon.updated','App\\Models\\Coupon',1,'Cập nhật mã ưu đãi TEST01.',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-06 19:19:45','2026-09-06 19:19:45'),(19,30,'order.ghn_synced','App\\Models\\Order',8,'Đồng bộ vận đơn GHN cho đơn VB-NYZC6LE.','{\"ghn_status\":\"ready_to_pick\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-11 00:57:11','2026-09-11 00:57:11'),(20,30,'order.status_updated','App\\Models\\Order',22,'Cập nhật trạng thái đơn VB-RK8ECLZ từ pending sang confirmed.','{\"old_status\":\"pending\",\"new_status\":\"confirmed\",\"ghn_order_code\":\"L8W8YF\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-11 01:12:18','2026-09-11 01:12:18'),(21,30,'order.status_updated','App\\Models\\Order',21,'Cập nhật trạng thái đơn VB-5OBTJOU từ pending sang confirmed.','{\"old_status\":\"pending\",\"new_status\":\"confirmed\",\"ghn_order_code\":\"L8W8Y7\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-11 01:12:26','2026-09-11 01:12:26'),(22,30,'order.status_updated','App\\Models\\Order',22,'Cập nhật trạng thái đơn VB-RK8ECLZ từ confirmed sang shipping.','{\"old_status\":\"confirmed\",\"new_status\":\"shipping\",\"ghn_order_code\":null}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-11 01:12:37','2026-09-11 01:12:37'),(23,30,'order.status_updated','App\\Models\\Order',22,'Cập nhật trạng thái đơn VB-RK8ECLZ từ shipping sang completed.','{\"old_status\":\"shipping\",\"new_status\":\"completed\",\"ghn_order_code\":null}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-11 01:12:40','2026-09-11 01:12:40'),(24,30,'order.ghn_synced','App\\Models\\Order',22,'Đồng bộ vận đơn GHN cho đơn VB-RK8ECLZ.','{\"ghn_status\":\"ready_to_pick\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-11 01:12:44','2026-09-11 01:12:44'),(25,30,'product.updated','App\\Models\\Product',17,'Cập nhật sản phẩm: Quần bơi nam cao cấp đen phối sóng',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-11 01:46:37','2026-09-11 01:46:37'),(26,30,'order.status_updated','App\\Models\\Order',24,'Cập nhật trạng thái đơn VB-IMA3OZL từ pending sang confirmed.','{\"old_status\":\"pending\",\"new_status\":\"confirmed\",\"ghn_order_code\":\"L8WRQ3\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-11 01:50:45','2026-09-11 01:50:45'),(27,30,'order.status_updated','App\\Models\\Order',26,'Cập nhật trạng thái đơn VB-V3VDFEY từ pending sang confirmed.','{\"old_status\":\"pending\",\"new_status\":\"confirmed\",\"ghn_order_code\":\"L8XUB9\"}','127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Safari/605.1.15','2026-09-25 01:37:17','2026-09-25 01:37:17'),(28,30,'payment.manual_reconciled','App\\Models\\Order',26,'Đối soát giao dịch MoMo đã hủy cho đơn VB-V3VDFEY.','{\"from\":\"pending\",\"to\":\"failed\",\"reason\":\"user_confirmed_cancelled_no_success_evidence\"}',NULL,NULL,'2026-09-25 01:48:06','2026-09-25 01:48:06');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('vua-beach-cache-20f093f57333776f3e3886ac4a69feef','i:1;',1790308852),('vua-beach-cache-20f093f57333776f3e3886ac4a69feef:timer','i:1790308852;',1790308852),('vua-beach-cache-22d200f8670dbdb3e253a90eee5098477c95c23d','i:1;',1790308876),('vua-beach-cache-22d200f8670dbdb3e253a90eee5098477c95c23d:timer','i:1790308876;',1790308876),('vua-beach-cache-5696cb0a09ed2d5fdc602f10842aec0e','i:1;',1790308852),('vua-beach-cache-5696cb0a09ed2d5fdc602f10842aec0e:timer','i:1790308852;',1790308852),('vua-beach-cache-health:runtime:queue:heartbeat','i:1790309491;',1790309851),('vua-beach-cache-health:runtime:scheduler:heartbeat','i:1790309460;',1790309820);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (16,'Đồ Bơi','do-boi','Thoáng mát','/images/catalog/categories/do-boi.webp','2026-08-20 17:53:38','2026-09-06 17:07:33'),(17,'Đồ bơi nữ','do-boi-nu','Bikini, đồ bơi liền thân và các thiết kế kín đáo dành cho nữ.','/images/catalog/categories/do-boi-nu.webp','2026-08-28 00:28:44','2026-09-06 17:07:42'),(18,'Đồ bơi nam','do-boi-nam','Quần bơi và trang phục bơi năng động dành cho nam.','/images/catalog/categories/do-boi-nam.webp','2026-08-28 00:28:44','2026-09-06 17:07:42'),(19,'Đồ bơi trẻ em','do-boi-tre-em','Trang phục bơi thoải mái, nhiều màu sắc dành cho bé.','/images/catalog/categories/do-boi-tre-em.webp','2026-08-28 00:28:44','2026-09-06 17:07:42'),(20,'Phụ kiện bơi','phu-kien-boi','Kính bơi, mũ bơi và phụ kiện cần thiết cho chuyến đi biển.','/images/vua-beach-hero-v2.webp','2026-08-28 00:28:44','2026-09-06 17:07:56');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `category_coupon`
--

DROP TABLE IF EXISTS `category_coupon`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category_coupon` (
  `coupon_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`coupon_id`,`category_id`),
  KEY `category_coupon_category_id_foreign` (`category_id`),
  CONSTRAINT `category_coupon_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `category_coupon_coupon_id_foreign` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category_coupon`
--

LOCK TABLES `category_coupon` WRITE;
/*!40000 ALTER TABLE `category_coupon` DISABLE KEYS */;
/*!40000 ALTER TABLE `category_coupon` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupon_product`
--

DROP TABLE IF EXISTS `coupon_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupon_product` (
  `coupon_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`coupon_id`,`product_id`),
  KEY `coupon_product_product_id_foreign` (`product_id`),
  CONSTRAINT `coupon_product_coupon_id_foreign` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `coupon_product_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupon_product`
--

LOCK TABLES `coupon_product` WRITE;
/*!40000 ALTER TABLE `coupon_product` DISABLE KEYS */;
/*!40000 ALTER TABLE `coupon_product` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupon_usages`
--

DROP TABLE IF EXISTS `coupon_usages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupon_usages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupon_usages_order_id_unique` (`order_id`),
  KEY `coupon_usages_user_id_foreign` (`user_id`),
  KEY `coupon_usages_coupon_id_user_id_index` (`coupon_id`,`user_id`),
  CONSTRAINT `coupon_usages_coupon_id_foreign` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `coupon_usages_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `coupon_usages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupon_usages`
--

LOCK TABLES `coupon_usages` WRITE;
/*!40000 ALTER TABLE `coupon_usages` DISABLE KEYS */;
INSERT INTO `coupon_usages` VALUES (1,1,30,14,'2026-09-06 09:52:50','2026-09-06 09:52:50'),(2,1,30,15,'2026-09-06 09:56:53','2026-09-06 09:56:53'),(3,1,30,16,'2026-09-06 09:58:28','2026-09-06 09:58:28');
/*!40000 ALTER TABLE `coupon_usages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(60) NOT NULL,
  `name` varchar(150) NOT NULL,
  `scope` varchar(20) NOT NULL DEFAULT 'all',
  `type` enum('percent','fixed') NOT NULL,
  `value` int(10) unsigned NOT NULL,
  `min_order_amount` int(10) unsigned NOT NULL DEFAULT 0,
  `max_discount_amount` int(10) unsigned DEFAULT NULL,
  `usage_limit` int(10) unsigned DEFAULT NULL,
  `per_user_limit` int(10) unsigned DEFAULT NULL,
  `used_count` int(10) unsigned NOT NULL DEFAULT 0,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
INSERT INTO `coupons` VALUES (1,'TEST01','TEST','all','fixed',70000,1000,1000000,10,10,3,'2026-09-06 09:51:00','2026-09-07 09:52:00',1,'2026-09-06 09:52:06','2026-09-06 09:58:28');
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exception_incidents`
--

DROP TABLE IF EXISTS `exception_incidents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exception_incidents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fingerprint` char(64) NOT NULL,
  `exception_class` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `route` varchar(255) DEFAULT NULL,
  `environment` varchar(32) NOT NULL,
  `occurrences` bigint(20) unsigned NOT NULL DEFAULT 1,
  `first_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_alerted_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exception_incidents_fingerprint_unique` (`fingerprint`),
  KEY `exception_incidents_last_seen_at_index` (`last_seen_at`),
  KEY `exception_incidents_resolved_at_index` (`resolved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exception_incidents`
--

LOCK TABLES `exception_incidents` WRITE;
/*!40000 ALTER TABLE `exception_incidents` DISABLE KEYS */;
/*!40000 ALTER TABLE `exception_incidents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_movements`
--

DROP TABLE IF EXISTS `inventory_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_variant_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `purchase_receipt_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('in','out') NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `balance_after` int(10) unsigned NOT NULL,
  `reason` varchar(100) NOT NULL,
  `idempotency_key` varchar(191) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_movements_idempotency_key_unique` (`idempotency_key`),
  KEY `inventory_movements_order_id_foreign` (`order_id`),
  KEY `inventory_movements_product_variant_id_created_at_index` (`product_variant_id`,`created_at`),
  KEY `inventory_movements_purchase_receipt_id_foreign` (`purchase_receipt_id`),
  CONSTRAINT `inventory_movements_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_movements_purchase_receipt_id_foreign` FOREIGN KEY (`purchase_receipt_id`) REFERENCES `purchase_receipts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_movements`
--

LOCK TABLES `inventory_movements` WRITE;
/*!40000 ALTER TABLE `inventory_movements` DISABLE KEYS */;
INSERT INTO `inventory_movements` VALUES (1,12,NULL,NULL,'in',99,99,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(2,13,NULL,NULL,'in',93,93,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(3,14,NULL,NULL,'in',99,99,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(4,15,NULL,NULL,'in',11,11,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(5,16,NULL,NULL,'in',12,12,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(6,17,NULL,NULL,'in',13,13,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(7,18,NULL,NULL,'in',99,99,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(8,19,NULL,NULL,'in',8,8,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(9,20,NULL,NULL,'in',9,9,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(10,21,NULL,NULL,'in',10,10,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(11,22,NULL,NULL,'in',12,12,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(12,23,NULL,NULL,'in',13,13,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(13,24,NULL,NULL,'in',14,14,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(14,25,NULL,NULL,'in',14,14,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(15,26,NULL,NULL,'in',15,15,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(16,27,NULL,NULL,'in',16,16,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(17,28,NULL,NULL,'in',10,10,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(18,29,NULL,NULL,'in',11,11,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(19,30,NULL,NULL,'in',12,12,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(20,31,NULL,NULL,'in',13,13,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(21,32,NULL,NULL,'in',14,14,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(22,33,NULL,NULL,'in',15,15,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(23,34,NULL,NULL,'in',17,17,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(24,35,NULL,NULL,'in',18,18,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(25,36,NULL,NULL,'in',19,19,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(26,37,NULL,NULL,'in',18,18,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(27,38,NULL,NULL,'in',19,19,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(28,39,NULL,NULL,'in',20,20,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(29,40,NULL,NULL,'in',9,9,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(30,41,NULL,NULL,'in',10,10,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(31,42,NULL,NULL,'in',11,11,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(32,43,NULL,NULL,'in',15,15,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(33,44,NULL,NULL,'in',16,16,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(34,45,NULL,NULL,'in',17,17,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(35,46,NULL,NULL,'in',16,16,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(36,47,NULL,NULL,'in',17,17,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(37,48,NULL,NULL,'in',18,18,'initial_catalog_restore',NULL,'Khôi phục tồn đầu kỳ từ bản sao lưu catalog ngày 04/09/2026','2026-09-04 18:34:40','2026-09-04 18:34:40'),(64,12,7,NULL,'out',1,98,'order_placed',NULL,'Đặt đơn VB-MA2Q4H2','2026-09-04 19:07:27','2026-09-04 19:07:27'),(65,42,8,NULL,'out',1,10,'order_placed',NULL,'Đặt đơn VB-NYZC6LE','2026-09-04 19:44:35','2026-09-04 19:44:35'),(66,47,8,NULL,'out',1,16,'order_placed',NULL,'Đặt đơn VB-NYZC6LE','2026-09-04 19:44:35','2026-09-04 19:44:35'),(67,48,8,NULL,'out',1,17,'order_placed',NULL,'Đặt đơn VB-NYZC6LE','2026-09-04 19:44:35','2026-09-04 19:44:35'),(68,19,9,NULL,'out',1,7,'order_placed',NULL,'Đặt đơn VB-9C3VMKK','2026-09-06 09:29:03','2026-09-06 09:29:03'),(69,19,9,NULL,'in',1,8,'momo_payment_creation_failed',NULL,'Hoàn tồn kho do không tạo được giao dịch MoMo: VB-9C3VMKK','2026-09-06 09:29:04','2026-09-06 09:29:04'),(70,19,10,NULL,'out',1,7,'order_placed',NULL,'Đặt đơn VB-HWIHJHG','2026-09-06 09:30:56','2026-09-06 09:30:56'),(71,19,10,NULL,'in',1,8,'momo_payment_failed',NULL,'Hoàn tồn kho do MoMo hủy: VB-HWIHJHG','2026-09-06 09:33:47','2026-09-06 09:33:47'),(72,47,11,NULL,'out',1,15,'order_placed',NULL,'Đặt đơn VB-VOCNYDZ','2026-09-06 09:35:05','2026-09-06 09:35:05'),(73,47,11,NULL,'in',1,16,'momo_payment_failed',NULL,'Hoàn tồn kho do MoMo không thành công: VB-VOCNYDZ','2026-09-06 09:35:14','2026-09-06 09:35:14'),(74,44,12,NULL,'out',1,15,'order_placed',NULL,'Đặt đơn VB-P0NXZTP','2026-09-06 09:40:10','2026-09-06 09:40:10'),(75,44,12,NULL,'in',1,16,'order_cancelled_by_customer',NULL,'Khách hủy đơn VB-P0NXZTP','2026-09-06 09:40:19','2026-09-06 09:40:19'),(76,47,13,NULL,'out',1,15,'order_placed',NULL,'Đặt đơn VB-PBEX1FE','2026-09-06 09:41:15','2026-09-06 09:41:15'),(77,47,13,NULL,'in',1,16,'momo_payment_failed',NULL,'Hoàn tồn kho do MoMo không thành công: VB-PBEX1FE','2026-09-06 09:41:34','2026-09-06 09:41:34'),(78,49,NULL,NULL,'in',10,10,'manual_adjustment',NULL,'Điều chỉnh tồn kho từ quản trị','2026-09-06 09:49:55','2026-09-06 09:49:55'),(79,49,14,NULL,'out',1,9,'order_placed',NULL,'Đặt đơn VB-UD1LZ1T','2026-09-06 09:52:50','2026-09-06 09:52:50'),(80,49,14,NULL,'in',1,10,'momo_payment_creation_failed',NULL,'Hoàn tồn kho do không tạo được giao dịch MoMo: VB-UD1LZ1T','2026-09-06 09:52:50','2026-09-06 09:52:50'),(81,49,15,NULL,'out',2,8,'order_placed',NULL,'Đặt đơn VB-KEEVDVQ','2026-09-06 09:56:53','2026-09-06 09:56:53'),(82,49,15,NULL,'in',2,10,'momo_payment_creation_failed',NULL,'Hoàn tồn kho do không tạo được giao dịch MoMo: VB-KEEVDVQ','2026-09-06 09:56:54','2026-09-06 09:56:54'),(83,49,16,NULL,'out',2,8,'order_placed',NULL,'Đặt đơn VB-TP4BSYP','2026-09-06 09:58:28','2026-09-06 09:58:28'),(84,49,16,NULL,'in',2,10,'momo_payment_failed',NULL,'Hoàn tồn kho do MoMo không thành công: VB-TP4BSYP','2026-09-06 09:58:37','2026-09-06 09:58:37'),(130,19,18,NULL,'out',1,7,'order_placed','order:18:variant:19:reserve','Đặt đơn VB-AC4W23H','2026-09-11 00:47:33','2026-09-11 00:47:33'),(131,34,18,NULL,'out',1,16,'order_placed','order:18:variant:34:reserve','Đặt đơn VB-AC4W23H','2026-09-11 00:47:33','2026-09-11 00:47:33'),(132,19,19,NULL,'out',1,6,'order_placed','order:19:variant:19:reserve','Đặt đơn VB-9HRCYLP','2026-09-11 00:55:49','2026-09-11 00:55:49'),(133,19,20,NULL,'out',1,5,'order_placed','order:20:variant:19:reserve','Đặt đơn VB-AVVEBF3','2026-09-11 01:03:01','2026-09-11 01:03:01'),(134,19,21,NULL,'out',1,4,'order_placed','order:21:variant:19:reserve','Đặt đơn VB-5OBTJOU','2026-09-11 01:06:44','2026-09-11 01:06:44'),(135,40,22,NULL,'out',1,8,'order_placed','order:22:variant:40:reserve','Đặt đơn VB-RK8ECLZ','2026-09-11 01:07:16','2026-09-11 01:07:16'),(136,64,NULL,NULL,'in',10,10,'initial_stock',NULL,'Tạo biến thể từ quản trị','2026-09-11 01:46:37','2026-09-11 01:46:37'),(137,34,23,NULL,'out',1,15,'order_placed','order:23:variant:34:reserve','Đặt đơn VB-GHT6OMV','2026-09-11 01:49:33','2026-09-11 01:49:33'),(138,46,23,NULL,'out',1,15,'order_placed','order:23:variant:46:reserve','Đặt đơn VB-GHT6OMV','2026-09-11 01:49:33','2026-09-11 01:49:33'),(139,34,24,NULL,'out',1,14,'order_placed','order:24:variant:34:reserve','Đặt đơn VB-IMA3OZL','2026-09-11 01:50:07','2026-09-11 01:50:07'),(140,64,24,NULL,'out',1,9,'order_placed','order:24:variant:64:reserve','Đặt đơn VB-IMA3OZL','2026-09-11 01:50:07','2026-09-11 01:50:07'),(141,12,25,NULL,'out',1,97,'order_placed','order:25:variant:12:reserve','Đặt đơn VB-NXXBEVW','2026-09-11 02:07:36','2026-09-11 02:07:36'),(142,47,26,NULL,'out',1,15,'order_placed','order:26:variant:47:reserve','Đặt đơn VB-V3VDFEY','2026-09-11 02:10:24','2026-09-11 02:10:24');
/*!40000 ALTER TABLE `inventory_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_08_14_000100_add_store_fields_to_users_table',1),(5,'2026_08_14_000200_create_categories_table',1),(6,'2026_08_14_000300_create_products_table',1),(7,'2026_08_14_000400_create_product_variants_table',1),(8,'2026_08_14_000500_create_orders_table',1),(9,'2026_08_14_000600_create_order_items_table',1),(10,'2026_08_21_000700_add_username_to_users_table',1),(11,'2026_08_21_000800_create_shipping_addresses_table',1),(12,'2026_08_28_000100_create_site_settings_table',1),(13,'2026_08_28_000200_add_product_variant_id_to_order_items_table',1),(14,'2026_09_04_000300_add_shipping_details_to_orders_table',1),(15,'2026_09_04_000400_add_ghn_locations_to_shipping_addresses_table',1),(16,'2026_09_04_000500_add_product_image_url_to_order_items_table',1),(17,'2026_09_04_000600_create_product_images_table',1),(18,'2026_09_04_000700_add_sku_and_low_stock_to_product_variants_table',1),(19,'2026_09_04_000800_create_inventory_movements_table',1),(20,'2026_09_04_000900_add_vnpay_fields_to_orders_table',1),(21,'2026_09_04_001000_add_unique_color_size_to_product_variants_table',2),(22,'2026_09_04_001100_add_is_active_to_product_variants_table',3),(23,'2026_09_04_001200_create_coupons_table',4),(24,'2026_09_04_001300_add_coupon_fields_to_orders_table',4),(25,'2026_09_04_001400_create_order_status_histories_table',4),(26,'2026_09_04_001500_add_completed_at_to_orders_table',5),(27,'2026_09_04_001600_create_return_requests_table',5),(28,'2026_09_04_001700_create_return_request_items_table',5),(29,'2026_09_04_001800_create_return_request_histories_table',5),(30,'2026_09_04_001900_backfill_completed_at_for_completed_orders',6),(31,'2026_09_04_002000_create_suppliers_table',7),(32,'2026_09_04_002100_create_purchase_receipts_tables',8),(33,'2026_09_04_002200_add_purchase_receipt_to_inventory_movements_table',8),(34,'2026_09_04_002300_add_targeting_to_coupons',9),(35,'2026_09_04_002400_create_wishlists_and_product_reviews_tables',10),(36,'2026_09_04_002500_create_activity_logs_table',11),(37,'2026_09_06_000100_add_momo_fields_to_orders_table',12),(38,'2026_09_06_000200_add_idempotency_to_inventory_movements',13),(39,'2026_09_06_000300_create_webhook_receipts_table',13),(40,'2026_09_06_000400_add_refund_tracking',13),(41,'2026_09_06_000500_add_two_factor_to_users',14),(42,'2026_09_07_000100_normalize_shipping_states',15),(43,'2026_09_12_000100_create_payment_transactions_table',16),(44,'2026_09_12_210000_create_exception_incidents_table',17);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `product_variant_id` bigint(20) unsigned DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_image_url` varchar(2048) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `size` varchar(10) DEFAULT NULL,
  `price` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `subtotal` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  KEY `order_items_product_id_foreign` (`product_id`),
  KEY `order_items_product_variant_id_foreign` (`product_variant_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (4,7,15,12,'Bikini','https://doboihuongdiep.vn/media/product/1850_jps08643.jpg','Đỏ','S',800000,1,800000,'2026-09-04 19:07:27','2026-09-04 19:07:27'),(5,8,20,42,'Đồ bơi cộc tay liền thân quần short đen','/images/catalog/products/do-boi-lien-than-short-den.jpg','Đen','L',419000,1,419000,'2026-09-04 19:44:35','2026-09-04 19:44:35'),(6,8,18,47,'Quần bơi nam cao cấp đen phối xanh','/images/catalog/products/quan-boi-nam-den-phoi-xanh.jpg','Đen phối xanh','L',479000,1,479000,'2026-09-04 19:44:35','2026-09-04 19:44:35'),(7,8,18,48,'Quần bơi nam cao cấp đen phối xanh','/images/catalog/products/quan-boi-nam-den-phoi-xanh.jpg','Đen phối xanh','XL',479000,1,479000,'2026-09-04 19:44:35','2026-09-04 19:44:35'),(8,9,19,19,'Bộ bơi dài tay hồng phối chân váy đen','/images/catalog/products/bo-boi-hong-chan-vay.jpg','Hồng phối đen','S',540000,1,540000,'2026-09-06 09:29:03','2026-09-06 09:29:03'),(9,10,19,19,'Bộ bơi dài tay hồng phối chân váy đen','/images/catalog/products/bo-boi-hong-chan-vay.jpg','Hồng phối đen','S',540000,1,540000,'2026-09-06 09:30:56','2026-09-06 09:30:56'),(10,11,18,47,'Quần bơi nam cao cấp đen phối xanh','/images/catalog/products/quan-boi-nam-den-phoi-xanh.jpg','Đen phối xanh','L',479000,1,479000,'2026-09-06 09:35:05','2026-09-06 09:35:05'),(11,12,17,44,'Quần bơi nam cao cấp đen phối sóng','/images/catalog/products/quan-boi-nam-den-phoi-song.jpg','Đen phối sóng','L',520000,1,520000,'2026-09-06 09:40:10','2026-09-06 09:40:10'),(12,13,18,47,'Quần bơi nam cao cấp đen phối xanh','/images/catalog/products/quan-boi-nam-den-phoi-xanh.jpg','Đen phối xanh','L',479000,1,479000,'2026-09-06 09:41:15','2026-09-06 09:41:15'),(13,14,28,49,'Test',NULL,'Vàng','S',9999,1,9999,'2026-09-06 09:52:50','2026-09-06 09:52:50'),(14,15,28,49,'Test',NULL,'Vàng','S',9999,2,19998,'2026-09-06 09:56:53','2026-09-06 09:56:53'),(15,16,28,49,'Test',NULL,'Vàng','S',9999,2,19998,'2026-09-06 09:58:28','2026-09-06 09:58:28'),(17,18,19,19,'Bộ bơi dài tay hồng phối chân váy đen','/images/catalog/products/bo-boi-hong-chan-vay.webp','Hồng phối đen','S',540000,1,540000,'2026-09-11 00:47:33','2026-09-11 00:47:33'),(18,18,26,34,'Bộ rời bé trai tay dài kèm mũ','/images/catalog/products/bo-roi-be-trai-tay-dai-kem-mu.webp','Xanh','4',300000,1,300000,'2026-09-11 00:47:33','2026-09-11 00:47:33'),(19,19,19,19,'Bộ bơi dài tay hồng phối chân váy đen','/images/catalog/products/bo-boi-hong-chan-vay.webp','Hồng phối đen','S',540000,1,540000,'2026-09-11 00:55:49','2026-09-11 00:55:49'),(20,20,19,19,'Bộ bơi dài tay hồng phối chân váy đen','/images/catalog/products/bo-boi-hong-chan-vay.webp','Hồng phối đen','S',540000,1,540000,'2026-09-11 01:03:01','2026-09-11 01:03:01'),(21,21,19,19,'Bộ bơi dài tay hồng phối chân váy đen','/images/catalog/products/bo-boi-hong-chan-vay.webp','Hồng phối đen','S',540000,1,540000,'2026-09-11 01:06:44','2026-09-11 01:06:44'),(22,22,20,40,'Đồ bơi cộc tay liền thân quần short đen','/images/catalog/products/do-boi-lien-than-short-den.webp','Đen','S',419000,1,419000,'2026-09-11 01:07:16','2026-09-11 01:07:16'),(23,23,26,34,'Bộ rời bé trai tay dài kèm mũ','/images/catalog/products/bo-roi-be-trai-tay-dai-kem-mu.webp','Xanh','4',300000,1,300000,'2026-09-11 01:49:33','2026-09-11 01:49:33'),(24,23,18,46,'Quần bơi nam cao cấp đen phối xanh','/images/catalog/products/quan-boi-nam-den-phoi-xanh.webp','Đen phối xanh','M',479000,1,479000,'2026-09-11 01:49:33','2026-09-11 01:49:33'),(25,24,26,34,'Bộ rời bé trai tay dài kèm mũ','/images/catalog/products/bo-roi-be-trai-tay-dai-kem-mu.webp','Xanh','4',300000,1,300000,'2026-09-11 01:50:07','2026-09-11 01:50:07'),(26,24,17,64,'Quần bơi nam cao cấp đen phối sóng','/images/catalog/products/quan-boi-nam-den-phoi-song.webp','Đen','L',520000,1,520000,'2026-09-11 01:50:07','2026-09-11 01:50:07'),(27,25,15,12,'Bikini','/images/catalog/products/bikini.webp','Đỏ','S',800000,1,800000,'2026-09-11 02:07:36','2026-09-11 02:07:36'),(28,26,18,47,'Quần bơi nam cao cấp đen phối xanh','/images/catalog/products/quan-boi-nam-den-phoi-xanh.webp','Đen phối xanh','L',479000,1,479000,'2026-09-11 02:10:24','2026-09-11 02:10:24');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_status_histories`
--

DROP TABLE IF EXISTS `order_status_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_status_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `status` varchar(30) NOT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'system',
  `changed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_status_histories_changed_by_user_id_foreign` (`changed_by_user_id`),
  KEY `order_status_histories_order_id_created_at_index` (`order_id`,`created_at`),
  CONSTRAINT `order_status_histories_changed_by_user_id_foreign` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_status_histories_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_status_histories`
--

LOCK TABLES `order_status_histories` WRITE;
/*!40000 ALTER TABLE `order_status_histories` DISABLE KEYS */;
INSERT INTO `order_status_histories` VALUES (1,7,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-04 19:07:27','2026-09-04 19:07:27'),(2,7,'confirmed','admin',30,'Đã xác nhận đơn và tạo vận đơn GHN L8K3XE.','2026-09-04 19:24:36','2026-09-04 19:24:36'),(3,7,'completed','admin',30,'Quản trị viên cập nhật trạng thái đơn.','2026-09-04 19:28:13','2026-09-04 19:28:13'),(4,8,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-04 19:44:35','2026-09-04 19:44:35'),(5,8,'confirmed','admin',30,'Đã xác nhận đơn và tạo vận đơn GHN L8K3YL.','2026-09-05 06:50:36','2026-09-05 06:50:36'),(6,9,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-06 09:29:03','2026-09-06 09:29:03'),(7,9,'cancelled','momo',NULL,'Không thể tạo giao dịch MoMo.','2026-09-06 09:29:04','2026-09-06 09:29:04'),(8,10,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-06 09:30:56','2026-09-06 09:30:56'),(9,10,'cancelled','momo',NULL,'MoMo xác nhận khách hàng đã hủy giao dịch.','2026-09-06 09:33:47','2026-09-06 09:33:47'),(10,11,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-06 09:35:05','2026-09-06 09:35:05'),(11,11,'cancelled','momo',NULL,'MoMo phản hồi giao dịch không thành công.','2026-09-06 09:35:14','2026-09-06 09:35:14'),(12,12,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-06 09:40:10','2026-09-06 09:40:10'),(13,12,'cancelled','customer',30,'Khách hàng đã hủy đơn khi đang chờ xác nhận.','2026-09-06 09:40:19','2026-09-06 09:40:19'),(14,13,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-06 09:41:15','2026-09-06 09:41:15'),(15,13,'cancelled','momo',NULL,'MoMo phản hồi giao dịch không thành công.','2026-09-06 09:41:34','2026-09-06 09:41:34'),(16,14,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-06 09:52:50','2026-09-06 09:52:50'),(17,14,'cancelled','momo',NULL,'Không thể tạo giao dịch MoMo.','2026-09-06 09:52:50','2026-09-06 09:52:50'),(18,15,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-06 09:56:53','2026-09-06 09:56:53'),(19,15,'cancelled','momo',NULL,'Không thể tạo giao dịch MoMo.','2026-09-06 09:56:54','2026-09-06 09:56:54'),(20,16,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-06 09:58:28','2026-09-06 09:58:28'),(21,16,'cancelled','momo',NULL,'MoMo phản hồi giao dịch không thành công.','2026-09-06 09:58:37','2026-09-06 09:58:37'),(22,18,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-11 00:47:33','2026-09-11 00:47:33'),(23,19,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-11 00:55:49','2026-09-11 00:55:49'),(24,20,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-11 01:03:01','2026-09-11 01:03:01'),(25,21,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-11 01:06:44','2026-09-11 01:06:44'),(26,22,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-11 01:07:16','2026-09-11 01:07:16'),(27,22,'confirmed','admin',30,'Đã xác nhận đơn và tạo vận đơn GHN L8W8YF.','2026-09-11 01:12:18','2026-09-11 01:12:18'),(28,21,'confirmed','admin',30,'Đã xác nhận đơn và tạo vận đơn GHN L8W8Y7.','2026-09-11 01:12:26','2026-09-11 01:12:26'),(29,22,'shipping','admin',30,'Quản trị viên cập nhật trạng thái đơn.','2026-09-11 01:12:37','2026-09-11 01:12:37'),(30,22,'completed','admin',30,'Quản trị viên cập nhật trạng thái đơn.','2026-09-11 01:12:40','2026-09-11 01:12:40'),(31,23,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-11 01:49:33','2026-09-11 01:49:33'),(32,24,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-11 01:50:07','2026-09-11 01:50:07'),(33,24,'confirmed','admin',30,'Đã xác nhận đơn và tạo vận đơn GHN L8WRQ3.','2026-09-11 01:50:45','2026-09-11 01:50:45'),(34,25,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-11 02:07:36','2026-09-11 02:07:36'),(35,26,'pending','customer',30,'Khách hàng đã đặt đơn.','2026-09-11 02:10:24','2026-09-11 02:10:24'),(36,26,'confirmed','admin',30,'Đã xác nhận đơn và tạo vận đơn GHN L8XUB9.','2026-09-25 01:37:17','2026-09-25 01:37:17');
/*!40000 ALTER TABLE `order_status_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `order_code` varchar(255) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `to_district_id` int(10) unsigned DEFAULT NULL,
  `to_ward_code` varchar(20) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `coupon_code` varchar(60) DEFAULT NULL,
  `total_amount` int(10) unsigned NOT NULL,
  `shipping_fee` int(10) unsigned NOT NULL DEFAULT 0,
  `subtotal_amount` int(10) unsigned NOT NULL DEFAULT 0,
  `discount_amount` int(10) unsigned NOT NULL DEFAULT 0,
  `refunded_amount` int(10) unsigned NOT NULL DEFAULT 0,
  `payment_method` varchar(255) NOT NULL DEFAULT 'cod',
  `payment_status` varchar(255) NOT NULL DEFAULT 'unpaid',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `shipping_status` varchar(255) NOT NULL DEFAULT 'pending',
  `ghn_order_code` varchar(255) DEFAULT NULL,
  `vnpay_transaction_no` varchar(255) DEFAULT NULL,
  `vnpay_bank_code` varchar(255) DEFAULT NULL,
  `vnpay_response_code` varchar(10) DEFAULT NULL,
  `vnpay_paid_at` timestamp NULL DEFAULT NULL,
  `momo_request_id` varchar(255) DEFAULT NULL,
  `momo_trans_id` varchar(255) DEFAULT NULL,
  `momo_result_code` varchar(10) DEFAULT NULL,
  `momo_payment_status` varchar(30) DEFAULT NULL,
  `momo_paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_code_unique` (`order_code`),
  UNIQUE KEY `orders_vnpay_transaction_no_unique` (`vnpay_transaction_no`),
  UNIQUE KEY `orders_momo_request_id_unique` (`momo_request_id`),
  UNIQUE KEY `orders_momo_trans_id_unique` (`momo_trans_id`),
  KEY `orders_user_id_foreign` (`user_id`),
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (7,30,'VB-MA2Q4H2','Quản trị viên','admin@doboi.test','0900000000','Hà Nội',1485,'1A0608',NULL,NULL,831900,31900,800000,0,0,'vnpay','paid','completed','2026-09-04 19:28:13','delivered','L8K3XE','15673403','NCB','00','2026-09-04 19:17:42',NULL,NULL,NULL,NULL,NULL,'2026-09-04 19:07:27','2026-09-06 12:49:14'),(8,30,'VB-NYZC6LE','Quản trị viên','admin@doboi.test','0989666666','La Xa',1765,'180212',NULL,NULL,1448500,71500,1377000,0,0,'vnpay','paid','confirmed',NULL,'ready_to_pick','L8K3YL','15673411','NCB','00','2026-09-04 19:45:45',NULL,NULL,NULL,NULL,NULL,'2026-09-04 19:44:35','2026-09-05 06:50:36'),(9,30,'VB-9C3VMKK','Quản trị viên','luongchienhieplch@gmail.com','0989666666','La Xa',1765,'180212',NULL,NULL,611500,71500,540000,0,0,'momo','unpaid','cancelled',NULL,'cancelled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'creation_failed',NULL,'2026-09-06 09:29:03','2026-09-06 09:29:04'),(10,30,'VB-HWIHJHG','Quản trị viên','luongchienhieplch@gmail.com','0989666666','La Xa',1765,'180212',NULL,NULL,611500,71500,540000,0,0,'momo','unpaid','cancelled',NULL,'cancelled',NULL,NULL,NULL,NULL,NULL,'55993ad6-776a-4425-baa4-7a315996bcc5',NULL,'1006','failed',NULL,'2026-09-06 09:30:56','2026-09-06 09:33:47'),(11,30,'VB-VOCNYDZ','Quản trị viên','luongchienhieplch@gmail.com','0989666666','La Xa',1765,'180212',NULL,NULL,550500,71500,479000,0,0,'momo','unpaid','cancelled',NULL,'cancelled',NULL,NULL,NULL,NULL,NULL,'2b43b90b-1bb1-4390-88f9-b0dd71dcac90',NULL,'1006','failed',NULL,'2026-09-06 09:35:05','2026-09-06 09:35:14'),(12,30,'VB-P0NXZTP','Quản trị viên','luongchienhieplch@gmail.com','0989666666','La Xa',1765,'180212',NULL,NULL,591500,71500,520000,0,0,'cod','unpaid','cancelled',NULL,'cancelled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-06 09:40:10','2026-09-06 09:40:19'),(13,30,'VB-PBEX1FE','Quản trị viên','luongchienhieplch@gmail.com','0989666666','La Xa',1765,'180212',NULL,NULL,550500,71500,479000,0,0,'momo','unpaid','cancelled',NULL,'cancelled',NULL,NULL,NULL,NULL,NULL,'6ec4b62d-4d7e-42a2-8792-dd493a0ea4a2',NULL,'1006','failed',NULL,'2026-09-06 09:41:15','2026-09-06 09:41:34'),(14,30,'VB-UD1LZ1T','Quản trị viên','luongchienhieplch@gmail.com','0989666666','La Xa',1765,'180212',NULL,'TEST01',71500,71500,9999,9999,0,'momo','unpaid','cancelled',NULL,'cancelled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'creation_failed',NULL,'2026-09-06 09:52:50','2026-09-06 09:52:50'),(15,30,'VB-KEEVDVQ','Lương Chiến Hiệp','luongchienhieplch@gmail.com','0989666666','Phú Diễn',1482,'11007',NULL,'TEST01',42900,42900,19998,19998,0,'momo','unpaid','cancelled',NULL,'cancelled',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'creation_failed',NULL,'2026-09-06 09:56:53','2026-09-06 09:56:54'),(16,30,'VB-TP4BSYP','Lương Chiến Hiệp','luongchienhieplch@gmail.com','0989666666','Phú Diễn',1482,'11007',NULL,'TEST01',42900,42900,19998,19998,0,'momo','unpaid','cancelled',NULL,'cancelled',NULL,NULL,NULL,NULL,NULL,'ca588935-4597-439f-89e1-d153bc623246',NULL,'1006','failed',NULL,'2026-09-06 09:58:28','2026-09-06 09:58:37'),(18,30,'VB-AC4W23H','Lương Chiến Hiệp','2311060276@hunre.edu.vn','0989666666','Phú Diễn',1482,'11007',NULL,NULL,882900,42900,840000,0,0,'vnpay','pending','pending',NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-11 00:47:33','2026-09-11 00:47:33'),(19,30,'VB-9HRCYLP','Lương Chiến Hiệp','2311060276@hunre.edu.vn','0989666666','Phú Diễn',1482,'11007',NULL,NULL,582900,42900,540000,0,0,'vnpay','pending','pending',NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-11 00:55:49','2026-09-11 00:55:49'),(20,30,'VB-AVVEBF3','Lương Chiến Hiệp','2311060276@hunre.edu.vn','0989666666','Phú Diễn',1482,'11007',NULL,NULL,582900,42900,540000,0,0,'momo','pending','pending',NULL,'pending',NULL,NULL,NULL,NULL,NULL,'6cc0f2da-c150-493c-acf8-de551c882257',NULL,NULL,'pending',NULL,'2026-09-11 01:03:01','2026-09-11 01:03:02'),(21,30,'VB-5OBTJOU','Lương Chiến Hiệp','2311060276@hunre.edu.vn','0989666666','Phú Diễn',1482,'11007',NULL,NULL,582900,42900,540000,0,0,'momo','pending','confirmed',NULL,'ready_to_pick','L8W8Y7',NULL,NULL,NULL,NULL,'3c415371-1dd3-45cf-a967-a915045cc40e',NULL,NULL,'pending',NULL,'2026-09-11 01:06:44','2026-09-11 01:12:26'),(22,30,'VB-RK8ECLZ','Lương Chiến Hiệp','2311060276@hunre.edu.vn','0989666666','Phú Diễn',1482,'11007',NULL,NULL,461900,42900,419000,0,0,'vnpay','pending','completed','2026-09-11 01:12:40','delivered','L8W8YF',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-11 01:07:16','2026-09-11 01:12:40'),(23,30,'VB-GHT6OMV','Lương Chiến Hiệp','2311060276@hunre.edu.vn','0989666666','Phú Diễn',1482,'11007',NULL,NULL,821900,42900,779000,0,0,'momo','pending','pending',NULL,'pending',NULL,NULL,NULL,NULL,NULL,'33da8dd6-6fe9-48d2-8c25-8fea105d58ec',NULL,NULL,'pending',NULL,'2026-09-11 01:49:33','2026-09-11 01:49:33'),(24,30,'VB-IMA3OZL','Lương Chiến Hiệp','2311060276@hunre.edu.vn','0989666666','Phú Diễn',1482,'11007',NULL,NULL,862900,42900,820000,0,0,'vnpay','pending','confirmed',NULL,'ready_to_pick','L8WRQ3',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-11 01:50:07','2026-09-11 01:50:45'),(25,30,'VB-NXXBEVW','Lương Chiến Hiệp','2311060276@hunre.edu.vn','0989666666','Phú Diễn',1482,'11007',NULL,NULL,842900,42900,800000,0,0,'momo','pending','pending',NULL,'pending',NULL,NULL,NULL,NULL,NULL,'e74c7ca2-b267-4722-b3cf-4495878c3571',NULL,NULL,'pending',NULL,'2026-09-11 02:07:36','2026-09-11 02:07:36'),(26,30,'VB-V3VDFEY','Lương Chiến Hiệp','2311060276@hunre.edu.vn','0989666666','Phú Diễn',1482,'11007',NULL,NULL,521900,42900,479000,0,0,'momo','failed','confirmed',NULL,'ready_to_pick','L8XUB9',NULL,NULL,NULL,NULL,'ce2e127d-9c39-46b6-b173-efb9b8de4c8e',NULL,NULL,'failed',NULL,'2026-09-11 02:10:24','2026-09-25 01:48:06');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_transactions`
--

DROP TABLE IF EXISTS `payment_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `parent_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(30) NOT NULL DEFAULT 'payment',
  `gateway` varchar(30) NOT NULL,
  `gateway_order_id` varchar(120) DEFAULT NULL,
  `gateway_request_id` varchar(120) DEFAULT NULL,
  `transaction_id` varchar(120) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `result_code` int(11) DEFAULT NULL,
  `response_code` varchar(30) DEFAULT NULL,
  `bank_code` varchar(50) DEFAULT NULL,
  `message` varchar(500) DEFAULT NULL,
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_payload`)),
  `response_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_payload`)),
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_transactions_gateway_gateway_order_id_unique` (`gateway`,`gateway_order_id`),
  KEY `payment_transactions_parent_transaction_id_foreign` (`parent_transaction_id`),
  KEY `payment_transactions_gateway_transaction_id_index` (`gateway`,`transaction_id`),
  KEY `payment_transactions_order_id_status_index` (`order_id`,`status`),
  KEY `payment_transactions_order_id_gateway_created_at_index` (`order_id`,`gateway`,`created_at`),
  KEY `payment_transactions_gateway_request_id_index` (`gateway_request_id`),
  CONSTRAINT `payment_transactions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_transactions_parent_transaction_id_foreign` FOREIGN KEY (`parent_transaction_id`) REFERENCES `payment_transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_transactions`
--

LOCK TABLES `payment_transactions` WRITE;
/*!40000 ALTER TABLE `payment_transactions` DISABLE KEYS */;
INSERT INTO `payment_transactions` VALUES (1,7,NULL,'payment','vnpay','VB-MA2Q4H2',NULL,'15673403',831900.00,'paid',NULL,'00','NCB','Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,'2026-09-04 19:17:42','2026-09-04 19:07:27','2026-09-06 12:49:14'),(2,8,NULL,'payment','vnpay','VB-NYZC6LE',NULL,'15673411',1448500.00,'paid',NULL,'00','NCB','Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,'2026-09-04 19:45:45','2026-09-04 19:44:35','2026-09-05 06:50:36'),(3,9,NULL,'payment','momo','VB-9C3VMKK',NULL,NULL,611500.00,'pending',NULL,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-06 09:29:03','2026-09-06 09:29:04'),(4,10,NULL,'payment','momo','VB-HWIHJHG','55993ad6-776a-4425-baa4-7a315996bcc5',NULL,611500.00,'pending',1006,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-06 09:30:56','2026-09-06 09:33:47'),(5,11,NULL,'payment','momo','VB-VOCNYDZ','2b43b90b-1bb1-4390-88f9-b0dd71dcac90',NULL,550500.00,'pending',1006,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-06 09:35:05','2026-09-06 09:35:14'),(6,12,NULL,'payment','cod','VB-P0NXZTP',NULL,NULL,591500.00,'pending',NULL,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-06 09:40:10','2026-09-06 09:40:19'),(7,13,NULL,'payment','momo','VB-PBEX1FE','6ec4b62d-4d7e-42a2-8792-dd493a0ea4a2',NULL,550500.00,'pending',1006,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-06 09:41:15','2026-09-06 09:41:34'),(8,14,NULL,'payment','momo','VB-UD1LZ1T',NULL,NULL,71500.00,'pending',NULL,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-06 09:52:50','2026-09-06 09:52:50'),(9,15,NULL,'payment','momo','VB-KEEVDVQ',NULL,NULL,42900.00,'pending',NULL,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-06 09:56:53','2026-09-06 09:56:54'),(10,16,NULL,'payment','momo','VB-TP4BSYP','ca588935-4597-439f-89e1-d153bc623246',NULL,42900.00,'pending',1006,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-06 09:58:28','2026-09-06 09:58:37'),(11,18,NULL,'payment','vnpay','VB-AC4W23H',NULL,NULL,882900.00,'pending',NULL,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-11 00:47:33','2026-09-11 00:47:33'),(12,19,NULL,'payment','vnpay','VB-9HRCYLP',NULL,NULL,582900.00,'pending',NULL,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-11 00:55:49','2026-09-11 00:55:49'),(13,20,NULL,'payment','momo','VB-AVVEBF3','6cc0f2da-c150-493c-acf8-de551c882257',NULL,582900.00,'pending',NULL,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-11 01:03:01','2026-09-11 01:03:02'),(14,21,NULL,'payment','momo','VB-5OBTJOU','3c415371-1dd3-45cf-a967-a915045cc40e',NULL,582900.00,'pending',NULL,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-11 01:06:44','2026-09-11 01:12:26'),(15,22,NULL,'payment','vnpay','VB-RK8ECLZ',NULL,NULL,461900.00,'pending',NULL,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-11 01:07:16','2026-09-11 01:12:40'),(16,23,NULL,'payment','momo','VB-GHT6OMV','33da8dd6-6fe9-48d2-8c25-8fea105d58ec',NULL,821900.00,'pending',NULL,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-11 01:49:33','2026-09-11 01:49:33'),(17,24,NULL,'payment','vnpay','VB-IMA3OZL',NULL,NULL,862900.00,'pending',NULL,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-11 01:50:07','2026-09-11 01:50:45'),(18,25,NULL,'payment','momo','VB-NXXBEVW','e74c7ca2-b267-4722-b3cf-4495878c3571',NULL,842900.00,'pending',NULL,NULL,NULL,'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',NULL,NULL,NULL,'2026-09-11 02:07:36','2026-09-11 02:07:36'),(19,26,NULL,'payment','momo','VB-V3VDFEY','ce2e127d-9c39-46b6-b173-efb9b8de4c8e',NULL,521900.00,'failed',NULL,NULL,NULL,'Đối soát thủ công: người dùng xác nhận đã hủy thanh toán MoMo; không có IPN hay mã giao dịch thành công.',NULL,NULL,NULL,'2026-09-11 02:10:24','2026-09-25 01:48:06');
/*!40000 ALTER TABLE `payment_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `path` varchar(2048) NOT NULL,
  `is_cover` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_images_product_id_foreign` (`product_id`),
  CONSTRAINT `product_images_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_reviews`
--

DROP TABLE IF EXISTS `product_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `content` text DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_reviews_user_id_product_id_order_id_unique` (`user_id`,`product_id`,`order_id`),
  KEY `product_reviews_order_id_foreign` (`order_id`),
  KEY `product_reviews_product_id_is_visible_index` (`product_id`,`is_visible`),
  CONSTRAINT `product_reviews_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_reviews`
--

LOCK TABLES `product_reviews` WRITE;
/*!40000 ALTER TABLE `product_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_variants`
--

DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_variants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `sku` varchar(80) DEFAULT NULL,
  `color` varchar(50) NOT NULL,
  `size` varchar(10) NOT NULL,
  `stock` int(10) unsigned NOT NULL DEFAULT 0,
  `low_stock_threshold` int(10) unsigned NOT NULL DEFAULT 5,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_variant_color_size_unique` (`product_id`,`color`,`size`),
  UNIQUE KEY `product_variants_sku_unique` (`sku`),
  CONSTRAINT `product_variants_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_variants`
--

LOCK TABLES `product_variants` WRITE;
/*!40000 ALTER TABLE `product_variants` DISABLE KEYS */;
INSERT INTO `product_variants` VALUES (12,15,'VB-P0015-V0032','Đỏ','S',97,5,1,'2026-08-27 23:57:08','2026-09-11 02:07:36'),(13,15,'VB-P0015-V0033','Hồng','L',93,5,1,'2026-08-27 23:57:08','2026-08-27 23:57:08'),(14,15,'VB-P0015-V0034','Xanh dương','M',99,5,1,'2026-08-27 23:57:08','2026-08-27 23:57:08'),(15,22,'VB-P0022-V0044','Họa tiết nhiệt đới','S',11,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(16,22,'VB-P0022-V0045','Họa tiết nhiệt đới','M',12,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(17,22,'VB-P0022-V0046','Họa tiết nhiệt đới','L',13,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(18,16,'VB-P0016-V0031','Hồng','L',99,5,1,'2026-08-27 23:14:31','2026-08-27 23:14:31'),(19,19,'VB-P0019-V0035','Hồng phối đen','S',4,5,1,'2026-08-28 00:28:44','2026-09-11 01:06:44'),(20,19,'VB-P0019-V0036','Hồng phối đen','M',9,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(21,19,'VB-P0019-V0037','Hồng phối đen','L',10,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(22,23,'VB-P0023-V0047','Xanh phối trắng','S',12,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(23,23,'VB-P0023-V0048','Xanh phối trắng','M',13,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(24,23,'VB-P0023-V0049','Xanh phối trắng','L',14,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(25,25,'VB-P0025-V0053','Đen','S',14,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(26,25,'VB-P0025-V0054','Đen','M',15,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(27,25,'VB-P0025-V0055','Đen','L',16,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(28,21,'VB-P0021-V0041','Kem','S',10,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(29,21,'VB-P0021-V0042','Kem','M',11,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(30,21,'VB-P0021-V0043','Kem','L',12,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(31,24,'VB-P0024-V0050','Đen phối ghi','S',13,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(32,24,'VB-P0024-V0051','Đen phối ghi','M',14,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(33,24,'VB-P0024-V0052','Đen phối ghi','L',15,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(34,26,'VB-P0026-V0062','Xanh','4',14,5,1,'2026-08-28 00:45:36','2026-09-11 01:50:07'),(35,26,'VB-P0026-V0063','Xanh','6',18,5,1,'2026-08-28 00:45:36','2026-08-28 00:45:36'),(36,26,'VB-P0026-V0064','Xanh','8',19,5,1,'2026-08-28 00:45:36','2026-08-28 00:45:36'),(37,27,'VB-P0027-V0065','Xanh phối cam','4',18,5,1,'2026-08-28 00:45:36','2026-08-28 00:45:36'),(38,27,'VB-P0027-V0066','Xanh phối cam','6',19,5,1,'2026-08-28 00:45:36','2026-08-28 00:45:36'),(39,27,'VB-P0027-V0067','Xanh phối cam','8',20,5,1,'2026-08-28 00:45:36','2026-08-28 00:45:36'),(40,20,'VB-P0020-V0038','Đen','S',8,5,1,'2026-08-28 00:28:44','2026-09-11 01:07:16'),(41,20,'VB-P0020-V0039','Đen','M',10,5,1,'2026-08-28 00:28:44','2026-08-28 00:28:44'),(42,20,'VB-P0020-V0040','Đen','L',10,5,1,'2026-08-28 00:28:44','2026-09-04 19:44:35'),(43,17,'VB-P0017-V0056','Đen phối sóng','M',15,5,1,'2026-08-28 00:45:36','2026-09-11 01:46:37'),(44,17,'VB-P0017-V0057','Đen phối sóng','L',16,5,1,'2026-08-28 00:45:36','2026-09-11 01:46:37'),(45,17,'VB-P0017-V0058','Đen phối sóng','XL',17,5,1,'2026-08-28 00:45:36','2026-09-11 01:46:37'),(46,18,'VB-P0018-V0059','Đen phối xanh','M',15,5,1,'2026-08-28 00:45:36','2026-09-11 01:49:33'),(47,18,'VB-P0018-V0060','Đen phối xanh','L',15,5,1,'2026-08-28 00:45:36','2026-09-11 02:10:24'),(48,18,'VB-P0018-V0061','Đen phối xanh','XL',17,5,1,'2026-08-28 00:45:36','2026-09-04 19:44:35'),(49,28,'VB-28-VANG-S','Vàng','S',10,5,1,'2026-09-06 09:49:43','2026-09-06 09:58:37'),(64,17,'VB-17-DEN-L','Đen','L',9,5,1,'2026-09-11 01:46:37','2026-09-11 01:50:07');
/*!40000 ALTER TABLE `product_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `price` int(10) unsigned NOT NULL,
  `sale_price` int(10) unsigned DEFAULT NULL,
  `description` text NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug_unique` (`slug`),
  KEY `products_category_id_foreign` (`category_id`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (15,16,'Bikini','bikini',999999,800000,'Mát mẻ, thoáng khí','/images/catalog/products/bikini.webp',1,'active','2026-08-20 17:54:54','2026-09-06 17:07:33'),(16,16,'Bikini cạp thấp sắc màu','bikini-cap-thap-sac-mau',999999,666666,'Mùa hè là thời điểm để bạn tự tin khoe cá tính và tận hưởng những chuyến đi đầy cảm hứng. Mẫu bikini 2 mảnh họa tiết sắc màu với gam xanh, hồng và cam nổi bật sẽ giúp bạn trở thành tâm điểm ở mọi bãi biển hay hồ bơi.Thiết kế bikini 2 mảnh trẻ trung, tôn dángHọa tiết phối màu cá tính, nổi bật trong mọi khung hìnhChất liệu co giãn 4 chiều, mềm mại, nhanh khô, tạo cảm giác thoải mái suốt ngày dàiPhù hợp cho đi biển, hồ bơi, nghỉ dưỡng và những buổi chụp ảnh mùa hè','/images/catalog/products/bikini-cap-thap-sac-mau.webp',1,'active','2026-08-27 23:13:52','2026-09-06 17:07:33'),(17,18,'Quần bơi nam cao cấp đen phối sóng','quan-boi-nam-cao-cap-den-phoi-song',520000,NULL,'Quần bơi nam dáng thể thao, nền đen phối họa tiết sóng khỏe khoắn và chất liệu co giãn nhanh khô.','/images/catalog/products/quan-boi-nam-den-phoi-song.webp',1,'active','2026-08-28 00:45:36','2026-09-06 17:07:42'),(18,18,'Quần bơi nam cao cấp đen phối xanh','quan-boi-nam-cao-cap-den-phoi-xanh',520000,479000,'Thiết kế quần bơi nam đen phối xanh hiện đại, ôm vừa vặn và linh hoạt khi vận động dưới nước.','/images/catalog/products/quan-boi-nam-den-phoi-xanh.webp',1,'active','2026-08-28 00:45:36','2026-09-06 17:07:42'),(19,17,'Bộ bơi dài tay hồng phối chân váy đen','bo-boi-dai-tay-hong-phoi-chan-vay-den',540000,NULL,'Thiết kế dài tay kín đáo, khóa kéo tiện dụng và chân váy cạp cao giúp vận động thoải mái khi bơi hoặc đi biển.','/images/catalog/products/bo-boi-hong-chan-vay.webp',1,'active','2026-08-28 00:28:44','2026-09-06 17:07:42'),(20,17,'Đồ bơi cộc tay liền thân quần short đen','do-boi-coc-tay-lien-than-quan-short-den',450000,419000,'Dáng liền thân cộc tay kết hợp quần short thể thao, phù hợp học bơi, đi biển và các hoạt động ngoài trời.','/images/catalog/products/do-boi-lien-than-short-den.webp',1,'active','2026-08-28 00:28:44','2026-09-06 17:07:42'),(21,17,'Bộ bơi liền tay dài phối ren','bo-boi-lien-tay-dai-phoi-ren',520000,NULL,'Phom liền thân thanh lịch với phần tay ren nhẹ, tạo cảm giác kín đáo mà vẫn nữ tính.','/images/catalog/products/bo-lien-tay-dai-phoi-ren.webp',1,'active','2026-08-28 00:28:44','2026-09-06 17:07:42'),(22,17,'Bikini cạp cao quần váy họa tiết nhiệt đới','bikini-cap-cao-quan-vay-hoa-tiet-nhiet-doi',450000,399000,'Bikini cạp cao phối quần váy họa tiết rực rỡ, tôn dáng và phù hợp cho kỳ nghỉ mùa hè.','/images/catalog/products/bikini-cap-cao-nhiet-doi.webp',1,'active','2026-08-28 00:28:44','2026-09-06 17:07:42'),(23,17,'Bộ bơi dài tay quần đùi xanh phối trắng','bo-boi-dai-tay-quan-dui-xanh-phoi-trang',630000,NULL,'Bộ bơi dài tay thể thao với quần đùi hai lớp, co giãn tốt và thuận tiện cho các hoạt động dưới nước.','/images/catalog/products/bo-boi-xanh-phoi-trang.webp',1,'active','2026-08-28 00:28:44','2026-09-06 17:07:42'),(24,17,'Bộ bơi liền váy dài tay đen phối ghi','bo-boi-lien-vay-dai-tay-den-phoi-ghi',550000,499000,'Thiết kế liền váy dài tay gọn gàng, gam đen ghi dễ mặc và phù hợp nhiều vóc dáng.','/images/catalog/products/bo-lien-vay-den-phoi-ghi.webp',1,'active','2026-08-28 00:28:44','2026-09-06 17:07:42'),(25,17,'Bộ bơi liền tay dài đen tối giản','bo-boi-lien-tay-dai-den-toi-gian',480000,NULL,'Bộ bơi liền thân màu đen với đường cắt tối giản, nhanh khô và dễ phối cùng phụ kiện đi biển.','/images/catalog/products/bo-lien-tay-dai-den.webp',1,'active','2026-08-28 00:28:44','2026-09-06 17:07:42'),(26,19,'Bộ rời bé trai tay dài kèm mũ','bo-roi-be-trai-tay-dai-kem-mu',300000,NULL,'Bộ bơi tay dài dành cho bé trai đi kèm mũ, giúp che nắng tốt và thoải mái trong các buổi học bơi.','/images/catalog/products/bo-roi-be-trai-tay-dai-kem-mu.webp',1,'active','2026-08-28 00:45:36','2026-09-06 17:07:42'),(27,19,'Bộ rời bé trai xanh phối cam','bo-roi-be-trai-xanh-phoi-cam',250000,NULL,'Bộ bơi rời xanh phối cam nổi bật, chất vải mềm và co giãn để bé vui chơi dưới nước dễ dàng.','/images/catalog/products/bo-roi-be-trai-xanh-phoi-cam.webp',1,'active','2026-08-28 00:45:36','2026-09-06 17:07:42'),(28,16,'Test','test',10000,9999,'Test',NULL,0,'inactive','2026-09-06 09:49:43','2026-09-06 21:03:21');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_receipt_items`
--

DROP TABLE IF EXISTS `purchase_receipt_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_receipt_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_receipt_id` bigint(20) unsigned NOT NULL,
  `product_variant_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `unit_cost` bigint(20) unsigned NOT NULL DEFAULT 0,
  `line_total` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_variant_unique` (`purchase_receipt_id`,`product_variant_id`),
  KEY `purchase_receipt_items_product_variant_id_foreign` (`product_variant_id`),
  CONSTRAINT `purchase_receipt_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`),
  CONSTRAINT `purchase_receipt_items_purchase_receipt_id_foreign` FOREIGN KEY (`purchase_receipt_id`) REFERENCES `purchase_receipts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_receipt_items`
--

LOCK TABLES `purchase_receipt_items` WRITE;
/*!40000 ALTER TABLE `purchase_receipt_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_receipt_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_receipts`
--

DROP TABLE IF EXISTS `purchase_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `receipt_code` varchar(40) NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_cost` bigint(20) unsigned NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_receipts_receipt_code_unique` (`receipt_code`),
  KEY `purchase_receipts_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `purchase_receipts_supplier_id_received_at_index` (`supplier_id`,`received_at`),
  CONSTRAINT `purchase_receipts_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_receipts_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_receipts`
--

LOCK TABLES `purchase_receipts` WRITE;
/*!40000 ALTER TABLE `purchase_receipts` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_request_histories`
--

DROP TABLE IF EXISTS `return_request_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `return_request_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `return_request_id` bigint(20) unsigned NOT NULL,
  `status` varchar(30) NOT NULL,
  `source` varchar(30) NOT NULL,
  `changed_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `return_request_histories_changed_by_user_id_foreign` (`changed_by_user_id`),
  KEY `return_request_histories_return_request_id_created_at_index` (`return_request_id`,`created_at`),
  CONSTRAINT `return_request_histories_changed_by_user_id_foreign` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `return_request_histories_return_request_id_foreign` FOREIGN KEY (`return_request_id`) REFERENCES `return_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_request_histories`
--

LOCK TABLES `return_request_histories` WRITE;
/*!40000 ALTER TABLE `return_request_histories` DISABLE KEYS */;
INSERT INTO `return_request_histories` VALUES (1,1,'requested','customer',30,'Khách hàng đã gửi yêu cầu đổi trả.','2026-09-05 06:55:04','2026-09-05 06:55:04'),(2,1,'approved','admin',30,'Yêu cầu đã được duyệt.','2026-09-05 06:55:28','2026-09-05 06:55:28');
/*!40000 ALTER TABLE `return_request_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_request_items`
--

DROP TABLE IF EXISTS `return_request_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `return_request_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `return_request_id` bigint(20) unsigned NOT NULL,
  `order_item_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `desired_size` varchar(20) DEFAULT NULL,
  `replacement_variant_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_request_items_return_request_id_order_item_id_unique` (`return_request_id`,`order_item_id`),
  KEY `return_request_items_order_item_id_foreign` (`order_item_id`),
  KEY `return_request_items_replacement_variant_id_foreign` (`replacement_variant_id`),
  CONSTRAINT `return_request_items_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_request_items_replacement_variant_id_foreign` FOREIGN KEY (`replacement_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `return_request_items_return_request_id_foreign` FOREIGN KEY (`return_request_id`) REFERENCES `return_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_request_items`
--

LOCK TABLES `return_request_items` WRITE;
/*!40000 ALTER TABLE `return_request_items` DISABLE KEYS */;
INSERT INTO `return_request_items` VALUES (1,1,4,1,NULL,NULL,'2026-09-05 06:55:04','2026-09-05 06:55:04');
/*!40000 ALTER TABLE `return_request_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_requests`
--

DROP TABLE IF EXISTS `return_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `return_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` enum('refund','exchange') NOT NULL,
  `status` enum('requested','approved','rejected','received','completed','cancelled') NOT NULL DEFAULT 'requested',
  `reason` varchar(255) NOT NULL,
  `customer_note` text DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `refund_amount` int(10) unsigned NOT NULL DEFAULT 0,
  `refund_processed_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `return_requests_order_id_foreign` (`order_id`),
  KEY `return_requests_user_id_foreign` (`user_id`),
  KEY `return_requests_status_created_at_index` (`status`,`created_at`),
  CONSTRAINT `return_requests_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `return_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_requests`
--

LOCK TABLES `return_requests` WRITE;
/*!40000 ALTER TABLE `return_requests` DISABLE KEYS */;
INSERT INTO `return_requests` VALUES (1,7,30,'refund','approved','Không đúng mô tả','như rẻ rách',NULL,0,NULL,'2026-09-05 06:55:28',NULL,NULL,'2026-09-05 06:55:04','2026-09-05 06:55:28');
/*!40000 ALTER TABLE `return_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('algvZJweqBPLI1KPP0kLrICLiaK5BXvpCwNs1HT6',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/601.2.4 (KHTML, like Gecko) Version/9.0.1 Safari/601.2.4 facebookexternalhit/1.1 Facebot Twitterbot/1.0','eyJfdG9rZW4iOiJvaTN4bEt0eFgxazBMRmMxVGZhdjB6Q2x0SjVyOW51SnAzSEdqUk5xIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDBcL2FkbWluXC91c2VycyJ9LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDBcL2RhbmctbmhhcCIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==',1790309214),('ED2swVM3Myl7g9efHsf26oaIRqBjfPQDk40VrcAs',30,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Safari/605.1.15','eyJfdG9rZW4iOiI3OHFNczVvZUhMYWdJa2pXdXA5NkVCdjRabTRMcGpVMURqUEJ5ZDNSIiwidXJsIjpbXSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwXC9iYW8tbWF0XC94YWMtdGh1Yy1oYWktbG9wXC9raWVtLXRyYSIsInJvdXRlIjoidHdvLWZhY3Rvci5jaGFsbGVuZ2UifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6MzAsInBhc3N3b3JkX2hhc2hfd2ViIjoiOTAwMzE3MTA2NDBkN2U5NGI4ZDExY2QxN2ZmM2ZjZWMzMTdhMDFiZDQxZDhjMGIzYjU3ZjNmMjJlNTc5NzA5NyJ9',1790308792),('sjbvBuLeRoPljYAGlfG6SbsU1lUWP9kI7BtmdG8z',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/601.2.4 (KHTML, like Gecko) Version/9.0.1 Safari/601.2.4 facebookexternalhit/1.1 Facebot Twitterbot/1.0','eyJfdG9rZW4iOiJocXc3VzZxY25uVTBpRUdTdlc5TlMwWDhLSVV3UHlWdFBOOFZBNVJFIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvbG9jYWxob3N0OjgwMDBcL2FkbWluXC9yZXBvcnRzXC9jaGFydHMifSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwXC9kYW5nLW5oYXAiLCJyb3V0ZSI6ImxvZ2luIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1790309076),('zBvjrz9QttGf3KC0pIiQl7NiFpZPpgSbJPBBMlga',30,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/27.0 Safari/605.1.15','eyJfdG9rZW4iOiJ2VW9LaUl4blZWZHBDekJFSWRqOEk1dTE3Vkh0ZjY5TDFWeVRtaFZZIiwidXJsIjpbXSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwXC9hZG1pblwvcmVwb3J0cyIsInJvdXRlIjoiYWRtaW4ucmVwb3J0cy5pbmRleCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjozMCwicGFzc3dvcmRfaGFzaF93ZWIiOiI5MDAzMTcxMDY0MGQ3ZTk0YjhkMTFjZDE3ZmYzZmNlYzMxN2EwMWJkNDFkOGMwYjNiNTdmM2YyMmU1Nzk3MDk3IiwiYWRtaW5fMmZhX3ZlcmlmaWVkX2F0IjoxNzkwMzA4ODE4LCJhZG1pbl8yZmFfdXNlcl9pZCI6IjMwIiwiYWRtaW5fMmZhX2Vucm9sbG1lbnQiOiIxZGRjZjc1ODA0YmFkNjdjYmFlYmUwZWM3ZThjZGYzNWU0Nzg3NWIxMDBhMzYyNThkMTk4MWE0OTUzYmEzMTkxIn0=',1790309239);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipping_addresses`
--

DROP TABLE IF EXISTS `shipping_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shipping_addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `label` varchar(50) NOT NULL DEFAULT 'Nhà riêng',
  `recipient_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `province_id` int(10) unsigned DEFAULT NULL,
  `province_name` varchar(100) DEFAULT NULL,
  `district_id` int(10) unsigned DEFAULT NULL,
  `district_name` varchar(100) DEFAULT NULL,
  `ward_code` varchar(20) DEFAULT NULL,
  `ward_name` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shipping_addresses_user_id_foreign` (`user_id`),
  CONSTRAINT `shipping_addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipping_addresses`
--

LOCK TABLES `shipping_addresses` WRITE;
/*!40000 ALTER TABLE `shipping_addresses` DISABLE KEYS */;
INSERT INTO `shipping_addresses` VALUES (1,30,'Nhà riêng','Quản trị viên','0989666666','La Xa',248,'Bắc Giang',1765,'Huyện Yên Thế','180212','Xã Đồng Vương',0,'2026-09-04 19:25:39','2026-09-06 09:54:38'),(2,30,'Nhà riêng','Lương Chiến Hiệp','0989666666','Phú Diễn',201,'Hà Nội',1482,'Quận Bắc Từ Liêm','11007','Phường Phú Diễn',1,'2026-09-06 09:54:38','2026-09-06 09:54:38');
/*!40000 ALTER TABLE `shipping_addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `site_name` varchar(255) NOT NULL DEFAULT 'Vua Beach',
  `tagline` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `support_email` varchar(255) DEFAULT NULL,
  `support_phone` varchar(30) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES (1,'Vua Beach','Đồ bơi hiện đại cho mọi hành trình mùa hè.','site/vua-beach-logo-optimized.webp','sp.doitheauto5s@gmail.com','0998 999999','2026-09-04 13:51:49','2026-09-06 22:40:54');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `suppliers_is_active_name_index` (`is_active`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (30,'Lương Chiến Hiệp','admin','2311060276@hunre.edu.vn','0989666666','Hà Nội','2026-09-11 00:46:29','$2y$12$4abxTVSHYvzYDH9ffWYLg.IOylP.ikKPygaMEQfd24uZN1WtXNd6G',1,'5qtZpgZzfWlaz9Klas94CWUPWYBjfp3n6vyJT8MbtGvPDohyLx1wKkHg8B5w','eyJpdiI6ImQxNXhhT1RMekpzdEhBWFJkem9YWXc9PSIsInZhbHVlIjoiMktBcHFiRVZyM25LbHR5ajR6OFRBcWVwVTJsOFJiQmxzRDVtRGQ5L3ZHN1dIWXZTZDlZSlJRd3NrVUVLSG5IbCIsIm1hYyI6ImZjYzY0OTc5MmYxYzgzMjAwYWJlZjYzZDBmZjk0NDA4OTI3NGNkYzk0ODZkNzFmYWUwMmFhMTI3YTU5MDYwMGYiLCJ0YWciOiIifQ==','eyJpdiI6ImxqVm1jckxIQVhORUJESHgxTkl6VGc9PSIsInZhbHVlIjoiVGNQbFh6bWMva1ZpbkhsVytVb1hhQXE3clFiTWg3OGdWRjFzaW1pRUJLMTNCV1YwS0lpWTRRaGFMcDBUKytrTHN1RUtUaUwrOEs4N3hxaDRrS3BraktBdHlVbHRzTCtYSlRKM1hPeG9VbXZaT2NzRkEyb0ZpN1BGMkRJT2RqQVdlMjI1NWE1cUFVbU8xbGIrUkM3djBkME5DUzlabGcwdHJ0SVZlMzRaZ2NGMXpRNEdTZXhOY1NBWGdlcXdtZjhjRGlMSkg0N2U4a1U5Z1B0c1o2S3lrdEZxcEd5UVdPVm1TTVdBYitZRlkyS0U2bkRLTExUeWlHc1N2VUszbi9RckRyUnlIVUhBbjkwWVc2VkpxeVRGZWtVOU9RcWNzQmZyL2hTQW13TnRCNlNYQ2NUMkZUYnJBVkIzakNjcndJaXJJSHFwbVAwd1FHL3JXUkdUNmorTlAvcExtTmg5NTliWnpUdXVPYW1QSUJwL0VKVmhQYWZzQ0p6c0U5dm5BQVY3WVFqZnMxek5pOUtXMkUxaTIxSGxjUFNvVkloSzFEd0dzMkxJU0ZSeDU3U1J2YzI1VjBQU3FTdm15YVJEcDR3ZE9abHh0L2RWU3ZZOVUydDZKek9yY3dyNGMzVVQ4clJNY3FLVW8wQng2V3F4NTBNdUlHZUdyZGxiMEhSSmpmdWQ2bFVSWE9PeDc5OFU0cjNWRVBNaWxJTkdoMmVlcjQzVW9QRkFxK21NTmNHRGd6Q1pWdVpvVUhBeHp2dTlMc1pUQVB2Z0VDblpWdkhZZEFjSHNDUU1TTVFvYXJNWjRBQ1hTTzJNMXhPU3Ywanc2MURzbVowRTFYNFBWVjJvYXNwQWtxbFFPWHBXUUExTi8wZ1lUTmtaUm9KUytyd0VXejhPUlVhYnNMQ29lWTJMd2NtTFBYVkw5cHA5VVJRQ1BPdEQiLCJtYWMiOiI1ZWFjY2E3N2U1MmFjZTFjNmNlMWEzZmY2OThkYTRjNWQ4ODk0YjM0NDVlMzBjMjZjNzUxODZjODUzZTAyNTgyIiwidGFnIjoiIn0=','2026-09-06 17:39:03','2026-09-04 18:42:48','2026-09-25 01:32:21'),(31,'Chiến Hiệp Lương','clay','gagos99166@daugr.com','0877786341',NULL,NULL,'$2y$12$x0CsvvhcnDFB6CNV4jDNv.IfXixhnQqDphPybbI8VHIulbynUjnFm',0,NULL,NULL,NULL,NULL,'2026-09-04 19:50:39','2026-09-04 19:50:39');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `webhook_receipts`
--

DROP TABLE IF EXISTS `webhook_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(30) NOT NULL,
  `event_key` varchar(191) NOT NULL,
  `payload_hash` varchar(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'processing',
  `response_code` smallint(5) unsigned DEFAULT NULL,
  `response_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_body`)),
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 1,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `webhook_receipts_provider_event_key_unique` (`provider`,`event_key`),
  KEY `webhook_receipts_provider_status_created_at_index` (`provider`,`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `webhook_receipts`
--

LOCK TABLES `webhook_receipts` WRITE;
/*!40000 ALTER TABLE `webhook_receipts` DISABLE KEYS */;
/*!40000 ALTER TABLE `webhook_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wishlists`
--

DROP TABLE IF EXISTS `wishlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wishlists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wishlists_user_id_product_id_unique` (`user_id`,`product_id`),
  KEY `wishlists_product_id_foreign` (`product_id`),
  CONSTRAINT `wishlists_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlists_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlists`
--

LOCK TABLES `wishlists` WRITE;
/*!40000 ALTER TABLE `wishlists` DISABLE KEYS */;
/*!40000 ALTER TABLE `wishlists` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-25 11:11:32
