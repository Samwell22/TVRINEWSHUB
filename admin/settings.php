<?php
/**
 * Admin: Pengaturan Website
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

// Cek role: hanya ADMIN yang boleh ubah settings
if ($logged_in_user['role'] !== 'admin') {
    setFlashMessage('error', 'Anda tidak memiliki akses ke halaman ini! Hanya Admin yang boleh mengubah pengaturan.');
    header('Location: dashboard.php');
    exit;
}

// AMBIL SEMUA SETTINGS
// getSetting() and updateSetting() are defined in config/config.php

// Load current settings
$settings = [
    'site_name' => getSetting($conn, 'site_name', 'TVRI SULUT NEWS HUB'),
    'site_tagline' => getSetting($conn, 'site_tagline', 'Portal Berita Terkini Sulawesi Utara'),
    'site_description' => getSetting($conn, 'site_description', 'Portal berita terkini Sulawesi Utara dari TVRI'),
    'running_text' => getSetting($conn, 'running_text', 'Selamat datang di TVRI Sulut News Hub'),
    'facebook_url' => getSetting($conn, 'facebook_url', ''),
    'twitter_url' => getSetting($conn, 'twitter_url', ''),
    'instagram_url' => getSetting($conn, 'instagram_url', ''),
    'youtube_url' => getSetting($conn, 'youtube_url', ''),
    'contact_email' => getSetting($conn, 'contact_email', 'info@tvrisulut.com'),
    'contact_phone' => getSetting($conn, 'contact_phone', '(0431) 123456'),
    'contact_address' => getSetting($conn, 'contact_address', 'Jl. A.A. Maramis, Manado, Sulawesi Utara')
];

// Variabel error
$errors = [];
$success = false;

// Proses form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    requireCSRF();
    
    // Ambil data dari form
    $site_name = sanitizeInput($_POST['site_name'] ?? '');
    $site_tagline = sanitizeInput($_POST['site_tagline'] ?? '');
    $site_description = sanitizeInput($_POST['site_description'] ?? '');
    $running_text = sanitizeInput($_POST['running_text'] ?? '');
    $facebook_url = sanitizeInput($_POST['facebook_url'] ?? '');
    $twitter_url = sanitizeInput($_POST['twitter_url'] ?? '');
    $instagram_url = sanitizeInput($_POST['instagram_url'] ?? '');
    $youtube_url = sanitizeInput($_POST['youtube_url'] ?? '');
    $contact_email = sanitizeInput($_POST['contact_email'] ?? '');
    $contact_phone = sanitizeInput($_POST['contact_phone'] ?? '');
    $contact_address = sanitizeInput($_POST['contact_address'] ?? '');
    
    // Validasi
    if (empty($site_name)) $errors[] = 'Nama website harus diisi!';
    if (empty($contact_email)) $errors[] = 'Email kontak harus diisi!';
    
    // Validasi email
    if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid!';
    }
    
    // Validasi URL social media (jika diisi)
    $urls = [
        'facebook_url' => $facebook_url,
        'twitter_url' => $twitter_url,
        'instagram_url' => $instagram_url,
        'youtube_url' => $youtube_url
    ];
    
    foreach ($urls as $key => $url) {
        if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Format URL ' . str_replace('_url', '', $key) . ' tidak valid!';
        }
    }
    
    // Jika tidak ada error, simpan
    if (empty($errors)) {
        $all_settings = [
            'site_name' => $site_name,
            'site_tagline' => $site_tagline,
            'site_description' => $site_description,
            'running_text' => $running_text,
            'facebook_url' => $facebook_url,
            'twitter_url' => $twitter_url,
            'instagram_url' => $instagram_url,
            'youtube_url' => $youtube_url,
            'contact_email' => $contact_email,
            'contact_phone' => $contact_phone,
            'contact_address' => $contact_address
        ];
        
        $update_success = true;
        foreach ($all_settings as $key => $value) {
            if (!updateSetting($conn, $key, $value)) {
                $update_success = false;
                $errors[] = 'Gagal update setting: ' . $key;
                break;
            }
        }
        
        if ($update_success) {
            $success = true;
            setFlashMessage('success', 'Pengaturan berhasil disimpan!');
            // Reload settings
            $settings = $all_settings;
        }
    }
}

// Set page variables
$page_title = 'Pengaturan Website';
$page_heading = 'Pengaturan Website';
$breadcrumbs = [
    'Dashboard' => 'dashboard.php',
    'Pengaturan' => null
];

// Include header
include 'includes/header.php';
?>

<!-- ALERT SUCCESS -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <h5><i class="fas fa-check-circle"></i> Berhasil!</h5>
        <p class="mb-0">Pengaturan website berhasil disimpan.</p>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

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
        <!-- GENERAL SETTINGS -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-globe"></i> Informasi Website</h5>
                </div>
                <div class="card-body">
                    <!-- Site Name -->
                    <div class="mb-3">
                        <label for="site_name" class="form-label">Nama Website <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="site_name" 
                               name="site_name" 
                               value="<?php echo htmlspecialchars($settings['site_name']); ?>"
                               required>
                    </div>
                    
                    <!-- Site Tagline -->
                    <div class="mb-3">
                        <label for="site_tagline" class="form-label">Tagline Website</label>
                        <input type="text" 
                               class="form-control" 
                               id="site_tagline" 
                               name="site_tagline" 
                               value="<?php echo htmlspecialchars($settings['site_tagline']); ?>">
                        <small class="text-muted">Slogan atau deskripsi singkat website</small>
                    </div>
                    
                    <!-- Running Text -->
                    <div class="mb-3">
                        <label for="running_text" class="form-label">Running Text</label>
                        <textarea class="form-control" 
                                  id="running_text" 
                                  name="running_text" 
                                  rows="2"><?php echo htmlspecialchars($settings['running_text']); ?></textarea>
                        <small class="text-muted">Teks berjalan di header website</small>
                    </div>
                    
                    <!-- Site Description -->
                    <div class="mb-3">
                        <label for="site_description" class="form-label">Deskripsi Website</label>
                        <textarea class="form-control" 
                                  id="site_description" 
                                  name="site_description" 
                                  rows="3"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                        <small class="text-muted">Deskripsi website untuk SEO dan tentang kami</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- CONTACT & SOCIAL MEDIA -->
        <div class="col-lg-6">
            <!-- Contact Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-address-book"></i> Informasi Kontak</h5>
                </div>
                <div class="card-body">
                    <!-- Email -->
                    <div class="mb-3">
                        <label for="contact_email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" 
                               class="form-control" 
                               id="contact_email" 
                               name="contact_email" 
                               value="<?php echo htmlspecialchars($settings['contact_email']); ?>"
                               required>
                    </div>
                    
                    <!-- Phone -->
                    <div class="mb-3">
                        <label for="contact_phone" class="form-label">Nomor Telepon</label>
                        <input type="text" 
                               class="form-control" 
                               id="contact_phone" 
                               name="contact_phone" 
                               value="<?php echo htmlspecialchars($settings['contact_phone']); ?>">
                    </div>
                    
                    <!-- Address -->
                    <div class="mb-3">
                        <label for="contact_address" class="form-label">Alamat</label>
                        <textarea class="form-control" 
                                  id="contact_address" 
                                  name="contact_address" 
                                  rows="2"><?php echo htmlspecialchars($settings['contact_address']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Social Media -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-share-alt"></i> Social Media</h5>
                </div>
                <div class="card-body">
                    <!-- Facebook -->
                    <div class="mb-3">
                        <label for="facebook_url" class="form-label">
                            <i class="fab fa-facebook text-primary"></i> Facebook URL
                        </label>
                        <input type="url" 
                               class="form-control" 
                               id="facebook_url" 
                               name="facebook_url" 
                               value="<?php echo htmlspecialchars($settings['facebook_url']); ?>"
                               placeholder="https://facebook.com/tvrisulut">
                    </div>
                    
                    <!-- Twitter -->
                    <div class="mb-3">
                        <label for="twitter_url" class="form-label">
                            <i class="fab fa-twitter text-info"></i> Twitter/X URL
                        </label>
                        <input type="url" 
                               class="form-control" 
                               id="twitter_url" 
                               name="twitter_url" 
                               value="<?php echo htmlspecialchars($settings['twitter_url']); ?>"
                               placeholder="https://twitter.com/tvrisulut">
                    </div>
                    
                    <!-- Instagram -->
                    <div class="mb-3">
                        <label for="instagram_url" class="form-label">
                            <i class="fab fa-instagram text-danger"></i> Instagram URL
                        </label>
                        <input type="url" 
                               class="form-control" 
                               id="instagram_url" 
                               name="instagram_url" 
                               value="<?php echo htmlspecialchars($settings['instagram_url']); ?>"
                               placeholder="https://instagram.com/tvrisulut">
                    </div>
                    
                    <!-- YouTube -->
                    <div class="mb-3">
                        <label for="youtube_url" class="form-label">
                            <i class="fab fa-youtube text-danger"></i> YouTube URL
                        </label>
                        <input type="url" 
                               class="form-control" 
                               id="youtube_url" 
                               name="youtube_url" 
                               value="<?php echo htmlspecialchars($settings['youtube_url']); ?>"
                               placeholder="https://youtube.com/@tvrisulut">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- SUBMIT BUTTON -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Simpan Pengaturan
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-lg ms-2">
                        <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
