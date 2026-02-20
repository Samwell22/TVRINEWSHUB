<?php
/**
 * Halaman Cuaca - Navigasi Bertingkat (ADM2 > ADM3 > ADM4)
 * Dibuat page-based agar klik membuka halaman baru dan mengurangi beban render inline.
 */

require_once 'config/config.php';
$conn = getDBConnection();

// getSetting() is defined in config/config.php

function loadWilayahTree(string $csvFile): array {
    $tree = [
        'adm1' => [],
        'adm2' => [],
        'adm3' => [],
        'adm4' => [],
        'adm2ByAdm1' => [],
        'adm3ByAdm2' => [],
        'adm4ByAdm3' => [],
    ];

    if (!file_exists($csvFile) || !is_readable($csvFile)) {
        return $tree;
    }

    $fp = fopen($csvFile, 'r');
    if ($fp === false) {
        return $tree;
    }

    $isHeader = true;
    while (($row = fgetcsv($fp)) !== false) {
        if ($isHeader) {
            $isHeader = false;
            continue;
        }

        $kode = trim((string)($row[0] ?? ''));
        $nama = trim((string)($row[1] ?? ''));
        if ($kode === '' || $nama === '') {
            continue;
        }

        if (preg_match('/^\d{2}$/', $kode)) {
            $tree['adm1'][$kode] = $nama;
            continue;
        }
        if (preg_match('/^\d{2}\.\d{2}$/', $kode)) {
            $tree['adm2'][$kode] = $nama;
            $parent = substr($kode, 0, 2);
            $tree['adm2ByAdm1'][$parent][$kode] = $nama;
            continue;
        }
        if (preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $kode)) {
            $tree['adm3'][$kode] = $nama;
            $parent = substr($kode, 0, 5);
            $tree['adm3ByAdm2'][$parent][$kode] = $nama;
            continue;
        }
        if (preg_match('/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/', $kode)) {
            $tree['adm4'][$kode] = $nama;
            $parent = substr($kode, 0, 8);
            $tree['adm4ByAdm3'][$parent][$kode] = $nama;
        }
    }
    fclose($fp);

    foreach (['adm1', 'adm2', 'adm3', 'adm4'] as $key) {
        ksort($tree[$key]);
    }
    foreach (['adm2ByAdm1', 'adm3ByAdm2', 'adm4ByAdm3'] as $groupKey) {
        foreach ($tree[$groupKey] as $parent => $children) {
            ksort($children);
            $tree[$groupKey][$parent] = $children;
        }
    }

    return $tree;
}

// Weather helper functions (getWeatherIcon, buildThreeDaySummary, etc.)
require_once __DIR__ . '/includes/weather-helpers.php';
// Shared BMKG API library for direct API calls
require_once __DIR__ . '/includes/bmkg-api.php';

function getCachedForecastDetail($conn, string $adm4): ?array {
    $cacheKey = 'cuaca_halaman_adm4_' . str_replace('.', '_', $adm4);
    static $cacheDuration = null;
    if ($cacheDuration === null) {
        $cacheDuration = max(300, (int)getSetting($conn, 'bmkg_prakiraan_cache', '3600'));
    }

    $stmt = $conn->prepare("SELECT cache_data FROM api_cache WHERE cache_key = ? AND expires_at > NOW() LIMIT 1");
    $stmt->bind_param('s', $cacheKey);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $decoded = json_decode($row['cache_data'], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    // Use shared library function instead of raw file_get_contents
    $result = bmkg_fetchPrakiraan($adm4);
    if (!$result['success']) {
        return null;
    }

    // Re-construct in the format cuaca.php expects (raw BMKG structure)
    // Rebuild cuaca array from parsed prakiraan
    $cuacaRebuild = [];
    foreach ($result['prakiraan'] as $dayGroup) {
        $cuacaRebuild[] = $dayGroup['data'];
    }

    $decoded = [
        'lokasi' => $result['lokasi'],
        'data' => [['cuaca' => $cuacaRebuild]],
    ];

    $stmtSave = $conn->prepare("INSERT INTO api_cache (cache_key, cache_data, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), expires_at = VALUES(expires_at)");
    $json = json_encode($decoded, JSON_UNESCAPED_UNICODE);
    $stmtSave->bind_param('ssi', $cacheKey, $json, $cacheDuration);
    $stmtSave->execute();

    return $decoded;
}

$enablePeringatan = getSetting($conn, 'bmkg_enable_peringatan', '1');
$enablePrakiraan = getSetting($conn, 'bmkg_enable_prakiraan', '1');

$csvFile = __DIR__ . '/data/wilayah/sulawesi_utara_full.csv';
$tree = loadWilayahTree($csvFile);

// SIMPLIFIED NAVIGATION: detect level from single param
$selectedAdm4 = trim((string)($_GET['adm4'] ?? ''));
$selectedAdm3 = trim((string)($_GET['adm3'] ?? ''));
$selectedAdm2 = trim((string)($_GET['adm2'] ?? ''));
$selectedAdm1 = trim((string)($_GET['adm1'] ?? '71'));

// Auto-derive parent dari child code
if ($selectedAdm4 !== '' && isset($tree['adm4'][$selectedAdm4])) {
    $selectedAdm3 = substr($selectedAdm4, 0, 8);
    $selectedAdm2 = substr($selectedAdm4, 0, 5);
    $selectedAdm1 = substr($selectedAdm4, 0, 2);
} elseif ($selectedAdm3 !== '' && isset($tree['adm3'][$selectedAdm3])) {
    $selectedAdm2 = substr($selectedAdm3, 0, 5);
    $selectedAdm1 = substr($selectedAdm3, 0, 2);
    $selectedAdm4 = '';
} elseif ($selectedAdm2 !== '' && isset($tree['adm2'][$selectedAdm2])) {
    $selectedAdm1 = substr($selectedAdm2, 0, 2);
    $selectedAdm3 = '';
    $selectedAdm4 = '';
} else {
    // Default: show ADM2 list for ADM1=71 (Sulawesi Utara)
    if (!isset($tree['adm1'][$selectedAdm1])) {
        $selectedAdm1 = (string)(array_key_first($tree['adm1']) ?: '71');
    }
    $selectedAdm2 = '';
    $selectedAdm3 = '';
    $selectedAdm4 = '';
}

$adm2List = $tree['adm2ByAdm1'][$selectedAdm1] ?? [];
$adm3List = ($selectedAdm2 !== '') ? ($tree['adm3ByAdm2'][$selectedAdm2] ?? []) : [];
$adm4List = ($selectedAdm3 !== '') ? ($tree['adm4ByAdm3'][$selectedAdm3] ?? []) : [];

// Fetch preview cuaca untuk level yang aktif
$previewAdm2 = null;
$previewAdm3 = null;
$detailForecast = null;
$allAdm2Weather = []; // Untuk list ADM2
$allAdm3Weather = []; // Untuk list ADM3 (kecamatan)
$allAdm4Weather = []; // Untuk list ADM4 (kelurahan/desa)
$cuacaLevel1Config = []; // Config for Level 1 skeleton AJAX

if ($enablePrakiraan === '1') {
    if ($selectedAdm4 !== '') {
        // Level 4: Detail prakiraan kelurahan/desa
        $detailForecast = getCachedForecastDetail($conn, $selectedAdm4);
    } elseif ($selectedAdm3 !== '') {
        // Level 3: Only fetch ADM3 preview (fast, from DB cache)
        // Kelurahan/desa weather loaded progressively via AJAX
        $resolvedPreview = bmkg_fetchCachedForecastByAdm3($conn, $selectedAdm3);
        if ($resolvedPreview && !bmkg_isCachedFailure($resolvedPreview) && !empty($resolvedPreview['prakiraan'])) {
            $cuacaRebuild = [];
            foreach ($resolvedPreview['prakiraan'] as $dayGroup) {
                $cuacaRebuild[] = $dayGroup['data'];
            }
            $previewAdm3 = [
                'lokasi' => $resolvedPreview['lokasi'],
                'data' => [['cuaca' => $cuacaRebuild]],
            ];
        }
    } elseif ($selectedAdm2 !== '') {
        // Level 2: Only fetch ADM2 preview (fast, from existing cache)
        // Kecamatan weather loaded progressively via AJAX (skeleton UI)
        $cacheKey = 'homepage_weather_adm2_' . str_replace('.', '_', $selectedAdm2);
        $stmt = $conn->prepare("SELECT cache_data FROM api_cache WHERE cache_key = ? AND expires_at > NOW() LIMIT 1");
        $stmt->bind_param('s', $cacheKey);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $previewAdm2 = json_decode($row['cache_data'], true);
        }
    } else {
        // Level 1: Only load config — weather data fetched via AJAX (skeleton UI)
        $widgetCache = getSetting($conn, 'weather_widget_locations', '');
        $widgetConfig = json_decode($widgetCache, true);
        if (!is_array($widgetConfig) || empty($widgetConfig)) {
            // Fallback: use adm2List from CSV
            foreach ($adm2List as $code => $name) {
                $cuacaLevel1Config[] = ['adm2' => $code, 'name' => $name];
            }
        } else {
            usort($widgetConfig, function($a, $b) {
                return ((int)($a['order'] ?? 999)) <=> ((int)($b['order'] ?? 999));
            });
            foreach ($widgetConfig as $cfg) {
                if ((int)($cfg['enabled'] ?? 0) !== 1) continue;
                $adm2Code = $cfg['adm2'] ?? '';
                if ($adm2Code === '') continue;
                $cuacaLevel1Config[] = [
                    'adm2' => $adm2Code,
                    'name' => $cfg['name'] ?? ($adm2List[$adm2Code] ?? $adm2Code),
                ];
            }
        }
    }
}

// Generate default date headers for skeleton table (today + 2 days)
$_dayAbbr  = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
$_monthAbbr = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$defaultDateHeaders = [];
for ($i = 0; $i < 3; $i++) {
    $ts = strtotime("+{$i} day");
    $defaultDateHeaders[] = $_dayAbbr[(int)date('w', $ts)] . ', ' . (int)date('d', $ts) . ' ' . $_monthAbbr[(int)date('m', $ts)];
}

$page_title = "Prakiraan Cuaca - SULUT NEWS HUB";
$active_menu = 'cuaca';
require_once 'includes/header.php';
?>

<div class="page-header">
    <div class="container-fluid">
        <span class="section-label section-label-light"><i class="fas fa-cloud-sun-rain"></i> DATA BMKG</span>
        <h1>Panel Cuaca Sulawesi Utara</h1>
        <p>Cari tahu cuaca di tempatmu sekarang. Jelajahi data cuaca dari level kota sampai kelurahan dengan sistem navigasi baru yang lebih ringan dan lancar.</p>
    </div>
</div>

<div class="container-fluid content-wrapper">
    <?php if ($enablePrakiraan === '1'): ?>
    <section class="content-section pt-0">
        <div class="widget-card mb-4">
            <div class="widget-card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge bg-primary">ADM1: <?= h($tree['adm1'][$selectedAdm1] ?? 'Sulawesi Utara') ?></span>
                    <?php if ($selectedAdm2 !== ''): ?><span class="badge bg-secondary">ADM2: <?= h($tree['adm2'][$selectedAdm2]) ?></span><?php endif; ?>
                    <?php if ($selectedAdm3 !== ''): ?><span class="badge bg-secondary">ADM3: <?= h($tree['adm3'][$selectedAdm3]) ?></span><?php endif; ?>
                    <?php if ($selectedAdm4 !== ''): ?><span class="badge bg-dark">ADM4: <?= h($tree['adm4'][$selectedAdm4]) ?></span><?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($selectedAdm2 === ''): ?>
            <div class="section-header"><div class="section-header-left"><div class="section-icon"><i class="fas fa-city"></i></div><h2>Prakiraan Cuaca Kabupaten/Kota</h2></div></div>
            <div class="alert alert-light border mb-3" style="font-size: 13px;">
                <i class="fas fa-info-circle me-1 text-primary"></i>
                Ringkasan prakiraan cuaca untuk seluruh wilayah Kabupaten dan Kota di Sulawesi Utara. Data diambil langsung dari titik representatif BMKG untuk memastikan akurasi informasi di setiap wilayah.
            </div>
            
            <?php
            // Determine which list to render — from widget config or full adm2List
            $level1Items = !empty($cuacaLevel1Config) ? $cuacaLevel1Config : [];
            if (empty($level1Items)) {
                foreach ($adm2List as $code => $name) {
                    $level1Items[] = ['adm2' => $code, 'name' => $name];
                }
            }
            ?>
            <?php if (!empty($level1Items)): ?>
            <!-- Progress indicator -->
            <div class="weather-progress-bar mb-3" id="weatherProgress">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="spinner-border spinner-border-sm text-primary" role="status" id="weatherSpinner"></div>
                    <span class="small text-muted" id="weatherProgressText">Memuat data cuaca <span id="weatherProgressCount">0</span>/<?= count($level1Items) ?> kab/kota...</span>
                </div>
                <div class="progress" style="height: 4px; border-radius: 4px; background: #e9ecef;">
                    <div id="weatherProgressFill" class="progress-bar" style="width: 0%; transition: width 0.3s ease;"></div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="weatherTable" class="table table-hover align-middle mb-0" data-type="adm2" data-total="<?= count($level1Items) ?>">
                            <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <tr>
                                    <th class="py-3 ps-4" style="border: none;">Kab. / Kota</th>
                                    <?php for ($h = 0; $h < 3; $h++): ?>
                                        <th class="text-center py-3 weather-date-header" style="border: none; min-width: 180px;"><?= h($defaultDateHeaders[$h]) ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($level1Items as $item):
                                $code = $item['adm2'];
                                $name = $item['name'];
                            ?>
                                <tr data-weather-code="<?= h($code) ?>" data-weather-name="<?= h($name) ?>" style="cursor: pointer; transition: background-color 0.2s;" onclick="window.location.href='?adm2=<?= urlencode($code) ?>'" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor=''">
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px;">
                                                <?= h(substr($name, 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold" style="color: #2d3748;"><?= h($name) ?></div>
                                                <div class="small text-muted"><?= h($code) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center py-3 weather-cell"><div class="weather-skeleton"><div class="skeleton-circle"></div><div class="skeleton-line"></div><div class="skeleton-line skeleton-line-sm"></div></div></td>
                                    <td class="text-center py-3 weather-cell"><div class="weather-skeleton"><div class="skeleton-circle"></div><div class="skeleton-line"></div><div class="skeleton-line skeleton-line-sm"></div></div></td>
                                    <td class="text-center py-3 weather-cell"><div class="weather-skeleton"><div class="skeleton-circle"></div><div class="skeleton-line"></div><div class="skeleton-line skeleton-line-sm"></div></div></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Data cuaca belum tersedia. Silakan konfigurasi wilayah widget cuaca.
                </div>
            <?php endif; ?>
        <?php elseif ($selectedAdm3 === ''): ?>
            <div class="section-header"><div class="section-header-left"><div class="section-icon"><i class="fas fa-map-marked-alt"></i></div><h2>Prakiraan Cuaca Kecamatan di <?= h($tree['adm2'][$selectedAdm2]) ?></h2></div><a class="view-all-link" href="cuaca.php"><i class="fas fa-arrow-left"></i> Kembali</a></div>
            
            <!-- Progress indicator -->
            <div class="weather-progress-bar mb-3" id="weatherProgress">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="spinner-border spinner-border-sm text-success" role="status" id="weatherSpinner"></div>
                    <span class="small text-muted" id="weatherProgressText">Memuat data cuaca <span id="weatherProgressCount">0</span>/<?= count($adm3List) ?> kecamatan...</span>
                </div>
                <div class="progress" style="height: 4px; border-radius: 4px; background: #e9ecef;">
                    <div id="weatherProgressFill" class="progress-bar bg-success" style="width: 0%; transition: width 0.3s ease;"></div>
                </div>
            </div>

            <!-- Skeleton table — data loaded via AJAX -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="weatherTable" class="table table-hover align-middle mb-0" data-type="adm3" data-total="<?= count($adm3List) ?>">
                            <thead style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                                <tr>
                                    <th class="py-3 ps-4" style="border: none;">Kecamatan</th>
                                    <?php for ($h = 0; $h < 3; $h++): ?>
                                        <th class="text-center py-3 weather-date-header" style="border: none; min-width: 180px;"><?= h($defaultDateHeaders[$h]) ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($adm3List as $code => $name): ?>
                                <tr data-weather-code="<?= h($code) ?>" style="cursor: pointer; transition: background-color 0.2s;" onclick="window.location.href='?adm3=<?= urlencode($code) ?>'" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor=''">
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px;">
                                                <?= h(substr($name, 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold" style="color: #2d3748;"><?= h($name) ?></div>
                                                <div class="small text-muted"><?= h($code) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center py-3 weather-cell"><div class="weather-skeleton"><div class="skeleton-circle"></div><div class="skeleton-line"></div><div class="skeleton-line skeleton-line-sm"></div></div></td>
                                    <td class="text-center py-3 weather-cell"><div class="weather-skeleton"><div class="skeleton-circle"></div><div class="skeleton-line"></div><div class="skeleton-line skeleton-line-sm"></div></div></td>
                                    <td class="text-center py-3 weather-cell"><div class="weather-skeleton"><div class="skeleton-circle"></div><div class="skeleton-line"></div><div class="skeleton-line skeleton-line-sm"></div></div></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($selectedAdm4 === ''): ?>
            <div class="section-header"><div class="section-header-left"><div class="section-icon"><i class="fas fa-map-pin"></i></div><h2>Prakiraan Cuaca Kelurahan/Desa di <?= h($tree['adm3'][$selectedAdm3]) ?></h2></div><a class="view-all-link" href="?adm2=<?= urlencode($selectedAdm2) ?>"><i class="fas fa-arrow-left"></i> Kembali</a></div>
            
            <!-- Progress indicator -->
            <div class="weather-progress-bar mb-3" id="weatherProgress">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="spinner-border spinner-border-sm text-warning" role="status" id="weatherSpinner"></div>
                    <span class="small text-muted" id="weatherProgressText">Memuat data cuaca <span id="weatherProgressCount">0</span>/<?= count($adm4List) ?> kelurahan/desa...</span>
                </div>
                <div class="progress" style="height: 4px; border-radius: 4px; background: #e9ecef;">
                    <div id="weatherProgressFill" class="progress-bar bg-warning" style="width: 0%; transition: width 0.3s ease;"></div>
                </div>
            </div>

            <!-- Skeleton table — data loaded via AJAX -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="weatherTable" class="table table-hover align-middle mb-0" data-type="adm4" data-total="<?= count($adm4List) ?>">
                            <thead style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                                <tr>
                                    <th class="py-3 ps-4" style="border: none;">Kelurahan / Desa</th>
                                    <?php for ($h = 0; $h < 3; $h++): ?>
                                        <th class="text-center py-3 weather-date-header" style="border: none; min-width: 180px;"><?= h($defaultDateHeaders[$h]) ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($adm4List as $code => $name): ?>
                                <tr data-weather-code="<?= h($code) ?>" style="cursor: pointer; transition: background-color 0.2s;" onclick="window.location.href='?adm4=<?= urlencode($code) ?>'" onmouseover="this.style.backgroundColor='#fff9f0'" onmouseout="this.style.backgroundColor=''">
                                    <td class="ps-4 py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px;">
                                                <?= h(substr($name, 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold" style="color: #2d3748;"><?= h($name) ?></div>
                                                <div class="small text-muted"><?= h($code) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center py-3 weather-cell"><div class="weather-skeleton"><div class="skeleton-circle"></div><div class="skeleton-line"></div><div class="skeleton-line skeleton-line-sm"></div></div></td>
                                    <td class="text-center py-3 weather-cell"><div class="weather-skeleton"><div class="skeleton-circle"></div><div class="skeleton-line"></div><div class="skeleton-line skeleton-line-sm"></div></div></td>
                                    <td class="text-center py-3 weather-cell"><div class="weather-skeleton"><div class="skeleton-circle"></div><div class="skeleton-line"></div><div class="skeleton-line skeleton-line-sm"></div></div></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="section-header"><div class="section-header-left"><div class="section-icon"><i class="fas fa-cloud"></i></div><h2>Detail Prakiraan 3 Hari</h2></div><a class="view-all-link" href="?adm3=<?= urlencode($selectedAdm3) ?>"><i class="fas fa-arrow-left"></i> Kembali ke Pilih Kelurahan/Desa</a></div>
            <?php if (!$detailForecast): ?>
                <div class="empty-state"><i class="fas fa-exclamation-circle"></i><h4>Data Belum Tersedia</h4><p>Gagal memuat data BMKG untuk wilayah ini.</p></div>
            <?php else: ?>
                <?php $lokasi = $detailForecast['lokasi'] ?? []; $hari = $detailForecast['data'][0]['cuaca'] ?? []; ?>
                <div class="weather-today-card mb-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h3 class="mb-2" style="color:#fff;"><?= h(($lokasi['desa'] ?? '-') . ', ' . ($lokasi['kecamatan'] ?? '-')) ?></h3>
                            <div class="text-white-50"><?= h(($lokasi['kotkab'] ?? '-') . ' - ' . ($lokasi['provinsi'] ?? '-')) ?></div>
                            <div class="text-white-50 small mt-2">ADM4: <?= h($selectedAdm4) ?></div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <?php foreach (array_slice($hari, 0, 3) as $index => $items): ?>
                        <div class="col-lg-4">
                            <div class="widget-card h-100">
                                <div class="widget-card-header"><h4>Hari <?= $index + 1 ?></h4></div>
                                <div class="widget-card-body">
                                    <?php if (!is_array($items) || empty($items)): ?>
                                        <div class="text-muted">Data kosong</div>
                                    <?php else: ?>
                                        <?php $first = $items[0]; ?>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <?php if (!empty($first['image'])): ?><img src="<?= h($first['image']) ?>" alt="Cuaca" style="width:42px;height:42px;"><?php endif; ?>
                                            <div><strong><?= h((string)($first['weather_desc'] ?? '-')) ?></strong><div class="small text-muted"><?= h((string)($first['t'] ?? '-')) ?>°C</div></div>
                                        </div>
                                        <div class="small text-muted">Update per jam tersedia <?= count($items) ?> slot.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($enablePeringatan === '1'): ?>
    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <section class="content-section pt-0">
        <div class="section-header">
            <div class="section-header-left">
                <div class="section-icon section-icon-danger"><i class="fas fa-exclamation-triangle"></i></div>
                <h2>Peringatan Dini Cuaca</h2>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span id="peringatanCount" class="badge bg-danger" style="font-size:.85rem;display:none;"></span>
                <button id="refreshPeringatan" class="view-all-link" onclick="loadPeringatanMap()"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
        </div>

        <!-- Map + Sidebar Layout -->
        <div id="peringatanMapWrapper" class="ewi-map-wrapper">
            <div id="peringatanLoading" class="ewi-loading">
                <div class="spinner-border text-danger" role="status"></div>
                <p class="mt-2 text-muted">Memuat peta peringatan dini cuaca...</p>
            </div>
            <div id="peringatanMapContainer" style="display:none;">
                <div class="ewi-map-layout">
                    <!-- Leaflet Map -->
                    <div class="ewi-map-col">
                        <div id="ewiMap" class="ewi-leaflet-map"></div>
                    </div>
                    <!-- Sidebar Detail Panel -->
                    <div class="ewi-sidebar-col" id="ewiSidebar">
                        <div class="ewi-sidebar-header">
                            <span class="ewi-sidebar-badge">Peringatan Dini Cuaca</span>
                            <button class="ewi-sidebar-close" id="ewiSidebarClose" title="Tutup">&times;</button>
                        </div>
                        <div class="ewi-sidebar-body" id="ewiSidebarBody">
                            <div class="ewi-sidebar-placeholder">
                                <i class="fas fa-map-marker-alt"></i>
                                <p>Klik area berwarna pada peta untuk melihat detail peringatan dini cuaca</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- List below map for mobile / overview -->
                <div class="ewi-list-strip" id="ewiListStrip"></div>
            </div>
            <div id="peringatanError" class="alert alert-danger mt-3" style="display:none;">
                <i class="fas fa-exclamation-circle me-1"></i> <span id="peringatanErrorMsg"></span>
            </div>
        </div>
    </section>

    <!-- Detail Modal (for infographic + full info) -->
    <div class="modal fade" id="peringatanDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-danger">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-1"></i> Detail Peringatan Dini Cuaca</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="peringatanDetailBody">
                    <div class="d-flex align-items-center gap-2 text-muted">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        Memuat detail...
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
/**
 * Progressive Weather Loader — Skeleton UI + AJAX
 * Loads weather data per-row in parallel batches, filling skeleton cells as data arrives.
 */
(function() {
    const table = document.getElementById('weatherTable');
    if (!table) return;

    const type = table.dataset.type; // 'adm2', 'adm3', or 'adm4'
    const total = parseInt(table.dataset.total, 10) || 0;
    const rows = table.querySelectorAll('tr[data-weather-code]');
    if (rows.length === 0 || !type) return;

    const BATCH_SIZE = 5; // Parallel requests per batch
    const items = Array.from(rows).map(function(r) {
        return { code: r.dataset.weatherCode, name: r.dataset.weatherName || '' };
    });
    let loaded = 0;
    let headersUpdated = false;

    // Progress elements
    const progressBar = document.getElementById('weatherProgressFill');
    const progressCount = document.getElementById('weatherProgressCount');
    const progressContainer = document.getElementById('weatherProgress');
    const progressText = document.getElementById('weatherProgressText');
    const spinner = document.getElementById('weatherSpinner');

    function updateProgress() {
        loaded++;
        const pct = Math.round((loaded / total) * 100);
        if (progressBar) progressBar.style.width = pct + '%';
        if (progressCount) progressCount.textContent = loaded;

        if (loaded >= total) {
            // All done — hide progress bar
            if (spinner) spinner.style.display = 'none';
            if (progressText) progressText.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Semua data cuaca berhasil dimuat.';
            setTimeout(function() {
                if (progressContainer) progressContainer.classList.add('completed');
            }, 2000);
        }
    }

    function updateHeaders(days) {
        if (headersUpdated) return;
        const headers = table.querySelectorAll('.weather-date-header');
        days.slice(0, 3).forEach(function(day, i) {
            if (headers[i] && day.date_full && day.date_full !== '-') {
                headers[i].textContent = day.date_full;
            }
        });
        headersUpdated = true;
    }

    function renderWeatherCell(day) {
        var icon = day.icon || '🌤️';
        var weather = escapeHtml(day.weather || '-');
        var tempMin = escapeHtml(String(day.temp_min || '-'));
        var tempMax = escapeHtml(String(day.temp_max || '-'));

        var html = '<div class="d-flex flex-column align-items-center gap-1 weather-loaded">'
            + '<span style="font-size:24px;">' + icon + '</span>'
            + '<div class="small" style="color:#4a5568;">' + weather + '</div>'
            + '<div class="fw-bold" style="color:#2d3748;">' + tempMin + '° - ' + tempMax + '°C</div>';

        // Show humidity for ADM2 level
        if (type === 'adm2') {
            var huMin = day.humidity_min || '-';
            var huMax = day.humidity_max || huMin;
            if (huMin !== '-' && huMax !== '-' && huMin !== huMax) {
                html += '<div class="small text-muted">RH: ' + escapeHtml(String(huMin)) + '-' + escapeHtml(String(huMax)) + '%</div>';
            } else if (huMin !== '-') {
                html += '<div class="small text-muted">RH: ' + escapeHtml(String(huMin)) + '%</div>';
            }
        }

        html += '</div>';
        return html;
    }

    function renderUnavailable() {
        return '<div class="weather-unavailable weather-loaded"><i class="fas fa-cloud-slash me-1"></i> Tidak tersedia</div>';
    }

    async function fetchWeather(item) {
        try {
            var controller = new AbortController();
            var timeoutId = setTimeout(function() { controller.abort(); }, 15000);

            var url = 'api/cuaca-ajax.php?type=' + encodeURIComponent(type) + '&code=' + encodeURIComponent(item.code);
            if (type === 'adm2' && item.name) {
                url += '&name=' + encodeURIComponent(item.name);
            }
            var response = await fetch(url, {
                signal: controller.signal
            });
            clearTimeout(timeoutId);
            return await response.json();
        } catch (err) {
            return { success: false, code: item.code, error: err.message || 'Timeout' };
        }
    }

    async function loadAll() {
        for (var i = 0; i < items.length; i += BATCH_SIZE) {
            var batch = items.slice(i, i + BATCH_SIZE);
            var results = await Promise.all(batch.map(function(item) { return fetchWeather(item); }));

            results.forEach(function(result) {
                var row = table.querySelector('tr[data-weather-code="' + result.code + '"]');
                if (!row) return;

                var cells = row.querySelectorAll('.weather-cell');
                if (result.success && result.days && result.days.length > 0) {
                    // Update header dates from first successful result
                    updateHeaders(result.days);

                    // Fill weather cells
                    result.days.slice(0, 3).forEach(function(day, idx) {
                        if (cells[idx]) {
                            cells[idx].innerHTML = renderWeatherCell(day);
                        }
                    });
                } else {
                    // Show unavailable
                    cells.forEach(function(cell) {
                        cell.innerHTML = renderUnavailable();
                    });
                }
                updateProgress();
            });
        }
    }

    // Start loading on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadAll);
    } else {
        loadAll();
    }
})();
</script>

<script>
function safeHtml(value) {
    if (typeof escapeHtml === 'function') return escapeHtml(value || '');
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * EWI (Early Warning Indonesia) — Interactive Leaflet Map
 */
(function() {
    let ewiMap = null;
    let ewiPolygonLayers = [];
    let ewiData = [];
    let activeLayerIndex = -1;

    // Severity → color mapping (matched to BMKG reference)
    const severityColors = {
        'Extreme':  { fill: '#dc2626', stroke: '#991b1b', opacity: 0.50 },
        'Severe':   { fill: '#ea580c', stroke: '#c2410c', opacity: 0.48 },
        'Moderate': { fill: '#f59e0b', stroke: '#d97706', opacity: 0.45 },
        'Minor':    { fill: '#facc15', stroke: '#ca8a04', opacity: 0.43 },
        'Unknown':  { fill: '#9ca3af', stroke: '#6b7280', opacity: 0.40 },
    };

    function getSeverityColor(severity) {
        return severityColors[severity] || severityColors['Unknown'];
    }

    function formatISODate(isoStr) {
        if (!isoStr) return '-';
        try {
            const d = new Date(isoStr);
            const dd = String(d.getDate()).padStart(2, '0');
            const mm = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'][d.getMonth()];
            const yyyy = d.getFullYear();
            const hh = String(d.getHours()).padStart(2, '0');
            const mi = String(d.getMinutes()).padStart(2, '0');
            // Detect timezone offset
            const offset = d.getTimezoneOffset();
            let tz = 'WIB';
            if (offset === -480) tz = 'WITA';
            else if (offset === -540) tz = 'WIT';
            return dd + ' ' + mm + ' ' + yyyy + ', ' + hh + '.' + mi + ' ' + tz;
        } catch (e) {
            return isoStr;
        }
    }

    function extractProvince(title) {
        // "Hujan Lebat disertai Petir di Gorontalo" → "Gorontalo"
        const match = title.match(/\bdi\s+(.+)/i);
        return match ? match[1].trim() : title;
    }

    function showSidebar(index) {
        const item = ewiData[index];
        if (!item) return;

        activeLayerIndex = index;
        const sidebar = document.getElementById('ewiSidebar');
        const body = document.getElementById('ewiSidebarBody');
        if (!sidebar || !body) return;

        sidebar.classList.add('active');

        const province = extractProvince(item.headline || item.title);
        const color = getSeverityColor(item.severity);

        body.innerHTML = ''
            + '<div class="ewi-sidebar-province">' + safeHtml(province) + '</div>'
            + '<div class="ewi-detail-rows">'
            +   '<div class="ewi-detail-row">'
            +     '<span class="ewi-detail-icon ewi-icon-start"><i class="fas fa-clock"></i></span>'
            +     '<span class="ewi-detail-label">Mulai:</span>'
            +     '<span class="ewi-detail-value"><strong>' + formatISODate(item.effective) + '</strong></span>'
            +   '</div>'
            +   '<div class="ewi-detail-row">'
            +     '<span class="ewi-detail-icon ewi-icon-end"><i class="fas fa-clock"></i></span>'
            +     '<span class="ewi-detail-label">Berakhir:</span>'
            +     '<span class="ewi-detail-value"><strong>' + formatISODate(item.expires) + '</strong></span>'
            +   '</div>'
            +   '<div class="ewi-detail-row">'
            +     '<span class="ewi-detail-icon ewi-icon-event"><i class="fas fa-cloud-showers-heavy"></i></span>'
            +     '<span class="ewi-detail-value">' + safeHtml(item.event || '-') + '</span>'
            +   '</div>'
            + '</div>'
            + '<button class="ewi-detail-btn" onclick="openEwiDetailModal(' + index + ')">'
            +   '<i class="fas fa-search-plus me-1"></i> Lihat Detail Wilayah'
            + '</button>';

        // Highlight polygon
        highlightPolygon(index);

        // Fly to center
        if (item.center && ewiMap) {
            ewiMap.flyTo(item.center, 7, { duration: 0.8 });
        }

        // Highlight list strip item
        document.querySelectorAll('.ewi-strip-item').forEach((el, i) => {
            el.classList.toggle('active', i === index);
        });
    }

    function highlightPolygon(index) {
        ewiPolygonLayers.forEach((group, i) => {
            if (!group) return;
            const isActive = (i === index);
            const item = ewiData[i];
            const itemColor = item ? getSeverityColor(item.severity) : null;
            group.eachLayer(layer => {
                // Only style polygons, not markers
                if (layer instanceof L.Polygon) {
                    const baseOpacity = itemColor ? itemColor.opacity : 0.45;
                    layer.setStyle({
                        weight: isActive ? 3 : 1.5,
                        fillOpacity: isActive ? Math.min(baseOpacity + 0.2, 0.75) : baseOpacity,
                    });
                }
            });
            if (isActive) group.bringToFront();
        });
    }

    function closeSidebar() {
        const sidebar = document.getElementById('ewiSidebar');
        if (sidebar) sidebar.classList.remove('active');
        activeLayerIndex = -1;
        // Reset all polygon styles
        ewiPolygonLayers.forEach((group, i) => {
            if (!group) return;
            const item = ewiData[i];
            const itemColor = item ? getSeverityColor(item.severity) : null;
            const baseOpacity = itemColor ? itemColor.opacity : 0.45;
            group.eachLayer(layer => {
                if (layer instanceof L.Polygon) {
                    layer.setStyle({ weight: 1.5, fillOpacity: baseOpacity });
                }
            });
        });
        document.querySelectorAll('.ewi-strip-item.active').forEach(el => el.classList.remove('active'));
    }

    window.openEwiDetailModal = function(index) {
        const item = ewiData[index];
        if (!item) return;

        const modalEl = document.getElementById('peringatanDetailModal');
        const bodyEl = document.getElementById('peringatanDetailBody');
        if (!modalEl || !bodyEl) return;

        bodyEl.innerHTML = '<div class="d-flex align-items-center gap-2 text-muted"><div class="spinner-border spinner-border-sm" role="status"></div> Memuat detail...</div>';
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        const province = extractProvince(item.headline || item.title);
        const color = getSeverityColor(item.severity);

        let detailHtml = ''
            + '<div class="ewi-modal-grid">'
            +   '<div class="ewi-modal-main">'
            +     '<div class="ewi-modal-alert-box">'
            +       '<h5 class="mb-2" style="color:#dc2626;font-weight:700;"><i class="fas fa-exclamation-triangle me-1"></i> ' + safeHtml(item.headline || item.title) + '</h5>'
            +       '<p style="color:#374151;line-height:1.7;font-size:0.92rem;">' + safeHtml(item.description || '-') + '</p>'
            +     '</div>'
            +     '<div class="ewi-modal-meta">'
            +       '<div class="ewi-meta-item"><span class="ewi-meta-label">Event</span><span class="ewi-meta-value">' + safeHtml(item.event || '-') + '</span></div>'
            +       '<div class="ewi-meta-item"><span class="ewi-meta-label">Mulai</span><span class="ewi-meta-value">' + formatISODate(item.effective) + '</span></div>'
            +       '<div class="ewi-meta-item"><span class="ewi-meta-label">Berakhir</span><span class="ewi-meta-value">' + formatISODate(item.expires) + '</span></div>'
            +       '<div class="ewi-meta-item"><span class="ewi-meta-label">Severity</span><span class="ewi-meta-value"><span class="badge" style="background:' + color.fill + ';">' + safeHtml(item.severity || '-') + '</span></span></div>'
            +       '<div class="ewi-meta-item"><span class="ewi-meta-label">Urgency</span><span class="ewi-meta-value"><span class="badge bg-warning text-dark">' + safeHtml(item.urgency || '-') + '</span></span></div>'
            +       '<div class="ewi-meta-item"><span class="ewi-meta-label">Certainty</span><span class="ewi-meta-value">' + safeHtml(item.certainty || '-') + '</span></div>'
            +       '<div class="ewi-meta-item"><span class="ewi-meta-label">Wilayah</span><span class="ewi-meta-value"><strong>' + safeHtml(item.areaDesc || province) + '</strong></span></div>'
            +       '<div class="ewi-meta-item"><span class="ewi-meta-label">Diterbitkan</span><span class="ewi-meta-value">' + safeHtml(item.senderName || 'BMKG') + '</span></div>'
            +     '</div>';

        // Instruction
        if (item.instruction) {
            detailHtml += '<div class="alert alert-warning mt-3 mb-0"><i class="fas fa-shield-alt me-1"></i> <strong>Instruksi:</strong> ' + safeHtml(item.instruction) + '</div>';
        }

        detailHtml += '</div>'; // close ewi-modal-main

        // Right column: Leaflet detail map + infographic
        detailHtml += '<div class="ewi-modal-side">';

        // Mini Leaflet map
        detailHtml += '<div class="ewi-modal-minimap-wrapper"><div id="ewiDetailMap" class="ewi-modal-minimap"></div></div>';

        // Infographic image
        if (item.web) {
            detailHtml += '<div class="ewi-modal-infographic">'
                + '<h6 class="mb-2"><i class="fas fa-image me-1"></i> Infografik BMKG</h6>'
                + '<div class="ewi-infographic-scroll">'
                + '<img src="' + safeHtml(item.web) + '" alt="Infografik Peringatan Dini ' + safeHtml(province) + '" class="ewi-infographic-img" onerror="this.parentElement.innerHTML=\'<div class=text-muted>Infografik tidak tersedia</div>\'">'
                + '</div>'
                + '</div>';
        }

        detailHtml += '</div></div>'; // close ewi-modal-side + ewi-modal-grid

        bodyEl.innerHTML = detailHtml;

        // Initialize mini leaflet map after DOM is ready
        setTimeout(() => {
            const mapDiv = document.getElementById('ewiDetailMap');
            if (!mapDiv) return;

            const detailMap = L.map('ewiDetailMap', { zoomControl: true, attributionControl: false });
            L.tileLayer('https://{s}.tile-cyclosm.openstreetmap.fr/cyclosm/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://github.com/cyclosm/cyclosm-cartocss-style/releases">CyclOSM</a>',
                maxZoom: 18
            }).addTo(detailMap);

            if (item.polygons && item.polygons.length > 0) {
                const polyGroup = L.featureGroup();
                item.polygons.forEach(coords => {
                    L.polygon(coords, {
                        color: color.stroke,
                        fillColor: color.fill,
                        fillOpacity: 0.45,
                        weight: 2
                    }).addTo(polyGroup);
                });
                polyGroup.addTo(detailMap);
                detailMap.fitBounds(polyGroup.getBounds(), { padding: [20, 20] });
            } else if (item.center) {
                detailMap.setView(item.center, 8);
            } else {
                detailMap.setView([-2.5, 118], 5);
            }
        }, 300);
    };

    window.loadPeringatanMap = async function() {
        const loading = document.getElementById('peringatanLoading');
        const container = document.getElementById('peringatanMapContainer');
        const error = document.getElementById('peringatanError');
        const countBadge = document.getElementById('peringatanCount');

        if (!loading || !container || !error) return;

        loading.style.display = 'flex';
        container.style.display = 'none';
        error.style.display = 'none';

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000);

            const response = await fetch('api/bmkg-peringatan-map.php', { signal: controller.signal });
            clearTimeout(timeoutId);

            const result = await response.json();
            loading.style.display = 'none';

            if (!result.success) {
                error.style.display = 'block';
                document.getElementById('peringatanErrorMsg').textContent = result.error || 'Gagal memuat data';
                console.error('EWI API Error:', result.error);
                return;
            }

            ewiData = result.data.items || [];
            console.log('EWI Data loaded:', ewiData.length, 'warnings');

            if (ewiData.length === 0) {
                container.style.display = 'block';
                container.innerHTML = '<div class="text-center py-5"><i class="fas fa-check-circle text-success" style="font-size:3rem;"></i><p class="mt-3 text-muted">Tidak ada peringatan dini cuaca aktif saat ini.</p></div>';
                return;
            }

            // Show count badge
            if (countBadge) {
                countBadge.textContent = ewiData.length + ' Peringatan Aktif';
                countBadge.style.display = 'inline-block';
            }

            container.style.display = 'block';

            // Initialize Leaflet map
            if (ewiMap) {
                ewiMap.remove();
                ewiMap = null;
            }
            ewiPolygonLayers = [];

            ewiMap = L.map('ewiMap', {
                center: [-2.5, 118],
                zoom: 5,
                minZoom: 4,
                maxZoom: 14,
                zoomControl: true,
                attributionControl: true
            });

            L.tileLayer('https://{s}.tile-cyclosm.openstreetmap.fr/cyclosm/{z}/{x}/{y}.png', {
                attribution: '<a href="https://leafletjs.com">Leaflet</a> | &copy; <a href="https://github.com/cyclosm/cyclosm-cartocss-style/releases">CyclOSM</a> | &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 18
            }).addTo(ewiMap);

            // Add Indonesia Province Boundaries
            fetch('https://raw.githubusercontent.com/superpikar/indonesia-geojson/master/indonesia-provinces-simple.json')
                .then(r => r.json())
                .then(geojson => {
                    L.geoJSON(geojson, {
                        style: {
                            color: '#059669',
                            weight: 2.5,
                            fillOpacity: 0,
                            opacity: 0.8
                        },
                        interactive: false
                    }).addTo(ewiMap);
                })
                .catch(() => console.log('Province boundaries not loaded'));

            // Add polygons for each warning
            const allBounds = L.featureGroup();

            ewiData.forEach((item, index) => {
                const color = getSeverityColor(item.severity);
                const group = L.featureGroup();

                // Add polygons if available
                if (item.polygons && item.polygons.length > 0) {
                    item.polygons.forEach(coords => {
                        const polygon = L.polygon(coords, {
                            color: color.stroke,
                            fillColor: color.fill,
                            fillOpacity: color.opacity,
                            weight: 1.5,
                            smoothFactor: 1.5,
                        });
                        polygon.on('click', () => showSidebar(index));
                        polygon.on('mouseover', function() {
                            if (activeLayerIndex !== index) {
                                this.setStyle({ fillOpacity: Math.min(color.opacity + 0.15, 0.75), weight: 2.5 });
                            }
                        });
                        polygon.on('mouseout', function() {
                            if (activeLayerIndex !== index) {
                                this.setStyle({ fillOpacity: color.opacity, weight: 1.5 });
                            }
                        });
                        group.addLayer(polygon);
                    });

                    // Add group to map only if has polygons
                    group.addTo(ewiMap);
                    allBounds.addLayer(group);
                    ewiPolygonLayers.push(group);
                } else {
                    ewiPolygonLayers.push(null);
                }
            });

            // Fit map to show all polygons
            if (allBounds.getLayers().length > 0) {
                ewiMap.fitBounds(allBounds.getBounds(), { padding: [30, 30], maxZoom: 6 });
            }

            // Build list strip below map
            const strip = document.getElementById('ewiListStrip');
            if (strip) {
                let stripHtml = '';
                ewiData.forEach((item, i) => {
                    const province = extractProvince(item.headline || item.title);
                    const color = getSeverityColor(item.severity);
                    stripHtml += '<div class="ewi-strip-item" data-index="' + i + '" onclick="ewiStripClick(' + i + ')">'
                        + '<div class="ewi-strip-dot" style="background:' + color.fill + ';"></div>'
                        + '<div class="ewi-strip-text">'
                        +   '<div class="ewi-strip-title">' + safeHtml(province) + '</div>'
                        +   '<div class="ewi-strip-sub">' + safeHtml(item.event || '-') + '</div>'
                        + '</div>'
                        + '</div>';
                });
                strip.innerHTML = stripHtml;
            }

            // Close sidebar handler
            const closeBtn = document.getElementById('ewiSidebarClose');
            if (closeBtn) {
                closeBtn.addEventListener('click', closeSidebar);
            }

        } catch (err) {
            loading.style.display = 'none';
            error.style.display = 'block';
            const errorMsg = err.name === 'AbortError'
                ? 'Timeout: BMKG server tidak merespon dalam 30 detik.'
                : (err.message || 'Kesalahan jaringan');
            document.getElementById('peringatanErrorMsg').textContent = errorMsg;
            console.error('EWI Map Error:', err);
        }
    };

    window.ewiStripClick = function(index) {
        showSidebar(index);
    };

    // Auto-load on DOM ready
    document.addEventListener('DOMContentLoaded', loadPeringatanMap);
})();
</script>

<?php require_once 'includes/footer.php'; ?>
