<?php
/**
 * WasteWise - Smart Waste Segregation Assistant
 * Main Landing & Authentication Page
 */
require_once __DIR__ . '/config/db.php';

// Fetch impact stats from database tatang
$stats = [];
try {
    $stmtStats = $pdo->query("SELECT stat_key, stat_value FROM `impact_stats`");
    while ($row = $stmtStats->fetch()) {
        $stats[$row['stat_key']] = $row['stat_value'];
    }
} catch (Exception $e) {
    // Fallback defaults
    $stats = [
        'trees_saved' => '1,245',
        'kg_recycled' => '3,678',
        'active_users' => '892',
        'co2_saved' => '12.5'
    ];
}

$currentUser = getLoggedInUser($pdo);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WasteWise - Smart Waste Segregation Assistant</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        emerald: {
                            50:'#f0fdf4',100:'#dcfce7',200:'#bbf7d0',500:'#22c55e',
                            600:'#16a34a',700:'#15803d',800:'#166534',900:'#14532d',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        html { background: #0a1512; }

        /* ===== SHARED DARK FOREST BACKGROUND ===== */
        .site-bg {
            position: fixed;
            inset: 0;
            z-index: -1;
            overflow: hidden;
            background:
                radial-gradient(85% 70% at 85% 10%, rgba(126, 224, 165, .16), transparent 60%),
                radial-gradient(80% 80% at 0% 100%, rgba(0, 0, 0, .55), transparent 65%),
                url('https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=2000&q=80')
                    center / cover no-repeat;
        }
        .site-bg::after {
            content: ""; position: absolute; inset: 0; opacity: .35; mix-blend-mode: overlay;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3CfeColorMatrix values='0 0 0 0 1 0 0 0 0 1 0 0 0 0 1 0 0 0 0.18 0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }
        .site-bg::before {
            content: ""; position: absolute; width: 900px; height: 900px;
            right: -240px; top: -240px; border-radius: 50%;
            background: radial-gradient(circle, rgba(126, 224, 165, .10), transparent 65%);
            filter: blur(20px);
        }

        /* ===== GLASS HELPERS ===== */
        .glass {
            background: rgba(18, 28, 24, .42);
            backdrop-filter: blur(22px) saturate(150%);
            -webkit-backdrop-filter: blur(22px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, .12);
        }
        .glass-soft {
            background: rgba(18, 28, 24, .32);
            backdrop-filter: blur(16px) saturate(140%);
            -webkit-backdrop-filter: blur(16px) saturate(140%);
            border: 1px solid rgba(255, 255, 255, .10);
        }
        .custom-shadow { box-shadow: 0 10px 30px -5px rgba(0,0,0,.45); }
        .dashboard-shadow { box-shadow: 0 25px 60px -20px rgba(0,0,0,.65), 0 8px 24px rgba(0,0,0,.25); }
        .hover-lift { transition: transform .25s cubic-bezier(.4,0,.2,1), box-shadow .25s, border-color .25s; }
        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 28px -6px rgba(0,0,0,.5);
            border-color: rgba(157,233,183,.35);
        }
        .text-shadow-soft { text-shadow: 0 2px 12px rgba(0,0,0,.5); }
        .text-shadow-sm { text-shadow: 0 1px 6px rgba(0,0,0,.4); }

        /* ===== AUTH MODAL ===== */
        .auth-overlay {
            position: fixed; inset: 0;
            background: rgba(5, 12, 9, .72);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex; justify-content: center; align-items: center;
            padding: 1.5rem;
            z-index: 2000;
            opacity: 0; visibility: hidden;
            transition: opacity .3s ease, visibility .3s ease;
        }
        .auth-overlay.open { opacity: 1; visibility: visible; }

        .auth-card {
            width: 100%; max-width: 440px;
            background: rgba(18, 28, 24, .75);
            backdrop-filter: blur(26px) saturate(160%);
            -webkit-backdrop-filter: blur(26px) saturate(160%);
            border-radius: 1.6rem;
            padding: 2.2rem 2rem 2rem;
            border: 1px solid rgba(255,255,255,.14);
            box-shadow:
                0 1px 0 rgba(255,255,255,.08) inset,
                0 25px 60px -20px rgba(0,0,0,.75),
                0 8px 24px rgba(0,0,0,.3);
            transform: translateY(20px) scale(.96);
            transition: transform .35s cubic-bezier(.34,1.56,.64,1);
            position: relative;
        }
        .auth-overlay.open .auth-card { transform: translateY(0) scale(1); }

        @keyframes shakeCard {
            0%,100% { transform: translateX(0); }
            20% { transform: translateX(-8px); }
            40% { transform: translateX(8px); }
            60% { transform: translateX(-5px); }
            80% { transform: translateX(5px); }
        }
        .auth-card.shake {
            animation: shakeCard .45s cubic-bezier(.36,.07,.19,.97);
            border-color: rgba(241,196,15,.55);
        }

        .auth-close {
            position: absolute; top: 1rem; right: 1rem;
            width: 34px; height: 34px; border-radius: 50%;
            border: none; background: rgba(255,255,255,.08);
            color: #cbe9d6; font-size: .9rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: .25s;
        }
        .auth-close:hover { background: rgba(255,255,255,.18); transform: rotate(90deg); }

        .auth-brand {
            display: flex; align-items: center; justify-content: center;
            gap: .55rem; margin-bottom: 1.4rem; color: #fff;
        }
        .auth-brand i { font-size: 1.35rem; color: #9de9b7; }
        .auth-brand span { font-size: 1.3rem; font-weight: 600; letter-spacing: -.2px; }

        .tab-group {
            display: flex; background: rgba(0,0,0,.3);
            border-radius: 60px; padding: 4px; margin-bottom: 1.4rem;
            border: 1px solid rgba(255,255,255,.07);
        }
        .tab-btn {
            flex: 1; border: none; background: transparent;
            color: rgba(255,255,255,.55); font-size: .88rem; font-weight: 500;
            padding: .6rem .3rem; border-radius: 60px; cursor: pointer;
            transition: all .2s; display: flex; align-items: center;
            justify-content: center; gap: 7px; font-family: inherit;
        }
        .tab-btn i { font-size: .82rem; opacity: .7; }
        .tab-btn.active {
            background: rgba(255,255,255,.12); color: #fff;
            box-shadow: 0 1px 0 rgba(255,255,255,.06) inset;
        }
        .tab-btn.active i { opacity: 1; color: #9de9b7; }
        .tab-btn:hover:not(.active) { color: rgba(255,255,255,.85); }

        .form-panel { display: block; }
        .form-panel.hidden { display: none; }

        .auth-input-group { margin-bottom: 1.1rem; }
        .auth-input-group label {
            display: block; color: rgba(255,255,255,.65);
            font-size: .7rem; font-weight: 600; letter-spacing: .4px;
            margin-bottom: .45rem; text-transform: uppercase;
        }
        .auth-input-wrapper {
            display: flex; align-items: center;
            background: rgba(0,0,0,.35); border-radius: 12px;
            border: 1px solid rgba(255,255,255,.12);
            transition: border-color .2s, background .2s, box-shadow .2s;
        }
        .auth-input-wrapper:focus-within {
            border-color: rgba(157,233,183,.6);
            background: rgba(0,0,0,.45);
            box-shadow: 0 0 0 3px rgba(157,233,183,.15);
        }
        .auth-input-wrapper i {
            color: rgba(255,255,255,.4); padding-left: 1rem;
            font-size: .88rem; width: 2.2rem; transition: color .2s;
        }
        .auth-input-wrapper:focus-within i { color: #9de9b7; }
        .auth-input-wrapper input {
            width: 100%; padding: .78rem .9rem .78rem 0;
            background: transparent; border: none; outline: none;
            color: #fff; font-size: .92rem; font-family: inherit;
        }
        .auth-input-wrapper input::placeholder { color: rgba(255,255,255,.35); }
        .password-toggle {
            background: transparent; border: none;
            color: rgba(255,255,255,.4); padding-right: 1rem;
            cursor: pointer; font-size: .85rem; transition: color .2s;
        }
        .password-toggle:hover { color: rgba(255,255,255,.85); }

        .auth-options {
            display: flex; justify-content: space-between; align-items: center;
            margin: 0.8rem 0 1.2rem;
        }
        .auth-options label {
            color: rgba(255,255,255,.65); font-size: .82rem;
            display: flex; align-items: center; gap: .5rem; cursor: pointer;
        }
        .auth-options label input[type="checkbox"] {
            accent-color: #4cc078; width: .9rem; height: .9rem;
        }
        .auth-options a {
            color: rgba(157,233,183,.9); text-decoration: none;
            font-size: .82rem; transition: color .2s;
        }
        .auth-options a:hover { color: #9de9b7; }

        .btn-primary {
            width: 100%; padding: .85rem; background: #2d7a4a;
            border: none; border-radius: 12px; font-size: .95rem;
            font-weight: 600; color: #fff; cursor: pointer;
            transition: background .2s, transform .1s;
            box-shadow: 0 4px 14px rgba(45,122,74,.35);
            display: flex; align-items: center; justify-content: center;
            gap: .55rem; font-family: inherit; letter-spacing: .2px;
        }
        .btn-primary:hover { background: #348a54; box-shadow: 0 6px 18px rgba(45,122,74,.45); }
        .btn-primary:active { transform: scale(.99); }

        /* Quick Demo Credentials Box */
        .demo-accounts {
            margin-top: 1.2rem;
            background: rgba(0,0,0,.25);
            border: 1px dashed rgba(157,233,183,.25);
            border-radius: 12px;
            padding: .75rem;
            font-size: .75rem;
            color: #9de9b7;
        }
        .demo-chip {
            display: inline-block;
            background: rgba(45,122,74,.4);
            border: 1px solid rgba(157,233,183,.3);
            border-radius: 6px;
            padding: 2px 7px;
            margin: 2px;
            cursor: pointer;
            transition: 0.15s;
        }
        .demo-chip:hover {
            background: rgba(45,122,74,.8);
            color: #fff;
        }

        .auth-footer-note {
            margin-top: 1.2rem; text-align: center;
            color: rgba(255,255,255,.35); font-size: .7rem;
            letter-spacing: .3px; border-top: 1px solid rgba(255,255,255,.08);
            padding-top: 1rem;
        }
        .auth-footer-note i { margin: 0 5px; color: rgba(255,255,255,.2); }

        /* Toast */
        .toast {
            position: fixed; left: 50%; bottom: 2rem;
            transform: translate(-50%, 200%);
            display: flex; align-items: center; gap: .65rem;
            background: rgba(20,32,27,.96);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(46, 204, 113, .4);
            color: #fff; padding: .8rem 1.4rem; border-radius: 60px;
            font-size: .88rem; font-weight: 500;
            box-shadow: 0 14px 40px rgba(0,0,0,.6);
            z-index: 2500;
            transition: transform .45s cubic-bezier(.34,1.56,.64,1);
            max-width: 90vw; text-align: center;
        }
        .toast.show { transform: translate(-50%, 0); }
        .toast i { color: #2ecc71; font-size: 1rem; flex-shrink: 0; }
    </style>
</head>
<body class="text-gray-200 font-sans antialiased overflow-x-hidden min-h-screen bg-transparent">

    <!-- SHARED BACKGROUND -->
    <div class="site-bg" aria-hidden="true"></div>

    <!-- HEADER / NAVBAR -->
    <header class="sticky top-0 z-50 glass border-b border-white/10 transition-all duration-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">

            <!-- Logo -->
            <a href="index.php" class="flex items-center space-x-3 cursor-pointer">
                <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center text-white shadow-md shadow-emerald-600/30">
                    <i class="fa-solid fa-recycle text-xl"></i>
                </div>
                <div>
                    <span class="text-xl font-bold tracking-tight text-white block leading-none">Waste<span class="text-emerald-300">Wise</span></span>
                    <span class="text-[11px] font-medium text-emerald-200/70 tracking-wide block mt-1">Smart Waste Segregation Assistant</span>
                </div>
            </a>

            <!-- Desktop Nav -->
            <nav class="hidden md:flex items-center space-x-8 text-sm font-medium">
                <a href="#home" class="text-white font-semibold relative py-1 after:content-[''] after:absolute after:bottom-0 after:left-0 after:w-full after:h-[2px] after:bg-emerald-300 after:rounded-full">Home</a>
                <a href="#about" class="text-gray-300 hover:text-white transition-colors">About Us</a>
                <a href="#how-it-works" class="text-gray-300 hover:text-white transition-colors">How It Works</a>
                <a href="#features" class="text-gray-300 hover:text-white transition-colors">Features</a>
                <?php if ($currentUser): ?>
                    <a href="<?php echo $currentUser['role'] === 'admin' ? 'admin.php' : ($currentUser['role'] === 'collector' ? 'collector.php' : 'dashboard.php'); ?>" class="text-emerald-300 hover:text-emerald-200 font-semibold transition-colors">
                        <i class="fa-solid fa-gauge-high mr-1"></i> Dashboard
                    </a>
                <?php endif; ?>
            </nav>

            <!-- Right CTA -->
            <div class="flex items-center space-x-3">
                <?php if ($currentUser): ?>
                    <a href="<?php echo $currentUser['role'] === 'admin' ? 'admin.php' : ($currentUser['role'] === 'collector' ? 'collector.php' : 'dashboard.php'); ?>" class="inline-flex items-center justify-center px-5 py-2.5 bg-emerald-700 hover:bg-emerald-600 text-white font-medium text-sm rounded-full shadow-md transition-all duration-200">
                        <i class="fa-solid fa-user mr-2 text-xs"></i> <?php echo htmlspecialchars($currentUser['name']); ?>
                    </a>
                    <a href="logout.php" class="inline-flex items-center justify-center px-4 py-2 bg-white/10 hover:bg-white/20 text-white border border-white/20 font-medium text-xs rounded-full backdrop-blur transition-all duration-200">
                        <i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Logout
                    </a>
                <?php else: ?>
                    <button id="headerLoginBtn" class="hidden sm:inline-flex items-center justify-center px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white border border-white/20 font-medium text-sm rounded-full backdrop-blur transition-all duration-200">
                        <i class="fa-solid fa-right-to-bracket mr-2 text-xs"></i> Log In
                    </button>
                    <button id="headerSignupBtn" class="hidden sm:inline-flex items-center justify-center px-6 py-2.5 bg-emerald-700 hover:bg-emerald-600 text-white font-medium text-sm rounded-full shadow-md shadow-emerald-900/40 transition-all duration-200 hover:shadow-lg active:scale-95">
                        Sign Up
                    </button>
                <?php endif; ?>
                <button id="mobile-menu-btn" class="md:hidden p-2 rounded-lg text-gray-200 hover:text-white hover:bg-white/10 focus:outline-none">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden glass border-b border-white/10 px-4 pt-2 pb-6 space-y-3 shadow-lg">
            <a href="#home" class="block px-3 py-2 rounded-md text-base font-semibold text-white bg-white/10">Home</a>
            <a href="#about" class="block px-3 py-2 rounded-md text-base font-medium text-gray-300 hover:bg-white/10 hover:text-white">About Us</a>
            <a href="#how-it-works" class="block px-3 py-2 rounded-md text-base font-medium text-gray-300 hover:bg-white/10 hover:text-white">How It Works</a>
            <a href="#features" class="block px-3 py-2 rounded-md text-base font-medium text-gray-300 hover:bg-white/10 hover:text-white">Features</a>
            <?php if ($currentUser): ?>
                <a href="<?php echo $currentUser['role'] === 'admin' ? 'admin.php' : ($currentUser['role'] === 'collector' ? 'collector.php' : 'dashboard.php'); ?>" class="block w-full text-center px-6 py-2.5 bg-emerald-700 text-white font-medium text-sm rounded-full">
                    Go to Dashboard
                </a>
                <a href="logout.php" class="block w-full text-center px-6 py-2 bg-white/10 text-white border border-white/20 font-medium text-sm rounded-full">
                    Logout
                </a>
            <?php else: ?>
                <button id="mobileLoginBtn" class="block w-full text-center mt-2 px-6 py-2.5 bg-white/10 text-white border border-white/20 font-medium text-sm rounded-full">Log In</button>
                <button id="mobileSignupBtn" class="block w-full text-center px-6 py-2.5 bg-emerald-700 text-white font-medium text-sm rounded-full shadow-sm">Sign Up</button>
            <?php endif; ?>
        </div>
    </header>

    <!-- HERO -->
    <section id="home" class="relative pt-12 pb-20 md:pt-16 md:pb-28 overflow-hidden">
        <div class="absolute top-10 right-5 opacity-10 pointer-events-none hidden lg:block">
            <i class="fa-solid fa-leaf text-emerald-200 text-9xl"></i>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">

                <div class="lg:col-span-5 space-y-6 text-left">
                    <div class="inline-flex items-center space-x-2 bg-white/10 border border-white/15 px-3.5 py-1.5 rounded-full text-emerald-100 text-xs font-semibold tracking-wide shadow-sm backdrop-blur">
                        <i class="fa-solid fa-leaf text-emerald-300"></i>
                        <span>A Cleaner Tomorrow Starts with You</span>
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-5xl font-extrabold text-white tracking-tight leading-[1.15] text-shadow-soft">
                        Smart Waste Solutions for a <span class="text-emerald-300 inline-block">Greener Future</span>
                    </h1>

                    <p class="text-gray-300 text-base sm:text-lg leading-relaxed text-shadow-sm">
                        <strong class="text-white font-semibold">WasteWise</strong> is a smart waste segregation assistant designed to help you identify, segregate, and properly dispose of different types of waste. Connected directly to MySQL database <span class="text-emerald-300 font-semibold">tatang</span>.
                    </p>

                    <div class="flex flex-wrap items-center gap-4 pt-2">
                        <?php if ($currentUser): ?>
                            <a href="<?php echo $currentUser['role'] === 'admin' ? 'admin.php' : ($currentUser['role'] === 'collector' ? 'collector.php' : 'dashboard.php'); ?>" class="inline-flex items-center justify-center px-7 py-3 bg-emerald-700 hover:bg-emerald-600 text-white font-medium text-sm rounded-full shadow-lg transition-all duration-200 hover:-translate-y-0.5">
                                Go to Dashboard <i class="fa-solid fa-arrow-right ml-2.5 text-xs"></i>
                            </a>
                        <?php else: ?>
                            <button id="heroSignupBtn" class="inline-flex items-center justify-center px-7 py-3 bg-emerald-700 hover:bg-emerald-600 text-white font-medium text-sm rounded-full shadow-lg shadow-emerald-900/40 transition-all duration-200 hover:shadow-xl hover:-translate-y-0.5">
                                Get Started
                                <i class="fa-solid fa-arrow-right ml-2.5 text-xs"></i>
                            </button>
                            <button id="heroLoginBtn" class="inline-flex items-center justify-center px-7 py-3 bg-white/10 hover:bg-white/15 text-white border border-white/20 font-medium text-sm rounded-full backdrop-blur transition-all duration-200">
                                <i class="fa-solid fa-right-to-bracket mr-2 text-xs"></i> Log In
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dashboard Mockup -->
                <div class="lg:col-span-7 relative">
                    <div class="relative glass-soft rounded-3xl p-4 sm:p-6 lg:p-8">
                        <div class="absolute -right-4 -bottom-4 sm:-right-8 sm:-bottom-8 w-32 h-32 sm:w-48 sm:h-48 bg-emerald-400/10 rounded-full blur-2xl pointer-events-none"></div>

                        <div class="hidden sm:flex absolute -right-6 bottom-12 w-28 h-40 bg-emerald-700 rounded-3xl shadow-xl flex-col items-center justify-center text-white border-4 border-white/80 transform rotate-6 z-20">
                            <i class="fa-solid fa-trash-can text-4xl mb-2 text-emerald-200"></i>
                            <i class="fa-solid fa-recycle text-3xl text-white"></i>
                            <div class="text-[10px] font-bold tracking-widest uppercase mt-2 text-emerald-100">ECO BIN</div>
                        </div>

                        <div class="bg-white rounded-2xl dashboard-shadow border border-gray-100 p-4 sm:p-6 relative z-10 text-gray-700">
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-4 border-b border-gray-100">
                                <div class="flex items-center space-x-2">
                                    <div class="w-6 h-6 rounded-md bg-emerald-600 flex items-center justify-center text-white text-xs">
                                        <i class="fa-solid fa-recycle"></i>
                                    </div>
                                    <span class="font-bold text-gray-800 text-sm">WasteWise</span>
                                </div>
                                <div class="flex items-center space-x-2 bg-gray-50 px-2.5 py-1 rounded-full border border-gray-100">
                                    <div class="w-5 h-5 rounded-full bg-emerald-700 text-white flex items-center justify-center text-[10px] font-semibold">U</div>
                                    <span class="text-xs font-medium text-gray-700"><?php echo htmlspecialchars($currentUser['name'] ?? 'Waste Warrior'); ?></span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mt-4">
                                <div class="hidden md:block md:col-span-3 space-y-1 text-xs">
                                    <a href="dashboard.php" class="flex items-center space-x-2 px-3 py-2 bg-emerald-50 text-emerald-800 font-semibold rounded-lg"><i class="fa-solid fa-house text-emerald-600"></i><span>Home</span></a>
                                    <a href="dashboard.php#search-result" class="flex items-center space-x-2 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg"><i class="fa-solid fa-magnifying-glass"></i><span>Wise Search</span></a>
                                    <a href="dashboard.php#schedule" class="flex items-center space-x-2 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg"><i class="fa-regular fa-calendar"></i><span>Schedule</span></a>
                                    <a href="dashboard.php#community" class="flex items-center space-x-2 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg"><i class="fa-solid fa-recycle"></i><span>Recycling</span></a>
                                    <a href="dashboard.php#community" class="flex items-center space-x-2 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg"><i class="fa-solid fa-users"></i><span>Community</span></a>
                                </div>

                                <div class="col-span-1 md:col-span-9 space-y-4">
                                    <div>
                                        <h3 class="text-sm font-bold text-gray-800">Good day, <?php echo htmlspecialchars($currentUser['name'] ?? 'Waste Warrior'); ?>!</h3>
                                        <p class="text-[11px] text-gray-500">Let's make a positive impact today.</p>
                                    </div>

                                    <div class="relative">
                                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                        <input type="text" placeholder="What waste do you want to dispose?" class="w-full text-xs pl-8 pr-10 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:border-emerald-500 focus:bg-white transition-all placeholder:text-gray-400">
                                    </div>

                                    <!-- Real Impact Counters from DB tatang -->
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                        <div class="bg-emerald-50/60 border border-emerald-100 p-2 rounded-xl flex items-center space-x-2">
                                            <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs shrink-0"><i class="fa-solid fa-tree"></i></div>
                                            <div class="overflow-hidden"><div class="text-xs font-bold text-gray-800 leading-none"><?php echo htmlspecialchars($stats['trees_saved'] ?? '1,245'); ?></div><div class="text-[9px] text-gray-500 truncate mt-0.5">Trees Saved</div></div>
                                        </div>
                                        <div class="bg-emerald-50/60 border border-emerald-100 p-2 rounded-xl flex items-center space-x-2">
                                            <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs shrink-0"><i class="fa-solid fa-recycle"></i></div>
                                            <div class="overflow-hidden"><div class="text-xs font-bold text-gray-800 leading-none"><?php echo htmlspecialchars($stats['kg_recycled'] ?? '3,678'); ?> kg</div><div class="text-[9px] text-gray-500 truncate mt-0.5">Recycled</div></div>
                                        </div>
                                        <div class="bg-emerald-50/60 border border-emerald-100 p-2 rounded-xl flex items-center space-x-2">
                                            <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs shrink-0"><i class="fa-solid fa-users"></i></div>
                                            <div class="overflow-hidden"><div class="text-xs font-bold text-gray-800 leading-none"><?php echo htmlspecialchars($stats['active_users'] ?? '892'); ?></div><div class="text-[9px] text-gray-500 truncate mt-0.5">Users</div></div>
                                        </div>
                                        <div class="bg-emerald-50/60 border border-emerald-100 p-2 rounded-xl flex items-center space-x-2">
                                            <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs shrink-0"><i class="fa-solid fa-leaf"></i></div>
                                            <div class="overflow-hidden"><div class="text-xs font-bold text-gray-800 leading-none"><?php echo htmlspecialchars($stats['co2_saved'] ?? '12.5'); ?> tons</div><div class="text-[9px] text-gray-500 truncate mt-0.5">CO₂ Saved</div></div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                        <div class="bg-emerald-50 border border-emerald-200/80 rounded-xl p-3 text-center"><div class="w-8 h-8 mx-auto rounded-full bg-emerald-200 text-emerald-800 flex items-center justify-center text-sm mb-1.5"><i class="fa-solid fa-leaf"></i></div><span class="text-[11px] font-bold text-emerald-900 block">Biodegradable</span></div>
                                        <div class="bg-blue-50 border border-blue-200/80 rounded-xl p-3 text-center"><div class="w-8 h-8 mx-auto rounded-full bg-blue-200 text-blue-700 flex items-center justify-center text-sm mb-1.5"><i class="fa-solid fa-recycle"></i></div><span class="text-[11px] font-bold text-blue-900 block">Recyclable</span></div>
                                        <div class="bg-gray-100 border border-gray-200 rounded-xl p-3 text-center"><div class="w-8 h-8 mx-auto rounded-full bg-gray-200 text-gray-700 flex items-center justify-center text-sm mb-1.5"><i class="fa-solid fa-trash-can"></i></div><span class="text-[11px] font-bold text-gray-800 block">Residual</span></div>
                                        <div class="bg-rose-50 border border-rose-200/80 rounded-xl p-3 text-center"><div class="w-8 h-8 mx-auto rounded-full bg-rose-200 text-rose-700 flex items-center justify-center text-sm mb-1.5"><i class="fa-solid fa-triangle-exclamation"></i></div><span class="text-[11px] font-bold text-rose-900 block">Hazardous</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- FEATURES -->
    <section id="features" class="py-16 md:py-24 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-left mb-12">
                <div class="flex items-center space-x-2 text-emerald-300 font-bold text-lg mb-1 text-shadow-sm">
                    <i class="fa-solid fa-leaf text-emerald-300"></i><h2>Our Key Features</h2>
                </div>
                <p class="text-gray-300 text-sm sm:text-base text-shadow-sm">Everything you need to manage waste responsibly and conveniently.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="glass rounded-2xl p-6 custom-shadow hover-lift">
                    <div class="w-12 h-12 rounded-xl bg-emerald-400/15 text-emerald-200 flex items-center justify-center text-xl mb-5"><i class="fa-solid fa-magnifying-glass"></i></div>
                    <h3 class="text-base font-bold text-white mb-2">Identify Waste</h3>
                    <p class="text-gray-300 text-xs sm:text-sm leading-relaxed">Search for an item and learn its waste category and proper disposal method.</p>
                </div>
                <div class="glass rounded-2xl p-6 custom-shadow hover-lift">
                    <div class="w-12 h-12 rounded-xl bg-emerald-400/15 text-emerald-200 flex items-center justify-center text-xl mb-5"><i class="fa-regular fa-calendar-check"></i></div>
                    <h3 class="text-base font-bold text-white mb-2">Track Collection</h3>
                    <p class="text-gray-300 text-xs sm:text-sm leading-relaxed">Check collection schedules and stay updated on upcoming waste collection activities.</p>
                </div>
                <div class="glass rounded-2xl p-6 custom-shadow hover-lift">
                    <div class="w-12 h-12 rounded-xl bg-emerald-400/15 text-emerald-200 flex items-center justify-center text-xl mb-5"><i class="fa-solid fa-location-dot"></i></div>
                    <h3 class="text-base font-bold text-white mb-2">Find Recycling Centers</h3>
                    <p class="text-gray-300 text-xs sm:text-sm leading-relaxed">Discover nearby recycling centers and learn where recyclable materials go.</p>
                </div>
                <div class="glass rounded-2xl p-6 custom-shadow hover-lift">
                    <div class="w-12 h-12 rounded-xl bg-emerald-400/15 text-emerald-200 flex items-center justify-center text-xl mb-5"><i class="fa-solid fa-users"></i></div>
                    <h3 class="text-base font-bold text-white mb-2">Join the Community</h3>
                    <p class="text-gray-300 text-xs sm:text-sm leading-relaxed">Connect with other users and be part of a cleaner, greener community.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="how-it-works" class="py-16 md:py-24 relative overflow-hidden">
        <div class="absolute left-0 bottom-0 opacity-10 pointer-events-none hidden lg:block -translate-x-1/3 translate-y-1/3">
            <i class="fa-solid fa-seedling text-emerald-200 text-[240px]"></i>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center max-w-xl mx-auto mb-16">
                <div class="flex items-center justify-center space-x-2 text-emerald-300 font-bold text-lg mb-1 text-shadow-sm">
                    <i class="fa-solid fa-leaf text-emerald-300"></i><h2>How It Works</h2>
                </div>
                <p class="text-gray-300 text-sm sm:text-base text-shadow-sm">Just a few simple steps to make a big difference.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 relative">
                <div class="flex flex-col items-center text-center relative">
                    <div class="relative mb-4">
                        <div class="w-16 h-16 rounded-full bg-white/10 text-emerald-200 border border-white/15 backdrop-blur flex items-center justify-center text-2xl"><i class="fa-solid fa-magnifying-glass"></i></div>
                        <span class="absolute -top-1 -left-1 w-6 h-6 rounded-full bg-emerald-600 text-white text-[11px] font-bold flex items-center justify-center border-2 border-white/80">01</span>
                    </div>
                    <h3 class="text-base font-bold text-white mb-1.5">Search</h3>
                    <p class="text-gray-300 text-xs sm:text-sm max-w-xs leading-relaxed">Enter the item you want to dispose of.</p>
                    <div class="hidden lg:block absolute top-8 right-0 translate-x-1/2 text-emerald-300/40"><i class="fa-solid fa-arrow-right text-sm"></i></div>
                </div>
                <div class="flex flex-col items-center text-center relative">
                    <div class="relative mb-4">
                        <div class="w-16 h-16 rounded-full bg-white/10 text-emerald-200 border border-white/15 backdrop-blur flex items-center justify-center text-2xl"><i class="fa-regular fa-lightbulb"></i></div>
                        <span class="absolute -top-1 -left-1 w-6 h-6 rounded-full bg-emerald-600 text-white text-[11px] font-bold flex items-center justify-center border-2 border-white/80">02</span>
                    </div>
                    <h3 class="text-base font-bold text-white mb-1.5">Identify</h3>
                    <p class="text-gray-300 text-xs sm:text-sm max-w-xs leading-relaxed">Get the waste category and proper disposal method.</p>
                    <div class="hidden lg:block absolute top-8 right-0 translate-x-1/2 text-emerald-300/40"><i class="fa-solid fa-arrow-right text-sm"></i></div>
                </div>
                <div class="flex flex-col items-center text-center relative">
                    <div class="relative mb-4">
                        <div class="w-16 h-16 rounded-full bg-white/10 text-emerald-200 border border-white/15 backdrop-blur flex items-center justify-center text-2xl"><i class="fa-regular fa-file-lines"></i></div>
                        <span class="absolute -top-1 -left-1 w-6 h-6 rounded-full bg-emerald-600 text-white text-[11px] font-bold flex items-center justify-center border-2 border-white/80">03</span>
                    </div>
                    <h3 class="text-base font-bold text-white mb-1.5">Learn</h3>
                    <p class="text-gray-300 text-xs sm:text-sm max-w-xs leading-relaxed">Access helpful information and tips.</p>
                    <div class="hidden lg:block absolute top-8 right-0 translate-x-1/2 text-emerald-300/40"><i class="fa-solid fa-arrow-right text-sm"></i></div>
                </div>
                <div class="flex flex-col items-center text-center relative">
                    <div class="relative mb-4">
                        <div class="w-16 h-16 rounded-full bg-white/10 text-emerald-200 border border-white/15 backdrop-blur flex items-center justify-center text-2xl"><i class="fa-solid fa-leaf"></i></div>
                        <span class="absolute -top-1 -left-1 w-6 h-6 rounded-full bg-emerald-600 text-white text-[11px] font-bold flex items-center justify-center border-2 border-white/80">04</span>
                    </div>
                    <h3 class="text-base font-bold text-white mb-1.5">Act</h3>
                    <p class="text-gray-300 text-xs sm:text-sm max-w-xs leading-relaxed">Check collection schedules or find nearby recycling centers.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ABOUT -->
    <section id="about" class="py-16 md:py-24 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="glass rounded-3xl p-8 sm:p-10 lg:p-12 dashboard-shadow">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                    <div>
                        <div class="flex items-center space-x-2 text-emerald-300 font-bold text-lg mb-3 text-shadow-sm">
                            <i class="fa-solid fa-leaf text-emerald-300"></i><h2>About WasteWise</h2>
                        </div>
                        <p class="text-gray-200 leading-relaxed mb-4 text-shadow-sm">
                            WasteWise is your smart companion for responsible waste disposal. We help you identify, sort, and recycle correctly — making sustainability simple and rewarding.
                        </p>
                        <ul class="space-y-3 mt-6">
                            <li class="flex items-center gap-3 text-gray-200 text-sm"><i class="fa-solid fa-magnifying-glass text-emerald-300 w-5 text-center"></i> Instant waste search &amp; sorting guide</li>
                            <li class="flex items-center gap-3 text-gray-200 text-sm"><i class="fa-solid fa-calendar-check text-emerald-300 w-5 text-center"></i> Personalized collection schedules</li>
                            <li class="flex items-center gap-3 text-gray-200 text-sm"><i class="fa-solid fa-people-group text-emerald-300 w-5 text-center"></i> Community challenges &amp; leaderboards</li>
                        </ul>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="glass-soft rounded-2xl p-5 text-center"><div class="text-3xl font-extrabold text-emerald-300"><?php echo htmlspecialchars($stats['trees_saved'] ?? '1,245'); ?></div><div class="text-xs text-gray-300 mt-1">Trees Saved</div></div>
                        <div class="glass-soft rounded-2xl p-5 text-center"><div class="text-3xl font-extrabold text-emerald-300"><?php echo htmlspecialchars($stats['kg_recycled'] ?? '3,678'); ?></div><div class="text-xs text-gray-300 mt-1">kg Recycled</div></div>
                        <div class="glass-soft rounded-2xl p-5 text-center"><div class="text-3xl font-extrabold text-emerald-300"><?php echo htmlspecialchars($stats['active_users'] ?? '892'); ?></div><div class="text-xs text-gray-300 mt-1">Active Users</div></div>
                        <div class="glass-soft rounded-2xl p-5 text-center"><div class="text-3xl font-extrabold text-emerald-300"><?php echo htmlspecialchars($stats['co2_saved'] ?? '12.5'); ?></div><div class="text-xs text-gray-300 mt-1">Tons CO₂ Saved</div></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="glass border-t border-white/10 text-gray-400 py-12 text-xs sm:text-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center text-white font-bold"><i class="fa-solid fa-recycle"></i></div>
                    <span class="text-white font-bold text-base">WasteWise</span>
                </div>
                <p class="text-gray-400 text-center">&copy; 2026 WasteWise. Smart Waste Segregation Assistant. All rights reserved.</p>
                <div class="flex items-center space-x-6">
                    <a href="dashboard.php" class="hover:text-emerald-300 transition-colors">Citizen Dashboard</a>
                    <a href="collector.php" class="hover:text-emerald-300 transition-colors">Collector Route</a>
                    <a href="admin.php" class="hover:text-emerald-300 transition-colors">Admin Portal</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- ============================
         LOG IN / SIGN UP MODAL
         ============================ -->
    <div class="auth-overlay" id="authOverlay" role="dialog" aria-modal="true">
        <div class="auth-card" id="authCard">
            <button class="auth-close" id="authClose" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="auth-brand">
                <i class="fas fa-leaf"></i>
                <span>Welcome to WasteWise</span>
            </div>

            <div class="tab-group" role="tablist">
                <button class="tab-btn active" data-panel="login" role="tab" aria-selected="true">
                    <i class="fas fa-arrow-right-to-bracket"></i> Log In
                </button>
                <button class="tab-btn" data-panel="signup" role="tab" aria-selected="false">
                    <i class="fas fa-user-plus"></i> Sign Up
                </button>
            </div>

            <!-- LOGIN PANEL -->
            <div id="login-panel" class="form-panel" role="tabpanel">
                <form id="login-form" autocomplete="on">
                    <div class="auth-input-group">
                        <label for="login-email">Email</label>
                        <div class="auth-input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="login-email" placeholder="you@example.com" required value="warrior@wastewise.com">
                        </div>
                    </div>

                    <div class="auth-input-group">
                        <label for="login-password">Password</label>
                        <div class="auth-input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="login-password" placeholder="Enter your password" required value="user123">
                            <button type="button" class="password-toggle" aria-label="toggle password visibility" tabindex="-1">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="auth-options">
                        <label><input type="checkbox" checked> Remember me</label>
                        <a href="#" onclick="alert('Please contact your administrator or use the demo accounts.')">Forgot password?</a>
                    </div>

                    <button type="submit" id="loginSubmitBtn" class="btn-primary">
                        <i class="fas fa-arrow-right-to-bracket"></i> Log In
                    </button>
                </form>

                <!-- Quick Demo Login Selection -->
                <div class="demo-accounts">
                    <p class="font-bold mb-1"><i class="fa-solid fa-key"></i> Quick Demo Accounts (Click to fill):</p>
                    <div>
                        <span class="demo-chip" onclick="fillDemo('warrior@wastewise.com', 'user123')">👤 Citizen (warrior)</span>
                        <span class="demo-chip" onclick="fillDemo('juan@wastewise.com', 'collector123')">🚛 Collector (juan)</span>
                        <span class="demo-chip" onclick="fillDemo('admin@wastewise.com', 'admin123')">⚡ Admin (admin)</span>
                    </div>
                </div>
            </div>

            <!-- SIGNUP PANEL -->
            <div id="signup-panel" class="form-panel hidden" role="tabpanel">
                <form id="signup-form" autocomplete="on">
                    <div class="auth-input-group">
                        <label for="signup-name">Full name</label>
                        <div class="auth-input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" id="signup-name" placeholder="Your name" required>
                        </div>
                    </div>

                    <div class="auth-input-group">
                        <label for="signup-email">Email</label>
                        <div class="auth-input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="signup-email" placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="auth-input-group">
                        <label for="signup-password">Password</label>
                        <div class="auth-input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="signup-password" placeholder="Create a strong password" required>
                            <button type="button" class="password-toggle" aria-label="toggle password visibility" tabindex="-1">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="auth-options">
                        <label><input type="checkbox" required checked> I agree to the terms</label>
                        <a href="#">Privacy policy</a>
                    </div>

                    <button type="submit" id="signupSubmitBtn" class="btn-primary">
                        <i class="fas fa-user-plus"></i> Create account
                    </button>
                </form>
            </div>

            <div class="auth-footer-note">
                <i class="fas fa-lock"></i> secure · <span>WasteWise &middot; tatang DB</span>
            </div>
        </div>
    </div>

    <!-- TOAST -->
    <div class="toast" id="toast" role="status" aria-live="polite">
        <i class="fas fa-circle-check"></i>
        <span id="toastMsg">Ready</span>
    </div>

    <!-- SCRIPT -->
    <script>
    (function () {
        'use strict';

        /* ====== TOAST ====== */
        const toastEl = document.getElementById('toast');
        const toastMsg = document.getElementById('toastMsg');
        let toastTimer = null;

        function showToast(message, icon) {
            if (message) toastMsg.textContent = message;
            if (icon) {
                toastEl.querySelector('i').className = 'fas ' + icon;
            }
            toastEl.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function () {
                toastEl.classList.remove('show');
            }, 3000);
        }

        /* ====== AUTH MODAL ====== */
        const authOverlay = document.getElementById('authOverlay');
        const authCard    = document.getElementById('authCard');
        const authClose   = document.getElementById('authClose');
        const tabBtns     = document.querySelectorAll('.tab-btn');
        const loginPanel  = document.getElementById('login-panel');
        const signupPanel = document.getElementById('signup-panel');
        const mobileMenu  = document.getElementById('mobile-menu');

        function openAuth(panel) {
            authOverlay.classList.add('open');
            document.body.style.overflow = 'hidden';
            if (panel) {
                const btn = document.querySelector('.tab-btn[data-panel="' + panel + '"]');
                if (btn) setActiveTab(btn);
            }
            if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.add('hidden');
            }
        }

        function closeAuth() {
            authOverlay.classList.remove('open');
            document.body.style.overflow = '';
        }

        function shakeCard() {
            authCard.classList.remove('shake');
            void authCard.offsetWidth;
            authCard.classList.add('shake');
            setTimeout(function () { authCard.classList.remove('shake'); }, 500);
        }

        function setActiveTab(activeBtn) {
            tabBtns.forEach(function (btn) {
                const isActive = btn === activeBtn;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', isActive);
            });
            const panelId = activeBtn.dataset.panel;
            if (panelId === 'login') {
                loginPanel.classList.remove('hidden');
                signupPanel.classList.add('hidden');
            } else {
                signupPanel.classList.remove('hidden');
                loginPanel.classList.add('hidden');
            }
        }

        tabBtns.forEach(function (btn) {
            btn.addEventListener('click', function () { setActiveTab(this); });
        });

        authClose.addEventListener('click', closeAuth);
        authOverlay.addEventListener('click', function (e) {
            if (e.target === authOverlay) closeAuth();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && authOverlay.classList.contains('open')) closeAuth();
        });

        /* ====== OPEN AUTH FROM BUTTONS ====== */
        function bindOpenAuth(id, panel) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('click', function (e) { e.preventDefault(); openAuth(panel); });
        }
        bindOpenAuth('headerLoginBtn',  'login');
        bindOpenAuth('headerSignupBtn', 'signup');
        bindOpenAuth('mobileLoginBtn',  'login');
        bindOpenAuth('mobileSignupBtn', 'signup');
        bindOpenAuth('heroLoginBtn',    'login');
        bindOpenAuth('heroSignupBtn',   'signup');

        /* ====== PASSWORD TOGGLE ====== */
        document.querySelectorAll('.password-toggle').forEach(function (toggleBtn) {
            toggleBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                const wrapper = this.closest('.auth-input-wrapper');
                const input = wrapper.querySelector('input');
                if (!input) return;
                const icon = this.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        /* ====== QUICK DEMO FILL ====== */
        window.fillDemo = function (email, pass) {
            document.getElementById('login-email').value = email;
            document.getElementById('login-password').value = pass;
            showToast('Filled credentials for ' + email, 'fa-key');
        };

        /* ====== LOGIN → Custom PHP backend connection ====== */
        document.getElementById('login-form').addEventListener('submit', function (e) {
            e.preventDefault();
            const email = document.getElementById('login-email').value.trim();
            const password = document.getElementById('login-password').value;
            const submitBtn = document.getElementById('loginSubmitBtn');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in…';

            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('email', email);
            formData.append('password', password);

            fetch('api/auth.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-arrow-right-to-bracket"></i> Log In';

                if (data.status === 'success') {
                    showToast(data.message, 'fa-circle-check');
                    setTimeout(function () {
                        window.location.href = data.redirect || 'dashboard.php';
                    }, 500);
                } else {
                    shakeCard();
                    showToast(data.message || 'Login failed', 'fa-triangle-exclamation');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-arrow-right-to-bracket"></i> Log In';
                showToast('Server connection error. Please try again.', 'fa-circle-xmark');
            });
        });

        /* ====== SIGNUP → Custom PHP backend connection ====== */
        document.getElementById('signup-form').addEventListener('submit', function (e) {
            e.preventDefault();
            const signupName  = document.getElementById('signup-name').value.trim();
            const signupEmail = document.getElementById('signup-email').value.trim();
            const signupPassword = document.getElementById('signup-password').value;
            const submitBtn = document.getElementById('signupSubmitBtn');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account…';

            const formData = new FormData();
            formData.append('action', 'signup');
            formData.append('name', signupName);
            formData.append('email', signupEmail);
            formData.append('password', signupPassword);

            fetch('api/auth.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create account';

                if (data.status === 'success') {
                    showToast('Account created! Logging you in...', 'fa-circle-check');
                    document.getElementById('login-email').value = signupEmail;
                    document.getElementById('login-password').value = signupPassword;
                    setActiveTab(document.querySelector('.tab-btn[data-panel="login"]'));
                    // Automatically trigger login
                    document.getElementById('login-form').dispatchEvent(new Event('submit'));
                } else {
                    shakeCard();
                    showToast(data.message || 'Signup failed', 'fa-triangle-exclamation');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create account';
                showToast('Server error. Please try again.', 'fa-circle-xmark');
            });
        });

        /* ====== MOBILE MENU TOGGLE ====== */
        const menuBtn = document.getElementById('mobile-menu-btn');
        if (menuBtn && mobileMenu) {
            menuBtn.addEventListener('click', function () {
                mobileMenu.classList.toggle('hidden');
            });
        }
    })();
    </script>
</body>
</html>
