<?php
/**
 * HALAMAN KATEGORI - SULUT NEWS HUB
 */

ob_start();
require_once 'config/config.php';

$category_slug = isset($_GET['slug']) ? $_GET['slug'] : '';
if (empty($category_slug)) { header('Location: ' . SITE_URL); exit; }

$conn = getDBConnection();

$stmt = mysqli_prepare($conn, "SELECT * FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $category_slug);
mysqli_stmt_execute($stmt);
$category = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$category || !isset($category['name'])) { header('Location: ' . SITE_URL); exit; }

$page_title = 'Kategori: ' . $category['name'] . ' - SULUT NEWS HUB';
$active_menu = 'home';
include 'includes/header.php';

$news_per_page = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $news_per_page;

$news_stmt = mysqli_prepare($conn, "SELECT n.*, c.name as category_name, c.slug as category_slug, c.color as category_color, u.full_name as author_name
    FROM news n JOIN categories c ON n.category_id = c.id JOIN users u ON n.author_id = u.id
    WHERE n.category_id = ? AND n.status = 'published' ORDER BY n.published_at DESC LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($news_stmt, 'iii', $category['id'], $news_per_page, $offset);
mysqli_stmt_execute($news_stmt);
$news_result = mysqli_stmt_get_result($news_stmt);

$count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM news WHERE category_id = ? AND status='published'");
mysqli_stmt_bind_param($count_stmt, 'i', $category['id']);
mysqli_stmt_execute($count_stmt);
$total_news = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
$total_pages = ceil($total_news / $news_per_page);

$cat_color = $category['color'] ?? 'var(--primary)';
?>

<!-- Category Header -->
<div class="page-header" style="background: <?= $cat_color ?>;">
    <div class="container-fluid">
        <span class="section-label section-label-muted"><i class="fas fa-tag me-1"></i> KATEGORI</span>
        <h1><i class="<?= $category['icon'] ?? 'fas fa-newspaper' ?> me-2"></i> <?= htmlspecialchars($category['name']) ?></h1>
        <?php if (!empty($category['description'])): ?>
        <p><?= htmlspecialchars($category['description']) ?></p>
        <?php endif; ?>
        <p class="page-header-subtitle"><?= $total_news ?> berita tersedia</p>
    </div>
</div>

<div class="container-fluid content-wrapper">
    <div class="row g-3">
        <?php if (mysqli_num_rows($news_result) > 0): ?>
            <?php while ($news = mysqli_fetch_assoc($news_result)): 
                $thumbnail_url = !empty($news['thumbnail']) ? SITE_URL . $news['thumbnail'] : SITE_URL . 'assets/images/default-news.jpg';
                $has_video = !empty($news['video_url']);
            ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <article class="news-card">
                    <a href="<?= SITE_URL ?>berita.php?slug=<?= $news['slug'] ?>" class="news-thumbnail">
                        <img src="<?= htmlspecialchars($thumbnail_url) ?>" alt="<?= htmlspecialchars($news['title']) ?>" loading="lazy">
                        <span class="news-category-badge" style="background: <?= $news['category_color'] ?>"><?= htmlspecialchars($news['category_name']) ?></span>
                        <?php if ($has_video): ?><div class="play-overlay"><i class="fas fa-play"></i></div><?php endif; ?>
                    </a>
                    <div class="news-body">
                        <h3 class="news-title"><a href="<?= SITE_URL ?>berita.php?slug=<?= $news['slug'] ?>"><?= htmlspecialchars($news['title']) ?></a></h3>
                        <?php if (!empty($news['excerpt'])): ?>
                        <p class="news-excerpt"><?= htmlspecialchars($news['excerpt']) ?></p>
                        <?php endif; ?>
                        <div class="news-meta">
                            <span><i class="far fa-calendar"></i> <?= (new DateTime($news['published_at']))->format('d M Y') ?></span>
                            <span><i class="far fa-eye"></i> <?= formatViews($news['views']) ?></span>
                        </div>
                    </div>
                </article>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>Belum Ada Berita</h4>
                    <p>Belum ada berita di kategori ini.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <?php renderPagination($page, $total_pages, ['slug' => $category_slug]); ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>