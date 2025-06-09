<?php
// Pastikan config.php diinclude pertama
// header.php sudah akan me-require config.php
if (file_exists(__DIR__ . '/includes/config.php')) {
    require_once __DIR__ . '/includes/config.php'; // Memastikan session_start() dipanggil
} else {
    die('Config file not found');
}

error_log("Streaming page. Session ID: " . session_id());
error_log("Streaming page. Session data: " . print_r($_SESSION, true));
include __DIR__ . '/includes/header.php'; //
?>

<div class="streaming-container">
    <div class="streaming-player">
        <h2>ELMIRA 95.8 FM - Live Streaming</h2>
        
        <div class="player-wrapper">
            <audio id="radio-stream" controls preload="metadata">  <?php // Perubahan di sini: preload="metadata" ?>
                Browser Anda tidak mendukung elemen audio.
            </audio>
            
            <div class="player-controls audio-controls">
                <button id="play-btn" class="control-btn" aria-label="Putar atau Jeda">
                    <i class="fas fa-play"></i>
                </button>
                <button id="mute-btn" class="control-btn" aria-label="Bisukan atau Aktifkan Suara">
                    <i class="fas fa-volume-up"></i>
                </button>
                <input type="range" id="volume-control" min="0" max="1" step="0.01" value="0.7" aria-label="Kontrol Volume">
            </div>
        </div>
        
        <div class="now-playing-info">
            <img src="<?php echo BASE_PATH_PREFIX; ?>/assets/images/logo.png" alt="Now Playing Cover" id="now-playing-cover"> <div>
                <h3 id="now-playing-title">Memuat Info Siaran...</h3>
                <p id="now-playing-artist">Artis</p>
                <p id="now-playing-album" style="display:none;">Album/Program</p>
                <p id="now-playing-penyiar" style="font-size: 0.9em; color: var(--text-secondary);">Penyiar: <span id="np-penyiar-name">Elmira FM</span></p>
            </div>
        </div>
    </div>
    
    <div class="side-features">
        <div class="chat-container card-bg section-mb">
            <h3><i class="fas fa-comments"></i> Live Chat</h3>
            <div class="chat-messages" id="chat-messages" aria-live="polite" aria-atomic="false">
                <p class="login-prompt" style="padding:10px;">Memuat pesan chat...</p>
            </div>
            <?php if (isLoggedIn()): ?>
                <div class="chat-input">
                    <input type="text" id="chat-message-input" placeholder="Ketik pesan Anda..." autocomplete="off" aria-label="Input Pesan Chat">
                    <button id="send-chat-message-btn" aria-label="Kirim Pesan Chat"><i class="fas fa-paper-plane"></i></button>
                </div>
            <?php else: ?>
                <p class="login-prompt">Silakan <a href="<?php echo BASE_PATH_PREFIX; ?>/login.php">login</a> untuk mengirim pesan.</p>
            <?php endif; ?>
        </div>
        
        <div class="song-request card-bg section-mb">
            <h3><i class="fas fa-music"></i> Request Lagu</h3>
            <?php if (isLoggedIn()): ?>
                <form id="streaming-request-form">
                    <div class="form-group">
                        <label for="streaming-song-title" class="sr-only">Judul Lagu</label>
                        <input type="text" id="streaming-song-title" name="song_title" placeholder="Judul Lagu" required>
                    </div>
                    <div class="form-group">
                         <label for="streaming-artist" class="sr-only">Nama Artis</label>
                         <input type="text" id="streaming-artist" name="artist" placeholder="Nama Artis" required>
                    </div>
                     <div class="form-group">
                        <label for="streaming-message" class="sr-only">Pesan (Opsional)</label>
                        <textarea id="streaming-message" name="message" placeholder="Pesan untuk penyiar (opsional)" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn-auth" style="width:100%;">Kirim Request</button>
                </form>
                <div id="streaming-request-feedback" style="margin-top:10px;"></div>
            <?php else: ?>
                <p class="login-prompt">Silakan <a href="<?php echo BASE_PATH_PREFIX; ?>/login.php">login</a> untuk request lagu.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>
    /* Penyesuaian kecil untuk halaman streaming */
    .streaming-player h2 {
        text-align: center; /* Pusatkan judul utama */
    }
    .player-wrapper {
        margin-bottom: 1.5rem;
    }
    .player-wrapper audio {
        width: 100%;
        border-radius: 5px;
        background-color: #000; /* Latar belakang player */
    }
    .audio-controls { /* Class baru untuk kontrol kustom jika audio default disembunyikan */
        display: flex;
        align-items: center;
        justify-content: center; /* Atau space-around */
        gap: 10px;
        margin-top: 10px;
    }
    .now-playing-info {
        border-top: 1px solid var(--hover-color);
        padding-top: 1.5rem;
    }
    .now-playing-info img#now-playing-cover { /* Pastikan ID ini digunakan di main.js */
        width: 120px; /* Sesuaikan ukuran cover */
        height: 120px;
    }
    .side-features {
        display: flex;
        flex-direction: column;
        gap: 1.5rem; /* Jarak antar chat dan request box */
    }
    .chat-container, .song-request {
        /* background-color & padding sudah ada dari .card-bg */
    }
    .sr-only { /* Untuk aksesibilitas, sembunyikan label secara visual tapi tetap ada untuk screen reader */
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>