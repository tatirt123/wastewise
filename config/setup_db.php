<?php
/**
 * WasteWise Database Migration & Seeder
 * Run this script to initialize all tables and seed data in database `tatang`
 */

require_once __DIR__ . '/db.php';

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(150) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('user', 'collector', 'admin') DEFAULT 'user',
        `avatar` VARCHAR(255) DEFAULT NULL,
        `city` VARCHAR(100) DEFAULT 'Lapu-Lapu City',
        `barangay` VARCHAR(100) DEFAULT 'Subabasbas',
        `eco_points` INT DEFAULT 2450,
        `level` INT DEFAULT 4,
        `xp` INT DEFAULT 2450,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. Waste Categories Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `waste_categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `slug` VARCHAR(50) NOT NULL UNIQUE,
        `name` VARCHAR(100) NOT NULL,
        `color` VARCHAR(20) NOT NULL,
        `dark_color` VARCHAR(20) NOT NULL,
        `bg_color` VARCHAR(20) NOT NULL,
        `icon` VARCHAR(50) NOT NULL,
        `description` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Waste Items Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `waste_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `category_id` INT NOT NULL,
        `item_key` VARCHAR(50) NOT NULL UNIQUE,
        `name` VARCHAR(100) NOT NULL,
        `visual_type` ENUM('svg', 'icon') DEFAULT 'icon',
        `visual_icon` VARCHAR(50) DEFAULT NULL,
        `where_to_throw` TEXT NOT NULL,
        `steps_json` TEXT NOT NULL,
        `keywords` TEXT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Collection Schedules Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `collection_schedules` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `city` VARCHAR(100) NOT NULL DEFAULT 'Lapu-Lapu City',
        `barangay` VARCHAR(100) NOT NULL DEFAULT 'Subabasbas',
        `street_route` VARCHAR(150) DEFAULT NULL,
        `waste_category_id` INT NOT NULL,
        `day_of_week` TINYINT NOT NULL COMMENT '0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat',
        `start_time` TIME NOT NULL DEFAULT '07:00:00',
        `end_time` TIME NOT NULL DEFAULT '10:00:00',
        `status` ENUM('Scheduled', 'In Progress', 'Completed', 'Pending') DEFAULT 'Scheduled',
        `collector_id` INT DEFAULT NULL,
        `route_order` INT DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (`city`, `barangay`),
        INDEX (`waste_category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 5. Illegal Dumping Reports Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `illegal_reports` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT DEFAULT NULL,
        `waste_type` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `report_date` DATE NOT NULL,
        `report_time` TIME NOT NULL,
        `location` VARCHAR(255) NOT NULL,
        `city` VARCHAR(100) DEFAULT 'Lapu-Lapu City',
        `barangay` VARCHAR(100) DEFAULT 'Subabasbas',
        `photo_url` VARCHAR(255) DEFAULT NULL,
        `priority` ENUM('Normal', 'High Priority') DEFAULT 'Normal',
        `status` ENUM('Pending', 'Under Review', 'Assigned', 'On the Way', 'Resolved') DEFAULT 'Pending',
        `collector_id` INT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 6. Recycling Centers Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `recycling_centers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `address` VARCHAR(255) NOT NULL,
        `city` VARCHAR(100) DEFAULT 'Lapu-Lapu City',
        `barangay` VARCHAR(100) DEFAULT 'Subabasbas',
        `distance_info` VARCHAR(50) DEFAULT '1.0 km',
        `accepted_tags` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 7. Community Activities Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `community_activities` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(150) NOT NULL,
        `activity_date` DATETIME NOT NULL,
        `location` VARCHAR(255) NOT NULL,
        `category` VARCHAR(50) DEFAULT 'tree',
        `icon` VARCHAR(50) DEFAULT 'fa-tree',
        `icon_bg` VARCHAR(20) DEFAULT 'green',
        `description` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 8. Activity Participants Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `activity_participants` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `activity_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user_activity` (`activity_id`, `user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 9. User Activity Logs Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_activity_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `action_text` VARCHAR(255) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 10. Announcements Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `announcements` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(200) NOT NULL,
        `category` VARCHAR(100) DEFAULT 'General',
        `content` TEXT NOT NULL,
        `date_posted` DATE NOT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 11. Impact Stats Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `impact_stats` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `stat_key` VARCHAR(50) NOT NULL UNIQUE,
        `stat_label` VARCHAR(100) NOT NULL,
        `stat_value` VARCHAR(50) NOT NULL,
        `icon` VARCHAR(50) NOT NULL,
        `color` VARCHAR(20) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // ==========================================
    // SEED INITIAL DATA (If tables are empty)
    // ==========================================

    // Seed Users
    $userCount = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    if ($userCount == 0) {
        $stmtUser = $pdo->prepare("INSERT INTO `users` (`name`, `email`, `password`, `role`, `city`, `barangay`, `eco_points`, `level`, `xp`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // 1. Citizen / User
        $stmtUser->execute([
            'Waste Warrior',
            'warrior@wastewise.com',
            password_hash('user123', PASSWORD_DEFAULT),
            'user',
            'Lapu-Lapu City',
            'Subabasbas',
            2450,
            4,
            2450
        ]);

        // 2. Collector
        $stmtUser->execute([
            'Juan Dela Cruz',
            'juan@wastewise.com',
            password_hash('collector123', PASSWORD_DEFAULT),
            'collector',
            'Lapu-Lapu City',
            'Subabasbas',
            3200,
            6,
            4200
        ]);

        // 3. Admin
        $stmtUser->execute([
            'Admin Officer',
            'admin@wastewise.com',
            password_hash('admin123', PASSWORD_DEFAULT),
            'admin',
            'Lapu-Lapu City',
            'Poblacion',
            5000,
            10,
            8000
        ]);
    }

    // Seed Categories
    $catCount = $pdo->query("SELECT COUNT(*) FROM `waste_categories`")->fetchColumn();
    if ($catCount == 0) {
        $stmtCat = $pdo->prepare("INSERT INTO `waste_categories` (`id`, `slug`, `name`, `color`, `dark_color`, `bg_color`, `icon`, `description`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtCat->execute([1, 'biodegradable', 'Biodegradable', '#208e3a', '#146527', '#dff0e6', 'fa-leaf', 'Waste that comes from plants or animals and can naturally decompose.']);
        $stmtCat->execute([2, 'recyclable', 'Recyclable', '#e8b32a', '#c2931a', '#f5edd6', 'fa-recycle', 'Clean and dry materials that can be processed and reused.']);
        $stmtCat->execute([3, 'residual', 'Residual', '#1b659c', '#154d77', '#d6eaf3', 'fa-trash-can', 'Non-recyclable household waste that cannot be decomposed or reused.']);
        $stmtCat->execute([4, 'hazardous', 'Hazardous', '#d64a3b', '#a83428', '#e8dff0', 'fa-triangle-exclamation', 'Toxic, flammable, corrosive, or dangerous materials requiring specialized handling.']);
    }

    // Seed Waste Items
    $itemCount = $pdo->query("SELECT COUNT(*) FROM `waste_items`")->fetchColumn();
    if ($itemCount == 0) {
        $stmtItem = $pdo->prepare("INSERT INTO `waste_items` (`category_id`, `item_key`, `name`, `visual_type`, `visual_icon`, `where_to_throw`, `steps_json`, `keywords`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $stmtItem->execute([
            2, 'plasticBottle', 'Plastic Bottle', 'svg', NULL,
            'Rinse and dispose in the <strong>Yellow Bin</strong> for recyclables.',
            json_encode(['Remove the cap and label.', 'Rinse to remove any residue.', 'Flatten the bottle to save space.', 'Dispose in the yellow bin.']),
            'plastic, bottle, pet, pet bottle, water bottle, soda bottle, plastic bottle'
        ]);

        $stmtItem->execute([
            2, 'glassBottle', 'Glass Bottle', 'icon', 'fa-wine-bottle',
            'Rinse and place in the <strong>Yellow Bin</strong>. Handle with care.',
            json_encode(['Empty and rinse the bottle.', 'Remove metal caps or corks.', 'Do not break the glass.', 'Place in the yellow bin.']),
            'glass, glass bottle, jar, bottle, wine bottle, beer bottle'
        ]);

        $stmtItem->execute([
            2, 'cardboard', 'Cardboard', 'icon', 'fa-box',
            'Flatten and place in the <strong>Yellow Bin</strong>. Keep it dry.',
            json_encode(['Remove tape and plastic wrap.', 'Flatten the box.', 'Keep it clean and dry.', 'Place in the yellow bin.']),
            'cardboard, box, carton, paper, newspaper, carton box'
        ]);

        $stmtItem->execute([
            1, 'bananaPeel', 'Banana Peel', 'icon', 'fa-apple-whole',
            'Place in the <strong>Green Bin</strong> for biodegradable waste, or compost it.',
            json_encode(['Remove any plastic or sticker.', 'Do not mix with plastics.', 'Place in the green bin.', 'Compost if you have a pit.']),
            'banana, banana peel, peel, fruit, food scrap, vegetable, fruit peel'
        ]);

        $stmtItem->execute([
            1, 'leaves', 'Dry Leaves', 'icon', 'fa-tree',
            'Place in the <strong>Green Bin</strong> or add to a compost pile.',
            json_encode(['Sweep them into a pile.', 'Remove plastic wrappers or trash.', 'Place in the green bin.', 'Or compost for garden soil.']),
            'leaves, leaf, dry leaves, grass, twigs, yard waste, plant cuttings'
        ]);

        $stmtItem->execute([
            3, 'diaper', 'Used Diaper', 'icon', 'fa-baby',
            'Wrap securely and place in the <strong>Blue Bin</strong> for residual waste.',
            json_encode(['Remove solid waste into the toilet.', 'Wrap the diaper tightly.', 'Seal it in a small bag.', 'Place in the blue bin.']),
            'diaper, nappy, used diaper, sanitary, pad, sanitary napkin'
        ]);

        $stmtItem->execute([
            3, 'tissue', 'Used Tissue', 'icon', 'fa-toilet-paper',
            'Place in the <strong>Blue Bin</strong> for residual waste.',
            json_encode(['Do not put it in the recycling bin.', 'Do not flush large amounts.', 'Place in the blue bin.']),
            'tissue, used tissue, napkin, paper towel, candy wrapper, wrapper, wipes'
        ]);

        $stmtItem->execute([
            4, 'battery', 'Used Battery', 'icon', 'fa-battery-full',
            'Take to a <strong>Hazardous Waste Drop-off</strong> — never in regular trash.',
            json_encode(['Tape the terminals to prevent sparks.', 'Never throw batteries in regular bins.', 'Store in a dry container.', 'Drop off at a hazardous waste point.']),
            'battery, batteries, used battery, aa, aaa, lithium, powerbank'
        ]);

        $stmtItem->execute([
            4, 'bulb', 'Used Light Bulb', 'icon', 'fa-lightbulb',
            'Take to a <strong>Hazardous Waste Drop-off</strong>. Do not break the bulb.',
            json_encode(['Wrap the bulb in paper or cloth.', 'Do not throw it in regular trash.', 'Place in a rigid container.', 'Drop off at a hazardous waste point.']),
            'bulb, light bulb, lamp, fluorescent, cfl, tube light, led'
        ]);
    }

    // Seed Collection Schedules
    $schedCount = $pdo->query("SELECT COUNT(*) FROM `collection_schedules`")->fetchColumn();
    if ($schedCount == 0) {
        $stmtSched = $pdo->prepare("INSERT INTO `collection_schedules` (`city`, `barangay`, `street_route`, `waste_category_id`, `day_of_week`, `start_time`, `end_time`, `status`, `route_order`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Subabasbas weekly schedule
        // Sunday (0) & Monday (1): Biodegradable (cat 1)
        $stmtSched->execute(['Lapu-Lapu City', 'Subabasbas', 'Rizal St.', 1, 0, '07:00:00', '10:00:00', 'Completed', 1]);
        $stmtSched->execute(['Lapu-Lapu City', 'Subabasbas', 'Rizal St.', 1, 1, '08:00:00', '11:00:00', 'Completed', 1]);
        // Tuesday (2) & Thursday (4): Recyclable (cat 2)
        $stmtSched->execute(['Lapu-Lapu City', 'Subabasbas', 'Bonifacio Ave.', 2, 2, '07:00:00', '10:00:00', 'Completed', 2]);
        $stmtSched->execute(['Lapu-Lapu City', 'Subabasbas', 'Aguinaldo St.', 2, 4, '09:00:00', '12:00:00', 'Pending', 4]);
        // Wednesday (3) & Saturday (6): Residual (cat 3)
        $stmtSched->execute(['Lapu-Lapu City', 'Subabasbas', 'Mabini St.', 3, 3, '07:00:00', '10:00:00', 'In Progress', 3]);
        $stmtSched->execute(['Lapu-Lapu City', 'Subabasbas', 'Mabini St.', 3, 6, '07:00:00', '10:00:00', 'Scheduled', 3]);
        // Friday (5): Hazardous (cat 4)
        $stmtSched->execute(['Lapu-Lapu City', 'Subabasbas', 'OSMEÑA Rd.', 4, 5, '08:00:00', '11:00:00', 'Pending', 5]);

        // Route 1 - North District schedule
        $stmtSched->execute(['North District', 'North District', 'Banilad', 1, 1, '08:00:00', '08:45:00', 'Completed', 1]);
        $stmtSched->execute(['North District', 'North District', 'Talamban', 2, 1, '08:45:00', '09:30:00', 'Completed', 2]);
        $stmtSched->execute(['North District', 'North District', 'Pari-an', 3, 1, '09:30:00', '10:15:00', 'In Progress', 3]);
        $stmtSched->execute(['North District', 'North District', 'Busay', 2, 1, '10:15:00', '11:00:00', 'Pending', 4]);
        $stmtSched->execute(['North District', 'North District', 'Guadalupe', 4, 1, '11:00:00', '12:00:00', 'Pending', 5]);

        // Basak & Marigondon schedules
        $stmtSched->execute(['Lapu-Lapu City', 'Basak', 'Basak Highway', 1, 1, '06:00:00', '09:00:00', 'Scheduled', 1]);
        $stmtSched->execute(['Lapu-Lapu City', 'Basak', 'Basak Highway', 2, 2, '06:00:00', '09:00:00', 'Scheduled', 2]);
        $stmtSched->execute(['Lapu-Lapu City', 'Marigondon', 'Marigondon Crossing', 3, 3, '07:00:00', '10:00:00', 'Scheduled', 1]);
        $stmtSched->execute(['Lapu-Lapu City', 'Pajo', 'Pajo Central', 2, 4, '08:00:00', '11:00:00', 'Scheduled', 1]);
    }

    // Seed Illegal Reports
    $repCount = $pdo->query("SELECT COUNT(*) FROM `illegal_reports`")->fetchColumn();
    if ($repCount == 0) {
        $stmtRep = $pdo->prepare("INSERT INTO `illegal_reports` (`user_id`, `waste_type`, `description`, `report_date`, `report_time`, `location`, `city`, `barangay`, `photo_url`, `priority`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmtRep->execute([
            1, 'Mixed household waste', 'Large pile of uncollected garbage left near school gate blocking sidewalk.',
            date('Y-m-d'), '08:15:00', 'Near Lapu-Lapu Elementary School', 'Lapu-Lapu City', 'Subabasbas',
            'https://images.unsplash.com/photo-1530587191325-3db32d826c18?auto=format&fit=crop&w=600&q=80',
            'High Priority', 'On the Way'
        ]);

        $stmtRep->execute([
            1, 'Hazardous / chemical', 'Improperly segregated waste bags and chemical containers on roadside.',
            date('Y-m-d'), '07:42:00', 'Along M.L. Quezon St.', 'Lapu-Lapu City', 'Subabasbas',
            'https://images.unsplash.com/photo-1611284446314-60a58ac0deb9?auto=format&fit=crop&w=600&q=80',
            'Normal', 'Assigned'
        ]);

        $stmtRep->execute([
            1, 'Construction debris', 'Dumping of broken concrete and tiles in vacant lot.',
            date('Y-m-d', strtotime('-1 day')), '14:20:00', 'Pajo Crossing', 'Lapu-Lapu City', 'Pajo',
            'https://images.unsplash.com/photo-1530587191325-3db32d826c18?auto=format&fit=crop&w=600&q=80',
            'Normal', 'Under Review'
        ]);

        $stmtRep->execute([
            1, 'Mixed household waste', 'Overflowing garbage bin not collected during schedule.',
            date('Y-m-d', strtotime('-2 days')), '10:00:00', 'Poblacion Market Area', 'Lapu-Lapu City', 'Poblacion',
            NULL, 'Normal', 'Resolved'
        ]);
    }

    // Seed Recycling Centers
    $cenCount = $pdo->query("SELECT COUNT(*) FROM `recycling_centers`")->fetchColumn();
    if ($cenCount == 0) {
        $stmtCen = $pdo->prepare("INSERT INTO `recycling_centers` (`name`, `address`, `city`, `barangay`, `distance_info`, `accepted_tags`, `phone`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmtCen->execute(['Subabasbas Materials Recovery Facility', 'Barangay Subabasbas, Lapu-Lapu City', 'Lapu-Lapu City', 'Subabasbas', '0.8 km', 'Plastic, Glass, Paper', '(032) 340-0001']);
        $stmtCen->execute(['Basak Eco Drop-off Point', 'Basak Road, Lapu-Lapu City', 'Lapu-Lapu City', 'Basak', '2.4 km', 'Paper, Metal, Cardboard', '(032) 340-0002']);
        $stmtCen->execute(['Marigondon Junk Shop & Recycler', 'Marigondon Crossing, Lapu-Lapu City', 'Lapu-Lapu City', 'Marigondon', '3.6 km', 'Metal, E-waste', '(032) 340-0003']);
        $stmtCen->execute(['Lapu-Lapu City Hazardous Waste Depot', 'City Hall Compound, Lapu-Lapu City', 'Lapu-Lapu City', 'Poblacion', '5.1 km', 'Battery, Chemical, Bulb', '(032) 340-0004']);
    }

    // Seed Community Activities
    $actCount = $pdo->query("SELECT COUNT(*) FROM `community_activities`")->fetchColumn();
    if ($actCount == 0) {
        $stmtAct = $pdo->prepare("INSERT INTO `community_activities` (`title`, `activity_date`, `location`, `category`, `icon`, `icon_bg`, `description`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtAct->execute(['Tree Planting Activity', '2026-07-29 08:00:00', 'Green Park City Center', 'tree', 'fa-tree', 'green', 'Community tree planting initiative to increase urban greenery and absorb carbon dioxide.']);
        $stmtAct->execute(['Clean-Up Drive', '2026-08-10 07:00:00', 'Riverside Area', 'cleanup', 'fa-broom', 'blue', 'Neighborhood cleanup event to clear waste from rivers and waterways.']);
    }

    // Seed User Activity Logs
    $logCount = $pdo->query("SELECT COUNT(*) FROM `user_activity_logs`")->fetchColumn();
    if ($logCount == 0) {
        $stmtLog = $pdo->prepare("INSERT INTO `user_activity_logs` (`user_id`, `action_text`, `created_at`) VALUES (?, ?, ?)");
        $stmtLog->execute([1, 'Recycled plastic bottle', date('Y-m-d H:i:s')]);
        $stmtLog->execute([1, 'Properly segregated household waste', date('Y-m-d H:i:s', strtotime('-1 day'))]);
        $stmtLog->execute([1, 'Joined Clean-Up Drive', date('Y-m-d H:i:s', strtotime('-2 days'))]);
    }

    // Seed Announcements
    $annCount = $pdo->query("SELECT COUNT(*) FROM `announcements`")->fetchColumn();
    if ($annCount == 0) {
        $stmtAnn = $pdo->prepare("INSERT INTO `announcements` (`title`, `category`, `content`, `date_posted`) VALUES (?, ?, ?, ?)");
        $stmtAnn->execute(['Proper Segregation Reminder', 'Segregation', 'Please separate biodegradable, recyclable, residual, and hazardous waste before collection.', date('Y-m-d')]);
        $stmtAnn->execute(['Collection Schedule Update', 'Schedule', 'New collection schedules are now available. Check the Collection Schedule page.', date('Y-m-d', strtotime('-1 day'))]);
        $stmtAnn->execute(['Holiday Schedule for Sector B', 'Notice', 'Collection for Sector B moves from 7:00 AM to 5:30 AM on holidays. Biodegradable pickup on Sunday and Monday stays the same.', date('Y-m-d', strtotime('-2 days'))]);
    }

    // Seed Impact Stats
    $statCount = $pdo->query("SELECT COUNT(*) FROM `impact_stats`")->fetchColumn();
    if ($statCount == 0) {
        $stmtStat = $pdo->prepare("INSERT INTO `impact_stats` (`stat_key`, `stat_label`, `stat_value`, `icon`, `color`) VALUES (?, ?, ?, ?, ?)");
        $stmtStat->execute(['items_recycled', 'Items Recycled', '5,240', 'fa-recycle', 'green']);
        $stmtStat->execute(['reports_submitted', 'Reports Submitted', '300', 'fa-circle-exclamation', 'blue']);
        $stmtStat->execute(['recycling_centers', 'Recycling Centers', '18', 'fa-bullseye', 'yellow']);
        $stmtStat->execute(['volunteers', 'Community Volunteers', '500', 'fa-users', 'teal']);
        $stmtStat->execute(['trees_saved', 'Trees Saved', '1,245', 'fa-tree', 'emerald']);
        $stmtStat->execute(['kg_recycled', 'kg Recycled', '3,678', 'fa-recycle', 'emerald']);
        $stmtStat->execute(['active_users', 'Active Users', '892', 'fa-users', 'emerald']);
        $stmtStat->execute(['co2_saved', 'Tons CO2 Saved', '12.5', 'fa-leaf', 'emerald']);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Database tatang has been initialized and seeded successfully!'
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
