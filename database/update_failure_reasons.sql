-- Update: Broadcast Failure Reasons

-- Deactivate reasons yang dihapus
UPDATE broadcast_failure_reasons 
SET is_active = FALSE 
WHERE reason IN ('Kru terlambat', 'Narasumber tidak hadir');

-- Add new reason: Kualitas Audio buruk
INSERT INTO broadcast_failure_reasons (reason, display_order, is_active) 
VALUES ('Kualitas Audio buruk', 2, TRUE)
ON DUPLICATE KEY UPDATE is_active = TRUE, display_order = 2;

-- Update display order untuk consistency
UPDATE broadcast_failure_reasons SET display_order = 1 WHERE reason = 'Kualitas video buruk';
UPDATE broadcast_failure_reasons SET display_order = 2 WHERE reason = 'Kualitas Audio buruk';
UPDATE broadcast_failure_reasons SET display_order = 3 WHERE reason = 'Masalah teknis';
UPDATE broadcast_failure_reasons SET display_order = 4 WHERE reason = 'Konten tidak layak tayang';
UPDATE broadcast_failure_reasons SET display_order = 5 WHERE reason = 'Batal dari manajemen';
UPDATE broadcast_failure_reasons SET display_order = 99 WHERE reason = 'Lainnya (tulis manual)';
