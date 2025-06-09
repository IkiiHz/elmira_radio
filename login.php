<?php
require_once __DIR__ . '/includes/config.php'; // config.php sudah di-include via header.php

if (isLoggedIn()) {
    // Tentukan redirect berdasarkan role jika sudah login
    $redirect = 'index.php';
    if (isAdmin()) $redirect = 'admin/dashboard.php';
    elseif (isPenyiar()) $redirect = 'penyiar/dashboard.php';
    header('Location: ' . $redirect);
    exit();
}

include __DIR__ . '/includes/header.php';
?>

<div class="auth-container">
    <div class="auth-form">
        <h2>Login ke ELMIRA Radio</h2>
        
        <div id="login-message-container" style="margin-bottom: 1rem;">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <form id="login-form" method="POST" class="login-form"> <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> Username atau Email
                </label>
                <input type="text" id="username" name="username" required 
                       placeholder="Masukkan username atau email Anda" autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Password
                </label>
                <input type="password" id="password" name="password" required 
                       placeholder="Masukkan password Anda" autocomplete="current-password">
                <span toggle="#password" class="fa fa-fw fa-eye field-icon toggle-password"></span>
            </div>
            
            <div class="form-group remember-forgot">
                <label class="remember-me">
                    <input type="checkbox" name="remember"> Ingat saya
                </label>
                <a href="forgot-password.php" class="forgot-password">Lupa password?</a>
            </div>
            
            <button type="submit" class="btn-auth" id="login-submit-btn">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="auth-footer">
            <p class="auth-link">Belum punya akun? <a href="register.php">Daftar disini</a></p>
            <p class="auth-link">Atau kembali ke <a href="index.php">Beranda</a></p>
        </div>
    </div>
</div>

<script>
// Script untuk password toggle sudah ada di main.js jika elemen .toggle-password ada
// Script untuk AJAX login
document.getElementById('login-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const messageContainer = document.getElementById('login-message-container');
    const submitButton = document.getElementById('login-submit-btn');
    const originalButtonText = submitButton.innerHTML;
    
    messageContainer.innerHTML = ''; // Bersihkan pesan lama
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
    submitButton.disabled = true;

    const formData = new FormData(this);

    fetch('includes/auth.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageContainer.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle"></i> ${data.message} Redirecting...</div>`;
            window.location.href = data.redirect || 'index.php';
        } else {
            messageContainer.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ${data.message || 'Login gagal.'}</div>`;
            submitButton.innerHTML = originalButtonText;
            submitButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Login fetch error:', error);
        messageContainer.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Terjadi kesalahan koneksi.</div>';
        submitButton.innerHTML = originalButtonText;
        submitButton.disabled = false;
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>