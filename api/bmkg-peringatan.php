<?php
/**
 * BMKG Peringatan Dini Cuaca API — Nowcast RSS Feed
 * 
 * GET /api/bmkg-peringatan.php
 * Response: JSON (cache: 5 min)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300'); // 5 min browser cache

require_once __DIR__ . '/../config/config.php';

// Ambil koneksi database
$conn = getDBConnection();

// getCache() and setCache() are defined in config/config.php

/**
 * Parse RSS Feed BMKG Peringatan Dini
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

function fetchBMKGPeringatan() {
    $rss_url = "https://www.bmkg.go.id/alerts/nowcast/id/rss.xml";
    
    $rss_content = bmkgCurlFetch($rss_url);
    
    if ($rss_content === false) {
        return [
            'success' => false,
            'error' => 'Gagal mengambil data dari BMKG API'
        ];
    }
    
    // Load XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($rss_content);
    
    if ($xml === false) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        return [
            'success' => false,
            'error' => 'Gagal memuat XML: ' . ($errors[0]->message ?? 'Unknown error')
        ];
    }
    
    // Parse channel info
    $channel = [
        'title' => isset($xml->channel->title) ? (string)$xml->channel->title : 'Peringatan Dini Cuaca BMKG',
        'description' => isset($xml->channel->description) ? (string)$xml->channel->description : '',
        'lastBuildDate' => isset($xml->channel->lastBuildDate) ? (string)$xml->channel->lastBuildDate : '',
        'pubDate' => isset($xml->channel->pubDate) ? (string)$xml->channel->pubDate : ''
    ];
    
    // Parse items
    $items = [];
    if (isset($xml->channel->item)) {
        foreach ($xml->channel->item as $item) {
            // Extract kode detail CAP from link
            $link = (string)$item->link;
            $kode_cap = '';
            if (preg_match('/\/([A-Z0-9]+)_alert\.xml(?:\?.*)?$/i', $link, $matches)) {
                $kode_cap = strtoupper($matches[1]);
            }
            
            $items[] = [
                'title' => isset($item->title) ? (string)$item->title : 'Tanpa Judul',
                'link' => $link,
                'kode_cap' => $kode_cap,
                'description' => isset($item->description) ? (string)$item->description : '',
                'author' => isset($item->author) ? (string)$item->author : 'BMKG',
                'pubDate' => isset($item->pubDate) ? (string)$item->pubDate : '',
                'pubDateTimestamp' => isset($item->pubDate) ? strtotime((string)$item->pubDate) : 0
            ];
        }
    }
    
    return [
        'success' => true,
        'channel' => $channel,
        'items' => $items,
        'total' => count($items)
    ];
}

// MAIN EXECUTION

try {
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
    $cache_key = 'bmkg_peringatan_dini';
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
    $result = fetchBMKGPeringatan();
    
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
