<?php
/**
 * Homepage
 */

$page_title = 'Beranda - SULUT NEWS HUB | TVRI Sulawesi Utara';
$active_menu = 'home';

require_once 'config/config.php';
$conn = getDBConnection();
include 'includes/header.php';

// AMBIL BERITA FEATURED (untuk hero)
$featured_query = "SELECT n.*, c.name as category_name, c.slug as category_slug, c.color as category_color,
                   u.full_name as author_name
                   FROM news n
                   JOIN categories c ON n.category_id = c.id
                   JOIN users u ON n.author_id = u.id
                  WHERE n.status = 'published' AND c.is_active = 1 AND n.is_featured = 1
                   ORDER BY n.published_at DESC
                   LIMIT 5";
$featured_result = mysqli_query($conn, $featured_query);
$featured_items = [];
while ($f = mysqli_fetch_assoc($featured_result)) { $featured_items[] = $f; }

// AMBIL BERITA VIDEO (terpisah)
$video_query = "SELECT n.*, c.name as category_name, c.color as category_color
                FROM news n
                JOIN categories c ON n.category_id = c.id
                WHERE n.status = 'published' AND c.is_active = 1 AND n.video_url IS NOT NULL AND n.video_url != ''
                ORDER BY n.published_at DESC
                LIMIT 8";
$video_result = mysqli_query($conn, $video_query);

// (Berita Terbaru replaced by Sulut Terkini RSS)

// AMBIL BERITA POPULER (sidebar)
$popular_query = "SELECT n.*, c.name as category_name, c.color as category_color
                  FROM news n
                  JOIN categories c ON n.category_id = c.id
                  WHERE n.status = 'published' AND c.is_active = 1
                  ORDER BY n.views DESC
                  LIMIT 5";
$popular_result = mysqli_query($conn, $popular_query);

// AMBIL SEMUA KATEGORI (untuk widget)
$categories_query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC";
$categories_result = mysqli_query($conn, $categories_query);

// AMBIL CUACA MINI — konfigurasi saja (data via AJAX)
require_once __DIR__ . '/includes/weather-helpers.php';

$weatherWidgetConfig = [];
try {
    $rawWidgetConfig = getSetting($conn, 'weather_widget_locations', '');
    $widgetConfig = json_decode($rawWidgetConfig, true);

    if (!is_array($widgetConfig) || empty($widgetConfig)) {
        $widgetConfig = [
            ['adm2' => '71.71', 'name' => 'Kota Manado', 'enabled' => 1, 'order' => 1],
            ['adm2' => '71.72', 'name' => 'Kota Bitung', 'enabled' => 1, 'order' => 2],
            ['adm2' => '71.73', 'name' => 'Kota Tomohon', 'enabled' => 1, 'order' => 3],
            ['adm2' => '71.74', 'name' => 'Kota Kotamobagu', 'enabled' => 1, 'order' => 4],
            ['adm2' => '71.01', 'name' => 'Kab. Bolaang Mongondow', 'enabled' => 1, 'order' => 5],
            ['adm2' => '71.02', 'name' => 'Kab. Minahasa', 'enabled' => 1, 'order' => 6],
            ['adm2' => '71.03', 'name' => 'Kab. Kepulauan Sangihe', 'enabled' => 1, 'order' => 7],
            ['adm2' => '71.04', 'name' => 'Kab. Kepulauan Talaud', 'enabled' => 1, 'order' => 8],
            ['adm2' => '71.05', 'name' => 'Kab. Minahasa Selatan', 'enabled' => 1, 'order' => 9],
            ['adm2' => '71.06', 'name' => 'Kab. Minahasa Utara', 'enabled' => 1, 'order' => 10],
            ['adm2' => '71.07', 'name' => 'Kab. Minahasa Tenggara', 'enabled' => 1, 'order' => 11],
            ['adm2' => '71.08', 'name' => 'Kab. Bolaang Mongondow Utara', 'enabled' => 1, 'order' => 12],
            ['adm2' => '71.09', 'name' => 'Kab. Kep. Siau Tagulandang Biaro', 'enabled' => 1, 'order' => 13],
            ['adm2' => '71.10', 'name' => 'Kab. Bolaang Mongondow Timur', 'enabled' => 1, 'order' => 14],
            ['adm2' => '71.11', 'name' => 'Kab. Bolaang Mongondow Selatan', 'enabled' => 1, 'order' => 15],
        ];
    }

    usort($widgetConfig, function($a, $b) {
        return ((int)($a['order'] ?? 999)) <=> ((int)($b['order'] ?? 999));
    });

    foreach ($widgetConfig as $cfg) {
        if ((int)($cfg['enabled'] ?? 0) !== 1) continue;
        $adm2 = trim((string)($cfg['adm2'] ?? ''));
        $name = trim((string)($cfg['name'] ?? $adm2));
        if ($adm2 === '') continue;
        $weatherWidgetConfig[] = ['adm2' => $adm2, 'name' => $name];
    }

    if (count($weatherWidgetConfig) > 15) {
        $weatherWidgetConfig = array_slice($weatherWidgetConfig, 0, 15);
    }
} catch (Exception $e) {
    $weatherWidgetConfig = [];
}

// Generate default date headers
$_dayAbbr  = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
$_monthAbbr = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$defaultDateHeaders = [];
for ($i = 0; $i < 3; $i++) {
    $ts = strtotime("+{$i} day");
    $defaultDateHeaders[] = $_dayAbbr[(int)date('w', $ts)] . ', ' . (int)date('d', $ts) . ' ' . $_monthAbbr[(int)date('m', $ts)];
}

// AMBIL HOLIDAYS (untuk calendar widget)
$holidays = [];
try {
    $year = date('Y');
    $month = date('n');
    $hol_cache_key = "holidays_{$year}_{$month}";
    $hol_cache_q = "SELECT cache_data FROM api_cache WHERE cache_key = ? AND expires_at > NOW()";
    $hstmt = mysqli_prepare($conn, $hol_cache_q);
    mysqli_stmt_bind_param($hstmt, 's', $hol_cache_key);
    mysqli_stmt_execute($hstmt);
    $hc_result = mysqli_stmt_get_result($hstmt);
    
    if ($hc_row = mysqli_fetch_assoc($hc_result)) {
        $holidays = json_decode($hc_row['cache_data'], true) ?: [];
    } else {
        $api_url = "https://holicuti-api.vercel.app/api?tahun={$year}&bulan={$month}";
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $resp = @file_get_contents($api_url, false, $ctx);
        if ($resp !== false) {
            $hol_data = json_decode($resp, true);
            if (is_array($hol_data)) {
                $holidays = $hol_data;
                $save_q = "INSERT INTO api_cache (cache_key, cache_data, expires_at) 
                           VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR)) 
                           ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)";
                $save_s = mysqli_prepare($conn, $save_q);
                $json_h = json_encode($hol_data);
                mysqli_stmt_bind_param($save_s, 'ss', $hol_cache_key, $json_h);
                mysqli_stmt_execute($save_s);
            }
        }
    }
} catch (Exception $e) { $holidays = []; }

// Build holiday dates map
$holiday_dates = [];
foreach ($holidays as $h) {
    $hd = date('j', strtotime($h['holiday_date']));
    $holiday_dates[$hd] = $h['holiday_name'];
}
?>

<!-- CONTAINER START -->
<div class="container-fluid">

    <!-- HERO SECTION - Magazine Style -->
    <?php if (count($featured_items) > 0): ?>
    <section class="hero-section">
        <div class="row g-3">
            <!-- Main Hero -->
            <div class="col-lg-7">
                <?php 
                $main = $featured_items[0];
                $thumb_url = !empty($main['thumbnail']) ? SITE_URL . $main['thumbnail'] : SITE_URL . 'assets/images/default-news.jpg';
                $has_video = !empty($main['video_url']);
                ?>
                <div class="hero-main">
                    <img src="<?= htmlspecialchars($thumb_url) ?>" alt="<?= htmlspecialchars($main['title']) ?>">
                    <?php if ($has_video): ?><div class="video-play-badge"><i class="fas fa-play"></i></div><?php endif; ?>
                    <div class="hero-overlay">
                        <span class="hero-category" style="background: <?= $main['category_color'] ?>">
                            <?= htmlspecialchars($main['category_name']) ?>
                        </span>
                        <h2 class="hero-title">
                            <a href="<?= SITE_URL ?>berita.php?slug=<?= $main['slug'] ?>">
                                <?= htmlspecialchars($main['title']) ?>
                            </a>
                        </h2>
                        <div class="hero-meta">
                            <span><i class="far fa-calendar"></i> <?= formatTanggalIndonesia($main['published_at']) ?></span>
                            <span><i class="far fa-user"></i> <?= htmlspecialchars($main['author_name']) ?></span>
                            <span><i class="far fa-eye"></i> <?= formatViews($main['views']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Side Items -->
            <div class="col-lg-5">
                <?php if (isset($featured_items[1])):
                    $item = $featured_items[1];
                    $thumb = !empty($item['thumbnail']) ? SITE_URL . $item['thumbnail'] : SITE_URL . 'assets/images/default-news.jpg';
                    $is_video = !empty($item['video_url']);
                ?>
                <a href="<?= SITE_URL ?>berita.php?slug=<?= $item['slug'] ?>" class="hero-side-item hero-side-item-large mb-3">
                    <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                    <?php if ($is_video): ?><div class="video-play-badge"><i class="fas fa-play"></i></div><?php endif; ?>
                    <div class="hero-overlay">
                        <span class="hero-category" style="background: <?= $item['category_color'] ?>"><?= htmlspecialchars($item['category_name']) ?></span>
                        <h3 class="hero-title"><?= htmlspecialchars($item['title']) ?></h3>
                        <div class="hero-meta"><span><i class="far fa-clock"></i> <?= timeAgo($item['published_at']) ?></span></div>
                    </div>
                </a>
                <?php endif; ?>

                <div class="row g-3">
                    <?php for ($i = 2; $i < min(4, count($featured_items)); $i++):
                        $item = $featured_items[$i];
                        $thumb = !empty($item['thumbnail']) ? SITE_URL . $item['thumbnail'] : SITE_URL . 'assets/images/default-news.jpg';
                        $is_video = !empty($item['video_url']);
                    ?>
                    <div class="col-md-6">
                        <a href="<?= SITE_URL ?>berita.php?slug=<?= $item['slug'] ?>" class="hero-side-item hero-side-item-small">
                            <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                            <?php if ($is_video): ?><div class="video-play-badge"><i class="fas fa-play"></i></div><?php endif; ?>
                            <div class="hero-overlay">
                                <span class="hero-category" style="background: <?= $item['category_color'] ?>"><?= htmlspecialchars($item['category_name']) ?></span>
                                <h3 class="hero-title"><?= htmlspecialchars($item['title']) ?></h3>
                            </div>
                        </a>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- VIDEO BERITA SECTION -->
    <?php if (mysqli_num_rows($video_result) > 0): ?>
    <section class="content-section">
        <div class="section-header">
            <div class="section-header-left">
                <div class="section-icon"><i class="fas fa-video"></i></div>
                <h2>Video Berita</h2>
            </div>
            <a href="<?= SITE_URL ?>video.php" class="view-all-link">
                Lihat Semua <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="video-carousel-wrapper">
            <div class="video-scroll-container" id="videoScroll">
                <?php while ($vid = mysqli_fetch_assoc($video_result)): 
                    $vid_thumb = !empty($vid['thumbnail']) ? SITE_URL . $vid['thumbnail'] : SITE_URL . 'assets/images/default-news.jpg';
                ?>
                <div class="video-scroll-item">
                    <a href="<?= SITE_URL ?>berita.php?slug=<?= $vid['slug'] ?>">
                        <div class="video-thumb">
                            <img src="<?= htmlspecialchars($vid_thumb) ?>" alt="<?= htmlspecialchars($vid['title']) ?>">
                            <div class="play-overlay"><div class="play-btn"><i class="fas fa-play"></i></div></div>
                        </div>
                    </a>
                    <a href="<?= SITE_URL ?>berita.php?slug=<?= $vid['slug'] ?>" class="video-title">
                        <?= htmlspecialchars($vid['title']) ?>
                    </a>
                    <div class="video-meta">
                        <i class="far fa-clock"></i> <?= timeAgo($vid['published_at']) ?> &middot; 
                        <i class="far fa-eye"></i> <?= formatViews($vid['views']) ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <button class="video-scroll-nav prev" onclick="document.getElementById('videoScroll').scrollBy({left:-300,behavior:'smooth'})"><i class="fas fa-chevron-left"></i></button>
            <button class="video-scroll-nav next" onclick="document.getElementById('videoScroll').scrollBy({left:300,behavior:'smooth'})"><i class="fas fa-chevron-right"></i></button>
        </div>
    </section>
    <?php endif; ?>

    <!-- MAIN CONTENT + SIDEBAR -->
    <section class="content-section">
        <div class="row g-4">
            <!-- LEFT: Cuaca Utama + Berita Terbaru -->
            <div class="col-lg-8">
                <div class="widget-card mb-4 weather-monitor-card">
                    <div class="widget-card-header">
                        <i class="fas fa-cloud-sun-rain"></i>
                        <h4>Pantauan Prakiraan 3 Hari (Kab/Kota)</h4>
                    </div>
                    <div class="widget-card-body">
                        <?php if (!empty($weatherWidgetConfig)): ?>
                        <!-- Progress bar -->
                        <div class="weather-progress-bar mb-2" id="homepageWeatherProgress">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <div class="spinner-border spinner-border-sm text-primary" role="status" id="homepageWeatherSpinner" style="width:14px;height:14px;border-width:2px;"></div>
                                <span class="small text-muted" id="homepageWeatherProgressText">Memuat cuaca <span id="homepageWeatherCount">0</span>/<?= count($weatherWidgetConfig) ?> kota...</span>
                            </div>
                            <div class="progress" style="height: 3px; border-radius: 3px; background: #e9ecef;">
                                <div id="homepageWeatherFill" class="progress-bar" style="width: 0%; transition: width 0.3s ease;"></div>
                            </div>
                        </div>

                        <div class="weather-monitor-table-wrap">
                            <table class="weather-monitor-table" id="homepageWeatherTable" data-total="<?= count($weatherWidgetConfig) ?>">
                                <thead>
                                    <tr>
                                        <th>Wilayah</th>
                                        <?php for ($h = 0; $h < 3; $h++): ?>
                                        <th class="homepage-weather-date-header"><?= htmlspecialchars($defaultDateHeaders[$h]) ?></th>
                                        <?php endfor; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($weatherWidgetConfig as $w): ?>
                                    <tr data-weather-adm2="<?= htmlspecialchars($w['adm2']) ?>" data-weather-name="<?= htmlspecialchars($w['name']) ?>">
                                        <td>
                                            <a href="<?= SITE_URL ?>cuaca.php?adm2=<?= urlencode($w['adm2']) ?>" class="weather-monitor-name">
                                                <?= htmlspecialchars($w['name']) ?>
                                            </a>
                                        </td>
                                        <td class="weather-widget-cell"><div class="weather-skeleton-sm"><div class="skeleton-circle-sm"></div><div class="skeleton-line"></div><div class="skeleton-line skeleton-line-sm"></div></div></td>
                                        <td class="weather-widget-cell"><div class="weather-skeleton-sm"><div class="skeleton-circle-sm"></div><div class="skeleton-line"></div><div class="skeleton-line skeleton-line-sm"></div></div></td>
                                        <td class="weather-widget-cell"><div class="weather-skeleton-sm"><div class="skeleton-circle-sm"></div><div class="skeleton-line"></div><div class="skeleton-line skeleton-line-sm"></div></div></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-2">
                            <a href="<?= SITE_URL ?>cuaca.php" class="view-all-link">Lihat panel cuaca lengkap <i class="fas fa-arrow-right"></i></a>
                        </div>
                        <?php else: ?>
                        <div class="empty-state py-3">
                            <i class="fas fa-cloud"></i>
                            <h4>Data Cuaca Belum Tersedia</h4>
                            <p>Cek koneksi BMKG atau konfigurasi wilayah widget cuaca.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section-header">
                    <div class="section-header-left">
                        <div class="section-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <h2>Sulawesi Utara Terkini</h2>
                    </div>
                    <span class="badge bg-danger fw-normal" style="font-size:0.7rem;">ANTARA NEWS</span>
                </div>
                
                <div id="sulutTerkiniList" class="sulut-terkini-list">
                    <div class="d-flex align-items-center gap-2 py-4 justify-content-center text-muted">
                        <div class="spinner-border spinner-border-sm" role="status"></div> Memuat berita Sulawesi Utara...
                    </div>
                </div>
            </div>

            <!-- RIGHT: Sidebar -->
            <div class="col-lg-4">
                <div class="widget-card mb-3">
                    <div class="widget-card-header">
                        <i class="fas fa-calendar-alt"></i>
                        <h4>Kalender <?= date('Y') ?></h4>
                    </div>
                    <div class="widget-card-body">
                        <div class="calendar-mini">
                            <?php
                            $cal_year = date('Y');
                            $cal_month = date('n');
                            $month_names = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                            $day_names = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
                            $first_day = mktime(0, 0, 0, $cal_month, 1, $cal_year);
                            $day_of_week = date('w', $first_day);
                            $days_in_month = date('t', $first_day);
                            $today = (int)date('j');
                            ?>
                            <div class="cal-header">
                                <h5><?= $month_names[$cal_month] ?> <?= $cal_year ?></h5>
                            </div>
                            <table>
                                <thead>
                                    <tr>
                                        <?php foreach ($day_names as $dn): ?>
                                            <th><?= $dn ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $day_counter = 1;
                                    $prev_month_days = date('t', mktime(0, 0, 0, $cal_month - 1, 1, $cal_year));
                                    for ($row = 0; $row < 6; $row++):
                                        if ($day_counter > $days_in_month) break;
                                    ?>
                                    <tr>
                                        <?php for ($col = 0; $col < 7; $col++):
                                            if ($row == 0 && $col < $day_of_week):
                                                $prev_day = $prev_month_days - $day_of_week + $col + 1;
                                        ?>
                                            <td><span class="cal-day other-month"><?= $prev_day ?></span></td>
                                        <?php elseif ($day_counter > $days_in_month):
                                                $next_day = $day_counter - $days_in_month;
                                                $day_counter++;
                                        ?>
                                            <td><span class="cal-day other-month"><?= $next_day ?></span></td>
                                        <?php else:
                                                $classes = 'cal-day';
                                                $title_attr = '';
                                                if ($day_counter == $today) $classes .= ' today';
                                                if ($col == 0) $classes .= ' sunday';
                                                if (isset($holiday_dates[$day_counter])) {
                                                    $classes .= ' holiday';
                                                    $title_attr = ' title="' . htmlspecialchars($holiday_dates[$day_counter]) . '"';
                                                }
                                        ?>
                                            <td><span class="<?= $classes ?>"<?= $title_attr ?>><?= $day_counter ?></span></td>
                                        <?php 
                                                $day_counter++;
                                            endif;
                                        endfor; ?>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                            <?php if (!empty($holidays)): ?>
                            <div class="calendar-holidays">
                                <?php foreach (array_slice($holidays, 0, 3) as $h): ?>
                                <div class="holiday-item">
                                    <span class="holiday-dot"></span>
                                    <span class="holiday-date"><?= date('d M', strtotime($h['holiday_date'])) ?></span>
                                    <span class="holiday-name"><?= htmlspecialchars($h['holiday_name']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Popular News Widget -->
                <div class="widget-card">
                    <div class="widget-card-header">
                        <i class="fas fa-fire"></i>
                        <h4>Berita Populer</h4>
                    </div>
                    <div class="widget-card-body" style="padding: 8px 16px;">
                        <?php $rank = 1; while ($pop = mysqli_fetch_assoc($popular_result)): ?>
                        <div class="news-list-item">
                            <span class="list-number"><?= str_pad($rank, 2, '0', STR_PAD_LEFT) ?></span>
                            <div class="list-content">
                                <h5><a href="<?= SITE_URL ?>berita.php?slug=<?= $pop['slug'] ?>"><?= htmlspecialchars($pop['title']) ?></a></h5>
                                <div class="list-meta">
                                    <i class="far fa-eye"></i> <?= formatViews($pop['views']) ?> &middot;
                                    <i class="far fa-clock"></i> <?= timeAgo($pop['published_at']) ?>
                                </div>
                            </div>
                        </div>
                        <?php $rank++; endwhile; ?>
                    </div>
                </div>

                <!-- Kategori Widget -->
                <div class="widget-card">
                    <div class="widget-card-header">
                        <i class="fas fa-folder-open"></i>
                        <h4>Kategori Berita</h4>
                    </div>
                    <div class="widget-card-body">
                        <div class="quick-links" style="max-height: 500px; overflow-y: auto;">
                            <?php 
                            mysqli_data_seek($categories_result, 0);
                            while ($cat = mysqli_fetch_assoc($categories_result)): 
                                $catColor = $cat['color'] ?: '#1A428A';
                                $catIcon = $cat['icon'] ?: 'fa-newspaper';
                            ?>
                            <a href="<?= SITE_URL ?>kategori.php?slug=<?= urlencode($cat['slug']) ?>" class="quick-link-item">
                                <div class="quick-link-icon" style="background: <?= htmlspecialchars($catColor) ?>;">
                                    <i class="fas <?= htmlspecialchars($catIcon) ?>"></i>
                                </div>
                                <div class="quick-link-text">
                                    <div class="quick-link-title"><?= htmlspecialchars($cat['name']) ?></div>
                                    <?php if (!empty($cat['description'])): ?>
                                    <div class="quick-link-desc"><?= htmlspecialchars(substr($cat['description'], 0, 40)) ?><?= strlen($cat['description']) > 40 ? '...' : '' ?></div>
                                    <?php endif; ?>
                                </div>
                                <i class="fas fa-chevron-right quick-link-arrow"></i>
                            </a>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- BERITA API SECTION (Lokal & Nasional) -->
    <section class="content-section">
        <div class="section-header">
            <div class="section-header-left">
                <div class="section-icon"><i class="fas fa-globe"></i></div>
                <h2>Berita Dari Berbagai Sumber</h2>
            </div>
            <a href="<?= SITE_URL ?>berita-nasional.php" class="view-all-link">
                Lihat Semua <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <ul class="nav nav-tabs-custom mb-4" id="apiNewsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-lokal" data-bs-toggle="tab" data-bs-target="#panel-lokal" type="button" role="tab">
                    <i class="fas fa-map-marker-alt"></i> Sulawesi Utara
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-nasional" data-bs-toggle="tab" data-bs-target="#panel-nasional" type="button" role="tab">
                    <i class="fas fa-flag"></i> Nasional
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-inter" data-bs-toggle="tab" data-bs-target="#panel-inter" type="button" role="tab">
                    <i class="fas fa-globe-americas"></i> Internasional
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="apiNewsContent">
            <div class="tab-pane fade show active" id="panel-lokal" role="tabpanel">
                <div class="row g-3" id="apiLokalGrid">
                    <div class="col-12">
                        <div class="loading-spinner">
                            <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
                            <p class="mt-2 text-muted">Memuat berita Sulawesi Utara...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="panel-nasional" role="tabpanel">
                <div class="row g-3" id="apiNasionalGrid">
                    <div class="col-12">
                        <div class="loading-spinner">
                            <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
                            <p class="mt-2 text-muted">Memuat berita nasional...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="panel-inter" role="tabpanel">
                <div class="row g-3" id="apiInterGrid">
                    <div class="col-12">
                        <div class="loading-spinner">
                            <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
                            <p class="mt-2 text-muted">Memuat berita internasional...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>
<!-- CONTAINER END -->

<script>
// Progressive Weather Loader for Homepage Widget
(function() {
    const table = document.getElementById('homepageWeatherTable');
    if (!table) return;

    const total = parseInt(table.dataset.total, 10) || 0;
    const rows = table.querySelectorAll('tr[data-weather-adm2]');
    if (rows.length === 0) return;

    const BATCH_SIZE = 5;
    const items = Array.from(rows).map(r => ({ code: r.dataset.weatherAdm2, name: r.dataset.weatherName }));
    let loaded = 0;
    let headersUpdated = false;

    const progressBar = document.getElementById('homepageWeatherFill');
    const progressCount = document.getElementById('homepageWeatherCount');
    const progressContainer = document.getElementById('homepageWeatherProgress');
    const progressText = document.getElementById('homepageWeatherProgressText');
    const spinner = document.getElementById('homepageWeatherSpinner');

    function updateProgress() {
        loaded++;
        const pct = Math.round((loaded / total) * 100);
        if (progressBar) progressBar.style.width = pct + '%';
        if (progressCount) progressCount.textContent = loaded;
        if (loaded >= total) {
            if (spinner) spinner.style.display = 'none';
            if (progressText) progressText.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Data cuaca lengkap.';
            setTimeout(function() { if (progressContainer) progressContainer.classList.add('completed'); }, 2000);
        }
    }

    function updateHeaders(days) {
        if (headersUpdated) return;
        var headers = table.querySelectorAll('.homepage-weather-date-header');
        days.slice(0, 3).forEach(function(day, i) {
            if (headers[i] && day.date_full && day.date_full !== '-') {
                headers[i].textContent = day.date_full;
            }
        });
        headersUpdated = true;
    }

    function renderCell(day) {
        var icon = day.icon || '🌤️';
        var weather = escapeHtml(day.weather || '-');
        var tMin = escapeHtml(String(day.temp_min || '-'));
        var tMax = escapeHtml(String(day.temp_max || '-'));
        var huMin = day.humidity_min || '-';
        var huMax = day.humidity_max || huMin;
        var huStr = '';
        if (huMin !== '-' && huMax !== '-' && huMin !== huMax) {
            huStr = 'RH: ' + escapeHtml(String(huMin)) + '-' + escapeHtml(String(huMax)) + '%';
        } else if (huMin !== '-') {
            huStr = 'RH: ' + escapeHtml(String(huMin)) + '%';
        }

        return '<div class="weather-loaded">'
            + '<div class="weather-monitor-icon" style="font-size:24px;margin-bottom:4px;">' + icon + '</div>'
            + '<div class="weather-monitor-desc">' + weather + '</div>'
            + '<div class="weather-monitor-temp">' + tMin + '-' + tMax + '°C</div>'
            + (huStr ? '<div class="weather-monitor-humidity" style="font-size:11px;color:#888;">' + huStr + '</div>' : '')
            + '</div>';
    }

    function renderUnavailable() {
        return '<div class="weather-loaded"><span class="text-muted" style="font-size:12px;">Tidak tersedia</span></div>';
    }

    async function fetchWeather(item) {
        try {
            var controller = new AbortController();
            var tid = setTimeout(function() { controller.abort(); }, 15000);
            var url = '<?= SITE_URL ?>api/cuaca-ajax.php?type=adm2&code=' + encodeURIComponent(item.code) + '&name=' + encodeURIComponent(item.name);
            var response = await fetch(url, { signal: controller.signal });
            clearTimeout(tid);
            return await response.json();
        } catch (err) {
            return { success: false, code: item.code, error: err.message || 'Timeout' };
        }
    }

    async function loadAll() {
        for (var i = 0; i < items.length; i += BATCH_SIZE) {
            var batch = items.slice(i, i + BATCH_SIZE);
            var results = await Promise.all(batch.map(fetchWeather));

            results.forEach(function(result) {
                var row = table.querySelector('tr[data-weather-adm2="' + result.code + '"]');
                if (!row) return;
                var cells = row.querySelectorAll('.weather-widget-cell');

                if (result.success && result.days && result.days.length > 0) {
                    updateHeaders(result.days);
                    result.days.slice(0, 3).forEach(function(day, idx) {
                        if (cells[idx]) cells[idx].innerHTML = renderCell(day);
                    });
                } else {
                    cells.forEach(function(cell) { cell.innerHTML = renderUnavailable(); });
                }
                updateProgress();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadAll);
    } else {
        loadAll();
    }
})();
</script>

<script>
// Sulawesi Utara Terkini - RSS Feed Loader
(function() {
    async function loadSulutTerkini() {
        const container = document.getElementById('sulutTerkiniList');
        if (!container) return;

        try {
            const resp = await fetch('<?= SITE_URL ?>api/antara-rss.php?feed=terkini&limit=8');
            const result = await resp.json();

            if (!result.success || !result.data?.articles?.length) {
                container.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-inbox"></i> Tidak ada berita tersedia.</div>';
                return;
            }

            let html = '';
            result.data.articles.forEach(function(article) {
                const img = escapeHtml(article.urlToImage || '<?= SITE_URL ?>assets/images/default-news.jpg');
                const title = escapeHtml(article.title || 'Tanpa Judul');
                const category = escapeHtml(article.category || 'Sulawesi Utara');
                const timeAgo = escapeHtml(article.timeAgo || '-');
                const location = escapeHtml(article.location || 'Sulawesi Utara');
                const url = escapeHtml(article.url || '#');

                html += '<a href="' + url + '" target="_blank" rel="noopener" class="sulut-news-item">'
                    + '<div class="sulut-news-body">'
                    +   '<span class="sulut-news-category">' + category + '</span>'
                    +   '<h4 class="sulut-news-title">' + title + '</h4>'
                    +   '<div class="sulut-news-meta">'
                    +     '<span class="sulut-news-location">' + location + '</span>'
                    +     '<span class="sulut-news-time"><i class="far fa-clock"></i> ' + timeAgo + '</span>'
                    +   '</div>'
                    + '</div>'
                    + '<div class="sulut-news-thumb">'
                    +   '<img src="' + img + '" alt="' + title + '" onerror="this.src=\'<?= SITE_URL ?>assets/images/default-news.jpg\'">'
                    + '</div>'
                    + '</a>';
            });
            container.innerHTML = html;
        } catch (err) {
            container.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-exclamation-circle"></i> Gagal memuat berita.</div>';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadSulutTerkini);
    } else {
        loadSulutTerkini();
    }
})();
</script>

<script>
// Berita Dari Berbagai Sumber - 3 Tab Loader
(function() {
    var loadedGrids = {};

    function loadApiNews(apiUrl, containerId, sourceLabel) {
        if (loadedGrids[containerId]) return;
        var container = document.getElementById(containerId);
        if (!container) return;

        fetch(apiUrl)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    container.innerHTML = '<div class="col-12"><div class="empty-state"><i class="fas fa-exclamation-circle"></i><h4>Gagal Memuat</h4><p>' + escapeHtml(data.error || 'Tidak tersedia') + '</p></div></div>';
                    return;
                }

                var articles = (data.data && data.data.articles) ? data.data.articles : (data.articles || []);
                if (articles.length === 0) {
                    container.innerHTML = '<div class="col-12"><div class="empty-state"><i class="fas fa-globe"></i><h4>Tidak Ada Berita</h4><p>Berita belum tersedia saat ini.</p></div></div>';
                    loadedGrids[containerId] = true;
                    return;
                }

                var html = '';
                articles.slice(0, 6).forEach(function(article) {
                    var img = escapeHtml(article.urlToImage || article.image_url || '<?= SITE_URL ?>assets/images/default-news.jpg');
                    var title = escapeHtml(article.title || 'Tanpa Judul');
                    var desc = escapeHtml(article.description || article.content || '');
                    var src = escapeHtml(article.source_name || (article.source && article.source.name ? article.source.name : '') || article.source || sourceLabel || '-');
                    var date = article.publishedAtFormatted || article.pubDate || article.publishedAt || '';
                    var url = escapeHtml(article.url || article.link || '#');
                    var dateStr = '';
                    if (date) {
                        try { dateStr = new Date(date).toLocaleDateString('id-ID', {day:'numeric',month:'short',year:'numeric'}); }
                        catch(e) { dateStr = date; }
                    }

                    html += '<div class="col-md-4 col-sm-6">'
                        + '<div class="api-news-card">'
                        + '<div class="card-img-wrapper">'
                        + '<img src="' + img + '" alt="' + title + '" onerror="this.src=\'<?= SITE_URL ?>assets/images/default-news.jpg\'">'
                        + '<span class="source-badge">' + src.toUpperCase() + '</span>'
                        + '</div>'
                        + '<div class="card-body-inner">'
                        + '<h5>' + title + '</h5>'
                        + '<p>' + desc.substring(0, 120) + (desc.length > 120 ? '...' : '') + '</p>'
                        + '</div>'
                        + '<div class="card-footer-inner">'
                        + '<span class="date-info"><i class="far fa-clock"></i> ' + escapeHtml(dateStr) + '</span>'
                        + '<a href="' + url + '" target="_blank" rel="noopener" class="read-link">Baca <i class="fas fa-external-link-alt"></i></a>'
                        + '</div>'
                        + '</div>'
                        + '</div>';
                });
                container.innerHTML = html;
                loadedGrids[containerId] = true;
            })
            .catch(function(err) {
                container.innerHTML = '<div class="col-12"><div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h4>Gagal Memuat</h4><p>Tidak dapat memuat berita. Coba lagi nanti.</p></div></div>';
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Tab 1: Sulawesi Utara (RSS Antara sulut-update)
        loadApiNews('<?= SITE_URL ?>api/antara-rss.php?feed=sulut-update&limit=6', 'apiLokalGrid', 'ANTARA News Sulut');

        // Tab 2: Nasional (lazy load)
        document.getElementById('tab-nasional').addEventListener('shown.bs.tab', function() {
            loadApiNews('<?= SITE_URL ?>api/newsapi-fetch.php?category=indonesia', 'apiNasionalGrid', 'NewsData');
        });

        // Tab 3: Internasional (lazy load)
        document.getElementById('tab-inter').addEventListener('shown.bs.tab', function() {
            loadApiNews('<?= SITE_URL ?>api/newsapi-fetch.php?category=international', 'apiInterGrid', 'NewsAPI');
        });
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
