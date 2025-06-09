<?php
require_once '../includes/config.php'; // Memuat konfigurasi dan helper
redirectIfNotAdmin('../login.php?error=Akses+admin+ditolak.'); // Hanya admin yang boleh akses

$edit_program = null;
$success_message = '';
$error_message = '';

// Ambil daftar penyiar untuk dropdown
try {
    $stmt_penyiar = $pdo->query("SELECT id, username FROM users WHERE role = 'penyiar' ORDER BY username ASC");
    $penyiars = $stmt_penyiar->fetchAll();
} catch (PDOException $e) {
    $penyiars = [];
    $error_message = "Gagal memuat daftar penyiar: " . $e->getMessage();
    error_log("Admin Schedule - Load Penyiar Error: " . $e->getMessage());
}

// Daftar Hari dalam Bahasa Indonesia untuk dropdown dan tampilan
// Tabel 'programs' menggunakan ENUM bahasa Inggris untuk day_of_week
$days_of_week_options = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$days_of_week_display_map = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];

// --- PROSES FORM SUBMISSION (CREATE, UPDATE, DELETE) ---

// Fungsi validasi waktu tumpang tindih
function isTimeOverlap($pdo, $day_of_week, $start_time, $end_time, $program_id = null) {
    $sql = "SELECT COUNT(*) FROM programs WHERE day_of_week = :day_of_week AND (
                (start_time < :end_time AND end_time > :start_time)
            )";
    $params = [
        ':day_of_week' => $day_of_week,
        ':start_time' => $start_time,
        ':end_time' => $end_time
    ];

    if ($program_id !== null) {
        $sql .= " AND id != :program_id";
        $params[':program_id'] = $program_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

// Proses Tambah Program
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_program') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $penyiar_id = !empty($_POST['penyiar_id']) ? (int)$_POST['penyiar_id'] : null;

    if (empty($title) || empty($day_of_week) || empty($start_time) || empty($end_time)) {
        $error_message = "Judul Program, Hari, Waktu Mulai, dan Waktu Selesai wajib diisi.";
    } elseif (!in_array($day_of_week, $days_of_week_options)) {
        $error_message = "Hari yang dipilih tidak valid.";
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $error_message = "Waktu Selesai harus setelah Waktu Mulai.";
    } elseif (isTimeOverlap($pdo, $day_of_week, $start_time, $end_time)) { // Validasi tumpang tindih
        $error_message = "Sudah ada program lain yang tumpang tindih pada hari dan jam tersebut. Silakan pilih jadwal lain.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO programs (title, description, day_of_week, start_time, end_time, penyiar_id)
                 VALUES (:title, :description, :day_of_week, :start_time, :end_time, :penyiar_id)"
            );
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':day_of_week' => $day_of_week,
                ':start_time' => $start_time,
                ':end_time' => $end_time,
                ':penyiar_id' => $penyiar_id
            ]);
            $success_message = "Program baru '" . htmlspecialchars($title) . "' berhasil ditambahkan!";
        } catch (PDOException $e) {
            $error_message = "Gagal menambahkan program: " . $e->getMessage();
            error_log("Admin Schedule - Add Program Error: " . $e->getMessage());
        }
    }
}

// Proses Edit Program
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_program') {
    $program_id = $_POST['program_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $penyiar_id = !empty($_POST['penyiar_id']) ? (int)$_POST['penyiar_id'] : null;

    if (empty($title) || empty($day_of_week) || empty($start_time) || empty($end_time) || empty($program_id)) {
        $error_message = "Semua field wajib diisi untuk mengedit program.";
    } elseif (!in_array($day_of_week, $days_of_week_options)) {
        $error_message = "Hari yang dipilih tidak valid.";
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $error_message = "Waktu Selesai harus setelah Waktu Mulai.";
    } elseif (isTimeOverlap($pdo, $day_of_week, $start_time, $end_time, $program_id)) { // Validasi tumpang tindih dengan mengecualikan program itu sendiri
        $error_message = "Perubahan ini akan menyebabkan tumpang tindih dengan program lain pada hari dan jam tersebut. Silakan pilih jadwal lain.";
    } else {
        try {
            $sql = "UPDATE programs SET
                        title = :title,
                        description = :description,
                        day_of_week = :day_of_week,
                        start_time = :start_time,
                        end_time = :end_time,
                        penyiar_id = :penyiar_id
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':day_of_week' => $day_of_week,
                ':start_time' => $start_time,
                ':end_time' => $end_time,
                ':penyiar_id' => $penyiar_id,
                ':id' => $program_id
            ]);
            $success_message = "Program '" . htmlspecialchars($title) . "' berhasil diperbarui!";
        } catch (PDOException $e) {
            $error_message = "Gagal memperbarui program: " . $e->getMessage();
            error_log("Admin Schedule - Edit Program Error: " . $e->getMessage());
        }
    }
}

// Proses Hapus Program
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_program') {
    $program_id_delete = $_POST['program_id_delete'];
    if (!empty($program_id_delete) && ctype_digit((string)$program_id_delete)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM programs WHERE id = :id");
            $stmt->execute([':id' => $program_id_delete]);
            $success_message = "Program berhasil dihapus!";
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                 $error_message = "Gagal menghapus program. Program ini mungkin masih terkait dengan data lain (misalnya, analitik, chat, atau notifikasi). Hapus data terkait terlebih dahulu atau set NULL pada foreign key.";
            } else {
                $error_message = "Gagal menghapus program: " . $e->getMessage();
            }
            error_log("Admin Schedule - Delete Program Error: " . $e->getMessage());
        }
    } else {
        $error_message = "ID Program tidak valid untuk dihapus.";
    }
}


// Ambil data program untuk ditampilkan atau diedit
if (isset($_GET['edit_id']) && ctype_digit((string)$_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM programs WHERE id = :id");
        $stmt->execute([':id' => $edit_id]);
        $edit_program = $stmt->fetch();
        if (!$edit_program) {
            $error_message = "Program dengan ID tersebut tidak ditemukan untuk diedit.";
            unset($_GET['edit_id']);
        }
    } catch (PDOException $e) {
        $error_message = "Gagal memuat data program untuk diedit: " . $e->getMessage();
        error_log("Admin Schedule - Load Edit Program Error: " . $e->getMessage());
    }
}

// Ambil semua program untuk ditampilkan di tabel
try {
    $stmt_programs = $pdo->query(
        "SELECT p.*, u.username AS penyiar_name
         FROM programs p
         LEFT JOIN users u ON p.penyiar_id = u.id
         ORDER BY
            FIELD(p.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
            p.start_time ASC"
    );
    $programs = $stmt_programs->fetchAll();
} catch (PDOException $e) {
    $programs = [];
    if(empty($error_message)) $error_message = "Gagal memuat daftar program: " . $e->getMessage();
    error_log("Admin Schedule - Load Programs Error: " . $e->getMessage());
}


include '../includes/header.php';
?>

<div class="admin-dashboard">
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <ul>
            <li><a href="<?php echo BASE_PATH_PREFIX; ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="active"><a href="<?php echo BASE_PATH_PREFIX; ?>/admin/schedule.php"><i class="fas fa-calendar-alt"></i> Jadwal Siaran</a></li>
            <li><a href="<?php echo BASE_PATH_PREFIX; ?>/admin/ads.php"><i class="fas fa-ad"></i> Manajemen Iklan</a></li>
            <li><a href="<?php echo BASE_PATH_PREFIX; ?>/admin/users.php"><i class="fas fa-users"></i> Manajemen User</a></li>
            <li><a href="<?php echo BASE_PATH_PREFIX; ?>/includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h2>Manajemen Jadwal Siaran</h2>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="form-container card-bg" style="padding: 20px; border-radius: 8px; margin-bottom: 30px; background-color: var(--card-bg);">
            <h3><?php echo $edit_program ? 'Edit Program: ' . htmlspecialchars($edit_program['title']) : 'Tambah Program Baru'; ?></h3>

            <form action="schedule.php<?php echo $edit_program ? '?edit_id='.$edit_program['id'] : ''; ?>" method="POST">
                <?php if ($edit_program): ?>
                    <input type="hidden" name="action" value="edit_program">
                    <input type="hidden" name="program_id" value="<?php echo htmlspecialchars($edit_program['id']); ?>">
                <?php else: ?>
                    <input type="hidden" name="action" value="add_program">
                <?php endif; ?>

                <div class="form-group">
                    <label for="title">Judul Program</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($edit_program['title'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Deskripsi</label>
                    <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($edit_program['description'] ?? ''); ?></textarea>
                </div>
                <div class="form-group-inline" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 180px;">
                        <label for="day_of_week">Hari Siaran</label>
                        <select id="day_of_week" name="day_of_week" required>
                            <option value="">-- Pilih Hari --</option>
                            <?php foreach ($days_of_week_options as $day_key): ?>
                                <option value="<?php echo $day_key; ?>" <?php echo (isset($edit_program['day_of_week']) && $edit_program['day_of_week'] === $day_key) ? 'selected' : ''; ?>>
                                    <?php echo $days_of_week_display_map[$day_key] ?? $day_key; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 150px;">
                        <label for="start_time">Waktu Mulai</label>
                        <input type="time" id="start_time" name="start_time" value="<?php echo htmlspecialchars($edit_program['start_time'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 150px;">
                        <label for="end_time">Waktu Selesai</label>
                        <input type="time" id="end_time" name="end_time" value="<?php echo htmlspecialchars($edit_program['end_time'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="penyiar_id">Penyiar</label>
                    <select id="penyiar_id" name="penyiar_id">
                        <option value="">-- Tidak Ditentukan --</option>
                        <?php if (!empty($penyiars)): ?>
                            <?php foreach ($penyiars as $penyiar): ?>
                                <option value="<?php echo $penyiar['id']; ?>" <?php echo (isset($edit_program['penyiar_id']) && $edit_program['penyiar_id'] == $penyiar['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($penyiar['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>Tidak ada data penyiar</option>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" class="btn-auth" style="margin-top: 10px;">
                    <i class="fas <?php echo $edit_program ? 'fa-save' : 'fa-plus-circle'; ?>"></i>
                    <?php echo $edit_program ? 'Simpan Perubahan' : 'Tambah Program'; ?>
                </button>
                <?php if ($edit_program): ?>
                    <a href="schedule.php" class="btn-auth" style="background-color: var(--text-secondary); margin-left: 10px; text-decoration:none; padding: 0.8rem 1.5rem;">Batal Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <h3 style="margin-top: 30px;">Daftar Program Siaran</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Hari</th>
                        <th>Waktu</th>
                        <th>Judul Program</th>
                        <th>Penyiar</th>
                        <th>Deskripsi Singkat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($programs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">Belum ada program yang dijadwalkan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($programs as $program): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($days_of_week_display_map[$program['day_of_week']] ?? $program['day_of_week']); ?></td>
                                <td><?php echo date('H:i', strtotime($program['start_time'])) . ' - ' . date('H:i', strtotime($program['end_time'])); ?></td>
                                <td><?php echo htmlspecialchars($program['title']); ?></td>
                                <td><?php echo htmlspecialchars($program['penyiar_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(substr($program['description'] ?? '', 0, 50)) . (strlen($program['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                <td class="actions">
                                    <a href="schedule.php?edit_id=<?php echo $program['id']; ?>" class="btn-edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="schedule.php" method="POST" style="display: inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus program ini: <?php echo htmlspecialchars(addslashes($program['title'])); ?>?');">
                                        <input type="hidden" name="action" value="delete_program">
                                        <input type="hidden" name="program_id_delete" value="<?php echo $program['id']; ?>">
                                        <button type="submit" class="btn-delete" title="Hapus">
                                            <i class="fas fa-trash-alt"></i>
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
    /* Tambahkan sedikit style untuk tombol aksi di tabel */
    .actions a, .actions button {
        margin-right: 5px;
        padding: 5px 8px;
        text-decoration: none;
        border: none;
        cursor: pointer;
        border-radius: 4px;
    }
    .btn-edit { background-color: var(--success-color); color: white; }
    .btn-delete { background-color: var(--error-color); color: white; }
    .btn-edit i, .btn-delete i { pointer-events: none; } /* Agar ikon tidak menangkap event klik form */

    .form-group-inline {
        margin-bottom: 1.5rem; /* Samakan dengan .form-group */
    }
    .form-group-inline .form-group {
        margin-bottom: 0; /* Hilangkan margin bawah internal jika sudah dihandle parent */
    }
    /* Style untuk select dan input agar mirip */
    select#day_of_week, select#penyiar_id, input[type="time"], input[type="text"], textarea {
        width: 100%;
        padding: 0.8rem;
        border: 1px solid var(--hover-color);
        border-radius: 5px;
        background-color: var(--secondary-color);
        color: var(--text-color);
        font-size: 1rem;
        outline: none;
    }
    select#day_of_week:focus, select#penyiar_id:focus, input[type="time"]:focus, input[type="text"]:focus, textarea:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.2);
    }
</style>

<?php include '../includes/footer.php'; ?>