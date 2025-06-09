<?php
// File: includes/auth.php
require_once __DIR__ . '/config.php'; // Memuat konfigurasi dan koneksi $pdo

// ------------------------------------------------------------------------------------
// FUNGSI HELPER OTENTIKASI DAN PERAN
// ------------------------------------------------------------------------------------
// Catatan: Fungsi-fungsi ini mungkin sudah ada di config.php.
// Pemeriksaan `if (!function_exists(...))` membantu mencegah error "cannot redeclare function".

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('getUserRole')) {
    function getUserRole() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['role'] ?? null;
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isLoggedIn() && getUserRole() === 'admin';
    }
}

if (!function_exists('isPenyiar')) {
    function isPenyiar() {
        return isLoggedIn() && getUserRole() === 'penyiar';
    }
}

if (!function_exists('redirectIfNotPenyiar')) {
    function redirectIfNotPenyiar($redirect_url = '../login.php?error=Akses+Ditolak') {
        if (!isPenyiar()) {
            $baseRedirect = defined('BASE_PATH_PREFIX') ? rtrim(BASE_PATH_PREFIX, '/') : '';
            header('Location: ' . $baseRedirect . '/' . ltrim($redirect_url, '/'));
            exit();
        }
    }
}
if (!function_exists('redirectIfNotAdmin')) {
    function redirectIfNotAdmin($redirect_url = '../login.php?error=Akses+Ditolak') {
        if (!isAdmin()) {
            $baseRedirect = defined('BASE_PATH_PREFIX') ? rtrim(BASE_PATH_PREFIX, '/') : '';
            header('Location: ' . $baseRedirect . '/' . ltrim($redirect_url, '/'));
            exit();
        }
    }
}
if (!function_exists('redirectIfNotLoggedIn')) {
    function redirectIfNotLoggedIn($redirect_url = 'login.php?error=Akses+Ditolak') {
        if (!isLoggedIn()) {
            $baseRedirect = defined('BASE_PATH_PREFIX') ? rtrim(BASE_PATH_PREFIX, '/') : '';
            header('Location: ' . $baseRedirect . '/' . ltrim($redirect_url, '/'));
            exit();
        }
    }
}


// ------------------------------------------------------------------------------------
// FUNGSI JSON RESPONSE
// ------------------------------------------------------------------------------------
if (!function_exists('json_response')) {
    function json_response($success, $data = [], $message = '') {
        header('Content-Type: application/json');
        $response = ['success' => (bool)$success];
        if ($message) {
            $response['message'] = $message;
        }
        // Jika $data adalah array asosiatif dan sukses, merge langsung key-nya ke root response
        if (!empty($data) || (is_array($data) && $success)) {
            if (is_array($data) && array_keys($data) !== range(0, count($data) - 1) && $success) {
                $response = array_merge($response, $data);
            } else {
                // Jika $data adalah array non-asosiatif atau bukan array, atau jika tidak sukses tapi data ada
                $response['data'] = $data;
            }
        }
        echo json_encode($response);
        exit;
    }
}


// ------------------------------------------------------------------------------------
// PENANGANAN REQUEST METHOD (AKSI UTAMA FILE INI)
// ------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            json_response(false, [], 'Username dan password tidak boleh kosong.');
        }

        try {
            $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = :username OR email = :username_as_email");
            $stmt->execute(['username' => $username, 'username_as_email' => $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if (session_status() === PHP_SESSION_NONE) { session_start(); }
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                $redirect_url = rtrim(BASE_PATH_PREFIX, '/') . '/index.php'; // Default redirect
                switch ($user['role']) {
                    case 'admin':
                        $redirect_url = rtrim(BASE_PATH_PREFIX, '/') . '/admin/dashboard.php';
                        break; 
                    case 'penyiar':
                        $redirect_url = rtrim(BASE_PATH_PREFIX, '/') . '/penyiar/dashboard.php';
                        break; 
                    // case 'user': (sudah default)
                }
                json_response(true, ['redirect_url' => $redirect_url], 'Login berhasil!');
            } else {
                json_response(false, [], 'Username atau password salah.');
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            json_response(false, [], 'Terjadi kesalahan database saat login.');
        }
    } elseif ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = 'user'; // Default role

        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            json_response(false, [], 'Semua field tidak boleh kosong.');
        }
        if ($password !== $confirm_password) {
            json_response(false, [], 'Password dan konfirmasi password tidak cocok.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(false, [], 'Format email tidak valid.');
        }
        if (strlen($password) < 6) {
            json_response(false, [], 'Password minimal 6 karakter.');
        }

        try {
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
            $stmtCheck->execute(['username' => $username, 'email' => $email]);
            if ($stmtCheck->fetch()) {
                json_response(false, [], 'Username atau email sudah terdaftar.');
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (:username, :email, :password, :role, NOW())");
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password' => $hashed_password,
                'role' => $role
            ]);
            json_response(true, [], 'Registrasi berhasil! Silakan login.');

        } catch (PDOException $e) {
            error_log("Register Error: " . $e->getMessage());
            json_response(false, [], 'Terjadi kesalahan database saat registrasi.');
        }

    } elseif ($action === 'update_now_playing') {
        if (!isLoggedIn() || !isPenyiar()) {
            json_response(false, [], 'Akses ditolak. Hanya penyiar yang dapat melakukan aksi ini.');
        }

        $program_id = isset($_POST['program_id']) && !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
        $song_file_name = trim($_POST['song_file_name'] ?? '');
        $song_title = trim($_POST['song_title'] ?? '');
        $artist_name = trim($_POST['artist_name'] ?? '');
        $album_art_url = trim($_POST['album_art_url'] ?? '');
        $penyiar_id = getCurrentUserId();

        if (empty($song_file_name) || empty($song_title)) {
            json_response(false, [], 'Nama file lagu dan judul lagu tidak boleh kosong.');
        }

        $web_accessible_song_path = rtrim(BASE_PATH_PREFIX, '/') . '/assets/music/' . ltrim($song_file_name, '/');

        try {
            $pdo->beginTransaction();

            $stmtDeactivate = $pdo->prepare(
                "UPDATE now_playing_stream SET is_active = 0 
                 WHERE penyiar_id = :penyiar_id AND is_active = 1"
            );
            $stmtDeactivate->execute([':penyiar_id' => $penyiar_id]);

            $stmtInsert = $pdo->prepare(
                "INSERT INTO now_playing_stream 
                    (program_id, penyiar_id, song_title, artist_name, album_art_url, song_file_path, is_active, updated_at) 
                 VALUES 
                    (:program_id, :penyiar_id, :song_title, :artist_name, :album_art_url, :song_file_path, 1, NOW())"
            );
            $stmtInsert->execute([
                ':program_id' => $program_id,
                ':penyiar_id' => $penyiar_id,
                ':song_title' => $song_title,
                ':artist_name' => $artist_name,
                ':album_art_url' => $album_art_url,
                ':song_file_path' => $web_accessible_song_path,
            ]);

            $pdo->commit();
            json_response(true, ['song_file_path' => $web_accessible_song_path], 'Info "Now Playing" berhasil diperbarui!');

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update Now Playing Error: " . $e->getMessage());
            json_response(false, [], 'Gagal memperbarui info "Now Playing" karena kesalahan database.');
        }
    } elseif ($action === 'request_song') {
        if (!isLoggedIn()) {
            json_response(false, [], 'Anda harus login untuk request lagu.');
        }
        $user_id = getCurrentUserId();
        $song_title = trim($_POST['song_title'] ?? '');
        $artist = trim($_POST['artist'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($song_title) || empty($artist)) {
            json_response(false, [], 'Judul lagu dan artis tidak boleh kosong.');
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO song_requests (user_id, song_title, artist, message, status, request_time) 
                 VALUES (:user_id, :song_title, :artist, :message, 'pending', NOW())"
            );
            $stmt->execute([
                'user_id' => $user_id,
                'song_title' => $song_title,
                'artist' => $artist,
                'message' => $message,
            ]);
            json_response(true, [], 'Request lagu berhasil dikirim!');
        } catch (PDOException $e) {
            error_log("Song Request Error: " . $e->getMessage());
            json_response(false, [], 'Gagal mengirim request lagu. Kesalahan database.');
        }
    } elseif ($action === 'send_chat_message') {
        if (!isLoggedIn()) {
            json_response(false, [], 'Anda harus login untuk mengirim pesan chat.');
        }
        $user_id = getCurrentUserId();
        $message = trim($_POST['message'] ?? '');
        $program_id = isset($_POST['program_id']) && !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;

        if (empty($message)) {
            json_response(false, [], 'Pesan tidak boleh kosong.');
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO live_chat (user_id, message, program_id, sent_at) 
                 VALUES (:user_id, :message, :program_id, NOW())"
            );
            $stmt->execute([
                'user_id' => $user_id,
                'message' => $message,
                'program_id' => $program_id
            ]);
            $chat_message_id = $pdo->lastInsertId();
            
            // Mengambil role pengguna untuk disertakan dalam respons
            $stmtFetch = $pdo->prepare(
                "SELECT lc.id, u.username, u.role as user_role, lc.message, DATE_FORMAT(lc.sent_at, '%H:%i') as time 
                 FROM live_chat lc JOIN users u ON lc.user_id = u.id 
                 WHERE lc.id = :id"
            );
            $stmtFetch->execute(['id' => $chat_message_id]);
            $chat_message = $stmtFetch->fetch(PDO::FETCH_ASSOC);

            json_response(true, ['chat_message' => $chat_message], 'Pesan berhasil dikirim.');
        } catch (PDOException $e) {
            error_log("Send Chat Message Error: " . $e->getMessage());
            json_response(false, [], 'Gagal mengirim pesan chat. Kesalahan database.');
        }
    } elseif ($action === 'update_request_status') {
         if (!isLoggedIn() || !isPenyiar()) {
            json_response(false, [], 'Akses ditolak.');
        }
        $request_id_input = $_POST['request_id'] ?? null;
        $status = $_POST['status'] ?? null;

        if (!$request_id_input || !ctype_digit((string)$request_id_input) || !in_array($status, ['played', 'rejected'])) {
            json_response(false, [], 'Data tidak valid.');
        }
        $request_id = (int)$request_id_input;

        try {
            $stmt = $pdo->prepare("UPDATE song_requests SET status = :status WHERE id = :request_id");
            $stmt->execute(['status' => $status, 'request_id' => $request_id]);
            if ($stmt->rowCount() > 0) {
                json_response(true, [], 'Status request berhasil diperbarui.');
            } else {
                json_response(false, [], 'Request tidak ditemukan atau status sudah sama.');
            }
        } catch (PDOException $e) {
            error_log("Update Request Status Error: " . $e->getMessage());
            json_response(false, [], 'Gagal memperbarui status request.');
        }
    } elseif ($action === 'set_program_reminder') {
        if (!isLoggedIn()) {
            json_response(false, [], 'Silakan login untuk mengatur pengingat.');
        }
        $user_id = getCurrentUserId();
        $program_id_input = $_POST['program_id'] ?? null;

        if (!$program_id_input || !ctype_digit((string)$program_id_input)) {
             json_response(false, [], 'ID Program tidak valid.');
        }
        $program_id = (int)$program_id_input;

        try {
            $stmtCheck = $pdo->prepare("SELECT id FROM notifications WHERE user_id = :user_id AND program_id = :program_id");
            $stmtCheck->execute(['user_id' => $user_id, 'program_id' => $program_id]);
            $existing_reminder = $stmtCheck->fetch();

            if ($existing_reminder) {
                $stmtDelete = $pdo->prepare("DELETE FROM notifications WHERE id = :id");
                $stmtDelete->execute(['id' => $existing_reminder['id']]);
                json_response(true, ['status' => 'removed', 'button_text' => '<i class="far fa-bell"></i> Ingatkan Saya'], 'Pengingat dibatalkan.');
            } else {
                $stmtProgram = $pdo->prepare("SELECT start_time, day_of_week FROM programs WHERE id = :program_id");
                $stmtProgram->execute(['program_id' => $program_id]);
                $program = $stmtProgram->fetch();

                if (!$program) {
                    json_response(false, [], 'Program tidak ditemukan.');
                }
                
                $timezone = new DateTimeZone('Asia/Jakarta'); // Sesuaikan dengan zona waktu server/target Anda
                $now = new DateTime('now', $timezone);
                
                $programTimeStr = $program['start_time'];
                $programDayOfWeek = $program['day_of_week']; // e.g., 'Monday', 'Tuesday'

                // Buat objek DateTime untuk waktu program pada hari yang ditentukan
                $targetDateTime = new DateTime('now', $timezone);
                // Set hari ke $programDayOfWeek, lalu set waktu ke $programTimeStr
                // Ini cara yang lebih aman untuk memastikan tanggal yang benar
                $targetDateTime->modify($programDayOfWeek); 
                list($hour, $minute, $second) = explode(':', $programTimeStr);
                $targetDateTime->setTime((int)$hour, (int)$minute, (int)$second);


                // Jika target waktu untuk hari tersebut di minggu ini sudah lewat, set untuk minggu depan
                if ($targetDateTime < $now) {
                    $targetDateTime->modify('+1 week');
                }
                
                $targetDateTime->modify('-15 minutes'); // Pengingat 15 menit sebelumnya
                $notification_time = $targetDateTime->format('Y-m-d H:i:s');

                $stmtInsert = $pdo->prepare("INSERT INTO notifications (user_id, program_id, notification_time, status) VALUES (:user_id, :program_id, :notification_time, 'unread')");
                $stmtInsert->execute([
                    'user_id' => $user_id,
                    'program_id' => $program_id,
                    'notification_time' => $notification_time,
                ]);
                json_response(true, ['status' => 'added', 'button_text' => '<i class="fas fa-check-circle"></i> Pengingat Aktif'], 'Pengingat berhasil diatur!');
            }
        } catch (Exception $e) { // Catch Exception umum untuk DateTime errors
            error_log("Set Program Reminder Error: " . $e->getMessage());
            json_response(false, [], 'Gagal mengatur pengingat: ' . $e->getMessage());
        }
    }
    else {
        json_response(false, [], 'Aksi POST tidak valid atau tidak dikenal.');
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'logout') {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        session_destroy();
        header('Location: ' . rtrim(BASE_PATH_PREFIX, '/') . '/login.php');
        exit;
    } elseif ($action === 'now_playing_info') {
        try {
            $stmt = $pdo->prepare(
                "SELECT nps.song_title, nps.artist_name, nps.album_art_url, nps.song_file_path, u.username as penyiar_username
                 FROM now_playing_stream nps
                 JOIN users u ON nps.penyiar_id = u.id
                 WHERE nps.is_active = 1 
                 ORDER BY nps.updated_at DESC 
                 LIMIT 1"
            );
            $stmt->execute();
            $song = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($song) {
                if (empty($song['album_art_url'])) {
                    $song['album_art_url'] = rtrim(BASE_PATH_PREFIX, '/') . '/assets/images/logo.png';
                }
                if ($song['song_file_path'] && !preg_match('/^(http|\/\/)/', $song['song_file_path'])) {
                     $prefixedPath = rtrim(BASE_PATH_PREFIX, '/');
                     if (substr($song['song_file_path'], 0, strlen($prefixedPath)) !== $prefixedPath) {
                        $song['song_file_path'] = $prefixedPath . '/' . ltrim($song['song_file_path'], '/');
                     }
                }
                json_response(true, ['song' => $song]);
            } else {
                $default_song_info = [
                    'song_title' => 'Elmira 95.8 FM',
                    'artist_name' => 'Musik Terbaik Untuk Anda',
                    'album_art_url' => rtrim(BASE_PATH_PREFIX, '/') . '/assets/images/logo.png',
                    'song_file_path' => null,
                    'penyiar_username' => 'Elmira FM'
                ];
                json_response(true, ['song' => $default_song_info], 'Tidak ada lagu yang sedang diputar secara spesifik.');
            }
        } catch (PDOException $e) {
            error_log("GET Now Playing Info Error: " . $e->getMessage());
            json_response(false, ['song' => null], 'Gagal mengambil info lagu.');
        }
    } elseif ($action === 'get_chat_messages') {
        $limit = isset($_GET['limit']) && ctype_digit((string)$_GET['limit']) ? (int)$_GET['limit'] : 50;
        $last_message_id = isset($_GET['last_message_id']) && ctype_digit((string)$_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;
        $program_id_filter = isset($_GET['program_id_chat']) && ctype_digit((string)$_GET['program_id_chat']) ? (int)$_GET['program_id_chat'] : null;

        try {
            // Mengambil juga u.role untuk membedakan warna username di chat
            $sql = "SELECT lc.id, u.username, u.role as user_role, lc.message, DATE_FORMAT(lc.sent_at, '%H:%i') as time, lc.program_id
                    FROM live_chat lc 
                    JOIN users u ON lc.user_id = u.id 
                    WHERE lc.id > :last_message_id";
            
            if ($program_id_filter) {
                $sql .= " AND (lc.program_id = :program_id_filter OR lc.program_id IS NULL)";
            } else {
                $sql .= " AND lc.program_id IS NULL";
            }
            
            $sql .= " ORDER BY lc.sent_at ASC LIMIT :limit_val";

            $stmt = $pdo->prepare($sql);
            
            $stmt->bindParam(':last_message_id', $last_message_id, PDO::PARAM_INT);
            if ($program_id_filter) {
                $stmt->bindParam(':program_id_filter', $program_id_filter, PDO::PARAM_INT);
            }
            $stmt->bindParam(':limit_val', $limit, PDO::PARAM_INT);

            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(true, ['messages' => $messages]);
        } catch (PDOException $e) {
            error_log("Get Chat Messages Error: " . $e->getMessage());
            json_response(false, ['messages' => []], 'Gagal mengambil pesan chat.');
        }
    }
    else {
        json_response(false, [], 'Aksi GET tidak valid atau tidak dikenal.');
    }
} else {
     json_response(false, [], 'Metode request tidak valid atau tidak didukung.');
}
?>