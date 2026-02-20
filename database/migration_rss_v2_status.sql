-- Migration: RSS v2 Status (editorial_status to read/bookmarked)

-- Step 1: Add is_read and is_bookmarked columns
ALTER TABLE rss_articles 
ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER is_top_news,
ADD COLUMN is_bookmarked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read;

-- Step 2: Migrate existing status data
-- picked → bookmarked, reviewed → read
UPDATE rss_articles SET is_read = 1, is_bookmarked = 1 WHERE editorial_status = 'picked';
UPDATE rss_articles SET is_read = 1 WHERE editorial_status = 'reviewed';
UPDATE rss_articles SET is_read = 1 WHERE editorial_status = 'rejected';

-- Step 3: Add indexes for new columns
ALTER TABLE rss_articles 
ADD INDEX idx_is_read (is_read),
ADD INDEX idx_is_bookmarked (is_bookmarked);

-- Step 4: Drop old editorial workflow columns (optional - keep for safety)
-- ALTER TABLE rss_articles DROP COLUMN editorial_status;
-- ALTER TABLE rss_articles DROP COLUMN reviewed_by;
-- ALTER TABLE rss_articles DROP COLUMN reviewed_at;
-- ALTER TABLE rss_articles DROP COLUMN editorial_notes;
