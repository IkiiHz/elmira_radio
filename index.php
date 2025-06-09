<?php include 'includes/header.php'; ?>

<div class="hero">
    <div class="hero-content">
        <img src="assets/images/logo.png" alt="ELMIRA 95.8 FM" class="logo-large">
        <h1>ELMIRA 95.8 FM</h1>
        <p>Radio dengan kualitas suara terbaik dan program menarik</p>
        <a href="streaming.php" class="btn-live">Dengarkan Sekarang</a>
    </div>
</div>

<div class="features">
    <div class="feature">
        <i class="fas fa-music"></i>
        <h3>Request Lagu</h3>
        <p>Minta lagu favorit Anda untuk diputar di radio kami</p>
    </div>
    <div class="feature">
        <i class="fas fa-comments"></i>
        <h3>Live Chat</h3>
        <p>Berinteraksi langsung dengan penyiar selama siaran</p>
    </div>
    <div class="feature">
        <i class="fas fa-bell"></i>
        <h3>Notifikasi</h3>
        <p>Dapatkan pemberitahuan untuk program favorit Anda</p>
    </div>
</div>


<div class="now-playing">
    <h2>Sedang Diputar</h2>
    <div class="player">
        <div class="song-info">
            <img src="https://via.placeholder.com/80" alt="Album Cover" class="album-cover" id="main-now-playing-cover">
            <div>
                <h4 class="song-title" id="main-now-playing-title">Judul Lagu</h4>
                <p class="artist" id="main-now-playing-artist">Nama Artis</p>
            </div>
        </div>
        <div class="player-controls">
            <button class="play-btn" id="main-play-btn"><i class="fas fa-play"></i></button>
            <div class="progress-bar">
                <div class="progress" id="main-progress"></div>
            </div>
            </div>
    </div>
</div>

<div class="upcoming-shows">
    <h2>Acara Mendatang</h2>
    <div class="shows-grid">
        <?php
        try {
            $stmt = $pdo->query("SELECT p.*, u.username as penyiar_name 
                                FROM programs p 
                                LEFT JOIN users u ON p.penyiar_id = u.id 
                                ORDER BY DAYOFWEEK(STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', 
                                    CASE p.day_of_week 
                                        WHEN 'Monday' THEN '01' WHEN 'Tuesday' THEN '02' WHEN 'Wednesday' THEN '03' 
                                        WHEN 'Thursday' THEN '04' WHEN 'Friday' THEN '05' WHEN 'Saturday' THEN '06' 
                                        WHEN 'Sunday' THEN '07' 
                                    END, '-01'), '%Y-%m-%d')) ASC, p.start_time ASC LIMIT 3");
            while ($program = $stmt->fetch()) {
         echo '<div class="show-card">';
            echo '<h4>' . htmlspecialchars($program['title']) . '</h4>';
            echo '<p><i class="fas fa-clock"></i> ' . date('H:i', strtotime($program['start_time'])) . ' - ' . date('H:i', strtotime($program['end_time'])) . '</p>';
            echo '<p><i class="fas fa-calendar-day"></i> ' . htmlspecialchars($program['day_of_week']) . '</p>';
            if (isLoggedIn()) {
                // Standarisasi kelas tombol pengingat
                echo '<button class="program-reminder-btn" data-program-id="' . $program['id'] . '"><i class="far fa-bell"></i> Ingatkan Saya</button>';
            }
            echo '</div>';
            }
        } catch (PDOException $e) {
            echo '<p>Gagal memuat acara mendatang.</p>';
            error_log("Error fetching upcoming shows: " . $e->getMessage());
        }
        ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>