<?php
/**
 * WasteWise - Collector Dashboard
 * Database: tatang (MySQL)
 */
require_once __DIR__ . '/config/db.php';

// Get current collector user or fallback to Juan
$user = getLoggedInUser($pdo);
if (!$user || $user['role'] !== 'collector') {
    $stmt = $pdo->query("SELECT * FROM `users` WHERE `role` = 'collector' LIMIT 1");
    $collector = $stmt->fetch();
    if ($collector) $user = $collector;
}

// Fetch metrics from tatang DB
$pendingReportsCount = 0;
$assignedRoutesCount = 5;
$illegalReports = [];
$todayTasks = [];

try {
    $pendingReportsCount = (int)$pdo->query("SELECT COUNT(*) FROM `illegal_reports` WHERE `status` = 'Pending'")->fetchColumn();
    $assignedRoutesCount = (int)$pdo->query("SELECT COUNT(*) FROM `collection_schedules` WHERE `city` = 'Lapu-Lapu City' AND `barangay` = 'Subabasbas'")->fetchColumn();

    // Fetch reports from DB
    $stmtRep = $pdo->query("SELECT * FROM `illegal_reports` ORDER BY `id` DESC LIMIT 5");
    $illegalReports = $stmtRep->fetchAll();

    // Fetch tasks from DB
    $stmtTasks = $pdo->query("
        SELECT cs.*, wc.name AS category_name, wc.slug AS category_slug, wc.color AS category_color
        FROM `collection_schedules` cs
        JOIN `waste_categories` wc ON cs.waste_category_id = wc.id
        WHERE cs.city = 'Lapu-Lapu City' AND cs.barangay = 'Subabasbas'
        ORDER BY cs.day_of_week ASC, cs.start_time ASC
        LIMIT 6
    ");
    $todayTasks = $stmtTasks->fetchAll();
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

        .map-canvas {
            background-color: #e5f0eb;
            background-image: 
                radial-gradient(#10b981 0.8px, transparent 0.8px),
                linear-gradient(to right, #cfdfd6 1px, transparent 1px),
                linear-gradient(to bottom, #cfdfd6 1px, transparent 1px);
            background-size: 16px 16px, 32px 32px, 32px 32px;
        }

        .truck-transition {
            transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col lg:flex-row antialiased select-none">

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

                <a href="admin.php" class="nav-item w-full text-left flex items-center space-x-3 px-4 py-3 rounded-xl text-slate-600 hover:bg-slate-100 font-medium text-sm transition-all cursor-pointer" style="text-decoration:none;">
                    <i class="fa-solid fa-shield-halved w-5 text-center text-slate-400"></i>
                    <span>Admin Portal</span>
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
                <p class="text-xs font-bold text-emerald-950 leading-snug">Connected to tatang DB</p>
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
                <h1 class="text-2xl lg:text-3xl font-bold text-slate-800 tracking-tight">Good day, <?php echo htmlspecialchars($user['name'] ?? 'Juan'); ?>!</h1>
                <p class="text-slate-500 text-xs sm:text-sm font-medium mt-0.5">Assigned route tasks and illegal dumping alerts from database <span class="text-brand-600 font-bold">tatang</span>.</p>
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
                    <span><?php echo htmlspecialchars($user['name'] ?? 'Juan Dela Cruz'); ?></span>
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
                        <p class="text-xs font-medium text-slate-500 mt-1">Active Reports <span class="text-slate-400">(tatang DB)</span></p>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center space-x-3.5 hover:border-brand-300 transition-all cursor-pointer">
                    <div class="w-11 h-11 rounded-xl bg-emerald-100/80 flex items-center justify-center text-brand-600 text-lg shrink-0">
                        <i class="fa-solid fa-trash-can"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800 leading-none">12.5 <span class="text-xs font-semibold">tons</span></p>
                        <p class="text-xs font-medium text-slate-500 mt-1">Total Collected <span class="text-slate-400">(This week)</span></p>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center space-x-3.5 hover:border-brand-300 transition-all cursor-pointer">
                    <div class="w-11 h-11 rounded-xl bg-emerald-100/80 flex items-center justify-center text-brand-600 text-lg shrink-0">
                        <i class="fa-solid fa-leaf"></i>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-slate-800 leading-none">98%</p>
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
                            <h2 class="font-bold text-slate-800 text-base">Live Collection Route (Lapu-Lapu City)</h2>
                        </div>
                        <span class="text-xs font-bold text-brand-600">5 Active Stops</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                        <div class="md:col-span-5 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-sm text-slate-800">Route 1 - North District</span>
                                <span class="bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2.5 py-0.5 rounded-full">In Progress</span>
                            </div>
                            <p class="text-xs text-slate-400 font-semibold">8:00 AM – 12:00 PM</p>

                            <!-- Route Steps -->
                            <div class="space-y-2 mt-2 text-xs">
                                <button id="route-step-1" onclick="selectMapStop(1)" class="route-step-btn w-full text-left flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 cursor-pointer hover:bg-emerald-50/80 transition-all">
                                    <div class="flex items-center space-x-2.5">
                                        <span class="step-badge w-5 h-5 rounded-full bg-emerald-600 text-white font-bold text-[10px] flex items-center justify-center">1</span>
                                        <span class="font-semibold text-slate-700">Rizal St. <span class="text-slate-400 font-normal">(Biodegradable)</span></span>
                                    </div>
                                    <i class="fa-solid fa-check text-emerald-600 text-xs"></i>
                                </button>

                                <button id="route-step-2" onclick="selectMapStop(2)" class="route-step-btn w-full text-left flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 cursor-pointer hover:bg-emerald-50/80 transition-all">
                                    <div class="flex items-center space-x-2.5">
                                        <span class="step-badge w-5 h-5 rounded-full bg-emerald-600 text-white font-bold text-[10px] flex items-center justify-center">2</span>
                                        <span class="font-semibold text-slate-700">Bonifacio Ave. <span class="text-slate-400 font-normal">(Recyclable)</span></span>
                                    </div>
                                    <i class="fa-solid fa-check text-emerald-600 text-xs"></i>
                                </button>

                                <button id="route-step-3" onclick="selectMapStop(3)" class="route-step-btn w-full text-left flex items-center justify-between p-2.5 rounded-xl bg-emerald-50 border border-emerald-300 cursor-pointer transition-all">
                                    <div class="flex items-center space-x-2.5">
                                        <span class="step-badge w-5 h-5 rounded-full bg-emerald-600 text-white font-bold text-[10px] flex items-center justify-center">3</span>
                                        <span class="font-bold text-emerald-900">Mabini St. <span class="text-slate-500 font-normal">(Residual)</span></span>
                                    </div>
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                </button>

                                <button id="route-step-4" onclick="selectMapStop(4)" class="route-step-btn w-full text-left flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 cursor-pointer hover:bg-emerald-50/80 transition-all">
                                    <div class="flex items-center space-x-2.5">
                                        <span class="step-badge w-5 h-5 rounded-full bg-slate-300 text-slate-600 font-bold text-[10px] flex items-center justify-center">4</span>
                                        <span class="font-medium text-slate-600">Aguinaldo St. <span class="text-slate-400 font-normal">(Recyclable)</span></span>
                                    </div>
                                    <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                                </button>

                                <button id="route-step-5" onclick="selectMapStop(5)" class="route-step-btn w-full text-left flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 cursor-pointer hover:bg-emerald-50/80 transition-all">
                                    <div class="flex items-center space-x-2.5">
                                        <span class="step-badge w-5 h-5 rounded-full bg-slate-300 text-slate-600 font-bold text-[10px] flex items-center justify-center">5</span>
                                        <span class="font-medium text-slate-600">OSMEÑA Rd. <span class="text-slate-400 font-normal">(Hazardous)</span></span>
                                    </div>
                                    <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                                </button>
                            </div>
                        </div>

                        <!-- MAP DISPLAY AREA -->
                        <div class="md:col-span-7 min-h-[300px] h-full rounded-2xl map-canvas relative overflow-hidden border border-emerald-300 shadow-inner flex items-center justify-center p-4">
                            <!-- Route Line Path SVG -->
                            <svg class="absolute inset-0 w-full h-full pointer-events-none" viewBox="0 0 300 240" fill="none">
                                <path d="M40 180 L80 140 L160 110 L210 50 L250 120 L180 200 L120 180 Z" stroke="#16a34a" stroke-width="4" stroke-dasharray="6 4" />
                            </svg>

                            <!-- Route Stop 1 -->
                            <button onclick="selectMapStop(1)" class="absolute top-[170px] left-[35px] cursor-pointer hover:scale-125 transition-transform z-10">
                                <span class="w-7 h-7 rounded-full bg-emerald-600 text-white font-bold text-xs flex items-center justify-center shadow-lg ring-2 ring-white">1</span>
                            </button>

                            <!-- Route Stop 2 -->
                            <button onclick="selectMapStop(2)" class="absolute top-[130px] left-[75px] cursor-pointer hover:scale-125 transition-transform z-10">
                                <span class="w-7 h-7 rounded-full bg-emerald-600 text-white font-bold text-xs flex items-center justify-center shadow-lg ring-2 ring-white">2</span>
                            </button>

                            <!-- Active Moving Garbage Truck -->
                            <div id="truck-map-pin" class="absolute top-[100px] left-[150px] flex flex-col items-center z-20 truck-transition">
                                <div class="w-9 h-9 rounded-full bg-brand-600 text-white flex items-center justify-center ring-4 ring-emerald-200 shadow-xl">
                                    <i class="fa-solid fa-truck text-sm"></i>
                                </div>
                            </div>

                            <!-- Route Stop 4 -->
                            <button onclick="selectMapStop(4)" class="absolute top-[40px] left-[200px] cursor-pointer hover:scale-125 transition-transform z-10">
                                <span class="w-7 h-7 rounded-full bg-emerald-600 text-white font-bold text-xs flex items-center justify-center shadow-lg ring-2 ring-white">4</span>
                            </button>

                            <!-- Route Stop 5 -->
                            <button onclick="selectMapStop(5)" class="absolute top-[190px] left-[170px] cursor-pointer hover:scale-125 transition-transform z-10">
                                <span class="w-7 h-7 rounded-full bg-emerald-600 text-white font-bold text-xs flex items-center justify-center shadow-lg ring-2 ring-white">5</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Illegal Report Notifications (From tatang DB) -->
            <div class="lg:col-span-5 space-y-4" id="reports">
                <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-triangle-exclamation text-red-500 text-base"></i>
                            <h2 class="font-bold text-slate-800 text-base">Illegal Report Notifications</h2>
                        </div>
                        <span class="text-xs font-bold text-slate-400">Database tatang</span>
                    </div>

                    <?php if (!empty($illegalReports)): ?>
                        <?php foreach (array_slice($illegalReports, 0, 3) as $rep): ?>
                            <div class="p-3.5 bg-red-50/60 hover:bg-red-100/70 rounded-2xl border border-red-200/90 flex items-center justify-between transition-all">
                                <div class="flex items-center space-x-3">
                                    <?php if (!empty($rep['photo_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($rep['photo_url']); ?>" alt="Report photo" class="w-14 h-14 rounded-xl object-cover shrink-0 border border-red-200 shadow-xs" />
                                    <?php else: ?>
                                        <div class="w-14 h-14 rounded-xl bg-red-100 text-red-600 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-trash-can"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <h4 class="font-bold text-xs text-slate-800"><?php echo htmlspecialchars($rep['waste_type']); ?></h4>
                                        <p class="text-[10px] text-slate-400 font-semibold mt-0.5"><?php echo date('M d, Y • g:i A', strtotime($rep['report_date'] . ' ' . $rep['report_time'])); ?></p>
                                        <p class="text-[11px] font-medium text-slate-600 mt-0.5"><?php echo htmlspecialchars($rep['location']); ?></p>
                                    </div>
                                </div>
                                <span class="bg-red-100 text-red-600 text-[10px] font-bold px-2.5 py-1 rounded-full shrink-0"><?php echo htmlspecialchars($rep['status']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-xs text-slate-400">No illegal dumping reports found.</p>
                    <?php endif; ?>

                    <!-- Tracking Timeline -->
                    <div class="pt-2 border-t border-slate-100">
                        <h4 class="text-xs font-bold text-slate-700 mb-3">Live Dispatch Tracking</h4>
                        <div class="flex items-center justify-between relative px-2">
                            <div class="absolute left-6 right-6 top-4 h-0.5 bg-emerald-200 -z-0"></div>

                            <div class="flex flex-col items-center z-10">
                                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs font-bold">
                                    <i class="fa-solid fa-file-invoice"></i>
                                </div>
                                <span class="text-[9px] font-bold text-slate-700 mt-1">Submitted</span>
                            </div>

                            <div class="flex flex-col items-center z-10">
                                <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold shadow-xs">
                                    <i class="fa-solid fa-user-check"></i>
                                </div>
                                <span class="text-[9px] font-bold text-emerald-700 mt-1">Assigned</span>
                            </div>

                            <div class="flex flex-col items-center z-10">
                                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs font-bold">
                                    <i class="fa-solid fa-truck-moving"></i>
                                </div>
                                <span class="text-[9px] font-bold text-slate-700 mt-1">On the Way</span>
                            </div>

                            <div class="flex flex-col items-center z-10">
                                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs font-bold">
                                    <i class="fa-solid fa-circle-check"></i>
                                </div>
                                <span class="text-[9px] font-bold text-slate-700 mt-1">Resolved</span>
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
                    <span class="text-xs text-slate-400">Lapu-Lapu City</span>
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
                                <?php foreach ($todayTasks as $task): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-3 pl-2 font-semibold"><?php echo date('g:i A', strtotime($task['start_time'])); ?> - <?php echo date('g:i A', strtotime($task['end_time'])); ?></td>
                                        <td class="py-3"><?php echo htmlspecialchars($task['street_route'] ?? $task['barangay']); ?></td>
                                        <td class="py-3">
                                            <span style="color: <?php echo htmlspecialchars($task['category_color']); ?>; font-weight:bold;">
                                                <?php echo htmlspecialchars($task['category_name']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <span class="px-2.5 py-1 rounded-full font-bold text-[10px] <?php echo ($task['status'] === 'Completed') ? 'bg-emerald-100 text-emerald-700' : (($task['status'] === 'In Progress') ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600'); ?>">
                                                <?php echo htmlspecialchars($task['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-center">
                                            <button onclick="openReportStatus(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars(addslashes($task['category_name'])); ?> Collection', '<?php echo htmlspecialchars(addslashes($task['street_route'] ?? $task['barangay'])); ?>', '<?php echo htmlspecialchars($task['status']); ?>')" class="px-3 py-1 bg-slate-100 hover:bg-emerald-600 hover:text-white text-slate-600 rounded-lg font-bold text-[11px] cursor-pointer transition">
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

            <!-- Recent Reports Table from illegal_reports in tatang DB -->
            <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <i class="fa-regular fa-file-lines text-brand-600 text-base"></i>
                        <h2 class="font-bold text-slate-800 text-base">Recent Reports in tatang DB</h2>
                    </div>
                </div>

                <div class="space-y-3">
                    <?php if (!empty($illegalReports)): ?>
                        <?php foreach (array_slice($illegalReports, 0, 4) as $rep): ?>
                            <div class="p-3 rounded-xl border border-slate-200/80 flex items-center justify-between hover:bg-slate-50 transition">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
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

    <!-- Status Update Modal -->
    <div id="reportStatusModal" style="display:none; position:fixed; inset:0; z-index:999; background:rgba(0,0,0,0.45); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:1.2rem; padding:2rem; max-width:480px; width:92%; box-shadow:0 20px 60px rgba(0,0,0,0.2);">
            <h3 style="font-size:1.1rem; font-weight:700; color:#1e293b; margin-bottom:1rem;">
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
                <button id="saveReportStatusBtn" onclick="saveReportStatus()" style="flex:1; padding:0.75rem; background:#16a34a; color:#fff; border:none; border-radius:0.75rem; font-weight:700; font-size:0.9rem; cursor:pointer; font-family:inherit;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Status
                </button>
                <button onclick="closeStatusModal()" style="padding:0.75rem 1.2rem; background:#f1f5f9; color:#64748b; border:none; border-radius:0.75rem; font-weight:600; font-size:0.9rem; cursor:pointer; font-family:inherit;">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="collectorToast" style="display:none; position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999; background:#1e293b; color:#fff; padding:0.85rem 1.4rem; border-radius:0.85rem; font-size:0.88rem; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,0.25); transition:all 0.3s;"></div>

    <!-- Script for interactive map stop selection + API wiring -->
    <script>
        const stopPositions = {
            1: { top: '170px', left: '35px' },
            2: { top: '130px', left: '75px' },
            3: { top: '100px', left: '150px' },
            4: { top: '40px', left: '200px' },
            5: { top: '190px', left: '170px' }
        };

        function selectMapStop(stopNum) {
            const truckPin = document.getElementById('truck-map-pin');
            if (truckPin && stopPositions[stopNum]) {
                truckPin.style.top = stopPositions[stopNum].top;
                truckPin.style.left = stopPositions[stopNum].left;
            }

            const steps = document.querySelectorAll('.route-step-btn');
            steps.forEach((btn, idx) => {
                const stepIndex = idx + 1;
                if (stepIndex === stopNum) {
                    btn.className = "route-step-btn w-full text-left flex items-center justify-between p-2.5 rounded-xl bg-emerald-50 border border-emerald-300 cursor-pointer transition-all shadow-2xs";
                } else {
                    btn.className = "route-step-btn w-full text-left flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 cursor-pointer hover:bg-emerald-50/80 transition-all";
                }
            });
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

        /* === Close modal on backdrop click === */
        document.getElementById('reportStatusModal').addEventListener('click', function(e) {
            if (e.target === this) closeStatusModal();
        });
    </script>
</body>
</html>
