<?php
require_once '../includes/config.php'; // Memuat konfigurasi dan helper
redirectIfNotPenyiar('../login.php?error=Akses penyiar ditolak.'); // Hanya penyiar yang boleh akses

$penyiar_id = getCurrentUserId(); // Dapatkan ID penyiar yang sedang login
$penyiar_username = getCurrentUsername();
$error_message = '';
$my_schedules = [];

// Daftar Hari dalam Bahasa Indonesia untuk tampilan
$days_of_week_display_map = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];

// Ambil semua program untuk penyiar yang login
try {
    $stmt = $pdo->prepare(
        "SELECT * FROM programs 
         WHERE penyiar_id = :penyiar_id 
         ORDER BY 
            FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), 
            start_time ASC"
    );
    $stmt->execute([':penyiar_id' => $penyiar_id]);
    $my_schedules = $stmt->fetchAll();
} catch (PDOException $e) {
    $my_schedules = [];
    $error_message = "Gagal memuat jadwal Anda: " . $e->getMessage();
    error_log("Penyiar Schedule - Load Schedules Error: " . $e->getMessage());
}

include '../includes/header.php'; // Memuat header standar
?>

<div class="penyiar-dashboard">
    <div class="sidebar">
        <h3>Penyiar Panel</h3>
<ul>
            <li><a href="<?php echo BASE_PATH_PREFIX; ?>/penyiar/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="active"><a href="<?php echo BASE_PATH_PREFIX; ?>/penyiar/schedule.php"><i class="fas fa-calendar-alt"></i> Jadwal Saya</a></li>
            <li><a href="<?php echo BASE_PATH_PREFIX; ?>/penyiar/manage_ads.php"><i class="fas fa-bullhorn"></i> Kelola Iklan Ditugaskan</a></li>
            <li><a href="<?php echo BASE_PATH_PREFIX; ?>/includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h2>Jadwal Siaran Saya - <?php echo htmlspecialchars($penyiar_username); ?></h2>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (empty($my_schedules) && !$error_message): ?>
            <div class="card-bg" style="padding: 20px; border-radius: 8px; text-align: center;">
                <p>Anda belum memiliki jadwal program siaran yang ditetapkan.</p>
            </div>
        <?php elseif (!empty($my_schedules)): ?>
            <div class="schedule-grid-penyiar">
                <?php 
                $current_day_group = '';
                foreach ($my_schedules as $program): 
                    $display_day = $days_of_week_display_map[$program['day_of_week']] ?? $program['day_of_week'];
                    if ($program['day_of_week'] !== $current_day_group):
                        if ($current_day_group !== ''):
                            // Menutup div untuk grup hari sebelumnya jika bukan yang pertama
                            echo '</div>'; 
                        endif;
                        $current_day_group = $program['day_of_week'];
                ?>
                        <h3 class="schedule-day-header-penyiar" id="<?php echo strtolower($current_day_group); ?>">
                            <i class="fas fa-calendar-day"></i> <?php echo $display_day; ?>
                        </h3>
                        <div class="day-schedule-list"> <?php 
                    endif; // Akhir dari if ($program['day_of_week'] !== $current_day_group)
                ?>
                            <div class="program-card-penyiar">
                                <div class="program-time-penyiar">
                                    <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($program['start_time'])) . ' - ' . date('H:i', strtotime($program['end_time'])); ?>
                                </div>
                                <div class="program-info-penyiar">
                                    <h4><?php echo htmlspecialchars($program['title']); ?></h4>
                                    <?php if(!empty($program['description'])): ?>
                                        <p class="program-desc-penyiar"><?php echo nl2br(htmlspecialchars($program['description'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                <?php 
                endforeach; // Akhir dari loop $my_schedules
                if ($current_day_group !== ''):
                    // Menutup div grup hari terakhir
                    echo '</div>'; 
                endif;
                ?>
            </div>
        <?php endif; // Akhir dari if (empty($my_schedules)) ?>
    </div>
</div>
<style>
    /* Style spesifik untuk halaman jadwal penyiar */
    .main-content h2 {
        color: var(--primary-color);
        margin-bottom: 20px;
    }
    .schedule-day-header-penyiar {
        color: var(--primary-color);
        margin-top: 25px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary-color);
        font-size: 1.6em;
    }
    .schedule-day-header-penyiar i {
        margin-right: 10px;
    }
    .day-schedule-list {
        /* Tidak perlu style khusus jika program-card-penyiar sudah diatur */
    }
    .program-card-penyiar {
        background-color: var(--card-bg);
        border-radius: 8px;
        padding: 15px 20px;
        margin-bottom: 15px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        border-left: 5px solid var(--primary-color);
    }
    .program-time-penyiar {
        font-weight: bold;
        color: var(--accent-color);
        font-size: 1.1em;
        margin-bottom: 8px;
    }
    .program-time-penyiar i {
        margin-right: 8px;
        color: var(--primary-color);
    }
    .program-info-penyiar h4 {
        font-size: 1.25em;
        color: var(--text-color);
        margin-top: 0;
        margin-bottom: 5px;
    }
    .program-desc-penyiar {
        font-size: 0.95em;
        color: var(--text-secondary);
        line-height: 1.5;
    }
    .alert { /* Pastikan style alert sudah ada di style.css utama */
        margin-bottom: 20px;
    }
</style>

<?php include '../includes/footer.php'; ?>