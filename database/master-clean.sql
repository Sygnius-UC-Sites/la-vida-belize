-- MySQL dump 10.13  Distrib 8.4.7-7, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: master_clean
-- ------------------------------------------------------
-- Server version	8.4.7-7

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
/*!50717 SELECT COUNT(*) INTO @rocksdb_has_p_s_session_variables FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'performance_schema' AND TABLE_NAME = 'session_variables' */;
/*!50717 SET @rocksdb_get_is_supported = IF (@rocksdb_has_p_s_session_variables, 'SELECT COUNT(*) INTO @rocksdb_is_supported FROM performance_schema.session_variables WHERE VARIABLE_NAME=\'rocksdb_bulk_load\'', 'SELECT 0') */;
/*!50717 PREPARE s FROM @rocksdb_get_is_supported */;
/*!50717 EXECUTE s */;
/*!50717 DEALLOCATE PREPARE s */;
/*!50717 SET @rocksdb_enable_bulk_load = IF (@rocksdb_is_supported, 'SET SESSION rocksdb_bulk_load = 1', 'SET @rocksdb_dummy_bulk_load = 0') */;
/*!50717 PREPARE s FROM @rocksdb_enable_bulk_load */;
/*!50717 EXECUTE s */;
/*!50717 DEALLOCATE PREPARE s */;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `batch_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attraction_categories`
--

DROP TABLE IF EXISTS `attraction_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attraction_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seo_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shortDescription` longtext COLLATE utf8mb4_unicode_ci,
  `mediumDescription` longtext COLLATE utf8mb4_unicode_ci,
  `longDescription` longtext COLLATE utf8mb4_unicode_ci,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` text COLLATE utf8mb4_unicode_ci,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `templete` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bannerImage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publish` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `footer_section` longtext COLLATE utf8mb4_unicode_ci,
  `header_section` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `attraction_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attraction_categories_seo_url_unique` (`seo_url`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attraction_categories`
--

LOCK TABLES `attraction_categories` WRITE;
/*!40000 ALTER TABLE `attraction_categories` DISABLE KEYS */;
-- [scrubbed clean clone] attraction_categories emptied
/*!40000 ALTER TABLE `attraction_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attractions`
--

DROP TABLE IF EXISTS `attractions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attractions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seo_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shortDescription` longtext COLLATE utf8mb4_unicode_ci,
  `mediumDescription` longtext COLLATE utf8mb4_unicode_ci,
  `longDescription` longtext COLLATE utf8mb4_unicode_ci,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` text COLLATE utf8mb4_unicode_ci,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `templete` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bannerImage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publish` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `footer_section` longtext COLLATE utf8mb4_unicode_ci,
  `header_section` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `location_id` bigint unsigned DEFAULT NULL,
  `property_id` bigint unsigned DEFAULT NULL,
  `ordering` int NOT NULL DEFAULT '0',
  `category_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal',
  `mobile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `map_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attractions_seo_url_unique` (`seo_url`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attractions`
--

LOCK TABLES `attractions` WRITE;
/*!40000 ALTER TABLE `attractions` DISABLE KEYS */;
-- [scrubbed clean clone] attractions emptied
/*!40000 ALTER TABLE `attractions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `basic_settings`
--

DROP TABLE IF EXISTS `basic_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `basic_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `basic_settings_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=188 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `basic_settings`
--

LOCK TABLES `basic_settings` WRITE;
/*!40000 ALTER TABLE `basic_settings` DISABLE KEYS */;
INSERT INTO `basic_settings` VALUES (1,'nav_menu','[{\"label\":\"Home\",\"url\":\"\\/\",\"side\":\"left\",\"type\":\"link\"},{\"label\":\"Condos\",\"url\":\"\\/condos\",\"side\":\"left\",\"type\":\"link\"},{\"label\":\"Houses\",\"url\":\"\\/houses\",\"side\":\"left\",\"type\":\"link\"},{\"label\":\"Our Story\",\"url\":\"\\/our-story\",\"side\":\"left\",\"type\":\"link\"},{\"label\":\"Experiences\",\"url\":\"\\/experiences\",\"side\":\"right\",\"type\":\"link\"},{\"label\":\"FAQs\",\"url\":\"\\/faqs\",\"side\":\"right\",\"type\":\"link\"},{\"label\":\"Contact Us\",\"url\":\"\\/contact-us\",\"side\":\"right\",\"type\":\"link\"}]','2026-06-19 13:53:43','2026-06-19 13:53:43'),(2,'enabled_payment_gateways','stripe','2026-06-19 13:53:35','2026-06-19 13:53:35'),(3,'installment_plan','two','2026-06-19 15:14:31','2026-06-19 15:14:31'),(4,'installment_2_days','30','2026-06-19 15:14:31','2026-06-19 15:14:31'),(5,'installment_2_percent','35','2026-06-19 15:14:31','2026-06-19 15:14:31'),(6,'installment_2_min_lead_days','30','2026-06-19 15:14:31','2026-06-19 15:14:31'),(7,'installment_3_days','15','2026-06-19 15:14:31','2026-06-19 15:14:31'),(8,'installment_3_percent','20','2026-06-19 15:14:31','2026-06-19 15:14:31'),(9,'installment_3_min_lead_days','115','2026-06-19 15:14:31','2026-06-19 15:14:31'),(10,'tax_applies_to_nights','1','2026-06-19 15:14:31','2026-06-19 15:14:31'),(11,'tax_applies_to_cleaning_fee','1','2026-06-19 15:14:31','2026-06-19 15:14:31'),(12,'tax_applies_to_pet_fee','1','2026-06-19 15:14:31','2026-06-19 15:14:31'),(13,'tax_applies_to_extra_guest_fee','1','2026-06-19 15:14:31','2026-06-19 15:14:31'),(14,'tax_applies_to_hottub_addon','0','2026-06-19 15:14:31','2026-06-19 15:14:31'),(15,'tax_applies_to_firewood_addon','0','2026-06-19 15:14:31','2026-06-19 15:14:31'),(16,'tax_applies_to_rv_hookup_addon','0','2026-06-19 15:14:31','2026-06-19 15:14:31'),(17,'tax_applies_to_extra_visitor_addon','0','2026-06-19 15:14:31','2026-06-19 15:14:31'),(18,'discount_applies_to_nights','1','2026-06-19 15:14:31','2026-06-19 15:14:31'),(19,'discount_applies_to_cleaning_fee','0','2026-06-19 15:14:31','2026-06-19 15:14:31'),(20,'discount_applies_to_pet_fee','0','2026-06-19 15:14:31','2026-06-19 15:14:31'),(21,'discount_applies_to_extra_guest_fee','0','2026-06-19 15:14:31','2026-06-19 15:14:31'),(22,'discount_applies_to_hottub_addon','0','2026-06-19 15:14:31','2026-06-19 15:14:31'),(23,'discount_applies_to_firewood_addon','0','2026-06-19 15:14:31','2026-06-19 15:14:31'),(24,'discount_applies_to_rv_hookup_addon','0','2026-06-19 15:14:31','2026-06-19 15:14:31'),(25,'discount_applies_to_extra_visitor_addon','0','2026-06-19 15:14:31','2026-06-19 15:14:31'),(26,'cancellation_template_flexible','Free cancellation up to 24 hours before check-in. After that, no refunds.','2026-06-19 13:53:35','2026-06-19 13:53:35'),(27,'cancellation_template_moderate','Free cancellation until 5 days before check-in. After that, no refunds.','2026-06-19 13:53:35','2026-06-19 13:53:35'),(28,'cancellation_template_strict','Free cancellation until 14 days before check-in. 50% refund 7-14 days before. No refund within 7 days.','2026-06-19 13:53:35','2026-06-19 13:53:35'),(29,'cancellation_template_firm','Free cancellation until 30 days before check-in. 50% refund 14-30 days before. No refund within 14 days.','2026-06-19 13:53:35','2026-06-19 13:53:35'),(30,'cancellation_template_super_strict','50% refund until 60 days before check-in. No refund within 60 days.','2026-06-19 13:53:35','2026-06-19 13:53:35'),(31,'rental_agreement_template_html','<h2>1. Parties</h2>\n<p>This Rental Agreement (the \"Agreement\") is entered into between <strong>{{owner_name}}</strong> (the \"Owner\"), {{owner_company}}, and <strong>{{guest_name}}</strong> (the \"Guest\"), email <a href=\"mailto:{{guest_email}}\">{{guest_email}}</a>.</p>\n\n<h2>2. Property and Term</h2>\n<p>The Owner agrees to rent to the Guest the property known as <strong>{{property_name}}</strong>, located at {{property_address}}, for the period from <strong>{{check_in}}</strong> through <strong>{{check_out}}</strong>, a total of <strong>{{nights}}</strong> night(s).</p>\n<p>Occupancy: {{adults}} adult(s), {{children}} child(ren), {{pets}} pet(s).</p>\n\n<h2>3. Total Charges and Damage Deposit</h2>\n<p>Total rental amount: <strong>{{currency}} {{total_amount}}</strong>.</p>\n<p>Refundable damage deposit: {{currency}} {{damage_deposit}}, held against any damages or excessive cleaning required after the Guest\'s stay.</p>\n\n<h2>4. Cancellation</h2>\n<p>Cancellation is subject to the cancellation policy displayed and accepted by the Guest at the time of booking. Refer to the booking confirmation for the applicable terms.</p>\n\n<h2>5. House Rules</h2>\n<p>The House Rules section that follows the Agreement is incorporated by reference and is binding on the Guest. The Guest acknowledges having read the House Rules prior to signing.</p>\n\n<h2>6. Liability</h2>\n<p>The Guest accepts the property in its present condition and assumes all risk associated with the use of the premises and its amenities. The Owner is not liable for personal injury, loss, or damage to personal property except in the case of gross negligence on the Owner\'s part.</p>\n\n<h2>7. Damage Policy</h2>\n<p>The Guest is responsible for any damage caused to the property or its contents during the rental period beyond normal wear and tear. The damage deposit may be applied toward the cost of repairs; damages exceeding the deposit will be billed to the Guest.</p>\n\n<h2>8. Governing Law</h2>\n<p>This Agreement is governed by the laws of the Republic of Your Location. Any dispute arising out of or related to this Agreement shall be subject to the exclusive jurisdiction of the courts of San Jose, Your Location.</p>\n\n<h2>9. Electronic Signature</h2>\n<p>The Guest agrees that the electronic signature affixed below is the legal equivalent of a handwritten signature and constitutes the Guest\'s intent and acceptance of the terms of this Agreement under applicable law.</p>',NULL,'2026-06-19 13:53:36'),(32,'rental_agreement_house_rules_html','<ul>\n  <li><strong>Maximum occupancy:</strong> as agreed at booking. No additional overnight guests without prior written approval from the Owner.</li>\n  <li><strong>No smoking</strong> inside the property. Smoking is permitted only in designated outdoor areas, if any.</li>\n  <li><strong>No parties or events</strong> without prior written approval from the Owner.</li>\n  <li><strong>Quiet hours:</strong> 10:00 PM to 8:00 AM. Please respect neighbors and surrounding wildlife.</li>\n  <li><strong>Pets:</strong> permitted only when explicitly approved at booking. Owners are responsible for cleaning up after their pets.</li>\n  <li><strong>Pool, hot tub, and outdoor amenities:</strong> use at your own risk. Children must be supervised at all times.</li>\n  <li><strong>Trash and recycling:</strong> follow the instructions left at the property.</li>\n  <li><strong>Departure:</strong> please leave the property in the condition in which you found it. Excessive cleaning beyond standard turnover may be charged against the damage deposit.</li>\n</ul>',NULL,'2026-06-19 13:53:36'),(33,'rental_agreement_default_enabled','0',NULL,'2026-06-19 13:53:36'),(34,'welcome_default_description','<p>Welcome to <strong>{{property_name}}</strong>, {{guest_name}}! Your stay starts on <strong>{{check_in}}</strong>. We can\'t wait to host you. If you have questions, your host {{host_name}} can be reached at <a href=\"tel:{{host_phone}}\">{{host_phone}}</a>.</p>',NULL,'2026-06-19 13:53:36'),(35,'welcome_default_attachment_path','',NULL,'2026-06-19 13:53:36'),(36,'checkin_default_arrival_window','Any time after 4:00 PM',NULL,'2026-06-19 13:53:36'),(37,'checkin_default_door_code','',NULL,'2026-06-19 13:53:36'),(38,'checkin_default_wifi_name','',NULL,'2026-06-19 13:53:36'),(39,'checkin_default_wifi_password','',NULL,'2026-06-19 13:53:36'),(40,'checkin_default_parking_instructions','<p>Park in the marked spot under the carport.</p>',NULL,'2026-06-19 13:53:36'),(41,'checkin_default_host_name','Demo Hosts',NULL,'2026-06-19 13:53:40'),(42,'checkin_default_host_phone','',NULL,'2026-06-19 13:53:36'),(43,'checkin_default_emergency_phone','911 (or local equivalent)',NULL,'2026-06-19 13:53:36'),(44,'checkin_default_notes_html','',NULL,'2026-06-19 13:53:36'),(45,'checkout_default_departure_time','By 11:00 AM',NULL,'2026-06-19 13:53:36'),(46,'checkout_default_key_return','<p>Leave the key in the lockbox using the same code.</p>',NULL,'2026-06-19 13:53:36'),(47,'checkout_default_cleaning_checklist','<ul><li>Empty the trash and place outside</li><li>Start the dishwasher if used</li><li>Turn off AC and lights</li><li>Lock all doors and windows</li></ul>',NULL,'2026-06-19 13:53:36'),(48,'checkout_default_notes_html','<p>Safe travels! We\'d love a review of your stay.</p>',NULL,'2026-06-19 13:53:36'),(49,'checkin_email_send_day','0',NULL,'2026-06-19 13:53:36'),(50,'checkout_email_send_day','0',NULL,'2026-06-19 13:53:36'),(51,'square_environment','sandbox','2026-06-19 13:53:37','2026-06-19 13:53:37'),(52,'authorize_environment','sandbox','2026-06-19 13:53:37','2026-06-19 13:53:37'),(53,'AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY','','2026-06-19 13:53:37','2026-06-19 13:53:41'),(54,'stripe_publish_key','pk_test_YTuklIXpPf0UvOjLxew4tgIf','2026-06-19 13:53:41','2026-06-19 13:53:41'),(55,'stripe_secret_key','','2026-06-19 13:53:41','2026-06-19 13:53:41'),(56,'paypal_access_token','','2026-06-19 13:53:37','2026-06-19 13:53:41'),(57,'square_ACCESS_TOKEN','','2026-06-19 13:53:37','2026-06-19 13:53:41'),(58,'square_Application_id','','2026-06-19 13:53:37','2026-06-19 13:53:37'),(59,'square_location_id','','2026-06-19 13:53:37','2026-06-19 13:53:41'),(60,'AUTHORIZED_MERCHANT_LOGIN_ID','','2026-06-19 13:53:37','2026-06-19 13:53:41'),(61,'AUTHORIZED_MERCHANT_TRANSACTION_KEY','','2026-06-19 13:53:37','2026-06-19 13:53:41'),(62,'cancellation_default_subject','Your booking at {{property_name}} has been cancelled','2026-06-19 13:53:37','2026-06-19 13:53:38'),(63,'cancellation_default_body_html','<p style=\"font-size:18px;color:#0f172a;margin:0 0 12px;\">Hi {{guest_name}},</p><p>This is to confirm your booking at <strong>{{property_name}}</strong> has been cancelled.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-in</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-out</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_out}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Nights</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{nights}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}} adult, {{children}} child, {{pets}} pet</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking ID</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr></table><p>If a refund applies under the cancellation policy you accepted at booking, it will be processed to the original payment method within 5–10 business days.</p><p>We\'re sorry your plans changed — and hope to host you another time. If anything was wrong on our end, please reply and let us know.</p><p style=\"margin-top:24px;color:#475569;\">Warmly,<br><strong style=\"color:#0f172a;\">{{brand}}</strong></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(64,'enquiry_default_subject','We received your enquiry — {{property_name}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(65,'enquiry_default_body_html','<p style=\"font-size:18px;color:#0f172a;margin:0 0 12px;\">Thanks for reaching out, {{guest_name}}.</p><p>We\'ve got your enquiry for <strong>{{property_name}}</strong> and will get back to you within 24 hours with availability and a tailored quote.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-in</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-out</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_out}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Nights</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{nights}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}} adult, {{children}} child, {{pets}} pet</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking ID</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr></table><p>If you have any questions in the meantime — accessibility, pet policy, local activities — just reply to this email and one of us will pick it up.</p><p style=\"margin-top:24px;color:#475569;\">Warmly,<br><strong style=\"color:#0f172a;\">{{brand}}</strong></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(66,'awaiting_payment_default_subject','Complete your booking at {{property_name}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(67,'awaiting_payment_default_body_html','<p style=\"font-size:18px;color:#0f172a;margin:0 0 12px;\">You\'re almost there, {{guest_name}}.</p><p>Your enquiry for <strong>{{property_name}}</strong> has been approved. To lock in your dates, please complete your payment using the link below.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-in</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-out</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_out}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Nights</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{nights}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}} adult, {{children}} child, {{pets}} pet</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking ID</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr></table><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin:18px 0;\"><tr><td style=\"border-radius:6px;background:#1a73e8;\"><a href=\"{{booking_url}}\" target=\"_blank\" style=\"display:inline-block;padding:12px 26px;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;border-radius:6px;\">Pay & confirm booking</a></td></tr></table><p style=\"color:#64748b;font-size:13px;\">The booking is held for you for 48 hours — after that, the dates open back up to other guests.</p><p style=\"margin-top:24px;color:#475569;\">Warmly,<br><strong style=\"color:#0f172a;\">{{brand}}</strong></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(68,'confirmed_default_subject','Booking confirmed — {{property_name}} ({{check_in}} → {{check_out}})','2026-06-19 13:53:37','2026-06-19 13:53:38'),(69,'confirmed_default_body_html','<p style=\"font-size:20px;color:#0f172a;margin:0 0 12px;\">🎉 You\'re booked, {{guest_name}}!</p><p>Your stay at <strong>{{property_name}}</strong> is confirmed. We\'ll send check-in details a day or two before your arrival.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-in</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-out</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_out}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Nights</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{nights}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}} adult, {{children}} child, {{pets}} pet</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking ID</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr></table><p style=\"margin-top:18px;\"><strong>What\'s next:</strong></p><ul style=\"margin:8px 0 0 20px;padding:0;color:#334155;\"><li style=\"margin-bottom:6px;\">A welcome package with house info will arrive a few days before check-in</li><li style=\"margin-bottom:6px;\">Check-in details (door code, parking, wifi) the day before</li><li style=\"margin-bottom:6px;\">Reach us anytime at this email or via the contact info in the welcome pack</li></ul><p style=\"margin-top:24px;color:#475569;\">Warmly,<br><strong style=\"color:#0f172a;\">{{brand}}</strong></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(70,'welcome_default_subject','Welcome to {{property_name}} — your stay begins {{check_in}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(71,'checkin_default_subject','Check-in details for {{property_name}} — see you tomorrow','2026-06-19 13:53:37','2026-06-19 13:53:38'),(72,'checkout_default_subject','Check-out tomorrow — {{property_name}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(73,'installment2_default_subject','Reminder: second payment due — {{property_name}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(74,'installment2_default_body_html','<p style=\"font-size:18px;color:#0f172a;margin:0 0 12px;\">Hi {{guest_name}},</p><p>This is a friendly nudge — the <strong>second instalment</strong> for your stay at <strong>{{property_name}}</strong> is now due.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-in</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-out</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_out}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Nights</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{nights}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}} adult, {{children}} child, {{pets}} pet</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking ID</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr></table><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin:18px 0;\"><tr><td style=\"border-radius:6px;background:#1a73e8;\"><a href=\"{{booking_url}}\" target=\"_blank\" style=\"display:inline-block;padding:12px 26px;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;border-radius:6px;\">Make a payment</a></td></tr></table><p style=\"color:#64748b;font-size:13px;\">Cleared payments keep your booking on schedule. Reply if you need a different payment method or split arrangement.</p><p style=\"margin-top:24px;color:#475569;\">Warmly,<br><strong style=\"color:#0f172a;\">{{brand}}</strong></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(75,'installment3_default_subject','Final payment due — {{property_name}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(76,'installment3_default_body_html','<p style=\"font-size:18px;color:#0f172a;margin:0 0 12px;\">Almost there, {{guest_name}}.</p><p>The <strong>final instalment</strong> for your stay at <strong>{{property_name}}</strong> is due. Once it clears, your check-in details will go out on schedule.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-in</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-out</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_out}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Nights</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{nights}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}} adult, {{children}} child, {{pets}} pet</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking ID</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr></table><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin:18px 0;\"><tr><td style=\"border-radius:6px;background:#1a73e8;\"><a href=\"{{booking_url}}\" target=\"_blank\" style=\"display:inline-block;padding:12px 26px;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;border-radius:6px;\">Complete final payment</a></td></tr></table><p style=\"color:#64748b;font-size:13px;\">Need a payment-method tweak or a quick split? Just reply — we\'ll figure it out.</p><p style=\"margin-top:24px;color:#475569;\">Warmly,<br><strong style=\"color:#0f172a;\">{{brand}}</strong></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(77,'review_default_subject','How was your stay at {{property_name}}?','2026-06-19 13:53:37','2026-06-19 13:53:38'),(78,'review_default_body_html','<p style=\"font-size:20px;color:#0f172a;margin:0 0 12px;\">Thanks for staying with us, {{guest_name}}.</p><p>We hope you had a wonderful time at <strong>{{property_name}}</strong>. Would you take a minute to share how it went?</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-in</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-out</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_out}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Nights</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{nights}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}} adult, {{children}} child, {{pets}} pet</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking ID</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr></table><p>Your review tells future guests what to expect — and tells us where we can improve. Even a sentence or two means a lot.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"margin:18px 0;\"><tr><td style=\"border-radius:6px;background:#1a73e8;\"><a href=\"{{review_url}}\" target=\"_blank\" style=\"display:inline-block;padding:12px 26px;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;border-radius:6px;\">Leave a review</a></td></tr></table><p style=\"color:#64748b;font-size:13px;\">If anything was off or could have been better, please reply privately — we\'d rather know.</p><p style=\"margin-top:24px;color:#475569;\">Warmly,<br><strong style=\"color:#0f172a;\">{{brand}}</strong></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(79,'admin_alert_enquiry_subject','[New enquiry] {{property_name}} — {{guest_name}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(80,'admin_alert_enquiry_body_html','<p>A new booking enquiry just came in for <strong>{{property_name}}</strong>.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guest</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{guest_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Stay</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}} → {{check_out}} ({{nights}}n)</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}}A · {{children}}C · {{pets}}P</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr></table><p><a href=\"{{admin_url}}\" target=\"_blank\" style=\"display:inline-block;padding:10px 22px;background:#0f172a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;\">Open booking in admin →</a></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(81,'admin_alert_awaiting_payment_subject','[Payment pending] #{{booking_id}} — {{property_name}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(82,'admin_alert_awaiting_payment_body_html','<p>Booking #{{booking_id}} was approved and the payment link has been issued. Awaiting renter payment.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guest</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{guest_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Stay</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}} → {{check_out}} ({{nights}}n)</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}}A · {{children}}C · {{pets}}P</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr></table><p><a href=\"{{admin_url}}\" target=\"_blank\" style=\"display:inline-block;padding:10px 22px;background:#0f172a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;\">Open booking in admin →</a></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(83,'admin_alert_confirmed_subject','[Booking confirmed] #{{booking_id}} — {{property_name}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(84,'admin_alert_confirmed_body_html','<p><strong>Payment received</strong> — booking #{{booking_id}} is now confirmed and on the calendar.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guest</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{guest_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Stay</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}} → {{check_out}} ({{nights}}n)</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}}A · {{children}}C · {{pets}}P</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr></table><p><a href=\"{{admin_url}}\" target=\"_blank\" style=\"display:inline-block;padding:10px 22px;background:#0f172a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;\">Open booking in admin →</a></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(85,'admin_alert_cancelled_subject','Booking #{{booking_id}} cancelled — {{property_name}}','2026-06-19 13:53:37','2026-06-19 13:53:37'),(86,'admin_alert_cancelled_body_html','<p><strong>A booking has been cancelled.</strong></p>\n<ul>\n<li>Booking ID: #{{booking_id}}</li>\n<li>Property: {{property_name}}</li>\n<li>Guest: {{guest_name}} &lt;{{guest_email}}&gt;</li>\n<li>Dates: {{check_in}} → {{check_out}} ({{nights}} nights)</li>\n<li>Total: {{total_amount}}</li>\n</ul>\n<p>Cancelled at: {{cancel_date}}</p>\n<p><a href=\"{{admin_url}}\">View booking in admin →</a></p>','2026-06-19 13:53:37','2026-06-19 13:53:37'),(87,'admin_alert_installment2_subject','[Instalment 2 reminder sent] #{{booking_id}} — {{property_name}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(88,'admin_alert_installment2_body_html','<p>Second instalment payment reminder sent to <strong>{{guest_name}}</strong>.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guest</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{guest_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Stay</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}} → {{check_out}} ({{nights}}n)</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}}A · {{children}}C · {{pets}}P</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr></table><p><a href=\"{{admin_url}}\" target=\"_blank\" style=\"display:inline-block;padding:10px 22px;background:#0f172a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;\">Open booking in admin →</a></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(89,'admin_alert_installment3_subject','[Final payment reminder sent] #{{booking_id}} — {{property_name}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(90,'admin_alert_installment3_body_html','<p>Final instalment payment reminder sent to <strong>{{guest_name}}</strong>.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guest</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{guest_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Stay</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}} → {{check_out}} ({{nights}}n)</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}}A · {{children}}C · {{pets}}P</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr></table><p><a href=\"{{admin_url}}\" target=\"_blank\" style=\"display:inline-block;padding:10px 22px;background:#0f172a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;\">Open booking in admin →</a></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(91,'admin_alert_rental_agreement_subject','Rental agreement signed — booking #{{booking_id}}','2026-06-19 13:53:37','2026-06-19 13:53:37'),(92,'admin_alert_rental_agreement_body_html','<p><strong>The guest has signed the rental agreement.</strong></p>\n<ul>\n<li>Booking ID: #{{booking_id}}</li>\n<li>Property: {{property_name}}</li>\n<li>Guest: {{guest_name}} &lt;{{guest_email}}&gt;</li>\n<li>Dates: {{check_in}} → {{check_out}} ({{nights}} nights)</li>\n<li>Total: {{total_amount}}</li>\n</ul>\n<p>A copy of the signed PDF is attached.</p>\n<p><a href=\"{{admin_url}}\">View booking in admin →</a></p>','2026-06-19 13:53:37','2026-06-19 13:53:37'),(93,'admin_bcc_welcome_enabled','0','2026-06-19 13:53:37','2026-06-19 13:53:37'),(94,'admin_alert_welcome_subject','[Welcome email sent] #{{booking_id}} — {{property_name}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(95,'admin_alert_welcome_body_html','<p>The welcome package has been delivered to <strong>{{guest_name}}</strong>. Check-in details will follow on schedule.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guest</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{guest_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Stay</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}} → {{check_out}} ({{nights}}n)</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}}A · {{children}}C · {{pets}}P</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr></table><p><a href=\"{{admin_url}}\" target=\"_blank\" style=\"display:inline-block;padding:10px 22px;background:#0f172a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;\">Open booking in admin →</a></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(96,'admin_bcc_checkin_enabled','0','2026-06-19 13:53:37','2026-06-19 13:53:37'),(97,'admin_alert_checkin_subject','[Check-in info sent] #{{booking_id}} — {{property_name}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(98,'admin_alert_checkin_body_html','<p>Check-in instructions delivered to <strong>{{guest_name}}</strong>. Door code, wifi and arrival window included.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guest</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{guest_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Stay</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}} → {{check_out}} ({{nights}}n)</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}}A · {{children}}C · {{pets}}P</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr></table><p><a href=\"{{admin_url}}\" target=\"_blank\" style=\"display:inline-block;padding:10px 22px;background:#0f172a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;\">Open booking in admin →</a></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(99,'admin_bcc_checkout_enabled','0','2026-06-19 13:53:37','2026-06-19 13:53:37'),(100,'admin_alert_checkout_subject','[Check-out reminder sent] #{{booking_id}} — {{property_name}}','2026-06-19 13:53:37','2026-06-19 13:53:38'),(101,'admin_alert_checkout_body_html','<p>Check-out reminder delivered to <strong>{{guest_name}}</strong>. Cleaning checklist included.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guest</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{guest_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Stay</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}} → {{check_out}} ({{nights}}n)</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}}A · {{children}}C · {{pets}}P</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr></table><p><a href=\"{{admin_url}}\" target=\"_blank\" style=\"display:inline-block;padding:10px 22px;background:#0f172a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;\">Open booking in admin →</a></p>','2026-06-19 13:53:37','2026-06-19 13:53:38'),(102,'welcome_default_body_html','<p style=\"font-size:20px;color:#0f172a;margin:0 0 12px;\">{{check_in}} is just around the corner, {{guest_name}}.</p><p>We\'re looking forward to hosting you at <strong>{{property_name}}</strong>. Here\'s a quick orientation so you arrive prepared.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-in</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-out</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_out}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Nights</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{nights}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}} adult, {{children}} child, {{pets}} pet</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking ID</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr></table><p style=\"margin-top:18px;\"><strong>Getting there</strong></p><p style=\"margin:6px 0;\">Detailed driving directions, the gate code, and parking instructions arrive in your check-in email a day before arrival.</p><p style=\"margin-top:14px;\"><strong>What we provide</strong></p><ul style=\"margin:6px 0 0 20px;padding:0;color:#334155;\"><li style=\"margin-bottom:5px;\">Linens, towels, basic toiletries</li><li style=\"margin-bottom:5px;\">Coffee, tea, salt, oil — the essentials</li><li style=\"margin-bottom:5px;\">High-speed Wi-Fi throughout the property</li><li style=\"margin-bottom:5px;\">A house manual on-site with restaurant + activity tips</li></ul><p style=\"margin-top:14px;color:#64748b;font-size:13px;\">Need anything before arrival? Reply to this email — we read everything.</p><p style=\"margin-top:24px;color:#475569;\">Warmly,<br><strong style=\"color:#0f172a;\">{{brand}}</strong></p>','2026-06-19 13:53:38','2026-06-19 13:53:38'),(103,'checkin_default_body_html','<p style=\"font-size:18px;color:#0f172a;margin:0 0 12px;\">All set for tomorrow, {{guest_name}}.</p><p>Here are your arrival details for <strong>{{property_name}}</strong>:</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-in</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-out</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_out}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Nights</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{nights}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}} adult, {{children}} child, {{pets}} pet</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking ID</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr></table><p style=\"margin-top:18px;\"><strong>How to get in</strong></p><p style=\"margin:6px 0 14px;\">Self check-in with keypad. Your access details and full directions are in the property\'s house manual + the printed welcome book inside.</p><p><strong>While you\'re here</strong></p><ul style=\"margin:6px 0 0 20px;padding:0;color:#334155;\"><li style=\"margin-bottom:5px;\">Quiet hours 10 PM – 7 AM</li><li style=\"margin-bottom:5px;\">Wi-Fi name + password are inside the welcome book</li><li style=\"margin-bottom:5px;\">Emergency phone: posted by the front door + in the welcome book</li></ul><p style=\"margin-top:14px;color:#64748b;font-size:13px;\">Have a wonderful stay. Reach out anytime — we\'re close by if you need anything.</p><p style=\"margin-top:24px;color:#475569;\">Warmly,<br><strong style=\"color:#0f172a;\">{{brand}}</strong></p>','2026-06-19 13:53:38','2026-06-19 13:53:38'),(104,'checkout_default_body_html','<p style=\"font-size:18px;color:#0f172a;margin:0 0 12px;\">Hope you\'ve had a great stay, {{guest_name}}.</p><p>A quick reminder: check-out from <strong>{{property_name}}</strong> is tomorrow.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-in</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Check-out</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_out}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Nights</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{nights}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}} adult, {{children}} child, {{pets}} pet</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking ID</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr></table><p style=\"margin-top:18px;\"><strong>Before you head out</strong></p><ul style=\"margin:6px 0 0 20px;padding:0;color:#334155;\"><li style=\"margin-bottom:5px;\">Strip used towels into the laundry basket</li><li style=\"margin-bottom:5px;\">Bag the trash and place it in the bin outside</li><li style=\"margin-bottom:5px;\">Turn off A/C, lights, and ceiling fans</li><li style=\"margin-bottom:5px;\">Lock the doors — keypad disarms automatically</li></ul><p style=\"margin-top:14px;\">Safe travels! We\'ll be in touch in a few days for a quick review request.</p><p style=\"margin-top:24px;color:#475569;\">Warmly,<br><strong style=\"color:#0f172a;\">{{brand}}</strong></p>','2026-06-19 13:53:38','2026-06-19 13:53:38'),(105,'admin_alert_cancellation_subject','[Cancelled] #{{booking_id}} — {{property_name}}','2026-06-19 13:53:38','2026-06-19 13:53:38'),(106,'admin_alert_cancellation_body_html','<p>Booking #{{booking_id}} was cancelled. Refund processing (if applicable) follows the cancellation policy on the booking.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guest</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{guest_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Stay</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}} → {{check_out}} ({{nights}}n)</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}}A · {{children}}C · {{pets}}P</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr></table><p><a href=\"{{admin_url}}\" target=\"_blank\" style=\"display:inline-block;padding:10px 22px;background:#0f172a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;\">Open booking in admin →</a></p>','2026-06-19 13:53:38','2026-06-19 13:53:38'),(107,'admin_alert_review_subject','[Review request sent] #{{booking_id}} — {{property_name}}','2026-06-19 13:53:38','2026-06-19 13:53:38'),(108,'admin_alert_review_body_html','<p>Review request delivered to <strong>{{guest_name}}</strong>. Submitted reviews appear in the admin Testimonials tab for approval.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Booking</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guest</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{guest_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Stay</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}} → {{check_out}} ({{nights}}n)</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}}A · {{children}}C · {{pets}}P</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:38%;\">Total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr></table><p><a href=\"{{admin_url}}\" target=\"_blank\" style=\"display:inline-block;padding:10px 22px;background:#0f172a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;\">Open booking in admin →</a></p>','2026-06-19 13:53:38','2026-06-19 13:53:38'),(109,'receipt_default_subject','Payment receipt — {{property_name}} · {{payment_amount}}','2026-06-19 13:53:38','2026-06-19 13:53:38'),(110,'receipt_default_body_html','<p style=\"font-size:18px;color:#0f172a;margin:0 0 12px;\">Hi {{guest_name}},</p><p>This is a receipt for your payment toward <strong>{{property_name}}</strong>. Keep this email for your records.</p><div style=\"margin:24px 0;padding:18px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;text-align:center;\"><div style=\"color:#065f46;font-size:13px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;\">Amount paid</div><div style=\"color:#047857;font-size:34px;font-weight:700;margin-top:6px;\">{{payment_amount}}</div></div><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Receipt date</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{payment_date}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Payment method</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{payment_method}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Transaction ID</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\"><code style=\"font-size:12px;color:#475569;\">{{transaction_id}}</code></td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Booking ID</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr></table><p style=\"margin-top:18px;\"><strong>Booking details</strong></p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Check-in</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Check-out</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_out}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Nights</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{nights}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Guests</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{adults}}A · {{children}}C · {{pets}}P</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Booking total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Outstanding balance</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{outstanding_balance}}</td></tr></table><p style=\"color:#64748b;font-size:13px;margin-top:14px;\">Need a different copy of this receipt or an invoice for accounting? Reply to this email and we\'ll send what you need.</p><p style=\"margin-top:24px;color:#475569;\">Thanks for your payment,<br><strong style=\"color:#0f172a;\">{{brand}}</strong></p>','2026-06-19 13:53:38','2026-06-19 13:53:38'),(111,'admin_alert_receipt_subject','[Payment received {{payment_amount}}] #{{booking_id}} — {{property_name}}','2026-06-19 13:53:38','2026-06-19 13:53:38'),(112,'admin_alert_receipt_body_html','<p style=\"font-size:18px;color:#0f172a;margin:0 0 12px;\">Payment received — booking #{{booking_id}}</p><p>A payment of <strong>{{payment_amount}}</strong> just landed on the booking below.</p><table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" style=\"background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;\"><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Booking</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">#{{booking_id}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Guest</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{guest_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Property</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{property_name}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Stay</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{check_in}} → {{check_out}} ({{nights}}n)</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Payment method</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{payment_method}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Transaction ID</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\"><code style=\"font-size:12px;\">{{transaction_id}}</code></td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Amount received</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\"><span style=\"color:#047857;\">{{payment_amount}}</span></td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Booking total</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">${{total_amount}}</td></tr><tr><td style=\"padding:8px 0;color:#64748b;font-size:13px;width:42%;\">Outstanding</td><td style=\"padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;\">{{outstanding_balance}}</td></tr></table><p><a href=\"{{admin_url}}\" target=\"_blank\" style=\"display:inline-block;padding:10px 22px;background:#0f172a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;\">Open booking in admin →</a></p>','2026-06-19 13:53:38','2026-06-19 13:53:38'),(113,'rental_agreement_signing_mode_global','pre_payment','2026-06-19 13:53:38','2026-06-19 13:53:38'),(114,'website','Your Brand','2026-06-19 13:53:42','2026-06-19 13:53:42'),(115,'email','hello@example.com','2026-06-19 13:53:42','2026-06-19 13:53:42'),(116,'mobile','+1 (555) 019-2847','2026-06-19 13:53:42','2026-06-19 13:53:42'),(117,'mobile1','','2026-06-19 13:53:41','2026-06-19 13:53:41'),(118,'mobile2','','2026-06-19 13:53:41','2026-06-19 13:53:41'),(119,'whatsapp_number','','2026-06-19 13:53:41','2026-06-19 13:53:41'),(120,'whatsapp_url','','2026-06-19 13:53:41','2026-06-19 13:53:41'),(121,'address','Your Location','2026-06-19 13:53:42','2026-06-19 13:53:42'),(122,'copyright','© 2026 Base Vacation Rentals. All rights reserved. · Your Destination','2026-06-19 13:53:40','2026-06-19 13:53:40'),(123,'about','Boutique vacation homes near the local trails and scenery of Your Destination — hosted directly by Demo Hosts.','2026-06-19 13:53:40','2026-06-19 13:53:40'),(124,'footer_brand_desc','Design-forward condos and private houses where rainforest views meet Pacific air.','2026-06-19 13:53:42','2026-06-19 13:53:42'),(125,'footer_brand_sub','YOUR LOCATION · JUNGLE & SEA','2026-06-19 13:53:42','2026-06-19 13:53:42'),(126,'footer_brand_tagline','DESTINATION','2026-06-19 13:53:40','2026-06-19 13:53:40'),(127,'owner_name','Your Brand Team','2026-06-19 13:53:42','2026-06-19 13:53:42'),(128,'logo','front/themes/base/images/logo-removebg-preview.webp','2026-06-19 13:53:42','2026-06-19 13:53:42'),(129,'header_logo','front/themes/base/images/logo-removebg-preview.webp','2026-06-19 13:53:42','2026-06-19 13:53:42'),(130,'favicon','front/themes/base/images/logo-removebg-preview.webp','2026-06-19 13:53:42','2026-06-19 13:53:42'),(131,'footer-image','front/themes/base/img/logo.svg','2026-06-19 13:53:40','2026-06-19 13:53:40'),(132,'map','https://www.google.com/maps?q=Your+Destination&output=embed','2026-06-19 13:53:42','2026-06-19 13:53:42'),(133,'map_embed_url','https://maps.google.com/?q=Your Location,+Costa+Rica&output=embed','2026-06-19 13:53:42','2026-06-19 13:53:42'),(134,'map_open_url','https://maps.google.com/?q=Your Location,+Costa+Rica','2026-06-19 13:53:42','2026-06-19 13:53:42'),(135,'airbnb_url','','2026-06-19 13:53:42','2026-06-19 13:53:42'),(136,'airbnb_booking_url','','2026-06-19 13:53:42','2026-06-19 13:53:42'),(137,'book_external_url','','2026-06-19 13:53:40','2026-06-19 13:53:40'),(138,'facebook',NULL,'2026-06-19 13:53:40','2026-06-19 15:09:25'),(139,'instagram',NULL,'2026-06-19 13:53:40','2026-06-19 15:09:25'),(140,'yelp_url','','2026-06-19 13:53:40','2026-06-19 13:53:40'),(141,'pms_driver','','2026-06-19 13:53:42','2026-06-19 13:53:42'),(142,'pms_source','','2026-06-19 13:53:42','2026-06-19 13:53:42'),(143,'mail_from_name','Your Brand','2026-06-19 13:53:42','2026-06-19 13:53:42'),(144,'property-heading-text','Three ways to wake up in the scenery','2026-06-19 13:53:40','2026-06-19 13:53:40'),(145,'property-subheading-text','The Homes','2026-06-19 13:53:40','2026-06-19 13:53:40'),(146,'testimonial-heading-text','Loved by 500+ guests','2026-06-19 13:53:40','2026-06-19 13:53:40'),(147,'testimonial-subheading-text','Guest Favorites','2026-06-19 13:53:40','2026-06-19 13:53:40'),(148,'attraction-heading-text','Your Destination, minutes from your door','2026-06-19 13:53:40','2026-06-19 13:53:40'),(149,'attraction-subheading-text','Explore','2026-06-19 13:53:40','2026-06-19 13:53:40'),(150,'faq-heading-text','Frequently Asked Questions','2026-06-19 13:53:40','2026-06-19 13:53:40'),(151,'faq-subheading-text','Good to Know','2026-06-19 13:53:40','2026-06-19 13:53:40'),(152,'home_video','','2026-06-19 13:53:40','2026-06-19 13:53:40'),(153,'lodgify_health_status','','2026-06-19 13:53:41','2026-06-19 13:53:41'),(154,'lodgify_last_health_check_at','','2026-06-19 13:53:41','2026-06-19 13:53:41'),(155,'lodgify_consecutive_failures','0','2026-06-19 13:53:41','2026-06-19 13:53:41'),(156,'default_header_image','front/themes/base/img/placeholder.svg','2026-06-19 13:53:42','2026-06-19 13:53:42'),(157,'frontend_theme_class','client-theme-base','2026-06-19 13:53:42','2026-06-19 13:53:42'),(158,'frontend_theme_css','front/themes/base/css/theme.css','2026-06-19 13:53:42','2026-06-19 13:53:42'),(159,'frontend_theme_fonts','https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap','2026-06-19 13:53:42','2026-06-19 13:53:42'),(160,'admin_email_primary',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(161,'mail_from','admin@example.com','2026-06-19 15:09:25','2026-06-19 15:09:25'),(162,'mail_footer',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(163,'smtp_host','smtp.gmail.com','2026-06-19 15:09:25','2026-06-19 15:09:25'),(164,'smtp_port','465','2026-06-19 15:09:25','2026-06-19 15:09:25'),(165,'email_encryption','ssl','2026-06-19 15:09:25','2026-06-19 15:09:25'),(166,'smtp_user','admin@example.com','2026-06-19 15:09:25','2026-06-19 15:09:25'),(167,'smtp_pass','osig jihb fspv hutj','2026-06-19 15:09:25','2026-06-19 15:09:25'),(168,'contact_us_receiving_mail',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(169,'newsletter_receiving_mail',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(170,'booking_receiving_mail',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(171,'payment_receiving_mail',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(172,'rental_aggrement_receiving_mail',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(173,'welcome_package_receiving_mail',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(174,'reminder_package_receiving_mail',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(175,'review_receiving_mail',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(176,'cancel_receiving_mail',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(177,'version_css_js',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(178,'webhook_base_url',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(179,'lodgify_webhook_token',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(180,'lodgify_api_key',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(181,'hostfully_agency_uid',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(182,'hostfully_api_key',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(183,'guesty_client_id',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(184,'guesty_api_key',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(185,'hostaway_account_id',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(186,'hostaway_api_key',NULL,'2026-06-19 15:09:25','2026-06-19 15:09:25'),(187,'payment_currency','$','2026-06-19 15:14:31','2026-06-19 15:14:31');
/*!40000 ALTER TABLE `basic_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_requests`
--

DROP TABLE IF EXISTS `booking_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pms_source` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_room_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_booking_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_recovery_attempts` tinyint unsigned DEFAULT '0',
  `pms_sync_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `property_id` int NOT NULL,
  `checkin` date NOT NULL,
  `checkout` date NOT NULL,
  `total_guests` int DEFAULT NULL,
  `adults` int DEFAULT NULL,
  `child` int DEFAULT NULL,
  `infants` int DEFAULT '0',
  `gross_amount` double DEFAULT NULL,
  `total_night` double DEFAULT NULL,
  `sub_amount` double DEFAULT NULL,
  `total_amount` double DEFAULT NULL,
  `after_total_fees` longtext COLLATE utf8mb4_unicode_ci,
  `before_total_fees` longtext COLLATE utf8mb4_unicode_ci,
  `request_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `booking_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'booked',
  `email_status` enum('true','false') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `payment_status` enum('paid','partially','pending','declined','failed','check-booking') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `welcome_email` enum('true','false') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `welcome_email_sent_at` timestamp NULL DEFAULT NULL,
  `welcome_email_attempted_at` timestamp NULL DEFAULT NULL,
  `welcome_email_error` text COLLATE utf8mb4_unicode_ci,
  `review_email` enum('true','false') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `review_email_sent_at` timestamp NULL DEFAULT NULL,
  `review_email_attempted_at` timestamp NULL DEFAULT NULL,
  `review_email_error` text COLLATE utf8mb4_unicode_ci,
  `reminder_email` enum('true','false') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `third_reminder_email` enum('true','false') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `checkin_email` enum('true','false') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `checkin_email_sent_at` timestamp NULL DEFAULT NULL,
  `checkin_email_attempted_at` timestamp NULL DEFAULT NULL,
  `checkin_email_error` text COLLATE utf8mb4_unicode_ci,
  `checkout_email` enum('true','false') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `checkout_email_sent_at` timestamp NULL DEFAULT NULL,
  `checkout_email_attempted_at` timestamp NULL DEFAULT NULL,
  `checkout_email_error` text COLLATE utf8mb4_unicode_ci,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` longtext COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_reason` longtext COLLATE utf8mb4_unicode_ci,
  `note` longtext COLLATE utf8mb4_unicode_ci,
  `rental_aggrement_status` enum('true','false') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `rental_aggrement_signature` text COLLATE utf8mb4_unicode_ci,
  `rental_aggrement_images` longtext COLLATE utf8mb4_unicode_ci,
  `rental_agreement_link` text COLLATE utf8mb4_unicode_ci,
  `rental_agreement_pdf_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rental_agreement_signed_at` timestamp NULL DEFAULT NULL,
  `rental_agreement_signed_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rental_agreement_signed_user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rental_agreement_consent_text` text COLLATE utf8mb4_unicode_ci,
  `rental_agreement_html_snapshot` longtext COLLATE utf8mb4_unicode_ci,
  `total_payment` int NOT NULL DEFAULT '1',
  `amount_data` mediumtext COLLATE utf8mb4_unicode_ci,
  `how_many_payment_done` int NOT NULL DEFAULT '0',
  `total_pets` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pet_fee` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_fee` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rest_guests` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `single_guest_fee` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_coupon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `after_discount_total` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extra_discount` int NOT NULL DEFAULT '0',
  `color` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'red',
  `heating_pool_fee` double DEFAULT NULL,
  `tax` varchar(155) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `define_tax` varchar(155) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pet_fee_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'taxable',
  `heating_pool_fee_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'taxable',
  `booking_type_admin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'invoice',
  `quote_date` timestamp NULL DEFAULT NULL,
  `quote_ip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rentar_agreement_date` timestamp NULL DEFAULT NULL,
  `rentar_agreement_ip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_before_total_fees` longtext COLLATE utf8mb4_unicode_ci,
  `heating_pool_fee_data_guarav` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `extra_amount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_profile_id` text COLLATE utf8mb4_unicode_ci,
  `customer_profile_payment_id` text COLLATE utf8mb4_unicode_ci,
  `customer_json_data` text COLLATE utf8mb4_unicode_ci,
  `payment_method` text COLLATE utf8mb4_unicode_ci,
  `security_deposit_data` text COLLATE utf8mb4_unicode_ci,
  `card_need_hide` int DEFAULT NULL,
  `cancel_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'USD',
  `security_deposit` decimal(10,2) DEFAULT NULL,
  `deposit_due_amount` decimal(10,2) DEFAULT NULL,
  `optional_services` longtext COLLATE utf8mb4_unicode_ci,
  `requested_services` longtext COLLATE utf8mb4_unicode_ci,
  `quoted_fees_snapshot` longtext COLLATE utf8mb4_unicode_ci,
  `price_changed_to` decimal(10,2) DEFAULT NULL,
  `price_changed_at` timestamp NULL DEFAULT NULL,
  `payment_intent_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_payment_intent_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_refund_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deposit_payment_intent_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deposit_paid_at` timestamp NULL DEFAULT NULL,
  `balance_payment_intent_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `balance_paid_at` timestamp NULL DEFAULT NULL,
  `pet` int NOT NULL DEFAULT '0',
  `quote_data_all` longtext COLLATE utf8mb4_unicode_ci,
  `pms_booking_response_all` longtext COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_deposit_charge_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `security_deposit_amount` decimal(10,2) DEFAULT NULL,
  `security_deposit_currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `security_deposit_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_recovery_last_at` timestamp NULL DEFAULT NULL,
  `payment_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quote_expires_at` timestamp NULL DEFAULT NULL,
  `quote_refresh_failed` tinyint(1) NOT NULL DEFAULT '0',
  `pms_payment_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_order_uid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_sync_error` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_requests`
--

LOCK TABLES `booking_requests` WRITE;
/*!40000 ALTER TABLE `booking_requests` DISABLE KEYS */;
-- [scrubbed clean clone] booking_requests emptied
/*!40000 ALTER TABLE `booking_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cms`
--

DROP TABLE IF EXISTS `cms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seo_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shortDescription` longtext COLLATE utf8mb4_unicode_ci,
  `mediumDescription` longtext COLLATE utf8mb4_unicode_ci,
  `longDescription` longtext COLLATE utf8mb4_unicode_ci,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` text COLLATE utf8mb4_unicode_ci,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `templete` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bannerImage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publish` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `footer_section` longtext COLLATE utf8mb4_unicode_ci,
  `header_section` longtext COLLATE utf8mb4_unicode_ci,
  `seo_section` longtext COLLATE utf8mb4_unicode_ci,
  `image_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `faq_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `faq_heading` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `faq_desction` text COLLATE utf8mb4_unicode_ci,
  `faq_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `strip_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `strip_desction` text COLLATE utf8mb4_unicode_ci,
  `strip_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `about_image1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `about_image2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gallery_background` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `property_background` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `about_background` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gallery_heading` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_eyebrow` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_heading` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hero_sub` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `story_eyebrow` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `story_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `story_body_2` text COLLATE utf8mb4_unicode_ci,
  `retreats_eyebrow` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retreats_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `better_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `better_sub` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `explore_eyebrow` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `explore_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `explore_sub` text COLLATE utf8mb4_unicode_ci,
  `explore_map_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `faq_eyebrow` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `rooftop_features` text COLLATE utf8mb4_unicode_ci,
  `living_pills` text COLLATE utf8mb4_unicode_ci,
  `beach_pills` text COLLATE utf8mb4_unicode_ci,
  `hero_heading_gold` text COLLATE utf8mb4_unicode_ci,
  `hero_video_src` text COLLATE utf8mb4_unicode_ci,
  `hero_cta1_label` text COLLATE utf8mb4_unicode_ci,
  `hero_cta1_url` text COLLATE utf8mb4_unicode_ci,
  `hero_cta2_label` text COLLATE utf8mb4_unicode_ci,
  `hero_cta2_url` text COLLATE utf8mb4_unicode_ci,
  `condos_heading` text COLLATE utf8mb4_unicode_ci,
  `condos_cta_label` text COLLATE utf8mb4_unicode_ci,
  `condos_cta_url` text COLLATE utf8mb4_unicode_ci,
  `condo_tabs` longtext COLLATE utf8mb4_unicode_ci,
  `houses_heading` text COLLATE utf8mb4_unicode_ci,
  `houses_card_eyebrow` text COLLATE utf8mb4_unicode_ci,
  `houses_card_heading` text COLLATE utf8mb4_unicode_ci,
  `houses_card_desc` text COLLATE utf8mb4_unicode_ci,
  `houses_card_tags` longtext COLLATE utf8mb4_unicode_ci,
  `houses_card_cta_label` text COLLATE utf8mb4_unicode_ci,
  `houses_card_cta_url` text COLLATE utf8mb4_unicode_ci,
  `experiences_eyebrow` text COLLATE utf8mb4_unicode_ci,
  `experiences_heading` text COLLATE utf8mb4_unicode_ci,
  `experiences_desc` text COLLATE utf8mb4_unicode_ci,
  `experiences_cta_label` text COLLATE utf8mb4_unicode_ci,
  `experiences_cta_url` text COLLATE utf8mb4_unicode_ci,
  `story_image` text COLLATE utf8mb4_unicode_ci,
  `story_body` longtext COLLATE utf8mb4_unicode_ci,
  `story_cta_label` text COLLATE utf8mb4_unicode_ci,
  `story_cta_url` text COLLATE utf8mb4_unicode_ci,
  `amenities_heading` text COLLATE utf8mb4_unicode_ci,
  `amenities_list` longtext COLLATE utf8mb4_unicode_ci,
  `amenities_cta1_label` text COLLATE utf8mb4_unicode_ci,
  `amenities_cta1_url` text COLLATE utf8mb4_unicode_ci,
  `amenities_cta2_label` text COLLATE utf8mb4_unicode_ci,
  `amenities_cta2_url` text COLLATE utf8mb4_unicode_ci,
  `location_heading` text COLLATE utf8mb4_unicode_ci,
  `location_body` text COLLATE utf8mb4_unicode_ci,
  `location_distances` longtext COLLATE utf8mb4_unicode_ci,
  `location_cta_label` text COLLATE utf8mb4_unicode_ci,
  `location_cta_url` text COLLATE utf8mb4_unicode_ci,
  `location_image` text COLLATE utf8mb4_unicode_ci,
  `location_caption_eyebrow` text COLLATE utf8mb4_unicode_ci,
  `location_caption_text` text COLLATE utf8mb4_unicode_ci,
  `testimonials_heading` text COLLATE utf8mb4_unicode_ci,
  `cta_section_heading` text COLLATE utf8mb4_unicode_ci,
  `cta_section_body` text COLLATE utf8mb4_unicode_ci,
  `cta_section_cta1_label` text COLLATE utf8mb4_unicode_ci,
  `cta_section_cta1_url` text COLLATE utf8mb4_unicode_ci,
  `cta_section_cta2_label` text COLLATE utf8mb4_unicode_ci,
  `cta_section_cta2_url` text COLLATE utf8mb4_unicode_ci,
  `cta_section_response_note` text COLLATE utf8mb4_unicode_ci,
  `story_block_label` text COLLATE utf8mb4_unicode_ci,
  `story_block_heading` text COLLATE utf8mb4_unicode_ci,
  `story_block_body` longtext COLLATE utf8mb4_unicode_ci,
  `story_block_image` text COLLATE utf8mb4_unicode_ci,
  `story_block_author_name` text COLLATE utf8mb4_unicode_ci,
  `story_block_author_role` text COLLATE utf8mb4_unicode_ci,
  `quote_text` text COLLATE utf8mb4_unicode_ci,
  `milestones_label` text COLLATE utf8mb4_unicode_ci,
  `milestones_heading` text COLLATE utf8mb4_unicode_ci,
  `milestones_list` longtext COLLATE utf8mb4_unicode_ci,
  `stats_list` longtext COLLATE utf8mb4_unicode_ci,
  `feature_ribbon_items` longtext COLLATE utf8mb4_unicode_ci,
  `filter_categories` longtext COLLATE utf8mb4_unicode_ci,
  `condos_count_text` text COLLATE utf8mb4_unicode_ci,
  `check_avail_heading` text COLLATE utf8mb4_unicode_ci,
  `compare_heading` text COLLATE utf8mb4_unicode_ci,
  `compare_table_data` longtext COLLATE utf8mb4_unicode_ci,
  `houses_subtitle_heading` text COLLATE utf8mb4_unicode_ci,
  `experiences_intro` longtext COLLATE utf8mb4_unicode_ci,
  `experience_grid_items` longtext COLLATE utf8mb4_unicode_ci,
  `quick_answers` longtext COLLATE utf8mb4_unicode_ci,
  `quick_answers_heading` text COLLATE utf8mb4_unicode_ci,
  `booking_empty_message` text COLLATE utf8mb4_unicode_ci,
  `getting_here_steps` longtext COLLATE utf8mb4_unicode_ci,
  `getting_here_map_iframe_src` longtext COLLATE utf8mb4_unicode_ci,
  `getting_here_directions_url` text COLLATE utf8mb4_unicode_ci,
  `hero_carousel_images` longtext COLLATE utf8mb4_unicode_ci,
  `better_items` longtext COLLATE utf8mb4_unicode_ci,
  `gallery_eyebrow` text COLLATE utf8mb4_unicode_ci,
  `gallery_title` text COLLATE utf8mb4_unicode_ci,
  `elfsight_widget_id` text COLLATE utf8mb4_unicode_ci,
  `story_image_accent` text COLLATE utf8mb4_unicode_ci,
  `home_gallery_images` longtext COLLATE utf8mb4_unicode_ci,
  `inner_banner_image` text COLLATE utf8mb4_unicode_ci,
  `hero_badges` longtext COLLATE utf8mb4_unicode_ci,
  `gallery_cards` longtext COLLATE utf8mb4_unicode_ci,
  `ui_labels` longtext COLLATE utf8mb4_unicode_ci,
  `fw_section_sub` longtext COLLATE utf8mb4_unicode_ci,
  `fw_banner_title` text COLLATE utf8mb4_unicode_ci,
  `fw_section_eyebrow` text COLLATE utf8mb4_unicode_ci,
  `fw_section_title` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cms_seo_url_unique` (`seo_url`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cms`
--

LOCK TABLES `cms` WRITE;
/*!40000 ALTER TABLE `cms` DISABLE KEYS */;
INSERT INTO `cms` VALUES (1,'Checkout','checkout',NULL,NULL,NULL,'Complete your booking.',NULL,NULL,NULL,NULL,'checkout',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Book Direct Benefits',NULL,NULL,NULL,NULL,'https://maps.google.com/?q=Your+Destination',NULL,'2026-06-19 13:53:35','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[\"\\/front\\/themes\\/base\\/img\\/placeholder.svg\"]',NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg','[]','front/themes/base/img/placeholder.svg','[]','[]',NULL,NULL,NULL,NULL,NULL),(2,'Payment Successful','payment-success',NULL,NULL,NULL,'Thanks — your payment has been received.',NULL,NULL,NULL,NULL,'payment-success',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Book Direct Benefits',NULL,NULL,NULL,NULL,'https://maps.google.com/?q=Your+Destination',NULL,'2026-06-19 13:53:35','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[\"\\/front\\/themes\\/base\\/img\\/placeholder.svg\"]',NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg','[]','front/themes/base/img/placeholder.svg','[]','[]',NULL,NULL,NULL,NULL,NULL),(3,'Payment Failed','payment-failed',NULL,NULL,NULL,'Sorry, your payment did not go through. Please try again.',NULL,NULL,NULL,NULL,'payment-failed',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Book Direct Benefits',NULL,NULL,NULL,NULL,'https://maps.google.com/?q=Your+Destination',NULL,'2026-06-19 13:53:35','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[\"\\/front\\/themes\\/base\\/img\\/placeholder.svg\"]',NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg','[]','front/themes/base/img/placeholder.svg','[]','[]',NULL,NULL,NULL,NULL,NULL),(4,'Complete Your Payment','magic-link-payment',NULL,NULL,NULL,'Your booking has been approved — complete your payment below.',NULL,NULL,NULL,NULL,'magic-link-payment',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Book Direct Benefits',NULL,NULL,NULL,NULL,'https://maps.google.com/?q=Your+Destination',NULL,'2026-06-19 13:53:35','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[\"\\/front\\/themes\\/base\\/img\\/placeholder.svg\"]',NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg','[]','front/themes/base/img/placeholder.svg','[]','[]',NULL,NULL,NULL,NULL,NULL),(5,'Link Expired','magic-link-expired',NULL,NULL,NULL,'This payment link has expired.',NULL,NULL,NULL,NULL,'magic-link-expired',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Book Direct Benefits',NULL,NULL,NULL,NULL,'https://maps.google.com/?q=Your+Destination',NULL,'2026-06-19 13:53:35','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[\"\\/front\\/themes\\/base\\/img\\/placeholder.svg\"]',NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg','[]','front/themes/base/img/placeholder.svg','[]','[]',NULL,NULL,NULL,NULL,NULL),(6,'Booking Confirmed','booking-confirmed-instant',NULL,NULL,NULL,'Your booking is confirmed.',NULL,NULL,NULL,NULL,'booking-confirmed',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Book Direct Benefits',NULL,NULL,NULL,NULL,'https://maps.google.com/?q=Your+Destination',NULL,'2026-06-19 13:53:35','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[\"\\/front\\/themes\\/base\\/img\\/placeholder.svg\"]',NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg','[]','front/themes/base/img/placeholder.svg','[]','[]',NULL,NULL,NULL,NULL,NULL),(7,'Request Received','booking-request-received',NULL,NULL,NULL,'We received your booking request and will get back to you within 24 hours.',NULL,NULL,NULL,NULL,'booking-request-received',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Book Direct Benefits',NULL,NULL,NULL,NULL,'https://maps.google.com/?q=Your+Destination',NULL,'2026-06-19 13:53:35','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[\"\\/front\\/themes\\/base\\/img\\/placeholder.svg\"]',NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg','[]','front/themes/base/img/placeholder.svg','[]','[]',NULL,NULL,NULL,NULL,NULL),(8,'Things To Do','things-to-do',NULL,NULL,NULL,NULL,NULL,'Things To Do',NULL,'Explore restaurants, beaches, hikes, and other attractions near our properties.','attractions',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Book Direct Benefits',NULL,NULL,NULL,NULL,'https://maps.google.com/?q=Your+Destination',NULL,'2026-06-19 13:53:37','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[\"\\/front\\/themes\\/base\\/img\\/placeholder.svg\"]',NULL,NULL,NULL,NULL,'front/themes/base/img/placeholder.svg','[]','front/themes/base/img/placeholder.svg','[]','[]',NULL,NULL,NULL,NULL,NULL),(9,'Home','home',NULL,NULL,NULL,NULL,NULL,'Your Brand Villas - Sleep where the jungle meets the sea',NULL,'Design-forward Your Location condos and private houses near jungle, ocean, and adventure.','home',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Your Brand Villas','Sleep where the jungle<br><span class=\"gold-text\">meets the sea.</span>','10 design-forward condos and 3 private houses in a Jurassic landscape.','Our Story','Your Location stays shaped by forest, ocean, and slow mornings.','<p class=\"story-body\">Wake to birdsong, watch the clouds move through the canopy, and return from beach days to thoughtful spaces made for real rest.</p><p class=\"story-body\">Every stay keeps guests close to nature while preserving the booking, payment, and owner tools of the shared PMS platform.</p>','Featured Stays','Condos and private houses above the jungle',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-19 13:53:42','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/images/joe-portrait.webp','Your Brand Villas is a private collection of homes for travelers who want comfort without losing the wild edge of Your Location.',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[\"front\\/themes\\/base\\/images\\/para.webp\",\"front\\/themes\\/base\\/images\\/house-bg.webp\",\"front\\/themes\\/base\\/images\\/condos-bg.webp\"]',NULL,'Gallery','Explore the Experience',NULL,'front/themes/base/images/waterfall-trekking.webp',NULL,NULL,NULL,'[{\"image\":\"front\\/themes\\/base\\/images\\/g1.webp\",\"title\":\"Ocean View\",\"label\":\"Condo\"},{\"image\":\"front\\/themes\\/base\\/images\\/house-bg.webp\",\"title\":\"Private House\",\"label\":\"Villa\"},{\"image\":\"front\\/themes\\/base\\/images\\/waterfall-trekking.webp\",\"title\":\"Waterfall Days\",\"label\":\"Experience\"},{\"image\":\"front\\/themes\\/base\\/images\\/zipline-canopy.webp\",\"title\":\"Canopy Adventure\",\"label\":\"Experience\"}]',NULL,NULL,NULL,NULL,NULL),(10,'Our Story','our-story',NULL,NULL,NULL,'A Your Location collection of design-forward stays for guests who want jungle quiet and easy direct booking.',NULL,'Our Story - Your Brand Villas',NULL,NULL,'our-story',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-19 13:53:42','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'A private collection between rainforest and sea','Your Brand Villas brings together modern condos, private houses, concierge support, and local experiences in Your Location.','front/themes/base/images/bg-story.webp',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/images/bg-our.webp',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(11,'Experiences','attractions',NULL,NULL,NULL,NULL,NULL,'Experiences - Your Brand Villas',NULL,NULL,'attractions',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Experiences','Explore Your Location from your door','Waterfalls, canopy zipline, wildlife, surf lessons, yoga, and easy days by the sea.',NULL,NULL,'2026-06-19 13:53:42','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/images/zipline-canopy.webp',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(12,'FAQs','faqs',NULL,NULL,NULL,NULL,NULL,'FAQs - Your Brand Villas',NULL,NULL,'faq',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,'Frequently Asked Questions',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Good to Know','2026-06-19 13:53:42','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/images/faq-bg.webp',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(13,'Contact Us','contact-us',NULL,NULL,NULL,'Ask about availability, group stays, experiences, and direct booking.',NULL,'Contact Your Brand Villas',NULL,NULL,'contact',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-19 13:53:42','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/images/map-placeholder.webp',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(14,'Stays','booking',NULL,NULL,NULL,NULL,NULL,'Stays - Your Brand Villas',NULL,NULL,'booking',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Available Stays','Choose your Your Location retreat',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-19 13:53:42','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/images/condos-bg.webp',NULL,NULL,NULL,NULL,'Our Stays',NULL,NULL),(15,'Gallery','gallery',NULL,NULL,NULL,NULL,NULL,'Gallery - Your Brand Villas',NULL,NULL,'gallery',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-19 13:53:42','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Gallery','Jungle, ocean, and villa moments',NULL,NULL,NULL,'front/themes/base/images/para.webp',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(16,'Reviews','reviews',NULL,NULL,NULL,NULL,NULL,'Reviews - Your Brand Villas',NULL,NULL,'reviews',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-19 13:53:42','2026-06-19 13:53:42',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/images/beach-closing.webp',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(17,'Our Condos','condos','10 design-forward stays with king beds, full kitchens, and jungle views from every balcony.',NULL,NULL,NULL,NULL,'Our Condos - Your Brand Villas',NULL,'Browse Your Brand Villas condos in Your Location.','condos',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-19 13:53:43','2026-06-19 13:53:43',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[{\"icon\":\"wind\",\"label\":\"A\\/C\"},{\"icon\":\"wifi\",\"label\":\"Fast Wifi\"},{\"icon\":\"car\",\"label\":\"Free Parking\"},{\"icon\":\"coffee\",\"label\":\"Full Kitchen\"},{\"icon\":\"bed\",\"label\":\"King Beds\"}]','[{\"key\":\"all\",\"label\":\"All\",\"info_strong\":\"All condos\",\"info_text\":\"Browse the full Your Brand condo collection.\"},{\"key\":\"1br\",\"label\":\"1 Bedroom\",\"info_strong\":\"1 Bedroom\",\"info_text\":\"Easy stays for couples, solo travelers, and small families.\"},{\"key\":\"2br\",\"label\":\"2 Bedroom\",\"info_strong\":\"2 Bedroom\",\"info_text\":\"More space for families or friends traveling together.\"},{\"key\":\"studio\",\"label\":\"Studio\",\"info_strong\":\"Studio\",\"info_text\":\"Compact retreats with the essentials close at hand.\"},{\"key\":\"ocean\",\"label\":\"Ocean View\",\"info_strong\":\"Ocean View\",\"info_text\":\"Pacific views from the cloud forest.\"},{\"key\":\"jungle\",\"label\":\"Jungle View\",\"info_strong\":\"Jungle View\",\"info_text\":\"Rainforest outlooks, birdsong, and green mornings.\"}]','10 condos available','Check Availability',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/images/condos-bg.webp',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(18,'Our Houses','houses','3 private homes ideal for families or groups who want the jungle without sacrificing comfort.',NULL,NULL,NULL,NULL,'Our Houses - Your Brand Villas',NULL,'Browse private Your Brand Villas houses in Your Location.','houses',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-19 13:53:43','2026-06-19 13:53:43',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Ready to Experience Paradise?','Choose your dates and continue through the same secure PMS booking flow.','Book Your Stay','/stays','Contact Us','/contact-us',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[{\"icon\":\"bed\",\"label\":\"1-3 Bedrooms\"},{\"icon\":\"home\",\"label\":\"Private Deck\"},{\"icon\":\"chef-hat\",\"label\":\"BBQ\"},{\"icon\":\"waves\",\"label\":\"Private Pool\"},{\"icon\":\"trees\",\"label\":\"Jungle Views\"}]',NULL,NULL,NULL,'Compare Our Houses','{\"headers\":[\"Feature\",\"Villa Cascada\",\"Heavenly Life\",\"Casita Del Eden\"],\"rows\":[{\"feature\":\"Private outdoor space\",\"values\":[\"Yes\",\"Yes\",\"Yes\"]},{\"feature\":\"Full kitchen\",\"values\":[\"Yes\",\"Yes\",\"Yes\"]},{\"feature\":\"Best for groups\",\"values\":[\"Yes\",\"Yes\",\"No\"]},{\"feature\":\"Best for couples\",\"values\":[\"No\",\"Yes\",\"Yes\"]}]}',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/images/house-bg.webp',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(19,'Our Experiences','experiences',NULL,NULL,NULL,NULL,NULL,'Our Experiences - Your Brand Villas',NULL,'Explore waterfalls, wildlife, whale watching, surfing, and paragliding near Your Brand Villas.','experiences',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-19 13:53:43','2026-06-19 13:53:43',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'St. Petersburg offers something for every kind of getaway - from award-winning beaches and waterfront parks to vibrant arts districts, local dining, and unforgettable outdoor adventures. Explore some of our favorite nearby attractions below, and be sure to visit our blog for more in-depth guides, insider tips, and curated recommendations to help you experience the very best of St. Pete during your stay.','[{\"title\":\"Nauyaca waterfall\",\"image\":\"front\\/themes\\/base\\/images\\/nau.webp\",\"alt\":\"Local Marina\",\"url\":\"https:\\/\\/nauyacawaterfallscostarica.com\\/en\\/\"},{\"title\":\"Tours - whale watching, snorkeling, scuba diving\",\"image\":\"front\\/themes\\/base\\/images\\/fotos.webp\",\"alt\":\"Jet Ski & Boat Rentals\",\"url\":\"https:\\/\\/ballenatourcostarica.com\\/\"},{\"title\":\"Surfing\",\"image\":\"front\\/themes\\/base\\/images\\/lessons.webp\",\"alt\":\"Nature Cruises\",\"url\":\"https:\\/\\/headhighsurfshop.com\\/\"},{\"title\":\"Para Gliding\",\"image\":\"front\\/themes\\/base\\/images\\/para.webp\",\"alt\":\"Nature Cruises\",\"url\":\"https:\\/\\/www.zionparagliding.com\\/\"}]',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'front/themes/base/images/zipline-canopy.webp',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(20,'Privacy Policy','privacy-policy',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'legal-page',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-19 13:55:51','2026-06-19 13:55:51',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(21,'Terms Of Service','terms-of-service',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'legal-page',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-19 13:58:55','2026-06-19 13:58:55',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(22,'Booking Confirmed','booking-confirmed',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'booking-confirmed',NULL,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-19 15:08:05','2026-06-19 15:08:05',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `cms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contactus_requests`
--

DROP TABLE IF EXISTS `contactus_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contactus_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(1025) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(1025) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(1025) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_request` varchar(1025) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `budget` varchar(1025) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guests` varchar(1025) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `property` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `property_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `check_in` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `check_out` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contactus_requests`
--

LOCK TABLES `contactus_requests` WRITE;
/*!40000 ALTER TABLE `contactus_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `contactus_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `countries`
--

DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso3` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numeric_code` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iso2` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phonecode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capital` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_symbol` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tld` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `native` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subregion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezones` text COLLATE utf8mb4_unicode_ci,
  `translations` text COLLATE utf8mb4_unicode_ci,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `emoji` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emojiU` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flag` int NOT NULL DEFAULT '1',
  `wikiDataId` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `footer_section` longtext COLLATE utf8mb4_unicode_ci,
  `header_section` longtext COLLATE utf8mb4_unicode_ci,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `meta_keywords` text COLLATE utf8mb4_unicode_ci,
  `meta_title` text COLLATE utf8mb4_unicode_ci,
  `seo_text` longtext COLLATE utf8mb4_unicode_ci,
  `seo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bannerImage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=234 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `countries`
--

LOCK TABLES `countries` WRITE;
/*!40000 ALTER TABLE `countries` DISABLE KEYS */;
INSERT INTO `countries` VALUES (233,'United States','USA','840','US','1','Washington','USD','United States dollar','$',NULL,'United States','Americas','Northern America',NULL,NULL,38.00000000,-97.00000000,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'united-states',NULL,'2026-06-19 13:53:28','2026-06-19 13:53:28');
/*!40000 ALTER TABLE `countries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` bigint unsigned DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `min_stay` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `max_uses` int DEFAULT NULL,
  `max_uses_per_customer` int DEFAULT '1',
  `min_order_amount` decimal(8,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_attachments`
--

DROP TABLE IF EXISTS `email_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_attachments_email_type_index` (`email_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_attachments`
--

LOCK TABLES `email_attachments` WRITE;
/*!40000 ALTER TABLE `email_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_send_log`
--

DROP TABLE IF EXISTS `email_send_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_send_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `email_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rendered_html` longtext COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '1',
  `error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_booking_type_created` (`booking_id`,`email_type`,`created_at`),
  CONSTRAINT `email_send_log_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `booking_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_send_log`
--

LOCK TABLES `email_send_log` WRITE;
/*!40000 ALTER TABLE `email_send_log` DISABLE KEYS */;
-- [scrubbed clean clone] email_send_log emptied
/*!40000 ALTER TABLE `email_send_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_templetes`
--

DROP TABLE IF EXISTS `email_templetes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_templetes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_templetes`
--

LOCK TABLES `email_templetes` WRITE;
/*!40000 ALTER TABLE `email_templetes` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_templetes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faq_categories`
--

DROP TABLE IF EXISTS `faq_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faq_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seo_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordering` int NOT NULL DEFAULT '0',
  `publish` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `faq_categories_seo_url_unique` (`seo_url`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faq_categories`
--

LOCK TABLES `faq_categories` WRITE;
/*!40000 ALTER TABLE `faq_categories` DISABLE KEYS */;
INSERT INTO `faq_categories` VALUES (1,'Booking','booking',1,'published','2026-06-19 13:53:41','2026-06-19 13:53:41'),(2,'Property','property',2,'published','2026-06-19 13:53:41','2026-06-19 13:53:41'),(3,'Amenities','amenities',3,'published','2026-06-19 13:53:41','2026-06-19 13:53:41'),(4,'Policies','policies',4,'published','2026-06-19 13:53:41','2026-06-19 13:53:41');
/*!40000 ALTER TABLE `faq_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faqs`
--

DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faqs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_ger` longtext COLLATE utf8mb4_unicode_ci,
  `answer_ger` longtext COLLATE utf8mb4_unicode_ci,
  `property_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faqs`
--

LOCK TABLES `faqs` WRITE;
/*!40000 ALTER TABLE `faqs` DISABLE KEYS */;
INSERT INTO `faqs` VALUES (1,'What time is check-in/check-out?','Check-in is at 3:00 PM and check-out is at 11:00 AM. Early check-in and late check-out may be available upon request, subject to availability. Please contact us at least 24 hours in advance.','booking',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(2,'Can I cancel or modify my reservation?','Yes. We offer free cancellation up to 7 days before check-in. Cancellations within 7 days are subject to the active cancellation policy. Modifications are subject to availability.','booking',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(3,'Is there a minimum stay requirement?','Most weekends require a minimum 2-night stay. During peak season, some dates may require a 3-5 night minimum. Contact us for specific requirements for your dates.','booking',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(4,'Do you require a security deposit?','A refundable security deposit may be required depending on the property and length of stay. Any deposit or hold is shown before booking.','booking',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(5,'What payment methods do you accept?','We accept major credit cards through the secure direct booking checkout. Payment options are shown clearly before confirmation.','payment',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(6,'When is payment due?','Payment timing depends on the selected dates, property, and booking rules. Any deposit, balance, or scheduled payment details are shown during checkout.','payment',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(7,'Are there any additional fees?','Cleaning fees, taxes, pet fees, or any property-specific charges are displayed in the quote before you confirm your booking.','payment',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(8,'How far is the property from the airport?','Drive times vary by arrival airport and road conditions. We can help with directions and local planning before arrival.','location',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(9,'Is there parking available?','Yes. Properties include convenient parking details in the arrival information sent before your stay.','location',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(10,'What is the nearest town or grocery store?','The nearest town and grocery options are a short drive away. We share local recommendations with guests before arrival.','location',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(11,'Is WiFi included?','Yes. The homes include WiFi suitable for remote work, streaming, and everyday travel needs.','amenities',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(12,'Do the condos have air conditioning?','Yes. The condos include air conditioning for comfortable Your Location stays.','amenities',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(13,'Are kitchens fully equipped?','Yes. Kitchens are stocked for real meals, including cookware, dishes, appliances, and everyday essentials.','amenities',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(14,'Is there a pool? Are there pool hours?','Pool access depends on the property. Where available, pool details and hours are included in the property information and pre-arrival notes.','amenities',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(15,'What activities are available nearby?','Guests can explore waterfalls, beaches, surf lessons, canopy tours, wildlife, dining, and day trips nearby.','activities',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(16,'Do you provide airport transportation?','Transportation can often be arranged or recommended. Contact us with your arrival plans and we will help coordinate options.','activities',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43'),(17,'Is it safe to drink the water?','Most guest areas have safe water access, and property-specific guidance is included in the arrival information.','activities',NULL,NULL,0,'2026-06-19 13:53:43','2026-06-19 13:53:43');
/*!40000 ALTER TABLE `faqs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ical_events`
--

DROP TABLE IF EXISTS `ical_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ical_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ppp_id` bigint unsigned NOT NULL DEFAULT '0',
  `ical_link` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_pid` bigint unsigned NOT NULL,
  `cat_id` bigint unsigned NOT NULL DEFAULT '0',
  `uid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `booking_status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ical_events`
--

LOCK TABLES `ical_events` WRITE;
/*!40000 ALTER TABLE `ical_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `ical_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ical_import_list`
--

DROP TABLE IF EXISTS `ical_import_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ical_import_list` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ical_link` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_id` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_refreshed_at` timestamp NULL DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ical_import_list`
--

LOCK TABLES `ical_import_list` WRITE;
/*!40000 ALTER TABLE `ical_import_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `ical_import_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `locations`
--

DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `seo_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shortDescription` longtext COLLATE utf8mb4_unicode_ci,
  `mediumDescription` longtext COLLATE utf8mb4_unicode_ci,
  `longDescription` longtext COLLATE utf8mb4_unicode_ci,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` text COLLATE utf8mb4_unicode_ci,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `templete` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bannerImage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publish` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `footer_section` longtext COLLATE utf8mb4_unicode_ci,
  `header_section` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `attraction_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `locations_seo_url_unique` (`seo_url`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `locations`
--

LOCK TABLES `locations` WRITE;
/*!40000 ALTER TABLE `locations` DISABLE KEYS */;
/*!40000 ALTER TABLE `locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lodgify_rate_calendar`
--

DROP TABLE IF EXISTS `lodgify_rate_calendar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lodgify_rate_calendar` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` int unsigned NOT NULL,
  `room_type_id` int unsigned NOT NULL,
  `date` date NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `min_stay` smallint unsigned NOT NULL DEFAULT '1',
  `max_stay` smallint unsigned NOT NULL DEFAULT '0',
  `price_per_day` decimal(10,4) NOT NULL,
  `price_per_additional_guest` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `additional_guests_starts_from` tinyint unsigned NOT NULL DEFAULT '0',
  `currency_code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `vat` decimal(5,2) NOT NULL DEFAULT '0.00',
  `is_vat_exclusive` tinyint(1) NOT NULL DEFAULT '0',
  `source_api_version` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'v2',
  `pms_updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lodgify_rate_band` (`property_id`,`room_type_id`,`date`,`is_default`,`min_stay`,`max_stay`),
  KEY `idx_lrc_room_date` (`property_id`,`room_type_id`,`date`),
  KEY `idx_lrc_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lodgify_rate_calendar`
--

LOCK TABLES `lodgify_rate_calendar` WRITE;
/*!40000 ALTER TABLE `lodgify_rate_calendar` DISABLE KEYS */;
/*!40000 ALTER TABLE `lodgify_rate_calendar` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mail_health_events`
--

DROP TABLE IF EXISTS `mail_health_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mail_health_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ses_message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mail_health_events_event_type_index` (`event_type`),
  KEY `mail_health_events_ses_message_id_index` (`ses_message_id`),
  KEY `mail_health_events_occurred_at_index` (`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mail_health_events`
--

LOCK TABLES `mail_health_events` WRITE;
/*!40000 ALTER TABLE `mail_health_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `mail_health_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mail_send_log`
--

DROP TABLE IF EXISTS `mail_send_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mail_send_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purpose` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ses_message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `retry_eligible` tinyint(1) NOT NULL DEFAULT '0',
  `retry_count` tinyint unsigned NOT NULL DEFAULT '0',
  `source_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`status`,`created_at`),
  KEY `mail_send_log_purpose_index` (`purpose`),
  KEY `mail_send_log_to_email_index` (`to_email`),
  KEY `mail_send_log_ses_message_id_index` (`ses_message_id`),
  KEY `mail_send_log_status_index` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mail_send_log`
--

LOCK TABLES `mail_send_log` WRITE;
/*!40000 ALTER TABLE `mail_send_log` DISABLE KEYS */;
-- [scrubbed clean clone] mail_send_log emptied
/*!40000 ALTER TABLE `mail_send_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mail_suppressions`
--

DROP TABLE IF EXISTS `mail_suppressions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mail_suppressions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bounce_subtype` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diagnostic` text COLLATE utf8mb4_unicode_ci,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'webhook',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mail_suppressions_email_unique` (`email`),
  KEY `mail_suppressions_reason_index` (`reason`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mail_suppressions`
--

LOCK TABLES `mail_suppressions` WRITE;
/*!40000 ALTER TABLE `mail_suppressions` DISABLE KEYS */;
/*!40000 ALTER TABLE `mail_suppressions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_10_12_000000_create_users_table',1),(2,'2014_10_12_100000_create_password_resets_table',1),(3,'2019_08_19_000000_create_failed_jobs_table',1),(4,'2019_12_14_000001_create_personal_access_tokens_table',1),(5,'2022_06_14_195410_create_activity_log_table',1),(6,'2022_06_14_195411_add_event_column_to_activity_log_table',1),(7,'2022_06_14_195412_add_batch_uuid_column_to_activity_log_table',1),(8,'2022_06_14_210436_create_properties_table',1),(9,'2022_06_14_210449_create_property_galleries_table',1),(10,'2022_06_14_210515_create_property_amenity_groups_table',1),(11,'2022_06_14_210523_create_property_amenities_table',1),(12,'2022_06_21_192652_create_property_rates_table',1),(13,'2022_09_16_223822_create_booking_requests_table',1),(14,'2026_04_28_000000_create_legacy_application_baseline',1),(15,'2026_04_29_000001_seed_nav_menu_basic_setting',1),(16,'2026_04_29_100000_add_home_fields_to_cms_table',1),(17,'2026_04_29_100001_add_map_url_and_caption_fields',1),(18,'2026_04_29_100002_create_direct_booking_benefits_table',1),(19,'2026_04_29_135302_add_panel_pills_to_cms_table',1),(20,'2026_04_29_200000_add_video_to_sliders_table',1),(21,'2026_04_29_210001_add_faq_heading_to_cms_table',1),(22,'2026_04_30_000000_add_home_page_fields_to_cms_table',1),(23,'2026_04_30_000001_add_type_to_properties_table',1),(24,'2026_04_30_010000_add_our_story_fields_to_cms_table',1),(25,'2026_04_30_020000_add_condos_page_fields',1),(26,'2026_04_30_030000_add_condos_detail_fields',1),(27,'2026_04_30_040000_add_houses_page_fields',1),(28,'2026_04_30_050000_add_house_detail_fields',1),(29,'2026_04_30_060000_add_experiences_page_fields',1),(30,'2026_04_30_070000_add_faq_page_fields',1),(31,'2026_04_30_080000_add_detail_fields_to_properties',1),(32,'2026_04_30_080001_migrate_detail_content_to_properties',1),(33,'2026_04_30_090000_create_property_repeater_tables',1),(34,'2026_04_30_090001_migrate_detail_json_to_repeaters',1),(35,'2026_04_30_100000_add_booking_page_fields_to_cms_table',1),(36,'2026_04_30_110000_fix_cms_cta_urls',1),(37,'2026_04_30_120000_add_contact_form_fields',1),(38,'2026_04_30_120001_seed_contact_page_content',1),(39,'2026_05_01_120000_add_booking_rules_to_properties',1),(40,'2026_05_01_120100_add_installment_override_to_properties',1),(41,'2026_05_01_120200_add_tax_discount_base_overrides_to_properties',1),(42,'2026_05_01_120300_add_guard_fields_to_coupons',1),(43,'2026_05_01_120400_seed_booking_basic_settings',1),(44,'2026_05_01_120500_seed_cancellation_policy_templates',1),(45,'2026_05_01_120600_canonicalize_booking_status',1),(46,'2026_05_01_140000_seed_visitor_flow_cms_pages',1),(47,'2026_05_02_000001_unify_property_pricing_columns',1),(48,'2026_05_02_000002_add_rate_phase1_property_overrides',1),(49,'2026_05_02_000003_seed_phase1_basic_settings',1),(50,'2026_05_02_010001_add_rental_agreement_audit_to_booking_requests',1),(51,'2026_05_02_010002_add_rental_agreement_template_to_properties',1),(52,'2026_05_02_010003_seed_rental_agreement_basic_settings',1),(53,'2026_05_02_020001_add_stay_info_columns_to_properties',1),(54,'2026_05_02_020002_add_lifecycle_email_sent_at_to_booking_requests',1),(55,'2026_05_02_020003_seed_stay_communications_basic_settings',1),(56,'2026_05_02_030001_add_review_submission_columns_to_testimonials',1),(57,'2026_05_02_030002_add_review_email_sent_at_to_booking_requests',1),(58,'2026_05_02_040001_seed_gateway_credentials_basic_settings',1),(59,'2026_05_02_050001_redesign_property_fees_table',1),(60,'2026_05_02_050002_drop_typed_addon_tables',1),(61,'2026_05_02_050003_simplify_pet_fee_columns_on_properties',1),(62,'2026_05_02_050004_drop_addon_json_columns_on_booking_requests',1),(63,'2026_05_02_050005_drop_addon_specific_tax_discount_flags',1),(64,'2026_05_02_230000_add_icon_to_amenity_tables',1),(65,'2026_05_03_010000_add_card_highlight_to_property_amenities',1),(66,'2026_05_03_020000_add_category_to_property_galleries',1),(67,'2026_05_03_022450_add_telemetry_to_ical_import_list',1),(68,'2026_05_03_072627_add_weekend_pricing_to_properties',1),(69,'2026_05_03_100743_drop_phase8_dead_tables',1),(70,'2026_05_03_120003_phase9_attractions_and_drop_slider_gallery',1),(71,'2026_05_04_010000_phase12_5_drop_dead_setting_keys',1),(72,'2026_05_04_020000_phase13c_seed_cancellation_email_defaults',1),(73,'2026_05_04_030000_phase13d_seed_editable_email_defaults',1),(74,'2026_05_04_040000_phase13e_seed_admin_alert_defaults',1),(75,'2026_05_04_050000_phase13i_add_email_failure_tracking',1),(76,'2026_05_04_060000_phase13j_add_invoice_table_to_email_defaults',1),(77,'2026_05_04_070000_phase13m_create_email_send_log_table',1),(78,'2026_05_04_080000_create_mail_send_log_table',1),(79,'2026_05_04_080001_create_mail_suppressions_table',1),(80,'2026_05_04_080002_create_mail_health_events_table',1),(81,'2026_05_04_090000_create_scrape_sources_table',1),(82,'2026_05_04_090001_create_scrape_runs_table',1),(83,'2026_05_04_090002_add_scraped_amenities_raw_to_properties',1),(84,'2026_05_04_100000_create_property_reviews_table',1),(85,'2026_05_04_110000_add_kind_to_scrape_runs',1),(86,'2026_05_04_120000_rename_vendor_setting_keys',1),(87,'2026_05_04_120100_rename_scrape_run_columns',1),(88,'2026_05_04_130000_seed_empty_email_templates',1),(89,'2026_05_04_140000_redesign_all_email_templates',1),(90,'2026_05_04_150000_seed_payment_receipt_templates',1),(91,'2026_05_07_010000_add_first_last_source_to_booking_requests',1),(92,'2026_05_07_010001_add_is_mandatory_to_property_fees',1),(93,'2026_05_07_010002_add_security_deposit_charge_flag_to_properties',1),(94,'2026_05_07_010003_drop_refundable_damage_fee_from_properties',1),(95,'2026_05_07_120000_create_email_attachments_table',1),(96,'2026_05_08_000001_add_rental_agreement_signing_mode',1),(97,'2026_05_12_000001_add_base_design_fields_to_cms',1),(98,'2026_05_28_000001_add_clone_qa_fields_to_cms',1),(99,'2026_05_28_000002_fix_better_items_icons',1),(100,'2026_05_28_000003_ensure_banner_image_on_properties',1),(101,'2026_06_10_000001_create_pms_tables',1),(102,'2026_06_10_000002_add_pms_columns_to_booking_requests',1),(103,'2026_06_10_000003_patch_pms_rooms_missing_columns',1),(104,'2026_06_10_000004_pms_supplemental_tables_and_columns',1),(105,'2026_06_10_100000_seed_base_demo_content',1),(106,'2026_06_12_220000_add_stripe_payment_method_id_to_scheduled_payments',1),(107,'2026_06_13_001000_add_is_developer_to_users_table',1),(108,'2026_06_13_002000_create_ops_runs_table',1),(109,'2026_06_13_003000_add_property_categories_and_pms_pet_limit',1),(110,'2026_06_18_000001_add_missing_scheduled_payment_runtime_columns',1),(111,'2026_06_18_010000_create_faq_categories_table',1),(112,'2026_06_19_000000_clean_base_faq_meta_title',1),(113,'2026_06_19_000001_add_rental_agreement_fields_to_pms_rooms',1),(114,'2026_06_19_000001_clean_base_faq_meta_description',1),(115,'2026_06_19_000002_reset_clone_starter_data',1),(116,'2026_06_19_000003_clear_operational_rows_after_workflow_fix',1),(117,'2026_06_19_000004_remove_retired_client_visual_fallbacks',1),(118,'2026_06_19_000005_ensure_clone_cms_edit_fields',1),(119,'2026_06_19_000006_ensure_cms_microcopy_and_heading_fields',1),(126,'2026_06_19_016000_create_our_teams_table_if_missing',2),(127,'2026_06_19_000007_fix_invalid_property_map_urls',3),(128,'2026_06_19_000008_fix_invalid_property_detail_map_iframe_urls',4);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ops_runs`
--

DROP TABLE IF EXISTS `ops_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ops_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `run_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `provider` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `command` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output` longtext COLLATE utf8mb4_unicode_ci,
  `error` longtext COLLATE utf8mb4_unicode_ci,
  `context` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ops_runs_type_status_created` (`run_type`,`status`,`created_at`),
  KEY `idx_ops_runs_provider_event_created` (`provider`,`event`,`created_at`),
  KEY `ops_runs_run_type_index` (`run_type`),
  KEY `ops_runs_source_index` (`source`),
  KEY `ops_runs_provider_index` (`provider`),
  KEY `ops_runs_event_index` (`event`),
  KEY `ops_runs_command_index` (`command`),
  KEY `ops_runs_status_index` (`status`),
  KEY `ops_runs_started_at_index` (`started_at`),
  KEY `ops_runs_finished_at_index` (`finished_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ops_runs`
--

LOCK TABLES `ops_runs` WRITE;
/*!40000 ALTER TABLE `ops_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `ops_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `our_teams`
--

DROP TABLE IF EXISTS `our_teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `our_teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bannerImage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `our_teams`
--

LOCK TABLES `our_teams` WRITE;
/*!40000 ALTER TABLE `our_teams` DISABLE KEYS */;
/*!40000 ALTER TABLE `our_teams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_refunds`
--

DROP TABLE IF EXISTS `payment_refunds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_refunds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned NOT NULL,
  `stripe_refund_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `pms_sync_status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `pms_refund_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_sync_error` text COLLATE utf8mb4_unicode_ci,
  `retry_count` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_refunds_stripe_refund_id_unique` (`stripe_refund_id`),
  KEY `payment_refunds_payment_id_index` (`payment_id`),
  KEY `payment_refunds_pms_sync_status_retry_count_index` (`pms_sync_status`,`retry_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_refunds`
--

LOCK TABLES `payment_refunds` WRITE;
/*!40000 ALTER TABLE `payment_refunds` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_refunds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint unsigned NOT NULL,
  `receipt_url` text COLLATE utf8mb4_unicode_ci,
  `customer_id` varchar(1025) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `balance_transaction` text COLLATE utf8mb4_unicode_ci,
  `tran_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `status` varchar(1025) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'stripe',
  `amount` double NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pms_payment_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_sync_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_sync_error` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_tran_id_unique` (`tran_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
-- [scrubbed clean clone] payments emptied
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_amenity_group_items`
--

DROP TABLE IF EXISTS `pms_amenity_group_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_amenity_group_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pms_property_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_room_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_source` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_amenity_group_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prefix` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bracket` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pms_amenity_group_items_pms_property_id_index` (`pms_property_id`),
  KEY `pms_amenity_group_items_pms_room_id_index` (`pms_room_id`),
  KEY `pms_amenity_group_items_pms_amenity_group_id_index` (`pms_amenity_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_amenity_group_items`
--

LOCK TABLES `pms_amenity_group_items` WRITE;
/*!40000 ALTER TABLE `pms_amenity_group_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_amenity_group_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_amenity_groups`
--

DROP TABLE IF EXISTS `pms_amenity_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_amenity_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pms_property_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_room_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_source` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pms_amenity_groups_pms_property_id_index` (`pms_property_id`),
  KEY `pms_amenity_groups_pms_room_id_index` (`pms_room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_amenity_groups`
--

LOCK TABLES `pms_amenity_groups` WRITE;
/*!40000 ALTER TABLE `pms_amenity_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_amenity_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_bookings`
--

DROP TABLE IF EXISTS `pms_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `arrival` date DEFAULT NULL,
  `departure` date DEFAULT NULL,
  `people` int DEFAULT NULL,
  `property_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_booking_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_source` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latest_webhook_event_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `booking_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest` longtext COLLATE utf8mb4_unicode_ci,
  `property_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rooms` longtext COLLATE utf8mb4_unicode_ci,
  `room_ids` longtext COLLATE utf8mb4_unicode_ci,
  `is_replied` tinyint(1) NOT NULL DEFAULT '0',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `date_deleted` timestamp NULL DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `total_paid` decimal(10,2) DEFAULT NULL,
  `amount_to_pay` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pms_bookings_property_id_index` (`property_id`),
  KEY `pms_bookings_pms_booking_id_index` (`pms_booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_bookings`
--

LOCK TABLES `pms_bookings` WRITE;
/*!40000 ALTER TABLE `pms_bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_calendar`
--

DROP TABLE IF EXISTS `pms_calendar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_calendar` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pms_source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pms_property_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pms_room_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `state` tinyint NOT NULL DEFAULT '-1' COMMENT '-1=unknown, 0=unavailable, 1=available',
  `inventory` smallint unsigned DEFAULT NULL,
  `nightly_rate` decimal(10,4) DEFAULT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_stay` tinyint unsigned DEFAULT NULL,
  `max_stay` smallint unsigned DEFAULT NULL,
  `closed_to_arrival` tinyint(1) NOT NULL DEFAULT '0',
  `closed_to_departure` tinyint(1) NOT NULL DEFAULT '0',
  `stop_sell` tinyint(1) NOT NULL DEFAULT '0',
  `pms_updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pms_calendar` (`pms_source`,`pms_room_id`,`date`),
  KEY `idx_room_date` (`pms_room_id`,`date`),
  KEY `idx_property_date` (`pms_property_id`,`date`),
  KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_calendar`
--

LOCK TABLES `pms_calendar` WRITE;
/*!40000 ALTER TABLE `pms_calendar` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_calendar` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_calendar_discrepancies`
--

DROP TABLE IF EXISTS `pms_calendar_discrepancies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_calendar_discrepancies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pms_source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pms_property_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pms_room_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date NOT NULL,
  `field` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `local_value` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `live_value` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `severity` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detected_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved` tinyint(1) NOT NULL DEFAULT '0',
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_disc_pms_date` (`pms_source`,`date`),
  KEY `idx_disc_severity` (`severity`,`resolved`),
  KEY `idx_disc_detected` (`detected_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_calendar_discrepancies`
--

LOCK TABLES `pms_calendar_discrepancies` WRITE;
/*!40000 ALTER TABLE `pms_calendar_discrepancies` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_calendar_discrepancies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_guesty_data`
--

DROP TABLE IF EXISTS `pms_guesty_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_guesty_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pms_property_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `raw_property_json` longtext COLLATE utf8mb4_unicode_ci,
  `raw_units_json` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_guesty_data`
--

LOCK TABLES `pms_guesty_data` WRITE;
/*!40000 ALTER TABLE `pms_guesty_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_guesty_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_hostaway_data`
--

DROP TABLE IF EXISTS `pms_hostaway_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_hostaway_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pms_property_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `raw_property_json` longtext COLLATE utf8mb4_unicode_ci,
  `raw_units_json` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_hostaway_data`
--

LOCK TABLES `pms_hostaway_data` WRITE;
/*!40000 ALTER TABLE `pms_hostaway_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_hostaway_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_hostfully_data`
--

DROP TABLE IF EXISTS `pms_hostfully_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_hostfully_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pms_property_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `raw_property_json` longtext COLLATE utf8mb4_unicode_ci,
  `raw_units_json` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_hostfully_data`
--

LOCK TABLES `pms_hostfully_data` WRITE;
/*!40000 ALTER TABLE `pms_hostfully_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_hostfully_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_images`
--

DROP TABLE IF EXISTS `pms_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pms_property_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_room_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_source` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pms_images_pms_property_id_index` (`pms_property_id`),
  KEY `pms_images_pms_room_id_index` (`pms_room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_images`
--

LOCK TABLES `pms_images` WRITE;
/*!40000 ALTER TABLE `pms_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_lodgify_data`
--

DROP TABLE IF EXISTS `pms_lodgify_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_lodgify_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pms_property_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `raw_property_json` longtext COLLATE utf8mb4_unicode_ci,
  `raw_units_json` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_lodgify_data`
--

LOCK TABLES `pms_lodgify_data` WRITE;
/*!40000 ALTER TABLE `pms_lodgify_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_lodgify_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_properties`
--

DROP TABLE IF EXISTS `pms_properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_properties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pms_property_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'guesty',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_addons` tinyint(1) NOT NULL DEFAULT '0',
  `has_agreement` tinyint(1) NOT NULL DEFAULT '0',
  `agreement_text` longtext COLLATE utf8mb4_unicode_ci,
  `agreement_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` decimal(3,1) DEFAULT NULL,
  `price_unit_in_days` tinyint(1) NOT NULL DEFAULT '1',
  `min_price` decimal(10,2) DEFAULT NULL,
  `original_min_price` decimal(10,2) DEFAULT NULL,
  `max_price` decimal(10,2) DEFAULT NULL,
  `original_max_price` decimal(10,2) DEFAULT NULL,
  `rooms` int DEFAULT NULL,
  `in_out_max_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` longtext COLLATE utf8mb4_unicode_ci,
  `meta_description` longtext COLLATE utf8mb4_unicode_ci,
  `is_home` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `map` longtext COLLATE utf8mb4_unicode_ci,
  `banner_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_plans` longtext COLLATE utf8mb4_unicode_ci,
  `location_id` bigint unsigned DEFAULT NULL,
  `review_widget` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pms_properties_pms_property_id_index` (`pms_property_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_properties`
--

LOCK TABLES `pms_properties` WRITE;
/*!40000 ALTER TABLE `pms_properties` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_properties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_rates`
--

DROP TABLE IF EXISTS `pms_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_source` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `single_date` date DEFAULT NULL,
  `single_date_timestamp` bigint unsigned DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `platform_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_price` decimal(10,2) DEFAULT NULL,
  `notes` longtext COLLATE utf8mb4_unicode_ci,
  `min_stay` int DEFAULT NULL,
  `base_min_stay` int DEFAULT NULL,
  `checkin_day` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkout_day` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_weekly` decimal(5,2) DEFAULT NULL,
  `discount_monthly` decimal(5,2) DEFAULT NULL,
  `rate_group_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pms_rates_property_id_index` (`property_id`),
  KEY `pms_rates_single_date_index` (`single_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_rates`
--

LOCK TABLES `pms_rates` WRITE;
/*!40000 ALTER TABLE `pms_rates` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_rooms`
--

DROP TABLE IF EXISTS `pms_rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pms_property_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_room_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'guesty',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `breakfast_included` tinyint(1) NOT NULL DEFAULT '0',
  `has_parking` tinyint(1) NOT NULL DEFAULT '0',
  `adults_only` tinyint(1) NOT NULL DEFAULT '0',
  `pets_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `show_additional_key_facts` tinyint(1) NOT NULL DEFAULT '0',
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_people` int DEFAULT NULL,
  `accommodates` int DEFAULT NULL,
  `units` int DEFAULT NULL,
  `has_wifi` tinyint(1) NOT NULL DEFAULT '0',
  `has_meal_plan` tinyint(1) NOT NULL DEFAULT '0',
  `bedrooms` int DEFAULT NULL,
  `bathrooms` decimal(4,1) DEFAULT NULL,
  `area_unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `area` decimal(10,2) DEFAULT NULL,
  `min_price` decimal(10,2) DEFAULT NULL,
  `original_min_price` decimal(10,2) DEFAULT NULL,
  `max_price` decimal(10,2) DEFAULT NULL,
  `original_max_price` decimal(10,2) DEFAULT NULL,
  `price_unit_in_days` tinyint(1) NOT NULL DEFAULT '1',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `images_json` longtext COLLATE utf8mb4_unicode_ci,
  `amenities_json` longtext COLLATE utf8mb4_unicode_ci,
  `seo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` longtext COLLATE utf8mb4_unicode_ci,
  `meta_description` longtext COLLATE utf8mb4_unicode_ci,
  `is_home` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `banner_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_id` bigint unsigned DEFAULT NULL,
  `review_widget` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `map` longtext COLLATE utf8mb4_unicode_ci,
  `ordering` int DEFAULT NULL,
  `custom_min_stay` int DEFAULT NULL,
  `min_stay` int DEFAULT NULL,
  `deposit_percent` decimal(5,2) DEFAULT NULL,
  `balance_due_days` int DEFAULT NULL,
  `api_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_pms` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `virtual_tour` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `house_rules` longtext COLLATE utf8mb4_unicode_ci,
  `cancellation_policy` longtext COLLATE utf8mb4_unicode_ci,
  `booking_policy` longtext COLLATE utf8mb4_unicode_ci,
  `rental_agreement_required` tinyint(1) NOT NULL DEFAULT '0',
  `rental_agreement_signing_mode` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inherit',
  `rental_agreement_template_html` longtext COLLATE utf8mb4_unicode_ci,
  `rental_agreement_house_rules_html` longtext COLLATE utf8mb4_unicode_ci,
  `key_facts_json` longtext COLLATE utf8mb4_unicode_ci,
  `optional_fees_json` longtext COLLATE utf8mb4_unicode_ci,
  `max_pets` int DEFAULT NULL,
  `pricing_json` longtext COLLATE utf8mb4_unicode_ci,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `property_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bed_config_json` longtext COLLATE utf8mb4_unicode_ci,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pms_rooms_pms_property_id_index` (`pms_property_id`),
  KEY `pms_rooms_pms_room_id_index` (`pms_room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_rooms`
--

LOCK TABLES `pms_rooms` WRITE;
/*!40000 ALTER TABLE `pms_rooms` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pms_unavailable_dates`
--

DROP TABLE IF EXISTS `pms_unavailable_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pms_unavailable_dates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `un_date` date DEFAULT NULL,
  `property_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_source` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pms_unavailable_dates_property_id_index` (`property_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pms_unavailable_dates`
--

LOCK TABLES `pms_unavailable_dates` WRITE;
/*!40000 ALTER TABLE `pms_unavailable_dates` DISABLE KEYS */;
/*!40000 ALTER TABLE `pms_unavailable_dates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `properties`
--

DROP TABLE IF EXISTS `properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `properties` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'property',
  `seo_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `heading` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `mobile` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_description` longtext COLLATE utf8mb4_unicode_ci,
  `long_description` longtext COLLATE utf8mb4_unicode_ci,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `cancellation_policy` longtext COLLATE utf8mb4_unicode_ci,
  `booking_policy` longtext COLLATE utf8mb4_unicode_ci,
  `notes` longtext COLLATE utf8mb4_unicode_ci,
  `scraped_amenities_raw` longtext COLLATE utf8mb4_unicode_ci,
  `bedroom` bigint NOT NULL DEFAULT '0',
  `bathroom` bigint DEFAULT NULL,
  `beds` bigint DEFAULT NULL,
  `sleeps` bigint DEFAULT NULL,
  `area` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_bath` bigint DEFAULT NULL,
  `half_bath` bigint DEFAULT NULL,
  `spaces` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `feature_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cleaning_fee` decimal(8,2) DEFAULT NULL,
  `heating_swimming_pool_fee` decimal(8,2) DEFAULT NULL,
  `pet_fee` decimal(8,2) DEFAULT NULL,
  `tax` decimal(8,2) DEFAULT NULL,
  `propane_gas` decimal(8,2) DEFAULT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'true',
  `meta_title` longtext COLLATE utf8mb4_unicode_ci,
  `meta_keywords` longtext COLLATE utf8mb4_unicode_ci,
  `meta_description` longtext COLLATE utf8mb4_unicode_ci,
  `header_section` longtext COLLATE utf8mb4_unicode_ci,
  `footer_section` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `standard_rate` decimal(10,2) DEFAULT NULL,
  `weekend_rate` int DEFAULT NULL,
  `weekend_days` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_home` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `instant_booking_button` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `ordering` int NOT NULL DEFAULT '0',
  `banner_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `property_view` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_amenities` longtext COLLATE utf8mb4_unicode_ci,
  `tags` longtext COLLATE utf8mb4_unicode_ci,
  `pms_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_room_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_trending` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `is_top` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `is_feature` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `is_bestseller` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `is_sale` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `is_hot` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `location_id` bigint unsigned DEFAULT NULL,
  `property_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_stay` int DEFAULT NULL,
  `map` mediumtext COLLATE utf8mb4_unicode_ci,
  `checkin` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkout` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bed_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rental_aggrement_attachment` text COLLATE utf8mb4_unicode_ci,
  `rental_agreement_source_doc_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `welcome_package_description` longtext COLLATE utf8mb4_unicode_ci,
  `welcome_package_attachment` text COLLATE utf8mb4_unicode_ci,
  `api_id` text COLLATE utf8mb4_unicode_ci,
  `api_pms` text COLLATE utf8mb4_unicode_ci,
  `king_beds` int NOT NULL DEFAULT '0',
  `queen_beds` int NOT NULL DEFAULT '0',
  `extra_bed` varchar(1025) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vrbo_link` text COLLATE utf8mb4_unicode_ci,
  `airbnb_link` text COLLATE utf8mb4_unicode_ci,
  `checkin_day` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_check_in_instructions` longtext COLLATE utf8mb4_unicode_ci,
  `checkin_arrival_window` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkin_door_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkin_wifi_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkin_wifi_password` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkin_parking_instructions` text COLLATE utf8mb4_unicode_ci,
  `checkin_host_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkin_host_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkin_emergency_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkout_departure_time` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkout_key_return` text COLLATE utf8mb4_unicode_ci,
  `checkout_cleaning_checklist` longtext COLLATE utf8mb4_unicode_ci,
  `checkout_notes` longtext COLLATE utf8mb4_unicode_ci,
  `welcome_email_send_day` int unsigned DEFAULT NULL,
  `checkin_email_send_day` int unsigned DEFAULT NULL,
  `checkout_email_send_day` int unsigned DEFAULT NULL,
  `checkout_day` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pet_fee_interval` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_pet` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_fee` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_of_guest` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `heating_pool_fee` double DEFAULT NULL,
  `pet_fee_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'taxable',
  `heating_pool_fee_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'taxable',
  `firebase_base_price` double DEFAULT NULL,
  `bedroom_text` text COLLATE utf8mb4_unicode_ci,
  `bathroom_text` text COLLATE utf8mb4_unicode_ci,
  `beds_text` text COLLATE utf8mb4_unicode_ci,
  `sleeps_text` text COLLATE utf8mb4_unicode_ci,
  `rates` longtext COLLATE utf8mb4_unicode_ci,
  `get_quote_description` longtext COLLATE utf8mb4_unicode_ci,
  `max_rv_hook_up` int NOT NULL DEFAULT '0',
  `review_text` longtext COLLATE utf8mb4_unicode_ci,
  `is_parent` int DEFAULT NULL,
  `box_heading` text COLLATE utf8mb4_unicode_ci,
  `box_description` text COLLATE utf8mb4_unicode_ci,
  `security_deposit_amount` int DEFAULT NULL,
  `security_deposit_text` text COLLATE utf8mb4_unicode_ci,
  `security_deposit_charge_at_booking` tinyint(1) NOT NULL DEFAULT '0',
  `visitor_rate_info` text COLLATE utf8mb4_unicode_ci,
  `total_amount_include_text` text COLLATE utf8mb4_unicode_ci,
  `rating` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_category_heading` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_meta_rating` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_meta_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_host_heading` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_about_heading` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_sleep_heading` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_amenities_heading` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_offcanvas_reviews_heading` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_share_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_booking_disclaimer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_map_heading` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_host_meta` text COLLATE utf8mb4_unicode_ci,
  `detail_gallery_images` longtext COLLATE utf8mb4_unicode_ci,
  `detail_highlights` longtext COLLATE utf8mb4_unicode_ci,
  `detail_about_text` longtext COLLATE utf8mb4_unicode_ci,
  `detail_sleep_cards` longtext COLLATE utf8mb4_unicode_ci,
  `detail_amenities_list` longtext COLLATE utf8mb4_unicode_ci,
  `detail_offcanvas_amenities` longtext COLLATE utf8mb4_unicode_ci,
  `detail_map_iframe_src` longtext COLLATE utf8mb4_unicode_ci,
  `instant_booking_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `rental_agreement_required` tinyint(1) NOT NULL DEFAULT '0',
  `rental_agreement_signing_mode` enum('inherit','pre_payment','post_confirmation') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'inherit',
  `rental_agreement_template_html` longtext COLLATE utf8mb4_unicode_ci,
  `rental_agreement_house_rules_html` longtext COLLATE utf8mb4_unicode_ci,
  `cancellation_policy_type` enum('flexible','moderate','strict','firm','super_strict','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'moderate',
  `max_stay` int DEFAULT NULL,
  `accepts_pets` tinyint(1) NOT NULL DEFAULT '0',
  `smoking_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `parties_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `wheelchair_accessible` tinyint(1) NOT NULL DEFAULT '0',
  `installment_plan` enum('full','two','three') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installment_2_days` int DEFAULT NULL,
  `installment_2_percent` int DEFAULT NULL,
  `installment_2_min_lead_days` int DEFAULT NULL,
  `installment_3_days` int DEFAULT NULL,
  `installment_3_percent` int DEFAULT NULL,
  `installment_3_min_lead_days` int DEFAULT NULL,
  `tax_applies_to_nights` tinyint(1) DEFAULT NULL,
  `tax_applies_to_cleaning_fee` tinyint(1) DEFAULT NULL,
  `tax_applies_to_pet_fee` tinyint(1) DEFAULT NULL,
  `tax_applies_to_extra_guest_fee` tinyint(1) DEFAULT NULL,
  `discount_applies_to_nights` tinyint(1) DEFAULT NULL,
  `discount_applies_to_cleaning_fee` tinyint(1) DEFAULT NULL,
  `discount_applies_to_pet_fee` tinyint(1) DEFAULT NULL,
  `discount_applies_to_extra_guest_fee` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `properties`
--

LOCK TABLES `properties` WRITE;
/*!40000 ALTER TABLE `properties` DISABLE KEYS */;
-- [scrubbed clean clone] properties emptied
/*!40000 ALTER TABLE `properties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `properties_rates_group`
--

DROP TABLE IF EXISTS `properties_rates_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `properties_rates_group` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `start_date_timestamp` int NOT NULL,
  `end_date` date NOT NULL,
  `end_date_timestamp` int NOT NULL,
  `price` decimal(8,2) DEFAULT NULL,
  `is_available` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `platform_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'airbnb',
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `base_price` decimal(8,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `min_stay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_min_stay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_weekly` varchar(1025) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_monthly` varchar(1025) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_of_price` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `type_of_price` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `monday_price` double DEFAULT NULL,
  `tuesday_price` double DEFAULT NULL,
  `wednesday_price` double DEFAULT NULL,
  `thrusday_price` double DEFAULT NULL,
  `friday_price` double DEFAULT NULL,
  `saturday_price` double DEFAULT NULL,
  `sunday_price` double DEFAULT NULL,
  `checkin_day` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkout_day` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `properties_rates_group`
--

LOCK TABLES `properties_rates_group` WRITE;
/*!40000 ALTER TABLE `properties_rates_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `properties_rates_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_amenities`
--

DROP TABLE IF EXISTS `property_amenities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_amenities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_amenity_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'true',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_card_highlight` tinyint(1) NOT NULL DEFAULT '0',
  `sorting` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_amenities`
--

LOCK TABLES `property_amenities` WRITE;
/*!40000 ALTER TABLE `property_amenities` DISABLE KEYS */;
-- [scrubbed clean clone] property_amenities emptied
/*!40000 ALTER TABLE `property_amenities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_amenity_groups`
--

DROP TABLE IF EXISTS `property_amenity_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_amenity_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'true',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_amenity_groups`
--

LOCK TABLES `property_amenity_groups` WRITE;
/*!40000 ALTER TABLE `property_amenity_groups` DISABLE KEYS */;
-- [scrubbed clean clone] property_amenity_groups emptied
/*!40000 ALTER TABLE `property_amenity_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_amenity_highlights`
--

DROP TABLE IF EXISTS `property_amenity_highlights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_amenity_highlights` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `emoji` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` int NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'true',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_amenity_highlights_property_id_index` (`property_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_amenity_highlights`
--

LOCK TABLES `property_amenity_highlights` WRITE;
/*!40000 ALTER TABLE `property_amenity_highlights` DISABLE KEYS */;
-- [scrubbed clean clone] property_amenity_highlights emptied
/*!40000 ALTER TABLE `property_amenity_highlights` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_categories`
--

DROP TABLE IF EXISTS `property_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `property_categories_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_categories`
--

LOCK TABLES `property_categories` WRITE;
/*!40000 ALTER TABLE `property_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `property_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_category_property`
--

DROP TABLE IF EXISTS `property_category_property`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_category_property` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_category_id` bigint unsigned NOT NULL,
  `property_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `property_category_property_unique` (`property_category_id`,`property_id`),
  KEY `property_category_property_property_id_foreign` (`property_id`),
  CONSTRAINT `property_category_property_property_category_id_foreign` FOREIGN KEY (`property_category_id`) REFERENCES `property_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `property_category_property_property_id_foreign` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_category_property`
--

LOCK TABLES `property_category_property` WRITE;
/*!40000 ALTER TABLE `property_category_property` DISABLE KEYS */;
/*!40000 ALTER TABLE `property_category_property` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_fees`
--

DROP TABLE IF EXISTS `property_fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_fees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `billing_type` enum('one_time','per_day') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'one_time',
  `taxable` tinyint(1) NOT NULL DEFAULT '1',
  `applies_discount` tinyint(1) NOT NULL DEFAULT '0',
  `is_mandatory` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_fees_property_id_index` (`property_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_fees`
--

LOCK TABLES `property_fees` WRITE;
/*!40000 ALTER TABLE `property_fees` DISABLE KEYS */;
/*!40000 ALTER TABLE `property_fees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_galleries`
--

DROP TABLE IF EXISTS `property_galleries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_galleries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'true',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sorting` bigint NOT NULL DEFAULT '0',
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_galleries`
--

LOCK TABLES `property_galleries` WRITE;
/*!40000 ALTER TABLE `property_galleries` DISABLE KEYS */;
-- [scrubbed clean clone] property_galleries emptied
/*!40000 ALTER TABLE `property_galleries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_highlights`
--

DROP TABLE IF EXISTS `property_highlights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_highlights` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `sorting` int NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'true',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_highlights_property_id_index` (`property_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_highlights`
--

LOCK TABLES `property_highlights` WRITE;
/*!40000 ALTER TABLE `property_highlights` DISABLE KEYS */;
-- [scrubbed clean clone] property_highlights emptied
/*!40000 ALTER TABLE `property_highlights` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_rates`
--

DROP TABLE IF EXISTS `property_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate_group_id` bigint unsigned DEFAULT NULL,
  `single_date` date NOT NULL,
  `single_date_timestamp` bigint NOT NULL,
  `price` decimal(8,2) NOT NULL,
  `is_available` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `platform_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'airbnb',
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `base_price` decimal(8,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `min_stay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_min_stay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `checkin_day` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkout_day` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_weekly` int DEFAULT NULL,
  `discount_monthly` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_property_rates_group` (`rate_group_id`),
  KEY `idx_property_rates_date` (`property_id`,`single_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_rates`
--

LOCK TABLES `property_rates` WRITE;
/*!40000 ALTER TABLE `property_rates` DISABLE KEYS */;
-- [scrubbed clean clone] property_rates emptied
/*!40000 ALTER TABLE `property_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_reviews`
--

DROP TABLE IF EXISTS `property_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'airbnb',
  `external_review_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_hash` char(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewer_photo_url` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewer_photo_local` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewer_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` tinyint unsigned DEFAULT NULL,
  `comment` longtext COLLATE utf8mb4_unicode_ci,
  `comment_translated` longtext COLLATE utf8mb4_unicode_ci,
  `language` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_text` longtext COLLATE utf8mb4_unicode_ci,
  `response_date` timestamp NULL DEFAULT NULL,
  `review_date` timestamp NULL DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'imported',
  `raw_payload` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_property_status_date` (`property_id`,`status`,`review_date`),
  KEY `idx_source_external` (`source`,`external_review_id`),
  KEY `idx_property_content_hash` (`property_id`,`content_hash`),
  KEY `property_reviews_property_id_index` (`property_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_reviews`
--

LOCK TABLES `property_reviews` WRITE;
/*!40000 ALTER TABLE `property_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `property_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_room_item_images`
--

DROP TABLE IF EXISTS `property_room_item_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_room_item_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sub_room_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_room_image` varchar(1025) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_room_item_images`
--

LOCK TABLES `property_room_item_images` WRITE;
/*!40000 ALTER TABLE `property_room_item_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `property_room_item_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_room_items`
--

DROP TABLE IF EXISTS `property_room_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_room_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sub_room_title` varchar(1025) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_room_sub_title` varchar(1025) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_room_description` longtext COLLATE utf8mb4_unicode_ci,
  `sub_room_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `room_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_room_items`
--

LOCK TABLES `property_room_items` WRITE;
/*!40000 ALTER TABLE `property_room_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `property_room_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_rooms`
--

DROP TABLE IF EXISTS `property_rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_title` varchar(1025) COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_sub_title` varchar(1025) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `room_description` longtext COLLATE utf8mb4_unicode_ci,
  `room_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `property_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_rooms`
--

LOCK TABLES `property_rooms` WRITE;
/*!40000 ALTER TABLE `property_rooms` DISABLE KEYS */;
/*!40000 ALTER TABLE `property_rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_sleep_cards`
--

DROP TABLE IF EXISTS `property_sleep_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_sleep_cards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text` text COLLATE utf8mb4_unicode_ci,
  `sorting` int NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'true',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_sleep_cards_property_id_index` (`property_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_sleep_cards`
--

LOCK TABLES `property_sleep_cards` WRITE;
/*!40000 ALTER TABLE `property_sleep_cards` DISABLE KEYS */;
-- [scrubbed clean clone] property_sleep_cards emptied
/*!40000 ALTER TABLE `property_sleep_cards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `property_spaces`
--

DROP TABLE IF EXISTS `property_spaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `property_spaces` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `property_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `space_name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `space_image` varchar(1025) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `space_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `property_spaces`
--

LOCK TABLES `property_spaces` WRITE;
/*!40000 ALTER TABLE `property_spaces` DISABLE KEYS */;
/*!40000 ALTER TABLE `property_spaces` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scheduled_payments`
--

DROP TABLE IF EXISTS `scheduled_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scheduled_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `booking_request_id` bigint unsigned NOT NULL,
  `pms_source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pms_booking_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_customer_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_payment_method_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `due_date` date NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `charged_at` timestamp NULL DEFAULT NULL,
  `stripe_charge_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `pms_notified` tinyint(1) NOT NULL DEFAULT '0',
  `pms_notify_error` text COLLATE utf8mb4_unicode_ci,
  `pms_payment_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reminder_sent` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sp_booking_charge_unique` (`booking_request_id`,`stripe_charge_id`),
  KEY `scheduled_payments_status_due_date_index` (`status`,`due_date`),
  KEY `scheduled_payments_booking_request_id_index` (`booking_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scheduled_payments`
--

LOCK TABLES `scheduled_payments` WRITE;
/*!40000 ALTER TABLE `scheduled_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `scheduled_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scrape_runs`
--

DROP TABLE IF EXISTS `scrape_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scrape_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scrape_source_id` bigint unsigned NOT NULL,
  `provider_run_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_actor_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'listing',
  `provider_dataset_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `raw_payload` longtext COLLATE utf8mb4_unicode_ci,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scrape_runs_apify_run_id_unique` (`provider_run_id`),
  KEY `scrape_runs_scrape_source_id_foreign` (`scrape_source_id`),
  KEY `idx_run_status_created` (`status`,`created_at`),
  KEY `scrape_runs_kind_index` (`kind`),
  CONSTRAINT `scrape_runs_scrape_source_id_foreign` FOREIGN KEY (`scrape_source_id`) REFERENCES `scrape_sources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scrape_runs`
--

LOCK TABLES `scrape_runs` WRITE;
/*!40000 ALTER TABLE `scrape_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `scrape_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scrape_sources`
--

DROP TABLE IF EXISTS `scrape_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scrape_sources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source_url` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'airbnb',
  `property_id` bigint unsigned DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `last_scraped_at` timestamp NULL DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_platform_status` (`platform`,`status`),
  KEY `scrape_sources_property_id_index` (`property_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scrape_sources`
--

LOCK TABLES `scrape_sources` WRITE;
/*!40000 ALTER TABLE `scrape_sources` DISABLE KEYS */;
/*!40000 ALTER TABLE `scrape_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `states`
--

DROP TABLE IF EXISTS `states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `states` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` bigint unsigned NOT NULL,
  `country_code` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fips_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iso2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `flag` int NOT NULL DEFAULT '1',
  `wikiDataId` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `footer_section` longtext COLLATE utf8mb4_unicode_ci,
  `header_section` longtext COLLATE utf8mb4_unicode_ci,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `meta_keywords` text COLLATE utf8mb4_unicode_ci,
  `meta_title` text COLLATE utf8mb4_unicode_ci,
  `seo_text` longtext COLLATE utf8mb4_unicode_ci,
  `bannerImage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_featured` int NOT NULL DEFAULT '0',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `states`
--

LOCK TABLES `states` WRITE;
/*!40000 ALTER TABLE `states` DISABLE KEYS */;
INSERT INTO `states` VALUES (1,'Arizona',233,'US',NULL,'AZ',NULL,34.04892800,-111.09373100,1,NULL,'arizona',NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,'2026-06-19 13:53:28','2026-06-19 13:53:28');
/*!40000 ALTER TABLE `states` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `testimonials`
--

DROP TABLE IF EXISTS `testimonials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `testimonials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stay_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `score` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `property_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `booking_id` bigint unsigned DEFAULT NULL,
  `submission_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` tinyint unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `submitted_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `end_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_home` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'false',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pms_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pms_review_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `testimonials_submission_token_unique` (`submission_token`),
  KEY `testimonials_booking_id_index` (`booking_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `testimonials`
--

LOCK TABLES `testimonials` WRITE;
/*!40000 ALTER TABLE `testimonials` DISABLE KEYS */;
-- [scrubbed clean clone] testimonials emptied
/*!40000 ALTER TABLE `testimonials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_developer` tinyint(1) NOT NULL DEFAULT '0',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Sygnius Digital','info@sygniusdigital.com','2026-06-19 13:53:41','$2y$10$GnvO.s7IUnuWiWv/OFFoe.4D3AZtNiJsqLDejmHJY7gYy66C2/TyO',1,'nSEAeCYqbboYFMa1jGtMVGY78nHH4CQTufeFmWhcKRAQL3AEnGujnndXFWS1','2026-06-19 13:53:41','2026-06-19 13:53:44');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'master_clean'
--
/*!50112 SET @disable_bulk_load = IF (@is_rocksdb_supported, 'SET SESSION rocksdb_bulk_load = @old_rocksdb_bulk_load', 'SET @dummy_rocksdb_bulk_load = 0') */;
/*!50112 PREPARE s FROM @disable_bulk_load */;
/*!50112 EXECUTE s */;
/*!50112 DEALLOCATE PREPARE s */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-19 19:20:01

-- Blank all CMS marketing copy (keep page structure). Master clean clone.
UPDATE `cms` SET `shortDescription`=NULL, `mediumDescription`=NULL, `longDescription`=NULL, `description`=NULL, `image`=NULL, `meta_title`=NULL, `meta_keywords`=NULL, `meta_description`=NULL, `bannerImage`=NULL, `footer_section`=NULL, `header_section`=NULL, `seo_section`=NULL, `image_2`=NULL, `image_3`=NULL, `faq_title`=NULL, `faq_heading`=NULL, `whatsapp_url`=NULL, `faq_desction`=NULL, `faq_image`=NULL, `strip_title`=NULL, `strip_desction`=NULL, `strip_image`=NULL, `about_image1`=NULL, `about_image2`=NULL, `owner_image`=NULL, `gallery_background`=NULL, `property_background`=NULL, `about_background`=NULL, `gallery_heading`=NULL, `hero_eyebrow`=NULL, `hero_heading`=NULL, `hero_sub`=NULL, `story_eyebrow`=NULL, `story_title`=NULL, `story_body_2`=NULL, `retreats_eyebrow`=NULL, `retreats_title`=NULL, `better_title`=NULL, `better_sub`=NULL, `explore_eyebrow`=NULL, `explore_title`=NULL, `explore_sub`=NULL, `explore_map_url`=NULL, `faq_eyebrow`=NULL, `rooftop_features`=NULL, `living_pills`=NULL, `beach_pills`=NULL, `hero_heading_gold`=NULL, `hero_video_src`=NULL, `hero_cta1_label`=NULL, `hero_cta1_url`=NULL, `hero_cta2_label`=NULL, `hero_cta2_url`=NULL, `condos_heading`=NULL, `condos_cta_label`=NULL, `condos_cta_url`=NULL, `condo_tabs`=NULL, `houses_heading`=NULL, `houses_card_eyebrow`=NULL, `houses_card_heading`=NULL, `houses_card_desc`=NULL, `houses_card_tags`=NULL, `houses_card_cta_label`=NULL, `houses_card_cta_url`=NULL, `experiences_eyebrow`=NULL, `experiences_heading`=NULL, `experiences_desc`=NULL, `experiences_cta_label`=NULL, `experiences_cta_url`=NULL, `story_image`=NULL, `story_body`=NULL, `story_cta_label`=NULL, `story_cta_url`=NULL, `amenities_heading`=NULL, `amenities_list`=NULL, `amenities_cta1_label`=NULL, `amenities_cta1_url`=NULL, `amenities_cta2_label`=NULL, `amenities_cta2_url`=NULL, `location_heading`=NULL, `location_body`=NULL, `location_distances`=NULL, `location_cta_label`=NULL, `location_cta_url`=NULL, `location_image`=NULL, `location_caption_eyebrow`=NULL, `location_caption_text`=NULL, `testimonials_heading`=NULL, `cta_section_heading`=NULL, `cta_section_body`=NULL, `cta_section_cta1_label`=NULL, `cta_section_cta1_url`=NULL, `cta_section_cta2_label`=NULL, `cta_section_cta2_url`=NULL, `cta_section_response_note`=NULL, `story_block_label`=NULL, `story_block_heading`=NULL, `story_block_body`=NULL, `story_block_image`=NULL, `story_block_author_name`=NULL, `story_block_author_role`=NULL, `quote_text`=NULL, `milestones_label`=NULL, `milestones_heading`=NULL, `milestones_list`=NULL, `stats_list`=NULL, `feature_ribbon_items`=NULL, `filter_categories`=NULL, `condos_count_text`=NULL, `check_avail_heading`=NULL, `compare_heading`=NULL, `compare_table_data`=NULL, `houses_subtitle_heading`=NULL, `experiences_intro`=NULL, `experience_grid_items`=NULL, `quick_answers`=NULL, `quick_answers_heading`=NULL, `booking_empty_message`=NULL, `getting_here_steps`=NULL, `getting_here_map_iframe_src`=NULL, `getting_here_directions_url`=NULL, `hero_carousel_images`=NULL, `better_items`=NULL, `gallery_eyebrow`=NULL, `gallery_title`=NULL, `elfsight_widget_id`=NULL, `story_image_accent`=NULL, `home_gallery_images`=NULL, `inner_banner_image`=NULL, `hero_badges`=NULL, `gallery_cards`=NULL, `ui_labels`=NULL, `fw_section_sub`=NULL, `fw_banner_title`=NULL, `fw_section_eyebrow`=NULL, `fw_section_title`=NULL;

-- Scrub secrets + leftover brand/contact copy from settings. Master clean clone.
UPDATE `basic_settings` SET `value`='' WHERE `name` IN ('stripe_publish_key','stripe_secret_key','smtp_pass','smtp_user','smtp_host','smtp_port','paypal_access_token','square_ACCESS_TOKEN','square_Application_id','square_location_id','AUTHORIZED_MERCHANT_LOGIN_ID','AUTHORIZED_MERCHANT_TRANSACTION_KEY','AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY','lodgify_api_key','lodgify_webhook_token','hostfully_api_key','hostfully_agency_uid','guesty_client_id','guesty_api_key','hostaway_account_id','hostaway_api_key','footer_brand_desc','footer_brand_sub','footer_brand_tagline','copyright','about','owner_name','address','mobile','mobile1','mobile2','whatsapp_number','whatsapp_url','map','map_embed_url','map_open_url','facebook','instagram','yelp_url','airbnb_url','airbnb_booking_url','book_external_url','property-heading-text','property-subheading-text','testimonial-heading-text','testimonial-subheading-text','attraction-heading-text','attraction-subheading-text','faq-heading-text','faq-subheading-text','home_video','default_header_image','admin_email_primary','contact_us_receiving_mail','newsletter_receiving_mail','booking_receiving_mail','payment_receiving_mail','rental_aggrement_receiving_mail','welcome_package_receiving_mail','reminder_package_receiving_mail','review_receiving_mail','cancel_receiving_mail');

-- Reset nav menu to a clean minimal default. Master clean clone.
UPDATE `basic_settings` SET `value`='[{"label":"Home","url":"/","side":"left","type":"link"},{"label":"Stays","url":"/stays","side":"left","type":"link"},{"label":"Contact","url":"/contact-us","side":"right","type":"link"}]' WHERE `name`='nav_menu';


-- Seed editable nav CTA (label + url) so nothing is hardcoded. Master clean clone.
DELETE FROM `basic_settings` WHERE `name` IN ('nav_cta_label','nav_cta_url');
INSERT INTO `basic_settings` (`name`,`value`,`created_at`,`updated_at`) VALUES ('nav_cta_label','Book Now',NOW(),NOW()),('nav_cta_url','/stays',NOW(),NOW());


-- Remove Joe design/content CMS pages from the master (keep only common: booking-flow, legal, home).
DELETE FROM `cms` WHERE `seo_url` IN ('condos','houses','experiences','gallery','attractions','reviews','things-to-do','our-story','faqs','contact-us');
