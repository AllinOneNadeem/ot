<?php
// ============================================================
// ADMIN SIDEBAR
// ============================================================
// This file is included in all admin pages
// Variables available: $siteSettings, $adminUser, $activeTab, $totalUsers, $totalServices, $pendingOrders
?>
        <!-- ============================================================
        SIDEBAR
        ============================================================ -->
        <div class="sidebar" id="sidebar">
            <!-- Logo Area -->
            <div class="logo-area">
                <?php if(!empty($siteSettings['site_logo'])): ?>
                    <div class="logo-icon"><img src="<?php echo htmlspecialchars($siteSettings['site_logo']); ?>" style="width:40px; height:40px; object-fit:cover; border-radius:var(--radius);"></div>
                <?php else: ?>
                    <div class="logo-icon">O</div>
                <?php endif; ?>
                <h2><?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPX'); ?></h2>
                <span class="badge-admin">Admin</span>
            </div>

            <!-- Admin Profile -->
            <div class="admin-profile" onclick="window.location.href='?tab=profile'">
                <div class="avatar"><?php echo strtoupper($adminUser['name'][0] ?? 'A'); ?></div>
                <div class="info">
                    <div class="name"><?php echo htmlspecialchars($adminUser['name'] ?? 'Admin'); ?></div>
                    <div class="role">Administrator</div>
                </div>
            </div>

            <!-- Menu -->
            <div class="menu">
                <div class="menu-label">Main</div>
                <a class="menu-item <?php echo ($activeTab ?? 'dashboard') == 'dashboard' ? 'active' : ''; ?>" href="?tab=dashboard">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard
                </a>
                <a class="menu-item <?php echo ($activeTab ?? '') == 'users' ? 'active' : ''; ?>" href="?tab=users">
                    <i class="fa-solid fa-users"></i> Users
                    <span class="badge"><?php echo $totalUsers ?? 0; ?></span>
                </a>
                <a class="menu-item <?php echo ($activeTab ?? '') == 'services' ? 'active' : ''; ?>" href="?tab=services">
                    <i class="fa-solid fa-server"></i> Services
                    <span class="badge"><?php echo $totalServices ?? 0; ?></span>
                </a>
                <a class="menu-item <?php echo ($activeTab ?? '') == 'orders' ? 'active' : ''; ?>" href="?tab=orders">
                    <i class="fa-solid fa-cart-shopping"></i> Orders
                    <span class="badge"><?php echo $pendingOrders ?? 0; ?></span>
                </a>

                <div class="menu-label">Management</div>
                <a class="menu-item <?php echo ($activeTab ?? '') == 'payments' ? 'active' : ''; ?>" href="?tab=payments">
                    <i class="fa-solid fa-credit-card"></i> Payments
                </a>
                <a class="menu-item <?php echo ($activeTab ?? '') == 'payment-settings' ? 'active' : ''; ?>" href="?tab=payment-settings">
                    <i class="fa-solid fa-sliders"></i> Payment Settings
                </a>
                <a class="menu-item <?php echo ($activeTab ?? '') == 'api' ? 'active' : ''; ?>" href="?tab=api">
                    <i class="fa-solid fa-key"></i> API Settings
                </a>
                <a class="menu-item <?php echo ($activeTab ?? '') == 'settings' ? 'active' : ''; ?>" href="?tab=settings">
                    <i class="fa-solid fa-gear"></i> Site Settings
                </a>
                <a class="menu-item <?php echo ($activeTab ?? '') == 'support' ? 'active' : ''; ?>" href="?tab=support">
                    <i class="fa-solid fa-headset"></i> Support
                </a>
                <a class="menu-item <?php echo ($activeTab ?? '') == 'broadcast' ? 'active' : ''; ?>" href="?tab=broadcast">
                    <i class="fa-solid fa-bullhorn"></i> Broadcast
                </a>
                <a class="menu-item <?php echo ($activeTab ?? '') == 'footer' ? 'active' : ''; ?>" href="?tab=footer">
                    <i class="fa-solid fa-copyright"></i> Footer
                </a>
                <a class="menu-item <?php echo ($activeTab ?? '') == 'promo' ? 'active' : ''; ?>" href="?tab=promo">
                    <i class="fa-solid fa-ticket"></i> Promo Codes
                </a>
                <a class="menu-item <?php echo ($activeTab ?? '') == 'pages' ? 'active' : ''; ?>" href="?tab=pages">
                    <i class="fa-solid fa-file-lines"></i> Pages
                </a>
                <a class="menu-item <?php echo ($activeTab ?? '') == 'banners' ? 'active' : ''; ?>" href="?tab=banners">
                    <i class="fa-solid fa-image"></i> Banners
                </a>
            </div>

            <!-- Logout Button -->
            <a href="../logout.php" class="logout-btn">
                <i class="fa-solid fa-sign-out-alt"></i> Logout
            </a>
        </div>

<style>
/* ============================================================
   SIDEBAR STYLES
   ============================================================ */
.sidebar {
    width: var(--sidebar-width, 270px);
    background: var(--bg-sidebar, #0f172a);
    color: var(--text-white, #ffffff);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    overflow-y: auto;
    transition: transform 0.3s ease;
    z-index: 100;
}

.sidebar .logo-area {
    padding: 20px 20px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar .logo-area .logo-icon {
    width: 40px;
    height: 40px;
    background: var(--accent, #246bfd);
    border-radius: var(--radius, 12px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 800;
    color: white;
    flex-shrink: 0;
    overflow: hidden;
}

.sidebar .logo-area .logo-icon img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sidebar .logo-area h2 {
    font-size: 18px;
    font-weight: 700;
    letter-spacing: -0.3px;
}

.sidebar .logo-area .badge-admin {
    background: rgba(239,68,68,0.2);
    color: #f87171;
    font-size: 9px;
    padding: 2px 8px;
    border-radius: 20px;
    font-weight: 600;
    text-transform: uppercase;
    margin-left: auto;
}

.sidebar .admin-profile {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
}

.sidebar .admin-profile .avatar {
    width: 42px;
    height: 42px;
    background: var(--accent, #246bfd);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    color: white;
    flex-shrink: 0;
}

.sidebar .admin-profile .info {
    flex: 1;
    overflow: hidden;
}

.sidebar .admin-profile .info .name {
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar .admin-profile .info .role {
    font-size: 11px;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.sidebar .menu {
    flex: 1;
    padding: 12px 10px;
    overflow-y: auto;
}

.sidebar .menu .menu-label {
    font-size: 10px;
    text-transform: uppercase;
    color: rgba(255,255,255,0.25);
    letter-spacing: 1px;
    padding: 12px 12px 6px;
    font-weight: 600;
}

.sidebar .menu .menu-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    color: rgba(255,255,255,0.55);
    border-radius: var(--radius, 12px);
    margin-bottom: 2px;
    font-weight: 500;
    font-size: 14px;
    cursor: pointer;
    transition: 0.2s;
    border: none;
    background: transparent;
    width: 100%;
    text-align: left;
    text-decoration: none;
}

.sidebar .menu .menu-item:hover {
    background: rgba(255,255,255,0.06);
    color: white;
}

.sidebar .menu .menu-item.active {
    background: rgba(36,107,253,0.15);
    color: var(--accent, #246bfd);
}

.sidebar .menu .menu-item i {
    font-size: 16px;
    width: 20px;
    text-align: center;
}

.sidebar .menu .menu-item .badge {
    margin-left: auto;
    background: var(--danger, #ef4444);
    color: white;
    font-size: 10px;
    padding: 1px 8px;
    border-radius: 20px;
    font-weight: 700;
}

.sidebar .logout-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    margin: 8px 10px 16px;
    color: rgba(255,255,255,0.4);
    border: 1px solid rgba(239,68,68,0.2);
    border-radius: var(--radius, 12px);
    cursor: pointer;
    transition: 0.2s;
    background: transparent;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
}

.sidebar .logout-btn:hover {
    background: rgba(239,68,68,0.12);
    color: #f87171;
    border-color: rgba(239,68,68,0.4);
}

.sidebar .logout-btn i {
    font-size: 16px;
}

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 768px) {
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        transform: translateX(-100%);
        box-shadow: 5px 0 30px rgba(0,0,0,0.3);
        width: 280px;
        z-index: 100;
    }

    .sidebar.open {
        transform: translateX(0);
    }
}
</style>