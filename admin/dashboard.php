<?php
/**
 * Admin: Dashboard
 */

// Include config dan auth
require_once '../config/config.php';
require_once 'auth.php';

// Cek login
requireLogin();

// Ambil koneksi database
$conn = getDBConnection();

// Get logged in user
$logged_in_user = getLoggedInUser();

// Set page variables
$page_title = 'Dashboard';
$page_heading = 'Dashboard';
$breadcrumbs = [
    'Dashboard' => null
];

// STATISTIK DATABASE

// Total berita published
$stat_news_query = "SELECT COUNT(*) as total FROM news WHERE status = 'published'";
$stat_news_result = mysqli_query($conn, $stat_news_query);
$total_news = mysqli_fetch_assoc($stat_news_result)['total'];

// Total berita draft
$stat_draft_query = "SELECT COUNT(*) as total FROM news WHERE status = 'draft'";
$stat_draft_result = mysqli_query($conn, $stat_draft_query);
$total_draft = mysqli_fetch_assoc($stat_draft_result)['total'];

// Total kategori aktif
$stat_category_query = "SELECT COUNT(*) as total FROM categories WHERE is_active = 1";
$stat_category_result = mysqli_query($conn, $stat_category_query);
$total_categories = mysqli_fetch_assoc($stat_category_result)['total'];

// Total views seluruh berita
$stat_views_query = "SELECT SUM(views) as total FROM news";
$stat_views_result = mysqli_query($conn, $stat_views_query);
$total_views = mysqli_fetch_assoc($stat_views_result)['total'] ?? 0;

// Total user aktif
$stat_users_query = "SELECT COUNT(*) as total FROM users WHERE is_active = 1";
$stat_users_result = mysqli_query($conn, $stat_users_query);
$total_users = mysqli_fetch_assoc($stat_users_result)['total'];

// LIVE BROADCAST STATS (HARI INI)
$today = date('Y-m-d');
$broadcast_stats_query = "SELECT 
                          COUNT(*) as total,
                          SUM(CASE WHEN status = 'broadcasted' THEN 1 ELSE 0 END) as broadcasted,
                          SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                          SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled
                          FROM live_broadcast_schedule
                          WHERE broadcast_date = ?";
$stmt = mysqli_prepare($conn, $broadcast_stats_query);
mysqli_stmt_bind_param($stmt, 's', $today);
mysqli_stmt_execute($stmt);
$broadcast_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$broadcast_success_rate = $broadcast_stats['total'] > 0 
    ? round(($broadcast_stats['broadcasted'] / $broadcast_stats['total']) * 100) 
    : 0;

// Berita terpopuler (top 5)
$popular_query = "SELECT n.*, c.name as category_name 
                  FROM news n
                  INNER JOIN categories c ON n.category_id = c.id
                  WHERE n.status = 'published'
                  ORDER BY n.views DESC
                  LIMIT 5";
$popular_result = mysqli_query($conn, $popular_query);

// Berita terbaru (top 5)
$latest_query = "SELECT n.*, c.name as category_name, u.full_name as author_name
                 FROM news n
                 INNER JOIN categories c ON n.category_id = c.id
                 INNER JOIN users u ON n.author_id = u.id
                 ORDER BY n.created_at DESC
                 LIMIT 5";
$latest_result = mysqli_query($conn, $latest_query);

// Today's upcoming events count
$today_events_query = "SELECT COUNT(*) as count FROM calendar_events WHERE event_date = ?";
$stmt = mysqli_prepare($conn, $today_events_query);
mysqli_stmt_bind_param($stmt, 's', $today);
mysqli_stmt_execute($stmt);
$today_events_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'];

// Upcoming events range (7 or 30 days)
$agenda_range_days = 7;
if (isset($_GET['agenda_range'])) {
    $range = (int)$_GET['agenda_range'];
    if (in_array($range, [7, 30], true)) {
        $agenda_range_days = $range;
    }
}
$agenda_range_label = $agenda_range_days === 30 ? '1 Bulan' : '7 Hari';
$agenda_end_date = date('Y-m-d', strtotime('+' . $agenda_range_days . ' days'));

// Upcoming events (based on selected range)
$upcoming_query = "SELECT * FROM calendar_events WHERE event_date BETWEEN ? AND ? ORDER BY event_date ASC, event_time ASC LIMIT 5";
$stmt = mysqli_prepare($conn, $upcoming_query);
mysqli_stmt_bind_param($stmt, 'ss', $today, $agenda_end_date);
mysqli_stmt_execute($stmt);
$upcoming_events = mysqli_stmt_get_result($stmt);

// Berita trend (last 7 days views)
$trend_query = "SELECT DATE(created_at) as dated, COUNT(*) as total
                FROM news 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY dated ASC";
$trend_result = mysqli_query($conn, $trend_query);
$trend_labels = [];
$trend_data = [];
while ($t = mysqli_fetch_assoc($trend_result)) {
    $trend_labels[] = date('d M', strtotime($t['dated']));
    $trend_data[] = (int)$t['total'];
}

// Include header
include 'includes/header.php';
?>

<!-- Dashboard Greeting Banner -->
<div class="dashboard-banner mb-4">
    <div class="dashboard-banner-content">
        <div>
            <h2 class="dashboard-banner-title">
                <?php 
                $hour = (int)date('H');
                if ($hour < 12) echo 'Selamat Pagi';
                elseif ($hour < 15) echo 'Selamat Siang';
                elseif ($hour < 18) echo 'Selamat Sore';
                else echo 'Selamat Malam';
                ?>, <?php echo htmlspecialchars($logged_in_user['full_name']); ?>! 
            </h2>
            <p class="dashboard-banner-subtitle">Berikut ringkasan aktivitas SULUT NEWS HUB hari ini</p>
        </div>
        <div class="dashboard-banner-meta">
            <div class="banner-date-display">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo date('l, d F Y'); ?></span>
            </div>
            <div class="banner-time-display" id="liveClock">
                <i class="fas fa-clock"></i>
                <span><?php echo date('H:i'); ?> WITA</span>
            </div>
        </div>
    </div>
</div>

<!-- STAT CARDS ROW -->
<div class="row g-3 mb-4">
    <div class="col-xl col-md-4 col-6">
        <div class="card stat-card stat-card--success">
            <div class="card-body py-3">
                <div class="stat-indicator"></div>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="stat-label">Published</p>
                        <h3 class="stat-value"><?php echo number_format($total_news); ?></h3>
                    </div>
                    <div class="stat-icon"><i class="fas fa-newspaper"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="card stat-card stat-card--warning">
            <div class="card-body py-3">
                <div class="stat-indicator"></div>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="stat-label">Draft</p>
                        <h3 class="stat-value"><?php echo number_format($total_draft); ?></h3>
                    </div>
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="card stat-card stat-card--info">
            <div class="card-body py-3">
                <div class="stat-indicator"></div>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="stat-label">Total Views</p>
                        <h3 class="stat-value"><?php echo number_format($total_views); ?></h3>
                    </div>
                    <div class="stat-icon"><i class="fas fa-eye"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="card stat-card stat-card--purple">
            <div class="card-body py-3">
                <div class="stat-indicator"></div>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="stat-label">Kategori</p>
                        <h3 class="stat-value"><?php echo number_format($total_categories); ?></h3>
                    </div>
                    <div class="stat-icon"><i class="fas fa-tags"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="card stat-card stat-card--gold">
            <div class="card-body py-3">
                <div class="stat-indicator"></div>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="stat-label">User Aktif</p>
                        <h3 class="stat-value"><?php echo number_format($total_users); ?></h3>
                    </div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ROW 2: BROADCAST WIDGET + MINI CHART -->
<div class="row g-4 mb-4">
    <!-- Live Broadcast Widget -->
    <div class="col-lg-8">
        <div class="card broadcast-widget h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <span class="pulse-dot"></span>
                        <span style="font-weight: 700;">Live Broadcast</span>
                        <span class="badge" style="background: var(--tvri-primary); font-size: 0.7rem; padding: 4px 10px;"><?php echo date('d M Y'); ?></span>
                    </h5>
                    <div class="d-flex gap-2 mt-2 mt-md-0">
                        <a href="broadcast-schedule.php" class="btn btn-sm btn-primary"><i class="fas fa-calendar-check me-1"></i>Jadwal</a>
                        <a href="broadcast-statistics.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chart-bar me-1"></i>Statistik</a>
                    </div>
                </div>
                <?php if ($broadcast_stats['total'] > 0): ?>
                <div class="broadcast-stats-grid">
                    <div class="broadcast-stat-card broadcast-stat-purple">
                        <div class="broadcast-stat-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="broadcast-stat-content">
                            <h3 class="broadcast-stat-number"><?php echo $broadcast_stats['total']; ?></h3>
                            <p class="broadcast-stat-text">Total Jadwal</p>
                        </div>
                    </div>
                    <div class="broadcast-stat-card broadcast-stat-green">
                        <div class="broadcast-stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="broadcast-stat-content">
                            <h3 class="broadcast-stat-number"><?php echo $broadcast_stats['broadcasted']; ?></h3>
                            <p class="broadcast-stat-text">Berhasil</p>
                        </div>
                    </div>
                    <div class="broadcast-stat-card broadcast-stat-red">
                        <div class="broadcast-stat-icon"><i class="fas fa-times-circle"></i></div>
                        <div class="broadcast-stat-content">
                            <h3 class="broadcast-stat-number"><?php echo $broadcast_stats['failed']; ?></h3>
                            <p class="broadcast-stat-text">Gagal</p>
                        </div>
                    </div>
                    <div class="broadcast-stat-card broadcast-stat-blue">
                        <div class="broadcast-stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="broadcast-stat-content">
                            <h3 class="broadcast-stat-number"><?php echo $broadcast_success_rate; ?>%</h3>
                            <p class="broadcast-stat-text">Success Rate</p>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center py-3">
                    <i class="fas fa-satellite-dish fa-2x mb-2" style="color: var(--text-light);"></i>
                    <p class="text-muted mb-0" style="font-size: 13px;">Belum ada jadwal broadcast hari ini</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Mini Activity Chart -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-1" style="font-weight: 700; font-size: 14px;">
                    <i class="fas fa-chart-area me-1" style="color: var(--tvri-primary);"></i> Trend Berita
                </h6>
                <p class="text-muted mb-3" style="font-size: 11.5px;">Publikasi 7 hari terakhir</p>
                <div style="height: 160px; position: relative;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ROW 3: CALENDAR + UPCOMING EVENTS -->
<div class="row g-4 mb-4">
    <!-- Calendar Widget -->
    <div class="col-lg-8">
        <div class="card calendar-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 d-flex align-items-center gap-2">
                    <i class="fas fa-calendar-alt" style="color: var(--tvri-gold);"></i>
                    <span>Kalender & Jadwal</span>
                    <?php if ($today_events_count > 0): ?>
                        <span class="badge bg-danger" style="font-size: 0.7rem;"><?php echo $today_events_count; ?> hari ini</span>
                    <?php endif; ?>
                </h5>
                <button class="btn btn-sm btn-primary" onclick="openEventModal()">
                    <i class="fas fa-plus me-1"></i>Tambah Event
                </button>
            </div>
            <div class="card-body p-0">
                <!-- Calendar Navigation -->
                <div class="calendar-nav">
                    <button class="cal-nav-btn" id="calPrev"><i class="fas fa-chevron-left"></i></button>
                    <h6 class="cal-nav-title" id="calTitle">Februari 2026</h6>
                    <button class="cal-nav-btn" id="calNext"><i class="fas fa-chevron-right"></i></button>
                    <button class="cal-nav-btn cal-today-btn" id="calToday">Hari Ini</button>
                </div>
                
                <!-- Calendar Grid -->
                <div class="calendar-grid" id="calendarGrid">
                    <!-- Days header -->
                    <div class="cal-header-row">
                        <div class="cal-header-cell sun">Min</div>
                        <div class="cal-header-cell">Sen</div>
                        <div class="cal-header-cell">Sel</div>
                        <div class="cal-header-cell">Rab</div>
                        <div class="cal-header-cell">Kam</div>
                        <div class="cal-header-cell">Jum</div>
                        <div class="cal-header-cell sat">Sab</div>
                    </div>
                    <!-- Calendar body rendered by JS -->
                    <div class="cal-body" id="calBody"></div>
                </div>

                <!-- Calendar Legend -->
                <div class="calendar-legend">
                    <span class="legend-item"><span class="legend-dot" style="background: var(--tvri-primary);"></span>Jadwal</span>
                    <span class="legend-item"><span class="legend-dot" style="background: #059669;"></span>Meeting</span>
                    <span class="legend-item"><span class="legend-dot" style="background: #D97706;"></span>Deadline</span>
                    <span class="legend-item"><span class="legend-dot" style="background: #E91E63;"></span>Ulang Tahun</span>
                    <span class="legend-item"><span class="legend-dot" style="background: #DC2626;"></span>Libur Nasional</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Events Sidebar -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <h5 class="mb-0 d-flex align-items-center gap-2">
                    <i class="fas fa-bell" style="color: var(--tvri-gold);"></i>
                    <span>Agenda Mendatang</span>
                    <span class="badge bg-light text-muted" style="font-size: 11px;"><?php echo $agenda_range_label; ?></span>
                </h5>
                <form method="GET" class="d-flex align-items-center" style="gap: 6px;">
                    <select name="agenda_range" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Rentang agenda">
                        <option value="7" <?php echo $agenda_range_days === 7 ? 'selected' : ''; ?>>7 Hari</option>
                        <option value="30" <?php echo $agenda_range_days === 30 ? 'selected' : ''; ?>>1 Bulan</option>
                    </select>
                </form>
            </div>
            <div class="card-body p-0" id="upcomingEventsContainer">
                <?php if (mysqli_num_rows($upcoming_events) > 0): ?>
                    <div class="upcoming-list">
                        <?php while ($evt = mysqli_fetch_assoc($upcoming_events)): ?>
                        <div class="upcoming-item">
                            <div class="upcoming-date-badge">
                                <span class="upcoming-day"><?php echo date('d', strtotime($evt['event_date'])); ?></span>
                                <span class="upcoming-month"><?php echo date('M', strtotime($evt['event_date'])); ?></span>
                            </div>
                            <div class="upcoming-info">
                                <h6 class="upcoming-title"><?php echo htmlspecialchars($evt['title']); ?></h6>
                                <div class="upcoming-meta">
                                    <?php $evt_color = !empty($evt['color']) ? $evt['color'] : '#1A428A'; ?>
                                    <span class="upcoming-type-badge" style="background: <?php echo $evt_color; ?>20; color: <?php echo $evt_color; ?>;">
                                        <?php echo ucfirst($evt['event_type'] ?: 'jadwal'); ?>
                                    </span>
                                    <?php if ($evt['event_time']): ?>
                                        <span><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($evt['event_time'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-check fa-2x mb-2" style="color: #e2e8f0;"></i>
                        <p class="text-muted mb-0" style="font-size: 13px;">Tidak ada agenda mendatang dalam <?php echo strtolower($agenda_range_label); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ROW 4: BERITA TERPOPULER + TERBARU -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-fire" style="color: #ef4444;"></i> Berita Terpopuler</h5>
                <span class="badge" style="background: rgba(239,68,68,0.1); color: #ef4444;">Top 5</span>
            </div>
            <div class="card-body p-0">
                <?php if (mysqli_num_rows($popular_result) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php $rank = 1; while ($news = mysqli_fetch_assoc($popular_result)): ?>
                            <a href="../berita.php?slug=<?php echo $news['slug']; ?>" target="_blank" class="list-group-item list-group-item-action border-0 px-4 py-3">
                                <div class="d-flex align-items-start">
                                    <div class="me-3 d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; border-radius: 10px; background: <?php echo $rank <= 3 ? 'var(--tvri-gold)' : '#e2e8f0'; ?>; color: <?php echo $rank <= 3 ? '#fff' : '#64748b'; ?>; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">
                                        <?php echo $rank++; ?>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <h6 class="mb-1 text-truncate" style="font-weight: 600; color: var(--text-primary); font-size: 13px;">
                                            <?php echo htmlspecialchars($news['title']); ?>
                                        </h6>
                                        <div class="d-flex align-items-center gap-3" style="font-size: 0.75rem; color: var(--text-muted);">
                                            <span><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($news['category_name']); ?></span>
                                            <span><i class="fas fa-eye me-1"></i><?php echo number_format($news['views']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-line fa-2x mb-2" style="color: #e2e8f0;"></i>
                        <p class="text-muted mb-0" style="font-size: 13px;">Belum ada data berita</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-clock" style="color: var(--tvri-primary);"></i> Berita Terbaru</h5>
                <a href="berita-list.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="card-body p-0">
                <?php if (mysqli_num_rows($latest_result) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($news = mysqli_fetch_assoc($latest_result)): ?>
                            <a href="berita-edit.php?id=<?php echo $news['id']; ?>" class="list-group-item list-group-item-action border-0 px-4 py-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="mb-1 me-2 text-truncate" style="font-weight: 600; color: var(--text-primary); font-size: 13px;">
                                        <?php echo htmlspecialchars($news['title']); ?>
                                    </h6>
                                    <?php if ($news['status'] === 'published'): ?>
                                        <span class="badge bg-success" style="flex-shrink: 0; font-size: 0.7rem;">Published</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning" style="flex-shrink: 0; font-size: 0.7rem;">Draft</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center gap-3 flex-wrap" style="font-size: 0.75rem; color: var(--text-muted);">
                                    <span><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($news['author_name']); ?></span>
                                    <span><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($news['category_name']); ?></span>
                                    <span><i class="fas fa-calendar me-1"></i><?php echo date('d M Y', strtotime($news['created_at'])); ?></span>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-newspaper fa-2x mb-2" style="color: #e2e8f0;"></i>
                        <p class="text-muted mb-0" style="font-size: 13px;">Belum ada data berita</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ROW 5: QUICK ACTIONS -->
<div class="row g-4 mb-2">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bolt" style="color: var(--tvri-gold);"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <a href="berita-add.php" class="quick-action-btn primary-action">
                            <i class="fas fa-plus-circle"></i>
                            <span>Tambah Berita</span>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <a href="berita-list.php" class="quick-action-btn">
                            <i class="fas fa-list"></i>
                            <span>Kelola Berita</span>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <a href="broadcast-schedule.php" class="quick-action-btn">
                            <i class="fas fa-broadcast-tower"></i>
                            <span>Live Broadcast</span>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <a href="settings.php" class="quick-action-btn">
                            <i class="fas fa-cog"></i>
                            <span>Pengaturan</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CALENDAR EVENT MODAL -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalTitle"><i class="fas fa-calendar-plus me-2"></i>Tambah Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="eventId">
                <div class="mb-3">
                    <label class="form-label">Judul Event <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="eventTitle" placeholder="Nama event..." required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="eventDate" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Waktu</label>
                        <input type="time" class="form-control" id="eventTime">
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Tipe Event</label>
                        <select class="form-select" id="eventType">
                            <option value="jadwal">📅 Jadwal</option>
                            <option value="meeting">👥 Meeting</option>
                            <option value="deadline">⏰ Deadline</option>
                            <option value="reminder">🔔 Reminder</option>
                            <option value="birthday">🎂 Ulang Tahun</option>
                            <option value="anniversary">🎉 Hari Penting</option>
                            <option value="lainnya">📌 Lainnya</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pengingat</label>
                        <select class="form-select" id="eventReminder">
                            <option value="0">Tidak ada</option>
                            <option value="15">15 menit sebelum</option>
                            <option value="30">30 menit sebelum</option>
                            <option value="60">1 jam sebelum</option>
                            <option value="1440">1 hari sebelum</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Warna</label>
                    <div class="color-picker-row">
                        <label class="color-option"><input type="radio" name="eventColor" value="#1A428A" checked><span style="background: #1A428A;"></span></label>
                        <label class="color-option"><input type="radio" name="eventColor" value="#059669"><span style="background: #059669;"></span></label>
                        <label class="color-option"><input type="radio" name="eventColor" value="#D97706"><span style="background: #D97706;"></span></label>
                        <label class="color-option"><input type="radio" name="eventColor" value="#DC2626"><span style="background: #DC2626;"></span></label>
                        <label class="color-option"><input type="radio" name="eventColor" value="#E91E63"><span style="background: #E91E63;"></span></label>
                        <label class="color-option"><input type="radio" name="eventColor" value="#7C3AED"><span style="background: #7C3AED;"></span></label>
                        <label class="color-option"><input type="radio" name="eventColor" value="#F0B429"><span style="background: #F0B429;"></span></label>
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-control" id="eventDesc" rows="2" placeholder="Catatan tambahan..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger" id="btnDeleteEvent" style="display:none;" onclick="deleteEvent()">
                    <i class="fas fa-trash me-1"></i>Hapus
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" onclick="saveEvent()">
                    <i class="fas fa-save me-1"></i><span id="btnSaveText">Simpan</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- EVENT DETAIL POPOVER (for day click) -->
<div class="day-popover" id="dayPopover" style="display:none;">
    <div class="day-popover-header">
        <span id="popoverDate"></span>
        <button type="button" onclick="closeDayPopover()" class="day-popover-close">&times;</button>
    </div>
    <div class="day-popover-body" id="popoverBody"></div>
    <div class="day-popover-footer">
        <button class="btn btn-sm btn-primary w-100" id="popoverAddBtn" onclick="addEventFromPopover()">
            <i class="fas fa-plus me-1"></i>Tambah Event
        </button>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// LIVE CLOCK
(function updateClock() {
    const el = document.getElementById('liveClock');
    if (el) {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        el.querySelector('span').textContent = h + ':' + m + ':' + s + ' WITA';
    }
    setTimeout(updateClock, 1000);
})();

// TREND MINI CHART
function initTrendChart() {
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx && typeof Chart !== 'undefined') {
        try {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($trend_labels); ?>,
                    datasets: [{
                        label: 'Berita',
                        data: <?php echo json_encode($trend_data); ?>,
                        borderColor: '#1A428A',
                        backgroundColor: 'rgba(26,66,138,0.08)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointBackgroundColor: '#1A428A',
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { 
                            grid: { display: false },
                            ticks: { font: { size: 10, family: 'Inter' }, color: '#94A3B8' }
                        },
                        y: { 
                            beginAtZero: true, 
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: { 
                                stepSize: 1,
                                font: { size: 10, family: 'Inter' }, 
                                color: '#94A3B8'
                            }
                        }
                    }
                }
            });
        } catch(e) {
            console.error('Chart error:', e);
        }
    }
}

// Init chart after Chart.js loads
if (typeof Chart !== 'undefined') {
    initTrendChart();
} else {
    window.addEventListener('load', initTrendChart);
}

// CALENDAR ENGINE
const CAL = {
    year: <?php echo date('Y'); ?>,
    month: <?php echo date('n'); ?>,
    today: '<?php echo date('Y-m-d'); ?>',
    events: [],
    holidays: [],
    
    monthNames: ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'],

    init() {
        const prevBtn = document.getElementById('calPrev');
        const nextBtn = document.getElementById('calNext');
        const todayBtn = document.getElementById('calToday');
        const calBody = document.getElementById('calBody');
        
        if (!prevBtn || !nextBtn || !todayBtn || !calBody) {
            console.error('Calendar elements not found');
            return false;
        }
        
        prevBtn.addEventListener('click', () => this.changeMonth(-1));
        nextBtn.addEventListener('click', () => this.changeMonth(1));
        todayBtn.addEventListener('click', () => {
            const now = new Date();
            this.year = now.getFullYear();
            this.month = now.getMonth() + 1;
            this.load();
        });
        this.load();
        return true;
    },

    changeMonth(dir) {
        this.month += dir;
        if (this.month < 1) { this.month = 12; this.year--; }
        if (this.month > 12) { this.month = 1; this.year++; }
        this.load();
    },

    async load() {
        document.getElementById('calTitle').textContent = this.monthNames[this.month - 1] + ' ' + this.year;
        
        try {
            const res = await fetch('api-calendar.php?year=' + this.year + '&month=' + this.month);
            const data = await res.json();
            if (data.success) {
                this.events = data.events;
                this.holidays = data.holidays;
            }
        } catch(e) {
            console.error('Calendar fetch error:', e);
        }
        
        this.render();
    },

    render() {
        const body = document.getElementById('calBody');
        if (!body) {
            console.error('❌ calBody element not found!');
            return;
        }
        
        console.log('📅 Rendering calendar: ' + this.monthNames[this.month-1] + ' ' + this.year);
        
        const firstDay = new Date(this.year, this.month - 1, 1).getDay(); // 0=Sun
        const daysInMonth = new Date(this.year, this.month, 0).getDate();
        const todayStr = this.today;
        
        console.log('✓ First day:', firstDay, '| Days in month:', daysInMonth);
        
        // Calculate total weeks needed
        const totalCells = firstDay + daysInMonth;
        const totalWeeks = Math.ceil(totalCells / 7);
        
        let html = '';
        let dayCounter = 1;
        
        // Build calendar week by week
        for (let week = 0; week < totalWeeks; week++) {
            html += '<div class="cal-week">';
            
            // Each week has exactly 7 days
            for (let dayOfWeek = 0; dayOfWeek < 7; dayOfWeek++) {
                const cellIndex = week * 7 + dayOfWeek;
                
                // Empty cell before month starts OR after month ends
                if (cellIndex < firstDay || dayCounter > daysInMonth) {
                    html += '<div class="cal-cell cal-empty"></div>';
                } else {
                    // Active day cell
                    const dateStr = this.year + '-' + String(this.month).padStart(2, '0') + '-' + String(dayCounter).padStart(2, '0');
                    const isToday = dateStr === todayStr;
                    const isSunday = dayOfWeek === 0;
                    const isSaturday = dayOfWeek === 6;
                    
                    // Find events for this day
                    const dayEvents = this.events.filter(e => e.event_date === dateStr);
                    const dayHolidays = this.holidays.filter(h => h.event_date === dateStr);
                    const totalItems = dayEvents.length + dayHolidays.length;
                    
                    // Build CSS classes
                    let cls = 'cal-cell';
                    if (isToday) cls += ' cal-today';
                    if (isSunday) cls += ' cal-sunday';
                    if (isSaturday) cls += ' cal-saturday';
                    if (dayHolidays.length > 0) cls += ' cal-holiday';
                    if (totalItems > 0) cls += ' cal-has-events';
                    
                    html += '<div class="' + cls + '" data-date="' + dateStr + '" onclick="CAL.showDay(\'' + dateStr + '\', event)">';
                    html += '<span class="cal-day-num">' + dayCounter + '</span>';
                    
                    // Show event dots
                    if (totalItems > 0) {
                        html += '<div class="cal-dots">';
                        
                        // Holiday dots first
                        dayHolidays.forEach(h => {
                            html += '<span class="cal-dot" style="background:' + h.color + ';" title="' + h.title.replace(/"/g, '&quot;') + '"></span>';
                        });
                        
                        // User event dots (max 3)
                        dayEvents.slice(0, 3).forEach(e => {
                            html += '<span class="cal-dot" style="background:' + e.color + ';" title="' + e.title.replace(/"/g, '&quot;') + '"></span>';
                        });
                        
                        // Show "+N more" indicator if more than 4 total items
                        if (totalItems > 4) {
                            html += '<span class="cal-dot-more">+' + (totalItems - 3) + '</span>';
                        }
                        
                        html += '</div>';
                    }
                    
                    html += '</div>';
                    dayCounter++;
                }
            }
            
            html += '</div>'; // Close cal-week
        }
        
        // Update DOM
        body.innerHTML = html;
        console.log('✅ Calendar rendered successfully! Total weeks:', totalWeeks);
    },

    showDay(dateStr, evt) {
        evt.stopPropagation();
        const popover = document.getElementById('dayPopover');
        const dayEvents = this.events.filter(e => e.event_date === dateStr);
        const dayHolidays = this.holidays.filter(h => h.event_date === dateStr);
        
        const dateObj = new Date(dateStr + 'T00:00:00');
        const dayName = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][dateObj.getDay()];
        const monthName = this.monthNames[dateObj.getMonth()];
        document.getElementById('popoverDate').textContent = dayName + ', ' + dateObj.getDate() + ' ' + monthName + ' ' + dateObj.getFullYear();
        
        let bodyHtml = '';
        
        dayHolidays.forEach(h => {
            bodyHtml += '<div class="popover-event holiday-event">';
            bodyHtml += '<div class="popover-event-color" style="background:' + h.color + ';"></div>';
            bodyHtml += '<div class="popover-event-info">';
            bodyHtml += '<strong>' + h.title + '</strong>';
            bodyHtml += '<small>' + h.description + '</small>';
            bodyHtml += '</div></div>';
        });
        
        dayEvents.forEach(e => {
            bodyHtml += '<div class="popover-event user-event" onclick="editEvent(' + e.id + ')" style="cursor:pointer;">';
            bodyHtml += '<div class="popover-event-color" style="background:' + e.color + ';"></div>';
            bodyHtml += '<div class="popover-event-info">';
            bodyHtml += '<strong>' + e.title + '</strong>';
            if (e.event_time) bodyHtml += '<small><i class="fas fa-clock"></i> ' + e.event_time.substring(0,5) + '</small>';
            bodyHtml += '</div></div>';
        });
        
        if (dayEvents.length === 0 && dayHolidays.length === 0) {
            bodyHtml = '<p class="text-muted text-center py-2 mb-0" style="font-size:12px;">Tidak ada event</p>';
        }
        
        document.getElementById('popoverBody').innerHTML = bodyHtml;
        document.getElementById('popoverAddBtn').setAttribute('data-date', dateStr);
        
        // Position popover near click (viewport-based so it's never clipped)
        const rect = evt.currentTarget.getBoundingClientRect();
        popover.style.display = 'block';
        popover.style.visibility = 'hidden'; // measure safely before final placement
        popover.style.position = 'fixed';
        
        let left = rect.left + rect.width / 2;
        let top = rect.bottom + 8;
        
        const popW = popover.offsetWidth;
        const popH = popover.offsetHeight;
        const margin = 14;
        const viewW = window.innerWidth;
        const viewH = window.innerHeight;
        
        // Horizontal clamp (accounting for transform: translateX(-50%))
        if (left + popW / 2 > viewW - margin) left = viewW - popW / 2 - margin;
        if (left - popW / 2 < margin) left = popW / 2 + margin;
        
        // Vertical clamp + flip above if needed
        if (top + popH > viewH - margin) {
            const flipped = rect.top - popH - 8;
            top = Math.max(flipped, margin);
        }
        if (top < margin) top = margin;
        
        popover.style.left = left + 'px';
        popover.style.top = top + 'px';
        popover.style.visibility = 'visible';
    }
};

function closeDayPopover() {
    document.getElementById('dayPopover').style.display = 'none';
}

// Close popover on outside click
document.addEventListener('click', function(e) {
    const popover = document.getElementById('dayPopover');
    if (popover && !popover.contains(e.target) && !e.target.closest('.cal-cell')) {
        popover.style.display = 'none';
    }
});

// EVENT MODAL FUNCTIONS
function openEventModal(dateStr) {
    document.getElementById('eventId').value = '';
    document.getElementById('eventTitle').value = '';
    document.getElementById('eventDate').value = dateStr || new Date().toISOString().split('T')[0];
    document.getElementById('eventTime').value = '';
    document.getElementById('eventType').value = 'jadwal';
    document.getElementById('eventReminder').value = '0';
    document.getElementById('eventDesc').value = '';
    document.querySelector('input[name="eventColor"][value="#1A428A"]').checked = true;
    document.getElementById('eventModalTitle').innerHTML = '<i class="fas fa-calendar-plus me-2"></i>Tambah Event';
    document.getElementById('btnSaveText').textContent = 'Simpan';
    document.getElementById('btnDeleteEvent').style.display = 'none';
    
    new bootstrap.Modal(document.getElementById('eventModal')).show();
}

function addEventFromPopover() {
    const dateStr = document.getElementById('popoverAddBtn').getAttribute('data-date');
    closeDayPopover();
    openEventModal(dateStr);
}

function editEvent(id) {
    const evt = CAL.events.find(e => e.id === id);
    if (!evt) return;
    
    closeDayPopover();
    
    document.getElementById('eventId').value = evt.id;
    document.getElementById('eventTitle').value = evt.title;
    document.getElementById('eventDate').value = evt.event_date;
    document.getElementById('eventTime').value = evt.event_time ? evt.event_time.substring(0, 5) : '';
    document.getElementById('eventType').value = evt.event_type;
    document.getElementById('eventReminder').value = evt.reminder_minutes;
    document.getElementById('eventDesc').value = evt.description || '';
    
    const colorRadio = document.querySelector('input[name="eventColor"][value="' + evt.color + '"]');
    if (colorRadio) colorRadio.checked = true;
    
    document.getElementById('eventModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Event';
    document.getElementById('btnSaveText').textContent = 'Update';
    document.getElementById('btnDeleteEvent').style.display = '';
    
    new bootstrap.Modal(document.getElementById('eventModal')).show();
}

async function saveEvent() {
    const id = document.getElementById('eventId').value;
    const title = document.getElementById('eventTitle').value.trim();
    const date = document.getElementById('eventDate').value;
    
    if (!title || !date) {
        alert('Judul dan tanggal harus diisi!');
        return;
    }
    
    const payload = {
        title: title,
        event_date: date,
        event_time: document.getElementById('eventTime').value || null,
        event_type: document.getElementById('eventType').value,
        color: document.querySelector('input[name="eventColor"]:checked').value,
        reminder_minutes: parseInt(document.getElementById('eventReminder').value),
        description: document.getElementById('eventDesc').value.trim(),
        is_all_day: !document.getElementById('eventTime').value ? 1 : 0
    };
    
    if (id) payload.id = parseInt(id);
    
    try {
        const res = await fetch('api-calendar.php', {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
            CAL.load();
            showToast(data.message, 'success');
        } else {
            alert(data.message || 'Gagal menyimpan');
        }
    } catch(e) {
        alert('Error: ' + e.message);
    }
}

async function deleteEvent() {
    const id = document.getElementById('eventId').value;
    if (!id || !confirm('Yakin ingin menghapus event ini?')) return;
    
    try {
        const res = await fetch('api-calendar.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id) })
        });
        const data = await res.json();
        
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
            CAL.load();
            showToast(data.message, 'success');
        } else {
            alert(data.message || 'Gagal menghapus');
        }
    } catch(e) {
        alert('Error: ' + e.message);
    }
}

// Simple toast notification
function showToast(msg, type) {
    const toast = document.createElement('div');
    toast.className = 'dashboard-toast ' + type;
    toast.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 50);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 3000);
}

// Initialize calendar after all resources loaded
function initCalendar() {
    if (typeof CAL !== 'undefined' && document.getElementById('calBody')) {
        try {
            CAL.init();
        } catch(e) {
            console.error('Calendar init error:', e);
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCalendar);
} else {
    initCalendar();
}
</script>

<?php
// Include footer
include 'includes/footer.php';

// Tutup koneksi
mysqli_close($conn);
?>
