<?php
/**
 * Admin: Edit Berita
 */

// Include config dan auth
require_once '../config/config.php';
require_once 'auth.php';
require_once 'upload-handler.php';

// Cek login
requireLogin();

// Ambil koneksi database
$conn = getDBConnection();

// Get logged in user
$logged_in_user = getLoggedInUser();

// Ambil ID berita dari URL
$news_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($news_id === 0) {
    setFlashMessage('error', 'ID Berita tidak valid!');
    header('Location: berita-list.php');
    exit;
}

// AMBIL DATA BERITA EXISTING
$news_query = "SELECT n.*, c.name as category_name 
               FROM news n
               JOIN categories c ON n.category_id = c.id
               WHERE n.id = ? LIMIT 1";
$stmt_news = mysqli_prepare($conn, $news_query);
mysqli_stmt_bind_param($stmt_news, 'i', $news_id);
mysqli_stmt_execute($stmt_news);
$news_result = mysqli_stmt_get_result($stmt_news);

if (mysqli_num_rows($news_result) === 0) {
    setFlashMessage('error', 'Berita tidak ditemukan!');
    header('Location: berita-list.php');
    exit;
}

$news = mysqli_fetch_assoc($news_result);

// Variabel error
$errors = [];

// Proses form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    requireCSRF();
    
    // Ambil data dari form
    $title = sanitizeInput($_POST['title'] ?? '');
    $slug = !empty($_POST['slug']) ? sanitizeInput($_POST['slug']) : generateSlug($title);
    $excerpt = sanitizeInput($_POST['excerpt'] ?? '');
    $content = $_POST['content'] ?? ''; // Tidak sanitize karena HTML content
    $category_id = (int)($_POST['category_id'] ?? 0);
    $tags = sanitizeInput($_POST['tags'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'draft');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $video_type = sanitizeInput($_POST['video_type'] ?? 'none');
    $video_url = $news['video_url']; // Default: keep existing
    $thumbnail_path = $news['thumbnail']; // Default: keep existing
    
    // Validasi input
    if (empty($title)) $errors[] = 'Judul berita harus diisi!';
    if (empty($content)) $errors[] = 'Konten berita harus diisi!';
    if ($category_id === 0) $errors[] = 'Kategori harus dipilih!';
    
    // Cek slug duplikat (kecuali slug sendiri)
    if (!empty($slug)) {
        $check_slug = "SELECT id FROM news WHERE slug = ? AND id != ? LIMIT 1";
        $stmt_check = mysqli_prepare($conn, $check_slug);
        mysqli_stmt_bind_param($stmt_check, 'si', $slug, $news_id);
        mysqli_stmt_execute($stmt_check);
        if (mysqli_stmt_get_result($stmt_check)->num_rows > 0) {
            $errors[] = 'Slug sudah digunakan! Silakan gunakan slug lain.';
        }
    }
    
    // Jika tidak ada error, lanjutkan
    if (empty($errors)) {
        // Upload thumbnail baru jika ada
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = uploadImage($_FILES['thumbnail']);
            if ($upload_result['status']) {
                // Hapus thumbnail lama
                if (!empty($news['thumbnail']) && file_exists('../' . $news['thumbnail'])) {
                    deleteFile('../' . $news['thumbnail']);
                }
                $thumbnail_path = $upload_result['filename'];
            } else {
                $errors[] = 'Upload Thumbnail: ' . $upload_result['message'];
            }
        }
        
        // Handle video
        if ($video_type === 'youtube') {
            // Hapus video file lama jika ada
            if (!empty($news['video_url']) && !filter_var($news['video_url'], FILTER_VALIDATE_URL)) {
                if (file_exists('../' . $news['video_url'])) {
                    deleteFile('../' . $news['video_url']);
                }
            }
            $video_url = sanitizeInput($_POST['youtube_url'] ?? '');
        } elseif ($video_type === 'upload') {
            if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $video_result = uploadVideo($_FILES['video_file']);
                if ($video_result['status']) {
                    // Hapus video file lama
                    if (!empty($news['video_url']) && !filter_var($news['video_url'], FILTER_VALIDATE_URL)) {
                        if (file_exists('../' . $news['video_url'])) {
                            deleteFile('../' . $news['video_url']);
                        }
                    }
                    $video_url = $video_result['filename'];
                } else {
                    $errors[] = 'Upload Video: ' . $video_result['message'];
                }
            }
        } elseif ($video_type === 'none') {
            // Hapus video jika user pilih "Tidak Ada Video"
            if (!empty($news['video_url']) && !filter_var($news['video_url'], FILTER_VALIDATE_URL)) {
                if (file_exists('../' . $news['video_url'])) {
                    deleteFile('../' . $news['video_url']);
                }
            }
            $video_url = '';
        }
        
        // Jika masih tidak ada error, update ke database
        if (empty($errors)) {
            $update_query = "UPDATE news SET 
                             title = ?, 
                             slug = ?, 
                             excerpt = ?, 
                             content = ?, 
                             category_id = ?, 
                             thumbnail = ?, 
                             video_url = ?, 
                             tags = ?, 
                             status = ?, 
                             is_featured = ?,
                             updated_at = NOW()
                             WHERE id = ?";
            
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, 'ssssississi', 
                $title, $slug, $excerpt, $content, $category_id, 
                $thumbnail_path, $video_url, $tags, $status, $is_featured, $news_id
            );
            
            if (mysqli_stmt_execute($stmt)) {
                setFlashMessage('success', 'Berita berhasil diupdate!');
                header('Location: berita-list.php');
                exit;
            } else {
                error_log('Gagal update berita: ' . mysqli_error($conn));
                $errors[] = 'Gagal update berita ke database. Silakan coba lagi.';
            }
        }
    }
    
    // Jika ada error, reload data news (karena mungkin sudah berubah di POST)
    $news['title'] = $title;
    $news['slug'] = $slug;
    $news['excerpt'] = $excerpt;
    $news['content'] = $content;
    $news['category_id'] = $category_id;
    $news['tags'] = $tags;
    $news['status'] = $status;
    $news['is_featured'] = $is_featured;
}

// Ambil semua kategori untuk dropdown
$category_query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC";
$category_result = mysqli_query($conn, $category_query);

// Set page variables
$page_title = 'Edit Berita';
$page_heading = 'Edit Berita: ' . htmlspecialchars($news['title']);
$breadcrumbs = [
    'Dashboard' => 'dashboard.php',
    'Kelola Berita' => 'berita-list.php',
    'Edit Berita' => null
];

// Include header
include 'includes/header.php';
?>

<!-- ALERT ERROR -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <h5><i class="fas fa-exclamation-triangle"></i> Error!</h5>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <?php echo csrfField(); ?>
    <div class="row g-4">
        <!-- MAIN CONTENT -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-edit"></i> Informasi Berita</h5>
                </div>
                <div class="card-body">
                    <!-- Judul -->
                    <div class="mb-3">
                        <label for="title" class="form-label">Judul Berita <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="title" 
                               name="title" 
                               value="<?php echo htmlspecialchars($news['title']); ?>"
                               required>
                        <small class="text-muted">Judul akan muncul sebagai headline berita</small>
                    </div>
                    
                    <!-- Slug (URL) -->
                    <div class="mb-3">
                        <label for="slug" class="form-label">Slug (URL)</label>
                        <input type="text" 
                               class="form-control" 
                               id="slug" 
                               name="slug" 
                               value="<?php echo htmlspecialchars($news['slug']); ?>">
                        <small class="text-muted">Biarkan kosong untuk generate otomatis dari judul</small>
                    </div>
                    
                    <!-- Ringkasan/Excerpt -->
                    <div class="mb-3">
                        <label for="excerpt" class="form-label">Ringkasan/Excerpt</label>
                        <textarea class="form-control" 
                                  id="excerpt" 
                                  name="excerpt" 
                                  rows="3" 
                                  maxlength="200"><?php echo htmlspecialchars($news['excerpt']); ?></textarea>
                        <small class="text-muted">Ringkasan singkat yang tampil di halaman utama (max 200 karakter)</small>
                    </div>
                    
                    <!-- Konten Berita -->
                    <div class="mb-3">
                        <label for="content" class="form-label">Konten Berita <span class="text-danger">*</span></label>
                        <textarea id="content" name="content" required><?php echo htmlspecialchars($news['content']); ?></textarea>
                    </div>
                    
                    <!-- Tags -->
                    <div class="mb-3">
                        <label for="tags" class="form-label">Tags</label>
                        <input type="text" 
                               class="form-control" 
                               id="tags" 
                               name="tags" 
                               value="<?php echo htmlspecialchars($news['tags']); ?>"
                               placeholder="Pisahkan dengan koma: manado, sulut, berita">
                        <small class="text-muted">Untuk SEO dan pencarian (pisahkan dengan koma)</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SIDEBAR -->
        <div class="col-lg-4">
            <!-- Publish Box -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5><i class="fas fa-paper-plane"></i> Publikasi</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="draft" <?php echo ($news['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo ($news['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1"
                                   <?php echo ($news['is_featured']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_featured">
                                <i class="fas fa-star text-warning"></i> Jadikan Berita Featured
                                <small class="d-block text-muted">Akan tampil di carousel beranda</small>
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Berita
                        </button>
                        <a href="berita-list.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Kategori -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5><i class="fas fa-tag"></i> Kategori</h5>
                </div>
                <div class="card-body">
                    <select class="form-select" name="category_id" required>
                        <option value="">Pilih Kategori</option>
                        <?php while ($cat = mysqli_fetch_assoc($category_result)): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo ($news['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <!-- Gambar Featured -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5><i class="fas fa-image"></i> Gambar Featured</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($news['thumbnail'])): ?>
                        <div class="mb-2">
                            <img src="<?php echo SITE_URL . htmlspecialchars($news['thumbnail']); ?>" 
                                 alt="Current thumbnail" 
                                 class="img-fluid rounded"
                                 id="currentThumbnail">
                            <small class="d-block text-muted mt-1">Gambar saat ini</small>
                        </div>
                    <?php endif; ?>
                    
                    <input type="file" 
                           class="form-control" 
                           name="thumbnail" 
                           accept="image/*"
                           id="thumbnailInput">
                    <small class="text-muted">JPG, PNG, WEBP (Max 5MB). Biarkan kosong jika tidak ingin mengubah.</small>
                    
                    <!-- Preview thumbnail baru -->
                    <img id="thumbnailPreview" style="display: none; margin-top: 10px;" class="img-fluid rounded">
                </div>
            </div>
            
            <!-- Video (Opsional) -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-video"></i> Video (Opsional)</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="video_type" class="form-label">Tipe Video</label>
                        <select class="form-select" id="video_type" name="video_type">
                            <option value="none" <?php echo (empty($news['video_url'])) ? 'selected' : ''; ?>>Tidak Ada Video</option>
                            <option value="youtube" <?php echo (!empty($news['video_url']) && filter_var($news['video_url'], FILTER_VALIDATE_URL)) ? 'selected' : ''; ?>>YouTube URL</option>
                            <option value="upload" <?php echo (!empty($news['video_url']) && !filter_var($news['video_url'], FILTER_VALIDATE_URL)) ? 'selected' : ''; ?>>Upload Video</option>
                        </select>
                    </div>
                    
                    <!-- YouTube URL -->
                    <div id="youtubeUrlField" style="display: none;">
                        <label for="youtube_url" class="form-label">YouTube URL</label>
                        <input type="url" 
                               class="form-control" 
                               name="youtube_url" 
                               placeholder="https://www.youtube.com/watch?v=..."
                               value="<?php echo (filter_var($news['video_url'] ?? '', FILTER_VALIDATE_URL)) ? htmlspecialchars($news['video_url']) : ''; ?>">
                        <small class="text-muted">Paste link YouTube video</small>
                    </div>
                    
                    <!-- Upload Video -->
                    <div id="uploadVideoField" style="display: none;">
                        <?php if (!empty($news['video_url']) && !filter_var($news['video_url'], FILTER_VALIDATE_URL)): ?>
                            <div class="mb-2">
                                <small class="text-success">
                                    <i class="fas fa-check-circle"></i> Video saat ini: <?php echo basename($news['video_url']); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" name="video_file" accept="video/*">
                        <small class="text-muted">MP4, MOV, AVI (Max 50MB). Biarkan kosong jika tidak ingin mengubah.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>

<!-- TinyMCE Rich Text Editor -->
<script src="https://cdn.tiny.cloud/1/xu810mst0bl37w7aaat1y6kyctgfyhdoj4hfu6uc753ylqlz/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: '#content',
        height: 500,
        menubar: true,
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
        toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | code fullscreen',
        content_style: 'body { font-family: Roboto, Arial, sans-serif; font-size: 14px; }'
    });
    
    // Auto-generate slug dari title
    document.getElementById('title').addEventListener('input', function() {
        const slug = document.getElementById('slug');
        if (slug.value === '' || slug.dataset.wasEmpty) {
            slug.value = this.value
                .toLowerCase()
                .trim()
                .replace(/[^\w\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/--+/g, '-');
            slug.dataset.wasEmpty = 'true';
        }
    });
    
    document.getElementById('slug').addEventListener('input', function() {
        if (this.value !== '') {
            delete this.dataset.wasEmpty;
        }
    });
    
    // Thumbnail preview
    document.getElementById('thumbnailInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('thumbnailPreview');
                preview.src = e.target.result;
                preview.style.display = 'block';
                
                // Hide current thumbnail
                const current = document.getElementById('currentThumbnail');
                if (current) current.style.opacity = '0.5';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Video type toggle
    const videoType = document.getElementById('video_type');
    const youtubeField = document.getElementById('youtubeUrlField');
    const uploadField = document.getElementById('uploadVideoField');
    
    function toggleVideoFields() {
        const selected = videoType.value;
        youtubeField.style.display = (selected === 'youtube') ? 'block' : 'none';
        uploadField.style.display = (selected === 'upload') ? 'block' : 'none';
    }
    
    videoType.addEventListener('change', toggleVideoFields);
    toggleVideoFields(); // Initial call
</script>
