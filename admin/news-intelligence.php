<?php
/**
 * Admin: News Intelligence Dashboard
 */

require_once '../config/config.php';
require_once 'auth.php';
requireLogin();

$conn = getDBConnection();
$logged_in_user = getLoggedInUser();
$isAdmin = ($logged_in_user['role'] === 'admin');

$page_title = 'Intelijen Berita';
$page_heading = 'Intelijen Berita';
$breadcrumbs = ['Dashboard' => 'dashboard.php', 'Intelijen Berita' => null];

include 'includes/header.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- Intel Banner -->
<div class="intel-banner">
    <div class="intel-banner-content">
        <div>
            <h2 class="intel-banner-title">
                <i class="fas fa-satellite-dish"></i> News Intelligence Center
            </h2>
            <p class="intel-banner-sub">Monitoring real-time ANTARA News Sulawesi Utara &bull; 27 RSS Feed &bull; Analisis Otomatis</p>
        </div>
        <div class="intel-banner-actions">
            <div class="intel-fetch-status" id="fetchStatus">
                <i class="fas fa-circle text-muted"></i>
                <span>Memeriksa status...</span>
            </div>
            <button class="btn btn-intel-refresh" id="btnRefreshData" onclick="collectRSS()">
                <i class="fas fa-sync-alt"></i> Refresh Data
            </button>
        </div>
    </div>
</div>

<!-- KPI Cards Row -->
<div class="row g-3 mb-4" id="kpiRow">
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card--info">
            <div class="stat-indicator"></div>
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Hari Ini</div>
                    <div class="stat-value" id="kpiToday">-</div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card--success">
            <div class="stat-indicator"></div>
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Minggu Ini</div>
                    <div class="stat-value" id="kpiWeek">-</div>
                </div>
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card--warning">
            <div class="stat-indicator"></div>
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Belum Dibaca</div>
                    <div class="stat-value" id="kpiUnread">-</div>
                </div>
                <div class="stat-icon"><i class="fas fa-inbox"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-card--purple">
            <div class="stat-indicator"></div>
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="stat-label">Ide Tersimpan</div>
                    <div class="stat-value" id="kpiBookmarked">-</div>
                </div>
                <div class="stat-icon"><i class="fas fa-bookmark"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Sub KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="intel-mini-card">
            <div class="intel-mini-icon" style="background:#DBEAFE;color:#2563EB;"><i class="fas fa-database"></i></div>
            <div class="intel-mini-body">
                <span class="intel-mini-value" id="kpiTotal">-</span>
                <span class="intel-mini-label">Total Artikel</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="intel-mini-card">
            <div class="intel-mini-icon" style="background:#FEE2E2;color:#DC2626;"><i class="fas fa-star"></i></div>
            <div class="intel-mini-body">
                <span class="intel-mini-value" id="kpiTopNews">-</span>
                <span class="intel-mini-label">Top News</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="intel-mini-card">
            <div class="intel-mini-icon" style="background:#D1FAE5;color:#059669;"><i class="fas fa-eye"></i></div>
            <div class="intel-mini-body">
                <span class="intel-mini-value" id="kpiRead">-</span>
                <span class="intel-mini-label">Sudah Dibaca</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="intel-mini-card">
            <div class="intel-mini-icon" style="background:#FEF3C7;color:#D97706;"><i class="fas fa-map-marked-alt"></i></div>
            <div class="intel-mini-body">
                <span class="intel-mini-value" id="kpiRegions">-</span>
                <span class="intel-mini-label">Wilayah Aktif Hari Ini</span>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- Trend Line Chart -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-chart-area text-primary"></i> Tren Berita Harian</h5>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary active" onclick="loadTrend(14, this)">14 Hari</button>
                    <button class="btn btn-outline-primary" onclick="loadTrend(30, this)">30 Hari</button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="trendChart" height="280"></canvas>
            </div>
        </div>
    </div>

    <!-- Category Doughnut -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie text-success"></i> Distribusi Kategori</h5>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="categoryChart" height="280"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Region + Intelligence Row -->
<div class="row g-4 mb-4">
    <!-- Region Bar Chart -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-map text-warning"></i> Distribusi per Wilayah</h5>
            </div>
            <div class="card-body">
                <canvas id="regionChart" height="360"></canvas>
            </div>
        </div>
    </div>

    <!-- Intelligence Panel -->
    <div class="col-lg-5">
        <!-- Spike Detection -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-bolt text-danger"></i> Deteksi Lonjakan</h5>
            </div>
            <div class="card-body p-0" id="spikeContainer">
                <div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div> Menganalisis...</div>
            </div>
        </div>

        <!-- Top Keywords -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-tags text-info"></i> Kata Kunci Trending</h5>
                <span class="badge bg-light text-dark" id="kwPeriod">7 hari</span>
            </div>
            <div class="card-body" id="keywordContainer">
                <div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Top News Section -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="fas fa-fire text-danger"></i> Top News ANTARA</h5>
        <span class="badge bg-danger" id="topNewsBadge">0</span>
    </div>
    <div class="card-body p-0" id="topNewsContainer">
        <div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div> Memuat top news...</div>
    </div>
</div>

<!-- Article Management Section -->
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5><i class="fas fa-newspaper text-primary"></i> Semua Artikel</h5>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <!-- Filters -->
            <select class="form-select form-select-sm intel-filter" id="filterStatus" onchange="loadArticles(1)" style="width:auto;">
                <option value="">Semua Status</option>
                <option value="unread">📩 Belum Dibaca</option>
                <option value="read">👁️ Sudah Dibaca</option>
                <option value="bookmarked">🔖 Ide Tersimpan</option>
            </select>
            <select class="form-select form-select-sm intel-filter" id="filterCategory" onchange="loadArticles(1)" style="width:auto;">
                <option value="">Semua Kategori</option>
            </select>
            <select class="form-select form-select-sm intel-filter" id="filterRegion" onchange="loadArticles(1)" style="width:auto;">
                <option value="">Semua Wilayah</option>
            </select>
            <div class="input-group input-group-sm" style="width:200px;">
                <input type="text" class="form-control" id="filterSearch" placeholder="Cari judul..." 
                       onkeydown="if(event.key==='Enter')loadArticles(1)">
                <button class="btn btn-outline-secondary" onclick="loadArticles(1)"><i class="fas fa-search"></i></button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 intel-table">
                <thead>
                    <tr>
                        <th style="width:45%">Judul</th>
                        <th>Kategori</th>
                        <th>Wilayah</th>
                        <th>Tanggal</th>
                        <?php if ($isAdmin): ?><th style="width:100px" class="text-center">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="articleTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div> Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted" id="articleMeta">Menampilkan 0 dari 0</small>
        <nav id="articlePagination"></nav>
    </div>
</div>

<!-- No modal needed for simple toggle actions -->

<script>
// GLOBAL CONFIG
const API_BASE = '<?= SITE_URL ?>api/';
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
const USER_ID = <?= (int)$logged_in_user['id'] ?>;
let trendChartInstance = null;
let categoryChartInstance = null;
let regionChartInstance = null;
let currentArticlePage = 1;

// INITIALIZATION
document.addEventListener('DOMContentLoaded', function() {
    checkAndAutoFetch();
    loadOverview();
    loadTrend(14);
    loadCategories();
    loadRegions();
    loadSpikes();
    loadKeywords();
    loadTopNews();
    loadArticles(1);
    populateFilterDropdowns();
});

// AUTO-FETCH — Check staleness and collect if needed
async function checkAndAutoFetch() {
    try {
        const resp = await fetch(API_BASE + 'rss-analytics.php?action=last-fetch');
        const data = await resp.json();
        
        if (data.success) {
            if (data.stale) {
                updateFetchStatus('stale', data.lastFetch);
                // Auto-collect in background
                collectRSS(true);
            } else {
                updateFetchStatus('fresh', data.lastFetch);
            }
        }
    } catch (e) {
        updateFetchStatus('error', null);
    }
}

function updateFetchStatus(state, lastFetch) {
    const el = document.getElementById('fetchStatus');
    const timeStr = lastFetch ? timeAgo(lastFetch) : 'Belum pernah';
    
    if (state === 'fresh') {
        el.innerHTML = '<i class="fas fa-circle" style="color:#059669;font-size:8px;"></i> <span>Data segar &bull; ' + timeStr + '</span>';
    } else if (state === 'stale') {
        el.innerHTML = '<i class="fas fa-circle" style="color:#D97706;font-size:8px;"></i> <span>Memperbarui data...</span>';
    } else if (state === 'collecting') {
        el.innerHTML = '<div class="spinner-border spinner-border-sm text-warning" style="width:12px;height:12px;"></div> <span>Mengumpulkan dari 27 feed...</span>';
    } else if (state === 'done') {
        el.innerHTML = '<i class="fas fa-circle" style="color:#059669;font-size:8px;"></i> <span>Data segar &bull; ' + timeStr + '</span>';
    } else {
        el.innerHTML = '<i class="fas fa-circle" style="color:#DC2626;font-size:8px;"></i> <span>Error</span>';
    }
}

// RSS COLLECTOR — Trigger batch collection
async function collectRSS(silent = false) {
    const btn = document.getElementById('btnRefreshData');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Mengumpulkan...';
    updateFetchStatus('collecting', null);

    try {
        const resp = await fetch(API_BASE + 'antara-rss-collector.php');
        const data = await resp.json();

        if (data.success) {
            updateFetchStatus('done', data.timestamp);
            if (!silent) {
                showToast('success', `Data diperbarui: ${data.stats.articles_new} artikel baru, ${data.stats.feeds_fetched}/${data.feedsTotal} feed berhasil (${data.duration})`);
            }
            // Reload all dashboard data
            loadOverview();
            loadTrend(14);
            loadCategories();
            loadRegions();
            loadSpikes();
            loadKeywords();
            loadTopNews();
            loadArticles(1);
        } else {
            updateFetchStatus('error', null);
            if (!silent) showToast('danger', 'Gagal mengumpulkan data: ' + (data.error || ''));
        }
    } catch (e) {
        updateFetchStatus('error', null);
        if (!silent) showToast('danger', 'Network error saat mengumpulkan data');
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Data';
}

// OVERVIEW KPIs
async function loadOverview() {
    try {
        const resp = await fetch(API_BASE + 'rss-analytics.php?action=overview');
        const json = await resp.json();
        if (!json.success) return;
        const d = json.data;

        document.getElementById('kpiToday').textContent = d.today;
        document.getElementById('kpiWeek').textContent = d.week;
        document.getElementById('kpiUnread').textContent = d.status.unread;
        document.getElementById('kpiBookmarked').textContent = d.status.bookmarked;
        document.getElementById('kpiTotal').textContent = d.total;
        document.getElementById('kpiTopNews').textContent = d.topNews;
        document.getElementById('kpiRead').textContent = d.status.read;
        document.getElementById('kpiRegions').textContent = d.regionsToday;
    } catch (e) {}
}

// TREND LINE CHART
async function loadTrend(days, btnEl) {
    // Handle button active state
    if (btnEl) {
        btnEl.closest('.btn-group').querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
        btnEl.classList.add('active');
    }

    try {
        const resp = await fetch(API_BASE + 'rss-analytics.php?action=trend&days=' + days);
        const json = await resp.json();
        if (!json.success) return;

        const ctx = document.getElementById('trendChart').getContext('2d');
        if (trendChartInstance) trendChartInstance.destroy();

        const gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.15)');
        gradient.addColorStop(1, 'rgba(37, 99, 235, 0)');

        trendChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: json.data.labels,
                datasets: [{
                    label: 'Jumlah Berita',
                    data: json.data.values,
                    borderColor: '#2563EB',
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#2563EB',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1E293B',
                        titleFont: { weight: '600' },
                        padding: 12,
                        cornerRadius: 8,
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 }, maxTicksLimit: 12 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { font: { size: 11 }, stepSize: 1 }
                    }
                }
            }
        });
    } catch (e) {}
}

// CATEGORY DOUGHNUT CHART
async function loadCategories() {
    try {
        const resp = await fetch(API_BASE + 'rss-analytics.php?action=categories&days=30');
        const json = await resp.json();
        if (!json.success || !json.data.length) return;

        const colors = [
            '#2563EB', '#059669', '#D97706', '#DC2626', '#7C3AED',
            '#0891B2', '#DB2777', '#65A30D', '#EA580C', '#4F46E5',
            '#0D9488', '#CA8A04', '#9333EA', '#E11D48', '#0284C7'
        ];

        const ctx = document.getElementById('categoryChart').getContext('2d');
        if (categoryChartInstance) categoryChartInstance.destroy();

        categoryChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: json.data.map(d => d.label),
                datasets: [{
                    data: json.data.map(d => d.value),
                    backgroundColor: colors.slice(0, json.data.length),
                    borderWidth: 2,
                    borderColor: '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 11 }, padding: 12, usePointStyle: true, pointStyleWidth: 8 }
                    },
                    tooltip: {
                        backgroundColor: '#1E293B',
                        padding: 10,
                        cornerRadius: 8,
                    }
                }
            }
        });

        // Populate filter dropdown
        const sel = document.getElementById('filterCategory');
        json.data.forEach(d => {
            if (!sel.querySelector('option[value="' + d.label + '"]')) {
                sel.innerHTML += '<option value="' + escapeHtml(d.label) + '">' + escapeHtml(d.label) + '</option>';
            }
        });
    } catch (e) {}
}

// REGION HORIZONTAL BAR CHART
async function loadRegions() {
    try {
        const resp = await fetch(API_BASE + 'rss-analytics.php?action=regions&days=30');
        const json = await resp.json();
        if (!json.success || !json.data.length) return;

        const ctx = document.getElementById('regionChart').getContext('2d');
        if (regionChartInstance) regionChartInstance.destroy();

        const data = json.data.slice(0, 18);
        const maxVal = Math.max(...data.map(d => d.value));

        regionChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.label),
                datasets: [{
                    label: 'Jumlah Berita',
                    data: data.map(d => d.value),
                    backgroundColor: data.map((d, i) => {
                        const ratio = d.value / maxVal;
                        if (ratio > 0.8) return '#1A428A';
                        if (ratio > 0.5) return '#2563EB';
                        if (ratio > 0.3) return '#60A5FA';
                        return '#93C5FD';
                    }),
                    borderRadius: 6,
                    barThickness: 18,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1E293B',
                        padding: 10,
                        cornerRadius: 8,
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { font: { size: 11 }, stepSize: 1 }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 11, weight: '500' } }
                    }
                }
            }
        });

        // Populate filter dropdown
        const sel = document.getElementById('filterRegion');
        json.data.forEach(d => {
            if (!sel.querySelector('option[value="' + d.label + '"]')) {
                sel.innerHTML += '<option value="' + escapeHtml(d.label) + '">' + escapeHtml(d.label) + '</option>';
            }
        });
    } catch (e) {}
}

// SPIKE DETECTION
async function loadSpikes() {
    const container = document.getElementById('spikeContainer');
    try {
        const resp = await fetch(API_BASE + 'rss-analytics.php?action=spikes');
        const json = await resp.json();
        if (!json.success) { container.innerHTML = '<div class="text-center text-muted py-3">Belum ada data</div>'; return; }

        const d = json.data;
        let html = '';

        // Overall status
        const severityMap = { high: ['🔴', 'bg-danger'], medium: ['🟡', 'bg-warning'], low: ['🟠', 'bg-info'], normal: ['🟢', 'bg-success'] };
        const [emoji, badge] = severityMap[d.overallSeverity] || severityMap.normal;

        html += '<div class="intel-spike-overall">';
        html += '<div class="d-flex align-items-center gap-2 mb-1">';
        html += '<span class="badge ' + badge + '">' + emoji + ' ' + d.overallSeverity.toUpperCase() + '</span>';
        html += '<span class="fw-semibold">' + d.todayTotal + ' berita hari ini</span>';
        html += '</div>';
        html += '<small class="text-muted">Rata-rata harian: ' + d.dailyAverage + ' &bull; Rasio: ' + d.overallRatio + 'x</small>';
        html += '</div>';

        // Category spikes
        if (d.categorySpikes.length > 0) {
            html += '<div class="intel-spike-section"><div class="intel-spike-label">Lonjakan Kategori</div>';
            d.categorySpikes.forEach(s => {
                const sev = s.severity === 'high' ? '🔴' : (s.severity === 'medium' ? '🟡' : '🟠');
                html += '<div class="intel-spike-item">';
                html += '<span>' + sev + ' <strong>' + escapeHtml(s.category) + '</strong></span>';
                html += '<span class="text-muted">' + s.today + ' hari ini (avg ' + s.avgDaily + ') &bull; <strong>' + s.ratio + 'x</strong></span>';
                html += '</div>';
            });
            html += '</div>';
        }

        // Region spikes
        if (d.regionSpikes.length > 0) {
            html += '<div class="intel-spike-section"><div class="intel-spike-label">Lonjakan Wilayah</div>';
            d.regionSpikes.forEach(s => {
                const sev = s.severity === 'high' ? '🔴' : (s.severity === 'medium' ? '🟡' : '🟠');
                html += '<div class="intel-spike-item">';
                html += '<span>' + sev + ' <strong>' + escapeHtml(s.region) + '</strong></span>';
                html += '<span class="text-muted">' + s.today + ' hari ini (avg ' + s.avgDaily + ') &bull; <strong>' + s.ratio + 'x</strong></span>';
                html += '</div>';
            });
            html += '</div>';
        }

        if (d.categorySpikes.length === 0 && d.regionSpikes.length === 0) {
            html += '<div class="text-center text-muted py-2"><i class="fas fa-check-circle text-success"></i> Tidak ada lonjakan terdeteksi</div>';
        }

        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<div class="text-center text-muted py-3">Gagal memuat data spike</div>';
    }
}

// KEYWORDS
async function loadKeywords() {
    const container = document.getElementById('keywordContainer');
    try {
        const resp = await fetch(API_BASE + 'rss-analytics.php?action=keywords&days=7');
        const json = await resp.json();
        if (!json.success || !json.data.length) {
            container.innerHTML = '<div class="text-center text-muted py-3">Belum ada data keyword</div>';
            return;
        }

        const maxCount = json.data[0].count;
        let html = '<div class="intel-keywords-cloud">';
        json.data.forEach((kw, i) => {
            const size = 0.7 + (kw.count / maxCount) * 0.5;
            const opacity = 0.5 + (kw.count / maxCount) * 0.5;
            const isTop = i < 5;
            html += '<span class="intel-kw-tag' + (isTop ? ' intel-kw-top' : '') + '" '
                + 'style="font-size:' + size + 'rem;opacity:' + opacity + ';" '
                + 'title="' + kw.count + ' kali">'
                + escapeHtml(kw.word) + '<sup>' + kw.count + '</sup></span> ';
        });
        html += '</div>';
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<div class="text-center text-muted py-3">Error</div>';
    }
}

// TOP NEWS (read-only — no action buttons)
async function loadTopNews() {
    const container = document.getElementById('topNewsContainer');
    try {
        const resp = await fetch(API_BASE + 'rss-analytics.php?action=top-news&limit=8');
        const json = await resp.json();
        if (!json.success || !json.data.length) {
            container.innerHTML = '<div class="text-center text-muted py-4">Belum ada top news</div>';
            document.getElementById('topNewsBadge').textContent = '0';
            return;
        }

        document.getElementById('topNewsBadge').textContent = json.data.length;
        let html = '<div class="intel-top-news-list">';
        json.data.forEach(article => {
            const img = article.image_url || '<?= SITE_URL ?>assets/images/default-news.jpg';
            const bookmarkIcon = article.is_bookmarked == 1 ? 'fas fa-bookmark text-warning' : 'far fa-bookmark text-muted';
            html += '<div class="intel-top-news-item">';
            html += '<img src="' + escapeHtml(img) + '" class="intel-top-news-img" onerror="this.src=\'<?= SITE_URL ?>assets/images/default-news.jpg\'" alt="">';
            html += '<div class="intel-top-news-body">';
            html += '<a href="' + escapeHtml(article.url) + '" target="_blank" rel="noopener" class="intel-top-news-title">' + escapeHtml(article.title) + '</a>';
            html += '<div class="intel-top-news-meta">';
            html += '<span class="badge bg-danger bg-opacity-10 text-danger" style="font-size:0.65rem;">⭐ TOP NEWS</span>';
            if (article.category) html += '<span class="text-muted">' + escapeHtml(article.category) + '</span>';
            if (article.region) html += '<span class="text-muted"><i class="fas fa-map-pin"></i> ' + escapeHtml(article.region) + '</span>';
            html += '<span class="text-muted">' + timeAgo(article.published_at) + '</span>';
            if (article.is_bookmarked == 1) html += '<span class="badge bg-warning bg-opacity-10 text-warning"><i class="fas fa-bookmark"></i> Ide</span>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<div class="text-center text-muted py-4">Gagal memuat top news</div>';
    }
}

// ARTICLES TABLE (Dibaca + Simpan Ide)
async function loadArticles(page) {
    currentArticlePage = page || 1;
    const status = document.getElementById('filterStatus').value;
    const category = document.getElementById('filterCategory').value;
    const region = document.getElementById('filterRegion').value;
    const search = document.getElementById('filterSearch').value;

    let url = API_BASE + 'rss-analytics.php?action=articles&page=' + currentArticlePage + '&limit=15';
    if (status) url += '&status=' + encodeURIComponent(status);
    if (category) url += '&category=' + encodeURIComponent(category);
    if (region) url += '&region=' + encodeURIComponent(region);
    if (search) url += '&q=' + encodeURIComponent(search);

    const tbody = document.getElementById('articleTableBody');
    const colSpan = IS_ADMIN ? 5 : 4;
    tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></td></tr>';

    try {
        const resp = await fetch(url);
        const json = await resp.json();
        if (!json.success) { tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center text-muted py-4">Error</td></tr>'; return; }

        if (!json.data.length) {
            tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center text-muted py-4"><i class="fas fa-inbox"></i> Tidak ada artikel ditemukan</td></tr>';
            document.getElementById('articleMeta').textContent = 'Menampilkan 0 dari 0';
            document.getElementById('articlePagination').innerHTML = '';
            return;
        }

        let rows = '';
        json.data.forEach(a => {
            const isRead = a.is_read == 1;
            const isBookmarked = a.is_bookmarked == 1;
            const topBadge = a.is_top_news == 1 ? ' <span class="badge bg-danger" style="font-size:0.55rem;">TOP</span>' : '';
            const readClass = isRead ? ' intel-row-read' : '';
            
            rows += '<tr class="' + readClass + '">';
            rows += '<td><div class="d-flex align-items-start gap-2">';
            if (a.image_url) {
                rows += '<img src="' + escapeHtml(a.image_url) + '" class="intel-art-thumb" onerror="this.style.display=\'none\'" alt="">';
            }
            rows += '<div>';
            rows += '<a href="' + escapeHtml(a.url) + '" target="_blank" rel="noopener" class="intel-art-title">' + escapeHtml(a.title) + '</a>' + topBadge;
            if (isBookmarked) rows += ' <i class="fas fa-bookmark text-warning" style="font-size:0.7rem;" title="Ide Tersimpan"></i>';
            if (isRead) rows += ' <i class="fas fa-eye text-success" style="font-size:0.65rem;" title="Sudah Dibaca"></i>';
            rows += '</div>';
            rows += '</div></td>';
            rows += '<td><span class="badge bg-light text-dark" style="font-size:0.7rem;">' + escapeHtml(a.category || '-') + '</span></td>';
            rows += '<td><span style="font-size:0.78rem;">' + escapeHtml(a.region || '-') + '</span></td>';
            rows += '<td><span style="font-size:0.78rem;" title="' + escapeHtml(a.published_at) + '">' + formatDate(a.published_at) + '</span></td>';
            if (IS_ADMIN) {
                rows += '<td class="text-center">';
                rows += '<div class="btn-group btn-group-sm">';
                // Tandai Dibaca toggle
                rows += '<button class="btn btn-' + (isRead ? '' : 'outline-') + 'primary btn-sm" title="' + (isRead ? 'Tandai Belum Dibaca' : 'Tandai Dibaca') + '" onclick="toggleRead(' + a.id + ')">';
                rows += '<i class="fas fa-eye' + (isRead ? '' : '-slash') + '"></i>';
                rows += '</button>';
                // Simpan Ide toggle
                rows += '<button class="btn btn-' + (isBookmarked ? '' : 'outline-') + 'warning btn-sm" title="' + (isBookmarked ? 'Hapus dari Ide' : 'Simpan Ide') + '" onclick="toggleBookmark(' + a.id + ')">';
                rows += '<i class="' + (isBookmarked ? 'fas' : 'far') + ' fa-bookmark"></i>';
                rows += '</button>';
                rows += '</div>';
                rows += '</td>';
            }
            rows += '</tr>';
        });
        tbody.innerHTML = rows;

        // Pagination
        const m = json.meta;
        document.getElementById('articleMeta').textContent = 'Menampilkan ' + json.data.length + ' dari ' + m.total + ' artikel (Hal. ' + m.page + '/' + m.totalPages + ')';
        renderPagination(m.page, m.totalPages);
    } catch (e) {
        const colSpan = IS_ADMIN ? 5 : 4;
        tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center text-muted py-4">Network error</td></tr>';
    }
}

function renderPagination(current, total) {
    if (total <= 1) { document.getElementById('articlePagination').innerHTML = ''; return; }
    let html = '<ul class="pagination pagination-sm mb-0">';
    if (current > 1) html += '<li class="page-item"><a class="page-link" href="#" onclick="loadArticles(' + (current-1) + ');return false;">&laquo;</a></li>';
    
    let startP = Math.max(1, current - 2);
    let endP = Math.min(total, current + 2);
    for (let i = startP; i <= endP; i++) {
        html += '<li class="page-item ' + (i === current ? 'active' : '') + '"><a class="page-link" href="#" onclick="loadArticles(' + i + ');return false;">' + i + '</a></li>';
    }
    
    if (current < total) html += '<li class="page-item"><a class="page-link" href="#" onclick="loadArticles(' + (current+1) + ');return false;">&raquo;</a></li>';
    html += '</ul>';
    document.getElementById('articlePagination').innerHTML = html;
}

// TOGGLE READ / BOOKMARK
async function toggleRead(id) {
    try {
        const resp = await fetch(API_BASE + 'rss-analytics.php?action=update-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, action: 'toggle-read' })
        });
        const json = await resp.json();
        if (json.success) {
            const label = json.is_read ? 'Ditandai dibaca' : 'Ditandai belum dibaca';
            showToast('success', label);
            loadArticles(currentArticlePage);
            loadOverview();
        }
    } catch (e) {
        showToast('danger', 'Gagal mengubah status baca');
    }
}

async function toggleBookmark(id) {
    try {
        const resp = await fetch(API_BASE + 'rss-analytics.php?action=update-status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, action: 'toggle-bookmark' })
        });
        const json = await resp.json();
        if (json.success) {
            const label = json.is_bookmarked ? 'Disimpan sebagai ide' : 'Dihapus dari ide';
            showToast('success', label);
            loadArticles(currentArticlePage);
            loadOverview();
            loadTopNews();
        }
    } catch (e) {
        showToast('danger', 'Gagal mengubah bookmark');
    }
}

// POPULATE FILTER DROPDOWNS
async function populateFilterDropdowns() {
    // Already populated by loadCategories and loadRegions
}

// UTILITIES
function getStatusBadge(article) {
    let badges = '';
    if (article.is_bookmarked == 1) badges += '<span class="badge bg-warning bg-opacity-75"><i class="fas fa-bookmark"></i> Ide</span> ';
    if (article.is_read == 1) badges += '<span class="badge bg-success bg-opacity-75"><i class="fas fa-eye"></i></span> ';
    return badges || '<span class="badge bg-light text-muted">Baru</span>';
}

function escapeHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

function timeAgo(dateStr) {
    if (!dateStr) return '-';
    const now = new Date();
    const then = new Date(dateStr.replace(' ', 'T'));
    const diffSec = Math.floor((now - then) / 1000);
    if (diffSec < 60) return 'Baru saja';
    if (diffSec < 3600) return Math.floor(diffSec / 60) + ' menit lalu';
    if (diffSec < 86400) return Math.floor(diffSec / 3600) + ' jam lalu';
    if (diffSec < 604800) return Math.floor(diffSec / 86400) + ' hari lalu';
    return formatDate(dateStr);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr.replace(' ', 'T'));
    const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    return d.getDate() + ' ' + months[d.getMonth()] + ' ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
}

function showToast(type, message) {
    const container = document.querySelector('.admin-content');
    if (!container) return;
    const alert = document.createElement('div');
    alert.className = 'alert alert-' + type + ' alert-dismissible fade show intel-toast';
    alert.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    container.insertBefore(alert, container.firstChild);
    setTimeout(() => { if (alert.parentNode) alert.remove(); }, 5000);
}
</script>

<?php include 'includes/footer.php'; ?>
