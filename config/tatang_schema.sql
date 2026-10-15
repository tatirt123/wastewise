-- WasteWise Database Dump for `tatang`
-- Host: 127.0.0.1
-- Server: MySQL / MariaDB (DevBoxPro)

CREATE DATABASE IF NOT EXISTS `tatang` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tatang`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('user','collector','admin') DEFAULT 'user',
  `avatar` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT 'Lapu-Lapu City',
  `barangay` varchar(100) DEFAULT 'Subabasbas',
  `eco_points` int(11) DEFAULT 2450,
  `level` int(11) DEFAULT 4,
  `xp` int(11) DEFAULT 2450,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Users:
-- password for warrior@wastewise.com is 'user123'
-- password for juan@wastewise.com is 'collector123'
-- password for admin@wastewise.com is 'admin123'
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `city`, `barangay`, `eco_points`, `level`, `xp`) VALUES
(1, 'Waste Warrior', 'warrior@wastewise.com', '$2y$10$wE47pW1f4T06rA4eH8L9se4W41iH9E2Kx3wH9cT7Z2V4m6rA4eH8L', 'user', 'Lapu-Lapu City', 'Subabasbas', 2450, 4, 2450),
(2, 'Juan Dela Cruz', 'juan@wastewise.com', '$2y$10$wE47pW1f4T06rA4eH8L9se4W41iH9E2Kx3wH9cT7Z2V4m6rA4eH8L', 'collector', 'Lapu-Lapu City', 'Subabasbas', 3200, 6, 4200),
(3, 'Admin Officer', 'admin@wastewise.com', '$2y$10$wE47pW1f4T06rA4eH8L9se4W41iH9E2Kx3wH9cT7Z2V4m6rA4eH8L', 'admin', 'Lapu-Lapu City', 'Poblacion', 5000, 10, 8000);

-- --------------------------------------------------------
-- Table structure for `waste_categories`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `waste_categories`;
CREATE TABLE `waste_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL UNIQUE,
  `name` varchar(100) NOT NULL,
  `color` varchar(20) NOT NULL,
  `dark_color` varchar(20) NOT NULL,
  `bg_color` varchar(20) NOT NULL,
  `icon` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `waste_categories` (`id`, `slug`, `name`, `color`, `dark_color`, `bg_color`, `icon`, `description`) VALUES
(1, 'biodegradable', 'Biodegradable', '#208e3a', '#146527', '#dff0e6', 'fa-leaf', 'Waste that comes from plants or animals and can naturally decompose.'),
(2, 'recyclable', 'Recyclable', '#e8b32a', '#c2931a', '#f5edd6', 'fa-recycle', 'Clean and dry materials that can be processed and reused.'),
(3, 'residual', 'Residual', '#1b659c', '#154d77', '#d6eaf3', 'fa-trash-can', 'Non-recyclable household waste that cannot be decomposed or reused.'),
(4, 'hazardous', 'Hazardous', '#d64a3b', '#a83428', '#e8dff0', 'fa-triangle-exclamation', 'Toxic, flammable, corrosive, or dangerous materials requiring specialized handling.');

-- --------------------------------------------------------
-- Table structure for `waste_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `waste_items`;
CREATE TABLE `waste_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `item_key` varchar(50) NOT NULL UNIQUE,
  `name` varchar(100) NOT NULL,
  `visual_type` enum('svg','icon') DEFAULT 'icon',
  `visual_icon` varchar(50) DEFAULT NULL,
  `where_to_throw` text NOT NULL,
  `steps_json` text NOT NULL,
  `keywords` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `waste_items` (`id`, `category_id`, `item_key`, `name`, `visual_type`, `visual_icon`, `where_to_throw`, `steps_json`, `keywords`) VALUES
(1, 2, 'plasticBottle', 'Plastic Bottle', 'svg', NULL, 'Rinse and dispose in the <strong>Yellow Bin</strong> for recyclables.', '[\"Remove the cap and label.\",\"Rinse to remove any residue.\",\"Flatten the bottle to save space.\",\"Dispose in the yellow bin.\"]', 'plastic, bottle, pet, pet bottle, water bottle, soda bottle, plastic bottle'),
(2, 2, 'glassBottle', 'Glass Bottle', 'icon', 'fa-wine-bottle', 'Rinse and place in the <strong>Yellow Bin</strong>. Handle with care.', '[\"Empty and rinse the bottle.\",\"Remove metal caps or corks.\",\"Do not break the glass.\",\"Place in the yellow bin.\"]', 'glass, glass bottle, jar, bottle, wine bottle, beer bottle'),
(3, 2, 'cardboard', 'Cardboard', 'icon', 'fa-box', 'Flatten and place in the <strong>Yellow Bin</strong>. Keep it dry.', '[\"Remove tape and plastic wrap.\",\"Flatten the box.\",\"Keep it clean and dry.\",\"Place in the yellow bin.\"]', 'cardboard, box, carton, paper, newspaper, carton box'),
(4, 1, 'bananaPeel', 'Banana Peel', 'icon', 'fa-apple-whole', 'Place in the <strong>Green Bin</strong> for biodegradable waste, or compost it.', '[\"Remove any plastic or sticker.\",\"Do not mix with plastics.\",\"Place in the green bin.\",\"Compost if you have a pit.\"]', 'banana, banana peel, peel, fruit, food scrap, vegetable, fruit peel'),
(5, 1, 'leaves', 'Dry Leaves', 'icon', 'fa-tree', 'Place in the <strong>Green Bin</strong> or add to a compost pile.', '[\"Sweep them into a pile.\",\"Remove plastic wrappers or trash.\",\"Place in the green bin.\",\"Or compost for garden soil.\"]', 'leaves, leaf, dry leaves, grass, twigs, yard waste, plant cuttings'),
(6, 3, 'diaper', 'Used Diaper', 'icon', 'fa-baby', 'Wrap securely and place in the <strong>Blue Bin</strong> for residual waste.', '[\"Remove solid waste into the toilet.\",\"Wrap the diaper tightly.\",\"Seal it in a small bag.\",\"Place in the blue bin.\"]', 'diaper, nappy, used diaper, sanitary, pad, sanitary napkin'),
(7, 3, 'tissue', 'Used Tissue', 'icon', 'fa-toilet-paper', 'Place in the <strong>Blue Bin</strong> for residual waste.', '[\"Do not put it in the recycling bin.\",\"Do not flush large amounts.\",\"Place in the blue bin.\"]', 'tissue, used tissue, napkin, paper towel, candy wrapper, wrapper, wipes'),
(8, 4, 'battery', 'Used Battery', 'icon', 'fa-battery-full', 'Take to a <strong>Hazardous Waste Drop-off</strong> — never in regular trash.', '[\"Tape the terminals to prevent sparks.\",\"Never throw batteries in regular bins.\",\"Store in a dry container.\",\"Drop off at a hazardous waste point.\"]', 'battery, batteries, used battery, aa, aaa, lithium, powerbank'),
(9, 4, 'bulb', 'Used Light Bulb', 'icon', 'fa-lightbulb', 'Take to a <strong>Hazardous Waste Drop-off</strong>. Do not break the bulb.', '[\"Wrap the bulb in paper or cloth.\",\"Do not throw it in regular trash.\",\"Place in a rigid container.\",\"Drop off at a hazardous waste point.\"]', 'bulb, light bulb, lamp, fluorescent, cfl, tube light, led');

-- --------------------------------------------------------
-- Table structure for `collection_schedules`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `collection_schedules`;
CREATE TABLE `collection_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `city` varchar(100) NOT NULL DEFAULT 'Lapu-Lapu City',
  `barangay` varchar(100) NOT NULL DEFAULT 'Subabasbas',
  `street_route` varchar(150) DEFAULT NULL,
  `waste_category_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL COMMENT '0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat',
  `start_time` time NOT NULL DEFAULT '07:00:00',
  `end_time` time NOT NULL DEFAULT '10:00:00',
  `status` enum('Scheduled','In Progress','Completed','Pending') DEFAULT 'Scheduled',
  `collector_id` int(11) DEFAULT NULL,
  `route_order` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `city` (`city`,`barangay`),
  KEY `waste_category_id` (`waste_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `collection_schedules` (`id`, `city`, `barangay`, `street_route`, `waste_category_id`, `day_of_week`, `start_time`, `end_time`, `status`, `route_order`) VALUES
(1, 'Lapu-Lapu City', 'Subabasbas', 'Rizal St.', 1, 0, '07:00:00', '10:00:00', 'Completed', 1),
(2, 'Lapu-Lapu City', 'Subabasbas', 'Rizal St.', 1, 1, '08:00:00', '11:00:00', 'Completed', 1),
(3, 'Lapu-Lapu City', 'Subabasbas', 'Bonifacio Ave.', 2, 2, '07:00:00', '10:00:00', 'Completed', 2),
(4, 'Lapu-Lapu City', 'Subabasbas', 'Aguinaldo St.', 2, 4, '09:00:00', '12:00:00', 'Pending', 4),
(5, 'Lapu-Lapu City', 'Subabasbas', 'Mabini St.', 3, 3, '07:00:00', '10:00:00', 'In Progress', 3),
(6, 'Lapu-Lapu City', 'Subabasbas', 'Mabini St.', 3, 6, '07:00:00', '10:00:00', 'Scheduled', 3),
(7, 'Lapu-Lapu City', 'Subabasbas', 'OSMEÑA Rd.', 4, 5, '08:00:00', '11:00:00', 'Pending', 5),
(101, 'North District', 'North District', 'Banilad', 1, 1, '08:00:00', '08:45:00', 'Completed', 1),
(102, 'North District', 'North District', 'Talamban', 2, 1, '08:45:00', '09:30:00', 'Completed', 2),
(103, 'North District', 'North District', 'Pari-an', 3, 1, '09:30:00', '10:15:00', 'In Progress', 3),
(104, 'North District', 'North District', 'Busay', 2, 1, '10:15:00', '11:00:00', 'Pending', 4),
(105, 'North District', 'North District', 'Guadalupe', 4, 1, '11:00:00', '12:00:00', 'Pending', 5);

-- --------------------------------------------------------
-- Table structure for `illegal_reports`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `illegal_reports`;
CREATE TABLE `illegal_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `waste_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `report_date` date NOT NULL,
  `report_time` time NOT NULL,
  `location` varchar(255) NOT NULL,
  `city` varchar(100) DEFAULT 'Lapu-Lapu City',
  `barangay` varchar(100) DEFAULT 'Subabasbas',
  `photo_url` varchar(255) DEFAULT NULL,
  `priority` enum('Normal','High Priority') DEFAULT 'Normal',
  `status` enum('Pending','Under Review','Assigned','On the Way','Resolved') DEFAULT 'Pending',
  `collector_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `illegal_reports` (`id`, `user_id`, `waste_type`, `description`, `report_date`, `report_time`, `location`, `city`, `barangay`, `photo_url`, `priority`, `status`) VALUES
(1, 1, 'Mixed household waste', 'Large pile of uncollected garbage left near school gate blocking sidewalk.', CURDATE(), '08:15:00', 'Near Lapu-Lapu Elementary School', 'Lapu-Lapu City', 'Subabasbas', 'https://images.unsplash.com/photo-1530587191325-3db32d826c18?auto=format&fit=crop&w=600&q=80', 'High Priority', 'On the Way'),
(2, 1, 'Hazardous / chemical', 'Improperly segregated waste bags and chemical containers on roadside.', CURDATE(), '07:42:00', 'Along M.L. Quezon St.', 'Lapu-Lapu City', 'Subabasbas', 'https://images.unsplash.com/photo-1611284446314-60a58ac0deb9?auto=format&fit=crop&w=600&q=80', 'Normal', 'Assigned');

-- --------------------------------------------------------
-- Table structure for `recycling_centers`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `recycling_centers`;
CREATE TABLE `recycling_centers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(100) DEFAULT 'Lapu-Lapu City',
  `barangay` varchar(100) DEFAULT 'Subabasbas',
  `distance_info` varchar(50) DEFAULT '1.0 km',
  `accepted_tags` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `recycling_centers` (`id`, `name`, `address`, `city`, `barangay`, `distance_info`, `accepted_tags`, `phone`) VALUES
(1, 'Subabasbas Materials Recovery Facility', 'Barangay Subabasbas, Lapu-Lapu City', 'Lapu-Lapu City', 'Subabasbas', '0.8 km', 'Plastic, Glass, Paper', '(032) 340-0001'),
(2, 'Basak Eco Drop-off Point', 'Basak Road, Lapu-Lapu City', 'Lapu-Lapu City', 'Basak', '2.4 km', 'Paper, Metal, Cardboard', '(032) 340-0002'),
(3, 'Marigondon Junk Shop & Recycler', 'Marigondon Crossing, Lapu-Lapu City', 'Lapu-Lapu City', 'Marigondon', '3.6 km', 'Metal, E-waste', '(032) 340-0003'),
(4, 'Lapu-Lapu City Hazardous Waste Depot', 'City Hall Compound, Lapu-Lapu City', 'Lapu-Lapu City', 'Poblacion', '5.1 km', 'Battery, Chemical, Bulb', '(032) 340-0004');

-- --------------------------------------------------------
-- Table structure for `community_activities`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `community_activities`;
CREATE TABLE `community_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `activity_date` datetime NOT NULL,
  `location` varchar(255) NOT NULL,
  `category` varchar(50) DEFAULT 'tree',
  `icon` varchar(50) DEFAULT 'fa-tree',
  `icon_bg` varchar(20) DEFAULT 'green',
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `community_activities` (`id`, `title`, `activity_date`, `location`, `category`, `icon`, `icon_bg`, `description`) VALUES
(1, 'Tree Planting Activity', '2026-07-29 08:00:00', 'Green Park City Center', 'tree', 'fa-tree', 'green', 'Community tree planting initiative to increase urban greenery and absorb carbon dioxide.'),
(2, 'Clean-Up Drive', '2026-08-10 07:00:00', 'Riverside Area', 'cleanup', 'fa-broom', 'blue', 'Neighborhood cleanup event to clear waste from rivers and waterways.');

-- --------------------------------------------------------
-- Table structure for `activity_participants`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `activity_participants`;
CREATE TABLE `activity_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_activity` (`activity_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `user_activity_logs`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_activity_logs`;
CREATE TABLE `user_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action_text` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_activity_logs` (`id`, `user_id`, `action_text`, `created_at`) VALUES
(1, 1, 'Recycled plastic bottle', NOW()),
(2, 1, 'Properly segregated household waste', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 1, 'Joined Clean-Up Drive', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- --------------------------------------------------------
-- Table structure for `announcements`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `category` varchar(100) DEFAULT 'General',
  `content` text NOT NULL,
  `date_posted` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `announcements` (`id`, `title`, `category`, `content`, `date_posted`, `is_active`) VALUES
(1, 'Proper Segregation Reminder', 'Segregation', 'Please separate biodegradable, recyclable, residual, and hazardous waste before collection.', CURDATE(), 1),
(2, 'Collection Schedule Update', 'Schedule', 'New collection schedules are now available. Check the Collection Schedule page.', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1),
(3, 'Holiday Schedule for Sector B', 'Notice', 'Collection for Sector B moves from 7:00 AM to 5:30 AM on holidays.', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 1);

-- --------------------------------------------------------
-- Table structure for `impact_stats`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `impact_stats`;
CREATE TABLE `impact_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stat_key` varchar(50) NOT NULL UNIQUE,
  `stat_label` varchar(100) NOT NULL,
  `stat_value` varchar(50) NOT NULL,
  `icon` varchar(50) NOT NULL,
  `color` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `impact_stats` (`id`, `stat_key`, `stat_label`, `stat_value`, `icon`, `color`) VALUES
(1, 'items_recycled', 'Items Recycled', '5,240', 'fa-recycle', 'green'),
(2, 'reports_submitted', 'Reports Submitted', '300', 'fa-circle-exclamation', 'blue'),
(3, 'recycling_centers', 'Recycling Centers', '18', 'fa-bullseye', 'yellow'),
(4, 'volunteers', 'Community Volunteers', '500', 'fa-users', 'teal'),
(5, 'trees_saved', 'Trees Saved', '1,245', 'fa-tree', 'emerald'),
(6, 'kg_recycled', 'kg Recycled', '3,678', 'fa-recycle', 'emerald'),
(7, 'active_users', 'Active Users', '892', 'fa-users', 'emerald'),
(8, 'co2_saved', 'Tons CO2 Saved', '12.5', 'fa-leaf', 'emerald');

SET FOREIGN_KEY_CHECKS = 1;
