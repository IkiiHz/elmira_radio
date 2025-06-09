<?php
// File: admin/users.php
require_once '../includes/config.php'; // Memuat konfigurasi dan helper
redirectIfNotAdmin('../login.php?error=Akses+admin+ditolak.'); // Hanya admin yang boleh akses

$edit_user = null;
$success_message = '';
$error_message = '';

// Daftar peran (role) yang valid sesuai ENUM di database
$roles = ['admin', 'penyiar', 'user', 'owner']; // Pastikan 'owner' sudah ada jika digunakan

// Fungsi untuk menangani upload file gambar profil
function handleProfileImageUpload($file_input_name, $current_image_filename = null) {
    global $error_message; // Akses variabel global untuk pesan error

    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        if (!defined('UPLOAD_DIR_PROFILES') || !defined('UPLOAD_URL_PROFILES')) {
            $error_message = "Konstanta path upload profil tidak terdefinisi.";
            error_log("Upload Error: UPLOAD_DIR_PROFILES or UPLOAD_URL_PROFILES not defined.");
            return $current_image_filename;
        }

        if (!file_exists(UPLOAD_DIR_PROFILES)) {
            if (!@mkdir(UPLOAD_DIR_PROFILES, 0775, true)) {
                 $error_message = "Gagal membuat direktori upload profil: " . UPLOAD_DIR_PROFILES;
                 error_log("Upload Error: Failed to create directory " . UPLOAD_DIR_PROFILES);
                 return $current_image_filename;
            }
        }
        
        if (!is_writable(UPLOAD_DIR_PROFILES)) {
            $error_message = "Direktori upload profil tidak dapat ditulis: " . UPLOAD_DIR_PROFILES;
            error_log("Upload Error: Directory not writable " . UPLOAD_DIR_PROFILES);
            return $current_image_filename;
        }

        $file_tmp_path = $_FILES[$file_input_name]['tmp_name'];
        $file_name = basename($_FILES[$file_input_name]['name']);
        $file_size = $_FILES[$file_input_name]['size'];
        // $file_type = $_FILES[$file_input_name]['type']; // Bisa digunakan untuk validasi MIME type jika perlu
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($file_ext, $allowed_extensions)) {
            $error_message = "Format file gambar tidak diizinkan (hanya jpg, jpeg, png, gif).";
            return $current_image_filename;
        }
        if ($file_size > $max_file_size) {
            $error_message = "Ukuran file gambar terlalu besar (maksimal 2MB).";
            return $current_image_filename;
        }

        $new_file_name = 'profile_' . uniqid('', true) . '.' . $file_ext;
        $destination_path_server = UPLOAD_DIR_PROFILES . $new_file_name;
        
        if (move_uploaded_file($file_tmp_path, $destination_path_server)) {
            // Hapus gambar lama jika ada dan ini adalah proses update (bukan gambar default)
            if ($current_image_filename && file_exists(UPLOAD_DIR_PROFILES . $current_image_filename)) {
                // Hindari menghapus file default jika nama file default disimpan di DB
                // Untuk saat ini, asumsikan $current_image_filename bukan nama file default
                @unlink(UPLOAD_DIR_PROFILES . $current_image_filename);
            }
            return $new_file_name; // Kembalikan nama file baru yang disimpan
        } else {
            $error_message = "Gagal memindahkan file gambar yang diunggah.";
            error_log("Upload Error: Failed to move uploaded file to " . $destination_path_server . ". Check permissions and path.");
            return $current_image_filename;
        }
    } elseif (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_NO_FILE) {
        $error_message = "Terjadi kesalahan saat mengunggah file (Kode Error PHP: " . $_FILES[$file_input_name]['error'] . ").";
        return $current_image_filename;
    }
    return $current_image_filename; // Tidak ada file baru diunggah, kembalikan path lama
}


// --- PROSES FORM SUBMISSION (CREATE, UPDATE, DELETE) ---

// Proses Tambah Pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $profile_image_db_filename = null;

    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error_message = "Username, Email, Password, dan Peran wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password minimal harus 6 karakter.";
    } elseif (!in_array($role, $roles)) {
        $error_message = "Peran yang dipilih tidak valid.";
    } else {
        // Handle upload gambar jika ada file yang dipilih
        if (isset($_FILES['profile_image_file']) && $_FILES['profile_image_file']['error'] === UPLOAD_ERR_OK) {
            $profile_image_db_filename = handleProfileImageUpload('profile_image_file');
        }

        if (empty($error_message)) {
            try {
                $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
                $stmtCheck->execute([':username' => $username, ':email' => $email]);
                if ($stmtCheck->fetch()) {
                    $error_message = "Username atau email sudah terdaftar.";
                    if ($profile_image_db_filename && file_exists(UPLOAD_DIR_PROFILES . $profile_image_db_filename)) {
                        @unlink(UPLOAD_DIR_PROFILES . $profile_image_db_filename);
                    }
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        "INSERT INTO users (username, email, password, role, profile_image_url, created_at) 
                         VALUES (:username, :email, :password, :role, :profile_image_url, NOW())"
                    );
                    $stmt->execute([
                        ':username' => $username,
                        ':email' => $email,
                        ':password' => $hashedPassword,
                        ':role' => $role,
                        ':profile_image_url' => $profile_image_db_filename
                    ]);
                    $success_message = "Pengguna baru '" . htmlspecialchars($username) . "' berhasil ditambahkan!";
                    $_POST = []; // Kosongkan POST
                }
            } catch (PDOException $e) {
                $error_message = "Gagal menambahkan pengguna: " . $e->getMessage();
                error_log("Admin Users - Add User Error: " . $e->getMessage());
                if ($profile_image_db_filename && file_exists(UPLOAD_DIR_PROFILES . $profile_image_db_filename)) {
                    @unlink(UPLOAD_DIR_PROFILES . $profile_image_db_filename);
                }
            }
        }
    }
}

// Proses Edit Pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $user_id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $new_password = $_POST['new_password'];
    $remove_profile_image = isset($_POST['remove_profile_image']) ? (int)$_POST['remove_profile_image'] : 0;

    $stmt_current_user = $pdo->prepare("SELECT profile_image_url FROM users WHERE id = :id");
    $stmt_current_user->execute([':id' => $user_id]);
    $current_user_data = $stmt_current_user->fetch();
    $current_profile_image_filename = $current_user_data['profile_image_url'] ?? null;
    $profile_image_to_update = $current_profile_image_filename;

    if (empty($username) || empty($email) || empty($role) || empty($user_id)) {
        $error_message = "Username, Email, dan Peran wajib diisi untuk mengedit pengguna.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } elseif (!in_array($role, $roles)) {
        $error_message = "Peran yang dipilih tidak valid.";
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error_message = "Password baru minimal harus 6 karakter.";
    } else {
        if ($remove_profile_image == 1 && $current_profile_image_filename) {
            if (file_exists(UPLOAD_DIR_PROFILES . $current_profile_image_filename)) {
                @unlink(UPLOAD_DIR_PROFILES . $current_profile_image_filename);
            }
            $profile_image_to_update = null;
            $current_profile_image_filename = null; // Reflect that current image is now none for this transaction
        }

        if (empty($error_message) && isset($_FILES['profile_image_file']) && $_FILES['profile_image_file']['error'] === UPLOAD_ERR_OK) {
            $new_uploaded_filename = handleProfileImageUpload('profile_image_file', $current_profile_image_filename);
            if (empty($error_message)) { // Cek lagi error dari handleProfileImageUpload
                $profile_image_to_update = $new_uploaded_filename;
            }
        }
        
        if (empty($error_message)) {
            try {
                $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :user_id");
                $stmtCheck->execute([':username' => $username, ':email' => $email, ':user_id' => $user_id]);
                if ($stmtCheck->fetch()) {
                    $error_message = "Username atau email sudah digunakan oleh pengguna lain.";
                    // Jika ada file baru diupload tapi gagal karena duplikat, hapus file baru tersebut
                    if ($profile_image_to_update !== $current_profile_image_filename && $profile_image_to_update !== null && file_exists(UPLOAD_DIR_PROFILES . $profile_image_to_update)) {
                         @unlink(UPLOAD_DIR_PROFILES . $profile_image_to_update);
                    }
                } else {
                    $params = [
                        ':username' => $username,
                        ':email' => $email,
                        ':role' => $role,
                        ':profile_image_url' => $profile_image_to_update,
                        ':id' => $user_id
                    ];
                    $sql_set_parts = ["username = :username", "email = :email", "role = :role", "profile_image_url = :profile_image_url"];
                    
                    if (!empty($new_password)) {
                        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                        $sql_set_parts[] = "password = :password";
                        $params[':password'] = $hashedPassword;
                    }

                    $stmt = $pdo->prepare("UPDATE users SET " . implode(", ", $sql_set_parts) . " WHERE id = :id");
                    $stmt->execute($params);
                    $success_message = "Pengguna '" . htmlspecialchars($username) . "' berhasil diperbarui!";
                    if (isset($_GET['edit_id']) && $_GET['edit_id'] == $user_id) {
                        $stmt_reload = $pdo->prepare("SELECT id, username, email, role, profile_image_url FROM users WHERE id = :id");
                        $stmt_reload->execute([':id' => $user_id]);
                        $edit_user = $stmt_reload->fetch();
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Gagal memperbarui pengguna: " . $e->getMessage();
                error_log("Admin Users - Edit User Error: " . $e->getMessage());
                if ($profile_image_to_update !== $current_profile_image_filename && $profile_image_to_update !== null && file_exists(UPLOAD_DIR_PROFILES . $profile_image_to_update)) {
                     @unlink(UPLOAD_DIR_PROFILES . $profile_image_to_update);
                }
            }
        }
    }
}

// Proses Hapus Pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id_delete = $_POST['user_id_delete'];
    $current_admin_id = getCurrentUserId();

    if (!empty($user_id_delete) && ctype_digit((string)$user_id_delete)) {
        if ($user_id_delete == $current_admin_id) {
            $error_message = "Anda tidak dapat menghapus akun Anda sendiri.";
        } else {
            try {
                // Ambil nama file gambar profil sebelum menghapus user
                $stmt_get_image = $pdo->prepare("SELECT profile_image_url FROM users WHERE id = :id");
                $stmt_get_image->execute([':id' => $user_id_delete]);
                $user_to_delete_data = $stmt_get_image->fetch();
                $image_to_delete = $user_to_delete_data['profile_image_url'] ?? null;

                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $user_id_delete]);

                // Hapus file gambar jika ada setelah user berhasil dihapus dari DB
                if ($image_to_delete && file_exists(UPLOAD_DIR_PROFILES . $image_to_delete)) {
                    @unlink(UPLOAD_DIR_PROFILES . $image_to_delete);
                }
                $success_message = "Pengguna berhasil dihapus!";
            } catch (PDOException $e) {
                 if ($e->getCode() == '23000') { // Integrity constraint violation
                     $error_message = "Gagal menghapus pengguna. Pengguna ini mungkin masih terkait dengan data lain (program, iklan, request, chat, dll). Periksa keterkaitan atau set relasi ke ON DELETE SET NULL jika memungkinkan.";
                 } else {
                    $error_message = "Gagal menghapus pengguna: " . $e->getMessage();
                 }
                error_log("Admin Users - Delete User Error: " . $e->getMessage());
            }
        }
    } else {
        $error_message = "ID Pengguna tidak valid untuk dihapus.";
    }
}

// Ambil data pengguna untuk ditampilkan atau diedit
if (isset($_GET['edit_id']) && ctype_digit((string)$_GET['edit_id'])) {
    $edit_id_get = $_GET['edit_id'];
    try {
        // Pastikan $edit_user tidak di-override jika POST edit gagal dan ada error
        if (!($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user' && !empty($error_message) && $_POST['user_id'] == $edit_id_get)) {
            $stmt = $pdo->prepare("SELECT id, username, email, role, profile_image_url FROM users WHERE id = :id");
            $stmt->execute([':id' => $edit_id_get]);
            $edit_user = $stmt->fetch();
        }
        if (!$edit_user && !($SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user')) { // Cek lagi jika $edit_user belum di-set
            $error_message = "Pengguna dengan ID tersebut tidak ditemukan untuk diedit.";
            unset($_GET['edit_id']); 
        }
    } catch (PDOException $e) {
        $error_message = "Gagal memuat data pengguna untuk diedit: " . $e->getMessage();
        error_log("Admin Users - Load Edit User Error: " . $e->getMessage());
        $edit_user = null; // Pastikan $edit_user null jika gagal load
    }
}


// Ambil semua pengguna untuk ditampilkan di tabel
try {
    $stmt_users = $pdo->query("SELECT id, username, email, role, profile_image_url, created_at FROM users ORDER BY created_at DESC");
    $users_list = $stmt_users->fetchAll();
} catch (PDOException $e) {
    $users_list = [];
    if(empty($error_message)) $error_message = "Gagal memuat daftar pengguna: " . $e->getMessage();
    error_log("Admin Users - Load Users Error: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="admin-dashboard">
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="<?php echo htmlspecialchars(BASE_PATH_PREFIX); ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="<?php echo htmlspecialchars(BASE_PATH_PREFIX); ?>/admin/schedule.php"><i class="fas fa-calendar-alt"></i> Jadwal Siaran</a></li>
            <li><a href="<?php echo htmlspecialchars(BASE_PATH_PREFIX); ?>/admin/ads.php"><i class="fas fa-ad"></i> Manajemen Iklan</a></li>
            <li class="active"><a href="<?php echo htmlspecialchars(BASE_PATH_PREFIX); ?>/admin/users.php"><i class="fas fa-users"></i> Manajemen User</a></li>
            <li><a href="<?php echo htmlspecialchars(BASE_PATH_PREFIX); ?>/includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <h2>Manajemen Pengguna</h2>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="form-container card-bg" style="padding: 20px; border-radius: 8px; margin-bottom: 30px; background-color: var(--card-bg);">
            <h3><?php echo $edit_user ? 'Edit Pengguna: ' . htmlspecialchars($edit_user['username']) : 'Tambah Pengguna Baru'; ?></h3>
            
            <form action="users.php<?php echo $edit_user ? '?edit_id='.htmlspecialchars($edit_user['id']) : ''; ?>" method="POST" enctype="multipart/form-data">
                <?php if ($edit_user): ?>
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($edit_user['id']); ?>">
                <?php else: ?>
                    <input type="hidden" name="action" value="add_user">
                <?php endif; ?>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($edit_user['username'] ?? $_POST['username'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($edit_user['email'] ?? $_POST['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password"><?php echo $edit_user ? 'Password Baru (Kosongkan jika tidak diubah)' : 'Password'; ?></label>
                    <input type="password" id="password" name="<?php echo $edit_user ? 'new_password' : 'password'; ?>" <?php echo !$edit_user ? 'required' : ''; ?> placeholder="<?php echo $edit_user ? 'Minimal 6 karakter jika diisi' : 'Minimal 6 karakter'; ?>">
                </div>
                 <div class="form-group">
                    <label for="role">Peran (Role)</label>
                    <select id="role" name="role" required>
                        <option value="">-- Pilih Peran --</option>
                        <?php foreach ($roles as $role_value): ?>
                            <option value="<?php echo htmlspecialchars($role_value); ?>" <?php echo (isset($edit_user['role']) && $edit_user['role'] === $role_value) || (isset($_POST['role']) && $_POST['role'] === $role_value) ? 'selected' : ''; ?>>
                                <?php echo ucfirst(htmlspecialchars($role_value)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="profile_image_file">Foto Profil (Opsional, maks 2MB, tipe: jpg, png, gif)</label>
                    <input type="file" id="profile_image_file" name="profile_image_file" accept="image/jpeg, image/png, image/gif">
                    <?php if ($edit_user && !empty($edit_user['profile_image_url'])): ?>
                        <div style="margin-top: 10px;">
                            <img src="<?php echo htmlspecialchars(UPLOAD_URL_PROFILES . $edit_user['profile_image_url']); ?>?t=<?php echo time(); // Cache buster ?>" alt="Foto Profil Saat Ini" style="max-width: 80px; max-height: 80px; border-radius: 50%; vertical-align: middle; margin-right:10px; border: 2px solid var(--border-color);">
                            <input type="checkbox" name="remove_profile_image" id="remove_profile_image_<?php echo htmlspecialchars($edit_user['id']); ?>" value="1" style="vertical-align: middle;">
                            <label for="remove_profile_image_<?php echo htmlspecialchars($edit_user['id']); ?>" style="font-weight:normal; font-size:0.9em; display:inline;">Hapus foto profil saat ini</label>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-auth" style="margin-top: 10px;">
                    <i class="fas <?php echo $edit_user ? 'fa-save' : 'fa-user-plus'; ?>"></i> 
                    <?php echo $edit_user ? 'Simpan Perubahan' : 'Tambah Pengguna'; ?>
                </button>
                <?php if ($edit_user): ?>
                    <a href="users.php" class="btn-auth btn-cancel" style="margin-left: 10px;">Batal Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <h3 style="margin-top: 30px;">Daftar Pengguna</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Foto</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Peran</th>
                        <th>Tanggal Daftar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users_list)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">Belum ada data pengguna.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users_list as $user_item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user_item['id']); ?></td>
                                <td>
                                    <?php if (!empty($user_item['profile_image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars(UPLOAD_URL_PROFILES . $user_item['profile_image_url']); ?>?t=<?php echo time(); // Cache buster ?>" alt="Profil" class="table-profile-img">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle table-profile-icon"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user_item['username']); ?></td>
                                <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                                <td><span class="role-<?php echo htmlspecialchars(strtolower($user_item['role'])); ?>"><?php echo ucfirst(htmlspecialchars($user_item['role'])); ?></span></td>
                                <td><?php echo date('d M Y, H:i', strtotime($user_item['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="users.php?edit_id=<?php echo $user_item['id']; ?>" class="btn-action btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($user_item['id'] != getCurrentUserId()): ?>
                                        <form action="users.php" method="POST" style="display: inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengguna \'<?php echo htmlspecialchars(addslashes($user_item['username'])); ?>\' ini? Aksi ini tidak dapat dibatalkan dan akan menghapus data terkait yang memiliki foreign key ON DELETE CASCADE.');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id_delete" value="<?php echo $user_item['id']; ?>">
                                            <button type="submit" class="btn-action btn-delete" title="Hapus">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn-action btn-delete" title="Tidak dapat menghapus akun sendiri" disabled>
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
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
    .btn-auth.btn-cancel { /* Style khusus untuk tombol batal edit */
        background-color: var(--text-secondary);
        color: var(--secondary-color); /* Atau var(--card-bg) agar kontras */
        text-decoration:none; 
        padding: 0.8rem 1.5rem; /* Samakan dengan tombol submit jika perlu */
    }
    .btn-auth.btn-cancel:hover {
        background-color: var(--hover-color); /* Warna hover yang sesuai */
    }
    .table-profile-img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid var(--border-color);
    }
    .table-profile-icon {
        font-size: 30px;
        color: var(--text-secondary); /* Warna ikon placeholder */
        opacity: 0.7;
    }
</style>
<?php include '../includes/footer.php'; ?>