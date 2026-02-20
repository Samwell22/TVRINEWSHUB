<?php
/**
 * ANTARA RSS Feed Collector — Batch fetch, deduplicate, and store articles
 * 
 * GET|POST /api/antara-rss-collector.php
 * Auth: Admin session (AJAX from dashboard)
 * Response: JSON collection statistics
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

// FEED REGISTRY — Smart Subset (27 feeds)
$FEED_REGISTRY = [
    // --- General ---
    'terkini' => [
        'url'      => 'https://manado.antaranews.com/rss/terkini.xml',
        'region'   => null,
        'category' => null,
        'is_top'   => false,
        'group'    => 'general',
    ],
    'top-news' => [
        'url'      => 'https://manado.antaranews.com/rss/top-news.xml',
        'region'   => null,
        'category' => null,
        'is_top'   => true,
        'group'    => 'general',
    ],
    'sulut-update' => [
        'url'      => 'https://manado.antaranews.com/rss/sulut-update.xml',
        'region'   => 'Sulawesi Utara',
        'category' => null,
        'is_top'   => false,
        'group'    => 'general',
    ],

    // --- Regional (17 feeds) ---
    'provinsi-sulut' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-provinsi-sulut.xml',
        'region' => 'Provinsi Sulut', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kota-manado' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kota-manado.xml',
        'region' => 'Kota Manado', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kota-tomohon' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kota-tomohon.xml',
        'region' => 'Kota Tomohon', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kota-bitung' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kota-bitung.xml',
        'region' => 'Kota Bitung', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kab-sitaro' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kabupaten-sitaro.xml',
        'region' => 'Kab. Sitaro', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kab-minahasa' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kabupaten-minahasa.xml',
        'region' => 'Kab. Minahasa', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'minahasa-tenggara' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-minahasa-tenggara.xml',
        'region' => 'Minahasa Tenggara', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'minahasa-utara' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-minahasa-utara.xml',
        'region' => 'Minahasa Utara', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kab-minahasa-tenggara' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kabupaten-minahasa-tenggara.xml',
        'region' => 'Kab. Mitra', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kab-sangihe' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kabupaten-sangihe.xml',
        'region' => 'Kab. Sangihe', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kab-bolmong' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kabupaten-bolmong.xml',
        'region' => 'Kab. Bolmong', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kab-minahasa-selatan' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kabupaten-minahasa-selatan.xml',
        'region' => 'Kab. Minsel', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kab-minahasa-utara' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kabupaten-minahasa-utara.xml',
        'region' => 'Kab. Minut', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kab-talaud' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kabupaten-talaud.xml',
        'region' => 'Kab. Talaud', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kota-kotamobagu' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kota-kotamobagu.xml',
        'region' => 'Kota Kotamobagu', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kab-bolmong-utara' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kabupaten-bolmong-utara.xml',
        'region' => 'Kab. Bolmong Utara', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kab-bolmong-selatan' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kabupaten-bolmong-selatan.xml',
        'region' => 'Kab. Bolmong Selatan', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],
    'kab-bolmong-timur' => [
        'url'    => 'https://manado.antaranews.com/rss/sulut-update-kabupaten-bolmong-timur.xml',
        'region' => 'Kab. Bolmong Timur', 'category' => null, 'is_top' => false, 'group' => 'regional',
    ],

    // --- Category (8 feeds) ---
    'wisata' => [
        'url'    => 'https://manado.antaranews.com/rss/wisata.xml',
        'region' => null, 'category' => 'Wisata', 'is_top' => false, 'group' => 'category',
    ],
    'ekonomi-bisnis' => [
        'url'    => 'https://manado.antaranews.com/rss/ekonomi-bisnis.xml',
        'region' => null, 'category' => 'Ekonomi & Bisnis', 'is_top' => false, 'group' => 'category',
    ],
    'kesra' => [
        'url'    => 'https://manado.antaranews.com/rss/kesra.xml',
        'region' => null, 'category' => 'Kesejahteraan Rakyat', 'is_top' => false, 'group' => 'category',
    ],
    'olahraga' => [
        'url'    => 'https://manado.antaranews.com/rss/olahraga.xml',
        'region' => null, 'category' => 'Olahraga', 'is_top' => false, 'group' => 'category',
    ],
    'politik-hukum' => [
        'url'    => 'https://manado.antaranews.com/rss/politik-dan-hukum.xml',
        'region' => null, 'category' => 'Politik & Hukum', 'is_top' => false, 'group' => 'category',
    ],
    'pendidikan' => [
        'url'    => 'https://manado.antaranews.com/rss/pendidikan.xml',
        'region' => null, 'category' => 'Pendidikan', 'is_top' => false, 'group' => 'category',
    ],
    'hiburan' => [
        'url'    => 'https://manado.antaranews.com/rss/hiburan.xml',
        'region' => null, 'category' => 'Hiburan', 'is_top' => false, 'group' => 'category',
    ],
    'teknologi' => [
        'url'    => 'https://manado.antaranews.com/rss/teknologi.xml',
        'region' => null, 'category' => 'Teknologi', 'is_top' => false, 'group' => 'category',
    ],
];

// MAIN COLLECTOR LOGIC (only when called directly)
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {

$startTime = microtime(true);
$startedAt = date('Y-m-d H:i:s');

$stats = [
    'feeds_fetched'    => 0,
    'feeds_failed'     => 0,
    'articles_new'     => 0,
    'articles_updated' => 0,
    'articles_total'   => 0,
    'errors'           => [],
];

// Ensure tables exist
ensureTablesExist($conn);

// Batch fetch all RSS feeds using curl_multi
$feedResults = batchFetchFeeds($FEED_REGISTRY);

// Prepare INSERT statement 
$insertSql = "INSERT INTO rss_articles (article_hash, title, description, url, image_url, source_feed, category, region, author, published_at, fetched_at, is_top_news)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        region = COALESCE(VALUES(region), rss_articles.region),
        category = COALESCE(VALUES(category), rss_articles.category),
        is_top_news = GREATEST(rss_articles.is_top_news, VALUES(is_top_news)),
        fetched_at = VALUES(fetched_at)";

$insertStmt = $conn->prepare($insertSql);
if (!$insertStmt) {
    echo json_encode(['success' => false, 'error' => 'DB prepare failed: ' . $conn->error]);
    exit;
}

$now = date('Y-m-d H:i:s');

foreach ($feedResults as $feedKey => $result) {
    $feedMeta = $FEED_REGISTRY[$feedKey];

    if (!$result['success']) {
        $stats['feeds_failed']++;
        $stats['errors'][] = "{$feedKey}: " . ($result['error'] ?? 'fetch failed');
        continue;
    }

    $stats['feeds_fetched']++;

    // Parse RSS XML
    $articles = parseRssArticles($result['body'], $feedKey, $feedMeta);

    foreach ($articles as $article) {
        $hash = hash('sha256', $article['url']);
        $isTop = $feedMeta['is_top'] ? 1 : 0;

        $insertStmt->bind_param(
            'sssssssssssi',
            $hash,
            $article['title'],
            $article['description'],
            $article['url'],
            $article['image_url'],
            $feedKey,
            $article['category'],
            $article['region'],
            $article['author'],
            $article['published_at'],
            $now,
            $isTop
        );

        try {
            $insertStmt->execute();
            $stats['articles_total']++;

            if ($insertStmt->affected_rows === 1) {
                $stats['articles_new']++;
            } elseif ($insertStmt->affected_rows === 2) {
                $stats['articles_updated']++;
            }
        } catch (\Throwable $e) {
            // Skip individual article errors
        }
    }
}

$insertStmt->close();

// Log the fetch run
$durationMs = (int)((microtime(true) - $startTime) * 1000);
$completedAt = date('Y-m-d H:i:s');
$errorDetails = !empty($stats['errors']) ? json_encode($stats['errors'], JSON_UNESCAPED_UNICODE) : null;

$logStmt = $conn->prepare(
    "INSERT INTO rss_fetch_log (started_at, completed_at, feeds_fetched, feeds_failed, articles_new, articles_updated, articles_total, duration_ms, error_details)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
if (!$logStmt) {
    // Log table might not exist yet, skip logging
    error_log('rss_fetch_log prepare failed: ' . $conn->error);
} else {
$logStmt->bind_param(
    'ssiiiiiss',
    $startedAt, $completedAt,
    $stats['feeds_fetched'], $stats['feeds_failed'],
    $stats['articles_new'], $stats['articles_updated'], $stats['articles_total'],
    $durationMs, $errorDetails
);
$logStmt->execute();
$logStmt->close();
} // end logStmt

// Update last fetch timestamp in settings
try {
    updateSetting($conn, 'rss_last_fetch', $completedAt);
} catch (\Throwable $e) {}

echo json_encode([
    'success'    => true,
    'stats'      => $stats,
    'duration'   => $durationMs . 'ms',
    'timestamp'  => $completedAt,
    'feedsTotal' => count($FEED_REGISTRY),
], JSON_UNESCAPED_UNICODE);

} // end main logic guard

// HELPER FUNCTIONS

/**
 * Batch fetch all feeds using curl_multi for parallel requests
 */
function batchFetchFeeds(array $registry): array {
    $mh = curl_multi_init();
    $handles = [];

    foreach ($registry as $key => $meta) {
        $ch = curl_init($meta['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'SulutNewsHub/2.0 (+https://tvrisulut.co.id)',
            CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, application/xml, text/xml'],
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }

    // Execute all requests in parallel
    $running = 0;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.5);
    } while ($running > 0);

    $results = [];
    foreach ($handles as $key => $ch) {
        $body     = curl_multi_getcontent($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        if ($body && $httpCode >= 200 && $httpCode < 400) {
            $results[$key] = ['success' => true, 'body' => $body];
        } else {
            $results[$key] = ['success' => false, 'error' => $error ?: "HTTP {$httpCode}"];
        }
    }
    curl_multi_close($mh);

    return $results;
}

/**
 * Parse RSS XML into article arrays with feed metadata
 */
function parseRssArticles(string $xmlString, string $feedKey, array $feedMeta): array {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($xml === false) {
        libxml_clear_errors();
        return [];
    }

    $channel = $xml->channel ?? $xml;
    $articles = [];

    if (!isset($channel->item)) return [];

    foreach ($channel->item as $item) {
        $media = $item->children('media', true);
        $dc    = $item->children('dc', true);

        // Image extraction (same logic as antara-rss.php)
        $imageUrl = '';
        if (isset($media->content)) {
            $imageUrl = (string)$media->content->attributes()->url;
        }
        if (empty($imageUrl) && isset($media->thumbnail)) {
            $imageUrl = (string)$media->thumbnail->attributes()->url;
        }
        if (empty($imageUrl) && isset($item->enclosure)) {
            $encType = (string)$item->enclosure->attributes()->type;
            if (strpos($encType, 'image') !== false) {
                $imageUrl = (string)$item->enclosure->attributes()->url;
            }
        }
        if (empty($imageUrl)) {
            $desc = (string)($item->description ?? '');
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $desc, $m)) {
                $imageUrl = $m[1];
            }
        }

        // Category: from RSS tag, or from feed metadata, or keyword extraction
        $category = '';
        if (isset($item->category)) {
            $category = (string)$item->category;
        } elseif (isset($dc->subject)) {
            $category = (string)$dc->subject;
        }
        if (empty($category) && !empty($feedMeta['category'])) {
            $category = $feedMeta['category'];
        }

        // Clean description for extraction
        $rawDesc = (string)($item->description ?? '');
        $cleanDescForExtraction = trim(strip_tags($rawDesc));

        // Region: keyword extraction from title+description (priority over feed metadata)
        $titleText = trim((string)($item->title ?? ''));
        $region = extractRegion($titleText, $cleanDescForExtraction, $feedMeta['region'] ?? null);

        // Category: keyword extraction fallback if still empty
        if (empty($category)) {
            $category = extractCategory($titleText, $cleanDescForExtraction, null);
        }

        // Published date
        $pubDate = (string)($item->pubDate ?? '');
        $publishedAt = !empty($pubDate) ? date('Y-m-d H:i:s', strtotime($pubDate)) : date('Y-m-d H:i:s');

        // Truncate description for storage
        $cleanDesc = $cleanDescForExtraction;
        if (mb_strlen($cleanDesc) > 300) {
            $cleanDesc = mb_substr($cleanDesc, 0, 300) . '...';
        }

        $url = trim((string)($item->link ?? ''));
        if (empty($url)) continue;

        $articles[] = [
            'title'        => trim((string)($item->title ?? 'Tanpa Judul')),
            'description'  => $cleanDesc,
            'url'          => $url,
            'image_url'    => $imageUrl,
            'category'     => $category ?: null,
            'region'       => $region,
            'author'       => (string)($dc->creator ?? 'ANTARA'),
            'published_at' => $publishedAt,
        ];
    }

    return $articles;
}

/**
 * Extract region (kabupaten/kota) from article title + description
 * using keyword matching against Sulut administrative areas
 */
function extractRegion(?string $title, ?string $description, ?string $feedRegion): ?string {
    // If feed already assigned a specific region (not generic "Sulawesi Utara"), keep it
    if ($feedRegion && $feedRegion !== 'Sulawesi Utara') {
        return $feedRegion;
    }

    $text = mb_strtolower(($title ?? '') . ' ' . ($description ?? ''));

    // Region keyword map — ordered from most specific to generic
    // Each entry: [keywords[], label]
    $regionMap = [
        // Kota
        [['kotamobagu'],                                        'Kota Kotamobagu'],
        [['tomohon'],                                           'Kota Tomohon'],
        [['bitung'],                                            'Kota Bitung'],
        // Kab multi-word first (before single-word catch)
        [['minahasa tenggara', 'mitra '],                       'Kab. Minahasa Tenggara'],
        [['minahasa selatan', 'minsel'],                        'Kab. Minahasa Selatan'],
        [['minahasa utara', 'minut'],                           'Kab. Minahasa Utara'],
        [['bolmong utara', 'bolmut', 'bolaang mongondow utara'],'Kab. Bolmong Utara'],
        [['bolmong selatan', 'bolsel', 'bolaang mongondow selatan'],'Kab. Bolmong Selatan'],
        [['bolmong timur', 'boltim', 'bolaang mongondow timur'],'Kab. Bolmong Timur'],
        [['bolaang mongondow', 'bolmong'],                      'Kab. Bolaang Mongondow'],
        [['kepulauan sangihe', 'sangihe', 'tahuna'],            'Kab. Kep. Sangihe'],
        [['kepulauan talaud', 'talaud', 'melonguane'],          'Kab. Kep. Talaud'],
        [['sitaro', 'siau', 'tagulandang', 'biaro'],            'Kab. Kep. Sitaro'],
        [['minahasa'],                                          'Kab. Minahasa'],
        // Kota Manado last — very common word
        [['manado'],                                            'Kota Manado'],
    ];

    foreach ($regionMap as [$keywords, $label]) {
        foreach ($keywords as $kw) {
            if (mb_strpos($text, $kw) !== false) {
                return $label;
            }
        }
    }

    // If feed region is set (e.g. "Sulawesi Utara"), keep it
    return $feedRegion;
}

/**
 * Extract category from article title + description
 * when RSS category tag is missing
 */
function extractCategory(?string $title, ?string $description, ?string $feedCategory): ?string {
    // If already has category from RSS or feed, keep it
    if (!empty($feedCategory)) {
        return $feedCategory;
    }

    $text = mb_strtolower(($title ?? '') . ' ' . ($description ?? ''));

    // Category keyword map
    $categoryMap = [
        'Politik & Hukum'       => ['politik', 'hukum', 'pemilu', 'pilkada', 'dprd', 'gubernur', 'walikota', 'bupati', 'caleg', 'partai', 'sidang', 'pengadilan', 'jaksa', 'polisi', 'kriminal', 'korupsi', 'narkoba', 'kpu', 'parpol', 'legislatif'],
        'Ekonomi & Bisnis'      => ['ekonomi', 'bisnis', 'investasi', 'inflasi', 'umkm', 'pajak', 'ekspor', 'impor', 'perdagangan', 'pasar', 'bank', 'keuangan', 'saham', 'rupiah', 'apbd', 'anggaran', 'industri'],
        'Olahraga'              => ['olahraga', 'sepak bola', 'liga', 'piala', 'turnamen', 'atlet', 'stadion', 'pertandingan', 'tim', 'pelatih', 'gol', 'pssi', 'pon', 'sea games', 'olimpiade', 'renang', 'bulu tangkis', 'tinju'],
        'Pendidikan'            => ['pendidikan', 'sekolah', 'universitas', 'mahasiswa', 'pelajar', 'guru', 'dosen', 'kampus', 'beasiswa', 'kurikulum', 'ujian', 'wisuda', 'siswa', 'sd ', 'smp ', 'sma '],
        'Kesejahteraan Rakyat'  => ['kesehatan', 'rumah sakit', 'puskesmas', 'vaksin', 'penyakit', 'bantuan sosial', 'bansos', 'bpjs', 'kemiskinan', 'stunting', 'gizi', 'medis', 'dokter', 'rsud', 'pasien'],
        'Wisata'                => ['wisata', 'pariwisata', 'turis', 'destinasi', 'pantai', 'gunung', 'diving', 'bunaken', 'taman laut', 'hotel', 'resort'],
        'Teknologi'             => ['teknologi', 'digital', 'internet', 'aplikasi', 'startup', 'ai ', 'artificial intelligence', 'cyber', 'data', 'satelit', 'inovasi'],
        'Hiburan'               => ['hiburan', 'musik', 'konser', 'film', 'seni', 'budaya', 'festival', 'artis', 'penyanyi', 'pameran', 'teater'],
        'Infrastruktur'         => ['infrastruktur', 'jalan', 'jembatan', 'bandara', 'pelabuhan', 'tol', 'pembangunan', 'proyek', 'konstruksi', 'gedung'],
        'Lingkungan'            => ['lingkungan', 'bencana', 'banjir', 'gempa', 'tsunami', 'longsor', 'kebakaran hutan', 'sampah', 'polusi', 'iklim', 'cuaca', 'gunung berapi'],
    ];

    foreach ($categoryMap as $category => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_strpos($text, $kw) !== false) {
                return $category;
            }
        }
    }

    return null;
}

/**
 * Ensure required tables exist (auto-migration)
 */
function ensureTablesExist($conn) {
    $check = $conn->query("SHOW TABLES LIKE 'rss_articles'");
    if ($check->num_rows === 0) {
        $sqlFile = __DIR__ . '/../database/migration_rss_intelligence.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $conn->multi_query($sql);
            // Consume all results
            while ($conn->next_result()) {;}
        }
    }
}
