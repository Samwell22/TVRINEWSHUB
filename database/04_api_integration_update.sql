-- Migration: API Integration (BMKG + NewsAPI)

USE tvri_sulut_db;

-- API Cache Table

CREATE TABLE IF NOT EXISTS api_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(255) NOT NULL UNIQUE COMMENT 'Unique identifier untuk cache (contoh: bmkg_peringatan, bmkg_prakiraan_71.71, newsapi_id)',
    cache_data LONGTEXT NOT NULL COMMENT 'Data JSON dari API response',
    expires_at DATETIME NOT NULL COMMENT 'Waktu expiry cache',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cache_key (cache_key),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cache untuk API eksternal (BMKG & NewsAPI)';

-- Settings: API Configuration

-- NewsAPI.org Configuration
INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('newsapi_key', '4a6231389e5c463284586bca6502a064', 'text', 'API Key untuk NewsAPI.org', 0),
('newsapi_country_id', 'id', 'text', 'Kode negara Indonesia untuk NewsAPI', 0),
('newsapi_country_international', 'us', 'text', 'Kode negara internasional untuk NewsAPI (us,gb,au)', 0),
('newsapi_language', 'id', 'text', 'Bahasa untuk NewsAPI (id=Indonesia, en=English)', 0),
('newsapi_pagesize', '20', 'number', 'Jumlah berita per request NewsAPI', 0),
('newsapi_cache_duration', '1800', 'number', 'Durasi cache NewsAPI dalam detik (1800=30 menit)', 0)
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- BMKG API Configuration
-- Note: Konfigurasi ADM manual (bmkg_adm4_*) dipensiunkan dari panel admin.
-- Homepage widget kini memakai weather_widget_locations (JSON ADM2 + urutan + hide/show).
INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('bmkg_default_city', 'manado', 'text', 'Kota default untuk prakiraan cuaca (manado/bitung/tomohon/kotamobagu)', 1),
('bmkg_peringatan_cache', '900', 'number', 'Durasi cache peringatan dini BMKG dalam detik (900=15 menit)', 0),
('bmkg_prakiraan_cache', '3600', 'number', 'Durasi cache prakiraan cuaca BMKG dalam detik (3600=1 jam)', 0),
('bmkg_enable_peringatan', '1', 'boolean', 'Aktifkan peringatan dini cuaca (1=Ya, 0=Tidak)', 1),
('bmkg_enable_prakiraan', '1', 'boolean', 'Aktifkan prakiraan cuaca (1=Ya, 0=Tidak)', 1),
('weather_widget_default_adm2', '71.71', 'text', 'ADM2 default untuk widget cuaca homepage', 0),
('weather_widget_locations', '[{"adm2":"71.71","name":"Kota Manado","enabled":1,"order":1},{"adm2":"71.72","name":"Kota Bitung","enabled":1,"order":2},{"adm2":"71.73","name":"Kota Tomohon","enabled":1,"order":3}]', 'json', 'Daftar kabupaten/kota widget cuaca homepage (sortable + hide/show)', 0)
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Widget Display Settings
INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('widget_cuaca_homepage', '1', 'boolean', 'Tampilkan widget cuaca di homepage (1=Ya, 0=Tidak)', 1),
('widget_berita_nasional', '1', 'boolean', 'Tampilkan widget berita nasional (1=Ya, 0=Tidak)', 1),
('widget_berita_internasional', '1', 'boolean', 'Tampilkan widget berita internasional (1=Ya, 0=Tidak)', 1),
('widget_breaking_news', '1', 'boolean', 'Tampilkan widget breaking news (1=Ya, 0=Tidak)', 1),
('berita_nasional_per_widget', '5', 'number', 'Jumlah berita per widget', 0)
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
