<?php
/**
 * Admin: Upload Handler
 */

/**
 * Upload gambar thumbnail/featured
 * @param array $file $_FILES['field_name']
 * @param string $target_folder Target folder (default: uploads/thumbnails/)
 * @return array Result dengan status dan message/filename
 */
function uploadImage($file, $target_folder = '../uploads/thumbnails/') {
    // Cek apakah file di-upload
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['status' => false, 'message' => 'Tidak ada file yang diupload'];
    }
    
    // Cek error upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Error saat upload file: ' . $file['error']];
    }
    
    // Validasi tipe file - check both header and actual content
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $file_type = $file['type'];
    
    // Check declared MIME type
    if (!in_array($file_type, $allowed_types)) {
        return ['status' => false, 'message' => 'Tipe file tidak diizinkan! Hanya JPG, PNG, WEBP'];
    }
    
    // Verify actual file content using fileinfo (more secure)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $real_type = $finfo->file($file['tmp_name']);
    if (!in_array($real_type, $allowed_types)) {
        return ['status' => false, 'message' => 'File tidak valid! Konten tidak sesuai dengan tipe file.'];
    }
    
    // Additional check using getimagesize for images
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return ['status' => false, 'message' => 'File bukan gambar yang valid!'];
    }
    
    // Validasi ukuran file (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $max_size) {
        return ['status' => false, 'message' => 'Ukuran file terlalu besar! Maksimal 5MB'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'img_' . time() . '_' . uniqid() . '.' . $extension;
    $target_path = $target_folder . $filename;
    
    // Pastikan folder exist
    if (!file_exists($target_folder)) {
        mkdir($target_folder, 0755, true);
    }
    
    // Pindahkan file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Return relative path untuk disimpan ke database
        $relative_path = str_replace('../', '', $target_path);
        return ['status' => true, 'filename' => $relative_path];
    } else {
        return ['status' => false, 'message' => 'Gagal memindahkan file ke folder tujuan'];
    }
}

/**
 * Upload video
 * @param array $file $_FILES['field_name']
 * @param string $target_folder Target folder (default: uploads/videos/)
 * @return array Result dengan status dan message/filename
 */
function uploadVideo($file, $target_folder = '../uploads/videos/') {
    // Cek apakah file di-upload
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['status' => false, 'message' => 'Tidak ada file yang diupload'];
    }
    
    // Cek error upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Error saat upload file: ' . $file['error']];
    }
    
    // Validasi tipe file - check both header and actual content
    $allowed_types = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo'];
    $file_type = $file['type'];
    
    // Check declared MIME type
    if (!in_array($file_type, $allowed_types)) {
        return ['status' => false, 'message' => 'Tipe file tidak diizinkan! Hanya MP4, MOV, AVI'];
    }
    
    // Verify actual file content using fileinfo (more secure)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $real_type = $finfo->file($file['tmp_name']);
    // Allow application/octet-stream for some video formats that finfo doesn't recognize well
    $valid_video_types = array_merge($allowed_types, ['application/octet-stream', 'video/x-m4v']);
    if (!in_array($real_type, $valid_video_types)) {
        return ['status' => false, 'message' => 'File tidak valid! Konten tidak sesuai dengan tipe video.'];
    }
    
    // Validasi ukuran file (max 50MB)
    $max_size = 50 * 1024 * 1024; // 50MB in bytes
    if ($file['size'] > $max_size) {
        return ['status' => false, 'message' => 'Ukuran file terlalu besar! Maksimal 50MB'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'vid_' . time() . '_' . uniqid() . '.' . $extension;
    $target_path = $target_folder . $filename;
    
    // Pastikan folder exist
    if (!file_exists($target_folder)) {
        mkdir($target_folder, 0755, true);
    }
    
    // Pindahkan file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Return relative path untuk disimpan ke database
        $relative_path = str_replace('../', '', $target_path);
        return ['status' => true, 'filename' => $relative_path];
    } else {
        return ['status' => false, 'message' => 'Gagal memindahkan file ke folder tujuan'];
    }
}

/**
 * Delete file
 * @param string $filepath Path to file (relative from root)
 * @return bool
 */
function deleteFile($filepath) {
    if (empty($filepath)) {
        return false;
    }
    
    // Tambahkan ../ jika belum ada
    if (strpos($filepath, '../') !== 0) {
        $filepath = '../' . $filepath;
    }
    
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    
    return false;
}
?>
