<?php
/**
 * Admin: Live Broadcast Schedule
 */

require_once '../config/config.php';
require_once 'auth.php';

// Cek login
requireLogin();

// Validate CSRF for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
}

// Ambil koneksi database
$conn = getDBConnection();

// Get logged in user
$logged_in_user = getLoggedInUser();

// HANDLE DELETE ACTION (POST only with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_single' && isset($_POST['id'])) {
    // CSRF already validated at top of file for all POST requests
    $schedule_id = (int)$_POST['id'];
    
    $delete_query = "DELETE FROM live_broadcast_schedule WHERE id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, 'i', $schedule_id);
    
    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage('success', 'Jadwal broadcast berhasil dihapus!');
    } else {
        setFlashMessage('error', 'Gagal menghapus jadwal!');
    }
    
    header('Location: broadcast-schedule.php');
    exit;
}

// HANDLE BULK ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
    $ids_raw = $_POST['ids'] ?? [];
    $ids = [];

    // Normalisasi input IDs agar stabil untuk single/multi select
    if (is_array($ids_raw)) {
        $ids = $ids_raw;
    } elseif (is_string($ids_raw) && trim($ids_raw) !== '') {
        $ids = explode(',', $ids_raw);
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function($id) {
        return $id > 0;
    })));

    $bulk_status = sanitizeInput($_POST['bulk_status'] ?? '');
    
    if (!empty($ids) && in_array($bulk_status, ['broadcasted', 'failed'])) {
        $success_count = 0;
        
        if ($bulk_status === 'broadcasted') {
            // Mark all as broadcasted
            foreach ($ids as $id) {
                $update_query = "UPDATE live_broadcast_schedule 
                                SET status = 'broadcasted', 
                                    failure_reason_type = NULL, 
                                    failure_reason_custom = NULL 
                                WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, 'i', $id);
                if (mysqli_stmt_execute($stmt)) {
                    $success_count++;
                }
            }
            setFlashMessage('success', "$success_count jadwal berhasil ditandai TAYANG!");
        } elseif ($bulk_status === 'failed') {
            // Mark all as failed with reason
            $failure_reason_type = sanitizeInput($_POST['bulk_failure_reason'] ?? '');
            $failure_reason_custom = sanitizeInput($_POST['bulk_failure_custom'] ?? '');
            
            foreach ($ids as $id) {
                $update_query = "UPDATE live_broadcast_schedule 
                                SET status = 'failed', 
                                    failure_reason_type = ?, 
                                    failure_reason_custom = ? 
                                WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, 'ssi', $failure_reason_type, $failure_reason_custom, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $success_count++;
                }
            }
            setFlashMessage('success', "$success_count jadwal berhasil ditandai GAGAL!");
        }
        
        header('Location: broadcast-schedule.php');
        exit;
    } else {
        setFlashMessage('error', 'Tidak ada jadwal yang dipilih!');
    }
}

// HANDLE BULK DELETE BY DATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete_by_date') {
    $delete_date = sanitizeInput($_POST['delete_date'] ?? '');
    
    if (!empty($delete_date)) {
        // Get count first to show in message
        $count_query = "SELECT COUNT(*) as total FROM live_broadcast_schedule WHERE broadcast_date = ?";
        $stmt = mysqli_prepare($conn, $count_query);
        mysqli_stmt_bind_param($stmt, 's', $delete_date);
        mysqli_stmt_execute($stmt);
        $count_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $total_records = $count_result['total'];
        
        if ($total_records > 0) {
            // Delete all records for this date
            $delete_query = "DELETE FROM live_broadcast_schedule WHERE broadcast_date = ?";
            $stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($stmt, 's', $delete_date);
            
            if (mysqli_stmt_execute($stmt)) {
                $affected = mysqli_stmt_affected_rows($stmt);
                setFlashMessage('success', "Berhasil menghapus $affected jadwal pada tanggal " . date('d M Y', strtotime($delete_date)) . "!");
            } else {
                setFlashMessage('error', 'Gagal menghapus jadwal!');
            }
        } else {
            setFlashMessage('info', 'Tidak ada jadwal pada tanggal tersebut.');
        }
    } else {
        setFlashMessage('error', 'Tanggal harus diisi!');
    }
    
    header('Location: broadcast-schedule.php');
    exit;
}

// HANDLE BULK TEXT IMPORT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_text') {
    $bulk_text = trim($_POST['bulk_text'] ?? '');
    $broadcast_date = sanitizeInput($_POST['broadcast_date'] ?? date('Y-m-d'));
    
    if (!empty($bulk_text)) {
        // Split by new line
        $lines = array_filter(array_map('trim', explode("\n", $bulk_text)));
        $success_count = 0;
        
        foreach ($lines as $line) {
            if (!empty($line)) {
                $insert_query = "INSERT INTO live_broadcast_schedule (broadcast_date, news_title, status, created_by, created_at) 
                                VALUES (?, ?, 'scheduled', ?, NOW())";
                $stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($stmt, 'ssi', $broadcast_date, $line, $logged_in_user['id']);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            setFlashMessage('success', "Berhasil menambahkan $success_count jadwal broadcast!");
        } else {
            setFlashMessage('error', 'Gagal menambahkan jadwal!');
        }
        
        header('Location: broadcast-schedule.php');
        exit;
    }
}

// HANDLE MANUAL ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'manual_add') {
    $news_title = sanitizeInput($_POST['news_title'] ?? '');
    $broadcast_date = sanitizeInput($_POST['broadcast_date'] ?? date('Y-m-d'));
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : NULL;
    
    if (!empty($news_title) && trim($news_title) !== '' && $news_title !== '0') {
        $insert_query = "INSERT INTO live_broadcast_schedule (broadcast_date, news_title, assigned_to, status, created_by, created_at) 
                        VALUES (?, ?, ?, 'scheduled', ?, NOW())";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, 'ssii', $broadcast_date, $news_title, $assigned_to, $logged_in_user['id']);
        
        if (mysqli_stmt_execute($stmt)) {
            setFlashMessage('success', 'Jadwal broadcast berhasil ditambahkan!');
        } else {
            setFlashMessage('error', 'Gagal menambahkan jadwal!');
        }
        
        header('Location: broadcast-schedule.php');
        exit;
    } else {
        setFlashMessage('error', 'Judul berita harus diisi dengan benar!');
    }
}

// GET SEARCH & FILTER PARAMS
$filter_date = isset($_GET['date']) ? sanitizeInput($_GET['date']) : date('Y-m-d');
$search_keyword = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter_user = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$filter_date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// GET SCHEDULE DATA WITH FILTERS
$query = "SELECT lbs.*, 
          u.full_name as creator_name,
          au.full_name as assigned_to_name
          FROM live_broadcast_schedule lbs
          LEFT JOIN users u ON lbs.created_by = u.id
          LEFT JOIN users au ON lbs.assigned_to = au.id
          WHERE 1=1";

$params = [];
$types = '';

// Apply filters
if (!empty($filter_date) && empty($filter_date_from) && empty($filter_date_to)) {
    $query .= " AND lbs.broadcast_date = ?";
    $params[] = $filter_date;
    $types .= 's';
}

if (!empty($filter_date_from) && !empty($filter_date_to)) {
    $query .= " AND lbs.broadcast_date BETWEEN ? AND ?";
    $params[] = $filter_date_from;
    $params[] = $filter_date_to;
    $types .= 'ss';
}

if (!empty($search_keyword)) {
    $query .= " AND lbs.news_title LIKE ?";
    $params[] = '%' . $search_keyword . '%';
    $types .= 's';
}

if ($filter_user > 0) {
    $query .= " AND (lbs.assigned_to = ? OR lbs.created_by = ?)";
    $params[] = $filter_user;
    $params[] = $filter_user;
    $types .= 'ii';
}

$query .= " ORDER BY 
            lbs.broadcast_date DESC,
            CASE lbs.status 
                WHEN 'scheduled' THEN 1
                WHEN 'failed' THEN 2
                WHEN 'broadcasted' THEN 3
                ELSE 4
            END,
            lbs.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$schedule_result = mysqli_stmt_get_result($stmt);

// Get statistics for selected date/filters
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
                SUM(CASE WHEN status = 'broadcasted' THEN 1 ELSE 0 END) as broadcasted,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM live_broadcast_schedule
                WHERE 1=1";

$stats_params = [];
$stats_types = '';

if (!empty($filter_date) && empty($filter_date_from) && empty($filter_date_to)) {
    $stats_query .= " AND broadcast_date = ?";
    $stats_params[] = $filter_date;
    $stats_types .= 's';
}

if (!empty($filter_date_from) && !empty($filter_date_to)) {
    $stats_query .= " AND broadcast_date BETWEEN ? AND ?";
    $stats_params[] = $filter_date_from;
    $stats_params[] = $filter_date_to;
    $stats_types .= 'ss';
}

$stmt_stats = mysqli_prepare($conn, $stats_query);
if (!empty($stats_params)) {
    mysqli_stmt_bind_param($stmt_stats, $stats_types, ...$stats_params);
}
mysqli_stmt_execute($stmt_stats);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_stats));

// Get failure reasons for dropdown
$reasons_query = "SELECT reason FROM broadcast_failure_reasons WHERE is_active = 1 ORDER BY display_order ASC";
$reasons_result = mysqli_query($conn, $reasons_query);

// Get all users for filter dropdown
$users_query = "SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name ASC";
$users_result = mysqli_query($conn, $users_query);

// Set page variables
$page_title = 'Live Broadcast Schedule';
$page_heading = 'Live Broadcast Monitoring';
$breadcrumbs = [
    'Dashboard' => 'dashboard.php',
    'Live Broadcast' => null
];

// Include header
include 'includes/header.php';
?>

<!-- STATISTICS CARDS -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3><?php echo $stats['total']; ?></h3>
            <p>Total Jadwal</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <h3><?php echo $stats['scheduled']; ?></h3>
            <p>Scheduled</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
            <h3><?php echo $stats['broadcasted']; ?></h3>
            <p>Broadcasted</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
            <h3><?php echo $stats['failed']; ?></h3>
            <p>Failed</p>
        </div>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-broadcast-tower"></i> Jadwal Broadcast</h5>
                <div>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#manualAddModal">
                        <i class="fas fa-plus"></i> Tambah Manual
                    </button>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkTextModal">
                        <i class="fas fa-paste"></i> Paste Judul
                    </button>
                    <a href="broadcast-statistics.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-chart-bar"></i> Statistik
                    </a>
                    <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#bulkDeleteDateModal">
                        <i class="fas fa-trash-alt"></i> Hapus by Tanggal
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- SEARCH & FILTER FORM -->
                <div class="card mb-4" style="background: #f8f9fa; border: 1px dashed #dee2e6;">
                    <div class="card-body">
                        <form method="GET" action="broadcast-schedule.php" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-search"></i> <strong>Kata Kunci</strong></label>
                                <input type="text" 
                                       class="form-control" 
                                       name="search" 
                                       value="<?php echo htmlspecialchars($search_keyword); ?>"
                                       placeholder="Cari judul berita...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-calendar"></i> <strong>Dari Tanggal</strong></label>
                                <input type="date" 
                                       class="form-control" 
                                       name="date_from" 
                                       value="<?php echo htmlspecialchars($filter_date_from); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-calendar"></i> <strong>Sampai Tanggal</strong></label>
                                <input type="date" 
                                       class="form-control" 
                                       name="date_to" 
                                       value="<?php echo htmlspecialchars($filter_date_to); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="fas fa-user"></i> <strong>User</strong></label>
                                <select class="form-control" name="user">
                                    <option value="">-- Semua User --</option>
                                    <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo ($filter_user == $user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Terapkan Filter
                                </button>
                                <a href="broadcast-schedule.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                                
                                <?php if (!empty($search_keyword) || $filter_user > 0 || (!empty($filter_date_from) && !empty($filter_date_to))): ?>
                                    <div class="d-inline-block ms-3">
                                        <span class="badge bg-info">
                                            <i class="fas fa-filter"></i> Filter aktif
                                        </span>
                                        <?php if (!empty($search_keyword)): ?>
                                            <span class="badge bg-secondary">
                                                Kata kunci: "<?php echo htmlspecialchars($search_keyword); ?>"
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($filter_date_from) && !empty($filter_date_to)): ?>
                                            <span class="badge bg-secondary">
                                                <?php echo date('d/m/Y', strtotime($filter_date_from)); ?> - 
                                                <?php echo date('d/m/Y', strtotime($filter_date_to)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- DATE FILTER (Quick Filter for Today) -->
                <?php if (empty($filter_date_from) && empty($filter_date_to)): ?>
                <div class="mb-4">
                    <label class="form-label"><strong>Quick Filter - Tanggal Spesifik:</strong></label>
                    <div class="input-group" style="max-width: 300px;">
                        <input type="date" 
                               class="form-control" 
                               id="filterDate" 
                               value="<?php echo $filter_date; ?>"
                               onchange="window.location.href='broadcast-schedule.php?date='+this.value">
                        <button class="btn btn-outline-secondary" onclick="document.getElementById('filterDate').value='<?php echo date('Y-m-d'); ?>'; document.getElementById('filterDate').onchange();">
                            Hari Ini
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- BULK ACTIONS -->
                <form id="bulkActionForm" method="POST" action="">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="bulk_update">
                    <input type="hidden" name="ids" id="bulkIdsInput" value="">
                    <div class="mb-3 p-3" style="background: #e0f2fe; border-radius: 8px; border: 1px solid #0284c7;">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <label class="form-check-label me-3" style="font-weight: 600;">
                                    <input type="checkbox" id="selectAll" class="form-check-input" style="width: 20px; height: 20px; margin-right: 8px;">
                                    Pilih Semua
                                </label>
                                <span id="selectedCount" class="badge bg-primary" style="font-size: 0.9rem;">0 dipilih</span>
                            </div>
                            <div class="col-md-4 text-end">
                                <button type="button" class="btn btn-success btn-sm" onclick="bulkMarkBroadcasted()">
                                    <i class="fas fa-check-circle"></i> Tandai Tayang
                                </button>
                                <button type="button" id="btnBulkFailed" class="btn btn-danger btn-sm">
                                    <i class="fas fa-times-circle"></i> Tandai Gagal
                                </button>
                            </div>
                        </div>
                    </div>

                <!-- SCHEDULE LIST -->
                <div id="scheduleList">
                    <?php if (mysqli_num_rows($schedule_result) > 0): ?>
                        <?php
                        $current_status_group = '';
                        $status_group_meta = [
                            'scheduled' => ['label' => 'Menunggu Tayang', 'color' => '#f59e0b', 'icon' => 'fa-clock'],
                            'failed' => ['label' => 'Gagal Tayang', 'color' => '#ef4444', 'icon' => 'fa-times-circle'],
                            'broadcasted' => ['label' => 'Sudah Tayang', 'color' => '#10b981', 'icon' => 'fa-check-circle'],
                        ];
                        ?>
                        <?php while ($item = mysqli_fetch_assoc($schedule_result)): ?>
                            <?php
                            $item_status = $item['status'];
                            if ($item_status !== $current_status_group):
                                $current_status_group = $item_status;
                                $group = $status_group_meta[$item_status] ?? ['label' => 'Status Lainnya', 'color' => '#64748b', 'icon' => 'fa-info-circle'];
                            ?>
                                <div class="mt-3 mb-2">
                                    <span class="badge" style="background: <?php echo $group['color']; ?>20; color: <?php echo $group['color']; ?>; font-weight: 700; font-size: 12px; padding: 8px 12px;">
                                        <i class="fas <?php echo $group['icon']; ?>"></i> <?php echo $group['label']; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="schedule-item <?php echo $item['status']; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <!-- CHECKBOX -->
                                    <div class="me-3 d-flex align-items-center">
                                        <?php if ($item['status'] === 'scheduled'): ?>
                                            <input type="checkbox" 
                                                   class="form-check-input schedule-checkbox" 
                                                   name="ids[]"
                                                   value="<?php echo $item['id']; ?>" 
                                                   style="width: 24px; height: 24px; cursor: pointer;" 
                                                   data-status="scheduled">
                                        <?php else: ?>
                                            <div style="width: 24px; height: 24px; opacity: 0.3;">
                                                <?php if ($item['status'] === 'broadcasted'): ?>
                                                    <i class="fas fa-check-circle text-success"></i>
                                                <?php elseif ($item['status'] === 'failed'): ?>
                                                    <i class="fas fa-times-circle text-danger"></i>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex-grow-1">
                                        <h6 class="mb-2">
                                            <?php if ($item['status'] === 'broadcasted'): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-circle"></i> Tayang</span>
                                            <?php elseif ($item['status'] === 'failed'): ?>
                                                <span class="badge bg-danger"><i class="fas fa-times-circle"></i> Gagal</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning"><i class="fas fa-clock"></i> Scheduled</span>
                                            <?php endif; ?>
                                            
                                            <strong><?php echo htmlspecialchars($item['news_title']); ?></strong>
                                        </h6>
                                        
                                        <div class="mb-1">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($item['broadcast_date'])); ?> | 
                                                <i class="fas fa-user"></i> Dibuat: <?php echo htmlspecialchars($item['creator_name']); ?>
                                                <?php if (!empty($item['assigned_to_name'])): ?>
                                                    | <i class="fas fa-user-tag"></i> Penanggung Jawab: <?php echo htmlspecialchars($item['assigned_to_name']); ?>
                                                <?php endif; ?>
                                                | <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($item['created_at'])); ?>
                                            </small>
                                        </div>
                                        
                                        <?php if (!empty($item['news_category'])): ?>
                                            <p class="mb-1 text-muted">
                                                <i class="fas fa-folder"></i> <?php echo htmlspecialchars($item['news_category']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($item['status'] === 'failed'): ?>
                                            <p class="mb-1 text-danger">
                                                <i class="fas fa-exclamation-triangle"></i> 
                                                <strong>Alasan:</strong> 
                                                <?php 
                                                echo htmlspecialchars($item['failure_reason_type']); 
                                                if (!empty($item['failure_reason_custom'])) {
                                                    echo ' - ' . htmlspecialchars($item['failure_reason_custom']);
                                                }
                                                ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="btn-group btn-group-sm ms-3">
                                        <?php if ($item['status'] === 'scheduled'): ?>
                                            <button type="button" class="btn btn-success" onclick="markBroadcasted(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger" onclick="markFailed(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-primary" onclick="editBroadcast(<?php echo $item['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-outline-danger"
                                                onclick="deleteSingleSchedule(<?php echo $item['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i> Tidak ada jadwal broadcast yang ditemukan
                        </div>
                    <?php endif; ?>
                </div>
                </form>

                <form id="deleteSingleForm" method="POST" action="" class="d-none">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="delete_single">
                    <input type="hidden" name="id" id="deleteSingleId" value="">
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: MANUAL ADD -->
<div class="modal fade" id="manualAddModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Jadwal Manual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <div class="modal-body">
                    <input type="hidden" name="action" value="manual_add">
                    
                    <div class="mb-3">
                        <label class="form-label">Tanggal Broadcast <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="broadcast_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Judul Berita <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="news_title" placeholder="Masukkan judul berita..." required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Penanggung Jawab (opsional)</label>
                        <select class="form-control" name="assigned_to">
                            <option value="">-- Pilih User --</option>
                            <?php 
                            mysqli_data_seek($users_result, 0);
                            while ($user = mysqli_fetch_assoc($users_result)): 
                            ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: BULK TEXT -->
<div class="modal fade" id="bulkTextModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-paste"></i> Paste Beberapa Judul Sekaligus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_text">
                    
                    <div class="mb-3">
                        <label class="form-label">Tanggal Broadcast <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="broadcast_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Judul Berita (satu per baris) <span class="text-danger">*</span></label>
                        <textarea class="form-control" 
                                  name="bulk_text" 
                                  rows="10" 
                                  placeholder="Breaking News: Banjir Manado&#10;Update COVID-19 Sulut&#10;Demo Mahasiswa di Unsrat&#10;Gubernur Rapat dengan DPRD&#10;Persipura Menang 2-1&#10;..." 
                                  required></textarea>
                        <small class="text-muted">💡 Paste judul berita, satu judul per baris. Misal: copy 18 judul dari dokumen Word/Excel.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Import Semua</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: MARK FAILED -->
<div class="modal fade" id="failedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Tandai Gagal Tayang</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="failedForm">
                <div class="modal-body">
                    <input type="hidden" id="failedId" name="id">
                    <input type="hidden" id="failedMode" value="single">
                    <div class="alert alert-warning" id="failedBulkInfo" style="display: none;"></div>
                    
                    <div class="mb-3">
                        <label class="form-label">Alasan Gagal Tayang <span class="text-danger">*</span></label>
                        <select class="form-control" id="failureReason" name="failure_reason_type" required onchange="toggleCustomReason()">
                            <option value="">-- Pilih Alasan --</option>
                            <?php 
                            mysqli_data_seek($reasons_result, 0);
                            while ($reason = mysqli_fetch_assoc($reasons_result)): 
                            ?>
                                <option value="<?php echo $reason['reason']; ?>"><?php echo $reason['reason']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="customReasonDiv" style="display: none;">
                        <label class="form-label">Tulis Alasan Custom</label>
                        <textarea class="form-control" id="customReason" name="failure_reason_custom" rows="3" placeholder="Jelaskan kenapa gagal tayang..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" id="failedSubmitBtn" class="btn btn-danger">Tandai Gagal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: BULK MARK FAILED -->
<div class="modal fade" id="bulkFailedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Tandai Gagal Tayang (Bulk)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>Perhatian:</strong> Anda akan menandai <strong id="bulkFailedCount">0</strong> jadwal sekaligus sebagai GAGAL TAYANG.
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Alasan Gagal Tayang <span class="text-danger">*</span></label>
                    <select class="form-control" id="bulkFailureReason" required onchange="toggleBulkCustomReason()">
                        <option value="">-- Pilih Alasan --</option>
                        <?php 
                        mysqli_data_seek($reasons_result, 0);
                        while ($reason = mysqli_fetch_assoc($reasons_result)): 
                        ?>
                            <option value="<?php echo $reason['reason']; ?>"><?php echo $reason['reason']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-3" id="bulkCustomReasonDiv" style="display: none;">
                    <label class="form-label">Tulis Alasan Custom</label>
                    <textarea class="form-control" id="bulkCustomReason" rows="3" placeholder="Jelaskan kenapa gagal tayang..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" onclick="submitBulkFailed()">Tandai Gagal Semua</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: BULK DELETE BY DATE -->
<div class="modal fade" id="bulkDeleteDateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-trash-alt"></i> Hapus Semua Jadwal Berdasarkan Tanggal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" onsubmit="return confirmDeleteByDate()">
                <?php echo csrfField(); ?>
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_delete_by_date">
                    
                    <div class="alert alert-danger">
                        <strong><i class="fas fa-exclamation-triangle"></i> PERINGATAN!</strong><br>
                        Anda akan menghapus <strong>SEMUA jadwal broadcast</strong> pada tanggal yang dipilih. 
                        Tindakan ini <strong>TIDAK BISA DIBATALKAN</strong>!
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Pilih Tanggal yang Akan Dihapus <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="delete_date" id="deleteDate" required>
                        <small class="text-muted">Semua berita pada tanggal ini akan dihapus permanen dari database</small>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmDeleteCheck" required>
                        <label class="form-check-label text-danger" for="confirmDeleteCheck">
                            <strong>Ya, saya yakin ingin menghapus semua data di tanggal tersebut</strong>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Hapus Semua
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// WAIT FOR DOM READY
document.addEventListener('DOMContentLoaded', function() {
    // Initialize select all checkbox
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.schedule-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
    
    // Initialize individual checkboxes
    const scheduleCheckboxes = document.querySelectorAll('.schedule-checkbox');
    scheduleCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedCount();
        });
    });
    
    // Initialize count on load
    updateSelectedCount();

    // Defensive listener: ensure bulk failed button always responds
    const btnBulkFailed = document.getElementById('btnBulkFailed');
    if (btnBulkFailed) {
        btnBulkFailed.addEventListener('click', function(e) {
            e.preventDefault();
            showBulkFailedModal();
        });
    }
    
    // FAILED FORM SUBMIT HANDLER
    const failedForm = document.getElementById('failedForm');
    if (failedForm) {
        failedForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const mode = document.getElementById('failedMode') ? document.getElementById('failedMode').value : 'single';
            const reason = document.getElementById('failureReason').value;
            const customReason = document.getElementById('customReason').value;

            if (!reason) {
                alert('Pilih alasan gagal tayang!');
                return;
            }

            if (reason === 'Lainnya (tulis manual)' && !customReason) {
                alert('Tulis alasan custom!');
                return;
            }

            if (mode === 'bulk') {
                submitBulkFailedDirect(reason, customReason);
                return;
            }
            
            const formData = new FormData(this);
            formData.append('status', 'failed');
            
            fetch('broadcast-update-status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('failedModal')).hide();
                    location.reload();
                } else {
                    alert('Gagal update status!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat update status!');
            });
        });
    }
});

// UPDATE SELECTED COUNT
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.schedule-checkbox:checked');
    const count = checkboxes.length;
    const countElement = document.getElementById('selectedCount');
    
    if (countElement) {
        countElement.textContent = count + ' dipilih';
    }
    
    // Update select all checkbox state
    const allCheckboxes = document.querySelectorAll('.schedule-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    
    if (selectAllCheckbox && allCheckboxes.length > 0) {
        if (count === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (count === allCheckboxes.length) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }
}

// BULK MARK AS BROADCASTED
function bulkMarkBroadcasted() {
    const checkboxes = document.querySelectorAll('.schedule-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Pilih minimal 1 jadwal untuk ditandai tayang!');
        return;
    }
    
    if (confirm(`Tandai ${checkboxes.length} jadwal sebagai TAYANG?`)) {
        const form = document.getElementById('bulkActionForm');
        if (!attachSelectedIdsToBulkForm()) {
            alert('Tidak ada ID jadwal valid yang dipilih.');
            return;
        }

        // Clear previous hidden inputs to avoid duplicate payload
        form.querySelectorAll('input[name="bulk_status"]').forEach(input => input.remove());
        form.querySelectorAll('input[name="bulk_failure_reason"]').forEach(input => input.remove());
        form.querySelectorAll('input[name="bulk_failure_custom"]').forEach(input => input.remove());

        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'bulk_status';
        statusInput.value = 'broadcasted';
        form.appendChild(statusInput);
        
        form.submit();
    }
}

// BULK MARK AS FAILED
function showBulkFailedModal() {
    const checkboxes = document.querySelectorAll('.schedule-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Pilih minimal 1 jadwal untuk ditandai gagal!');
        return;
    }

    const modalEl = document.getElementById('bulkFailedModal');
    if (!modalEl) {
        alert('Modal tidak ditemukan. Silakan refresh halaman.');
        return;
    }

    // Update count display
    const countEl = document.getElementById('bulkFailedCount');
    if (countEl) countEl.textContent = checkboxes.length;

    // Reset form fields
    const reasonEl = document.getElementById('bulkFailureReason');
    const customEl = document.getElementById('bulkCustomReason');
    const customDiv = document.getElementById('bulkCustomReasonDiv');
    if (reasonEl) reasonEl.value = '';
    if (customEl) customEl.value = '';
    if (customDiv) customDiv.style.display = 'none';

    try {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    } catch (error) {
        console.error('Gagal membuka modal bulk failed:', error);
        alert('Gagal membuka form alasan gagal. Silakan refresh halaman.');
    }
}

function submitBulkFailedDirect(reason, customReason) {
    const checkboxes = document.querySelectorAll('.schedule-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Pilih minimal 1 jadwal untuk ditandai gagal!');
        return;
    }

    const form = document.getElementById('bulkActionForm');
    if (!form) {
        alert('Form bulk action tidak ditemukan. Silakan refresh halaman.');
        return;
    }

    if (!attachSelectedIdsToBulkForm()) {
        alert('Tidak ada ID jadwal valid yang dipilih.');
        return;
    }

    const modalInstance = bootstrap.Modal.getInstance(document.getElementById('bulkFailedModal'));
    if (modalInstance) {
        modalInstance.hide();
    }

    form.querySelectorAll('input[name="bulk_status"]').forEach(input => input.remove());
    form.querySelectorAll('input[name="bulk_failure_reason"]').forEach(input => input.remove());
    form.querySelectorAll('input[name="bulk_failure_custom"]').forEach(input => input.remove());

    const statusInput = document.createElement('input');
    statusInput.type = 'hidden';
    statusInput.name = 'bulk_status';
    statusInput.value = 'failed';
    form.appendChild(statusInput);

    const reasonInput = document.createElement('input');
    reasonInput.type = 'hidden';
    reasonInput.name = 'bulk_failure_reason';
    reasonInput.value = reason;
    form.appendChild(reasonInput);

    if (customReason) {
        const customInput = document.createElement('input');
        customInput.type = 'hidden';
        customInput.name = 'bulk_failure_custom';
        customInput.value = customReason;
        form.appendChild(customInput);
    }

    form.submit();
}

function toggleBulkCustomReason() {
    const select = document.getElementById('bulkFailureReason');
    const customDiv = document.getElementById('bulkCustomReasonDiv');
    
    if (select.value === 'Lainnya (tulis manual)') {
        customDiv.style.display = 'block';
        document.getElementById('bulkCustomReason').required = true;
    } else {
        customDiv.style.display = 'none';
        document.getElementById('bulkCustomReason').required = false;
    }
}

function submitBulkFailed() {
    const reason = document.getElementById('bulkFailureReason').value;
    if (!reason) {
        alert('Pilih alasan gagal tayang!');
        return;
    }
    
    const customReason = document.getElementById('bulkCustomReason').value;
    if (reason === 'Lainnya (tulis manual)' && !customReason) {
        alert('Tulis alasan custom!');
        return;
    }
    
    submitBulkFailedDirect(reason, customReason);
}

// BULK DELETE BY DATE - CONFIRMATION
function confirmDeleteByDate() {
    const dateInput = document.getElementById('deleteDate');
    const confirmCheckbox = document.getElementById('confirmDeleteCheck');
    
    if (!dateInput.value) {
        alert('Pilih tanggal terlebih dahulu!');
        return false;
    }
    
    if (!confirmCheckbox.checked) {
        alert('Centang konfirmasi untuk melanjutkan!');
        return false;
    }
    
    const dateFormatted = new Date(dateInput.value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
    
    const confirmation = confirm(
        `KONFIRMASI TERAKHIR!\n\n` +
        `Anda akan MENGHAPUS PERMANEN semua jadwal broadcast pada:\n` +
        `${dateFormatted}\n\n` +
        `Tindakan ini TIDAK DAPAT DIBATALKAN!\n\n` +
        `Lanjutkan?`
    );
    
    return confirmation;
}

// SINGLE ITEM ACTIONS
function markBroadcasted(id) {
    console.log('markBroadcasted called with id:', id);
    if (confirm('Tandai berita ini sudah tayang?')) {
        fetch('broadcast-update-status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${id}&status=broadcasted`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Gagal update status!');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat update status!');
        });
    }
}

function markFailed(id) {
    console.log('=== markFailed START ===');
    console.log('ID:', id);
    console.log('typeof bootstrap:', typeof bootstrap);
    
    try {
        const failedIdInput = document.getElementById('failedId');
        const failureReasonSelect = document.getElementById('failureReason');
        const customReasonTextarea = document.getElementById('customReason');
        const customReasonDiv = document.getElementById('customReasonDiv');
        const modalElement = document.getElementById('failedModal');
        
        console.log('Elements found:', {
            failedIdInput: !!failedIdInput,
            failureReasonSelect: !!failureReasonSelect,
            customReasonTextarea: !!customReasonTextarea,
            customReasonDiv: !!customReasonDiv,
            modalElement: !!modalElement
        });
        
        if (!failedIdInput || !failureReasonSelect || !customReasonTextarea || !customReasonDiv || !modalElement) {
            console.error('Modal elements not found!');
            alert('Error: Modal tidak ditemukan. Silakan refresh halaman.');
            return;
        }

        const failedModeEl = document.getElementById('failedMode');
        const failedBulkInfo = document.getElementById('failedBulkInfo');
        const failedSubmitBtn = document.getElementById('failedSubmitBtn');
        if (failedModeEl) failedModeEl.value = 'single';
        if (failedBulkInfo) failedBulkInfo.style.display = 'none';
        if (failedSubmitBtn) failedSubmitBtn.textContent = 'Tandai Gagal';
        const titleEl = modalElement.querySelector('.modal-title');
        if (titleEl) {
            titleEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Tandai Gagal Tayang';
        }
        
        failedIdInput.value = id;
        failureReasonSelect.value = '';
        customReasonTextarea.value = '';
        customReasonDiv.style.display = 'none';
        
        console.log('About to create bootstrap.Modal...');
        const modal = new bootstrap.Modal(modalElement);
        console.log('Modal created, showing...');
        modal.show();
        console.log('=== markFailed END - SUCCESS ===');
    } catch (error) {
        console.error('=== markFailed ERROR ===');
        console.error('Error details:', error);
        console.error('Error message:', error.message);
        console.error('Error stack:', error.stack);
        alert('Terjadi kesalahan: ' + error.message);
    }
}

function toggleCustomReason() {
    const select = document.getElementById('failureReason');
    const customDiv = document.getElementById('customReasonDiv');
    
    if (select.value === 'Lainnya (tulis manual)') {
        customDiv.style.display = 'block';
        document.getElementById('customReason').required = true;
    } else {
        customDiv.style.display = 'none';
        document.getElementById('customReason').required = false;
    }
}

function editBroadcast(id) {
    alert('Fitur edit akan ditambahkan. ID: ' + id);
}

function getSelectedScheduleIds() {
    return Array.from(document.querySelectorAll('.schedule-checkbox:checked'))
        .map(function(checkbox) { return parseInt(checkbox.value, 10); })
        .filter(function(id) { return Number.isInteger(id) && id > 0; });
}

function attachSelectedIdsToBulkForm() {
    const ids = getSelectedScheduleIds();
    const bulkIdsInput = document.getElementById('bulkIdsInput');
    if (!bulkIdsInput) {
        return false;
    }
    bulkIdsInput.value = ids.join(',');
    return ids.length > 0;
}

function deleteSingleSchedule(id) {
    const parsedId = parseInt(id, 10);
    if (!Number.isInteger(parsedId) || parsedId <= 0) {
        alert('ID jadwal tidak valid.');
        return;
    }

    if (!confirm('Yakin hapus jadwal ini?')) {
        return;
    }

    const deleteInput = document.getElementById('deleteSingleId');
    const deleteForm = document.getElementById('deleteSingleForm');
    if (!deleteInput || !deleteForm) {
        alert('Form hapus tidak ditemukan. Silakan refresh halaman.');
        return;
    }

    deleteInput.value = String(parsedId);
    deleteForm.submit();
}
</script>

<?php
// Include footer
include 'includes/footer.php';

// Tutup koneksi
mysqli_close($conn);
?>
