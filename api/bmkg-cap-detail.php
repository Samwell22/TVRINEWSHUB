<?php
/**
 * BMKG CAP Detail API — Peringatan dini cuaca (Common Alerting Protocol)
 * 
 * GET /api/bmkg-cap-detail.php?kode={kode_cap}
 * Response: JSON (detail lengkap + polygon wilayah terdampak)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/config.php';

// Ambil koneksi database
$conn = getDBConnection();

// getCache() and setCache() are defined in config/config.php

/**
 * Parse CAP XML Detail
 */
/**
 * Helper: fetch URL via cURL with SSL bypass for BMKG
 */
function bmkgCurlFetch(string $url, int $timeout = 20): string|false {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($body !== false && $code >= 200 && $code < 400) ? $body : false;
}

function fetchBMKGCAPDetail($kode_cap) {
    $cap_url = "https://www.bmkg.go.id/alerts/nowcast/id/{$kode_cap}_alert.xml";
    
    $xml_string = bmkgCurlFetch($cap_url);
    
    if ($xml_string === false) {
        return [
            'success' => false,
            'error' => 'Gagal mengambil data CAP dari BMKG'
        ];
    }
    
    // Parse XML with DOM + XPath (namespace tolerant)
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (!$dom->loadXML($xml_string, LIBXML_NOCDATA)) {
        libxml_clear_errors();
        return [
            'success' => false,
            'error' => 'Gagal memuat XML CAP'
        ];
    }

    $xpath = new DOMXPath($dom);

    $rootExpr = '/*[local-name()="alert"]';
    $infoNodes = $xpath->query($rootExpr . '/*[local-name()="info"]');
    if (!$infoNodes || $infoNodes->length === 0) {
        return [
            'success' => false,
            'error' => 'Info block tidak ditemukan dalam CAP'
        ];
    }
    $infoNode = $infoNodes->item(0);

    // Parse polygon data (all areas)
    $polygons_data = [];
    $area_desc_parts = [];
    $areaNodes = $xpath->query('./*[local-name()="area"]', $infoNode);
    if ($areaNodes) {
        foreach ($areaNodes as $areaNode) {
            $areaDescNode = $xpath->query('./*[local-name()="areaDesc"]', $areaNode);
            if ($areaDescNode && $areaDescNode->length > 0) {
                $desc = trim((string)$areaDescNode->item(0)->textContent);
                if ($desc !== '') {
                    $area_desc_parts[] = $desc;
                }
            }

            $polygonNodes = $xpath->query('./*[local-name()="polygon"]', $areaNode);
            if (!$polygonNodes) {
                continue;
            }

            foreach ($polygonNodes as $polygonNode) {
                $polygonString = trim((string)$polygonNode->textContent);
                if ($polygonString === '') {
                    continue;
                }

                $polygon_coords = [];
                $coord_pairs = preg_split('/\s+/', $polygonString);
                if (!is_array($coord_pairs)) {
                    continue;
                }

                foreach ($coord_pairs as $pair) {
                    $lat_lon = array_map('trim', explode(',', $pair));
                    if (count($lat_lon) !== 2) {
                        continue;
                    }
                    if (!is_numeric($lat_lon[0]) || !is_numeric($lat_lon[1])) {
                        continue;
                    }

                    $polygon_coords[] = [
                        'lat' => (float)$lat_lon[0],
                        'lng' => (float)$lat_lon[1]
                    ];
                }

                if (!empty($polygon_coords)) {
                    $polygons_data[] = $polygon_coords;
                }
            }
        }
    }
    $area_desc = implode('; ', array_values(array_unique($area_desc_parts)));
    
    // Extract all info data
    $cap_data = [
        'identifier' => trim((string)$xpath->evaluate('string(' . $rootExpr . '/*[local-name()="identifier"])')),
        'sender' => trim((string)$xpath->evaluate('string(' . $rootExpr . '/*[local-name()="sender"])')),
        'sent' => trim((string)$xpath->evaluate('string(' . $rootExpr . '/*[local-name()="sent"])')),
        'status' => trim((string)$xpath->evaluate('string(' . $rootExpr . '/*[local-name()="status"])')),
        'msgType' => trim((string)$xpath->evaluate('string(' . $rootExpr . '/*[local-name()="msgType"])')),
        'scope' => trim((string)$xpath->evaluate('string(' . $rootExpr . '/*[local-name()="scope"])')),
        'info' => [
            'language' => trim((string)$xpath->evaluate('string(./*[local-name()="language"])', $infoNode)) ?: 'id-ID',
            'category' => trim((string)$xpath->evaluate('string(./*[local-name()="category"])', $infoNode)),
            'event' => trim((string)$xpath->evaluate('string(./*[local-name()="event"])', $infoNode)),
            'urgency' => trim((string)$xpath->evaluate('string(./*[local-name()="urgency"])', $infoNode)),
            'severity' => trim((string)$xpath->evaluate('string(./*[local-name()="severity"])', $infoNode)),
            'certainty' => trim((string)$xpath->evaluate('string(./*[local-name()="certainty"])', $infoNode)),
            'effective' => trim((string)$xpath->evaluate('string(./*[local-name()="effective"])', $infoNode)),
            'expires' => trim((string)$xpath->evaluate('string(./*[local-name()="expires"])', $infoNode)),
            'senderName' => trim((string)$xpath->evaluate('string(./*[local-name()="senderName"])', $infoNode)) ?: 'BMKG',
            'headline' => trim((string)$xpath->evaluate('string(./*[local-name()="headline"])', $infoNode)),
            'description' => trim((string)$xpath->evaluate('string(./*[local-name()="description"])', $infoNode)),
            'instruction' => trim((string)$xpath->evaluate('string(./*[local-name()="instruction"])', $infoNode)),
            'web' => trim((string)$xpath->evaluate('string(./*[local-name()="web"])', $infoNode)),
            'parameter' => []
        ],
        'area' => [
            'areaDesc' => $area_desc,
            'polygons' => $polygons_data
        ]
    ];
    
    // Parse parameters if any
    $parameterNodes = $xpath->query('./*[local-name()="parameter"]', $infoNode);
    if ($parameterNodes) {
        foreach ($parameterNodes as $paramNode) {
            $valueName = trim((string)$xpath->evaluate('string(./*[local-name()="valueName"])', $paramNode));
            $value = trim((string)$xpath->evaluate('string(./*[local-name()="value"])', $paramNode));
            if ($valueName !== '') {
                $cap_data['info']['parameter'][$valueName] = $value;
            }
        }
    }
    
    return [
        'success' => true,
        'data' => $cap_data
    ];
}

// MAIN EXECUTION

try {
    // Get kode CAP from parameter
    $kode_cap = isset($_GET['kode']) ? trim($_GET['kode']) : '';
    
    if (empty($kode_cap)) {
        echo json_encode([
            'success' => false,
            'error' => 'Parameter kode CAP tidak boleh kosong',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Validate kode format (huruf kapital dan angka)
    if (!preg_match('/^[A-Za-z0-9]+$/', $kode_cap)) {
        echo json_encode([
            'success' => false,
            'error' => 'Format kode CAP tidak valid',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $kode_cap = strtoupper($kode_cap);
    
    // Check if peringatan dini is enabled
    $enabled = getSetting($conn, 'bmkg_enable_peringatan', '1');
    if ($enabled !== '1') {
        echo json_encode([
            'success' => false,
            'error' => 'Fitur peringatan dini cuaca sedang dinonaktifkan',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Cache configuration
    $cache_key = "bmkg_cap_{$kode_cap}";
    $cache_duration = (int)getSetting($conn, 'bmkg_peringatan_cache', '900'); // Default 15 menit
    
    // Check cache first
    $cached_data = getCache($conn, $cache_key);
    
    if ($cached_data !== null) {
        // Return from cache
        echo json_encode([
            'success' => true,
            'data' => $cached_data,
            'cached' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Fetch fresh data
    $result = fetchBMKGCAPDetail($kode_cap);
    
    if (!$result['success']) {
        echo json_encode([
            'success' => false,
            'error' => $result['error'],
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Save to cache
    setCache($conn, $cache_key, $result['data'], $cache_duration);
    
    // Return fresh data
    echo json_encode([
        'success' => true,
        'data' => $result['data'],
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
