<?php
/**
 * Admin: Calendar API
 */

require_once '../config/config.php';
require_once 'auth.php';

// Check login
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$conn = getDBConnection();
$logged_in_user = getLoggedInUser();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // GET - Fetch events for a month
        case 'GET':
            $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
            $month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
            
            $start_date = sprintf('%04d-%02d-01', $year, $month);
            $end_date = date('Y-m-t', strtotime($start_date));
            
            // Get user events
            $query = "SELECT ce.*, u.full_name as creator_name 
                      FROM calendar_events ce
                      LEFT JOIN users u ON ce.created_by = u.id
                      WHERE ce.event_date BETWEEN ? AND ?
                      ORDER BY ce.event_date ASC, ce.event_time ASC";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ss', $start_date, $end_date);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $events = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $events[] = [
                    'id' => (int)$row['id'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'event_date' => $row['event_date'],
                    'event_time' => $row['event_time'],
                    'event_type' => $row['event_type'],
                    'color' => $row['color'],
                    'is_all_day' => (bool)$row['is_all_day'],
                    'reminder_minutes' => (int)$row['reminder_minutes'],
                    'creator_name' => $row['creator_name'],
                    'is_mine' => ($row['created_by'] == $logged_in_user['id']),
                    'source' => 'local'
                ];
            }
            
            // Fetch holidays from external API
            $holidays = [];
            $cache_key = "holidays_{$year}_{$month}";
            
            // Check cache first
            $cache_query = "SELECT cache_data FROM api_cache WHERE cache_key = ? AND expires_at > NOW()";
            $cache_stmt = mysqli_prepare($conn, $cache_query);
            mysqli_stmt_bind_param($cache_stmt, 's', $cache_key);
            mysqli_stmt_execute($cache_stmt);
            $cache_result = mysqli_stmt_get_result($cache_stmt);
            
            if ($cached = mysqli_fetch_assoc($cache_result)) {
                $holidays = json_decode($cached['cache_data'], true) ?: [];
            } else {
                // Fetch from API
                $api_url = "https://holicuti-api.vercel.app/api?tahun={$year}&bulan={$month}";
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 5,
                        'ignore_errors' => true
                    ]
                ]);
                $api_response = @file_get_contents($api_url, false, $context);
                
                if ($api_response !== false) {
                    $holiday_data = json_decode($api_response, true);
                    if (is_array($holiday_data)) {
                        $holidays = $holiday_data;
                        
                        // Cache the response (24 hours)
                        $save_cache = "INSERT INTO api_cache (cache_key, cache_data, expires_at) 
                                       VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR)) 
                                       ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)";
                        $save_stmt = mysqli_prepare($conn, $save_cache);
                        $json_data = json_encode($holiday_data);
                        mysqli_stmt_bind_param($save_stmt, 'ss', $cache_key, $json_data);
                        mysqli_stmt_execute($save_stmt);
                    }
                }
            }
            
            // Convert holidays to event format
            $holiday_events = [];
            foreach ($holidays as $h) {
                $holiday_events[] = [
                    'id' => null,
                    'title' => $h['holiday_name'],
                    'description' => $h['holiday_type'],
                    'event_date' => $h['holiday_date'],
                    'event_time' => null,
                    'event_type' => 'holiday',
                    'color' => $h['is_national_holiday'] ? '#DC2626' : '#D97706',
                    'is_all_day' => true,
                    'reminder_minutes' => 0,
                    'creator_name' => null,
                    'is_mine' => false,
                    'source' => 'holiday_api',
                    'is_national' => $h['is_national_holiday']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'events' => $events,
                'holidays' => $holiday_events,
                'meta' => [
                    'year' => $year,
                    'month' => $month,
                    'total_events' => count($events),
                    'total_holidays' => count($holiday_events)
                ]
            ]);
            break;

        // POST - Create new event
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['title']) || empty($input['event_date'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Judul dan tanggal harus diisi']);
                exit;
            }
            
            $title = trim($input['title']);
            $description = isset($input['description']) ? trim($input['description']) : null;
            $event_date = $input['event_date'];
            $event_time = !empty($input['event_time']) ? $input['event_time'] : null;
            $event_type = isset($input['event_type']) ? $input['event_type'] : 'jadwal';
            $color = isset($input['color']) ? $input['color'] : '#1A428A';
            $is_all_day = isset($input['is_all_day']) ? (int)$input['is_all_day'] : 0;
            $reminder = isset($input['reminder_minutes']) ? (int)$input['reminder_minutes'] : 0;
            $user_id = $logged_in_user['id'];
            
            $query = "INSERT INTO calendar_events (title, description, event_date, event_time, event_type, color, is_all_day, reminder_minutes, created_by)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssssssiis', $title, $description, $event_date, $event_time, $event_type, $color, $is_all_day, $reminder, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Event berhasil ditambahkan',
                    'event_id' => $new_id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan event']);
            }
            break;

        // PUT - Update event
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['id']) || empty($input['title']) || empty($input['event_date'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
                exit;
            }
            
            $id = (int)$input['id'];
            $title = trim($input['title']);
            $description = isset($input['description']) ? trim($input['description']) : null;
            $event_date = $input['event_date'];
            $event_time = !empty($input['event_time']) ? $input['event_time'] : null;
            $event_type = isset($input['event_type']) ? $input['event_type'] : 'jadwal';
            $color = isset($input['color']) ? $input['color'] : '#1A428A';
            $is_all_day = isset($input['is_all_day']) ? (int)$input['is_all_day'] : 0;
            $reminder = isset($input['reminder_minutes']) ? (int)$input['reminder_minutes'] : 0;
            
            $query = "UPDATE calendar_events SET title=?, description=?, event_date=?, event_time=?, event_type=?, color=?, is_all_day=?, reminder_minutes=? WHERE id=?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssssssiis', $title, $description, $event_date, $event_time, $event_type, $color, $is_all_day, $reminder, $id);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Event berhasil diupdate']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal mengupdate event']);
            }
            break;

        // DELETE - Delete event
        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = isset($input['id']) ? (int)$input['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
            
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID event tidak valid']);
                exit;
            }
            
            $query = "DELETE FROM calendar_events WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $id);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Event berhasil dihapus']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus event']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>
