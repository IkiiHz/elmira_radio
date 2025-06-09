<?php
require_once '../includes/config.php'; // Memuat konfigurasi dan helper
redirectIfNotOwner('../login.php?error=Akses+owner+ditolak.'); // Hanya owner yang boleh akses

$page_error_message = '';
$stats = [
    'listeners_today' => 0,
    'song_requests_today' => 0,
    'chat_messages_today' => 0,
    'total_programs' => 0,
    'ads_pending_admin' => 0,
    'ads_processed_or_ready' => 0, // Menggantikan active_ads_count
];
$ad_bookings_report = [];

// --- Statistik Inti (Pastikan kolom 'start_time' di tabel 'analytics' benar) ---
try {
    $stmt_listeners = $pdo->query("SELECT SUM(listeners_count) as total FROM analytics WHERE DATE(start_time) = CURDATE()");
    $res_listeners = $stmt_listeners->fetch();
    $stats['listeners_today'] = $res_listeners['total'] ?? 0;
} catch (PDOException $e) {
    $stats['listeners_today'] = "Error";
    error_log("Owner Dashboard - Listeners Stat Error: " . $e->getMessage());
    if(empty($page_error_message)) $page_error_message = "Gagal memuat statistik pendengar.";
}
try {
    $stmt_requests = $pdo->query("SELECT COUNT(*) as total FROM song_requests WHERE DATE(request_time) = CURDATE()");
    $res_requests = $stmt_requests->fetch();
    $stats['song_requests_today'] = $res_requests['total'] ?? 0;
} catch (PDOException $e) {
    $stats['song_requests_today'] = "Error";
    error_log("Owner Dashboard - Song Requests Stat Error: " . $e->getMessage());
    if(empty($page_error_message)) $page_error_message = "Gagal memuat statistik request lagu.";
}
try {
    $stmt_chat = $pdo->query("SELECT COUNT(*) as total FROM live_chat WHERE DATE(sent_at) = CURDATE()");
    $res_chat = $stmt_chat->fetch();
    $stats['chat_messages_today'] = $res_chat['total'] ?? 0;
} catch (PDOException $e) {
    $stats['chat_messages_today'] = "Error";
    error_log("Owner Dashboard - Chat Messages Stat Error: " . $e->getMessage());
    if(empty($page_error_message)) $page_error_message = "Gagal memuat statistik pesan chat.";
}
try {
    $stmt_programs_total = $pdo->query("SELECT COUNT(*) as total FROM programs");
    $res_programs_total = $stmt_programs_total->fetch();
    $stats['total_programs'] = $res_programs_total['total'] ?? 0;
} catch (PDOException $e) {
    $stats['total_programs'] = "Error";
    error_log("Owner Dashboard - Total Programs Stat Error: " . $e->getMessage());
    if(empty($page_error_message)) $page_error_message = "Gagal memuat statistik total program.";
}

// --- Statistik Iklan Disesuaikan ---
try {
    $stmt_ads_pending = $pdo->query("SELECT COUNT(*) as total FROM ad_bookings WHERE status = 'pending_admin_confirmation'");
    $res_ads_pending = $stmt_ads_pending->fetch();
    $stats['ads_pending_admin'] = $res_ads_pending['total'] ?? 0;
} catch (PDOException $e) {
    $stats['ads_pending_admin'] = "Error";
    error_log("Owner Dashboard - Ads Pending Stat Error: " . $e->getMessage());
    if(empty($page_error_message)) $page_error_message = "Gagal memuat statistik iklan menunggu persetujuan admin.";
}

try {
    // Menghitung iklan yang sudah diproses melewati tahap awal persetujuan admin
    $stmt_ads_processed = $pdo->query(
        "SELECT COUNT(*) as total FROM ad_bookings 
         WHERE status IN ('pending_broadcaster_creation', 'pending_user_confirmation', 'confirmed_by_admin', 'confirmed_by_user', 'aired')"
    );
    $res_ads_processed = $stmt_ads_processed->fetch();
    $stats['ads_processed_or_ready'] = $res_ads_processed['total'] ?? 0;
} catch (PDOException $e) {
    $stats['ads_processed_or_ready'] = "Error";
    error_log("Owner Dashboard - Ads Processed Stat Error: " . $e->getMessage());
    if(empty($page_error_message)) $page_error_message = "Gagal memuat statistik iklan diproses/siap tayang.";
}


// --- Laporan Detail Pemesanan Iklan ---
$possible_statuses_display = [ // Diambil dari admin/ads.php untuk konsistensi tampilan status
    'pending_admin_confirmation' => 'Menunggu Konfirmasi Admin',
    'pending_broadcaster_creation' => 'Tugaskan ke Penyiar',
    'pending_user_confirmation' => 'Materi Siap (Menunggu Konfirmasi Pengguna)',
    'confirmed_by_admin' => 'Disetujui Admin (Siap Tayang)',
    'confirmed_by_user' => 'Disetujui Pengguna', // Status tambahan jika ada alur konfirmasi pengguna
    'aired' => 'Sudah Tayang',
    'rejected_admin' => 'Ditolak Admin',
    'revision_needed_by_user' => 'Perlu Revisi (dari Pengguna ke Penyiar)',
    'revision_needed_by_admin' => 'Minta Revisi ke Pengguna',
    'cancelled' => 'Dibatalkan'
];

try {
    $stmt_bookings_report = $pdo->query(
        "SELECT ab.id, ab.ad_title, ab.desired_schedule, ab.status, ab.created_at, 
                u.username AS requester_name, 
                p.username AS assigned_penyiar_name,
                ab.ad_file_listener, ab.ad_file_broadcaster 
         FROM ad_bookings ab 
         JOIN users u ON ab.user_id = u.id 
         LEFT JOIN users p ON ab.assigned_penyiar_id = p.id
         ORDER BY ab.created_at DESC
         LIMIT 10" // Ambil 10 terbaru untuk laporan owner
    );
    $ad_bookings_report = $stmt_bookings_report->fetchAll();
} catch (PDOException $e) {
    error_log("Owner Dashboard - Ad Bookings Report Error: " . $e->getMessage());
    if(empty($page_error_message)) $page_error_message = "Gagal memuat laporan pemesanan iklan.";
}


include '../includes/header.php';
?>

<div class="owner-dashboard">
    <div class="sidebar">
        <h3>Owner Panel</h3>
        <ul>
            <li class="active"><a href="<?php echo htmlspecialchars(BASE_PATH_PREFIX); ?>/owner/dashboard.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
            <li><a href="<?php echo htmlspecialchars(BASE_PATH_PREFIX); ?>/includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h2>Dashboard Laporan Owner</h2>
        
        <?php if (!empty($page_error_message)): ?>
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($page_error_message); ?> Beberapa data mungkin tidak termuat dengan benar.</div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h4>Pendengar Hari Ini</h4>
                <p class="stat-number">
                    <?php echo ($stats['listeners_today'] === "Error") ? "<span class='text-error'>Error</span>" : number_format($stats['listeners_today']); ?>
                </p>
                <p class="stat-change">Total unik hari ini</p>
            </div>
            
            <div class="stat-card">
                <h4>Request Lagu Hari Ini</h4>
                <p class="stat-number">
                    <?php echo ($stats['song_requests_today'] === "Error") ? "<span class='text-error'>Error</span>" : number_format($stats['song_requests_today']); ?>
                </p>
                 <p class="stat-change">Jumlah request diterima</p>
            </div>
            
            <div class="stat-card">
                <h4>Pesan Chat Hari Ini</h4>
                <p class="stat-number">
                    <?php echo ($stats['chat_messages_today'] === "Error") ? "<span class='text-error'>Error</span>" : number_format($stats['chat_messages_today']); ?>
                </p>
                <p class="stat-change">Total pesan terkirim</p>
            </div>
            
            <div class="stat-card">
                <h4>Total Program Siaran</h4>
                 <p class="stat-number">
                    <?php echo ($stats['total_programs'] === "Error") ? "<span class='text-error'>Error</span>" : number_format($stats['total_programs']); ?>
                 </p>
                <p class="stat-change">Aktif terjadwal</p>
            </div>

            {/* Kartu Statistik Iklan Baru */}
            <div class="stat-card">
                <h4>Iklan Menunggu Konfirmasi</h4>
                <p class="stat-number">
                    <?php echo ($stats['ads_pending_admin'] === "Error") ? "<span class='text-error'>Error</span>" : number_format($stats['ads_pending_admin']); ?>
                </p>
                <p class="stat-change">Persetujuan Admin</p>
            </div>

            <div class="stat-card">
                <h4>Iklan Diproses/Siap Tayang</h4>
                <p class="stat-number">
                     <?php echo ($stats['ads_processed_or_ready'] === "Error") ? "<span class='text-error'>Error</span>" : number_format($stats['ads_processed_or_ready']); ?>
                </p>
                <p class="stat-change">Total terkonfirmasi/tayang/proses</p>
            </div>
        </div>
        
        <div class="ad-bookings-report section-mb card-bg"> 
            <h3>Laporan Pemesanan Iklan (10 Terbaru)</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Judul Iklan</th>
                            <th>Pemesan</th>
                            <th>Penyiar Ditugaskan</th>
                            <th>Status</th>
                            <th>Tgl Pesan</th>
                            <th>File Materi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ad_bookings_report) && !empty($page_error_message) && strpos($page_error_message, 'pemesanan iklan') !== false) : ?>
                             <tr><td colspan="7" style="text-align:center;">Gagal memuat laporan pemesanan iklan.</td></tr>
                        <?php elseif (empty($ad_bookings_report)): ?>
                            <tr><td colspan="7" style="text-align:center;">Tidak ada data pemesanan iklan untuk ditampilkan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($ad_bookings_report as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['id']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['ad_title']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['requester_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($booking['assigned_penyiar_name'] ?? 'Belum Ditugaskan'); ?></td>
                                    <td>
                                        <span class="badge status-<?php echo htmlspecialchars(strtolower(str_replace('_', '-', $booking['status']))); ?>">
                                            <?php echo htmlspecialchars($possible_statuses_display[$booking['status']] ?? ucfirst(str_replace('_', ' ', $booking['status']))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y, H:i', strtotime($booking['created_at'])); ?></td>
                                    <td>
                                        <?php if (!empty($booking['ad_file_listener'])): ?>
                                            <a href="<?php echo htmlspecialchars(UPLOAD_URL_LISTENER_ADS . $booking['ad_file_listener']); ?>" target="_blank" title="File Pemesan"><i class="fas fa-file-download"></i> Pemesan</a><br>
                                        <?php endif; ?>
                                        <?php if (!empty($booking['ad_file_broadcaster'])): ?>
                                            <a href="<?php echo htmlspecialchars(UPLOAD_URL_BROADCASTER_ADS . $booking['ad_file_broadcaster']); ?>" target="_blank" title="File Penyiar"><i class="fas fa-file-audio"></i> Penyiar</a>
                                        <?php endif; ?>
                                        <?php if (empty($booking['ad_file_listener']) && empty($booking['ad_file_broadcaster'])): ?>
                                            <small>-</small>
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
</div>
<style>
    /* Tambahan style jika diperlukan, atau pastikan sudah ada di style.css */
    .text-error {
        color: var(--error-color, #dc3545); /* Merah untuk teks "Error" */
        font-weight: bold;
    }
    .ad-bookings-report.card-bg { /* Styling untuk konsistensi card */
        background-color: var(--card-bg);
        padding: 20px;
        border-radius: 8px;
        margin-top: 30px; /* Jarak dari stats-grid */
        box-shadow: var(--shadow-sm);
    }
    .ad-bookings-report h3 {
        margin-top: 0;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
        color: var(--primary-color);
    }
    .section-mb { /* Class untuk margin bottom antar seksi */
        margin-bottom: 30px;
    }

    /* Style untuk badge status, sesuaikan dengan yang sudah ada di style.css */
    /* Anda mungkin sudah memiliki ini di style.css */
    .badge {
        padding: 0.3em 0.7em;
        font-size: 0.75em;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.35rem;
        color: var(--accent-color-text); /* Default warna teks badge */
    }
    .status-pending-admin-confirmation { background-color: var(--warning-color, #ffc107); color: #212529; }
    .status-pending-broadcaster-creation { background-color: var(--info-color-alt, #fd7e14); } /* Oranye */
    .status-pending-user-confirmation { background-color: var(--cyan-color, #20c997); } /* Cyan/Teal */
    .status-confirmed-by-admin, .status-confirmed-by-user { background-color: var(--success-color, #28a745); }
    .status-aired { background-color: var(--blue-color, #17a2b8); } /* Biru Info */
    .status-rejected-admin, .status-revision-needed-by-user, .status-revision-needed-by-admin { background-color: var(--error-color, #dc3545); }
    .status-cancelled { background-color: var(--secondary-dark-color, #6c757d); }

    /* Untuk link file download */
    .ad-bookings-report table td a {
        text-decoration: none;
        color: var(--accent-color-bright);
        margin-right: 5px;
    }
    .ad-bookings-report table td a:hover {
        text-decoration: underline;
        color: var(--primary-color);
    }
    .ad-bookings-report table td a i {
        margin-right: 3px;
    }

</style>
<?php include '../includes/footer.php'; ?>