<?php
/**
 * Database Configuration - TVRI Sulut News Hub
 */

// KONFIGURASI DATABASE
// Sesuaikan dengan setting XAMPP kamu
if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1');       // Host database (gunakan 127.0.0.1 agar tidak hang di Windows)
    define('DB_USER', 'root');             // Username MySQL (default XAMPP: root)
    define('DB_PASS', '');                 // Password MySQL (default XAMPP: kosong)
    define('DB_NAME', 'tvri_sulut_db');    // Nama database yang sudah kita buat
}

/**
 * FUNGSI: Membuat koneksi ke database
 * Return: Object mysqli connection atau die jika gagal
 * Auto-create database jika belum ada
 */
function getDBConnection() {
    static $conn = null;
    if ($conn !== null) {
        try {
            if (@mysqli_ping($conn)) {
                return $conn;
            }
        } catch (\Throwable $e) {
            $conn = null;
        }
    }
    
    // Matikan error reporting mysqli agar tidak hang
    mysqli_report(MYSQLI_REPORT_OFF);
    
    // Buat koneksi (tanpa persistent, tanpa database dulu)
    $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS);
    
    // Cek apakah MySQL server bisa dihubungi
    if (!$conn) {
        die("
            <div style='font-family: Arial; padding: 20px; background: #fee; border-left: 4px solid #c00;'>
                <h3 style='margin:0; color: #c00;'>❌ MySQL Server Tidak Ditemukan!</h3>
                <p><strong>Error:</strong> " . mysqli_connect_error() . "</p>
                <p><strong>Solusi:</strong></p>
                <ul>
                    <li>Buka XAMPP Control Panel</li>
                    <li>Pastikan <strong>MySQL</strong> sudah di-<strong>Start</strong> (hijau)</li>
                    <li>Jika error, coba <strong>Stop</strong> lalu <strong>Start</strong> ulang</li>
                </ul>
            </div>
        ");
    }
    
    // Set character set
    mysqli_set_charset($conn, 'utf8mb4');
    
    // Cek apakah database ada, jika tidak: auto-create
    $dbCheck = @mysqli_select_db($conn, DB_NAME);
    if (!$dbCheck) {
        // Database belum ada, buat otomatis
        $created = autoCreateDatabase($conn);
        if (!$created) {
            die("
                <div style='font-family: Arial; padding: 20px; background: #fee; border-left: 4px solid #c00;'>
                    <h3 style='margin:0; color: #c00;'>❌ Database Gagal Dibuat!</h3>
                    <p><strong>Error:</strong> " . mysqli_error($conn) . "</p>
                    <p><strong>Solusi Manual:</strong></p>
                    <ol>
                        <li>Buka <a href='http://localhost/phpmyadmin'>phpMyAdmin</a></li>
                        <li>Buat database baru: <code>" . DB_NAME . "</code></li>
                        <li>Import file: <code>database/tvri_sulut_db.sql</code></li>
                    </ol>
                </div>
            ");
        }
        // Select database yang baru dibuat
        mysqli_select_db($conn, DB_NAME);
    }
    
    return $conn;
}

/**
 * Auto-create database dan import semua SQL files
 */
function autoCreateDatabase($conn) {
    $dbName = DB_NAME;
    
    // 1. Buat database
    $result = @mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    if (!$result) return false;
    
    // 2. Select database
    if (!@mysqli_select_db($conn, $dbName)) return false;
    
    // 3. Cari project root
    $projectRoot = dirname(__DIR__);
    
    // 4. Import SQL files secara berurutan
    $sqlFiles = [
        $projectRoot . '/database/tvri_sulut_db.sql',
        $projectRoot . '/database/04_api_integration_update.sql',
        $projectRoot . '/database/migration_live_broadcast.sql',
        $projectRoot . '/database/migration_rss_intelligence.sql',
        $projectRoot . '/database/migration_rss_v2_status.sql',
        $projectRoot . '/database/update_failure_reasons.sql',
    ];
    
    foreach ($sqlFiles as $sqlFile) {
        if (!file_exists($sqlFile)) continue;
        
        $sql = file_get_contents($sqlFile);
        if (empty($sql)) continue;
        
        // Hapus perintah DROP/CREATE/USE DATABASE dari migration files
        // (database sudah dibuat di atas)
        $sql = preg_replace('/^\s*DROP\s+DATABASE\s+.*?;\s*$/mi', '', $sql);
        $sql = preg_replace('/^\s*CREATE\s+DATABASE\s+.*?;\s*$/mi', '', $sql);
        $sql = preg_replace('/^\s*USE\s+.*?;\s*$/mi', '', $sql);
        
        // Hapus CREATE EVENT (tidak support di semua XAMPP)
        $sql = preg_replace('/CREATE\s+EVENT\s+.*?;/si', '', $sql);
        
        // Eksekusi multi-query
        @mysqli_multi_query($conn, $sql);
        
        // Proses semua result sets
        do {
            if ($result = @mysqli_store_result($conn)) {
                mysqli_free_result($result);
            }
        } while (@mysqli_next_result($conn));
    }
    
    // 5. Verifikasi tabel utama ada
    $checkTables = @mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    return ($checkTables && mysqli_num_rows($checkTables) > 0);
}

/**
 * FUNGSI: Menutup koneksi database
 * Parameter: $conn (object mysqli connection)
 */
function closeDBConnection($conn) {
    if ($conn) {
        mysqli_close($conn);
    }
}

/**
 * FUNGSI: Eksekusi query dengan prepared statement (AMAN dari SQL Injection)
 * Parameter:
 *   - $conn: koneksi database
 *   - $query: query SQL dengan placeholder (?)
 *   - $params: array parameter untuk bind
 *   - $types: string types untuk bind (s=string, i=integer, d=double, b=blob)
 * Return: Result set atau boolean
 */
function executeQuery($conn, $query, $params = [], $types = '') {
    // Siapkan statement
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        // Jika prepare gagal, log error
        error_log("Prepare failed: " . mysqli_error($conn));
        return false;
    }
    
    // Jika ada parameter, lakukan binding
    if (!empty($params) && !empty($types)) {
        // bind_param($types, ...$params)
        // Contoh: bind_param('si', $string_param, $int_param)
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    // Eksekusi statement
    $execute = mysqli_stmt_execute($stmt);
    
    if (!$execute) {
        // Jika eksekusi gagal, log error
        error_log("Execute failed: " . mysqli_stmt_error($stmt));
        return false;
    }
    
    // Ambil result jika query SELECT
    $result = mysqli_stmt_get_result($stmt);
    
    // Jika bukan SELECT (INSERT/UPDATE/DELETE), return boolean
    if (!$result) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        return $affected_rows;
    }
    
    // Tutup statement
    mysqli_stmt_close($stmt);
    
    // Return result set
    return $result;
}

/**
 * FUNGSI: Sanitasi input untuk keamanan
 * Parameter: $data (string input dari user)
 * Return: String yang sudah di-sanitasi
 */
function sanitizeInput($data) {
    // Hapus spasi di awal dan akhir
    $data = trim($data);
    // Hapus backslashes
    $data = stripslashes($data);
    // Konversi karakter spesial HTML
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

?>

