<?php
// ============================================================
// USER SIDEBAR
// ============================================================
// Variables available: $siteSettings, $user, $userName, $userEmail, $userInitial, $userBalance, $currency, $isAdmin, $activeTab, $activeNumbersCount
?>
<!-- ============================================================
SIDEBAR
============================================================ -->
<div class="sidebar" id="sidebar">
    <!-- Logo Area -->
    <div class="logo-area">
        <div class="logo-inner">
            <?php if(!empty($siteSettings['site_logo'])): ?>
                <div class="logo-circle">
                    <img src="<?php echo htmlspecialchars($siteSettings['site_logo']); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                </div>
            <?php else: ?>
                <div class="logo-circle">O</div>
            <?php endif; ?>
            <span class="site-name"><?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPSMS'); ?></span>
        </div>
        <button class="theme-toggle" id="theme-btn" onclick="toggleTheme()">
            <i class="fa-solid fa-moon"></i>
        </button>
    </div>

    <!-- Balance Box -->
    <div class="balance-box">
        <p>Available Balance</p>
        <h2>
            <?php echo htmlspecialchars($currency); ?><span id="bal-amt"><?php echo $userBalance; ?></span>
            <div class="add-btn" onclick="window.location.href='?tab=recharge'">
                <i class="fa-solid fa-plus"></i>
            </div>
        </h2>
    </div>

    <!-- Menu -->
    <div class="menu">
        <a class="menu-item <?php echo ($activeTab ?? 'dashboard') == 'dashboard' ? 'active' : ''; ?>" href="?tab=dashboard">
            <i class="fa-solid fa-house"></i> Dashboard
        </a>
        <a class="menu-item <?php echo ($activeTab ?? '') == 'numbers' ? 'active' : ''; ?>" href="?tab=numbers">
            <i class="fa-solid fa-mobile-screen"></i> Active Numbers
            <span class="badge" id="active-count"><?php echo $activeNumbersCount ?? 0; ?></span>
        </a>
        <a class="menu-item <?php echo ($activeTab ?? '') == 'recharge' ? 'active' : ''; ?>" href="?tab=recharge">
            <i class="fa-solid fa-wallet"></i> Recharge
        </a>
        <a class="menu-item <?php echo ($activeTab ?? '') == 'history' ? 'active' : ''; ?>" href="?tab=history">
            <i class="fa-solid fa-clock-rotate-left"></i> History
        </a>
        <a class="menu-item <?php echo ($activeTab ?? '') == 'profile' ? 'active' : ''; ?>" href="?tab=profile">
            <i class="fa-regular fa-user"></i> Profile
        </a>
        <a class="menu-item" onclick="window.open('<?php echo htmlspecialchars($siteSettings['support_link'] ?? '#'); ?>', '_blank')">
            <i class="fa-regular fa-circle-question"></i> Support
        </a>
        <?php if(isset($isAdmin) && $isAdmin): ?>
            <a class="menu-item" style="background:rgba(36,107,253,0.1); color:var(--accent); margin-top:10px;" href="../admin/index.php">
                <i class="fa-solid fa-user-gear"></i> Go to Admin Panel
            </a>
        <?php endif; ?>
        <a class="menu-item" style="background:rgba(239,68,68,0.1); color:var(--danger); margin-top:10px;" onclick="logout()">
            <i class="fa-solid fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- User Profile -->
    <div class="user-profile" onclick="window.location.href='?tab=profile'">
        <div class="avatar"><?php echo $userInitial ?? 'G'; ?></div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($userName ?? 'Guest'); ?></div>
            <div class="user-email"><?php echo htmlspecialchars($userEmail ?? ''); ?></div>
        </div>
    </div>
</div>

<style>
/* ============================================================
SIDEBAR STYLES
============================================================ */
.sidebar {
    width: var(--sidebar-width, 260px);
    background: var(--bg-sidebar, #ffffff);
    border-right: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    flex-direction: column;
    padding: 20px 0;
    z-index: 100;
    transition: transform 0.3s ease;
    flex-shrink: 0;
    overflow-y: auto;
}

.sidebar .logo-area {
    padding: 0 20px 20px;
    font-size: 22px;
    font-weight: 700;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    margin-bottom: 20px;
}

.sidebar .logo-area .logo-inner {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar .logo-area .logo-circle {
    width: 36px;
    height: 36px;
    background: var(--accent, #246bfd);
    border-radius: 50%;
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 18px;
    overflow: hidden;
    flex-shrink: 0;
}

.sidebar .logo-area .logo-circle img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sidebar .logo-area .site-name {
    font-size: 20px;
    font-weight: 700;
}

.sidebar .logo-area .theme-toggle {
    background: transparent;
    border: none;
    font-size: 20px;
    color: var(--text-muted, #64748b);
    cursor: pointer;
    padding: 5px;
}

.sidebar .balance-box {
    background: linear-gradient(135deg, var(--accent, #246bfd), #bc13fe);
    margin: 0 20px 20px 20px;
    padding: 20px;
    border-radius: 16px;
    color: white;
    box-shadow: 0 10px 20px rgba(36,107,253,0.2);
}

.sidebar .balance-box p {
    font-size: 13px;
    opacity: 0.9;
}

.sidebar .balance-box h2 {
    font-size: 28px;
    font-weight: 700;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 5px;
}

.sidebar .balance-box .add-btn {
    background: rgba(255,255,255,0.2);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    backdrop-filter: blur(5px);
    border: none;
    color: white;
    font-size: 18px;
}

.sidebar .menu {
    flex: 1;
    padding: 0 10px;
    overflow-y: auto;
}

.sidebar .menu .menu-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px 20px;
    color: var(--text-muted, #64748b);
    text-decoration: none;
    border-radius: var(--radius, 12px);
    margin-bottom: 2px;
    font-weight: 500;
    cursor: pointer;
    transition: 0.2s;
    border: none;
    background: transparent;
    width: 100%;
    text-align: left;
    font-size: 14px;
}

.sidebar .menu .menu-item:hover {
    background: var(--hover-bg, rgba(36,107,253,0.06));
    color: var(--accent, #246bfd);
}

.sidebar .menu .menu-item.active {
    background: var(--hover-bg, rgba(36,107,253,0.06));
    color: var(--accent, #246bfd);
}

.sidebar .menu .menu-item i {
    font-size: 18px;
    width: 24px;
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

.sidebar .user-profile {
    padding: 15px 20px;
    border-top: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    flex-shrink: 0;
}

.sidebar .user-profile .avatar {
    width: 40px;
    height: 40px;
    background: var(--hover-bg, rgba(36,107,253,0.06));
    color: var(--accent, #246bfd);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: bold;
    font-size: 18px;
    flex-shrink: 0;
}

.sidebar .user-profile .user-info {
    overflow: hidden;
}

.sidebar .user-profile .user-info .user-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-main, #0f172a);
    text-overflow: ellipsis;
    white-space: nowrap;
    overflow: hidden;
}

.sidebar .user-profile .user-info .user-email {
    font-size: 12px;
    color: var(--text-muted, #64748b);
    text-overflow: ellipsis;
    white-space: nowrap;
    overflow: hidden;
}

/* ============================================================
RESPONSIVE
============================================================ */
@media (max-width: 900px) {
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        transform: translateX(-100%);
        box-shadow: 5px 0 25px rgba(0,0,0,0.2);
        width: 280px;
        z-index: 100;
    }
    .sidebar.open {
        transform: translateX(0);
    }
    .overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.6);
        z-index: 50;
        backdrop-filter: blur(3px);
    }
    .overlay.active {
        display: block;
    }
}
</style>