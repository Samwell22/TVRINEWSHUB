<?php
/**
 * Weather Helper Functions
 * Digunakan oleh: index.php, cuaca.php, build_weather_cache.php
 */

if (defined('WEATHER_HELPERS_LOADED')) return;
define('WEATHER_HELPERS_LOADED', true);

/**
 * Get the first ADM3 code for a given ADM2 code.
 * Reads from CSV wilayah file with fallback map.
 */
function getAdm3FirstByAdm2($adm2Code) {
    static $map = null;
    static $fallbackMap = [
        '71.71' => '71.71.07', '71.72' => '71.72.01', '71.73' => '71.73.01', '71.74' => '71.74.01',
        '71.04' => '71.04.02', '71.03' => '71.03.01', '71.09' => '71.09.04', '71.08' => '71.08.01',
        '71.10' => '71.10.01', '71.11' => '71.11.01', '71.12' => '71.12.01', '71.05' => '71.05.01',
        '71.06' => '71.06.02', '71.13' => '71.13.01', '71.14' => '71.14.01'
    ];

    if ($map !== null) {
        return $map[$adm2Code] ?? $fallbackMap[$adm2Code] ?? null;
    }

    $map = [];
    $csvFile = (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__) . '/') . 'data/wilayah/sulawesi_utara_full.csv';
    if (file_exists($csvFile) && ($fp = fopen($csvFile, 'r')) !== false) {
        $isHeader = true;
        while (($line = fgetcsv($fp)) !== false) {
            if ($isHeader) { $isHeader = false; continue; }
            $code = trim((string)($line[0] ?? ''));
            if (preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $code)) {
                $adm2 = substr($code, 0, 5);
                if (!isset($map[$adm2])) { $map[$adm2] = $code; }
            }
        }
        fclose($fp);
    } else {
        error_log('[WEATHER] CSV tidak ada: ' . $csvFile);
    }

    return $map[$adm2Code] ?? $fallbackMap[$adm2Code] ?? null;
}

/**
 * Get all ADM3 codes under an ADM2. Returns up to $limit spread codes.
 */
function getAllAdm3ByAdm2($adm2Code, $limit = 3) {
    static $map = null;
    if ($map === null) {
        $map = [];
        $csvFile = (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__) . '/') . 'data/wilayah/sulawesi_utara_full.csv';
        if (file_exists($csvFile) && ($fp = fopen($csvFile, 'r')) !== false) {
            $isHeader = true;
            while (($line = fgetcsv($fp)) !== false) {
                if ($isHeader) { $isHeader = false; continue; }
                $code = trim((string)($line[0] ?? ''));
                if (preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $code)) {
                    $adm2 = substr($code, 0, 5);
                    $map[$adm2][] = $code;
                }
            }
            fclose($fp);
        }
    }
    $all = $map[$adm2Code] ?? [];
    if (count($all) <= $limit) return $all;
    // Spread: first, middle, last
    $indices = [0, (int)(count($all) / 2), count($all) - 1];
    $result = [];
    foreach (array_unique($indices) as $i) {
        $result[] = $all[$i];
    }
    return array_slice($result, 0, $limit);
}

/**
 * Get weather emoji icon from Indonesian weather description.
 */
function getWeatherIcon($weatherDesc) {
    $weather = strtolower(trim($weatherDesc));
    
    if (strpos($weather, 'cerah') !== false) return '☀️';
    if (strpos($weather, 'berawan') !== false) return '☁️';
    if (strpos($weather, 'lebat') !== false || strpos($weather, 'petir') !== false || strpos($weather, 'badai') !== false) return '⛈️';
    if (strpos($weather, 'hujan') !== false && (strpos($weather, 'sedang') !== false || strpos($weather, 'ringan') !== false)) return '🌧️';
    if (strpos($weather, 'hujan') !== false) return '🌧️';
    if (strpos($weather, 'kabut') !== false || strpos($weather, 'berkabut') !== false) return '🌫️';
    if (strpos($weather, 'mendung') !== false) return '☁️';
    
    return '🌤️';
}

/**
 * Weather severity ranking — higher = more impactful.
 * Used to pick the "representative" weather for a day
 * instead of the most frequent one (which is always "Berawan").
 */
function getWeatherSeverity($weatherDesc) {
    $w = strtolower(trim($weatherDesc));

    // Severe
    if (strpos($w, 'petir') !== false || strpos($w, 'badai') !== false || strpos($w, 'es') !== false) return 90;
    if (strpos($w, 'lebat') !== false) return 80;
    // Moderate
    if (strpos($w, 'sedang') !== false && strpos($w, 'hujan') !== false) return 70;
    // Light rain
    if (strpos($w, 'ringan') !== false && strpos($w, 'hujan') !== false) return 60;
    // General rain
    if (strpos($w, 'hujan') !== false) return 55;
    // Fog
    if (strpos($w, 'kabut') !== false || strpos($w, 'asap') !== false) return 50;
    // Heavy clouds
    if (strpos($w, 'mendung') !== false) return 40;
    if (strpos($w, 'tebal') !== false && strpos($w, 'berawan') !== false) return 35;
    // Cloudy
    if (strpos($w, 'berawan') !== false) return 20;
    // Partly cloudy
    if (strpos($w, 'cerah berawan') !== false) return 15;
    // Clear
    if (strpos($w, 'cerah') !== false) return 10;

    return 25; // unknown defaults between cloudy/rain
}

/**
 * Build a 3-day weather summary from raw prakiraan data.
 *
 * STRATEGY (v2 — severity-based representative):
 * Instead of picking the most FREQUENT weather (which is always "Berawan"
 * in tropical areas), we pick the most SEVERE/IMPACTFUL weather among
 * daytime slots (06:00-18:00). This matches BMKG's display approach
 * and produces varied, accurate weather per day per city.
 *
 * Temperature & humidity still use min/max from all time slots.
 */
function buildThreeDaySummary($prakiraan) {
    $days = [];
    $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    // Normalize various data structures into [{data: [...]}]
    $normalized = [];
    if (!empty($prakiraan)) {
        $first = $prakiraan[0] ?? null;

        if (is_array($first) && isset($first['data']) && is_array($first['data'])) {
            $normalized = $prakiraan;
        } elseif (is_array($first) && isset($first[0]) && is_array($first[0]) && isset($first[0]['local_datetime'])) {
            foreach (array_slice($prakiraan, 0, 3) as $dayItems) {
                if (is_array($dayItems)) {
                    $normalized[] = ['data' => $dayItems];
                }
            }
        } elseif (is_array($first) && isset($first['local_datetime'])) {
            $groupedByDate = [];
            foreach ($prakiraan as $item) {
                if (!empty($item['local_datetime'])) {
                    $date = date('Y-m-d', strtotime($item['local_datetime']));
                    if (!isset($groupedByDate[$date])) {
                        $groupedByDate[$date] = [];
                    }
                    $groupedByDate[$date][] = $item;
                }
            }
            foreach (array_slice($groupedByDate, 0, 3, true) as $items) {
                $normalized[] = ['data' => $items];
            }
        }
    }
    $prakiraan = $normalized;
    
    foreach (array_slice($prakiraan, 0, 3) as $day) {
        $items = $day['data'] ?? [];
        if (!is_array($items) || empty($items)) {
            $days[] = ['weather' => '-', 'temp_min' => '-', 'temp_max' => '-', 'humidity_min' => '-', 'humidity_max' => '-', 'image' => '', 'label' => 'Hari', 'date_full' => '-', 'slots' => []];
            continue;
        }

        $weatherImageMap = [];
        $tempMin = null;
        $tempMax = null;
        $huMin = null;
        $huMax = null;
        $dateLabel = 'Hari';
        $dateFull = '-';

        // Track the most severe weather among daytime slots (06-18 local)
        $representativeWeather = null;
        $representativeSeverity = -1;
        $representativeImage = '';

        // Also collect time-slot details for per-jam display
        $slots = [];

        foreach ($items as $item) {
            $weather = trim((string)($item['weather_desc'] ?? 'Tidak diketahui'));
            if ($weather === '') $weather = 'Tidak diketahui';

            if (!empty($item['image']) && !isset($weatherImageMap[$weather])) {
                $weatherImageMap[$weather] = $item['image'];
            }

            if (isset($item['t']) && is_numeric($item['t'])) {
                $t = (int)$item['t'];
                $tempMin = ($tempMin === null) ? $t : min($tempMin, $t);
                $tempMax = ($tempMax === null) ? $t : max($tempMax, $t);
            }

            if (isset($item['hu']) && is_numeric($item['hu'])) {
                $hu = (int)$item['hu'];
                $huMin = ($huMin === null) ? $hu : min($huMin, $hu);
                $huMax = ($huMax === null) ? $hu : max($huMax, $hu);
            }

            if (!empty($item['local_datetime'])) {
                $dt = strtotime($item['local_datetime']);
                $dayNum = (int)date('w', $dt);
                $dateNum = (int)date('d', $dt);
                $monthNum = (int)date('m', $dt);
                $hour = (int)date('H', $dt);
                $dateLabel = $dayNames[$dayNum];
                $dateFull = $dateLabel . ', ' . $dateNum . ' ' . $monthNames[$monthNum];

                // Collect slot info
                $slots[] = [
                    'time'    => date('H:i', $dt),
                    'weather' => $weather,
                    'temp'    => $item['t'] ?? '-',
                    'hu'      => $item['hu'] ?? '-',
                    'image'   => $weatherImageMap[$weather] ?? ($item['image'] ?? ''),
                ];

                // Only consider daytime slots (06:00 - 18:00) for representative
                if ($hour >= 6 && $hour <= 18) {
                    $severity = getWeatherSeverity($weather);
                    if ($severity > $representativeSeverity) {
                        $representativeSeverity = $severity;
                        $representativeWeather = $weather;
                        $representativeImage = $weatherImageMap[$weather] ?? ($item['image'] ?? '');
                    }
                }
            } else {
                // No datetime — still consider for representative
                $severity = getWeatherSeverity($weather);
                if ($severity > $representativeSeverity) {
                    $representativeSeverity = $severity;
                    $representativeWeather = $weather;
                    $representativeImage = $weatherImageMap[$weather] ?? ($item['image'] ?? '');
                }
            }
        }

        // Fallback: if no daytime slot found, use overall most severe
        if ($representativeWeather === null) {
            $representativeWeather = '-';
        }

        $days[] = [
            'weather'      => $representativeWeather,
            'temp_min'     => $tempMin ?? '-',
            'temp_max'     => $tempMax ?? '-',
            'humidity_min' => $huMin ?? '-',
            'humidity_max' => $huMax ?? '-',
            'image'        => $representativeImage,
            'label'        => $dateLabel,
            'date_full'    => $dateFull,
            'slots'        => $slots, // per-jam detail for expanded view
        ];
    }

    return $days;
}

/**
 * Fetch compact weather data for a city (ADM2) with caching.
 *
 * v2 CHANGES:
 * - Uses only 1 ADM3 per city (not 3) for accuracy + speed.
 *   Merging 3 ADM3's dilutes variation → "Berawan" always wins.
 * - Calls bmkg_fetchCachedForecastByAdm3() DIRECTLY instead of
 *   HTTP loopback to api/bmkg-prakiraan.php. Eliminates the
 *   file_get_contents-to-self bottleneck (was 45 HTTP calls → 0).
 * - Uses severity-based buildThreeDaySummary() for representative weather.
 */
function fetchCompactWeatherByAdm2($conn, $adm2Code, $displayName) {
    // Ensure BMKG API library is loaded
    require_once __DIR__ . '/bmkg-api.php';

    $cacheKey = 'homepage_weather_adm2_' . str_replace('.', '_', $adm2Code);

    // Check per-city cache first (1 hour TTL)
    $cacheQ = "SELECT cache_data FROM api_cache WHERE cache_key = ? AND expires_at > NOW() LIMIT 1";
    $stmt = mysqli_prepare($conn, $cacheQ);
    mysqli_stmt_bind_param($stmt, 's', $cacheKey);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $decoded = json_decode($row['cache_data'], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    // Get the PRIMARY ADM3 code (just 1, not 3)
    $adm3 = getAdm3FirstByAdm2($adm2Code);
    if (!$adm3) {
        error_log("[WEATHER] Tidak ada ADM3 untuk ADM2={$adm2Code}");
        return null;
    }

    // Call BMKG API directly via shared library (NO HTTP loopback)
    $result = bmkg_fetchCachedForecastByAdm3($conn, $adm3);
    if (!$result || bmkg_isCachedFailure($result) || empty($result['prakiraan'])) {
        error_log("[WEATHER] BMKG fetch gagal untuk ADM2={$adm2Code} ADM3={$adm3}");
        return null;
    }

    // Build 3-day summary using severity-based algorithm
    $days = buildThreeDaySummary($result['prakiraan']);

    // Current snapshot from first time slot
    $firstSlot = $result['prakiraan'][0]['data'][0] ?? [];

    $item = [
        'adm2' => $adm2Code,
        'name' => $displayName,
        'temp' => $firstSlot['t'] ?? '-',
        'desc' => $days[0]['weather'] ?? ($firstSlot['weather_desc'] ?? '-'),
        'image' => $days[0]['image'] ?? ($firstSlot['image'] ?? ''),
        'humidity' => $days[0]['humidity_min'] ?? ($firstSlot['hu'] ?? '-'),
        'days' => $days,
    ];

    // Cache for 2 hours (was 1 hour — longer cache = fewer BMKG calls)
    $saveQ = "INSERT INTO api_cache (cache_key, cache_data, expires_at)
              VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))
              ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), expires_at = DATE_ADD(NOW(), INTERVAL 2 HOUR)";
    $saveStmt = mysqli_prepare($conn, $saveQ);
    $jsonItem = json_encode($item, JSON_UNESCAPED_UNICODE);
    mysqli_stmt_bind_param($saveStmt, 'ss', $cacheKey, $jsonItem);
    mysqli_stmt_execute($saveStmt);

    return $item;
}
