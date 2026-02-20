<?php
/**
 * BMKG Peringatan Dini — Batch Polygon Data API
 * 
 * GET /api/bmkg-peringatan-map.php
 * Response: JSON { success, data: { items, total } }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');

require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

/**
 * Fetch URL via cURL
 */
function curlGet(string $url, int $timeout = 20): string|false {
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

/**
 * Parse a single CAP XML string → extract polygons, headline, event, etc.
 */
function parseCAPXml(string $xml_string): ?array {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (!$dom->loadXML($xml_string, LIBXML_NOCDATA)) {
        libxml_clear_errors();
        return null;
    }
    $xpath = new DOMXPath($dom);
    $rootExpr = '/*[local-name()="alert"]';
    $infoNodes = $xpath->query($rootExpr . '/*[local-name()="info"]');
    if (!$infoNodes || $infoNodes->length === 0) return null;
    $infoNode = $infoNodes->item(0);

    // Extract key info fields
    $get = function(string $name) use ($xpath, $infoNode): string {
        return trim((string)$xpath->evaluate('string(./*[local-name()="' . $name . '"])', $infoNode));
    };

    // Parse polygon data
    $polygons = [];
    $areaDescParts = [];
    $areaNodes = $xpath->query('./*[local-name()="area"]', $infoNode);
    if ($areaNodes) {
        foreach ($areaNodes as $areaNode) {
            $descNode = $xpath->query('./*[local-name()="areaDesc"]', $areaNode);
            if ($descNode && $descNode->length > 0) {
                $d = trim($descNode->item(0)->textContent);
                if ($d !== '') $areaDescParts[] = $d;
            }
            $polyNodes = $xpath->query('./*[local-name()="polygon"]', $areaNode);
            if (!$polyNodes) continue;
            foreach ($polyNodes as $pn) {
                $str = trim($pn->textContent);
                if ($str === '') continue;
                $coords = [];
                foreach (preg_split('/\s+/', $str) as $pair) {
                    $ll = explode(',', $pair);
                    if (count($ll) === 2 && is_numeric($ll[0]) && is_numeric($ll[1])) {
                        $coords[] = [round((float)$ll[0], 4), round((float)$ll[1], 4)];
                    }
                }
                if (!empty($coords)) $polygons[] = $coords;
            }
        }
    }

    // Compute polygon center for map positioning
    $center = null;
    if (!empty($polygons)) {
        $latSum = 0; $lngSum = 0; $cnt = 0;
        foreach ($polygons as $poly) {
            foreach ($poly as $pt) {
                $latSum += $pt[0]; $lngSum += $pt[1]; $cnt++;
            }
        }
        if ($cnt > 0) {
            $center = [round($latSum / $cnt, 4), round($lngSum / $cnt, 4)];
        }
    }

    $effective = $get('effective');
    $expires   = $get('expires');

    return [
        'headline'    => $get('headline'),
        'event'       => $get('event'),
        'urgency'     => $get('urgency'),
        'severity'    => $get('severity'),
        'certainty'   => $get('certainty'),
        'effective'   => $effective,
        'expires'     => $expires,
        'senderName'  => $get('senderName') ?: 'BMKG',
        'description' => $get('description'),
        'instruction' => $get('instruction'),
        'web'         => $get('web'),
        'areaDesc'    => implode('; ', array_unique($areaDescParts)),
        'polygons'    => $polygons,
        'center'      => $center,
    ];
}

try {
    $enabled = getSetting($conn, 'bmkg_enable_peringatan', '1');
    if ($enabled !== '1') {
        echo json_encode(['success' => false, 'error' => 'Fitur peringatan dini dinonaktifkan'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Cache key
    $cache_key = 'bmkg_peringatan_map_v2';
    $cache_duration = (int)getSetting($conn, 'bmkg_peringatan_cache', '900');

    $cached = getCache($conn, $cache_key);
    if ($cached !== null) {
        echo json_encode(['success' => true, 'data' => $cached, 'cached' => true, 'timestamp' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 1. Fetch RSS feed
    $rss_content = curlGet('https://www.bmkg.go.id/alerts/nowcast/id/rss.xml');
    if ($rss_content === false) {
        echo json_encode(['success' => false, 'error' => 'Gagal mengambil RSS feed BMKG'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($rss_content);
    if ($xml === false) {
        libxml_clear_errors();
        echo json_encode(['success' => false, 'error' => 'Gagal memuat XML RSS'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. Collect all CAP URLs from RSS items
    $rssItems = [];
    if (isset($xml->channel->item)) {
        foreach ($xml->channel->item as $item) {
            $link = (string)$item->link;
            $kode = '';
            if (preg_match('/\/([A-Z0-9]+)_alert\.xml/i', $link, $m)) {
                $kode = strtoupper($m[1]);
            }
            $rssItems[] = [
                'title'   => (string)$item->title,
                'link'    => $link,
                'kode'    => $kode,
                'pubDate' => (string)($item->pubDate ?? ''),
            ];
        }
    }

    // 3. Batch fetch CAP details using curl_multi for parallel requests
    $mh = curl_multi_init();
    $handles = [];

    foreach ($rssItems as $i => $ri) {
        if (empty($ri['link'])) continue;
        $ch = curl_init($ri['link']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }

    // Execute all requests in parallel
    $running = 0;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.5);
    } while ($running > 0);

    // 4. Parse results
    $items = [];
    foreach ($handles as $i => $ch) {
        $body = curl_multi_getcontent($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $rss = $rssItems[$i];
        $capData = null;
        if ($body && $httpCode >= 200 && $httpCode < 400) {
            $capData = parseCAPXml($body);
        }

        if ($capData) {
            $items[] = array_merge($rss, $capData);
        } else {
            // Fallback: include RSS-only data without polygons
            $items[] = array_merge($rss, [
                'headline' => $rss['title'],
                'event' => '', 'urgency' => '', 'severity' => '', 'certainty' => '',
                'effective' => '', 'expires' => '', 'senderName' => 'BMKG',
                'description' => '', 'instruction' => '', 'web' => '',
                'areaDesc' => '', 'polygons' => [], 'center' => null,
            ]);
        }
    }
    curl_multi_close($mh);

    $result = [
        'items' => $items,
        'total' => count($items),
        'lastBuildDate' => isset($xml->channel->lastBuildDate) ? (string)$xml->channel->lastBuildDate : '',
    ];

    // Save to cache (graceful — skip if data too large for MySQL)
    try {
        @setCache($conn, $cache_key, $result, $cache_duration);
    } catch (\Throwable $e) {
        // Cache save failed (likely max_allowed_packet), continue without caching
    }

    echo json_encode(['success' => true, 'data' => $result, 'cached' => false, 'timestamp' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
