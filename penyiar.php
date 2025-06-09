<?php
// File: elmira_radio/penyiar.php
require_once __DIR__ . '/includes/config.php'; // Memuat konfigurasi dan helper

$page_title = "Profil Penyiar Elmira FM";
$error_message = '';
$penyiars_data = [];

// Daftar Hari dalam Bahasa Indonesia untuk tampilan jadwal program
$days_of_week_display_map = [
    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];

try {
    // 1. Ambil semua user dengan role 'penyiar', sertakan profile_image_url
    $stmtPenyiar = $pdo->query(
        "SELECT id, username, email, profile_image_url 
         FROM users 
         WHERE role = 'penyiar' 
         ORDER BY username ASC"
    );
    $penyiars = $stmtPenyiar->fetchAll(PDO::FETCH_ASSOC);

    if ($penyiars) {
        // 2. Ambil semua program yang memiliki penyiar_id (untuk efisiensi)
        $stmtPrograms = $pdo->query(
            "SELECT penyiar_id, title, day_of_week, start_time, end_time 
             FROM programs 
             WHERE penyiar_id IS NOT NULL 
             ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time ASC"
        );
        $all_programs_raw = $stmtPrograms->fetchAll(PDO::FETCH_ASSOC);

        // 3. Kelompokkan program berdasarkan penyiar_id
        $programs_by_penyiar_id = [];
        foreach ($all_programs_raw as $prog) {
            $programs_by_penyiar_id[$prog['penyiar_id']][] = $prog;
        }

        // 4. Gabungkan data penyiar dengan program mereka
        foreach ($penyiars as $penyiar) {
            $penyiars_data[] = [
                'id' => $penyiar['id'],
                'username' => $penyiar['username'],
                'email' => $penyiar['email'], // Opsional, bisa untuk kontak atau tidak ditampilkan
                'profile_image_url' => $penyiar['profile_image_url'], // Nama file gambar profil
                'programs' => $programs_by_penyiar_id[$penyiar['id']] ?? [] // Ambil program untuk penyiar ini
            ];
        }
    }

} catch (PDOException $e) {
    $error_message = "Gagal memuat data penyiar: " . $e->getMessage();
    error_log("Penyiar Profile Page Error: " . $e->getMessage());
}

include __DIR__ . '/includes/header.php';
?>

<div class="penyiar-profile-container">
    <h1>Profil Penyiar Kami</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if (empty($penyiars_data) && !$error_message): ?>
        <p style="text-align: center; margin-top: 2rem;">Belum ada data penyiar untuk ditampilkan.</p>
    <?php elseif (!empty($penyiars_data)): ?>
        <div class="penyiar-grid">
            <?php foreach ($penyiars_data as $penyiar): ?>
                <div class="penyiar-card">
                    <div class="penyiar-card-image">
                        <?php
                        $image_display_path = rtrim(BASE_PATH_PREFIX, '/') . '/assets/images/default-profile.png'; // Gambar default
                        if (!empty($penyiar['profile_image_url'])) {
                            // Cek apakah URL absolut atau path relatif
                            if (filter_var($penyiar['profile_image_url'], FILTER_VALIDATE_URL)) {
                                $image_display_path = htmlspecialchars($penyiar['profile_image_url']);
                            } elseif (defined('UPLOAD_URL_PROFILES')) {
                                // Asumsikan profile_image_url adalah nama file saja
                                $image_display_path = htmlspecialchars(UPLOAD_URL_PROFILES . $penyiar['profile_image_url']);
                            }
                        }
                        ?>
                        <?php if (!empty($penyiar['profile_image_url'])): ?>
                            <img src="<?php echo $image_display_path; ?>?t=<?php echo time(); // Cache buster ?>" alt="Foto <?php echo htmlspecialchars($penyiar['username']); ?>">
                        <?php else: ?>
                            <div class="penyiar-card-image-placeholder">
                                <i class="fas fa-microphone-alt"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="penyiar-card-info">
                        <h3><?php echo htmlspecialchars($penyiar['username']); ?></h3>
                        
                        <?php if (!empty($penyiar['programs'])): ?>
                            <h4>Program Acara:</h4>
                            <ul class="penyiar-program-list">
                                <?php foreach ($penyiar['programs'] as $program): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($program['title']); ?></strong><br>
                                        <small>
                                            <?php echo htmlspecialchars($days_of_week_display_map[$program['day_of_week']] ?? $program['day_of_week']); ?>,
                                            <?php echo date('H:i', strtotime($program['start_time'])); ?> - <?php echo date('H:i', strtotime($program['end_time'])); ?>
                                        </small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p><small><em>Belum ada program yang ditugaskan untuk penyiar ini.</em></small></p>
                        <?php endif; ?>
                        
                        </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>