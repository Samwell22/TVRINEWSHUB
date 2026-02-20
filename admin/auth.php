<?php
/**
 * Admin: Auth System
 */

// Include config untuk SITE_URL
require_once __DIR__ . '/../config/config.php';

// Mulai session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user sudah login
 * Redirect ke login jika belum
 */
function requireLogin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: ' . SITE_URL . 'admin/login.php');
        exit;
    }
}

/**
 * Cek apakah user sudah login
 * Redirect ke dashboard jika sudah
 */
function redirectIfLoggedIn() {
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        header('Location: ' . SITE_URL . 'admin/dashboard.php');
        exit;
    }
}

/**
 * Get data user yang sedang login
 * @return array|null Data user atau null jika belum login
 */
function getLoggedInUser() {
    if (isset($_SESSION['admin_user_id'])) {
        return [
            'id' => $_SESSION['admin_user_id'],
            'username' => $_SESSION['admin_username'],
            'full_name' => $_SESSION['admin_full_name'],
            'role' => $_SESSION['admin_role']
        ];
    }
    return null;
}

/**
 * Cek apakah user punya role tertentu
 * @param string $required_role Role yang diperlukan (admin/editor/reporter)
 * @return bool
 */
function hasRole($required_role) {
    if (!isset($_SESSION['admin_role'])) {
        return false;
    }
    
    $user_role = $_SESSION['admin_role'];
    
    // Admin punya akses penuh
    if ($user_role === 'admin') {
        return true;
    }
    
    // Cek role spesifik
    return $user_role === $required_role;
}

/**
 * Require role tertentu, redirect jika tidak punya akses
 * @param string $required_role Role yang diperlukan
 */
function requireRole($required_role) {
    if (!hasRole($required_role)) {
        $_SESSION['error_message'] = 'Anda tidak punya akses ke halaman ini!';
        header('Location: ' . SITE_URL . 'admin/dashboard.php');
        exit;
    }
}

/**
 * Require admin role (helper khusus endpoint admin-only)
 */
function requireAdmin() {
    requireLogin();
    requireRole('admin');
}

/**
 * Logout user
 */
function logout() {
    // Hapus semua session variables
    session_unset();
    
    // Destroy session
    session_destroy();
    
    // Redirect ke login
    header('Location: ' . SITE_URL . 'admin/login.php');
    exit;
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * @param string $token Token dari form
 * @return bool Valid atau tidak
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output hidden CSRF input field
 * @return string HTML hidden input
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Require valid CSRF token or die
 * Use this at the start of POST handlers
 */
function requireCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            http_response_code(403);
            die('Invalid CSRF token. Please refresh the page and try again.');
        }
    }
}

/**
 * Set flash message
 * @param string $type Type pesan (success/error/warning/info)
 * @param string $message Isi pesan
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get dan hapus flash message
 * @return array|null Flash message atau null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}
?>
