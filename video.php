<?php
/**
 * HALAMAN VIDEO - SULUT NEWS HUB
 * Premium Video Gallery
 */

require_once 'config/config.php';
$conn = getDBConnection();

$page_title = 'Video Berita - SULUT NEWS HUB | TVRI Sulawesi Utara';
$active_menu = 'video';

include 'includes/header.php';

$news_per_page = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $news_per_page;

$stmt = mysqli_prepare($conn, "SELECT n.*, c.name as category_name, c.slug as category_slug, c.color as category_color, u.full_name as author_name 
    FROM news n
    INNER JOIN categories c ON n.category_id = c.id
    INNER JOIN users u ON n.author_id = u.id
    WHERE n.status = 'published' AND c.is_active = 1 AND n.video_url IS NOT NULL AND n.video_url != ''
    ORDER BY n.published_at DESC LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($stmt, 'ii', $news_per_page, $offset);
mysqli_stmt_execute($stmt);
$video_result = mysqli_stmt_get_result($stmt);

$count_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total
    FROM news n
    INNER JOIN categories c ON n.category_id = c.id
    WHERE n.status='published' AND c.is_active = 1 AND n.video_url IS NOT NULL AND n.video_url!=''"));
$total_videos = $count_row['total'];
$total_pages = ceil($total_videos / $news_per_page);
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container-fluid">
        <span class="section-label section-label-light">MULTIMEDIA</span>
        <h1><i class="fas fa-play-circle me-2"></i> Video Berita</h1>
        <p>Tonton liputan video berita terkini dari TVRI Sulawesi Utara &mdash; <strong><?= $total_videos ?> video</strong></p>
    </div>
</div>

<div class="container-fluid content-wrapper">
    <?php if (mysqli_num_rows($video_result) > 0): ?>
    <div class="row g-3">
        <?php while ($video = mysqli_fetch_assoc($video_result)): ?>
        <div class="col-lg-4 col-md-6">
            <a href="<?= SITE_URL ?>berita.php?slug=<?= $video['slug'] ?>" class="news-card">
                <div class="news-thumbnail">
                    <?php if (!empty($video['thumbnail'])): ?>
                    <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="<?= htmlspecialchars($video['title']) ?>" loading="lazy">
                    <?php else: ?>
                    <div class="video-placeholder"><i class="fas fa-video"></i></div>
                    <?php endif; ?>
                    <div class="play-overlay"><i class="fas fa-play"></i></div>
                    <span class="news-category-badge" style="background: <?= $video['category_color'] ?>;"><?= htmlspecialchars($video['category_name']) ?></span>
                </div>
                <div class="news-body">
                    <h3 class="news-title"><?= htmlspecialchars($video['title']) ?></h3>
                    <?php if (!empty($video['excerpt'])): ?>
                    <p class="news-excerpt"><?= htmlspecialchars(substr($video['excerpt'], 0, 100)) ?>...</p>
                    <?php endif; ?>
                    <div class="news-meta">
                        <span><i class="far fa-calendar"></i> <?= formatTanggalIndonesia($video['published_at']) ?></span>
                        <span><i class="far fa-eye"></i> <?= formatViews($video['views']) ?></span>
                    </div>
                </div>
            </a>
        </div>
        <?php endwhile; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <?php renderPagination($page, $total_pages); ?>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-video"></i>
        <h4>Belum Ada Video</h4>
        <p>Video berita akan segera ditambahkan.</p>
        <a href="<?= SITE_URL ?>" class="btn btn-primary mt-3"><i class="fas fa-home me-1"></i> Kembali ke Beranda</a>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
