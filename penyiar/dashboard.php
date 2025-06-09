<?php
// File: penyiar/dashboard.php
// (Sebelumnya mungkin disebut manage_ads.php, kita gunakan nama dashboard.php sesuai file yang Anda berikan)

require_once '../includes/config.php'; // Memuat konfigurasi dan helper (termasuk fungsi otentikasi)

redirectIfNotPenyiar('../login.php?error=Akses penyiar ditolak.'); // Fungsi dari config.php
$penyiar_id = getCurrentUserId(); // Fungsi dari config.php
$penyiarUsername = getCurrentUsername(); // Fungsi dari config.php

$success_message = ''; // Untuk pesan sukses setelah aksi POST (jika ada di masa depan)
$error_message_page = ''; // Untuk error saat load data halaman

// Definisikan konstanta path upload. IDEALNYA, letakkan ini di includes/config.php
// agar konsisten dan terpusat.
if (!defined('UPLOAD_DIR_BROADCASTER_ADS')) {
    define('UPLOAD_DIR_BROADCASTER_ADS', dirname(__DIR__) . '/uploads/broadcaster_ads/'); // Path fisik di server
}
if (!defined('UPLOAD_URL_BROADCASTER_ADS')) {
    define('UPLOAD_URL_BROADCASTER_ADS', rtrim(BASE_PATH_PREFIX, '/') . '/uploads/broadcaster_ads/'); // URL untuk akses web
}

// Status yang relevan untuk Penyiar dan aksi yang bisa dilakukan
$penyiar_actionable_statuses = [ //
    'pending_broadcaster_creation' => 'Baru Ditugaskan', //
    'revision_needed_by_user' => 'Perlu Revisi dari Pengguna' //
];
// Status yang di-set oleh penyiar setelah selesai mengunggah/memproses materi
$penyiar_submitted_status = 'pending_user_confirmation'; //

$currentProgram = null; //
$currentTime = date('H:i:s'); //
$currentDay = date('l');  //
$songRequests = []; //
$lastNowPlayingData = null; //
$availableMusicFiles = []; //
$ad_bookings_for_penyiar = [];


// --- PROSES AKSI FORM (Upload Materi Iklan oleh Penyiar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $booking_id_form = $_POST['booking_id'] ?? null;

    if (!$booking_id_form || !ctype_digit((string)$booking_id_form)) {
        $error_message_page = "ID Pemesanan Iklan tidak valid untuk diproses.";
    } else {
        // Ambil detail booking untuk validasi status dan kepemilikan tugas sebelum update
        $stmt_check_booking = $pdo->prepare("SELECT status, ad_file_broadcaster FROM ad_bookings WHERE id = :id AND assigned_penyiar_id = :penyiar_id");
        $stmt_check_booking->execute([':id' => $booking_id_form, ':penyiar_id' => $penyiar_id]);
        $current_booking_details = $stmt_check_booking->fetch();

        if (!$current_booking_details) {
            $error_message_page = "Pemesanan iklan tidak ditemukan atau tidak ditugaskan untuk Anda.";
        } else {
            if ($_POST['action'] === 'submit_broadcaster_ad_material') { // Ubah nama aksi agar lebih spesifik
                $broadcaster_notes = trim($_POST['broadcaster_notes'] ?? '');
                $uploaded_file_path_db = $current_booking_details['ad_file_broadcaster'] ?? null;

                if (isset($_FILES['ad_file_broadcaster']) && $_FILES['ad_file_broadcaster']['error'] === UPLOAD_ERR_OK) {
                    if (!file_exists(UPLOAD_DIR_BROADCASTER_ADS)) {
                        if (!mkdir(UPLOAD_DIR_BROADCASTER_ADS, 0775, true)) {
                            $error_message_page = "Gagal membuat direktori upload: " . UPLOAD_DIR_BROADCASTER_ADS;
                            error_log("Gagal membuat direktori: " . UPLOAD_DIR_BROADCASTER_ADS);
                        }
                    }

                    if (empty($error_message_page) && is_writable(UPLOAD_DIR_BROADCASTER_ADS)) {
                        $file_tmp_path = $_FILES['ad_file_broadcaster']['tmp_name'];
                        $file_name = basename($_FILES['ad_file_broadcaster']['name']);
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $allowed_extensions = ['mp3', 'wav', 'ogg', 'aac', 'm4a', 'flac'];

                        if (in_array($file_ext, $allowed_extensions)) {
                            if ($_FILES['ad_file_broadcaster']['size'] < 50 * 1024 * 1024) { // Maks 50MB
                                $new_file_name = 'ad_bcast_' . $booking_id_form . '_' . time() . '.' . $file_ext;
                                $destination_path = UPLOAD_DIR_BROADCASTER_ADS . $new_file_name;

                                if (move_uploaded_file($file_tmp_path, $destination_path)) {
                                    if ($uploaded_file_path_db && $uploaded_file_path_db !== $new_file_name && file_exists(UPLOAD_DIR_BROADCASTER_ADS . $uploaded_file_path_db)) {
                                        unlink(UPLOAD_DIR_BROADCASTER_ADS . $uploaded_file_path_db);
                                    }
                                    $uploaded_file_path_db = $new_file_name;
                                    $success_message = "File materi iklan berhasil diunggah.";
                                } else {
                                    $error_message_page = "Gagal memindahkan file yang diunggah. Periksa izin tulis direktori.";
                                    error_log("Gagal memindahkan file ke: " . $destination_path);
                                }
                            } else {
                                $error_message_page = "Ukuran file terlalu besar (maks 50MB).";
                            }
                        } else {
                            $error_message_page = "Format file tidak diizinkan. Hanya: " . implode(', ', $allowed_extensions);
                        }
                    } elseif (empty($error_message_page) && !is_writable(UPLOAD_DIR_BROADCASTER_ADS)) {
                        $error_message_page = "Direktori upload tidak dapat ditulis. Hubungi administrator.";
                        error_log("Direktori upload tidak writable: " . UPLOAD_DIR_BROADCASTER_ADS);
                    }
                } elseif (isset($_FILES['ad_file_broadcaster']) && $_FILES['ad_file_broadcaster']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $error_message_page = "Terjadi kesalahan saat mengunggah file (Kode Error: " . $_FILES['ad_file_broadcaster']['error'] . ").";
                }

                if (empty($error_message_page)) {
                    $allowed_update_statuses = array_merge(array_keys($penyiar_actionable_statuses), [$penyiar_submitted_status]);
                    if (in_array($current_booking_details['status'], $allowed_update_statuses)) {
                        if ($uploaded_file_path_db) {
                            try {
                                $stmt_update_ad = $pdo->prepare(
                                    "UPDATE ad_bookings 
                                     SET ad_file_broadcaster = :file_path, broadcaster_notes = :notes, status = :status, updated_at = NOW() 
                                     WHERE id = :id AND assigned_penyiar_id = :penyiar_id"
                                );
                                $stmt_update_ad->execute([
                                    ':file_path' => $uploaded_file_path_db,
                                    ':notes' => $broadcaster_notes ?: null,
                                    ':status' => $penyiar_submitted_status,
                                    ':id' => $booking_id_form,
                                    ':penyiar_id' => $penyiar_id
                                ]);
                                if ($stmt_update_ad->rowCount() > 0) {
                                    $success_message = ($success_message ? $success_message . " " : "") . "Materi iklan dan status berhasil diperbarui. Menunggu konfirmasi pengguna.";
                                } else {
                                    $error_message_page = ($error_message_page ? $error_message_page . " " : "") . "Tidak ada perubahan dilakukan pada data iklan.";
                                }
                            } catch (PDOException $e) {
                                $error_message_page = "Gagal memperbarui database iklan: " . $e->getMessage();
                                error_log("Penyiar Dashboard - Submit Ad DB Error: " . $e->getMessage());
                            }
                        } else {
                             $error_message_page = "File materi iklan wajib ada atau diunggah untuk dikirim ke pengguna.";
                        }
                    } else {
                        $error_message_page = "Status pemesanan saat ini (".htmlspecialchars($current_booking_details['status']).") tidak valid untuk diupdate oleh Anda.";
                    }
                }
            }
        }
    }
}


// --- Ambil Data Awal untuk Dashboard Penyiar ---
try {
    // 1. Program Saat Ini
    $stmtProgram = $pdo->prepare( //
        "SELECT * FROM programs 
         WHERE penyiar_id = :penyiar_id AND day_of_week = :day_of_week 
         AND start_time <= :current_time AND end_time >= :current_time
         LIMIT 1" //
    );
    $stmtProgram->execute([ //
        ':penyiar_id' => $penyiar_id,  //
        ':day_of_week' => $currentDay,  //
        ':current_time' => $currentTime //
    ]);
    $currentProgram = $stmtProgram->fetch(); //

    // 2. Song Requests Pending
    $stmtRequests = $pdo->query( //
        "SELECT sr.*, u.username 
         FROM song_requests sr 
         JOIN users u ON sr.user_id = u.id 
         WHERE sr.status = 'pending' 
         ORDER BY sr.request_time ASC" //
    );
    $songRequests = $stmtRequests->fetchAll(); //

    // 3. Data 'Now Playing' Terakhir (untuk pre-fill form)
    $stmtNp = $pdo->prepare( //
        "SELECT song_title, artist_name, album_art_url, song_file_path 
         FROM now_playing_stream 
         WHERE penyiar_id = :penyiar_id AND is_active = 1 
         ORDER BY updated_at DESC 
         LIMIT 1" //
    );
    $stmtNp->execute([':penyiar_id' => $penyiar_id]); //
    $lastNowPlayingData = $stmtNp->fetch(); //

    // 4. Daftar Iklan yang Ditugaskan ke Penyiar Ini
    $stmt_ad_bookings = $pdo->prepare(
        "SELECT ab.*, u.username AS requester_name 
         FROM ad_bookings ab 
         JOIN users u ON ab.user_id = u.id 
         WHERE ab.assigned_penyiar_id = :current_penyiar_id 
           AND ab.status IN (:status_creation, :status_revision, :status_submitted_by_penyiar)
         ORDER BY ab.updated_at DESC"
    );
    $stmt_ad_bookings->execute([
        ':current_penyiar_id' => $penyiar_id,
        ':status_creation' => 'pending_broadcaster_creation',
        ':status_revision' => 'revision_needed_by_user',
        ':status_submitted_by_penyiar' => $penyiar_submitted_status
    ]);
    $ad_bookings_for_penyiar = $stmt_ad_bookings->fetchAll();

} catch (PDOException $e) {
    error_log("Penyiar Dashboard - Initial Data Load Error: " . $e->getMessage()); //
    $error_message_page = "Gagal memuat data dashboard utama. Silakan coba lagi nanti."; //
}

// Fungsi untuk mengambil daftar file musik (sudah ada di kode Anda)
function getMusicFiles($dir) { //
    $musicFiles = []; //
    if (is_dir($dir)) { //
        if ($handle = opendir($dir)) { //
            while (false !== ($file = readdir($handle))) { //
                if ($file !== '.' && $file !== '..') { //
                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION)); //
                    if (in_array($extension, ['mp3', 'wav', 'ogg', 'm4a', 'flac'])) { // Menambahkan flac
                        $musicFiles[] = $file; // Hanya simpan nama file
                    }
                }
            }
            closedir($handle); //
        }
    }
    sort($musicFiles);  //
    return $musicFiles; //
}

$musicLibraryPath = dirname(__DIR__) . '/assets/music/'; // Path fisik dari root proyek
if (!is_dir($musicLibraryPath)) { //
    $error_message_page .= " PENTING: Direktori musik ('" . htmlspecialchars($musicLibraryPath) . "') tidak ditemukan. Harap buat folder 'assets/music/' di root proyek dan letakkan file musik di sana."; //
} else {
    $availableMusicFiles = getMusicFiles($musicLibraryPath); //
    if (empty($availableMusicFiles)) { //
         $error_message_page .= " Info: Tidak ada file musik yang ditemukan di '" . htmlspecialchars($musicLibraryPath) . "'."; //
    }
}


include '../includes/header.php'; //
?>
<script>
    // Variabel JS ini dikirim dari PHP untuk digunakan di main.js atau script inline
    const currentPenyiarUsername = <?php echo json_encode($penyiarUsername); ?>; //
    const currentProgramIdForChat = <?php echo $currentProgram ? json_encode($currentProgram['id']) : 'null'; ?>; //
    // basePath sudah didefinisikan di header.php (misal: /elmira_radio)
    const musicBaseUrl = basePath + '/assets/music/';  //
</script>

<div class="penyiar-dashboard">
    <div class="sidebar">
        <h3>Penyiar Panel</h3>
        <ul>
            <li <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php') echo 'class="active"'; ?>><a href="<?php echo BASE_PATH_PREFIX; ?>/penyiar/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li <?php if (basename($_SERVER['PHP_SELF']) == 'schedule.php') echo 'class="active"'; ?>><a href="<?php echo BASE_PATH_PREFIX; ?>/penyiar/schedule.php"><i class="fas fa-calendar-alt"></i> Jadwal Saya</a></li>
            <li <?php if (basename($_SERVER['PHP_SELF']) == 'manage_ads.php') echo 'class="active"'; // Ganti nama file jika dashboard ini adalah manage_ads ?>><a href="<?php echo BASE_PATH_PREFIX; ?>/penyiar/manage_ads.php"><i class="fas fa-bullhorn"></i> Materi Iklan</a></li>
            <li><a href="<?php echo rtrim(BASE_PATH_PREFIX, '/'); ?>/includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h2>Dashboard Penyiar - Selamat Datang, <?php echo htmlspecialchars($penyiarUsername ?? 'Penyiar'); ?>!</h2>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success" style="margin-bottom: 15px;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message_page): ?>
            <div class="alert alert-error" style="margin-bottom: 15px;"><i class="fas fa-exclamation-circle"></i> <?php echo nl2br(htmlspecialchars($error_message_page)); ?></div>
        <?php endif; ?>

        <div class="current-show card-bg section-mb">
            <h3><i class="fas fa-broadcast-tower"></i> Acara Saat Ini</h3>
            <?php if ($currentProgram): ?>
                <div class="show-info">
                    <h4><?php echo htmlspecialchars($currentProgram['title']); ?></h4>
                    <p><strong>Waktu:</strong> <?php echo date('H:i', strtotime($currentProgram['start_time'])) . ' - ' . date('H:i', strtotime($currentProgram['end_time'])); ?></p>
                    <p><strong>Deskripsi:</strong> <?php echo nl2br(htmlspecialchars($currentProgram['description'])); ?></p>
                    <?php
                    // ... (logika listeners count tetap sama) ...
                    ?>
                </div>
            <?php else: ?>
                <p>Tidak ada acara yang dijadwalkan untuk Anda saat ini.</p>
            <?php endif; ?>
        </div>

        <div class="now-playing-form card-bg section-mb">
            <h3><i class="fas fa-compact-disc"></i> Update Info & Pilih Lagu "Now Playing"</h3>
            <form id="update-now-playing-form">
                <input type="hidden" name="action" value="update_now_playing">
                <?php if ($currentProgram): ?>
                    <input type="hidden" name="program_id" value="<?php echo htmlspecialchars($currentProgram['id']); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="np_song_file">Pilih File Lagu dari Library</label>
                    <select id="np_song_file" name="song_file_name">
                        <option value="">-- Pilih Lagu --</option>
                        <?php if (!empty($availableMusicFiles)): ?>
                            <?php foreach ($availableMusicFiles as $musicFile): ?>
                                <option value="<?php echo htmlspecialchars($musicFile); ?>" 
                                    <?php 
                                    $selected_file_name = isset($lastNowPlayingData['song_file_path']) ? basename($lastNowPlayingData['song_file_path']) : '';
                                    echo ($selected_file_name === $musicFile) ? 'selected' : ''; 
                                    ?>>
                                    <?php echo htmlspecialchars(pathinfo($musicFile, PATHINFO_FILENAME)); // Tampilkan nama file tanpa ekstensi ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>Tidak ada file musik di folder `assets/music/`.</option>
                        <?php endif; ?>
                    </select>
                     <small>File musik diambil dari: <code><?php echo htmlspecialchars(str_replace(dirname(__DIR__, 2), '', realpath($musicLibraryPath) ?: 'elmira_radio/assets/music/')); ?></code></small>
                </div>

                <div class="form-group">
                    <label for="np_song_title">Judul Lagu</label>
                    <input type="text" id="np_song_title" name="song_title" value="<?php echo htmlspecialchars($lastNowPlayingData['song_title'] ?? ''); ?>" required placeholder="Judul lagu yang sedang diputar">
                </div>
                <div class="form-group">
                    <label for="np_artist_name">Nama Artis</label>
                    <input type="text" id="np_artist_name" name="artist_name" value="<?php echo htmlspecialchars($lastNowPlayingData['artist_name'] ?? ''); ?>" placeholder="Nama penyanyi atau band">
                </div>
                <div class="form-group">
                    <label for="np_album_art_url">URL Gambar Album (Opsional)</label>
                    <input type="url" id="np_album_art_url" name="album_art_url" value="<?php echo htmlspecialchars($lastNowPlayingData['album_art_url'] ?? ''); ?>" placeholder="https://example.com/cover.jpg">
                </div>
                <button type="submit" class="btn-auth" id="submit-now-playing-btn" style="margin-top:10px;">
                    <i class="fas fa-sync-alt"></i> Update Info & Set "Now Playing"
                </button>
                <div id="np-form-feedback" style="margin-top:10px;"></div>
            </form>
        </div>
        
        <div class="ad-management-penyiar card-bg section-mb">
            <h3><i class="fas fa-bullhorn"></i> Tugas Materi Iklan (<?php echo count($ad_bookings_for_penyiar); ?>)</h3>
            <?php if (empty($ad_bookings_for_penyiar)): ?>
                <p>Tidak ada tugas pembuatan materi iklan untuk Anda saat ini.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Judul Iklan</th>
                                <th>Pemesan</th>
                                <th>Status Tugas</th>
                                <th>Naskah/Brief</th>
                                <th>File Anda</th>
                                <th>Catatan Anda</th>
                                <th>Revisi dari Pemesan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ad_bookings_for_penyiar as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['id']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['ad_title']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['requester_name'] ?? 'N/A'); ?></td>
                                    <td class="status-<?php echo htmlspecialchars(strtolower(str_replace('_', '-', $booking['status']))); ?>">
                                        <?php
                                        $current_status_display_ad = '';
                                        if ($booking['status'] === 'pending_broadcaster_creation') $current_status_display_ad = 'Baru Ditugaskan';
                                        elseif ($booking['status'] === 'revision_needed_by_user') $current_status_display_ad = 'Perlu Revisi';
                                        elseif ($booking['status'] === $penyiar_submitted_status) $current_status_display_ad = 'Menunggu Konfirmasi Pengguna';
                                        else $current_status_display_ad = ucfirst(str_replace('_', ' ', $booking['status']));
                                        echo htmlspecialchars($current_status_display_ad);
                                        ?>
                                    </td>
                                    <td>
                                        <button onclick="showModal('modal-ad-content-<?php echo $booking['id']; ?>')" class="btn-action btn-view-script" title="Lihat Naskah">
                                            <i class="fas fa-file-alt"></i> Lihat
                                        </button>
                                        <div id="modal-ad-content-<?php echo $booking['id']; ?>" class="modal">
                                            <div class="modal-content">
                                                <span class="close-button" onclick="closeModal('modal-ad-content-<?php echo $booking['id']; ?>')">&times;</span>
                                                <h4>Naskah Iklan: <?php echo htmlspecialchars($booking['ad_title']); ?></h4>
                                                <p class="preserve-whitespace"><?php echo nl2br(htmlspecialchars($booking['ad_content'])); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($booking['ad_file_broadcaster'])): ?>
                                            <a href="<?php echo UPLOAD_URL_BROADCASTER_ADS . htmlspecialchars($booking['ad_file_broadcaster']); ?>" target="_blank" download class="file-download-link" title="Download <?php echo htmlspecialchars($booking['ad_file_broadcaster']); ?>">
                                                <i class="fas fa-download"></i> <?php echo htmlspecialchars(substr($booking['ad_file_broadcaster'], 0, 15) . '...'); ?>
                                            </a>
                                        <?php else: ?><small>Belum ada</small><?php endif; ?>
                                    </td>
                                    <td class="preserve-whitespace"><?php echo nl2br(htmlspecialchars($booking['broadcaster_notes'] ?? '-')); ?></td>
                                    <td class="user-rejection-reason">
                                        <?php if ($booking['status'] === 'revision_needed_by_user' && !empty($booking['user_rejection_reason'])): ?>
                                            <?php echo nl2br(htmlspecialchars($booking['user_rejection_reason'])); ?>
                                        <?php elseif(!empty($booking['user_rejection_reason'])): ?>
                                             <small><i>(Revisi lalu: <?php echo nl2br(htmlspecialchars($booking['user_rejection_reason'])); ?>)</i></small>
                                        <?php else: ?> - <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <?php if (in_array($booking['status'], array_keys($penyiar_actionable_statuses)) || $booking['status'] === $penyiar_submitted_status ): ?>
                                            <button onclick="showModal('form-ad-modal-<?php echo $booking['id']; ?>')" class="btn-action btn-edit">
                                                <i class="fas fa-pencil-alt"></i> 
                                                <?php echo ($booking['status'] === $penyiar_submitted_status || !empty($booking['ad_file_broadcaster'])) ? 'Edit/Revisi Materi' : 'Proses Materi'; ?>
                                            </button>
                                            <div id="form-ad-modal-<?php echo $booking['id']; ?>" class="modal">
                                                <div class="modal-content form-container card-bg">
                                                    <span class="close-button" onclick="closeModal('form-ad-modal-<?php echo $booking['id']; ?>')">&times;</span>
                                                    <h5>Materi Iklan: <?php echo htmlspecialchars($booking['ad_title']); ?></h5>
                                                    <p>Status: <strong><?php echo htmlspecialchars($current_status_display_ad); ?></strong></p>
                                                    <?php if ($booking['status'] === 'revision_needed_by_user' && !empty($booking['user_rejection_reason'])): ?>
                                                        <div class="alert alert-warning"><strong>Revisi dari Pemesan:</strong><br><?php echo nl2br(htmlspecialchars($booking['user_rejection_reason'])); ?></div>
                                                    <?php endif; ?>
                                                    <form action="dashboard.php" method="POST" enctype="multipart/form-data"> <input type="hidden" name="action" value="submit_broadcaster_ad_material">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <div class="form-group">
                                                            <label for="ad_file_broadcaster_<?php echo $booking['id']; ?>">Unggah File Materi (MP3, WAV):</label>
                                                            <input type="file" name="ad_file_broadcaster" id="ad_file_broadcaster_<?php echo $booking['id']; ?>">
                                                            <?php if (!empty($booking['ad_file_broadcaster'])): ?>
                                                                <small>Saat ini: <a href="<?php echo UPLOAD_URL_BROADCASTER_ADS . htmlspecialchars($booking['ad_file_broadcaster']); ?>" target="_blank"><?php echo htmlspecialchars($booking['ad_file_broadcaster']); ?></a></small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="broadcaster_notes_<?php echo $booking['id']; ?>">Catatan Penyiar:</label>
                                                            <textarea name="broadcaster_notes" id="broadcaster_notes_<?php echo $booking['id']; ?>" rows="3"><?php echo htmlspecialchars($booking['broadcaster_notes'] ?? ''); ?></textarea>
                                                        </div>
                                                        <button type="submit" class="btn-auth">Simpan & Kirim ke Pengguna</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php else: ?><small>Tidak ada aksi.</small><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="song-requests card-bg section-mb">
            <h3><i class="fas fa-music"></i> Request Lagu Tertunda (<?php echo count($songRequests); ?>)</h3>
            <?php if (!empty($songRequests)): ?>
                <div class="table-responsive">
                    <table id="song-requests-table">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Judul Lagu</th>
                                <th>Artis</th>
                                <th>Dari</th>
                                <th>Pesan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($songRequests as $request): ?>
                                <tr data-request-id="<?php echo htmlspecialchars($request['id']); ?>">
                                    <td><?php echo date('H:i', strtotime($request['request_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($request['song_title']); ?></td>
                                    <td><?php echo htmlspecialchars($request['artist']); ?></td>
                                    <td><?php echo htmlspecialchars($request['username']); ?></td>
                                    <td class="preserve-whitespace"><?php echo !empty($request['message']) ? nl2br(htmlspecialchars($request['message'])) : '-'; ?></td>
                                    <td class="actions">
                                        <button class="btn-action btn-play action-request-btn" data-status="played" title="Tandai sebagai Diputar">
                                            <i class="fas fa-check"></i> Putar
                                        </button>
                                        <button class="btn-action btn-reject action-request-btn" data-status="rejected" title="Tolak Request">
                                            <i class="fas fa-times"></i> Tolak
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Tidak ada request lagu yang tertunda saat ini.</p>
            <?php endif; ?>
        </div>
        
        <div class="live-chat card-bg section-mb">
            <h3><i class="fas fa-comments"></i> Live Chat Pendengar</h3>
             <div class="chat-messages" id="penyiar-chat-messages" style="min-height: 150px; border: 1px solid var(--hover-color); padding:10px; overflow-y:auto; max-height:300px; background-color: var(--secondary-color, #f9f9f9);">
                <p class="login-prompt" style="text-align:center; color:#888;">Memuat pesan chat...</p>
            </div>
            <div class="chat-input" style="display:flex; margin-top:10px;">
                <input type="text" id="penyiar-chat-message-input" placeholder="Ketik balasan Anda..." autocomplete="off" style="flex-grow:1; margin-right:5px; padding:8px; border:1px solid var(--hover-color); border-radius:4px;">
                <button id="penyiar-send-chat-btn" class="btn-auth" style="padding:8px 12px;"><i class="fas fa-paper-plane"></i> Kirim</button>
            </div>
        </div>
    </div>
</div>
<style>
    /* ... (style yang sudah ada dari file Anda dan penambahan sebelumnya) ... */
    .penyiar-dashboard .card-bg {
        background-color: var(--card-bg, #fff); /* Ambil dari CSS utama jika ada */
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Shadow lebih halus */
    }
    .penyiar-dashboard .section-mb {
        margin-bottom: 25px;
    }
    .penyiar-dashboard h3 {
        color: var(--primary-color, #e74c3c); /* Ambil dari CSS utama */
        margin-top: 0; 
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--hover-color, #eee); /* Ambil dari CSS utama */
    }
    .actions .btn-action {
        margin: 2px;
        padding: 6px 10px;
        font-size: 0.85em; 
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    .actions .btn-play { background-color: var(--success-color, #28a745); }
    .actions .btn-reject { background-color: var(--error-color, #dc3545); }
    .actions .btn-play:hover { background-color: #218838; }
    .actions .btn-reject:hover { background-color: #c82333; }

    #song-requests-table td, #song-requests-table th,
    .ad-management-penyiar table td, .ad-management-penyiar table th {
        font-size: 0.9em; 
        padding: 8px; 
        vertical-align: middle;
    }
    .show-info p { margin: 0.7rem 0; }
    .now-playing-form label, .form-container.card-bg label { display: block; margin-bottom: .5rem; font-weight: 600;}
    .now-playing-form input[type="text"],
    .now-playing-form input[type="url"],
    .now-playing-form select,
    .form-container.card-bg input[type="file"],
    .form-container.card-bg textarea {
        width: 100%; /* Lebih baik gunakan calc(100% - padding*2) jika box-sizing belum border-box */
        box-sizing: border-box;
        padding: .7rem;
        margin-bottom: .8rem;
        border: 1px solid var(--hover-color, #ccc); /* Ambil dari CSS utama */
        border-radius: 4px;
        background-color: var(--secondary-color, #fff); /* Ambil dari CSS utama */
        color: var(--text-color, #333); /* Ambil dari CSS utama */
    }
     .now-playing-form input[type="file"] { padding: 4px; }

    .now-playing-form input:focus,
    .now-playing-form select:focus,
    .form-container.card-bg input:focus,
    .form-container.card-bg textarea:focus {
        border-color: var(--primary-color, #e74c3c); /* Ambil dari CSS utama */
        box-shadow: 0 0 0 2px rgba(231, 76, 60, 0.2);
    }
    #np_song_file + small { display: block; margin-top: -0.5rem; margin-bottom: 0.8rem; font-size: 0.85em; color: var(--text-secondary, #6c757d); }
    .alert { margin-bottom: 15px; }
    .btn-view-script { background-color: #17a2b8; color: white; padding: 3px 6px; font-size: 0.8em; border:none; border-radius:3px; cursor:pointer; }
    .btn-view-script:hover { background-color: #138496; }
    .preserve-whitespace { white-space: pre-wrap; }
    .user-rejection-reason { color: var(--error-color, #dc3545); font-style: italic; font-size:0.9em; }
    .file-download-link { text-decoration: none; color: #007bff; }
    .file-download-link:hover { text-decoration: underline; }

    /* Style tambahan untuk konsistensi tombol pada dashboard penyiar */
    .btn-edit { background-color: var(--primary-button-color, #007bff); }
    .btn-edit:hover { background-color: var(--primary-button-hover-color, #0056b3); }

    /* Penyesuaian untuk modal dan form di dalamnya (jika belum ter-cover CSS utama) */
    .modal-content h5 { margin-top:0; color: var(--primary-color, #e74c3c); }
    .modal-content .form-group { margin-bottom: 1rem; }
    .modal-content .alert-warning { font-size: 0.9em; }

</style>

<?php include '../includes/footer.php'; // ?>