<?php
/**
 * News API Handler — Multi-Provider (NewsData.io + NewsAPI.org)
 * 
 * GET /api/newsapi-fetch.php?category={indonesia|international}&page={n}
 * Response: JSON (cache: 10 min)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=600'); // 10 min browser cache

require_once __DIR__ . '/../config/config.php';

// Ambil koneksi database
$conn = getDBConnection();

// getCache() and setCache() are defined in config/config.php

/**
 * Fetch berita dari NewsData.io (untuk Indonesia)
 */
function fetchNewsDataIO($api_key, $page, $pageSize, $language = 'id') {
    $base_url = 'https://newsdata.io/api/1/news';
    $params = [
        'apikey' => $api_key,
        'language' => $language
    ];
    
    // NewsData.io uses token-based pagination, not page numbers
    // For simplicity, we only fetch the latest articles (no pagination support yet)
    // If page > 1, return empty results
    if ($page > 1) {
        return [
            'success' => true,
            'totalResults' => 0,
            'articles' => [],
            'articlesCount' => 0,
            'nextPage' => null
        ];
    }
    
    $url = $base_url . '?' . http_build_query($params);
    
    // Get API response
    $context = stream_context_create([
        'http' => [
            'timeout' => 20,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $response_body = @file_get_contents($url, false, $context);
    
    if ($response_body === false) {
        return [
            'success' => false,
            'error' => 'Gagal mengambil data dari NewsData.io'
        ];
    }
    
    // Decode JSON
    $data = json_decode($response_body, true);
    
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Data bukan format JSON yang valid: ' . json_last_error_msg()
        ];
    }
    
    // Check API response status
    if (isset($data['status']) && $data['status'] === 'error') {
        return [
            'success' => false,
            'error' => $data['results']['message'] ?? 'Terjadi error dari NewsData.io'
        ];
    }
    
    // Parse articles (NewsData.io uses "results" instead of "articles")
    $articles = [];
    if (isset($data['results']) && is_array($data['results'])) {
        foreach ($data['results'] as $article) {
            // Skip if no title
            if (empty($article['title'])) {
                continue;
            }
            
            // Convert NewsData.io format to NewsAPI format for compatibility
            $articles[] = [
                'source' => [
                    'id' => $article['source_id'] ?? null,
                    'name' => $article['source_id'] ?? 'Unknown'
                ],
                'author' => $article['creator'][0] ?? 'Unknown', // NewsData.io uses array for creators
                'title' => $article['title'] ?? '',
                'description' => $article['description'] ?? '',
                'url' => $article['link'] ?? '#',
                'urlToImage' => $article['image_url'] ?? '',
                'publishedAt' => $article['pubDate'] ?? '',
                'publishedAtFormatted' => isset($article['pubDate']) ? 
                    date('d M Y, H:i', strtotime($article['pubDate'])) : '',
                'content' => $article['content'] ?? ($article['description'] ?? '')
            ];
        }
    }
    
    return [
        'success' => true,
        'totalResults' => $data['totalResults'] ?? 0,
        'articles' => $articles,
        'articlesCount' => count($articles),
        'nextPage' => $data['nextPage'] ?? null
    ];
}

/**
 * Fetch berita dari NewsAPI.org (untuk International)
 */
function fetchNewsAPI($api_key, $category, $page, $pageSize, $language, $country_id, $country_international) {
    $base_url = 'https://newsapi.org/v2/';
    $params = [
        'apiKey' => $api_key,
        'page' => $page,
        'pageSize' => $pageSize
    ];
    
    // Build URL based on category
    switch ($category) {
        case 'indonesia':
            $endpoint = 'top-headlines';
            $params['category'] = 'general'; // General top-headlines (global)
            // Note: country=id tidak ada artikel di NewsAPI free tier
            break;
            
        case 'international':
            $endpoint = 'top-headlines';
            $params['country'] = $country_international; // us
            $params['language'] = 'en';
            break;
            
        case 'breaking':
            $endpoint = 'top-headlines';
            $params['language'] = $language; // id or en
            // Breaking news bisa campuran Indonesia & International
            break;
            
        default:
            return [
                'success' => false,
                'error' => 'Kategori tidak valid'
            ];
    }
    
    $url = $base_url . $endpoint . '?' . http_build_query($params);
    
    // Get API response
    $context = stream_context_create([
        'http' => [
            'timeout' => 20,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $response_body = @file_get_contents($url, false, $context);
    
    if ($response_body === false) {
        return [
            'success' => false,
            'error' => 'Gagal mengambil data dari NewsAPI'
        ];
    }
    
    // Decode JSON
    $data = json_decode($response_body, true);
    
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Data bukan format JSON yang valid: ' . json_last_error_msg()
        ];
    }
    
    // Check API response status
    if (isset($data['status']) && $data['status'] === 'error') {
        return [
            'success' => false,
            'error' => $data['message'] ?? 'Terjadi error dari NewsAPI'
        ];
    }
    
    // Parse articles
    $articles = [];
    if (isset($data['articles']) && is_array($data['articles'])) {
        foreach ($data['articles'] as $article) {
            // Skip if no title
            if (empty($article['title']) || $article['title'] === '[Removed]') {
                continue;
            }
            
            $articles[] = [
                'source' => [
                    'id' => $article['source']['id'] ?? null,
                    'name' => $article['source']['name'] ?? 'Unknown'
                ],
                'author' => $article['author'] ?? 'Unknown',
                'title' => $article['title'] ?? '',
                'description' => $article['description'] ?? '',
                'url' => $article['url'] ?? '#',
                'urlToImage' => $article['urlToImage'] ?? '',
                'publishedAt' => $article['publishedAt'] ?? '',
                'publishedAtFormatted' => isset($article['publishedAt']) ? 
                    date('d M Y, H:i', strtotime($article['publishedAt'])) : '',
                'content' => $article['content'] ?? ''
            ];
        }
    }
    
    return [
        'success' => true,
        'totalResults' => $data['totalResults'] ?? 0,
        'articles' => $articles,
        'articlesCount' => count($articles)
    ];
}

// MAIN EXECUTION

try {
    // Get parameters
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    
    if (empty($category)) {
        echo json_encode([
            'success' => false,
            'error' => 'Parameter category wajib diisi (indonesia|international|breaking)',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Validate category
    $valid_categories = ['indonesia', 'international'];
    if (!in_array(strtolower($category), $valid_categories)) {
        echo json_encode([
            'success' => false,
            'error' => 'Kategori tidak valid. Pilihan: indonesia, international',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get API settings based on category
    if ($category === 'indonesia') {
        // Use NewsData.io for Indonesian news
        $api_key = getSetting($conn, 'newsdataio_api_key', '');
        if (empty($api_key)) {
            echo json_encode([
                'success' => false,
                'error' => 'API Key NewsData.io belum dikonfigurasi',
                'data' => null
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $api_provider = 'newsdata';
    } else {
        // Use NewsAPI.org for international news
        $api_key = getSetting($conn, 'newsapi_key', '');
        if (empty($api_key)) {
            echo json_encode([
                'success' => false,
                'error' => 'API Key NewsAPI belum dikonfigurasi',
                'data' => null
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $api_provider = 'newsapi';
    }
    
    $pageSize = (int)getSetting($conn, 'newsapi_pagesize', '20');
    $language = getSetting($conn, 'newsapi_language', 'id');
    $country_id = getSetting($conn, 'newsapi_country_id', 'id');
    $country_international = getSetting($conn, 'newsapi_country_international', 'us');
    
    // Cache configuration
    $cache_key = "{$api_provider}_{$category}_page{$page}";
    $cache_duration = (int)getSetting($conn, 'newsapi_cache_duration', '1800'); // Default 30 menit
    
    // Check cache first (only for page 1 to keep fresh data)
    if ($page === 1) {
        $cached_data = getCache($conn, $cache_key);
        
        if ($cached_data !== null) {
            // Return from cache
            echo json_encode([
                'success' => true,
                'category' => $category,
                'page' => $page,
                'provider' => $api_provider,
                'data' => $cached_data,
                'cached' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // Fetch fresh data based on provider
    if ($api_provider === 'newsdata') {
        // Use NewsData.io for Indonesian news
        $result = fetchNewsDataIO($api_key, $page, $pageSize, 'id');
    } else {
        // Use NewsAPI.org for international news
        $result = fetchNewsAPI($api_key, $category, $page, $pageSize, $language, $country_id, $country_international);
    }
    
    if (!$result['success']) {
        echo json_encode([
            'success' => false,
            'error' => $result['error'],
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Save to cache (only page 1)
    if ($page === 1) {
        setCache($conn, $cache_key, $result, $cache_duration);
    }
    
    // Return fresh data
    echo json_encode([
        'success' => true,
        'category' => $category,
        'page' => $page,
        'provider' => $api_provider,
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
