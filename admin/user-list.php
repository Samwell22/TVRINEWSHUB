<?php
/**
 * Admin: Daftar User
 */

// Include config dan auth
require_once '../config/config.php';
require_once 'auth.php';

// Cek login
requireLogin();

// Ambil koneksi database
$conn = getDBConnection();

// Get logged in user
$logged_in_user = getLoggedInUser();

// Cek role: hanya ADMIN yang boleh manage users
if ($logged_in_user['role'] !== 'admin') {
    setFlashMessage('error', 'Anda tidak memiliki akses ke halaman ini! Hanya Admin yang boleh kelola user.');
    header('Location: dashboard.php');
    exit;
}

// HANDLE DELETE (POST only with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    requireCSRF();
    $user_id = (int)$_POST['id'];
    
    // Tidak boleh hapus diri sendiri
    if ($user_id === $logged_in_user['id']) {
        setFlashMessage('error', 'Anda tidak bisa menghapus akun Anda sendiri!');
        header('Location: user-list.php');
        exit;
    }
    
    // Cek apakah user punya berita
    $check_news = "SELECT COUNT(*) as total FROM news WHERE author_id = ?";
    $stmt_check = mysqli_prepare($conn, $check_news);
    mysqli_stmt_bind_param($stmt_check, 'i', $user_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $row = mysqli_fetch_assoc($result_check);
    
    if ($row['total'] > 0) {
        setFlashMessage('warning', 'User memiliki ' . $row['total'] . ' berita. Hapus atau transfer berita tersebut terlebih dahulu sebelum menghapus user.');
    } else {
        // Hapus user
        $delete_query = "DELETE FROM users WHERE id = ?";
        $stmt_delete = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt_delete, 'i', $user_id);
        
        if (mysqli_stmt_execute($stmt_delete)) {
            setFlashMessage('success', 'User berhasil dihapus!');
        } else {
            error_log('Gagal menghapus user: ' . mysqli_error($conn));
            setFlashMessage('error', 'Gagal menghapus user. Silakan coba lagi.');
        }
    }
    
    header('Location: user-list.php');
    exit;
}

// AMBIL SEMUA USER
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM news WHERE author_id = u.id) as news_count
          FROM users u 
          ORDER BY 
              CASE 
                  WHEN u.role = 'admin' THEN 1
                  WHEN u.role = 'editor' THEN 2
                  WHEN u.role = 'reporter' THEN 3
              END,
              u.full_name ASC";
$result = mysqli_query($conn, $query);

// Set page variables
$page_title = 'Kelola User';
$page_heading = 'Kelola User';
$breadcrumbs = [
    'Dashboard' => 'dashboard.php',
    'Kelola User' => null
];

// Include header
include 'includes/header.php';
?>

<!-- ACTION BUTTONS -->
<div class="mb-3">
    <a href="user-add.php" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Tambah User Baru
    </a>
</div>

<!-- USER TABLE -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-users"></i> Daftar User</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="userTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Jumlah Berita</th>
                                <th>Terakhir Login</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td>
                                        <i class="fas fa-envelope text-muted"></i>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </td>
                                    <td>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-user-shield"></i> Admin
                                            </span>
                                        <?php elseif ($user['role'] === 'editor'): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-user-edit"></i> Editor
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-user"></i> Reporter
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['news_count'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $user['news_count']; ?> berita</span>
                                        <?php else: ?>
                                            <span class="text-muted">0 berita</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['last_login']): ?>
                                            <small><?php echo date('d/m/Y H:i', strtotime($user['last_login'])); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Belum pernah login</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="user-add.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-outline-primary"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['id'] !== $logged_in_user['id']): ?>
                                                <form method="POST" action="" class="d-inline delete-form">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" 
                                                            class="btn btn-outline-danger"
                                                            title="Hapus"
                                                            onclick="return confirm('Yakin ingin menghapus user <?php echo htmlspecialchars($user['username']); ?>?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-outline-secondary" 
                                                        title="Tidak bisa hapus akun sendiri" 
                                                        disabled>
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- DataTables JS -->
<script>
    $(document).ready(function() {
        $('#userTable').DataTable({
            pageLength: 25,
            order: [[3, 'asc'], [1, 'asc']], // Sort by role then name
            language: {
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ user per halaman",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ user",
                infoEmpty: "Tidak ada user",
                infoFiltered: "(difilter dari _MAX_ total user)",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                    previous: "Sebelumnya"
                },
                zeroRecords: "User tidak ditemukan"
            }
        });
    });
</script>
