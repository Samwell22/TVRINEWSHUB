-- Migration: RSS Intelligence & Editorial Dashboard

-- Table: rss_articles
-- Stores all collected articles from ANTARA RSS feeds
-- Deduplicated by article_hash (SHA256 of URL)
CREATE TABLE IF NOT EXISTS rss_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_hash VARCHAR(64) NOT NULL COMMENT 'SHA256 of URL for deduplication',
    title VARCHAR(500) NOT NULL,
    description TEXT,
    url VARCHAR(1000) NOT NULL,
    image_url VARCHAR(1000) DEFAULT NULL,
    source_feed VARCHAR(100) NOT NULL COMMENT 'Feed key e.g. top-news, kota-manado',
    category VARCHAR(100) DEFAULT NULL COMMENT 'Article category from RSS or feed',
    region VARCHAR(100) DEFAULT NULL COMMENT 'Region label from feed mapping',
    author VARCHAR(200) DEFAULT 'ANTARA',
    published_at DATETIME NOT NULL,
    fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Editorial workflow
    editorial_status ENUM('new','reviewed','picked','rejected') NOT NULL DEFAULT 'new',
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    editorial_notes TEXT DEFAULT NULL,
    is_top_news TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Article appeared in top-news feed',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_article_hash (article_hash),
    INDEX idx_published_at (published_at),
    INDEX idx_category (category),
    INDEX idx_region (region),
    INDEX idx_editorial_status (editorial_status),
    INDEX idx_source_feed (source_feed),
    INDEX idx_is_top_news (is_top_news),
    INDEX idx_fetched_at (fetched_at),
    INDEX idx_date_category (published_at, category),
    INDEX idx_date_region (published_at, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rss_fetch_log
-- Tracks each collection run for monitoring and debugging
CREATE TABLE IF NOT EXISTS rss_fetch_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    started_at DATETIME NOT NULL,
    completed_at DATETIME DEFAULT NULL,
    feeds_fetched INT DEFAULT 0,
    feeds_failed INT DEFAULT 0,
    articles_new INT DEFAULT 0,
    articles_updated INT DEFAULT 0,
    articles_total INT DEFAULT 0,
    duration_ms INT DEFAULT 0,
    error_details TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
