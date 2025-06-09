<?php
require_once '../includes/config.php';
redirectIfNotAdmin('../login.php?error=Akses admin ditolak.');

include '../includes/header.php';
?>

<div class="admin-dashboard">
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <ul>
            <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Jadwal Siaran</a></li>
            <li><a href="ads.php"><i class="fas fa-ad"></i> Manajemen Iklan</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Manajemen User</a></li>
            <li><a href="../includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h2>Dashboard Admin</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Pendengar Hari Ini</h4>
                <p class="stat-number">
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT SUM(listeners_count) as total FROM analytics WHERE DATE(analytics_time) = CURDATE()"); // Ganti start_time ke analytics_time
                        $result = $stmt->fetch();
                        echo number_format($result['total'] ?? 0);
                    } catch (PDOException $e) { echo "Error"; error_log("Admin Stat Error: ".$e->getMessage());}
                    ?>
                </p>
                <p class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> N/A % dari kemarin
                </p>
            </div>
            
            <div class="stat-card">
                <h4>Request Lagu Hari Ini</h4>
                <p class="stat-number">
                     <?php
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as total FROM song_requests WHERE DATE(request_time) = CURDATE()");
                        $result = $stmt->fetch();
                        echo number_format($result['total'] ?? 0);
                    } catch (PDOException $e) { echo "Error"; error_log("Admin Stat Error: ".$e->getMessage());}
                    ?>
                </p>
                 <p class="stat-change negative">
                    <i class="fas fa-arrow-down"></i> N/A % dari kemarin
                </p>
            </div>
            
            <div class="stat-card">
                <h4>Pesan Chat Hari Ini</h4>
                <p class="stat-number">
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as total FROM live_chat WHERE DATE(sent_at) = CURDATE()");
                        $result = $stmt->fetch();
                        echo number_format($result['total'] ?? 0);
                    } catch (PDOException $e) { echo "Error"; error_log("Admin Stat Error: ".$e->getMessage());}
                    ?>
                </p>
                <p class="stat-change">
                   N/A % dari kemarin
                </p>
            </div>
            
            <div class="stat-card">
                <h4>Total Program</h4>
                 <p class="stat-number">
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as total FROM programs");
                        $result = $stmt->fetch();
                        echo number_format($result['total'] ?? 0);
                    } catch (PDOException $e) { echo "Error"; error_log("Admin Stat Error: ".$e->getMessage());}
                    ?>
                </p>
                <p class="stat-change">
                  Aktif terjadwal
                </p>
            </div>
        </div>
        
        <div class="recent-activity">
            <h3>Aktivitas Terkini (Contoh)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Aktivitas</th>
                        <th>Detail</th>
                        <th>Pengguna</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        // Get recent song requests
                        $stmtReq = $pdo->query(
                            "SELECT sr.request_time, sr.song_title, u.username 
                             FROM song_requests sr 
                             JOIN users u ON sr.user_id = u.id 
                             ORDER BY sr.request_time DESC 
                             LIMIT 3"
                        );
                        while ($request = $stmtReq->fetch()) {
                            echo '<tr>';
                            echo '<td>' . date('d M, H:i', strtotime($request['request_time'])) . '</td>';
                            echo '<td><span class="badge badge-info">Request Lagu</span></td>';
                            echo '<td>' . htmlspecialchars($request['song_title']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['username']) . '</td>';
                            echo '</tr>';
                        }
                        
                        // Get recent registrations
                        $stmtReg = $pdo->query(
                            "SELECT created_at, username FROM users ORDER BY created_at DESC LIMIT 2"
                        );
                         while ($user = $stmtReg->fetch()) {
                            echo '<tr>';
                            echo '<td>' . date('d M, H:i', strtotime($user['created_at'])) . '</td>';
                            echo '<td><span class="badge badge-success">User Baru</span></td>';
                            echo '<td>Registrasi</td>';
                            echo '<td>' . htmlspecialchars($user['username']) . '</td>';
                            echo '</tr>';
                        }
                        if ($stmtReq->rowCount() == 0 && $stmtReg->rowCount() == 0) {
                             echo '<tr><td colspan="4" style="text-align:center;">Tidak ada aktivitas terkini.</td></tr>';
                        }

                    } catch (PDOException $e) {
                         echo '<tr><td colspan="4" style="text-align:center;">Gagal memuat aktivitas.</td></tr>';
                         error_log("Admin recent activity error: " . $e->getMessage());
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>