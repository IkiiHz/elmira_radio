<?php
// File: admin/ads.php
require_once '../includes/config.php'; // Memuat konfigurasi dan helper
redirectIfNotAdmin('../login.php?error=Akses+admin+ditolak.'); // Hanya admin yang boleh akses

$success_message = '';
$error_message = '';

// Daftar status yang bisa di-set oleh Admin untuk ad_bookings
$possible_statuses = [
    'pending_payment' => 'Menunggu Pembayaran', // Tambahkan status ini jika admin perlu melihatnya
    'pending_admin_confirmation' => 'Verifikasi Pembayaran & Konfirmasi Admin', // Perjelas
    'pending_broadcaster_creation' => 'Tugaskan ke Penyiar',
    'pending_user_confirmation' => 'Materi Siap (Menunggu Konfirmasi Pengguna)',
    'confirmed_by_admin' => 'Disetujui Admin (Siap Tayang)',
    'aired' => 'Sudah Tayang',
    'rejected_admin' => 'Ditolak Admin (Termasuk Pembayaran Tidak Valid)', // Perjelas
    'revision_needed_by_admin' => 'Minta Revisi ke Pengguna',
    'cancelled' => 'Dibatalkan'
];

// ... (logika $penyiar_list tetap sama) ...
try {
    $stmt_penyiar = $pdo->query("SELECT id, username FROM users WHERE role = 'penyiar' ORDER BY username ASC");
    $penyiar_list = $stmt_penyiar->fetchAll();
} catch (PDOException $e) {
    $penyiar_list = [];
    $error_message .= " Gagal memuat daftar penyiar: " . $e->getMessage();
    error_log("Admin Ad Bookings - Load Penyiar List Error: " . $e->getMessage());
}


// --- PROSES AKSI FORM (Update Status, Delete, Assign Penyiar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // ... (logika POST handling yang sudah ada tetap sama) ...
    // Pastikan $booking_id divalidasi
    $booking_id = $_POST['booking_id'] ?? null;

    if (!$booking_id || !ctype_digit((string)$booking_id)) {
        $error_message = "ID Pemesanan Iklan tidak valid.";
    } else {
        if ($_POST['action'] === 'update_booking_status') {
            $new_status = $_POST['new_status'] ?? '';
            if (array_key_exists($new_status, $possible_statuses)) {
                try {
                    $stmt = $pdo->prepare("UPDATE ad_bookings SET status = :status, updated_at = NOW() WHERE id = :id");
                    $stmt->execute([':status' => $new_status, ':id' => $booking_id]);
                    $success_message = "Status pemesanan iklan berhasil diperbarui.";

                    // Jika admin mengkonfirmasi dan status sebelumnya adalah 'pending_admin_confirmation' (artinya pembayaran diverifikasi)
                    // dan belum ada penyiar ditugaskan, otomatis ubah ke 'pending_broadcaster_creation' agar alur lebih jelas.
                    // Atau bisa juga admin langsung memilih 'pending_broadcaster_creation' dari dropdown.
                    // Ini opsional, tergantung alur kerja yang diinginkan.
                    if ($new_status === 'confirmed_by_admin' || $new_status === 'pending_broadcaster_creation') {
                        // Tidak ada aksi tambahan otomatis di sini, biarkan admin memilih langkah selanjutnya.
                        // Misal, jika memilih 'pending_broadcaster_creation', langkah selanjutnya adalah assign penyiar.
                    }

                } catch (PDOException $e) {
                    $error_message = "Gagal memperbarui status: " . $e->getMessage();
                }
            } else {
                $error_message = "Status baru tidak valid.";
            }
        } elseif ($_POST['action'] === 'delete_booking') {
            // Hapus juga file bukti pembayaran dan file listener/broadcaster jika ada
            try {
                $stmt_get_files = $pdo->prepare("SELECT ad_file_listener, ad_file_broadcaster, payment_proof_file FROM ad_bookings WHERE id = :id");
                $stmt_get_files->execute([':id' => $booking_id]);
                $files_to_delete = $stmt_get_files->fetch();

                $stmt = $pdo->prepare("DELETE FROM ad_bookings WHERE id = :id");
                $stmt->execute([':id' => $booking_id]);

                if ($files_to_delete) {
                    if ($files_to_delete['ad_file_listener'] && file_exists(UPLOAD_DIR_LISTENER_ADS . $files_to_delete['ad_file_listener'])) {
                        @unlink(UPLOAD_DIR_LISTENER_ADS . $files_to_delete['ad_file_listener']);
                    }
                    if ($files_to_delete['ad_file_broadcaster'] && file_exists(UPLOAD_DIR_BROADCASTER_ADS . $files_to_delete['ad_file_broadcaster'])) {
                        @unlink(UPLOAD_DIR_BROADCASTER_ADS . $files_to_delete['ad_file_broadcaster']);
                    }
                    if ($files_to_delete['payment_proof_file'] && file_exists(UPLOAD_DIR_PAYMENT_PROOFS . $files_to_delete['payment_proof_file'])) {
                        @unlink(UPLOAD_DIR_PAYMENT_PROOFS . $files_to_delete['payment_proof_file']);
                    }
                }
                $success_message = "Pemesanan iklan dan file terkait berhasil dihapus.";
            } catch (PDOException $e) {
                $error_message = "Gagal menghapus pemesanan iklan: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'assign_penyiar') {
             // ... (Logika assign penyiar yang sudah ada, pastikan status juga disesuaikan jika perlu) ...
            $assigned_penyiar_id = $_POST['assigned_penyiar_id'] ?? null;
            $penyiar_id_to_save = (!empty($assigned_penyiar_id) && ctype_digit((string)$assigned_penyiar_id)) ? (int)$assigned_penyiar_id : null;

            try {
                $stmt_update_assign = $pdo->prepare("UPDATE ad_bookings SET assigned_penyiar_id = :penyiar_id, updated_at = NOW() WHERE id = :id");
                $stmt_update_assign->execute([':penyiar_id' => $penyiar_id_to_save, ':id' => $booking_id]);
                
                if ($penyiar_id_to_save) {
                    // Jika penyiar ditugaskan, dan statusnya masih 'pending_admin_confirmation' atau 'pending_payment', ubah ke 'pending_broadcaster_creation'
                    // Ini penting agar alur berjalan setelah pembayaran diverifikasi dan penyiar ditugaskan
                    $stmt_update_status_assign = $pdo->prepare(
                        "UPDATE ad_bookings 
                         SET status = 'pending_broadcaster_creation', updated_at = NOW() 
                         WHERE id = :id AND (status = 'pending_admin_confirmation' OR status = 'pending_payment')" // Tambah OR status = 'pending_payment' jika admin bisa assign sebelum bayar
                    );
                    $stmt_update_status_assign->execute([':id' => $booking_id]);
                    $success_message = "Penyiar berhasil ditugaskan.";
                    if ($stmt_update_status_assign->rowCount() > 0) {
                        $success_message .= " Status diubah menjadi 'Menunggu Materi dari Penyiar'.";
                    }
                } else {
                    $success_message = "Penugasan penyiar dibatalkan/dihapus.";
                    // Pertimbangkan untuk mengubah status kembali ke 'pending_admin_confirmation' jika penugasan dibatalkan dan status sebelumnya 'pending_broadcaster_creation'
                    $stmt_revert_status = $pdo->prepare(
                        "UPDATE ad_bookings SET status = 'pending_admin_confirmation', updated_at = NOW() WHERE id = :id AND status = 'pending_broadcaster_creation'"
                    );
                    $stmt_revert_status->execute([':id' => $booking_id]);
                    if ($stmt_revert_status->rowCount() > 0) {
                        $success_message .= " Status dikembalikan ke 'Verifikasi Pembayaran & Konfirmasi Admin'.";
                    }
                }

            } catch (PDOException $e) {
                $error_message = "Gagal menugaskan penyiar: " . $e->getMessage();
            }
        }
    }
}


// Ambil semua pemesanan iklan untuk ditampilkan di tabel
try {
    $stmt_bookings = $pdo->query(
        "SELECT ab.*, u.username AS requester_name, p.username AS assigned_penyiar_name 
         FROM ad_bookings ab 
         JOIN users u ON ab.user_id = u.id 
         LEFT JOIN users p ON ab.assigned_penyiar_id = p.id
         ORDER BY 
            CASE ab.status
                WHEN 'pending_admin_confirmation' THEN 1
                WHEN 'pending_payment' THEN 2
                WHEN 'pending_broadcaster_creation' THEN 3
                ELSE 4
            END, 
            ab.updated_at DESC" // Prioritaskan yang butuh aksi admin
    );
    $bookings_list = $stmt_bookings->fetchAll();
} catch (PDOException $e) {
    $bookings_list = [];
    $error_message = ($error_message ? $error_message . " | " : "") . "Gagal memuat daftar pemesanan iklan: " . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="admin-dashboard">
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="<?php echo BASE_PATH_PREFIX; ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="<?php echo BASE_PATH_PREFIX; ?>/admin/schedule.php"><i class="fas fa-calendar-alt"></i> Jadwal Siaran</a></li>
            <li class="active"><a href="<?php echo BASE_PATH_PREFIX; ?>/admin/ads.php"><i class="fas fa-ad"></i> Manajemen Iklan</a></li>
            <li><a href="<?php echo BASE_PATH_PREFIX; ?>/admin/users.php"><i class="fas fa-users"></i> Manajemen User</a></li>
            <li><a href="<?php echo rtrim(BASE_PATH_PREFIX, '/'); ?>/includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h2>Manajemen Pemesanan Iklan</h2>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <h3 style="margin-top: 30px;">Daftar Pemesanan Iklan</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Judul Iklan</th>
                        <th>Pemesan</th>
                        <th>Detail & Materi</th>
                        <th>Penyiar</th>
                        <th>Status</th>
                        <th>Tgl Pesan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings_list)): ?>
                        <tr><td colspan="8" style="text-align: center;">Belum ada data pemesanan iklan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bookings_list as $booking): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($booking['id']); ?></td>
                                <td><?php echo htmlspecialchars($booking['ad_title']); ?></td>
                                <td><?php echo htmlspecialchars($booking['requester_name'] ?? 'N/A'); ?></td>
                                <td>
                                   <button onclick="showModal('modal-content-<?php echo $booking['id']; ?>')" class="btn-action btn-view-script" title="Lihat Detail Lengkap">
                                        <i class="fas fa-info-circle"></i> Detail
                                    </button>
                                    <div id="modal-content-<?php echo $booking['id']; ?>" class="modal">
                                        <div class="modal-content">
                                            <span class="close-button" onclick="closeModal('modal-content-<?php echo $booking['id']; ?>')">&times;</span>
                                            <h4>Detail Iklan: <?php echo htmlspecialchars($booking['ad_title']); ?></h4>
                                            <p><strong>Jadwal Diinginkan:</strong> <?php echo htmlspecialchars($booking['desired_schedule'] ?? 'N/A'); ?></p>
                                            <p class="preserve-whitespace"><strong>Naskah/Brief Pemesan:</strong><br><?php echo nl2br(htmlspecialchars($booking['ad_content'])); ?></p>
                                            
                                            <?php if(!empty($booking['ad_file_listener'])): ?>
                                                <p><strong>File dari Pemesan:</strong> <a href="<?php echo UPLOAD_URL_LISTENER_ADS . htmlspecialchars($booking['ad_file_listener']); ?>" target="_blank" download class="file-download-link"><i class="fas fa-download"></i> <?php echo htmlspecialchars($booking['ad_file_listener']); ?></a></p>
                                            <?php endif; ?>

                                            <?php if(!empty($booking['payment_proof_file'])): ?>
                                                <p><strong>Bukti Pembayaran:</strong> <a href="<?php echo UPLOAD_URL_PAYMENT_PROOFS . htmlspecialchars($booking['payment_proof_file']); ?>" target="_blank" download class="file-download-link"><i class="fas fa-receipt"></i> <?php echo htmlspecialchars($booking['payment_proof_file']); ?></a></p>
                                            <?php elseif($booking['status'] === 'pending_payment'): ?>
                                                <p><strong>Bukti Pembayaran:</strong> <span style="color:var(--warning-color);">Menunggu unggahan dari pemesan.</span></p>
                                            <?php endif; ?>
                                             <?php if(!empty($booking['user_payment_notes'])): ?>
                                                <p class="preserve-whitespace"><strong>Catatan Pembayaran Pemesan:</strong><br><?php echo nl2br(htmlspecialchars($booking['user_payment_notes'])); ?></p>
                                            <?php endif; ?>


                                            <?php if(!empty($booking['ad_file_broadcaster'])): ?>
                                                <p><strong>File Materi dari Penyiar:</strong> <a href="<?php echo UPLOAD_URL_BROADCASTER_ADS . htmlspecialchars($booking['ad_file_broadcaster']); ?>" target="_blank" download class="file-download-link"><i class="fas fa-file-audio"></i> <?php echo htmlspecialchars($booking['ad_file_broadcaster']); ?></a></p>
                                            <?php endif; ?>
                                            <?php if(!empty($booking['broadcaster_notes'])): ?>
                                                <p class="preserve-whitespace"><strong>Catatan Penyiar:</strong><br><?php echo nl2br(htmlspecialchars($booking['broadcaster_notes'])); ?></p>
                                            <?php endif; ?>
                                            <?php if(!empty($booking['user_rejection_reason'])): ?>
                                                <p class="preserve-whitespace user-rejection-reason"><strong>Revisi dari Pemesan:</strong><br><?php echo nl2br(htmlspecialchars($booking['user_rejection_reason'])); ?></p>
                                            <?php endif; ?>
                                            <p><small>Update Terakhir: <?php echo date('d M Y, H:i', strtotime($booking['updated_at'])); ?></small></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <form action="ads.php" method="POST" style="display: inline-block; min-width:150px;">
                                        <input type="hidden" name="action" value="assign_penyiar">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <select name="assigned_penyiar_id" title="Tugaskan Penyiar" onchange="this.form.submit()" class="form-control-sm">
                                            <option value="">-- Belum/Batal Tugas --</option>
                                            <?php foreach ($penyiar_list as $penyiar): ?>
                                                <option value="<?php echo $penyiar['id']; ?>" <?php echo (isset($booking['assigned_penyiar_id']) && $booking['assigned_penyiar_id'] == $penyiar['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($penyiar['username']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <form action="ads.php" method="POST" style="display: inline-block; min-width:200px;">
                                        <input type="hidden" name="action" value="update_booking_status">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <select name="new_status" onchange="this.form.submit()" title="Ubah Status Pemesanan" class="form-control-sm">
                                            <?php foreach ($possible_statuses as $status_val => $status_label): ?>
                                                <option value="<?php echo $status_val; ?>" <?php echo ($booking['status'] === $status_val) ? 'selected' : ''; ?>
                                                    <?php // Disable opsi jika tidak logis, misal dari 'pending_payment' tidak bisa langsung 'aired'
                                                        $disabled = '';
                                                        if ($booking['status'] === 'pending_payment' && !in_array($status_val, ['pending_payment', 'pending_admin_confirmation', 'rejected_admin', 'cancelled'])) {
                                                            // $disabled = 'disabled'; // Opsional: Batasi perubahan status dari pending_payment
                                                        }
                                                    ?>
                                                    <?php echo $disabled; ?>>
                                                    <?php echo htmlspecialchars($status_label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td><?php echo $booking['created_at'] ? date('d M Y, H:i', strtotime($booking['created_at'])) : 'N/A'; ?></td>
                                <td class="actions">
                                    <form action="ads.php" method="POST" style="display: inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pemesanan iklan \'<?php echo htmlspecialchars(addslashes($booking['ad_title'])); ?>\' ini? Aksi ini juga akan menghapus file materi terkait.');">
                                        <input type="hidden" name="action" value="delete_booking">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <button type="submit" class="btn-action btn-delete" title="Hapus Pemesanan">
                                            <i class="fas fa-trash-alt"></i> Hapus
                                        </button>
                                    </form>
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
    /* ... (CSS yang sudah ada) ... */
    /* Pastikan select di tabel tidak terlalu besar */
    td select.form-control-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        line-height: 1.5;
        border-radius: 0.2rem;
        /* width: auto; /* Biarkan width mengikuti konten atau atur min-width */
        max-width: 220px; /* Batasi lebar maksimum */
        vertical-align: middle; /* Agar sejajar dengan teks atau tombol lain di sel */
    }
    .user-rejection-reason { 
        color: var(--error-color); 
        font-style: italic; 
        font-size:0.9em; 
        display: block; /* Agar menempati baris sendiri jika panjang */
        margin-top: 5px;
        padding: 5px;
        border: 1px dashed var(--error-color);
        border-radius: 4px;
        background-color: rgba(207, 102, 121, 0.05);
    }
    .file-download-link { /* Style link download di modal */
        text-decoration: none;
        color: var(--accent-color-bright);
        font-weight: 500;
    }
    .file-download-link:hover {
        text-decoration: underline;
        color: var(--primary-color);
    }
    .file-download-link i {
        margin-right: 5px;
    }
</style>

<script>
    function showModal(modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show'); // Gunakan classList.add
        }
    }

    function closeModal(modalId) {
        var modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show'); // Gunakan classList.remove
        }
    }

    // Event listener untuk menutup modal jika user klik di luar area modal-content
    window.addEventListener('click', function(event) {
        document.querySelectorAll('.modal.show').forEach(function(modal) { // Hanya target modal yang sedang .show
            if (event.target == modal) {
                modal.classList.remove('show');
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>