<?php
/**
 * Admin Page - API & Widget Settings
 * Konfigurasi BMKG API dan Widget Display
 */

require_once 'auth.php';
requireLogin();
require_once '../config/config.php';

$conn = getDBConnection();
$logged_in_user = getLoggedInUser();

if ($logged_in_user['role'] !== 'admin') {
    setFlashMessage('error', 'Anda tidak memiliki akses ke halaman ini! Hanya Admin yang boleh mengelola API & Widget.');
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Pengaturan API & Widget';
$success_msg = '';
$error_msg = '';

// getSetting() and updateSetting() are defined in config/config.php

function loadAdm2Sulut(): array {
    $csvFile = __DIR__ . '/../data/wilayah/sulawesi_utara_full.csv';
    $adm2 = [];

    if (!file_exists($csvFile) || !is_readable($csvFile)) {
        return $adm2;
    }

    if (($fp = fopen($csvFile, 'r')) === false) {
        return $adm2;
    }

    $isHeader = true;
    while (($line = fgetcsv($fp)) !== false) {
        if ($isHeader) {
            $isHeader = false;
            continue;
        }

        $code = trim((string)($line[0] ?? ''));
        $name = trim((string)($line[1] ?? ''));

        if (preg_match('/^\d{2}\.\d{2}$/', $code)) {
            $adm2[$code] = $name;
        }
    }
    fclose($fp);
    ksort($adm2);

    return $adm2;
}

$adm2Options = loadAdm2Sulut();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    requireCSRF();
    
    try {
        $conn->begin_transaction();

        updateSetting($conn, 'bmkg_peringatan_cache', $_POST['bmkg_peringatan_cache'] ?? '900');
        updateSetting($conn, 'bmkg_prakiraan_cache', $_POST['bmkg_prakiraan_cache'] ?? '3600');
        updateSetting($conn, 'bmkg_enable_peringatan', isset($_POST['bmkg_enable_peringatan']) ? '1' : '0');
        updateSetting($conn, 'bmkg_enable_prakiraan', isset($_POST['bmkg_enable_prakiraan']) ? '1' : '0');

        updateSetting($conn, 'widget_cuaca_homepage', isset($_POST['widget_cuaca_homepage']) ? '1' : '0');
        updateSetting($conn, 'widget_berita_nasional', isset($_POST['widget_berita_nasional']) ? '1' : '0');
        updateSetting($conn, 'widget_berita_internasional', isset($_POST['widget_berita_internasional']) ? '1' : '0');
        updateSetting($conn, 'widget_breaking_news', isset($_POST['widget_breaking_news']) ? '1' : '0');
        updateSetting($conn, 'berita_nasional_per_widget', $_POST['berita_nasional_per_widget'] ?? '5');

        $weatherLocations = [];

        foreach ($adm2Options as $adm2Code => $name) {
            $orderRaw = $_POST['widget_order'][$adm2Code] ?? 999;
            $order = (int)$orderRaw;
            if ($order < 1) {
                $order = 999;
            }

            $weatherLocations[] = [
                'adm2' => $adm2Code,
                'name' => $name,
                'enabled' => isset($_POST['widget_enabled'][$adm2Code]) ? 1 : 0,
                'order' => $order,
            ];
        }

        if (empty($weatherLocations)) {
            $fallback = array_slice(array_keys($adm2Options), 0, 4);
            $rank = 1;
            foreach ($fallback as $adm2Code) {
                $weatherLocations[] = [
                    'adm2' => $adm2Code,
                    'name' => $adm2Options[$adm2Code],
                    'enabled' => 1,
                    'order' => $rank++,
                ];
            }
        }

        usort($weatherLocations, function($a, $b) {
            return (int)$a['order'] <=> (int)$b['order'];
        });

        $defaultAdm2 = trim((string)($_POST['weather_widget_default_adm2'] ?? ''));
        if (!isset($adm2Options[$defaultAdm2])) {
            $defaultAdm2 = $weatherLocations[0]['adm2'] ?? '';
        }

        updateSetting($conn, 'weather_widget_default_adm2', $defaultAdm2);
        updateSetting($conn, 'weather_widget_locations', json_encode($weatherLocations, JSON_UNESCAPED_UNICODE));

        $conn->commit();
        $success_msg = 'Pengaturan API & Widget berhasil disimpan!';
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}

$rawWeatherLocations = getSetting($conn, 'weather_widget_locations', '');
$weatherLocations = json_decode($rawWeatherLocations, true);
if (!is_array($weatherLocations) || empty($weatherLocations)) {
    $weatherLocations = [];
    $rank = 1;
    foreach ($adm2Options as $code => $name) {
        $weatherLocations[] = ['adm2' => $code, 'name' => $name, 'enabled' => 1, 'order' => $rank++];
    }
}

$weatherLocationsMap = [];
foreach ($weatherLocations as $wl) {
    $key = (string)($wl['adm2'] ?? '');
    if ($key !== '') {
        $weatherLocationsMap[$key] = $wl;
    }
}

$settings = [
    'bmkg_peringatan_cache' => getSetting($conn, 'bmkg_peringatan_cache', '900'),
    'bmkg_prakiraan_cache' => getSetting($conn, 'bmkg_prakiraan_cache', '3600'),
    'bmkg_enable_peringatan' => getSetting($conn, 'bmkg_enable_peringatan', '1'),
    'bmkg_enable_prakiraan' => getSetting($conn, 'bmkg_enable_prakiraan', '1'),
    'weather_widget_default_adm2' => getSetting($conn, 'weather_widget_default_adm2', $weatherLocations[0]['adm2'] ?? ''),
    'widget_cuaca_homepage' => getSetting($conn, 'widget_cuaca_homepage', '1'),
    'widget_berita_nasional' => getSetting($conn, 'widget_berita_nasional', '1'),
    'widget_berita_internasional' => getSetting($conn, 'widget_berita_internasional', '1'),
    'widget_breaking_news' => getSetting($conn, 'widget_breaking_news', '1'),
    'berita_nasional_per_widget' => getSetting($conn, 'berita_nasional_per_widget', '5'),
];

$page_heading = 'Pengaturan API & Widget';
$breadcrumbs = [
    'Dashboard' => 'dashboard.php',
    'Pengaturan API & Widget' => null,
];

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-cog me-2"></i><?php echo $page_title; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="settings.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Pengaturan Utama</a>
    </div>
</div>

<?php if ($success_msg): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error_msg): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" action="" class="needs-validation" novalidate>
    <?php echo csrfField(); ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-cloud-sun-rain me-2"></i>Pengaturan BMKG (Operasional)</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle me-2"></i>
                Input manual kode ADM wilayah sudah dipensiunkan dari panel ini. Pengaturan wilayah cuaca homepage sekarang menggunakan daftar kabupaten/kota di bawah.
            </div>

            <h6 class="mb-3">Cache Duration (detik)</h6>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Cache Peringatan Dini</label>
                <div class="col-sm-9">
                    <input type="number" name="bmkg_peringatan_cache" class="form-control" value="<?= htmlspecialchars($settings['bmkg_peringatan_cache']) ?>" min="60" step="60" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Cache Prakiraan Cuaca</label>
                <div class="col-sm-9">
                    <input type="number" name="bmkg_prakiraan_cache" class="form-control" value="<?= htmlspecialchars($settings['bmkg_prakiraan_cache']) ?>" min="60" step="60" required>
                </div>
            </div>

            <h6 class="mb-3">Fitur Aktif</h6>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="bmkg_enable_peringatan" id="bmkg_enable_peringatan" <?= $settings['bmkg_enable_peringatan'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="bmkg_enable_peringatan">Aktifkan Peringatan Dini Cuaca</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="bmkg_enable_prakiraan" id="bmkg_enable_prakiraan" <?= $settings['bmkg_enable_prakiraan'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="bmkg_enable_prakiraan">Aktifkan Prakiraan Cuaca</label>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-list-ol me-2"></i>Widget Prakiraan Cuaca Landing Page</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Default Kabupaten/Kota</label>
                <div class="col-sm-9">
                    <select name="weather_widget_default_adm2" class="form-select" required>
                        <?php foreach ($adm2Options as $code => $name): ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $settings['weather_widget_default_adm2'] === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?> (<?= htmlspecialchars($code) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th style="width:45%">Kabupaten/Kota</th>
                            <th style="width:20%">Urutan</th>
                            <th style="width:20%">Tampilkan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($adm2Options as $code => $name): 
                        $item = $weatherLocationsMap[$code] ?? ['adm2' => $code, 'name' => $name, 'enabled' => 1, 'order' => 999];
                    ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($name) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($code) ?></small>
                            </td>
                            <td>
                                <input type="number" name="widget_order[<?= htmlspecialchars($code) ?>]" class="form-control" value="<?= (int)($item['order'] ?? 999) ?>" min="1" max="99" required>
                            </td>
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="widget_enabled[<?= htmlspecialchars($code) ?>]" id="widget_enabled_<?= htmlspecialchars(str_replace('.', '_', $code)) ?>" <?= (int)($item['enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="widget_enabled_<?= htmlspecialchars(str_replace('.', '_', $code)) ?>">Aktif</label>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <small class="text-muted">Gunakan urutan kecil untuk tampil lebih atas. Nonaktifkan baris untuk menyembunyikan dari widget landing.</small>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-th-large me-2"></i>Pengaturan Widget</h5>
        </div>
        <div class="card-body">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="widget_cuaca_homepage" id="widget_cuaca_homepage" <?= $settings['widget_cuaca_homepage'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="widget_cuaca_homepage">Tampilkan Widget Cuaca di Homepage</label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="widget_berita_nasional" id="widget_berita_nasional" <?= $settings['widget_berita_nasional'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="widget_berita_nasional">Tampilkan Widget Berita Nasional</label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="widget_berita_internasional" id="widget_berita_internasional" <?= $settings['widget_berita_internasional'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="widget_berita_internasional">Tampilkan Widget Berita Internasional</label>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="widget_breaking_news" id="widget_breaking_news" <?= $settings['widget_breaking_news'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="widget_breaking_news">Tampilkan Widget Breaking News</label>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Berita per Widget</label>
                <div class="col-sm-9"><input type="number" name="berita_nasional_per_widget" class="form-control" value="<?= htmlspecialchars($settings['berita_nasional_per_widget']) ?>" min="3" max="10" required></div>
            </div>
        </div>
    </div>

    <div class="d-grid gap-2 mb-5">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Simpan Pengaturan</button>
    </div>
</form>

<div class="card mb-4">
    <div class="card-header bg-warning">
        <h5 class="mb-0"><i class="fas fa-database me-2"></i>Manajemen Cache</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">Hapus cache jika data API tidak terupdate atau terjadi masalah.</p>
        <button type="button" class="btn btn-warning" onclick="clearAPICache()"><i class="fas fa-trash-alt me-2"></i>Hapus Semua Cache API</button>
        <div id="cacheResult" class="mt-3"></div>
    </div>
</div>

<script>
(function() {
    'use strict';
    const form = document.querySelector('.needs-validation');
    if (!form) return;

    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
})();

async function clearAPICache() {
    if (!confirm('Yakin ingin menghapus semua cache API?')) return;

    const resultDiv = document.getElementById('cacheResult');
    resultDiv.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"></div> Menghapus cache...';

    try {
        const response = await fetch('ajax-clear-cache.php', { method: 'POST' });
        const rawText = await response.text();
        let result;

        try {
            result = JSON.parse(rawText);
        } catch (parseErr) {
            resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Respons server tidak valid JSON. Cek session login admin atau error PHP endpoint.</div>`;
            return;
        }

        if (result.success) {
            resultDiv.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>${result.message}</div>`;
        } else {
            resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>${result.message}</div>`;
        }

        setTimeout(() => resultDiv.innerHTML = '', 5000);
    } catch (err) {
        resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Terjadi kesalahan: ${err.message}</div>`;
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
