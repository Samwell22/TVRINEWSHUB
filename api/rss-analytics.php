<?php
/**
 * RSS Analytics API — Data analysis for News Intelligence
 * 
 * GET /api/rss-analytics.php?action={overview|trend|categories|regions|keywords|spikes|articles|top-news|update-status|last-fetch}
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';

$conn = getDBConnection();

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

switch ($action) {
    case 'overview':     echo json_encode(getOverview($conn));     break;
    case 'trend':        echo json_encode(getTrend($conn));        break;
    case 'categories':   echo json_encode(getCategories($conn));   break;
    case 'regions':      echo json_encode(getRegions($conn));      break;
    case 'keywords':     echo json_encode(getKeywords($conn));     break;
    case 'spikes':       echo json_encode(getSpikes($conn));       break;
    case 'articles':     echo json_encode(getArticles($conn));     break;
    case 'top-news':     echo json_encode(getTopNews($conn));      break;
    case 'update-status':echo json_encode(updateStatus($conn));    break;
    case 'last-fetch':   echo json_encode(getLastFetch($conn));    break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action. Valid: overview, trend, categories, regions, keywords, spikes, articles, top-news, update-status, last-fetch']);
}

// OVERVIEW — KPI summary stats
function getOverview($conn) {
    $today = date('Y-m-d');
    $weekAgo = date('Y-m-d', strtotime('-7 days'));

    // Total articles
    $total = $conn->query("SELECT COUNT(*) as c FROM rss_articles")->fetch_assoc()['c'] ?? 0;
    
    // Today count
    $todayCount = $conn->query("SELECT COUNT(*) as c FROM rss_articles WHERE DATE(published_at) = '{$today}'")->fetch_assoc()['c'] ?? 0;
    
    // This week
    $weekCount = $conn->query("SELECT COUNT(*) as c FROM rss_articles WHERE DATE(published_at) >= '{$weekAgo}'")->fetch_assoc()['c'] ?? 0;
    
    // By read/bookmarked status
    $unreadCount = $conn->query("SELECT COUNT(*) as c FROM rss_articles WHERE is_read = 0")->fetch_assoc()['c'] ?? 0;
    $readCount = $conn->query("SELECT COUNT(*) as c FROM rss_articles WHERE is_read = 1")->fetch_assoc()['c'] ?? 0;
    $bookmarkedCount = $conn->query("SELECT COUNT(*) as c FROM rss_articles WHERE is_bookmarked = 1")->fetch_assoc()['c'] ?? 0;
    $statusCounts = ['unread' => (int)$unreadCount, 'read' => (int)$readCount, 'bookmarked' => (int)$bookmarkedCount];

    // Top news count
    $topNewsCount = $conn->query("SELECT COUNT(*) as c FROM rss_articles WHERE is_top_news = 1")->fetch_assoc()['c'] ?? 0;

    // Regions active today
    $regionsToday = $conn->query("SELECT COUNT(DISTINCT region) as c FROM rss_articles WHERE DATE(published_at) = '{$today}' AND region IS NOT NULL")->fetch_assoc()['c'] ?? 0;

    // Categories active today
    $catsToday = $conn->query("SELECT COUNT(DISTINCT category) as c FROM rss_articles WHERE DATE(published_at) = '{$today}' AND category IS NOT NULL AND category != ''")->fetch_assoc()['c'] ?? 0;

    // Last fetch info
    $lastFetch = getSetting($conn, 'rss_last_fetch', null);
    
    // Last fetch log
    $lastLog = $conn->query("SELECT * FROM rss_fetch_log ORDER BY id DESC LIMIT 1");
    $fetchLog = $lastLog ? $lastLog->fetch_assoc() : null;

    return [
        'success' => true,
        'data' => [
            'total'          => (int)$total,
            'today'          => (int)$todayCount,
            'week'           => (int)$weekCount,
            'status'         => $statusCounts,
            'topNews'        => (int)$topNewsCount,
            'regionsToday'   => (int)$regionsToday,
            'categoriesActive' => (int)$catsToday,
            'lastFetch'      => $lastFetch,
            'lastFetchLog'   => $fetchLog,
        ]
    ];
}

// TREND — Daily article count for last N days
function getTrend($conn) {
    $days = isset($_GET['days']) ? min(max((int)$_GET['days'], 7), 90) : 30;
    $startDate = date('Y-m-d', strtotime("-{$days} days"));

    $result = $conn->query(
        "SELECT DATE(published_at) as dt, COUNT(*) as cnt
         FROM rss_articles
         WHERE DATE(published_at) >= '{$startDate}'
         GROUP BY DATE(published_at)
         ORDER BY dt ASC"
    );

    $labels = [];
    $values = [];
    $dataMap = [];

    while ($row = $result->fetch_assoc()) {
        $dataMap[$row['dt']] = (int)$row['cnt'];
    }

    // Fill gaps (days with zero articles)
    for ($i = $days; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $labels[] = date('d M', strtotime($d));
        $values[] = $dataMap[$d] ?? 0;
    }

    return [
        'success' => true,
        'data'    => ['labels' => $labels, 'values' => $values, 'days' => $days]
    ];
}

// CATEGORIES — Article count per category
function getCategories($conn) {
    $days = isset($_GET['days']) ? min(max((int)$_GET['days'], 1), 90) : 30;
    $startDate = date('Y-m-d', strtotime("-{$days} days"));

    $result = $conn->query(
        "SELECT COALESCE(NULLIF(category, ''), 'Tidak Berkategori') as cat, COUNT(*) as cnt
         FROM rss_articles
         WHERE DATE(published_at) >= '{$startDate}'
         GROUP BY cat
         ORDER BY cnt DESC
         LIMIT 15"
    );

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = ['label' => $row['cat'], 'value' => (int)$row['cnt']];
    }

    return ['success' => true, 'data' => $data];
}

// REGIONS — Article count per region (kabupaten/kota)
function getRegions($conn) {
    $days = isset($_GET['days']) ? min(max((int)$_GET['days'], 1), 90) : 30;
    $startDate = date('Y-m-d', strtotime("-{$days} days"));

    $result = $conn->query(
        "SELECT COALESCE(region, 'Tidak Diketahui') as rgn, COUNT(*) as cnt
         FROM rss_articles
         WHERE DATE(published_at) >= '{$startDate}' AND region IS NOT NULL
         GROUP BY rgn
         ORDER BY cnt DESC"
    );

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = ['label' => $row['rgn'], 'value' => (int)$row['cnt']];
    }

    return ['success' => true, 'data' => $data];
}

// KEYWORDS — Word frequency from titles (last 7 days)
function getKeywords($conn) {
    $days = isset($_GET['days']) ? min(max((int)$_GET['days'], 1), 30) : 7;
    $startDate = date('Y-m-d', strtotime("-{$days} days"));

    $result = $conn->query(
        "SELECT title FROM rss_articles WHERE DATE(published_at) >= '{$startDate}'"
    );

    // Indonesian stop words
    $stopWords = array_flip([
        'yang', 'dan', 'di', 'ini', 'itu', 'ke', 'dari', 'untuk', 'dengan', 'pada',
        'akan', 'sudah', 'tidak', 'bisa', 'juga', 'lebih', 'saat', 'masih', 'ada',
        'oleh', 'telah', 'atau', 'secara', 'atas', 'agar', 'bagi', 'belum', 'bukan',
        'hingga', 'karena', 'menjadi', 'namun', 'nya', 'pun', 'serta', 'setelah',
        'seperti', 'tentang', 'hanya', 'dalam', 'tersebut', 'bahwa', 'mereka', 'kita',
        'kami', 'saya', 'anda', 'ia', 'dia', 'lagi', 'maka', 'lalu', 'begitu',
        'adalah', 'merupakan', 'sebagai', 'dapat', 'baik', 'antara', 'tahun', 'ke',
        'se', 'ter', 'ber', 'men', 'per', 'kan', 'an', 'dua', 'satu', 'tiga',
        'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh',
        'kata', 'hal', 'para', 'banyak', 'sangat', 'beberapa', 'semua', 'masing',
        'setiap', 'paling', 'sejumlah', 'yakni', 'yaitu', 'maupun', 'sehingga',
        'bahkan', 'terus', 'tetap', 'harus', 'hendak', 'perlu', 'usai', 'pasca',
        'pra', 'selama', 'sambil', 'sejak', 'meski', 'walau', 'supaya',
        'the', 'and', 'of', 'in', 'to', 'a', 'is', 'for', 'on', 'news',
    ]);

    $wordCounts = [];
    while ($row = $result->fetch_assoc()) {
        $title = mb_strtolower($row['title']);
        // Remove punctuation
        $title = preg_replace('/[^a-z\x{00C0}-\x{024F}\s]/u', ' ', $title);
        $words = preg_split('/\s+/', $title, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) < 3) continue;
            if (isset($stopWords[$word])) continue;
            $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
        }
    }

    arsort($wordCounts);
    $top = array_slice($wordCounts, 0, 25, true);

    $data = [];
    foreach ($top as $word => $count) {
        $data[] = ['word' => $word, 'count' => $count];
    }

    return ['success' => true, 'data' => $data, 'days' => $days];
}

// SPIKES — Detect unusual article volume
function getSpikes($conn) {
    // Compare today and yesterday vs 14-day average
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $startDate = date('Y-m-d', strtotime('-14 days'));

    // Daily counts for the last 14 days
    $result = $conn->query(
        "SELECT DATE(published_at) as dt, COUNT(*) as cnt
         FROM rss_articles
         WHERE DATE(published_at) >= '{$startDate}'
         GROUP BY DATE(published_at)
         ORDER BY dt ASC"
    );

    $dailyCounts = [];
    while ($row = $result->fetch_assoc()) {
        $dailyCounts[$row['dt']] = (int)$row['cnt'];
    }

    $todayCount = $dailyCounts[$today] ?? 0;

    // Calculate 14-day average (excluding today)
    $past = array_filter($dailyCounts, fn($k) => $k !== $today, ARRAY_FILTER_USE_KEY);
    $avg = count($past) > 0 ? array_sum($past) / count($past) : 0;

    // Overall spike
    $overallSpike = $avg > 0 ? round($todayCount / $avg, 2) : 0;

    // Per-category spikes
    $catResult = $conn->query(
        "SELECT COALESCE(NULLIF(category, ''), 'Lainnya') as cat,
                SUM(CASE WHEN DATE(published_at) = '{$today}' THEN 1 ELSE 0 END) as today_cnt,
                SUM(CASE WHEN DATE(published_at) < '{$today}' AND DATE(published_at) >= '{$startDate}' THEN 1 ELSE 0 END) as past_cnt,
                COUNT(DISTINCT CASE WHEN DATE(published_at) < '{$today}' AND DATE(published_at) >= '{$startDate}' THEN DATE(published_at) END) as past_days
         FROM rss_articles
         WHERE DATE(published_at) >= '{$startDate}'
         GROUP BY cat
         HAVING today_cnt > 0"
    );

    $categorySpikes = [];
    while ($row = $catResult->fetch_assoc()) {
        $pastAvg = $row['past_days'] > 0 ? $row['past_cnt'] / $row['past_days'] : 0;
        $ratio = $pastAvg > 0 ? round($row['today_cnt'] / $pastAvg, 2) : 0;
        if ($ratio >= 1.5 || $row['today_cnt'] >= 5) {
            $categorySpikes[] = [
                'category'  => $row['cat'],
                'today'     => (int)$row['today_cnt'],
                'avgDaily'  => round($pastAvg, 1),
                'ratio'     => $ratio,
                'severity'  => $ratio >= 3 ? 'high' : ($ratio >= 2 ? 'medium' : 'low'),
            ];
        }
    }

    // Per-region spikes
    $regResult = $conn->query(
        "SELECT COALESCE(region, 'Lainnya') as rgn,
                SUM(CASE WHEN DATE(published_at) = '{$today}' THEN 1 ELSE 0 END) as today_cnt,
                SUM(CASE WHEN DATE(published_at) < '{$today}' AND DATE(published_at) >= '{$startDate}' THEN 1 ELSE 0 END) as past_cnt,
                COUNT(DISTINCT CASE WHEN DATE(published_at) < '{$today}' AND DATE(published_at) >= '{$startDate}' THEN DATE(published_at) END) as past_days
         FROM rss_articles
         WHERE DATE(published_at) >= '{$startDate}' AND region IS NOT NULL
         GROUP BY rgn
         HAVING today_cnt > 0"
    );

    $regionSpikes = [];
    while ($row = $regResult->fetch_assoc()) {
        $pastAvg = $row['past_days'] > 0 ? $row['past_cnt'] / $row['past_days'] : 0;
        $ratio = $pastAvg > 0 ? round($row['today_cnt'] / $pastAvg, 2) : 0;
        if ($ratio >= 1.5 || $row['today_cnt'] >= 3) {
            $regionSpikes[] = [
                'region'   => $row['rgn'],
                'today'    => (int)$row['today_cnt'],
                'avgDaily' => round($pastAvg, 1),
                'ratio'    => $ratio,
                'severity' => $ratio >= 3 ? 'high' : ($ratio >= 2 ? 'medium' : 'low'),
            ];
        }
    }

    // Sort by severity ratio
    usort($categorySpikes, fn($a, $b) => $b['ratio'] <=> $a['ratio']);
    usort($regionSpikes, fn($a, $b) => $b['ratio'] <=> $a['ratio']);

    return [
        'success' => true,
        'data' => [
            'todayTotal'     => $todayCount,
            'dailyAverage'   => round($avg, 1),
            'overallRatio'   => $overallSpike,
            'overallSeverity'=> $overallSpike >= 3 ? 'high' : ($overallSpike >= 2 ? 'medium' : ($overallSpike >= 1.5 ? 'low' : 'normal')),
            'categorySpikes' => $categorySpikes,
            'regionSpikes'   => $regionSpikes,
        ]
    ];
}

// ARTICLES — Paginated article list with filters
function getArticles($conn) {
    $page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit  = isset($_GET['limit']) ? min(max((int)$_GET['limit'], 5), 50) : 20;
    $offset = ($page - 1) * $limit;

    $status   = isset($_GET['status']) ? trim($_GET['status']) : '';
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    $region   = isset($_GET['region']) ? trim($_GET['region']) : '';
    $search   = isset($_GET['q']) ? trim($_GET['q']) : '';
    $topOnly  = isset($_GET['top_only']) && $_GET['top_only'] === '1';

    $where = [];
    $params = [];
    $types  = '';

    if ($status === 'unread') {
        $where[] = "is_read = 0";
    } elseif ($status === 'read') {
        $where[] = "is_read = 1";
    } elseif ($status === 'bookmarked') {
        $where[] = "is_bookmarked = 1";
    }
    if ($category) {
        $where[] = "category = ?";
        $params[] = $category;
        $types .= 's';
    }
    if ($region) {
        $where[] = "region = ?";
        $params[] = $region;
        $types .= 's';
    }
    if ($search) {
        $where[] = "title LIKE ?";
        $params[] = "%{$search}%";
        $types .= 's';
    }
    if ($topOnly) {
        $where[] = "is_top_news = 1";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count total
    $countSql = "SELECT COUNT(*) as c FROM rss_articles {$whereClause}";
    $stmt = $conn->prepare($countSql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    // Fetch articles
    $sql = "SELECT ra.*, u.full_name as reviewer_name
            FROM rss_articles ra
            LEFT JOIN users u ON ra.reviewed_by = u.id
            {$whereClause}
            ORDER BY ra.published_at DESC
            LIMIT {$limit} OFFSET {$offset}";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $articles = [];
    while ($row = $result->fetch_assoc()) {
        $articles[] = $row;
    }
    $stmt->close();

    return [
        'success' => true,
        'data'    => $articles,
        'meta'    => [
            'total'       => (int)$total,
            'page'        => $page,
            'limit'       => $limit,
            'totalPages'  => ceil($total / $limit),
        ]
    ];
}

// TOP NEWS — Articles from top-news feed
function getTopNews($conn) {
    $limit = isset($_GET['limit']) ? min(max((int)$_GET['limit'], 1), 20) : 10;

    $result = $conn->query(
        "SELECT ra.*, u.full_name as reviewer_name
         FROM rss_articles ra
         LEFT JOIN users u ON ra.reviewed_by = u.id
         WHERE ra.is_top_news = 1
         ORDER BY ra.published_at DESC
         LIMIT {$limit}"
    );

    $articles = [];
    while ($row = $result->fetch_assoc()) {
        $articles[] = $row;
    }

    return ['success' => true, 'data' => $articles];
}

// UPDATE STATUS — Change editorial status (admin only)
function updateStatus($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'error' => 'POST required'];
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    $articleId = isset($input['id']) ? (int)$input['id'] : 0;
    $action    = isset($input['action']) ? trim($input['action']) : '';

    if (!$articleId || !in_array($action, ['toggle-read', 'toggle-bookmark'])) {
        return ['success' => false, 'error' => 'Invalid article ID or action'];
    }

    if ($action === 'toggle-read') {
        $stmt = $conn->prepare("UPDATE rss_articles SET is_read = IF(is_read = 0, 1, 0) WHERE id = ?");
        $stmt->bind_param('i', $articleId);
        $stmt->execute();
        $stmt->close();
        // Get new state
        $row = $conn->query("SELECT is_read FROM rss_articles WHERE id = {$articleId}")->fetch_assoc();
        return ['success' => true, 'id' => $articleId, 'is_read' => (int)($row['is_read'] ?? 0)];
    }

    if ($action === 'toggle-bookmark') {
        $stmt = $conn->prepare("UPDATE rss_articles SET is_bookmarked = IF(is_bookmarked = 0, 1, 0) WHERE id = ?");
        $stmt->bind_param('i', $articleId);
        $stmt->execute();
        $stmt->close();
        $row = $conn->query("SELECT is_bookmarked FROM rss_articles WHERE id = {$articleId}")->fetch_assoc();
        return ['success' => true, 'id' => $articleId, 'is_bookmarked' => (int)($row['is_bookmarked'] ?? 0)];
    }

    return ['success' => false, 'error' => 'Unknown action'];
}

// LAST FETCH — Get last collection timestamp
function getLastFetch($conn) {
    $lastFetch = getSetting($conn, 'rss_last_fetch', null);
    return [
        'success'   => true,
        'lastFetch' => $lastFetch,
        'stale'     => $lastFetch ? (strtotime('now') - strtotime($lastFetch)) > 900 : true,
    ];
}
