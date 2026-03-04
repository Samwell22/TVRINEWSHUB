<?php
/**
 * Admin: AJAX Update Broadcast Status
 */

require_once '../config/config.php';
require_once 'auth.php';

// Cek login
requireLogin();

// Set JSON header
header('Content-Type: application/json');

// Get logged in user
$logged_in_user = getLoggedInUser();

// Ambil koneksi database
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? '');
    $edit_mode = isset($_POST['edit_mode']) && $_POST['edit_mode'] === '1';
    
    if ($id > 0 && in_array($status, ['scheduled', 'broadcasted', 'failed'])) {
        
        if ($edit_mode) {
            // Edit mode: update title, category, status, and reason
            $news_title = sanitizeInput($_POST['news_title'] ?? '');
            $news_category = sanitizeInput($_POST['news_category'] ?? '');
            
            if (empty($news_title)) {
                echo json_encode(['success' => false, 'message' => 'Judul berita harus diisi!']);
                exit;
            }
            
            if ($status === 'failed') {
                $failure_reason_type = sanitizeInput($_POST['failure_reason_type'] ?? '');
                $failure_reason_custom = sanitizeInput($_POST['failure_reason_custom'] ?? '');
                
                $update_query = "UPDATE live_broadcast_schedule 
                                SET news_title = ?,
                                    news_category = ?,
                                    status = 'failed', 
                                    failure_reason_type = ?, 
                                    failure_reason_custom = ?,
                                    updated_at = NOW()
                                WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, 'ssssi', $news_title, $news_category, $failure_reason_type, $failure_reason_custom, $id);
            } else {
                // Changed from failed to broadcasted or scheduled
                $update_query = "UPDATE live_broadcast_schedule 
                                SET news_title = ?,
                                    news_category = ?,
                                    status = ?, 
                                    failure_reason_type = NULL, 
                                    failure_reason_custom = NULL,
                                    updated_at = NOW()
                                WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, 'sssi', $news_title, $news_category, $status, $id);
            }
        } elseif ($status === 'failed') {
            // Mark as failed with reason
            $failure_reason_type = sanitizeInput($_POST['failure_reason_type'] ?? '');
            $failure_reason_custom = sanitizeInput($_POST['failure_reason_custom'] ?? '');
            
            $update_query = "UPDATE live_broadcast_schedule 
                            SET status = 'failed', 
                                failure_reason_type = ?, 
                                failure_reason_custom = ?,
                                updated_at = NOW()
                            WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, 'ssi', $failure_reason_type, $failure_reason_custom, $id);
        } else {
            // Mark as broadcasted or back to scheduled
            $update_query = "UPDATE live_broadcast_schedule 
                            SET status = ?, 
                                failure_reason_type = NULL, 
                                failure_reason_custom = NULL,
                                updated_at = NOW()
                            WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, 'si', $status, $id);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Status berhasil diupdate!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal update status!']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters!']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method!']);
}

mysqli_close($conn);
?>
