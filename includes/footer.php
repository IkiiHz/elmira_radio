<?php
// File: elmira_radio/includes/footer.php
// Diasumsikan BASE_PATH_PREFIX sudah didefinisikan dari config.php (yang di-include oleh header.php)
// Jika tidak, Anda perlu memastikan config.php di-include di sini atau $base_path_trimmed diteruskan.
// Untuk amannya, kita definisikan ulang $base_path_trimmed jika belum ada (meskipun seharusnya sudah dari header)
if (!isset($base_path_trimmed) && defined('BASE_PATH_PREFIX')) {
    $base_path_trimmed = rtrim(BASE_PATH_PREFIX, '/');
} elseif (!isset($base_path_trimmed)) {
    $base_path_trimmed = '/elmira_radio'; // Fallback absolut jika semua gagal
    error_log("Peringatan: BASE_PATH_PREFIX tidak terdefinisi di footer.php, menggunakan fallback.");
}
?>
</main>
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h4>ELMIRA 95.8 FM</h4>
                <p>Radio dengan kualitas terbaik dan program menarik</p>
            </div>
            <div class="footer-section">
                <h4>Kontak</h4>
                <p><i class="fas fa-phone"></i> (021) 1234567</p>
                <p><i class="fas fa-envelope"></i> info@elmradio.com</p>
            </div>
            <div class="footer-section">
                <h4>Sosial Media</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> ELMIRA 95.8 FM. All Rights Reserved.</p>
        </div>
    </footer>

    <div id="custom-alert-modal" class="custom-modal-overlay" style="display: none;">
        <div class="custom-modal-content">
            <h4 id="custom-alert-title">Pemberitahuan</h4>
            <p id="custom-alert-message"></p>
            <button id="custom-alert-ok-btn" class="btn-auth">OK</button>
        </div>
    </div>

    <?php
    // Menggunakan BASE_PATH_PREFIX untuk path main.js yang lebih robust
    // Variabel $base_path_trimmed seharusnya sudah ada dari header.php
    $mainJsPath = $base_path_trimmed . '/assets/js/main.js';
    ?>
    <script src="<?php echo htmlspecialchars($mainJsPath); ?>"></script>
</body>
</html>