<?php
/**
 * Halaman Detail Berita
 */

ob_start();
require_once 'config/config.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug)) {
    header('Location: ' . SITE_URL);
    exit;
}

$conn = getDBConnection();

// Ambil berita berdasarkan slug
$stmt = mysqli_prepare($conn, "
    SELECT n.*, 
           c.name AS category_name, c.slug AS category_slug, c.color AS category_color, c.icon AS category_icon,
           u.full_name AS author_name
    FROM news n
    JOIN categories c ON n.category_id = c.id
    JOIN users u ON n.author_id = u.id
    WHERE n.slug = ? AND n.status = 'published'
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, 's', $slug);
mysqli_stmt_execute($stmt);
$news = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$news) {
    header('Location: ' . SITE_URL);
    exit;
}

// Update views counter
$update_stmt = mysqli_prepare($conn, "UPDATE news SET views = views + 1 WHERE id = ?");
mysqli_stmt_bind_param($update_stmt, 'i', $news['id']);
mysqli_stmt_execute($update_stmt);
$news['views'] = $news['views'] + 1;

// Set page title dan meta
$page_title = htmlspecialchars($news['title']) . ' - SULUT NEWS HUB';
$active_menu = 'home';

// Ambil berita terkait (kategori sama, kecuali berita ini)
$related_stmt = mysqli_prepare($conn, "
    SELECT n.*, c.name AS category_name, c.slug AS category_slug, c.color AS category_color,
           u.full_name AS author_name
    FROM news n
    JOIN categories c ON n.category_id = c.id
    JOIN users u ON n.author_id = u.id
    WHERE n.category_id = ? AND n.id != ? AND n.status = 'published'
    ORDER BY n.published_at DESC
    LIMIT 4
");
mysqli_stmt_bind_param($related_stmt, 'ii', $news['category_id'], $news['id']);
mysqli_stmt_execute($related_stmt);
$related_result = mysqli_stmt_get_result($related_stmt);

// Ambil berita populer (sidebar)
$popular_query = "SELECT n.*, c.name AS category_name, c.color AS category_color
                  FROM news n
                  JOIN categories c ON n.category_id = c.id
                  WHERE n.status = 'published' AND c.is_active = 1 AND n.id != ?
                  ORDER BY n.views DESC
                  LIMIT 5";
$pop_stmt = mysqli_prepare($conn, $popular_query);
mysqli_stmt_bind_param($pop_stmt, 'i', $news['id']);
mysqli_stmt_execute($pop_stmt);
$popular_result = mysqli_stmt_get_result($pop_stmt);

// URL untuk sharing
$article_url = SITE_URL . 'berita.php?slug=' . urlencode($news['slug']);
$article_title = $news['title'];

// Thumbnail
$thumbnail_url = !empty($news['thumbnail']) ? SITE_URL . $news['thumbnail'] : SITE_URL . 'assets/images/default-news.jpg';

// Video detection
$has_video = !empty($news['video_url']);
$is_youtube = false;
$youtube_id = '';
if ($has_video) {
    if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $news['video_url'], $matches)) {
        $is_youtube = true;
        $youtube_id = $matches[1];
    }
}

include 'includes/header.php';
?>

<!-- Page Header / Breadcrumb -->
<div class="page-header" style="padding: 18px 0;">
    <div class="container-fluid">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="background:transparent;">
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>" style="color:rgba(255,255,255,.8);"><i class="fas fa-home"></i> Beranda</a></li>
                <li class="breadcrumb-item"><a href="<?= SITE_URL ?>kategori.php?slug=<?= urlencode($news['category_slug']) ?>" style="color:rgba(255,255,255,.8);"><?= htmlspecialchars($news['category_name']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page" style="color:rgba(255,255,255,.5);"><?= htmlspecialchars(mb_substr($news['title'], 0, 50)) ?><?= mb_strlen($news['title']) > 50 ? '...' : '' ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="container-fluid content-wrapper">
    <div class="row g-4">
        <!-- Main Content -->
        <div class="col-lg-8">
            <article class="article-detail">
                <!-- Article Header -->
                <div class="article-header">
                    <a href="<?= SITE_URL ?>kategori.php?slug=<?= urlencode($news['category_slug']) ?>" 
                       class="article-category" 
                       style="background: <?= htmlspecialchars($news['category_color']) ?>">
                        <?php if (!empty($news['category_icon'])): ?>
                            <i class="<?= htmlspecialchars($news['category_icon']) ?>"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($news['category_name']) ?>
                    </a>
                    
                    <h1 class="article-title"><?= htmlspecialchars($news['title']) ?></h1>
                    
                    <?php if (!empty($news['excerpt'])): ?>
                    <p class="article-subtitle"><?= htmlspecialchars($news['excerpt']) ?></p>
                    <?php endif; ?>
                    
                    <!-- Meta Bar -->
                    <div class="article-meta">
                        <div class="article-meta-item author-info">
                            <div class="author-avatar">
                                <i class="fas fa-user-circle" style="font-size: 35px; color: #ccc;"></i>
                            </div>
                            <div>
                                <strong><?= htmlspecialchars($news['author_name']) ?></strong>
                            </div>
                        </div>
                        <div class="article-meta-item">
                            <i class="far fa-calendar-alt"></i>
                            <span><?= formatTanggalIndonesia($news['published_at']) ?></span>
                        </div>
                        <div class="article-meta-item">
                            <i class="far fa-clock"></i>
                            <span><?= timeAgo($news['published_at']) ?></span>
                        </div>
                        <div class="article-meta-item">
                            <i class="far fa-eye"></i>
                            <span><?= formatViews($news['views']) ?> views</span>
                        </div>
                    </div>
                </div>

                <!-- Video Player or Thumbnail -->
                <?php if ($has_video): ?>
                <div class="video-container">
                    <?php if ($is_youtube): ?>
                        <iframe class="video-player" 
                                src="https://www.youtube.com/embed/<?= htmlspecialchars($youtube_id) ?>?rel=0" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen></iframe>
                    <?php else: ?>
                        <video class="video-player" controls preload="metadata" poster="<?= htmlspecialchars($thumbnail_url) ?>">
                            <source src="<?= htmlspecialchars($news['video_url']) ?>" type="video/mp4">
                            Browser Anda tidak mendukung video.
                        </video>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="article-thumbnail">
                    <img src="<?= htmlspecialchars($thumbnail_url) ?>" alt="<?= htmlspecialchars($news['title']) ?>">
                </div>
                <?php endif; ?>

                <!-- Article Content -->
                <div class="article-content">
                    <?= $news['content'] ?>
                </div>

                <!-- Tags -->
                <?php if (!empty($news['tags'])): ?>
                <div class="article-tags">
                    <?php 
                    $tags = array_map('trim', explode(',', $news['tags']));
                    foreach ($tags as $tag): 
                        if (empty($tag)) continue;
                    ?>
                    <a href="<?= SITE_URL ?>cari.php?q=<?= urlencode($tag) ?>" class="tag-item">
                        <i class="fas fa-tag"></i> <?= htmlspecialchars($tag) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Share Section -->
                <div class="share-section">
                    <span class="share-title"><i class="fas fa-share-alt"></i> Bagikan Berita:</span>
                    <div class="share-buttons">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($article_url) ?>" 
                           target="_blank" rel="noopener" class="share-btn share-btn-facebook">
                            <i class="fab fa-facebook-f"></i> Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?= urlencode($article_url) ?>&text=<?= urlencode($article_title) ?>" 
                           target="_blank" rel="noopener" class="share-btn share-btn-twitter">
                            <i class="fab fa-twitter"></i> Twitter
                        </a>
                        <a href="https://wa.me/?text=<?= urlencode($article_title . ' ' . $article_url) ?>" 
                           target="_blank" rel="noopener" class="share-btn share-btn-whatsapp">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                        <a href="https://t.me/share/url?url=<?= urlencode($article_url) ?>&text=<?= urlencode($article_title) ?>" 
                           target="_blank" rel="noopener" class="share-btn share-btn-telegram">
                            <i class="fab fa-telegram-plane"></i> Telegram
                        </a>
                        <button onclick="window.print()" class="share-btn share-btn-print">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </article>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Related News -->
            <?php if (mysqli_num_rows($related_result) > 0): ?>
            <div class="related-news">
                <h3 class="related-title"><i class="fas fa-newspaper me-2"></i> Berita Terkait</h3>
                <?php while ($rel = mysqli_fetch_assoc($related_result)): 
                    $rel_thumb = !empty($rel['thumbnail']) ? SITE_URL . $rel['thumbnail'] : SITE_URL . 'assets/images/default-news.jpg';
                ?>
                <a href="<?= SITE_URL ?>berita.php?slug=<?= urlencode($rel['slug']) ?>" class="related-card">
                    <div class="related-thumbnail">
                        <img src="<?= htmlspecialchars($rel_thumb) ?>" alt="<?= htmlspecialchars($rel['title']) ?>" loading="lazy">
                        <span class="related-category" style="background: <?= $rel['category_color'] ?>">
                            <?= htmlspecialchars($rel['category_name']) ?>
                        </span>
                    </div>
                    <div class="related-content">
                        <h4 class="related-title-text"><?= htmlspecialchars($rel['title']) ?></h4>
                        <span class="related-date">
                            <i class="far fa-calendar"></i> <?= formatTanggalIndonesia($rel['published_at']) ?>
                        </span>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>

            <!-- Popular News Widget -->
            <div class="widget-card mt-3">
                <div class="widget-card-header">
                    <i class="fas fa-fire"></i>
                    <h4>Berita Populer</h4>
                </div>
                <div class="widget-card-body" style="padding: 8px 16px;">
                    <?php $rank = 1; while ($pop = mysqli_fetch_assoc($popular_result)): ?>
                    <div class="news-list-item">
                        <span class="list-number"><?= str_pad($rank, 2, '0', STR_PAD_LEFT) ?></span>
                        <div class="list-content">
                            <h5><a href="<?= SITE_URL ?>berita.php?slug=<?= urlencode($pop['slug']) ?>"><?= htmlspecialchars($pop['title']) ?></a></h5>
                            <div class="list-meta">
                                <i class="far fa-eye"></i> <?= formatViews($pop['views']) ?> &middot;
                                <i class="far fa-clock"></i> <?= timeAgo($pop['published_at']) ?>
                            </div>
                        </div>
                    </div>
                    <?php $rank++; endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
