<?php
// CLI mode: hardcode SITE_URL
define('SITE_URL', 'http://localhost/TVRI%20NEWS%20HUB/');

require 'config/config.php';

$conn = getDBConnection();

// Shared weather helpers (getAdm3FirstByAdm2, buildThreeDaySummary, etc.)
require_once __DIR__ . '/includes/weather-helpers.php';
// Shared BMKG API library (direct calls, no HTTP loopback)
require_once __DIR__ . '/includes/bmkg-api.php';

/**
 * CLI-specific fetch: uses shared helpers + direct BMKG API calls.
 * v2: Uses 1 ADM3 per city + direct API call (no HTTP loopback).
 */
function fetchCompactWeatherByAdm2_CLI($conn, $adm2Code, $displayName) {
    $cacheKey = 'homepage_weather_adm2_' . str_replace('.', '_', $adm2Code);

    // Get the primary ADM3 code (just 1, not 3)
    $adm3 = getAdm3FirstByAdm2($adm2Code);
    if (!$adm3) {
        echo "  ❌ ADM3 null untuk {$displayName} ({$adm2Code})\n";
        return null;
    }

    echo "  🌐 Fetch ADM3={$adm3} via direct BMKG API...\n";

    // Call BMKG API directly (no HTTP loopback to own server)
    $result = bmkg_fetchCachedForecastByAdm3($conn, $adm3);
    if (!$result || bmkg_isCachedFailure($result) || empty($result['prakiraan'])) {
        echo "  ❌ BMKG fetch gagal untuk {$displayName} (ADM3={$adm3})\n";
        return null;
    }

    // Build 3-day summary using severity-based algorithm
    $days = buildThreeDaySummary($result['prakiraan']);

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

    $saveQ = "INSERT INTO api_cache (cache_key, cache_data, expires_at)
              VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))
              ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), expires_at = DATE_ADD(NOW(), INTERVAL 2 HOUR)";
    $saveStmt = mysqli_prepare($conn, $saveQ);
    $jsonItem = json_encode($item, JSON_UNESCAPED_UNICODE);
    mysqli_stmt_bind_param($saveStmt, 'ss', $cacheKey, $jsonItem);
    mysqli_stmt_execute($saveStmt);

    echo "  ✅ {$displayName}: {$item['temp']}°C, {$item['desc']}\n";
    return $item;
}

echo "=== WEATHER CACHE BUILDER ===\n\n";

$widgetConfig = [
    ['adm2' => '71.71', 'name' => 'Kota Manado', 'enabled' => 1, 'order' => 1],
    ['adm2' => '71.72', 'name' => 'Kota Bitung', 'enabled' => 1, 'order' => 2],
    ['adm2' => '71.73', 'name' => 'Kota Tomohon', 'enabled' => 1, 'order' => 3],
    ['adm2' => '71.74', 'name' => 'Kota Kotamobagu', 'enabled' => 1, 'order' => 4],
    ['adm2' => '71.04', 'name' => 'Bolaang Mongondow', 'enabled' => 1, 'order' => 5],
    ['adm2' => '71.03', 'name' => 'Kepulauan Sangihe', 'enabled' => 1, 'order' => 6],
    ['adm2' => '71.09', 'name' => 'Minahasa Utara', 'enabled' => 1, 'order' => 7],
    ['adm2' => '71.08', 'name' => 'Minahasa Tenggara', 'enabled' => 1, 'order' => 8],
    ['adm2' => '71.10', 'name' => 'Bolaang Mongondow Timur', 'enabled' => 1, 'order' => 9],
    ['adm2' => '71.11', 'name' => 'Kep. Siau Tagulandang Biaro', 'enabled' => 1, 'order' => 10],
    ['adm2' => '71.12', 'name' => 'Minahasa Selatan', 'enabled' => 1, 'order' => 11],
    ['adm2' => '71.05', 'name' => 'Kepulauan Talaud', 'enabled' => 1, 'order' => 12],
    ['adm2' => '71.06', 'name' => 'Minahasa', 'enabled' => 1, 'order' => 13],
    ['adm2' => '71.13', 'name' => 'Bolaang Mongondow Selatan', 'enabled' => 1, 'order' => 14],
    ['adm2' => '71.14', 'name' => 'Bolaang Mongondow Utara', 'enabled' => 1, 'order' => 15],
];

$weatherWidgetItems = [];
foreach ($widgetConfig as $cfg) {
    $adm2 = $cfg['adm2'];
    $name = $cfg['name'];
    echo "{$cfg['order']}. {$name} ({$adm2})\n";
    $compact = fetchCompactWeatherByAdm2_CLI($conn, $adm2, $name);
    if ($compact) {
        $weatherWidgetItems[] = $compact;
    }
    echo "\n";
}

echo "\n=== CACHE WIDGET SNAPSHOT ===\n";
$cacheSignature = md5(json_encode($widgetConfig, JSON_UNESCAPED_UNICODE));
$widgetCacheKey = 'homepage_weather_widget_3day_' . $cacheSignature;
$saveWidgetCache = mysqli_prepare($conn, "INSERT INTO api_cache (cache_key, cache_data, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR)) ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), expires_at = VALUES(expires_at)");
$jsonWidgetItems = json_encode($weatherWidgetItems, JSON_UNESCAPED_UNICODE);
mysqli_stmt_bind_param($saveWidgetCache, 'ss', $widgetCacheKey, $jsonWidgetItems);
mysqli_stmt_execute($saveWidgetCache);
echo "Widget snapshot saved: {$widgetCacheKey}\n";
echo "Total items in cache: " . count($weatherWidgetItems) . "\n";

mysqli_close($conn);
echo "\nDone! Cache valid for 30/60 minutes.\n";
