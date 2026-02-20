<?php
/**
 * AJAX Cuaca — Progressive weather loading (ADM2/ADM3/ADM4)
 * 
 * GET /api/cuaca-ajax.php?type={adm2|adm3|adm4}&code={kode}
 * Response: JSON weather summary (cache: 30 min)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/weather-helpers.php';
require_once __DIR__ . '/../includes/bmkg-api.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=1800'); // 30 min browser cache

$type = trim($_GET['type'] ?? '');
$code = trim($_GET['code'] ?? '');

if (!in_array($type, ['adm2', 'adm3', 'adm4'], true) || $code === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parameter type dan code diperlukan']);
    exit;
}

$conn = getDBConnection();

if ($type === 'adm2') {
    // --- ADM2 (Kabupaten/Kota) ---
    if (!preg_match('/^\d{2}\.\d{2}$/', $code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Format ADM2 tidak valid']);
        exit;
    }

    $displayName = trim($_GET['name'] ?? $code);
    $compact = fetchCompactWeatherByAdm2($conn, $code, $displayName);

    if ($compact && !empty($compact['days'])) {
        // Add emoji icons
        foreach ($compact['days'] as &$day) {
            $day['icon'] = getWeatherIcon($day['weather']);
        }
        unset($day);
        echo json_encode([
            'success'  => true,
            'code'     => $code,
            'name'     => $compact['name'] ?? $displayName,
            'days'     => $compact['days'],
            'temp'     => $compact['temp'] ?? '-',
            'desc'     => $compact['desc'] ?? '-',
            'image'    => $compact['image'] ?? '',
            'humidity' => $compact['humidity'] ?? '-',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'code'    => $code,
            'error'   => 'Data tidak tersedia dari BMKG'
        ]);
    }

} elseif ($type === 'adm3') {
    // --- ADM3 (Kecamatan) ---
    if (!preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Format ADM3 tidak valid']);
        exit;
    }

    $result = bmkg_fetchCachedForecastByAdm3($conn, $code);
    if ($result && !bmkg_isCachedFailure($result) && !empty($result['prakiraan'])) {
        $days = buildThreeDaySummary($result['prakiraan']);
        // Tambah icon emoji ke setiap hari untuk rendering client-side
        foreach ($days as &$day) {
            $day['icon'] = getWeatherIcon($day['weather']);
        }
        unset($day);
        echo json_encode([
            'success' => true,
            'code'    => $code,
            'days'    => $days,
            'lokasi'  => $result['lokasi'] ?? []
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'code'    => $code,
            'error'   => 'Data tidak tersedia dari BMKG'
        ]);
    }

} elseif ($type === 'adm4') {
    // --- ADM4 (Kelurahan/Desa) ---
    if (!preg_match('/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/', $code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Format ADM4 tidak valid']);
        exit;
    }

    $cacheKey = 'cuaca_halaman_adm4_' . str_replace('.', '_', $code);
    $cacheDuration = max(300, (int)getSetting($conn, 'bmkg_prakiraan_cache', '3600'));
    $forecast = null;

    // Cek DB cache
    $stmt = $conn->prepare("SELECT cache_data FROM api_cache WHERE cache_key = ? AND expires_at > NOW() LIMIT 1");
    $stmt->bind_param('s', $cacheKey);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $forecast = json_decode($row['cache_data'], true);
    }

    // Kalau belum ada di cache, fetch dari BMKG API
    if (!$forecast) {
        $apiResult = bmkg_fetchPrakiraan($code);
        if ($apiResult['success']) {
            $cuacaRebuild = [];
            foreach ($apiResult['prakiraan'] as $dayGroup) {
                $cuacaRebuild[] = $dayGroup['data'];
            }
            $forecast = [
                'lokasi' => $apiResult['lokasi'],
                'data'   => [['cuaca' => $cuacaRebuild]],
            ];
            // Simpan ke cache
            $json = json_encode($forecast, JSON_UNESCAPED_UNICODE);
            $stmtSave = $conn->prepare("INSERT INTO api_cache (cache_key, cache_data, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), expires_at = VALUES(expires_at)");
            $stmtSave->bind_param('ssi', $cacheKey, $json, $cacheDuration);
            $stmtSave->execute();
        }
    }

    if ($forecast && isset($forecast['data'][0]['cuaca'])) {
        $cuacaData = $forecast['data'][0]['cuaca'];
        $days = buildThreeDaySummary($cuacaData);
        foreach ($days as &$day) {
            $day['icon'] = getWeatherIcon($day['weather']);
        }
        unset($day);
        echo json_encode([
            'success' => true,
            'code'    => $code,
            'days'    => $days,
            'lokasi'  => $forecast['lokasi'] ?? []
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'code'    => $code,
            'error'   => 'Data tidak tersedia dari BMKG'
        ]);
    }
}
