<?php
/**
 * Header Template - Sulut News Hub
 * Digunakan di semua halaman public
 */

// Error display (disabled untuk production)
// if (!defined('ERROR_DISPLAY_ENABLED')) {
//     error_reporting(E_ALL);
//     ini_set('display_errors', 1);
// }

// Cek apakah config sudah di-include dan koneksi sudah ada
if (!isset($conn)) {
    // Jika belum, include config dan buat koneksi
    require_once __DIR__ . '/../config/config.php';
    $conn = getDBConnection();
}

// Ambil running text via memoized getSetting
$running_text = getSetting($conn, 'running_text', 'Selamat datang di SULUT NEWS HUB - Portal berita terpercaya Sulawesi Utara');

// Ambil kategori untuk menu (shared with footer via global)
$categories_query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC";
$categories_result = mysqli_query($conn, $categories_query);
$_shared_categories = [];
if ($categories_result) {
    while ($cat_row = mysqli_fetch_assoc($categories_result)) {
        $_shared_categories[] = $cat_row;
    }
    mysqli_data_seek($categories_result, 0); // reset for header iteration
}

// Ambil social media links & contact info in ONE query (shared with footer via global)
$_shared_social_links = [];
$_shared_contact_info = [];
$_settings_query = "SELECT setting_key, setting_value FROM settings 
                    WHERE setting_key IN ('facebook_url', 'twitter_url', 'instagram_url', 'youtube_url',
                                          'contact_email', 'contact_phone', 'contact_address')";
$_settings_result = mysqli_query($conn, $_settings_query);
if ($_settings_result) {
    while ($_row = mysqli_fetch_assoc($_settings_result)) {
        $k = $_row['setting_key'];
        $v = $_row['setting_value'];
        if (strpos($k, '_url') !== false) {
            $_shared_social_links[$k] = $v;
        } else {
            $_shared_contact_info[$k] = $v;
        }
    }
}

// Set page title default jika tidak ada
if (!isset($page_title)) {
    $page_title = 'SULUT NEWS HUB - TVRI Sulawesi Utara';
}

// Set active menu
if (!isset($active_menu)) {
    $active_menu = 'home';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- SEO Meta Tags -->
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="Portal berita terpercaya Sulawesi Utara dari TVRI. Berita terkini seputar politik, ekonomi, budaya, dan kehidupan masyarakat Sulut.">
    <meta name="keywords" content="berita sulut, tvri sulawesi utara, berita manado, portal berita, sulawesi utara news">
    <meta name="author" content="TVRI Sulawesi Utara">
    
    <!-- Open Graph Meta Tags (untuk social media sharing) -->
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="og:description" content="Portal berita terpercaya Sulawesi Utara">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>">
    
    <!-- Preconnect to CDN origins for faster loading -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Bootstrap 5 CSS (via CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons (via CDN) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Playfair Display, Inter, Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;800&family=Inter:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css?v=<?php echo ASSET_VERSION; ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>assets/images/TVRI_Sulawesi_Utara.png">
</head>
<body>

<!-- TOP BAR - Running Text & Social Media -->
<div class="top-bar">
    <div class="container-fluid">
        <div class="row align-items-center">
            <!-- Running Text -->
            <div class="col-md-7 col-lg-8">
                <div class="running-text">
                    <span class="badge-live">LIVE</span>
                    <div class="running-text-marquee" aria-label="Running text">
                        <span class="running-text-track"><?php echo htmlspecialchars($running_text); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Date & Social Media Icons -->
            <div class="col-md-5 col-lg-4">
                <div class="top-bar-right">
                    <span class="top-bar-date d-none d-md-inline-flex">
                        <i class="far fa-calendar-alt"></i> <?php echo date('l, d F Y'); ?>
                    </span>
                    <div class="social-icons">
                    <?php $social_links = $_shared_social_links; ?>
                    
                    <?php if (!empty($social_links['facebook_url'])): ?>
                        <a href="<?php echo htmlspecialchars($social_links['facebook_url']); ?>" target="_blank" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($social_links['twitter_url'])): ?>
                        <a href="<?php echo htmlspecialchars($social_links['twitter_url']); ?>" target="_blank" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($social_links['instagram_url'])): ?>
                        <a href="<?php echo htmlspecialchars($social_links['instagram_url']); ?>" target="_blank" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($social_links['youtube_url'])): ?>
                        <a href="<?php echo htmlspecialchars($social_links['youtube_url']); ?>" target="_blank" title="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    <?php endif; ?>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MAIN HEADER - Logo & Navigation -->
<header class="main-header">
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <!-- Logo & Brand -->
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <div class="brand-wrapper">
                    <div class="brand-logo">
                        <img src="<?php echo SITE_URL; ?>assets/images/TVRI_Sulawesi_Utara.png" 
                             alt="TVRI Sulawesi Utara">
                    </div>
                    <div class="brand-text">
                        <span class="brand-name">SULUT NEWS HUB</span>
                        <span class="brand-tagline">TVRI Sulawesi Utara</span>
                    </div>
                </div>
            </a>
            
            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Menu -->
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <!-- Home -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($active_menu == 'home') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>">
                            <i class="fas fa-home"></i> Beranda
                        </a>
                    </li>
                    
                    <!-- Berita (Consolidated Content Hub) -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= ($active_menu === 'berita-nasional' || $active_menu === 'video' || $active_menu === 'kategori') ? 'active' : '' ?>" 
                           href="<?php echo SITE_URL; ?>berita-nasional.php" 
                           id="beritaDropdown" 
                           role="button" 
                           data-bs-toggle="dropdown"
                           aria-expanded="false">
                            <i class="fas fa-newspaper"></i> Berita
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="beritaDropdown">
                            <!-- Primary Actions -->
                            <li>
                                <a class="dropdown-item <?= $active_menu === 'berita-nasional' ? 'active' : '' ?>" href="<?php echo SITE_URL; ?>berita-nasional.php">
                                    <i class="fas fa-globe"></i> <strong>Berita Terkini</strong>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item <?= $active_menu === 'video' ? 'active' : '' ?>" href="<?php echo SITE_URL; ?>video.php">
                                    <i class="fas fa-video"></i> <strong>Video Berita</strong>
                                </a>
                            </li>
                            
                            <!-- Divider -->
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header"><i class="fas fa-folder-open"></i> Kategori Berita</h6></li>
                            
                            <!-- Categories -->
                            <?php 
                            // Reset pointer result set
                            if ($categories_result && mysqli_num_rows($categories_result) > 0) {
                                mysqli_data_seek($categories_result, 0);
                                while ($menu_category = mysqli_fetch_assoc($categories_result)): 
                            ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>kategori.php?slug=<?php echo $menu_category['slug']; ?>">
                                        <i class="<?php echo $menu_category['icon']; ?>" style="width: 20px;"></i>
                                        <?php echo htmlspecialchars($menu_category['name']); ?>
                                    </a>
                                </li>
                            <?php 
                                endwhile;
                            } else {
                                echo '<li><span class="dropdown-item text-muted">Belum ada kategori</span></li>';
                            }
                            ?>
                        </ul>
                    </li>
                    
                    <!-- Cuaca -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($active_menu == 'cuaca') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>cuaca.php">
                            <i class="fas fa-cloud-sun-rain"></i> Cuaca
                        </a>
                    </li>
                    
                    <!-- Tentang Kami -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($active_menu == 'tentang') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>tentang.php">
                            <i class="fas fa-info-circle"></i> Tentang
                        </a>
                    </li>
                    
                    <!-- Search Icon -->
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#searchModal">
                            <i class="fas fa-search"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<!-- SEARCH MODAL -->
<div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="searchModalLabel">
                    <i class="fas fa-search"></i> Cari Berita
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="<?php echo SITE_URL; ?>cari.php" method="GET">
                    <div class="input-group input-group-lg">
                        <input type="text" class="form-control" name="q" placeholder="Ketik kata kunci berita..." required>
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> Cari
                        </button>
                    </div>
                </form>
                <div class="search-tips mt-3">
                    <small class="text-muted">
                        <i class="fas fa-lightbulb"></i> 
                        Tips: Coba kata kunci seperti "pemilu", "ekonomi", "budaya"
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MAIN CONTENT START -->
<main class="main-content">
