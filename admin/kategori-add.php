<?php
/**
 * Admin: Form Tambah/Edit Kategori
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

// Cek apakah ini mode EDIT atau ADD
$is_edit = isset($_GET['id']) && (int)$_GET['id'] > 0;
$category_id = $is_edit ? (int)$_GET['id'] : 0;

// Default values untuk form
$category = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'icon' => 'fas fa-folder',
    'color' => '#3b82f6',
    'is_active' => 1
];

// Jika mode EDIT, ambil data existing
if ($is_edit) {
    $query = "SELECT * FROM categories WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $category_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        setFlashMessage('error', 'Kategori tidak ditemukan!');
        header('Location: kategori-list.php');
        exit;
    }
    
    $category = mysqli_fetch_assoc($result);
}

// Variabel error
$errors = [];

// Proses form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    requireCSRF();
    
    // Ambil data dari form
    $name = sanitizeInput($_POST['name'] ?? '');
    $slug = !empty($_POST['slug']) ? sanitizeInput($_POST['slug']) : generateSlug($name);
    $description = sanitizeInput($_POST['description'] ?? '');
    $icon = sanitizeInput($_POST['icon'] ?? 'fas fa-folder');
    $color = sanitizeInput($_POST['color'] ?? '#3b82f6');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validasi input
    if (empty($name)) {
        $errors[] = 'Nama kategori harus diisi!';
    }
    
    if (empty($slug)) {
        $errors[] = 'Slug harus diisi!';
    }
    
    // Validasi format color hex
    if (!preg_match('/^#[0-9A-F]{6}$/i', $color)) {
        $errors[] = 'Format warna tidak valid! Harus format hex (#rrggbb)';
    }
    
    // Cek slug duplikat
    if (!empty($slug)) {
        if ($is_edit) {
            $check_slug = "SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1";
            $stmt_check = mysqli_prepare($conn, $check_slug);
            mysqli_stmt_bind_param($stmt_check, 'si', $slug, $category_id);
        } else {
            $check_slug = "SELECT id FROM categories WHERE slug = ? LIMIT 1";
            $stmt_check = mysqli_prepare($conn, $check_slug);
            mysqli_stmt_bind_param($stmt_check, 's', $slug);
        }
        
        mysqli_stmt_execute($stmt_check);
        if (mysqli_stmt_get_result($stmt_check)->num_rows > 0) {
            $errors[] = 'Slug sudah digunakan! Silakan gunakan slug lain.';
        }
    }
    
    // Jika tidak ada error, proses simpan
    if (empty($errors)) {
        if ($is_edit) {
            // UPDATE
            $update_query = "UPDATE categories SET 
                             name = ?, 
                             slug = ?, 
                             description = ?, 
                             icon = ?, 
                             color = ?, 
                             is_active = ?
                             WHERE id = ?";
            
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, 'sssssii', $name, $slug, $description, $icon, $color, $is_active, $category_id);
            
            if (mysqli_stmt_execute($stmt)) {
                setFlashMessage('success', 'Kategori berhasil diupdate!');
                header('Location: kategori-list.php');
                exit;
            } else {
                error_log('Gagal update kategori: ' . mysqli_error($conn));
                $errors[] = 'Gagal update kategori. Silakan coba lagi.';
            }
        } else {
            // INSERT
            $insert_query = "INSERT INTO categories (name, slug, description, icon, color, is_active) 
                             VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, 'sssssi', $name, $slug, $description, $icon, $color, $is_active);
            
            if (mysqli_stmt_execute($stmt)) {
                setFlashMessage('success', 'Kategori berhasil ditambahkan!');
                header('Location: kategori-list.php');
                exit;
            } else {
                error_log('Gagal menambahkan kategori: ' . mysqli_error($conn));
                $errors[] = 'Gagal menambahkan kategori. Silakan coba lagi.';
            }
        }
    }
    
    // Jika ada error, simpan input untuk ditampilkan ulang
    if (!empty($errors)) {
        $category = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'icon' => $icon,
            'color' => $color,
            'is_active' => $is_active
        ];
    }
}

// Set page variables
$page_title = $is_edit ? 'Edit Kategori' : 'Tambah Kategori';
$page_heading = $is_edit ? 'Edit Kategori: ' . htmlspecialchars($category['name']) : 'Tambah Kategori Baru';
$breadcrumbs = [
    'Dashboard' => 'dashboard.php',
    'Kelola Kategori' => 'kategori-list.php',
    $page_title => null
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

<form method="POST" action="">
    <?php echo csrfField(); ?>
    <div class="row g-4">
        <!-- MAIN CONTENT -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-folder"></i> Informasi Kategori</h5>
                </div>
                <div class="card-body">
                    <!-- Nama Kategori -->
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="name" 
                               name="name" 
                               value="<?php echo htmlspecialchars($category['name']); ?>"
                               required>
                        <small class="text-muted">Contoh: Politik, Ekonomi, Olahraga, Budaya</small>
                    </div>
                    
                    <!-- Slug (URL) -->
                    <div class="mb-3">
                        <label for="slug" class="form-label">Slug (URL)</label>
                        <input type="text" 
                               class="form-control" 
                               id="slug" 
                               name="slug" 
                               value="<?php echo htmlspecialchars($category['slug']); ?>">
                        <small class="text-muted">Biarkan kosong untuk generate otomatis dari nama kategori</small>
                    </div>
                    
                    <!-- Deskripsi -->
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3"><?php echo htmlspecialchars($category['description']); ?></textarea>
                        <small class="text-muted">Deskripsi singkat tentang kategori ini (opsional)</small>
                    </div>
                    
                    <!-- Icon -->
                    <div class="mb-3">
                        <label for="icon" class="form-label">Icon (FontAwesome)</label>
                        <select class="form-select mb-2" id="iconPreset" aria-label="Pilih icon cepat">
                            <option value="">Custom (manual)</option>
                            <option value="fas fa-folder" <?php echo $category['icon'] === 'fas fa-folder' ? 'selected' : ''; ?>>Folder</option>
                            <option value="fas fa-newspaper" <?php echo $category['icon'] === 'fas fa-newspaper' ? 'selected' : ''; ?>>Berita</option>
                            <option value="fas fa-bullhorn" <?php echo $category['icon'] === 'fas fa-bullhorn' ? 'selected' : ''; ?>>Pengumuman</option>
                            <option value="fas fa-tv" <?php echo $category['icon'] === 'fas fa-tv' ? 'selected' : ''; ?>>TV</option>
                            <option value="fas fa-video" <?php echo $category['icon'] === 'fas fa-video' ? 'selected' : ''; ?>>Video</option>
                            <option value="fas fa-microphone" <?php echo $category['icon'] === 'fas fa-microphone' ? 'selected' : ''; ?>>Radio</option>
                            <option value="fas fa-futbol" <?php echo $category['icon'] === 'fas fa-futbol' ? 'selected' : ''; ?>>Olahraga</option>
                            <option value="fas fa-briefcase" <?php echo $category['icon'] === 'fas fa-briefcase' ? 'selected' : ''; ?>>Bisnis</option>
                            <option value="fas fa-building" <?php echo $category['icon'] === 'fas fa-building' ? 'selected' : ''; ?>>Daerah</option>
                            <option value="fas fa-globe" <?php echo $category['icon'] === 'fas fa-globe' ? 'selected' : ''; ?>>Nasional</option>
                            <option value="fas fa-landmark" <?php echo $category['icon'] === 'fas fa-landmark' ? 'selected' : ''; ?>>Pemerintahan</option>
                            <option value="fas fa-flag" <?php echo $category['icon'] === 'fas fa-flag' ? 'selected' : ''; ?>>Kebijakan</option>
                            <option value="fas fa-bolt" <?php echo $category['icon'] === 'fas fa-bolt' ? 'selected' : ''; ?>>Terkini</option>
                            <option value="fas fa-leaf" <?php echo $category['icon'] === 'fas fa-leaf' ? 'selected' : ''; ?>>Lingkungan</option>
                            <option value="fas fa-heart" <?php echo $category['icon'] === 'fas fa-heart' ? 'selected' : ''; ?>>Sosial</option>
                            <option value="fas fa-star" <?php echo $category['icon'] === 'fas fa-star' ? 'selected' : ''; ?>>Pilihan</option>
                            <option value="fas fa-camera" <?php echo $category['icon'] === 'fas fa-camera' ? 'selected' : ''; ?>>Foto</option>
                            <option value="fas fa-music" <?php echo $category['icon'] === 'fas fa-music' ? 'selected' : ''; ?>>Hiburan</option>
                            <option value="fas fa-gavel" <?php echo $category['icon'] === 'fas fa-gavel' ? 'selected' : ''; ?>>Hukum</option>
                            <option value="fas fa-user-tie" <?php echo $category['icon'] === 'fas fa-user-tie' ? 'selected' : ''; ?>>Tokoh</option>
                            <option value="fas fa-chart-line" <?php echo $category['icon'] === 'fas fa-chart-line' ? 'selected' : ''; ?>>Ekonomi</option>
                        </select>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i id="iconPreview" class="<?php echo htmlspecialchars($category['icon']); ?>"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="icon" 
                                   name="icon" 
                                   value="<?php echo htmlspecialchars($category['icon']); ?>"
                                   placeholder="fas fa-folder">
                        </div>
                        <small class="text-muted">
                            Pilih cepat di atas, atau isi manual jika ingin icon lain. 
                            <a href="https://fontawesome.com/search?o=r&m=free" target="_blank">
                                <i class="fas fa-external-link-alt"></i> Browse Icons
                            </a>
                        </small>
                    </div>
                    
                    <!-- Color -->
                    <div class="mb-3">
                        <label for="color" class="form-label">Warna Badge</label>
                        <div class="input-group" style="max-width: 300px;">
                            <input type="color" 
                                   class="form-control form-control-color" 
                                   id="colorPicker" 
                                   value="<?php echo htmlspecialchars($category['color']); ?>"
                                   title="Pilih warna">
                            <input type="text" 
                                   class="form-control" 
                                   id="color" 
                                   name="color" 
                                   value="<?php echo htmlspecialchars($category['color']); ?>"
                                   pattern="^#[0-9A-Fa-f]{6}$"
                                   placeholder="#3b82f6">
                        </div>
                        <small class="text-muted">Warna untuk badge dan icon kategori (format hex)</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SIDEBAR -->
        <div class="col-lg-4">
            <!-- Publish Box -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5><i class="fas fa-cog"></i> Pengaturan</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   id="is_active" 
                                   name="is_active" 
                                   value="1"
                                   <?php echo ($category['is_active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">
                                <i class="fas fa-eye text-success"></i> Kategori Aktif
                                <small class="d-block text-muted">Kategori akan tampil di menu dan bisa digunakan</small>
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $is_edit ? 'Update' : 'Simpan'; ?> Kategori
                        </button>
                        <a href="kategori-list.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Preview Box -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-eye"></i> Preview</h5>
                </div>
                <div class="card-body text-center">
                    <div id="badgePreview" 
                         style="display: inline-block; padding: 8px 16px; border-radius: 20px; 
                                background: <?php echo htmlspecialchars($category['color']); ?>; color: white; font-size: 14px;">
                        <i id="badgeIcon" class="<?php echo htmlspecialchars($category['icon']); ?>"></i>
                        <span id="badgeName"><?php echo htmlspecialchars($category['name']) ?: 'Nama Kategori'; ?></span>
                    </div>
                    <p class="text-muted mt-3 mb-0">
                        <small>Tampilan badge di halaman berita</small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>

<script>
    // Auto-generate slug dari nama
    document.getElementById('name').addEventListener('input', function() {
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
        
        // Update badge preview name
        document.getElementById('badgeName').textContent = this.value || 'Nama Kategori';
    });
    
    document.getElementById('slug').addEventListener('input', function() {
        if (this.value !== '') {
            delete this.dataset.wasEmpty;
        }
    });
    
    // Icon preview + preset sync
    const iconInput = document.getElementById('icon');
    const iconPreset = document.getElementById('iconPreset');
    
    function applyIcon(iconClass) {
        const safeClass = iconClass || 'fas fa-folder';
        iconInput.value = safeClass;
        document.getElementById('iconPreview').className = safeClass;
        document.getElementById('badgeIcon').className = safeClass;
    }
    
    iconInput.addEventListener('input', function() {
        const iconClass = this.value || 'fas fa-folder';
        document.getElementById('iconPreview').className = iconClass;
        document.getElementById('badgeIcon').className = iconClass;
        
        if (iconPreset) {
            const match = iconPreset.querySelector('option[value="' + iconClass + '"]');
            iconPreset.value = match ? iconClass : '';
        }
    });
    
    if (iconPreset) {
        iconPreset.addEventListener('change', function() {
            if (this.value) applyIcon(this.value);
        });
    }
    
    // Initialize preset selection on load
    if (iconPreset) {
        const initialMatch = iconPreset.querySelector('option[value="' + iconInput.value + '"]');
        iconPreset.value = initialMatch ? iconInput.value : '';
    }
    
    // Color picker synchronization
    const colorPicker = document.getElementById('colorPicker');
    const colorInput = document.getElementById('color');
    const badgePreview = document.getElementById('badgePreview');
    
    colorPicker.addEventListener('input', function() {
        colorInput.value = this.value;
        badgePreview.style.background = this.value;
    });
    
    colorInput.addEventListener('input', function() {
        if (/^#[0-9A-F]{6}$/i.test(this.value)) {
            colorPicker.value = this.value;
            badgePreview.style.background = this.value;
        }
    });
</script>
