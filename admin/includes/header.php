<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Admin Panel'; ?> - SULUT NEWS HUB</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Admin CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/admin.css">
</head>
<body>
    <!-- SIDEBAR OVERLAY (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="adminSidebar">
        <!-- Brand -->
        <div class="sidebar-brand">
            <img src="<?php echo SITE_URL; ?>assets/images/TVRI_Sulawesi_Utara.png" 
                 alt="TVRI Sulawesi Utara" 
                 class="sidebar-brand-logo">
            <div class="sidebar-brand-text">
                <h4>SULUT NEWS HUB</h4>
                <span>Admin Panel</span>
            </div>
        </div>
        
        <!-- Navigation -->
        <nav class="sidebar-nav">
            <p class="nav-section-title">Menu Utama</p>
            
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <p class="nav-section-title">Konten</p>
            
            <div class="nav-item">
                <a href="berita-list.php" class="nav-link <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'berita') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-newspaper"></i>
                    <span>Kelola Berita</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="kategori-list.php" class="nav-link <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'kategori') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-folder-open"></i>
                    <span>Kategori</span>
                </a>
            </div>
            
            <p class="nav-section-title">Broadcast</p>
            
            <div class="nav-item">
                <a href="broadcast-schedule.php" class="nav-link <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'broadcast') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-broadcast-tower"></i>
                    <span>Live Monitoring</span>
                </a>
            </div>
            
            <p class="nav-section-title">Intelijen</p>
            
            <div class="nav-item">
                <a href="news-intelligence.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'news-intelligence.php') ? 'active' : ''; ?>">
                    <i class="fas fa-satellite-dish"></i>
                    <span>Intelijen Berita</span>
                </a>
            </div>
            
            <?php if ($logged_in_user['role'] === 'admin'): ?>
            <p class="nav-section-title">Administrasi</p>
            
            <div class="nav-item">
                <a href="user-list.php" class="nav-link <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'user') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Kelola User</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="settings.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Pengaturan</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="api-settings.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'api-settings.php') ? 'active' : ''; ?>">
                    <i class="fas fa-plug"></i>
                    <span>API & Widget</span>
                </a>
            </div>
            <?php endif; ?>
        </nav>
        
        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <div class="nav-item">
                <a href="<?php echo SITE_URL; ?>" target="_blank" class="nav-link">
                    <i class="fas fa-external-link-alt"></i>
                    <span>Lihat Website</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="logout.php" class="nav-link logout-link" onclick="return confirm('Yakin ingin logout?')">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </aside>
    
    <!-- MAIN CONTENT -->
    <main class="admin-main">
        <!-- TOP NAVBAR -->
        <header class="admin-topbar">
            <div class="topbar-left">
                <button class="mobile-toggle" id="mobileToggle" type="button">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="topbar-greeting">
                    <h2><?php echo isset($page_heading) ? $page_heading : 'Dashboard'; ?></h2>
                    <?php if (isset($breadcrumbs)): ?>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <?php foreach ($breadcrumbs as $label => $url): ?>
                                    <?php if ($url): ?>
                                        <li class="breadcrumb-item"><a href="<?php echo $url; ?>"><?php echo $label; ?></a></li>
                                    <?php else: ?>
                                        <li class="breadcrumb-item active"><?php echo $label; ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="topbar-right">
                <div class="topbar-date d-none d-md-flex">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('d M Y'); ?>
                </div>
                
                <div class="topbar-user">
                    <div class="topbar-avatar">
                        <?php echo strtoupper(substr($logged_in_user['full_name'], 0, 1)); ?>
                    </div>
                    <div class="topbar-user-info d-none d-sm-flex">
                        <strong><?php echo htmlspecialchars($logged_in_user['full_name']); ?></strong>
                        <small><?php echo htmlspecialchars($logged_in_user['role']); ?></small>
                    </div>
                </div>
                
                <a href="logout.php" class="topbar-logout" title="Logout" onclick="return confirm('Yakin ingin logout?')">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </header>
        
        <!-- CONTENT AREA -->
        <div class="admin-content">
            <?php
            // Tampilkan flash message jika ada
            $flash = getFlashMessage();
            if ($flash):
            ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                    <?php echo htmlspecialchars($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
