<?php
// File: elmira_radio/register.php

// config.php sudah di-include via header.php, yang juga memulai session
include 'includes/header.php';

// Jika pengguna sudah login, arahkan ke halaman yang sesuai
if (isLoggedIn()) {
    $redirect_url = BASE_PATH_PREFIX . '/index.php'; // Default redirect
    if (isAdmin()) {
        $redirect_url = BASE_PATH_PREFIX . '/admin/dashboard.php';
    } elseif (isPenyiar()) {
        $redirect_url = BASE_PATH_PREFIX . '/penyiar/dashboard.php';
    }
    header('Location: ' . $redirect_url);
    exit();
}
?>

<div class="auth-container">
    <div class="auth-form">
        <h2>Daftar Akun Baru</h2>
        
        <div id="register-feedback" style="margin-bottom: 1rem;">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['success'])): // Ini akan berguna jika redirect dari tempat lain ke register dengan pesan ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <form id="register-form" method="POST" action="<?php echo BASE_PATH_PREFIX; ?>/includes/auth.php">
            <input type="hidden" name="action" value="register">
            
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" name="username" required placeholder="Masukkan username">
            </div>
            
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" required placeholder="Masukkan alamat email">
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" required placeholder="Minimal 6 karakter">
                <span toggle="#password" class="fa fa-fw fa-eye field-icon toggle-password" style="cursor: pointer; position: absolute; right: 15px; top: 70%; transform: translateY(-50%); color: var(--text-secondary);"></span>
            </div>
            
            <div class="form-group">
                <label for="confirm-password"><i class="fas fa-lock"></i> Konfirmasi Password</label>
                <input type="password" id="confirm-password" name="confirm_password" required placeholder="Ketik ulang password">
                <span toggle="#confirm-password" class="fa fa-fw fa-eye field-icon toggle-password" style="cursor: pointer; position: absolute; right: 15px; top: 70%; transform: translateY(-50%); color: var(--text-secondary);"></span>
            </div>
            
            <button type="submit" class="btn-auth"><i class="fas fa-user-plus"></i> Daftar</button>
        </form>
        
        <div class="auth-footer">
            <p class="auth-link">Sudah punya akun? <a href="<?php echo BASE_PATH_PREFIX; ?>/login.php">Login disini</a></p>
            <p class="auth-link">Atau kembali ke <a href="<?php echo BASE_PATH_PREFIX; ?>/index.php">Beranda</a></p>
        </div>
    </div>
</div>

<!-- Custom Alert Modal -->
<div id="custom-alert-modal" class="custom-modal-overlay">
  <div class="custom-modal-content">
    <h4 id="custom-alert-title">Judul</h4>
    <p id="custom-alert-message">Pesan</p>
    <button id="custom-alert-ok-btn" class="btn-auth">OK</button>
  </div>
</div>


<?php include 'includes/footer.php'; ?>