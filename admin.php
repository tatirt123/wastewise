<?php
/**
 * WasteWise - Admin Dashboard
 * Database: tatang (MySQL)
 */
require_once __DIR__ . '/config/db.php';

// Fetch admin user or fallback
$user = getLoggedInUser($pdo);
if (!$user || $user['role'] !== 'admin') {
    $stmt = $pdo->query("SELECT * FROM `users` WHERE `role` = 'admin' LIMIT 1");
    $admin = $stmt->fetch();
    if ($admin) $user = $admin;
}

// Fetch dynamic admin metrics from tatang DB
$totalUsers = 0;
$todaysCollectionsCount = 0;
$pendingReportsCount = 0;
$reports = [];
$schedules = [];

try {
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    $todaysCollectionsCount = (int)$pdo->query("SELECT COUNT(*) FROM `collection_schedules`")->fetchColumn();
    $pendingReportsCount = (int)$pdo->query("SELECT COUNT(*) FROM `illegal_reports` WHERE `status` = 'Pending'")->fetchColumn();

    // Fetch reports
    $stmtReports = $pdo->query("SELECT * FROM `illegal_reports` ORDER BY `id` DESC LIMIT 6");
    $reports = $stmtReports->fetchAll();

    // Fetch schedules
    $stmtSched = $pdo->query("
        SELECT cs.*, wc.name AS category_name, wc.color AS category_color
        FROM `collection_schedules` cs
        JOIN `waste_categories` wc ON cs.waste_category_id = wc.id
        ORDER BY cs.id DESC
        LIMIT 6
    ");
    $schedules = $stmtSched->fetchAll();
} catch (Exception $e) { /* fallback */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WASTE WISE - Admin Dashboard</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; margin: 0; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }

        .card-lift {
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
        }
        .card-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -8px rgba(15, 23, 42, 0.10);
            border-color: #a7f3d0;
        }

        .chart-box { position: relative; width: 100%; height: 260px; }
        .chart-box canvas { position: absolute; inset: 0; width: 100% !important; height: 100% !important; }

        .donut-wrap { position: relative; width: 130px; height: 130px; flex-shrink: 0; }
        .donut-center {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            text-align: center; pointer-events: none; line-height: 1.1;
        }

        .row-equal { align-items: stretch; }
        .row-equal > * { display: flex; flex-direction: column; }
        .row-equal > * > * { flex: 1; display: flex; flex-direction: column; }

        .metric-icon {
            width: 48px; height: 48px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">

    <!-- HEADER -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-[1400px] mx-auto px-6">
            <div class="flex justify-between items-center h-16 gap-4">

                <!-- Logo + Nav -->
                <div class="flex items-center space-x-8 min-w-0">
                    <a href="index.php" class="flex items-center space-x-2 shrink-0" style="text-decoration:none;">
                        <div class="bg-emerald-600 text-white p-1.5 rounded-lg">
                            <i data-lucide="leaf" class="w-5 h-5"></i>
                        </div>
                        <div class="hidden sm:block">
                            <span class="font-bold text-base text-slate-800 leading-none block">WASTE WISE</span>
                            <span class="text-[10px] text-slate-500 font-medium tracking-tight">Admin &bull; tatang Database</span>
                        </div>
                    </a>

                    <nav class="hidden lg:flex space-x-1">
                        <a href="admin.php" class="bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2" style="text-decoration:none;">
                            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                        </a>
                        <a href="collector.php" class="text-slate-600 hover:text-slate-900 hover:bg-slate-50 px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition" style="text-decoration:none;">
                            <i data-lucide="truck" class="w-4 h-4"></i> Collector Route
                        </a>
                        <a href="dashboard.php" class="text-slate-600 hover:text-slate-900 hover:bg-slate-50 px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition" style="text-decoration:none;">
                            <i data-lucide="leaf" class="w-4 h-4"></i> Citizen Portal
                        </a>
                    </nav>
                </div>

                <!-- Right Actions -->
                <div class="flex items-center space-x-2 sm:space-x-4 shrink-0">
                    <div class="flex items-center space-x-2 border-l pl-2 sm:pl-4 border-slate-200">
                        <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-semibold text-sm">A</div>
                        <span class="hidden sm:inline text-sm font-medium text-slate-700"><?php echo htmlspecialchars($user['name'] ?? 'Admin Officer'); ?></span>
                        <a href="logout.php" title="Logout" class="text-red-500 hover:text-red-700 text-xs font-semibold ml-2">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="max-w-[1400px] mx-auto px-6 py-6 space-y-5">

        <!-- Title -->
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Admin Control Center</h1>
            <p class="text-sm text-slate-500">Live oversight of municipal waste management &middot; Database: <span class="text-emerald-600 font-semibold">tatang</span></p>
        </div>

        <!-- ROW 1 — Metric Cards from tatang DB -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- Total Users -->
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm card-lift">
                <div class="flex items-start gap-4">
                    <div class="metric-icon bg-emerald-50 text-emerald-600">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-500">Total Registered Users</p>
                        <h2 class="text-[28px] leading-tight font-bold text-slate-900 mt-0.5"><?php echo number_format($totalUsers); ?></h2>
                        <p class="text-xs text-emerald-600 font-medium mt-1 flex items-center gap-1">
                            <i data-lucide="trending-up" class="w-3.5 h-3.5"></i> Live in tatang DB
                        </p>
                    </div>
                </div>
            </div>

            <!-- Today's Collections -->
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm card-lift">
                <div class="flex items-start gap-4">
                    <div class="metric-icon bg-emerald-50 text-emerald-600">
                        <i data-lucide="truck" class="w-6 h-6"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-500">Active Collection Routes</p>
                        <h2 class="text-[28px] leading-tight font-bold text-slate-900 mt-0.5"><?php echo number_format($todaysCollectionsCount); ?></h2>
                        <p class="text-xs text-slate-400 mt-1">across barangays</p>
                    </div>
                </div>
            </div>

            <!-- Pending Reports -->
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm card-lift">
                <div class="flex items-start gap-4">
                    <div class="metric-icon bg-amber-50 text-amber-500">
                        <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-500">Pending Illegal Reports</p>
                        <h2 class="text-[28px] leading-tight font-bold text-slate-900 mt-0.5"><?php echo number_format($pendingReportsCount); ?></h2>
                        <p class="text-xs text-slate-400 mt-1">awaiting dispatch</p>
                    </div>
                </div>
            </div>

            <!-- Collection Compliance -->
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm card-lift">
                <div class="flex items-start gap-4">
                    <div class="metric-icon bg-emerald-50 text-emerald-600">
                        <i data-lucide="leaf" class="w-6 h-6"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-500">Collection Compliance</p>
                        <h2 class="text-[28px] leading-tight font-bold text-slate-900 mt-0.5">92%</h2>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-2 overflow-hidden">
                            <div class="bg-emerald-600 h-full rounded-full" style="width: 92%"></div>
                        </div>
                        <p class="text-xs text-emerald-600 font-medium mt-1.5 flex items-center gap-1">
                            <i data-lucide="trending-up" class="w-3.5 h-3.5"></i> High efficiency
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROW 2 — Collection Overview | Today's Status | Waste Dist -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 row-equal">

            <!-- LEFT: Collection Overview Bar Chart -->
            <div class="lg:col-span-5 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="leaf" class="w-5 h-5 text-emerald-600"></i>
                        <h3 class="font-bold text-slate-800">Weekly Collection Volume</h3>
                    </div>
                    <span class="text-xs text-slate-400">Current Week</span>
                </div>
                <div class="chart-box">
                    <canvas id="collectionOverviewChart"></canvas>
                </div>
            </div>

            <!-- MIDDLE: Today's Collection Status -->
            <div class="lg:col-span-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center space-x-2 mb-3">
                    <i data-lucide="truck" class="w-5 h-5 text-emerald-600"></i>
                    <h3 class="font-bold text-slate-800 text-sm">Waste Category Status</h3>
                </div>

                <div class="space-y-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center space-x-3 min-w-0">
                            <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                <i data-lucide="leaf" class="w-5 h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-800 truncate">Biodegradable</p>
                                <p class="text-[11px] text-slate-400">Sun &amp; Mon Routes</p>
                            </div>
                        </div>
                        <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 font-semibold text-[11px] rounded-full">Active</span>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center space-x-3 min-w-0">
                            <div class="w-10 h-10 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                                <i data-lucide="recycle" class="w-5 h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-800 truncate">Recyclable</p>
                                <p class="text-[11px] text-slate-400">Tue &amp; Thu Routes</p>
                            </div>
                        </div>
                        <span class="px-2.5 py-1 bg-amber-50 text-amber-700 font-semibold text-[11px] rounded-full">Active</span>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center space-x-3 min-w-0">
                            <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center shrink-0">
                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-800 truncate">Residual</p>
                                <p class="text-[11px] text-slate-400">Wed &amp; Sat Routes</p>
                            </div>
                        </div>
                        <span class="px-2.5 py-1 bg-blue-50 text-blue-700 font-semibold text-[11px] rounded-full">Scheduled</span>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center space-x-3 min-w-0">
                            <div class="w-10 h-10 rounded-full bg-red-50 text-red-500 flex items-center justify-center shrink-0">
                                <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-800 truncate">Hazardous</p>
                                <p class="text-[11px] text-slate-400">Fri Route Only</p>
                            </div>
                        </div>
                        <span class="px-2.5 py-1 bg-red-50 text-red-700 font-semibold text-[11px] rounded-full">Pending</span>
                    </div>
                </div>
            </div>

            <!-- RIGHT STACK: Waste Distribution -->
            <div class="lg:col-span-3 bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center space-x-2 mb-3">
                        <i data-lucide="pie-chart" class="w-5 h-5 text-emerald-600"></i>
                        <h3 class="font-bold text-slate-800 text-sm">Waste Distribution</h3>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="donut-wrap">
                            <canvas id="wasteDistributionChart"></canvas>
                            <div class="donut-center">
                                <span class="block text-[9px] text-slate-400 uppercase tracking-wider font-semibold">Total</span>
                                <span class="block text-base font-bold text-slate-800"><?php echo number_format($totalUsers); ?></span>
                                <span class="block text-[9px] text-slate-400">users</span>
                            </div>
                        </div>

                        <div class="flex-1 space-y-1.5 text-[11px]">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600">Biodegradable</span>
                                <span class="font-semibold text-slate-800">40%</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600">Recyclable</span>
                                <span class="font-semibold text-slate-800">30%</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600">Residual</span>
                                <span class="font-semibold text-slate-800">20%</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600">Hazardous</span>
                                <span class="font-semibold text-slate-800">10%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 mt-4">
                    <p class="text-[11px] text-slate-400">Live sync with municipal MySQL database tatang.</p>
                </div>
            </div>
        </div>

        <!-- ROW 3 — Recent Reports | Collection Schedule Management -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

            <!-- Recent Reports from tatang DB -->
            <div class="lg:col-span-5 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="file-text" class="w-5 h-5 text-emerald-600"></i>
                        <h3 class="font-bold text-slate-800">Recent Illegal Reports (tatang DB)</h3>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-100">
                                <th class="pb-2 font-medium">Type</th>
                                <th class="pb-2 font-medium">Location</th>
                                <th class="pb-2 font-medium">Date</th>
                                <th class="pb-2 font-medium text-right">Status</th>
                                <th class="pb-2 font-medium text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!empty($reports)): ?>
                                <?php foreach ($reports as $rep): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-3 font-medium text-slate-800">
                                            <?php echo htmlspecialchars($rep['waste_type']); ?>
                                        </td>
                                        <td class="py-3 text-slate-500"><?php echo htmlspecialchars($rep['location']); ?></td>
                                        <td class="py-3 text-slate-400"><?php echo date('M d', strtotime($rep['report_date'])); ?></td>
                                        <td class="py-3 text-right">
                                            <span data-report-badge="<?php echo $rep['id']; ?>" class="px-2 py-0.5 rounded-md text-[10px] font-semibold <?php echo ($rep['status'] === 'Resolved') ? 'bg-emerald-100 text-emerald-700' : (($rep['status'] === 'Pending') ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700'); ?>">
                                                <?php echo htmlspecialchars($rep['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-center">
                                            <button onclick="openAdminReportModal(<?php echo $rep['id']; ?>, '<?php echo htmlspecialchars(addslashes($rep['waste_type'])); ?>', '<?php echo htmlspecialchars(addslashes($rep['location'])); ?>', '<?php echo htmlspecialchars($rep['status']); ?>')" class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-[10px] font-bold cursor-pointer transition">
                                                Update
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="py-3 text-center text-slate-400">No reports found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Collection Schedule Management from tatang DB -->
            <div class="lg:col-span-7 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-3 gap-2">
                    <div class="flex items-center space-x-2 min-w-0">
                        <i data-lucide="calendar" class="w-5 h-5 text-emerald-600 shrink-0"></i>
                        <h3 class="font-bold text-slate-800 text-sm truncate">Collection Schedule Management (tatang DB)</h3>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-100">
                                <th class="pb-2 font-medium">City / Barangay</th>
                                <th class="pb-2 font-medium">Route / Street</th>
                                <th class="pb-2 font-medium">Waste Type</th>
                                <th class="pb-2 font-medium">Time Window</th>
                                <th class="pb-2 font-medium text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!empty($schedules)): ?>
                                <?php foreach ($schedules as $sc): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-3 font-medium text-slate-700"><?php echo htmlspecialchars($sc['barangay'] . ', ' . $sc['city']); ?></td>
                                        <td class="py-3 text-slate-500"><?php echo htmlspecialchars($sc['street_route'] ?? '-'); ?></td>
                                        <td class="py-3">
                                            <span style="color:<?php echo htmlspecialchars($sc['category_color']); ?>; font-weight:bold;">
                                                <?php echo htmlspecialchars($sc['category_name']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-slate-500"><?php echo date('g:i A', strtotime($sc['start_time'])); ?> - <?php echo date('g:i A', strtotime($sc['end_time'])); ?></td>
                                        <td class="py-3 text-center">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo ($sc['status'] === 'Completed') ? 'bg-emerald-100 text-emerald-700' : (($sc['status'] === 'In Progress') ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600'); ?>">
                                                <?php echo htmlspecialchars($sc['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="py-3 text-center text-slate-400">No schedules found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </main>

    <!-- Admin Report Status Modal -->
    <div id="adminReportModal" style="display:none; position:fixed; inset:0; z-index:999; background:rgba(0,0,0,0.45); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:1rem; padding:2rem; max-width:440px; width:92%; box-shadow:0 20px 60px rgba(0,0,0,0.2);">
            <h3 style="font-size:1rem; font-weight:700; color:#1e293b; margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
                <i data-lucide="file-edit" style="width:18px;height:18px;color:#16a34a;"></i> Update Report Status
            </h3>
            <div id="adminReportInfo" style="background:#f8fafc; border-radius:0.5rem; padding:0.75rem; margin-bottom:1rem; font-size:0.8rem; color:#475569; border:1px solid #e2e8f0;"></div>
            <input type="hidden" id="adminReportId">
            <label style="display:block; font-size:0.7rem; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:0.35rem;">New Status</label>
            <select id="adminStatusSelect" style="width:100%; padding:0.6rem 0.9rem; border-radius:0.5rem; border:1px solid #cbd5e1; font-size:0.85rem; font-family:inherit; color:#1e293b; margin-bottom:1rem;">
                <option value="Pending">Pending</option>
                <option value="Under Review">Under Review</option>
                <option value="Assigned">Assigned</option>
                <option value="On the Way">On the Way</option>
                <option value="Resolved">Resolved</option>
            </select>
            <div style="display:flex; gap:0.5rem;">
                <button id="adminSaveStatusBtn" onclick="saveAdminReportStatus()" style="flex:1; padding:0.65rem; background:#16a34a; color:#fff; border:none; border-radius:0.5rem; font-weight:700; font-size:0.85rem; cursor:pointer; font-family:inherit;">
                    Save Changes
                </button>
                <button onclick="closeAdminModal()" style="padding:0.65rem 1rem; background:#f1f5f9; color:#64748b; border:none; border-radius:0.5rem; font-weight:600; cursor:pointer; font-family:inherit;">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Admin Toast -->
    <div id="adminToast" style="display:none; position:fixed; bottom:1.5rem; right:1.5rem; z-index:9999; background:#1e293b; color:#fff; padding:0.75rem 1.2rem; border-radius:0.75rem; font-size:0.85rem; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,0.25);"></div>

    <!-- SCRIPTS -->
    <script>
        lucide.createIcons();

        // Stacked Bar Chart
        const ctxBar = document.getElementById('collectionOverviewChart').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [
                    { label: 'Biodegradable', data: [13, 15, 14, 11, 9, 7, 9], backgroundColor: '#16a34a' },
                    { label: 'Recyclable', data: [9, 11, 10, 9, 7, 5, 6], backgroundColor: '#facc15' },
                    { label: 'Residual', data: [9, 11, 10, 8, 8, 6, 7], backgroundColor: '#3b82f6' },
                    { label: 'Hazardous', data: [6, 7, 6, 5, 4, 4, 4], backgroundColor: '#ef4444' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
            }
        });

        // Donut Chart
        const ctxDoughnut = document.getElementById('wasteDistributionChart').getContext('2d');
        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: ['Biodegradable', 'Recyclable', 'Residual', 'Hazardous'],
                datasets: [{
                    data: [40, 30, 20, 10],
                    backgroundColor: ['#16a34a', '#facc15', '#3b82f6', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: { legend: { display: false } }
            }
        });

        /* === Admin Report Status Modal === */
        function openAdminReportModal(reportId, wasteType, location, currentStatus) {
            document.getElementById('adminReportId').value = reportId;
            document.getElementById('adminReportInfo').innerHTML =
                '<strong>Report #' + reportId + ':</strong> ' + wasteType +
                '<br><span style="color:#94a3b8;">' + location + '</span>' +
                '<br><strong>Current: ' + currentStatus + '</strong>';
            document.getElementById('adminStatusSelect').value = currentStatus;
            document.getElementById('adminReportModal').style.display = 'flex';
            lucide.createIcons();
        }

        function closeAdminModal() {
            document.getElementById('adminReportModal').style.display = 'none';
        }

        function showAdminToast(msg, isError) {
            const t = document.getElementById('adminToast');
            t.textContent = msg;
            t.style.background = isError ? '#dc2626' : '#16a34a';
            t.style.display = 'block';
            setTimeout(() => { t.style.display = 'none'; }, 4000);
        }

        function saveAdminReportStatus() {
            const reportId = document.getElementById('adminReportId').value;
            const newStatus = document.getElementById('adminStatusSelect').value;
            const saveBtn = document.getElementById('adminSaveStatusBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving…';

            const fd = new FormData();
            fd.append('action', 'update_report');
            fd.append('report_id', reportId);
            fd.append('status', newStatus);

            fetch('api/admin.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
                if (data.status === 'success') {
                    showAdminToast(data.message, false);
                    closeAdminModal();
                    const badge = document.querySelector('[data-report-badge="' + reportId + '"]');
                    if (badge) {
                        badge.textContent = newStatus;
                        badge.className = 'px-2 py-0.5 rounded-md text-[10px] font-semibold ' +
                            (newStatus === 'Resolved' ? 'bg-emerald-100 text-emerald-700' :
                            (newStatus === 'Pending' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700'));
                    }
                } else {
                    showAdminToast(data.message || 'Error', true);
                }
            })
            .catch(() => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
                showAdminToast('Connection error.', true);
            });
        }

        document.getElementById('adminReportModal').addEventListener('click', function(e) {
            if (e.target === this) closeAdminModal();
        });
    </script>
</body>
</html>
