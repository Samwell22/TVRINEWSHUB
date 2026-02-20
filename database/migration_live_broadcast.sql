-- Migration: Live Broadcast Monitoring

-- Main table: Live broadcast schedule
CREATE TABLE IF NOT EXISTS live_broadcast_schedule (
    id INT PRIMARY KEY AUTO_INCREMENT,
    news_id INT NULL,                          -- Link ke news table (optional)
    broadcast_date DATE NOT NULL,              -- Tanggal siaran
    news_title VARCHAR(255) NOT NULL,          -- Judul berita
    news_category VARCHAR(100),                -- Kategori (optional)
    assigned_to INT,                           -- User ID yang assigned
    
    -- Status tracking (simple, no time tracking)
    status ENUM('scheduled', 'broadcasted', 'failed') DEFAULT 'scheduled',
    failure_reason_type VARCHAR(100),          -- Alasan gagal (predefined)
    failure_reason_custom TEXT,                -- Alasan custom (manual input)
    
    -- Metadata
    created_by INT NOT NULL,                   -- User yang buat entry
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes untuk performa
    INDEX idx_broadcast_date (broadcast_date),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    
    -- Foreign keys
    FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Predefined failure reasons (sesuai approval user)
CREATE TABLE IF NOT EXISTS broadcast_failure_reasons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reason VARCHAR(100) NOT NULL UNIQUE,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default failure reasons (approved by user)
INSERT INTO broadcast_failure_reasons (reason, display_order) VALUES
('Kualitas video buruk', 1),
('Narasumber tidak hadir', 2),
('Kru terlambat', 3),
('Masalah teknis', 4),
('Konten tidak layak tayang', 5),
('Batal dari manajemen', 6),
('Lainnya (tulis manual)', 99);

-- Sample data (optional, untuk testing)
-- Uncomment untuk insert sample data:
/*
INSERT INTO live_broadcast_schedule (broadcast_date, news_title, news_category, status, created_by) VALUES
(CURDATE(), 'Breaking News: Banjir Manado', 'Berita Utama', 'broadcasted', 1),
(CURDATE(), 'Update COVID-19 Sulut', 'Kesehatan', 'scheduled', 1),
(CURDATE(), 'Demo Mahasiswa di Unsrat', 'Politik', 'failed', 1);

UPDATE live_broadcast_schedule 
SET failure_reason_type = 'Kru terlambat', 
    failure_reason_custom = 'Tim liputan terkendala macet' 
WHERE id = 3;
*/

-- Cleanup job (auto-delete old data after 1 month)
-- Note: Bisa dijadwalkan via cron job atau event scheduler
CREATE EVENT IF NOT EXISTS cleanup_old_broadcast_data
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
DELETE FROM live_broadcast_schedule 
WHERE broadcast_date < DATE_SUB(CURDATE(), INTERVAL 1 MONTH);

-- Enable event scheduler (run once manually in MySQL)
-- SET GLOBAL event_scheduler = ON;
