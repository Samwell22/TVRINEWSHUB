<?php
/**
 * Portal Berita Terkini - API Feeds + ANTARA RSS
 * 3 Tabs: Sulawesi Utara | Nasional | Internasional
 */

require_once 'config/config.php';
$conn = getDBConnection();

$enable_indonesia = getSetting($conn, 'widget_berita_nasional', '1');
$enable_international = getSetting($conn, 'widget_berita_internasional', '1');

$page_title = "Portal Berita Terkini - SULUT NEWS HUB";
$active_menu = 'berita-nasional';

require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container-fluid">
        <span class="section-label section-label-light">LIVE FEED</span>
        <h1><i class="fas fa-newspaper me-2"></i> Portal Berita Terkini</h1>
        <p>Berita terkini dari berbagai sumber terpercaya</p>
    </div>
</div>

<div class="container-fluid content-wrapper">
    
    <!-- Tabs: 3 tabs -->
    <ul class="nav nav-pills-custom mb-4 justify-content-center" id="newsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-sulut" data-bs-toggle="pill" data-bs-target="#content-sulut" data-feed="sulut" type="button" role="tab">
                <i class="fas fa-map-marker-alt me-1"></i> Sulawesi Utara
            </button>
        </li>
        <?php if ($enable_indonesia === '1'): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-indonesia" data-bs-toggle="pill" data-bs-target="#content-indonesia" data-feed="nasional" type="button" role="tab">
                <i class="fas fa-flag me-1"></i> Nasional
            </button>
        </li>
        <?php endif; ?>
        <?php if ($enable_international === '1'): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-international" data-bs-toggle="pill" data-bs-target="#content-international" data-feed="internasional" type="button" role="tab">
                <i class="fas fa-globe-americas me-1"></i> Internasional
            </button>
        </li>
        <?php endif; ?>
    </ul>
    
    <div class="tab-content" id="newsTabContent">
        <!-- Sulawesi Utara (ANTARA RSS) -->
        <div class="tab-pane fade show active" id="content-sulut" role="tabpanel">
            <div class="news-container" data-feed="sulut">
                <div class="loading-spinner">
                    <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-2 text-muted">Memuat berita Sulawesi Utara...</p>
                </div>
            </div>
        </div>

        <?php if ($enable_indonesia === '1'): ?>
        <div class="tab-pane fade" id="content-indonesia" role="tabpanel">
            <div class="news-container" data-feed="nasional">
                <div class="loading-spinner">
                    <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-2 text-muted">Memuat berita nasional...</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($enable_international === '1'): ?>
        <div class="tab-pane fade" id="content-international" role="tabpanel">
            <div class="news-container" data-feed="internasional">
                <div class="loading-spinner">
                    <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-2 text-muted">Memuat berita internasional...</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($enable_indonesia !== '1' && $enable_international !== '1'): ?>
    <div class="empty-state">
        <i class="fas fa-tools"></i>
        <h4>Fitur Dalam Pemeliharaan</h4>
        <p>Fitur berita eksternal sedang tidak tersedia.</p>
    </div>
    <?php endif; ?>
</div>

<script>
let loadedTabs = {};

function renderNewsCards(articles, sourceLabel) {
    if (!articles || articles.length === 0) {
        return '<div class="empty-state"><i class="fas fa-inbox"></i><h4>Tidak Ada Berita</h4><p>Belum ada berita tersedia saat ini.</p></div>';
    }

    let html = '<div class="row g-3">';
    articles.forEach(article => {
        const img = article.urlToImage || article.image_url || '<?= SITE_URL ?>assets/images/default-news.jpg';
        const title = article.title || 'Tanpa Judul';
        const desc = article.description || article.content || '';
        const src = article.source?.name || article.source_name || article.source || sourceLabel || '-';
        const date = article.publishedAtFormatted || article.pubDate || article.publishedAt || '';
        const url = article.url || article.link || '#';
        const category = article.category || '';
        const dateStr = date && !date.includes('N/A') ? date : '';
        
        html += `
        <div class="col-lg-4 col-md-6">
            <div class="api-news-card">
                <div class="card-img-wrapper">
                    <img src="${img}" alt="${escapeHtml(title)}" onerror="this.src='<?= SITE_URL ?>assets/images/default-news.jpg'">
                    <span class="source-badge">${escapeHtml(src).toUpperCase()}</span>
                </div>
                <div class="card-body-inner">
                    <h5>${escapeHtml(title)}</h5>
                    <p>${escapeHtml(desc).substring(0, 150)}${desc.length > 150 ? '...' : ''}</p>
                </div>
                <div class="card-footer-inner">
                    <span class="date-info"><i class="far fa-clock me-1"></i>${escapeHtml(dateStr)}</span>
                    <a href="${url}" target="_blank" rel="noopener" class="read-link">
                        Baca <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
            </div>
        </div>`;
    });
    html += '</div>';
    html += '<div class="text-center mt-4"><small class="text-muted"><i class="fas fa-info-circle me-1"></i> Artikel akan dibuka di situs sumber</small></div>';
    return html;
}

async function loadTab(feedKey) {
    if (loadedTabs[feedKey]) return;
    const container = document.querySelector('.news-container[data-feed="' + feedKey + '"]');
    if (!container) return;

    try {
        let apiUrl, sourceLabel;

        if (feedKey === 'sulut') {
            apiUrl = 'api/antara-rss.php?feed=top-news&limit=12';
            sourceLabel = 'ANTARA News Sulut';
        } else if (feedKey === 'nasional') {
            apiUrl = 'api/newsapi-fetch.php?category=indonesia';
            sourceLabel = 'NewsData';
        } else {
            apiUrl = 'api/newsapi-fetch.php?category=international';
            sourceLabel = 'NewsAPI';
        }

        const resp = await fetch(apiUrl);
        const result = await resp.json();

        if (!result.success) {
            container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h4>Gagal Memuat</h4><p>' + escapeHtml(result.error || 'Tidak tersedia') + '</p></div>';
            return;
        }

        const articles = result.data?.articles || result.articles || [];
        container.innerHTML = renderNewsCards(articles, sourceLabel);
        loadedTabs[feedKey] = true;
    } catch (err) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h4>Error</h4><p>' + escapeHtml(err.message) + '</p></div>';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Load first tab immediately
    loadTab('sulut');
    
    // Lazy load other tabs on click
    document.querySelectorAll('#newsTabs button[data-bs-toggle="pill"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', e => {
            loadTab(e.target.dataset.feed);
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
