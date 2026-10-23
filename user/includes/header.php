<?php
// ============================================================
// USER HEADER - Top Navbar
// ============================================================
// Variables available: $siteSettings, $user, $currency, $userBalance, $userName, $userEmail, $userInitial, $isAdmin, $activeTab
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPSMS'); ?> - Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ============================================================
           USER PANEL MASTER STYLES
           ============================================================ */
        :root {
            --bg-body: #f0f4ff;
            --bg-sidebar: #ffffff;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --accent: #246bfd;
            --accent-dark: #1a56db;
            --hover-bg: rgba(36, 107, 253, 0.06);
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --sidebar-width: 260px;
            --right-panel-width: 380px;
            --radius: 12px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.12);
        }

        body.dark-mode {
            --bg-body: #0a0e17;
            --bg-sidebar: #131824;
            --bg-card: #131824;
            --text-main: #ffffff;
            --text-muted: #8a939f;
            --border-color: #1e2532;
            --hover-bg: #1c2230;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, -apple-system, sans-serif; -webkit-tap-highlight-color: transparent; }
        body { background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; transition: background 0.3s, color 0.3s; }
        ::-webkit-scrollbar { width: 4px; background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
        a { text-decoration: none; color: inherit; }

        .app-container { display: flex; width: 100%; height: 100%; }

        /* ============================================================
           TOP NAVBAR
           ============================================================ */
        .top-navbar {
            display: none;
            padding: 12px 20px;
            background: var(--bg-sidebar);
            border-bottom: 1px solid var(--border-color);
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            flex-shrink: 0;
        }

        .top-navbar .top-nav-left { display: flex; align-items: center; gap: 15px; }
        .top-navbar .top-nav-left .menu-btn { background: transparent; border: none; font-size: 24px; color: var(--text-main); cursor: pointer; }
        .top-navbar .top-nav-left .brand { font-weight: 700; font-size: 18px; display: flex; align-items: center; gap: 8px; }
        .top-navbar .top-nav-left .brand .logo-circle { width: 28px; height: 28px; background: var(--accent); border-radius: 50%; display: flex; justify-content: center; align-items: center; color: white; font-weight: 800; font-size: 12px; flex-shrink: 0; }
        .top-navbar .top-nav-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .top-navbar .top-nav-right .admin-btn { background: var(--accent); color: white; padding: 6px 14px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; border: none; cursor: pointer; transition: 0.2s; }
        .top-navbar .top-nav-right .admin-btn:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(36,107,253,0.3); }
        .top-navbar .top-nav-right .balance-badge { background: var(--hover-bg); padding: 6px 14px; border-radius: 10px; font-weight: 700; color: var(--accent); cursor: pointer; font-size: 14px; }
        .top-navbar .top-nav-right .bell-icon { font-size: 20px; color: var(--text-muted); cursor: pointer; }

        .main-content { flex: 1; display: flex; flex-direction: column; background: var(--bg-body); min-width: 0; }
        .content-scroll { flex: 1; overflow-y: auto; padding: 25px 30px; scroll-behavior: smooth; }

        /* ============================================================
           CONTENT SECTIONS
           ============================================================ */
        .content-section { display: none; animation: fadeIn 0.3s; }
        .content-section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* ============================================================
           SEARCH BAR
           ============================================================ */
        .search-bar { background: var(--bg-card); padding: 12px 18px; border-radius: var(--radius); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .search-bar input { border: none; outline: none; width: 100%; font-size: 15px; background: transparent; color: var(--text-main); }

        /* ============================================================
           FILTER CONTAINER
           ============================================================ */
        .filter-container { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; align-items: center; }
        .server-filter-btn { padding: 8px 16px; border: 2px solid var(--border-color); border-radius: 8px; background: transparent; color: var(--text-muted); font-weight: 600; cursor: pointer; transition: 0.2s; white-space: nowrap; }
        .server-filter-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .server-filter-btn.active { background: var(--accent) !important; color: white !important; border-color: var(--accent) !important; }
        .server-filter-btn .count-badge { font-size: 11px; background: var(--hover-bg); padding: 2px 8px; border-radius: 10px; margin-left: 4px; }

        /* ============================================================
           SERVICES GRID
           ============================================================ */
        .services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .server-group { display: block; grid-column: 1 / -1; margin-bottom: 15px; }
        .server-group .server-header { grid-column: 1/-1; display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: var(--bg-card); border-radius: var(--radius); border: 1px solid var(--border-color); margin-bottom: 5px; cursor: pointer; transition: 0.2s; }
        .server-group .server-header:hover { border-color: var(--accent); background: var(--hover-bg); }
        .server-group .service-card { grid-column: span 1; }

        /* ============================================================
           SERVICE CARD
           ============================================================ */
        .service-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.2s; }
        .service-card:hover { border-color: var(--accent); transform: translateY(-2px); box-shadow: 0 4px 15px rgba(36,107,253,0.1); }
        .service-card.active { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent); }
        .service-left { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .service-icon { width: 36px; height: 36px; border-radius: 10px; background: var(--bg-body); padding: 4px; display: flex; justify-content: center; align-items: center; overflow: hidden; flex-shrink: 0; }
        .service-icon img { width: 100%; height: 100%; object-fit: contain; }
        .service-icon i { font-size: 20px; color: var(--accent); }
        .service-name { font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .service-code { font-size: 11px; color: var(--text-muted); }
        .service-price { font-size: 13px; font-weight: 700; color: var(--accent); background: var(--hover-bg); padding: 4px 10px; border-radius: 8px; white-space: nowrap; }

        /* ============================================================
           BANNER SLIDER
           ============================================================ */
        .banner-slider { display: flex; gap: 15px; overflow-x: auto; padding: 10px 0 20px; scroll-snap-type: x mandatory; margin-bottom: 20px; }
        .banner-slider .banner-item { min-width: 280px; flex: 0 0 auto; scroll-snap-align: start; background: linear-gradient(135deg, var(--accent), #bc13fe); border-radius: 16px; padding: 20px 25px; color: white; cursor: pointer; transition: 0.3s; }
        .banner-slider .banner-item:hover { transform: scale(1.02); }
        .banner-slider .banner-item h3 { font-size: 18px; margin-bottom: 5px; }
        .banner-slider .banner-item p { opacity: 0.9; font-size: 14px; }
        .banner-slider .banner-item img { width: 100%; height: 100px; object-fit: cover; border-radius: 8px; margin-top: 10px; }
        .banner-slider::-webkit-scrollbar { display: none; }

        /* ============================================================
           HISTORY
           ============================================================ */
        .history-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .history-table th { text-align: left; padding: 10px 12px; background: var(--bg-body); color: var(--text-muted); font-weight: 600; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid var(--border-color); }
        .history-table td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .history-table tr:hover td { background: var(--hover-bg); }
        .history-tabs { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
        .history-tab { padding: 8px 20px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; background: transparent; color: var(--text-muted); transition: 0.2s; }
        .history-tab.active { background: var(--accent); color: white; border-color: var(--accent); }

        /* ============================================================
           STATUS BADGE
           ============================================================ */
        .status-badge { padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-badge.credit { background: #dcfce7; color: #16a34a; }
        .status-badge.debit { background: #fee2e2; color: #dc2626; }
        .status-badge.completed { background: #dcfce7; color: #16a34a; }
        .status-badge.pending { background: #fef3c7; color: #d97706; }
        .status-badge.failed { background: #fee2e2; color: #dc2626; }
        .status-badge.WAITING { background: #dbeafe; color: #2563eb; }
        .status-badge.RECEIVED { background: #dcfce7; color: #16a34a; }
        .status-badge.CANCELLED { background: #fee2e2; color: #dc2626; }
        .status-badge.active { background: #dcfce7; color: #16a34a; }
        .status-badge.banned { background: #fee2e2; color: #dc2626; }

        /* ============================================================
           PAYMENT & PROMO
           ============================================================ */
        .promo-input-group { display: flex; gap: 10px; align-items: center; }
        .promo-input-group input { flex: 1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; background: var(--bg-body); color: var(--text-main); outline: none; }
        .promo-input-group input:focus { border-color: var(--accent); }
        .promo-input-group button { padding: 10px 20px; background: var(--accent); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .promo-box { background: var(--bg-card); border: 1px dashed var(--accent); border-radius: var(--radius); padding: 15px; margin-bottom: 20px; }
        .payment-method { display: flex; align-items: center; justify-content: space-between; padding: 16px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius); margin-bottom: 12px; cursor: pointer; transition: 0.2s; }
        .payment-method:hover { border-color: var(--accent); }
        .amount-option { padding: 10px 20px; border: 2px solid var(--border-color); border-radius: var(--radius); cursor: pointer; font-weight: 600; transition: 0.2s; }
        .amount-option:hover { border-color: var(--accent); }
        .amount-option.active { border-color: var(--accent); background: var(--hover-bg); }

        /* ============================================================
           BUTTONS
           ============================================================ */
        .btn-primary { width: 100%; background: var(--accent); color: white; border: none; padding: 14px; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { background: var(--accent-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(36,107,253,0.3); }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }
        .btn { padding: 6px 12px; border: none; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; transition: 0.2s; }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #16a34a; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 4px 10px; font-size: 11px; }

        /* ============================================================
           OVERLAY
           ============================================================ */
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 50; backdrop-filter: blur(3px); }
        .overlay.active { display: block; }

        /* ============================================================
           RIGHT PANEL
           ============================================================ */
        .right-panel { width: var(--right-panel-width); background: var(--bg-sidebar); border-left: 1px solid var(--border-color); padding: 25px; display: flex; flex-direction: column; transition: transform 0.3s; z-index: 90; flex-shrink: 0; overflow-y: auto; }
        .right-panel-placeholder { text-align: center; color: var(--text-muted); margin-top: 50px; }
        .right-panel .rp-swipe-indicator { display: none; }
        .country-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; max-height: 250px; overflow-y: auto; padding: 8px; border: 1px solid var(--border-color); border-radius: var(--radius); }
        .country-grid .country-item { padding: 10px; border: 2px solid var(--border-color); border-radius: 8px; text-align: center; cursor: pointer; transition: 0.2s; font-weight: 600; font-size: 13px; }
        .country-grid .country-item:hover { border-color: var(--accent); background: var(--hover-bg); }
        .country-grid .country-item.active { border-color: var(--accent); background: var(--hover-bg); color: var(--accent); }
        .server-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; max-height: 200px; overflow-y: auto; }
        .server-grid .server-item { padding: 12px; border: 2px solid var(--border-color); border-radius: 8px; text-align: center; cursor: pointer; transition: 0.2s; font-weight: 600; font-size: 13px; }
        .server-grid .server-item:hover { border-color: var(--accent); background: var(--hover-bg); }
        .server-grid .server-item.active { border-color: var(--accent); background: var(--accent); color: white; }

        /* ============================================================
           PROFILE
           ============================================================ */
        .profile-edit-form .form-group { margin-bottom: 16px; }
        .profile-edit-form label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px; color: var(--text-main); }
        .profile-edit-form input { width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; background: var(--bg-body); color: var(--text-main); outline: none; transition: 0.2s; }
        .profile-edit-form input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(36,107,253,0.1); }
        .profile-edit-form .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        /* ============================================================
           FOOTER
           ============================================================ */
        .user-footer { background: var(--bg-sidebar); border-top: 1px solid var(--border-color); padding: 40px 30px 20px; margin-top: 30px; }
        .user-footer .footer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 30px; max-width: 1200px; margin: 0 auto; }
        .user-footer .footer-grid h4 { color: var(--text-main); margin-bottom: 12px; font-weight: 700; font-size: 14px; }
        .user-footer .footer-grid a { display: block; color: var(--text-muted); text-decoration: none; padding: 4px 0; font-size: 13px; transition: 0.2s; }
        .user-footer .footer-grid a:hover { color: var(--accent); }
        .user-footer .footer-bottom { border-top: 1px solid var(--border-color); margin-top: 25px; padding-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .user-footer .footer-bottom span { color: var(--text-muted); font-size: 13px; }

        /* ============================================================
           MODALS
           ============================================================ */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
        .modal-overlay.show { display: flex; }
        .modal-content { background: var(--bg-card); width: 100%; max-width: 500px; border-radius: 24px; padding: 30px; text-align: center; animation: fadeIn 0.3s; margin: 20px; max-height: 90vh; overflow-y: auto; position: relative; }
        .modal-content .close-modal { background: transparent; border: none; font-size: 24px; color: var(--text-muted); cursor: pointer; position: absolute; top: 15px; right: 20px; }
        .modal-content .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); position: relative; }

        /* ============================================================
           SPINNER
           ============================================================ */
        .spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 900px) {
            .top-navbar { display: flex; }
            .content-scroll { padding: 15px; }
            .services-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
            .service-card { padding: 12px; }
            .right-panel { position: fixed; bottom: 0; left: 0; width: 100%; height: auto; max-height: 80vh; transform: translateY(100%); border-top: 1px solid var(--border-color); border-left: none; border-radius: 24px 24px 0 0; box-shadow: 0 -10px 40px rgba(0,0,0,0.3); padding: 20px; z-index: 95; }
            .right-panel.open { transform: translateY(0); }
            .right-panel .rp-swipe-indicator { width: 40px; height: 5px; background: var(--border-color); border-radius: 10px; margin: 0 auto 15px; display: block !important; }
            .user-footer { padding: 30px 20px 15px; }
            .user-footer .footer-grid { grid-template-columns: 1fr 1fr; gap: 20px; }
            .profile-edit-form .form-row { grid-template-columns: 1fr; }
        }

        @media (max-width: 480px) {
            .services-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
            .service-card { padding: 10px; }
            .service-price { font-size: 11px; padding: 2px 8px; }
            .history-table { font-size: 11px; }
            .history-table th, .history-table td { padding: 6px 8px; }
            .user-footer .footer-grid { grid-template-columns: 1fr; }
            .top-navbar { padding: 10px 15px; }
            .top-navbar .top-nav-right .balance-badge { font-size: 12px; padding: 4px 10px; }
            .modal-content { margin: 10px; padding: 20px; }
        }
    </style>
</head>
<body>

<!-- ============================================================
OVERLAY
============================================================ -->
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ============================================================
APP CONTAINER
============================================================ -->
<div class="app-container">

    <!-- ============================================================
    SIDEBAR
    ============================================================ -->
    <?php include_once 'sidebar.php'; ?>

    <!-- ============================================================
    MAIN CONTENT
    ============================================================ -->
    <div class="main-content">

        <!-- ============================================================
        TOP NAVBAR - Mobile Only
        ============================================================ -->
        <div class="top-navbar">
            <div class="top-nav-left">
                <button class="menu-btn" onclick="toggleSidebar()">
                    <i class="fa-solid fa-bars-staggered"></i>
                </button>
                <div class="brand">
                    <div class="logo-circle">
                        <?php if(!empty($siteSettings['site_logo'])): ?>
                            <img src="<?php echo htmlspecialchars($siteSettings['site_logo']); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                        <?php else: ?>
                            O
                        <?php endif; ?>
                    </div>
                    <?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPSMS'); ?>
                </div>
            </div>
            <div class="top-nav-right">
                <?php if($isAdmin): ?>
                    <a href="../admin/index.php" class="admin-btn"><i class="fa-solid fa-user-gear"></i> Admin</a>
                <?php endif; ?>
                <i class="fa-regular fa-bell bell-icon"></i>
                <div class="balance-badge" onclick="window.location.href='?tab=recharge'">
                    <?php echo htmlspecialchars($currency); ?><span id="nav-bal-amt"><?php echo $userBalance; ?></span>
                </div>
            </div>
        </div>