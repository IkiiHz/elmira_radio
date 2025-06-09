<?php
// File: penyiar/manage_ads.php

require_once '../includes/config.php'; // Memuat konfigurasi dan helper

redirectIfNotPenyiar('../login.php?error=Akses+penyiar+ditolak.');
$penyiar_id = getCurrentUserId();

$success_message = '';
$error_message = '';

// Status yang relevan untuk Penyiar dan aksi yang bisa dilakukan
$penyiar_actionable_statuses = [
    'pending_broadcaster_creation' => 'Baru Ditugaskan',
    'revision_needed_by_user' => 'Perlu Revisi dari Pengguna'
];
// Status yang di-set oleh penyiar setelah selesai mengunggah/memproses materi
$penyiar_submitted_status = 'pending_user_confirmation';


// --- PROSES AKSI FORM (Upload Materi, Update Catatan) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $booking_id = $_POST['booking_id'] ?? null;

    if (!$booking_id || !ctype_digit((string)$booking_id)) {
        $error_message = "ID Pemesanan Iklan tidak valid.";
    } else {
        // Ambil detail booking untuk validasi status sebelum update
        // Pastikan penyiar yang sedang login adalah yang ditugaskan (assigned_penyiar_id)
        $stmt_check_booking = $pdo->prepare("SELECT status, ad_file_broadcaster FROM ad_bookings WHERE id = :id AND assigned_penyiar_id = :penyiar_id");
        $stmt_check_booking->execute([':id' => $booking_id, ':penyiar_id' => $penyiar_id]);
        $current_booking_details = $stmt_check_booking->fetch();

        if (!$current_booking_details) {
            $error_message = "Pemesanan iklan tidak ditemukan atau tidak ditugaskan untuk Anda.";
        } else {
            if ($_POST['action'] === 'submit_broadcaster_ad') {
                $broadcaster_notes = trim($_POST['broadcaster_notes'] ?? '');
                $uploaded_file_path_db = $current_booking_details['ad_file_broadcaster'] ?? null;

                if (isset($_FILES['ad_file_broadcaster']) && $_FILES['ad_file_broadcaster']['error'] === UPLOAD_ERR_OK) {
                    if (!file_exists(UPLOAD_DIR_BROADCASTER_ADS)) {
                        if (!mkdir(UPLOAD_DIR_BROADCASTER_ADS, 0775, true)) {
                            $error_message = "Gagal membuat direktori upload: " . UPLOAD_DIR_BROADCASTER_ADS;
                            error_log("Gagal membuat direktori broadcaster ads: " . UPLOAD_DIR_BROADCASTER_ADS);
                        }
                    }

                    if (empty($error_message) && is_writable(UPLOAD_DIR_BROADCASTER_ADS)) {
                        $file_tmp_path = $_FILES['ad_file_broadcaster']['tmp_name'];
                        $file_name = basename($_FILES['ad_file_broadcaster']['name']);
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $allowed_extensions = ['mp3', 'wav', 'ogg', 'aac', 'm4a', 'flac'];

                        if (in_array($file_ext, $allowed_extensions)) {
                            if ($_FILES['ad_file_broadcaster']['size'] < 50 * 1024 * 1024) { // Maks 50MB
                                $new_file_name = 'ad_bcast_' . $booking_id . '_' . time() . '.' . $file_ext;
                                $destination_path = UPLOAD_DIR_BROADCASTER_ADS . $new_file_name;

                                if (move_uploaded_file($file_tmp_path, $destination_path)) {
                                    if ($uploaded_file_path_db && $uploaded_file_path_db !== $new_file_name && file_exists(UPLOAD_DIR_BROADCASTER_ADS . $uploaded_file_path_db)) {
                                        unlink(UPLOAD_DIR_BROADCASTER_ADS . $uploaded_file_path_db);
                                    }
                                    $uploaded_file_path_db = $new_file_name;
                                    $success_message = "File berhasil diunggah.";
                                } else {
                                    $error_message = "Gagal memindahkan file yang diunggah. Periksa izin tulis direktori.";
                                    error_log("Gagal memindahkan file broadcaster ke: " . $destination_path);
                                }
                            } else {
                                $error_message = "Ukuran file terlalu besar (maks 50MB).";
                            }
                        } else {
                            $error_message = "Format file tidak diizinkan. Hanya: " . implode(', ', $allowed_extensions);
                        }
                    } elseif (empty($error_message) && !is_writable(UPLOAD_DIR_BROADCASTER_ADS)) {
                        $error_message = "Direktori upload tidak dapat ditulis. Hubungi administrator.";
                        error_log("Direktori upload broadcaster tidak writable: " . UPLOAD_DIR_BROADCASTER_ADS);
                    }
                } elseif (isset($_FILES['ad_file_broadcaster']) && $_FILES['ad_file_broadcaster']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $error_message = "Terjadi kesalahan saat mengunggah file (Kode Error: " . $_FILES['ad_file_broadcaster']['error'] . ").";
                }

                if (empty($error_message)) {
                    $allowed_update_statuses = array_merge(array_keys($penyiar_actionable_statuses), [$penyiar_submitted_status]);

                    if (in_array($current_booking_details['status'], $allowed_update_statuses)) {
                        // File materi wajib ada jika penyiar men-submit ke pengguna, KECUALI jika hanya mengupdate catatan pada status yang sudah 'pending_user_confirmation'
                        if ($uploaded_file_path_db || ($current_booking_details['status'] === $penyiar_submitted_status && !empty($broadcaster_notes) && !isset($_FILES['ad_file_broadcaster']['name']))) {
                            try {
                                $stmt = $pdo->prepare(
                                    "UPDATE ad_bookings 
                                     SET ad_file_broadcaster = :file_path, broadcaster_notes = :notes, status = :status, updated_at = NOW() 
                                     WHERE id = :id AND assigned_penyiar_id = :penyiar_id"
                                );
                                $stmt->execute([
                                    ':file_path' => $uploaded_file_path_db, // Ini akan berisi nama file lama jika tidak ada upload baru
                                    ':notes' => $broadcaster_notes ?: null,
                                    ':status' => $penyiar_submitted_status,
                                    ':id' => $booking_id,
                                    ':penyiar_id' => $penyiar_id
                                ]);
                                if ($stmt->rowCount() > 0) {
                                    $success_message = ($success_message ? $success_message . " " : "") . "Detail iklan dan status berhasil diperbarui menjadi 'Menunggu Konfirmasi Pengguna'.";
                                } else {
                                    // Jika tidak ada file baru dan catatan juga tidak berubah, mungkin tidak ada rowCount
                                    // Cek apakah ini hanya update notes tanpa file baru
                                    if (empty($_FILES['ad_file_broadcaster']['name']) && $broadcaster_notes === ($current_booking_details['broadcaster_notes'] ?? null)) {
                                         $success_message = ($success_message ? $success_message . " " : "") . "Tidak ada perubahan data terdeteksi.";
                                    } else {
                                         $error_message = ($error_message ? $error_message . " " : "") . "Tidak ada perubahan dilakukan atau data tidak cocok.";
                                    }
                                }
                            } catch (PDOException $e) {
                                $error_message = "Gagal memperbarui pemesanan iklan: " . $e->getMessage();
                                error_log("Penyiar Manage Ads - Submit Ad DB Error: " . $e->getMessage());
                            }
                        } else {
                             $error_message = "File materi iklan wajib ada atau diunggah untuk menyelesaikan tugas ini.";
                        }
                    } else {
                        $error_message = "Status pemesanan saat ini (".htmlspecialchars($current_booking_details['status']).") tidak memungkinkan Anda melakukan update.";
                    }
                }
            }
        }
    }
}


// Ambil pemesanan iklan yang relevan untuk penyiar
try {
    $stmt_bookings = $pdo->prepare(
        "SELECT ab.*, u.username AS requester_name 
         FROM ad_bookings ab 
         JOIN users u ON ab.user_id = u.id 
         WHERE ab.assigned_penyiar_id = :current_penyiar_id 
           AND ab.status IN (:status_creation, :status_revision, :status_submitted_by_penyiar)
         ORDER BY ab.updated_at DESC"
    );
    $stmt_bookings->execute([
        ':current_penyiar_id' => $penyiar_id,
        ':status_creation' => 'pending_broadcaster_creation',
        ':status_revision' => 'revision_needed_by_user',
        ':status_submitted_by_penyiar' => $penyiar_submitted_status
    ]);
    $bookings_list = $stmt_bookings->fetchAll();
} catch (PDOException $e) {
    $bookings_list = [];
    $error_message = "Gagal memuat daftar pemesanan iklan: " . $e->getMessage();
    error_log("Penyiar Manage Ads - Load Bookings Error: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="penyiar-dashboard"> <div class="sidebar">
        <h3>Panel Penyiar</h3>
        <ul>
            <li><a href="<?php echo BASE_PATH_PREFIX; ?>/penyiar/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard Penyiar</a></li>
            <li><a href="<?php echo BASE_PATH_PREFIX; ?>/penyiar/schedule.php"><i class="fas fa-calendar-alt"></i> Jadwal Siaran Saya</a></li>
            <li class="active"><a href="<?php echo BASE_PATH_PREFIX; ?>/penyiar/manage_ads.php"><i class="fas fa-bullhorn"></i> Kelola Iklan Ditugaskan</a></li>
            <li><a href="<?php echo rtrim(BASE_PATH_PREFIX, '/'); ?>/includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h2>Kelola Iklan Ditugaskan</h2>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <h3 style="margin-top: 30px;">Daftar Tugas Pembuatan Iklan</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Judul Iklan</th>
                        <th>Pemesan</th>
                        <th>Status Tugas</th>
                        <th>Naskah/Brief</th>
                        <th>File Pemesan</th> <th>Jadwal Diinginkan</th>
                        <th>File Materi Anda</th>
                        <th>Catatan Anda</th>
                        <th>Catatan/Revisi Pemesan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings_list)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center;">Tidak ada tugas iklan yang perlu diproses atau menunggu konfirmasi.</td> </tr>
                    <?php else: ?>
                        <?php foreach ($bookings_list as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['id']); ?></td>
                                <td><?php echo htmlspecialchars($booking['ad_title']); ?></td>
                                <td><?php echo htmlspecialchars($booking['requester_name'] ?? 'N/A'); ?></td>
                                <td class="status-<?php echo htmlspecialchars(strtolower(str_replace('_', '-', $booking['status']))); ?>">
                                    <?php
                                    $current_status_display = '';
                                    if ($booking['status'] === 'pending_broadcaster_creation') $current_status_display = 'Baru Ditugaskan';
                                    elseif ($booking['status'] === 'revision_needed_by_user') $current_status_display = 'Perlu Revisi';
                                    elseif ($booking['status'] === $penyiar_submitted_status) $current_status_display = 'Menunggu Konfirmasi Pengguna';
                                    else $current_status_display = ucfirst(str_replace('_', ' ', $booking['status']));
                                    echo htmlspecialchars($current_status_display);
                                    ?>
                                </td>
                                <td>
                                    <button onclick="showModal('modal-content-<?php echo $booking['id']; ?>')" class="btn-action btn-view-script" title="Lihat Naskah">
                                        <i class="fas fa-file-alt"></i> Lihat
                                    </button>
                                    <div id="modal-content-<?php echo $booking['id']; ?>" class="modal">
                                        <div class="modal-content">
                                            <span class="close-button" onclick="closeModal('modal-content-<?php echo $booking['id']; ?>')">&times;</span>
                                            <h4>Naskah Iklan: <?php echo htmlspecialchars($booking['ad_title']); ?></h4>
                                            <p class="preserve-whitespace"><?php echo nl2br(htmlspecialchars($booking['ad_content'])); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($booking['ad_file_listener'])): ?>
                                        <a href="<?php echo UPLOAD_URL_LISTENER_ADS . htmlspecialchars($booking['ad_file_listener']); ?>" target="_blank" download class="file-download-link" title="Download File dari Pemesan: <?php echo htmlspecialchars($booking['ad_file_listener']); ?>">
                                            <i class="fas fa-download"></i> <?php echo htmlspecialchars(substr($booking['ad_file_listener'], 0, 15) . (strlen($booking['ad_file_listener']) > 15 ? '...' : '')); ?>
                                        </a>
                                    <?php else: ?>
                                        <small>-</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($booking['desired_schedule'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($booking['ad_file_broadcaster'])): ?>
                                        <a href="<?php echo UPLOAD_URL_BROADCASTER_ADS . htmlspecialchars($booking['ad_file_broadcaster']); ?>" target="_blank" download class="file-download-link" title="Download <?php echo htmlspecialchars($booking['ad_file_broadcaster']); ?>">
                                            <i class="fas fa-download"></i> <?php echo htmlspecialchars(substr($booking['ad_file_broadcaster'], 0, 20) . (strlen($booking['ad_file_broadcaster']) > 20 ? '...' : '')); ?>
                                        </a>
                                    <?php else: ?>
                                        <small>Belum ada</small>
                                    <?php endif; ?>
                                </td>
                                <td class="preserve-whitespace"><?php echo nl2br(htmlspecialchars($booking['broadcaster_notes'] ?? '-')); ?></td>
                                <td class="user-rejection-reason">
                                    <?php if ($booking['status'] === 'revision_needed_by_user' && !empty($booking['user_rejection_reason'])): ?>
                                        <?php echo nl2br(htmlspecialchars($booking['user_rejection_reason'])); ?>
                                    <?php elseif(!empty($booking['user_rejection_reason'])): ?>
                                         <small><i>(Revisi sebelumnya: <?php echo nl2br(htmlspecialchars($booking['user_rejection_reason'])); ?>)</i></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <?php if (in_array($booking['status'], array_keys($penyiar_actionable_statuses)) || $booking['status'] === $penyiar_submitted_status ): ?>
                                        <button onclick="showModal('form-modal-<?php echo $booking['id']; ?>')" class="btn-action btn-edit" title="<?php echo ($booking['status'] === $penyiar_submitted_status || !empty($booking['ad_file_broadcaster'])) ? 'Edit Materi atau Catatan' : 'Proses dan Upload Materi'; ?>">
                                            <i class="fas fa-pencil-alt"></i> 
                                            <?php echo ($booking['status'] === $penyiar_submitted_status || !empty($booking['ad_file_broadcaster'])) ? 'Edit/Revisi' : 'Proses'; ?>
                                        </button>
                                        
                                        <div id="form-modal-<?php echo $booking['id']; ?>" class="modal">
                                            <div class="modal-content form-container card-bg"> <span class="close-button" onclick="closeModal('form-modal-<?php echo $booking['id']; ?>')">&times;</span>
                                                <h4>Form Materi Iklan: <?php echo htmlspecialchars($booking['ad_title']); ?></h4>
                                                <p>Status saat ini: <strong><?php echo htmlspecialchars($current_status_display); ?></strong></p>
                                                <?php if ($booking['status'] === 'revision_needed_by_user' && !empty($booking['user_rejection_reason'])): ?>
                                                    <div class="alert alert-warning"><strong>Permintaan Revisi dari Pemesan:</strong><br><?php echo nl2br(htmlspecialchars($booking['user_rejection_reason'])); ?></div>
                                                <?php endif; ?>

                                                <form action="manage_ads.php" method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="action" value="submit_broadcaster_ad">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">

                                                    <div class="form-group">
                                                        <label for="ad_file_broadcaster_<?php echo $booking['id']; ?>">Unggah File Materi Iklan (MP3, WAV, dll):</label>
                                                        <input type="file" name="ad_file_broadcaster" id="ad_file_broadcaster_<?php echo $booking['id']; ?>">
                                                        <?php if (!empty($booking['ad_file_broadcaster'])): ?>
                                                            <small>File saat ini: <a href="<?php echo UPLOAD_URL_BROADCASTER_ADS . htmlspecialchars($booking['ad_file_broadcaster']); ?>" target="_blank" download><?php echo htmlspecialchars($booking['ad_file_broadcaster']); ?></a>. Unggah file baru akan menggantikan file ini.</small>
                                                        <?php else: ?>
                                                            <small>Belum ada file materi yang diunggah untuk iklan ini.</small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="broadcaster_notes_<?php echo $booking['id']; ?>">Catatan Penyiar (opsional, misal detail produksi, saran, dll.):</label>
                                                        <textarea name="broadcaster_notes" id="broadcaster_notes_<?php echo $booking['id']; ?>" rows="4" placeholder="Tulis catatan Anda di sini..."><?php echo htmlspecialchars($booking['broadcaster_notes'] ?? ''); ?></textarea>
                                                    </div>
                                                    <p><small>Setelah menyimpan, status akan diubah menjadi "Menunggu Konfirmasi Pengguna" dan pemesan akan dapat melihat/mendownload materi ini.</small></p>
                                                    <button type="submit" class="btn-auth">Simpan & Kirim ke Pengguna</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php elseif($booking['status'] !== $penyiar_submitted_status ): ?> 
                                        <small>Menunggu status lain.</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    /* Style untuk modal (bisa dipindahkan ke CSS global jika dipakai di banyak tempat) */
    .modal { 
        display: none; 
        position: fixed; 
        z-index: 1000; 
        left: 0; 
        top: 0; 
        width: 100%; 
        height: 100%; 
        overflow: auto; 
        background-color: rgba(0,0,0,0.75); /* Latar belakang modal lebih gelap */
        padding-top: 50px; /* Disesuaikan agar modal tidak terlalu ke atas */
    }
    .modal-content { 
        background-color: var(--card-bg); /* Menggunakan variabel --card-bg untuk background modal */
        color: var(--text-color); /* Menggunakan variabel --text-color untuk teks di modal */
        margin: 5% auto; 
        padding: 25px; 
        border: 1px solid var(--border-color); /* Border sesuai tema */
        width: 80%; 
        max-width: 700px; 
        border-radius: 8px; 
        position: relative; 
        box-shadow: var(--shadow-lg); /* Shadow sesuai tema */
        animation-name: animatetop; 
        animation-duration: 0.4s;
    }
    @keyframes animatetop { 
        from {top: -300px; opacity: 0} 
        to {top: 0; opacity: 1} 
    }
    .close-button { 
        color: var(--text-secondary); /* Warna tombol close disesuaikan */
        float: right; 
        font-size: 28px; 
        font-weight: bold; 
        position: absolute; 
        top: 15px; /* Disesuaikan agar pas */
        right: 20px; /* Disesuaikan agar pas */
    }
    .close-button:hover, .close-button:focus { 
        color: var(--primary-color); /* Warna hover tombol close */
        text-decoration: none; 
        cursor: pointer; 
    }
    .preserve-whitespace { 
        white-space: pre-wrap; 
    }
    .user-rejection-reason { 
        color: var(--error-color); /* Menggunakan variabel --error-color */
        font-style: italic; 
        font-size: 0.9em; /* Sedikit lebih kecil */
    }
    .alert.alert-warning { 
        padding: 10px 15px; /* Padding disesuaikan */
        background-color: rgba(255, 193, 7, 0.15); /* Background warning lebih transparan dan cerah */
        border: 1px solid #ffc107; 
        color: #ffc107; /* Teks warning kuning */
        margin-bottom: 15px; 
        border-radius: .35rem; /* Radius border disamakan dengan badge */
        font-size: 0.9em;
    }
    .alert.alert-warning strong {
        color: #e0a800; /* Warna bold lebih gelap */
    }


    /* Tombol Aksi di Tabel */
    .actions .btn-action { 
        margin-right: 5px; 
        padding: 6px 10px; 
        text-decoration: none; 
        border: none; 
        cursor: pointer; 
        border-radius: 6px; /* Dibuat lebih rounded */
        color: var(--accent-color-text); 
        font-size: 0.85em; /* Ukuran font disesuaikan */
        transition: all 0.2s ease-in-out;
    }
    .actions .btn-action:hover {
        opacity: 0.85;
        transform: translateY(-1px);
    }
    .btn-edit { background-color: var(--primary-color); } 
    .btn-edit:hover { background-color: #FF7043; }
    .btn-view-script { background-color: var(--accent-color-bright); color: var(--secondary-color); } 
    .btn-view-script:hover { opacity: 0.8; }
    
    .file-download-link { 
        text-decoration: none; 
        color: var(--accent-color-bright); 
        font-size: 0.9em;
    }
    .file-download-link:hover { 
        text-decoration: underline; 
        color: var(--primary-color);
    }
    .file-download-link i {
        margin-right: 4px;
    }

    /* Status Styling */
    .status-pending-broadcaster-creation { color: #fd7e14; font-weight: bold; }
    .status-revision-needed-by-user { color: var(--error-color); font-weight: bold; }
    .status-pending-user-confirmation { color: var(--success-color); font-weight: bold; } /* Diubah agar lebih positif */
    
    /* Form di Modal */
    .form-container.card-bg { /* Sudah ada di style.css global, ini hanya memastikan */
        padding: 25px; /* Disesuaikan paddingnya */
        background-color: var(--card-bg); 
        border-radius: 8px;
    }
    .form-container.card-bg h4, 
    .form-container.card-bg p { 
        color: var(--text-color);
        margin-bottom: 15px; /* Jarak antar elemen teks */
    }
     .form-container.card-bg p small { /* Teks kecil di form modal */
        color: var(--text-secondary);
        font-size: 0.85em;
        line-height: 1.4;
    }
    .form-container.card-bg label { 
        display: block; 
        margin-bottom: 6px; /* Jarak label ke input lebih dekat */
        color: var(--text-color); 
        font-weight: 600; 
        font-size: 0.95em;
    }
    .form-container.card-bg .form-group { /* Mengurangi margin bawah default .form-group jika ada di dalam modal */
        margin-bottom: 1.2rem;
    }
    .form-container.card-bg input[type="file"],
    .form-container.card-bg textarea { 
        width: 100%; 
        box-sizing: border-box;
        padding: 10px; 
        border: 1px solid var(--border-color); 
        border-radius: 5px; 
        background-color: var(--secondary-color); 
        color: var(--text-color); 
        font-size: 1rem; 
        /* margin-bottom: 15px; /* Sudah dihandle .form-group */
    }
    .form-container.card-bg input[type="file"] { 
        padding: 8px; 
        background-color: var(--hover-color); /* Sedikit beda background untuk input file */
    }
    .form-container.card-bg textarea { 
        min-height: 100px; /* Tinggi textarea disesuaikan */
    }
    .form-container.card-bg input:focus,
    .form-container.card-bg textarea:focus { 
        border-color: var(--primary-color); 
        box-shadow: 0 0 0 3px var(--focus-ring-color); 
    }
    .form-container.card-bg .btn-auth { 
        background-color: var(--primary-color); 
        color: var(--accent-color-text); 
        padding: 0.8rem 1.5rem; /* Padding disesuaikan */
        border: none; 
        border-radius: 6px; /* Disesuaikan */
        cursor: pointer; 
        font-size: 1rem; 
        font-weight: 600; 
        transition: background-color 0.2s ease;
    }
    .form-container.card-bg .btn-auth:hover { 
        background-color: #FF7043; 
    }
    .form-container.card-bg .form-group small { /* Teks kecil di bawah input field */
        display: block; 
        margin-top: 5px; /* Disesuaikan agar tidak terlalu mepet */
        margin-bottom: 0; 
        font-size: 0.8em; 
        color: var(--text-secondary); 
    }

    /* Penyesuaian Sidebar jika menggunakan kelas penyiar-dashboard */
    .penyiar-dashboard .sidebar h3 {
        font-size: 1.4rem; /* Sedikit lebih kecil */
    }
    .penyiar-dashboard .sidebar li a {
        padding: 0.8rem 1rem; /* Padding link sidebar */
        font-size: 0.95rem;
    }
    .penyiar-dashboard .sidebar li a i {
        font-size: 1rem;
    }

</style>

<script>
    function showModal(modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = "block";
        }
    }
    function closeModal(modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = "none";
        }
    }
    // Event listener untuk menutup modal jika user klik di luar area modal-content
    window.addEventListener('click', function(event) {
        document.querySelectorAll('.modal').forEach(function(modal){
            if (event.target == modal) {
                modal.style.display = "none";
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>