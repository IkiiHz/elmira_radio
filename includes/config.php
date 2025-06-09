<?php
// Periksa status session sebelum memulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definisi BASE_PATH_PREFIX
if (!defined('BASE_PATH_PREFIX')) {
    // Menentukan BASE_PATH_PREFIX berdasarkan lokasi config.php
    $script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); 
    
    $projectName = 'elmira_radio'; // PASTIKAN INI SESUAI DENGAN NAMA FOLDER PROYEK ANDA
    
    if (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false || strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false) {
        if (strpos($script_path, '/' . $projectName) !== false) {
            // Jika script path mengandung nama proyek, misal /elmira_radio/includes
            // maka BASE_PATH_PREFIX adalah /elmira_radio
            define('BASE_PATH_PREFIX', '/' . $projectName);
        } else {
            // Jika script path tidak mengandung nama proyek (misal di root XAMPP tapi DocumentRoot menunjuk ke elmira_radio)
            // atau jika nama folder proyek adalah root DocumentRoot server lokal.
            // Untuk localhost/elmira_radio/, ini seharusnya tidak terpanggil jika $script_path benar.
            // Sebagai fallback aman jika elmira_radio adalah root dari domain lokal (misal http://elmira_radio.test/)
            // maka BASE_PATH_PREFIX bisa jadi string kosong ''.
            // Namun, untuk http://localhost/elmira_radio/, prefix /elmira_radio diperlukan.
            define('BASE_PATH_PREFIX', '/' . $projectName); // Fallback, sesuaikan jika DocumentRoot Anda langsung ke folder proyek
        }
    } else {
        // Untuk server produksi, BASE_PATH_PREFIX mungkin string kosong jika domain langsung ke root proyek
        define('BASE_PATH_PREFIX', '');
    }
    error_log('[CONFIG_DEBUG] BASE_PATH_PREFIX defined as: ' . BASE_PATH_PREFIX . ' from SCRIPT_NAME: ' . $_SERVER['SCRIPT_NAME'] . ' and script_path: ' . $script_path);
}


$host = 'localhost';
$dbname = 'elmira_radio'; // Pastikan nama database Anda benar
$username = 'root'; 
$password = '';     

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Tidak dapat terhubung ke database. Silakan coba lagi nanti.");
}

// Fungsi-fungsi helper
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('getCurrentUsername')) {
    function getCurrentUsername() {
        return $_SESSION['username'] ?? null;
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

if (!function_exists('isPenyiar')) {
    function isPenyiar() {
        return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'penyiar';
    }
}

if (!function_exists('isUser')) {
    function isUser() {
        return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'user';
    }
}
// File: includes/config.php
// ... (kode konfigurasi Anda yang sudah ada di atas) ...

// Tambahkan fungsi ini di bagian helper functions
if (!function_exists('isOwner')) {
    function isOwner() {
        // Pastikan session sudah dimulai jika belum (seharusnya sudah di awal config.php)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'owner';
    }
}

if (!function_exists('redirectIfNotOwner')) {
    function redirectIfNotOwner($page = '../login.php?error=Akses+owner+ditolak.') {
        if (!isOwner()) {
            // Pastikan BASE_PATH_PREFIX terdefinisi
            $baseRedirect = defined('BASE_PATH_PREFIX') ? rtrim(BASE_PATH_PREFIX, '/') : '';
            
            // Logika untuk menentukan path redirect yang benar
            // Jika $page dimulai dengan '../', asumsikan relatif terhadap direktori /owner/
            if (substr($page, 0, 3) === '../') {
                // Contoh: '../login.php' dari '/owner/dashboard.php' akan menjadi '/login.php' relatif ke BASE_PATH_PREFIX
                $effective_page = ltrim(substr($page, 2), '/'); // Menghilangkan '../' dan memastikan tidak ada / di awal
                header('Location: ' . $baseRedirect . '/' . $effective_page);
            } else {
                // Jika $page adalah path absolut dari root domain atau path relatif dari root proyek
                header('Location: ' . $baseRedirect . '/' . ltrim($page, '/'));
            }
            exit();
        }
    }
}

if (!function_exists('redirectIfNotLoggedIn')) {
    function redirectIfNotLoggedIn($page = 'login.php') {
        if (!isLoggedIn()) {
            header('Location: ' . rtrim(BASE_PATH_PREFIX, '/') . '/' . ltrim($page, '/')); 
            exit();
        }
    }
}

if (!function_exists('redirectIfNotAdmin')) {
    function redirectIfNotAdmin($page = 'index.php') { 
        if (!isAdmin()) {
            header('Location: ' . rtrim(BASE_PATH_PREFIX, '/') . '/' . ltrim($page, '/') . '?error=Akses+Ditolak');
            exit();
        }
    }
}

if (!function_exists('redirectIfNotPenyiar')) {
    function redirectIfNotPenyiar($page = 'index.php') {
        if (!isPenyiar()) {
            header('Location: ' . rtrim(BASE_PATH_PREFIX, '/') . '/' . ltrim($page, '/') . '?error=Akses+Ditolak');
            exit();
        }
    }
}

if (!function_exists('redirectIfNotUser')) {
    function redirectIfNotUser($page = 'login.php') {
        if (!isUser() && !isPenyiar() && !isAdmin()) { 
            header('Location: ' . rtrim(BASE_PATH_PREFIX, '/') . '/' . ltrim($page, '/') . '?error=Silakan+login');
            exit();
        }
    }
}

if (!function_exists('json_response')) {
    function json_response($success, $data = [], $message = '') {
        header('Content-Type: application/json'); // PENTING: Tambahkan/pastikan baris ini ada
        $response = ['success' => (bool)$success];
        if ($message) {
            $response['message'] = $message;
        }
        // Logika untuk data bisa disesuaikan dengan versi auth.php Anda jika lebih disukai:
        if (!empty($data) || (is_array($data) && $success)) {
            // Jika $data adalah array asosiatif dan ingin key-nya langsung di root response (seperti di auth.php)
            if (is_array($data) && array_keys($data) !== range(0, count($data) - 1) && $success) {
                $response = array_merge($response, $data);
            } else {
                $response['data'] = $data;
            }
        }
        echo json_encode($response);
        exit();
    }
}
if (!defined('UPLOAD_URL_BROADCASTER_ADS')) {
    define('UPLOAD_URL_BROADCASTER_ADS', rtrim(BASE_PATH_PREFIX, '/') . '/uploads/broadcaster_ads/'); // URL untuk akses web file dari penyiar
}
// Di dalam includes/config.php
// ... (kode konfigurasi lainnya) ...

if (!defined('UPLOAD_DIR_LISTENER_ADS')) {
    define('UPLOAD_DIR_LISTENER_ADS', dirname(__DIR__) . '/uploads/listener_ads/');
}
if (!defined('UPLOAD_URL_LISTENER_ADS')) {
    define('UPLOAD_URL_LISTENER_ADS', rtrim(BASE_PATH_PREFIX, '/') . '/uploads/listener_ads/');
}

if (!defined('UPLOAD_DIR_BROADCASTER_ADS')) {
    define('UPLOAD_DIR_BROADCASTER_ADS', dirname(__DIR__) . '/uploads/broadcaster_ads/');
}
if (!defined('UPLOAD_URL_BROADCASTER_ADS')) {
    define('UPLOAD_URL_BROADCASTER_ADS', rtrim(BASE_PATH_PREFIX, '/') . '/uploads/broadcaster_ads/');
}
if (!defined('UPLOAD_DIR_PROFILES')) {
    define('UPLOAD_DIR_PROFILES', dirname(__DIR__) . '/assets/images/profiles/'); // Path fisik server
}
if (!defined('UPLOAD_URL_PROFILES')) {
    define('UPLOAD_URL_PROFILES', rtrim(BASE_PATH_PREFIX, '/') . '/assets/images/profiles/'); // URL akses web
}

// Pastikan direktori ada dan bisa ditulis (opsional, bisa dilakukan manual atau saat setup)
if (!file_exists(UPLOAD_DIR_PROFILES)) {
    @mkdir(UPLOAD_DIR_PROFILES, 0775, true); // Coba buat jika belum ada
}
// ... (sisa kode config.php) ...
// File: elmira_radio/includes/config.php
// ... (kode konfigurasi lainnya) ...

if (!defined('UPLOAD_DIR_LISTENER_ADS')) {
    define('UPLOAD_DIR_LISTENER_ADS', dirname(__DIR__) . '/uploads/listener_ads/');
}
if (!defined('UPLOAD_URL_LISTENER_ADS')) {
    define('UPLOAD_URL_LISTENER_ADS', rtrim(BASE_PATH_PREFIX, '/') . '/uploads/listener_ads/');
}

if (!defined('UPLOAD_DIR_BROADCASTER_ADS')) {
    define('UPLOAD_DIR_BROADCASTER_ADS', dirname(__DIR__) . '/uploads/broadcaster_ads/');
}
if (!defined('UPLOAD_URL_BROADCASTER_ADS')) {
    define('UPLOAD_URL_BROADCASTER_ADS', rtrim(BASE_PATH_PREFIX, '/') . '/uploads/broadcaster_ads/');
}

// TAMBAHKAN INI:
if (!defined('UPLOAD_DIR_PAYMENT_PROOFS')) {
    define('UPLOAD_DIR_PAYMENT_PROOFS', dirname(__DIR__) . '/uploads/payment_proofs/'); // Path fisik server
}
if (!defined('UPLOAD_URL_PAYMENT_PROOFS')) {
    define('UPLOAD_URL_PAYMENT_PROOFS', rtrim(BASE_PATH_PREFIX, '/') . '/uploads/payment_proofs/'); // URL akses web
}


if (!defined('UPLOAD_DIR_PROFILES')) {
    define('UPLOAD_DIR_PROFILES', dirname(__DIR__) . '/assets/images/profiles/'); // Path fisik server
}
if (!defined('UPLOAD_URL_PROFILES')) {
    define('UPLOAD_URL_PROFILES', rtrim(BASE_PATH_PREFIX, '/') . '/assets/images/profiles/'); // URL akses web
}

// Pastikan direktori ada dan bisa ditulis (opsional, bisa dilakukan manual atau saat setup)
if (!file_exists(UPLOAD_DIR_PROFILES)) {
    @mkdir(UPLOAD_DIR_PROFILES, 0775, true); // Coba buat jika belum ada
}
// TAMBAHKAN INI JUGA untuk direktori payment_proofs:
if (!file_exists(UPLOAD_DIR_PAYMENT_PROOFS)) {
    @mkdir(UPLOAD_DIR_PAYMENT_PROOFS, 0775, true); // Coba buat jika belum ada
}
// ... (sisa kode config.php) ...
?>
