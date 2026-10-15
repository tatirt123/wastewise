<?php
/**
 * WasteWise - Collector Dashboard
 * Database: database (MySQL)
 */
require_once __DIR__ . '/config/db.php';

// Get current collector user or fallback to Juan
$user = getLoggedInUser($pdo);
if (!$user || $user['role'] !== 'collector') {
    $stmt = $pdo->query("SELECT * FROM `users` WHERE `role` = 'collector' LIMIT 1");
    $collector = $stmt->fetch();
    if ($collector) $user = $collector;
}

// Fetch metrics from database DB
$pendingReportsCount = 0;
$assignedRoutesCount = 5;
$illegalReports = [];
$todayTasks = [];

try {
    $pendingReportsCount = (int)$pdo->query("SELECT COUNT(*) FROM `illegal_reports` WHERE `status` = 'Pending'")->fetchColumn();

    // Fetch reports from DB
    $stmtRep = $pdo->query("SELECT * FROM `illegal_reports` ORDER BY `id` DESC LIMIT 5");
    $illegalReports = $stmtRep->fetchAll();

    // Fetch filled-out collection schedules (from Collection Schedule)
    $stmtTasks = $pdo->query("
        SELECT cs.*, 
               COALESCE(wc.name, 'General Waste') AS category_name, 
               COALESCE(wc.slug, 'general') AS category_slug, 
               COALESCE(wc.color, '#16a34a') AS category_color
        FROM `collection_schedules` cs
        LEFT JOIN `waste_categories` wc ON cs.waste_category_id = wc.id
        WHERE cs.collection_date IS NOT NULL 
          AND cs.street_route IS NOT NULL 
          AND TRIM(cs.street_route) != ''
        ORDER BY 
           CASE WHEN cs.collection_date = CURDATE() THEN 0 ELSE 1 END ASC,
           cs.collection_date DESC,
           cs.start_time ASC,
           cs.id DESC
    ");
    $todayTasks = $stmtTasks->fetchAll();

    // If no collection schedules have been filled out yet, fallback to default stops
    if (empty($todayTasks)) {
        $stmtDefault = $pdo->query("
            SELECT cs.*, 
                   COALESCE(wc.name, 'General Waste') AS category_name, 
                   COALESCE(wc.slug, 'general') AS category_slug, 
                   COALESCE(wc.color, '#16a34a') AS category_color
            FROM `collection_schedules` cs
            LEFT JOIN `waste_categories` wc ON cs.waste_category_id = wc.id
            WHERE cs.city = 'North District' OR cs.street_route IN ('Banilad', 'Talamban', 'Pari-an', 'Busay', 'Guadalupe')
            ORDER BY cs.route_order ASC, cs.start_time ASC
            LIMIT 5
        ");
        $todayTasks = $stmtDefault->fetchAll();
    }
    $assignedRoutesCount = count($todayTasks);

    // Coordinate mapping for real live map stops (Lapu-Lapu City & Metro Cebu)
    $knownCoords = [
        // Cebu City & North District
        'banilad' => [10.3400, 123.9130],
        'talamban' => [10.3660, 123.9180],
        'pari-an' => [10.2980, 123.9030],
        'busay' => [10.3550, 123.8800],
        'guadalupe' => [10.3240, 123.8820],
        'cebu' => [10.3157, 123.8854],
        'cebu city' => [10.3157, 123.8854],
        'mandaue' => [10.3340, 123.9400],
        'north district' => [10.3400, 123.9130],

        // Lapu-Lapu City Barangays & Landmarks
        'lapu-lapu' => [10.3157, 123.9514],
        'lapu-lapu city' => [10.3157, 123.9514],
        'subabasbas' => [10.2785, 123.9782],
        'basak' => [10.2915, 123.9610],
        'basak highway' => [10.2915, 123.9610],
        'marigondon' => [10.2740, 123.9680],
        'marigondon crossing' => [10.2740, 123.9680],
        'pajo' => [10.3160, 123.9600],
        'pajo central' => [10.3160, 123.9600],
        'pusok' => [10.3250, 123.9710],
        'ibo' => [10.3280, 123.9850],
        'mactan' => [10.3000, 124.0150],
        'punta engaño' => [10.3120, 124.0300],
        'buaya' => [10.3160, 123.9920],
        'bankal' => [10.3030, 123.9780],
        'pajac' => [10.2920, 123.9840],
        'agus' => [10.2800, 123.9900],
        'maribago' => [10.2880, 124.0020],
        'gun-ob' => [10.3060, 123.9450],
        'gunob' => [10.3060, 123.9450],
        'looc' => [10.3100, 123.9400],
        'poblacion' => [10.3140, 123.9490],
        'canjulao' => [10.3020, 123.9380],
        'babag' => [10.2950, 123.9320],
        'llcc' => [10.3150, 123.9540],

        // Street routes
        'rizal st' => [10.3120, 123.9550],
        'rizal st.' => [10.3120, 123.9550],
        'bonifacio ave.' => [10.3180, 123.9620],
        'bonifacio ave' => [10.3180, 123.9620],
        'mabini st.' => [10.3090, 123.9530],
        'mabini st' => [10.3090, 123.9530],
        'aguinaldo st.' => [10.3140, 123.9580],
        'aguinaldo st' => [10.3140, 123.9580],
        'osmeña rd.' => [10.3170, 123.9480],
        'osmeña rd' => [10.3170, 123.9480],
        'osmena rd.' => [10.3170, 123.9480],
        'osmena rd' => [10.3170, 123.9480],
    ];
    $baseLat = 10.3157;
    $baseLng = 123.9250;

    $geoStops = [];
    foreach ($todayTasks as $index => $t) {
        $stopNumber = $index + 1;
        $locName = !empty($t['street_route']) ? $t['street_route'] : (!empty($t['barangay']) ? $t['barangay'] : $t['city']);
        $locKey = strtolower(trim($locName));
        
        $matchedCoords = null;
        foreach ($knownCoords as $k => $c) {
            if ($locKey === $k || strpos($locKey, $k) !== false || strpos($k, $locKey) !== false) {
                $matchedCoords = $c;
                break;
            }
        }
        if (!$matchedCoords) {
            $hash = crc32($locKey) % 1000;
            $latOffset = (($hash % 20) - 10) * 0.0035;
            $lngOffset = ((($hash / 20) % 20) - 10) * 0.0035;
            $matchedCoords = [$baseLat + $latOffset, $baseLng + $lngOffset];
        }
        
        $geoStops[] = [
            'stop_number' => $stopNumber,
            'id' => (int)$t['id'],
            'location' => $locName,
            'lat' => $matchedCoords[0],
            'lng' => $matchedCoords[1],
            'time' => date('g:i A', strtotime($t['start_time'])) . ' – ' . date('g:i A', strtotime($t['end_time'])),
            'category' => $t['category_name'] ?? 'General Waste',
            'status' => $t['status'] ?? 'Scheduled'
        ];
    }
} catch (Exception $e) { /* fallback */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WasteWise - Collector Dashboard</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Leaflet CSS & JS for Real Interactive Live Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac',
                            400: '#4ade80', 500: '#22c55e', 600: '#16a34a', 700: '#15803d',
                            800: '#166534', 900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f2f5f3; color: #1e293b; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }

        /* Custom Leaflet Map styling */
        .leaflet-container {
            font-family: 'Inter', sans-serif !important;
            background: #f1f5f9;
        }
        .stop-marker-pin {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #16a34a;
            color: #ffffff;
            font-weight: 800;
            font-size: 13px;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.45);
            border: 2.5px solid #ffffff;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }
        .stop-marker-pin.completed {
            background: #059669;
        }
        .stop-marker-pin.in-progress {
            background: #0284c7;
            animation: pulse-ring 1.8s infinite;
        }
        .stop-marker-pin.active-selected {
            transform: scale(1.25);
            border-color: #facc15;
            box-shadow: 0 0 0 4px rgba(250, 204, 21, 0.55);
        }
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(2, 132, 199, 0); }
            100% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0); }
        }
        .truck-live-marker {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #15803d;
            color: #ffffff;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
            border: 3px solid #ffffff;
            font-size: 16px;
            position: relative;
            cursor: pointer;
        }
        .truck-live-marker::after {
            content: '';
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            border: 2px solid #22c55e;
            animation: truck-radar 2s infinite ease-out;
        }
        @keyframes truck-radar {
            0% { transform: scale(0.9); opacity: 1; }
            100% { transform: scale(1.6); opacity: 0; }
        }
        .custom-map-popup .leaflet-popup-content-wrapper {
            border-radius: 14px;
            padding: 4px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
            border: 1px solid #e2e8f0;
        }
        .custom-map-popup .leaflet-popup-content {
            margin: 10px 12px;
            line-height: 1.4;
            font-size: 12px;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col lg:flex-row antialiased">

    <!-- Sidebar -->
    <aside class="w-full lg:w-64 bg-white border-r border-slate-200/80 flex flex-col justify-between shrink-0 lg:min-h-screen z-20">
        <div>
            <!-- Header Logo -->
            <a href="index.php" class="p-5 flex items-center space-x-3 border-b border-slate-100 lg:border-b-0" style="text-decoration:none;">
                <div class="w-10 h-10 rounded-xl bg-brand-100 flex items-center justify-center text-brand-600 text-2xl shadow-sm">
                    <i class="fa-solid fa-recycle"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg text-slate-800 leading-tight tracking-tight">WasteWise</h1>
                    <p class="text-[10px] font-semibold text-slate-400 tracking-tight">Smart Waste Segregation Assistant</p>
                </div>
            </a>

            <!-- Navigation Bar -->
            <nav class="p-3 space-y-1">
                <a href="collector.php" class="nav-item w-full text-left flex items-center space-x-3 px-4 py-3 rounded-xl bg-brand-100/80 text-brand-800 font-bold text-sm transition-all shadow-xs cursor-pointer" style="text-decoration:none;">
                    <i class="fa-solid fa-house w-5 text-center text-brand-600"></i>
                    <span>Collector Dashboard</span>
                </a>

                <a href="dashboard.php" class="nav-item w-full text-left flex items-center space-x-3 px-4 py-3 rounded-xl text-slate-600 hover:bg-slate-100 font-medium text-sm transition-all cursor-pointer" style="text-decoration:none;">
                    <i class="fa-solid fa-leaf w-5 text-center text-slate-400"></i>
                    <span>Citizen Dashboard</span>
                </a>
                <a href="#tasks" class="nav-item w-full text-left flex items-center space-x-3 px-4 py-3 rounded-xl text-slate-600 hover:bg-slate-100 font-medium text-sm transition-all cursor-pointer" style="text-decoration:none;">
                    <i class="fa-regular fa-calendar-days w-5 text-center text-slate-400"></i>
                    <span>Today's Schedule</span>
                </a>

                <a href="#reports" class="nav-item w-full flex items-center justify-between px-4 py-3 rounded-xl text-slate-600 hover:bg-slate-100 font-medium text-sm transition-all cursor-pointer" style="text-decoration:none;">
                    <div class="flex items-center space-x-3">
                        <i class="fa-solid fa-triangle-exclamation w-5 text-center text-slate-400"></i>
                        <span>Illegal Reports</span>
                    </div>
                    <span class="bg-red-500 text-white text-[11px] font-bold px-2 py-0.5 rounded-full shadow-xs"><?php echo $pendingReportsCount ?: 2; ?></span>
                </a>

                <a href="logout.php" class="nav-item w-full text-left flex items-center space-x-3 px-4 py-3 rounded-xl text-red-600 hover:bg-red-50 font-medium text-sm transition-all cursor-pointer" style="text-decoration:none;">
                    <i class="fa-solid fa-arrow-right-from-bracket w-5 text-center text-red-500"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>

        <!-- Sidebar Banner -->
        <div class="p-4 m-3 bg-emerald-50/80 border border-emerald-100 rounded-2xl relative overflow-hidden text-center">
            <div class="relative z-10 flex flex-col items-center">
                <i class="fa-solid fa-truck-moving text-brand-600 text-3xl mb-1"></i>
                <p class="text-xs font-bold text-emerald-950 leading-snug">Connected to </p>
                <p class="text-[10px] text-slate-400 font-medium mt-0.5">Route updates synced live.</p>
                <div class="w-8 h-1 bg-brand-500 rounded-full mt-2"></div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-4 lg:p-7 space-y-6 overflow-y-auto max-w-[1550px] mx-auto">

        <!-- Header -->
        <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-slate-800 tracking-tight">Good day!</h1>
                <p class="text-slate-500 text-xs sm:text-sm font-medium mt-0.5">Assigned route tasks and live collection route schedule.</p>
            </div>

            <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-2.5 bg-white px-3.5 py-2 rounded-xl border border-slate-200/90 shadow-2xs text-xs font-semibold text-slate-700">
                    <i class="fa-regular fa-calendar text-brand-600 text-sm"></i>
                    <div>
                        <span class="block text-[9px] text-slate-400 uppercase tracking-wider font-extrabold leading-none">Today</span>
                        <span class="text-slate-700"><?php echo date('M d, Y'); ?></span>
                    </div>
                </div>

                <a href="logout.php" class="flex items-center space-x-2 bg-white px-3 py-2 border border-slate-200/90 hover:bg-slate-50 rounded-xl text-xs font-bold text-slate-700 transition" style="text-decoration:none;">
                    <div class="w-6 h-6 rounded-full bg-slate-700 text-white flex items-center justify-center text-[10px]">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <span>Collector</span>
                    <span class="text-[10px] text-slate-400">(Logout)</span>
                </a>
            </div>
        </header>

        <!-- Metrics Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            <div class="lg:col-span-9 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center space-x-3.5 hover:border-brand-300 transition-all cursor-pointer">
                    <div class="w-11 h-11 rounded-xl bg-emerald-100/80 flex items-center justify-center text-brand-600 text-lg shrink-0">
                        <i class="fa-solid fa-truck"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800 leading-none"><?php echo $assignedRoutesCount; ?></p>
                        <p class="text-xs font-medium text-slate-500 mt-1">Assigned Routes <span class="text-slate-400">(Today)</span></p>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center space-x-3.5 hover:border-amber-300 transition-all cursor-pointer">
                    <div class="w-11 h-11 rounded-xl bg-amber-100/80 flex items-center justify-center text-amber-600 text-lg shrink-0">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800 leading-none"><?php echo count($illegalReports); ?></p>
                        <p class="text-xs font-medium text-slate-500 mt-1">Active Reports <span class="text-slate-400">(Database DB)</span></p>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center space-x-3.5 hover:border-brand-300 transition-all cursor-pointer">
                    <div class="w-11 h-11 rounded-xl bg-emerald-100/80 flex items-center justify-center text-brand-600 text-lg shrink-0">
                        <i class="fa-solid fa-trash-can"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800 leading-none">0 <span class="text-xs font-semibold">tons</span></p>
                        <p class="text-xs font-medium text-slate-500 mt-1">Total Collected <span class="text-slate-400">(This week)</span></p>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center space-x-3.5 hover:border-brand-300 transition-all cursor-pointer">
                    <div class="w-11 h-11 rounded-xl bg-emerald-100/80 flex items-center justify-center text-brand-600 text-lg shrink-0">
                        <i class="fa-solid fa-leaf"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800 leading-none">0%</p>
                        <p class="text-xs font-medium text-slate-500 mt-1">Accuracy <span class="text-slate-400">(This week)</span></p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3 bg-gradient-to-r from-emerald-600 to-green-500 rounded-2xl p-4 text-white flex items-center justify-between shadow-2xs">
                <div>
                    <h3 class="font-bold text-base leading-snug">Collect.<br>Segregate.<br>Make an Impact.</h3>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-truck-pickup text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <!-- Middle Section: Collection Route MAP -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Collection Route Card with MAP -->
            <div class="lg:col-span-7 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-location-dot text-brand-600 text-base"></i>
                            <h2 class="font-bold text-slate-800 text-base">Live Collection Route</h2>
                        </div>
                        <span class="text-xs font-bold text-brand-600"><?php echo count($todayTasks); ?> Active Stops</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                        <div class="md:col-span-5 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-sm text-slate-800">Route 1 - North District</span>
                                <span class="bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2.5 py-0.5 rounded-full">In Progress</span>
                            </div>
                            <?php 
                                $firstTime = !empty($todayTasks) ? date('g:i A', strtotime($todayTasks[0]['start_time'])) : '8:00 AM';
                                $lastTime = !empty($todayTasks) ? date('g:i A', strtotime(end($todayTasks)['end_time'])) : '12:00 PM';
                            ?>
                            <p class="text-xs text-slate-400 font-semibold"><?php echo $firstTime . ' – ' . $lastTime; ?></p>

                            <!-- Route Steps (Location Time Display) -->
                            <div class="space-y-2 mt-2 text-xs" id="routeStepsListContainer">
                                <?php if (!empty($todayTasks)): ?>
                                    <?php foreach ($todayTasks as $index => $t): 
                                        $sNum = $index + 1;
                                        $loc = !empty($t['street_route']) ? $t['street_route'] : (!empty($t['barangay']) ? $t['barangay'] : $t['city']);
                                        $tStr = date('g:i A', strtotime($t['start_time'])) . ' – ' . date('g:i A', strtotime($t['end_time']));
                                        $cat = $t['category_name'] ?? 'General';
                                        $st = $t['status'] ?? 'Scheduled';
                                        $isCur = ($st === 'In Progress');
                                        $isComp = ($st === 'Completed');
                                        $btnBg = $isCur ? 'bg-emerald-50 border border-emerald-300' : 'bg-slate-50 border border-slate-200/80 hover:bg-emerald-50/80';
                                        $badgeBg = ($isComp || $isCur) ? 'bg-emerald-600 text-white' : 'bg-slate-300 text-slate-600';
                                    ?>
                                    <button id="route-step-<?php echo $sNum; ?>" onclick="selectMapStop(<?php echo $sNum; ?>)" class="route-step-btn w-full text-left flex items-center justify-between p-2.5 rounded-xl <?php echo $btnBg; ?> cursor-pointer transition-all shadow-2xs">
                                        <div class="flex items-center space-x-2.5 min-w-0">
                                            <span class="step-badge w-5 h-5 rounded-full <?php echo $badgeBg; ?> font-bold text-[10px] flex items-center justify-center shrink-0"><?php echo $sNum; ?></span>
                                            <div class="truncate">
                                                <span class="font-semibold text-slate-700 block"><?php echo htmlspecialchars($loc); ?> <span class="text-slate-400 font-normal">(<?php echo htmlspecialchars($cat); ?>)</span></span>
                                                <span class="text-[10px] text-slate-400 font-medium"><?php echo $tStr; ?></span>
                                            </div>
                                        </div>
                                        <span class="route-step-indicator shrink-0">
                                            <?php if ($isComp): ?>
                                                <i class="fa-solid fa-check text-emerald-600 text-xs"></i>
                                            <?php elseif ($isCur): ?>
                                                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                            <?php else: ?>
                                                <span class="inline-block w-2 h-2 rounded-full bg-slate-300"></span>
                                            <?php endif; ?>
                                        </span>
                                    </button>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-xs text-slate-400 py-2">No route stops scheduled.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- STANDARD MAP DISPLAY AREA (Interactive Live Map + Google Maps Ready) -->
                        <div class="md:col-span-7 relative min-h-[380px] h-[380px] rounded-2xl overflow-hidden border border-emerald-300 shadow-sm flex flex-col bg-slate-100">
                            <!-- Standard Interactive Map Container (Always visible, free, real street names & markers) -->
                            <div id="standardLiveMap" class="w-full h-full z-0"></div>

                            <!-- Google Maps JS Canvas (Used when Google Maps API Key is applied) -->
                            <div id="googleMapCanvas" class="w-full h-full hidden z-0"></div>

                            <!-- Map Top Header Controls -->
                            <div class="absolute top-3 right-3 z-[1000] flex items-center gap-2">
                                <button type="button" onclick="focusTruckLocation()" class="bg-white/95 backdrop-blur-md px-3 py-1.5 rounded-xl border border-slate-200 shadow-xs text-xs font-bold text-slate-700 hover:text-emerald-700 hover:border-emerald-300 transition flex items-center gap-1.5 cursor-pointer" title="Focus Collection Truck">
                                    <i class="fa-solid fa-truck-moving text-emerald-600 text-[11px]"></i>
                                    <span>Truck #04</span>
                                </button>
                                <button type="button" onclick="openMapApiKeyModal()" class="bg-white/95 backdrop-blur-md px-3 py-1.5 rounded-xl border border-slate-200 shadow-xs text-xs font-bold text-slate-700 hover:text-emerald-700 hover:border-emerald-300 transition flex items-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-key text-amber-500 text-[11px]"></i>
                                    <span id="mapApiStatusText">Apply API Key</span>
                                </button>
                                <?php 
                                    $firstLocName = !empty($todayTasks[0]['street_route']) ? $todayTasks[0]['street_route'] : 'Lapu-Lapu City';
                                ?>
                                <a id="openInGoogleMapsBtn" 
                                   href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($firstLocName . ' Lapu-Lapu City Cebu'); ?>" 
                                   target="_blank" 
                                   rel="noopener"
                                   class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl text-xs font-bold shadow-xs transition flex items-center gap-1.5">
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i> Open in Maps
                                </a>
                            </div>

                            <!-- Active Stop Location Badge at Bottom -->
                            <div class="absolute bottom-3 left-3 right-3 z-[1000] bg-slate-900/90 backdrop-blur-md text-white px-3.5 py-2.5 rounded-xl shadow-lg border border-slate-700/60 flex items-center justify-between text-xs pointer-events-auto">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-7 h-7 rounded-lg bg-emerald-500 text-white flex items-center justify-center text-xs shrink-0 font-bold">
                                        <i class="fa-solid fa-location-dot"></i>
                                    </div>
                                    <div class="truncate leading-tight">
                                        <p class="font-bold text-[11px] text-emerald-400" id="mapCurrentStopLabel">Stop #1 Selected</p>
                                        <p class="text-xs text-white font-semibold truncate" id="mapCurrentLocationText"><?php echo htmlspecialchars($firstLocName); ?></p>
                                    </div>
                                </div>
                                <span id="mapTypeBadge" class="bg-emerald-500/20 text-emerald-300 text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0 border border-emerald-500/30">
                                    Standard Live Map
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Illegal Report Notifications (From Database) -->
            <div class="lg:col-span-5 space-y-4" id="reports">
                <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-triangle-exclamation text-red-500 text-base"></i>
                            <h2 class="font-bold text-slate-800 text-base">Ilegal Report Notifications</h2>
                        </div>
                        <span class="text-xs font-bold text-slate-400">Database Auth</span>
                    </div>

                    <div id="illegalReportsContainer" class="space-y-3">
                    <?php if (!empty($illegalReports)): ?>
                        <?php foreach (array_slice($illegalReports, 0, 3) as $rep): ?>
                            <?php
                            $photoSrc = !empty($rep['photo_url']) ? htmlspecialchars($rep['photo_url']) : 'uploads/reports/default-report.jpg';
                            ?>
                            <div class="p-3.5 bg-red-50/60 hover:bg-red-100/70 rounded-2xl border border-red-200/90 flex items-center justify-between transition-all" data-report-card="<?php echo $rep['id']; ?>">
                                <div class="flex items-center space-x-3">
                                    <div class="relative group shrink-0 cursor-pointer" onclick="openPhotoLightbox('<?php echo $photoSrc; ?>', '<?php echo htmlspecialchars(addslashes($rep['waste_type'])); ?> - Photo')">
                                        <img src="<?php echo $photoSrc; ?>" alt="Report photo" class="w-14 h-14 rounded-xl object-cover shrink-0 border border-red-200 shadow-xs group-hover:scale-105 transition-all" onerror="this.onerror=null; this.src='uploads/reports/default-report.jpg';" />
                                        <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 rounded-xl flex items-center justify-center text-white text-xs transition-opacity">
                                            <i class="fa-solid fa-magnifying-glass-plus"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-xs text-slate-800"><?php echo htmlspecialchars($rep['waste_type']); ?></h4>
                                        <p class="text-[10px] text-slate-400 font-semibold mt-0.5"><?php echo date('M d, Y • g:i A', strtotime($rep['report_date'] . ' ' . $rep['report_time'])); ?></p>
                                        <p class="text-[11px] font-medium text-slate-600 mt-0.5"><?php echo htmlspecialchars($rep['location']); ?></p>
                                    </div>
                                </div>
                                <span data-report-badge="<?php echo $rep['id']; ?>" class="bg-red-100 text-red-600 text-[10px] font-bold px-2.5 py-1 rounded-full shrink-0"><?php echo htmlspecialchars($rep['status']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-xs text-slate-400">No illegal dumping reports found.</p>
                    <?php endif; ?>
                    </div>

                    <!-- Tracking Timeline with Gradual Stage Progression & Adjustments -->
                    <?php
                    $trackedReport = !empty($illegalReports) ? $illegalReports[0] : [
                        'id' => 1,
                        'waste_type' => 'Mixed Waste Dispatch',
                        'location' => 'Subabasbas, Lapu-Lapu City',
                        'status' => 'Assigned'
                    ];
                    $stageMap = [
                        'Pending' => 0,
                        'Under Review' => 0,
                        'Assigned' => 1,
                        'On the Way' => 2,
                        'Resolved' => 3
                    ];
                    $currentStatus = $trackedReport['status'] ?? 'Assigned';
                    $currentStageIdx = $stageMap[$currentStatus] ?? 1;
                    ?>
                    <div class="pt-3 border-t border-slate-100 space-y-3" id="dispatchTrackingWidget">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <h4 class="text-xs font-bold text-slate-800">Live Dispatch Tracking</h4>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> LIVE
                                    </span>
                                </div>
                                <p class="text-[10px] text-slate-400 font-medium mt-0.5" id="dispatchTargetText">
                                    Report #<span id="trackedReportIdDisplay"><?php echo $trackedReport['id']; ?></span> &bull; <span id="trackedReportWasteDisplay"><?php echo htmlspecialchars($trackedReport['waste_type']); ?></span>
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <?php if (count($illegalReports) > 1): ?>
                                    <select id="dispatchReportSelector" onchange="switchTrackedReport(this.value)" class="text-[10px] font-semibold bg-slate-50 text-slate-700 border border-slate-200 rounded-lg px-2 py-1 focus:ring-1 focus:ring-emerald-500 focus:outline-none cursor-pointer">
                                        <?php foreach ($illegalReports as $idx => $r): ?>
                                            <option value="<?php echo $r['id']; ?>" <?php echo $idx === 0 ? 'selected' : ''; ?>>
                                                #<?php echo $r['id']; ?> - <?php echo htmlspecialchars($r['waste_type']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                                <span id="dispatchCurrentBadge" class="text-[10px] font-bold px-2.5 py-0.5 rounded-full <?php echo ($currentStatus === 'Resolved') ? 'bg-emerald-100 text-emerald-700' : (($currentStatus === 'Pending') ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700'); ?>">
                                    <?php echo htmlspecialchars($currentStatus); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Step-by-step Gradual Timeline -->
                        <div class="relative px-3 py-1">
                            <!-- Background line track -->
                            <div class="absolute left-7 right-7 top-5 h-1 bg-slate-200 rounded-full z-0"></div>
                            <!-- Filled progress line -->
                            <div id="dispatchLineFill" class="absolute left-7 top-5 h-1 bg-emerald-500 rounded-full z-0 transition-all duration-500" style="width: calc((100% - 3.5rem) * <?php echo $currentStageIdx / 3; ?>);"></div>

                            <div class="flex items-center justify-between relative z-10" id="dispatchStepsContainer">
                                <!-- Stage 0: Submitted -->
                                <button type="button" onclick="adjustDispatchStage('Pending')" title="Set status to Submitted (Pending)" class="dispatch-step-node flex flex-col items-center group cursor-pointer focus:outline-none" data-stage-index="0" data-stage-name="Pending">
                                    <div class="step-circle w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all shadow-2xs <?php echo ($currentStageIdx > 0) ? 'bg-emerald-600 text-white' : (($currentStageIdx === 0) ? 'bg-emerald-600 text-white ring-4 ring-emerald-100 shadow-md animate-pulse' : 'bg-slate-100 text-slate-400 border border-slate-200'); ?>">
                                        <i class="fa-solid <?php echo ($currentStageIdx > 0) ? 'fa-check' : 'fa-file-invoice'; ?>"></i>
                                    </div>
                                    <span class="step-label text-[9px] font-bold mt-1.5 transition-colors <?php echo ($currentStageIdx >= 0) ? 'text-slate-800' : 'text-slate-400'; ?>">Submitted</span>
                                </button>

                                <!-- Stage 1: Assigned -->
                                <button type="button" onclick="adjustDispatchStage('Assigned')" title="Set status to Assigned" class="dispatch-step-node flex flex-col items-center group cursor-pointer focus:outline-none" data-stage-index="1" data-stage-name="Assigned">
                                    <div class="step-circle w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all shadow-2xs <?php echo ($currentStageIdx > 1) ? 'bg-emerald-600 text-white' : (($currentStageIdx === 1) ? 'bg-emerald-600 text-white ring-4 ring-emerald-100 shadow-md animate-pulse' : 'bg-slate-100 text-slate-400 border border-slate-200'); ?>">
                                        <i class="fa-solid <?php echo ($currentStageIdx > 1) ? 'fa-check' : 'fa-user-check'; ?>"></i>
                                    </div>
                                    <span class="step-label text-[9px] font-bold mt-1.5 transition-colors <?php echo ($currentStageIdx >= 1) ? 'text-slate-800' : 'text-slate-400'; ?>">Assigned</span>
                                </button>

                                <!-- Stage 2: On the Way -->
                                <button type="button" onclick="adjustDispatchStage('On the Way')" title="Set status to On the Way" class="dispatch-step-node flex flex-col items-center group cursor-pointer focus:outline-none" data-stage-index="2" data-stage-name="On the Way">
                                    <div class="step-circle w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all shadow-2xs <?php echo ($currentStageIdx > 2) ? 'bg-emerald-600 text-white' : (($currentStageIdx === 2) ? 'bg-emerald-600 text-white ring-4 ring-emerald-100 shadow-md animate-pulse' : 'bg-slate-100 text-slate-400 border border-slate-200'); ?>">
                                        <i class="fa-solid <?php echo ($currentStageIdx > 2) ? 'fa-check' : 'fa-truck-moving'; ?>"></i>
                                    </div>
                                    <span class="step-label text-[9px] font-bold mt-1.5 transition-colors <?php echo ($currentStageIdx >= 2) ? 'text-slate-800' : 'text-slate-400'; ?>">On the Way</span>
                                </button>

                                <!-- Stage 3: Resolved -->
                                <button type="button" onclick="adjustDispatchStage('Resolved')" title="Set status to Resolved" class="dispatch-step-node flex flex-col items-center group cursor-pointer focus:outline-none" data-stage-index="3" data-stage-name="Resolved">
                                    <div class="step-circle w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all shadow-2xs <?php echo ($currentStageIdx === 3) ? 'bg-emerald-600 text-white ring-4 ring-emerald-100 shadow-md' : 'bg-slate-100 text-slate-400 border border-slate-200'; ?>">
                                        <i class="fa-solid fa-circle-check"></i>
                                    </div>
                                    <span class="step-label text-[9px] font-bold mt-1.5 transition-colors <?php echo ($currentStageIdx === 3) ? 'text-emerald-700 font-extrabold' : 'text-slate-400'; ?>">Resolved</span>
                                </button>
                            </div>
                        </div>

                        <!-- Quick Stage Adjustments Bar -->
                        <div class="bg-slate-50 border border-slate-200/90 rounded-xl p-2 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Adjust:</span>
                                <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 shadow-2xs" id="quickStageBtnGroup">
                                    <button type="button" onclick="adjustDispatchStage('Pending')" data-btn-stage="Pending" class="dispatch-quick-btn px-2 py-0.5 rounded text-[10px] font-bold transition <?php echo ($currentStatus === 'Pending') ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100'; ?>">Submitted</button>
                                    <button type="button" onclick="adjustDispatchStage('Assigned')" data-btn-stage="Assigned" class="dispatch-quick-btn px-2 py-0.5 rounded text-[10px] font-bold transition <?php echo ($currentStatus === 'Assigned') ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100'; ?>">Assigned</button>
                                    <button type="button" onclick="adjustDispatchStage('On the Way')" data-btn-stage="On the Way" class="dispatch-quick-btn px-2 py-0.5 rounded text-[10px] font-bold transition <?php echo ($currentStatus === 'On the Way') ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100'; ?>">On the Way</button>
                                    <button type="button" onclick="adjustDispatchStage('Resolved')" data-btn-stage="Resolved" class="dispatch-quick-btn px-2 py-0.5 rounded text-[10px] font-bold transition <?php echo ($currentStatus === 'Resolved') ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100'; ?>">Resolved</button>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button type="button" id="advanceDispatchBtn" onclick="advanceNextDispatchStage()" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[10px] inline-flex items-center gap-1 shadow-2xs transition cursor-pointer">
                                    <span>Advance Stage</span>
                                    <i class="fa-solid fa-arrow-right text-[9px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Tables: Today's Tasks & Recent Illegal Reports -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6" id="tasks">

            <!-- Today's Tasks Table from collection_schedules in tatang DB -->
            <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <i class="fa-regular fa-rectangle-list text-brand-600 text-base"></i>
                        <h2 class="font-bold text-slate-800 text-base">Today's Assigned Route Tasks</h2>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-100 font-semibold">
                                <th class="pb-3 pl-2">Time</th>
                                <th class="pb-3">Location / Street</th>
                                <th class="pb-3">Waste Type</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                            <?php if (!empty($todayTasks)): ?>
                                <?php foreach ($todayTasks as $index => $task): ?>
                                    <?php 
                                        $stopNumber = $index + 1;
                                        $timeStr = date('g:i A', strtotime($task['start_time'])) . ' – ' . date('g:i A', strtotime($task['end_time']));
                                        $locStr = !empty($task['street_route']) ? $task['street_route'] : (!empty($task['barangay']) ? $task['barangay'] : $task['city']);
                                        $rawStartTime = date('H:i', strtotime($task['start_time']));
                                        $rawEndTime = date('H:i', strtotime($task['end_time']));
                                        $catId = (int)($task['waste_category_id'] ?? 1);
                                        $statusClass = ($task['status'] === 'Completed') ? 'bg-emerald-100 text-emerald-700' : (($task['status'] === 'In Progress') ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600');
                                    ?>
                                    <tr id="task-row-<?php echo $stopNumber; ?>" 
                                        class="hover:bg-slate-50 cursor-pointer transition select-text" 
                                        onclick="selectMapStop(<?php echo $stopNumber; ?>)"
                                        ondblclick="openTaskStatusModal(<?php echo (int)$task['id']; ?>, '<?php echo htmlspecialchars(addslashes($locStr)); ?>', '<?php echo $rawStartTime; ?>', '<?php echo $rawEndTime; ?>', <?php echo $catId; ?>, '<?php echo htmlspecialchars($task['status']); ?>', <?php echo $stopNumber; ?>)"
                                        title="Click to view stop on map • Double-click or click Update to change time, location or status">
                                        <td class="py-3 pl-2 font-semibold text-slate-800 whitespace-nowrap"><?php echo $timeStr; ?></td>
                                        <td class="py-3 font-semibold text-slate-800">
                                            <div class="flex items-center space-x-2">
                                                <span class="w-5 h-5 rounded-full bg-slate-100 border border-slate-200 text-slate-600 font-bold text-[10px] flex items-center justify-center shrink-0"><?php echo $stopNumber; ?></span>
                                                <span><?php echo htmlspecialchars($locStr); ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <span style="color: <?php echo htmlspecialchars($task['category_color']); ?>; font-weight:bold;">
                                                <?php echo htmlspecialchars($task['category_name']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <button type="button" 
                                                    onclick="event.stopPropagation(); openTaskStatusModal(<?php echo (int)$task['id']; ?>, '<?php echo htmlspecialchars(addslashes($locStr)); ?>', '<?php echo $rawStartTime; ?>', '<?php echo $rawEndTime; ?>', <?php echo $catId; ?>, '<?php echo htmlspecialchars($task['status']); ?>', <?php echo $stopNumber; ?>)"
                                                    title="Click to update details"
                                                    class="group inline-flex items-center cursor-pointer focus:outline-none">
                                                <span id="task-badge-<?php echo $stopNumber; ?>" class="px-2.5 py-1 rounded-full font-bold text-[10px] <?php echo $statusClass; ?> group-hover:ring-2 group-hover:ring-emerald-400/70 transition-all">
                                                    <?php echo htmlspecialchars($task['status']); ?> <i class="fa-solid fa-angle-down text-[8px] ml-0.5 opacity-70"></i>
                                                </span>
                                            </button>
                                        </td>
                                        <td class="py-3 text-center">
                                            <button type="button" 
                                                    onclick="event.stopPropagation(); openTaskStatusModal(<?php echo (int)$task['id']; ?>, '<?php echo htmlspecialchars(addslashes($locStr)); ?>', '<?php echo $rawStartTime; ?>', '<?php echo $rawEndTime; ?>', <?php echo $catId; ?>, '<?php echo htmlspecialchars($task['status']); ?>', <?php echo $stopNumber; ?>)" 
                                                    class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white rounded-lg font-bold text-[11px] cursor-pointer shadow-2xs hover:shadow-xs transition inline-flex items-center gap-1.5">
                                                <i class="fa-solid fa-pen-to-square"></i> Update
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="py-4 text-center text-slate-400">No route tasks scheduled.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Reports Table from illegal_reports in database DB -->
            <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <i class="fa-regular fa-file-lines text-brand-600 text-base"></i>
                        <h2 class="font-bold text-slate-800 text-base">Recent Reports in database</h2>
                    </div>
                </div>

                <div class="space-y-3" id="recentReportsListContainer">
                    <?php if (!empty($illegalReports)): ?>
                        <?php foreach (array_slice($illegalReports, 0, 4) as $rep): ?>
                            <?php
                            $thumbSrc = !empty($rep['photo_url']) ? htmlspecialchars($rep['photo_url']) : 'uploads/reports/default-report.jpg';
                            ?>
                            <div class="p-3 rounded-xl border border-slate-200/80 flex items-center justify-between hover:bg-slate-50 transition" data-report-table-row="<?php echo $rep['id']; ?>">
                                <div class="flex items-center space-x-3">
                                    <div class="relative group shrink-0 cursor-pointer" onclick="openPhotoLightbox('<?php echo $thumbSrc; ?>', 'Report #<?php echo $rep['id']; ?> Photo')">
                                        <img src="<?php echo $thumbSrc; ?>" alt="Report photo" class="w-10 h-10 rounded-xl object-cover shrink-0 border border-slate-200 shadow-2xs group-hover:scale-105 transition" onerror="this.onerror=null; this.src='uploads/reports/default-report.jpg';" />
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-xs text-slate-800"><?php echo htmlspecialchars($rep['waste_type']); ?></h4>
                                        <p class="text-[10px] text-slate-400 font-semibold"><?php echo date('M d, Y', strtotime($rep['report_date'])); ?> &bull; <?php echo htmlspecialchars($rep['location']); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span data-report-badge="<?php echo $rep['id']; ?>" class="font-bold text-[10px] px-2.5 py-1 rounded-full <?php echo ($rep['status']==='Resolved') ? 'bg-emerald-100 text-emerald-700' : (($rep['status']==='Pending') ? 'bg-red-100 text-red-600' : 'bg-sky-100 text-sky-700'); ?>"><?php echo htmlspecialchars($rep['status']); ?></span>
                                    <button onclick="openReportStatus(<?php echo $rep['id']; ?>, '<?php echo htmlspecialchars(addslashes($rep['waste_type'])); ?>', '<?php echo htmlspecialchars(addslashes($rep['location'])); ?>', '<?php echo htmlspecialchars($rep['status']); ?>')" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[10px] cursor-pointer transition">
                                        <i class="fa-solid fa-pen-to-square"></i> Update
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-xs text-slate-400">No reports recorded yet.</p>
                    <?php endif; ?>
                </div>
            </div>


        </div>

    </main>

    <!-- Route Task Detail & Status Update Modal -->
    <div id="taskStatusModal" style="display:none; position:fixed; inset:0; z-index:999999; background:rgba(15,23,42,0.55); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;" onclick="closeTaskStatusModal()">
        <div style="background:#fff; border-radius:1.25rem; padding:2rem; max-width:480px; width:94%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); position:relative;" onclick="event.stopPropagation()">
            <button type="button" onclick="closeTaskStatusModal()" style="position:absolute; top:1.2rem; right:1.2rem; background:#f1f5f9; border:none; color:#64748b; width:32px; height:32px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.9rem;" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h3 style="font-size:1.15rem; font-weight:700; color:#1e293b; margin-bottom:1.2rem; display:flex; align-items:center; gap:0.5rem;">
                <i class="fa-solid fa-route text-emerald-600"></i> Update Assigned Route Task
            </h3>
            
            <input type="hidden" id="statusTaskId">
            <input type="hidden" id="statusTaskStopNum">

            <div style="margin-bottom:1rem;">
                <label style="display:block; font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:0.35rem;">Location / Street</label>
                <input type="text" id="statusTaskLocation" placeholder="e.g. Banilad, Subabasbas, Rizal St." style="width:100%; padding:0.65rem 0.9rem; border-radius:0.75rem; border:1px solid #cbd5e1; font-size:0.9rem; font-family:inherit; color:#1e293b;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
                <div>
                    <label style="display:block; font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:0.35rem;">Start Time</label>
                    <input type="time" id="statusTaskStartTime" style="width:100%; padding:0.65rem 0.9rem; border-radius:0.75rem; border:1px solid #cbd5e1; font-size:0.9rem; font-family:inherit; color:#1e293b;">
                </div>
                <div>
                    <label style="display:block; font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:0.35rem;">End Time</label>
                    <input type="time" id="statusTaskEndTime" style="width:100%; padding:0.65rem 0.9rem; border-radius:0.75rem; border:1px solid #cbd5e1; font-size:0.9rem; font-family:inherit; color:#1e293b;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1.25rem;">
                <div>
                    <label style="display:block; font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:0.35rem;">Waste Category</label>
                    <select id="statusTaskCategory" style="width:100%; padding:0.65rem 0.9rem; border-radius:0.75rem; border:1px solid #cbd5e1; font-size:0.88rem; font-family:inherit; color:#1e293b;">
                        <option value="1">Biodegradable</option>
                        <option value="2">Recyclable</option>
                        <option value="3">Residual</option>
                        <option value="4">Hazardous</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:0.35rem;">Status</label>
                    <select id="newTaskStatusSelect" style="width:100%; padding:0.65rem 0.9rem; border-radius:0.75rem; border:1px solid #cbd5e1; font-size:0.88rem; font-family:inherit; color:#1e293b;">
                        <option value="Completed">Completed</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Pending">Pending</option>
                        <option value="Scheduled">Scheduled</option>
                    </select>
                </div>
            </div>

            <div style="display:flex; gap:0.75rem;">
                <button type="button" id="saveTaskStatusBtn" onclick="saveTaskStatus()" style="flex:1; padding:0.75rem; background:#16a34a; color:#fff; border:none; border-radius:0.75rem; font-weight:700; font-size:0.9rem; cursor:pointer; font-family:inherit;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
                <button type="button" onclick="closeTaskStatusModal()" style="padding:0.75rem 1.2rem; background:#f1f5f9; color:#64748b; border:none; border-radius:0.75rem; font-weight:600; font-size:0.9rem; cursor:pointer; font-family:inherit;">Cancel</button>
            </div>
        </div>
    </div>


    <!-- Status Update Modal -->
    <div id="reportStatusModal" style="display:none; position:fixed; inset:0; z-index:999999; background:rgba(15,23,42,0.55); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;" onclick="closeStatusModal()">
        <div style="background:#fff; border-radius:1.25rem; padding:2rem; max-width:480px; width:94%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); position:relative;" onclick="event.stopPropagation()">
            <button type="button" onclick="closeStatusModal()" style="position:absolute; top:1.2rem; right:1.2rem; background:#f1f5f9; border:none; color:#64748b; width:32px; height:32px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.9rem;" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h3 style="font-size:1.15rem; font-weight:700; color:#1e293b; margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
                <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;"></i> Update Report Status
            </h3>
            <div id="reportStatusInfo" style="background:#f8fafc; border-radius:0.75rem; padding:1rem; margin-bottom:1.2rem; font-size:0.85rem; color:#475569; border:1px solid #e2e8f0;">
                Loading report details...
            </div>
            <input type="hidden" id="statusReportId">
            <label style="display:block; font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:0.4rem;">New Status</label>
            <select id="newStatusSelect" style="width:100%; padding:0.7rem 1rem; border-radius:0.75rem; border:1px solid #cbd5e1; font-size:0.9rem; font-family:inherit; color:#1e293b; margin-bottom:1.2rem;">
                <option value="Pending">Pending</option>
                <option value="Under Review">Under Review</option>
                <option value="Assigned">Assigned</option>
                <option value="On the Way">On the Way</option>
                <option value="Resolved">Resolved</option>
            </select>
            <div style="display:flex; gap:0.75rem;">
                <button type="button" id="saveReportStatusBtn" onclick="saveReportStatus()" style="flex:1; padding:0.75rem; background:#16a34a; color:#fff; border:none; border-radius:0.75rem; font-weight:700; font-size:0.9rem; cursor:pointer; font-family:inherit;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Status
                </button>
                <button type="button" onclick="closeStatusModal()" style="padding:0.75rem 1.2rem; background:#f1f5f9; color:#64748b; border:none; border-radius:0.75rem; font-weight:600; font-size:0.9rem; cursor:pointer; font-family:inherit;">Cancel</button>
            </div>
    </div>

    <!-- Map API Key Modal -->
    <div id="mapApiKeyModal" style="display:none; position:fixed; inset:0; z-index:999999; background:rgba(15,23,42,0.55); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;" onclick="closeMapApiKeyModal()">
        <div style="background:#fff; border-radius:1.25rem; padding:2rem; max-width:440px; width:94%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); position:relative;" onclick="event.stopPropagation()">
            <button type="button" onclick="closeMapApiKeyModal()" style="position:absolute; top:1.2rem; right:1.2rem; background:#f1f5f9; border:none; color:#64748b; width:32px; height:32px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:0.9rem;" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h3 style="font-size:1.15rem; font-weight:700; color:#1e293b; margin-bottom:0.75rem; display:flex; align-items:center; gap:0.5rem;">
                <i class="fa-solid fa-key" style="color:#f59e0b;"></i> Apply Google Maps API Key
            </h3>
            <p style="font-size:0.82rem; color:#64748b; margin-bottom:1.2rem; line-height:1.4;">
                Enter your Google Maps API key below to unlock the official JavaScript Maps API. If no key is entered, the standard map embed displays the live location automatically.
            </p>
            <label style="display:block; font-size:0.75rem; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:0.35rem;">API Key</label>
            <input type="text" id="googleMapsApiKeyInput" placeholder="AIzaSy..." style="width:100%; padding:0.65rem 0.9rem; border-radius:0.75rem; border:1px solid #cbd5e1; font-size:0.88rem; font-family:monospace; color:#1e293b; margin-bottom:1.2rem;">
            <div style="display:flex; gap:0.75rem;">
                <button type="button" onclick="saveMapApiKey()" style="flex:1; padding:0.75rem; background:#16a34a; color:#fff; border:none; border-radius:0.75rem; font-weight:700; font-size:0.9rem; cursor:pointer; font-family:inherit;">
                    <i class="fa-solid fa-check"></i> Apply API Key
                </button>
                <button type="button" onclick="clearMapApiKey()" style="padding:0.75rem 1rem; background:#fee2e2; color:#b91c1c; border:none; border-radius:0.75rem; font-weight:700; font-size:0.85rem; cursor:pointer; font-family:inherit;">
                    Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Photo Lightbox Modal -->
    <div id="photoLightboxModal" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.85); backdrop-filter:blur(6px); align-items:center; justify-content:center; padding:1.5rem;" onclick="closePhotoLightbox()">
        <div style="position:relative; max-width:850px; width:100%; max-height:90vh; display:flex; flex-direction:column; align-items:center;" onclick="event.stopPropagation()">
            <div style="display:flex; justify-content:space-between; align-items:center; width:100%; margin-bottom:0.75rem; color:#fff;">
                <h4 id="photoLightboxTitle" style="font-weight:700; font-size:1rem; display:flex; align-items:center; gap:0.5rem;">
                    <i class="fa-solid fa-camera" style="color:#10b981;"></i> <span>Report Photo</span>
                </h4>
                <button type="button" onclick="closePhotoLightbox()" style="background:rgba(255,255,255,0.15); border:none; color:#fff; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:1.1rem; display:flex; align-items:center; justify-content:center; transition:0.2s;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <img id="photoLightboxImg" src="" alt="Enlarged report photo" style="max-width:100%; max-height:78vh; object-fit:contain; border-radius:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.7); border:1px solid rgba(255,255,255,0.15);" />
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="collectorToast" style="display:none; position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999; background:#1e293b; color:#fff; padding:0.85rem 1.4rem; border-radius:0.85rem; font-size:0.88rem; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,0.25); transition:all 0.3s;"></div>

    <!-- Script for interactive map stop selection + API wiring -->
    <script>
        /* === STANDARD LIVE MAP CONTROLLER (Google Maps Ready + Live View) === */
        const liveStopsData = <?php echo json_encode(!empty($geoStops) ? $geoStops : []); ?>;
        let activeStopIndex = 0;
        let googleMapInstance = null;
        let googleMapMarkers = [];
        let googleTruckMarker = null;
        let standardLeafletMap = null;
        let standardMarkers = [];
        let standardPolyline = null;
        let truckMarker = null;

        function getStoredApiKey() {
            return (localStorage.getItem('google_maps_api_key') || '').trim();
        }

        function openMapApiKeyModal() {
            const modal = document.getElementById('mapApiKeyModal');
            const input = document.getElementById('googleMapsApiKeyInput');
            if (input) input.value = getStoredApiKey();
            if (modal) modal.style.display = 'flex';
        }

        function closeMapApiKeyModal() {
            const modal = document.getElementById('mapApiKeyModal');
            if (modal) modal.style.display = 'none';
        }

        function saveMapApiKey() {
            const input = document.getElementById('googleMapsApiKeyInput');
            const key = input ? input.value.trim() : '';
            if (key) {
                localStorage.setItem('google_maps_api_key', key);
                showCollectorToast('Google Maps API Key applied! Loading standard Google Map…', false);
            } else {
                localStorage.removeItem('google_maps_api_key');
                showCollectorToast('API Key removed. Reverted to standard live map.', false);
            }
            closeMapApiKeyModal();
            updateMapMode();
        }

        function clearMapApiKey() {
            localStorage.removeItem('google_maps_api_key');
            const input = document.getElementById('googleMapsApiKeyInput');
            if (input) input.value = '';
            closeMapApiKeyModal();
            showCollectorToast('Google Maps API Key reset.', false);
            updateMapMode();
        }

        function initStandardMap() {
            const container = document.getElementById('standardLiveMap');
            if (!container || typeof L === 'undefined') return;
            if (standardLeafletMap) {
                standardLeafletMap.invalidateSize();
                return;
            }

            const initial = liveStopsData[0] || { lat: 10.3157, lng: 123.9514 };
            standardLeafletMap = L.map('standardLiveMap', {
                zoomControl: true,
                attributionControl: false
            }).setView([parseFloat(initial.lat), parseFloat(initial.lng)], 14);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(standardLeafletMap);

            standardMarkers = [];
            const latlngs = [];

            liveStopsData.forEach((stop, idx) => {
                const lat = parseFloat(stop.lat);
                const lng = parseFloat(stop.lng);
                latlngs.push([lat, lng]);

                const isInitialActive = (idx === 0);
                const icon = L.divIcon({
                    className: 'custom-stop-marker-wrapper',
                    html: '<div id="stop-pin-' + stop.stop_number + '" class="stop-marker-pin ' + 
                          (stop.status === 'Completed' ? 'completed' : (stop.status === 'In Progress' ? 'in-progress' : '')) + 
                          (isInitialActive ? ' active-selected' : '') + '">' + stop.stop_number + '</div>',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16],
                    popupAnchor: [0, -18]
                });

                const marker = L.marker([lat, lng], { icon: icon }).addTo(standardLeafletMap);
                const popupContent = `
                    <div style="font-family:'Inter',sans-serif;min-width:160px;padding:2px;">
                        <div style="font-weight:700;font-size:12px;color:#0f172a;margin-bottom:2px;">Stop #${stop.stop_number} - ${escapeHtml(stop.location)}</div>
                        <div style="font-size:11px;color:#16a34a;font-weight:600;margin-bottom:2px;">${escapeHtml(stop.category)}</div>
                        <div style="font-size:11px;color:#64748b;margin-bottom:4px;"><i class="fa-regular fa-clock" style="margin-right:3px;"></i>${escapeHtml(stop.time)}</div>
                        <span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;background:#dcfce7;color:#166534;">${escapeHtml(stop.status)}</span>
                    </div>
                `;
                marker.bindPopup(popupContent);
                marker.on('click', () => {
                    selectMapStop(stop.stop_number);
                });
                standardMarkers.push(marker);
            });

            if (latlngs.length > 1) {
                standardPolyline = L.polyline(latlngs, {
                    color: '#16a34a',
                    weight: 4,
                    opacity: 0.85,
                    dashArray: '8, 8',
                    lineCap: 'round'
                }).addTo(standardLeafletMap);
                standardLeafletMap.fitBounds(standardPolyline.getBounds(), { padding: [35, 35] });
            }

            // Add Live Collection Truck Marker
            const initialTruckStop = liveStopsData.find(s => s.status === 'In Progress') || liveStopsData[0] || { lat: 10.3157, lng: 123.9514 };
            const truckIcon = L.divIcon({
                className: 'custom-truck-wrapper',
                html: `
                    <div class="relative flex flex-col items-center pointer-events-auto" style="transform: translate(-50%, -50%);">
                        <div class="truck-live-marker" title="WasteWise Collection Truck #04">
                            <i class="fa-solid fa-truck-moving"></i>
                        </div>
                        <span style="background: rgba(15,23,42,0.92); color: #86efac; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 9999px; margin-top: 4px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); border: 1px solid rgba(51,65,85,0.7); white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: #4ade80; display: inline-block; animation: pulse-ring 1.8s infinite;"></span>
                            Truck #04
                        </span>
                    </div>
                `,
                iconSize: [0, 0],
                iconAnchor: [0, 0],
                popupAnchor: [0, -32]
            });

            truckMarker = L.marker([parseFloat(initialTruckStop.lat), parseFloat(initialTruckStop.lng)], {
                icon: truckIcon,
                zIndexOffset: 2500
            }).addTo(standardLeafletMap);

            truckMarker.bindPopup(`
                <div style="font-family:'Inter',sans-serif;min-width:185px;padding:3px;">
                    <div style="display:flex;align-items:center;gap:6px;font-weight:700;font-size:12px;color:#0f172a;margin-bottom:3px;">
                        <i class="fa-solid fa-truck-moving" style="color:#16a34a;"></i> WasteWise Truck #04
                    </div>
                    <div style="font-size:11px;color:#16a34a;font-weight:600;margin-bottom:2px;">Route 1 - North District</div>
                    <div style="font-size:11px;color:#64748b;margin-bottom:3px;">Driver: Juan Dela Cruz</div>
                    <div style="font-size:10px;color:#94a3b8;border-top:1px solid #f1f5f9;padding-top:3px;margin-top:3px;">
                        <i class="fa-solid fa-satellite-dish" style="color:#22c55e;margin-right:2px;"></i> Live GPS Active
                    </div>
                </div>
            `);

            setTimeout(() => {
                if (standardLeafletMap) standardLeafletMap.invalidateSize();
            }, 300);
        }

        function updateMapMode() {
            const key = getStoredApiKey();
            const statusText = document.getElementById('mapApiStatusText');
            const stdMap = document.getElementById('standardLiveMap');
            const gMap = document.getElementById('googleMapCanvas');
            const badge = document.getElementById('mapTypeBadge');

            if (key) {
                if (statusText) statusText.innerHTML = '<span class="text-emerald-600"><i class="fa-solid fa-check"></i> API Active</span>';
                if (badge) {
                    badge.textContent = 'Google Maps Active';
                    badge.className = 'bg-sky-500/20 text-sky-300 text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0 border border-sky-500/30';
                }
                if (stdMap) stdMap.classList.add('hidden');
                if (gMap) gMap.classList.remove('hidden');
                loadGoogleMapsApi(key);
            } else {
                if (statusText) statusText.textContent = 'Apply API Key';
                if (badge) {
                    badge.textContent = 'Standard Live Map';
                    badge.className = 'bg-emerald-500/20 text-emerald-300 text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0 border border-emerald-500/30';
                }
                if (gMap) gMap.classList.add('hidden');
                if (stdMap) stdMap.classList.remove('hidden');
                initStandardMap();
                selectMapStop(activeStopIndex + 1);
            }
        }

        function loadGoogleMapsApi(apiKey) {
            if (window.google && window.google.maps) {
                window.initGoogleMapsCanvas();
                return;
            }
            const existingScript = document.getElementById('googleMapsApiScript');
            if (existingScript) existingScript.remove();

            const script = document.createElement('script');
            script.id = 'googleMapsApiScript';
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(apiKey) + '&callback=initGoogleMapsCanvas';
            script.async = true;
            script.defer = true;
            script.onerror = function() {
                showCollectorToast('Could not load Google Maps API. Falling back to standard map.', true);
                const stdMap = document.getElementById('standardLiveMap');
                const gMap = document.getElementById('googleMapCanvas');
                if (gMap) gMap.classList.add('hidden');
                if (stdMap) stdMap.classList.remove('hidden');
                initStandardMap();
            };
            document.head.appendChild(script);
        }

        window.initGoogleMapsCanvas = function() {
            const canvas = document.getElementById('googleMapCanvas');
            if (!canvas || !window.google || !window.google.maps) return;

            const initial = liveStopsData[activeStopIndex] || liveStopsData[0] || { lat: 10.3157, lng: 123.9514 };
            googleMapInstance = new google.maps.Map(canvas, {
                center: { lat: parseFloat(initial.lat), lng: parseFloat(initial.lng) },
                zoom: 14,
                mapTypeControl: true,
                streetViewControl: true,
                fullscreenControl: false
            });

            googleMapMarkers = [];
            const bounds = new google.maps.LatLngBounds();

            liveStopsData.forEach(stop => {
                const pos = { lat: parseFloat(stop.lat), lng: parseFloat(stop.lng) };
                bounds.extend(pos);

                const marker = new google.maps.Marker({
                    position: pos,
                    map: googleMapInstance,
                    title: 'Stop #' + stop.stop_number + ' - ' + stop.location,
                    label: { text: String(stop.stop_number), color: '#ffffff', fontWeight: 'bold' }
                });

                const info = new google.maps.InfoWindow({
                    content: '<div style="padding:4px;font-family:Inter,sans-serif;"><strong>Stop #' + stop.stop_number + '</strong><br>' + escapeHtml(stop.location) + '<br><span style="color:#16a34a;">' + escapeHtml(stop.category) + '</span><br><small>' + escapeHtml(stop.time) + '</small></div>'
                });

                marker.addListener('click', () => {
                    info.open(googleMapInstance, marker);
                    selectMapStop(stop.stop_number);
                });

                googleMapMarkers.push({ marker: marker, info: info });
            });

            // Add Collection Truck marker for Google Maps
            const initialTruckStop = liveStopsData.find(s => s.status === 'In Progress') || liveStopsData[0] || { lat: 10.3157, lng: 123.9514 };
            googleTruckMarker = new google.maps.Marker({
                position: { lat: parseFloat(initialTruckStop.lat), lng: parseFloat(initialTruckStop.lng) },
                map: googleMapInstance,
                title: 'WasteWise Collection Truck #04 (Live)',
                zIndex: 9999,
                label: {
                    text: '🚛',
                    fontSize: '18px'
                }
            });
            const truckInfo = new google.maps.InfoWindow({
                content: '<div style="padding:4px;font-family:Inter,sans-serif;"><strong>WasteWise Truck #04</strong><br><span style="color:#16a34a;font-weight:600;">Route 1 - North District</span><br><small>Driver: Juan Dela Cruz • Live GPS Active</small></div>'
            });
            googleTruckMarker.addListener('click', () => {
                truckInfo.open(googleMapInstance, googleTruckMarker);
            });

            if (liveStopsData.length > 1) {
                googleMapInstance.fitBounds(bounds);
            }
        };

        function selectMapStop(stopNum) {
            const stop = liveStopsData.find(s => s.stop_number === stopNum);
            if (!stop) return;
            activeStopIndex = stopNum - 1;

            // 1. If standard Leaflet map is visible, pan to stop and show popup
            const stdContainer = document.getElementById('standardLiveMap');
            if (standardLeafletMap && stdContainer && !stdContainer.classList.contains('hidden')) {
                standardLeafletMap.flyTo([parseFloat(stop.lat), parseFloat(stop.lng)], 15, { animate: true, duration: 0.7 });
                document.querySelectorAll('.stop-marker-pin').forEach(pin => pin.classList.remove('active-selected'));
                const activePin = document.getElementById('stop-pin-' + stopNum);
                if (activePin) activePin.classList.add('active-selected');
                if (standardMarkers[activeStopIndex]) {
                    standardMarkers[activeStopIndex].openPopup();
                }
            }

            // Move Truck Icon to the selected stop
            if (truckMarker) {
                truckMarker.setLatLng([parseFloat(stop.lat), parseFloat(stop.lng)]);
            }

            // 2. If Google Maps JS API instance active, center it
            const gCanvas = document.getElementById('googleMapCanvas');
            if (googleMapInstance && window.google && gCanvas && !gCanvas.classList.contains('hidden')) {
                const pos = { lat: parseFloat(stop.lat), lng: parseFloat(stop.lng) };
                googleMapInstance.panTo(pos);
                googleMapInstance.setZoom(15);
                if (googleMapMarkers[activeStopIndex]) {
                    googleMapMarkers[activeStopIndex].info.open(googleMapInstance, googleMapMarkers[activeStopIndex].marker);
                }
            }
            if (googleTruckMarker) {
                googleTruckMarker.setPosition({ lat: parseFloat(stop.lat), lng: parseFloat(stop.lng) });
            }

            // 3. Update external link
            const extLink = document.getElementById('openInGoogleMapsBtn');
            if (extLink) {
                extLink.href = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent((stop.location || '') + ' Lapu-Lapu City Cebu');
            }

            // 4. Update overlay badges
            const labelEl = document.getElementById('mapCurrentStopLabel');
            const locEl = document.getElementById('mapCurrentLocationText');
            if (labelEl) labelEl.textContent = 'Stop #' + stop.stop_number + ' (' + stop.status + ')';
            if (locEl) locEl.textContent = stop.location + ' • ' + stop.time;

            // 5. Highlight sidebar step button
            const steps = document.querySelectorAll('.route-step-btn');
            steps.forEach((btn, idx) => {
                const stepIndex = idx + 1;
                if (stepIndex === stopNum) {
                    btn.className = "route-step-btn w-full text-left flex items-center justify-between p-2.5 rounded-xl bg-emerald-50 border border-emerald-300 cursor-pointer transition-all shadow-2xs";
                } else {
                    btn.className = "route-step-btn w-full text-left flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 cursor-pointer hover:bg-emerald-50/80 transition-all";
                }
            });

            // 6. Highlight corresponding task row in table
            document.querySelectorAll('[id^="task-row-"]').forEach(row => {
                row.classList.remove('bg-emerald-50/80', 'ring-1', 'ring-emerald-300');
            });
            const activeRow = document.getElementById('task-row-' + stopNum);
            if (activeRow) {
                activeRow.classList.add('bg-emerald-50/80', 'ring-1', 'ring-emerald-300');
            }
        }

        // Center map function to view all stops
        function centerMapOnRoute() {
            if (standardLeafletMap && standardPolyline) {
                standardLeafletMap.fitBounds(standardPolyline.getBounds(), { padding: [35, 35] });
            } else if (googleMapInstance && liveStopsData.length > 0) {
                const bounds = new google.maps.LatLngBounds();
                liveStopsData.forEach(s => bounds.extend({ lat: parseFloat(s.lat), lng: parseFloat(s.lng) }));
                googleMapInstance.fitBounds(bounds);
            }
        }

        function focusTruckLocation() {
            const curStop = liveStopsData[activeStopIndex] || liveStopsData[0];
            if (!curStop) return;
            const stdContainer = document.getElementById('standardLiveMap');
            if (standardLeafletMap && truckMarker && stdContainer && !stdContainer.classList.contains('hidden')) {
                standardLeafletMap.flyTo(truckMarker.getLatLng(), 16, { animate: true, duration: 0.8 });
                truckMarker.openPopup();
            } else if (googleMapInstance && googleTruckMarker) {
                googleMapInstance.panTo(googleTruckMarker.getPosition());
                googleMapInstance.setZoom(16);
            }
            showCollectorToast('Focusing on Collection Truck #04', false);
        }
        window.focusTruckLocation = focusTruckLocation;

        // Initialize Map mode on load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', updateMapMode);
        } else {
            updateMapMode();
        }

        /* === Live Dispatch Tracking Controller === */
        let activeTrackedReport = <?php echo json_encode($trackedReport); ?>;
        const allLoadedReports = <?php echo json_encode($illegalReports); ?>;
        const DISPATCH_STAGES = [
            { key: 'Pending', label: 'Submitted', icon: 'fa-file-invoice' },
            { key: 'Assigned', label: 'Assigned', icon: 'fa-user-check' },
            { key: 'On the Way', label: 'On the Way', icon: 'fa-truck-moving' },
            { key: 'Resolved', label: 'Resolved', icon: 'fa-circle-check' }
        ];

        function getStageIndex(status) {
            if (status === 'Under Review') return 1;
            const idx = DISPATCH_STAGES.findIndex(s => s.key === status);
            return idx !== -1 ? idx : 1;
        }

        function updateDispatchTimelineUI(status) {
            const stageIdx = getStageIndex(status);

            // 1. Update line progress width
            const lineFill = document.getElementById('dispatchLineFill');
            if (lineFill) {
                lineFill.style.width = 'calc((100% - 3.5rem) * ' + (stageIdx / 3) + ')';
            }

            // 2. Update stage nodes
            const nodes = document.querySelectorAll('.dispatch-step-node');
            nodes.forEach((node) => {
                const idx = parseInt(node.dataset.stageIndex, 10);
                const circle = node.querySelector('.step-circle');
                const label = node.querySelector('.step-label');
                const config = DISPATCH_STAGES[idx];

                if (idx < stageIdx) {
                    // Completed milestone
                    circle.className = "step-circle w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all shadow-2xs bg-emerald-600 text-white";
                    circle.innerHTML = '<i class="fa-solid fa-check"></i>';
                    label.className = "step-label text-[9px] font-bold mt-1.5 transition-colors text-slate-800";
                } else if (idx === stageIdx) {
                    // Current active stage (pulsing)
                    circle.className = "step-circle w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all shadow-md bg-emerald-600 text-white ring-4 ring-emerald-100 animate-pulse";
                    circle.innerHTML = '<i class="fa-solid ' + config.icon + '"></i>';
                    label.className = "step-label text-[9px] font-extrabold mt-1.5 transition-colors text-emerald-800";
                } else {
                    // Upcoming pending stage
                    circle.className = "step-circle w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all shadow-2xs bg-slate-100 text-slate-400 border border-slate-200";
                    circle.innerHTML = '<i class="fa-solid ' + config.icon + '"></i>';
                    label.className = "step-label text-[9px] font-bold mt-1.5 transition-colors text-slate-400";
                }
            });

            // 3. Update Current Status Badge
            const badge = document.getElementById('dispatchCurrentBadge');
            if (badge) {
                badge.textContent = status;
                if (status === 'Resolved') {
                    badge.className = "text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700";
                } else if (status === 'Pending') {
                    badge.className = "text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-700";
                } else {
                    badge.className = "text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-sky-100 text-sky-700";
                }
            }

            // 4. Update Quick Adjust Buttons
            document.querySelectorAll('#quickStageBtnGroup [data-btn-stage]').forEach(btn => {
                if (btn.dataset.btnStage === status) {
                    btn.className = "dispatch-quick-btn px-2 py-0.5 rounded text-[10px] font-bold transition bg-emerald-600 text-white";
                } else {
                    btn.className = "dispatch-quick-btn px-2 py-0.5 rounded text-[10px] font-bold transition text-slate-600 hover:bg-slate-100";
                }
            });

            // 5. Update Advance Button text
            const advBtn = document.getElementById('advanceDispatchBtn');
            if (advBtn) {
                if (stageIdx >= DISPATCH_STAGES.length - 1) {
                    advBtn.innerHTML = '<i class="fa-solid fa-check"></i> Completed';
                    advBtn.disabled = true;
                    advBtn.className = "px-2.5 py-1 bg-slate-200 text-slate-400 rounded-lg font-bold text-[10px] inline-flex items-center gap-1 shadow-2xs cursor-not-allowed";
                } else {
                    const nextStage = DISPATCH_STAGES[stageIdx + 1];
                    advBtn.innerHTML = '<span>Advance to ' + nextStage.label + '</span> <i class="fa-solid fa-arrow-right text-[9px]"></i>';
                    advBtn.disabled = false;
                    advBtn.className = "px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[10px] inline-flex items-center gap-1 shadow-2xs transition cursor-pointer";
                }
            }
        }

        function adjustDispatchStage(newStatus) {
            if (!activeTrackedReport || !activeTrackedReport.id) return;
            const reportId = activeTrackedReport.id;

            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('report_id', reportId);
            formData.append('status', newStatus);

            fetch('api/reports.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    activeTrackedReport.status = newStatus;
                    updateDispatchTimelineUI(newStatus);

                    // Update corresponding badge in reports list table if present
                    const tableBadge = document.querySelector('[data-report-badge="' + reportId + '"]');
                    if (tableBadge) {
                        tableBadge.textContent = newStatus;
                        tableBadge.className = "font-bold text-[10px] px-2.5 py-1 rounded-full " +
                            (newStatus === 'Resolved' ? 'bg-emerald-100 text-emerald-700' :
                            (newStatus === 'Pending' ? 'bg-red-100 text-red-600' : 'bg-sky-100 text-sky-700'));
                    }

                    // Update matching object in allLoadedReports
                    const r = allLoadedReports.find(x => x.id == reportId);
                    if (r) r.status = newStatus;

                    showCollectorToast('Live Dispatch adjusted to: ' + newStatus, false);
                } else {
                    showCollectorToast(data.message || 'Error updating dispatch status', true);
                }
            })
            .catch(() => {
                activeTrackedReport.status = newStatus;
                updateDispatchTimelineUI(newStatus);
                showCollectorToast('Live Dispatch set to: ' + newStatus, false);
            });
        }

        function advanceNextDispatchStage() {
            if (!activeTrackedReport) return;
            const currIdx = getStageIndex(activeTrackedReport.status);
            if (currIdx < DISPATCH_STAGES.length - 1) {
                adjustDispatchStage(DISPATCH_STAGES[currIdx + 1].key);
            }
        }

        function switchTrackedReport(reportId) {
            const found = allLoadedReports.find(x => x.id == reportId);
            if (found) {
                activeTrackedReport = found;
                const idDisplay = document.getElementById('trackedReportIdDisplay');
                const wasteDisplay = document.getElementById('trackedReportWasteDisplay');
                if (idDisplay) idDisplay.textContent = found.id;
                if (wasteDisplay) wasteDisplay.textContent = found.waste_type;
                updateDispatchTimelineUI(found.status);
            }
        }

        // Initialize UI states
        if (activeTrackedReport) {
            updateDispatchTimelineUI(activeTrackedReport.status);
        }

        /* === Toast Notification === */
        function showCollectorToast(msg, isError) {
            const t = document.getElementById('collectorToast');
            t.innerHTML = '<i class="fa-solid ' + (isError ? 'fa-circle-xmark' : 'fa-circle-check') + '" style="margin-right:0.5rem;"></i>' + msg;
            t.style.background = isError ? '#dc2626' : '#16a34a';
            t.style.display = 'block';
            setTimeout(() => { t.style.display = 'none'; }, 4000);
        }

        /* === Report Status Modal === */
        function openReportStatus(reportId, wasteType, location, currentStatus) {
            document.getElementById('statusReportId').value = reportId;
            document.getElementById('reportStatusInfo').innerHTML =
                '<strong>Report #' + reportId + '</strong>: ' + wasteType + '<br>' +
                '<span style="color:#94a3b8;">' + location + '</span><br>' +
                '<span style="font-weight:700;">Current Status: ' + currentStatus + '</span>';
            document.getElementById('newStatusSelect').value = currentStatus;
            document.getElementById('reportStatusModal').style.display = 'flex';
        }

        function closeStatusModal() {
            document.getElementById('reportStatusModal').style.display = 'none';
        }

        function saveReportStatus() {
            const reportId = document.getElementById('statusReportId').value;
            const newStatus = document.getElementById('newStatusSelect').value;
            const saveBtn = document.getElementById('saveReportStatusBtn');

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('report_id', reportId);
            formData.append('status', newStatus);

            fetch('api/reports.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Status';

                if (data.status === 'success') {
                    showCollectorToast(data.message, false);
                    closeStatusModal();
                    // Update badge in the reports list
                    const badge = document.querySelector('[data-report-badge="' + reportId + '"]');
                    if (badge) badge.textContent = newStatus;
                    // Reload after short delay to refresh list
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showCollectorToast(data.message || 'Error updating status', true);
                }
            })
            .catch(err => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Status';
                showCollectorToast('Connection error. Please try again.', true);
            });
        }

        /* === Task Status / Edit Modal === */
        function openTaskStatusModal(taskId, location, startTime, endTime, categoryId, currentStatus, stopNum) {
            try {
                const idEl = document.getElementById('statusTaskId');
                const stopNumEl = document.getElementById('statusTaskStopNum');
                const locEl = document.getElementById('statusTaskLocation');
                const startEl = document.getElementById('statusTaskStartTime');
                const endEl = document.getElementById('statusTaskEndTime');
                const catEl = document.getElementById('statusTaskCategory');
                const statusEl = document.getElementById('newTaskStatusSelect');
                const modal = document.getElementById('taskStatusModal');

                if (idEl) idEl.value = taskId || '';
                if (stopNumEl) stopNumEl.value = stopNum || 1;
                if (locEl) locEl.value = location || '';
                if (startEl) startEl.value = startTime || '08:00';
                if (endEl) endEl.value = endTime || '09:00';
                if (catEl) catEl.value = categoryId || '1';
                if (statusEl && currentStatus) statusEl.value = currentStatus;

                if (modal) {
                    modal.style.display = 'flex';
                }
            } catch (err) {
                console.error('Error opening task status modal:', err);
            }
        }

        function closeTaskStatusModal() {
            const modal = document.getElementById('taskStatusModal');
            if (modal) modal.style.display = 'none';
        }

        function saveTaskStatus() {
            const taskId = document.getElementById('statusTaskId').value;
            const stopNum = parseInt(document.getElementById('statusTaskStopNum').value, 10);
            const locationVal = (document.getElementById('statusTaskLocation').value || '').trim();
            const startTime = document.getElementById('statusTaskStartTime').value;
            const endTime = document.getElementById('statusTaskEndTime').value;
            const categoryId = document.getElementById('statusTaskCategory').value;
            const newStatus = document.getElementById('newTaskStatusSelect').value;
            const saveBtn = document.getElementById('saveTaskStatusBtn');

            if (!locationVal) {
                showCollectorToast('Please enter a location or street.', true);
                return;
            }

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

            const formData = new FormData();
            formData.append('action', 'update_task');
            formData.append('schedule_id', taskId);
            formData.append('street_route', locationVal);
            formData.append('location', locationVal);
            formData.append('start_time', startTime);
            formData.append('end_time', endTime);
            formData.append('waste_category_id', categoryId);
            formData.append('category_id', categoryId);
            formData.append('status', newStatus);

            fetch('api/schedule.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes';
                if (data.status === 'success' || data.success) {
                    closeTaskStatusModal();
                    showCollectorToast('Route task updated successfully!', false);
                    setTimeout(() => location.reload(), 700);
                } else {
                    showCollectorToast(data.message || 'Error updating task', true);
                }
            })
            .catch(err => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes';
                showCollectorToast('Connection error. Please try again.', true);
            });
        }

        // Expose functions globally to window for inline onclick handlers
        window.openTaskStatusModal = openTaskStatusModal;
        window.closeTaskStatusModal = closeTaskStatusModal;
        window.saveTaskStatus = saveTaskStatus;
        window.selectMapStop = selectMapStop;
        window.centerMapOnRoute = centerMapOnRoute;
        window.openReportStatus = openReportStatus;
        window.closeStatusModal = closeStatusModal;
        window.saveReportStatus = saveReportStatus;

        const taskModalEl = document.getElementById('taskStatusModal');
        if (taskModalEl) {
            taskModalEl.addEventListener('click', function(e) {
                if (e.target === this) closeTaskStatusModal();
            });
        }

        /* === Close modal on backdrop click === */
        const repModalEl = document.getElementById('reportStatusModal');
        if (repModalEl) {
            repModalEl.addEventListener('click', function(e) {
                if (e.target === this) closeStatusModal();
            });
        }

        /* === Photo Lightbox Functions === */
        function openPhotoLightbox(src, title) {
            const img = document.getElementById('photoLightboxImg');
            const ttl = document.getElementById('photoLightboxTitle');
            if (img) img.src = src;
            if (ttl) ttl.innerHTML = '<i class="fa-solid fa-camera" style="color:#10b981;"></i> <span>' + (title || 'Report Photo') + '</span>';
            const modal = document.getElementById('photoLightboxModal');
            if (modal) modal.style.display = 'flex';
        }

        function closePhotoLightbox() {
            const modal = document.getElementById('photoLightboxModal');
            if (modal) modal.style.display = 'none';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePhotoLightbox();
                closeStatusModal();
                closeTaskStatusModal();
            }
        });

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        /* === Live Poll for Newly Submitted Reports & Pictures === */
        let latestKnownReportId = <?php echo !empty($illegalReports) ? (int)$illegalReports[0]['id'] : 0; ?>;

        function pollForNewReports() {
            fetch('api/reports.php?action=list&limit=6')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.reports && data.reports.length > 0) {
                        const newest = data.reports[0];
                        if (newest.id > latestKnownReportId) {
                            latestKnownReportId = parseInt(newest.id, 10);
                            allLoadedReports.unshift(newest);

                            const photoUrl = newest.photo_url || 'uploads/reports/default-report.jpg';

                            // 1. Prepend to top illegal reports notification cards
                            const container = document.getElementById('illegalReportsContainer');
                            if (container) {
                                const newCard = document.createElement('div');
                                newCard.className = "p-3.5 bg-red-50/90 hover:bg-red-100 rounded-2xl border-2 border-red-300 flex items-center justify-between transition-all shadow-xs";
                                newCard.setAttribute('data-report-card', newest.id);
                                newCard.innerHTML = `
                                    <div class="flex items-center space-x-3">
                                        <div class="relative group shrink-0 cursor-pointer" onclick="openPhotoLightbox('${photoUrl}', '${escapeHtml(newest.waste_type)} - Photo')">
                                            <img src="${photoUrl}" alt="Report photo" class="w-14 h-14 rounded-xl object-cover shrink-0 border border-red-200 shadow-xs group-hover:scale-105 transition-all" onerror="this.onerror=null; this.src='uploads/reports/default-report.jpg';" />
                                            <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 rounded-xl flex items-center justify-center text-white text-xs transition-opacity">
                                                <i class="fa-solid fa-magnifying-glass-plus"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-1.5">
                                                <h4 class="font-bold text-xs text-slate-800">${escapeHtml(newest.waste_type)}</h4>
                                                <span class="text-[9px] bg-red-600 text-white font-extrabold px-1.5 py-0.2 rounded-full uppercase tracking-wider">NEW</span>
                                            </div>
                                            <p class="text-[10px] text-slate-400 font-semibold mt-0.5">${newest.report_date} &bull; Just now</p>
                                            <p class="text-[11px] font-medium text-slate-600 mt-0.5">${escapeHtml(newest.location)}</p>
                                        </div>
                                    </div>
                                    <span data-report-badge="${newest.id}" class="bg-red-100 text-red-600 text-[10px] font-bold px-2.5 py-1 rounded-full shrink-0">${escapeHtml(newest.status)}</span>
                                `;
                                container.prepend(newCard);
                            }

                            // 2. Prepend to Recent Reports table
                            const tableContainer = document.getElementById('recentReportsListContainer');
                            if (tableContainer) {
                                const newTableRow = document.createElement('div');
                                newTableRow.className = "p-3 rounded-xl border border-slate-200/80 flex items-center justify-between hover:bg-slate-50 transition bg-emerald-50/50";
                                newTableRow.setAttribute('data-report-table-row', newest.id);
                                newTableRow.innerHTML = `
                                    <div class="flex items-center space-x-3">
                                        <div class="relative group shrink-0 cursor-pointer" onclick="openPhotoLightbox('${photoUrl}', 'Report #${newest.id} Photo')">
                                            <img src="${photoUrl}" alt="Report photo" class="w-10 h-10 rounded-xl object-cover shrink-0 border border-slate-200 shadow-2xs group-hover:scale-105 transition" onerror="this.onerror=null; this.src='uploads/reports/default-report.jpg';" />
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-xs text-slate-800">${escapeHtml(newest.waste_type)}</h4>
                                            <p class="text-[10px] text-slate-400 font-semibold">${newest.report_date} &bull; ${escapeHtml(newest.location)}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span data-report-badge="${newest.id}" class="font-bold text-[10px] px-2.5 py-1 rounded-full bg-red-100 text-red-600">${escapeHtml(newest.status)}</span>
                                        <button onclick="openReportStatus(${newest.id}, '${escapeHtml(newest.waste_type)}', '${escapeHtml(newest.location)}', '${escapeHtml(newest.status)}')" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[10px] cursor-pointer transition">
                                            <i class="fa-solid fa-pen-to-square"></i> Update
                                        </button>
                                    </div>
                                `;
                                tableContainer.prepend(newTableRow);
                            }

                            // 3. Update count stat
                            const countEl = document.getElementById('totalReportsCountStat');
                            if (countEl) {
                                countEl.textContent = (parseInt(countEl.textContent, 10) || 0) + 1;
                            }

                            // 4. Update dropdown selector in dispatch tracking
                            const selector = document.getElementById('dispatchReportSelector');
                            if (selector) {
                                const opt = document.createElement('option');
                                opt.value = newest.id;
                                opt.textContent = '#' + newest.id + ' - ' + newest.waste_type;
                                selector.prepend(opt);
                            }

                            // 5. Toast alert
                            showCollectorToast('New report #' + newest.id + ' received with picture!', false);
                        }
                    }
                })
                .catch(() => {});
        }

        setInterval(pollForNewReports, 5000);
    </script>
</body>
</html>
