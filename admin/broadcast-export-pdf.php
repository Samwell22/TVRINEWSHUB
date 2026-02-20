<?php
/**
 * Admin: Export PDF Broadcast Statistics
 */

require_once '../config/config.php';
require_once 'auth.php';

// Cek login
requireLogin();

// Ambil koneksi database
$conn = getDBConnection();

// Get logged in user
$logged_in_user = getLoggedInUser();

// Get period filter
$view_mode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'range';
$view_mode = in_array($view_mode, ['range', 'yearly'], true) ? $view_mode : 'range';
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';
$range = isset($_GET['range']) ? (int)$_GET['range'] : 7;
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Calculate date range
if ($view_mode === 'yearly' && $year >= 2000 && $year <= 2100) {
    $start_date = sprintf('%04d-01-01', $year);
    $end_date = sprintf('%04d-12-31', $year);
    $period_label = 'Tahun ' . $year;
} else {
    switch($period) {
        case 'daily':
            $start_date = date('Y-m-d', strtotime("-{$range} days"));
            $end_date = date('Y-m-d');
            $period_label = "$range Hari Terakhir";
            break;
        case 'weekly':
            $start_date = date('Y-m-d', strtotime("-{$range} weeks"));
            $end_date = date('Y-m-d');
            $period_label = "$range Minggu Terakhir";
            break;
        case 'monthly':
            $start_date = date('Y-m-d', strtotime("-{$range} months"));
            $end_date = date('Y-m-d');
            $period_label = "$range Bulan Terakhir";
            break;
        default:
            $start_date = date('Y-m-d', strtotime("-7 days"));
            $end_date = date('Y-m-d');
            $period_label = "7 Hari Terakhir";
    }
}

// Get statistics
$overall_query = "SELECT 
                  COUNT(*) as total,
                  SUM(CASE WHEN status = 'broadcasted' THEN 1 ELSE 0 END) as broadcasted,
                  SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                  FROM live_broadcast_schedule
                  WHERE broadcast_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $overall_query);
mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
mysqli_stmt_execute($stmt);
$overall_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$success_rate = $overall_stats['total'] > 0 
    ? round(($overall_stats['broadcasted'] / $overall_stats['total']) * 100, 1) 
    : 0;

// Get trend breakdown
if ($view_mode === 'yearly') {
    $daily_query = "SELECT 
                    DATE_FORMAT(broadcast_date, '%Y-%m') as period_key,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'broadcasted' THEN 1 ELSE 0 END) as broadcasted,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                    FROM live_broadcast_schedule
                    WHERE broadcast_date BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(broadcast_date, '%Y-%m')
                    ORDER BY period_key DESC";
} else {
    $daily_query = "SELECT 
                    broadcast_date,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'broadcasted' THEN 1 ELSE 0 END) as broadcasted,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                    FROM live_broadcast_schedule
                    WHERE broadcast_date BETWEEN ? AND ?
                    GROUP BY broadcast_date
                    ORDER BY broadcast_date DESC";
}
$stmt = mysqli_prepare($conn, $daily_query);
mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
mysqli_stmt_execute($stmt);
$daily_result = mysqli_stmt_get_result($stmt);

// Get failure reasons
$reasons_query = "SELECT 
                  failure_reason_type,
                  COUNT(*) as count
                  FROM live_broadcast_schedule
                  WHERE status = 'failed' 
                  AND broadcast_date BETWEEN ? AND ?
                  AND failure_reason_type IS NOT NULL
                  GROUP BY failure_reason_type
                  ORDER BY count DESC";
$stmt = mysqli_prepare($conn, $reasons_query);
mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
mysqli_stmt_execute($stmt);
$reasons_result = mysqli_stmt_get_result($stmt);

// Get failed broadcasts
$failed_query = "SELECT lbs.*, u.full_name as creator_name
                 FROM live_broadcast_schedule lbs
                 LEFT JOIN users u ON lbs.created_by = u.id
                 WHERE lbs.status = 'failed'
                 AND lbs.broadcast_date BETWEEN ? AND ?
                 ORDER BY lbs.broadcast_date DESC, lbs.created_at DESC";
$stmt = mysqli_prepare($conn, $failed_query);
mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
mysqli_stmt_execute($stmt);
$failed_list = mysqli_stmt_get_result($stmt);

// Set headers untuk PDF download (using browser print to PDF)
// Alternatif: bisa pakai library TCPDF atau DomPDF untuk generate native PDF
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Broadcast - <?php echo $period_label; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #1A428A;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .stats-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #1A428A;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-item {
            text-align: center;
            padding: 15px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }
        .stat-item h2 {
            margin: 0;
            font-size: 32px;
            color: #1A428A;
        }
        .stat-item p {
            margin: 5px 0 0 0;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        table th {
            background: #1A428A;
            color: white;
            font-weight: bold;
        }
        table tr:nth-child(even) {
            background: #f9f9f9;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin: 30px 0 15px 0;
            color: #1A428A;
            border-bottom: 2px solid #1A428A;
            padding-bottom: 5px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .success { color: #10b981; font-weight: bold; }
        .failed { color: #ef4444; font-weight: bold; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #1A428A; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">
            <strong>🖨️ Print / Save as PDF</strong>
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #666; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin-left: 10px;">
            Tutup
        </button>
    </div>

    <!-- Header -->
    <div class="header">
        <h1>📡 LAPORAN MONITORING LIVE BROADCAST</h1>
        <p><strong>TVRI Sulawesi Utara - TVRI NEWS HUB</strong></p>
        <p>Periode: <?php echo date('d F Y', strtotime($start_date)); ?> - <?php echo date('d F Y', strtotime($end_date)); ?> (<?php echo $period_label; ?>)</p>
        <p>Dicetak: <?php echo date('d F Y, H:i'); ?> WIB | Oleh: <?php echo $logged_in_user['full_name']; ?></p>
    </div>

    <!-- Overall Statistics -->
    <div class="stats-box">
        <h3 style="margin: 0 0 15px 0;">📊 Ringkasan Statistik</h3>
        <div class="stats-grid">
            <div class="stat-item">
                <h2><?php echo $overall_stats['total']; ?></h2>
                <p>Total Jadwal</p>
            </div>
            <div class="stat-item">
                <h2 class="success"><?php echo $overall_stats['broadcasted']; ?></h2>
                <p>Berhasil Tayang</p>
            </div>
            <div class="stat-item">
                <h2 class="failed"><?php echo $overall_stats['failed']; ?></h2>
                <p>Gagal Tayang</p>
            </div>
            <div class="stat-item">
                <h2><?php echo $success_rate; ?>%</h2>
                <p>Success Rate</p>
            </div>
        </div>
    </div>

    <!-- Daily Breakdown -->
    <div class="section-title">📅 <?= $view_mode === 'yearly' ? 'Breakdown Bulanan' : 'Breakdown Harian' ?></div>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th style="text-align: center;">Total</th>
                <th style="text-align: center;">Berhasil Tayang</th>
                <th style="text-align: center;">Gagal Tayang</th>
                <th style="text-align: center;">Success Rate</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            mysqli_data_seek($daily_result, 0);
            while ($day = mysqli_fetch_assoc($daily_result)): 
                $day_success = $day['total'] > 0 ? round(($day['broadcasted'] / $day['total']) * 100, 1) : 0;
            ?>
                <tr>
                    <td>
                        <?php if ($view_mode === 'yearly'): ?>
                            <?php echo date('F Y', strtotime(($day['period_key'] ?? '') . '-01')); ?>
                        <?php else: ?>
                            <?php echo date('d F Y (l)', strtotime($day['broadcast_date'])); ?>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;"><strong><?php echo $day['total']; ?></strong></td>
                    <td style="text-align: center;" class="success"><?php echo $day['broadcasted']; ?></td>
                    <td style="text-align: center;" class="failed"><?php echo $day['failed']; ?></td>
                    <td style="text-align: center;"><?php echo $day_success; ?>%</td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Failure Reasons -->
    <?php if (mysqli_num_rows($reasons_result) > 0): ?>
    <div class="section-title">⚠️ Alasan Gagal Tayang</div>
    <table>
        <thead>
            <tr>
                <th>Alasan</th>
                <th style="text-align: center;">Jumlah</th>
                <th style="text-align: center;">Persentase</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_failed = $overall_stats['failed'];
            mysqli_data_seek($reasons_result, 0);
            while ($reason = mysqli_fetch_assoc($reasons_result)): 
                $percentage = $total_failed > 0 ? round(($reason['count'] / $total_failed) * 100, 1) : 0;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($reason['failure_reason_type']); ?></td>
                    <td style="text-align: center;"><strong><?php echo $reason['count']; ?></strong></td>
                    <td style="text-align: center;"><?php echo $percentage; ?>%</td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Failed Broadcasts Detail -->
    <?php if (mysqli_num_rows($failed_list) > 0): ?>
    <div class="section-title">📋 Detail Berita Gagal Tayang</div>
    <table>
        <thead>
            <tr>
                <th style="width: 100px;">Tanggal</th>
                <th>Judul Berita</th>
                <th style="width: 150px;">Alasan</th>
                <th style="width: 120px;">Dibuat Oleh</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            mysqli_data_seek($failed_list, 0);
            while ($failed = mysqli_fetch_assoc($failed_list)): 
            ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($failed['broadcast_date'])); ?></td>
                    <td><?php echo htmlspecialchars($failed['news_title']); ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($failed['failure_reason_type']); ?></strong>
                        <?php if (!empty($failed['failure_reason_custom'])): ?>
                            <br><small style="color: #666;"><?php echo htmlspecialchars($failed['failure_reason_custom']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($failed['creator_name']); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer">
        <p><strong>TVRI Sulawesi Utara - TVRI NEWS HUB</strong></p>
        <p>Dokumen ini dihasilkan secara otomatis dari sistem Live Broadcast Monitoring</p>
        <p>© <?php echo date('Y'); ?> TVRI Sulawesi Utara. All Rights Reserved.</p>
    </div>
</body>
</html>
<?php
mysqli_close($conn);
?>
