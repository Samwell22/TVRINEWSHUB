-- Database Schema: TVRI Sulut News Hub

-- Hapus database jika sudah ada (hati-hati di production!)
DROP DATABASE IF EXISTS tvri_sulut_db;

-- Buat database baru dengan encoding UTF-8 (untuk support karakter Indonesia)
CREATE DATABASE tvri_sulut_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Gunakan database yang baru dibuat
USE tvri_sulut_db;

-- Users
CREATE TABLE users (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,                      -- ID unik untuk setiap user
    username VARCHAR(50) NOT NULL UNIQUE,                       -- Username untuk login (harus unik)
    password VARCHAR(255) NOT NULL,                             -- Password (akan di-hash dengan password_hash)
    full_name VARCHAR(100) NOT NULL,                            -- Nama lengkap redaksi
    email VARCHAR(100) NOT NULL UNIQUE,                         -- Email (harus unik)
    role ENUM('admin', 'editor', 'reporter') DEFAULT 'reporter', -- Level akses pengguna
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',           -- Foto profil (opsional)
    is_active TINYINT(1) DEFAULT 1,                             -- Status aktif (1=aktif, 0=nonaktif)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,             -- Waktu pembuatan akun
    last_login TIMESTAMP NULL,                                  -- Waktu login terakhir
    INDEX idx_username (username),                              -- Index untuk mempercepat pencarian
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- Categories
CREATE TABLE categories (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,                      -- ID unik kategori
    name VARCHAR(100) NOT NULL,                                 -- Nama kategori (misal: Politik, Ekonomi)
    slug VARCHAR(100) NOT NULL UNIQUE,                          -- URL-friendly name (misal: politik)
    description TEXT,                                           -- Deskripsi kategori (opsional)
    icon VARCHAR(50) DEFAULT 'fa-newspaper',                    -- Icon Font Awesome (untuk tampilan)
    color VARCHAR(7) DEFAULT '#1A428A',                         -- Warna kategori (hex code)
    is_active TINYINT(1) DEFAULT 1,                             -- Status aktif kategori
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- News
CREATE TABLE news (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,                      -- ID unik berita
    slug VARCHAR(255) NOT NULL UNIQUE,                          -- URL slug (misal: pemilu-sulut-2026)
    title VARCHAR(255) NOT NULL,                                -- Judul berita
    subtitle VARCHAR(255),                                      -- Sub judul (opsional)
    content TEXT NOT NULL,                                      -- Isi berita (HTML supported)
    excerpt VARCHAR(500),                                       -- Ringkasan berita (untuk preview)
    
    -- Media Files
    thumbnail VARCHAR(255),                                     -- Path file thumbnail (misal: uploads/thumbnails/berita1.jpg)
    video_url VARCHAR(255),                                     -- Path file video lokal ATAU link YouTube embed
    
    -- Metadata
    category_id INT(11) NOT NULL,                               -- Foreign key ke tabel categories
    author_id INT(11) NOT NULL,                                 -- Foreign key ke tabel users (siapa yang buat berita)
    views INT(11) DEFAULT 0,                                    -- Jumlah views/pembaca
    is_featured TINYINT(1) DEFAULT 0,                           -- Apakah berita headline? (1=ya, 0=tidak)
    
    -- SEO & Tags
    meta_description VARCHAR(160),                              -- Meta description untuk SEO
    tags VARCHAR(255),                                          -- Tags dipisah koma (misal: sulut,pemilu,politik)
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft', -- Status publikasi
    
    -- Timestamps
    published_at TIMESTAMP NULL,                                -- Waktu publikasi
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,             -- Waktu pembuatan berita
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Waktu update terakhir
    
    -- Foreign Keys
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes untuk performa
    INDEX idx_slug (slug),
    INDEX idx_category (category_id),
    INDEX idx_author (author_id),
    INDEX idx_published (status, published_at),
    INDEX idx_featured (is_featured),
    FULLTEXT INDEX idx_search (title, content, excerpt)         -- Full-text search untuk fitur pencarian
) ENGINE=InnoDB;

-- News Logs
CREATE TABLE news_logs (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,                      -- ID unik log
    news_id INT(11) NULL,                                       -- Foreign key ke news (NULL jika upload gagal total)
    user_id INT(11) NOT NULL,                                   -- User yang melakukan aksi
    
    -- Detail Log
    action VARCHAR(50) NOT NULL,                                -- Jenis aksi (misal: upload, update, delete)
    status ENUM('success', 'failed') NOT NULL,                  -- Status aksi
    error_message TEXT,                                         -- Pesan error (jika gagal)
    
    -- Detail File (untuk debugging)
    file_name VARCHAR(255),                                     -- Nama file yang di-upload
    file_size INT(11),                                          -- Ukuran file dalam bytes
    file_type VARCHAR(50),                                      -- Tipe file (misal: image/jpeg, video/mp4)
    
    -- Metadata
    ip_address VARCHAR(45),                                     -- IP address user (untuk tracking)
    user_agent TEXT,                                            -- Browser/device info
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,             -- Waktu log dibuat
    
    -- Foreign Keys
    FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Settings
CREATE TABLE settings (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,                      -- ID setting
    setting_key VARCHAR(100) NOT NULL UNIQUE,                   -- Kunci setting (misal: site_name)
    setting_value TEXT,                                         -- Nilai setting
    setting_type VARCHAR(20) DEFAULT 'text',                    -- Tipe data (text, number, boolean, json)
    description VARCHAR(255),                                   -- Deskripsi setting (untuk admin)
    is_public TINYINT(1) DEFAULT 0,                             -- Apakah setting ini bisa diakses public?
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB;

-- Seed Data

-- Insert Admin Default dengan password yang BENAR
-- admin: admin123 | editor: editor123 | reporter: reporter123
-- Password di-hash dengan: password_hash('[password]', PASSWORD_DEFAULT)
INSERT INTO users (username, password, full_name, email, role, is_active) VALUES
('admin', '$2y$10$bdqmDoWLalhFJEjcqmnmhu4pbMPSW/g9WfZgqhiBOV5sZaQgG8XZG', 'Administrator TVRI Sulut', 'admin@tvrisulut.co.id', 'admin', 1),
('editor', '$2y$10$awpTdS4t7V/C7MlXtPvKA.ZN9S3HmpVy0J8i.V5NQyV8I7kSUVud2', 'Editor TVRI Sulut', 'editor@tvrisulut.co.id', 'editor', 1),
('reporter', '$2y$10$rv7L/ZXBXVLrP4p.jYvf9ufeCd2/aEAIcwi9yJHQx0eZworCtkIA6', 'Reporter TVRI Sulut', 'reporter@tvrisulut.co.id', 'reporter', 1);

-- Insert Kategori Berita
INSERT INTO categories (name, slug, description, icon, color) VALUES
('Berita Utama', 'berita-utama', 'Berita headline dan terkini Sulawesi Utara', 'fa-star', '#1A428A'),
('Politik', 'politik', 'Berita politik dan pemerintahan', 'fa-landmark', '#DC3545'),
('Ekonomi', 'ekonomi', 'Berita ekonomi dan bisnis', 'fa-chart-line', '#28A745'),
('Pendidikan', 'pendidikan', 'Berita pendidikan dan kampus', 'fa-graduation-cap', '#FFC107'),
('Budaya', 'budaya', 'Berita budaya dan pariwisata', 'fa-masks-theater', '#6F42C1'),
('Olahraga', 'olahraga', 'Berita olahraga dan event', 'fa-futbol', '#FD7E14'),
('Kesehatan', 'kesehatan', 'Berita kesehatan dan lingkungan', 'fa-heart-pulse', '#E83E8C');

-- Insert Settings Awal Website
INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', 'SULUT NEWS HUB - TVRI Sulawesi Utara', 'text', 'Nama website', 1),
('site_tagline', 'Portal Berita Terpercaya Sulawesi Utara', 'text', 'Tagline website', 1),
('site_description', 'SULUT NEWS HUB adalah portal berita resmi TVRI Sulawesi Utara yang menyajikan informasi terkini seputar politik, ekonomi, budaya, dan kehidupan masyarakat Sulut.', 'text', 'Deskripsi website untuk SEO', 1),
('contact_email', 'redaksi@tvrisulut.co.id', 'text', 'Email kontak redaksi', 1),
('contact_phone', '(0431) 123456', 'text', 'Nomor telepon', 1),
('contact_address', 'Jl. Raya Trans Sulawesi, Manado, Sulawesi Utara', 'text', 'Alamat kantor', 1),
('running_text', 'Selamat datang di SULUT NEWS HUB - Portal berita terpercaya Sulawesi Utara', 'text', 'Running text di header website', 1),
('facebook_url', 'https://facebook.com/tvrisulut', 'text', 'Link Facebook', 1),
('twitter_url', 'https://twitter.com/tvrisulut', 'text', 'Link Twitter', 1),
('instagram_url', 'https://instagram.com/tvrisulut', 'text', 'Link Instagram', 1),
('youtube_url', 'https://youtube.com/@tvrisulut', 'text', 'Link YouTube', 1),
('max_upload_size', '10485760', 'number', 'Maksimal ukuran upload (bytes) - 10MB', 0),
('news_per_page', '12', 'number', 'Jumlah berita per halaman', 0);

-- Sample News
INSERT INTO news (slug, title, subtitle, content, excerpt, category_id, author_id, status, is_featured, published_at, views) VALUES
(
    'peluncuran-sulut-news-hub-portal-berita-tvri',
    'Peluncuran SULUT NEWS HUB: Portal Berita Digital TVRI Sulawesi Utara',
    'Platform digital baru untuk masyarakat Sulut mengakses berita terpercaya',
    '<p>Manado - TVRI Sulawesi Utara meluncurkan portal berita digital bernama SULUT NEWS HUB sebagai langkah transformasi digital dalam penyampaian informasi kepada masyarakat.</p><p>Portal ini dikembangkan oleh tim magang TVRI Sulut dengan mengusung konsep responsif dan user-friendly, memudahkan masyarakat mengakses berita melalui berbagai perangkat.</p><p>"Kami berkomitmen memberikan informasi yang akurat, cepat, dan terpercaya untuk masyarakat Sulawesi Utara," ujar Kepala TVRI Sulut dalam sambutannya.</p>',
    'TVRI Sulawesi Utara meluncurkan SULUT NEWS HUB sebagai portal berita digital untuk transformasi penyampaian informasi kepada masyarakat.',
    1, 1, 'published', 1, NOW(), 150
),
(
    'cuaca-manado-hari-ini-bmkg-prediksi-hujan',
    'BMKG: Waspadai Potensi Hujan Lebat di Manado Sore Ini',
    'Masyarakat diminta waspada terhadap cuaca ekstrem',
    '<p>Manado - Badan Meteorologi, Klimatologi, dan Geofisika (BMKG) Sulawesi Utara memperingatkan potensi hujan lebat disertai petir di wilayah Manado dan sekitarnya pada sore hingga malam hari.</p><p>Kepala BMKG Sulut menghimbau masyarakat untuk selalu waspada dan mengurangi aktivitas di luar ruangan saat cuaca buruk.</p>',
    'BMKG memperingatkan potensi hujan lebat di Manado sore ini. Masyarakat diminta waspada.',
    1, 2, 'published', 0, NOW(), 89
);
