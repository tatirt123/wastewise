<?php
/**
 * WasteWise - Citizen Eco Dashboard
 * Database: tatang (MySQL)
 */
require_once __DIR__ . '/config/db.php';

// Get current user or fallback to demo citizen user
$user = getLoggedInUser($pdo);
if (!$user) {
    $stmt = $pdo->query("SELECT * FROM `users` WHERE `role` = 'user' LIMIT 1");
    $user = $stmt->fetch();
    if (!$user) {
        $user = [
            'id' => 1,
            'name' => 'Waste Warrior',
            'email' => 'waste.warrior@email.com',
            'barangay' => 'Subabasbas',
            'city' => 'Lapu-Lapu City',
            'eco_points' => 2450,
            'level' => 4,
            'xp' => 2450,
            'avatar' => null,
            'role' => 'user'
        ];
    }
}

// Fetch dynamic impact stats from tatang DB
$reportCount = 0;
$itemsRecycled = '5,240';
$recyclingCentersCount = 18;
$volunteerCount = 500;

try {
    $reportCount = (int)$pdo->query("SELECT COUNT(*) FROM `illegal_reports`")->fetchColumn();
    $recyclingCentersCount = (int)$pdo->query("SELECT COUNT(*) FROM `recycling_centers`")->fetchColumn();
    $volunteerCount = (int)$pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();

    $stmtStat = $pdo->query("SELECT stat_key, stat_value FROM `impact_stats`");
    while ($row = $stmtStat->fetch()) {
        if ($row['stat_key'] === 'items_recycled') $itemsRecycled = $row['stat_value'];
    }
} catch (Exception $e) { /* ignore */ }

// Fetch waste items from database tatang
$dbWasteItems = [];
try {
    $stmtItems = $pdo->query("
        SELECT wi.*, wc.name AS category_name, wc.slug AS category_slug, wc.color AS category_color
        FROM `waste_items` wi
        JOIN `waste_categories` wc ON wi.category_id = wc.id
        ORDER BY wi.id ASC
    ");
    while ($row = $stmtItems->fetch()) {
        $steps = json_decode($row['steps_json'], true) ?: [];
        $keywords = array_map('trim', explode(',', strtolower($row['keywords'])));
        $dbWasteItems[$row['item_key']] = [
            'name' => $row['name'],
            'category' => $row['category_name'],
            'catKey' => $row['category_slug'],
            'visual' => [
                'type' => $row['visual_type'],
                'icon' => $row['visual_icon']
            ],
            'where' => $row['where_to_throw'],
            'steps' => $steps,
            'keywords' => $keywords
        ];
    }
} catch (Exception $e) { /* fallback */ }

// Fetch recycling centers from tatang DB
$centers = [];
try {
    $stmtCenters = $pdo->query("SELECT * FROM `recycling_centers` WHERE `is_active` = 1 ORDER BY `id` ASC");
    $centers = $stmtCenters->fetchAll();
} catch (Exception $e) { /* fallback */ }

// Fetch community activities from tatang DB
$activities = [];
try {
    $stmtAct = $pdo->query("SELECT * FROM `community_activities` ORDER BY `activity_date` ASC");
    $activities = $stmtAct->fetchAll();
} catch (Exception $e) { /* fallback */ }

// Fetch announcements from tatang DB
$announcements = [];
try {
    $stmtAnn = $pdo->query("SELECT * FROM `announcements` WHERE `is_active` = 1 ORDER BY `id` DESC LIMIT 5");
    $announcements = $stmtAnn->fetchAll();
} catch (Exception $e) { /* fallback */ }

// Fetch recent user activity logs from tatang DB
$activityLogs = [];
try {
    $stmtLogs = $pdo->prepare("SELECT * FROM `user_activity_logs` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 6");
    $stmtLogs->execute([$user['id']]);
    $activityLogs = $stmtLogs->fetchAll();
} catch (Exception $e) { /* fallback */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Waste Wise · Eco Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <header class="navbar">
      <a href="index.php" class="logo" style="text-decoration:none;">
        <i class="fa-solid fa-leaf logo-icon"></i>
        <div>
          <h1>Waste<span>Wise</span></h1>
          <small>Smart Waste Segregation Assistant</small>
        </div>
      </a>
      <nav class="nav-links">
        <a href="#home" class="nav-item"><i class="fa-solid fa-house"></i> Home</a>
        <a href="#search-result" class="nav-item active"><i class="fa-solid fa-bolt"></i> Wise Search</a>
        <a href="#schedule" class="nav-item"><i class="fa-regular fa-calendar"></i> Schedule</a>
        <a href="#community" class="nav-item"><i class="fa-solid fa-people-group"></i> Community</a>
        <a href="#profile" class="nav-item"><i class="fa-regular fa-user"></i> Profile</a>
        <?php if (!empty($user['role']) && $user['role'] === 'collector'): ?>
          <a href="collector.php" class="nav-item" style="color:#2d7a4a;"><i class="fa-solid fa-truck"></i> Collector</a>
        <?php endif; ?>
        <?php if (!empty($user['role']) && $user['role'] === 'admin'): ?>
          <a href="admin.php" class="nav-item" style="color:#2d7a4a;"><i class="fa-solid fa-shield-halved"></i> Admin</a>
        <?php endif; ?>
        <a href="#profile" class="nav-profile" title="View profile">
          <?php if (!empty($user['avatar'])): ?>
            <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="avatar" id="navAvatar" style="width:34px; height:34px; border-radius:50%; object-fit:cover;">
          <?php else: ?>
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='50' fill='%232d7a4a'/%3E%3Cpath d='M50 20C30 35 28 60 50 84 72 60 70 35 50 20Z' fill='%23eaf7ee'/%3E%3Cpath d='M50 26V80M50 46L36 56M50 46L64 56M50 62L38 70M50 62L62 70' stroke='%232d7a4a' stroke-width='3' stroke-linecap='round' fill='none'/%3E%3C/svg%3E" alt="avatar" id="navAvatar">
          <?php endif; ?>
          <span class="points" id="navPoints"><i class="fa-solid fa-star"></i> <?php echo number_format($user['eco_points'] ?? 2450); ?></span>
        </a>
        <a href="logout.php" class="nav-item" title="Logout" style="color:#d64a3b;"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
      </nav>
    </header>

    <section class="hero-section" id="home">
      <div class="hero-text">
        <h2>Hello, <?php echo htmlspecialchars($user['name']); ?> </h2>
        <p>Small actions today, greener tomorrow. <span style="font-size:0.85em; opacity:0.8;">(Connected to tatang DB)</span></p>
      </div>
    </section>

    <section class="section" id="quick-actions">
      <div class="section-title">
        <i class="fa-solid fa-bolt" style="color:#2d7a4a;"></i> Quick Actions
      </div>

      <div class="cards-grid">
        <div class="card">
          <div class="card-icon green-bg"><i class="fa-solid fa-book-open"></i></div>
          <h4>Recycle Guide</h4>
          <p>Learn how to segregate waste properly.</p>
          <button class="card-action" data-guide="biodegradable">Learn more <i class="fa-solid fa-arrow-right"></i></button>
        </div>

        <div class="card">
          <div class="card-icon yellow-bg"><i class="fa-solid fa-triangle-exclamation"></i></div>
          <h4>Report Illegal Dumping</h4>
          <p>Help keep our community clean.</p>
          <button class="card-action" id="openReportBtn">Report now <i class="fa-solid fa-arrow-right"></i></button>
        </div>

        <div class="card">
          <div class="card-icon blue-bg"><i class="fa-solid fa-calendar-alt"></i></div>
          <h4>Collection Schedule</h4>
          <p>View garbage collection dates.</p>
          <button class="card-action" id="openSchedulePreviewBtn">Learn more <i class="fa-solid fa-arrow-right"></i></button>
        </div>

        <div class="card">
          <div class="card-icon purple-bg"><i class="fa-solid fa-earth-asia"></i></div>
          <h4>Climate Action Tips</h4>
          <p>Batteries, chemicals, e-waste, etc.</p>
          <button class="card-action" id="openClimateBtn">Learn more <i class="fa-solid fa-arrow-right"></i></button>
        </div>
      </div>
    </section>

    <!-- IMPACT STATS (Connected to tatang DB) -->
    <section class="section" id="our-impact">
      <div class="section-title">
        <i class="fa-solid fa-chart-simple" style="color:#2d7a4a;"></i> Our Impact
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon green"><i class="fa-solid fa-recycle"></i></div>
          <div class="stat-info">
            <span class="stat-label">Items Recycled</span>
            <span class="stat-number"><?php echo htmlspecialchars($itemsRecycled); ?></span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon blue"><i class="fa-solid fa-circle-exclamation"></i></div>
          <div class="stat-info">
            <span class="stat-label">Reports Submitted</span>
            <span class="stat-number" id="reportCount"><?php echo number_format($reportCount); ?></span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon yellow"><i class="fa-solid fa-bullseye"></i></div>
          <div class="stat-info">
            <span class="stat-label">Recycling Centers</span>
            <span class="stat-number"><?php echo number_format($recyclingCentersCount); ?></span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon teal"><i class="fa-solid fa-users"></i></div>
          <div class="stat-info">
            <span class="stat-label">Community Users</span>
            <span class="stat-number"><?php echo number_format($volunteerCount); ?></span>
          </div>
        </div>
      </div>
    </section>

    <!-- WISE SEARCH -->
    <section class="section" id="search-result">
      <div class="section-title">
        <i class="fa-solid fa-magnifying-glass" style="color:#2d7a4a;"></i> Wise Search
      </div>

      <div class="search-bar">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="wiseSearchInput" placeholder="Search an item — e.g. plastic bottle, battery, banana peel, cardboard…" autocomplete="off">
        <button class="search-btn" id="wiseSearchBtn">Search</button>
      </div>

      <!-- BROWSE BY CATEGORY -->
      <div class="browse-category-wrap">
        <h3 class="browse-category-title">Browse by category</h3>
        <div class="browse-category-grid">
          <div class="browse-card" data-guide="biodegradable">
            <div class="browse-card-icon green-bg"><i class="fa-solid fa-leaf"></i></div>
            <h4>Biodegradable</h4>
            <p>Food, plants, paper, etc.</p>
            <span class="browse-link">Learn more <i class="fa-solid fa-arrow-right"></i></span>
          </div>

          <div class="browse-card" data-guide="recyclable">
            <div class="browse-card-icon yellow-bg"><i class="fa-solid fa-recycle"></i></div>
            <h4>Recyclable</h4>
            <p>Plastic, glass, metal, paper</p>
            <span class="browse-link">Learn more <i class="fa-solid fa-arrow-right"></i></span>
          </div>

          <div class="browse-card" data-guide="residual">
            <div class="browse-card-icon blue-bg"><i class="fa-solid fa-trash-can"></i></div>
            <h4>Residual</h4>
            <p>Non-recyclable waste</p>
            <span class="browse-link">Learn more <i class="fa-solid fa-arrow-right"></i></span>
          </div>

          <div class="browse-card" data-guide="hazardous">
            <div class="browse-card-icon purple-bg"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <h4>Hazardous</h4>
            <p>Batteries, chemicals, e-waste, etc.</p>
            <span class="browse-link">Learn more <i class="fa-solid fa-arrow-right"></i></span>
          </div>
        </div>
      </div>

      <div class="detail-section-wrapper" id="searchResult">
        <div class="detail-container">
          <div class="item-card-left">
            <div class="item-visual" id="itemVisual"></div>
            <div class="details-content">
              <h2 id="itemName">Plastic Bottle</h2>
              <div class="category-text">Category: <span id="itemCategory">Recyclable</span></div>
              <h4>Where to throw:</h4>
              <p id="itemWhere">Rinse and dispose in the Yellow Bin for recyclables.</p>
              <button class="find-center-btn" id="findCenterBtn">
                <i class="fa-solid fa-location-crosshairs"></i> Find Recycling Centers Near You
              </button>
            </div>
          </div>

          <div class="item-card-right">
            <div class="steps-content-wrapper">
              <ul class="steps-list" id="stepsList"></ul>
              <div class="bin-visual" id="binVisual"></div>
            </div>
            <div class="pagination-dots" id="categoryDots">
              <div class="dot" data-cat="biodegradable" title="Biodegradable"></div>
              <div class="dot active" data-cat="recyclable" title="Recyclable"></div>
              <div class="dot" data-cat="residual" title="Residual"></div>
              <div class="dot" data-cat="hazardous" title="Hazardous"></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SCHEDULE -->
    <section class="section" id="schedule">
      <div class="section-title">
        <i class="fa-regular fa-calendar" style="color:#2d7a4a;"></i> Collection Schedule
      </div>

      <div class="schedule-dashboard">
        <div class="schedule-left-sidebar">
          <div class="schedule-legend-box">
            <h4>Collection Week</h4>
            <div class="legend-item" data-legend="biodegradable"><span class="legend-circle circle-green"></span> Sunday and Monday — Biodegradable</div>
            <div class="legend-item" data-legend="recyclable"><span class="legend-circle circle-yellow"></span> Tuesday and Thursday — Recyclable</div>
            <div class="legend-item" data-legend="residual"><span class="legend-circle circle-blue"></span> Wednesday and Saturday — Residual</div>
            <div class="legend-item" data-legend="hazardous"><span class="legend-circle circle-red"></span> Friday — Hazardous</div>
          </div>

          <div class="schedule-location-box">
            <h4>Select Your Location</h4>
            <label for="citySelect" style="font-size: 0.75rem; color: #666; display: block; margin-bottom: 0.2rem;">City / Municipality</label>
            <select class="select-field" id="citySelect">
              <option value="Lapu-Lapu City" selected>Lapu-Lapu City</option>
              <option value="Cebu City">Cebu City</option>
              <option value="Mandaue City">Mandaue City</option>
            </select>

            <label for="brgySelect" style="font-size: 0.75rem; color: #666; display: block; margin-bottom: 0.2rem;">Barangay</label>
            <select class="select-field" id="brgySelect">
              <option value="Subabasbas" <?php echo ($user['barangay'] === 'Subabasbas') ? 'selected' : ''; ?>>Subabasbas</option>
              <option value="Basak" <?php echo ($user['barangay'] === 'Basak') ? 'selected' : ''; ?>>Basak</option>
              <option value="Marigondon" <?php echo ($user['barangay'] === 'Marigondon') ? 'selected' : ''; ?>>Marigondon</option>
              <option value="Pajo" <?php echo ($user['barangay'] === 'Pajo') ? 'selected' : ''; ?>>Pajo</option>
              <option value="Poblacion">Poblacion</option>
              <option value="Mactan">Mactan</option>
              <option value="Punta Engaño">Punta Engaño</option>
            </select>

            <button class="view-btn" id="viewScheduleBtn">View Schedule</button>
          </div>
        </div>

        <div class="schedule-calendar-center">
          <div class="calendar-header">
            <i class="fa-solid fa-chevron-left" id="prevMonth" role="button" aria-label="Previous month"></i>
            <span id="calMonthLabel">May 2026</span>
            <i class="fa-solid fa-chevron-right" id="nextMonth" role="button" aria-label="Next month"></i>
          </div>
          <div class="calendar-sub" id="calendarSub"><?php echo htmlspecialchars($user['barangay']); ?>, <?php echo htmlspecialchars($user['city']); ?></div>

          <div class="calendar-grid" id="calendarGrid"></div>

          <div style="margin-top: 1.2rem; border-top: 1px solid #eee; padding-top: 0.8rem; display: flex; gap: 1rem; font-size: 0.75rem; flex-wrap: wrap;">
            <span><strong>LEGEND:</strong></span>
            <span style="color: #208e3a;">● Biodegradable</span>
            <span style="color: #e8b32a;">● Recyclable</span>
            <span style="color: #1b659c;">● Residual</span>
            <span style="color: #d64a3b;">● Hazardous</span>
          </div>
        </div>

        <div class="schedule-right-sidebar">
          <h4 style="font-size: 0.95rem; color: #1a402b;">Upcoming Collections</h4>
          <div id="upcomingList" style="display:flex;flex-direction:column;gap:1rem;"></div>

          <button class="view-btn" id="viewAllUpcomingBtn" style="margin-top: 0.5rem;">
            View All <i class="fa-solid fa-arrow-right"></i>
          </button>

          <div class="reminder-toggle" id="reminderToggle">
            <div>
              <h5 style="font-size: 0.85rem; color: #1a402b;">Reminder</h5>
              <p style="font-size: 0.7rem; color: #666;">Get notified before collection</p>
            </div>
            <i class="fa-regular fa-bell-slash" id="reminderIcon" style="font-size: 1.2rem; color: #888;"></i>
          </div>
        </div>
      </div>
    </section>

    <!-- COMMUNITY -->
    <section class="section" id="community">
      <div class="section-title">
        <i class="fa-solid fa-people-group" style="color:#2d7a4a;"></i> Community
      </div>

      <div class="community-grid">
        <div class="community-panel">
          <h3><i class="fa-solid fa-hands-holding-circle"></i> Community Activities</h3>
          
          <?php if (!empty($activities)): ?>
            <?php foreach ($activities as $act): ?>
              <div class="activity-item-row">
                <div class="activity-item-info">
                  <div class="activity-item-icon <?php echo htmlspecialchars($act['icon_bg'] ?? 'green'); ?>">
                    <i class="fa-solid <?php echo htmlspecialchars($act['icon'] ?? 'fa-tree'); ?>"></i>
                  </div>
                  <div class="activity-item-text">
                    <h5><?php echo htmlspecialchars($act['title']); ?></h5>
                    <p><?php echo date('M d, Y • g:i A', strtotime($act['activity_date'])); ?><br><?php echo htmlspecialchars($act['location']); ?></p>
                  </div>
                </div>
                <button class="join-activity-btn" data-id="<?php echo $act['id']; ?>" data-activity="<?php echo htmlspecialchars($act['title']); ?>">Join Activity</button>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="font-size:0.85rem; color:#888;">No upcoming activities listed.</p>
          <?php endif; ?>

          <a href="#" class="view-all-link" onclick="event.preventDefault(); showToast('Showing all community activities', 'fa-calendar-check');">View All Activities <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <div class="community-panel">
          <h3><i class="fa-solid fa-bullhorn"></i> Community Announcements</h3>

          <?php if (!empty($announcements)): ?>
            <?php foreach ($announcements as $ann): ?>
              <div class="announcement-item" onclick="openAnnouncementModal(<?php echo htmlspecialchars(json_encode($ann['title'])); ?>, <?php echo htmlspecialchars(json_encode($ann['content'])); ?>)" style="cursor:pointer;">
                <div class="announcement-icon"><i class="fa-solid fa-recycle"></i></div>
                <div class="announcement-text">
                  <h5><?php echo htmlspecialchars($ann['title']); ?></h5>
                  <p><?php echo htmlspecialchars($ann['content']); ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="font-size:0.85rem; color:#888;">No announcements at this time.</p>
          <?php endif; ?>

          <a href="#" class="view-all-link" id="readAnnouncementBtn">View All Announcements <i class="fa-solid fa-arrow-right"></i></a>
        </div>
      </div>

      <div class="community-banner">
        <div class="community-banner-text">
          <i class="fa-solid fa-recycle"></i>
          <h3>Together we can build a<br>cleaner, greener, and more resilient community.</h3>
        </div>
        <div class="community-banner-image-wrap">
          <img src="https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?auto=format&fit=crop&w=600&q=80" alt="Community members cleaning up" class="community-banner-img">
        </div>
      </div>
    </section>

    <!-- PROFILE -->
    <section class="section" id="profile">
      <div class="section-title">
        <i class="fa-regular fa-user" style="color:#2d7a4a;"></i> Profile
      </div>
      <div class="profile-layout">

        <div class="profile-sidebar">
          <div class="profile-card">
            <div class="profile-avatar" id="profileAvatar">
              <?php if (!empty($user['avatar'])): ?>
                <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Profile Avatar" style="width:72px; height:72px; object-fit:cover; border-radius:50%;">
              <?php else: ?>
                <svg viewBox="0 0 100 100" width="72" height="72" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Profile avatar">
                  <circle cx="50" cy="50" r="50" fill="#dff0e6"/>
                  <circle cx="50" cy="38" r="16" fill="#2d7a4a"/>
                  <path d="M50 62c-15 0-27 11-29 25 8 6 18 9 29 9s21-3 29-9c-2-14-14-25-29-25z" fill="#2d7a4a"/>
                </svg>
              <?php endif; ?>
            </div>
            <div class="profile-name" id="profileName"><?php echo htmlspecialchars($user['name']); ?></div>
            <div class="profile-email" id="profileEmail"><?php echo htmlspecialchars($user['email']); ?></div>
            <div style="font-size:0.75rem; color:#2d7a4a; font-weight:600; margin-top:2px;">Barangay: <span id="profileBrgyText"><?php echo htmlspecialchars($user['barangay']); ?></span></div>

            <div class="profile-points" id="profilePoints">
              <i class="fa-solid fa-leaf"></i> <?php echo number_format($user['eco_points'] ?? 2450); ?> Eco Points
            </div>

            <div class="profile-level">
              <div class="profile-level-header">
                <span>Level <?php echo htmlspecialchars($user['level'] ?? 4); ?></span>
                <span>Green Protector</span>
              </div>
              <div class="profile-progress-bar">
                <div class="profile-progress-fill" style="width: 81%;"></div>
              </div>
              <div style="text-align: center; font-size: 0.7rem; color: #6b8a78; margin-bottom: 0.5rem;"><?php echo number_format($user['xp'] ?? 2450); ?> / 3,000 XP</div>
            </div>

            <button class="edit-profile-btn" id="editProfileBtn">Edit Profile</button>
            <a href="logout.php" style="display:block; text-align:center; font-size:0.8rem; color:#d64a3b; text-decoration:none; margin-top:0.8rem; font-weight:600;"><i class="fa-solid fa-power-off"></i> Logout</a>
          </div>

          <div class="profile-quote">
            <i class="fa-solid fa-quote-left"></i>
            <div>
              Every action counts. Every waste matters. Together, we can build a greener future.
            </div>
          </div>
        </div>

        <div class="profile-main">
          <h2>My Waste Activity</h2>

          <div class="activity-stats">
            <div class="activity-stat-card">
              <div class="activity-stat-icon green"><i class="fa-solid fa-recycle"></i></div>
              <div class="activity-stat-info">
                <h4>24.5 kg</h4>
                <p>Waste Recycled<br>This Month</p>
              </div>
            </div>
            <div class="activity-stat-card">
              <div class="activity-stat-icon blue"><i class="fa-solid fa-trash-can"></i></div>
              <div class="activity-stat-info">
                <h4>18</h4>
                <p>Items Sorted<br>This Month</p>
              </div>
            </div>
            <div class="activity-stat-card">
              <div class="activity-stat-icon yellow"><i class="fa-solid fa-leaf"></i></div>
              <div class="activity-stat-info">
                <h4>87%</h4>
                <p>Properly Segregated<br>This Month</p>
              </div>
            </div>
          </div>

          <h2>My Achievements</h2>
          <div class="achievements-grid">
            <div class="achievement-card">
              <div class="achievement-icon"><i class="fa-solid fa-seedling"></i></div>
              <div class="achievement-info">
                <h5>Eco Starter</h5>
                <p>You started your eco-friendly journey.</p>
              </div>
            </div>
            <div class="achievement-card">
              <div class="achievement-icon"><i class="fa-solid fa-recycle"></i></div>
              <div class="achievement-info">
                <h5>Recycling Hero</h5>
                <p>Great job recycling regularly.</p>
              </div>
            </div>
            <div class="achievement-card">
              <div class="achievement-icon"><i class="fa-solid fa-trash-can"></i></div>
              <div class="achievement-info">
                <h5>Waste Wise</h5>
                <p>You segregate waste the right way!</p>
              </div>
            </div>
          </div>

          <h2>Recent Activity</h2>
          <div class="activity-list" id="activityList">
            <?php if (!empty($activityLogs)): ?>
              <?php foreach ($activityLogs as $log): ?>
                <div class="activity-item">
                  <div class="activity-item-left">
                    <i class="fa-solid fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($log['action_text']); ?></span>
                  </div>
                  <div class="activity-date">
                    <i class="fa-regular fa-calendar"></i> <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="activity-item">
                <div class="activity-item-left">
                  <i class="fa-solid fa-check-circle"></i>
                  <span>Account setup complete</span>
                </div>
                <div class="activity-date"><i class="fa-regular fa-calendar"></i> Today</div>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </section>

    <footer class="footer">
      <div style="text-align:center; width:100%; margin-bottom:.4rem;">&copy; 2026 WasteWise &middot; Connected to Database <span style="color:#2d7a4a; font-weight:bold;">tatang</span></div>
      <div style="display:flex; justify-content:center; width:100%; gap: 1.5rem;">
        <a data-info="privacy" style="cursor:pointer;">Privacy Policy</a>
        <a data-info="terms" style="cursor:pointer;">Terms of Service</a>
        <a data-info="help" style="cursor:pointer;">Help Center</a>
      </div>
    </footer>

  </div>

  <!-- GUIDE MODAL -->
  <div class="guide-modal-overlay" id="guideModal" role="dialog" aria-modal="true">
    <div class="guide-modal">
      <button class="guide-close" id="guideClose" aria-label="Close guide">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <aside class="guide-sidebar" id="guideSidebar"></aside>
      <div class="guide-main" id="guideMainContent"></div>
    </div>
  </div>

  <!-- CLIMATE ACTION MODAL -->
  <div class="climate-modal-overlay" id="climateModal" role="dialog" aria-modal="true">
    <div class="climate-modal">
      <button class="climate-close" id="climateClose" aria-label="Close guide">
        <i class="fa-solid fa-xmark"></i>
      </button>

      <h2 class="climate-modal-title">Climate Action Tips</h2>

      <div class="climate-scroll-container">
        <div class="climate-card">
          <div class="climate-card-img">
            <svg viewBox="0 0 100 120" xmlns="http://www.w3.org/2000/svg">
              <rect x="40" y="8" width="20" height="12" rx="3" fill="#1f6d8f"/>
              <path d="M40 20h20v10l10 14v62a10 10 0 0 1-10 10H40a10 10 0 0 1-10-10V44l10-14z" fill="#5fb3e0"/>
              <rect x="34" y="56" width="32" height="30" rx="4" fill="#ffffff" opacity="0.6"/>
              <path d="M40 66h20M40 76h20" stroke="#3d95c9" stroke-width="3" stroke-linecap="round"/>
            </svg>
          </div>
          <h4>Use a Reusable Bottle</h4>
          <ul>
            <li>Reduce plastic waste by using a reusable bottle instead of single-use plastic bottles.</li>
          </ul>
        </div>

        <div class="climate-card">
          <div class="climate-card-img">
            <svg viewBox="0 0 100 120" xmlns="http://www.w3.org/2000/svg">
              <rect x="45" y="70" width="10" height="40" rx="4" fill="#8d6e4a"/>
              <circle cx="50" cy="46" r="24" fill="#2ecc71"/>
              <circle cx="30" cy="62" r="16" fill="#27ae60"/>
              <circle cx="70" cy="62" r="16" fill="#27ae60"/>
              <path d="M22 110h56" stroke="#c9b18c" stroke-width="6" stroke-linecap="round"/>
            </svg>
          </div>
          <h4>Plant Trees</h4>
          <ul>
            <li>Trees help absorb carbon dioxide and provide cleaner air for our communities.</li>
          </ul>
        </div>

        <div class="climate-card">
          <div class="climate-card-img">
            <svg viewBox="0 0 100 120" xmlns="http://www.w3.org/2000/svg">
              <path d="M50 16c15 19 23 32 23 43a23 23 0 0 1-46 0c0-11 8-24 23-43z" fill="#3498db"/>
              <path d="M50 42c7 10 11 17 11 23a11 11 0 0 1-22 0c0-6 4-13 11-23z" fill="#a9d8f5" opacity="0.65"/>
              <path d="M24 108h52" stroke="#cfe8f7" stroke-width="6" stroke-linecap="round"/>
            </svg>
          </div>
          <h4>Save Water</h4>
          <ul>
            <li>Turn off the tap when it is not needed and avoid wasting clean water.</li>
          </ul>
        </div>
      </div>

      <div class="eco-challenge-banner">
        <div class="eco-challenge-icon">
          <i class="fa-solid fa-leaf"></i>
        </div>
        <div class="eco-challenge-text">
          <h4>Today's Eco Challenge</h4>
          <p>Avoid using single-use plastics today!<br>Cleaner surroundings and healthier ecosystems.</p>
        </div>
      </div>

      <div class="climate-footer">
        <a href="#home" class="back-home-btn" id="climateBackHome">
          <i class="fa-solid fa-arrow-left"></i> Back to Home
        </a>
        <div class="climate-footer-text">
          Small Actions, greener tomorrow
        </div>
      </div>
    </div>
  </div>

  <!-- SCHEDULE PREVIEW MODAL -->
  <div class="simple-overlay" id="schedulePreviewModal" role="dialog" aria-modal="true">
    <div class="simple-modal" style="max-width: 1000px;">
      <button class="simple-close" data-close="schedulePreviewModal" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>

      <p class="simple-sub" style="margin-top: 0.4rem;">
        Preview of your collection calendar for <?php echo htmlspecialchars($user['city']); ?>. Pick your city and barangay in the
        Schedule tab to see your exact pickup dates.
      </p>

      <button class="primary-btn" id="schedulePreviewGoBtn" style="margin-top:1rem;">
        <i class="fa-solid fa-arrow-right"></i> Go to Full Schedule
      </button>
    </div>
  </div>

  <!-- VIEW ALL UPCOMING COLLECTIONS MODAL -->
  <div class="simple-overlay" id="viewAllImagesModal" role="dialog" aria-modal="true">
    <div class="simple-modal" style="max-width: 720px;">
      <button class="simple-close" data-close="viewAllImagesModal" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <h2><i class="fa-solid fa-calendar-check" style="color:#2d7a4a;"></i> Upcoming Collections</h2>
      <p class="simple-sub">Here are your scheduled garbage collection days in <?php echo htmlspecialchars($user['barangay']); ?>.</p>

      <div class="full-schedule-item">
        <div class="center-icon" style="background:#dff0e6;color:#208e3a;"><i class="fa-solid fa-leaf"></i></div>
        <div class="center-info">
          <h5>Biodegradable Waste</h5>
          <p>Sunday &amp; Monday<br>7:00 AM – 10:00 AM</p>
        </div>
        <button class="reminder-btn">Set Reminder</button>
      </div>

      <div class="full-schedule-item">
        <div class="center-icon" style="background:#f5edd6;color:#c2931a;"><i class="fa-solid fa-recycle"></i></div>
        <div class="center-info">
          <h5>Recyclable Waste</h5>
          <p>Tuesday &amp; Thursday<br>7:00 AM – 10:00 AM</p>
        </div>
        <button class="reminder-btn">Set Reminder</button>
      </div>

      <div class="full-schedule-item">
        <div class="center-icon" style="background:#d6eaf3;color:#1b659c;"><i class="fa-solid fa-trash-can"></i></div>
        <div class="center-info">
          <h5>Residual Waste</h5>
          <p>Wednesday &amp; Saturday<br>7:00 AM – 10:00 AM</p>
        </div>
        <button class="reminder-btn">Set Reminder</button>
      </div>

      <div class="full-schedule-item">
        <div class="center-icon" style="background:#e8dff0;color:#d64a3b;"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="center-info">
          <h5>Hazardous Waste</h5>
          <p>Friday<br>7:00 AM – 10:00 AM</p>
        </div>
        <button class="reminder-btn">Set Reminder</button>
      </div>

      <button class="back-home-btn" data-close="viewAllImagesModal" style="margin-top: 1rem;">
        <i class="fa-solid fa-arrow-left"></i> Close
      </button>
    </div>
  </div>

  <!-- REPORT MODAL (Saves to database tatang.illegal_reports) -->
  <div class="simple-overlay" id="reportModal" role="dialog" aria-modal="true">
    <div class="simple-modal" style="max-width: 900px; padding: 2rem;">
      <button class="simple-close" data-close="reportModal" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>

      <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
        
        <div style="flex: 1.5; min-width: 320px;">
          <h2 style="font-size: 1.6rem; font-weight: 700; color: #1a402b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem;">
            <i class="fa-solid fa-triangle-exclamation" style="color:#b48b2a;"></i> Report Details
          </h2>

          <form class="simple-form" id="reportForm" enctype="multipart/form-data">
            <label style="display: block; font-size: 0.78rem; font-weight: 700; color: #2d4a3a; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.5rem;">Upload Photo</label>
            <div id="photoDropArea" style="border: 2px dashed #c8dcd0; border-radius: 12px; padding: 1.2rem; text-align: center; margin-bottom: 1rem; background: #fcfdfe; cursor: pointer; transition: 0.2s;" onclick="document.getElementById('reportPhoto').click()">
              <i class="fa-solid fa-camera" style="font-size: 1.8rem; color: #2d7a4a; margin-bottom: 0.4rem; display: block;"></i>
              <span id="photoPreviewText" style="font-size: 0.85rem; font-weight: 600; color: #1a402b;">Click to upload photo</span>
              <input type="file" id="reportPhoto" name="photo" accept="image/*" style="display: none;">
            </div>

            <label for="reportType" style="display: block; font-size: 0.78rem; font-weight: 700; color: #2d4a3a; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.35rem;">Type of Waste</label>
            <select id="reportType" name="waste_type" required style="width: 100%; padding: 0.7rem 0.9rem; border-radius: 12px; border: 1px solid #c8dcd0; background: #fcfdfe; font-size: 0.9rem; color: #1a402b; font-family: inherit; outline: none; margin-bottom: 1rem;">
              <option value="" disabled selected>Select type of Waste</option>
              <option value="Mixed household waste">Mixed household waste</option>
              <option value="Construction debris">Construction debris</option>
              <option value="Hazardous / chemical">Hazardous / chemical</option>
              <option value="Electronic waste (e-waste)">Electronic waste (e-waste)</option>
              <option value="Other">Other</option>
            </select>

            <label for="reportLocation" style="display: block; font-size: 0.78rem; font-weight: 700; color: #2d4a3a; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.35rem;">Location</label>
            <input type="text" id="reportLocation" name="location" placeholder="e.g. Near Lapu-Lapu Elementary School" required style="width: 100%; padding: 0.7rem 0.9rem; border-radius: 12px; border: 1px solid #c8dcd0; background: #fcfdfe; font-size: 0.9rem; color: #1a402b; font-family: inherit; outline: none; margin-bottom: 1rem;">

            <label for="reportDesc" style="display: block; font-size: 0.78rem; font-weight: 700; color: #2d4a3a; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.35rem;">Description</label>
            <textarea id="reportDesc" name="description" rows="3" placeholder="Provide details ..." style="width: 100%; padding: 0.7rem 0.9rem; border-radius: 12px; border: 1px solid #c8dcd0; background: #fcfdfe; font-size: 0.9rem; color: #1a402b; font-family: inherit; outline: none; margin-bottom: 1rem;"></textarea>

            <label style="display: block; font-size: 0.78rem; font-weight: 700; color: #2d4a3a; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.35rem;">Date and Time</label>
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem;">
              <input type="date" id="reportDate" name="report_date" value="<?php echo date('Y-m-d'); ?>" style="flex: 1; padding: 0.7rem 0.9rem; border-radius: 12px; border: 1px solid #c8dcd0; background: #fcfdfe; font-size: 0.9rem; color: #1a402b; font-family: inherit; outline: none;">
              <input type="time" id="reportTime" name="report_time" value="<?php echo date('H:i'); ?>" style="flex: 1; padding: 0.7rem 0.9rem; border-radius: 12px; border: 1px solid #c8dcd0; background: #fcfdfe; font-size: 0.9rem; color: #1a402b; font-family: inherit; outline: none;">
            </div>

            <button type="submit" id="submitReportBtn" class="primary-btn" style="background: #1b653a; color: #fff; border: none; padding: 0.85rem; border-radius: 12px; font-weight: 700; font-size: 0.95rem; cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; justify-content: center; gap: 0.6rem; width: 100%;">
              <i class="fa-solid fa-paper-plane"></i> Submit Report to tatang DB
            </button>
          </form>
        </div>

        <div style="flex: 1; min-width: 280px; background: #f8faf8; border-radius: 1.5rem; padding: 1.5rem; border: 1px solid #e4eee8;">
          <h3 style="font-size: 1.1rem; font-weight: 700; color: #1a402b; margin-bottom: 1.2rem;">Report Status Workflow</h3>

          <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="display: flex; align-items: flex-start; gap: 0.8rem;">
              <div style="width: 32px; height: 32px; border-radius: 50%; background: #f5edd6; color: #b48b2a; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0;">
                <i class="fa-regular fa-clock"></i>
              </div>
              <div>
                <h5 style="font-size: 0.9rem; font-weight: 700; color: #1a402b; margin-bottom: 0.1rem;">Pending</h5>
                <p style="font-size: 0.75rem; color: #6b8a78; line-height: 1.3;">Your report has been saved to database</p>
              </div>
            </div>

            <div style="display: flex; align-items: flex-start; gap: 0.8rem;">
              <div style="width: 32px; height: 32px; border-radius: 50%; background: #d6eaf3; color: #1f6d8f; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0;">
                <i class="fa-solid fa-spinner"></i>
              </div>
              <div>
                <h5 style="font-size: 0.9rem; font-weight: 700; color: #1a402b; margin-bottom: 0.1rem;">Under Review / Assigned</h5>
                <p style="font-size: 0.75rem; color: #6b8a78; line-height: 1.3;">Collector dispatched to site</p>
              </div>
            </div>

            <div style="display: flex; align-items: flex-start; gap: 0.8rem;">
              <div style="width: 32px; height: 32px; border-radius: 50%; background: #dff0e6; color: #208e3a; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0;">
                <i class="fa-solid fa-check"></i>
              </div>
              <div>
                <h5 style="font-size: 0.9rem; font-weight: 700; color: #1a402b; margin-bottom: 0.1rem;">Resolved</h5>
                <p style="font-size: 0.75rem; color: #6b8a78; line-height: 1.3;">The waste has been cleaned</p>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- RECYCLING CENTERS MODAL (Loaded from DB tatang.recycling_centers) -->
  <div class="simple-overlay" id="centersModal" role="dialog" aria-modal="true">
    <div class="simple-modal">
      <button class="simple-close" data-close="centersModal" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <h2><i class="fa-solid fa-location-dot" style="color:#1a7ab8;"></i> Recycling Centers Near You</h2>
      <p class="simple-sub">Drop-off points closest to <strong id="centersLocation"><?php echo htmlspecialchars($user['barangay']); ?>, <?php echo htmlspecialchars($user['city']); ?></strong>.</p>
      
      <div id="centersList">
        <?php foreach ($centers as $cen): ?>
          <div class="center-item">
            <div class="center-icon"><i class="fa-solid fa-recycle"></i></div>
            <div class="center-info">
              <h5><?php echo htmlspecialchars($cen['name']); ?></h5>
              <p><?php echo htmlspecialchars($cen['address']); ?> &middot; <?php echo htmlspecialchars($cen['distance_info']); ?> away</p>
              <div class="tags">
                <?php foreach (explode(',', $cen['accepted_tags']) as $tag): ?>
                  <span><?php echo htmlspecialchars(trim($tag)); ?></span>
                <?php endforeach; ?>
              </div>
            </div>
            <a class="center-dir-btn" href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($cen['name'] . ' ' . $cen['address']); ?>" target="_blank" rel="noopener">
              <i class="fa-solid fa-diamond-turn-right"></i> Directions
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- SYSTEM ANNOUNCEMENT MODAL -->
  <div class="simple-overlay" id="announceModal" role="dialog" aria-modal="true">
    <div class="simple-modal">
      <button class="simple-close" data-close="announceModal" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <h2 id="modalAnnounceTitle"><i class="fa-solid fa-bullhorn" style="color:#b48b2a;"></i> System Announcement</h2>
      <p class="simple-sub" id="modalAnnounceBody">Schedule update for holiday pickup times in Sector B.</p>
    </div>
  </div>

  <!-- EDIT PROFILE MODAL (Saves to database tatang.users) -->
  <div class="simple-overlay" id="editProfileModal" role="dialog" aria-modal="true">
    <div class="simple-modal">
      <button class="simple-close" data-close="editProfileModal" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <h2><i class="fa-regular fa-user" style="color:#2d7a4a;"></i> Edit Profile</h2>
      <p class="simple-sub">Update your display name, contact email, barangay, and avatar.</p>

      <form class="simple-form" id="editProfileForm" enctype="multipart/form-data">
        <label for="editName">Display name</label>
        <input type="text" id="editName" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>

        <label for="editEmail">Email address</label>
        <input type="email" id="editEmail" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

        <label for="editBarangay">Barangay</label>
        <select id="editBarangay" name="barangay">
          <option value="Subabasbas" <?php echo ($user['barangay'] === 'Subabasbas') ? 'selected' : ''; ?>>Subabasbas</option>
          <option value="Basak" <?php echo ($user['barangay'] === 'Basak') ? 'selected' : ''; ?>>Basak</option>
          <option value="Marigondon" <?php echo ($user['barangay'] === 'Marigondon') ? 'selected' : ''; ?>>Marigondon</option>
          <option value="Pajo" <?php echo ($user['barangay'] === 'Pajo') ? 'selected' : ''; ?>>Pajo</option>
          <option value="Poblacion" <?php echo ($user['barangay'] === 'Poblacion') ? 'selected' : ''; ?>>Poblacion</option>
          <option value="Mactan" <?php echo ($user['barangay'] === 'Mactan') ? 'selected' : ''; ?>>Mactan</option>
          <option value="Punta Engaño" <?php echo ($user['barangay'] === 'Punta Engaño') ? 'selected' : ''; ?>>Punta Engaño</option>
        </select>

        <label for="editAvatar">Profile Picture</label>
        <input type="file" id="editAvatar" name="avatar" accept="image/*" style="padding: 0.5rem; background: #f4f8f5; cursor: pointer;">

        <button type="submit" id="saveProfileBtn" class="primary-btn">
          <i class="fa-solid fa-floppy-disk"></i> Save Changes to tatang DB
        </button>
      </form>
    </div>
  </div>

  <!-- GENERIC INFO MODAL -->
  <div class="simple-overlay" id="infoModal" role="dialog" aria-modal="true">
    <div class="simple-modal">
      <button class="simple-close" data-close="infoModal" aria-label="Close">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <h2 id="infoTitle">Information</h2>
      <div id="infoBody"></div>
    </div>
  </div>

  <!-- TOAST NOTIFICATION -->
  <div class="toast" id="toast"><i class="fa-solid fa-circle-check"></i> Connected to tatang DB</div>

  <!-- JAVASCRIPT LOGIC (Connected to Backend Custom PHP) -->
  <script>
  (function () {
    'use strict';

    // Injected wasteDB from MySQL database tatang
    const wasteDB = <?php echo json_encode($dbWasteItems, JSON_PRETTY_PRINT); ?>;

    const toastEl = document.getElementById('toast');
    let toastTimer = null;

    function showToast(msg, icon) {
      toastEl.innerHTML = '<i class="fa-solid ' + (icon || 'fa-circle-check') + '"></i> ' + msg;
      toastEl.classList.add('show');
      clearTimeout(toastTimer);
      toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 3000);
    }
    window.showToast = showToast;

    function openOverlay(id) {
      const el = document.getElementById(id);
      if (!el) return;
      el.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeOverlay(id) {
      const el = document.getElementById(id);
      if (!el) return;
      el.classList.remove('open');
      if (!document.querySelector('.simple-overlay.open, .guide-modal-overlay.open, .climate-modal-overlay.open')) {
        document.body.style.overflow = '';
      }
    }

    document.querySelectorAll('[data-close]').forEach(function (btn) {
      btn.addEventListener('click', function () { closeOverlay(btn.dataset.close); });
    });

    document.querySelectorAll('.simple-overlay').forEach(function (ov) {
      ov.addEventListener('click', function (e) {
        if (e.target === ov) closeOverlay(ov.id);
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      document.querySelectorAll('.simple-overlay.open').forEach(function (o) { closeOverlay(o.id); });
      if (guideModal.classList.contains('open')) closeGuide();
      if (climateModal.classList.contains('open')) closeClimateModal();
    });

    document.querySelectorAll('a[href^="#"]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        const id = this.getAttribute('href');
        if (!id || id === '#') return;
        const target = document.querySelector(id);
        if (!target) return;

        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });

        if (this.classList.contains('nav-item')) {
          document.querySelectorAll('.nav-item').forEach(function (n) { n.classList.remove('active'); });
          this.classList.add('active');
        }
      });
    });

    const CATEGORY_STYLE = {
      biodegradable: { color: '#208e3a', dark: '#146527', label: 'Biodegradable' },
      recyclable:    { color: '#e8b32a', dark: '#c2931a', label: 'Recyclable' },
      residual:      { color: '#1b659c', dark: '#154d77', label: 'Residual' },
      hazardous:     { color: '#d64a3b', dark: '#a83428', label: 'Hazardous' }
    };

    const BOTTLE_SVG =
      '<svg class="bottle-img" viewBox="0 0 100 200" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M 35 15 L 65 15 L 65 30 L 75 45 L 75 180 C 75 190, 65 195, 50 195 C 35 195, 25 190, 25 180 L 25 45 L 35 30 Z" fill="#cbe3f0" stroke="#7bb1d4" stroke-width="3"/>' +
        '<rect x="33" y="5" width="34" height="12" rx="2" fill="#3190cc" />' +
        '<path d="M 26 80 Q 50 90 74 80 M 26 110 Q 50 120 74 110 M 26 140 Q 50 150 74 140" stroke="#a0ccf0" stroke-width="2" fill="none"/>' +
      '</svg>';

    function binSVG(catKey) {
      const s = CATEGORY_STYLE[catKey] || CATEGORY_STYLE.biodegradable;
      return '<svg class="bin-img" viewBox="0 0 140 140" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M 15 35 L 125 35 L 115 125 C 113 132, 105 135, 95 135 L 45 135 C 35 135, 27 132, 25 125 Z" fill="' + s.color + '" />' +
        '<path d="M 10 25 L 130 25 L 125 35 L 15 35 Z" fill="' + s.dark + '" />' +
        '<path d="M 50 70 L 60 55 L 70 70 M 60 55 L 60 85 M 75 75 L 85 85 L 75 95 M 85 85 L 55 85" stroke="#ffffff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' +
        '<text x="70" y="115" font-size="11" text-anchor="middle" fill="#ffffff" font-weight="bold">' + s.label + '</text>' +
      '</svg>';
    }

    const itemVisualEl   = document.getElementById('itemVisual');
    const itemNameEl     = document.getElementById('itemName');
    const itemCategoryEl = document.getElementById('itemCategory');
    const itemWhereEl    = document.getElementById('itemWhere');
    const stepsListEl    = document.getElementById('stepsList');
    const binVisualEl    = document.getElementById('binVisual');
    const dotsEl         = document.getElementById('categoryDots');

    function renderItem(key) {
      const item = wasteDB[key];
      if (!item) return;

      const style = CATEGORY_STYLE[item.catKey] || CATEGORY_STYLE.recyclable;

      if (item.visual.type === 'svg') {
        itemVisualEl.innerHTML = BOTTLE_SVG;
      } else {
        itemVisualEl.innerHTML = '<i class="fa-solid ' + (item.visual.icon || 'fa-box') + '" style="color:' + style.color + '"></i>';
      }

      itemNameEl.textContent = item.name;
      itemCategoryEl.textContent = item.category;
      itemCategoryEl.style.color = style.color;
      itemWhereEl.innerHTML = item.where;

      stepsListEl.innerHTML = (item.steps || [])
        .map(function (s) { return '<li><span class="check">✓</span> ' + s + '</li>'; })
        .join('');

      binVisualEl.innerHTML = binSVG(item.catKey);

      dotsEl.querySelectorAll('.dot').forEach(function (d) {
        d.classList.toggle('active', d.dataset.cat === item.catKey);
      });
    }

    const CATEGORY_DEFAULT = {
      biodegradable: 'bananaPeel',
      recyclable: 'plasticBottle',
      residual: 'diaper',
      hazardous: 'battery'
    };

    dotsEl.querySelectorAll('.dot').forEach(function (dot) {
      dot.addEventListener('click', function () {
        const cat = dot.dataset.cat;
        renderItem(CATEGORY_DEFAULT[cat]);
        showToast('Showing ' + CATEGORY_STYLE[cat].label + ' item', 'fa-circle-info');
      });
    });

    function searchWaste(rawQuery) {
      const q = (rawQuery || '').trim().toLowerCase();
      if (!q) {
        showToast('Type an item to search — e.g. "battery"', 'fa-circle-info');
        return;
      }

      let foundKey = null;
      Object.keys(wasteDB).some(function (key) {
        const item = wasteDB[key];
        const name = item.name.toLowerCase();

        if (name === q || name.indexOf(q) !== -1 || q.indexOf(name) !== -1) {
          foundKey = key; return true;
        }
        const hit = (item.keywords || []).some(function (k) {
          return k === q || (k.length > 2 && q.indexOf(k) !== -1) || (q.length > 2 && k.indexOf(q) !== -1);
        });
        if (hit) { foundKey = key; return true; }
        return false;
      });

      if (foundKey) {
        renderItem(foundKey);
        showToast('Found in tatang DB: ' + wasteDB[foundKey].name, 'fa-magnifying-glass');
      } else {
        // Query backend custom PHP API
        fetch('api/search.php?q=' + encodeURIComponent(q))
          .then(res => res.json())
          .then(data => {
            if (data.status === 'success' && data.item) {
              const it = data.item;
              wasteDB[it.item_key] = {
                name: it.name,
                category: it.category_name,
                catKey: it.category_slug,
                visual: { type: it.visual_type, icon: it.visual_icon },
                where: it.where_to_throw,
                steps: it.steps,
                keywords: it.keywords ? it.keywords.split(',') : []
              };
              renderItem(it.item_key);
              showToast('Found in DB: ' + it.name, 'fa-magnifying-glass');
            } else {
              showToast('No match — try "bottle", "battery", "peel"…', 'fa-circle-question');
            }
          })
          .catch(() => {
            showToast('No match — try "bottle", "battery", "peel"…', 'fa-circle-question');
          });
      }
    }

    document.getElementById('wiseSearchBtn').addEventListener('click', function () {
      searchWaste(document.getElementById('wiseSearchInput').value);
    });

    document.getElementById('wiseSearchInput').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        searchWaste(this.value);
      }
    });

    // Default item display
    renderItem('plasticBottle');

    /* ====== RECYCLE GUIDES MODAL ====== */
    const recycleGuides = {
      biodegradable: {
        title: 'Biodegradable', icon: 'fa-leaf', color: '#208e3a',
        description: 'Waste that comes from plants or animals and can naturally decompose.',
        images: ['https://images.unsplash.com/photo-1540420773420-3366772f4999?w=500']
      },
      recyclable: {
        title: 'Recyclable', icon: 'fa-recycle', color: '#e8b32a',
        description: 'Clean materials that can be processed and reused into new products.',
        images: ['https://images.unsplash.com/photo-1532996122724-e3c354a0b15b?w=500']
      },
      residual: {
        title: 'Residual', icon: 'fa-trash-can', color: '#1b659c',
        description: 'Non-recyclable household waste that cannot be decomposed.',
        images: ['https://images.unsplash.com/photo-1563245372-f21724e3856d?w=500']
      },
      hazardous: {
        title: 'Hazardous', icon: 'fa-triangle-exclamation', color: '#d64a3b',
        description: 'Batteries, chemicals, e-waste, and toxic items requiring special drop-off.',
        images: ['https://images.unsplash.com/photo-1508873696983-2df5293cb395?w=500']
      }
    };

    const guideModal       = document.getElementById('guideModal');
    const guideSidebar     = document.getElementById('guideSidebar');
    const guideMainContent = document.getElementById('guideMainContent');
    const guideClose       = document.getElementById('guideClose');

    function renderSidebar(activeKey) {
      guideSidebar.innerHTML = Object.keys(recycleGuides).map(function (key) {
        const g = recycleGuides[key];
        const isActive = key === activeKey ? 'active' : '';
        return '<button class="guide-sidebar-btn ' + isActive + '" data-guide="' + key + '">' +
                 '<i class="fa-solid ' + g.icon + '"></i> ' + g.title +
               '</button>';
      }).join('');

      guideSidebar.querySelectorAll('.guide-sidebar-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { openGuide(btn.dataset.guide); });
      });
    }

    function renderMainContent(key) {
      const g = recycleGuides[key];
      if (!g) return;

      guideMainContent.innerHTML =
        '<div style="display: flex; gap: 2rem; align-items: flex-start;">' +
          '<div style="flex-shrink: 0; width: 220px;">' +
            '<h2 style="font-size: 1.8rem; font-weight: 800; color: #1a402b; margin-bottom: 0.5rem; text-transform: uppercase;">' + g.title + '</h2>' +
            '<p class="guide-subtitle" style="font-size: 0.95rem; color: #4a6f5a; line-height: 1.5;">' + g.description + '</p>' +
          '</div>' +
          '<div style="flex: 1;">' +
            '<div class="grid-card" style="aspect-ratio: 16/9; border-radius: 1rem; overflow:hidden;">' +
              '<img src="' + g.images[0] + '" alt="' + g.title + ' example" style="width:100%;height:100%;object-fit:cover;">' +
            '</div>' +
          '</div>' +
        '</div>';
    }

    function openGuide(key) {
      if (!recycleGuides[key]) key = 'biodegradable';
      renderSidebar(key);
      renderMainContent(key);
      guideModal.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeGuide() {
      guideModal.classList.remove('open');
      document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-guide]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        openGuide(btn.dataset.guide);
      });
    });

    guideClose.addEventListener('click', closeGuide);
    guideModal.addEventListener('click', function (e) {
      if (e.target === guideModal) closeGuide();
    });

    /* ====== CLIMATE ACTION MODAL ====== */
    const climateModal   = document.getElementById('climateModal');
    const climateClose   = document.getElementById('climateClose');
    const openClimateBtn = document.getElementById('openClimateBtn');
    const climateBackHome = document.getElementById('climateBackHome');

    function openClimateModal(e) {
      if (e) e.preventDefault();
      climateModal.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeClimateModal() {
      climateModal.classList.remove('open');
      document.body.style.overflow = '';
    }

    openClimateBtn.addEventListener('click', openClimateModal);
    climateClose.addEventListener('click', closeClimateModal);
    climateBackHome.addEventListener('click', function (e) {
      e.preventDefault();
      closeClimateModal();
      document.getElementById('home').scrollIntoView({ behavior: 'smooth' });
    });
    climateModal.addEventListener('click', function (e) {
      if (e.target === climateModal) closeClimateModal();
    });

    document.getElementById('openSchedulePreviewBtn').addEventListener('click', function () {
      openOverlay('schedulePreviewModal');
    });

    document.getElementById('schedulePreviewGoBtn').addEventListener('click', function () {
      closeOverlay('schedulePreviewModal');
      setTimeout(function () {
        document.getElementById('schedule').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 200);
    });

    const viewAllUpcomingBtn = document.getElementById('viewAllUpcomingBtn');
    if (viewAllUpcomingBtn) {
      viewAllUpcomingBtn.addEventListener('click', function (e) {
        e.preventDefault();
        openOverlay('viewAllImagesModal');
      });
    }

    document.getElementById('findCenterBtn').addEventListener('click', function () {
      openOverlay('centersModal');
    });

    /* ====== SUBMIT REPORT TO tatang DB ====== */
    document.getElementById('openReportBtn').addEventListener('click', function () {
      openOverlay('reportModal');
    });

    const reportPhotoInput = document.getElementById('reportPhoto');
    const photoPreviewText = document.getElementById('photoPreviewText');
    reportPhotoInput.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        photoPreviewText.textContent = 'Selected: ' + this.files[0].name;
      }
    });

    document.getElementById('reportForm').addEventListener('submit', function (e) {
      e.preventDefault();
      const submitBtn = document.getElementById('submitReportBtn');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving to tatang DB…';

      const formData = new FormData(this);
      formData.append('action', 'submit');

      fetch('api/reports.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Report to tatang DB';

        if (data.status === 'success') {
          showToast(data.message, 'fa-circle-check');
          if (data.total_reports) {
            document.getElementById('reportCount').textContent = Number(data.total_reports).toLocaleString();
          }

          // Prepend to activity list
          const list = document.getElementById('activityList');
          const row = document.createElement('div');
          row.className = 'activity-item';
          row.innerHTML =
            '<div class="activity-item-left">' +
              '<i class="fa-solid fa-check-circle"></i>' +
              '<span>' + data.action_text + '</span>' +
            '</div>' +
            '<div class="activity-date"><i class="fa-regular fa-calendar"></i> Just now</div>';
          list.prepend(row);

          this.reset();
          photoPreviewText.textContent = 'Click to upload photo';
          closeOverlay('reportModal');
        } else {
          showToast(data.message || 'Error submitting report', 'fa-triangle-exclamation');
        }
      })
      .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Report to tatang DB';
        showToast('Error connecting to backend.', 'fa-circle-xmark');
      });
    });

    /* ====== JOIN COMMUNITY ACTIVITY ====== */
    document.querySelectorAll('.join-activity-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const activityId = this.dataset.id;
        const activityName = this.dataset.activity;

        if (this.classList.contains('done')) {
          showToast('You are already registered for ' + activityName, 'fa-circle-info');
          return;
        }

        const formData = new FormData();
        formData.append('action', 'join');
        formData.append('activity_id', activityId);
        formData.append('activity_name', activityName);

        fetch('api/activities.php', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            btn.classList.add('done');
            btn.textContent = 'Joined';
            showToast(data.message, 'fa-hands-holding-circle');

            const list = document.getElementById('activityList');
            const row = document.createElement('div');
            row.className = 'activity-item';
            row.innerHTML =
              '<div class="activity-item-left">' +
                '<i class="fa-solid fa-check-circle"></i>' +
                '<span>' + data.action_text + '</span>' +
              '</div>' +
              '<div class="activity-date"><i class="fa-regular fa-calendar"></i> Just now</div>';
            list.prepend(row);
          } else {
            showToast(data.message || 'Error joining activity', 'fa-triangle-exclamation');
          }
        })
        .catch(err => {
          showToast('Connection error.', 'fa-circle-xmark');
        });
      });
    });

    /* ====== EDIT PROFILE ====== */
    const profileNameEl  = document.getElementById('profileName');
    const profileEmailEl = document.getElementById('profileEmail');
    const profileAvatarEl = document.getElementById('profileAvatar');
    const navAvatarEl = document.getElementById('navAvatar');

    document.getElementById('editProfileBtn').addEventListener('click', function () {
      openOverlay('editProfileModal');
    });

    document.getElementById('editProfileForm').addEventListener('submit', function (e) {
      e.preventDefault();
      const saveBtn = document.getElementById('saveProfileBtn');
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving to tatang DB…';

      const formData = new FormData(this);
      formData.append('action', 'update');

      fetch('api/profile.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes to tatang DB';

        if (data.status === 'success') {
          profileNameEl.textContent = data.user.name;
          profileEmailEl.textContent = data.user.email;
          document.getElementById('profileBrgyText').textContent = data.user.barangay;

          if (data.user.avatar) {
            profileAvatarEl.innerHTML = '<img src="' + data.user.avatar + '" alt="Avatar" style="width:72px; height:72px; object-fit:cover; border-radius:50%;">';
            navAvatarEl.src = data.user.avatar;
          }

          closeOverlay('editProfileModal');
          showToast(data.message, 'fa-floppy-disk');
        } else {
          showToast(data.message || 'Error updating profile', 'fa-triangle-exclamation');
        }
      })
      .catch(err => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes to tatang DB';
        showToast('Connection error.', 'fa-circle-xmark');
      });
    });

    /* ====== CALENDAR & SCHEDULE ====== */
    const MONTH_NAMES = ['January','February','March','April','May','June',
                         'July','August','September','October','November','December'];
    const DAY_HEADERS = ['SUN','MON','TUE','WED','THU','FRI','SAT'];

    const COLLECTION_TYPES = {
      0: { key: 'biodegradable', name: 'Biodegradable', color: '#208e3a', icon: 'fa-leaf' },
      1: { key: 'biodegradable', name: 'Biodegradable', color: '#208e3a', icon: 'fa-leaf' },
      2: { key: 'recyclable',    name: 'Recyclable',    color: '#e8b32a', icon: 'fa-recycle' },
      3: { key: 'residual',      name: 'Residual',      color: '#1b659c', icon: 'fa-trash-can' },
      4: { key: 'recyclable',    name: 'Recyclable',    color: '#e8b32a', icon: 'fa-recycle' },
      5: { key: 'hazardous',     name: 'Hazardous',     color: '#d64a3b', icon: 'fa-triangle-exclamation' },
      6: { key: 'residual',      name: 'Residual',      color: '#1b659c', icon: 'fa-trash-can' }
    };

    const today = new Date();
    let calYear  = today.getFullYear();
    let calMonth = today.getMonth();

    const calendarGrid = document.getElementById('calendarGrid');
    const calMonthLabel = document.getElementById('calMonthLabel');

    function renderCalendar() {
      calMonthLabel.textContent = MONTH_NAMES[calMonth] + ' ' + calYear;

      const firstWeekday = new Date(calYear, calMonth, 1).getDay();
      const daysInMonth  = new Date(calYear, calMonth + 1, 0).getDate();

      let html = DAY_HEADERS.map(function (d) {
        return '<div class="cal-day-header">' + d + '</div>';
      }).join('');

      for (let i = 0; i < firstWeekday; i++) {
        html += '<div class="cal-date" style="cursor:default;"></div>';
      }

      for (let day = 1; day <= daysInMonth; day++) {
        const weekday = (firstWeekday + day - 1) % 7;
        const type = COLLECTION_TYPES[weekday];

        let ind = 'ind-green';
        if (type.key === 'recyclable') ind = 'ind-yellow';
        else if (type.key === 'residual') ind = 'ind-blue';
        else if (type.key === 'hazardous') ind = 'ind-red';

        const isToday = (day === today.getDate() &&
                         calMonth === today.getMonth() &&
                         calYear === today.getFullYear());

        html += '<div class="cal-date has-indicator ' + ind + (isToday ? ' today' : '') + '" ' +
                     'data-day="' + day + '" data-wd="' + weekday + '">' + day + '</div>';
      }

      calendarGrid.innerHTML = html;

      calendarGrid.querySelectorAll('.cal-date[data-day]').forEach(function (cell) {
        cell.addEventListener('click', function () {
          calendarGrid.querySelectorAll('.cal-date').forEach(function (c) { c.classList.remove('selected'); });
          cell.classList.add('selected');

          const type = COLLECTION_TYPES[Number(cell.dataset.wd)];
          showToast(MONTH_NAMES[calMonth] + ' ' + cell.dataset.day + ' · ' + type.name + ' collection',
                    type.icon);
        });
      });
    }

    document.getElementById('prevMonth').addEventListener('click', function () {
      calMonth--;
      if (calMonth < 0) { calMonth = 11; calYear--; }
      renderCalendar();
    });

    document.getElementById('nextMonth').addEventListener('click', function () {
      calMonth++;
      if (calMonth > 11) { calMonth = 0; calYear++; }
      renderCalendar();
    });

    renderCalendar();

    function renderUpcoming() {
      const container = document.getElementById('upcomingList');
      const out = [];
      const d = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);

      while (out.length < 3) {
        const type = COLLECTION_TYPES[d.getDay()];
        out.push({
          date: new Date(d),
          type: type
        });
        d.setDate(d.getDate() + 1);
      }

      container.innerHTML = out.map(function (o) {
        const label = MONTH_NAMES[o.date.getMonth()].slice(0, 3) + ' ' + o.date.getDate() + ', ' + o.date.getFullYear() +
                      ' • ' + DAY_HEADERS[o.date.getDay()].charAt(0) + DAY_HEADERS[o.date.getDay()].slice(1).toLowerCase();
        return '<div class="upcoming-card">' +
                 '<div class="upcoming-icon" style="background:' + o.type.color + ';">' +
                   '<i class="fa-solid ' + o.type.icon + '"></i>' +
                 '</div>' +
                 '<div class="upcoming-info">' +
                   '<h5>' + label + '</h5>' +
                   '<p><strong>' + o.type.name + '</strong><br>7:00 AM - 10:00 AM</p>' +
                 '</div>' +
               '</div>';
      }).join('');
    }

    renderUpcoming();

    document.getElementById('viewScheduleBtn').addEventListener('click', function () {
      const city = document.getElementById('citySelect').value;
      const brgy = document.getElementById('brgySelect').value;
      document.getElementById('calendarSub').textContent = brgy + ', ' + city;

      fetch('api/schedule.php?city=' + encodeURIComponent(city) + '&barangay=' + encodeURIComponent(brgy))
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success' && data.schedules.length > 0) {
            showToast('Loaded ' + data.schedules.length + ' schedule routes for ' + brgy, 'fa-calendar-check');
          } else {
            showToast('Showing default weekly schedule for ' + brgy, 'fa-calendar-check');
          }
        })
        .catch(() => {
          showToast('Showing schedule for ' + brgy + ', ' + city, 'fa-calendar-check');
        });
    });

    let reminderOn = false;
    const reminderToggle = document.getElementById('reminderToggle');
    const reminderIcon   = document.getElementById('reminderIcon');

    reminderToggle.addEventListener('click', function () {
      reminderOn = !reminderOn;
      if (reminderOn) {
        reminderIcon.className = 'fa-solid fa-bell';
        reminderIcon.style.color = '#2d7a4a';
        showToast('Reminders turned ON', 'fa-bell');
      } else {
        reminderIcon.className = 'fa-regular fa-bell-slash';
        reminderIcon.style.color = '#888';
        showToast('Reminders turned OFF', 'fa-bell-slash');
      }
    });

    window.openAnnouncementModal = function (title, content) {
      document.getElementById('modalAnnounceTitle').innerHTML = '<i class="fa-solid fa-bullhorn" style="color:#b48b2a;"></i> ' + title;
      document.getElementById('modalAnnounceBody').textContent = content;
      openOverlay('announceModal');
    };

    const readAnnouncementBtn = document.getElementById('readAnnouncementBtn');
    if (readAnnouncementBtn) {
      readAnnouncementBtn.addEventListener('click', function (e) {
        e.preventDefault();
        openAnnouncementModal('WasteWise Announcements', 'Check back regularly for holiday route changes and community cleanup drives in Lapu-Lapu City.');
      });
    }

    const INFO = {
      privacy: {
        title: 'Privacy Policy',
        body: '<p class="simple-sub">WasteWise collects only the information needed to run collection schedules and community reports.</p>' +
              '<ul class="guide-list">' +
                '<li><i class="fa-solid fa-circle-check"></i> <span>We never sell your personal data.</span></li>' +
                '<li><i class="fa-solid fa-circle-check"></i> <span>Location data is used only to match you with nearby collection routes and recycling centers.</span></li>' +
                '<li><i class="fa-solid fa-circle-check"></i> <span>Reports you submit are shared only with your barangay waste office.</span></li>' +
              '</ul>'
      },
      terms: {
        title: 'Terms of Service',
        body: '<p class="simple-sub">By using WasteWise you agree to keep the community accurate and respectful.</p>' +
              '<ul class="guide-list">' +
                '<li><i class="fa-solid fa-circle-check"></i> <span>Submit only truthful waste reports.</span></li>' +
                '<li><i class="fa-solid fa-circle-check"></i> <span>Do not misuse the reporting feature to target individuals.</span></li>' +
                '<li><i class="fa-solid fa-circle-check"></i> <span>Eco Points have no cash value and may be reset each season.</span></li>' +
              '</ul>'
      },
      help: {
        title: 'Help Center',
        body: '<p class="simple-sub">Quick answers to the most common questions.</p>' +
              '<ul class="guide-list">' +
                '<li><i class="fa-solid fa-circle-check"></i> <span><strong>How do I know which bin to use?</strong> Use Wise Search — type the item and we will tell you.</span></li>' +
                '<li><i class="fa-solid fa-circle-check"></i> <span><strong>When is pickup in my area?</strong> Open the Schedule tab, pick your city and barangay, then press View Schedule.</span></li>' +
                '<li><i class="fa-solid fa-circle-check"></i> <span><strong>Where do I bring batteries?</strong> Search "battery" and tap Find Recycling Centers Near You.</span></li>' +
              '</ul>'
      }
    };

    document.querySelectorAll('[data-info]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        const info = INFO[link.dataset.info];
        if (!info) return;
        document.getElementById('infoTitle').textContent = info.title;
        document.getElementById('infoBody').innerHTML = info.body;
        openOverlay('infoModal');
      });
    });

  })();
  </script>
</body>
</html>
