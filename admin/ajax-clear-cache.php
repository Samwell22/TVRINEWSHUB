<?php
/**
 * AJAX Handler - Clear API Cache
 * Menghapus semua cache dari tabel api_cache
 */

ob_start();

require_once 'auth.php';
requireAdmin();

require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method tidak diizinkan'
    ]);
    exit;
}

try {
    // Get database connection
    $conn = getDBConnection();

    $stmt = $conn->prepare("DELETE FROM api_cache");
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        
        echo json_encode([
            'success' => true,
            'message' => "Berhasil menghapus {$affected} cache entries"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menghapus cache'
        ]);
    }
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}

if (isset($conn) && $conn) {
    $conn->close();
}

ob_end_flush();
?>
