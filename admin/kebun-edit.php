<?php
require_once 'auth.php';
requireAdminLogin();
require_once '../config/database.php';

$db = getDBConnection();
$error = '';
$success = '';

// Get kebun ID from GET or POST
$kebun_id = (int)($_GET['id'] ?? $_POST['kebun_id'] ?? 0);

if ($kebun_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

// Fetch existing Kebun, Pekebun, and Tanam Semula details
$stmt = $db->prepare("
    SELECT 
        k.*,
        p.id AS pekebun_id, p.nama AS nama_pekebun, p.no_telefon, p.alamat,
        ts.id AS tanam_semula_id, ts.no_tanam_semula, ts.tahun_tanam_semula, ts.keluasan_diluluskan, ts.bantuan_ansuran
    FROM kebun k
    JOIN pekebun p ON k.pekebun_id = p.id
    LEFT JOIN tanam_semula ts ON k.id = ts.kebun_id
    WHERE k.id = ?
");
$stmt->execute([$kebun_id]);
$kebun = $stmt->fetch();

if (!$kebun) {
    header('Location: dashboard.php');
    exit();
}

// Fetch all pekebun list for selection option
$stmt_pekebun = $db->prepare("SELECT id, nama, no_telefon, alamat FROM pekebun ORDER BY nama ASC");
$stmt_pekebun->execute();
$pekebun_list = $stmt_pekebun->fetchAll();

// Check if current kebun has a pelan lot image/file
$pelan_lot_blob = $kebun['pelan_lot'] ?? null;
$has_pelan_lot = !empty($pelan_lot_blob);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pekebun_mode = $_POST['pekebun_mode'] ?? 'current'; // 'current', 'existing', or 'new_pekebun'
    
    try {
        $db->beginTransaction();

        $pekebun_id = $kebun['pekebun_id'];

        // 1. Handle Pekebun update/assignment
        if ($pekebun_mode === 'current') {
            // Update current pekebun's details
            $nama_pekebun = trim($_POST['nama_pekebun_current'] ?? '');
            $no_telefon = trim($_POST['no_telefon_current'] ?? '');
            $alamat = trim($_POST['alamat_current'] ?? '');

            if (empty($nama_pekebun) || empty($no_telefon) || empty($alamat)) {
                throw new Exception('Sila isi semua maklumat pekebun semasa.');
            }

            $stmt = $db->prepare("UPDATE pekebun SET nama = ?, no_telefon = ?, alamat = ? WHERE id = ?");
            $stmt->execute([$nama_pekebun, $no_telefon, $alamat, $pekebun_id]);

        } elseif ($pekebun_mode === 'existing') {
            // Assign to a different existing pekebun
            $selected_pekebun_id = (int)($_POST['pekebun_id_existing'] ?? 0);
            if ($selected_pekebun_id <= 0) {
                throw new Exception('Sila pilih pekebun sedia ada yang sah.');
            }
            $pekebun_id = $selected_pekebun_id;

        } elseif ($pekebun_mode === 'new_pekebun') {
            // Register a new pekebun and assign
            $nama_pekebun = trim($_POST['nama_pekebun_new'] ?? '');
            $no_telefon = trim($_POST['no_telefon_new'] ?? '');
            $alamat = trim($_POST['alamat_new'] ?? '');

            if (empty($nama_pekebun) || empty($no_telefon) || empty($alamat)) {
                throw new Exception('Sila isi semua maklumat pekebun baharu.');
            }

            $stmt = $db->prepare("INSERT INTO pekebun (nama, no_telefon, alamat) VALUES (?, ?, ?)");
            $stmt->execute([$nama_pekebun, $no_telefon, $alamat]);
            $pekebun_id = (int)$db->lastInsertId();
        }

        // 2. Handle Kebun Data
        $no_lot = trim($_POST['no_lot'] ?? '');
        $keluasan_kebun = trim($_POST['keluasan_kebun'] ?? '');
        $lokasi_kebun = trim($_POST['lokasi_kebun'] ?? '');
        $mukim = trim($_POST['mukim'] ?? '');
        $daerah = trim($_POST['daerah'] ?? '');
        $klon_getah = trim($_POST['klon_getah'] ?? '');
        $jumlah_pokok = !empty($_POST['jumlah_pokok']) ? (int)$_POST['jumlah_pokok'] : null;
        $tahun_tanam = !empty($_POST['tahun_tanam']) ? (int)$_POST['tahun_tanam'] : null;
        $tahun_sulaman = !empty($_POST['tahun_sulaman']) ? (int)$_POST['tahun_sulaman'] : null;
        $jarak_tanaman = trim($_POST['jarak_tanaman'] ?? '');
        $koordinat = trim($_POST['koordinat'] ?? '');

        if (empty($no_lot) || empty($keluasan_kebun) || empty($lokasi_kebun) || empty($daerah) || empty($klon_getah)) {
            throw new Exception('Sila isi semua medan wajib (*) untuk maklumat kebun.');
        }

        // Handle Pelan Lot Image File Upload / Removal
        $remove_pelan_lot = isset($_POST['remove_pelan_lot']) && $_POST['remove_pelan_lot'] === '1';
        $new_pelan_lot_blob = $pelan_lot_blob;

        if ($remove_pelan_lot) {
            $new_pelan_lot_blob = null;
        }

        if (!empty($_FILES['pelan_lot_file']['name'])) {
            $max_file_size = 5 * 1024 * 1024; // 5MB
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

            $file = $_FILES['pelan_lot_file'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_error = $file['error'];
            $file_type = $file['type'];

            if ($file_error !== UPLOAD_ERR_OK) {
                throw new Exception('Ralat semasa memuat naik fail pelan lot. Sila cuba lagi.');
            }

            if ($file_size > $max_file_size) {
                throw new Exception('Saiz fail pelan lot terlalu besar. Maksimum 5MB.');
            }

            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('Jenis fail tidak dibenarkan. Sila gunakan JPG, PNG, GIF, atau PDF.');
            }

            if (!in_array($file_type, $allowed_mimes)) {
                throw new Exception('Jenis MIME fail tidak sah.');
            }

            $file_content = file_get_contents($file_tmp);
            if ($file_content === false) {
                throw new Exception('Gagal membaca kandungan fail pelan lot.');
            }

            $new_pelan_lot_blob = $file_content;
        }

        // Update Kebun Record
        $stmt = $db->prepare("
            UPDATE kebun SET
                pekebun_id = ?,
                no_lot = ?,
                keluasan_kebun = ?,
                lokasi_kebun = ?,
                mukim = ?,
                daerah = ?,
                klon_getah = ?,
                jumlah_pokok = ?,
                tahun_tanam = ?,
                tahun_sulaman = ?,
                jarak_tanaman = ?,
                koordinat = ?,
                pelan_lot = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $pekebun_id,
            $no_lot,
            (float)$keluasan_kebun,
            $lokasi_kebun,
            $mukim,
            $daerah,
            $klon_getah,
            $jumlah_pokok,
            $tahun_tanam,
            $tahun_sulaman,
            $jarak_tanaman,
            $koordinat,
            $new_pelan_lot_blob,
            $kebun_id
        ]);

        // 3. Handle Tanam Semula Data
        $no_tanam_semula = trim($_POST['no_tanam_semula'] ?? '');
        $tahun_tanam_semula = !empty($_POST['tahun_tanam_semula']) ? (int)$_POST['tahun_tanam_semula'] : null;
        $keluasan_diluluskan = !empty($_POST['keluasan_diluluskan']) ? (float)$_POST['keluasan_diluluskan'] : null;
        $bantuan_ansuran = trim($_POST['bantuan_ansuran'] ?? '');

        $has_tanam_semula_input = (!empty($no_tanam_semula) || !empty($tahun_tanam_semula) || !empty($keluasan_diluluskan) || !empty($bantuan_ansuran));

        if (!empty($kebun['tanam_semula_id'])) {
            // Update existing Tanam Semula
            $stmt = $db->prepare("
                UPDATE tanam_semula SET
                    no_tanam_semula = ?,
                    tahun_tanam_semula = ?,
                    keluasan_diluluskan = ?,
                    bantuan_ansuran = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $no_tanam_semula,
                $tahun_tanam_semula,
                $keluasan_diluluskan,
                $bantuan_ansuran,
                $kebun['tanam_semula_id']
            ]);
        } elseif ($has_tanam_semula_input) {
            // Insert new Tanam Semula if created
            $stmt = $db->prepare("
                INSERT INTO tanam_semula (kebun_id, no_tanam_semula, tahun_tanam_semula, keluasan_diluluskan, bantuan_ansuran)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $kebun_id,
                $no_tanam_semula,
                $tahun_tanam_semula,
                $keluasan_diluluskan,
                $bantuan_ansuran
            ]);
        }

        $db->commit();

        $success = "Rekod kebun bagi No. Lot: " . htmlspecialchars($no_lot) . " telah berjaya dikemaskini!";

        // Refresh kebun data to show updated values in form
        $stmt = $db->prepare("
            SELECT 
                k.*,
                p.id AS pekebun_id, p.nama AS nama_pekebun, p.no_telefon, p.alamat,
                ts.id AS tanam_semula_id, ts.no_tanam_semula, ts.tahun_tanam_semula, ts.keluasan_diluluskan, ts.bantuan_ansuran
            FROM kebun k
            JOIN pekebun p ON k.pekebun_id = p.id
            LEFT JOIN tanam_semula ts ON k.id = ts.kebun_id
            WHERE k.id = ?
        ");
        $stmt->execute([$kebun_id]);
        $kebun = $stmt->fetch();

        $pelan_lot_blob = $kebun['pelan_lot'] ?? null;
        $has_pelan_lot = !empty($pelan_lot_blob);

        header("Refresh: 2; url=kebun-detail.php?id={$kebun_id}");

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kemaskini Kebun - <?= htmlspecialchars($kebun['no_lot']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1b4332;
            --accent-color: #2d6a4f;
            --bg-light: #f4f6f8;
            --card-border: #e9ecef;
        }
        body { 
            background-color: var(--bg-light); 
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; 
        }
        .navbar-custom { 
            background-color: var(--primary-color); 
        }
        .card-custom {
            border: 1px solid var(--card-border);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            background: #ffffff;
            padding: 1.5rem;
        }
        .form-label {
            font-size: 0.875rem;
            font-weight: 700;
            color: #2d3748;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        .form-control, .form-select {
            border-color: var(--card-border);
            border-radius: 8px;
            padding: 0.65rem 0.85rem;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(45, 106, 79, 0.15);
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a202c;
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 0.75rem;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }
        .tab-toggle {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .tab-toggle .btn {
            flex: 1;
            border-radius: 8px;
            font-weight: 600;
        }
        .tab-toggle .btn-outline-secondary:not(.active) {
            border-color: var(--card-border);
            color: #6c757d;
        }
        .tab-toggle .btn.active {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: #ffffff;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .info-box {
            background: #f8f9fa;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 0.85rem;
            font-size: 0.875rem;
            color: #6c757d;
        }
        .current-img-preview {
            max-width: 220px;
            max-height: 180px;
            object-fit: contain;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            background: #fafafa;
            padding: 4px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
            <img src="../assets/images/logo-risda.png" alt="Logo RISDA" style="height: 40px; width: auto; margin-right: 0.75rem;">
            Pentadbir RISDA
        </a>
        <div class="d-flex gap-2">
            <a href="kebun-detail.php?id=<?= $kebun['id'] ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-eye me-1"></i> Lihat Butiran
            </a>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>
</nav>

<div class="container px-4 mb-5" style="max-width: 900px;">

    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h3 class="fw-bold mb-1 text-dark">
                <i class="bi bi-pencil-square text-success me-2"></i> Kemaskini Rekod Kebun
            </h3>
            <p class="text-muted small mb-0">No. Lot: <strong><?= htmlspecialchars($kebun['no_lot']) ?></strong> | Pekebun: <strong><?= htmlspecialchars($kebun['nama_pekebun']) ?></strong></p>
        </div>
        <a href="kebun-detail.php?id=<?= $kebun['id'] ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Batal & Kembali
        </a>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($error) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div>
                <?= htmlspecialchars($success) ?><br>
                <small class="text-muted">Mengalihkan ke halaman butiran dalam masa 2 saat...</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="card card-custom">
        <form action="kebun-edit.php?id=<?= $kebun['id'] ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="kebun_id" value="<?= $kebun['id'] ?>">

            <!-- Pekebun Section -->
            <div class="section-header">
                <i class="bi bi-person-lines-fill text-success"></i>
                <span>Maklumat Pekebun</span>
            </div>

            <!-- Toggle Pekebun Mode -->
            <div class="tab-toggle">
                <button type="button" class="btn btn-outline-secondary active" onclick="switchPekebunMode('current')">
                    <i class="bi bi-pencil me-1"></i> Kemaskini Pekebun Semasa
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="switchPekebunMode('existing')">
                    <i class="bi bi-people me-1"></i> Pilih Pekebun Lain
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="switchPekebunMode('new_pekebun')">
                    <i class="bi bi-person-plus me-1"></i> Tambah Pekebun Baru
                </button>
            </div>

            <input type="hidden" id="pekebun_mode" name="pekebun_mode" value="current">

            <!-- Mode 1: Edit Current Pekebun -->
            <div id="tab-current" class="tab-content active">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nama_pekebun_current" class="form-label">Nama Pekebun *</label>
                        <input type="text" class="form-control" id="nama_pekebun_current" name="nama_pekebun_current" value="<?= htmlspecialchars($kebun['nama_pekebun']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="no_telefon_current" class="form-label">No. Telefon *</label>
                        <input type="tel" class="form-control" id="no_telefon_current" name="no_telefon_current" value="<?= htmlspecialchars($kebun['no_telefon']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="alamat_current" class="form-label">Alamat *</label>
                        <textarea class="form-control" id="alamat_current" name="alamat_current" rows="3" required><?= htmlspecialchars($kebun['alamat']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Mode 2: Choose Existing Pekebun -->
            <div id="tab-existing" class="tab-content">
                <div class="mb-3">
                    <label for="pekebun_id_existing" class="form-label">Pilih Pekebun Sedia Ada</label>
                    <select class="form-select" id="pekebun_id_existing" name="pekebun_id_existing">
                        <option value="">-- Sila Pilih Pekebun --</option>
                        <?php foreach ($pekebun_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $p['id'] == $kebun['pekebun_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nama']) ?> (<?= htmlspecialchars($p['no_telefon']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted d-block mt-1">Kebun ini akan dipindahkan ke bawah pengurusan pekebun yang dipilih.</small>
                </div>
            </div>

            <!-- Mode 3: Register New Pekebun -->
            <div id="tab-new_pekebun" class="tab-content">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="nama_pekebun_new" class="form-label">Nama Pekebun Baharu *</label>
                        <input type="text" class="form-control" id="nama_pekebun_new" name="nama_pekebun_new" placeholder="Nama pekebun baharu">
                    </div>
                    <div class="col-md-6">
                        <label for="no_telefon_new" class="form-label">No. Telefon *</label>
                        <input type="tel" class="form-control" id="no_telefon_new" name="no_telefon_new" placeholder="Contoh: 0123456789">
                    </div>
                    <div class="col-12">
                        <label for="alamat_new" class="form-label">Alamat *</label>
                        <textarea class="form-control" id="alamat_new" name="alamat_new" rows="3" placeholder="Alamat penuh pekebun baharu"></textarea>
                    </div>
                </div>
            </div>


            <!-- Kebun Section -->
            <div class="section-header mt-4">
                <i class="bi bi-geo-alt-fill text-success"></i>
                <span>Maklumat Kebun</span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="no_lot" class="form-label">No. Lot *</label>
                    <input type="text" class="form-control" id="no_lot" name="no_lot" value="<?= htmlspecialchars($kebun['no_lot']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="keluasan_kebun" class="form-label">Keluasan Kebun (Hektar) *</label>
                    <input type="number" class="form-control" id="keluasan_kebun" name="keluasan_kebun" step="0.01" value="<?= htmlspecialchars($kebun['keluasan_kebun']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="lokasi_kebun" class="form-label">Lokasi Kebun *</label>
                    <input type="text" class="form-control" id="lokasi_kebun" name="lokasi_kebun" value="<?= htmlspecialchars($kebun['lokasi_kebun']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="mukim" class="form-label">Mukim</label>
                    <input type="text" class="form-control" id="mukim" name="mukim" value="<?= htmlspecialchars($kebun['mukim']) ?>">
                </div>

                <div class="col-md-6">
                    <label for="daerah" class="form-label">Daerah *</label>
                    <input type="text" class="form-control" id="daerah" name="daerah" value="<?= htmlspecialchars($kebun['daerah']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="klon_getah" class="form-label">Klon Getah *</label>
                    <?php 
                        $klon_options = ['GT1', 'RRIM600', 'RRIM712', 'BPM24', 'Lain'];
                        $current_klon = $kebun['klon_getah'];
                    ?>
                    <select class="form-select" id="klon_getah" name="klon_getah" required>
                        <option value="">-- Pilih Klon --</option>
                        <?php foreach ($klon_options as $option): ?>
                            <option value="<?= $option ?>" <?= ($current_klon === $option) ? 'selected' : '' ?>>
                                <?= $option ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (!in_array($current_klon, $klon_options) && !empty($current_klon)): ?>
                            <option value="<?= htmlspecialchars($current_klon) ?>" selected><?= htmlspecialchars($current_klon) ?></option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="jumlah_pokok" class="form-label">Jumlah Pokok</label>
                    <input type="number" class="form-control" id="jumlah_pokok" name="jumlah_pokok" value="<?= htmlspecialchars($kebun['jumlah_pokok'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="tahun_tanam" class="form-label">Tahun Tanam</label>
                    <input type="number" class="form-control" id="tahun_tanam" name="tahun_tanam" value="<?= htmlspecialchars($kebun['tahun_tanam'] ?? '') ?>" min="1900" max="2100">
                </div>

                <div class="col-md-6">
                    <label for="tahun_sulaman" class="form-label">Tahun Sulaman</label>
                    <input type="number" class="form-control" id="tahun_sulaman" name="tahun_sulaman" value="<?= htmlspecialchars($kebun['tahun_sulaman'] ?? '') ?>" min="1900" max="2100">
                </div>
                <div class="col-md-6">
                    <label for="jarak_tanaman" class="form-label">Jarak Tanaman</label>
                    <input type="text" class="form-control" id="jarak_tanaman" name="jarak_tanaman" value="<?= htmlspecialchars($kebun['jarak_tanaman'] ?? '') ?>">
                </div>

                <div class="col-12">
                    <label for="koordinat" class="form-label">Koordinat (Latitude, Longitude)</label>
                    <input type="text" class="form-control" id="koordinat" name="koordinat" value="<?= htmlspecialchars($kebun['koordinat'] ?? '') ?>" placeholder="Contoh: 3.1357, 101.6880">
                </div>

                <div class="col-12">
                    <label for="pelan_lot_file" class="form-label">Pelan Lot (Muat Naik Fail Baharu / Tukar)</label>
                    
                    <?php if ($has_pelan_lot): ?>
                        <div class="mb-3 p-3 bg-light rounded border d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <img src="get-pelan-image.php?id=<?= $kebun['id'] ?>" class="current-img-preview" alt="Pelan Lot Semasa" onerror="this.style.display='none';">
                                <div>
                                    <span class="badge bg-success mb-1">Fail Sedia Ada</span>
                                    <div class="small text-muted">Pelan lot telah dimuat naik.</div>
                                    <a href="get-pelan-image.php?id=<?= $kebun['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Lihat Fail Penuh
                                    </a>
                                </div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remove_pelan_lot" name="remove_pelan_lot" value="1">
                                <label class="form-check-label text-danger fw-semibold" for="remove_pelan_lot">
                                    <i class="bi bi-trash me-1"></i> Padam Pelan Lot Semasa
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <input type="file" class="form-control" id="pelan_lot_file" name="pelan_lot_file" accept=".jpg,.jpeg,.png,.gif,.pdf" onchange="displayFileName(this)">
                    <small class="text-muted d-block mt-1">
                        <i class="bi bi-info-circle me-1"></i>
                        Format dibenarkan: JPG, PNG, GIF, PDF | Saiz maksimum: 5MB. Biarkan kosong jika tidak mahu menukar fail semasa.
                    </small>
                    <div id="file-preview" class="mt-2"></div>
                </div>
            </div>

            <!-- Tanam Semula Section -->
            <div class="section-header mt-4">
                <i class="bi bi-file-text-fill text-success"></i>
                <span>Maklumat Tanam Semula</span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="no_tanam_semula" class="form-label">No. Tanam Semula</label>
                    <input type="text" class="form-control" id="no_tanam_semula" name="no_tanam_semula" value="<?= htmlspecialchars($kebun['no_tanam_semula'] ?? '') ?>" placeholder="Contoh: TS001">
                </div>
                <div class="col-md-6">
                    <label for="tahun_tanam_semula" class="form-label">Tahun Tanam Semula</label>
                    <input type="number" class="form-control" id="tahun_tanam_semula" name="tahun_tanam_semula" value="<?= htmlspecialchars($kebun['tahun_tanam_semula'] ?? '') ?>" min="1900" max="2100">
                </div>

                <div class="col-md-6">
                    <label for="keluasan_diluluskan" class="form-label">Keluasan Diluluskan (Hektar)</label>
                    <input type="number" class="form-control" id="keluasan_diluluskan" name="keluasan_diluluskan" step="0.01" value="<?= htmlspecialchars($kebun['keluasan_diluluskan'] ?? '') ?>" placeholder="Contoh: 1.5">
                </div>
                <div class="col-md-6">
                    <label for="bantuan_ansuran" class="form-label">Status Bantuan Ansuran</label>
                    <?php 
                        $ansuran_options = [
                            'Lulus - Ansuran 1' => 'Diluluskan - Ansuran 1',
                            'Lulus - Ansuran 2' => 'Diluluskan - Ansuran 2',
                            'Lulus - Ansuran 3' => 'Diluluskan - Ansuran 3',
                            'Lulus - Ansuran 4' => 'Diluluskan - Ansuran 4',
                            'Belum Memohon'     => 'Belum Memohon',
                            'Ditolak'           => 'Ditolak'
                        ];
                        $current_ansuran = $kebun['bantuan_ansuran'] ?? '';
                    ?>
                    <select class="form-select" id="bantuan_ansuran" name="bantuan_ansuran">
                        <option value="">Pilih Status Ansuran</option>
                        <?php foreach ($ansuran_options as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($current_ansuran === $val) ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (!array_key_exists($current_ansuran, $ansuran_options) && !empty($current_ansuran)): ?>
                            <option value="<?= htmlspecialchars($current_ansuran) ?>" selected><?= htmlspecialchars($current_ansuran) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top">
                <a href="kebun-detail.php?id=<?= $kebun['id'] ?>" class="btn btn-outline-secondary rounded-3 px-4">
                    <i class="bi bi-x-circle me-1"></i> Batal
                </a>
                <button type="submit" class="btn btn-success rounded-3 px-4" style="background-color: var(--accent-color);">
                    <i class="bi bi-check-circle me-1"></i> Simpan Perubahan
                </button>
            </div>

        </form>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchPekebunMode(mode) {
    // Toggle tab buttons
    document.querySelectorAll('.tab-toggle .btn').forEach(btn => btn.classList.remove('active'));
    event.target.closest('.btn').classList.add('active');
    
    // Toggle content panels
    document.getElementById('tab-current').classList.remove('active');
    document.getElementById('tab-existing').classList.remove('active');
    document.getElementById('tab-new_pekebun').classList.remove('active');

    // Reset required attributes
    document.getElementById('nama_pekebun_current').removeAttribute('required');
    document.getElementById('no_telefon_current').removeAttribute('required');
    document.getElementById('alamat_current').removeAttribute('required');
    document.getElementById('pekebun_id_existing').removeAttribute('required');
    document.getElementById('nama_pekebun_new').removeAttribute('required');
    document.getElementById('no_telefon_new').removeAttribute('required');
    document.getElementById('alamat_new').removeAttribute('required');
    
    if (mode === 'current') {
        document.getElementById('tab-current').classList.add('active');
        document.getElementById('nama_pekebun_current').setAttribute('required', 'required');
        document.getElementById('no_telefon_current').setAttribute('required', 'required');
        document.getElementById('alamat_current').setAttribute('required', 'required');
    } else if (mode === 'existing') {
        document.getElementById('tab-existing').classList.add('active');
        document.getElementById('pekebun_id_existing').setAttribute('required', 'required');
    } else if (mode === 'new_pekebun') {
        document.getElementById('tab-new_pekebun').classList.add('active');
        document.getElementById('nama_pekebun_new').setAttribute('required', 'required');
        document.getElementById('no_telefon_new').setAttribute('required', 'required');
        document.getElementById('alamat_new').setAttribute('required', 'required');
    }
    
    document.getElementById('pekebun_mode').value = mode;
}

function displayFileName(input) {
    const preview = document.getElementById('file-preview');
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        const fileType = file.type;
        
        let previewHTML = `
            <div class="alert alert-info py-2 small d-flex align-items-center">
                <i class="bi bi-file-earmark-check me-2"></i>
                <div>
                    <strong>Fail Baharu Dipilih: ${fileName}</strong><br>
                    Saiz: ${fileSize} MB
                </div>
            </div>
        `;
        
        if (fileType.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.maxWidth = '200px';
                img.style.maxHeight = '200px';
                img.style.borderRadius = '8px';
                img.style.marginTop = '0.5rem';
                img.style.border = '1px solid #e9ecef';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        }
        
        preview.innerHTML += previewHTML;
    }
}
</script>
</body>
</html>
