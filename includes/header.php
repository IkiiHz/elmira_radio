<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php'; // config.php sekarang memiliki fungsi isOwner()

// Periksa apakah BASE_PATH_PREFIX sudah terdefinisi setelah inklusi.
if (!defined('BASE_PATH_PREFIX')) {
    define('BASE_PATH_PREFIX', '/elmira_radio'); // Fallback default
    error_log("Peringatan: BASE_PATH_PREFIX tidak terdefinisi oleh config.php, menggunakan fallback '/elmira_radio'. Periksa file config.php.");
}

// Periksa apakah $pdo terdefinisi
if (!isset($pdo)) {
    error_log("Peringatan: Variabel \$pdo tidak terdefinisi setelah inklusi config.php.");
}

// Variabel untuk path dasar yang sudah di-trim
$base_path_trimmed = rtrim(BASE_PATH_PREFIX, '/');

// Ambil informasi login sekali untuk menghindari pemanggilan fungsi berulang
$isUserLoggedIn = isLoggedIn();
$currentUsername = $isUserLoggedIn ? htmlspecialchars(getCurrentUsername()) : null;
$isAdmin = $isUserLoggedIn ? isAdmin() : false;
$isPenyiar = $isUserLoggedIn ? isPenyiar() : false;
$isOwner = $isUserLoggedIn ? (function_exists('isOwner') && isOwner()) : false; // Cek apakah fungsi isOwner ada

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMIRA 95.8 FM - Radio Modern</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($base_path_trimmed); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="<?php echo htmlspecialchars($base_path_trimmed); ?>/assets/images/logo.png" type="image/png">
    <script>
        const currentLoggedInUsername = <?php echo $isUserLoggedIn ? json_encode($currentUsername) : 'null'; ?>;
        const isUserCurrentlyLoggedIn = <?php echo $isUserLoggedIn ? 'true' : 'false'; ?>;
        const basePath = '<?php echo $base_path_trimmed; // Tidak perlu htmlspecialchars karena ini untuk JS path ?>';
    </script>
</head>
<body>
    <header>
        <a href="<?php echo htmlspecialchars($base_path_trimmed); ?>/index.php" class="logo-link">
            <img src="<?php echo htmlspecialchars($base_path_trimmed); ?>/assets/images/logo.png" alt="ELMIRA 95.8 FM" class="logo">
        </a>
        
        <nav>
            <button class="menu-toggle" aria-label="Toggle menu" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
            <ul>
                <li><a href="<?php echo htmlspecialchars($base_path_trimmed); ?>/index.php">Beranda</a></li>
                <li><a href="<?php echo htmlspecialchars($base_path_trimmed); ?>/streaming.php">Streaming</a></li>
                <li><a href="<?php echo htmlspecialchars($base_path_trimmed); ?>/schedule.php">Jadwal</a></li>
                <li><a href="<?php echo htmlspecialchars($base_path_trimmed); ?>/penyiar.php">Penyiar</a></li>
                <?php if ($isUserLoggedIn): ?>
                    <li><a href="<?php echo htmlspecialchars($base_path_trimmed); ?>/pesan_iklan.php">Request iklan</a></li>
                    <?php if ($isAdmin): ?>
                        <li><a href="<?php echo htmlspecialchars($base_path_trimmed); ?>/admin/dashboard.php">Admin Panel</a></li>
                    <?php elseif ($isPenyiar): ?>
                        <li><a href="<?php echo htmlspecialchars($base_path_trimmed); ?>/penyiar/dashboard.php">Penyiar Panel</a></li>
                    <?php elseif ($isOwner): // Ditambahkan kondisi untuk Owner ?>
                        <li><a href="<?php echo htmlspecialchars($base_path_trimmed); ?>/owner/dashboard.php">Owner Panel</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo htmlspecialchars($base_path_trimmed); ?>/includes/auth.php?action=logout">Logout (<?php echo $currentUsername; ?>)</a></li>
                <?php else: ?>
                    <li><a href="<?php echo htmlspecialchars($base_path_trimmed); ?>/login.php">Login</a></li>
                    <li><a href="<?php echo htmlspecialchars($base_path_trimmed); ?>/register.php">Daftar</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
    <?php // Tag penutup </main> akan ada di footer.php ?>