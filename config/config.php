<?php
/**
 * Konfigurasi Utama - TVRI Sulut News Hub
 */

// Prevent direct access
if (!defined('APP_NAME')) {
    define('APP_NAME', 'SULUT NEWS HUB');
}

// Asset version for cache busting (increment when CSS/JS changes)
if (!defined('ASSET_VERSION')) {
    define('ASSET_VERSION', '2.0.1');
}

// SITE URL CONFIGURATION

/**
 * Automatically detect the base URL
 * Works for both localhost and production
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the directory path - we need to find the project root
    $script_name = $_SERVER['SCRIPT_NAME'];
    
    // Find the project root by looking for the first directory after the host
    // Example: /TVRI NEWS HUB/admin/file.php -> /TVRI NEWS HUB/
    $path = dirname($script_name);
    
    // Remove /admin, /api, /includes from path if present
    $path = preg_replace('#/(admin|api|includes).*$#', '', $path);
    
    // Ensure path ends with /
    $path = rtrim($path, '/') . '/';
    
    // URL encode spaces and special characters in path
    $path_parts = explode('/', trim($path, '/'));
    $encoded_parts = array_map('rawurlencode', $path_parts);
    $path = '/' . implode('/', $encoded_parts) . '/';
    
    return $protocol . $host . $path;
}

// Define SITE_URL constant
if (!defined('SITE_URL')) {
    define('SITE_URL', getBaseUrl());
}

// Define SITE_ROOT (physical path)
if (!defined('SITE_ROOT')) {
    define('SITE_ROOT', dirname(__DIR__) . '/');
}

// TIMEZONE & LOCALE
date_default_timezone_set('Asia/Makassar');
setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'Indonesian');

// ERROR REPORTING (Development vs Production)
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development'); // Change to 'production' when live
}

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php-errors.log');
}

// UPLOAD CONFIGURATION
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', __DIR__ . '/../uploads/');
}

if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 5242880); // 5MB in bytes
}

// PAGINATION
if (!defined('ITEMS_PER_PAGE')) {
    define('ITEMS_PER_PAGE', 12);
}

// CACHE CONFIGURATION
if (!defined('CACHE_ENABLED')) {
    define('CACHE_ENABLED', true);
}

if (!defined('CACHE_DURATION')) {
    define('CACHE_DURATION', 1800); // 30 minutes in seconds
}

// DATABASE CONNECTION
require_once __DIR__ . '/db.php';

// HELPER FUNCTIONS

/**
 * Get setting value from database
 * Canonical definition — used site-wide. Do not redefine elsewhere.
 */
function getSetting($conn, $key, $default = '') {
    static $memo = [];

    if (array_key_exists($key, $memo)) {
        return $memo[$key];
    }

    if (!$conn) {
        $memo[$key] = $default;
        return $memo[$key];
    }

    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) {
        $memo[$key] = $default;
        return $memo[$key];
    }

    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $memo[$key] = $row['setting_value'];
        return $memo[$key];
    }

    $memo[$key] = $default;
    return $memo[$key];
}

/**
 * Sanitize output for HTML
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format date in Indonesian
 */
function formatTanggalIndonesia($date) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    $d = date('j', $timestamp);
    $m = date('n', $timestamp);
    $y = date('Y', $timestamp);
    
    return $d . ' ' . $bulan[$m] . ' ' . $y;
}

/**
 * Get time ago in Indonesian
 */
function timeAgo($timestamp) {
    $time = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Baru saja';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' menit yang lalu';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' jam yang lalu';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' hari yang lalu';
    } else {
        return formatTanggalIndonesia($time);
    }
}

/**
 * Truncate text with ellipsis
 */
function truncate($text, $length = 150, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * Generate slug from title
 */
function createSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Alias for backward compatibility
 */
function generateSlug($string) {
    return createSlug($string);
}

/**
 * Format angka views (1000 -> 1K, 1000000 -> 1M)
 */
function formatViews($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return $number;
}

// CACHE HELPERS (shared by all API files)

/**
 * Get cache from database.
 * Canonical definition — used by all API endpoints.
 */
function getCache($conn, $cache_key) {
    $stmt = $conn->prepare("SELECT cache_data, expires_at FROM api_cache WHERE cache_key = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $cache_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return json_decode($row['cache_data'], true);
    }
    
    return null;
}

/**
 * Set cache to database.
 * Canonical definition — used by all API endpoints.
 */
function setCache($conn, $cache_key, $data, $duration_seconds) {
    $cache_data = json_encode($data, JSON_UNESCAPED_UNICODE);
    $expires_at = date('Y-m-d H:i:s', time() + $duration_seconds);
    
    try {
        $stmt = $conn->prepare("INSERT INTO api_cache (cache_key, cache_data, expires_at) VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE cache_data = ?, expires_at = ?");
        if (!$stmt) return false;
        $stmt->bind_param("sssss", $cache_key, $cache_data, $expires_at, $cache_data, $expires_at);
        return @$stmt->execute();
    } catch (\Throwable $e) {
        return false;
    }
}

// SETTINGS HELPERS

/**
 * Update or insert a setting value.
 * Canonical definition — used by admin/settings.php and admin/api-settings.php.
 */
function updateSetting($conn, $key, $value) {
    $stmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        return $stmt->execute();
    }

    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->bind_param("ss", $key, $value);
    return $stmt->execute();
}

// PAGINATION HELPER

/**
 * Render pagination nav HTML.
 * @param int $page Current page
 * @param int $total_pages Total pages
 * @param array $params Extra query params (e.g. ['slug' => 'tech', 'q' => 'search'])
 */
function renderPagination($page, $total_pages, $params = []) {
    if ($total_pages <= 1) return;
    
    // Build base query string without page
    $base = '';
    foreach ($params as $k => $v) {
        $base .= $k . '=' . urlencode($v) . '&';
    }
    
    echo '<nav class="custom-pagination">';
    
    if ($page > 1) {
        echo '<a href="?' . $base . 'page=' . ($page - 1) . '" class="page-link"><i class="fas fa-chevron-left"></i></a>';
    }
    
    $start_page = max(1, $page - 2);
    $end_page = min($total_pages, $page + 2);
    
    if ($start_page > 1) {
        echo '<a href="?' . $base . 'page=1" class="page-link">1</a>';
        echo '<span class="page-link disabled">...</span>';
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        $active = ($i == $page) ? ' active' : '';
        echo '<a href="?' . $base . 'page=' . $i . '" class="page-link' . $active . '">' . $i . '</a>';
    }
    
    if ($end_page < $total_pages) {
        echo '<span class="page-link disabled">...</span>';
        echo '<a href="?' . $base . 'page=' . $total_pages . '" class="page-link">' . $total_pages . '</a>';
    }
    
    if ($page < $total_pages) {
        echo '<a href="?' . $base . 'page=' . ($page + 1) . '" class="page-link"><i class="fas fa-chevron-right"></i></a>';
    }
    
    echo '</nav>';
}

// DEBUG MODE
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', ENVIRONMENT === 'development');
}

/**
 * Debug print helper
 */
function dd($var) {
    if (DEBUG_MODE) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
        die();
    }
}
