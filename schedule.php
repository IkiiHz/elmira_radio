<?php
// header.php sudah include config.php
include __DIR__ . '/includes/header.php';
?>

<div class="schedule-container">
    <h1>Jadwal Siaran ELMIRA 95.8 FM</h1>

    <div class="schedule-filter">
        <button class="filter-btn active" data-day="all">Semua Hari</button>
        <button class="filter-btn" data-day="monday">Senin</button> 
        <button class="filter-btn" data-day="tuesday">Selasa</button> 
        <button class="filter-btn" data-day="wednesday">Rabu</button> 
        <button class="filter-btn" data-day="thursday">Kamis</button> 
        <button class="filter-btn" data-day="friday">Jumat</button> 
        <button class="filter-btn" data-day="saturday">Sabtu</button> 
        <button class="filter-btn" data-day="sunday">Minggu</button> 
    </div>

    <div class="schedule-grid">
        <?php
        try {
            // Ambil data jadwal dari database
            $stmt = $pdo->query("
                SELECT p.*, u.username as penyiar_name 
                FROM programs p
                LEFT JOIN users u ON p.penyiar_id = u.id
                ORDER BY 
                    CASE p.day_of_week
                        WHEN 'Monday' THEN 1 WHEN 'Senin' THEN 1
                        WHEN 'Tuesday' THEN 2 WHEN 'Selasa' THEN 2
                        WHEN 'Wednesday' THEN 3 WHEN 'Rabu' THEN 3
                        WHEN 'Thursday' THEN 4 WHEN 'Kamis' THEN 4
                        WHEN 'Friday' THEN 5 WHEN 'Jumat' THEN 5
                        WHEN 'Saturday' THEN 6 WHEN 'Sabtu' THEN 6
                        WHEN 'Sunday' THEN 7 WHEN 'Minggu' THEN 7
                        ELSE 8
                    END,
                    p.start_time
            ");

            $currentDayDisplayNormalized = ''; // Untuk melacak grup hari
            $programDisplayed = false; // Untuk melacak apakah ada program yang ditampilkan

            while ($program = $stmt->fetch()) {
                $programDisplayed = true;
                $dayOfWeekDb = strtolower($program['day_of_week']); // day_of_week dari DB, jadikan lowercase

                // Grup berdasarkan hari dari database (English name, lowercase)
                if ($dayOfWeekDb != $currentDayDisplayNormalized) {
                    $currentDayDisplayNormalized = $dayOfWeekDb;
                    // Map untuk tampilan nama hari Bahasa Indonesia
                    $daysMapDisplay = [
                        'monday' => 'Senin', 'tuesday' => 'Selasa', 'wednesday' => 'Rabu',
                        'thursday' => 'Kamis', 'friday' => 'Jumat', 'saturday' => 'Sabtu',
                        'sunday' => 'Minggu'
                    ];
                    $displayDayName = $daysMapDisplay[$currentDayDisplayNormalized] ?? ucfirst($currentDayDisplayNormalized);

                    // data-day-group pada header disamakan dengan tombol filter (lowercase English)
                    echo '<h2 class="schedule-day-header" data-day-group="'.htmlspecialchars($currentDayDisplayNormalized).'">';
                    echo $displayDayName;
                    echo '</h2>';
                }

                // data-day pada program card juga lowercase English
                echo '<div class="program-card" data-day="'.htmlspecialchars($dayOfWeekDb).'">'; 
                echo '<div class="program-time">';
                echo date('H:i', strtotime($program['start_time'])).' - '.date('H:i', strtotime($program['end_time']));
                echo '</div>';
                echo '<div class="program-info">';
                echo '<h3>'.htmlspecialchars($program['title']).'</h3>';
                echo '<p class="program-desc">'.nl2br(htmlspecialchars($program['description'])).'</p>'; // Tambahkan nl2br
                echo '<p class="program-penyiar"><i class="fas fa-user"></i> '.htmlspecialchars($program['penyiar_name'] ?? 'TBA').'</p>';

                if (isLoggedIn()) {
                    // Standarisasi kelas tombol pengingat
                    echo '<button class="program-reminder-btn" data-program-id="'.$program['id'].'">'; 
                    echo '<i class="far fa-bell"></i> Ingatkan Saya'; // Default icon
                    echo '</button>';
                }

                echo '</div>';
                echo '</div>';
            }
             if (!$programDisplayed) { // Jika loop tidak pernah berjalan
                echo '<p style="text-align:center; margin-top:2rem;">Belum ada jadwal siaran yang tersedia.</p>';
            }
        } catch (PDOException $e) {
            echo '<p style="text-align:center; margin-top:2rem;">Gagal memuat jadwal siaran.</p>';
            error_log("Error fetching schedule: " . $e->getMessage());
        }
        ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>