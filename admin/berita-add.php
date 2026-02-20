<?php
/**
 * Admin: Tambah Berita
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
    $video_url = '';
    
    // Validasi input
    if (empty($title)) $errors[] = 'Judul berita harus diisi!';
    if (empty($content)) $errors[] = 'Konten berita harus diisi!';
    if ($category_id === 0) $errors[] = 'Kategori harus dipilih!';
    
    // Cek slug duplikat
    if (!empty($slug)) {
        $check_slug = "SELECT id FROM news WHERE slug = ? LIMIT 1";
        $stmt_check = mysqli_prepare($conn, $check_slug);
        mysqli_stmt_bind_param($stmt_check, 's', $slug);
        mysqli_stmt_execute($stmt_check);
        if (mysqli_stmt_get_result($stmt_check)->num_rows > 0) {
            $errors[] = 'Slug sudah digunakan! Silakan gunakan slug lain.';
        }
    }
    
    // Jika tidak ada error, lanjutkan
    if (empty($errors)) {
        $thumbnail_path = '';
        
        // Upload thumbnail jika ada
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = uploadImage($_FILES['thumbnail']);
            if ($upload_result['status']) {
                $thumbnail_path = $upload_result['filename'];
            } else {
                $errors[] = 'Upload Thumbnail: ' . $upload_result['message'];
            }
        }
        
        // Handle video
        if ($video_type === 'youtube') {
            $video_url = sanitizeInput($_POST['youtube_url'] ?? '');
        } elseif ($video_type === 'upload') {
            if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $video_result = uploadVideo($_FILES['video_file']);
                if ($video_result['status']) {
                    $video_url = $video_result['filename'];
                } else {
                    $errors[] = 'Upload Video: ' . $video_result['message'];
                }
            }
        }
        
        // Jika masih tidak ada error, insert ke database
        if (empty($errors)) {
            $insert_query = "INSERT INTO news (title, slug, excerpt, content, category_id, author_id, thumbnail, video_url, tags, status, is_featured, published_at, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, 'ssssiissssi', 
                $title, $slug, $excerpt, $content, $category_id, 
                $logged_in_user['id'], $thumbnail_path, $video_url, $tags, $status, $is_featured
            );
            
            if (mysqli_stmt_execute($stmt)) {
                setFlashMessage('success', 'Berita berhasil ditambahkan!');
                header('Location: berita-list.php');
                exit;
            } else {
                $errors[] = 'Gagal menyimpan berita ke database!';
            }
        }
    }
}

// Ambil semua kategori untuk dropdown
$category_query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC";
$category_result = mysqli_query($conn, $category_query);

// Set page variables
$page_title = 'Tambah Berita';
$page_heading = 'Tambah Berita Baru';
$breadcrumbs = [
    'Dashboard' => 'dashboard.php',
    'Kelola Berita' => 'berita-list.php',
    'Tambah Berita' => null
];

// Include header
include 'includes/header.php';
?>

<!-- TinyMCE Rich Text Editor -->
<script src="https://cdn.tiny.cloud/1/xu810mst0bl37w7aaat1y6kyctgfyhdoj4hfu6uc753ylqlz/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: '#content',
        height: 500,
        menubar: true,
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
        toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | code fullscreen',
        content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }'
    });
</script>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <strong><i class="fas fa-exclamation-triangle"></i> Error:</strong>
        <ul class="mb-0 mt-2">
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
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                               required
                               onkeyup="generateSlug()">
                        <small class="text-muted">Judul akan muncul sebagai headline berita</small>
                    </div>
                    
                    <!-- Slug -->
                    <div class="mb-3">
                        <label for="slug" class="form-label">Slug (URL)</label>
                        <input type="text" 
                               class="form-control" 
                               id="slug" 
                               name="slug" 
                               value="<?php echo isset($_POST['slug']) ? htmlspecialchars($_POST['slug']) : ''; ?>">
                        <small class="text-muted">Biarkan kosong untuk generate otomatis dari judul</small>
                    </div>
                    
                    <!-- Excerpt -->
                    <div class="mb-3">
                        <label for="excerpt" class="form-label">Ringkasan/Excerpt</label>
                        <textarea class="form-control" 
                                  id="excerpt" 
                                  name="excerpt" 
                                  rows="3"><?php echo isset($_POST['excerpt']) ? htmlspecialchars($_POST['excerpt']) : ''; ?></textarea>
                        <small class="text-muted">Ringkasan singkat yang tampil di halaman utama (max 200 karakter)</small>
                    </div>
                    
                    <!-- Content -->
                    <div class="mb-3">
                        <label for="content" class="form-label">Konten Berita <span class="text-danger">*</span></label>
                        <textarea id="content" name="content"><?php echo isset($_POST['content']) ? $_POST['content'] : ''; ?></textarea>
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
                            <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo (isset($_POST['status']) && $_POST['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1"
                                   <?php echo (isset($_POST['is_featured'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_featured">
                                <i class="fas fa-star text-warning"></i> Jadikan Berita Featured
                                <small class="d-block text-muted">Akan tampil di carousel beranda</small>
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Berita
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
                                    <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <!-- Thumbnail -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5><i class="fas fa-image"></i> Gambar Featured</h5>
                </div>
                <div class="card-body">
                    <input type="file" class="form-control" name="thumbnail" accept="image/*" onchange="previewImage(this)">
                    <small class="text-muted d-block mt-1">JPG, PNG, WEBP (Max 5MB)</small>
                    <div id="image-preview" class="mt-3" style="display: none;">
                        <img src="" alt="Preview" style="width: 100%; border-radius: 8px;">
                    </div>
                </div>
            </div>
            
            <!-- Video -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5><i class="fas fa-video"></i> Video (Opsional)</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Tipe Video</label>
                        <select class="form-select" name="video_type" id="video_type" onchange="toggleVideoInput()">
                            <option value="none">Tidak Ada Video</option>
                            <option value="youtube">YouTube URL</option>
                            <option value="upload">Upload Video File</option>
                        </select>
                    </div>
                    
                    <div id="youtube_input" style="display: none;">
                        <label class="form-label">YouTube URL</label>
                        <input type="url" class="form-control" name="youtube_url" placeholder="https://www.youtube.com/watch?v=...">
                        <small class="text-muted">Paste link YouTube video</small>
                    </div>
                    
                    <div id="upload_input" style="display: none;">
                        <label class="form-label">Upload Video</label>
                        <input type="file" class="form-control" name="video_file" accept="video/*">
                        <small class="text-muted">MP4, MOV, AVI (Max 50MB)</small>
                    </div>
                </div>
            </div>
            
            <!-- Tags -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-tags"></i> Tags</h5>
                </div>
                <div class="card-body">
                    <input type="text" 
                           class="form-control" 
                           name="tags" 
                           value="<?php echo isset($_POST['tags']) ? htmlspecialchars($_POST['tags']) : ''; ?>"
                           placeholder="Pisahkan dengan koma">
                    <small class="text-muted">Contoh: politik, sulut, pemilu</small>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Generate slug from title
function generateSlug() {
    var title = document.getElementById('title').value;
    var slug = title.toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    document.getElementById('slug').value = slug;
}

// Toggle video input
function toggleVideoInput() {
    var videoType = document.getElementById('video_type').value;
    document.getElementById('youtube_input').style.display = (videoType === 'youtube') ? 'block' : 'none';
    document.getElementById('upload_input').style.display = (videoType === 'upload') ? 'block' : 'none';
}

// Preview image
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.querySelector('#image-preview img').src = e.target.result;
            document.getElementById('image-preview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php
// Include footer
include 'includes/footer.php';

// Tutup koneksi
mysqli_close($conn);
?>
