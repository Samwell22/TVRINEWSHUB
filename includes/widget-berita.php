<?php
/**
 * Widget Berita Nasional/Internasional untuk Sidebar
 * 
 * Usage:
 *   include 'includes/widget-berita.php';
 *   renderBeritaWidget('indonesia'); // atau 'international' atau 'breaking'
 */

function renderBeritaWidget($category = 'indonesia') {
    global $conn;
    
    // Get settings
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    
    // Check if widget enabled
    $widget_key = '';
    switch ($category) {
        case 'indonesia':
            $widget_key = 'widget_berita_nasional';
            $title = 'Berita Nasional';
            $icon = 'fa-flag';
            $color = '#2980b9';
            break;
        case 'international':
            $widget_key = 'widget_berita_internasional';
            $title = 'Berita Internasional';
            $icon = 'fa-globe-americas';
            $color = '#27ae60';
            break;
        case 'breaking':
            $widget_key = 'widget_breaking_news';
            $title = 'Breaking News';
            $icon = 'fa-bolt';
            $color = '#e74c3c';
            break;
        default:
            return;
    }
    
    $stmt->bind_param("s", $widget_key);
    $stmt->execute();
    $result = $stmt->get_result();
    $enabled = '1';
    if ($row = $result->fetch_assoc()) {
        $enabled = $row['setting_value'];
    }
    
    if ($enabled !== '1') {
        return;
    }
    
    // Get items count
    $widget_count_key = 'berita_nasional_per_widget';
    $stmt->bind_param("s", $widget_count_key);
    $stmt->execute();
    $result = $stmt->get_result();
    $items_count = 5;
    if ($row = $result->fetch_assoc()) {
        $items_count = (int)$row['setting_value'];
    }
    
    $widget_id = 'widget-berita-' . $category;
    ?>
    
    <div class="card shadow-sm border-0 mb-4 berita-widget">
        <div class="card-header" style="background-color: <?= $color ?>; color: white;">
            <h5 class="mb-0 fw-bold">
                <i class="fas <?= $icon ?> me-2"></i>
                <?= $title ?>
            </h5>
        </div>
        <div class="card-body p-0">
            
            <div id="<?= $widget_id ?>-loading" class="text-center py-3">
                <div class="spinner-border spinner-border-sm" role="status" style="color: <?= $color ?>;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <small class="text-muted d-block mt-2">Memuat...</small>
            </div>
            
            <div id="<?= $widget_id ?>-container" style="display: none;">
                <!-- Will be filled by JavaScript -->
            </div>
            
            <div id="<?= $widget_id ?>-error" class="p-3" style="display: none;">
                <small class="text-muted">
                    <i class="fas fa-exclamation-circle me-1"></i> 
                    <span id="<?= $widget_id ?>-error-msg"></span>
                </small>
            </div>
            
        </div>
        <div class="card-footer bg-light text-center">
            <a href="<?= SITE_URL ?>berita-nasional.php" class="small text-decoration-none" style="color: <?= $color ?>;">
                Lihat semua <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
    
    <script>
    (function() {
        'use strict';
        
        const widgetId = '<?= $widget_id ?>';
        const category = '<?= $category ?>';
        const itemsCount = <?= $items_count ?>;
        
        async function loadWidget() {
            const loading = document.getElementById(widgetId + '-loading');
            const container = document.getElementById(widgetId + '-container');
            const error = document.getElementById(widgetId + '-error');
            const errorMsg = document.getElementById(widgetId + '-error-msg');
            
            try {
                const response = await fetch('<?= SITE_URL ?>api/newsapi-fetch.php?category=' + category);
                const result = await response.json();
                
                loading.style.display = 'none';
                
                if (!result.success) {
                    error.style.display = 'block';
                    errorMsg.textContent = result.error || 'Gagal memuat data';
                    return;
                }
                
                const articles = result.data.articles;
                
                if (!articles || articles.length === 0) {
                    error.style.display = 'block';
                    errorMsg.textContent = 'Tidak ada berita tersedia';
                    return;
                }
                
                let html = '';
                const itemsToShow = articles.slice(0, itemsCount);
                
                itemsToShow.forEach(article => {
                    const imgUrl = article.urlToImage || 'https://via.placeholder.com/60x60?text=No+Image';
                    
                    html += `
                        <a href="${article.url}" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="berita-widget-item d-flex">
                            <img src="${imgUrl}" 
                                 alt="${escapeHtml(article.title)}" 
                                 class="item-image me-3 flex-shrink-0"
                                 onerror="this.src='https://via.placeholder.com/60x60?text=No+Image'">
                            <div class="flex-grow-1">
                                <div class="item-title">${escapeHtml(article.title)}</div>
                                <div class="item-source">
                                    <i class="fas fa-newspaper me-1"></i>
                                    ${escapeHtml(article.source.name)}
                                </div>
                            </div>
                        </a>
                    `;
                });
                
                container.innerHTML = html;
                container.style.display = 'block';
                
            } catch (err) {
                loading.style.display = 'none';
                error.style.display = 'block';
                errorMsg.textContent = 'Terjadi kesalahan';
                console.error('Widget berita error:', err);
            }
        }
        
        // escapeHtml() is defined globally in main.js
        
        // Initialize
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadWidget);
        } else {
            loadWidget();
        }
    })();
    </script>
    
    <?php
}
?>
