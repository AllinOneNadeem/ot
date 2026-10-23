<?php
// ============================================================
// ADMIN HEADER - Top Navbar
// ============================================================
// This file is included in all admin pages
// Variables available: $siteSettings, $adminUser, $activeTab
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPX'); ?> - Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        /* ============================================================
           ADMIN HEADER STYLES
           ============================================================ */
        .top-navbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            padding: 12px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            flex-wrap: wrap;
            gap: 10px;
        }

        .top-navbar .left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .top-navbar .left .menu-toggle {
            display: none;
            background: transparent;
            border: none;
            font-size: 22px;
            color: var(--text-main);
            cursor: pointer;
        }

        .top-navbar .left .page-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
        }

        .top-navbar .right {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .top-navbar .right .notification-btn {
            background: transparent;
            border: none;
            font-size: 20px;
            color: var(--text-muted);
            cursor: pointer;
            position: relative;
        }

        .top-navbar .right .notification-btn .dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid var(--bg-card);
        }

        .top-navbar .right .admin-badge {
            background: var(--accent-light);
            color: var(--accent);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .top-navbar .right .back-user-btn {
            background: #6b7280;
            color: white;
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }

        .top-navbar .right .back-user-btn:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }

        .top-navbar .right .back-admin-btn {
            background: #f59e0b;
            color: white;
            padding: 6px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }

        .top-navbar .right .back-admin-btn:hover {
            background: #d97706;
            transform: translateY(-1px);
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .top-navbar .left .menu-toggle {
                display: block;
            }

            .top-navbar .left .page-title {
                font-size: 15px;
            }

            .top-navbar .right .admin-badge {
                display: none;
            }

            .top-navbar .right .back-user-btn,
            .top-navbar .right .back-admin-btn {
                font-size: 11px;
                padding: 5px 10px;
            }

            .top-navbar {
                padding: 10px 15px;
            }
        }

        @media (max-width: 480px) {
            .top-navbar .right .notification-btn {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <div class="app-container">

        <!-- ============================================================
        SIDEBAR - Included from sidebar.php
        ============================================================ -->
        <?php include_once 'sidebar.php'; ?>

        <!-- ============================================================
        MAIN CONTENT
        ============================================================ -->
        <div class="main-content">

            <!-- ============================================================
            TOP NAVBAR
            ============================================================ -->
            <div class="top-navbar">
                <div class="left">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="fa-solid fa-bars-staggered"></i>
                    </button>
                    <span class="page-title" id="page-title">
                        <?php
                        $titles = [
                            'dashboard' => 'Dashboard',
                            'users' => 'Users Management',
                            'services' => 'Services Management',
                            'orders' => 'Orders Management',
                            'payments' => 'Payments',
                            'payment-settings' => 'Payment Settings',
                            'api' => 'API Settings',
                            'settings' => 'Site Settings',
                            'support' => 'Support',
                            'broadcast' => 'Broadcast',
                            'footer' => 'Footer Management',
                            'promo' => 'Promo Codes',
                            'pages' => 'Pages Management',
                            'banners' => 'Banner Management',
                            'profile' => 'Profile'
                        ];
                        echo $titles[$activeTab ?? 'dashboard'] ?? 'Dashboard';
                        ?>
                    </span>
                </div>
                <div class="right">
                    <?php if(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                        <a href="../back_to_admin.php" class="back-admin-btn">
                            <i class="fa-solid fa-arrow-left"></i> Back to Admin
                        </a>
                    <?php endif; ?>
                    <a href="../index.php" class="back-user-btn">
                        <i class="fa-solid fa-arrow-left"></i> Back to User Panel
                    </a>
                    <button class="notification-btn" onclick="showNotification()">
                        <i class="fa-regular fa-bell"></i>
                        <span class="dot"></span>
                    </button>
                    <span class="admin-badge"><i class="fa-regular fa-user"></i> Admin</span>
                </div>
            </div>

            <!-- ============================================================
            CONTENT AREA
            ============================================================ -->
            <div class="content-area">