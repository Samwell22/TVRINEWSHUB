<?php
/**
 * Widget Cuaca untuk Homepage
 * Menampilkan summary peringatan dini dan prakiraan cuaca hari ini
 */

// Check if widget is enabled
$widget_enabled = getSetting($conn, 'widget_cuaca_homepage', '1');
if ($widget_enabled !== '1') {
    return;
}

$bmkg_enabled_peringatan = getSetting($conn, 'bmkg_enable_peringatan', '1');
$bmkg_enabled_prakiraan = getSetting($conn, 'bmkg_enable_prakiraan', '1');
$default_city = getSetting($conn, 'bmkg_default_city', 'manado');
?>

<div class="card shadow-sm border-0 mb-4 weather-widget">
    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">
                <i class="fas fa-cloud-sun-rain me-2"></i>
                Cuaca Hari Ini
            </h5>
            <a href="<?= SITE_URL ?>cuaca.php" class="btn btn-sm btn-light">
                Selengkapnya <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
    <div class="card-body">
        
        <?php if ($bmkg_enabled_peringatan === '1'): ?>
        <!-- Peringatan Dini Summary -->
        <div id="widgetPeringatanContainer" style="display: none;">
            <div class="alert alert-warning mb-3" id="widgetPeringatanContent">
                <!-- Will be filled by JavaScript -->
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($bmkg_enabled_prakiraan === '1'): ?>
        <!-- Prakiraan Hari Ini -->
        <div id="widgetPrakiraanLoading" class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <small class="text-muted d-block mt-2">Memuat cuaca...</small>
        </div>
        
        <div id="widgetPrakiraanContainer" style="display: none;">
            <div class="d-flex align-items-center mb-3">
                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                <strong id="widgetCityName"><?= ucfirst($default_city) ?></strong>
            </div>
            <div id="widgetPrakiraanContent" class="row g-2">
                <!-- Will be filled by JavaScript -->
            </div>
        </div>
        
        <div id="widgetPrakiraanError" class="alert alert-danger" style="display: none;">
            <small><i class="fas fa-exclamation-circle me-1"></i> <span id="widgetPrakiraanErrorMsg"></span></small>
        </div>
        <?php endif; ?>
        
        <?php if ($bmkg_enabled_peringatan !== '1' && $bmkg_enabled_prakiraan !== '1'): ?>
        <div class="text-center text-muted py-3">
            <i class="fas fa-info-circle"></i>
            <small>Fitur cuaca sedang dalam pemeliharaan</small>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
(function() {
    'use strict';
    
    // Load peringatan summary
    <?php if ($bmkg_enabled_peringatan === '1'): ?>
    async function loadWidgetPeringatan() {
        try {
            const response = await fetch('<?= SITE_URL ?>api/bmkg-peringatan.php');
            const result = await response.json();
            
            if (result.success && result.data.items && result.data.items.length > 0) {
                const container = document.getElementById('widgetPeringatanContainer');
                const content = document.getElementById('widgetPeringatanContent');
                
                const totalPeringatan = result.data.items.length;
                const firstItem = result.data.items[0];
                
                content.innerHTML = `
                    <div class="d-flex align-items-start">
                        <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
                        <div>
                            <strong>${totalPeringatan} Peringatan Aktif</strong>
                            <div class="small mt-1">${escapeHtml(firstItem.title)}</div>
                        </div>
                    </div>
                `;
                container.style.display = 'block';
            }
        } catch (err) {
            // Silent fail for widget
            console.error('Widget peringatan error:', err);
        }
    }
    <?php endif; ?>
    
    // Load prakiraan hari ini (first 4 items)
    <?php if ($bmkg_enabled_prakiraan === '1'): ?>
    async function loadWidgetPrakiraan() {
        const loading = document.getElementById('widgetPrakiraanLoading');
        const container = document.getElementById('widgetPrakiraanContainer');
        const error = document.getElementById('widgetPrakiraanError');
        const content = document.getElementById('widgetPrakiraanContent');
        
        try {
            const response = await fetch('<?= SITE_URL ?>api/bmkg-prakiraan.php?city=<?= $default_city ?>');
            const result = await response.json();
            
            loading.style.display = 'none';
            
            if (!result.success) {
                error.style.display = 'block';
                document.getElementById('widgetPrakiraanErrorMsg').textContent = result.error || 'Gagal memuat data';
                return;
            }
            
            const prakiraan = result.data.prakiraan;
            if (!prakiraan || prakiraan.length === 0) {
                error.style.display = 'block';
                document.getElementById('widgetPrakiraanErrorMsg').textContent = 'Data tidak tersedia';
                return;
            }
            
            // Get first day data (hari ini)
            const hariIni = prakiraan[0].data;
            
            // Show only 3-4 items
            const itemsToShow = hariIni.slice(0, 4);
            
            let html = '';
            itemsToShow.forEach(item => {
                const waktu = item.local_datetime ? 
                    new Date(item.local_datetime).toLocaleTimeString('id-ID', { 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    }) : '';
                
                html += `
                    <div class="col-6">
                        <div class="weather-widget-item text-center">
                            <div class="small text-muted mb-1">${waktu}</div>
                            ${item.image ? 
                                `<img src="${item.image}" alt="${escapeHtml(item.weather_desc)}" class="weather-icon-small mx-auto d-block mb-1">` : 
                                '<i class="fas fa-cloud fa-lg text-secondary mb-1"></i>'
                            }
                            <div class="temp-display">${item.t}°C</div>
                            <div class="small text-truncate">${escapeHtml(item.weather_desc)}</div>
                            <div class="small text-muted">
                                <i class="fas fa-tint"></i> ${item.hu}%
                            </div>
                        </div>
                    </div>
                `;
            });
            
            content.innerHTML = html;
            container.style.display = 'block';
            
        } catch (err) {
            loading.style.display = 'none';
            error.style.display = 'block';
            document.getElementById('widgetPrakiraanErrorMsg').textContent = 'Terjadi kesalahan';
            console.error('Widget prakiraan error:', err);
        }
    }
    <?php endif; ?>
    
    // Utility: escapeHtml() is defined globally in main.js
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($bmkg_enabled_peringatan === '1'): ?>
        loadWidgetPeringatan();
        <?php endif; ?>
        
        <?php if ($bmkg_enabled_prakiraan === '1'): ?>
        loadWidgetPrakiraan();
        <?php endif; ?>
    });
})();
</script>
