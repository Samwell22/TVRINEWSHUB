<?php
/**
 * Admin: Daftar Kategori
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

// HANDLE DELETE (POST only with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    requireCSRF();
    $category_id = (int)$_POST['id'];
    
    // Cek apakah kategori masih punya berita
    $check_news = "SELECT COUNT(*) as total FROM news WHERE category_id = ?";
    $stmt_check = mysqli_prepare($conn, $check_news);
    mysqli_stmt_bind_param($stmt_check, 'i', $category_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $row = mysqli_fetch_assoc($result_check);
    
    if ($row['total'] > 0) {
        setFlashMessage('error', 'Kategori tidak bisa dihapus karena masih ada ' . $row['total'] . ' berita yang menggunakan kategori ini!');
    } else {
        // Hapus kategori
        $delete_query = "DELETE FROM categories WHERE id = ?";
        $stmt_delete = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt_delete, 'i', $category_id);
        
        if (mysqli_stmt_execute($stmt_delete)) {
            setFlashMessage('success', 'Kategori berhasil dihapus!');
        } else {
            error_log('Gagal menghapus kategori: ' . mysqli_error($conn));
            setFlashMessage('error', 'Gagal menghapus kategori. Silakan coba lagi.');
        }
    }
    
    header('Location: kategori-list.php');
    exit;
}

// HANDLE TOGGLE ACTIVE/INACTIVE (POST only with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle' && isset($_POST['id'])) {
    requireCSRF();
    $category_id = (int)$_POST['id'];
    $target_status = isset($_POST['target_status']) ? (int)$_POST['target_status'] : null;

    if ($target_status !== 0 && $target_status !== 1) {
        setFlashMessage('error', 'Target status kategori tidak valid.');
        header('Location: kategori-list.php');
        exit;
    }
    
    // Set is_active explicitly (avoid nullable/NOT edge cases)
    $toggle_query = "UPDATE categories SET is_active = ? WHERE id = ?";
    $stmt_toggle = mysqli_prepare($conn, $toggle_query);
    mysqli_stmt_bind_param($stmt_toggle, 'ii', $target_status, $category_id);
    
    if (mysqli_stmt_execute($stmt_toggle)) {
        setFlashMessage('success', 'Status kategori berhasil diubah!');
    } else {
        error_log('Gagal mengubah status kategori: ' . mysqli_error($conn));
        setFlashMessage('error', 'Gagal mengubah status kategori. Silakan coba lagi.');
    }
    
    header('Location: kategori-list.php');
    exit;
}

// AMBIL SEMUA KATEGORI
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM news WHERE category_id = c.id) as news_count
          FROM categories c 
          ORDER BY 
            CASE WHEN (SELECT COUNT(*) FROM news WHERE category_id = c.id) = 0 THEN 1 ELSE 0 END,
            news_count DESC,
            c.name ASC";
$result = mysqli_query($conn, $query);

// Set page variables
$page_title = 'Kelola Kategori';
$page_heading = 'Kelola Kategori';
$breadcrumbs = [
    'Dashboard' => 'dashboard.php',
    'Kelola Kategori' => null
];

// Include header
include 'includes/header.php';
?>

<!-- ACTION BUTTONS -->
<div class="mb-3">
    <a href="kategori-add.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Tambah Kategori Baru
    </a>
</div>

<!-- KATEGORI TABLE -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Daftar Kategori</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="categoryTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Icon</th>
                                <th>Nama Kategori</th>
                                <th>Slug</th>
                                <th>Deskripsi</th>
                                <th>Jumlah Berita</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($cat = mysqli_fetch_assoc($result)): ?>
                                <tr class="<?php echo ($cat['news_count'] == 0) ? 'text-muted' : ''; ?>">
                                    <td>
                                        <div class="category-color-swatch" style="background: <?php echo htmlspecialchars($cat['color']); ?>; opacity: <?php echo ($cat['news_count'] == 0) ? '0.5' : '1'; ?>;">
                                            <i class="<?php echo htmlspecialchars($cat['icon']); ?> text-white fa-lg"></i>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($cat['name']); ?></strong>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($cat['slug']); ?></code>
                                    </td>
                                    <td>
                                        <?php 
                                        $desc = htmlspecialchars($cat['description']); 
                                        echo (strlen($desc) > 50) ? substr($desc, 0, 50) . '...' : $desc;
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($cat['news_count'] > 0): ?>
                                            <span class="badge bg-info"><?php echo $cat['news_count']; ?> berita</span>
                                        <?php else: ?>
                                            <span class="text-muted">0 berita</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cat['is_active']): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle"></i> Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-times-circle"></i> Nonaktif
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="kategori-add.php?id=<?php echo $cat['id']; ?>" 
                                               class="btn btn-outline-primary"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" action="" class="d-inline">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                <input type="hidden" name="target_status" value="<?php echo $cat['is_active'] ? '0' : '1'; ?>">
                                                <button type="submit" 
                                                        class="btn btn-outline-<?php echo $cat['is_active'] ? 'warning' : 'success'; ?>"
                                                        title="<?php echo $cat['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>"
                                                        onclick="return confirm('Yakin ingin mengubah status kategori ini?')">
                                                    <i class="fas fa-<?php echo $cat['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="" class="d-inline delete-form">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                <button type="submit" 
                                                        class="btn btn-outline-danger"
                                                        title="Hapus"
                                                        onclick="return confirm('Yakin ingin menghapus kategori ini? Kategori yang masih memiliki berita tidak bisa dihapus.')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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
        $('#categoryTable').DataTable({
            pageLength: 25,
            order: [[4, 'desc']], // Sort by jumlah berita (descending)
            ordering: false, // Disable client-side sorting (use server-side ORDER BY)
            language: {
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ kategori per halaman",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ kategori",
                infoEmpty: "Tidak ada kategori",
                infoFiltered: "(difilter dari _MAX_ total kategori)",
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Selanjutnya",
                    previous: "Sebelumnya"
                },
                zeroRecords: "Kategori tidak ditemukan"
            }
        });
    });
</script>
