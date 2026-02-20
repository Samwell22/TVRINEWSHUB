<?php
/**
 * BMKG Prakiraan Cuaca API — Multi-kota & ADM3/ADM4
 * 
 * GET /api/bmkg-prakiraan.php?city={name}&adm3={xx.xx.xx}&adm4={xx.xx.xx.xxxx}
 * Response: JSON (cache: 5 min)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300'); // 5 min browser cache

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/bmkg-api.php';

// Ambil koneksi database
$conn = getDBConnection();

// getCache() and setCache() are defined in config/config.php

// Backward-compatible function aliases:
// Old code (api-level) called these names — now delegated
// to the shared bmkg-api.php library with bmkg_ prefix.
if (!function_exists('getAdm3MapCacheFile')) {
    function getAdm3MapCacheFile() { return bmkg_getAdm3MapCacheFile(); }
}
if (!function_exists('getWilayahCsvFile')) {
    function getWilayahCsvFile() { return bmkg_getWilayahCsvFile(); }
}
if (!function_exists('loadAdm3MapCache')) {
    function loadAdm3MapCache() { return bmkg_loadAdm3MapCache(); }
}
if (!function_exists('saveAdm3MapCache')) {
    function saveAdm3MapCache($cache) { return bmkg_saveAdm3MapCache($cache); }
}
if (!function_exists('isValidAdm3')) {
    function isValidAdm3($adm3) { return bmkg_isValidAdm3($adm3); }
}
if (!function_exists('isValidAdm4')) {
    function isValidAdm4($adm4) { return bmkg_isValidAdm4($adm4); }
}
if (!function_exists('getAdm4CandidatesFromCsv')) {
    function getAdm4CandidatesFromCsv($adm3) { return bmkg_getAdm4CandidatesFromCsv($adm3); }
}
if (!function_exists('resolveAdm3ToForecast')) {
    function resolveAdm3ToForecast($adm3, $retry_failed = false) { return bmkg_resolveAdm3ToForecast($adm3, $retry_failed); }
}
if (!function_exists('fetchBMKGPrakiraan')) {
    function fetchBMKGPrakiraan($adm4_code) { return bmkg_fetchPrakiraan($adm4_code); }
}

/**
 * Get ADM4 code untuk kota
 */
function getADM4Code($conn, $city) {
    $city_lower = strtolower(trim($city));
    $setting_key = "bmkg_adm4_{$city_lower}";
    return getSetting($conn, $setting_key, '');
}

// fetchBMKGPrakiraan() is now provided by includes/bmkg-api.php
// via the backward-compatible alias defined above.

// MAIN EXECUTION

try {
    // Check if prakiraan is enabled
    $enabled = getSetting($conn, 'bmkg_enable_prakiraan', '1');
    if ($enabled !== '1') {
        echo json_encode([
            'success' => false,
            'error' => 'Fitur prakiraan cuaca sedang dinonaktifkan',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // PRIORITAS 1: Mode ADM3 (kecamatan)
    $adm4 = isset($_GET['adm4']) ? trim($_GET['adm4']) : '';
    $adm3 = isset($_GET['adm3']) ? trim($_GET['adm3']) : '';
    $retry_failed = isset($_GET['retry_failed']) && $_GET['retry_failed'] === '1';

    // PRIORITAS 1: Mode ADM4 (kelurahan/desa)
    if (!empty($adm4)) {
        if (!isValidAdm4($adm4)) {
            echo json_encode([
                'success' => false,
                'error' => 'Format adm4 tidak valid. Gunakan format xx.xx.xx.xxxx, contoh: 71.71.07.1008',
                'data' => null
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $cache_key = 'bmkg_prakiraan_adm4_' . str_replace('.', '_', $adm4);
        $cache_duration = (int)getSetting($conn, 'bmkg_prakiraan_cache', '3600');

        $cached_data = getCache($conn, $cache_key);
        if ($cached_data !== null) {
            echo json_encode([
                'success' => true,
                'mode' => 'adm4',
                'adm4_code' => $adm4,
                'data' => $cached_data,
                'cached' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = fetchBMKGPrakiraan($adm4);
        if (!$result['success']) {
            echo json_encode([
                'success' => false,
                'mode' => 'adm4',
                'adm4_code' => $adm4,
                'error' => $result['error'],
                'data' => null
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        setCache($conn, $cache_key, $result, $cache_duration);
        echo json_encode([
            'success' => true,
            'mode' => 'adm4',
            'adm4_code' => $adm4,
            'data' => $result,
            'cached' => false,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // PRIORITAS 2: Mode ADM3 (kecamatan)
    if (!empty($adm3)) {
        if (!isValidAdm3($adm3)) {
            echo json_encode([
                'success' => false,
                'error' => 'Format adm3 tidak valid. Gunakan format xx.xx.xx, contoh: 71.71.07',
                'data' => null
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $cache_key = 'bmkg_prakiraan_adm3_' . str_replace('.', '_', $adm3);
        $cache_duration = (int)getSetting($conn, 'bmkg_prakiraan_cache', '3600');

        $cached_data = getCache($conn, $cache_key);
        if ($cached_data !== null) {
            echo json_encode([
                'success' => true,
                'mode' => 'adm3',
                'adm3_code' => $adm3,
                'adm4_code' => $cached_data['lokasi']['adm4'] ?? null,
                'data' => $cached_data,
                'cached' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $resolved = resolveAdm3ToForecast($adm3, $retry_failed);
        if (!$resolved['success']) {
            echo json_encode([
                'success' => false,
                'mode' => 'adm3',
                'adm3_code' => $adm3,
                'error' => $resolved['error'],
                'data' => null
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = $resolved['data'];
        setCache($conn, $cache_key, $result, $cache_duration);

        echo json_encode([
            'success' => true,
            'mode' => 'adm3',
            'adm3_code' => $adm3,
            'adm4_code' => $resolved['adm4_code'],
            'resolve_source' => $resolved['source'],
            'data' => $result,
            'cached' => false,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // PRIORITAS 3: Mode city (existing behavior)
    // Get city parameter or default
    $city = isset($_GET['city']) ? trim($_GET['city']) : getSetting($conn, 'bmkg_default_city', 'manado');
    $city_lower = strtolower($city);
    
    // Validate city
    $valid_cities = ['manado', 'bitung', 'tomohon', 'kotamobagu'];
    if (!in_array($city_lower, $valid_cities)) {
        echo json_encode([
            'success' => false,
            'error' => 'Kota tidak valid. Pilihan: manado, bitung, tomohon, kotamobagu',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get ADM4 code for the city
    $adm4_code = getADM4Code($conn, $city_lower);
    
    if (empty($adm4_code)) {
        echo json_encode([
            'success' => false,
            'error' => "Kode ADM4 untuk {$city} belum dikonfigurasi di settings",
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Cache configuration
    $cache_key = "bmkg_prakiraan_{$city_lower}";
    $cache_duration = (int)getSetting($conn, 'bmkg_prakiraan_cache', '3600'); // Default 1 jam
    
    // Check cache first
    $cached_data = getCache($conn, $cache_key);
    
    if ($cached_data !== null) {
        // Return from cache
        echo json_encode([
            'success' => true,
            'city' => ucfirst($city_lower),
            'adm4_code' => $adm4_code,
            'data' => $cached_data,
            'cached' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Fetch fresh data
    $result = fetchBMKGPrakiraan($adm4_code);
    
    if (!$result['success']) {
        echo json_encode([
            'success' => false,
            'error' => $result['error'],
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Save to cache
    setCache($conn, $cache_key, $result, $cache_duration);
    
    // Return fresh data
    echo json_encode([
        'success' => true,
        'city' => ucfirst($city_lower),
        'adm4_code' => $adm4_code,
        'data' => $result,
        'cached' => false,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Terjadi kesalahan: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
