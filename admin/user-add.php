<?php
/**
 * Admin: Form Tambah/Edit User
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

// Cek apakah ini mode EDIT atau ADD
$is_edit = isset($_GET['id']) && (int)$_GET['id'] > 0;
$user_id = $is_edit ? (int)$_GET['id'] : 0;

// Default values untuk form
$user = [
    'username' => '',
    'full_name' => '',
    'email' => '',
    'role' => 'reporter'
];

// Jika mode EDIT, ambil data existing
if ($is_edit) {
    $query = "SELECT * FROM users WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        setFlashMessage('error', 'User tidak ditemukan!');
        header('Location: user-list.php');
        exit;
    }
    
    $user = mysqli_fetch_assoc($result);
}

// Variabel error
$errors = [];

// Proses form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    requireCSRF();
    
    // Ambil data dari form
    $username = sanitizeInput($_POST['username'] ?? '');
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $role = sanitizeInput($_POST['role'] ?? 'reporter');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validasi input
    if (empty($username)) {
        $errors[] = 'Username harus diisi!';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors[] = 'Username harus 3-20 karakter dan hanya boleh huruf, angka, dan underscore!';
    }
    
    if (empty($full_name)) {
        $errors[] = 'Nama lengkap harus diisi!';
    }
    
    if (empty($email)) {
        $errors[] = 'Email harus diisi!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid!';
    }
    
    if (!in_array($role, ['admin', 'editor', 'reporter'])) {
        $errors[] = 'Role tidak valid!';
    }
    
    // Validasi password (hanya untuk ADD atau jika diisi saat EDIT)
    if (!$is_edit || !empty($password)) {
        if (empty($password)) {
            $errors[] = 'Password harus diisi!';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password minimal 6 karakter!';
        } elseif ($password !== $password_confirm) {
            $errors[] = 'Password dan konfirmasi password tidak sama!';
        }
    }
    
    // Cek username duplikat
    if (!empty($username)) {
        if ($is_edit) {
            $check_username = "SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1";
            $stmt_check = mysqli_prepare($conn, $check_username);
            mysqli_stmt_bind_param($stmt_check, 'si', $username, $user_id);
        } else {
            $check_username = "SELECT id FROM users WHERE username = ? LIMIT 1";
            $stmt_check = mysqli_prepare($conn, $check_username);
            mysqli_stmt_bind_param($stmt_check, 's', $username);
        }
        
        mysqli_stmt_execute($stmt_check);
        if (mysqli_stmt_get_result($stmt_check)->num_rows > 0) {
            $errors[] = 'Username sudah digunakan! Silakan gunakan username lain.';
        }
    }
    
    // Cek email duplikat
    if (!empty($email)) {
        if ($is_edit) {
            $check_email = "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1";
            $stmt_check = mysqli_prepare($conn, $check_email);
            mysqli_stmt_bind_param($stmt_check, 'si', $email, $user_id);
        } else {
            $check_email = "SELECT id FROM users WHERE email = ? LIMIT 1";
            $stmt_check = mysqli_prepare($conn, $check_email);
            mysqli_stmt_bind_param($stmt_check, 's', $email);
        }
        
        mysqli_stmt_execute($stmt_check);
        if (mysqli_stmt_get_result($stmt_check)->num_rows > 0) {
            $errors[] = 'Email sudah digunakan! Silakan gunakan email lain.';
        }
    }
    
    // Jika tidak ada error, proses simpan
    if (empty($errors)) {
        if ($is_edit) {
            // UPDATE
            if (!empty($password)) {
                // Update dengan password baru
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $update_query = "UPDATE users SET 
                                 username = ?, 
                                 full_name = ?, 
                                 email = ?, 
                                 password = ?,
                                 role = ?
                                 WHERE id = ?";
                
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, 'sssssi', $username, $full_name, $email, $password_hash, $role, $user_id);
            } else {
                // Update tanpa ganti password
                $update_query = "UPDATE users SET 
                                 username = ?, 
                                 full_name = ?, 
                                 email = ?, 
                                 role = ?
                                 WHERE id = ?";
                
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, 'ssssi', $username, $full_name, $email, $role, $user_id);
            }
            
            if (mysqli_stmt_execute($stmt)) {
                setFlashMessage('success', 'User berhasil diupdate!');
                header('Location: user-list.php');
                exit;
            } else {
                error_log('Gagal update user: ' . mysqli_error($conn));
                $errors[] = 'Gagal update user. Silakan coba lagi.';
            }
        } else {
            // INSERT
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $insert_query = "INSERT INTO users (username, full_name, email, password, role) 
                             VALUES (?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, 'sssss', $username, $full_name, $email, $password_hash, $role);
            
            if (mysqli_stmt_execute($stmt)) {
                setFlashMessage('success', 'User berhasil ditambahkan!');
                header('Location: user-list.php');
                exit;
            } else {
                error_log('Gagal menambahkan user: ' . mysqli_error($conn));
                $errors[] = 'Gagal menambahkan user. Silakan coba lagi.';
            }
        }
    }
    
    // Jika ada error, simpan input untuk ditampilkan ulang
    if (!empty($errors)) {
        $user = [
            'username' => $username,
            'full_name' => $full_name,
            'email' => $email,
            'role' => $role
        ];
    }
}

// Set page variables
$page_title = $is_edit ? 'Edit User' : 'Tambah User';
$page_heading = $is_edit ? 'Edit User: ' . htmlspecialchars($user['username']) : 'Tambah User Baru';
$breadcrumbs = [
    'Dashboard' => 'dashboard.php',
    'Kelola User' => 'user-list.php',
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
                    <h5><i class="fas fa-user"></i> Informasi User</h5>
                </div>
                <div class="card-body">
                    <!-- Username -->
                    <div class="mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               value="<?php echo htmlspecialchars($user['username']); ?>"
                               pattern="[a-zA-Z0-9_]{3,20}"
                               required>
                        <small class="text-muted">3-20 karakter, hanya huruf, angka, dan underscore</small>
                    </div>
                    
                    <!-- Nama Lengkap -->
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="full_name" 
                               name="full_name" 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>"
                               required>
                    </div>
                    
                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>"
                               required>
                        <small class="text-muted">Email valid untuk notifikasi dan reset password</small>
                    </div>
                    
                    <!-- Role -->
                    <div class="mb-3">
                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="reporter" <?php echo ($user['role'] === 'reporter') ? 'selected' : ''; ?>>
                                Reporter - Bisa edit semua berita dan kelola kategori
                            </option>
                            <option value="editor" <?php echo ($user['role'] === 'editor') ? 'selected' : ''; ?>>
                                Editor - Bisa edit semua berita dan kelola kategori
                            </option>
                            <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>
                                Admin - Akses penuh termasuk kelola user
                            </option>
                        </select>
                    </div>
                    
                    <hr>
                    
                    <!-- Password -->
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            Password 
                            <?php if (!$is_edit): ?>
                                <span class="text-danger">*</span>
                            <?php endif; ?>
                        </label>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               minlength="6"
                               <?php echo !$is_edit ? 'required' : ''; ?>>
                        <small class="text-muted">
                            <?php if ($is_edit): ?>
                                Biarkan kosong jika tidak ingin mengubah password. Minimal 6 karakter.
                            <?php else: ?>
                                Minimal 6 karakter
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <!-- Konfirmasi Password -->
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">
                            Konfirmasi Password 
                            <?php if (!$is_edit): ?>
                                <span class="text-danger">*</span>
                            <?php endif; ?>
                        </label>
                        <input type="password" 
                               class="form-control" 
                               id="password_confirm" 
                               name="password_confirm" 
                               minlength="6"
                               <?php echo !$is_edit ? 'required' : ''; ?>>
                        <small class="text-muted">Ulangi password untuk konfirmasi</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SIDEBAR -->
        <div class="col-lg-4">
            <!-- Action Box -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5><i class="fas fa-cog"></i> Aksi</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $is_edit ? 'Update' : 'Simpan'; ?> User
                        </button>
                        <a href="user-list.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Info Box -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> Informasi</h5>
                </div>
                <div class="card-body">
                    <h6 class="fw-bold">Role & Permission</h6>
                    <ul class="small mb-0">
                        <li><strong>Reporter:</strong> Edit semua berita dan kelola kategori</li>
                        <li><strong>Editor:</strong> Edit semua berita dan kelola kategori</li>
                        <li><strong>Admin:</strong> Akses penuh dan kelola user</li>
                    </ul>
                    
                    <?php if ($is_edit): ?>
                        <hr>
                        <h6 class="fw-bold">Detail User</h6>
                        <p class="mb-1 small">
                            <strong>Dibuat:</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                        </p>
                        <?php if ($user['last_login']): ?>
                            <p class="mb-0 small">
                                <strong>Terakhir Login:</strong><br>
                                <?php echo date('d/m/Y H:i', strtotime($user['last_login'])); ?>
                            </p>
                        <?php else: ?>
                            <p class="mb-0 small text-muted">
                                Belum pernah login
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>

<script>
    // Password confirmation validation
    const password = document.getElementById('password');
    const passwordConfirm = document.getElementById('password_confirm');
    
    function validatePassword() {
        if (password.value !== '' || passwordConfirm.value !== '') {
            if (password.value !== passwordConfirm.value) {
                passwordConfirm.setCustomValidity('Password tidak sama!');
            } else {
                passwordConfirm.setCustomValidity('');
            }
        } else {
            passwordConfirm.setCustomValidity('');
        }
    }
    
    password.addEventListener('input', validatePassword);
    passwordConfirm.addEventListener('input', validatePassword);
</script>
