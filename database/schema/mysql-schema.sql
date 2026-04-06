/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `character_static`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `character_static` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `static_id` bigint unsigned NOT NULL,
  `role` enum('main','alt') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `character_static_character_id_foreign` (`character_id`),
  KEY `character_static_static_id_foreign` (`static_id`),
  CONSTRAINT `character_static_character_id_foreign` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `character_static_static_id_foreign` FOREIGN KEY (`static_id`) REFERENCES `statics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `character_static_specs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `character_static_specs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `static_id` bigint unsigned NOT NULL,
  `spec_id` int unsigned NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `character_static_specs_character_id_static_id_spec_id_unique` (`character_id`,`static_id`,`spec_id`),
  KEY `character_static_specs_static_id_foreign` (`static_id`),
  KEY `character_static_specs_spec_id_foreign` (`spec_id`),
  CONSTRAINT `character_static_specs_character_id_foreign` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `character_static_specs_spec_id_foreign` FOREIGN KEY (`spec_id`) REFERENCES `specializations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `character_static_specs_static_id_foreign` FOREIGN KEY (`static_id`) REFERENCES `statics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `characters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `characters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `realm_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `playable_class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `playable_race` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` int NOT NULL,
  `equipped_item_level` int DEFAULT NULL,
  `ilvl` int DEFAULT NULL,
  `mythic_rating` decimal(8,2) DEFAULT NULL,
  `active_spec` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_level` int DEFAULT NULL,
  `avatar_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw_bnet_data` json DEFAULT NULL,
  `raw_raiderio_data` json DEFAULT NULL,
  `raw_wcl_data` json DEFAULT NULL,
  `compiled_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `characters_user_id_foreign` (`user_id`),
  CONSTRAINT `characters_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
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
DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `items` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
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
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
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
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_tactical_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_tactical_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tactical_report_id` bigint unsigned NOT NULL,
  `character_id` bigint unsigned NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `personal_tactical_reports_tactical_report_id_foreign` (`tactical_report_id`),
  KEY `personal_tactical_reports_character_id_foreign` (`character_id`),
  CONSTRAINT `personal_tactical_reports_character_id_foreign` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `personal_tactical_reports_tactical_report_id_foreign` FOREIGN KEY (`tactical_report_id`) REFERENCES `tactical_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `price_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `price_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `price` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `price_snapshots_item_id_foreign` (`item_id`),
  CONSTRAINT `price_snapshots_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `raid_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `raid_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `raid_event_id` bigint unsigned NOT NULL,
  `character_id` bigint unsigned NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present',
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spec_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `raid_attendances_raid_event_id_character_id_unique` (`raid_event_id`,`character_id`),
  KEY `raid_attendances_character_id_foreign` (`character_id`),
  KEY `raid_attendances_spec_id_foreign` (`spec_id`),
  CONSTRAINT `raid_attendances_character_id_foreign` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `raid_attendances_raid_event_id_foreign` FOREIGN KEY (`raid_event_id`) REFERENCES `raid_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `raid_attendances_spec_id_foreign` FOREIGN KEY (`spec_id`) REFERENCES `specializations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `raid_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `raid_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `static_id` bigint unsigned NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `discord_message_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `raid_events_static_id_foreign` (`static_id`),
  CONSTRAINT `raid_events_static_id_foreign` FOREIGN KEY (`static_id`) REFERENCES `statics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `realms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `realms` (
  `id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'eu',
  `timezone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `realms_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recipe_ingredients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recipe_ingredients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `recipe_id` bigint unsigned NOT NULL,
  `item_id` int NOT NULL,
  `quantity` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recipe_ingredients_recipe_id_foreign` (`recipe_id`),
  KEY `recipe_ingredients_item_id_foreign` (`item_id`),
  CONSTRAINT `recipe_ingredients_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recipe_ingredients_recipe_id_foreign` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recipes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recipes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `blizzard_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `profession` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `output_item_id` int DEFAULT NULL,
  `yield_quantity` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recipes_blizzard_id_unique` (`blizzard_id`),
  KEY `recipes_output_item_id_foreign` (`output_item_id`),
  CONSTRAINT `recipes_output_item_id_foreign` FOREIGN KEY (`output_item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `services_raw_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `services_raw_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `bnet_profile` json DEFAULT NULL,
  `bnet_equipment` json DEFAULT NULL,
  `bnet_media` json DEFAULT NULL,
  `bnet_mplus` json DEFAULT NULL,
  `bnet_raid` json DEFAULT NULL,
  `rio_profile` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `services_raw_data_character_id_foreign` (`character_id`),
  CONSTRAINT `services_raw_data_character_id_foreign` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
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
DROP TABLE IF EXISTS `specializations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `specializations` (
  `id` int unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `class_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('tank','heal','mdps','rdps') COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `static_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `static_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `static_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `static_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `static_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` enum('owner','officer','member') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `roster_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'core',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `static_user_static_id_foreign` (`static_id`),
  KEY `static_user_user_id_foreign` (`user_id`),
  CONSTRAINT `static_user_static_id_foreign` FOREIGN KEY (`static_id`) REFERENCES `statics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `static_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `statics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `statics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invite_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invite_until` timestamp NULL DEFAULT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'eu',
  `wcl_guild_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wcl_region` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wcl_realm` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raid_days` json DEFAULT NULL,
  `discord_channel_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discord_webhook_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discord_guild_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guild_tax_per_player` bigint NOT NULL DEFAULT '0',
  `automation_settings` json DEFAULT NULL,
  `consumable_settings` json DEFAULT NULL,
  `raid_start_time` time DEFAULT NULL,
  `raid_end_time` time DEFAULT NULL,
  `timezone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Europe/Paris',
  `owner_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bnet_last_synced_at` timestamp NULL DEFAULT NULL,
  `rio_last_synced_at` timestamp NULL DEFAULT NULL,
  `wcl_last_synced_at` timestamp NULL DEFAULT NULL,
  `plan_tier` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free' COMMENT 'Subscription tier: free | premium | pro',
  `weekly_tax_per_player` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `statics_slug_unique` (`slug`),
  UNIQUE KEY `statics_invite_token_unique` (`invite_token`),
  KEY `statics_owner_id_foreign` (`owner_id`),
  CONSTRAINT `statics_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tactical_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tactical_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `static_id` bigint unsigned NOT NULL,
  `raid_event_id` bigint unsigned DEFAULT NULL,
  `wcl_report_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `difficulties` json DEFAULT NULL,
  `ai_analysis` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tactical_reports_static_id_foreign` (`static_id`),
  KEY `tactical_reports_raid_event_id_foreign` (`raid_event_id`),
  CONSTRAINT `tactical_reports_raid_event_id_foreign` FOREIGN KEY (`raid_event_id`) REFERENCES `raid_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tactical_reports_static_id_foreign` FOREIGN KEY (`static_id`) REFERENCES `statics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `static_id` bigint unsigned NOT NULL,
  `amount` bigint NOT NULL,
  `type` enum('deposit','withdrawal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'deposit',
  `week_number` int NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transactions_user_id_foreign` (`user_id`),
  KEY `transactions_static_id_foreign` (`static_id`),
  CONSTRAINT `transactions_static_id_foreign` FOREIGN KEY (`static_id`) REFERENCES `statics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `locale` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `battlenet_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `battletag` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discord_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discord_username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_battlenet_id_unique` (`battlenet_id`),
  UNIQUE KEY `users_discord_id_unique` (`discord_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

/*M!999999\- enable the sandbox mode */ 
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_03_28_120742_create_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_03_28_120742_create_price_snapshots_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_03_28_121141_create_statics_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_03_28_121142_create_static_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_03_28_121806_create_realms_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_03_28_122416_create_raid_events_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_03_28_122416_create_static_items_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_03_28_122851_create_recipes_and_ingredients_tables',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_03_28_130514_make_output_item_id_nullable_in_recipes_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_03_28_130659_alter_recipes_table_for_midnight',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_03_28_141701_create_characters_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_03_28_141702_create_character_static_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_03_28_175917_add_equipped_item_level_to_characters_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_03_28_181012_update_characters_table_add_realm_id',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_03_28_190852_add_active_spec_to_characters_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_03_28_190852_add_combat_role_to_character_static_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_03_29_064804_update_raid_events_table_with_fields',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_03_29_065045_add_schedule_settings_to_statics_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_03_29_065500_add_end_time_and_timezone_to_statics_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_03_29_065500_add_end_time_to_raid_events_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_03_29_070223_create_raid_attendances_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_03_29_074922_add_discord_id_to_users_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_03_29_091407_add_discord_channel_id_to_statics_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_03_29_091407_add_discord_message_id_to_raid_events_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_03_29_100315_add_discord_username_to_users_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_03_29_103831_add_automation_settings_to_statics_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_03_29_104910_add_discord_guild_id_to_statics_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_03_29_111412_create_transactions_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_03_29_111420_add_treasury_settings_to_statics_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_03_29_114655_add_consumable_settings_to_statics_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_03_29_120817_add_wcl_and_ai_fields_to_statics_and_raid_events_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_03_29_125047_create_tactical_reports_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_03_29_125100_remove_wcl_fields_from_raid_events_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_03_29_141055_change_ai_analysis_column_type_in_tactical_reports_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_03_29_144911_create_personal_tactical_reports_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_03_29_204331_add_invite_token_to_statics_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_03_29_205728_add_invite_until_to_statics_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_03_29_214203_remove_server_from_statics_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_03_30_000000_add_sync_fields_to_characters_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_03_31_212155_add_sync_timestamps_to_statics_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_04_02_190000_add_weekly_tax_to_static_groups_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_04_02_191000_fix_weekly_tax_values',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_04_02_162225_add_roles_and_status_to_static_user_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_04_02_204232_create_services_raw_data_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_04_02_205707_add_compiled_data_to_characters_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_04_03_195036_remove_title_from_raid_events_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_04_04_103550_add_locale_to_users_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_04_04_120000_add_difficulties_to_tactical_reports_table',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_04_05_082308_add_plan_tier_to_statics_table',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_04_05_120000_add_discord_webhook_url_to_static_groups',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_04_05_130000_create_specializations_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_04_05_130001_create_character_static_specs_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_04_05_130002_add_spec_id_to_raid_attendances',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_04_05_130003_remove_combat_role_from_character_static',37);
