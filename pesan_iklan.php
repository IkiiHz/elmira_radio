<?php
// File: pesan_iklan.php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once 'includes/config.php';

redirectIfNotLoggedIn('login.php?error=Silakan+login+untuk+mengakses+halaman+ini.');

$user_id = getCurrentUserId();
$errors = [];
$success_message = '';

// --- Definisikan Konstanta Path Upload untuk Listener ---
if (!defined('UPLOAD_DIR_LISTENER_ADS')) {
    define('UPLOAD_DIR_LISTENER_ADS', __DIR__ . '/uploads/listener_ads/');
}
if (!defined('UPLOAD_URL_LISTENER_ADS')) {
    define('UPLOAD_URL_LISTENER_ADS', rtrim(BASE_PATH_PREFIX, '/') . '/uploads/listener_ads/');
}
// Path upload bukti pembayaran sudah didefinisikan di config.php


// --- Logika Penanganan Permintaan POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'submit_ad_booking_form') {
        $ad_title = trim($_POST['ad_title'] ?? '');
        $ad_content = trim($_POST['ad_content'] ?? '');
        $desired_schedule = trim($_POST['desired_schedule'] ?? '');
        $uploaded_listener_file_name = null;

        if (empty($ad_title)) $errors[] = "Judul iklan tidak boleh kosong.";
        if (empty($ad_content)) $errors[] = "Isi/naskah iklan tidak boleh kosong.";
        // Validasi lain tetap...

        // --- Logika Upload File dari Listener ---
        if (isset($_FILES['ad_file_listener']) && $_FILES['ad_file_listener']['error'] === UPLOAD_ERR_OK) {
            // ... (logika upload file listener tetap sama) ...
            // Pastikan direktori ada dan writable
            if (!file_exists(UPLOAD_DIR_LISTENER_ADS)) {
                if (!mkdir(UPLOAD_DIR_LISTENER_ADS, 0775, true)) {
                    $errors[] = "Gagal membuat direktori upload listener: " . UPLOAD_DIR_LISTENER_ADS;
                }
            }
            if (empty($errors) && is_writable(UPLOAD_DIR_LISTENER_ADS)) {
                $file_tmp_path = $_FILES['ad_file_listener']['tmp_name'];
                $file_name = basename($_FILES['ad_file_listener']['name']);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_extensions = ['mp3', 'wav', 'ogg', 'aac', 'm4a', 'flac', 'pdf', 'doc', 'docx', 'txt'];

                if (in_array($file_ext, $allowed_extensions)) {
                    if ($_FILES['ad_file_listener']['size'] < 50 * 1024 * 1024) { // 50MB
                        $new_file_name = 'ad_listener_' . $user_id . '_' . time() . '.' . $file_ext;
                        $destination_path = UPLOAD_DIR_LISTENER_ADS . $new_file_name;
                        if (move_uploaded_file($file_tmp_path, $destination_path)) {
                            $uploaded_listener_file_name = $new_file_name;
                        } else {
                            $errors[] = "Gagal memindahkan file listener yang diunggah.";
                        }
                    } else {
                        $errors[] = "Ukuran file listener terlalu besar (maks 50MB).";
                    }
                } else {
                    $errors[] = "Format file listener tidak diizinkan.";
                }
            } elseif(empty($errors)) { // Tambah kondisi jika !is_writable
                $errors[] = "Direktori upload listener tidak dapat ditulis.";
            }
        } elseif (isset($_FILES['ad_file_listener']) && $_FILES['ad_file_listener']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = "Terjadi kesalahan saat mengunggah file listener (Error: " . $_FILES['ad_file_listener']['error'] . ").";
        }
        // --- Akhir Logika Upload File Listener ---

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO ad_bookings (user_id, ad_title, ad_content, desired_schedule, ad_file_listener, status, created_at, updated_at)
                     VALUES (:user_id, :ad_title, :ad_content, :desired_schedule, :ad_file_listener, 'pending_payment', NOW(), NOW())" // STATUS DIUBAH
                );
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':ad_title' => $ad_title,
                    ':ad_content' => $ad_content,
                    ':desired_schedule' => $desired_schedule ?: null,
                    ':ad_file_listener' => $uploaded_listener_file_name
                ]);
                $lastBookingId = $pdo->lastInsertId();
                $success_message = "Pemesanan iklan Anda telah berhasil dikirim. Silakan lakukan pembayaran dan konfirmasi di bawah (ID Pesanan: #$lastBookingId).";
                $_POST = []; 
            } catch (PDOException $e) {
                error_log("Pesan Iklan - Ad booking insert error: " . $e->getMessage());
                $errors[] = "Gagal menyimpan data pemesanan iklan Anda ke database.";
                if ($uploaded_listener_file_name && file_exists(UPLOAD_DIR_LISTENER_ADS . $uploaded_listener_file_name)) {
                    unlink(UPLOAD_DIR_LISTENER_ADS . $uploaded_listener_file_name);
                }
            }
        }
    } elseif ($action === 'user_confirm_ad_payment') { // AKSI BARU UNTUK KONFIRMASI PEMBAYARAN
        $booking_id_payment = filter_input(INPUT_POST, 'payment_booking_id', FILTER_VALIDATE_INT);
        $user_payment_notes = trim($_POST['user_payment_notes'] ?? '');
        $uploaded_payment_proof_filename = null;

        if (!$booking_id_payment) {
            $errors[] = "ID Pemesanan untuk konfirmasi pembayaran tidak valid.";
        }

        // --- Logika Upload File Bukti Pembayaran ---
        if (empty($errors) && isset($_FILES['payment_proof_file']) && $_FILES['payment_proof_file']['error'] === UPLOAD_ERR_OK) {
            if (!file_exists(UPLOAD_DIR_PAYMENT_PROOFS)) {
                if (!mkdir(UPLOAD_DIR_PAYMENT_PROOFS, 0775, true)) {
                    $errors[] = "Gagal membuat direktori upload bukti pembayaran: " . UPLOAD_DIR_PAYMENT_PROOFS;
                }
            }
            if (empty($errors) && is_writable(UPLOAD_DIR_PAYMENT_PROOFS)) {
                $file_tmp_path = $_FILES['payment_proof_file']['tmp_name'];
                $file_name = basename($_FILES['payment_proof_file']['name']);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_extensions_proof = ['jpg', 'jpeg', 'png', 'pdf'];

                if (in_array($file_ext, $allowed_extensions_proof)) {
                    if ($_FILES['payment_proof_file']['size'] < 5 * 1024 * 1024) { // Maks 5MB untuk bukti
                        $new_file_name = 'proof_booking_' . $booking_id_payment . '_' . time() . '.' . $file_ext;
                        $destination_path = UPLOAD_DIR_PAYMENT_PROOFS . $new_file_name;
                        if (move_uploaded_file($file_tmp_path, $destination_path)) {
                            $uploaded_payment_proof_filename = $new_file_name;
                        } else {
                            $errors[] = "Gagal memindahkan file bukti pembayaran yang diunggah.";
                        }
                    } else {
                        $errors[] = "Ukuran file bukti pembayaran terlalu besar (maks 5MB).";
                    }
                } else {
                    $errors[] = "Format file bukti pembayaran tidak diizinkan (jpg, png, pdf).";
                }
            } elseif(empty($errors)) {
                $errors[] = "Direktori upload bukti pembayaran tidak dapat ditulis.";
            }
        } elseif (empty($errors) && isset($_FILES['payment_proof_file']) && $_FILES['payment_proof_file']['error'] !== UPLOAD_ERR_NO_FILE) {
             $errors[] = "Terjadi kesalahan saat mengunggah file bukti pembayaran (Error: " . $_FILES['payment_proof_file']['error'] . ").";
        }
        // --- Akhir Logika Upload Bukti ---

        if(empty($errors) && !$uploaded_payment_proof_filename){ // Jika tidak ada error lain dan bukti wajib diupload
            // $errors[] = "Bukti pembayaran wajib diunggah."; // Hapus jika bukti opsional
        }


        if (empty($errors)) {
            try {
                $stmtCheck = $pdo->prepare("SELECT id, status FROM ad_bookings WHERE id = :booking_id AND user_id = :user_id");
                $stmtCheck->execute([':booking_id' => $booking_id_payment, ':user_id' => $user_id]);
                $booking = $stmtCheck->fetch();

                if (!$booking) {
                    $errors[] = 'Pemesanan iklan tidak ditemukan atau Anda tidak memiliki hak akses.';
                } elseif ($booking['status'] !== 'pending_payment') {
                    $errors[] = 'Status pemesanan saat ini (' . htmlspecialchars($booking['status']) . ') tidak memungkinkan konfirmasi pembayaran.';
                } else {
                    $stmtUpdate = $pdo->prepare(
                        "UPDATE ad_bookings 
                         SET status = 'pending_admin_confirmation', 
                             payment_proof_file = :payment_proof_file, 
                             user_payment_notes = :user_payment_notes, 
                             updated_at = NOW() 
                         WHERE id = :booking_id"
                    );
                    $stmtUpdate->execute([
                        ':payment_proof_file' => $uploaded_payment_proof_filename,
                        ':user_payment_notes' => $user_payment_notes ?: null,
                        ':booking_id' => $booking_id_payment
                    ]);
                    $success_message = "Konfirmasi pembayaran Anda telah diterima (ID Pesanan: #$booking_id_payment). Admin akan segera melakukan verifikasi.";
                }
            } catch (PDOException $e) {
                error_log("Pesan Iklan - User confirm payment error: " . $e->getMessage());
                $errors[] = "Terjadi kesalahan server saat memproses konfirmasi pembayaran Anda.";
                if ($uploaded_payment_proof_filename && file_exists(UPLOAD_DIR_PAYMENT_PROOFS . $uploaded_payment_proof_filename)) {
                    unlink(UPLOAD_DIR_PAYMENT_PROOFS . $uploaded_payment_proof_filename);
                }
            }
        }
    } elseif ($action === 'user_confirm_booking' || $action === 'user_reject_booking') {
        // ... (logika konfirmasi/tolak materi iklan dari penyiar tetap sama) ...
        $booking_id = filter_input(INPUT_POST, 'user_action_booking_id', FILTER_VALIDATE_INT); 
        $rejection_reason = ($action === 'user_reject_booking') ? trim($_POST['user_rejection_reason'] ?? '') : null; 

        if (!$booking_id) $errors[] = 'Booking ID tidak valid.'; 
        if ($action === 'user_reject_booking' && empty($rejection_reason)) $errors[] = 'Alasan penolakan/revisi wajib diisi.'; 
        // ... validasi lainnya ...

        if (empty($errors)) { 
            try {
                $stmtCheck = $pdo->prepare("SELECT id, status FROM ad_bookings WHERE id = :booking_id AND user_id = :user_id"); 
                $stmtCheck->execute([':booking_id' => $booking_id, ':user_id' => $user_id]); 
                $booking = $stmtCheck->fetch(); 

                if (!$booking) { 
                    $errors[] = 'Pemesanan iklan tidak ditemukan atau Anda tidak memiliki hak akses.'; 
                } elseif ($booking['status'] !== 'pending_user_confirmation') { 
                    $errors[] = 'Status pemesanan saat ini (' . htmlspecialchars($booking['status']) . ') tidak memungkinkan aksi ini.'; 
                } else {
                    // ... (logika update status user_confirm_booking / user_reject_booking tetap sama) ...
                    $new_status_ad_material = ''; 
                    $paramsExecute_ad_material = []; 
                    if ($action === 'user_confirm_booking') { 
                        $new_status_ad_material = 'confirmed_by_user'; 
                        $sql_ad_material = "UPDATE ad_bookings SET status = :new_status, user_rejection_reason = NULL, updated_at = NOW() WHERE id = :booking_id"; 
                        $paramsExecute_ad_material = [':new_status' => $new_status_ad_material, ':booking_id' => $booking_id]; 
                        $success_message = "Materi iklan telah berhasil Anda konfirmasi."; 
                    } else { // user_reject_booking
                        $new_status_ad_material = 'revision_needed_by_user'; 
                        $sql_ad_material = "UPDATE ad_bookings SET status = :new_status, user_rejection_reason = :rejection_reason, updated_at = NOW() WHERE id = :booking_id"; 
                        $paramsExecute_ad_material = [':new_status' => $new_status_ad_material, ':rejection_reason' => $rejection_reason, ':booking_id' => $booking_id]; 
                        $success_message = "Permintaan revisi telah dikirim ke penyiar."; 
                    }
                    $stmtUpdate_ad_material = $pdo->prepare($sql_ad_material); 
                    $stmtUpdate_ad_material->execute($paramsExecute_ad_material); 
                    if ($stmtUpdate_ad_material->rowCount() === 0) { 
                         $errors[] = "Tidak ada perubahan dilakukan pada status materi iklan. Mungkin status sudah diperbarui atau data tidak cocok."; 
                         $success_message = ''; // Reset success message jika tidak ada update
                    }
                }
            } catch (PDOException $e) {
                error_log("Pesan Iklan - User update ad booking status error: " . $e->getMessage()); 
                $errors[] = "Terjadi kesalahan server saat memperbarui status pemesanan materi iklan."; 
            }
        }
    } else {
        if (!empty($action)) $errors[] = 'Aksi POST tidak dikenal: ' . htmlspecialchars($action);
    }
}

// --- Logika untuk Menampilkan Halaman HTML ---
$bookings = [];
try {
    $stmt_fetch_bookings = $pdo->prepare(
        "SELECT id, ad_title, ad_content, desired_schedule, ad_file_listener, payment_proof_file, user_payment_notes, 
                ad_file_broadcaster, status, admin_notes, broadcaster_notes, user_rejection_reason, created_at, updated_at
         FROM ad_bookings
         WHERE user_id = :user_id
         ORDER BY created_at DESC"
    );
    $stmt_fetch_bookings->execute([':user_id' => $user_id]);
    $bookings = $stmt_fetch_bookings->fetchAll();
} catch (PDOException $e) {
    error_log("Pesan Iklan - Fetch Bookings Error: " . $e->getMessage());
    $errors[] = "Gagal mengambil riwayat pemesanan Anda dari database.";
}

// if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
// $errors[] = 'Metode request (' . htmlspecialchars($_SERVER['REQUEST_METHOD']) . ') tidak diizinkan.';
// } // Komentari atau hapus ini, karena bisa menyebabkan error saat halaman pertama kali load

$pageTitle = "Pesan & Kelola Iklan Radio";
include 'includes/header.php';
?>

<main class="main-content">
    <div class="container-fluid">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"> <strong>Terjadi Kesalahan:</strong>
                <ul style="margin-top: 10px; padding-left: 20px; margin-bottom:0;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="form-container card-bg" style="margin-bottom: 30px;">
            <h2>Informasi Harga dan Paket Iklan</h2>
            <p>Untuk informasi mengenai harga, paket iklan yang tersedia, serta durasi dan ketentuan lainnya, silakan hubungi tim marketing kami melalui:</p>
            <ul>
                <li>Email: <a href="mailto:marketing@elmirafm.com">marketing@elmirafm.com</a> (Contoh)</li>
                <li>Telepon/WhatsApp: <a href="tel:+6281234567890">0812-3456-7890</a> (Contoh)</li>
            </ul>
             <p style="margin-top:15px; font-weight:bold;">Setelah menyepakati harga dengan tim marketing, silakan lanjutkan pemesanan di bawah dan lakukan pembayaran sesuai instruksi yang akan muncul.</p>
        </div>


        <div class="form-container card-bg">
            <h2>Formulir Pemesanan Iklan Baru</h2>
            <p>Isi formulir di bawah ini untuk detail materi iklan Anda. Instruksi pembayaran akan muncul setelah formulir ini dikirim.</p>
            
            <form action="pesan_iklan.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_ad_booking_form">
                <div class="form-group">
                    <label for="ad_title">Judul Iklan/Produk/Layanan:</label>
                    <input type="text" id="ad_title" name="ad_title" required value="<?php echo htmlspecialchars($_POST['ad_title'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="ad_content">Naskah Iklan Lengkap (maks 2000 karakter):</label>
                    <textarea id="ad_content" name="ad_content" rows="7" required maxlength="2000" placeholder="Tuliskan naskah iklan yang akan dibacakan atau konsep iklan Anda di sini..."><?php echo htmlspecialchars($_POST['ad_content'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="ad_file_listener">Unggah File Materi dari Anda (Opsional - MP3, WAV, Naskah DOC/PDF, maks 50MB):</label>
                    <input type="file" id="ad_file_listener" name="ad_file_listener">
                    <small>Jika Anda memiliki rekaman audio sendiri atau naskah dalam format file, silakan unggah di sini.</small>
                </div>

                <div class="form-group">
                    <label for="desired_schedule">Jadwal Tayang yang Diinginkan (maks 255 karakter):</label>
                    <input type="text" id="desired_schedule" name="desired_schedule" value="<?php echo htmlspecialchars($_POST['desired_schedule'] ?? ''); ?>" placeholder="Contoh: Setiap Senin-Jumat jam 8 pagi, atau Sesuai kesepakatan paket X">
                </div>
                <div>
                    <button type="submit" class="btn-auth">Kirim Detail & Lanjut Pembayaran</button>
                </div>
            </form>
        </div>

        <div class="recent-activity card-bg" style="margin-top:30px;">
            <h3>Riwayat Pemesanan Iklan Anda</h3>
            <?php if (empty($bookings)): ?>
                <p>Anda belum pernah memesan iklan.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Judul Iklan</th>
                                <th>Status</th>
                                <th>Tgl Pesan</th>
                                <th>Aksi & Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($booking['id']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['ad_title']); ?></td>
                                    <td>
                                        <?php
                                        $status_text = '';
                                        switch ($booking['status']) {
                                            case 'pending_payment': $status_text = 'Menunggu Pembayaran'; break;
                                            case 'pending_admin_confirmation': $status_text = 'Menunggu Konfirmasi Admin/Verifikasi Pembayaran'; break;
                                            case 'pending_broadcaster_creation': $status_text = 'Diproses Admin, Menunggu Materi dari Penyiar'; break;
                                            case 'pending_user_confirmation': $status_text = 'MATERI SIAP: Menunggu Konfirmasi Anda'; break;
                                            case 'confirmed_by_user': $status_text = 'Disetujui Anda'; break;
                                            case 'confirmed_by_admin': $status_text = 'Disetujui Admin (Siap Tayang)'; break;
                                            case 'rejected_admin': $status_text = 'Ditolak Admin'; break;
                                            case 'rejected_user': $status_text = 'Ditolak Anda (menunggu tindak lanjut)'; break;
                                            case 'revision_needed_by_user': $status_text = 'Permintaan Revisi Terkirim ke Penyiar'; break;
                                            case 'revision_needed_by_admin': $status_text = 'Admin Meminta Revisi dari Anda'; break;
                                            case 'aired': $status_text = 'Sudah Tayang'; break;
                                            case 'cancelled': $status_text = 'Dibatalkan'; break;
                                            default: $status_text = ucfirst(str_replace('_', ' ', htmlspecialchars($booking['status']))); break;
                                        }
                                        $status_class = 'badge ';
                                        if (strpos($booking['status'], 'pending_payment') !== false || strpos($booking['status'], 'pending_admin_confirmation') !== false) $status_class .= 'badge-warning';
                                        elseif (strpos($booking['status'], 'pending') !== false && $booking['status'] !== 'pending_payment' && $booking['status'] !== 'pending_admin_confirmation') $status_class .= 'badge-info'; // Misalnya pending_broadcaster_creation
                                        else if (strpos($booking['status'], 'confirmed') !== false || $booking['status'] === 'aired') $status_class .= 'badge-success';
                                        else if (strpos($booking['status'], 'rejected') !== false || strpos($booking['status'], 'revision_needed') !== false) $status_class .= 'badge-danger';
                                        else $status_class .= 'badge-secondary'; // Default
                                        echo '<span class="' . $status_class . '">' . $status_text . '</span>';
                                        ?>
                                    </td>
                                    <td><?php echo date('d M Y, H:i', strtotime($booking['created_at'])); ?></td>
                                    <td>
                                        <button onclick="toggleDetails('booking-details-<?php echo $booking['id']; ?>', this)" class="btn-action btn-info" style="margin-bottom:5px;"><i class="fas fa-eye"></i> Detail</button>
                                        <div id="booking-details-<?php echo $booking['id']; ?>" class="booking-details card-bg" style="display:none; margin-top:10px; padding:15px;">
                                            <p><strong>Judul Iklan:</strong> <?php echo htmlspecialchars($booking['ad_title']); ?></p>
                                            <p><strong>Naskah/Brief Anda:</strong><br><?php echo nl2br(htmlspecialchars($booking['ad_content'])); ?></p>
                                            <?php if (!empty($booking['desired_schedule'])): ?>
                                                <p><strong>Jadwal Diinginkan:</strong> <?php echo htmlspecialchars($booking['desired_schedule']); ?></p>
                                            <?php endif; ?>

                                            <?php if (!empty($booking['ad_file_listener']) && defined('UPLOAD_URL_LISTENER_ADS')): ?>
                                                <p style="margin-top:10px;"><strong><i class="fas fa-paperclip"></i> File dari Anda:</strong><br>
                                                    <a href="<?php echo UPLOAD_URL_LISTENER_ADS . htmlspecialchars($booking['ad_file_listener']); ?>" target="_blank" download class="file-download-link">
                                                        <i class="fas fa-download"></i> <?php echo htmlspecialchars($booking['ad_file_listener']); ?>
                                                    </a>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($booking['admin_notes'])): ?>
                                                <p><strong>Catatan Admin:</strong><br><span class="note-text"><?php echo nl2br(htmlspecialchars($booking['admin_notes'])); ?></span></p>
                                            <?php endif; ?>
                                            <?php if (!empty($booking['broadcaster_notes'])): ?>
                                                <p><strong>Catatan Penyiar:</strong><br><span class="note-text"><?php echo nl2br(htmlspecialchars($booking['broadcaster_notes'])); ?></span></p>
                                            <?php endif; ?>

                                            <?php // Bagian materi dari penyiar (logika tetap sama)
                                            if (in_array($booking['status'], ['pending_user_confirmation', 'confirmed_by_user', 'confirmed_by_admin', 'aired']) && !empty($booking['ad_file_broadcaster']) && defined('UPLOAD_URL_BROADCASTER_ADS')):
                                            ?>
                                                <p style="margin-top:15px;"><strong><i class="fas fa-file-audio"></i> File Materi Iklan dari Penyiar:</strong><br>
                                                    <a href="<?php echo UPLOAD_URL_BROADCASTER_ADS . htmlspecialchars($booking['ad_file_broadcaster']); ?>" target="_blank" download class="file-download-link">
                                                        <i class="fas fa-download"></i> Download/Dengarkan <?php echo htmlspecialchars($booking['ad_file_broadcaster']); ?>
                                                    </a>
                                                </p>
                                            <?php elseif ($booking['status'] === 'pending_user_confirmation' && empty($booking['ad_file_broadcaster'])): ?>
                                                <p style="margin-top:15px; color: #777;"><em>Penyiar sedang mempersiapkan file materi iklan...</em></p>
                                            <?php endif; ?>

                                            <?php if (in_array($booking['status'], ['rejected_user', 'revision_needed_by_user']) && !empty($booking['user_rejection_reason'])): ?>
                                                 <p style="margin-top:15px;"><strong>Alasan Penolakan/Permintaan Revisi Anda:</strong><br><span class="note-text-user"><?php echo nl2br(htmlspecialchars($booking['user_rejection_reason'])); ?></span></p>
                                            <?php endif; ?>
                                            
                                            <?php // BAGIAN BARU: INFORMASI PEMBAYARAN & KONFIRMASI ?>
                                            <?php if ($booking['status'] === 'pending_payment' || !empty($booking['payment_proof_file'])): ?>
                                                <div class="payment-section" style="margin-top:20px; padding-top:15px; border-top: 1px solid var(--border-color, #eee);">
                                                    <h4>Informasi Pembayaran</h4>
                                                    <?php if ($booking['status'] === 'pending_payment'): ?>
                                                        <p>Silakan lakukan pembayaran sejumlah <strong>Rp. XXX.XXX</strong> (Hubungi marketing untuk jumlah pasti) ke rekening berikut:</p>
                                                        <ul>
                                                            <li>Bank: Nama Bank Anda (Contoh: BCA)</li>
                                                            <li>No. Rekening: 123-456-7890 (Contoh)</li>
                                                            <li>Atas Nama: Nama Perusahaan/Radio Anda</li>
                                                        </ul>
                                                        <p>Setelah melakukan pembayaran, silakan unggah bukti transfer dan isi catatan (jika perlu) di bawah ini, lalu klik tombol "Saya Sudah Bayar".</p>
                                                        
                                                        <form action="pesan_iklan.php" method="POST" enctype="multipart/form-data" style="margin-top:15px; padding:15px; border:1px solid var(--border-color, #ddd); border-radius:8px; background-color:var(--card-bg-light, #f9f9f9);">
                                                            <input type="hidden" name="action" value="user_confirm_ad_payment">
                                                            <input type="hidden" name="payment_booking_id" value="<?php echo $booking['id']; ?>">
                                                            <div class="form-group">
                                                                <label for="payment_proof_file_<?php echo $booking['id']; ?>"><strong>Unggah Bukti Pembayaran (JPG, PNG, PDF - Maks 5MB):</strong></label>
                                                                <input type="file" name="payment_proof_file" id="payment_proof_file_<?php echo $booking['id']; ?>" accept=".jpg,.jpeg,.png,.pdf" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="user_payment_notes_<?php echo $booking['id']; ?>"><strong>Catatan Pembayaran (Opsional):</strong></label>
                                                                <textarea name="user_payment_notes" id="user_payment_notes_<?php echo $booking['id']; ?>" rows="3" class="form-control" placeholder="Misal: Transfer dari rekening a.n. Budi..."></textarea>
                                                            </div>
                                                            <button type="submit" class="btn-auth btn-success"><i class="fas fa-money-check-alt"></i> Saya Sudah Bayar & Konfirmasi</button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <?php if (!empty($booking['payment_proof_file']) && defined('UPLOAD_URL_PAYMENT_PROOFS')): ?>
                                                        <p style="margin-top:10px;"><strong><i class="fas fa-receipt"></i> Bukti Pembayaran Anda:</strong><br>
                                                            <a href="<?php echo UPLOAD_URL_PAYMENT_PROOFS . htmlspecialchars($booking['payment_proof_file']); ?>" target="_blank" download class="file-download-link">
                                                                <i class="fas fa-download"></i> <?php echo htmlspecialchars($booking['payment_proof_file']); ?>
                                                            </a>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($booking['user_payment_notes'])): ?>
                                                        <p><strong>Catatan Pembayaran Anda:</strong><br><span class="note-text"><?php echo nl2br(htmlspecialchars($booking['user_payment_notes'])); ?></span></p>
                                                    <?php endif; ?>
                                                    <?php if ($booking['status'] === 'pending_admin_confirmation' && !empty($booking['payment_proof_file'])): ?>
                                                         <p style="color: var(--info-color, blue); margin-top:10px;"><em>Admin sedang memverifikasi pembayaran Anda.</em></p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>


                                            <p style="margin-top:15px;"><small>Update Terakhir: <?php echo date('d M Y, H:i', strtotime($booking['updated_at'])); ?></small></p>
                                            
                                            <div class="user-actions-panel" style="margin-top:20px; padding-top:15px; border-top: 1px solid var(--border-color, #eee);">
                                            <?php if ($booking['status'] == 'pending_user_confirmation'): ?>
                                                <p><strong>Silakan review materi iklan di atas.</strong> Apakah Anda menyetujuinya atau memerlukan revisi?</p>
                                                <form action="pesan_iklan.php" method="POST" style="display:inline-block; margin-right:10px;">
                                                    <input type="hidden" name="user_action_booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="action" value="user_confirm_booking">
                                                    <button type="submit" class="btn-action btn-success"><i class="fas fa-check"></i> Setujui Materi Ini</button>
                                                </form>
                                                <button onclick="showRejectForm('reject-form-<?php echo $booking['id']; ?>', this)" class="btn-action btn-danger"><i class="fas fa-times"></i> Tolak / Minta Revisi</button>
                                                
                                                <div id="reject-form-<?php echo $booking['id']; ?>" style="display:none; margin-top:15px; padding:15px; border:1px solid var(--border-color, #ddd); border-radius:8px; background-color:var(--card-bg-light, #f9f9f9);">
                                                    <form action="pesan_iklan.php" method="POST">
                                                        <input type="hidden" name="user_action_booking_id" value="<?php echo $booking['id']; ?>">
                                                        <input type="hidden" name="action" value="user_reject_booking">
                                                        <div class="form-group">
                                                           <label for="user_rejection_reason_<?php echo $booking['id']; ?>"><strong>Catatan untuk Penyiar (Alasan Penolakan/Permintaan Revisi - Wajib Diisi):</strong></label>
                                                           <textarea name="user_rejection_reason" id="user_rejection_reason_<?php echo $booking['id']; ?>" rows="4" required class="form-control" placeholder="Jelaskan bagian mana yang perlu direvisi atau alasan penolakan..."></textarea>
                                                        </div>
                                                        <button type="submit" class="btn-auth btn-danger">Kirim Permintaan Revisi</button>
                                                        <button type="button" onclick="showRejectForm('reject-form-<?php echo $booking['id']; ?>', null)" class="btn-action btn-secondary" style="margin-left:5px;">Batal</button>
                                                    </form>
                                                </div>
                                            <?php elseif (in_array($booking['status'], ['pending_admin_confirmation', 'pending_broadcaster_creation', 'confirmed_by_user', 'revision_needed_by_user'])): ?>
                                                <p><small><em>Status saat ini: <?php echo $status_text; ?>. Menunggu proses selanjutnya...</em></small></p>
                                            <?php elseif (in_array($booking['status'], ['confirmed_by_admin', 'aired'])): ?>
                                                <p style="color: var(--success-color, green);"><small><i class="fas fa-check-circle"></i> <strong>Proses Selesai.</strong></small></p>
                                            <?php elseif ($booking['status'] !== 'pending_payment'): // Jangan tampilkan 'tidak ada aksi' jika sedang menunggu pembayaran ?>
                                                <p><small><em>Tidak ada aksi yang dapat dilakukan saat ini untuk status ini.</em></small></p>
                                            <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div> 
</main>

<style>
    /* (Salin semua CSS dari file asli pesan_iklan.php Anda ke sini) */
    /* ... CSS yang sudah ada ... */

    /* Tambahan/penyesuaian style untuk bagian pembayaran */
    .payment-section h4 {
        color: var(--primary-color);
        margin-bottom: 10px;
        font-size: 1.1em;
    }
    .payment-section ul {
        list-style: disc;
        margin-left: 20px;
        margin-bottom: 15px;
    }
    #payment_proof_file, #user_payment_notes { /* Untuk input file dan textarea di form konfirmasi bayar */
        background-color: var(--secondary-color) !important; /* Pastikan backgroundnya sesuai tema */
        color: var(--text-color) !important;
        border: 1px solid var(--border-color) !important;
    }
    .badge-secondary { background-color: var(--text-secondary); color: var(--card-bg); } /* Badge default jika status aneh */
</style>

<script>
    // ... (JavaScript toggleDetails dan showRejectForm tetap sama) ...
    function toggleDetails(detailsId, buttonElement) {
        var element = document.getElementById(detailsId);
        var allDetails = document.querySelectorAll('.booking-details');
        
        allDetails.forEach(function(detailDiv) {
            if (detailDiv.id !== detailsId && detailDiv.style.display === "block") {
                detailDiv.style.display = "none";
                // var otherButtonId = detailDiv.id.replace('booking-details-', 'btn-detail-'); // Ini mungkin tidak relevan lagi
                var otherButton = document.querySelector('button[onclick*="' + detailDiv.id + '"]'); 
                if (otherButton) {
                    otherButton.innerHTML = '<i class="fas fa-eye"></i> Detail';
                }
            }
        });

        if (element) {
            if (element.style.display === "none" || element.style.display === "") {
                element.style.display = "block";
                if (buttonElement) buttonElement.innerHTML = '<i class="fas fa-eye-slash"></i> Sembunyikan Detail';
            } else {
                element.style.display = "none";
                if (buttonElement) buttonElement.innerHTML = '<i class="fas fa-eye"></i> Detail';
            }
        }
    }

    function showRejectForm(formId, buttonElement) {
        var formDiv = document.getElementById(formId);
        if (formDiv) {
            var mainToggleButton = buttonElement; 

            if (formDiv.style.display === "none" || formDiv.style.display === "") {
                formDiv.style.display = "block";
                if (mainToggleButton) { 
                     mainToggleButton.style.display = "none"; // Sembunyikan tombol "Tolak / Minta Revisi"
                }
            } else {
                formDiv.style.display = "none";
                // Cari tombol "Tolak / Minta Revisi" yang sesuai dan tampilkan kembali
                var allMainToggleButtons = document.querySelectorAll('.btn-action.btn-danger'); 
                allMainToggleButtons.forEach(function(btn) {
                    // Pastikan tombol yang ditampilkan adalah tombol utama, bukan tombol di dalam form revisi itu sendiri
                    if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(formId) && !btn.closest('form')) {
                        btn.style.display = "inline-block"; // atau "block" atau "flex" tergantung display aslinya
                    }
                });
            }
        }
    }
</script>

<?php include 'includes/footer.php'; ?>