<?php
/**
 * ANTARA News Sulawesi Utara — RSS Feed Parser
 * 
 * GET /api/antara-rss.php?feed={terkini|top-news|sulut-update}&limit={1-20}
 * Response: JSON
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=600');

require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

// Allowed feeds
$allowedFeeds = [
    'terkini'      => 'https://manado.antaranews.com/rss/terkini.xml',
    'top-news'     => 'https://manado.antaranews.com/rss/top-news.xml',
    'sulut-update' => 'https://manado.antaranews.com/rss/sulut-update.xml',
];

// Validate input
$feed = isset($_GET['feed']) ? trim($_GET['feed']) : '';
$limit = isset($_GET['limit']) ? min(max((int)$_GET['limit'], 1), 20) : 10;

if (empty($feed) || !isset($allowedFeeds[$feed])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Parameter feed wajib diisi. Pilihan: ' . implode(', ', array_keys($allowedFeeds))
    ]);
    exit;
}

$feedUrl = $allowedFeeds[$feed];

// Check cache first (15 minutes)
$cacheKey = 'antara_rss_' . $feed;
$cacheDuration = 900; // 15 minutes

$cached = getCache($conn, $cacheKey, $cacheDuration);
if ($cached !== null) {
    $cachedData = json_decode($cached, true);
    if ($cachedData) {
        // Apply limit
        if (isset($cachedData['data']['articles'])) {
            $cachedData['data']['articles'] = array_slice($cachedData['data']['articles'], 0, $limit);
            $cachedData['data']['articlesCount'] = count($cachedData['data']['articles']);
        }
        $cachedData['cached'] = true;
        echo json_encode($cachedData);
        exit;
    }
}

// Fetch RSS feed via cURL
function fetchRssFeed($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'SulutNewsHub/1.0 (+https://tvrisulut.co.id)',
        CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/xml, text/xml'],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || $httpCode !== 200) {
        return ['success' => false, 'error' => $error ?: "HTTP $httpCode"];
    }
    
    return ['success' => true, 'body' => $response];
}

/**
 * Parse RSS XML into normalized article array
 */
function parseRssXml($xmlString) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
    
    if ($xml === false) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        return ['success' => false, 'error' => 'XML parse error'];
    }
    
    $articles = [];
    $channel = $xml->channel ?? $xml;
    $channelTitle = (string)($channel->title ?? 'ANTARA News Sulut');
    
    foreach ($channel->item as $item) {
        // Namespaces
        $media = $item->children('media', true);
        $dc = $item->children('dc', true);
        
        // Extract image
        $imageUrl = '';
        
        // Try media:content
        if (isset($media->content)) {
            $imageUrl = (string)$media->content->attributes()->url;
        }
        // Try media:thumbnail
        if (empty($imageUrl) && isset($media->thumbnail)) {
            $imageUrl = (string)$media->thumbnail->attributes()->url;
        }
        // Try enclosure
        if (empty($imageUrl) && isset($item->enclosure)) {
            $encType = (string)$item->enclosure->attributes()->type;
            if (strpos($encType, 'image') !== false) {
                $imageUrl = (string)$item->enclosure->attributes()->url;
            }
        }
        // Fallback: extract from description HTML
        if (empty($imageUrl)) {
            $desc = (string)($item->description ?? '');
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $desc, $m)) {
                $imageUrl = $m[1];
            }
        }
        
        // Extract category
        $category = '';
        if (isset($item->category)) {
            $category = (string)$item->category;
        } elseif (isset($dc->subject)) {
            $category = (string)$dc->subject;
        }
        
        // Parse publish date
        $pubDate = (string)($item->pubDate ?? '');
        $publishedAt = !empty($pubDate) ? date('c', strtotime($pubDate)) : null;
        
        // Clean description (strip HTML, trim)
        $rawDesc = (string)($item->description ?? '');
        $cleanDesc = trim(strip_tags($rawDesc));
        // Limit to ~200 chars
        if (mb_strlen($cleanDesc) > 200) {
            $cleanDesc = mb_substr($cleanDesc, 0, 200) . '...';
        }
        
        // Extract location from title if possible
        $location = 'Sulawesi Utara';
        $title = (string)($item->title ?? '');
        
        $articles[] = [
            'title'              => $title,
            'description'        => $cleanDesc,
            'url'                => (string)($item->link ?? ''),
            'urlToImage'         => $imageUrl,
            'publishedAt'        => $publishedAt,
            'publishedAtFormatted' => !empty($pubDate) ? formatTanggalIndonesia(date('Y-m-d H:i:s', strtotime($pubDate))) : '-',
            'timeAgo'            => !empty($pubDate) ? timeAgo(date('Y-m-d H:i:s', strtotime($pubDate))) : '-',
            'source'             => 'ANTARA News Sulut',
            'category'           => $category,
            'location'           => $location,
            'author'             => (string)($dc->creator ?? 'ANTARA'),
        ];
    }
    
    return [
        'success'  => true,
        'channel'  => $channelTitle,
        'articles' => $articles,
    ];
}

// Fetch the feed
$fetchResult = fetchRssFeed($feedUrl);

if (!$fetchResult['success']) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error'   => 'Gagal mengambil RSS feed: ' . ($fetchResult['error'] ?? 'Unknown error'),
        'feed'    => $feed,
    ]);
    exit;
}

// Parse the RSS XML
$parseResult = parseRssXml($fetchResult['body']);

if (!$parseResult['success']) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Gagal parsing RSS feed: ' . ($parseResult['error'] ?? 'Unknown error'),
        'feed'    => $feed,
    ]);
    exit;
}

// Build response
$response = [
    'success'   => true,
    'feed'      => $feed,
    'provider'  => 'ANTARA News Sulawesi Utara',
    'channel'   => $parseResult['channel'],
    'data'      => [
        'articles'      => $parseResult['articles'],
        'articlesCount' => count($parseResult['articles']),
    ],
    'cached'    => false,
    'timestamp' => date('c'),
];

// Cache the full result (before limiting)
setCache($conn, $cacheKey, json_encode($response), $cacheDuration);

// Apply limit for this request
$response['data']['articles'] = array_slice($response['data']['articles'], 0, $limit);
$response['data']['articlesCount'] = count($response['data']['articles']);

echo json_encode($response);
