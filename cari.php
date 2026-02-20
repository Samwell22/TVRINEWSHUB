<?php
/**
 * HALAMAN PENCARIAN - SULUT NEWS HUB
 */

require_once 'config/config.php';

$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$page_title = !empty($search_query) 
    ? 'Pencarian: ' . $search_query . ' - SULUT NEWS HUB' 
    : 'Pencarian Berita - SULUT NEWS HUB';
$active_menu = 'home';

$conn = getDBConnection();
include 'includes/header.php';

if (empty($search_query)) {
?>
<div class="container-fluid">
    <div class="empty-state empty-state-lg">
        <i class="fas fa-search"></i>
        <h4>Cari Berita</h4>
        <p>Masukkan kata kunci untuk mencari berita</p>
        <form action="cari.php" method="GET" class="search-form-inline">
            <div class="input-group input-group-lg">
                <input type="text" class="form-control" name="q" placeholder="Ketik kata kunci..." required>
                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>
</div>
<?php
    include 'includes/footer.php';
    exit;
}

$news_per_page = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $news_per_page;
$safe_query = '%' . $search_query . '%';

$stmt = mysqli_prepare($conn, "SELECT n.*, c.name as category_name, c.slug as category_slug, c.color as category_color, u.full_name as author_name
    FROM news n JOIN categories c ON n.category_id = c.id JOIN users u ON n.author_id = u.id
    WHERE n.status = 'published' AND (n.title LIKE ? OR n.content LIKE ? OR n.excerpt LIKE ? OR n.tags LIKE ?)
    ORDER BY n.published_at DESC LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($stmt, 'ssssii', $safe_query, $safe_query, $safe_query, $safe_query, $news_per_page, $offset);
mysqli_stmt_execute($stmt);
$news_result = mysqli_stmt_get_result($stmt);

$count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM news WHERE status='published' AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ? OR tags LIKE ?)");
mysqli_stmt_bind_param($count_stmt, 'ssss', $safe_query, $safe_query, $safe_query, $safe_query);
mysqli_stmt_execute($count_stmt);
$total_results = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
$total_pages = ceil($total_results / $news_per_page);
?>

<!-- Search Header -->
<div class="page-header">
    <div class="container-fluid">
        <h1><i class="fas fa-search me-2"></i> Hasil Pencarian</h1>
        <p>Ditemukan <strong><?= $total_results ?></strong> berita untuk &ldquo;<?= htmlspecialchars($search_query) ?>&rdquo;</p>
        <form action="cari.php" method="GET" class="search-form-inline" style="margin: 16px 0 0;">
            <div class="input-group">
                <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($search_query) ?>" placeholder="Cari berita lain...">
                <button class="btn btn-light" type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
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
                    <i class="fas fa-exclamation-triangle"></i>
                    <h4>Tidak Ada Hasil</h4>
                    <p>Tidak ditemukan berita untuk &ldquo;<?= htmlspecialchars($search_query) ?>&rdquo;. Coba kata kunci lain.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <?php renderPagination($page, $total_pages, ['q' => $search_query]); ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>