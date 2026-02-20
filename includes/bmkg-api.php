<?php
/**
 * BMKG API - Shared Library
 * Digunakan oleh: weather-helpers.php, api/bmkg-prakiraan.php, cuaca.php, build_weather_cache.php
 */

if (defined('BMKG_API_LOADED')) return;
define('BMKG_API_LOADED', true);

/**
 * Path cache file mapping ADM3 -> ADM4
 */
function bmkg_getAdm3MapCacheFile() {
    return (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__) . '/') . 'cache/bmkg-adm3-adm4-map-sulut.json';
}

/**
 * Path CSV wilayah ADM1-ADM4
 */
function bmkg_getWilayahCsvFile() {
    return (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__) . '/') . 'data/wilayah/sulawesi_utara_full.csv';
}

/**
 * Load cache mapping ADM3 -> ADM4 dari file
 */
function bmkg_loadAdm3MapCache() {
    $file = bmkg_getAdm3MapCacheFile();
    if (!file_exists($file)) return [];
    $json = @file_get_contents($file);
    if ($json === false) return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Save cache mapping ADM3 -> ADM4 ke file
 */
function bmkg_saveAdm3MapCache($cache) {
    $file = bmkg_getAdm3MapCacheFile();
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    @file_put_contents($file, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * Validasi format ADM3 (xx.xx.xx)
 */
function bmkg_isValidAdm3($adm3) {
    return preg_match('/^\d{2}\.\d{2}\.\d{2}$/', (string)$adm3) === 1;
}

/**
 * Validasi format ADM4 (xx.xx.xx.xxxx)
 */
function bmkg_isValidAdm4($adm4) {
    return preg_match('/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/', (string)$adm4) === 1;
}

/**
 * Ambil daftar ADM4 resmi dari CSV untuk 1 ADM3
 */
function bmkg_getAdm4CandidatesFromCsv($adm3) {
    static $adm3ToAdm4 = null;

    if ($adm3ToAdm4 === null) {
        $adm3ToAdm4 = [];
        $file = bmkg_getWilayahCsvFile();
        if (file_exists($file) && is_readable($file)) {
            if (($handle = fopen($file, 'r')) !== false) {
                $isHeader = true;
                while (($row = fgetcsv($handle)) !== false) {
                    if ($isHeader) { $isHeader = false; continue; }
                    $code = isset($row[0]) ? trim((string)$row[0]) : '';
                    if (!bmkg_isValidAdm4($code)) continue;
                    $parentAdm3 = substr($code, 0, 8);
                    if (!isset($adm3ToAdm4[$parentAdm3])) $adm3ToAdm4[$parentAdm3] = [];
                    $adm3ToAdm4[$parentAdm3][] = $code;
                }
                fclose($handle);
            }
        }
    }

    return $adm3ToAdm4[$adm3] ?? [];
}

/**
 * Fetch prakiraan cuaca dari BMKG public API (raw HTTP call).
 * No caching — caller is responsible for caching.
 *
 * @return array {success: bool, lokasi: array, prakiraan: array, total_hari: int}
 */
function bmkg_fetchPrakiraan($adm4_code) {
    $api_url = "https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4={$adm4_code}";

    $context = stream_context_create([
        'http' => [
            'timeout' => 20,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);

    $response_body = @file_get_contents($api_url, false, $context);
    if ($response_body === false) {
        return ['success' => false, 'error' => 'Gagal mengambil data dari BMKG API'];
    }

    $data = json_decode($response_body, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Data bukan format JSON valid: ' . json_last_error_msg()];
    }

    if (!isset($data['lokasi'])) {
        return ['success' => false, 'error' => 'Kode ADM4 tidak valid atau data tidak ditemukan'];
    }

    // Parse lokasi
    $lokasi = [
        'adm1' => $data['lokasi']['adm1'] ?? '',
        'adm2' => $data['lokasi']['adm2'] ?? '',
        'adm3' => $data['lokasi']['adm3'] ?? '',
        'adm4' => $data['lokasi']['adm4'] ?? '',
        'desa' => $data['lokasi']['desa'] ?? 'N/A',
        'kecamatan' => $data['lokasi']['kecamatan'] ?? 'N/A',
        'kotkab' => $data['lokasi']['kotkab'] ?? 'N/A',
        'provinsi' => $data['lokasi']['provinsi'] ?? 'N/A',
        'lat' => $data['lokasi']['lat'] ?? 0,
        'lon' => $data['lokasi']['lon'] ?? 0,
        'timezone' => $data['lokasi']['timezone'] ?? 'Asia/Makassar'
    ];

    // Parse prakiraan cuaca
    $prakiraan = [];
    if (isset($data['data'][0]['cuaca']) && is_array($data['data'][0]['cuaca'])) {
        foreach ($data['data'][0]['cuaca'] as $index_hari => $prakiraan_harian) {
            $hari_data = [];
            if (is_array($prakiraan_harian)) {
                foreach ($prakiraan_harian as $prakiraan_item) {
                    $img_url = '';
                    if (isset($prakiraan_item['image']) && !empty($prakiraan_item['image'])) {
                        $img_url = str_replace(' ', '%20', $prakiraan_item['image']);
                    }
                    $hari_data[] = [
                        'utc_datetime'    => $prakiraan_item['utc_datetime'] ?? '',
                        'local_datetime'  => $prakiraan_item['local_datetime'] ?? '',
                        't'               => $prakiraan_item['t'] ?? 0,
                        'hu'              => $prakiraan_item['hu'] ?? 0,
                        'weather'         => $prakiraan_item['weather'] ?? '',
                        'weather_desc'    => $prakiraan_item['weather_desc'] ?? '',
                        'weather_desc_en' => $prakiraan_item['weather_desc_en'] ?? '',
                        'ws'              => $prakiraan_item['ws'] ?? 0,
                        'wd'              => $prakiraan_item['wd'] ?? '',
                        'wd_deg'          => $prakiraan_item['wd_deg'] ?? 0,
                        'wd_to'           => $prakiraan_item['wd_to'] ?? '',
                        'tcc'             => $prakiraan_item['tcc'] ?? 0,
                        'vs_text'         => $prakiraan_item['vs_text'] ?? '',
                        'image'           => $img_url,
                        'analysis_date'   => $prakiraan_item['analysis_date'] ?? ''
                    ];
                }
            }
            if (!empty($hari_data)) {
                $prakiraan[] = ['hari_ke' => $index_hari + 1, 'data' => $hari_data];
            }
        }
    }

    return [
        'success'    => true,
        'lokasi'     => $lokasi,
        'prakiraan'  => $prakiraan,
        'total_hari' => count($prakiraan)
    ];
}

/**
 * Resolve ADM3 -> ADM4 dan fetch prakiraan BMKG.
 * Uses file-based cache for ADM3→ADM4 mapping, then calls bmkg_fetchPrakiraan().
 *
 * @return array {success: bool, adm4_code: string, source: string, data: array}
 */
function bmkg_resolveAdm3ToForecast($adm3, $retry_failed = false) {
    $mapCache = bmkg_loadAdm3MapCache();

    if (isset($mapCache[$adm3]) && is_string($mapCache[$adm3]) && $mapCache[$adm3] !== '') {
        $cached_value = $mapCache[$adm3];

        if ($cached_value === '__NO_ADM4__' && !$retry_failed) {
            return [
                'success' => false,
                'error' => 'ADM4 untuk ADM3 ini belum ditemukan (cached fail). retry_failed=1 untuk coba ulang.'
            ];
        }

        if ($cached_value !== '__NO_ADM4__') {
            $cached_result = bmkg_fetchPrakiraan($cached_value);
            if ($cached_result['success']) {
                return ['success' => true, 'adm4_code' => $cached_value, 'source' => 'map_cache', 'data' => $cached_result];
            }
        }
    }

    // Kandidat ADM4 dari CSV (prioritas utama)
    $candidates = bmkg_getAdm4CandidatesFromCsv($adm3);

    // Fallback jika CSV tidak tersedia
    if (empty($candidates)) {
        $candidates = [];
        for ($i = 1001; $i <= 2012; $i++) {
            $suffix = str_pad($i, 4, '0', STR_PAD_LEFT);
            $candidates[] = $adm3 . '.' . $suffix;
        }
    }

    foreach ($candidates as $candidate_adm4) {
        $result = bmkg_fetchPrakiraan($candidate_adm4);
        if ($result['success']) {
            $mapCache[$adm3] = $candidate_adm4;
            bmkg_saveAdm3MapCache($mapCache);
            return ['success' => true, 'adm4_code' => $candidate_adm4, 'source' => 'discovered', 'data' => $result];
        }
    }

    $mapCache[$adm3] = '__NO_ADM4__';
    bmkg_saveAdm3MapCache($mapCache);

    return ['success' => false, 'error' => 'Gagal resolve ADM3 ke ADM4.'];
}

/**
 * Fetch weather forecast for an ADM3 code WITH DB caching.
 * This is the main entry point for widget / homepage usage.
 *
 * Returns the same structure as bmkg_fetchPrakiraan() on success,
 * or null on failure.
 *
 * @param mysqli $conn Database connection
 * @param string $adm3 ADM3 code (xx.xx.xx)
 * @return array|null {success, lokasi, prakiraan, total_hari}
 */
function bmkg_fetchCachedForecastByAdm3($conn, $adm3) {
    $cache_key = 'bmkg_prakiraan_adm3_' . str_replace('.', '_', $adm3);
    $cache_duration = 3600; // 1 hour

    // Check DB cache first
    $cached = getCache($conn, $cache_key);
    if ($cached !== null) {
        return $cached;
    }

    // Resolve ADM3 → ADM4 and fetch from BMKG
    // retry_failed=true so that __NO_ADM4__ entries get retried periodically
    // (DB cache prevents hammering — retries at most once per hour)
    $resolved = bmkg_resolveAdm3ToForecast($adm3, true);
    if (!$resolved['success']) {
        // Cache the failure in DB (as null marker) for 2 hours to avoid
        // retrying genuinely unavailable ADM3 on every page load.
        setCache($conn, $cache_key, ['__failed__' => true], 7200);
        return null;
    }

    $result = $resolved['data'];

    // Save to DB cache
    setCache($conn, $cache_key, $result, $cache_duration);

    return $result;
}

/**
 * Wrapper: check if a cached result is a failure marker.
 */
function bmkg_isCachedFailure($data) {
    return is_array($data) && isset($data['__failed__']) && $data['__failed__'] === true;
}
