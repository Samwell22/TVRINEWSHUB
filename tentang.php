<?php
/**
 * HALAMAN TENTANG - SULUT NEWS HUB
 * Premium About Page
 */

require_once 'config/config.php';
$conn = getDBConnection();

$page_title = 'Tentang Kami - SULUT NEWS HUB | TVRI Sulawesi Utara';
$active_menu = 'tentang';

include 'includes/header.php';

// getSetting() is defined in config/config.php

$settings = [
    'contact_address' => getSetting($conn, 'contact_address', 'Manado, Sulawesi Utara'),
    'contact_phone'   => getSetting($conn, 'contact_phone', '+62 431 XXXXXX'),
    'contact_email'   => getSetting($conn, 'contact_email', 'redaksi@tvrisulut.id'),
    'facebook_url'    => getSetting($conn, 'facebook_url', '#'),
    'instagram_url'   => getSetting($conn, 'instagram_url', '#'),
    'youtube_url'     => getSetting($conn, 'youtube_url', '#'),
    'twitter_url'     => getSetting($conn, 'twitter_url', '#')
];

// Stats
$total_news = 0; $total_categories = 0; $total_videos = 0;
$r = mysqli_query($conn, "SELECT COUNT(*) as t FROM news WHERE status='published'");
if ($row = mysqli_fetch_assoc($r)) $total_news = $row['t'];
$r = mysqli_query($conn, "SELECT COUNT(*) as t FROM categories WHERE is_active=1");
if ($row = mysqli_fetch_assoc($r)) $total_categories = $row['t'];
$r = mysqli_query($conn, "SELECT COUNT(*) as t FROM news WHERE status='published' AND video_url IS NOT NULL AND video_url!=''");
if ($row = mysqli_fetch_assoc($r)) $total_videos = $row['t'];

// Categories
$categories = [];
$cat_result = mysqli_query($conn, "SELECT * FROM categories WHERE is_active=1 ORDER BY name ASC");
while ($cat = mysqli_fetch_assoc($cat_result)) $categories[] = $cat;
?>

<!-- About Hero -->
<div class="about-hero">
    <div class="container-fluid">
        <span class="section-label section-label-light">TENTANG</span>
        <h1><i class="fas fa-landmark me-2"></i> SULUT NEWS HUB</h1>
        <p>Portal Berita Digital TVRI Sulawesi Utara — Terpercaya, Akurat, Berimbang</p>
    </div>
</div>

<!-- Stats Bar -->
<div class="container-fluid stats-bar-overlap">
    <div class="row g-3 justify-content-center">
        <div class="col-md-4 col-lg-3">
            <div class="stats-card">
                <div class="stats-number"><?= number_format($total_news) ?></div>
                <div class="stats-label">Total Berita</div>
            </div>
        </div>
        <div class="col-md-4 col-lg-3">
            <div class="stats-card">
                <div class="stats-number"><?= number_format($total_categories) ?></div>
                <div class="stats-label">Kategori</div>
            </div>
        </div>
        <div class="col-md-4 col-lg-3">
            <div class="stats-card">
                <div class="stats-number"><?= number_format($total_videos) ?></div>
                <div class="stats-label">Video Berita</div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid content-wrapper">
    <div class="row g-4">
        <!-- CONTENT COLUMN -->
        <div class="col-lg-8">
            <!-- Visi & Misi -->
            <div class="about-card">
                <h2><i class="fas fa-bullseye me-2"></i> Visi &amp; Misi</h2>
                <h4>Visi</h4>
                <p>Menjadi portal berita digital terdepan di Sulawesi Utara yang menyajikan informasi terpercaya, akurat, dan berimbang untuk meningkatkan literasi masyarakat.</p>
                <h4>Misi</h4>
                <ul>
                    <li>Menyajikan berita dan informasi yang akurat, faktual, dan berimbang</li>
                    <li>Memberikan liputan mendalam tentang peristiwa di Sulawesi Utara</li>
                    <li>Mendukung transparansi dan akuntabilitas publik melalui jurnalisme berkualitas</li>
                    <li>Mengintegrasikan multimedia (teks, foto, video) untuk pengalaman berita yang optimal</li>
                    <li>Menjadi sumber informasi terpercaya bagi masyarakat Sulawesi Utara</li>
                </ul>
            </div>

            <!-- Tentang TVRI SULUT -->
            <div class="about-card">
                <h2><i class="fas fa-building me-2"></i> TVRI Sulawesi Utara</h2>
                <p>TVRI Sulawesi Utara adalah stasiun televisi publik yang berkomitmen untuk menyajikan program dan berita berkualitas kepada masyarakat Sulawesi Utara. Sebagai bagian dari TVRI Nasional, kami berperan penting dalam memberikan informasi yang edukatif, menghibur, dan memberdayakan masyarakat.</p>
                <p>SULUT NEWS HUB adalah platform digital kami yang hadir untuk memperluas jangkauan penyebaran informasi ke masyarakat melalui media online. Portal ini menghadirkan berita terkini, video liputan, dan konten multimedia lainnya yang dapat diakses kapan saja dan di mana saja.</p>
            </div>

            <!-- Kategori Berita -->
            <div class="about-card">
                <h2><i class="fas fa-th-large me-2"></i> Kategori Berita</h2>
                <div class="category-grid">
                    <?php foreach ($categories as $cat): ?>
                    <a href="<?= SITE_URL ?>kategori.php?slug=<?= $cat['slug'] ?>" class="category-grid-item" style="--cat-color: <?= $cat['color'] ?>;">
                        <i class="<?= $cat['icon'] ?>"></i>
                        <strong><?= htmlspecialchars($cat['name']) ?></strong>
                        <?php if (!empty($cat['description'])): ?>
                        <small><?= htmlspecialchars($cat['description']) ?></small>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Kontak Redaksi -->
            <div class="about-card">
                <h2><i class="fas fa-envelope me-2"></i> Kontak Redaksi</h2>
                <p>Punya informasi menarik atau ingin menyampaikan kritik dan saran? Hubungi kami:</p>
                <div class="contact-info-box">
                    <p><i class="fas fa-map-marker-alt"></i> <strong>Alamat:</strong> <?= htmlspecialchars($settings['contact_address']) ?></p>
                    <p><i class="fas fa-phone"></i> <strong>Telepon:</strong> <?= htmlspecialchars($settings['contact_phone']) ?></p>
                    <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($settings['contact_email']) ?>"><?= htmlspecialchars($settings['contact_email']) ?></a></p>
                    <p class="mb-0"><i class="fas fa-clock"></i> <strong>Jam Kerja:</strong> Senin - Jumat, 08:00 - 17:00 WITA</p>
                </div>
            </div>
        </div>

        <!-- SIDEBAR -->
        <div class="col-lg-4">
            <!-- Social Media -->
            <div class="social-card">
                <h3><i class="fas fa-share-alt me-2"></i> Ikuti Kami</h3>
                <p>Dapatkan update berita terbaru melalui media sosial kami:</p>
                <div class="d-grid gap-2">
                    <a href="<?= htmlspecialchars($settings['facebook_url']) ?>" target="_blank" class="social-btn social-btn-facebook">
                        <i class="fab fa-facebook-f"></i> Facebook
                    </a>
                    <a href="<?= htmlspecialchars($settings['instagram_url']) ?>" target="_blank" class="social-btn social-btn-instagram">
                        <i class="fab fa-instagram"></i> Instagram
                    </a>
                    <a href="<?= htmlspecialchars($settings['youtube_url']) ?>" target="_blank" class="social-btn social-btn-youtube">
                        <i class="fab fa-youtube"></i> YouTube
                    </a>
                    <a href="<?= htmlspecialchars($settings['twitter_url']) ?>" target="_blank" class="social-btn social-btn-twitter">
                        <i class="fab fa-twitter"></i> Twitter / X
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="widget-card mt-4">
                <div class="widget-card-header">
                    <i class="fas fa-link"></i>
                    <h4>Tautan Cepat</h4>
                </div>
                <div class="widget-card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><a href="<?= SITE_URL ?>" class="text-decoration-none"><i class="fas fa-home me-2 text-muted"></i> Beranda</a></li>
                        <li class="mb-2"><a href="<?= SITE_URL ?>berita-nasional.php" class="text-decoration-none"><i class="fas fa-newspaper me-2 text-muted"></i> Semua Berita</a></li>
                        <li class="mb-2"><a href="<?= SITE_URL ?>video.php" class="text-decoration-none"><i class="fas fa-video me-2 text-muted"></i> Video</a></li>
                        <li class="mb-2"><a href="<?= SITE_URL ?>cuaca.php" class="text-decoration-none"><i class="fas fa-cloud-sun me-2 text-muted"></i> Cuaca</a></li>
                        <li><a href="<?= SITE_URL ?>berita-nasional.php" class="text-decoration-none"><i class="fas fa-globe me-2 text-muted"></i> Berita Nasional</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
