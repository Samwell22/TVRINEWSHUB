<?php
/**
 * Admin: Broadcast Statistics
 */

require_once '../config/config.php';
require_once 'auth.php';

// Cek login
requireLogin();

// Prevent caching for fresh data
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Ambil koneksi database
$conn = getDBConnection();

// Get logged in user
$logged_in_user = getLoggedInUser();

// Handle delete yearly statistics
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_year_stats') {
    requireCSRF();
    $delete_year = (int)($_POST['delete_year'] ?? 0);

    if ($delete_year >= 2000 && $delete_year <= 2100) {
        $delete_query = "DELETE FROM live_broadcast_schedule WHERE YEAR(broadcast_date) = ?";
        $stmt_delete = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt_delete, 'i', $delete_year);

        if (mysqli_stmt_execute($stmt_delete)) {
            $affected = mysqli_stmt_affected_rows($stmt_delete);
            setFlashMessage('success', "Berhasil menghapus {$affected} data statistik broadcast tahun {$delete_year}.");
        } else {
            setFlashMessage('error', 'Gagal menghapus statistik tahunan. Silakan coba lagi.');
        }
    } else {
        setFlashMessage('error', 'Tahun tidak valid.');
    }

    $redirect_year = ($delete_year >= 2000 && $delete_year <= 2100) ? $delete_year : (int)date('Y');
    header('Location: broadcast-statistics.php?view_mode=yearly&year=' . $redirect_year);
    exit;
}

// GET PERIOD FILTER
$view_mode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'range';
$view_mode = in_array($view_mode, ['range', 'yearly'], true) ? $view_mode : 'range';

$period = isset($_GET['period']) ? $_GET['period'] : 'daily';
$range = isset($_GET['range']) ? (int)$_GET['range'] : 7; // Default 7 days
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$available_years = [];
$years_query = "SELECT DISTINCT YEAR(broadcast_date) AS year_num FROM live_broadcast_schedule ORDER BY year_num DESC";
$years_result = mysqli_query($conn, $years_query);
if ($years_result) {
    while ($year_row = mysqli_fetch_assoc($years_result)) {
        $year_num = (int)($year_row['year_num'] ?? 0);
        if ($year_num > 0) {
            $available_years[] = $year_num;
        }
    }
}
if (empty($available_years)) {
    $available_years[] = (int)date('Y');
}
if (!in_array($selected_year, $available_years, true)) {
    $selected_year = $available_years[0];
}

// Calculate date range
if ($view_mode === 'yearly') {
    $start_date = sprintf('%04d-01-01', $selected_year);
    $end_date = sprintf('%04d-12-31', $selected_year);
    $period_label = 'Tahun ' . $selected_year;
} else {
    switch($period) {
        case 'daily':
            $start_date = date('Y-m-d', strtotime("-{$range} days"));
            $end_date = date('Y-m-d');
            break;
        case 'weekly':
            $start_date = date('Y-m-d', strtotime("-{$range} weeks"));
            $end_date = date('Y-m-d');
            break;
        case 'monthly':
            $start_date = date('Y-m-d', strtotime("-{$range} months"));
            $end_date = date('Y-m-d');
            break;
        default:
            $start_date = date('Y-m-d', strtotime("-7 days"));
            $end_date = date('Y-m-d');
    }
    $period_label = ucfirst($period) . ' - ' . $range . ' terakhir';
}

// GET STATISTICS

// Overall stats
$overall_query = "SELECT 
                  COUNT(*) as total,
                  SUM(CASE WHEN status = 'broadcasted' THEN 1 ELSE 0 END) as broadcasted,
                  SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                  SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled
                  FROM live_broadcast_schedule
                  WHERE broadcast_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $overall_query);
mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
mysqli_stmt_execute($stmt);
$overall_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Calculate success rate
$success_rate = $overall_stats['total'] > 0 
    ? round(($overall_stats['broadcasted'] / $overall_stats['total']) * 100, 1) 
    : 0;

// Trend breakdown for chart
if ($view_mode === 'yearly') {
    $daily_query = "SELECT 
                    DATE_FORMAT(broadcast_date, '%Y-%m') as period_key,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'broadcasted' THEN 1 ELSE 0 END) as broadcasted,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                    FROM live_broadcast_schedule
                    WHERE broadcast_date BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(broadcast_date, '%Y-%m')
                    ORDER BY period_key ASC";
} else {
    $daily_query = "SELECT 
                    broadcast_date,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'broadcasted' THEN 1 ELSE 0 END) as broadcasted,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                    FROM live_broadcast_schedule
                    WHERE broadcast_date BETWEEN ? AND ?
                    GROUP BY broadcast_date
                    ORDER BY broadcast_date ASC";
}
$stmt = mysqli_prepare($conn, $daily_query);
mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
mysqli_stmt_execute($stmt);
$daily_result = mysqli_stmt_get_result($stmt);

// Prepare data for chart
$chart_labels = [];
$chart_broadcasted = [];
$chart_failed = [];

while ($row = mysqli_fetch_assoc($daily_result)) {
    if ($view_mode === 'yearly') {
        $chart_labels[] = date('M Y', strtotime(($row['period_key'] ?? '') . '-01'));
    } else {
        $chart_labels[] = date('d M', strtotime($row['broadcast_date']));
    }
    $chart_broadcasted[] = $row['broadcasted'];
    $chart_failed[] = $row['failed'];
}

// Failure reasons breakdown
$reasons_query = "SELECT 
                  failure_reason_type,
                  COUNT(*) as count
                  FROM live_broadcast_schedule
                  WHERE status = 'failed' 
                  AND broadcast_date BETWEEN ? AND ?
                  AND failure_reason_type IS NOT NULL
                  GROUP BY failure_reason_type
                  ORDER BY count DESC
                  LIMIT 5";
$stmt = mysqli_prepare($conn, $reasons_query);
mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
mysqli_stmt_execute($stmt);
$reasons_result = mysqli_stmt_get_result($stmt);

// Prepare pie chart data
$pie_labels = [];
$pie_data = [];
$total_failed = 0;

while ($row = mysqli_fetch_assoc($reasons_result)) {
    $pie_labels[] = $row['failure_reason_type'];
    $pie_data[] = $row['count'];
    $total_failed += $row['count'];
}

// Failed broadcasts list
$failed_query = "SELECT lbs.*, u.full_name as creator_name
                 FROM live_broadcast_schedule lbs
                 LEFT JOIN users u ON lbs.created_by = u.id
                 WHERE lbs.status = 'failed'
                 AND lbs.broadcast_date BETWEEN ? AND ?
                 ORDER BY lbs.broadcast_date DESC, lbs.created_at DESC
                 LIMIT 20";
$stmt = mysqli_prepare($conn, $failed_query);
mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
mysqli_stmt_execute($stmt);
$failed_list = mysqli_stmt_get_result($stmt);

// Set page variables
$page_title = 'Broadcast Statistics';
$page_heading = 'Statistik Live Broadcast';
$breadcrumbs = [
    'Dashboard' => 'dashboard.php',
    'Live Broadcast' => 'broadcast-schedule.php',
    'Statistik' => null
];

// Include header
include 'includes/header.php';
?>

<!-- HEADER WITH FILTERS -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Filter Statistik</h5>
                    </div>
                    <div class="col-md-6">
                        <form method="GET" class="row g-2">
                            <div class="col-auto">
                                <select name="view_mode" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <option value="range" <?php echo $view_mode === 'range' ? 'selected' : ''; ?>>Rentang</option>
                                    <option value="yearly" <?php echo $view_mode === 'yearly' ? 'selected' : ''; ?>>Lihat Tahun</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <select name="period" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <option value="daily" <?php echo $period === 'daily' ? 'selected' : ''; ?>>Harian</option>
                                    <option value="weekly" <?php echo $period === 'weekly' ? 'selected' : ''; ?>>Mingguan</option>
                                    <option value="monthly" <?php echo $period === 'monthly' ? 'selected' : ''; ?>>Bulanan</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <select name="range" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <option value="7" <?php echo $range === 7 ? 'selected' : ''; ?>>7 Terakhir</option>
                                    <option value="14" <?php echo $range === 14 ? 'selected' : ''; ?>>14 Terakhir</option>
                                    <option value="30" <?php echo $range === 30 ? 'selected' : ''; ?>>30 Terakhir</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <?php foreach ($available_years as $year_option): ?>
                                        <option value="<?php echo $year_option; ?>" <?php echo $selected_year === $year_option ? 'selected' : ''; ?>>
                                            Tahun <?php echo $year_option; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-auto">
                                <a href="broadcast-export-pdf.php?view_mode=<?php echo urlencode($view_mode); ?>&period=<?php echo urlencode($period); ?>&range=<?php echo $range; ?>&year=<?php echo $selected_year; ?>" 
                                   class="btn btn-danger btn-sm" 
                                   target="_blank">
                                    <i class="fas fa-file-pdf"></i> Export PDF
                                </a>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteYearStatsModal">
                                    <i class="fas fa-trash"></i> Hapus Statistik Tahun
                                </button>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-primary btn-sm" onclick="location.reload(true);">
                                    <i class="fas fa-sync-alt"></i> Refresh Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- OVERVIEW STATS -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <h2><?php echo $overall_stats['total']; ?></h2>
            <p>Total Jadwal</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-lg" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
            <h2><?php echo $overall_stats['broadcasted']; ?></h2>
            <p>Berhasil Tayang</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-lg" style="background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);">
            <h2><?php echo $overall_stats['failed']; ?></h2>
            <p>Gagal Tayang</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-lg" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <h2><?php echo $success_rate; ?>%</h2>
            <p>Success Rate</p>
        </div>
    </div>
</div>

<!-- CHARTS -->
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar"></i> Broadcast Trend (<?php echo htmlspecialchars($period_label); ?>)</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="broadcastChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie"></i> Alasan Gagal Tayang</h5>
            </div>
            <div class="card-body">
                <?php if ($total_failed > 0): ?>
                    <div class="chart-container">
                        <canvas id="failureChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-check-circle"></i><br>
                        Tidak ada kegagalan!
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- FAILED BROADCASTS TABLE -->
<?php if (mysqli_num_rows($failed_list) > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-exclamation-triangle text-danger"></i> Daftar Berita Gagal Tayang</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Judul Berita</th>
                                <th>Alasan Gagal</th>
                                <th>Dibuat Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($failed = mysqli_fetch_assoc($failed_list)): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($failed['broadcast_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($failed['news_title']); ?></td>
                                    <td>
                                        <span class="badge bg-danger"><?php echo htmlspecialchars($failed['failure_reason_type']); ?></span>
                                        <?php if (!empty($failed['failure_reason_custom'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($failed['failure_reason_custom']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($failed['creator_name']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODAL: DELETE YEARLY STATISTICS -->
<div class="modal fade" id="deleteYearStatsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-trash"></i> Hapus Statistik Tahunan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" onsubmit="return confirmDeleteYearStats()">
                <?php echo csrfField(); ?>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_year_stats">
                    <div class="alert alert-danger mb-3">
                        <strong>Perhatian:</strong> Semua data broadcast pada tahun terpilih akan dihapus permanen.
                    </div>
                    <div class="mb-3">
                        <label for="deleteYearSelect" class="form-label">Pilih Tahun</label>
                        <select class="form-control" id="deleteYearSelect" name="delete_year" required>
                            <?php foreach ($available_years as $year_option): ?>
                                <option value="<?php echo $year_option; ?>" <?php echo $selected_year === $year_option ? 'selected' : ''; ?>>
                                    Tahun <?php echo $year_option; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="deleteYearConfirm" required>
                        <label class="form-check-label" for="deleteYearConfirm">
                            Saya memahami tindakan ini tidak dapat dibatalkan
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus Data Tahun Ini</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
function confirmDeleteYearStats() {
    var yearSelect = document.getElementById('deleteYearSelect');
    var check = document.getElementById('deleteYearConfirm');
    if (!yearSelect || !check) return false;
    if (!check.checked) {
        alert('Centang konfirmasi terlebih dahulu.');
        return false;
    }
    return confirm('Yakin ingin menghapus seluruh statistik broadcast tahun ' + yearSelect.value + '?');
}

// Broadcast Trend Chart (Bar)
const broadcastCtx = document.getElementById('broadcastChart').getContext('2d');
new Chart(broadcastCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [
            {
                label: 'Berhasil Tayang',
                data: <?php echo json_encode($chart_broadcasted); ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 2
            },
            {
                label: 'Gagal Tayang',
                data: <?php echo json_encode($chart_failed); ?>,
                backgroundColor: 'rgba(239, 68, 68, 0.8)',
                borderColor: 'rgba(239, 68, 68, 1)',
                borderWidth: 2
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false,
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

<?php if ($total_failed > 0): ?>
// Failure Reasons Chart (Pie)
const failureCtx = document.getElementById('failureChart').getContext('2d');
new Chart(failureCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($pie_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($pie_data); ?>,
            backgroundColor: [
                'rgba(239, 68, 68, 0.8)',
                'rgba(249, 115, 22, 0.8)',
                'rgba(234, 179, 8, 0.8)',
                'rgba(59, 130, 246, 0.8)',
                'rgba(139, 92, 246, 0.8)'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return label + ': ' + value + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php
// Include footer
include 'includes/footer.php';

// Tutup koneksi
mysqli_close($conn);
?>
