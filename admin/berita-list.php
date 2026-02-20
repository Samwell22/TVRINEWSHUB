<?php
/**
 * Admin: Daftar Berita
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

// Handle delete action (POST only with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    requireCSRF();
    $delete_id = (int)$_POST['id'];
    
    // Ambil data berita untuk hapus file jika ada
    $check_query = "SELECT thumbnail, video_url FROM news WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, 'i', $delete_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        $news_data = mysqli_fetch_assoc($check_result);
        
        // Hapus dari database
        $delete_query = "DELETE FROM news WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($delete_stmt, 'i', $delete_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            // Hapus file thumbnail jika ada
            if (!empty($news_data['thumbnail'])) {
                $file_path = '../' . $news_data['thumbnail'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            // Hapus file video jika ada dan bukan URL YouTube
            if (!empty($news_data['video_url']) && strpos($news_data['video_url'], 'youtube') === false) {
                $video_path = '../' . $news_data['video_url'];
                if (file_exists($video_path)) {
                    unlink($video_path);
                }
            }
            
            setFlashMessage('success', 'Berita berhasil dihapus!');
        } else {
            setFlashMessage('error', 'Gagal menghapus berita!');
        }
    }
    
    header('Location: berita-list.php');
    exit;
}

// Set page variables
$page_title = 'Kelola Berita';
$page_heading = 'Kelola Berita';
$breadcrumbs = [
    'Dashboard' => 'dashboard.php',
    'Kelola Berita' => null
];

// Query untuk ambil semua berita
$news_query = "SELECT n.*, c.name as category_name, u.full_name as author_name
               FROM news n
               INNER JOIN categories c ON n.category_id = c.id
               INNER JOIN users u ON n.author_id = u.id
               ORDER BY n.created_at DESC";
$news_result = mysqli_query($conn, $news_query);

// Include header
include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-newspaper"></i> Daftar Berita</h5>
                <a href="berita-add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Berita Baru
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Thumbnail</th>
                                <th>Judul</th>
                                <th>Kategori</th>
                                <th>Penulis</th>
                                <th>Status</th>
                                <th>Views</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($news = mysqli_fetch_assoc($news_result)): ?>
                                <tr>
                                    <td><?php echo $news['id']; ?></td>
                                    <td>
                                        <?php if (!empty($news['thumbnail'])): ?>
                                            <img src="<?php echo '../' . htmlspecialchars($news['thumbnail']); ?>" 
                                                 alt="Thumbnail" 
                                                 class="thumbnail-img">
                                        <?php else: ?>
                                            <div class="thumbnail-placeholder">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($news['title']); ?></strong>
                                        <?php if (!empty($news['video_url'])): ?>
                                            <br><small class="text-muted"><i class="fas fa-video"></i> Ada video</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($news['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($news['author_name']); ?></td>
                                    <td>
                                        <?php if ($news['status'] === 'published'): ?>
                                            <span class="badge bg-success">Published</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($news['views']); ?></td>
                                    <td>
                                        <small><?php echo date('d/m/Y', strtotime($news['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($news['status'] === 'published'): ?>
                                            <a href="../berita.php?slug=<?php echo $news['slug']; ?>" 
                                               class="btn btn-outline-secondary" 
                                               target="_blank"
                                               title="Lihat">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php endif; ?>
                                            <a href="berita-edit.php?id=<?php echo $news['id']; ?>" 
                                               class="btn btn-outline-primary"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" action="" class="d-inline delete-form">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $news['id']; ?>">
                                                <button type="submit" 
                                                        class="btn btn-outline-danger"
                                                        title="Hapus"
                                                        onclick="return confirm('Yakin ingin menghapus berita ini?')">
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

<?php
// Include footer
include 'includes/footer.php';

// Tutup koneksi
mysqli_close($conn);
?>
